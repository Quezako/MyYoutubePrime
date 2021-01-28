<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('max_execution_time', 600); //300 seconds = 5 minutes
set_time_limit(600);

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);

$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]", FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

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
		if (isset($_GET['action'])) {
			switch ($_GET['action']) {
				case '_updateSubscriptions':
					_updateSubscriptions($service, $pdo, $htmlBody);
					break;
				case '_listSubscriptions':
					_listSubscriptions($service, $pdo, $table);
					break;
				case '_updateVideos':
					_updateVideos($service, $pdo, $htmlBody);
					break;
				case '_updateVideosDetails':
					_updateVideosDetails($service, $pdo, $htmlBody);
					break;
				case '_ajaxUpdate':
					_ajaxUpdate($service, $pdo, $htmlBody);
					break;
				case '_listVideos':
					_listVideos($service, $pdo, $table);
					break;
			}
		}
	} catch (Google_Service_Exception $e) {
		$htmlBody .= sprintf('<p>A Google_Service_Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
		
		if (in_array($e->getCode(), [401])) {
			_showAuth($client, $htmlBody);
		}
	} catch (Google_Exception $e) {
		$htmlBody .= sprintf('<p>An Google_Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
	} catch (Exception $e) {
		$htmlBody .= sprintf('<p>An Exception error occurred: <code>%s</code></p>',
		($e->getMessage()));
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

function _covtime($youtube_time) {    
    if ($youtube_time) {
        $start = new DateTime('@0'); // Unix epoch
        $start->add(new DateInterval($youtube_time));
        $youtube_time = round($start->getTimestamp() / 60, 0);
    }
    
    return $youtube_time;
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
	}
	
	$sql = "INSERT OR IGNORE INTO channels (id, name, playlist_id) VALUES";
	
	foreach ($arrMySubscriptions as $mySubscriptionId => $mySubscription) {
		$sql .= "(\"$mySubscriptionId\", \"{$mySubscription['title']}\", \"{$mySubscription['uploads']}\"),";
	}
	
	$sql = substr($sql, 0, -1).';';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	
	$htmlBody .= 'Upated subscriptions: ' . count($arrMySubscriptions) . '<br>';
}

function _listSubscriptions($service, $pdo, &$table) {
	$sql = "SELECT * FROM channels ORDER BY sort ASC LIMIT 200";
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
	<td>{$row['status']}</td>
	<td>{$row['sort']}</td>
</tr>
END;
    }
	
	$tableBody = implode('', $arrTable);
	$table = <<<END
<table id="groups">
	<thead>
		<tr>
			<th class="group-word"><input type='checkbox' id='checkAll' /> Action</th>
			<th class="group-letter-1">Channel</th>
			<th class="group-letter-1">Uploads</th>
			<th class="group-date-month">Last Upload</th>
			<th class="group-date-month">Checked</th>
			<th class="group-date-month">Status</th>
			<th class="group-date-month">Sort</th>
		</tr>
	</thead>
	<tbody>
		$tableBody
	</tbody>
</table>
END;
}

function _updateVideos($service, $pdo, &$htmlBody) {
	$now = date_create();
	
	$sql = "SELECT playlist_id FROM channels WHERE status=1;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resChannels = $stmt->fetchAll();
	
	$sql = "SELECT * FROM (SELECT playlist_id, date_checked FROM videos ORDER BY date_checked DESC) GROUP BY playlist_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resVideos = $stmt->fetchAll();
	
    $arrVideos = [];
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
			$arrVideos[$video->snippet->resourceId->videoId] = [
				'channelTitle' => $video->snippet->channelTitle,
				'title' => $video->snippet->title,
				'videoId' => $video->snippet->resourceId->videoId,
				'playlistId' => $row['playlist_id'],
				'publishedAt' => $video->snippet->publishedAt,
			];
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
	// Check if user has rated the videos.
    $arrVideos = [];
	$arrVideoIds = [];
    $strVideos = '';
    $i = 0;
	
	$sql = "SELECT id FROM videos WHERE my_playlist_id = 0;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resVideos = $stmt->fetchAll();
	
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
        $rspRatings = $service->videos->getRating($strVideos);

        foreach ($rspRatings->items as $rating) {
            if ($rating->rating == 'none') {
                $arrVideos[$rating->videoId]['my_playlist_id'] = 0;
            } elseif ($rating->rating == 'like') {
                $arrVideos[$rating->videoId]['my_playlist_id'] = 1;
            } elseif ($rating->rating == 'dislike') {
                $arrVideos[$rating->videoId]['my_playlist_id'] = -1;
            }
        }
    }
	
	// Check if in one of user's playlists.
    $arrVideos2 = [];
	$arrVideoIds = [];
    $strVideos = '';
    $i = 0;
	
	$sql = "SELECT id FROM videos WHERE duration IS NULL LIMIT 1000;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resVideos = $stmt->fetchAll();
	
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
            $arrVideos2[$video->id]['duration'] = _covtime($video->contentDetails->duration);
        }
    }
	
	if (count($arrVideos) !== 0) {
		foreach ($arrVideos as $strVideoId => $arrVideo) {
			$sql = "UPDATE videos SET my_playlist_id = \"{$arrVideo['my_playlist_id']}\" WHERE id = \"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}
		
		$htmlBody .= 'Upated videos ratings: ' . count($arrVideos) . '<br>';
	} else {
		$htmlBody .= 'Videos ratings up to date.<br>';
	}
	
	if (count($arrVideos2) !== 0) {
		foreach ($arrVideos2 as $strVideoId => $arrVideo) {
			$sql = "UPDATE videos SET duration = \"{$arrVideo['duration']}\" WHERE id = \"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}
		
		$htmlBody .= 'Upated videos duration: ' . count($arrVideos2) . '<br>';
	} else {
		$htmlBody .= 'Videos duration up to date.<br>';
	}
	
	// Playlists
	$arrVideos = [];
	
    $arrMyPlaylists = [
        'prime' => 'PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4',
        'zera' => 'PL52YqI0PbEYX18AmDYyv1lx28lIzwYgTb',
        'lennon' => 'PL52YqI0PbEYXMGfA71veOZCM_4NAWVopj',
        'zap' => 'PL52YqI0PbEYV_g-5wdATX6GV7QPXvPadH',
        'long' => 'PL52YqI0PbEYXZv2APe7B3wPkgdVXi1yK7',
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
SELECT videos.*, 
SUBSTR(videos.date_published, 0, 8) AS video_date_pub, SUBSTR(videos.date_checked, 0, 11) AS video_date_chk,
playlists.name AS my_playlist_name,
channels.name AS channel_name, channels.id AS channel_id, channels.sort AS channel_sort
FROM videos 
LEFT JOIN playlists ON videos.my_playlist_id = playlists.id
INNER JOIN channels ON videos.playlist_id = channels.playlist_id
WHERE videos.my_playlist_id = 0 AND channels.status > 0 AND (videos.status <> -2 OR videos.status IS NULL)
ORDER BY SUBSTR(videos.date_published, 0, 8) DESC, channel_sort, CAST(duration AS INT) ASC, channel_sort ASC
LIMIT 1000
END;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$resChannels = $stmt->fetchAll();
	
    $arrTable = [];
	
    foreach ($resChannels as $row) {
		if ($row['my_playlist_id'] == "0") {
			$status = "To sort";
		} elseif ($row['my_playlist_id'] == "1") {
			$status = "Liked";
		} elseif ($row['my_playlist_id'] == "-1") {
			$status = "Disliked";
		} elseif ($row['my_playlist_id'] == "2") {
			$status = "Ignored";
		} else {
			$status = "<a href='https://www.youtube.com/playlist?list={$row['my_playlist_id']}' target='_blank'>{$row['my_playlist_name']}</a>";
		}
		
		$arrTable[] = <<<END
<tr>
	<td><input type='checkbox' /></td>
	<td><a href='https://www.youtube.com/channel/{$row['channel_id']}' target='_blank'>{$row['channel_name']}</a></td>
	<td><a href='https://www.youtube.com/watch?v={$row['id']}' target='_blank'>{$row['title']}</a></td>
	<td>{$row['duration']}</td>
	<td>{$row['channel_sort']}</td>
	<td>{$row['date_published']}</td>
	<td>{$row['video_date_pub']}</td>
	<td>$status</td>
</tr>
END;
    }
	
	$tableBody = implode('', $arrTable);
	$table = <<<END
<table id="groups">
	<thead>
		<tr>
			<th class="group-word">Action</th>
			<th class="group-letter-1">Channel</th>
			<th class="group-letter-1">Video</th>
			<th class="group-Number-1">Duration</th>
			<th class="group-Number-1">Priority</th>
			<th class="group-date-month">Published</th>
			<th class="group-date-month">Pub Month</th>
			<th class="group-word">Status</th>
		</tr>
	</thead>
	<tbody>
		$tableBody
	</tbody>
</table>
END;
}

function _ajaxUpdate($service, $pdo, &$htmlBody) {
	if (!isset($_POST['data'])) {
		echo 'No POST data.';
	} else {
		$strStatus = $_POST['data'][1] == 'btnIgnore' ? -2 : 1;
		$strTable = $_POST['data'][0] == '_listVideos' ? 'videos' : 'channels';
		
		// Define the $playlistItem object, which will be uploaded as the request body.
		$playlistItem = new Google_Service_YouTube_PlaylistItem();

		// Add 'snippet' object to the $playlistItem object.
		$playlistItemSnippet = new Google_Service_YouTube_PlaylistItemSnippet();
		$playlistItemSnippet->setPlaylistId('PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4');
		// $playlistItemSnippet->setPosition(0);
		$resourceId = new Google_Service_YouTube_ResourceId();
		$resourceId->setKind('youtube#video');
		$arrVideoId = [];
		
		foreach($_POST['data'][2] as $key => $isChecked) {
			if ($_POST['data'][1] == 'btnSort') {
				$strSort = $key + 1;
				$sql = "UPDATE $strTable SET sort=\"{$strSort}\" WHERE id = \"{$_POST['data'][3][$key]}\";";
				// var_dump($sql);
				$stmt = $pdo->prepare($sql);
				$stmt->execute();
			} elseif ($isChecked == 1) {
				if ($_POST['data'][1] == 'btnPlaylist') {
					$arrVideoId[] = $_POST['data'][3][$key];
				} else {
					$sql = "UPDATE $strTable SET status=\"$strStatus\" WHERE id = \"{$_POST['data'][3][$key]}\";";
					// var_dump($sql);
					$stmt = $pdo->prepare($sql);
					$stmt->execute();
				}
			}
		}
		
		$arrReversedVideoId = array_reverse($arrVideoId);
		
		foreach ($arrReversedVideoId as $videoId) {
			$resourceId->setVideoId($videoId);
			$playlistItemSnippet->setResourceId($resourceId);
			$playlistItem->setSnippet($playlistItemSnippet);

			$response = $service->playlistItems->insert('snippet', $playlistItem);
		}
		
		
		if ($_POST['data'][1] == 'btnSort') {
			$htmlBody .= "Upated $strTable Order.<br>";
		} elseif (count($arrVideoId) > 0) {
			$htmlBody .= "Inserted " . count($arrVideoId) . " videos into playlist.<br>";
		} else {
			$htmlBody .= "Upated $strTable Status.<br>";
		}
		
		echo $htmlBody;
	}
	
	die;
}
?>

<!doctype html>
<html>
	<head>
		<title>My Prime</title>
		<!-- Tablesorter: required -->
		<script src="js/jquery-latest.min.js"></script>
		<script src="js/jquery.tablesorter.min.js"></script>
		<script src="js/jquery.tablesorter.widgets.min.js"></script>

		<!-- Theme -->
		<link rel="stylesheet" href="css/bootstrap-v3.min.css">
		<link rel="stylesheet" href="css/theme.bootstrap.css">
		<script src="js/bootstrap.min.js"></script>
		
		<!-- Tablesorter: optional -->
		<link rel="stylesheet" href="css/jquery.tablesorter.pager.min.css">
		<script src="js/widgets/widget-filter.min.js"></script>
		<script src="js/widgets/widget-storage.js"></script>
		<script src="js/extras/jquery.tablesorter.pager.min.js"></script>
		
		<!-- DRAG -->
		<script src="js/jquery-ui.min.js"></script>
		
		<!-- My Prime -->
		<link href="css/my-prime.css" rel="stylesheet">
		<script src="js/my-prime.js"></script>
	</head>
	<body>
		<a href="?action=_updateSubscriptions">Update Subscriptions</a> - 
		<a href="?action=_listSubscriptions">List Subscriptions</a> - 
		<a href="?action=_updateVideos">Update Videos</a> - 
		<a href="?action=_updateVideosDetails">Update Videos Details</a> - 
		<a href="?action=_listVideos">List Videos</a>
		<br>
		<?=$htmlBody?>
		<?php
		if (isset($_GET['action'])) {
			$action = $_GET['action'];
		} else {
			$action = '';
		}
		?>
		<div class="<?=$action?>">
			<?php
			if ($action == '_listSubscriptions' || $action == '_listVideos') {
			?>
			<div class="pager">
				Page: <select class="gotoPage"></select>
				<img src="img/icons/first.png" class="first" alt="First" title="First page" />
				<img src="img/icons/prev.png" class="prev" alt="Prev" title="Previous page" />
				<span class="pagedisplay"></span>
				<img src="img/icons/next.png" class="next" alt="Next" title="Next page" />
				<img src="img/icons/last.png" class="last" alt="Last" title= "Last page" />
				<select class="pagesize">
					<option value="50">50</option>
					<option value="100">100</option>
					<option value="200">200</option>
					<option value="500">500</option>
				</select> | 
				<input type='checkbox' id='checkAll' /> Check All 
				<button type="button" id="btnIgnore" class="download">Hide selected</button>
				<button type="button" id="btnUnignore" class="download">Show selected</button>
				<?php
				if ($action == '_listSubscriptions') {
				?>
				<button type="button" id="btnSort" class="download">Save channel order</button>
				<?php
				} elseif ($action == '_listVideos') {
				?>
				<button type="button" id="btnPlaylist" class="download">Save checked to playlist</button>
				<?php
				}
				?>
				<span id='status'></span>
			</div>
			
			<?php
			}
			?>
			<?=$table?>
		</div>
	</body>
</html>
