<?php

//Your path to StepMania's song cache folder.
$cacheDir = "C:/Users/[USER]/AppData/Roaming/StepMania 5.1/Cache/Songs";

//location of StepMania "Save" directory
$saveDir = "C:/Users/[USER]/AppData/Roaming/StepMania 5.1/Save";
$profileDir = $saveDir."/LocalProfiles";

//location of StepMania songs folder
$songsDir = "D:/StepMania 5.1/Songs";

//list of song packs/groups to ignore while scraping
//Example: "~WIP","Secret Folder","MyDog's")
$packsIgnore = array("~WIP");

//Target url for POSTING updates to the server and uploading banner images to the server.
$target_url = "https://subdomain.smrequests.com";

//Security key. Set this to anything. All incoming requests (like from moobot) will have to include this key or they'll be discarded.
//This way people can't hit your endpoints directly without permission. This MUST match the security key on the server-side.
$security_key = "any-secret-here";

?>