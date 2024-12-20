@echo off
wsl -d DwemerAI4Skyrim3 -- bash -c "grep -Fxq 'php /var/www/html/HerikaServer/connector/websocket.php&>log.txt&' /etc/start_env"
if %ERRORLEVEL%==0 (
    echo Websocket is already installed.
) else (
    wsl -d DwemerAI4Skyrim3 -- bash -c "sed -i '/\/etc\/init.d\/apache2 restart &>\/dev\/null/i php /var/www/html/HerikaServer/connector/websocket.php&>log.txt&' /etc/start_env"
    echo Websocket installed successfully.
)
pause
