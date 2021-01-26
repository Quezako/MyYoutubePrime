<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();

header('Pragma: public'); 	// required
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="yt-my-subs.php.html"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Cache-Control: private', false);
header('Content-Transfer-Encoding: binary');
flush(); // Flush system output buffer
ini_set('max_execution_time', 600); //300 seconds = 5 minutes
set_time_limit(600);


// Call set_include_path() as needed to point to your client library.
require_once 'config.php';
require_once 'vendor/autoload.php';

echo '<meta charset="UTF-8"><pre>';

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

$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);

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
    $htmlBody = '';
    $htmlSelect = '';
    $htmlOptions = '';
    $htmlImages = '';
    $strPlaylist = 'Watch Later';
    $videoIds = isset($_GET['video_ids']) ? $_GET['video_ids'] : '';
    $playlistId = isset($_GET['playlist_id']) ? $_GET['playlist_id'] : '';
    // Define an object that will be used to make all API requests.
    $service = new Google_Service_YouTube($client);

    $arrSubs = [
        'UCo0U1tbk3YbqiLDhkeWOviQ' => 'Bob Lennon',
        'UCY-_QmcW09PHAImgVnKxU2g' => 'SQUEEZIE GAMING',
        'UCYGjxo5ifuhnmvhPvCc3DJQ' => 'Wankil Studio - Laink et Terracid',
        'UC_yP2DpIgs5Y1uWC0T03Chw' => 'Joueur Du Grenier',
        'UCow2IGnug1l3Xazkrc5jM_Q' => 'JOYCA',
        'UCWeg2Pkate69NFdBeuRFTAw' => 'SQUEEZIE',
        'UCZ_oIYI9ZNpOfWbpZxWNuRQ' => 'ZeratoR',
        'UCU0FhLr6fr7U9GOn6OiQHpQ' => 'Officiel DEFAKATOR',
        'UCsXVnXF2Lg8FZQZtxw5t2GA' => 'Mad Dog',
        'UCV6uM3y-8TeO7JlCtcEN-Bg' => 'ScienceClic',
		
        'UCCMxHHciWRBBouzk-PGzmtQ' => 'Bazar du Grenier',
        'UChFfLNTK64xQj7NscGmLLLg' => 'Cours de japonais !',
        'UC-4M8AN08hw39nn2v91VuMQ' => 'CYRILmp4',
        'UCSuUdvJ_ILNpxcC4kATuMvw' => 'imRodolphe - 80 Jours Japon',
        'UCgvqvBoSHB1ctlyyhoHrGwQ' => 'Amixem',
        'UCaNlbnghtwlsGF-KzAFThqA' => 'ScienceEtonnante',
        'UCSzsmZdY_v3aYN-GDz26OoQ' => 'L\'Ermite Moderne',
        'UCJIl08OJstq_9kgI9wf_xjg' => 'Groland Le Zapoï',
		'UCnrhy622SNPt04Avj5idFIg' => 'Parole de chat',
        'UC-4WUubuVGowG_R7gdgesPA' => 'Max Bird',
		
        'UCOchT7ZJ4TXe3stdLW1Sfxw' => 'Dans Ton Corps',
        'UC1Ue7TuX3iH4y8-Qrjj-hyg' => 'J\'m\'énerve pas, j\'explique',
        'UCgANFlYnbE47942AoazfNsg' => 'Lil Joyca',
        'UCdHXMcWxwZr2qQpdCCOcSkw' => 'OLYDRI',
        'UCcziTK2NKeWtWQ6kB5tmQ8Q' => 'e-penser',
        'UCeR8BYZS7IHYjk_9Mh5JgkA' => 'Scilabus',
        'UCDWnqQWk7e6rt-H-ZN6JKwg' => 'La Caverne de l\'Ermite Moderne',
        'UCI0LNmSlhS-H9mGNPWM8gzQ' => 'At0mium',
        'UC5Twj1Axp_-9HLsZ5o_cEQQ' => 'Doc Seven',
        'UCNgudhx2s_ubmiA2L_2NMmg' => 'Defakator Vite Fait',
		
        'UCw3tZ7g_FljNjzGprCuptpA' => 'Taupe10',
        'UCah8C0gmLkdtvsy0b2jrjrw' => 'Cyrus North',
        'UCaZRpfXzlHVchIJMpf-PVFw' => 'Scilabus plus',
		'UCq-8pBMM3I40QlrhM9ExXJQ' => 'La Tronche en Biais',
		'UCMFcMhePnH4onVHt2-ItPZw' => 'Hygiène Mentale',
		'UCOuIgj0CYCXCvjWywjDbauw' => 'Chat Sceptique',
        'UC_56vSO35nctESDan8agevg' => 'DeBunKer des Etoiles',
		'UCCelP6iJfYoj0Ea3qXqugXQ' => 'Mike Horn',
		'UCqA8H22FwgBVcF3GJpp0MQw' => 'Monsieur Phi',
        'UC9VMz-llpSHTIfOzuggf5zA' => 'Japania',
		
		'UCKwLqdUm-iOxVA2ERRTsFng' => 'Ichiban Japan',
        'UCfwWXdYixiKsWa_VogacrGg' => 'Le Japon fou fou fou',
		'UCeOp9CWBaW2tVIBAzCobzow' => 'L\'antisèche',
		'UCtqICqGbPSbTN09K1_7VZ3Q' => 'DirtyBiology',
        'UCYK1TyKyMxyDQU8c6zF8ltg' => 'Funny Pet Videos',
		'UCZpJLCV3gVP8R_sc8P30VpQ' => 'BOTCH',
		'UC2_OG1L8DLTzQ7UrZVOk7OA' => 'Axolotblog',
		'UCAx1h7lSxq1SHkEB2LRGfDQ' => 'Nus & Culottés',
        'UCLXDNUOO3EQ80VmD9nQBHPg' => 'Fouloscopie',
		'UCj--qtjUrXbWABX-KWLRu9A' => 'Ludovic B',
		
        // 'UC3ajq73sIK0OPwoK7GuVISw' => 'EnfluredeRenard',
		// 'UC1EacOJoqsKaYxaDomTCTEQ' => 'Le Réveilleur',
		// 'UCk4xx9_ljrqPjT2tnk34k2g' => 'Enzo Sultan Mafia Tripes',
		// 'UC0NCbj8CxzeCGIF6sODJ-7A' => 'Science4All',
		// 'UCB1K5xp5SME58OR0hEW8w9A' => 'Curieux du Japon',
        // 'UCLT3vrpSjt-NfCHsSAGJwJQ' => 'cotcotprod',
        // 'UC30EA869VRGNMw2qFYIdX5g' => 'Anto80',
		// 'UCS_7tplUgzJG4DhA16re5Yg' => 'Balade Mentale',
        // 'UC8Ux-LOyEXeioYQ4LFzpBXw' => 'Aude WTFake',
		
        // 'UCWVmHgIv4U5Bpik88ZqyjGA' => 'JinnKid',
		// 'UCkMsSGlEMPRxhqvGj6qeucQ' => '12 Parsecs',
		// 'UCP4wIoy9W9WdAfVIN2sVmEw' => 'Skyrroz',
		// 'UCGWoTWsXJi1UtCjOiBHIsfw' => 'Sofyan',
        // 'UChV2oq_a-UZfJF-UiW0u-DQ' => 'MATH',
        // 'UCyWqModMQlbIo8274Wh_ZsQ' => 'Cyprien',
        // 'UCyIqcxz-vR_o2GK4HWuZL8w' => 'CatPusic',
        // 'UCus9EeXDcLaCJhVXYd6PJcg' => 'mistermv',
        // 'UCBuee5JCBlI_VuKjxmqtyUA' => 'Best Of Antoine Daniel',
    ];

    $strSubs = implode(",", array_keys($arrSubs));
	// echo $strSubs;die;
    
    $queryParams = [
       'id' => $strSubs
    ];

    $resChannels = $service->channels->listChannels('contentDetails', $queryParams);
	// echo 'resChannels<br>';
	// print_r($resChannels);
	// die;

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

    foreach ($resChannels->items as $channel) {
        $uploadsId = $channel->contentDetails->relatedPlaylists->uploads;
        // get uploaded videos from a chan, sort by date descendant.
        $queryParams = [
            'playlistId' => $uploadsId,
            'maxResults' => '50'
        ];

        $reslistPlaylistItems = $service->playlistItems->listPlaylistItems('snippet', $queryParams);
		// echo 'reslistPlaylistItems<br>';
        /*
        Live 
        en live 

        best of:
        zerator
        moman
        gius
        mistermv 
        */
        foreach ($reslistPlaylistItems['items'] as $video) {
            if (preg_match($strFilter, $video->snippet->title) !== 1) {
                $arrVideos[$video->snippet->resourceId->videoId] = [
                    'channelTitle' => $video->snippet->channelTitle,
                    'title' => $video->snippet->title,
                    'videoId' => $video->snippet->resourceId->videoId,
                    'publishedAt' => $video->snippet->publishedAt,
                    'publishedMonth' => substr($video->snippet->publishedAt, 0, 7),
                    'isRated'  => 0,
                    'isPlaylist'  => 0,
                    'duration'  => '',
                ];
            }
        }
    }

    $arrVideoIds = [];
    $strVideos = '';
    $i = 0;

    foreach (array_keys($arrVideos) as $videoId) {
        $strVideos .= $videoId.',';
        $i++;

        if ($i % 49 == 0) {
            $arrVideoIds[] = $strVideos;
            $strVideos = '';
            $i = 0;
        }
    }

    $arrVideoIds[] = $strVideos;

    foreach ($arrVideoIds as $strVideos) {
        $ratings = $service->videos->getRating($strVideos);
		// echo 'ratings<br>';

        foreach ($ratings->items as $rating) {
            if ($rating->rating != 'none') {
                $arrVideos[$rating->videoId]['isRated'] = 1;
            }
        }

        $queryParams = [
            'id' => $strVideos
        ];

        $resVideos = $service->videos->listVideos('contentDetails', $queryParams);
		// echo 'resVideos<br>';
		
        foreach ($resVideos->items as $video) {
            $arrVideos[$video->id]['duration'] = covtime($video->contentDetails->duration);
            $arrVideos[$video->id]['publishedMonth'] = str_replace('-', '', $arrVideos[$video->id]['publishedMonth']).(999 - sprintf('%03d', covtime($video->contentDetails->duration)));
        }
    }

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

    foreach ($arrMyPlaylists as $myPlaylist) {
        $pageToken = "";

        while (!is_null($pageToken)) {
            $queryParams = [
                'maxResults' => 50,
                'pageToken' => $pageToken,
                'playlistId' => $myPlaylist
            ];
            
            $myPlaylistItems = $service->playlistItems->listPlaylistItems('contentDetails', $queryParams);
            $pageToken = $myPlaylistItems->nextPageToken;
            $arrMyPlaylistItems[] = $myPlaylistItems;
        }
    }

    foreach ($arrMyPlaylistItems as $myPlaylistItems) {
        foreach ($myPlaylistItems as $myPlaylistItemDetails) {
            $arrVideos[$myPlaylistItemDetails->contentDetails->videoId]['isPlaylist'] = 1;
        }
    }
	
    
    usort($arrVideos, function($a, $b) {
        if (isset($a['publishedMonth']) && isset($b['publishedMonth'])) {
            return $b['publishedMonth'] <=> $a['publishedMonth'];
        }
    });
		
    $strMonth = '';

    foreach ($arrVideos as $videos) {
        if (isset($videos['isRated']) && isset($videos['isPlaylist']) && $videos['isRated'] == 0 && $videos['isPlaylist'] == 0) {
            if ($strMonth !== substr($videos['publishedMonth'], 0, 6)) {
                $strMonth = substr($videos['publishedMonth'], 0, 6);
                echo "<h1>$strMonth</h1>";
            }

            echo "{$videos['channelTitle']} - {$videos['title']}<br>";
            echo "<a href='https://www.youtube.com/watch?v={$videos['videoId']}'>{$videos['videoId']}</a> - {$videos['publishedAt']} - {$videos['duration']} - {$videos['publishedMonth']}<hr>\r\n";
        }
    }
} else {
    // If the user hasn't authorized the app, initiate the OAuth flow.
    $state = mt_rand();
    $client->setState($state);
    $_SESSION['state'] = $state;
    
    $authUrl = $client->createAuthUrl();
    $htmlBody = "<p>Authorization Required</p><p>You need to <a href='$authUrl'>authorize access</a> before proceeding.<p>";
}

function covtime($youtube_time){    
    if($youtube_time) {
        $start = new DateTime('@0'); // Unix epoch
        $start->add(new DateInterval($youtube_time));
        $youtube_time = round($start->getTimestamp() / 60, 0);
    }
    
    return $youtube_time;
}