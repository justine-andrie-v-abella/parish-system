#!/bin/sh
set -e

# Render assigns a dynamic $PORT at runtime and requires the service to
# listen on it — it isn't known at image-build time, so this has to happen
# on container start, not in the Dockerfile. Falls back to 80 for any other
# Docker host (e.g. running this image locally) that doesn't set $PORT.
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

exec "$@"
