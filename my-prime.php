<?php
/*
TODO:
separate static html from api service and sqlite service
subscriptions: add nb of subs
tracks: add nb of views
*/
error_reporting(E_ALL);
set_time_limit(600);
ini_set("display_errors", 1);
ini_set('max_execution_time', 600); //300 seconds = 5 minutes
ini_set("xdebug.var_display_max_children", '-1');
ini_set("xdebug.var_display_max_data", '-1');
ini_set("xdebug.var_display_max_depth", '-1');

// server should keep session data for AT LEAST x seconds.
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
// each client should remember their session id for EXACTLY x seconds.
session_set_cookie_params(30 * 24 * 60 * 60);
session_start();

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
	throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ . '"');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setAccessType('offline');
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);

$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]", FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

$service = new Google_Service_YouTube($client);

$htmlBody = '';
$htmlTable = '';
$htmlSelect = '';
$myChannelId = 'UCDhEgLlKq6teYnMOUS3MZ_g';

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

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
	if (isset($_GET['action'])) {
		if (!in_array($_GET['action'], ['_listSubscriptions', '_listPlaylists', '_listVideos', '_ajaxUpdate'])) {
			try {
				_getMyChannelId($service, $myChannelId);
			} catch (Google_Service_Exception $e) {
				echo sprintf(
					'<p>A Google_Service_Exception error occurred: <code>%s</code></p>',
					($e->getMessage())
				);

				if (in_array($e->getCode(), [401])) {
					_showAuth($client, $htmlBody);
				}
			} catch (Google_Exception $e) {
				echo sprintf(
					'<p>An Google_Exception error occurred: <code>%s</code></p>',
					($e->getMessage())
				);
			} catch (Exception $e) {
				echo sprintf(
					'<p>An Exception error occurred: <code>%s</code></p>',
					($e->getMessage())
				);
			}
		}

		switch ($_GET['action']) {
			case '_listSubscriptions':
			case '_listPlaylists':
			case '_listVideos':
				_list($pdo, $myChannelId);
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
			case '_updateAll':
				_updateAll($service, $pdo, $htmlBody, $myChannelId);
				break;
			case '_ajaxUpdate':
				_ajaxUpdate($service, $pdo, $htmlBody);
				break;
		}
	}

	$_SESSION[$tokenSessionKey] = $client->getAccessToken();
} elseif ($OAUTH2_CLIENT_ID == 'REPLACE_ME') {
	echo <<<END
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
	echo <<<END
	<h3>Authorization Required</h3>
	<p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;

	// header('Location: ' . $authUrl);
}

function _getMyChannelId($service, &$myChannelId)
{
	$queryParams = [
		'mine' => true
	];

	$rspMyChannel = $service->channels->listChannels('id', $queryParams);

	if (isset($_COOKIE['radio_music']) && $_COOKIE['radio_music'] == '{"checked":true}') {
		$myChannelId = 'UCjp4sUlXfWngnLyPfA5SrIQ'; // Music
	} else {
		$myChannelId = 'UCDhEgLlKq6teYnMOUS3MZ_g'; // Quezako
	}
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

function _list($pdo, $myChannelId)
{
	$strFilter = '';
	$arrSort = [];

	if ($_GET['action'] == '_listSubscriptions') {
		$table = 'channels';
		$arrHeaders = [
			"Action",
			"Channel",
			"Uploads",
			"Status",
			"Sort",
			"Type",
			"Subs",
		];
		$arrFields = [
			"id",
			"name",
			"name",
			"status",
			"sort",
			"type2",
			"subs",
		];
		$sqlCount = <<<END
SELECT count(*) AS count
FROM channels
LEFT JOIN channel_types ON channels.type = channel_types.id 
WHERE channels.account = '$myChannelId'
END;
		$sql = <<<END
SELECT channels.*, channel_types.label, substr('0000'||channel_types.sort, -4, 4) || "_" || channel_types.label AS type2
FROM channels 
LEFT JOIN channel_types ON channels.type = channel_types.id 
WHERE channels.account = '$myChannelId' 
END;
	} elseif ($_GET['action'] == '_listPlaylists') {
		$table = 'playlists';
		$arrHeaders = [
			"Action",
			"Playlist",
			"Status",
			"Sort",
		];
		$arrFields = [
			"id",
			"name",
			"status",
			"sort",
		];
		$sqlCount = <<<END
SELECT count(*) AS count
FROM playlists
WHERE playlists.account = '$myChannelId'
END;
		$sql = <<<END
SELECT * 
FROM playlists 
WHERE account = '$myChannelId' 
-- ORDER BY sort ASC
-- LIMIT 200;
END;
	} elseif ($_GET['action'] == '_listVideos') {
		$table = 'videos';
		$arrHeaders = [
			"Action",
			"Channel",
			"Video",
			"Duration",
			"Priority",
			"Published",
			"Type",
			"Views",
		];
		$arrFields = [
			"id",
			"channels.name",
			"title",
			"duration",
			"channels.sort",
			"date_published",
			"channel_types.label",
			"views",
		];
		$sqlCount = <<<END
SELECT count(*) AS count
FROM videos 
INNER JOIN channels ON videos.playlist_id = channels.playlist_id AND channels.account = '$myChannelId'
LEFT JOIN playlists ON videos.my_playlist_id = playlists.id AND playlists.account = '$myChannelId'
LEFT JOIN channel_types ON channels.type = channel_types.id 
WHERE channels.status > 0 
	AND (videos.my_playlist_id = 0 OR videos.my_playlist_id IS NULL) 
	AND (videos.status <> -2 OR videos.status IS NULL)
END;
		$sql = <<<END
SELECT 
	videos.*, channel_types.label, SUBSTR(videos.date_published, 0, 8) AS video_date_pub, SUBSTR(videos.date_checked, 0, 11) AS video_date_chk,
	playlists.name AS my_playlist_name, channels.name AS channel_name, channels.id AS channel_id, channels.sort AS channel_sort
FROM videos 
INNER JOIN channels ON videos.playlist_id = channels.playlist_id AND channels.account = '$myChannelId'
LEFT JOIN playlists ON videos.my_playlist_id = playlists.id AND playlists.account = '$myChannelId'
LEFT JOIN channel_types ON channels.type = channel_types.id 
WHERE channels.status > 0 
	AND (videos.my_playlist_id = 0 OR videos.my_playlist_id IS NULL) 
	AND (videos.status <> -2 OR videos.status IS NULL)
-- ORDER BY channel_sort ASC, SUBSTR(videos.date_published, 0, 8) DESC, CAST(duration AS INT) ASC
-- LIMIT 1000;
END;
	}

	$isType2 = false;

	if (is_array($_GET['filter'])) {
		foreach ($_GET['filter'] as $key => $filter) {
			if ($arrFields[$key] === "type2") {
				$arrFields[$key] = "channel_types.label";
				$isType2 = true;
			}

			$strFilterType = substr($filter, 0, 1);

			if ($strFilterType === '<' || $strFilterType === '>') {
				$arrFilter = explode(" and ", $filter);
				$arrFilter2 = explode(" or ", $filter);

				if (isset($arrFilter2[1])) {
					$strFilter .= " AND ({$arrFields[$key]} " . implode(" OR {$arrFields[$key]} ", $arrFilter2) . ") ";
				} else {
					foreach ($arrFilter as $strFilterSplit) {
						$strFilter .= " AND {$arrFields[$key]} $strFilterSplit";
					}
				}
			} elseif ($strFilterType === '!') {
				$filter = trim($filter, '!()');
				$arrFilter = explode("|", $filter);

				foreach ($arrFilter as $strFilterSplit) {
					$strFilter .= " AND {$arrFields[$key]} NOT LIKE '%$strFilterSplit%'";
				}
			} elseif ($strFilterType === "'") {
				$strFilter .= " AND {$arrFields[$key]} ISNULL";
			} else {
				$arrFilter = explode("|", $filter);
				$strFilter .= " AND ({$arrFields[$key]} LIKE '%" . implode("%' OR {$arrFields[$key]} LIKE '%", $arrFilter) . "%')";
			}
		}
	}

	if (is_array($_GET['column'])) {
		foreach ($_GET['column'] as $key => $order) {
			$order = $order == 0 ? 'ASC' : 'DESC';

			if ($arrFields[$key] === "channel_types.label" && $isType2 === true) {
				$arrFields[$key] = "channel_types.sort";
			}

			$arrSort[] = "{$arrFields[$key]} $order";
		}
	}

	if (count($arrSort) == 0) {
		$strSort = 'ORDER BY sort ASC';
	} else {
		$strSort = 'ORDER BY ' . implode(',', $arrSort);
	}

	$sqlCount .= <<<END
	
$strFilter
END;
	$stmt = $pdo->prepare($sqlCount);
	$stmt->execute();
	$resCount = $stmt->fetch();

	if ($_GET['size'] == 'all') {
		$offset = 0;
		$rowCount = $resCount['count'];
	} else {
		$offset = $_GET['size'] * $_GET['page'];
		$rowCount = $_GET['size'];
	}
	$sql .= <<<END
	
$strFilter
$strSort
LIMIT {$offset}, {$rowCount};
END;
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$resChannels = $stmt->fetchAll();

	$arrTable = [];

	foreach ($resChannels as $row) {
		if ($table == 'channels') {

			$arrTable[] = [
				$arrHeaders[0] => "<input type='checkbox'>",
				$arrHeaders[1] => "<a href='https://www.youtube.com/channel/{$row['id']}' target='_blank'>{$row['name']}</a>",
				$arrHeaders[2] => "<a href='https://www.youtube.com/channel/{$row['id']}/videos?view=0&sort=p&flow=grid' target='_blank'>{$row['name']}</a>",
				$arrHeaders[3] => "{$row['status']}",
				$arrHeaders[4] => "{$row['sort']}",
				$arrHeaders[5] => "{$row['type2']}",
				$arrHeaders[6] => "{$row['subs']}",
			];
		} elseif ($table == 'playlists') {
			$arrTable[] = [
				$arrHeaders[0] => "<input type='checkbox'>",
				$arrHeaders[1] => "<a href='https://www.youtube.com/playlist?list={$row['id']}' target='_blank'>{$row['name']}</a>",
				$arrHeaders[2] => "{$row['status']}",
				$arrHeaders[3] => "{$row['sort']}",
			];
		} elseif ($table == 'videos') {
			$row['date_published'] = substr($row['date_published'], 0, 10);

			$arrTable[] = [
				$arrHeaders[0] => "<input type='checkbox' />",
				$arrHeaders[1] => "<a href='https://www.youtube.com/channel/{$row['channel_id']}' target='_blank'>{$row['channel_name']}</a>",
				$arrHeaders[2] => "<a href='https://www.youtube.com/watch?v={$row['id']}' target='_blank'>{$row['title']}</a>",
				$arrHeaders[3] => "{$row['duration']}",
				$arrHeaders[4] => "{$row['channel_sort']}",
				$arrHeaders[5] => "{$row['date_published']}",
				$arrHeaders[6] => "{$row['label']}",
				$arrHeaders[7] => "{$row['views']}",
			];
		}
	}

	$objJson = [
		"total_rows" => $resCount['count'],
		"headers" => $arrHeaders,
		"rows" => $arrTable
	];

	echo json_encode($objJson, JSON_PRETTY_PRINT);
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
			'fields' => 'items.id,items.contentDetails.relatedPlaylists.uploads,items.statistics.subscriberCount',
		];

		$rspChannels = $service->channels->listChannels('contentDetails,statistics', $paramChannels);

		foreach ($rspChannels->items as $channel) {
			$arrTmpSubscriptions[$channel->id]['uploads'] = $channel->contentDetails->relatedPlaylists->uploads;
			$arrTmpSubscriptions[$channel->id]['subs'] = $channel->statistics->subscriberCount;
		}

		$arrMySubscriptions += $arrTmpSubscriptions;
	}

	foreach ($arrMySubscriptions as $mySubscriptionId => $mySubscription) {
		$sql = <<<END
	INSERT INTO channels (id, name, playlist_id, account, subs) 
	VALUES ("$mySubscriptionId", "{$mySubscription['title']}", "{$mySubscription['uploads']}", "$myChannelId", "{$mySubscription['subs']}")
	ON CONFLICT(id) DO UPDATE SET 
	name = "{$mySubscription['title']}",
	playlist_id = "{$mySubscription['uploads']}",
	account = "$myChannelId",
	subs = "{$mySubscription['subs']}"
	WHERE id="$mySubscriptionId";
END;
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
	}

	echo 'Upated subscriptions: ' . count($arrMySubscriptions) . '<br>';
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


		foreach ($rspMyPlaylists->items as $playlist) {
			$arrMyPlaylists[$playlist->id] = [
				'title' => $playlist->snippet->title,
				'channel' => $playlist->snippet->channelId,
			];
		}
	}

	foreach ($arrMyPlaylists as $myPlaylistId => $myPlaylist) {
		$sql = <<<END
	INSERT OR IGNORE INTO playlists (id, name, account) 
	VALUES ("$myPlaylistId", "{$myPlaylist['title']}", "{$myPlaylist['channel']}")
	ON CONFLICT(id) DO 
	UPDATE SET
	name = "{$myPlaylist['title']}",
	account = "{$myPlaylist['channel']}"
	WHERE id="$myPlaylistId";
END;
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
	}

	echo 'Upated playlists: ' . count($arrMyPlaylists) . '<br>';
}

function _updatePlaylistsDetails($service, $pdo, &$htmlBody, $myChannelId)
{
	$arrVideos = [];

	if ($myChannelId == 'UCjp4sUlXfWngnLyPfA5SrIQ') {
		$sql = <<<END
SELECT id FROM playlists 
WHERE id = 'PLPjkJ-eKlc2yp5mv6wuVo_UfhT2ZsZk5m' 
OR id = 'PLPjkJ-eKlc2zjByQjtI1M8ELC8TBeb2DN';
OR id = 'PLPjkJ-eKlc2z1Hf-JY3WRmTUUGlMkGav6';
END;
	} else {
		$sql = <<<END
SELECT id FROM playlists 
WHERE 1 = 0
OR id = 'PL52YqI0PbEYWESzZ97h5QzCIoKo9OZlhI'
OR id = 'PL52YqI0PbEYWZ5aorN_KSeHvVeUcrr8H6'
OR id = 'PL52YqI0PbEYUxtItqu6WAIqj1C7hX4LUL'
OR id = 'PL52YqI0PbEYV_g-5wdATX6GV7QPXvPadH'
OR id = 'PL52YqI0PbEYXzJuYKJFyCCu1lREuoMhnO'
OR id = 'PL52YqI0PbEYX2s-juEdNuXCyY-GUvSVor'
OR id = 'PL52YqI0PbEYU5t0I78FhbXfeexjksd5D0'
OR id = 'PL52YqI0PbEYW5hDkfG_5AaOLz--xroo-i'
OR id = 'PL52YqI0PbEYU5l8BMJiMAEJO6owe7Tjvl'
OR id = 'PL52YqI0PbEYXUDQbgy-ucXFLYk-LTdc4o'
OR id = 'PL52YqI0PbEYX18AmDYyv1lx28lIzwYgTb'
OR id = 'PL52YqI0PbEYWdYWfZIDSb9d4TLcwTHFNM'
OR id = 'PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4'
OR id = 'PL52YqI0PbEYXZv2APe7B3wPkgdVXi1yK7'
OR id = 'PL52YqI0PbEYXY2mIqhMzRQlYlSk_qjipA'
OR id = 'PL52YqI0PbEYWYGVaDb7WaotZw1-DqeIbt'
OR id = 'PL52YqI0PbEYXMGfA71veOZCM_4NAWVopj'
OR id = 'PL52YqI0PbEYV-CR9Y8gOO1TlanC6VHpBV'
OR id = 'PL52YqI0PbEYWZxaXbsJRgKSLlvL-Caj0m'
OR id = 'PL52YqI0PbEYWqnBLaviFHouJHNYj_gNmY'
OR id = 'PL52YqI0PbEYXTcfJc2988HdNXTuNz_R46'
OR id = 'PL52YqI0PbEYUTr_Wnjip5_p5ue0E-60Aj'
OR id = 'PL52YqI0PbEYWHvaMgh19UXBNqB-K8CmLB'
OR id = 'PL52YqI0PbEYVjeAuX3CMF9ky1rmAQLADN'
OR id = 'PL52YqI0PbEYXfVltmvdm2OrgB58SYLpzI'
OR id = 'PL52YqI0PbEYWFKKOwPJxMXdTi7IhEb_e5'
OR id = 'FLDhEgLlKq6teYnMOUS3MZ_g'
OR id = 'PL52YqI0PbEYUdPJEVeMbzMujHNDHbJUPi'
OR id = 'PL52YqI0PbEYXaL5E8EiraX6wdEC98Q7XX'
OR id = 'PL52YqI0PbEYWdzx7RrT2h6bp7cb5uYq7C'
OR id = 'PL52YqI0PbEYXpDVrt6oQBeJDUkzOkOKGc'
OR id = 'PL52YqI0PbEYVG4sa24kHOAbEN7B3v4ZzZ'
OR id = 'PL52YqI0PbEYW-NsQxLQWpSVE-aVBS53pH'
-- OR id = 'PL52YqI0PbEYUTVvCGhOpiDaq0NsRHbEIk'
-- OR id = 'PL52YqI0PbEYUqv-NNv4pKY7Hmd_fsftaL'
-- OR id = 'PL52YqI0PbEYVcJePTrwQ5wbVvh25ThpHy'
-- OR id = 'PL52YqI0PbEYVA1jSoAyGEOQZMdOaOlL-C'
-- OR id = 'PL52YqI0PbEYVrIe5j_v6EPoK9V59AaN9d'
-- OR id = 'PL52YqI0PbEYWUtAjS-edI4qZmyWQMTVTB'
;
END;
	}

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

			try {
				$myPlaylistItems = $service->playlistItems->listPlaylistItems('contentDetails', $queryParams);
				$pageToken = $myPlaylistItems->nextPageToken;
			} catch (Exception $e) {
				$myPlaylistItems = [];
				$pageToken = null;
				echo "can't find playlist_id: {$myPlaylist['id']}<br>";
			}


			foreach ($myPlaylistItems as $myPlaylistItemDetails) {
				$arrVideos[$myPlaylistItemDetails->contentDetails->videoId]['my_playlist_id'] = $myPlaylist['id'];
			}
		}
	}

	if (count($arrVideos) !== 0) {
		foreach ($arrVideos as $strVideoId => $strPlaylistId) {
			$sql = <<<END
	INSERT INTO videos (id, my_playlist_id) 
	VALUES ("{$strVideoId}", "{$strPlaylistId['my_playlist_id']}")
	ON CONFLICT(id) DO 
	UPDATE SET my_playlist_id="{$strPlaylistId['my_playlist_id']}" 
	WHERE id="$strVideoId";
END;
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}

		echo 'Upated videos playlists: ' . count($arrVideos) . '<br>';
	} else {
		echo 'Videos playlists up to date.<br>';
	}
}

function _updateVideos($service, $pdo, &$htmlBody, $myChannelId)
{
	$now = date_create();
	$timeStart = time();
	$timeLimit = 400; // interval in seconds.

	$sql = "SELECT playlist_id FROM channels WHERE account = '$myChannelId' AND status = 1 ORDER BY sort DESC;";
	// EDIT: Update specific channels.
	// $sql = "SELECT playlist_id FROM channels WHERE account = '$myChannelId' AND (
	// 	id = 'UCahRghBkidF24RvCXLL4ezA'
	// 	OR id = 'UCsSsgPaZ2GSmO6il8Cb5iGA'
	// ) ORDER BY sort DESC;";
		
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$resChannels = $stmt->fetchAll();

	$sql = <<<END
SELECT * FROM (
	SELECT videos.playlist_id, videos.date_checked 
	FROM videos
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.account = '$myChannelId' 
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

	foreach ($resChannels as $row) {
		if (isset($arrStoredVideos[$row['playlist_id']])) {
			$interval = date_diff(date_create($arrStoredVideos[$row['playlist_id']]), $now);
			$dateDiff = $interval->format('%a') > 50 ? 50 : $interval->format('%a');
		} else {
			$dateDiff = 5000;
		}

		if ($dateDiff > 0) {
			$pageToken = "";

			while (!is_null($pageToken)) {
				$queryParams = [
					'playlistId' => $row['playlist_id'],
					'maxResults' => $dateDiff,
					'pageToken' => $pageToken,
				];

				try {
					$rspPlaylistItems = $service->playlistItems->listPlaylistItems('snippet', $queryParams);
					$pageToken = $rspPlaylistItems->nextPageToken;
				} catch (Exception $e) {
					$rspPlaylistItems['items'] = [];
					$pageToken = null;
					echo "can't find playlist_id: {$row['playlist_id']}<br>";
				}

				// EDIT: Speed-up: only last page.
				$pageToken = null;

				foreach ($rspPlaylistItems['items'] as $video) {
					$arrVideos[$video->snippet->resourceId->videoId] = [
						'channelTitle' => $video->snippet->channelTitle,
						'title' => $video->snippet->title,
						'videoId' => $video->snippet->resourceId->videoId,
						'playlistId' => $row['playlist_id'],
						'publishedAt' => $video->snippet->publishedAt,
					];
				}

				if (time() - $timeStart > $timeLimit) {
					var_dump($timeStart);
					var_dump(time());
					var_dump($timeStart - time());
					var_dump($video->snippet->channelTitle);
					var_dump($video->snippet->publishedAt);
					var_dump($video->snippet->title);
					break;
				}
			}
		}

		if (time() - $timeStart > $timeLimit) {
			break;
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

		echo 'Added videos: ' . count($arrVideos) . '<br>';
	} else {
		echo 'Videos up to date.<br>';
	}

	if (time() - $timeStart > $timeLimit) {
		var_dump($timeStart);
		var_dump(time());
		var_dump($timeStart - time());
	}
}

function _updateVideosDetails($service, $pdo, &$htmlBody, $myChannelId)
{
	// Check if user has rated the videos.
	$arrVideos = [];
	$arrVideoIds = [];
	$strVideos = '';
	$i = 0;
	$timeStart = time();
	$timeLimit = 400;

	$sql = <<<END
	SELECT videos.id FROM videos 
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.account = '$myChannelId' 
	WHERE (my_playlist_id = 0 OR my_playlist_id IS NULL);
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
		if (time() - $timeStart > $timeLimit) {
			var_dump($timeStart);
			var_dump(time());
			var_dump($timeStart - time());
			var_dump($strVideos);
			break;
		}

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
	$arrVideos3 = [];
	$arrVideoIds = [];
	$strVideos = '';
	$i = 0;

	// Duration
	$sql = <<<END
	SELECT videos.id FROM videos
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.account = '$myChannelId' 
	WHERE duration IS NULL
	-- LIMIT 1000;
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
		if (time() - $timeStart > $timeLimit) {
			var_dump($timeStart);
			var_dump(time());
			var_dump($timeStart - time());
			var_dump($strVideos);
			break;
		}

		$queryParams = [
			'id' => $strVideos
		];

		$rspVideos = $service->videos->listVideos('contentDetails,statistics', $queryParams);

		foreach ($rspVideos->items as $video) {
			$arrVideos2[$video->id]['duration'] = _covtime($video->contentDetails->duration);
		}
	}

	// Views
	$strVideos = '';
	$i = 0;
	$sql = <<<END
	SELECT videos.id FROM videos
	INNER JOIN channels ON channels.playlist_id = videos.playlist_id AND channels.account = '$myChannelId' 
	WHERE views IS NULL
	-- LIMIT 1000;
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
		if (time() - $timeStart > $timeLimit) {
			var_dump($timeStart);
			var_dump(time());
			var_dump($timeStart - time());
			var_dump($strVideos);
			break;
		}

		$queryParams = [
			'id' => $strVideos
		];

		$rspVideos = $service->videos->listVideos('contentDetails,statistics', $queryParams);

		foreach ($rspVideos->items as $video) {
			$arrVideos3[$video->id]['views'] = $video->statistics->viewCount;
		}
	}

	if (count($arrVideos) !== 0) {
		foreach ($arrVideos as $strVideoId => $arrVideo) {
			$sql = "UPDATE videos SET my_playlist_id = \"{$arrVideo['my_playlist_id']}\" WHERE id = \"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}

		echo 'Upated videos ratings: ' . count($arrVideos) . '<br>';
	} else {
		echo 'Videos ratings up to date.<br>';
	}

	if (count($arrVideos2) !== 0) {
		foreach ($arrVideos2 as $strVideoId => $arrVideo) {
			$sql = "UPDATE videos SET duration = \"{$arrVideo['duration']}\" WHERE id = \"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}

		echo 'Upated videos duration: ' . count($arrVideos2) . '<br>';
	} else {
		echo 'Videos duration up to date.<br>';
	}

	if (count($arrVideos3) !== 0) {
		foreach ($arrVideos3 as $strVideoId => $arrVideo) {
			$sql = "UPDATE videos SET views = \"{$arrVideo['views']}\" WHERE id = \"$strVideoId\";";
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
		}

		echo 'Upated videos views: ' . count($arrVideos3) . '<br>';
	} else {
		echo 'Videos views up to date.<br>';
	}
}

function _updateAll($service, $pdo, &$htmlBody, $myChannelId)
{
	_updatePlaylistsDetails($service, $pdo, $htmlBody, $myChannelId);
	_updateVideos($service, $pdo, $htmlBody, $myChannelId);
	_updateVideosDetails($service, $pdo, $htmlBody, $myChannelId);
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
				$stmt = $pdo->prepare($sql);
				$stmt->execute();
			}
		}

		if (isset($strPlaylist)) {
			$arrReversedVideoId = array_reverse($arrVideoId);
			$playlistItem = new Google_Service_YouTube_PlaylistItem();
			$playlistItemSnippet = new Google_Service_YouTube_PlaylistItemSnippet();
			$playlistItemSnippet->setPlaylistId($strPlaylist);
			$resourceId = new Google_Service_YouTube_ResourceId();
			$resourceId->setKind('youtube#video');

			foreach ($arrReversedVideoId as $videoId) {
				$resourceId->setVideoId($videoId);
				$playlistItemSnippet->setResourceId($resourceId);
				$playlistItem->setSnippet($playlistItemSnippet);

				try {
					$response = $service->playlistItems->insert('snippet', $playlistItem);
				} catch (Exception $e) {
					echo "can't find video_id: {$videoId}<br>";
				}
			}
		}

		if ($strBtn == 'btnSort') {
			echo "Upated $strTable Order ($i).<br>";
		} elseif ($strBtn == 'btnType') {
			echo "Upated $strTable Type ($i).<br>";
		} elseif (count($arrVideoId) > 0) {
			echo "Inserted " . count($arrVideoId) . " videos into playlist.<br>";
		} else {
			echo "Upated $strTable Status ($i).<br>";
		}

		echo $htmlBody;
	}
}
?>