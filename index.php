<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('max_execution_time', 300); //300 seconds = 5 minutes
set_time_limit(300);

// server should keep session data for AT LEAST x seconds.
ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
// each client should remember their session id for EXACTLY x seconds.
session_set_cookie_params(30 * 24 * 60 * 60);
session_start();

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
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

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-' . $client->prepareScopes();

// $service = 'remove_me';
$htmlBody = '';
$htmlTable = '';
$htmlSelect = '';

if (isset($_COOKIE['radio_music']) && $_COOKIE['radio_music'] == '{"checked":true}') {
	$myChannelId = 'UCjp4sUlXfWngnLyPfA5SrIQ'; // Music
} else {
	$myChannelId = 'UCDhEgLlKq6teYnMOUS3MZ_g'; // Quezako
}

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
/*
if ($client->isAccessTokenExpired()) {
	// var_dump($client->getAccessToken());
	// $newAccessToken = json_decode($client->getAccessToken());
	$newAccessToken = ($client->getAccessToken());
	// $client->refreshToken($newAccessToken->refresh_token);
	$client->refreshToken($newAccessToken['access_token']);
}
*/
// SQLite.
try {
    $pdo = new PDO('sqlite:' . dirname(__FILE__) . '/my-prime.db');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
} catch (Exception $e) {
    echo "Can't access SQLite DB: " . $e->getMessage();
    die();
}

// Check to ensure that the access token was successfully acquired.// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
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

function _listSubscriptions($service, $pdo, &$htmlTable, $myChannelId, &$htmlSelect)
{
	$sql = "SELECT * FROM channel_types WHERE account = '$myChannelId' ORDER BY sort ASC";
    // var_dump($sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannelTypes = $stmt->fetchAll();
    
    $htmlSelect = '<select name="selChannelTypes" id="selChannelTypes">';
    
    foreach ($resChannelTypes as $row) {
        $htmlSelect .= "<option value='{$row["id"]}'>{$row["label"]}</option>";
    }
    
    $htmlSelect .= '</select>';
    
    $htmlTable = <<<END
	<table id="tblSubs_$myChannelId" class="table table-bordered table-striped">
		<thead class="thead-dark">
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
		</tbody>
	</table>
END;
}

function _listPlaylists($service, $pdo, &$htmlTable, $myChannelId)
{
    $htmlTable = <<<END
	<table id="tblPlaylists_$myChannelId" class="table table-bordered table-striped">
		<thead class="thead-dark">
			<tr>
				<th class="group-word"><input type='checkbox' id='checkAll' /> Action</th>
				<th class="group-letter-1">Playlist</th>
				<th class="group-date-month">Status</th>
				<th class="group-date-month">Sort</th>
			</tr>
		</thead>
		<tbody>
		</tbody>
	</table>
END;
}

function _listVideos($service, $pdo, &$htmlTable, &$htmlSelect, $myChannelId)
{
    $htmlTable = <<<END
	<table id="tblTracks_$myChannelId" class="table table-bordered table-striped">
		<thead class="thead-dark">
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
		</tbody>
	</table>
END;

    // select list: playlists.
    $sql = "SELECT id, name FROM playlists WHERE account = '$myChannelId' AND status > 0 ORDER BY sort ASC LIMIT 200;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resChannels = $stmt->fetchAll();

    $htmlSelect = '<select name="selPlaylist" id="selPlaylist">';
    
    foreach ($resChannels as $row) {
        $htmlSelect .= "<option value='{$row["id"]}'>{$row["name"]}</option>";
    }
    
    $htmlSelect .= '</select>';
}
?>
<!doctype html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>My Prime</title>
		<link rel="shortcut icon" type="image/ico" href="assets/favicon/favicon.ico"/>
		
		<!-- Tablesorter: required -->
		<script src="js/jquery-latest.min.js"></script>
		<script src="js/jquery.tablesorter.min.js"></script>
		<script src="js/jquery.tablesorter.widgets.min.js"></script>

		<!-- Bootstrap -->
		<link rel="stylesheet" href="css/bootstrap.min.css">
		<link rel="stylesheet" href="css/theme.bootstrap_4.min.css">
		<script src="js/bootstrap.min.js"></script>
		<script src="js/bootstrap.bundle.min.js"></script>


		<!-- Tablesorter: optional -->
		<link rel="stylesheet" href="css/jquery.tablesorter.pager.min.css">
		<script src="js/widgets/widget-filter.min.js"></script>
		<script src="js/widgets/widget-storage.js"></script>
		<script src="js/extras/jquery.tablesorter.pager.min.js"></script>
		
		<!-- DRAG -->
		<script src="js/jquery-ui.min.js"></script>
		
		<!-- COOKIES -->
		<script src="js/js.cookie-2.2.1.min.js"></script>
		
		<!-- My Prime -->
		<link rel="stylesheet" href="css/my-prime.css">
		<script src="js/my-prime.js"></script>
	</head>
	<body>
		<div id='status' class="alert alert-primary alert-dismissible fade show" role="alert">
			Ready.
			<button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<nav class="navbar navbar-expand-lg navbar-light bg-light">
			<input type="radio" name="account" id="video" value="video" />&nbsp;Video&nbsp;<input type="radio" name="account" id="music" value="music" />&nbsp;Music&nbsp;&nbsp;&nbsp;
			<ul class="nav nav-tabs">
				<li class="nav-item">
					<a class="nav-link active" href="?">Home</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="?action=_listSubscriptions">🔖Subscriptions</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="?action=_listPlaylists">🔀Playlists</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="?action=_listVideos">📖Tracks</a>
				</li>
			</ul>
			&nbsp;&nbsp;&nbsp;Update&nbsp;&nbsp;
			<div class="btn-group" role="group" aria-label="Basic example">
					<button type="button" id="_updateSubscriptions" class="btn btn-dark">🔖Subs</button>
					<button type="button" id="_updatePlaylists" class="btn btn-dark">🔀Playlists</button>
					<button type="button" id="_updateAll" class="btn btn-dark">🔂ALL</button>
					<button type="button" id="_updateVideos" class="btn btn-dark">📖Tracks</button>
					<button type="button" id="_updatePlaylistsDetails" class="btn btn-dark">🔂Pl. Det.</button>
					<button type="button" id="_updateVideosDetails" class="btn btn-dark">📄Tracks Det.</button>
			</div>
		</nav>
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
				Filters:
				<select id="selFilter" class="form-control-sm">
					<option value="">-----</option>
					<?php
					// select list: playlists.
					$sql = "SELECT * FROM filters WHERE account = '$myChannelId' ORDER BY sort ASC";
					$stmt = $pdo->prepare($sql);
					$stmt->execute();
					$resChannels = $stmt->fetchAll();
					
					foreach ($resChannels as $row) {
						echo "<option value=\"{$row["rules"]}^{$row["playlist"]}\">{$row["label"]}</option>";
					}
					?>
				</select>
				<div class="pager ts-pager">
					<div class="form-inline">
						<div class="btn-group btn-group-sm mx-1" role="group">
							<button type="button" class="btn btn-secondary first" title="first">⇤</button>
							<button type="button" class="btn btn-secondary prev" title="previous">←</button>
						</div>
						<span class="pagedisplay"></span>
						<div class="btn-group btn-group-sm mx-1" role="group">
							<button type="button" class="btn btn-secondary next" title="next">→</button>
							<button type="button" class="btn btn-secondary last" title="last">⇥</button>
						</div>
						<select class="form-control-sm mx-1 pagesize" title="Select page size">
							<option value="50">50</option>
							<option value="100">100</option>
							<option value="200">200</option>
							<option value="500">500</option>
							<option value="1000">1000</option>
							<option value="all">All Rows</option>
						</select>
						<select class="form-control-sm mx-1 pagenum gotoPage" title="Select page number"></select>
					</div>
				</div>
				<div class="btn-group" role="group" aria-label="Basic example">
					<button type="button" id="btnCheck" class="btn btn-dark"><input type='checkbox' id='checkAll' /> Check All </button>
					<button type="button" id="btnIgnore" class="btn btn-dark">Hide selected</button>
					<button type="button" id="btnUnignore" class="btn btn-dark">Show selected</button>
					<?php
					if (in_array($action, ['_listSubscriptions', '_listPlaylists'])) {
						?>
					<button type="button" id="btnSort" class="btn btn-dark">Save order</button>
					<?php
					}
					?>
					<?=$htmlSelect;?>
					<?php
					if (in_array($action, ['_listSubscriptions', '_listPlaylists'])) {
						?>
					<button type="button" id="btnType" class="btn btn-dark">Save checked to type</button>
					<?php
					} elseif ($action == '_listVideos') {
					?>
					<button type="button" id="btnPlaylist" class="btn btn-dark">Save checked to playlist</button>
					<?php
					} ?>
				</div>
			</div>
			
			<?php
            }
            
            echo $htmlTable;
            ?>
		</div>
	</body>
</html>
