<?php

/////
//SM5 Stats.xml scraper
//Call this scraper each time the Stats.xml file(s) are modified.
//The scraper will not run with out specifying at least one profile ID! 
//You can run the scraper in auto-run mode, which will run the script each time a Stats.xml file changes.
//To run in auto-run mode: add "-auto" as an argument along with the profileID(s).
//For each profile you want scraped, pass the profile ID/folder as an arguement:
//Example for the Stats.xml file in the first folder, "00000000": "php scrape_stats.php 00000000".
//Add additional profile IDs by space [_]: "php scrape_stats.php 00000000 00000001".
/////

//Config

if (php_sapi_name() == "cli") {
	// In cli-mode
	//process command arguments as profile IDs and run mode
	$profileIDs = array();
	$autoRun = TRUE;
	$frequency = 5;
	$fileTime = "";

	if ($argc > 1){
		$argv = array_splice($argv,1);
		foreach ($argv as $arg){
			if (is_numeric($arg) && strlen($arg) == 8){
				$profileIDs[] = $arg;
			}elseif ($arg == "-auto"){
				$autoRun = FALSE;
			}
		}
		if (empty($profileIDs)){die("Please specify at least 1 profile ID! Usage: scrape_stats.php [-auto] 00000000");}
	}else{
		die("No arguments! Usage: scrape_stats.php [-auto] 00000000");
	}

} else {
	// Not in cli-mode
	if (!isset($_GET['security_key']) || $_GET['security_key'] != $security_key || empty($_GET['security_key'])){die("Fuck off");}
	$security_key = $GET['security_key'];

	//check for profile IDS
	if (!isset($_GET['id']) || empty($_GET['id'])){die("Please specify at least 1 profile ID! Usage: &id=00000000+00000001");}
	//check if no arguments are specified
	if (!isset($_GET['auto']) && !isset($_GET['id'])){die("No arguments! Usage: scrape_stats.php?id=00000000[+00000001]&[auto]");}

	//process command arguments as profile IDs and run mode
	$profileIDs = array();
	$autoRun = TRUE;
	$frequency = 5;
	$fileTime = '';

	//get auto mode
	if (isset($_GET['auto'])){$autoRun = FALSE;}
	//get profile IDs
	if (isset($_GET['id'])){
		$getIds = explode("+",isset($_GET['id']));
		foreach ($getIds as $getId){
			if (is_numeric($getId) && strlen($getId) == 8){
				$profileIDs[] = $getId;
			}
		}
	}
}

include ('config.php');

//

function fixEncoding($line){
	//detect and convert ascii directory string to UTF-8 (Thanks, StepMania!)
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

function parseXmlErrors($errors,$xml_file){
	unset($xml);
	//open file for fixin'
	$xml = file($xml_file);

	foreach ($errors as $error){
		if ($error->code == 9){
			//error code: 9 is "Invalid UTF-8 encoding detected"
			echo "Oh look! StepMania left us invalid UTF-8 characters in an XML file.".PHP_EOL;
			echo "I recommend removing all special characters from this song's directory name!".PHP_EOL;
			//get line number of the invalid character(s)
			$lineNo = $error->line - 1;
			//open file, fix encoding, and write new file
			//$xml = file($xml_file);
			echo "Line ".$lineNo.": [".str_replace(array("\n","\r"),'',$xml[$lineNo])."] Fixing (Temporarily)...".PHP_EOL;
			$xml[$lineNo] = fixEncoding($xml[$lineNo]);
			//write back changes to the file
			//file_put_contents($xml_file,implode("",$xml));
			$xml = implode("",$xml);
		}elseif($error->code != 9){
			//error code is not "9"
			print_r($errors);
		}
	}
	return $xml;
}

function find_statsxml($directory,$profileIDs){
	//look for any Stats.xml files in the profile directory
	$file_arr = array();
	$i = 0;
	foreach ($profileIDs as $profileID){
		foreach (glob($directory."/".$profileID."/Stats.xml",GLOB_BRACE) as $xml_file){
			//build array of file directory, IDs, and modified file times
			$file_arr[$i]['id'] = $profileID;
			$file_arr[$i]['file'] = $xml_file;
			$file_arr[$i]['ftime'] = '';
			$file_arr[$i]['mtime'] = filemtime($xml_file);
			$i++;
		}
		if (empty($file_arr)){
			exit ("Stats.xml file(s) not found! LocalProfiles directory not found in Stepmania Save directory. Also, if you are not running Stepmania in portable mode, your Stepmania Save directory may be in \"AppData\".");
		}
	}
	return $file_arr;
}

function statsXMLtoArray ($xml_file){
	//create array to store xml file
	$statsLastPlayed = array();
	$statsHighScores = array();
	$stats_arr = array();
	unset ($xml,$errors);
	$xml = FALSE;
	
	//open xml file
	libxml_clear_errors();
	libxml_use_internal_errors(TRUE);
	$xml = simplexml_load_file($xml_file);

	//check for errors with the xml file that will prevent a successful parse
	$errors = libxml_get_errors();
	if (!empty($errors)){
		//attempt to fix errors in memory then load xml (fixed) via string
		//not a great solution, but blame StepMania, not me!
		$xml_str = parseXmlErrors($errors,$xml_file);
		libxml_clear_errors();
		$xml = simplexml_load_string($xml_str);
	}

	//die if too many errors
	if(!$xml){die ("Too many errors with Stats.xml file.\n");}

	// Example xml structure of Stats.xml file:
	// $xml->SongScores->Song[11]['Dir'];
	// $xml->SongScores->Song[11]->Steps['Difficulty'];
	// $xml->SongScores->Song[11]->Steps['StepsType'];
	// $xml->SongScores->Song[11]->Steps->HighScoreList->HighScore->Grade;
	// $xml->SongScores->Song[11]->Steps->HighScoreList->HighScore->Score;
	// $xml->SongScores->Song[11]->Steps->HighScoreList->HighScore->PercentDP;
	// $xml->SongScores->Song[11]->Steps->HighScoreList->HighScore->Modifiers;
	// $xml->SongScores->Song[11]->Steps->HighScoreList->HighScore->DateTime;

	$display_name = (string)$xml->GeneralData->DisplayName;

	foreach ($xml->SongScores->Song as $song){
		$song_dir = (string)$song['Dir'];
		
		foreach ($song->Steps as $steps){		
			$steps_type = (string)$steps['StepsType']; //dance-single, dance-double, etc.
			$difficulty = (string)$steps['Difficulty']; //Beginner, Medium, Expert, etc.
			
			foreach ($steps->HighScoreList as $high_score_lists){
				$num_played = (string)$high_score_lists->NumTimesPlayed; //useful for getting popular songs
				$last_played = (string)$high_score_lists->LastPlayed; //date the song/difficulty was last played

				$dateTimeHS = array(null);
				$highScores = array();

				foreach ($high_score_lists->HighScore as $high_score){				
					$highScores[] = $high_score;
					$dateTimeHS[] = (string)$high_score->DateTime;
				}

				$dateTimeMax = max($dateTimeHS);
				if (strtotime($dateTimeMax) > strtotime($last_played)){
					$last_played = $dateTimeMax;
				}
				
				if (!empty($highScores)){
					foreach ($highScores as $highScoreSingle){
						$statsHighScores[] = array('DisplayName' => $display_name, 'SongDir' => $song_dir, 'StepsType' => $steps_type, 'Difficulty' => $difficulty, 'NumTimesPlayed' => $num_played, 'LastPlayed' => $last_played, 'HighScore' => $highScoreSingle);
					}
				}

				$statsLastPlayed[] = array('DisplayName' => $display_name, 'SongDir' => $song_dir, 'StepsType' => $steps_type, 'Difficulty' => $difficulty, 'NumTimesPlayed' => $num_played, 'LastPlayed' => $last_played);
	
			}
		}
	}

	$stats_arr = array('LastPlayed' => $statsLastPlayed, 'HighScores' => $statsHighScores);
	return $stats_arr; 
}

function curlPost($postSource, $array){
	global $target_url;
	global $security_key;
	//add the security_key to the array
	$jsonArray = array('security_key' => $security_key, 'source' => $postSource, 'data' => $array);
	//encode array as json
	$post = json_encode($jsonArray);
	//this curl method only works with PHP 5.5+
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$target_url."/status.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //if true, must specify cacert.pem location in php.ini
	curl_setopt($ch, CURLOPT_ENCODING,'gzip,deflate');
	curl_setopt($ch, CURLOPT_POST,1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$result = curl_exec ($ch);
	//$error = curl_strerror(curl_errno($ch));
	if(curl_exec($ch) === FALSE){echo 'Curl error: '.curl_error($ch);}
	curl_close ($ch);
	echo $result; //echo from the server-side script
	unset($ch,$result,$post,$jsonArray);
}

$file_arr = find_statsxml ($profileDir,$profileIDs);

if (!$autoRun){
	echo "\\\\\\\\\\\\\\\\\\AUTO MODE ENABLED////////\n";
}

//endless loop
for (;;){

	foreach ($file_arr as &$file){

		$file['mtime'] = filemtime($file['file']);
		if ($file['ftime'] <> $file['mtime']) {
			echo "Starting scrape of profile ".$file['id']."...\n";
			$stats_arr = statsXMLtoArray ($file['file']);
			//LastPlayed
			curlPost("lastplayed", $stats_arr['LastPlayed']);
			//HighScores
			curlPost("highscores", $stats_arr['HighScores']);
			echo "Done \n";
			unset($stats_arr);
		}
		$file['ftime'] = $file['mtime'];
	}
	if ($autoRun){
		//autorun was not set, break the loop
		break;
	}
	clearstatcache();
	sleep($frequency);
}
?>