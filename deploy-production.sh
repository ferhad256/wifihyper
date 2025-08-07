#!/bin/bash

# Production Deployment Script for WIFIHYPER

echo '🚀 Deploying WIFIHYPER to production...'

# Set file permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Run migrations
php artisan migrate --force

# Seed data if needed
php artisan db:seed --class=SubscriptionPlanSeeder --force

echo '✅ Deployment complete!'
