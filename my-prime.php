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

// $client->setScopes('https://www.googleapis.com/auth/youtube.readonly');
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Define service object for making API requests.
$service = new Google_Service_YouTube($client);

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-' . $client->prepareScopes();
$htmlBody = '';
$table = '';

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

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
	try {
		// _updateSubscriptions($service, $pdo, $htmlBody);
		// _listSubscriptions($service, $pdo, $table);
		// _updateVideos($service, $pdo, $htmlBody);
		// _updateVideosDetails($service, $pdo, $htmlBody);
		_listVideos($service, $pdo, $htmlBody);
	} catch (Google_Service_Exception $e) {
		$htmlBody .= sprintf('<p>A Google_Service_Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
		
		if (in_array($e->getCode(), [401])) {
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

  header('Location: ' . $authUrl);
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
		
		$rspMySubscriptions = $service->subscriptions->listSubscriptions('snippet', $paramMySubscriptions);
		$pageToken = $rspMySubscriptions->nextPageToken;
		
		foreach ($rspMySubscriptions->items as $subscription) {
			$arrTmpSubscriptions[$subscription->snippet->resourceId->channelId]['title'] = $subscription->snippet->title;
		}
	
		$strSubs = implode(",", array_keys($arrTmpSubscriptions));

		$paramChannels = [
		   'id' => $strSubs,
		   'fields' => 'items.id,items.contentDetails.relatedPlaylists.uploads',
		];

		$rspChannels = $service->channels->listChannels('contentDetails', $paramChannels);
		
		foreach ($rspChannels->items as $channel) {
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
	$resChannels = $stmt->fetchAll();
	
    $arrTable = [];
	
    foreach ($resChannels as $row) {
		$arrTable[] = <<<END
<tr>
	<td><input type='checkbox'></td>
	<td><a href='https://www.youtube.com/channel/{$row['id']}' target='_blank'>{$row['name']}</a></td>
	<td><a href='https://www.youtube.com/playlist?list={$row['playlist_id']}' target='_blank'>{$row['name']}</a></td>
	<td>{$row['date_last_upload']}</td>
	<td>{$row['date_checked']}</td>
</tr>
END;
    }
	
	$tableBody = implode('', $arrTable);
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
	$now = date_create();
	
	$sql = "SELECT playlist_id FROM channels";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resChannels = $stmt->fetchAll();
	
	$sql = "SELECT * FROM (SELECT playlist_id, date_checked FROM videos ORDER BY date_checked DESC) GROUP BY playlist_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resVideos = $stmt->fetchAll();
	
	$nbChannels = 0;
    $arrVideos = [];
	/*$arrFilter = [
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
	$strFilter = '/('.implode('|', $arrFilter).')/i';*/
	
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
	
    foreach ($resVideos as $row) {
        $arrStoredVideos[$row['playlist_id']] = $row['date_checked'];
	}
	
    foreach ($resChannels as $row) {
	if (isset($arrStoredVideos[$row['playlist_id']])) {
			$interval = date_diff(date_create($arrStoredVideos[$row['playlist_id']]), $now);
			$dateDiff = $interval->format('%a') > 50 ? 50 : $interval->format('%a') + 1;
		} else {
			$dateDiff = 50;
		}
		
        $queryParams = [
            'playlistId' => $row['playlist_id'],
            'maxResults' => $dateDiff
        ];

        $rspPlaylistItems = $service->playlistItems->listPlaylistItems('snippet', $queryParams);
		
        foreach ($rspPlaylistItems['items'] as $video) {
            // if (preg_match($strFilter, $video->snippet->title) !== 1) {
                $arrVideos[$video->snippet->resourceId->videoId] = [
                    'channelTitle' => $video->snippet->channelTitle,
                    'title' => addslashes($video->snippet->title),
                    'videoId' => $video->snippet->resourceId->videoId,
                    'playlistId' => $row['playlist_id'],
                    'publishedAt' => $video->snippet->publishedAt,
                    // 'publishedMonth' => substr($video->snippet->publishedAt, 0, 7),
                    // 'isRated' => 0,
                    // 'isPlaylist' => 0,
                    // 'duration' => '',
                ];
            // }
        }
		
		$nbChannels++;
		
		if ($nbChannels == 50) {
			break;
		}
	}
	
	if (count($arrVideos) !== 0) {
		$sql = "INSERT OR IGNORE INTO videos (id, playlist_id, title, date_published, date_checked) VALUES";
		
		foreach ($arrVideos as $arrSqlVideosId => $strSqlVideos) {
			$title = str_replace('"', '""', $strSqlVideos['title']);
			$sql .= "(\"$arrSqlVideosId\", \"{$strSqlVideos['playlistId']}\", \"{$title}\", \"{$strSqlVideos['publishedAt']}\", \"{$now->format('Y-m-d\TH:i:s\Z')}\"),";
		}
		
		$sql = substr($sql, 0, -1).';';
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		
		$htmlBody .= 'Added videos: ' . count($arrVideos);
	} else {
		$htmlBody .= 'Videos up to date.';
	}
}

function _updateVideosDetails($service, $pdo, &$htmlBody) {
	// Check if I have rated.
	$sql = "SELECT id FROM videos WHERE my_playlist_id IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resVideos = $stmt->fetchAll();
	
    $arrVideos = [];
	$arrVideoIds = [];
    $strVideos = '';
    $i = 0;

    foreach ($resVideos as $row) {
        $strVideos .= $row['id'] . ',';
        $i++;

        if ($i == 49) {
            $arrVideoIds[] = $strVideos;
            $strVideos = '';
            $i = 0;
        }
    }

    $arrVideoIds[] = $strVideos;

    foreach ($arrVideoIds as $strVideos) {
        $ratings = $service->videos->getRating($strVideos);

        foreach ($ratings->items as $rating) {
            if ($rating->rating == 'none') {
                $arrVideos[$rating->videoId]['my_playlist_id'] = 0;
            } elseif ($rating->rating == 'like') {
                $arrVideos[$rating->videoId]['my_playlist_id'] = 1;
            } elseif ($rating->rating == 'dislike') {
                $arrVideos[$rating->videoId]['my_playlist_id'] = -1;
            }
        }
    }
	
	if (count($arrVideos) !== 0) {
		foreach ($arrVideos as $strVideoId => $strPlaylistId) {
			$sql = "UPDATE videos SET my_playlist_id=\"{$strPlaylistId['my_playlist_id']}\" WHERE id=\"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}
		
		$htmlBody .= 'Upated videos ratings: ' . count($arrVideos) . '<br>';
	} else {
		$htmlBody .= 'Videos ratings up to date.<br>';
	}
	
	
	
	// Check if in one of my playlists.
	/*
	$sql = "SELECT id FROM videos WHERE my_playlist_id = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resVideos = $stmt->fetchAll();
	
	$arrVideoIds = [];
    $strVideos = '';
    $i = 0;

    foreach ($resVideos as $row) {
        $strVideos .= $row['id'] . ',';
        $i++;

        if ($i == 49) {
            $arrVideoIds[] = $strVideos;
            $strVideos = '';
            $i = 0;
        }
    }

    $arrVideoIds[] = $strVideos;
	
	
    foreach ($arrVideoIds as $strVideos) {
        $queryParams = [
            'id' => $strVideos
        ];

        $rspVideos = $service->videos->listVideos('contentDetails', $queryParams);
		
        foreach ($rspVideos->items as $video) {
            $arrVideos[$video->id]['duration'] = covtime($video->contentDetails->duration);
        }
    }*/
	$arrVideos = [];
    $arrMyPlaylists = [
        'prime' => 'PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4',
        'zera' => 'PL52YqI0PbEYX18AmDYyv1lx28lIzwYgTb',
        'lennon' => 'PL52YqI0PbEYXMGfA71veOZCM_4NAWVopj',
        'zap' => 'PL52YqI0PbEYV_g-5wdATX6GV7QPXvPadH',
        'long' => 'PL52YqI0PbEYXZv2APe7B3wPkgdVXi1yK7',
        // 'japon short' => 'PL52YqI0PbEYWYGVaDb7WaotZw1-DqeIbt',
        'japon' => 'PL52YqI0PbEYXzJuYKJFyCCu1lREuoMhnO',
        'podcast' => 'PL52YqI0PbEYUxtItqu6WAIqj1C7hX4LUL',
    ];

    $strMyPlaylists = implode(',', $arrMyPlaylists);
    $arrMyPlaylistItems = [];

    foreach ($arrMyPlaylists as $myPlaylistId) {
        $pageToken = "";

        while (!is_null($pageToken)) {
            $queryParams = [
                'maxResults' => 50,
                'pageToken' => $pageToken,
                'playlistId' => $myPlaylistId
            ];
            
            $myPlaylistItems = $service->playlistItems->listPlaylistItems('contentDetails', $queryParams);
            $pageToken = $myPlaylistItems->nextPageToken;
			
			foreach ($myPlaylistItems as $myPlaylistItemDetails) {
				$arrVideos[$myPlaylistItemDetails->contentDetails->videoId]['my_playlist_id'] = $myPlaylistId;
			}
        }
    }
	
	if (count($arrVideos) !== 0) {
		foreach ($arrVideos as $strVideoId => $strPlaylistId) {
			$sql = "INSERT INTO videos (id, my_playlist_id) VALUES (\"{$strVideoId}\", \"{$strPlaylistId['my_playlist_id']}\")";
			$sql .= " ON CONFLICT(id) DO UPDATE SET my_playlist_id=\"{$strPlaylistId['my_playlist_id']}\" WHERE id=\"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}
		
		$htmlBody .= 'Upated videos playlists: ' . count($arrVideos) . '<br>';
	} else {
		$htmlBody .= 'Videos playlists up to date.<br>';
	}
}

function _listVideos($service, $pdo, &$table) {
	$sql = <<<END
		SELECT videos.*
		, playlists.name AS my_playlist_name 
		, channels.name AS channel_name, channels.id AS channel_id
		FROM videos 
		LEFT JOIN playlists ON videos.my_playlist_id=playlists.id
		LEFT JOIN channels ON videos.playlist_id=channels.playlist_id
		LIMIT 100
END;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resChannels = $stmt->fetchAll();
	
    $arrTable = [];
	
    foreach ($resChannels as $row) {
		$arrTable[] = <<<END
<tr>
	<td><input type='checkbox'></td>
	<td><a href='https://www.youtube.com/channel/{$row['channel_id']}' target='_blank'>{$row['channel_name']}</a></td>
	<td><a href='https://www.youtube.com/watch?v={$row['id']}' target='_blank'>{$row['title']}</a></td>
	<td>{$row['date_published']}</td>
	<td>{$row['date_checked']}</td>
	<td><a href='https://www.youtube.com/playlist?list={$row['my_playlist_id']}' target='_blank'>{$row['my_playlist_name']}</a></td>
</tr>
END;
    }
	
	$tableBody = implode('', $arrTable);
	$table = <<<END
<table id="groups">
	<thead>
		<tr>
			<th class="group-word"></th>
			<th class="group-letter-1">Channel</th>
			<th class="group-letter-1">Video</th>
			<th class="group-date-month">Published</th>
			<th class="group-date-month">Checked</th>
			<th class="group-date-month">My playlist</th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th class="group-word"></th>
			<th class="group-letter-1">Channel</th>
			<th class="group-letter-1">Video</th>
			<th class="group-date-month">Published</th>
			<th class="group-date-month">Checked</th>
			<th class="group-date-month">My playlist</th>
		</tr>
	</tfoot>
	<tbody>
		$tableBody
	</tbody>
</table>
END;
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
