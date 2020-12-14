# What is this?

The scraper parses all the high score entries from your StepMania profile folder. This can be used as a record of songs played, session high score table, or used for special request commands based on scores. I am using this stats scraper method as my request list "check-off" script instead of the python script method. 

# How to use this

Run the "scrape_stats.php" from the cli. In this folder is a powershell script, which triggers this script everytime any Stats.xml file is updated (usually at the song evaluation screen).


# Banner uploading

Run the upload_banners.php in the CLI to upload pack banners. This script finds the first image file in each pack folder, formats the name to match the name of the pack, and uploads it to the server.
