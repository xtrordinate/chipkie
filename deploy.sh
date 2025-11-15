#!/bin/bash

# Ensure all volumes for all instances exist
# 
docker volume create chipkie_db_data
docker volume create chipkie_app_storage
docker volume create staging_chipkie_db_data
docker volume create staging_chipkie_app_storage

# Pull the latest images, recreate the containers, and run post-deployment commands
# 
docker-compose pull
docker-compose down worker app webserver
docker-compose up -d
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache
docker-compose exec -T app php artisan migrate --force
docker-compose exec -u root -T app chown -R 9001:9001 /app/storage