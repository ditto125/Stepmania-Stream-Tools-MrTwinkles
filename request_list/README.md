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
    * '!unplayed'* : Picks a random song that has never been played
    * '!randomroll'* : Responds with 3 random songs, which then can be requested by ID
    * '!theusual' : Picks a random song from your top 10 most requested songs
* Special Request Commands
    * '!random["something"]' : Queries the song list filtering by pack name and credit. Examples:
        * '!randomddr' : Picks a random official DDR song
        * '!randomitg' : Picks a random official ITG song
        * '!randomben' : Picks a random song charted by Ben Speirs
        * '!randomfearmix' : Picks a random song from the FEARMIX packs
* Moderator/Broadcaster Commands
    * '!skip #' : Skip the nth request (skips last request if no number is specified)
    * '!complete #' : Complete the nth request (completes last request if no number is specified) [For use as a fallback, if a request is not auto-completing.]
    * '!whitelist @user' : Whitelist a user to remove all request cooldowns
    * '!banuser @user' : Ban a user’s ability to request songs
    * '!bansong "song name" [#random]' : Ban a song by song name. Append `#random` to ban the song from random commands only.
    * '!bansongid "songID" [#random]' : Ban a song by song id. Append `#random` to ban the song from random commands only.
    * '!requesttoggle [custom message]' : Enable/Disable taking requests. A custom message can be appended when disabling requests
    * '!stepstype [singles/doubles/off]' : Set a stepstype limit (singles/doubles) for requests
    * '!meter [#/off]' : Set a max difficulty meter for requests

*Requires use of the realtime Stats.xml scraping

## Stats.php Usage

##### Configuring Request Status Indicator

Request status indicator is something that you can use to help your stream understand if you are accepting requests currently or not. It has some configuration options in CSS that allow you to place it anywhere and have it go with your stream.

* Within OBS, create a new browser source with whatever size allocation you would like to alot for the indicator
* Use the address https://[URL]/stats.php?data=requestStatus&broadcaster=[TwitchName]
* Configure your desired display CSS in the Custom CSS window in OBS

	CSS options are:
```css
.statusOFF { background-color: rgba(255, 0, 0, 255); color: Yellow;}
.statusON { background-color: rgba(120, 255, 50, 255); color: Black;}
.outputOFF { color: White; }
.outputON { color: Blue; }
```

##### Configuring Scrolling End Screen Statistics

Scrolling End Screen Statistics allows you to give a comprehensive list of the requested songs, requestor, score acheived, and any awards associated with the score obtained during the requested play.

* Within OBS, create a new browser source with whatever size allocation you would like to alot for the scrolling list
* Use the address https://[URL]/stats.php?data=EndScreenScroll&judgement=itg
 **Note**: Judgement should be either ITG or DDR depending on your theme
* Configure your desired display CSS in the Custom CSS window in OBS

Custom CSS for behavior options:

 **Note**: Default behavior is top-to-bottom-animation, below is an example of how to change that to scroll the opposite

```css
#scroll-text {
  height: 100%;
  text-align: center;
  
  /* animation properties */
  /* Negative (-200%) for top to bottom. Positive (100%) for bottom to top */
  -moz-transform: translateY(100%);
  -webkit-transform: translateY(100%);
  transform: translateY(100%);
  
  /* Modify the time to speed up or slow down the scroll speed */
  /* Change animation between top-to-bottom-animation or bottom-to-top-animation */
  -moz-animation: bottom-to-top-animation 30s linear infinite;
  -webkit-animation: bottom-to-top-animation 30s linear infinite;
  animation: bottom-to-top-animation 30s linear infinite;
}
```

Custom CSS for display options:

 **Note**: Default style is all text being white. Use any modifier in the below listing to change the colors or other attributes desired

```css
.requestor { color: white; } /* The class for ONLY the tag requestor */
.requestor-data { color: white; } /* The class for ONLY the returned data for requestor */
.song { color: white; } /* The class for ONLY the tag song */
.song-data { color: white; } /* The class for ONLY the returned data for song */
.score { color: white; } /* The class for ONLY the tag score */
.score-data { color: white; } /* The class for ONLY the returned data for score */
.award { color: white; } /* The class for ONLY the tag award */
.award-data { color: white; } /* The class for ONLY the returned data for award */
```