<?php

include ('config.php');

$fileSizeMax = 5242880; //5MB
	
// recieve upload of banner images via POST, FILES
if(!isset($_GET["security_key"]) || $_GET["security_key"] != $security_key || empty($_GET["security_key"])){
    die("Fuck off");
}

$uploadfile = $uploaddir .'/'. $_FILES['file_contents']['name'];

If (!file_exists($uploadfile) && $_FILES['file_contents']['size'] <= $fileSizeMax){
	if (move_uploaded_file($_FILES['file_contents']['tmp_name'], $uploadfile)) {
		echo "Successfully uploaded banner for ".$_FILES['file_contents']['name']."\n";
	}else{
		echo "Possible file upload attack!\n";
	}
}else{
	echo "File already exists for ".$_FILES['file_contents']['name']."\n";
}

//echo 'Here is some more debugging info:';
//echo $_FILES;

?>