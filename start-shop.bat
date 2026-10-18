@echo off
REM Starts the shop on this PC: website, background sender (emails / WhatsApp / alerts) and the scheduler.
REM Keep the three windows open while the shop is running. Change PHP if php.exe is somewhere else.
set PHP=C:\php83\php.exe
cd /d "%~dp0"
start "Shop website - http://127.0.0.1:8000" %PHP% artisan serve
start "Shop messages (queue worker)" %PHP% artisan queue:work --tries=3 --sleep=3
start "Shop scheduler (gold rates, cart reminders)" %PHP% artisan schedule:work
echo Shop started. Open http://127.0.0.1:8000 in your browser.
