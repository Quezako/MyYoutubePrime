<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// Call set_include_path() as needed to point to your client library.
// require_once 'src/Google/autoload.php';
// require_once 'src/Google/Client.php';
// require_once 'src/Google/Service/YouTube.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

session_start();

// SQLite.
try {
    $pdo = new PDO('sqlite:' . dirname(__FILE__) . '/db-quezako.sqlite');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
} catch (Exception $e) {
    echo "Can't access SQLite DB: " . $e->getMessage();
    die();
}

$pdo->query("CREATE TABLE IF NOT EXISTS token (
    token VARCHAR( 500 ),
    type VARCHAR( 16 )
);");

$stmt = $pdo->prepare("SELECT token FROM token WHERE type = :type");
$stmt->execute(array(
    'type' => 'token'
));
$result = $stmt->fetch();
$dbToken = isset($result['token']) ? $result['token'] : null;

$stmt->execute(array(
    'type' => 'refreshtoken'
));
$result = $stmt->fetch();
$dbRefreshToken = isset($result['token']) ? $result['token'] : null;

if (!isset($dbRefreshToken)) {
    if ($dbToken) {
        $refreshToken = json_decode($dbToken)->refresh_token;
        $dbRefreshToken = isset($refreshToken) ? $refreshToken : null;
    }
}

/*
 * You can acquire an OAuth 2.0 client ID and client secret from the
 * {{ Google Cloud Console }} <{{ https://cloud.google.com/console }}>
 * For more information about using OAuth 2.0 to access Google APIs, please see:
 * <https://developers.google.com/youtube/v3/guides/authentication>
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
$redirect = filter_var('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setDeveloperKey($DEVKEY);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$client->setRedirectUri($redirect);
$client->setAccessType('offline');

$timediff = 9999;
$token = '';

if (isset($_GET['code']) && ! isset($dbToken)) {
    if (isset($_GET['state'])) {
        if (strval($_SESSION['state']) !== strval($_GET['state'])) {
            die('The session state did not match.');
        }
    }
    
    $client->authenticate($_GET['code']);
    
    if (isset($dbToken)) {
        $sql = "UPDATE token SET token=:token WHERE type=:type";
    } else {
        $sql = "INSERT INTO token (token,type) VALUES (:token,:type)";
    }
    
    $dbToken = $client->getAccessToken();
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        //':token' => $dbToken,
        ':token' => json_encode($dbToken),
        ':type' => 'token'
    ));
    
    header('Location: '.$redirect);
}

if (isset($dbToken)) {
    $client->setAccessToken($dbToken);
    $token = $dbToken;
    $timeCreated = json_decode($token)->created;
    
    $t = time();
    $timediff = $t - $timeCreated;
    $refreshToken = $dbRefreshToken;
}

// Resets token if expired.
if ($timediff > 3600 && $token !== '') {
    // If a refresh token is in there.
    if (isset($dbRefreshToken)) {
        $token = $dbToken;
        $refreshCreated = json_decode($token)->created;
        $refreshtimediff = $t - $refreshCreated;
        
        // If refresh token is expired.
        if ($refreshtimediff > 3600) {
            $client->refreshToken($refreshToken);
            $newtoken = $client->getAccessToken();
            $token = $newtoken;
            // If the refresh token hasn't expired, set token as the refresh token.
        } else {
            $client->setAccessToken($token);
        }
    }
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
	// echo "ok";
    
    if (isset($dbToken)) {
        $sql = "UPDATE token SET token=:token WHERE type=:type";
    } else {
        $sql = "INSERT INTO token (token,type) VALUES (:token,:type)";
    }
    
    $dbToken = $client->getAccessToken();
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        // ':token' => $dbToken,
		':token' => json_encode($dbToken),
        ':type' => 'token'
    ));
    
    //if (isset(json_decode($client->getAccessToken())->refresh_token)) {
	if (isset(($client->getAccessToken())->refresh_token)) {
        if (isset($dbRefreshToken)) {
            $sql = "UPDATE token SET token=:token WHERE type=:type";
        } else {
            $sql = "INSERT INTO token (token,type) VALUES (:token,:type)";
        }
        
        $dbRefreshToken = json_decode($client->getAccessToken())->refresh_token;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':token' => $dbRefreshToken,
            ':type' => 'refreshtoken'
        ));
    } elseif (! isset($dbRefreshToken)) {
        unset($dbToken);
        
        $stmt = $pdo->prepare("DELETE FROM token WHERE type=:type");
        $stmt->execute(array(
            ':type' => 'token'
        ));
        $client->revokeToken();
    }
} else {
    // If the user hasn't authorized the app, initiate the OAuth flow.
    $state = mt_rand();
    $client->setState($state);
    $_SESSION['state'] = $state;
    
    $authUrl = $client->createAuthUrl();
    $htmlBody = "<p>Authorization Required</p><p>You need to <a href='$authUrl'>authorize access</a> before proceeding.<p>";
	die;
}


$htmlBody = '';

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);
$playlistId = 'PL52YqI0PbEYU1_3bne33bXQYAviFN8AD4';
// Get all the playlists and generate a select list.
$resPlaylistItem = $youtube->playlistItems->listPlaylistItems('snippet', [
	'playlistId' => $playlistId,
	'maxResults' => 50,
	'part' => 'contentDetails'
]);

$arrVideoId = [];

foreach ($resPlaylistItem['items'] as $playlistItem) {
	$arrVideoId[$playlistItem['id']] = $playlistItem['contentDetails']['videoId'];
}

$strVideoId = implode(',', $arrVideoId);

$resVideo = $youtube->videos->listVideos('contentDetails', [
    'id' => $strVideoId
]);

$arrVideo = [];

foreach ($resVideo['items'] as $video) {
	$start = new DateTime('@0'); // Unix epoch
    $start->add(new DateInterval($video['contentDetails']['duration']));
    $arrVideo[$start->format('H:i:s')] = [
		'videoId' => $video['id'],
		'playlistId' => array_search($video['id'], $arrVideoId)
	];
}

ksort($arrVideo);

foreach ($arrVideo as $video) {
	try {
		$youtube->playlistItems->delete($video['playlistId']);
		$htmlBody .= "<p style='color:green'>Removed from playlist: {$video['playlistId']}</p>";
		// $htmlBody .= sprintf('<p style="text-align: left;">%s (%s)</p>', $resPlaylistItem['snippet']['title'], $resPlaylistItem['id']);
	} catch (Google_ServiceException $e) {
		$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>', htmlspecialchars($e->getMessage()));
	} catch (Google_Exception $e) {
		if ($e->getErrors()[0]['reason'] == 'videoAlreadyInPlaylist') {
			$htmlBody .= '<p style="color:orange">Video already added to Watch Later.</p>';
		} else {
			$htmlBody .= sprintf('<p style="color:red">A client error occurred: <code>%s</code></p>', htmlspecialchars($e->getMessage()));
		}
	}
	
	try {
		// This code adds a video to the playlist. First, define the resource being added to the playlist by setting its video ID and kind.
		$resourceId = new Google_Service_YouTube_ResourceId();
		$resourceId->setVideoId($video['videoId']);
		$resourceId->setKind('youtube#video');
		
		// Then define a snippet for the playlist item. Set the playlist item's title if you want to display a different value than the title of the video being added.
		// Add the resource ID and the playlist ID retrieved in step 4 to the snippet as well.
		$playlistItemSnippet = new Google_Service_YouTube_PlaylistItemSnippet();
		$playlistItemSnippet->setTitle('First video in the test playlist');
		$playlistItemSnippet->setPlaylistId($playlistId);
		$playlistItemSnippet->setResourceId($resourceId);
		
		// Finally, create a playlistItem resource and add the snippet to the resource, then call the playlistItems.insert method to add the playlist item.
		$playlistItem = new Google_Service_YouTube_PlaylistItem();
		$playlistItem->setSnippet($playlistItemSnippet);
		$resPlaylistItem = $youtube->playlistItems->insert('snippet,contentDetails', $playlistItem, array());
		
		$htmlBody .= "<p style='color:green'>Added to playlist:>";
		$htmlBody .= sprintf('%s (%s)</p>', $resPlaylistItem['snippet']['title'], $resPlaylistItem['id']);
	} catch (Google_ServiceException $e) {
		$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>', htmlspecialchars($e->getMessage()));
	} catch (Google_Exception $e) {
		if ($e->getErrors()[0]['reason'] == 'videoAlreadyInPlaylist') {
			$htmlBody .= '<p style="color:orange">Video already added to Watch Later.</p>';
		} else {
			$htmlBody .= sprintf('<p style="color:red">A client error occurred: <code>%s</code></p>', htmlspecialchars($e->getMessage()));
		}
	}
	
	// echo $htmlBody;
	// die;
}

?>

<!doctype html>
<html>
<head>
<title>Add to Watch Later</title>
<style>
p, div {
	font: 12px Arial;
	/*width: 100%;*/
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	padding: 5px;
}

div {
	width: 134px;
	height: 50px;
	float: left;
}

input, img {
	width: 134px;
	height: 40px;
	border: 0;
	background-color: #ddd;
}

#container, #message {
	width: 100%;
	height: 100%;
}
</style>
</head>
<body>
	<script>
        function convert(event) {
            setTimeout(function () {
                var val = decodeURIComponent(event.target.value);
                var vid = val.split('v=')[1];
                
                if (typeof vid === "undefined") {
                    var vid = val.split('video_ids=')[1];
                    vid = vid.split('%')[0];
                }
                
                if (typeof vid === "undefined") {
                    document.getElementById('message').innerHTML = '<p style="color:red">No video ID found in string.</p>';
                } else {
                    var andPosition = vid.indexOf('&');
                    
                    if (andPosition != -1) {
                        vid = vid.substring(0, andPosition);
                    }
                    
                    window.location.href = '//' + location.host + location.pathname + '?playlist_id=' + event.target.id + '&video_ids=' + vid
                }
            }, 500, event);
        }
    </script>
	<div id='container'><?
		echo $htmlBody;
		// echo '<pre>';
		// var_dump($arrVideo);
		// var_dump($resVideo);
		// var_dump($arrVideoId);
		// var_dump($resPlaylistItem);
	?></div>
</body>
</html>
