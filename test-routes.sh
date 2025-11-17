#!/bin/bash
cd /mnt/c/Users/Dilyana/Documents/GitHub/AIToolsPlatform-VibeCodingProject
docker compose exec -T php_fpm php artisan route:list
echo "---"
docker compose exec -T php_fpm php artisan route:clear
docker compose exec -T php_fpm php artisan config:clear
docker compose restart php_fpm backend
sleep 3
docker compose exec -T php_fpm php artisan route:list

