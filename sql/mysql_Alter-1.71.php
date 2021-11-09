<?php
//PHP script to fix existing smrequests tables with improper utf-8 string storage
//Need to run this per db for the v1.71 migration/upgrade.
//run this .php script inside each subdomain folder

include ('config.php');

$tables = array('sm_broadcaster' => array('broadcaster'),
'sm_notedata' => array('song_dir','chart_name','description','chartstyle','credit','stepfile_name'),
'sm_requestors' => array('name'),
'sm_requests' => array('requestor'),
'sm_scores' => array('song_dir','title','pack','username'),
'sm_songs' => array('song_dir','title','subtitle','artist','pack','credit'),
'sm_songsplayed' => array('song_dir','username')
);

//connect to db
//$conn = mysqli_connect(dbhost, dbuser, dbpass, db);
//if(! $conn ) {die('Could not connect: ' . mysqli_error($conn));}
//$conn->set_charset("utf8mb4"); //may or may not need this for the conversion to work, since this line was ommited originally

foreach($tables as $table => $col){
    foreach($col as $column){
    //UPDATE [tabe] SET [column] = CONVERT(cast(CONVERT(column USING latin1) AS BINARY) USING utf8mb4);
    $sql = "UPDATE `$table` SET `$column` = CONVERT(cast(CONVERT(`$column` USING latin1) AS BINARY) USING utf8mb4)";
    echo $sql . "<br>";
    }
}

//mysqli_close();
die();

?>