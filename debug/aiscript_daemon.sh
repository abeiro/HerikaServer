#!/bin/bash

PORT=12345 # Choose a port number

# Check if the port is already in use
if nc -z localhost $PORT; then
    echo "An instance of the script is already running."
    exit 1
fi

# Start the listener in the background that always sends the current process ID
while true; do
    # Echo the current process ID (PID) whenever something connects to the port
    echo $$ | nc -lk -p $PORT -w 1 &>/dev/null
done &

LISTENER_PID=$!

# Trap signal to clean up listener on exit
trap "kill $LISTENER_PID" EXIT

# Main loop
while [ true ]; 
do 
	php /var/www/html/HerikaServer/debug/tool_quest_giver.php &>> /var/www/html/HerikaServer/log/aiscript.log;
	sleep 1;
done
