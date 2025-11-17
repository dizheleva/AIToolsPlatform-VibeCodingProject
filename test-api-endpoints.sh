#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

API_URL="http://localhost:8201/api"
BASE_URL="http://localhost:8201"

echo "🧪 Testing API Endpoints"
echo "========================="
echo ""

# Test 1: Check if API is accessible
echo "1. Testing API accessibility..."
if curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/up" | grep -q "200"; then
    echo -e "${GREEN}✅ API is accessible${NC}"
else
    echo -e "${RED}❌ API is not accessible${NC}"
    exit 1
fi
echo ""

# Test 2: Run Laravel Feature Tests
echo "2. Running Laravel Feature Tests..."
echo "   - TwoFactorTest"
docker compose exec php_fpm php artisan test --filter TwoFactorTest
echo ""

echo "   - AdminPanelTest"
docker compose exec php_fpm php artisan test --filter AdminPanelTest
echo ""

echo "   - ActivityLogTest"
docker compose exec php_fpm php artisan test --filter ActivityLogTest
echo ""

# Test 3: Check if routes are registered
echo "3. Checking if routes are registered..."
ROUTES=$(docker compose exec php_fpm php artisan route:list --path=api/2fa 2>/dev/null | grep -c "2fa" || echo "0")
if [ "$ROUTES" -gt "0" ]; then
    echo -e "${GREEN}✅ 2FA routes are registered${NC}"
else
    echo -e "${YELLOW}⚠️  2FA routes not found (might need to clear route cache)${NC}"
fi

ADMIN_ROUTES=$(docker compose exec php_fpm php artisan route:list --path=api/admin 2>/dev/null | grep -c "admin" || echo "0")
if [ "$ADMIN_ROUTES" -gt "0" ]; then
    echo -e "${GREEN}✅ Admin routes are registered${NC}"
else
    echo -e "${YELLOW}⚠️  Admin routes not found (might need to clear route cache)${NC}"
fi
echo ""

# Test 4: Check if services are loaded
echo "4. Checking if services are loaded..."
if docker compose exec php_fpm php artisan tinker --execute="echo class_exists('App\Services\TwoFactorService') ? 'YES' : 'NO';" 2>/dev/null | grep -q "YES"; then
    echo -e "${GREEN}✅ TwoFactorService is loaded${NC}"
else
    echo -e "${RED}❌ TwoFactorService not found${NC}"
fi

if docker compose exec php_fpm php artisan tinker --execute="echo class_exists('App\Services\ActivityLogService') ? 'YES' : 'NO';" 2>/dev/null | grep -q "YES"; then
    echo -e "${GREEN}✅ ActivityLogService is loaded${NC}"
else
    echo -e "${RED}❌ ActivityLogService not found${NC}"
fi
echo ""

# Test 5: Check if models exist
echo "5. Checking if models exist..."
if docker compose exec php_fpm php artisan tinker --execute="echo class_exists('App\Models\ActivityLog') ? 'YES' : 'NO';" 2>/dev/null | grep -q "YES"; then
    echo -e "${GREEN}✅ ActivityLog model exists${NC}"
else
    echo -e "${RED}❌ ActivityLog model not found${NC}"
fi
echo ""

# Test 6: Check database tables
echo "6. Checking database tables..."
if docker compose exec php_fpm php artisan tinker --execute="echo Schema::hasColumn('users', 'two_factor_type') ? 'YES' : 'NO';" 2>/dev/null | grep -q "YES"; then
    echo -e "${GREEN}✅ 2FA columns exist in users table${NC}"
else
    echo -e "${RED}❌ 2FA columns missing${NC}"
fi

if docker compose exec php_fpm php artisan tinker --execute="echo Schema::hasTable('activity_logs') ? 'YES' : 'NO';" 2>/dev/null | grep -q "YES"; then
    echo -e "${GREEN}✅ activity_logs table exists${NC}"
else
    echo -e "${RED}❌ activity_logs table missing${NC}"
fi
echo ""

echo "========================="
echo -e "${GREEN}✅ Testing complete!${NC}"
echo ""
echo "To run all tests:"
echo "  docker compose exec php_fpm php artisan test"
echo ""
echo "To test specific feature:"
echo "  docker compose exec php_fpm php artisan test --filter TwoFactorTest"
echo "  docker compose exec php_fpm php artisan test --filter AdminPanelTest"
echo "  docker compose exec php_fpm php artisan test --filter ActivityLogTest"

