ALTER TABLE sm_songsplayed 
ADD COLUMN steps_hash VARCHAR(50) AFTER difficulty,
ADD COLUMN player_guid text AFTER username;

ALTER TABLE sm_scores
ADD COLUMN steps_hash VARCHAR(50) AFTER difficulty;

ALTER TABLE sm_requests
CHANGE request_type request_type text;

ALTER TABLE sm_notedata
ADD INDEX `song_id` (`song_id`) USING BTREE;

ALTER TABLE sm_scores
ADD INDEX `song_id` (`song_id`) USING BTREE;

ALTER TABLE sm_songsplayed
ADD INDEX `song_id` (`song_id`) USING BTREE;


--Due to fixes for proper UTF-8 connection to the db,
--we will need to do some utf-8 convertions of every table
--TO DO:
--UPDATE [tabe] SET [column] = CONVERT(cast(CONVERT(column USING latin1) AS BINARY) USING utf8mb4);
--See mysql_Alter-1.71.php