<?php

include ('config.php');

$fileSizeMax = 5242880; //5MB
	
// recieve upload of banner images via POST, FILES
if(!isset($_POST["security_key"]) || $_POST["security_key"] != $security_key || empty($_POST["security_key"])){
    die("Fuck off");
}

if($_FILES['file_contents']['size'] > $fileSizeMax){
	die($_FILES['file_contents']['name']."'s image file is too large (max size: ". $fileSizeMax / 1024^2 ."MB)!\n");
}

$uploadfile = $uploaddir .'/'. $_FILES['file_contents']['name'];

if(!file_exists($uploadfile)){
	if (move_uploaded_file($_FILES['file_contents']['tmp_name'], $uploadfile)) {
		echo "Successfully uploaded banner for ".$_FILES['file_contents']['name']."\n";
	}else{
		echo "Possible file upload attack!\n";
	}
}else{
	if(filesize($uploadfile) == $_FILES['file_contents']['size']){
		echo "File already exists for ".$_FILES['file_contents']['name']."\n";
	}else{
		if(move_uploaded_file($_FILES['file_contents']['tmp_name'], $uploadfile)) {
			echo "Successfully updated banner for ".$_FILES['file_contents']['name']."\n";
		}else{
			echo "Possible file upload attack!\n";
		}
	}	
}

//echo 'Here is some more debugging info:';
//print_r($_FILES);

?>