@echo off
wsl -d DwemerAI4Skyrim3 -- bash -c "sed -i '/php \/var\/www\/html\/HerikaServer\/connector\/websocket.php&>log.txt&/d' /etc/start_env"
echo Websocket uninstalled successfully.
pause
