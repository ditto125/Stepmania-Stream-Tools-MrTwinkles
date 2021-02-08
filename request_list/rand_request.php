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

function request_song($song_id, $requestor, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty){

	$userobj = check_user($twitchid, $requestor);

	if($userobj["banned"] == "true"){
        die();
	}   
	if($userobj["whitelisted"] != "true"){
        check_cooldown($requestor);
		}

	if(empty($request_type)){$request_type = "random";}

	global $conn;

	$sql0 = "SELECT COUNT(*) AS total FROM sm_requests WHERE song_id = '$song_id' AND state <> 'canceled' AND request_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
	$retval0 = mysqli_query( $conn, $sql0 );
	$row0 = mysqli_fetch_assoc($retval0);
	if(($row0["total"] > 0) && ($userobj["whitelisted"] != "true")){die("That song has already been requested recently!");}

        $sql = "INSERT INTO sm_requests (song_id, request_time, requestor, twitch_tier, broadcaster, request_type, stepstype, difficulty) VALUES ('{$song_id}', NOW(), '{$requestor}', '{$tier}', '{$broadcaster}', '{$request_type}', '{$stepstype}', '{$difficulty}')";
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
}elseif(!isset($_GET["num"]) || empty($_GET["num"])){
	$num = 1;
}else{ 
	die("Good one, $user, but only positive integers are allowed!");
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
$difficulty = "";

//get scoring type
global $scoreType;

//standard random request from songs that have at least been played once
if($_GET["random"] == "random"){

	$request_type = "random";

        $sql = "SELECT sm_songs.id AS id,sm_songs.title AS title,sm_songs.subtitle AS subtitle,sm_songs.artist AS artist,sm_songs.pack AS pack,SUM(sm_songsplayed.numplayed) AS numplayed 
		FROM sm_songs 
		JOIN sm_songsplayed ON sm_songsplayed.song_id=sm_songs.id 
		JOIN sm_scores ON sm_scores.song_id=sm_songs.id 
		WHERE sm_songsplayed.song_id > 0 AND sm_songsplayed.username LIKE '{$profileName}' AND banned<>1 AND installed=1 AND sm_songsplayed.numplayed>1 AND percentdp>0 
		GROUP BY sm_songs.id 
		ORDER BY RAND()
		LIMIT 100";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
		$i=1;
		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
			if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
				request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty);
				echo ("{$user} randomly requested " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
				$i++;
			}
		}
	} elseif (mysqli_num_rows($retval) == 0) {
		//didn't find any songs from the sm_songsplayed table. request system is running in "offline" mode.
		$sql = "SELECT sm_songs.id AS id,sm_songs.title AS title,sm_songs.subtitle AS subtitle,sm_songs.artist AS artist,sm_songs.pack AS pack,numplayed  
		FROM sm_songs 
		JOIN 
        	(SELECT sm_requests.song_id AS id,COUNT(sm_requests.song_id) AS numplayed 
             	FROM sm_requests
            	WHERE sm_requests.song_id > 0 AND sm_requests.broadcaster LIKE '$broadcaster' AND sm_requests.state = 'completed'
            	GROUP BY sm_requests.song_id
             ) AS t2
        ON t2.id=sm_songs.id 
		WHERE banned<>1 AND installed=1 AND numplayed>1 
		GROUP BY sm_songs.id 
		ORDER BY RAND()
		LIMIT 100";
		$retval = mysqli_query( $conn, $sql );
		
		if(mysqli_num_rows($retval) >= 10) {
			//let's hope for at least 10 results so that it at least seems like a random pick
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty);
					echo ("{$user} randomly requested " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
		}else{
			die("Too few songs played/requested to pick a random song!");
		}
	
	} else {
        die("Didn't find any random songs!");
}

die();
}

//standard portal request, any installed/unbanned songs can be selected
if($_GET["random"] == "portal"){

	$request_type = "portal";

        $sql = "SELECT * FROM sm_songs WHERE installed=1 AND banned<>1 ORDER BY RAND() LIMIT 100";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty);
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

	$request_type = "top";
	if(empty($stepstype)){$stepstype = '%';}

        $sql = "SELECT id,title,subtitle,artist,pack,numplayed,stepstype 
				FROM sm_songs 
				JOIN 
					(SELECT song_id,SUM(numplayed) AS numplayed,stepstype 
					FROM sm_songsplayed
					WHERE song_id>0 AND numplayed>1 AND username LIKE '{$profileName}' AND stepstype LIKE '{$stepstype}' 
					GROUP BY song_id
					ORDER BY numplayed DESC
					LIMIT 100) AS t2
				ON t2.song_id=sm_songs.id 
				WHERE banned<>1 AND installed=1 AND stepstype LIKE '{$stepstype}'  
				ORDER BY RAND()";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
		$i=1;
		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
			if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
				request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $row['stepstype'], $difficulty);
				echo ("$user picked a top request " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
				$i++;
			}
		}
	} elseif (mysqli_num_rows($retval) == 0) {
		//didn't find any songs from the sm_songsplayed table. request system is running in "offline" mode.
		$sql = "SELECT id,title,subtitle,artist,pack,numplayed 
				FROM sm_songs 
				JOIN 
					(SELECT sm_requests.song_id AS song_id,COUNT(sm_requests.song_id) AS numplayed 
					FROM sm_requests
					WHERE sm_requests.song_id > 0 AND sm_requests.broadcaster LIKE '$broadcaster' AND sm_requests.state = 'completed'
					GROUP BY sm_requests.song_id
					ORDER BY numplayed DESC
					LIMIT 100) AS t2
				ON t2.song_id=sm_songs.id 
				WHERE banned<>1 AND installed=1 AND numplayed>1
				ORDER BY RAND()";
		$retval = mysqli_query( $conn, $sql );
		
		if(mysqli_num_rows($retval) >= 10) {
			//let's hope for at least 10 results so that it at least seems like a random pick
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty);
					echo ("$user picked a top request " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
		}else{
			die("Too few songs played/requested to pick a top song!");
		}
	} else {
        die("Didn't find any top songs!");
}

die();
}

//random worst 25 scored top 100 songs
if($_GET["random"] == "gitgud"){

	$request_type = "gitgud";
	if(empty($stepstype)){$stepstype = '%';}

	switch ($scoreType){
		case "ddr":
			$score_grade = "ddr_grade";
			$score_tier = "ddr_tier";
			break;
		case "itg":
			$score_grade = "itg_grade";
			$score_tier = "itg_tier";
			break;
		default:
			$score_grade = "itg_grade";
			$score_tier = "itg_tier";
	}

        $sql = "SELECT id,title,subtitle,artist,pack,t2.percentdp,score,$score_grade AS grade,stepstype,difficulty 
				FROM sm_songs 
				JOIN 
				(SELECT song_id,MAX(percentdp) AS percentdp,MAX(score) AS score,grade,stepstype,difficulty 
					FROM sm_scores 
					WHERE EXISTS 
						(SELECT song_id,SUM(numplayed) AS numplayed   
						FROM sm_songsplayed 
						WHERE song_id>0 AND numplayed>1 AND username LIKE '{$profileName}' AND stepstype LIKE '{$stepstype}' 
						GROUP BY song_id 
						ORDER BY numplayed DESC 
						LIMIT 100) 
					AND grade <> 'Failed' AND percentdp > 0 AND percentdp < 1 AND username LIKE '{$profileName}' AND stepstype LIKE '{$stepstype}' 
					GROUP BY song_id 
					ORDER BY percentdp ASC, score ASC 
					LIMIT 25) AS t2 
				ON t2.song_id = sm_songs.id 
				JOIN sm_grade_tiers 
					ON sm_grade_tiers.$score_tier = t2.grade 
				WHERE banned <> 1 AND installed = 1 
				ORDER BY RAND()";
        $retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) > 0) {
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $row['stepstype'], $row['difficulty']);
					switch ($scoreType){
						case "ddr":
							$score = $row['score'];
							$score !== 0 ? $base = ceil(log10($score)) : $base = 1;
							if($base > 6){
								//score is >1000000. It was obtained while using a non-modern-ddr theme.
								//translate the score to ddr range (out of 1M)
								$score = $score / pow(10,$base - 6);
								$displayScore = number_format($score,0,".",",")."* [".$row['grade']."]";
							}else{
								$displayScore = number_format($score,0,".",",")." [".$row['grade']."]";
							}
							break;
						case "itg":
							$displayScore = number_format($row['percentdp']*100,2)."% [".$row['grade']."]";
							break;
						default:
							$displayScore = number_format($row['percentdp']*100,2)."% [".$row['grade']."]";
					}
					echo ("$user dares you to beat ".$displayScore." at " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . " ");
					$i++;
				}
			}
	} else {
        	die("Didn't find any songs to git gud at!");
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
		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
			if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
			echo " [ ".$row["id"]. " => " .trim($row["title"]." ".$row["subtitle"])." from ".$row["pack"]." ]";
			$i++;
			}
		}
	} elseif (mysqli_num_rows($retval) == 0) {
		//didn't find any songs from the sm_songsplayed table. request system is running in "offline" mode.
		$sql = "SELECT sm_songs.id AS id,sm_songs.title AS title,sm_songs.subtitle AS subtitle,sm_songs.artist AS artist,sm_songs.pack AS pack,numplayed  
		FROM sm_songs 
		JOIN 
        	(SELECT sm_requests.song_id AS id,COUNT(sm_requests.song_id) AS numplayed 
             	FROM sm_requests
            	WHERE sm_requests.song_id > 0 AND sm_requests.broadcaster LIKE '$broadcaster' AND sm_requests.state = 'completed'
            	GROUP BY sm_requests.song_id
             ) AS t2
        ON t2.id=sm_songs.id 
		WHERE banned<>1 AND installed=1 AND numplayed>1 
		GROUP BY sm_songs.id 
		ORDER BY RAND()
		LIMIT 100";
		$retval = mysqli_query( $conn, $sql );
		
		if(mysqli_num_rows($retval) >= 10) {
			//let's hope for at least 10 results so that it at least seems like a random pick
			echo "$user rolled (request with !requestid [song id]):\n";
			$i=1;
			while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
				if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
				echo " [ ".$row["id"]. " => " .trim($row["title"]." ".$row["subtitle"])." from ".$row["pack"]." ]";
				$i++;
				}
			}
		}else{
			die("Too few songs played/requested to roll random songs!");
		}	
	} else {
		die("$user rolled a natural 1 BibleThump");
	}
	die();
}

//special random for regulars: picks a random song from top 10 requested by requestor
if($_GET["random"] == "theusual"){
	
	$userLC = strtolower($user);
	$request_type = "theusual";
	
	$sql = "SELECT id,title,subtitle,artist,pack,idcount    
			FROM sm_songs  
			JOIN 
				(SELECT song_id, COUNT(song_id) AS idcount 
				FROM sm_requests 
				WHERE song_id>0 AND LOWER(requestor) LIKE '{$userLC}' AND state <> 'canceled' AND state <> 'skipped' 
				GROUP BY song_id 
				ORDER BY idcount DESC  
				LIMIT 20) AS t2 
			ON t2.song_id=sm_songs.id  
			WHERE banned<>1 AND installed=1 AND idcount>1 
			ORDER BY RAND()";
	$retval = mysqli_query( $conn, $sql );

	if (mysqli_num_rows($retval) >= 10) {
		$i=1;
		while(($row = mysqli_fetch_assoc($retval)) && ($i <= $num)) {
			if(recently_played($row["id"])==FALSE && check_stepstype($broadcaster,$row["id"])==TRUE && check_meter($broadcaster,$row["id"])==TRUE){
				request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty);
				echo ("Of course {$user} would request " . trim($row["title"]." ".$row["subtitle"]). " from " . $row["pack"] . ". HoW oRiGiNaL! ");
				$i++;
			}
		}
	} else {
		die("$user hasn't met the minimum number of requested songs or isn't a reqular around here.");
	}

	die();
}

//specific pack(s) random request/catch-all REGEX pack name matching
//randomben, randomddr, randomnitg, randomhellkite...
if(!empty($_GET["random"]) && $_GET["random"] != "random"){
		
		$random = $_GET["random"];
		if(isset($_GET["type"])){$request_type = mysqli_real_escape_string($conn,$_GET["type"]);}
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
					request_song($row["id"], $user, $tier, $twitchid, $broadcaster, $request_type, $stepstype, $difficulty);
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
