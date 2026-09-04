#!/usr/bin/env bash
# Start the GLPI middleware (Node/Express) in the background.
# Writes its PID to middleware.pid and logs to middleware.log.
set -e
cd "$(dirname "$0")"

if [ -f middleware.pid ] && kill -0 "$(cat middleware.pid)" 2>/dev/null; then
    echo "Middleware already running (PID $(cat middleware.pid))."
    exit 0
fi

nohup node node_modules/server.js > middleware.log 2>&1 &
echo $! > middleware.pid
sleep 1
echo "Started middleware (PID $(cat middleware.pid)). Logs: $(pwd)/middleware.log"
echo "Follow logs with:  tail -f middleware.log"
