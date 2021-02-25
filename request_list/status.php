<?php
//--------Status.php--------//
//This file is responsible for processing the converted Stats.xml json array.
//Up to three tasks are accomplished at each run depending on the "source" value of the json string. The json string should look something like this:
//json: {EXAMPLE HERE}
//////////////////////////
//Tasks:
//1. Process the lastplayed data adding or updating entries for each unique song (songDir) in the sm_songsplayed table. The lastplayed data from the Stats.xml files can be in two formats: (1) with a full timestamp, if a new highscore was achieved, or (2) with only a date stamp, if no new highscore was achieved. The addLastPlayedtoDB function attempts to add/update any new values. This table is used to keep a record of when and how many times a song is played, which is critical for any of the random request commands. 
//2. Process any new highscores (also from Stats.xml files) an populate the sm_scores table. This information opens huge opertunities for score tracking stream widgets, score-based chat commands, etc.
//3. Determine if a recently completed song was requested beforehand and mark the request as "complete" in the sm_requests table.
//////////////////

//--------Configuration--------//

include ("config.php");

//--------Accept the POSTed json string, validate, and check security--------//
	
//Make sure that it is a POST request.
if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0){
    echo('Request method must be POST!' . PHP_EOL);
}
 
//Make sure that the content type of the POST request has been set to application/json
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if(strcasecmp($contentType, 'application/json') != 0){
	echo('Content type must be: application/json' . PHP_EOL);
}
 
//Receive the RAW post data.
$content = trim(file_get_contents("php://input"));
 
//Attempt to decode the incoming RAW post data from JSON.
$jsonDecoded = json_decode($content, true);
 
//If json_decode failed, the JSON is invalid.
if(!is_array($jsonDecoded)){
    echo('Received content contained invalid JSON!' . PHP_EOL);
}

if (!isset($jsonDecoded['security_key']) || $jsonDecoded['security_key'] != $security_key || empty($jsonDecoded['security_key']) || !isset($jsonDecoded['source']) || empty($jsonDecoded['data'])){die("Fuck off" . PHP_EOL);}

//--------Open mysql link--------//

$conn = mysqli_connect(dbhost, dbuser, dbpass, db);   
if(! $conn ) {die('Could not connect: ' . mysqli_error($conn));}

function clean($string) {
	$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
	return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
 }

function splitSongDir($song_dir){
	//This function splits the "song_dir" string into title and pack
	
	$splitDir = array();
	//find the folder name and set as the pack name
	$song_pack = substr($song_dir, 0, strripos($song_dir, "/"));
	$song_pack = substr($song_pack, 0, strripos($song_pack, "/"));
	$song_pack = substr($song_pack, strripos($song_pack, "/")+1);
	//use the folder name as the song title
	$song_title = substr($song_dir, 0, strripos($song_dir, "/"));
	$song_title = substr($song_title, strripos($song_title, "/")+1);
	//return array containing the title and pack
	$splitDir = array ('title' => $song_title, 'pack' => $song_pack);
	return $splitDir;
}

function lookupSongID ($song_dir){
	//This function looks up the song ID that matches the song_dir in the sm_songs db
	global $conn;
	$songInfo = array();
	$song_ids = array();
	//query for IDs matching the song_dir
	$sql_id = "SELECT id, title, pack FROM sm_songs WHERE song_dir=\"{$song_dir}\" ORDER BY id ASC";
	$id_result = mysqli_query($conn, $sql_id);
	if(mysqli_num_rows($id_result) == 1){
		//1 result found - set array from query results
		$songInfo = mysqli_fetch_assoc($id_result);
	}elseif(mysqli_num_rows($id_result) > 1){
		//more than 1 result found - set array from split song_dir, but set id to non-zero minimum id
		while ($row = mysqli_fetch_assoc($id_result)){
			if ($row['id'] > 0){
				$song_ids[] = $id;
			}
		}
		$song_id = min($song_ids);
		$song_ids = implode(", ",$song_ids);
		$song_title = splitSongDir($song_dir)['title'];
		$song_pack = splitSongDir($song_dir)['pack'];
		$songInfo = array('id' => $song_id, 'title' => $song_title, 'pack' => $song_pack);
		//notify user that there are duplicate results
		echo "Multiple possible IDs found for {$song_title} in {$song_pack}: {$song_ids}" . PHP_EOL;
	}elseif(mysqli_num_rows($id_result) == 0){
		//no results found - set array from split song_dir and id=0
		$song_id = "0";
		$song_title = splitSongDir($song_dir)['title'];
		$song_pack = splitSongDir($song_dir)['pack'];
		$songInfo = array('id' => $song_id, 'title' => $song_title, 'pack' => $song_pack);
		//notify user that an ID was not found in the sm_songs db
		//echo "No song ID found for {$song_title} in {$song_pack}. Moving on...\n";
	}
	return $songInfo;
}
 
function scrapeSongStart(){
	//clear the scraper field in sm_songs and get ready for scraping
	global $conn;

	$sql_clear = "UPDATE sm_songs SET scraper = 0";
	$res = mysqli_query($conn, $sql_clear);

	//check if this is the first run of the scraper
	$sql_clear = "SELECT * FROM sm_songs";
	$res = mysqli_query($conn, $sql_clear);
	if (mysqli_num_rows($res) == 0){
		return TRUE;
	}
}

function scrapeSongEnd($cFiles){
	global $conn;
	// After scraping all songs, update the existing and new songs as "installed"
		$sql_getstats = "SELECT COUNT(id) FROM sm_songs WHERE installed=1 AND scraper=2";
		$retval = mysqli_query($conn,$sql_getstats);
		$newSongs = mysqli_fetch_row($retval)[0];

		$sql_getstats = "SELECT COUNT(id) FROM sm_songs WHERE installed=1 AND scraper=3";
		$retval = mysqli_query($conn,$sql_getstats);
		$updatedSongs = mysqli_fetch_row($retval)[0];

		$sql_getstats = "SELECT COUNT(id) FROM sm_songs WHERE installed=1 AND scraper=1";
		$retval = mysqli_query($conn,$sql_getstats);
		$totalSongs = mysqli_fetch_row($retval)[0];
		$totalSongs = $totalSongs + $updatedSongs + $newSongs;

		$sql_getstats = "SELECT COUNT(id) FROM sm_songs WHERE installed=1 AND scraper=0";
		$retval = mysqli_query($conn,$sql_getstats);
		$addNotInstalledSongs = mysqli_fetch_row($retval)[0];

		$sql_getstats = "SELECT COUNT(id) FROM sm_songs WHERE installed=0 AND scraper=0";
		$retval = mysqli_query($conn,$sql_getstats);
		$notInstalledSongs = mysqli_fetch_row($retval)[0];

	//mark songs not found during scraping as "not installed"
		$sql_getstats = "UPDATE sm_songs SET installed=0 WHERE scraper=0";
		mysqli_query($conn,$sql_getstats);

	//clear scraper field
		$sql_getstats = "UPDATE sm_songs SET scraper=NULL";
		mysqli_query($conn,$sql_getstats);	


	//Let's show some stats!
	echo "Scraped {$cFiles} cache file(s) adding {$newSongs} new song(s) and updating {$updatedSongs} song(s) resulting in a new total of {$totalSongs} songs in the database!" . PHP_EOL;
	echo "{$addNotInstalledSongs} song(s) marked as 'not installed', totaling {$notInstalledSongs} 'not installed' song(s)." . PHP_EOL;

}

function scrapeSong($songCache_array){
	//This function processes the song cache arrays and inserts/updates song records into the sm_songs table
	global $conn;
	
	$metadata = array();
	$notedata_array = array();
	$song_dir = "";
	$title = "";
	$subtitle = "";
	$artist = "";
	$pack = "";
	$display_bpm = "";
	$music_length = "";
	$bga = 0;
	$stepstype = "";
	$difficulty = "";
	$stored_hash = "";
	$file_hash = "";

	$metadata = $songCache_array['metadata'];
	$file_hash = $metadata['file_hash'];
	$file = $metadata['file'];
	$notedata_array = $songCache_array['notedata'];

	//echo "Starting inspection of file $file\n";

	//Get song directory (this is needed to associate the songlist with score records)	

		if(isset($metadata['#SONGFILENAME'])){
				//song has a an associated simfile
				//echo "directory to simfile\n";
				$song_dir = substr($metadata['#SONGFILENAME'],1,strrpos($metadata['#SONGFILENAME'],"/")-1); //remove beginning slash and file extension
				//echo "'$song_dir'\n";
			}else{
				echo $file . PHP_EOL . "There's something truly wrong with this song, like how?" . PHP_EOL;
			}

	//		
	
	//Get pack

		$pack = substr($song_dir, 0, strripos($song_dir, "/"));
		$pack = substr($pack, strripos($pack, "/")+1);
		$pack = addslashes($pack);
		
	//
	
	//Get title
		if( !isset($metadata['#TITLETRANSLIT']) || empty($metadata['#TITLETRANSLIT'])){
			//song does not have a transliterated title
			If (isset($metadata['#TITLE']) && !empty($metadata['#TITLE'])){
				//song has a regular title
				$title = $metadata['#TITLE'];
			}else{
				//song doesn't have a title, can you believe that shit? Use the end of the filename.
				$title = substr($song_dir, strripos($song_dir, "/")+1);
			}
		}elseif( isset($metadata['#TITLETRANSLIT']) && !empty($metadata['#TITLETRANSLIT'])){
			//song has a transliterated title
				$title = $metadata['#TITLETRANSLIT'];
			}else{
				echo "!!!!! File must be busted. No title or titletranslit. !!!!!" . PHP_EOL;
			}
		
		if(strpos($title, "[") == 0 && strpos($title, "]") && !preg_match("/]$/",$title)){
			//This song title has a [BRACKETED TAG] before the actual title, let's remove it
			$firstbracketpos = strpos($title, "[");
			$lastbracketpos = strpos($title, "]");
			$title = substr($title, $lastbracketpos+1);
			
			if(strpos($title, "- ") == 1){
				//This song title now has a " - " before the actual title, let's remove that too
				$title = substr($title, 3);
			}
		}
		
		$title = trim($title);
		$title = addslashes($title);
		$strippedtitle = clean($title);
		
	//

	//Get subtitle
		
		if( !isset($metadata['#SUBTITLETRANSLIT']) || empty($metadata['#SUBTITLETRANSLIT'])){
			//song does not have a transliterated subtitle
			If (isset($metadata['#SUBTITLE']) && !empty($metadata['#SUBTITLE'])){
				//song has a regular subtitle
				$subtitle = $metadata['#SUBTITLE'];
			}
		}elseif( isset($metadata['#SUBTITLETRANSLIT']) && !empty($metadata['#SUBTITLETRANSLIT'])){
			//song has a transliterated subtitle
				$subtitle = $metadata['#SUBTITLETRANSLIT'];
			}
		
		$subtitle = trim($subtitle);
		$subtitle = addslashes($subtitle);
		$strippedsubtitle = clean($subtitle);
		
	//

	//Get artist
		
		if( !isset($metadata['#ARTISTTRANSLIT']) || empty($metadata['#ARTISTTRANSLIT'])){
			//song does not have a transliterated artist
			If (isset($metadata['#ARTIST']) && !empty($metadata['#ARTIST'])){
				//song has a regular artist
				$artist = $metadata['#ARTIST'];
			}
		}elseif( isset($metadata['#ARTISTTRANSLIT']) && !empty($metadata['#ARTISTTRANSLIT'])){
			//song has a transliterated artist
				$artist = $metadata['#ARTISTTRANSLIT'];
			}
		
		
		$artist = trim($artist);
		$artist = addslashes($artist);
		$strippedartist = clean($artist);
		
	//

	// Get BPM

		if( isset($metadata['#DISPLAYBPM']) && !empty($metadata['#DISPLAYBPM'])){
			//song has a bpm listed
			$display_bpm = $metadata['#DISPLAYBPM'];
		}elseif( isset($metadata['#BPMS']) && !empty($metadata['#BPMS'])){
			$displaybpmstart = strpos($metadata['#BPMS'],"=")+1;
			$display_bpm = substr($metadata['#BPMS'],$displaybpmstart);
			}

		if( strpos($display_bpm,':') > 0){
			$display_bpmSplit = array();
			$display_bpmSplit = preg_split("/:/",$display_bpm);
			//get the average bpm
			$display_bpm =  ceil((intval($display_bpmSplit[0],0) + intval($display_bpmSplit[1],0)) / 2);
		}else{
			$display_bpm = trim($display_bpm);
			$display_bpm = intval($display_bpm,0);
		}

	//

	// Get music length in seconds

		if( isset($metadata['#MUSICLENGTH'])){
			//song has a music length listed
			$music_length = $metadata['#MUSICLENGTH'];
		}

		$music_length = trim($music_length);
		$music_length = intval($music_length,0);

	//

	//Get existence of background video
		
		if( isset($metadata['#BGCHANGES']) && !empty($metadata['#BGCHANGES'])){
			//song has a background video
			$bga = 1;
		}
			
	//

	//Get song credit
		
	if( isset($metadata['#CREDIT']) && !empty($metadata['#CREDIT'])){
		//song has a credit
		$song_credit = $metadata['#CREDIT'];
		$song_credit = addslashes($song_credit);
	}else{
		$song_credit = "";
	}
		
	//
		
		//check if this song exists in the db
		$sql = "SELECT * FROM sm_songs WHERE song_dir=\"$song_dir/\"";
		$retval = mysqli_query( $conn, $sql );
		
		$sql_notedata_values = "";
		//$installed = "";
		//$scraper = "";
		
		if(mysqli_num_rows($retval) == 0){
		//This song doesn't yet exist in the db, let's add it!
			$installed = 1;
			$scraper = 2;
			echo "Adding to DB: ".stripslashes($title)." from ".stripslashes($pack) . PHP_EOL;

		$sql_songs_query = "INSERT INTO sm_songs (title, subtitle, artist, pack, strippedtitle, strippedsubtitle, strippedartist, song_dir, credit, display_bpm, music_length, bga, installed, added, checksum, scraper) VALUES (\"$title\", \"$subtitle\", \"$artist\", \"$pack\", \"$strippedtitle\", \"$strippedsubtitle\", \"$strippedartist\", \"$song_dir/\", \"$song_credit\", '$display_bpm', '$music_length', '$bga', '$installed', NOW(), \"$file_hash\", '$scraper')";
			
			if (!mysqli_query($conn, $sql_songs_query)) {
				echo "Error: " . $sql_songs_query . PHP_EOL . mysqli_error($conn) . PHP_EOL;
			}
		// Adding note data to sm_notedata DB:		
			$song_id = mysqli_insert_id($conn);
			
			//build notedata array into query ready values
			foreach ($notedata_array as $key){
				$sql_notedata_values = $sql_notedata_values.",(\"$song_id\",\"$song_dir/\",\"".implode("\",\"",$key)."\",NOW())";
			}
				
			//remove beginning comma and concat to sql query string
			$sql_notedata_query = "INSERT INTO sm_notedata (song_id, song_dir, chart_name, stepstype, description, chartstyle, difficulty, meter, radar_values, credit, display_bpm, stepfile_name, datetime) VALUES ".substr($sql_notedata_values,1);
			
			if (!mysqli_query($conn, $sql_notedata_query)) {
				echo "Error: " . $sql_notedata_query . PHP_EOL . mysqli_error($conn) . PHP_EOL;
			}
		}else{
				//This song already exists in the db, checking if there are any updates
				$retval = mysqli_fetch_assoc($retval);
				$song_id = $retval['id'];
				$stored_hash = $retval['checksum'];
				
				if( $file_hash != $stored_hash){
				// md5s do not match, assume there were updates to this song
					//echo "File Hash: ".$file_hash." != Stored Hash: ".$stored_hash.PHP_EOL;
					$installed = 1;
					$scraper = 3;
					$sql_songs_query = "UPDATE sm_songs SET 
					title=\"$title\", subtitle=\"$subtitle\", artist=\"$artist\", pack=\"$pack\", strippedtitle=\"$strippedtitle\", strippedsubtitle=\"$strippedsubtitle\", strippedartist=\"$strippedartist\", credit=\"$song_credit\", display_bpm='$display_bpm', music_length='$music_length', bga='$bga', installed={$installed}, checksum=\"$file_hash\", scraper='$scraper'   
					WHERE id='$song_id'";
			
				echo "Changes detected in {$song_id}: ".stripslashes($title)." from ".stripslashes($pack)." Updating..." . PHP_EOL;
			
					if (!mysqli_query($conn, $sql_songs_query)) {
						echo "Error: " . $sql_songs_query . PHP_EOL . mysqli_error($conn) . PHP_EOL;
					}
				
					//whether song db updates or not, delete and insert notedata for song_id
					foreach ($notedata_array as $key){
						$sql_notedata_values = $sql_notedata_values.",(\"$song_id\",\"$song_dir/\",\"".implode("\",\"",$key)."\",NOW())";
					}
					
					$sql_notedata_query = "DELETE FROM sm_notedata WHERE song_id={$song_id}";
					
					if (!mysqli_query($conn, $sql_notedata_query)) {
						echo "Error: " . $sql_notedata_query . PHP_EOL . mysqli_error($conn) . PHP_EOL;
					}
					
					$sql_notedata_query = "INSERT INTO sm_notedata (song_id, song_dir, chart_name, stepstype, description, chartstyle, difficulty, meter, radar_values, credit, display_bpm, stepfile_name, datetime) VALUES ".substr($sql_notedata_values,1); 
					
					if (!mysqli_query($conn, $sql_notedata_query)) {
						echo "Error: " . $sql_notedata_query . PHP_EOL . mysqli_error($conn) . PHP_EOL;
					}

				}else{
						
					//we will mark the existing record as "installed"
					$installed = 1;
					$scraper = 1;
					$sql_songs_query = "UPDATE sm_songs SET installed='$installed', scraper='$scraper' WHERE id='$song_id'";
						if (!mysqli_query($conn, $sql_songs_query)) {
							echo "Error: " . $sql_songs_query . PHP_EOL . mysqli_error($conn) . PHP_EOL;
						}
				}
			}
}

function addLastPlayedtoDB ($lastplayed_array){
	//This function inserts or updates song records in the sm_songsplayed table 
	global $conn;
	$lastplayedIDUpdated = array();

	foreach ($lastplayed_array as $lastplayed){
		//loop through the array and parse the lastplayed information
		//check if this entry exists already
		$sql0 = "SELECT * FROM sm_songsplayed WHERE song_dir = \"{$lastplayed['SongDir']}\" AND numplayed = \"{$lastplayed['NumTimesPlayed']}\" AND lastplayed >= \"{$lastplayed['LastPlayed']}\" AND difficulty = \"{$lastplayed['Difficulty']}\" AND stepstype = \"{$lastplayed['StepsType']}\" AND username = \"{$lastplayed['DisplayName']}\"";
		if (!$retval = mysqli_query($conn, $sql0)){
			echo "Error: " . $sql0 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
		}
		if (mysqli_num_rows($retval) == 0){
			//existing record is not found - let's either update or insert a record
			$id = "";
			//check if the number of times played has increased and update db
			$sql0 = "SELECT * FROM sm_songsplayed WHERE song_dir = \"{$lastplayed['SongDir']}\" AND numplayed < \"{$lastplayed['NumTimesPlayed']}\" AND lastplayed <= \"{$lastplayed['LastPlayed']}\" AND difficulty = \"{$lastplayed['Difficulty']}\" AND stepstype = \"{$lastplayed['StepsType']}\" AND username = \"{$lastplayed['DisplayName']}\" ORDER BY lastplayed DESC";
			if (!$retval = mysqli_query($conn, $sql0)){
				echo "Error: " . $sql0 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
			}
			if (mysqli_num_rows($retval) > 0){
				//there are updates - update the db record for song_dir
				//first let's also grab the song_id just in case the entry here is 0
				while($row = mysqli_fetch_assoc($retval)){
					$song_id = $row['song_id'];
					if($song_id == 0){
						$songInfo = lookupSongID($row['song_dir']);
						$song_id = $songInfo['id'];
					}
					$id = $row['id'];
					$sql0 = "UPDATE sm_songsplayed SET song_id = \"{$song_id}\", numplayed = \"{$lastplayed['NumTimesPlayed']}\", lastplayed = \"{$lastplayed['LastPlayed']}\", datetime = NOW() WHERE id = \"{$id}\"";
					if (!mysqli_query($conn, $sql0)){
						echo "Error: " . $sql0 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
					}
				}
			}elseif(mysqli_num_rows($retval) == 0){
				//record does not exist - insert a new row
				$songInfo = lookupSongID($lastplayed['SongDir']);
				$song_id = $songInfo['id'];
				$sql0 = "INSERT INTO sm_songsplayed (song_id,song_dir,stepstype,difficulty,username,numplayed,lastplayed,datetime) VALUES (\"{$song_id}\",\"{$lastplayed['SongDir']}\",\"{$lastplayed['StepsType']}\",\"{$lastplayed['Difficulty']}\",\"{$lastplayed['DisplayName']}\",\"{$lastplayed['NumTimesPlayed']}\",\"{$lastplayed['LastPlayed']}\",NOW())";
				if (!mysqli_query($conn, $sql0)){
					echo "Error: " . $sql0 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
				}
				$id = mysqli_insert_id($conn);
			}
			//save row ids of updated/inserted records for marking requests later
			$lastplayedIDUpdated[] = $id;
			echo $lastplayed['LastPlayed']." -- ".$songInfo['title']." from ".$songInfo['pack'].PHP_EOL;
		}elseif(mysqli_num_rows($retval) > 0){
			//echo "record already exists. No need to update/insert.";
			//Let's update the song ID, just in case it was added before a song cache scrape
			while($row = mysqli_fetch_assoc($retval)){
				$song_id = $row['song_id'];
				if($song_id == 0){
					$songInfo = lookupSongID($row['song_dir']);
					$song_id = $songInfo['id'];
					$id = $row['id'];
					$sql0 = "UPDATE sm_songsplayed SET song_id = \"{$song_id}\" WHERE id = \"{$id}\"";
					if (!mysqli_query($conn, $sql0)){
						echo "Error: " . $sql0 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
					}
				}
			}
		}
	}
	return $lastplayedIDUpdated;
}

function markRequest ($idArray){
	//This function updates the sm_requests table if requests were completed
	global $conn;
	
	foreach ($idArray as $id){
		//send ID to sm_requests to mark request as completed
		//first, we check if there is a new fully timestamped update
		$sql3 = "UPDATE sm_requests
		JOIN sm_songsplayed ON sm_songsplayed.song_id=sm_requests.song_id
		SET state = 'completed'
		WHERE sm_requests.state = 'requested' AND sm_songsplayed.id = {$id} AND sm_songsplayed.lastplayed > sm_requests.request_time AND sm_songsplayed.lastplayed > DATE(sm_songsplayed.lastplayed) 
		ORDER BY lastplayed DESC, request_time DESC";
		if (!$retval = mysqli_query($conn, $sql3)){echo "Error: " . $sql3 . PHP_EOL . mysqli_error($conn) . PHP_EOL;}
		if (mysqli_affected_rows($conn) > 0){
			echo "Marking request as complete." . PHP_EOL;
		}else{
			//if no fully timestamp update is found, we fallback to determining an update by an increase in NumTimesPlayed
			$sql3 = "UPDATE sm_requests
			JOIN sm_songsplayed ON sm_songsplayed.song_id=sm_requests.song_id
			SET state = 'completed'
			WHERE sm_requests.state = 'requested' AND sm_songsplayed.id = {$id} AND (DATE(sm_songsplayed.lastplayed) = DATE(sm_requests.request_time) OR sm_songsplayed.lastplayed = DATE(sm_songsplayed.lastplayed))  
			ORDER BY lastplayed DESC, request_time DESC";
			if (!$retval = mysqli_query($conn, $sql3)){echo "Error: " . $sql3 . PHP_EOL . mysqli_error($conn) . PHP_EOL;}
			if (mysqli_affected_rows($conn) > 0){
				echo "Marking request as complete (fallback)." . PHP_EOL;
			}
			//add the time to the lastplayed timestamp, if it's obvious what time it should be
			$sql3 = "SELECT * FROM sm_songsplayed WHERE id = {$id}";
			//echo $sql3.PHP_EOL;
			$retval3 = mysqli_fetch_assoc(mysqli_query($conn, $sql3));
			$dateTime = strtotime($retval3['datetime']);
			$lastplayedDate = strtotime($retval3['lastplayed']);
			$dateTimeDate = strtotime(date("Y-m-j",$dateTime));
			if ($dateTimeDate == $lastplayedDate){	
				$newDT = date("Y-m-j",$lastplayedDate) . " " . date("H:i:s",$dateTime);
				$sql3 = "UPDATE sm_songsplayed SET lastplayed = \"{$newDT}\" WHERE id = {$id}";
				if (!$retval = mysqli_query($conn, $sql3)){echo "Error: " . $sql3 . PHP_EOL . mysqli_error($conn) . PHP_EOL;}
				echo "Updated lastplayed timestamp from ".date("Y-m-j",$lastplayedDate)." to {$newDT}." . PHP_EOL;
			}
			
		}
	}
}

function addHighScoretoDB ($highscore_array){
	//This function adds highscore entries into the sm_scores table
	global $conn;

	foreach ($highscore_array as $highscore){
		//look for existing record and skip if found
		$sql1 = "SELECT * FROM sm_scores 
		WHERE song_dir=\"{$highscore['SongDir']}\" AND stepstype=\"{$highscore['StepsType']}\" AND difficulty=\"{$highscore['Difficulty']}\" AND score=\"{$highscore['HighScore']['Score']}\" AND datetime=\"{$highscore['HighScore']['DateTime']}\" AND username =\"{$highscore['DisplayName']}\"";
		$retval = mysqli_query($conn, $sql1);
			
		if (mysqli_num_rows($retval) == 0){
			//this record is not in the table, let's put that beautiful score in there!
			//but first, lets grab the song id from the songlist db
			$songInfo = lookupSongID($highscore['SongDir']);
			$song_id = $songInfo['id'];
			//clean quotes from song titles and packs
			$song_title = str_ireplace("\"","",$songInfo['title']);
			$song_pack = str_ireplace("\"","",$songInfo['pack']);
			//the StageAward and PeakComboAward fields can sometimes be an array and need to be converted to strings
			if(is_array($highscore['HighScore']['StageAward'])){
				$stageAward = implode(',',$highscore['HighScore']['StageAward']);
			}else{
				$stageAward = $highscore['HighScore']['StageAward'];
			}
			if(is_array($highscore['HighScore']['PeakComboAward'])){
				$peakComboAward = implode(',',$highscore['HighScore']['PeakComboAward']);
			}else{
				$peakComboAward = $highscore['HighScore']['PeakComboAward'];
			}
			
			//Let's build the VALUES string!
			$sql1_values = "(\"{$highscore['SongDir']}\",\"{$song_id}\",\"{$song_title}\",\"{$song_pack}\",\"{$highscore['Difficulty']}\",\"{$highscore['StepsType']}\",\"{$highscore['DisplayName']}\",\"{$highscore['HighScore']['Grade']}\",\"{$highscore['HighScore']['Score']}\",\"{$highscore['HighScore']['PercentDP']}\",\"{$highscore['HighScore']['Modifiers']}\",\"{$highscore['HighScore']['DateTime']}\",\"{$highscore['HighScore']['SurviveSeconds']}\",\"{$highscore['HighScore']['LifeRemainingSeconds']}\",\"{$highscore['HighScore']['Disqualified']}\",\"{$highscore['HighScore']['MaxCombo']}\",\"{$stageAward}\",\"{$peakComboAward}\",\"{$highscore['HighScore']['PlayerGuid']}\",\"{$highscore['HighScore']['MachineGuid']}\",\"{$highscore['HighScore']['TapNoteScores']['HitMine']}\",\"{$highscore['HighScore']['TapNoteScores']['AvoidMine']}\",\"{$highscore['HighScore']['TapNoteScores']['CheckpointMiss']}\",\"{$highscore['HighScore']['TapNoteScores']['Miss']}\",\"{$highscore['HighScore']['TapNoteScores']['W5']}\",\"{$highscore['HighScore']['TapNoteScores']['W4']}\",\"{$highscore['HighScore']['TapNoteScores']['W3']}\",\"{$highscore['HighScore']['TapNoteScores']['W2']}\",\"{$highscore['HighScore']['TapNoteScores']['W1']}\",\"{$highscore['HighScore']['TapNoteScores']['CheckpointHit']}\",\"{$highscore['HighScore']['HoldNoteScores']['LetGo']}\",\"{$highscore['HighScore']['HoldNoteScores']['Held']}\",\"{$highscore['HighScore']['HoldNoteScores']['MissedHold']}\",\"{$highscore['HighScore']['RadarValues']['Stream']}\",\"{$highscore['HighScore']['RadarValues']['Voltage']}\",\"{$highscore['HighScore']['RadarValues']['Air']}\",\"{$highscore['HighScore']['RadarValues']['Freeze']}\",\"{$highscore['HighScore']['RadarValues']['Chaos']}\",\"{$highscore['HighScore']['RadarValues']['Notes']}\",\"{$highscore['HighScore']['RadarValues']['TapsAndHolds']}\",\"{$highscore['HighScore']['RadarValues']['Jumps']}\",\"{$highscore['HighScore']['RadarValues']['Holds']}\",\"{$highscore['HighScore']['RadarValues']['Mines']}\",\"{$highscore['HighScore']['RadarValues']['Hands']}\",\"{$highscore['HighScore']['RadarValues']['Rolls']}\",\"{$highscore['HighScore']['RadarValues']['Lifts']}\",\"{$highscore['HighScore']['RadarValues']['Fakes']}\")"; 
				
			echo "Adding a " . $highscore['HighScore']['Grade'] . " grade for the " . $highscore['Difficulty'] . " chart of " . $song_title . " from " . $song_pack . PHP_EOL;
			
			$sql2 = "INSERT INTO sm_scores (song_dir,song_id,title,pack,difficulty,stepstype,username,grade,score,percentdp,modifiers,datetime,survive_seconds,life_remaining_seconds,disqualified,max_combo,stage_award,peak_combo_award,player_guid,machine_guid,hit_mine,avoid_mine,checkpoint_miss,miss,w5,w4,w3,w2,w1,checkpoint_hit,let_go,held,missed_hold,stream,voltage,air,freeze,chaos,notes,taps_holds,jumps,holds,mines,hands,rolls,lifts,fakes) VALUES {$sql1_values}";
			if (!mysqli_query($conn, $sql2)){
				echo "Error: " . $sql2 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
			}
		}elseif(mysqli_num_rows($retval) > 0){
			//echo "This entry already exists in the db, skipping \n";
			//Let's update the song ID, just in case it was added before a song cache scrape
			while($row = mysqli_fetch_assoc($retval)){
				$song_id = $row['song_id'];
				if($song_id == 0){
					$songInfo = lookupSongID($row['song_dir']);
					$song_id = $songInfo['id'];
					$id = $row['id'];
					$sql0 = "UPDATE sm_scores SET song_id = \"{$song_id}\" WHERE id = \"{$id}\"";
					if (!mysqli_query($conn, $sql0)){
						echo "Error: " . $sql0 . PHP_EOL . mysqli_error($conn) . PHP_EOL;
					}
				}
			}

		}
	}
}

//--------Process the JSON and run specific functions based on source type--------// 

if(isset($jsonDecoded['offline'])){
	$offlineMode = $jsonDecoded['offline'];
}else{
	$offlineMode = FALSE;
}

switch ($jsonDecoded['source']){
	case "songs":
		//recieve json from song cache scraper
		//echo "Processing song...\n";
		foreach ($jsonDecoded['data'] as $cacheFile){
			scrapeSong($cacheFile);
		}
	break;
	case "songsStart":
		//prepare scraper helper field for song scraping
		$firstRun = scrapeSongStart();
		echo $firstRun;
	break;
	case "songsEnd":
		//cleanup song ids after scraping
		scrapeSongEnd($jsonDecoded['data'][0]);
	break;
	case "lastplayed":
		//recieve json from stats scraper
		echo "Updating songs played..." . PHP_EOL;
		$lastplayedIDUpdated = addLastPlayedtoDB($jsonDecoded['data']);
		if(!empty($lastplayedIDUpdated) && $offlineMode != TRUE){
			echo "Completing song requests..." . PHP_EOL;
			markRequest($lastplayedIDUpdated);
		}
	break;
	case "highscores":
		//recieve json from stats scraper
		echo "Adding highscores to DB..." . PHP_EOL;
		addHighScoretoDB($jsonDecoded['data']);
	break;
	default:
		echo "No valid json string found." . PHP_EOL;
}

mysqli_close($conn);

?>