<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('max_execution_time', 300); //300 seconds = 5 minutes
set_time_limit(300);

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
// server should keep session data for AT LEAST x seconds.
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
// each client should remember their session id for EXACTLY x seconds.
session_set_cookie_params(30 * 24 * 60 * 60);
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
$htmlTable = '';
$htmlSelect = '';
$myChannelId = '';

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
        _getMyChannelId($service, $myChannelId);
    } catch (Google_Service_Exception $e) {
        $htmlBody .= sprintf(
            '<p>A Google_Service_Exception error occurred: <code>%s</code></p>',
            ($e->getMessage())
        );
        
        if (in_array($e->getCode(), [401])) {
            _showAuth($client, $htmlBody);
        }
    } catch (Google_Exception $e) {
        $htmlBody .= sprintf(
            '<p>An Google_Exception error occurred: <code>%s</code></p>',
            ($e->getMessage())
        );
    } catch (Exception $e) {
        $htmlBody .= sprintf(
            '<p>An Exception error occurred: <code>%s</code></p>',
            ($e->getMessage())
        );
    }

    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case '_listSubscriptions':
                _listSubscriptions($service, $pdo, $htmlTable, $myChannelId, $htmlSelect);
                break;
            case '_listPlaylists':
                _listPlaylists($service, $pdo, $htmlTable, $myChannelId);
                break;
            case '_listVideos':
                _listVideos($service, $pdo, $htmlTable, $htmlSelect, $myChannelId);
                break;
            case '_updateSubscriptions':
                _updateSubscriptions($service, $pdo, $htmlBody, $myChannelId);
                break;
            case '_updateVideos':
                _updateVideos($service, $pdo, $htmlBody, $myChannelId);
                break;
            case '_updateVideosDetails':
                _updateVideosDetails($service, $pdo, $htmlBody, $myChannelId);
                break;
            case '_updatePlaylists':
                _updatePlaylists($service, $pdo, $htmlBody, $myChannelId);
                break;
            case '_updatePlaylistsDetails':
                _updatePlaylistsDetails($service, $pdo, $htmlBody, $myChannelId);
                break;
            case '_updateMusicPlaylistsDetails':
                _updateMusicPlaylistsDetails($service, $pdo, $htmlBody, $myChannelId);
                break;
            case '_ajaxUpdate':
                _ajaxUpdate($service, $pdo, $htmlBody);
                break;
        }
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

function _showAuth($client, &$htmlBody)
{
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

function _covtime($youtube_time)
{
    if ($youtube_time) {
        $start = new DateTime('@0'); // Unix epoch
        $start->add(new DateInterval($youtube_time));
        $youtube_time = round($start->getTimestamp() / 60, 0);
    }
    
    return $youtube_time;
}

function _getMyChannelId($service, &$myChannelId)
{
    $queryParams = [
        'mine' => true
    ];

    $rspMyChannel = $service->channels->listChannels('id', $queryParams);
    $myChannelId = $rspMyChannel[0]->id;
}

function _listSubscriptions($service, $pdo, &$htmlTable, $myChannelId, &$htmlSelect)
{
	$intType = 1;
	
	if ($myChannelId == 'UCjp4sUlXfWngnLyPfA5SrIQ') {
		$intType = 2;
	}
	
	$sql = "SELECT * FROM channel_types WHERE type = $intType";
    // var_dump($sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannelTypes = $stmt->fetchAll();
    
    $htmlSelect = '<select name="selChannelTypes" id="selChannelTypes">';
    
    foreach ($resChannelTypes as $row) {
        $htmlSelect .= "<option value='{$row["id"]}'>{$row["label"]}</option>";
    }
    
    $htmlSelect .= '</select>';
	
    $sql = <<<END
SELECT channels.*, channel_types.label FROM channels 
LEFT JOIN channel_types ON channels.type = channel_types.id 
WHERE my_channel_id = '$myChannelId' 
-- AND channels.type IS NULL
ORDER BY sort ASC
LIMIT 300;
END;
    // var_dump($sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannels = $stmt->fetchAll();
    
    $arrTable = [];
    
    foreach ($resChannels as $row) {
		$type = $row['type'];
		if ($type !== null) {
			$type = sprintf('%02d', $row['type']);	
		}
        $arrTable[] = <<<END
	<tr>
		<td><input type='checkbox'></td>
		<td><a href='https://www.youtube.com/channel/{$row['id']}' target='_blank'>{$row['name']}</a></td>
		<td><a href='https://www.youtube.com/playlist?list={$row['playlist_id']}' target='_blank'>{$row['name']}</a></td>
		<td>{$row['status']}</td>
		<td>{$row['sort']}</td>
		<td>{$type}-{$row['label']}</td>
	</tr>
END;
    }
    
    $htmlTableBody = implode('', $arrTable);
    $htmlTable = <<<END
	<table id="tblSubs">
		<thead>
			<tr>
				<th class="group-word"><input type='checkbox' id='checkAll' /> Action</th>
				<th class="group-letter-1">Channel</th>
				<th class="group-letter-1">Uploads</th>
				<th class="group-date-month">Status</th>
				<th class="group-date-month">Sort</th>
				<th class="group-letter-1">Type</th>
			</tr>
		</thead>
		<tbody>
			$htmlTableBody
		</tbody>
	</table>
END;
}

function _listPlaylists($service, $pdo, &$htmlTable, $myChannelId)
{
    $sql = "SELECT * FROM playlists WHERE my_channel_id = '$myChannelId' ORDER BY sort ASC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannels = $stmt->fetchAll();
    
    $arrTable = [];
    
    foreach ($resChannels as $row) {
        $arrTable[] = <<<END
	<tr>
		<td><input type='checkbox'></td>
		<td><a href='https://www.youtube.com/playlist?list={$row['id']}' target='_blank'>{$row['name']}</a></td>
		<td>{$row['status']}</td>
		<td>{$row['sort']}</td>
	</tr>
END;
    }
    
    $htmlTableBody = implode('', $arrTable);
    $htmlTable = <<<END
	<table id="tblPlaylists">
		<thead>
			<tr>
				<th class="group-word"><input type='checkbox' id='checkAll' /> Action</th>
				<th class="group-letter-1">Playlist</th>
				<th class="group-date-month">Status</th>
				<th class="group-date-month">Sort</th>
			</tr>
		</thead>
		<tbody>
			$htmlTableBody
		</tbody>
	</table>
END;
}

function _listVideos($service, $pdo, &$htmlTable, &$htmlSelect, $myChannelId)
{
	if ($myChannelId == '') {
		if (isset($_GET['type']) && $_GET['type'] == 'music') {
			$myChannelId = 'UCjp4sUlXfWngnLyPfA5SrIQ'; // Music
		} else {
			$myChannelId = 'UCDhEgLlKq6teYnMOUS3MZ_g'; // Quezako
		}
	}
	
    $sql = <<<END
	SELECT 
		videos.*, channel_types.label, SUBSTR(videos.date_published, 0, 8) AS video_date_pub, SUBSTR(videos.date_checked, 0, 11) AS video_date_chk,
		playlists.name AS my_playlist_name, channels.name AS channel_name, channels.id AS channel_id, channels.sort AS channel_sort
	FROM videos 
	LEFT JOIN playlists ON videos.my_playlist_id = playlists.id AND playlists.my_channel_id = '$myChannelId'
	INNER JOIN channels ON videos.playlist_id = channels.playlist_id AND channels.my_channel_id = '$myChannelId'
	LEFT JOIN channel_types ON channels.type = channel_types.id 
	WHERE channels.status > 0 
		AND (videos.my_playlist_id = 0 OR videos.my_playlist_id IS NULL) 
		AND (videos.status <> -2 OR videos.status IS NULL)
	ORDER BY channel_sort ASC, SUBSTR(videos.date_published, 0, 8) DESC, CAST(duration AS INT) ASC
	LIMIT 1000;
END;
    // echo("<pre>$sql</pre>");
	// die;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannels = $stmt->fetchAll();
    
    $arrTable = [];
    
    foreach ($resChannels as $row) {
		// $type = $row['type'];
		// if ($type !== null) {
			// $type = sprintf('%02d', $row['type']);	
		// }
		$row['date_published'] = substr($row['date_published'], 0, 10);
        
        $arrTable[] = <<<END
	<tr>
		<td><input type='checkbox' /></td>
		<td><a href='https://www.youtube.com/channel/{$row['channel_id']}' target='_blank'>{$row['channel_name']}</a></td>
		<td><a href='https://www.youtube.com/watch?v={$row['id']}' target='_blank'>{$row['title']}</a></td>
		<td>{$row['duration']}</td>
		<td>{$row['channel_sort']}</td>
		<td>{$row['date_published']}</td>
		<td>{$row['label']}</td>
	</tr>
END;
    }
    
    $htmlTableBody = implode('', $arrTable);
    $htmlTable = <<<END
	<table id="tblVideos">
		<thead>
			<tr>
				<th class="group-word">Action</th>
				<th class="group-letter-1">Channel</th>
				<th class="group-letter-1">Video</th>
				<th class="group-Number-1">Duration</th>
				<th class="group-Number-1">Priority</th>
				<th class="group-date-day">Published</th>
				<th class="group-letter-1">Type</th>
			</tr>
		</thead>
		<tbody>
			$htmlTableBody
		</tbody>
	</table>
END;

    // select list: playlists.
    $sql = "SELECT id, name FROM playlists WHERE my_channel_id = '$myChannelId' AND status > 0 ORDER BY sort ASC LIMIT 200;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannels = $stmt->fetchAll();

    $htmlSelect = '<select name="selPlaylist" id="selPlaylist">';
    
    foreach ($resChannels as $row) {
        $htmlSelect .= "<option value='{$row["id"]}'>{$row["name"]}</option>";
    }
    
    $htmlSelect .= '</select>';
}

function _updateSubscriptions($service, $pdo, &$htmlBody, $myChannelId)
{
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
    
    foreach ($arrMySubscriptions as $mySubscriptionId => $mySubscription) {
        $sql = <<<END
	INSERT INTO channels (id, name, playlist_id, my_channel_id) 
	VALUES ("$mySubscriptionId", "{$mySubscription['title']}", "{$mySubscription['uploads']}", "$myChannelId")
	ON CONFLICT(id) DO UPDATE SET 
	name = "{$mySubscription['title']}",
	playlist_id = "{$mySubscription['uploads']}",
	my_channel_id = "$myChannelId"
	WHERE id="$mySubscriptionId";
END;
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    $htmlBody .= 'Upated subscriptions: ' . count($arrMySubscriptions) . '<br>';
}

function _updatePlaylists($service, $pdo, &$htmlBody, $myChannelId)
{
    $pageToken = '';
    $arrMyPlaylists = [];
    
    while (!is_null($pageToken)) {
        $paramMyPlaylists = [
            'maxResults' => 50,
            'pageToken' => $pageToken,
            'mine' => true,
            'fields' => 'items.id,items.snippet.title,items.snippet.channelId,nextPageToken'
        ];
        
        $rspMyPlaylists = $service->playlists->listPlaylists('snippet', $paramMyPlaylists);
        $pageToken = $rspMyPlaylists->nextPageToken;
        // var_dump($rspMyPlaylists[0]->snippet);
        
        
        foreach ($rspMyPlaylists->items as $playlist) {
            $arrMyPlaylists[$playlist->id] = [
                'title' => $playlist->snippet->title,
                'channel' => $playlist->snippet->channelId,
            ];
        }
    }
    
    foreach ($arrMyPlaylists as $myPlaylistId => $myPlaylist) {
        $sql = <<<END
	INSERT OR IGNORE INTO playlists (id, name, my_channel_id) 
	VALUES ("$myPlaylistId", "{$myPlaylist['title']}", "{$myPlaylist['channel']}")
	ON CONFLICT(id) DO 
	UPDATE SET
	name = "{$myPlaylist['title']}",
	my_channel_id = "{$myPlaylist['channel']}"
	WHERE id="$myPlaylistId";
END;
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    $htmlBody .= 'Upated playlists: ' . count($arrMyPlaylists) . '<br>';
}

function _updatePlaylistsDetails($service, $pdo, &$htmlBody, $myChannelId)
{
    $arrVideos = [];
    
	if (isset($_GET['type']) && $_GET['type'] == 'music') {
		$sql = <<<END
SELECT id FROM playlists 
WHERE id = 'PLPjkJ-eKlc2yp5mv6wuVo_UfhT2ZsZk5m' 
OR id = 'PLPjkJ-eKlc2zjByQjtI1M8ELC8TBeb2DN';
END;
	} elseif (isset($_GET['type']) && $_GET['type'] == 'video') {
		$sql = <<<END
SELECT id FROM playlists 
WHERE id = 'PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4'
OR id = 'PL52YqI0PbEYXZv2APe7B3wPkgdVXi1yK7'
OR id = 'PL52YqI0PbEYXMGfA71veOZCM_4NAWVopj'
OR id = 'PL52YqI0PbEYX18AmDYyv1lx28lIzwYgTb'
OR id = 'PL52YqI0PbEYV_g-5wdATX6GV7QPXvPadH'
OR id = 'PL52YqI0PbEYUxtItqu6WAIqj1C7hX4LUL'
OR id = 'PL52YqI0PbEYXzJuYKJFyCCu1lREuoMhnO';
END;
	} else {
		$sql = "SELECT id FROM playlists WHERE my_channel_id = '$myChannelId' AND status > 0 ORDER BY sort ASC LIMIT 200;";
	}
    
    // var_dump($sql);
	// die;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resPlaylists = $stmt->fetchAll();

    foreach ($resPlaylists as $myPlaylist) {
        $pageToken = "";

        while (!is_null($pageToken)) {
			$queryParams = [
				'maxResults' => 200,
				'pageToken' => $pageToken,
				'playlistId' => $myPlaylist['id']
			];
			
			// var_dump($queryParams);
            
			try {
				$myPlaylistItems = $service->playlistItems->listPlaylistItems('contentDetails', $queryParams);
				$pageToken = $myPlaylistItems->nextPageToken;
				// var_dump($pageToken);
			} catch (Exception $e) {
				$myPlaylistItems = [];
				$pageToken = null;
				echo "can't find playlist_id: {$myPlaylist['id']}<br>";
			}
			
            
            foreach ($myPlaylistItems as $myPlaylistItemDetails) {
                $arrVideos[$myPlaylistItemDetails->contentDetails->videoId]['my_playlist_id'] = $myPlaylist['id'];
				// var_dump($myPlaylistItemDetails);
            }
        }
    }
	// var_dump($myPlaylistItems->pageInfo);
	// var_dump($arrVideos);
	// die;
    
    if (count($arrVideos) !== 0) {
        foreach ($arrVideos as $strVideoId => $strPlaylistId) {
            $sql = <<<END
	INSERT INTO videos (id, my_playlist_id) 
	VALUES ("{$strVideoId}", "{$strPlaylistId['my_playlist_id']}")
	ON CONFLICT(id) DO 
	UPDATE SET my_playlist_id="{$strPlaylistId['my_playlist_id']}" 
	WHERE id="$strVideoId";
END;
	// var_dump($arrVideos);
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        $htmlBody .= 'Upated videos playlists: ' . count($arrVideos) . '<br>';
    } else {
        $htmlBody .= 'Videos playlists up to date.<br>';
    }
}

function _updateVideos($service, $pdo, &$htmlBody, $myChannelId)
{
    $now = date_create();
    
    $sql = "SELECT playlist_id FROM channels WHERE my_channel_id = '$myChannelId' AND status = 1;";
    // $sql = "SELECT playlist_id FROM channels WHERE my_channel_id = '$myChannelId' AND status = 1 AND sort < 50;";
    // var_dump($sql);
	// die;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannels = $stmt->fetchAll();
    
    $sql = <<<END
SELECT * FROM (
	SELECT videos.playlist_id, videos.date_checked 
	FROM videos
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.my_channel_id = '$myChannelId' 
	ORDER BY videos.date_checked DESC
) GROUP BY playlist_id;
END;
    // var_dump($sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resVideos = $stmt->fetchAll();
    
    $arrVideos = [];
    $arrStoredVideos = [];
    
    foreach ($resVideos as $row) {
        $arrStoredVideos[$row['playlist_id']] = $row['date_checked'];
    }
    
	$numItems = 0;
	
    foreach ($resChannels as $row) {
        if (isset($arrStoredVideos[$row['playlist_id']])) {
            $interval = date_diff(date_create($arrStoredVideos[$row['playlist_id']]), $now);
            // $dateDiff = $interval->format('%a') > 50 ? 50 : $interval->format('%a') + 1;
            $dateDiff = $interval->format('%a') > 50 ? 50 : $interval->format('%a');
        } else {
            $dateDiff = 50;
        }
        // $dateDiff = 5000;
        
		if ($dateDiff > 0) {
			$queryParams = [
				'playlistId' => $row['playlist_id'],
				'maxResults' => $dateDiff
			];
			// var_dump($queryParams);

			try {
				$rspPlaylistItems = $service->playlistItems->listPlaylistItems('snippet', $queryParams);
			} catch (Exception $e) {
				$rspPlaylistItems['items'] = [];
				echo "can't find playlist_id: {$row['playlist_id']}<br>";
			}
			
			foreach ($rspPlaylistItems['items'] as $video) {
				$arrVideos[$video->snippet->resourceId->videoId] = [
					'channelTitle' => $video->snippet->channelTitle,
					'title' => $video->snippet->title,
					'videoId' => $video->snippet->resourceId->videoId,
					'playlistId' => $row['playlist_id'],
					'publishedAt' => $video->snippet->publishedAt,
				];
				
				$numItems++;
				echo $row['playlist_id'] . ': ' . $video->snippet->resourceId->videoId . '<br />';
			}
			
			if ($numItems > 200) {
				break;
			}
		}
    }
    
    if (count($arrVideos) > 0) {
        foreach ($arrVideos as $arrSqlVideosId => $strSqlVideos) {
            $title = str_replace('"', '""', $strSqlVideos['title']);
            $sql = <<<END
	INSERT INTO videos (id, playlist_id, title, date_published, date_checked) 
	VALUES ("$arrSqlVideosId", "{$strSqlVideos['playlistId']}", "{$title}", "{$strSqlVideos['publishedAt']}", "{$now->format('Y-m-d\TH:i:s\Z')}")
	ON CONFLICT(id) DO UPDATE SET 
	playlist_id = "{$strSqlVideos['playlistId']}",
	title = "{$title}",
	date_published = "{$strSqlVideos['publishedAt']}",
	date_checked = "{$now->format('Y-m-d\TH:i:s\Z')}"
	WHERE id="$arrSqlVideosId";
END;
            // echo "$sql<br>";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        $htmlBody .= 'Added videos: ' . count($arrVideos);
    } else {
        $htmlBody .= 'Videos up to date.';
    }
}

function _updateVideosDetails($service, $pdo, &$htmlBody, $myChannelId)
{
    // Check if user has rated the videos.
    $arrVideos = [];
    $arrVideoIds = [];
    $strVideos = '';
    $i = 0;
    
    $sql = <<<END
	SELECT videos.id FROM videos 
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.my_channel_id = '$myChannelId' 
	WHERE (my_playlist_id = 0 OR my_playlist_id IS NULL);
END;
	// var_dump($sql);
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
    
    // Check if duration is defined.
    $arrVideos2 = [];
    $arrVideoIds = [];
    $strVideos = '';
    $i = 0;
    
    $sql = <<<END
	SELECT videos.id FROM videos
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.my_channel_id = '$myChannelId' 
	WHERE duration IS NULL LIMIT 1000;
END;
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
}

function _ajaxUpdate($service, $pdo, &$htmlBody)
{
    if (!isset($_POST['data'])) {
        echo 'No POST data.';
    } else {
        $strAction = $_POST['data'][0];
        $strBtn = $_POST['data'][1];
        $strPlaylist = (isset($_POST['data'][4]) && $_POST['data'][4] !== '') ? $_POST['data'][4] : null;
        $strType = isset($_POST['data'][5]) ? $_POST['data'][5] : null;
        $strStatus = $strBtn == 'btnIgnore' ? -2 : 1;
        // var_dump($_POST);
		// die;
		
        if ($strAction == '_listSubscriptions') {
            $strTable = 'channels';
        } elseif ($strAction == '_listPlaylists') {
            $strTable = 'playlists';
        } elseif ($strAction == '_listVideos') {
            $strTable = 'videos';
        }
		
        $arrVideoId = [];
        $i = 0;
		
        foreach ($_POST['data'][2] as $key => $isChecked) {
			$sql = '';
			
            if ($strBtn == 'btnSort') {
                $strSort = $key + 1;
                $sql = "UPDATE $strTable SET sort=\"{$strSort}\" WHERE id = \"{$_POST['data'][3][$key]}\";";
            } elseif ($isChecked == 1) {
                if ($strBtn == 'btnPlaylist') {
                    $arrVideoId[] = $_POST['data'][3][$key];
                } elseif ($strBtn == 'btnType') {
					$sql = "UPDATE $strTable SET type=\"{$strType}\" WHERE id = \"{$_POST['data'][3][$key]}\";";
				} else {
                    $sql = "UPDATE $strTable SET status=\"$strStatus\" WHERE id = \"{$_POST['data'][3][$key]}\";";
                }
            }
			
			if ($sql !== '') {
				$i++;
				// var_dump($sql);
				$stmt = $pdo->prepare($sql);
				$stmt->execute();
			}
        }
        
        if (isset($strPlaylist)) {
            var_dump($strPlaylist);
            $arrReversedVideoId = array_reverse($arrVideoId);
            $playlistItem = new Google_Service_YouTube_PlaylistItem();
            $playlistItemSnippet = new Google_Service_YouTube_PlaylistItemSnippet();
            $playlistItemSnippet->setPlaylistId($strPlaylist);
            $resourceId = new Google_Service_YouTube_ResourceId();
            $resourceId->setKind('youtube#video');
            
            foreach ($arrReversedVideoId as $videoId) {
				// var_dump($videoId);
                $resourceId->setVideoId($videoId);
                $playlistItemSnippet->setResourceId($resourceId);
                $playlistItem->setSnippet($playlistItemSnippet);
                // var_dump($playlistItem);
                // var_dump($videoId);
				
				try {
					$response = $service->playlistItems->insert('snippet', $playlistItem);
				} catch (Exception $e) {
					echo "can't find video_id: {$videoId}<br>";
				}
                // break;
            }
        }
        
        if ($strBtn == 'btnSort') {
            $htmlBody .= "Upated $strTable Order ($i).<br>";
        } elseif ($strBtn == 'btnType') {
            $htmlBody .= "Upated $strTable Type ($i).<br>";
        } elseif (count($arrVideoId) > 0) {
            $htmlBody .= "Inserted " . count($arrVideoId) . " videos into playlist.<br>";
        } else {
            $htmlBody .= "Upated $strTable Status ($i).<br>";
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
		<link rel="shortcut icon" type="image/ico" href="assets/favicon/favicon.ico"/>
		
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
		<a href="?">Home</a> | 
		List: <a href="?action=_listSubscriptions">🔖Subscriptions</a> | 
		<a href="?action=_listPlaylists">🔀Playlists</a> | 
		<a href="?action=_listVideos">📺Videos</a>  | 
		<a href="?action=_listVideos&type=music">🎵Music</a>
		<br />		
		Update: <a href="?action=_updateSubscriptions">🔖Upd Subscriptions</a> | 
		<a href="?action=_updatePlaylists">🔀Upd Playlists</a> | 
		<a href="?action=_updatePlaylistsDetails">🔂Upd Pl. Details</a> | 
		<a href="?action=_updatePlaylistsDetails&type=video">🔂📺Upd VIDEO Pl. Details</a> | 
		<a href="?action=_updatePlaylistsDetails&type=music">🔂🎵Upd MUSIC Pl. Details</a> | 
		<a href="?action=_updateVideos">📖Upd Tracks</a> | 
		<a href="?action=_updateVideosDetails">📄Upd Tracks Details</a>
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
            if (in_array($action, ['_listSubscriptions', '_listPlaylists', '_listVideos'])) {
                ?>
			<div class="pager">
				Filters:
				<select id="selFilter">
					<option value="">-----</option>
					<option value="1">Prime</option>
					<option value="2">Prime Short</option>
					<option value="3">Bob</option>
					<option value="4">Zera</option>
					<option value="5">gamers|humor</option>
					<option value="6">education|technologies</option>
					<option value="7">gaming|zap|fun</option>
					<option value="8">Podcast: health|philo|documentary</option>
					<option value="9">Asia|travel|art</option>
					<option value="10">fiction</option>
					<option value="11">TV / Radio|streaming</option>
					<option value="12">friends</option>
					<option value="13">Very Short</option>
					<option value="14">Medium</option>
					<option value="15">Long</option>
					<option value="16">MUSIC Electro</option>
					<option value="17">MUSIC Dance</option>
					<option value="18">MUSIC Rock / Pop</option>
					<option value="19">MUSIC France</option>
					<option value="20">MUSIC Other</option>
					<option value="21">MUSIC live</option>
					<option value="22">MUSIC bad length</option>
					<option value="99">Clear all</option>
				</select>
				|
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
				</select>
				<br />
				<input type='checkbox' id='checkAll' /> Check All 
				<button type="button" id="btnIgnore" class="download">Hide selected</button>
				<button type="button" id="btnUnignore" class="download">Show selected</button>
				<?=$htmlSelect;?>
				<?php
                if (in_array($action, ['_listSubscriptions', '_listPlaylists'])) {
                    ?>
				<button type="button" id="btnSort" class="download">Save order</button>
				<button type="button" id="btnType" class="download">Save checked to type</button>
				<?php
                } elseif ($action == '_listVideos') {
                ?>
				<button type="button" id="btnPlaylist" class="download">Save checked to playlist</button>
				<?php
                } ?>
				<span id='status'></span>
			</div>
			
			<?php
            }
            
            echo $htmlTable;
            ?>
		</div>
	</body>
</html>
