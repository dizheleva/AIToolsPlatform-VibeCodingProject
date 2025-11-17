@echo off
echo Checking migration status...
docker compose exec php_fpm php artisan migrate:status

echo.
echo Checking if tables exist...
docker compose exec php_fpm php artisan tinker --execute="echo Schema::hasColumn('users', 'two_factor_type') ? '2FA columns exist' : '2FA columns missing';"
docker compose exec php_fpm php artisan tinker --execute="echo Schema::hasTable('activity_logs') ? 'activity_logs table exists' : 'activity_logs table missing';"

pause

