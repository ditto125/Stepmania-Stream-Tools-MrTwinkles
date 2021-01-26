::update path to php installation
@echo off
cd "C:\php"
::you must add profile IDs and the optional "-auto" argument for this script to run
::example:  php.exe "%~dp0scrape_stats.php" -auto 00000000
php.exe "%~dp0scrape_stats.php"
pause