#!/bin/bash

case "$1" in
    "connect")
        echo "🔗 Connecting to MySQL database..."
        docker compose exec mysql mysql -u root -p
        ;;
    "backup")
        echo "💾 Creating database backup..."
        BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
        docker compose exec mysql mysqldump -u root -p --all-databases > "$BACKUP_FILE"
        echo "✅ Backup saved as: $BACKUP_FILE"
        ;;
    "redis")
        echo "🔗 Connecting to Redis..."
        docker compose exec redis redis-cli
        ;;
    *)
        echo "Usage: $0 {connect|backup|redis}"
        echo "  connect  - Connect to MySQL database"
        echo "  backup   - Create database backup"
        echo "  redis    - Connect to Redis CLI"
        ;;
esac
