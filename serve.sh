#!/bin/bash
# Development server startup script

echo "Setting up Boutique Store Management System..."

# 1. Composer install
if command -v composer &> /dev/null; then
    echo "Running composer install..."
    composer install
else
    echo "Composer not found. Skipping 'composer install'..."
fi

# 2. Setup .env
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Extract strictly DB credentials to prevent error overrides
DB_USER=$(grep -E '^DB_USER=' .env | cut -d '=' -f 2 | tr -d '"'"'"'" | tr -d '\r')
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d '=' -f 2 | tr -d '"'"'"'" | tr -d '\r')
DB_NAME=$(grep -E '^DB_NAME=' .env | cut -d '=' -f 2 | tr -d '"'"'"'" | tr -d '\r')

# 3. Seed data
if command -v mysql &> /dev/null; then
    echo "Seeding database ($DB_NAME)..."
    if [ -n "$DB_PASSWORD" ]; then
        mysql -u"$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
        mysql -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/store.sql
        if [ -f database/seed_data.sql ]; then
            mysql -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < database/seed_data.sql
        fi
    else
        mysql -u"$DB_USER" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
        mysql -u"$DB_USER" "$DB_NAME" < database/store.sql
        if [ -f database/seed_data.sql ]; then
            mysql -u"$DB_USER" "$DB_NAME" < database/seed_data.sql
        fi
    fi
    echo "Database seeded successfully."
else
    echo "MySQL not found in environment PATH. Skipping auto-seeding..."
fi

echo ""
echo "Starting Boutique Store Management System..."
echo "Server will be available externally at: http://0.0.0.0:8000"
echo "Press Ctrl+C to stop the server"
echo ""
php -S localhost:8000 -t public public/router.php

