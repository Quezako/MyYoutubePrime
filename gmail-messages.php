<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

// require_once 'src/Google/autoload.php';
// require_once 'src/Google/Client.php';
// require_once 'src/Google/Service/Gmail.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

session_start();

function base64UrlDecode($string)
{
	return base64_decode(str_replace(array('-', '_'), array('+', '/'), $string));
}

// SQLite.
try {
    $pdo = new PDO('sqlite:' . dirname(__FILE__) . '/db-quezako.sqlite');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
} catch (Exception $e) {
    echo "Can't access SQLite DB: " . $e->getMessage();
    die();
}

$pdo->query("CREATE TABLE IF NOT EXISTS token2 (
    token VARCHAR( 500 ),
    type VARCHAR( 16 )
);");

$stmt = $pdo->prepare("SELECT token FROM token2 WHERE type = :type");
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

//$redirect = filter_var('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setDeveloperKey($DEVKEY);
$client->setScopes('https://www.googleapis.com/auth/gmail.readonly');
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
        $sql = "UPDATE token2 SET token=:token WHERE type=:type";
    } else {
        $sql = "INSERT INTO token2 (token,type) VALUES (:token,:type)";
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
    // Define an object that will be used to make all API requests.
    $gmail = new Google_Service_Gmail($client);
	
    $resMessages = $gmail->users_messages->listUsersMessages( 'quezako35@gmail.com', [
        'maxResults' => 50,
		// 'q' => 'label:youtube !in:inbox subject:("just uploaded a video" || "is live now"　|| "just started a Premiere")',
		'q' => 'label:youtube subject:("[YT]")',
    ]);

	foreach($resMessages->messages as $message) {
		$resMessage = $gmail->users_messages->get('quezako35@gmail.com', $message->id);
		$resMessageDetails = $resMessage->getPayload();
		
		$optParam = [];
		$data = [];
		$headers = [];
		$body = ['text/plain' => [], 'text/html' => []];
		$files = [];
		
		//echo "<pre>";
		//var_dump($resMessageDetails);
		//var_dump($resMessageDetails['headers']);
		//var_dump($resMessageDetails->headers);
		//var_dump($resMessageDetails->headers->Subject);
		//echo ($resMessageDetails['headers'][4]['value'])."<br />";
		
		foreach ($resMessageDetails->headers as $headers) {
			if ($headers['name'] == 'Subject') {
				echo $headers['value']."<br />";
			}
		}
		
		//die;
		
		if (!is_null($resMessageDetails['body']['data'])) {
			array_push($body['text/plain'], nl2br(base64UrlDecode($resMessageDetails['body']['data'])));
		}
		
		foreach ($resMessageDetails['parts'] as $key => $value) {
			if (isset($value['body']['data'])) {
				array_push($body[$value['mimeType']], nl2br(base64UrlDecode($value['body']['data'])));
			} else {
				array_push($files, $value['partId']);
			}
		}
		
		if (empty($body['text/html'])) {		
			foreach ($body['text/plain'] as $key => $value) {
				echo preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1">$1</a>', $value)."<hr>";
			}
		}
		
		
		foreach ($body['text/html'] as $key => $bodyHtml) {
			$bodyHtml = preg_replace( "/\r|\n/", "", $bodyHtml );
			//$bodyHtml = str_replace("/r", '', $bodyHtml);
			//$bodyHtml = str_replace("/n", '', $bodyHtml);
			$bodyHtml = str_replace(PHP_EOL, '', $bodyHtml);
			$bodyHtml = str_replace('<br />', '', $bodyHtml)."<hr>";
			$bodyHtml = str_replace('<div style="width: 480px;height: 270px;overflow: hidden;"></div>', '', $bodyHtml);
			$bodyHtml = str_replace("<br><br>", '<br>', $bodyHtml);
			echo $bodyHtml;
		}
	}
	
    if (isset($dbToken)) {
        $sql = "UPDATE token2 SET token=:token WHERE type=:type";
    } else {
        $sql = "INSERT INTO token2 (token,type) VALUES (:token,:type)";
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
            $sql = "UPDATE token2 SET token=:token WHERE type=:type";
        } else {
            $sql = "INSERT INTO token2 (token,type) VALUES (:token,:type)";
        }
        
        $dbRefreshToken = json_decode($client->getAccessToken())->refresh_token;
        //$dbRefreshToken = ($client->getAccessToken())->refresh_token;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            ':token' => $dbRefreshToken,
            ':type' => 'refreshtoken'
        ));
    } elseif (! isset($dbRefreshToken)) {
        unset($dbToken);
        
        $stmt = $pdo->prepare("DELETE FROM token2 WHERE type=:type");
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
    echo"<p>Authorization Required</p><p>You need to <a href='$authUrl'>authorize access</a> before proceeding.<p>";
}
