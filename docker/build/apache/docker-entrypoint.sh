#!/bin/bash
set -e

# Start cron daemon in background
echo "Starting cron daemon..."
cron

# Start Apache in foreground
echo "Starting Apache..."
exec apache2-foreground
