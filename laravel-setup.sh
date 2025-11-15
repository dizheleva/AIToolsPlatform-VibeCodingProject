#!/bin/bash
echo "🔧 Full Laravel initialization..."

# Check if services are running
if ! docker compose ps | grep -q "Up"; then
    echo "❌ Services not running. Please run ./start.sh first"
    exit 1
fi

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
docker compose exec php_fpm composer install --no-interaction

# Generate application key
echo "🔑 Generating application key..."
docker compose exec php_fpm php artisan key:generate

# Run database migrations
echo "🗄️ Running database migrations..."
docker compose exec php_fpm php artisan migrate --force

# Seed database (optional)
read -p "🌱 Do you want to seed the database? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker compose exec php_fpm php artisan db:seed
fi

# Clear and cache configurations
echo "🧹 Optimizing Laravel..."
docker compose exec php_fpm php artisan config:clear
docker compose exec php_fpm php artisan route:clear
docker compose exec php_fpm php artisan view:clear
docker compose exec php_fpm php artisan config:cache
docker compose exec php_fpm php artisan route:cache

# Set proper permissions
echo "🔒 Setting proper permissions..."
docker compose exec php_fpm chown -R laravel:laravel /var/www/html/storage
docker compose exec php_fpm chown -R laravel:laravel /var/www/html/bootstrap/cache

echo "✅ Laravel initialization complete!"
