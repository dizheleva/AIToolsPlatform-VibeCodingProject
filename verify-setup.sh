#!/bin/bash
echo "🔍 Verifying setup..."
echo ""

echo "1. Checking migration status..."
docker compose exec php_fpm php artisan migrate:status | grep -E "(two_factor|activity_log|Pending|Ran)"

echo ""
echo "2. Checking if 2FA columns exist in users table..."
docker compose exec php_fpm php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
echo Schema::hasColumn('users', 'two_factor_type') ? '✅ 2FA columns exist' : '❌ 2FA columns missing';
echo PHP_EOL;
"

echo ""
echo "3. Checking if activity_logs table exists..."
docker compose exec php_fpm php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
echo Schema::hasTable('activity_logs') ? '✅ activity_logs table exists' : '❌ activity_logs table missing';
echo PHP_EOL;
"

echo ""
echo "4. Running migrations (if needed)..."
docker compose exec php_fpm php artisan migrate --force

echo ""
echo "✅ Verification complete!"

