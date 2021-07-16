<?php

if (php_sapi_name() == "cli") {
    // In cli-mode
} else {
	// Not in cli-mode
	if (!isset($_GET['security_key']) || $_GET['security_key'] != $security_key || empty($_GET['security_key'])){die("Fuck off");}
	$security_key = $GET['security_key'];
}

if(file_exists(__DIR__."/config.php") && is_file(__DIR__."/config.php")){
	require ('config.php');
}else{
	wh_log("config.php file not found! You must configure these scripts before running. You can find an example config.php file at config.example.php.");
	die("config.php file not found! You must configure these scripts before running. You can find an example config.php file at config.example.php.".PHP_EOL);
}

$banners_copied = $notFoundBanners = $cPacks = 0;
$fileSizeMax = 5242880; //5MB

function check_environment(){
	//check for a php.ini file
	$iniPath = php_ini_loaded_file();

	if(!$iniPath){
		//no config found
		wh_log("ERROR: A php.ini configuration file was not found. Refer to the documentation on how to configure your php envirnment for SMRequests.");
		die("A php.ini configuration file was not found. Refer to the documentation on how to configure your php envirnment for SMRequests." . PHP_EOL);
	}else{
		//config found. check for enabled extensions
		$expectedExts = array('curl','json','mbstring','SimpleXML');
		$loadedPhpExt = get_loaded_extensions();

		foreach ($expectedExts as $ext){
			if(!in_array($ext,$loadedPhpExt)){
				wh_log("ERROR: $ext extension not enabled. Please enable the extension in your config file: \"$iniPath\"");
				die("$ext extension not enabled. Please enable the extension in your config file: \"$iniPath\"" . PHP_EOL);
			}
		}
	}
}

function wh_log($log_msg){
    $log_filename = __DIR__."/log";
    if (!file_exists($log_filename)) 
    {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename.'/log_' . date('Y-m-d') . '.log';
	$log_msg = rtrim($log_msg); //remove line endings
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, date("Y-m-d H:i:s") . " -- [" . strtoupper(basename(__FILE__)) . "] : ". $log_msg . PHP_EOL, FILE_APPEND);
}

function get_version(){
	//check the version of this script against the server
	$versionFilename = __DIR__."/VERSION";

	if(file_exists($versionFilename)){
		$versionClient = file_get_contents($versionFilename);
		$versionClient = json_decode($versionClient,TRUE);
		$versionClient = $versionClient['version'];

//		if($versionServer > $versionClient){
//			wh_log("Script out of date. Client: ".$versionClient." | Server: ".$versionServer);
//			die("WARNING! Your client scripts are out of date! Update your scripts to the latest version! Exiting..." . PHP_EOL);
//		}
	}else{
		$versionClient = 0;
		wh_log("Client version not found or unexpected value. Check VERSION file in client scrapers folder.");
	}
	return $versionClient;
}

function additionalSongsFolders($saveDir){
	global $offlineMode;
	
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

function findFiles($directory) {
    $dir_paths = array ();
	foreach(glob("{$directory}/*", GLOB_ONLYDIR) as $filename) {
            $dir_paths[] = $filename;
	}
	usort( $dir_paths, function( $a, $b ) { return filemtime($b) - filemtime($a); } );
    return $dir_paths;
}

function isIgnoredPack($pack){
	global $packsIgnore;
	global $packsIgnoreRegex;

	$return = FALSE;
	if (in_array($pack,$packsIgnore)){
		$return = TRUE;
	}elseif(!empty($packsIgnoreRegex)){
		if(preg_match($packsIgnoreRegex,$pack)){
			$return = TRUE;
		}
	}
	return $return;
}

function get_banner($img_path){
	//$imgNames = array_map(function($e){
	//	return pathinfo($e, PATHINFO_FILENAME);
	//},$img_path);
	
	foreach($img_path as $img){
		if(stripos(pathinfo($img,PATHINFO_FILENAME),'banner') !== FALSE){
			$return = $img;
			break;
		}elseif(stripos(pathinfo($img,PATHINFO_FILENAME),'ban') !== FALSE){
			$return = $img;
			break;
		}elseif(stripos(pathinfo($img,PATHINFO_FILENAME),'bn') !== FALSE){
			$return = $img;
			break;
		}elseif(stripos(pathinfo($img,PATHINFO_FILENAME),'jacket') !== FALSE){
			continue;
		}elseif(stripos(pathinfo($img,PATHINFO_FILENAME),'cdtitle') !== FALSE){
			continue;
		}else{
			$return = $img;
		}
	}
	//echo $return.PHP_EOL;
	return $return;
}

function does_banner_exist($file,$pack_name){
	//quick check to see if the banner is on the server
	global $target_url;
	$return = FALSE;
	unset($ch);

	$imgName = urlencode($pack_name.'.'.strtolower(pathinfo($file,PATHINFO_EXTENSION)));
	$ch = curl_init($target_url."/images/packs/".$imgName);
	curl_setopt($ch, CURLOPT_NOBODY, TRUE);
	curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if($retcode == 200){$return = TRUE;}
	return $return;
}

function curl_upload($file,$pack_name){
	global $target_url;
	global $security_key;
	unset($ch,$post,$cFile);
	$versionClient = get_version();
	//special curl function to create the information needed to upload files
	//renaming the banner images to be consistent with the pack name
	$cFile = curl_file_create($file,'',$pack_name.'.'.strtolower(pathinfo($file,PATHINFO_EXTENSION)));
	//add the security_key to the array
	$post = array('security_key' => $security_key, 'version' => $versionClient,'file_contents'=> $cFile);
	//this curl method only works with PHP 5.5+
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$target_url."/banners.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //if true, must specify cacert.pem location in php.ini
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
	curl_setopt($ch, CURLOPT_ENCODING,'gzip,deflate');
	curl_setopt($ch, CURLOPT_POST,1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
	$result = curl_exec ($ch);
	if(curl_exec($ch) === FALSE){echo 'Curl error: '.curl_error($ch);wh_log("Curl error: ".curl_error($ch));}
	if(curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400){
		echo $result; //echo from the server-side script
		wh_log($result);
		$return = 0;
	}else{
		echo "There was an error communicating with $target_url.".PHP_EOL;
		wh_log("The server responded with error: " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "The server responded with error: " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$return = 1;
	}
	curl_close ($ch);
	return $return;
}

//check php environment
check_environment();

echo "Finding and uploading pack banner images..." . PHP_EOL;

// find all the pack/group folders
$pack_dir = findFiles($songsDir);
//add any additional songs folder(s)
//foreach (additionalSongsFolders($saveDir) as $addPack){
//	$pack_dir[] = $addPack;
//}
if(is_array($addSongsDir) && !empty($addSongsDir)){
	foreach($addSongsDir as $directory){
		$pack_dir[] = findFiles($directory);
	}
}elseif(!empty($addSongsDir)){
	$pack_dir[] = findFiles($addSongsDir);
}

$cPacks = count($pack_dir);

if ($cPacks == 0){wh_log("No pack/group folders found. Your StepMania /Songs directory may be located in \"AppData\""); die ("No pack/group folders found. Your StepMania /Songs directory may be located in \"AppData\"" . PHP_EOL);}

$img_arr = array();

foreach ($pack_dir as $path){
	
	$pack_name = $img_path = "";
	//get pack name from folder
	$pack_name = substr($path,strrpos($path,"/")+1);
	//check if pack is to be ignored and skip if it is
	if(!isIgnoredPack($pack_name)){
		//pack is not ignored
		//clean up pack name and replace spaces with underscore
		$pack_name = strtolower(preg_replace('/\s+/', '_', trim($pack_name)));
		//look for any picture file in the pack directory
		$img_path = glob("{$path}/*{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF,bmp,BMP}",GLOB_BRACE);
		
		if (isset($img_path) && !empty($img_path)){
			if(count($img_path) > 1){
				//more than 1 image found, let's search file names for which one is the banner
				$img_path = get_banner($img_path);
			}else{
				//use the first result as the pack banner
				$img_path = $img_path[0];
			}
			//echo $img_path.PHP_EOL;
			//check for filesize
			if (filesize($img_path) > $fileSizeMax){
				echo $pack_name."'s image file is too large (max size: ". $fileSizeMax / 1024^2 ."MB)!" . PHP_EOL;
				wh_log($pack_name."'s image file is too large (max size: ". $fileSizeMax / 1024^2 ."MB)!");
			}else{
				$img_arr[] = array('img_path' => $img_path,'pack_name' => $pack_name);
			}
		}else{
			echo "No banner image for ".$pack_name. PHP_EOL;
			wh_log("No banner image for ".$pack_name);
			$notFoundBanners++;
		}
	}
}

foreach ($img_arr as $img){
	//check if banner already on server
	//if(does_banner_exist($img['img_path'],$img['pack_name'])){
	//	echo "Banner for ". $img['pack_name'] . " already exists. Skipping...".PHP_EOL;
	//}else{
		//upload banner images
		if(curl_upload($img['img_path'],$img['pack_name']) === 0){
			$banners_copied++;
		}
	//}
}

$cPacks = $cPacks - $notFoundBanners;

//STATS!
echo "Uploaded ".$banners_copied." of ".$cPacks." banner images. Banners were not found for ".$notFoundBanners." packs." . PHP_EOL;
wh_log("Uploaded ".$banners_copied." of ".$cPacks." banner images. Banners were not found for ".$notFoundBanners." packs.");

?>
