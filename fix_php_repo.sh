#!/bin/bash

# =============================================================================
# PHP Repository Fix Script for WIFIHYPER
# =============================================================================
# This script adds the PHP repository and installs PHP packages
# Run this if you get "Unable to locate package php8.2" errors
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    print_error "This script must be run as root (use sudo)"
    exit 1
fi

print_status "Adding PHP repository and installing PHP packages..."

# Update package lists
print_status "Updating package lists..."
apt update -y

# Install software-properties-common if not installed
if ! command -v add-apt-repository &> /dev/null; then
    print_status "Installing software-properties-common..."
    apt install -y software-properties-common
fi

# Add PHP repository
print_status "Adding ondrej/php repository..."
add-apt-repository -y ppa:ondrej/php

# Update package lists again
print_status "Updating package lists with new repository..."
apt update -y

# Check which PHP version is available and install it
if apt-cache show php8.2 &>/dev/null; then
    PHP_VERSION="8.2"
    print_success "PHP 8.2 is available"
elif apt-cache show php8.1 &>/dev/null; then
    PHP_VERSION="8.1"
    print_warning "PHP 8.2 not available, using PHP 8.1 instead"
else
    print_error "Neither PHP 8.2 nor PHP 8.1 is available"
    exit 1
fi

# Install PHP and extensions
print_status "Installing PHP $PHP_VERSION and extensions..."
apt install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-ldap \
    libapache2-mod-php${PHP_VERSION}

if [[ $? -eq 0 ]]; then
    print_success "PHP $PHP_VERSION and extensions installed successfully!"
    
    # Show installed PHP version
    php --version
    
    print_success "You can now continue with the deployment script"
    echo ""
    echo "Run: sudo ./deploy_production.sh --db-pass=YOUR_SECURE_PASSWORD"
else
    print_error "Failed to install PHP packages"
    exit 1
fi
