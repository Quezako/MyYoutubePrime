<?php
/**
TODO:
- _updateSubscriptions: not delete. select, then update or create.
**/

error_reporting(E_ALL);
ini_set("display_errors", 1);

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);

// $client->setScopes('https://www.googleapis.com/auth/youtube');
$client->setScopes('https://www.googleapis.com/auth/youtube.readonly');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Define service object for making API requests.
$service = new Google_Service_YouTube($client);

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-' . $client->prepareScopes();

if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION[$tokenSessionKey] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION[$tokenSessionKey])) {
  $client->setAccessToken($_SESSION[$tokenSessionKey]);
}

// SQLite.
try {
    $pdo = new PDO('sqlite:' . dirname(__FILE__) . '/my-prime.db');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
} catch (Exception $e) {
    echo "Can't access SQLite DB: " . $e->getMessage();
    die();
}

$htmlBody = '';
$tableBody = '';
$table = '';

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
	try {
		// _updateSubscriptions($service, $pdo, $htmlBody);
		// _listSubscriptions($service, $pdo, $table);
		_updateVideos($service, $pdo, $htmlBody);
	} catch (Google_Service_Exception $e) {
		$htmlBody .= sprintf('<p>A Google_Service_Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
		
		if ($e->getCode() == 401) {
			_showAuth($client, $htmlBody);
		}
	} catch (Google_Exception $e) {
		$htmlBody .= sprintf('<p>An Google_Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
		// _showAuth($client, $htmlBody);
	} catch (Exception $e) {
		$htmlBody .= sprintf('<p>An Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
		// _showAuth($client, $htmlBody);
	}
  
	$_SESSION[$tokenSessionKey] = $client->getAccessToken();
} elseif ($OAUTH2_CLIENT_ID == 'REPLACE_ME') {
	$htmlBody .= <<<END
	<h3>Client Credentials Required</h3>
	<p>
		You need to set <code>\$OAUTH2_CLIENT_ID</code> and
		<code>\$OAUTH2_CLIENT_ID</code> before proceeding.
	<p>
END;
} else {
	_showAuth($client, $htmlBody);
}

function _showAuth($client, &$htmlBody) {
	// If the user hasn't authorized the app, initiate the OAuth flow
	$state = mt_rand();
	$client->setState($state);
	$_SESSION['state'] = $state;

	$authUrl = $client->createAuthUrl();
	$htmlBody .= <<<END
	<h3>Authorization Required</h3>
	<p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;
}

function _updateSubscriptions($service, $pdo, &$htmlBody) {
	$pageToken = '';
    $arrMySubscriptions = [];
	
	while (!is_null($pageToken)) {
		$arrTmpSubscriptions = [];
		$paramMySubscriptions = [
			'maxResults' => 50,
			'pageToken' => $pageToken,
			'mine' => true,
			'fields' => 'items.snippet.title,items.snippet.resourceId.channelId,nextPageToken'
		];
		
		$resMySubscriptions = $service->subscriptions->listSubscriptions('snippet', $paramMySubscriptions);
		$pageToken = $resMySubscriptions->nextPageToken;
		
		foreach ($resMySubscriptions->items as $subscription) {
			$arrTmpSubscriptions[$subscription->snippet->resourceId->channelId]['title'] = $subscription->snippet->title;
		}
	
		$strSubs = implode(",", array_keys($arrTmpSubscriptions));

		$paramChannels = [
		   'id' => $strSubs,
		   'fields' => 'items.id,items.contentDetails.relatedPlaylists.uploads',
		];

		$resChannels = $service->channels->listChannels('contentDetails', $paramChannels);
		
		foreach ($resChannels->items as $channel) {
			$arrTmpSubscriptions[$channel->id]['uploads'] = $channel->contentDetails->relatedPlaylists->uploads;
		}
		
		$arrMySubscriptions += $arrTmpSubscriptions;
		// break;
	}
    
	$sql = "DELETE FROM channels";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	
	$sql = "INSERT INTO channels (id, name, playlist_id) VALUES";
	
	foreach ($arrMySubscriptions as $mySubscriptionId => $mySubscription) {
		$sql .= "(\"$mySubscriptionId\", \"{$mySubscription['title']}\", \"{$mySubscription['uploads']}\"),";
	}
	
	$sql = substr($sql, 0, -1).';';
	echo $sql;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	
	$htmlBody .= print_r($arrMySubscriptions, true);
}

function _listSubscriptions($service, $pdo, &$table) {
	$sql = "SELECT * FROM channels";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$result = $stmt->fetchAll();
	
    $rows = array();
	
    foreach ($result as $row) {
        // $rows[] = "<tr><td><input type='checkbox' checked=''></td><td><a href='https://www.youtube.com/channel/{$row['id']}' target='_blank'>{$row['name']}</a></td></tr>";
		$rows[] = <<<END
<tr>
	<td><input type='checkbox'></td>
	<td><a href='https://www.youtube.com/channel/{$row['id']}' target='_blank'>{$row['name']}</a></td>
	<td><a href='https://www.youtube.com/playlist?list={$row['playlist_id']}' target='_blank'>{$row['name']}</a></td>
	<td>{$row['date_last_upload']}</td>
	<td>{$row['date_checked']}</td>
</tr>
END;
    }
	
	$tableBody = implode('', $rows);
	$table = <<<END
<table id="groups">
	<thead>
		<tr>
			<th class="group-word"></th> <!-- checkbox status -->
			<th class="group-letter-1">Channel</th>
			<th class="group-letter-1">Uploads</th>
			<th class="group-date-month">Last Upload</th>
			<th class="group-date-month">Checked</th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th class="group-word"></th> <!-- checkbox status -->
			<th class="group-letter-1">Channel</th>
			<th class="group-letter-1">Uploads</th>
			<th class="group-date-month">Last Upload</th>
			<th class="group-date-month">Checked</th>
		</tr>
	</tfoot>
	<tbody>
		$tableBody
	</tbody>
</table>
END;
}

function _updateVideos($service, $pdo, &$htmlBody) {
	$sql = "SELECT playlist_id FROM channels";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$result = $stmt->fetchAll();
	
	// SELECT * FROM (SELECT * FROM videos ORDER BY date_published DESC) GROUP BY playlist_id
	$sql = "SELECT id FROM videos";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$result2 = $stmt->fetchAll();
	
    $rows = array();
	
	$nbChannels = 0;
    $arrVideos = [];
	$arrFilter = [
		"clip officiel",
		"making of",
		"en live",
		"\- live",
		"bazar du grenier \- critique",
		"ermite réagit",
		"détatouage laser",
		"détatouage laser", // autre écriture
		"flander's company",
		"l'épopée temporelle",
		"wtfake academy",
		"sans filtre #",
		"parole de deporte",
	];
	$strFilter = '/('.implode('|', $arrFilter).')/i';
	
	/*
	Live 
	en live 

	best of:
	zerator
	moman
	gius
	mistermv 
	*/
	
	$arrStoredVideos = [];
	
    foreach ($result2 as $row) {
        $arrStoredVideos[] = $row['id'];
	}
	
    foreach ($result as $row) {
        $queryParams = [
            'playlistId' => $row['playlist_id'],
            'maxResults' => '50'
        ];

        $reslistPlaylistItems = $service->playlistItems->listPlaylistItems('snippet', $queryParams);
		
        foreach ($reslistPlaylistItems['items'] as $video) {
            if (preg_match($strFilter, $video->snippet->title) !== 1 && !in_array($video->snippet->resourceId->videoId, $arrStoredVideos)) {
                $arrVideos[$video->snippet->resourceId->videoId] = [
                    'channelTitle' => $video->snippet->channelTitle,
                    'title' => addslashes($video->snippet->title),
                    'videoId' => $video->snippet->resourceId->videoId,
                    'playlistId' => $row['playlist_id'],
                    'publishedAt' => $video->snippet->publishedAt,
                    'publishedMonth' => substr($video->snippet->publishedAt, 0, 7),
                    'isRated' => 0,
                    'isPlaylist' => 0,
                    'duration' => '',
                ];
            }
        }
		
		$nbChannels++;
		
		if ($nbChannels == 50) {
			break;
		}
		
		break;
	}
	
	if (count($arrVideos) !== 0) {
		$htmlBody .= print_r($arrVideos, true);
	
		$sql = "INSERT INTO videos (id, playlist_id, title, date_published, date_checked) VALUES";
		$arrSqlVideos = [];
		$now = date('Y-m-d');
		
		foreach ($arrVideos as $arrSqlVideosId => $strSqlVideos) {
			$title = str_replace('"', '""', $strSqlVideos['title']);
			$sql .= "(\"$arrSqlVideosId\", \"{$strSqlVideos['playlistId']}\", \"{$title}\", \"{$strSqlVideos['publishedAt']}\", \"$now\"),";
		}
		
		$sql = substr($sql, 0, -1).';';
		echo $sql;
		// die;
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		
		$htmlBody .= print_r($arrSqlVideos, true);
	} else {
		$htmlBody .= 'Videos up to date.';
	}
	
}
?>

<!doctype html>
<html>
<head>
<title>My Prime</title>
</head>
<body>
	<!-- Tablesorter: required -->
	<link href="css/theme.blue.css" rel="stylesheet">
	<script src="js/jquery-latest.min.js"></script>
	<script src="js/jquery.tablesorter.js"></script>
	<script src="js/widgets/widget-filter.min.js"></script>
	<script src="js/widgets/widget-storage.js"></script>

	<!-- Grouping widget -->
	<script src="js/parsers/parser-input-select.min.js"></script>
	<script src="js/parsers/parser-date-weekday.min.js"></script>
	<script src="js/widgets/widget-grouping.min.js"></script>
	
	<link href="css/my-prime.css" rel="stylesheet">
	<script src="js/my-prime.js"></script>
	<pre>
		<?=$htmlBody?>
	</pre>

	<?=$table?>

</body>
</html>
