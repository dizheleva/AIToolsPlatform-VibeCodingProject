#!/bin/bash
echo "Checking migration status..."
docker compose exec php_fpm php artisan migrate:status

echo ""
echo "Checking if 2FA columns exist in users table..."
docker compose exec php_fpm php artisan tinker --execute="echo Schema::hasColumn('users', 'two_factor_type') ? '✅ 2FA columns exist' : '❌ 2FA columns missing';"

echo ""
echo "Checking if activity_logs table exists..."
docker compose exec php_fpm php artisan tinker --execute="echo Schema::hasTable('activity_logs') ? '✅ activity_logs table exists' : '❌ activity_logs table missing';"

