#!/bin/sh

# Ensure the SQLite database file exists
if [ ! -f "database/database.sqlite" ]; then
    echo "Creating database/database.sqlite..."
    touch database/database.sqlite
fi

# If we have a database connection, try to run migrations
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force
    
    # Optional seeding
    if [ "$RUN_SEEDER" = "true" ]; then
        echo "Running seeders..."
        php artisan db:seed --force
    fi
fi
