::update path to php installation
cd "C:\php"
::you must add profile IDs and the optional "-auto" argument for this script to run
::example:  php.exe "%~dp0scrape_stats.php" -auto 00000000
php.exe "%~dp0scrape_stats.php"
pause