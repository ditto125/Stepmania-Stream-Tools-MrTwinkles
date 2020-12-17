<?php
   
include("config.php");
include("misc_functions.php");

if(!isset($_GET["security_key"]) || $_GET["security_key"] != $security_key || empty($_GET["security_key"])){
    die("Fuck off");
}
//limit to how many random songs can be requested at once
$max_num = 3;

function clean($string) {
   global $conn;
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
   $string = mysqli_real_escape_string($conn, $string); // Removes sql injection atempts.
}

if(!isset($_GET["user"])){
	die("Error");
}

if(!isset($_GET["random"]) && !isset($_GET["num"]) && !is_numeric($_GET["num"])){
	die();
}

function request_song($song_id, $requestor, $tier, $twitchid, $broadcaster, $stepstype){

	$userobj = check_user($twitchid, $requestor);

	if($userobj["banned"] == "true"){
        die();
	}   
	if($userobj["whitelisted"] != "true"){
        check_cooldown($requestor);
		}

	global $conn;

	$sql0 = "SELECT COUNT(*) AS total FROM sm_requests WHERE song_id = '$song_id' AND state <> 'canceled' AND request_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
	$retval0 = mysqli_query( $conn, $sql0 );
	$row0 = mysqli_fetch_assoc($retval0);
	if(($row0["total"] > 0) && ($userobj["whitelisted"] != "true")){die("That song has already been requested recently!");}

        $sql = "INSERT INTO sm_requests (song_id, request_time, requestor, twitch_tier, broadcaster, request_type, stepstype) VALUES ('{$song_id}', NOW(), '{$requestor}', '{$tier}', '{$broadcaster}', 'random', '{$stepstype}')";
        $retval = mysqli_query( $conn, $sql );

}

$conn = mysqli_connect(dbhost, dbuser, dbpass, db);
if(! $conn ) {die('Could not connect: ' . mysqli_error($conn));}

//check if the active channel category/game is StepMania, etc.
if(isset($_GET["game"])){
	$game = $_GET["game"];
    if(in_array($game,$categoryGame)==FALSE){
        die("Hmmm...I don't think it's possible to request songs in ".$game.".");
    }
}

$user = $_GET["user"];
$tier = $_GET["tier"];
if(isset($_GET["userid"])){
	$twitchid = $_GET["userid"];
}else{
	$twitchid = 0;
}
//get broadcaster and adjust query filters
if(isset($_GET["broadcaster"]) && !empty($_GET["broadcaster"])){
	$broadcaster = $_GET["broadcaster"];
	check_request_toggle($broadcaster);
	if (array_key_exists($broadcaster,$broadcasters)){
		$profileName = $broadcasters[$broadcaster];
	}else{
		$profileName = "%";
	}
}else{
	$broadcaster = "";
	$profileName = "%";
}

//get number of random requests, if not specified, set as 1
if (isset($_GET["num"]) && !empty($_GET["num"]) && is_numeric($_GET["num"]) && $_GET["num"] > 0){
	$num = $_GET["num"];
}elseif(!isset($_GET["num"]) && empty($_GET["num"])){
	$num = 1;
}else{ 
	die("Good one, ".$user.", but only positive integers are allowed!");
}

if($num > $max_num){
	die("$user can't request that many songs at once!");
}

$broadcasterLimits = get_broadcaster_limits($broadcaster);
if(!empty($broadcasterLimits) && is_array($broadcasterLimits)){
	$stepstype = $broadcasterLimits['stepstype'];
	$meter = $broadcasterLimits['meter_max'];
//	if(empty($stepstype)){$stepstype = '%';}
//	if(empty($meter)){$meter = '99999999999';}
}

//standard random request from songs that have at least been played once
if($_GET["random"] == "random"){

        $sql = "SELECT sm_songs.id AS id,sm_songs.title AS title,sm_songs.subtitle AS subtitle,sm_songs.artist AS artist,sm_songs.pack AS pack,SUM(sm_songsplayed.numplayed) AS numplayed 
		FROM sm_songs 
		JOIN sm_songsplayed ON sm_songsplayed.song_id=sm_songs.id 
		JOIN sm_scores ON sm_scores.song_id=sm_songs.id 
		WHERE sm_songsplayed.song_id > 0 AND sm_songsplayed.username LIKE '{$profileName}' AND banned<>1 AND installed=1 AND  sm_songsplayed.numplayed>1 AND percentdp>0 
		GROUP BY sm_songs.id 
		ORDER BY RAND()
		LIMIT 100";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
    		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $stepstype);
					echo ("{$user} randomly requested " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("Didn't find any random songs!");
}

die();
}

//standard portal request, any installed/unbanned songs can be selected
if($_GET["random"] == "portal"){

        $sql = "SELECT * FROM sm_songs WHERE installed=1 AND banned<>1 ORDER BY RAND() LIMIT 100";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $stepstype);
					echo ("$user opened a portal to " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("Didn't find any portal songs!");
}

die();
}

//standard top request of 1 random 100 most played songs
if($_GET["random"] == "top"){

        $sql = "SELECT id,title,subtitle,artist,pack,numplayed,stepstype
				FROM sm_songs 
				JOIN 
					(SELECT song_id,SUM(numplayed) AS numplayed,stepstype
					FROM sm_songsplayed
					WHERE song_id>0 AND numplayed>1 AND username LIKE '{$profileName}' 
					GROUP BY song_id
					ORDER BY numplayed desc
					LIMIT 100) AS t2
				ON t2.song_id=sm_songs.id 
				WHERE banned<>1 AND installed=1  
				ORDER BY RAND()";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $row['stepstype']);
					echo ("$user picked a top request " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("Didn't find any top songs!");
}

die();
}

//random worst 25 scored top 100 songs
if($_GET["random"] == "gitgud"){

        $sql = "SELECT id,title,subtitle,artist,pack,percentdp,stepstype 
				FROM sm_songs 
				JOIN 
				(SELECT song_id,MAX(percentdp) AS percentdp,stepstype 
					FROM sm_scores 
					WHERE EXISTS 
						(SELECT song_id,SUM(numplayed) AS numplayed   
						FROM sm_songsplayed 
						WHERE song_id>0 AND numplayed>1 AND username LIKE '{$profileName}'  
						GROUP BY song_id 
						ORDER BY numplayed DESC 
						LIMIT 100) 
					AND grade <> 'Failed' AND percentdp > 0 AND username LIKE '{$profileName}'  
					GROUP BY song_id 
					ORDER BY percentdp ASC 
					LIMIT 25) AS t2 
				ON t2.song_id = sm_songs.id 
				WHERE banned <> 1 AND installed = 1 
				ORDER BY RAND()";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $row['stepstype']);
					echo ("$user dares you to beat ".number_format($row['percentdp']*100,2)."% at " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("Didn't find any songs to git gud at!");
}

die();
}

//edge-case random request just for djfipu
if($_GET["random"] == "djfipu"){
		
		$random = $_GET["random"];
		$random = htmlspecialchars($random);
		//$random = clean($random);
		
        $sql = "SELECT * FROM sm_songs WHERE installed=1 AND banned<>1 AND (artist IN('e-rotic','erotic','crispy','aqua','missing heart') OR title IN('exotic ethnic','Dadadadadadadadadada','Bi') OR title LIKE '%euro%' OR subtitle LIKE '%euro%') ORDER BY RAND() LIMIT 100";
		$retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
    		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $stepstype);
					echo ("$user requested djfipu's favorite song " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("djfipu, what's this all about!?");
}

die();
}

//roll command responds with 3 random songs that the user can then request with "requestid"
if($_GET["random"] == "roll"){
	
	$sql = "SELECT sm_songs.id AS id,sm_songs.title AS title,sm_songs.subtitle AS subtitle,sm_songs.artist AS artist,sm_songs.pack AS pack,SUM(sm_songsplayed.numplayed) AS numplayed 
		FROM sm_songs 
		JOIN sm_songsplayed ON sm_songsplayed.song_id=sm_songs.id 
		JOIN sm_scores ON sm_scores.song_id=sm_songs.id 
		WHERE sm_songsplayed.song_id > 0 AND sm_songsplayed.username LIKE '{$profileName}' AND banned<>1 AND installed=1 AND  sm_songsplayed.numplayed>1 AND percentdp>0 
		GROUP BY sm_songs.id 
		ORDER BY RAND()
		LIMIT 100";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
		echo "$user rolled (request with !requestid [song id]):\n";
		$i=1;
		while($row = mysqli_fetch_assoc($retval)) {
			if($i>$num){die();}
		echo " [ ".$row["id"]. " => " .trim($row["title"]." ".$row["subtitle"])." from ".$row["pack"]." ]";
		$i++;
		}
	}
die();
}

//special random for regulars: picks a random song from top 10 requested by requestor
if($_GET["random"] == "theusual"){
	$userLC = strtolower($user);
	
	$sql = "SELECT id,title,subtitle,artist,pack,idcount    
			FROM sm_songs  
			JOIN 
				(SELECT song_id, COUNT(song_id) AS idcount 
				FROM sm_requests 
				WHERE song_id>0 AND LOWER(requestor) LIKE '{$userLC}' AND state<>'canceled' 
				GROUP BY song_id 
				ORDER BY idcount DESC  
				LIMIT 20) AS t2 
			ON t2.song_id=sm_songs.id  
			WHERE banned<>1 AND installed=1 AND idcount>1 
			ORDER BY RAND()";
	$retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
		$i=1;
		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
			if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
				request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $stepstype);
				echo ("Of course {$user} would request " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . ". How original! ");
				$i++;
			}
		}
	} else {
		die("$user hasn't requested enough songs or isn't a reqular around here.");
	}

	die();
}

//specific pack(s) random request/catch-all REGEX pack name matching
//randomben, randomddr, randomnitg, randomhellkite...
if(!empty($_GET["random"]) && $_GET["random"] != "random"){
		
		$random = $_GET["random"];
		$random = htmlspecialchars($random);
		//$random = clean($random);
		
        $sql = "SELECT sm_songs.id AS id,title,subtitle,pack FROM sm_songs 
		JOIN sm_notedata ON sm_notedata.song_id = sm_songs.id 
		WHERE installed=1 AND banned<>1 AND (pack REGEXP '{$random}' OR sm_songs.credit REGEXP '{$random}' OR sm_notedata.credit REGEXP '{$random}') 
		GROUP BY sm_songs.id 
		ORDER BY RAND()
		LIMIT 100";
		$retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
    		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $stepstype);
					echo ("$user randomly requested " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("Uh oh. RNGesus was not on your side!");
}

die();
}

mysqli_close();
die();

?>
