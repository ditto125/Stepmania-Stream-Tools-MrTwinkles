::update path to php installation
@echo off
cd "C:\php"
cls
::an optional "-auto" argument is used to allow the scraper to run continuously
::example:  php.exe "%~dp0scrape_stats.php" -auto
php.exe "%~dp0scrape_stats.php -auto"
pause