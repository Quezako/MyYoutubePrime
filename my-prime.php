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

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
	try {
		// _updateSubscriptions($service, $pdo, $htmlBody);
		_listSubscriptions($service, $pdo, $tableBody);
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


function _listSubscriptions($service, $pdo, &$tableBody) {
	
	$sql = "SELECT * FROM channels";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
	$result = $stmt->fetchAll();
	
    $rows = array();
	
    foreach ($result as $row) {
        $cells = array();
		
        foreach ($row as $cell) {
            $cells[] = "<td>{$cell}</td>";
        }
		
        $rows[] = "<tr>" . implode('', $cells) . "</tr>";
    }
	
	$tableBody = implode('', $rows);
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
	<!-- <link href="css/widget.group.css" rel="stylesheet"> -->
	<script src="js/parsers/parser-input-select.min.js"></script>
	<script src="js/parsers/parser-date-weekday.min.js"></script>
	<script src="js/widgets/widget-grouping.min.js"></script>
	
	<link href="css/my-prime.css" rel="stylesheet">
	<script src="js/my-prime.js"></script>
	<pre>
		<?=$htmlBody?>
	</pre>
	<!--
	<h1>Demo</h1>

	<ul>
		<li>Clicking on a sortable header cells will sort the column and group the rows based on the group setting.</li>
		<li>Clicking on a group header will toggle the view of the content below it.</li>
		<li>Using <kbd>Shift</kbd> plus Click on a group header will toggle the view of all groups in that table.</li>
	</ul>

	<span class="demo-label">Numeric column:</span> <div id="slider0"></div> <span class="numberclass"></span> (includes subtotals)<br>
	<span class="demo-label">Animals column:</span> <div id="slider1"></div> <span class="animalclass"></span><br>
	<span class="demo-label">Date column:</span> <div id="slider2"></div> <span class="dateclass"></span><sup class="results">&dagger;</sup>
	<br><br>
	<button type="button" class="group_reset">Reset Saved Collapsed Groups</button>
	-->
	<div id="demo">
	<table id="groups">
		<thead>
			<tr>
				<th class="group-word"></th> <!-- checkbox status -->
				<th class="group-number">Quality (number)</th> <!-- notice this uses the same class name as the Numeric column, it's just left at 1 -->
				<th class="group-number-10">Numeric (every <span>10</span>)</th>
				<th class="group-letter-1">Priority (letter)</th>
				<th class="group-letter-1">Animals (first <span>letter</span>)</th>
				<th class="group-word-1">Natural Sort (first word)</th>
				<th class="group-word-2">Inputs (second word)</th>
				<!-- try "group-date", "group-date-year", "group-date-month", "group-data-monthyear", "group-date-day", "group-date-week" or "group-date-time" -->
				<th class="group-date">Date (<span>Full</span>)</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th></th>
				<th>Quality</th>
				<th>Numeric</th>
				<th>Priority</th>
				<th>Animals</th>
				<th>Natural Sort</th>
				<th>Inputs</th>
				<th>Date</th>
			</tr>
		</tfoot>
		<tbody>
			<tr><td><input type="checkbox" checked=""></td><td>1</td><td>10</td><td><select><option selected="">A</option><option>B</option><option>C</option></select></td><td>Koala</td><td>abc 123</td><td><input type="text" value="item: truck"></td><td>1/13/2013 12:01 AM</td></tr>
			<?=$tableBody?>
		</tbody>
	</table>
	</div>
</body>
</html>
