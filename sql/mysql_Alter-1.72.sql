--SQL changes for upgrades from 1.71 to 1.72:

-- rename columns to match charthash function
ALTER TABLE sm_songsplayed 
RENAME COLUMN steps_hash TO charthash;

ALTER TABLE sm_scores
RENAME COLUMN steps_hash TO charthash;

-- add charthash column to notedata
ALTER TABLE sm_notedata
ADD COLUMN charthash VARCHAR(50) AFTER chartstyle;
