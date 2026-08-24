#!/usr/bin/env bash
# Stop the GLPI middleware started by start.sh.
cd "$(dirname "$0")"

if [ -f middleware.pid ]; then
    PID="$(cat middleware.pid)"
    if kill "$PID" 2>/dev/null; then
        echo "Stopped middleware (PID $PID)."
    else
        echo "PID $PID not running; cleaning up."
    fi
    rm -f middleware.pid
else
    # No PID file — fall back to matching the process by name.
    if pkill -f 'node .*server\.js'; then
        echo "Stopped middleware (matched by name)."
    else
        echo "No middleware process found."
    fi
fi
