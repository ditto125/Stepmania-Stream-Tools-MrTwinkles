# What is this?

These scrapers parse all local StepMania data required to run the request system. A pre-configured PHP cli environment is required. For Windows systems, convenient .bat files are provided.

## Scrape Songs Cache

This script parses the song cache files to add/update songs to the sm_songs and sm_notedata tables.

## Banner uploading

This script finds the first image file in each pack folder, formats the name to match the name of the pack, and uploads it to the server.

## Scrape Stats

The stats scraper is responsible for grabbing all your highscores and recently played songs from your Stats.xml file(s). An "-auto" argument can be added to the run command to automatically run when the Stats.xml is updated (usually at the song evaluation screen). You must specify with profiles to scrape by profile ID number.

# Usage
* Run "scrape stats.bat" everytime you stream to populate your sm_songsplayed and sm_scores DB tables. This script is also responsible for completing/marking-off requests on the request board.
* Run "scrape new songs.bat" when you add new songs to StepMania (and allowed StepMania to build new cache files).
    * A  note on deleting songs or song packs/groups: StepMania does not automatically remove the associated cache files after a song is deleted. Make sure you delete the song's cache file before running the scrape so that the scraper can remove the song from the sm_songs table.
* Run "upload banners.bat" to upload new pack/group banners to the server to later be used as song background images on the request board.
