#!/bin/bash
# This runs build-in worker to periodically clean up older games

# In case process crashes we it will restart indefinitely
while true; do
    php bin/console worker

    echo "Worker process crashed, restarting in 60 seconds..."
    sleep 60
done
