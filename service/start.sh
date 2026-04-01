#!/bin/bash

PORT=12345
SERVICE_LOG="/var/www/html/HerikaServer/log/service.log"
FALLBACK_SERVICE_LOG="/tmp/herika_service.log"
LOG_DIR="$(dirname "$SERVICE_LOG")"

# Ensure new files are group-readable/writable for dwemer + www-data workflows.
umask 0002

mkdir -p "$LOG_DIR" 2>/dev/null
if ! touch "$SERVICE_LOG" 2>/dev/null; then
    SERVICE_LOG="$FALLBACK_SERVICE_LOG"
    touch "$SERVICE_LOG" 2>/dev/null || true
fi

# Check if port is in use
if nc -z localhost "$PORT" 2>/dev/null; then
    echo "An instance of the script is already running."
    exit 1
fi

# Function for the listener that checks parent PID
listener() {
    local parent_pid=$1
    while true; do
        # Check if parent is still running
        if ! kill -0 "$parent_pid" 2>/dev/null; then
            # Parent is gone → exit listener
            exit 0
        fi
        # Accept one connection and send PID
        echo "$parent_pid" | nc -l -p "$PORT" -w 1 &>/dev/null
        # Optional: small delay to avoid busy loop if no connections
        sleep 0.1
    done
}

# Start listener in background
listener $$ &
LISTENER_PID=$!

# Set trap for clean exit (still useful for SIGTERM, etc.)
# Also kills relationship worker daemon and cleans up PID file
trap "kill $LISTENER_PID 2>/dev/null; pkill -f 'relationship_system/worker.php.*--daemon' 2>/dev/null; rm -f /var/www/html/HerikaServer/log/relationship_worker.pid 2>/dev/null" EXIT

# Main loop
while true; do 
    php /var/www/html/HerikaServer/service/manager.php &>> "$SERVICE_LOG"
    sleep 5
done
