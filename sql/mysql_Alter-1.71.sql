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