# File Descriptions and Usage

## Pages/End-points
* broadcaster.php -- end point to set/control options such as requests toggle and chart limitations
* rand_request.php -- end point for random requests
* request.php -- end point for standard requests
* requestors.php -- end point to whitelist and ban users
* show_requests.php -- song request stream widget
* song_admin.php -- end point for banning songs
* songlist.php -- webpage that shows the public song list
* stats.php -- end point for pulling session/requestor stats

## Bot Commands Available
(The actual bot command is arbitrary. These are supplied as examples)
* Songlist
    * '!songlist' : Link to a webpage of songs that can be requested
* Request Commands
    * '!request "song name"' : Request a song by song title lookup
    * '!requestid "songID"' : Request a song by its unique ID
    * '!cancel #' : Cancel your nth request (cancels last request if no number is specified)
* Random Request Commands
    * '!random'* : Picks a random song that has been played at least once
    * '!top'* : Picks a random song from the top 100 most played songs
    * '!portal' : Picks any random song
    * '!gitgud'* : Picks a random song from the lowest scoring 25 of the top 100 most played songs
    * '!randomroll'* : Responds with 3 random songs, which then can be requested by ID
    * '!theusual' : Picks a random song from your top 10 most requested songs
* Special Request Commands
    * '!random["something"]' : Queries the song list filtering by pack name and credit. Examples:
        * '!randomddr' : Picks a random official DDR song
        * '!randomben' : Picks a random song charted by Ben Speirs
* Moderator/Broadcaster Commands
    * '!skip #' : Skip the nth request (skips last request if no number is specified)
    * '!complete #' : Complete the nth request (completes last request if no number is specified) [For use as a fallback, if a request is not auto-completing.]
    * '!whitelist @user' : Whitelist a user to remove all request cooldowns
    * '!banuser @user' : Ban a user’s ability to request songs
    * '!bansong "song name"' : Ban a song by song name
    * '!bansongid "songID"' : Ban a song by song id
    * '!banrandom "song name"' : Ban a song from random commands by song name
    * '!banrandomid "songID"' : Ban a song from random commands by song id
    * '!requesttoggle [custom message]' : Enable/Disable taking requests. A custom message can be appended when disabling requests
    * '!stepstype [singles/doubles/off]' : Set a stepstype limit (singles/doubles) for requests
    * '!meter [#/off]' : Set a max difficulty meter for requests

*Requires use of the realtime Stats.xml scraping