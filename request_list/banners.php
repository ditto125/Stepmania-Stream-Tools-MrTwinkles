<?php

require_once ('config.php');
require_once ('misc_functions.php');

$fileSizeMax = 5242880; //5MB
	
// recieve upload of banner images via POST, FILES
//Make sure that it is a POST request.
if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0){
    die("Request method must be POST!" . PHP_EOL);
}

//Get access token/security key from http header
if(isset($_SERVER['HTTP_KEY'])){
	$keyToken = trim($_SERVER['HTTP_KEY']);
	if(empty($keyToken)){
		die("Fuck off" . PHP_EOL);
	}
	$keyToken = base64_decode($keyToken);
	if($keyToken != $security_key){
		die("Fuck off" . PHP_EOL);
	}
}else{
	die("No valid HTTP security_key header" . PHP_EOL);
}

//if(!isset($_POST["security_key"]) || $_POST["security_key"] != $security_key || empty($_POST["security_key"])){die("Fuck off");}

//get version of client
if(isset($_POST['version'])){
	$versionClient = $_POST['version'];
}else{
	$versionClient = 0;
}
//is the client script up to date?
check_version($versionClient);

if($_FILES['file_contents']['size'] > $fileSizeMax){
	die($_FILES['file_contents']['name']."'s image file is too large (max size: ". $fileSizeMax / 1024^2 ."MB)!".PHP_EOL);
}

$uploadfile = $uploaddir .'/'. $_FILES['file_contents']['name'];

if(!file_exists($uploadfile)){
	//No banner exists, move the temp uploaded file to the banner directory
	if (move_uploaded_file($_FILES['file_contents']['tmp_name'], $uploadfile)) {
		echo "Successfully uploaded banner for ".$_FILES['file_contents']['name'].PHP_EOL;
	}else{
		echo "Possible file upload attack!".PHP_EOL;
	}
}else{
	//a banner has been found, check if the filesize has changed
	if(filesize($uploadfile) == $_FILES['file_contents']['size']){
		//filesize is the same, *probably* the same image
		echo "File already exists for ".$_FILES['file_contents']['name'].PHP_EOL;
	}else{
		//check if any image exists for the pack name
		$files = glob($uploaddir."/".pathinfo($_FILES['file_contents']['name'],PATHINFO_FILENAME).".{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF,bmp,BMP}",GLOB_BRACE);
		if(count($files) > 0){
			//an image exists for this pack name, but is a different file, remove it
			array_map('unlink',$files);
			echo "Removed previous banner image for ".$_FILES['file_contents']['name'].PHP_EOL;
		}
		//update banner image with the newly updated file
		if(move_uploaded_file($_FILES['file_contents']['tmp_name'], $uploadfile)) {
			echo "Successfully updated banner for ".$_FILES['file_contents']['name'].PHP_EOL;
		}else{
			echo "Possible file upload attack!".PHP_EOL;
		}
	}	
}

die();
//echo 'Here is some more debugging info:';
//print_r($_FILES);
?>