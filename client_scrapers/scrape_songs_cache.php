<?php

// PHP "Song scraper" for Stepmania
// https://github.com/DaveLinger/Stepmania-Stream-Tools
// This script scrapes your Stepmania cache directory for songs and posts each unique song to a mysql database table.
// It cleans [TAGS] from the song titles and it saves a "search ready" version of each song title (without spaces or special characters) to the "strippedtitle" column.
// This way you can have another script search/parse your entire song library - for example to make song requests.
// You only need to re-run this script any time you add new songs and Stepmania has a chance to build its cache. It'll skip songs that already exist in the DB.
// The same exact song title is allowed to exist in different packs.
//
// Run this from the command line like this: "php scrape_songs_cache.php"
//
// "Wouldn't it be nice" future features?:
// 
// 2. Automatically upload each SONG's banner to the remote server (optional - this would use a lot of remote storage space)

// Configuration

if (php_sapi_name() == "cli") {
    // In cli-mode
} else {
	// Not in cli-mode
	if (!isset($_GET['security_key']) || $_GET['security_key'] != $security_key || empty($_GET['security_key'])){die("Fuck off");}
	$security_key = $GET['security_key'];
}

include ('config.php');

// Code

function wh_log($log_msg){
    $log_filename = __DIR__."/log";
    if (!file_exists($log_filename)) 
    {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename.'/log_' . date('Y-m-d') . '.log';
	$log_msg = str_replace(array("\r", "\n"), '', $log_msg); //remove line endings
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, date("Y-m-d H:i:s") . " -- [" . strtoupper(basename(__FILE__)) . "] : ". $log_msg . PHP_EOL, FILE_APPEND);
}

function fixEncoding($line){
	//detect and convert ascii, et. al directory string to UTF-8 (Thanks, StepMania!)
	$encoding = mb_detect_encoding($line,'UTF-8,CP1252,ASCII,ISO-8859-1');
	if($encoding != 'UTF-8'){
		//echo "Invalid UTF-8 detected ($encoding). Converting...\n";
		$line = mb_convert_encoding($line,'UTF-8',$encoding);
		//echo "Text: ".$line."\n";
	}elseif($encoding == FALSE || empty($encoding)){
		//encoding not detected, assuming 'ISO-8859-1', again, thanks, StepMania.
		$encoding = 'ISO-8859-1';
		//echo "Invalid UTF-8 detected ($encoding) (fallback). Converting...\n";
		$line = mb_convert_encoding($line,'UTF-8',$encoding);
		//echo "Text: ".$line."\n";
	}
	//afer conversion we check AGAIN to confirm the new line is encoded as UTF-8
	if(mb_detect_encoding($line) != 'UTF-8'){
		//string still has invalid characters, give up and remove them completely
		$line = mb_convert_encoding($line,'UTF-8','UTF-8');
	}
	return $line;
}

function parseMetadata($file) {
	$file_arr = array();
	$lines = array();
	$delimiter = ":";
	$eol = ";";
	
	//$data = utf8_encode(file_get_contents($file));
	$data = file_get_contents($file);
	$data = substr($data,0,strpos($data,"//-------"));
	
	$file_arr = preg_split("/{$eol}/",$data);
	//print_r($file_arr);
	
	foreach ($file_arr as $line){
		// if there is no $delimiter, set an empty string
			$line = trim($line);
			if (substr($line,0,1) == "#"){
				if (stripos($line,$delimiter)===FALSE){
					$key = $line;
					$value = "";
			// esle treat the line as normal with $delimiter
				}else{
					$key = substr($line,0,strpos($line,$delimiter));
					$value = substr($line,strpos($line,$delimiter)+1);
				}
				$value = fixEncoding($value);
				$lines[trim($key,'"')] = trim($value,'"');	
			}
			
	}
	
	return $lines;
}

function parseNotedata($file) {
	$file_arr = array();
	$lines = array();
	$delimiter = ":";
	$eol = ";";
	$notedata_array = array();
	
	$data = file_get_contents($file);

	if( strpos($data,"#NOTEDATA:")){
		$data = substr($data,strpos($data,"//-------"));
		$data = substr($data,strpos($data,"#"));
		
	//getting notedata info...
			$notedata_array = array();
			
				$notedata_total = substr_count($data,"#NOTEDATA:"); //how many step charts are there?
				$notedata_offset = 0;
				$notedata_next = 0;
				$notedata_count = 1;
				//start from the first occurance of notedata, set found data to array
				while ($notedata_count <= $notedata_total){ 
					$notedata_offset = strpos($data, "#NOTEDATA:",$notedata_next);
					$notedata_next = strpos($data, "#NOTEDATA:",$notedata_offset + strlen("#NOTEDATA:"));
						if ($notedata_next === FALSE){
							$notedata_next = strlen($data);
						}
					
					$data_sub = substr($data,$notedata_offset,$notedata_next-$notedata_offset);
					$file_arr = "";
					$file_arr = preg_split("/{$eol}/",$data_sub);
					
					foreach ($file_arr as $line){
						$line = trim($line);
						//only process lines beginning with '#'
						if (substr($line,0,1) == "#"){
							// if there is no $delimiter, set an empty string
							if (stripos($line,$delimiter)===FALSE){
								$key = $line;
								$value = "";
						// esle treat the line as normal with $delimiter
							}else{
								$key = substr($line,0,strpos($line,$delimiter));
								$value = substr($line,strpos($line,$delimiter)+1);
							}
							$value = fixEncoding($value);
							// trim any quotes (messes up later queries)
							$lines[trim($key,'"')] = trim($value,'"');	
						}	
					}
					
					//build array of notedata chart information
					
				//Not all chart files have these descriptors, so let's check if they exist to avoid notices/errors	
					array_key_exists('#CHARTNAME',$lines) 	? addslashes($lines['#CHARTNAME']) 	: $lines['#CHARTNAME']   = "";
					array_key_exists('#DESCRIPTION',$lines) ? addslashes($lines['#DESCRIPTION']): $lines['#DESCRIPTION'] = "";
					array_key_exists('#CHARTSTYLE',$lines)  ? addslashes($lines['#CHARTSTYLE']) : $lines['#CHARTSTYLE']  = "";
					array_key_exists('#CREDIT',$lines)      ? addslashes($lines['#CREDIT']) 	: $lines['#CREDIT']      = "";
					
					if( array_key_exists('#DISPLAYBPM',$lines)){
						if( strpos($lines['#DISPLAYBPM'],':') > 0){
							$display_bpmSplit = array();
							$display_bpmSplit = preg_split("/:/",$lines['#DISPLAYBPM']);
							$lines['#DISPLAYBPM'] = intval($display_bpmSplit[0],0)."-".intval($display_bpmSplit[1],0);
						}else{
							$lines['#DISPLAYBPM'] = intval($lines['#DISPLAYBPM'],0);
						}
					}else{
						  $lines['#DISPLAYBPM']  = "";
					}
					
					$notedata_array[] = array('chartname' => $lines['#CHARTNAME'], 'steptype' => $lines['#STEPSTYPE'], 'description' => $lines['#DESCRIPTION'], 'chartstyle' => $lines['#CHARTSTYLE'], 'difficulty' => $lines['#DIFFICULTY'], 'meter' => $lines['#METER'], 'radarvalues' => $lines['#RADARVALUES'], 'credit' => $lines['#CREDIT'], 'displaybpm' => $lines['#DISPLAYBPM'], 'stepfilename' => $lines['#STEPFILENAME']);

					$notedata_count++;
				}
	}
	
	return $notedata_array;
}

function prepareCacheFiles($filesArr){
	//sort files by last modified date
	echo "Sorting cache files by modified date..." . PHP_EOL;
	wh_log("Sorting cache files by modified date...");
	$micros = microtime(true);
	usort( $filesArr, function( $a, $b ) { return filemtime($b) - filemtime($a); } );
	echo ("Sort time: ".round(microtime(true) - $micros,3)." secs." . PHP_EOL);

	return $filesArr;
}

function isIgnoredPack($songFilename){
	global $packsIgnore;
	global $packsIgnoreRegex;

	$return = FALSE;
	if(!empty($songFilename)){
		//song has a an associated simfile
		$song_dir = substr($songFilename,1,strrpos($songFilename,"/")-1); //remove benginning slash and file extension

		//Get pack name
		$pack = substr($song_dir, 0, strripos($song_dir, "/"));
		$pack = substr($pack, strripos($pack, "/")+1);
		//if the pack is on ignore list, skip it
		if (in_array($pack,$packsIgnore)){
			$return = TRUE;
		}elseif(!empty($packsIgnoreRegex)){
			if(preg_match($packsIgnoreRegex,$pack)){
				$return = TRUE;
			}
		}
	}
	return $return;
}

function doesFileExist($songFilename){
	global $songsDir;
	global $offlineMode;
	global $addSongDirs;

	//if offline mode is set, always return TRUE
	if($offlineMode){
		$return = TRUE;
		return $return;
	}

	$return = FALSE;

	//fix possible character encoding
	//convert string to UTF-8 then back to ISO-8859-1 so Windows can understand it
	$songFilename = fixEncoding($songFilename);
	$songFilename = utf8_decode($songFilename);

	//check if the chart file exists on the filesystem
	if(substr($songFilename,0,strpos($songFilename,"/",1)+1) == "/Songs/"){
		//file is in the normal "Songs" folder
		$songFilename = str_replace("/Songs/",$songsDir."/",$songFilename);
		if(file_exists($songFilename)){
			$return = TRUE;
		}else{
			//echo "File: ".$songFilename."\n";
			wh_log("File Not Found: ".$songFilename);
		}
	}elseif(substr($songFilename,0,strpos($songFilename,"/",1)+1) == "/AdditionalSongs/"){
		//file is in one of the "AdditionalSongs" folder(s)
		foreach($addSongDirs as $songsDir){
			//loop through the "AdditionalSongsFolders"
			$songFilename = str_replace("/AdditionalSongs/",$songsDir."/",$songFilename);
			if(file_exists($songFilename)){
				$return = TRUE;
			}else{
				//echo "File: ".$songFilename."\n";
				wh_log("File Not Found: ".$songFilename);
			}
		}
	}
	return $return;
}

function additionalSongsFolders($saveDir){
	//read StepMania 5.x Preferences.ini file and extract the "AdditionalSongFolders" to an array
	$prefFile = $saveDir."/Preferences.ini";
	$addSongDirs = array();

	//if offline mode is set, always return empty
	if($offlineMode){
		return $addSongDirs;
	}

	if(file_exists($prefFile)){
		$lines = file($prefFile);
		foreach ($lines as $line){
			$addSongFolder = substr(strstr($line,"AdditionalSongFolders="),22);
			if(strlen($addSongFolder) > 1){
				//file exists, line is in file, and line contains at least 1 directory
				//directories are delimited by ","
				$addSongDirs = array_map('trim',explode(',',$addSongFolder));
			break;
			}
		}
		wh_log("Preferences.ini file loaded. Adding directories: " . implode(',',$addSongDirs));
	}else{
		wh_log("Preferences.ini file not found!");
	}
	return $addSongDirs;
}

function parseJsonErrors($error,$jsonArray){
	if($error == "JSON_ERROR_UTF8"){
		echo json_last_error_msg().PHP_EOL;
		echo "One of these files has an error. Correct the special character in the song folder name and re-run the script.".PHP_EOL;
		wh_log("One of these files has an error. Correct the special character in the song folder name and re-run the script.");
		foreach($jsonArray['data'] as $cacheFile){
			//echo $cacheFile['metadata']['#SONGFILENAME'].PHP_EOL;
			$songFilename = $cacheFile['metadata']['#SONGFILENAME'];
			echo $songFilename.PHP_EOL;
			wh_log($songFilename);
		}
		die();
	}else{
		wh_log(json_last_error_msg());
		die(json_last_error_msg().PHP_EOL);
	}
}

function curlPost($postSource, $array){
	global $target_url;
	global $security_key;
	unset($ch,$result,$post,$jsonArray,$errorJson);
	//add the security_key to the array
	$jsonArray = array('security_key' => $security_key, 'source' => $postSource, 'data' => $array);
	//encode array as json
	$post = json_encode($jsonArray);
	$errorJson = json_last_error();
	if($errorJson != "JSON_ERROR_NONE"){
		//there was an error with the json string
		parseJsonErrors($errorJson,$jsonArray);
		die();
	}
	//this curl method only works with PHP 5.5+
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$target_url."/status.php?$postSource");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_ENCODING,'gzip,deflate');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //if true, must specify cacert.pem location in php.ini
	curl_setopt($ch, CURLOPT_POST,1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
	$result = curl_exec ($ch);
	if(curl_exec($ch) === FALSE){echo 'Curl error: '.curl_error($ch);wh_log("Curl error: ".curl_error($ch));}
	echo $result; //echo from the server-side script
	wh_log($result);
	echo (round(curl_getinfo($ch)['total_time_us'] / 1000000,3)." secs." . PHP_EOL);
	wh_log(round(curl_getinfo($ch)['total_time_us'] / 1000000,3)." secs");
	curl_close ($ch);
	//print_r($result);
	return $result;
}

//get start time
$microStart = microtime(true);

$files = array ();
foreach(glob("{$cacheDir}/*", GLOB_BRACE) as $file) {
    $files[] = $file;
}

if(count($files) == 0){wh_log("No files. Songs cache directory not found in Stepmania directory. You must start Stepmania before running this software. Also, if you are not running Stepmania in portable mode, your Stepmania directory may be in \"AppData\"."); die("No files. Songs cache directory not found in Stepmania directory. You must start Stepmania before running this software. Also, if you are not running Stepmania in portable mode, your Stepmania directory may be in \"AppData\".");}

$i = 0;
$chunk = 500;

//prepare sm_songs database for scraping and check if this is a first-run
echo "Preparing database for song scraping..." . PHP_EOL;
wh_log("Preparing database for song scraping...");

$firstRun = curlPost("songsStart",array(0));

//loop through cache files, process to json strings, and post to the webserver for further processing
$totalFiles = count($files);
echo "Looping through ".$totalFiles." cache files..." . PHP_EOL;
wh_log("Looping through ".$totalFiles." cache files...");
$totalChunks = ceil($totalFiles / $chunk);
$currentChunk = 1;
if ($firstRun != TRUE){
	//only sort files if NOT first run
	$files = prepareCacheFiles($files);
}

//read preferences.ini file for AddtionalSongsFolder(s)
$addSongDirs = additionalSongsFolders($saveDir);

//print_r($files);
$files = array_chunk($files,$chunk,true);
foreach ($files as $filesChunk){
	unset($cache_array,$cache_file,$metadata,$notedata_array);
	foreach ($filesChunk as $file){	
		//get md5 hash of file to determine if there are any updates
		$file_hash = md5_file($file);
		$metadata = parseMetadata($file);
		$metadata['file_hash'] = $file_hash;
		$metadata['file'] = fixEncoding(basename($file));
		$notedata_array = parseNotedata($file);
		//sanity on the file, if no filename or notedata, ignore
		if (isset($metadata['#SONGFILENAME']) && !empty($metadata['#SONGFILENAME']) && !empty($notedata_array)){
			//check if this file is in an ignored pack and that the chart file exists
			if (isIgnoredPack($metadata['#SONGFILENAME']) == FALSE && doesFileExist($metadata['#SONGFILENAME']) == TRUE){
				$cache_file = array('metadata' => $metadata, 'notedata' => $notedata_array);
				$cache_array[] = $cache_file;
				$i++;
			}else{
				echo $metadata['file']." is either in an Ignored Pack or the orginal chart file is missing!" . PHP_EOL;
				wh_log($metadata['file']." is either in an Ignored Pack or the orginal chart file is missing!");
			}
		}else{
			echo "There was an error with: [".$metadata['file']."]. No chartfile or NOTEDATA found! Skipping..." . PHP_EOL;
			wh_log("There was an error with: [".$metadata['file']."]. No chartfile or NOTEDATA found! Skipping...");
		}
	}
	echo "Sending ".$currentChunk." of ".$totalChunks." chunk(s) via cURL..." . PHP_EOL;
	wh_log("Sending ".$currentChunk." of ".$totalChunks." chunk(s) via cURL...");
	curlPost("songs", $cache_array);
	$currentChunk++;
}

//mark songs as (not)installed
echo "Finishing up..." . PHP_EOL;
wh_log("Finishing up...");
curlPost("songsEnd",array($i));

//display time
echo (PHP_EOL . "Total time: ". round((microtime(true) - $microStart)/60,1) . " mins." . PHP_EOL);
wh_log("Total time: ". round((microtime(true) - $microStart)/60,1) . " mins.");

//

// Let's clean up the sm_songs db, removing records that are not installed, have never been requested, never played, or don't have a recorded score
	//echo "Purging song database and cleaning up...";
	//$sql_purge = "DELETE FROM sm_songs 
	//			WHERE NOT EXISTS(SELECT NULL FROM sm_requests WHERE sm_requests.song_id = sm_songs.id LIMIT 1) AND NOT EXISTS (SELECT NULL FROM sm_scores WHERE sm_scores.song_id = sm_songs.id LIMIT 1) AND NOT EXISTS (SELECT NULL FROM sm_songsplayed WHERE sm_songsplayed.song_id = sm_songs.id LIMIT 1) AND sm_songs.installed<>1";
	//if (!mysqli_query($conn, $sql_purge)) {
	//		echo "Error: " . $sql_purge . "\n" . mysqli_error($conn);
	//	}

//

?>