<?php

if (php_sapi_name() == "cli") {
    // In cli-mode
} else {
	// Not in cli-mode
	if (!isset($_GET['security_key']) || $_GET['security_key'] != $security_key || empty($_GET['security_key'])){die("Fuck off");}
	$security_key = $GET['security_key'];
}

include ('config.php');

$banners_copied = 0;
$notFoundBanners = 0;
$cPacks = 0;
$fileSizeMax = 5242880; //5MB

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

function curl_upload($file,$pack_name){
	global $target_url;
	global $security_key;
	unset($ch,$post,$cFile);
	//special curl function to create the information needed to upload files
	//renaming the banner images to be consistent with the pack name
	$cFile = curl_file_create($file,'',$pack_name.'.'.strtolower(pathinfo($file,PATHINFO_EXTENSION)));
	//add the security_key to the array
	$post = array('security_key' => $security_key,'file_contents'=> $cFile);
	//this curl method only works with PHP 5.5+
	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_URL,$target_url."/banners.php");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //if true, must specify cacert.pem location in php.ini
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
	curl_setopt($ch, CURLOPT_ENCODING,'gzip,deflate');
	curl_setopt($ch, CURLOPT_POST,1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
	$result = curl_exec ($ch);
	$error = curl_strerror(curl_errno($ch));
	//print_r(curl_getinfo($ch));
	curl_close ($ch);
	echo $result; //echo from the server-side script
	wh_log($result);

	return $error;
}

// find all the pack/group folders
$pack_dir = findFiles($songsDir);
//add any additional songs folder(s)
foreach (additionalSongsFolders($saveDir) as $addPack){
	$pack_dir[] = $addPack;
}
$cPacks = count($pack_dir);

if ($cPacks == 0){wh_log("No pack/group folders found. Your StepMania /Songs directory may be located in \"AppData\""); die ("No pack/group folders found. Your StepMania /Songs directory may be located in \"AppData\"" . PHP_EOL);}

$img_arr = array();

foreach ($pack_dir as $path){
	
	$pack_name = "";
	$img_path = "";
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
			//use the first result as the pack banner and add to array
			//check for filesize
			if (filesize($img_path[0]) > $fileSizeMax){
				echo $pack_name."'s image file is too large (max size: ". $fileSizeMax / 1024^2 ."MB)!" . PHP_EOL;
				wh_log($pack_name."'s image file is too large (max size: ". $fileSizeMax / 1024^2 ."MB)!");
			}else{
				$img_arr[] = array('img_path' => $img_path[0],'pack_name' => $pack_name);
			}
		}else{
			echo "No banner image for ".$pack_name. PHP_EOL;
			wh_log("No banner image for ".$pack_name);
			$notFoundBanners++;
		}
	}
}

foreach ($img_arr as $img){
	//upload banner images
	$cError = curl_upload($img['img_path'],$img['pack_name']);
	//output any errors from the curl upload
	if ($cError != "No error"){
		echo "CURL Error: ".$cError."\n";
		wh_log("CURL Error: ".$cError);
	}else{
		$banners_copied++;
	}
}

$cPacks = $cPacks - $notFoundBanners;

//STATS!
echo "Uploaded ".$banners_copied." of ".$cPacks." banner images. Banners were not found for ".$notFoundBanners." packs." . PHP_EOL;
wh_log("Uploaded ".$banners_copied." of ".$cPacks." banner images. Banners were not found for ".$notFoundBanners." packs.");

?>