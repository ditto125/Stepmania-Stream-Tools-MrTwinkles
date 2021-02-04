<?php
//
//Contains all functions for managing users during request processing and other misc. functions that are called throughout
//

//include("config.php");

$conn = mysqli_connect(dbhost, dbuser, dbpass, db);
if(! $conn ) {die('Could not connect: ' . mysqli_error($conn));}

function add_user($userid, $user){

	global $conn;
	
	$user = strtolower($user);
	$sql = "INSERT INTO sm_requestors (twitchid, name, dateadded) VALUES ('$userid', '$user', NOW())";
	$retval = mysqli_query( $conn, $sql );
	$the_id = mysqli_insert_id($conn);

	return($the_id);

}

function check_user($userid, $user){

    global $conn;

    $user = strtolower($user);

    //case where the bot cannot supply the twitchid, use the name
    if($userid > 0 || !empty($userid)){
        $sql0 = "SELECT * FROM sm_requestors WHERE twitchid = '$userid'";
    }else{
        $sql0 = "SELECT * FROM sm_requestors WHERE name = '$user'";
    }
    $retval0 = mysqli_query( $conn, $sql0 );
    $numrows = mysqli_num_rows($retval0);

    if($numrows != 0){
        //User exists in DB, return data
        $row0 = mysqli_fetch_assoc($retval0);
        $id = $row0["id"];
        $twitchid = $row0["twitchid"];
        $whitelisted = $row0["whitelisted"];
        $banned = $row0["banned"];
    }else{
        //User is new - create then return data
        $id = add_user($userid, $user);
        $whitelisted = "false";
        $banned = "false";
    }

    $userobj["id"] = "$id";
    $userobj["name"] = "$user";
    $userobj["twitchid"] = "$userid";
    $userobj["whitelisted"] = "$whitelisted";
    $userobj["banned"] = "$banned";

    return($userobj);

}

function check_length(){
    global $conn;
    $sql0 = "SELECT state FROM sm_requests ORDER BY request_time DESC LIMIT 10";
    $retval0 = mysqli_query( $conn, $sql0 );
    $length = 0;
    foreach($retval0 as $row){
        if($row['state'] == 'requested'){
            $length++;
        }
    }
    return $length;
}

function check_cooldown($user){
    global $cooldownMultiplier;
    global $maxRequests;

    //check total length of requests, if over maxRequests, stop
    $length = check_length();

    if($length > $maxRequests){
        die("Too many songs on the request list! Try again in a few minutes.");
    }
    $interval = $cooldownMultiplier * $length;

    //scale cooldown as a function of the number of requests. X minutes per open request.	
    global $conn;
    $sql0 = "SELECT * FROM sm_requests WHERE state <> 'canceled' AND requestor = '$user' AND request_time > DATE_SUB(NOW(), INTERVAL {$interval} MINUTE)";
    $retval0 = mysqli_query( $conn, $sql0 );
    $numrows = mysqli_num_rows($retval0);
    if($numrows > 0){
        die("Slow down there, part'ner! Try again in ".ceil($interval)." minutes.");
    }
}

function recently_played($song_id){
	global $conn;
	$recently_played = FALSE;
	$sql = "SELECT song_id FROM sm_songsplayed WHERE song_id={$song_id} AND lastplayed > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
	$retval = mysqli_query($conn,$sql);
	if(mysqli_num_rows($retval) > 0){
		$recently_played = TRUE;
	}
	return $recently_played;
}

function is_emote_request($song){
    $emoteArray = array (
        "mrtwin1HaHaHa"     =>  "The Smiler",
        "danizoOHNO"        =>  "Minna no Kimochi",
        "beniplKitty"       =>  "Kitty From Hell",
        "ddrDav2"           =>  "Heaven is a 57 metallic gray",
        "hellki1Nabi1"      =>  "My Baby Mama",
        "iambgsKool"        =>  "New Horizons TOKYO",
        "kikoiaRusty"       =>  "Doom Crossing",
        "noreseSLOW"        =>  "VEAH",
        "xancarDex"         =>  "Ding Dong Song"
    );

    if(array_key_exists($song,$emoteArray)){
        $song = $emoteArray[$song];
    }

    return $song;
}

function get_broadcaster_limits($broadcaster){
    global $conn;
    $broadcaserLimits = array();
    $sql0 = "SELECT * FROM sm_broadcaster WHERE broadcaster = '$broadcaster'";
    $retval0 = mysqli_query( $conn, $sql0 );

    if(mysqli_num_rows($retval0) == 1){
        $broadcaserLimits = mysqli_fetch_assoc($retval0);
    }
    return $broadcaserLimits;
}

function check_request_toggle($broadcaster){
    global $conn;
    $sql0 = "SELECT * FROM sm_broadcaster WHERE broadcaster LIKE '$broadcaster'";
    $retval0 = mysqli_query( $conn, $sql0 );
    $numrows = mysqli_num_rows($retval0);

    if($numrows == 1){
        $row0 = mysqli_fetch_assoc($retval0);
        $request_toggle = $row0["request_toggle"];
        $message = $row0["message"];
        if($request_toggle == "OFF"){
            die("Requests are disabled. ".$message);
        }
    }
}

function check_stepstype($broadcaster,$song_id){
    global $conn;

    $response = TRUE;
    $sql0 = "SELECT * FROM sm_broadcaster WHERE broadcaster LIKE '$broadcaster'";
    $retval0 = mysqli_query( $conn, $sql0 );
    $numrows = mysqli_num_rows($retval0);

    if($numrows == 1){
        $row0 = mysqli_fetch_assoc($retval0);
        $stepstype = $row0["stepstype"];
        if(!empty($stepstype)){
            $sql = "SELECT * FROM sm_notedata WHERE song_id = '$song_id' AND stepstype LIKE '$stepstype'";
            $retval = mysqli_query( $conn, $sql);
            if(mysqli_num_rows($retval) == 0){
                $response = FALSE;
            }
        }
    }
    return $response;
}

function check_meter($broadcaster,$song_id){
    global $conn;

    $response = TRUE;
    $sql0 = "SELECT * FROM sm_broadcaster WHERE broadcaster LIKE '$broadcaster'";
    $retval0 = mysqli_query( $conn, $sql0 );
    $numrows = mysqli_num_rows($retval0);

    if($numrows == 1){
        $row0 = mysqli_fetch_assoc($retval0);
        $meter_max = $row0["meter_max"];
        if(!empty($meter_max)){
            $sql = "SELECT * FROM sm_notedata WHERE song_id = '$song_id' AND stepstype LIKE 'dance%' AND meter <= '$meter_max'";
            $retval = mysqli_query( $conn, $sql);
            if(mysqli_num_rows($retval) == 0){
                $response = FALSE;
            }
        }
    }
    return $response;
}

function check_notedata($broadcaster,$song_id,$stepstype,$difficulty,$user){
    global $conn;
    $response = TRUE;

    if(!empty($stepstype) && $stepstype != '%' && empty($difficulty)){
        $difficulty = '%';
    }elseif(empty($stepstype) && !empty($difficulty)){
        die("$user didn't specify a stepstype!");
    }

    $sql = "SELECT * FROM sm_notedata WHERE song_id = '$song_id' AND stepstype LIKE '$stepstype' AND difficulty LIKE '$difficulty'";
    $retval = mysqli_query( $conn, $sql);
    if(mysqli_num_rows($retval) == 0){
        $response = FALSE;
    }
    return $response;
}

function parseCommandArgs($argsStr,$user,$broadcaster){
    global $conn;

    $result = array('song'=>'','stepstype'=>'','difficulty'=>'');
    $args = explode("#",$argsStr,3);
    $args = array_map("trim",$args);
    $result['song'] = $args[0];
    if(count($args) == 2 && strlen($args[1]) == 3){
        switch (strtoupper($args[1])){
            case "BSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Easy";
            break;
            case "DSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Medium";
            break;
            case "MSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Medium";
            break;
            case "SSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Medium";
            break;
            case "ESP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Expert";
            break;
            case "HSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Hard";
            break;
            case "CSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Challenge";
            break;
            case "XSP":
                $result['stepstype'] = "dance-single";
                $result['difficulty'] = "Edit";
            break;
            case "BDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Easy";
            break;
            case "DDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Medium";
            break;
            case "MDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Medium";
            break;
            case "SDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Medium";
            break;
            case "EDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Expert";
            break;
            case "HDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Hard";
            break;
            case "CDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Challenge";
            break;
            case "XDP":
                $result['stepstype'] = "dance-double";
                $result['difficulty'] = "Edit";
            break;
            default:
                die("$user gave an invalid 3-letter stepstype/difficulty.");
        }  
    }elseif(count($args) > 1){
        $args = array_splice($args,1);
        foreach ($args as $arg){
            switch (strtolower($arg)){
                case "single":
                    $result['stepstype'] = "dance-single";
                break;
                case "singles":
                    $result['stepstype'] = "dance-single";
                break;
                case "double":
                    $result['stepstype'] = "dance-double";
                break;
                case "doubles":
                    $result['stepstype'] = "dance-double";
                break;
                case "doublays":
                    $result['stepstype'] = "dance-double";
                break;
                case "beginner":
                    $result['difficulty'] = "Beginner";
                break;
                case "easy":
                    $result['difficulty'] = "Easy";
                break;
                case "light":
                    $result['difficulty'] = "Easy";
                break;
                case "medium":
                    $result['difficulty'] = "Medium";
                break;
                case "standard":
                    $result['difficulty'] = "Medium";
                break;
                case "hard":
                    $result['difficulty'] = "Hard";
                break;
                case "heavy":
                    $result['difficulty'] = "Hard";
                break;
                case "expert":
                    $result['difficulty'] = "Hard";
                break;
                case "challenge":
                    $result['difficulty'] = "Challenge";
                break;
                case "oni":
                    $result['difficulty'] = "Challenge";
                break;
                case "edit":
                    $result['difficulty'] = "Edit";
                break;
                default:
                    die("$user gave invalid stepstype or difficulty.");
            }
        }
    }
    //if stepstype is empty, check if sm_broadcast has one set globally
    if(empty($result['stepstype'])){
        $sql0 = "SELECT * FROM sm_broadcaster WHERE broadcaster = '$broadcaster'";
        $retval0 = mysqli_query( $conn, $sql0 );
        if(mysqli_num_rows($retval0) == 1){
            $row0 = mysqli_fetch_assoc($retval0);
            $result['stepstype'] = $row0["stepstype"];
        }elseif(!empty($result['difficulty'])){
            //no stepstype in sm_broadcaster and only difficulty specified
            die("$user didn't specify a stepstype!");
        }
    }
    
    return $result;
}

function wh_log($log_msg)
{
    $log_filename = "log";
    if (!file_exists($log_filename)) 
    {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, date("Y-m-d H:i:s") . "  " . $log_msg . "\n", FILE_APPEND);
} 

mysqli_close($conn);
?>