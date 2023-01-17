<?php

require_once ('config.php');

if(!isset($_GET["security_key"]) || $_GET["security_key"] != $security_key || empty($_GET["security_key"])){
    die("Fuck off");
}

$conn = mysqli_connect(dbhost, dbuser, dbpass, db);
if(! $conn ) {die('Could not connect: ' . mysqli_error($conn));}
$conn->set_charset("utf8mb4");
   
function format_pack($pack,$requestor){
	$length = 40;
	$length = $length - (strlen($requestor) * 0.8);

 	$pack = str_ireplace("Dance Dance Revolution","DDR",$pack);
	$pack = str_ireplace("DanceDanceRevolution","DDR",$pack);
	$pack = str_ireplace("Dancing Stage","DS",$pack);
	$pack = str_ireplace("DancingStage","DS",$pack);
	$pack = str_ireplace("In The Groove","ITG",$pack);
	$pack = str_ireplace("InTheGroove","ITG",$pack);
	//$pack = str_ireplace("Ben Speirs","BS",$pack);
	//$pack = str_ireplace("JBEAN Exclusives","JBEAN...",$pack);
	$pack = preg_replace("/(\(.*\).\(.*\))$/","",$pack,1);
	if(strlen($pack) > $length){
		//$pack = trim(substr($pack,0,18))."...".trim(substr($pack,strlen($pack)-7));
		$separator = "...";
		$maxLength = $length - strlen($separator);
		$startTrunc = $maxLength / 2;
		$truncLength =  strlen($pack) - $maxLength; 
		$pack = substr_replace($pack,$separator,$startTrunc,$truncLength);
	}
return $pack;
}   

if(isset($_GET["broadcaster"]) && !empty($_GET["broadcaster"])){
	$broadcaster = $_GET["broadcaster"];
}else{
	$broadcaster = "%";
}

if(!isset($_GET["middle"])){

echo '<html>
<head>
<link rel="stylesheet" href="style.css" />
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="scripts.js"></script>
</head>

<body>
<audio id="new" src="new.mp3" type="audio/mpeg"></audio>
<audio id="cancel" src="cancel.mp3" type="audio/mpeg"></audio>
<div id="middle">

';

}
	//parse url parameter for request board length
	if ( isset($_GET['length']) && !empty($_GET['length']) && is_numeric($_GET['length'])) {
		//is valid number
		$requestWidgetLength = $_GET['length'];
	}else{
		//not valid number, use default
		$requestWidgetLength = 10;
	}

	$sql = "SELECT sm_requests.id AS id, sm_requests.song_id AS song_id, title, subtitle, artist, pack, requestor, request_time, request_type, stepstype, difficulty 
			FROM sm_requests 
			JOIN sm_songs ON sm_songs.id = sm_requests.song_id 
			WHERE (state = 'requested' OR state = 'completed') AND broadcaster LIKE '$broadcaster'  
			ORDER BY request_time DESC LIMIT $requestWidgetLength";
	$retval = mysqli_query( $conn, $sql ) or die(mysqli_error($conn));

	while($row = mysqli_fetch_assoc($retval)) {
		$request_id = $row["id"];
		$song_id = $row["song_id"];
		$request_time = $row["request_time"];
		$requestor = $row["requestor"];
		$title = $row["title"];
		$subtitle = $row["subtitle"];
		$pack = format_pack($row["pack"],$requestor);
		$request_type = strtolower($row["request_type"]);
		$stepstype = strtolower($row["stepstype"]);
		$difficulty = strtolower($row["difficulty"]);

		$pack_img = strtolower(preg_replace('/\s+/', '_', trim($row["pack"])));
		$pack_img = glob("images/packs/".$pack_img.".{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF,bmp,BMP}", GLOB_BRACE);
		if (!$pack_img){
			$pack_img = "images/packs/unknown.png";
		}else{
			$pack_img = "images/packs/".urlencode(basename($pack_img[0]));
		}

		if($request_type != "normal"){
			$request_img = glob("images/".$request_type.".{png,PNG,gif,GIF}", GLOB_BRACE);
			if (!$request_img){
				$request_img = "images/random.png";
			}else{
				$request_img = "images/".urlencode(basename($request_img[0]));
			}
			$request_type = "<img src=\"$request_img\" class=\"type\">";
		}else{
			$request_type = "";
		}

		if(!empty($stepstype)){
			$stepstype_split = explode("-",$stepstype);
			$stepstype = "<img src=\"images/$stepstype_split[1]s.png\" class=\"$stepstype_split[0] $stepstype_split[1]\">";
		}

		if(!empty($difficulty)){
			$difficulty = "<div class=\"difficulty $difficulty\"></div>";
		}else{
			$difficulty = "<div class=\"difficulty\"></div>";
		}

		if(empty($stepstype)){$difficulty = "";}
		
		if($i == 0){
			echo "<span id=\"lastid\" style=\"display:none;\">$request_id</span>\n";
			echo "<span id=\"security_key\" style=\"display:none;\">".urlencode($_GET["security_key"])."</span>\n";
			echo "<span id=\"broadcaster\" style=\"display:none;\">".urlencode($broadcaster)."</span>\n";
			if(isset($_GET['admin'])){echo "<span id=\"admin\" style=\"display:none;\">admin</span>\n";}
			echo "\n";
		}
		
		echo "<div class=\"songrow\" id=\"request_".$request_id."\">			
		<h2>$title<h2a>$subtitle</h2a></h2>
		<h3>$pack</h3>
		<h4>$requestor</h4>";
		echo $request_type."\n";
		echo $difficulty."\n";
		echo $stepstype."\n";
		echo "<img class=\"songrow-bg\" src=\"{$pack_img}\" />
		<span id=\"request_${request_id}_time\" style=\"display:none;\">$request_time</span>\n
		</div>\n";
		if(isset($_GET['admin'])){
			echo "<div class=\"admindiv\" id=\"requestadmin_".$request_id."\">
			<button class=\"adminbuttons\" style=\"margin-left:4vw; background-color:rgb(0, 128, 0);\" type=\"button\" onclick=\"MarkCompleted(".$request_id.")\">Mark Complete</button>\n
			<button class=\"adminbuttons\" style=\"background-color:rgb(153, 153, 0);\" type=\"button\" onclick=\"MarkSkipped(".$request_id.")\">Mark Skipped</button>
			<button class=\"adminbuttons\" style=\"margin-right:4vw; float:right; background-color:rgb(178, 34, 34);\" type=\"button\" onclick=\"MarkBanned(".$request_id.")\">Mark Banned</button>
			</div>\n";
		}

		$ids[] = $request_id;
		$i++;

	}

if(!is_array($ids) || empty($ids)){
	$oldid = 0;
}else{
	$oldid = min($ids);
}
	
echo "<span id=\"oldid\" style=\"display:none;\">{$oldid}</span>\n";
echo "
</div>
</html>";

mysqli_close($conn);
?>