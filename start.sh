#!/bin/bash
# This should be started through Procfile on Fly.io

echo "Starting worker process in the background..."
./worker.sh &

echo "Starting web process..."
vendor/bin/heroku-php-nginx -C nginx.inc.conf public/
