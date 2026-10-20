#!/bin/sh
# Start of every NutriTrace container (web app, scheduler, Kubernetes pods). The configuration
# comes from the environment variables, so it is cached here and not when the image is built.
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Only one container migrates the database: the web app in docker-compose.yml
# (RUN_MIGRATIONS=true); in Kubernetes, a Job does it before the pods start.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
