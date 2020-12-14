# What is this?

The scraper parses all the high score entries from your StepMania profile folder. This is used as a record of songs played, session high score table, or used for special request commands based on scores. I am using this stats scraper method as my request list "check-off" script instead of the python script method. 

# Scrape Stats

Run the "scrape_stats.php" from the cli. An "-auto" argument can be added to the run command to scrape Stats.xml file(s) when they update (usually at the song evaluation screen).

# Scrape Songs Cache

Run the "scrape_songs_cache.php" from the cli to scrape new songs to the sm_songs and sm_notedata tables.

# Banner uploading

Run the upload_banners.php in the CLI to upload pack banners. This script finds the first image file in each pack folder, formats the name to match the name of the pack, and uploads it to the server.
