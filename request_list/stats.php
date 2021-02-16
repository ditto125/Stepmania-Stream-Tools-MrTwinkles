<html>
   <head>
      
      <script type = "text/JavaScript">
         <!--
            function AutoRefresh( t ) {
               setTimeout("location.reload(true);", t);
            }
         //-->
      </script>
      <style type="text/css">
		@font-face {
		font-family: 'Fundamental';
		src: url('fonts/fundamentalbrigade.ttf') format('truetype');
		font-weight: normal;
		font-style: normal;
		}

		body {
			font-family: 'Fundamental', normal;
			letter-spacing:0.01em;
			font-size:5vw;
			color: white;
			white-space:nowrap;
			overflow:hidden;
			line-height: 38px;
		}

		h1 {
			font-family: 'Fundamental', normal;
			letter-spacing:0.01em;
			font-size:5vw;
			font-weight:normal;
			color: white;
			white-space:nowrap;
			overflow:hidden;
			text-align: center;
			padding-botton: 0vw;
		}

		table {
			font-size:2vh;
		}
		td {
			padding: 0.5vw;
		}
	  </style>

   </head>
   
   <body onload = "JavaScript:AutoRefresh(5000);">
<?php

include("config.php");

if(!isset($_GET["data"])){die("No data set");}

$conn = mysqli_connect(dbhost, dbuser, dbpass, db);
if(! $conn ) {die('Could not connect: ' . mysqli_error($conn));}

function getLastRequest(){
	global $conn;
	$lastRequest = array();
	$sql = "SELECT * FROM sm_requests WHERE state <> 'canceled' AND state <> 'skipped' ORDER BY request_time DESC LIMIT 1";
	$retval = mysqli_query( $conn, $sql );
	$lastRequest = mysqli_fetch_assoc($retval);
	
	return $lastRequest;
}

function format_pack($pack){
	$pack = str_ireplace("Dance Dance Revolution","DDR",$pack);
	$pack = str_ireplace("DanceDanceRevolution","DDR",$pack);
	$pack = str_ireplace("Dancing Stage","DS",$pack);
	$pack = str_ireplace("DancingStage","DS",$pack);
	$pack = str_ireplace("In The Groove","ITG",$pack);
	$pack = str_ireplace("InTheGroove","ITG",$pack);
	$pack = str_ireplace("Ben Speirs","BS",$pack);
	$pack = str_ireplace("JBEAN Exclusives","JBEAN...",$pack);
	return $pack;
}   

switch($_GET["data"]){
////////REQUESTS/////////
	case "requests":	
		$timestamp = getLastRequest()['request_time'];

		$sql = "SELECT COUNT(*) AS requestsToday FROM sm_requests WHERE state <> 'canceled' AND state <> 'skipped' AND request_time > date_sub(\"{$timestamp}\", interval 3 hour)";
		$retval = mysqli_query( $conn, $sql );

		$row = mysqli_fetch_assoc($retval);
		$requestsToday = $row["requestsToday"];

		echo "<body style=\"text-align: right;\">$requestsToday &nbsp; requests this session</body";
	break;
////////SONGS/////////
	case "songs":
		$timestamp = getLastRequest()['request_time'];

		$sql = "SELECT COUNT(DISTINCT datetime) AS playedToday FROM sm_scores WHERE datetime > date_sub(\"{$timestamp}\", interval 3 hour)";
		$retval = mysqli_query( $conn, $sql );

		$row = mysqli_fetch_assoc($retval);
		$playedToday = $row["playedToday"];

		echo "<body style=\"text-align: right;\">$playedToday &nbsp; songs played this session</body>";
	break;
	case "scores":
////////SCORES/////////
		if(isset($_GET["judgement"])){
			switch ($_GET["judgement"]){
				case "itg":
					$tier = "itg_tier";
					$grade = "itg_grade";
					$score = "itg";
				break;
				case "ddr":
					$tier = "ddr_tier";
					$grade = "ddr_grade";
					$score = "ddr";
				break;
				default:
				die("No judgement specified. Usage: judgement=\"itg\" or \"ddr\".");
			}
		}else{die("No judgement specified. Usage: judgement=\"itg\" or \"ddr\".");}
		
		$timestamp = getLastRequest()['request_time'];
		
		$sql = "SELECT sm_grade_tiers.$grade,FORMAT(MAX(sm_scores.percentdp*100),2) AS percentdp,FORMAT(MAX(score),0) AS score,COUNT(sm_scores.grade) AS gradeCount 
		FROM sm_scores 
		LEFT JOIN sm_grade_tiers ON sm_grade_tiers.$tier = sm_scores.grade
		WHERE sm_scores.datetime > date_sub(\"{$timestamp}\", interval 3 hour) AND sm_scores.grade <> 'Failed' AND sm_scores.percentdp > 0 
		GROUP BY sm_scores.grade 
		ORDER BY sm_scores.grade ASC";
		mysqli_set_charset($conn,"utf8mb4");
		$retval = mysqli_query( $conn, $sql );

		if($score == "ddr"){
			$score = "score";
		}else{
			$score = "percentdp";
		}
		
		echo '<table>';
		while ($row = mysqli_fetch_assoc($retval)){
			echo '<tr>';
			echo "<td>".$row[$grade]."</td><td>(".$row[$score].")</td><td>".$row['gradeCount']."</td>";
			echo '</tr>';
		}
		echo '</table>';
	break;
	case "recent":
////////RECENT HIGHSCORES/////////
		if(isset($_GET["judgement"])){
			$judgement = $_GET["judgement"];
			switch ($judgement){
				case "itg":
					$tier = "itg_tier";
					$grade = "itg_grade";
				break;
				case "ddr":
					$tier = "ddr_tier";
					$grade = "ddr_grade";
				break;
				default:
				die("No judgement specified. Usage: judgement=\"itg\" or \"ddr\".");
			}
		}else{die("No judgement specified. Usage: judgement=\"itg\" or \"ddr\".");}

		$timestamp = getLastRequest()['request_time'];

		$sql = "SELECT TRIM(CONCAT(sm_songs.title,' ',sm_songs.subtitle)) AS title,sm_songs.pack AS pack,sm_grade_tiers.$grade,CONCAT(FORMAT(maxpercentdp * 100, 2),'%') AS percentdp,FORMAT(score, 0) AS score,sm_scores.stage_award AS award,sm_scores.stepstype,sm_scores.difficulty,sm_scores.username,datetime
		FROM
			sm_scores
		JOIN sm_songs ON sm_songs.id = sm_scores.song_id
		JOIN sm_grade_tiers ON sm_grade_tiers.$tier = sm_scores.grade
		JOIN(
			SELECT song_id,stepstype,difficulty,username,MAX(percentdp) AS MaxPercentdp
			FROM
				sm_scores
			WHERE
				song_id > 0 AND percentdp > 0 AND grade <> 'Failed'
			GROUP BY
				song_id,stepstype,difficulty,username
			) AS h2
		ON sm_scores.song_id = h2.song_id AND sm_scores.stepstype = h2.stepstype AND sm_scores.difficulty = h2.difficulty AND sm_scores.username = h2.username AND sm_scores.percentdp = h2.maxpercentdp
		ORDER BY `datetime` DESC
		LIMIT 5";
		mysqli_set_charset($conn,"utf8mb4");
		$retval = mysqli_query( $conn, $sql );
		
		echo '<table>';
		while ($row = mysqli_fetch_assoc($retval)){
			//translate SM5 scores and stage award to game-specific names
			$award = $row['award'];
			switch($judgement){
				case "ddr":
					$score = "score";
					$stageAwards = array(
						"FullComboW3" => "FC",
						"SingleDigitW3" => "",
						"OneW3" => "1G",
						"FullComboW2" => "PFC",
						"SingleDigitW2" => "",
						"OneW2" => "1P",
						"FullComboW1" => "MFC"
					);
					if(array_key_exists($award,$stageAwards)){
						$award = $stageAwards[$award];
					}
				break;
				case "itg":
					$score = "percentdp";
					$stageAwards = array(
						"FullComboW3" => "FC",
						"SingleDigitW3" => "",
						"OneW3" => "1G",
						"FullComboW2" => "Tri-Star",
						"SingleDigitW2" => "",
						"OneW2" => "1P",
						"FullComboW1" => "Quad"
					);
					if(array_key_exists($award,$stageAwards)){
						$award = $stageAwards[$award];
					}
				break;
				default:
					$score = "percentdp";
					$award = "";
			}
		//build table of recent highscores
			echo '<tr>';
			echo '<td>'.$row['title'].'</td><td>'.format_pack($row['pack']).'</td><td><strong>'.$row[$grade].'</strong></td><td>('.$row[$score].')</td><td>'.$award.'</td>';
			echo '</tr>';
		}
		echo '</table>';
	break;
	case "requestors":
////////REQUESTORS/////////
		$broadcasters = getLastRequest()['broadcaster'];
		$timestamp = getLastRequest()['request_time'];

		$sql = "SELECT requestor,COUNT(id) AS count 
		FROM sm_requests 
		WHERE state <> 'canceled' AND state <> 'skipped' AND LOWER(requestor) NOT IN(\"{$broadcasters}\") AND request_time > date_sub(\"{$timestamp}\", interval 3 hour) 
		GROUP BY requestor 
		ORDER BY count DESC,requestor DESC 
		LIMIT 5";
		$retval = mysqli_query( $conn, $sql );

		echo '<h1>Special thanks to requestors:</h1>';
		while ($row = mysqli_fetch_assoc($retval)){
			echo "<body style=\"text-align: center;\">".$row['requestor']." (".$row['count'].")</br>";
		}
		echo "</body>";
	break;
}

//close everything out
die();
mysqli_close($conn);
?>
</body>
   
</html>
