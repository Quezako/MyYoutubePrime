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

$service = 'remove_me';
$htmlBody = '';
$htmlTable = '';
$htmlSelect = '';

if (isset($_COOKIE['radio_music']) && $_COOKIE['radio_music'] == '{"checked":true}') {
	$myChannelId = 'UCjp4sUlXfWngnLyPfA5SrIQ'; // Music
} else {
	$myChannelId = 'UCDhEgLlKq6teYnMOUS3MZ_g'; // Quezako
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
	$sql = "SELECT * FROM channel_types WHERE account = '$myChannelId'";
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
	<table id="tblSubs_$myChannelId">
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
		</tbody>
	</table>
END;
}

function _listPlaylists($service, $pdo, &$htmlTable, $myChannelId)
{
    $htmlTable = <<<END
	<table id="tblPlaylists_$myChannelId">
		<thead>
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
	<table id="tblTracks_$myChannelId">
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
		
		<!-- COOKIES -->
		<script src="js/js.cookie-2.2.1.min.js"></script>
		
		<!-- My Prime -->
		<link href="css/my-prime.css" rel="stylesheet">
		<script src="js/my-prime.js"></script>
	</head>
	<body>
		<input type="radio" name="account" id="video" value="video" /> Video <input type="radio" name="account" id="music" value="music" /> Music | 
		<a href="?">Home</a> | 
		List: <a href="?action=_listSubscriptions">🔖Subscriptions</a> | 
		<a href="?action=_listPlaylists">🔀Playlists</a> | 
		<a href="?action=_listVideos">📖Tracks</a>
		<br />	
		Update: 
		<button type="button" id="_updateSubscriptions" class="download">🔖Upd Subscriptions</button> | 
		<button type="button" id="_updatePlaylists" class="download">🔀Upd Playlists</button>  | 
		<button type="button" id="_updateAll" class="download">🔂Upd ALL</button>  | 
		<button type="button" id="_updatePlaylistsDetails" class="download">🔂Upd Pl. Details</button>  | 
		<button type="button" id="_updateVideos" class="download">📖Upd Tracks</button>  | 
		<button type="button" id="_updateVideosDetails" class="download">📄Upd Tracks Details</button> 
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
					<?php
					// select list: playlists.
					$sql = "SELECT * FROM filters WHERE account = '$myChannelId'";
					$stmt = $pdo->prepare($sql);
					$stmt->execute();
					$resChannels = $stmt->fetchAll();
					
					foreach ($resChannels as $row) {
						echo "<option value=\"{$row["rules"]}^{$row["playlist"]}\">{$row["label"]}</option>";
					}
					?>
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
					<option value="1000">1000</option>
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
