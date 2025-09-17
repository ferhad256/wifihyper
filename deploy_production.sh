#!/bin/bash

# =============================================================================
# WIFIHYPER Production Deployment Script
# =============================================================================
# This script automates the complete production deployment process
# 
# Usage: ./deploy_production.sh [options]
# Options:
#   --domain=example.com     Set the domain name
#   --email=admin@example.com Set admin email
#   --db-name=wifihyper      Set database name
#   --db-user=wifihyper      Set database user
#   --db-pass=password       Set database password
#   --skip-ssl               Skip SSL certificate setup
#   --skip-backup            Skip database backup
#   --force                  Force deployment without confirmation
# =============================================================================

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default configuration
DOMAIN="wifihyper.com"
ADMIN_EMAIL="walusimbifahd@gmail.com"
DB_NAME="wifihyper"
DB_USER="root"
DB_PASS=""
SKIP_SSL=false
SKIP_APACHE=false
SKIP_BACKUP=false
FORCE=false
DEPLOYMENT_DIR="/var/www/wifihyper"
BACKUP_DIR="/var/backups/wifihyper"

# Function to print colored output
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

# Function to print banner
print_banner() {
    echo -e "${BLUE}"
    echo "=================================================================="
    echo "                    WIFIHYPER PRODUCTION DEPLOYMENT"
    echo "=================================================================="
    echo -e "${NC}"
}

# Function to check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

# Function to parse command line arguments
parse_arguments() {
    for arg in "$@"; do
        case $arg in
            --domain=*)
                DOMAIN="${arg#*=}"
                shift
                ;;
            --email=*)
                ADMIN_EMAIL="${arg#*=}"
                shift
                ;;
            --db-name=*)
                DB_NAME="${arg#*=}"
                shift
                ;;
            --db-user=*)
                DB_USER="${arg#*=}"
                shift
                ;;
            --db-pass=*)
                DB_PASS="${arg#*=}"
                shift
                ;;
            --skip-ssl)
                SKIP_SSL=true
                shift
                ;;
            --skip-apache)
                SKIP_APACHE=true
                shift
                ;;
            --skip-backup)
                SKIP_BACKUP=true
                shift
                ;;
            --force)
                FORCE=true
                shift
                ;;
            --help)
                echo "Usage: $0 [options]"
                echo "Options:"
                echo "  --domain=example.com     Set the domain name"
                echo "  --email=admin@example.com Set admin email"
                echo "  --db-name=wifihyper      Set database name"
                echo "  --db-user=wifihyper      Set database user"
                echo "  --db-pass=password       Set database password"
                echo "  --skip-ssl               Skip SSL certificate setup"
                echo "  --skip-apache            Skip Apache configuration"
                echo "  --skip-backup            Skip database backup"
                echo "  --force                  Force deployment without confirmation"
                exit 0
                ;;
            *)
                print_error "Unknown option: $arg"
                exit 1
                ;;
        esac
    done
}

# Function to validate configuration
validate_config() {
    if [[ -z "$DOMAIN" ]]; then
        print_error "Domain name is required. Use --domain=example.com"
        exit 1
    fi
    
    if [[ -z "$ADMIN_EMAIL" ]]; then
        print_error "Admin email is required. Use --email=admin@example.com"
        exit 1
    fi
    
    if [[ -z "$DB_PASS" ]]; then
        print_error "Database password is required. Use --db-pass=password"
        exit 1
    fi
    
    # Validate email format
    if ! echo "$ADMIN_EMAIL" | grep -qE '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'; then
        print_error "Invalid email format: $ADMIN_EMAIL"
        exit 1
    fi
    
    # Validate domain format
    if ! echo "$DOMAIN" | grep -qE '^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$'; then
        print_error "Invalid domain format: $DOMAIN"
        exit 1
    fi
    
    # Check if required files exist
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ ! -f "$SCRIPT_DIR/composer.json" ]]; then
        print_error "composer.json not found. Please run this script from the project root directory."
        exit 1
    fi
    
    if [[ ! -f "$SCRIPT_DIR/.env.example" ]]; then
        print_error ".env.example not found. Please run this script from the project root directory."
        exit 1
    fi
}

# Function to confirm deployment
confirm_deployment() {
    if [[ "$FORCE" == true ]]; then
        return 0
    fi
    
    echo -e "${YELLOW}"
    echo "=================================================================="
    echo "                    DEPLOYMENT CONFIRMATION"
    echo "=================================================================="
    echo "Domain: $DOMAIN"
    echo "Admin Email: $ADMIN_EMAIL"
    echo "Database: $DB_NAME"
    echo "Database User: $DB_USER"
    echo "Deployment Directory: $DEPLOYMENT_DIR"
    echo "SSL Setup: $([[ "$SKIP_SSL" == true ]] && echo "SKIPPED" || echo "ENABLED")"
    echo "Backup: $([[ "$SKIP_BACKUP" == true ]] && echo "SKIPPED" || echo "ENABLED")"
    echo ""
    echo "This will deploy WIFIHYPER to production. Are you sure? (y/N)"
    echo -e "${NC}"
    
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        print_warning "Deployment cancelled by user"
        exit 0
    fi
}

# Function to update system packages
update_system() {
    print_status "Updating system packages..."
    
    apt update -y
    apt upgrade -y
    
    print_success "System packages updated"
}

# Function to install required packages
install_packages() {
    print_status "Installing required packages..."
    
    # Essential packages
    apt install -y curl wget git unzip rsync software-properties-common apt-transport-https ca-certificates gnupg lsb-release
    
    # Add PHP repository (ondrej/php PPA for latest PHP versions)
    print_status "Adding PHP repository..."
    if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
        add-apt-repository -y ppa:ondrej/php
        apt update -y
        print_success "PHP repository added"
    else
        print_warning "PHP repository already exists"
    fi
    
    # Apache (skip if already installed)
    if ! command -v apache2 &> /dev/null; then
        apt install -y apache2
    else
        print_warning "Apache is already installed, skipping installation"
    fi
    
    # PHP 8.2 and extensions (with fallback to 8.1)
    print_status "Installing PHP and extensions..."
    
    # Try PHP 8.2 first
    if apt-cache show php8.2 &>/dev/null; then
        PHP_VERSION="8.2"
        print_status "Installing PHP 8.2 and extensions..."
    elif apt-cache show php8.1 &>/dev/null; then
        PHP_VERSION="8.1"
        print_warning "PHP 8.2 not available, using PHP 8.1 instead"
        print_status "Installing PHP 8.1 and extensions..."
    else
        print_error "Neither PHP 8.2 nor PHP 8.1 is available"
        exit 1
    fi
    
    apt install -y php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-mysql php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-zip php${PHP_VERSION}-gd php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl php${PHP_VERSION}-redis php${PHP_VERSION}-ldap libapache2-mod-php${PHP_VERSION}
    
    # MySQL
    apt install -y mysql-server
    
    # Redis
    apt install -y redis-server
    
    # Certbot for SSL (skip if already installed)
    if [[ "$SKIP_SSL" == false ]]; then
        if ! command -v certbot &> /dev/null; then
            apt install -y certbot python3-certbot-apache
        else
            print_warning "Certbot is already installed, skipping installation"
        fi
    fi
    
    # Supervisor for process management
    apt install -y supervisor
    
    # Composer
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    
    print_success "Required packages installed"
}

# Function to configure MySQL
configure_mysql() {
    print_status "Configuring MySQL..."
    
    # Secure MySQL installation
    mysql_secure_installation <<EOF

y
0
$DB_PASS
$DB_PASS
y
y
y
y
EOF
    
    # Create database and user
    if [[ "$DB_USER" == "root" ]]; then
        # If using root user, just create the database
        mysql -u root -p$DB_PASS <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
FLUSH PRIVILEGES;
EOF
    else
        # Create database and dedicated user
        mysql -u root -p$DB_PASS <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    fi
    
    print_success "MySQL configured"
}

# Function to configure PHP
configure_php() {
    print_status "Configuring PHP..."
    
    # Detect installed PHP version
    if [[ -f "/etc/php/8.2/apache2/php.ini" ]]; then
        PHP_VERSION="8.2"
    elif [[ -f "/etc/php/8.1/apache2/php.ini" ]]; then
        PHP_VERSION="8.1"
    else
        print_error "PHP configuration file not found"
        exit 1
    fi
    
    print_status "Configuring PHP $PHP_VERSION..."
    
    # PHP configuration for Apache
    sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 100M/' /etc/php/${PHP_VERSION}/apache2/php.ini
    sed -i 's/post_max_size = 8M/post_max_size = 100M/' /etc/php/${PHP_VERSION}/apache2/php.ini
    sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/${PHP_VERSION}/apache2/php.ini
    sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/${PHP_VERSION}/apache2/php.ini
    
    # Enable Apache modules
    a2enmod rewrite
    a2enmod ssl
    a2enmod headers
    
    print_success "PHP $PHP_VERSION configured"
}

# Function to configure Apache
configure_apache() {
    if [[ "$SKIP_APACHE" == true ]]; then
        print_warning "Apache configuration skipped"
        return 0
    fi
    
    print_status "Configuring Apache..."
    
    # Check if Apache virtual host already exists
    if [[ -f "/etc/apache2/sites-available/wifihyper.conf" ]]; then
        print_warning "Apache virtual host already exists, skipping configuration"
        return 0
    fi
    
    # Create Apache virtual host configuration
    cat > /etc/apache2/sites-available/wifihyper.conf <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot $DEPLOYMENT_DIR/public
    
    <Directory $DEPLOYMENT_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer-when-downgrade"
    Header always set Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'"
    
    # Logging
    ErrorLog \${APACHE_LOG_DIR}/wifihyper_error.log
    CustomLog \${APACHE_LOG_DIR}/wifihyper_access.log combined
    
    # Cache static files
    <LocationMatch "\.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </LocationMatch>
</VirtualHost>
EOF
    
    # Enable site
    a2ensite wifihyper.conf
    a2dissite 000-default.conf
    
    # Test Apache configuration
    apache2ctl configtest
    
    # Restart Apache
    systemctl restart apache2
    
    print_success "Apache configured"
}

# Function to setup SSL certificate
setup_ssl() {
    if [[ "$SKIP_SSL" == true ]]; then
        print_warning "SSL setup skipped"
        return 0
    fi
    
    # Check if SSL certificate already exists
    if [[ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]]; then
        print_warning "SSL certificate already exists for $DOMAIN, skipping SSL setup"
        return 0
    fi
    
    print_status "Setting up SSL certificate..."
    
    # Stop Apache temporarily
    systemctl stop apache2
    
    # Obtain SSL certificate
    certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN --email $ADMIN_EMAIL --agree-tos --non-interactive
    
    # Update Apache configuration for SSL
    cat > /etc/apache2/sites-available/wifihyper.conf <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    Redirect permanent / https://$DOMAIN/
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMAIN
    ServerAlias www.$DOMAIN
    DocumentRoot $DEPLOYMENT_DIR/public
    
    # SSL configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$DOMAIN/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN/privkey.pem
    
    # SSL Security
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder off
    SSLSessionTickets off
    
    <Directory $DEPLOYMENT_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "no-referrer-when-downgrade"
    Header always set Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    # Logging
    ErrorLog \${APACHE_LOG_DIR}/wifihyper_ssl_error.log
    CustomLog \${APACHE_LOG_DIR}/wifihyper_ssl_access.log combined
    
    # Cache static files
    <LocationMatch "\.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public, immutable"
    </LocationMatch>
</VirtualHost>
EOF
    
    # Start Apache
    systemctl start apache2
    
    # Test Apache configuration
    apache2ctl configtest
    
    # Restart Apache
    systemctl restart apache2
    
    # Setup auto-renewal
    echo "0 12 * * * /usr/bin/certbot renew --quiet" | crontab -
    
    print_success "SSL certificate configured"
}

# Function to create deployment directory
create_deployment_dir() {
    print_status "Creating deployment directory..."
    
    mkdir -p $DEPLOYMENT_DIR
    mkdir -p $BACKUP_DIR
    
    # Set proper permissions
    chown -R www-data:www-data $DEPLOYMENT_DIR
    chmod -R 755 $DEPLOYMENT_DIR
    
    print_success "Deployment directory created"
}

# Function to backup existing deployment
backup_existing() {
    if [[ "$SKIP_BACKUP" == true ]]; then
        print_warning "Backup skipped"
        return 0
    fi
    
    if [[ -d "$DEPLOYMENT_DIR" ]] && [[ "$(ls -A $DEPLOYMENT_DIR)" ]]; then
        print_status "Backing up existing deployment..."
        
        BACKUP_FILE="$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).tar.gz"
        tar -czf "$BACKUP_FILE" -C $DEPLOYMENT_DIR .
        
        print_success "Backup created: $BACKUP_FILE"
    fi
}

# Function to deploy application
deploy_application() {
    print_status "Deploying WIFIHYPER application..."
    
    # Get the current directory (assuming script is run from project root)
    CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    
    # Copy application files to deployment directory
    if [[ "$CURRENT_DIR" != "$DEPLOYMENT_DIR" ]]; then
        print_status "Copying application files from $CURRENT_DIR to $DEPLOYMENT_DIR"
        
        # Create deployment directory if it doesn't exist
        mkdir -p $DEPLOYMENT_DIR
        
        # Copy all files except .git, node_modules, and other unnecessary files
        rsync -av --exclude='.git' --exclude='node_modules' --exclude='vendor' --exclude='.env' --exclude='storage/logs/*' --exclude='storage/framework/cache/*' --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' "$CURRENT_DIR/" "$DEPLOYMENT_DIR/"
        
        cd $DEPLOYMENT_DIR
    else
        print_warning "Already in deployment directory"
        cd $DEPLOYMENT_DIR
    fi
    
    # Install Composer dependencies
    print_status "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Create necessary storage directories
    mkdir -p storage/logs
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p storage/app/public
    mkdir -p bootstrap/cache
    
    # Set proper permissions
    chown -R www-data:www-data $DEPLOYMENT_DIR
    chmod -R 755 $DEPLOYMENT_DIR
    chmod -R 775 $DEPLOYMENT_DIR/storage
    chmod -R 775 $DEPLOYMENT_DIR/bootstrap/cache
    
    print_success "Application deployed"
}

# Function to configure environment
configure_environment() {
    print_status "Configuring environment..."
    
    # Copy environment file
    cp $DEPLOYMENT_DIR/.env.example $DEPLOYMENT_DIR/.env
    
    # Generate application key
    cd $DEPLOYMENT_DIR
    php artisan key:generate
    
    # Update environment variables
    sed -i "s/APP_NAME=.*/APP_NAME=WIFIHYPER/" .env
    sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
    sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
    sed -i "s/APP_URL=.*/APP_URL=https:\/\/$DOMAIN/" .env
    
    # Database configuration
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
    
    # Mail configuration
    sed -i "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=noreply@$DOMAIN/" .env
    sed -i "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=WIFIHYPER/" .env
    
    # Cache and session
    sed -i "s/CACHE_DRIVER=.*/CACHE_DRIVER=redis/" .env
    sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=redis/" .env
    sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/" .env
    
    # Admin configuration (add admin environment variables)
    echo "" >> .env
    echo "# Admin Configuration" >> .env
    echo "ADMIN_NAME=System Administrator" >> .env
    echo "ADMIN_EMAIL=$ADMIN_EMAIL" >> .env
    echo "ADMIN_PASSWORD=$(openssl rand -base64 32)" >> .env
    echo "ADMIN_NAME_2=Secondary Admin" >> .env
    echo "ADMIN_EMAIL_2=admin2@$DOMAIN" >> .env
    echo "ADMIN_PASSWORD_2=$(openssl rand -base64 32)" >> .env
    
    print_success "Environment configured"
}

# Function to setup database
setup_database() {
    print_status "Setting up database..."
    
    cd $DEPLOYMENT_DIR
    
    # Check if database connection works
    if ! php artisan migrate:status &>/dev/null; then
        print_error "Database connection failed. Please check your database configuration."
        exit 1
    fi
    
    # Run migrations with error checking
    print_status "Running database migrations..."
    if php artisan migrate --force; then
        print_success "Migrations completed successfully"
    else
        print_error "Migration failed. Please check the logs."
        exit 1
    fi
    
    # Seed database with admin accounts (critical for production access)
    print_status "Creating admin accounts..."
    if php artisan db:seed --class=AdminSeeder --force; then
        print_success "Admin accounts created successfully"
    else
        print_error "Admin seeder failed. This is critical - you won't be able to access the admin panel!"
        print_error "Please check the logs and ensure ADMIN_EMAIL and ADMIN_PASSWORD are set correctly."
        exit 1
    fi
    
    # Run all other seeders (non-critical, continue on failure)
    print_status "Running additional database seeders..."
    if php artisan db:seed --force; then
        print_success "All seeders completed successfully"
    else
        print_warning "Some seeders failed, but continuing deployment..."
        print_warning "You may need to run 'php artisan db:seed' manually later"
    fi
    
    # Verify admin accounts were created
    print_status "Verifying admin account creation..."
    ADMIN_COUNT=$(php artisan tinker --execute="echo App\Models\Admin::count();" 2>/dev/null | tail -1)
    if [[ "$ADMIN_COUNT" -gt 0 ]]; then
        print_success "✓ Admin accounts verified ($ADMIN_COUNT admin(s) found)"
    else
        print_error "✗ No admin accounts found in database!"
        print_error "This is critical - deployment cannot continue without admin access"
        exit 1
    fi
    
    print_success "Database setup completed successfully"
}

# Function to configure cron jobs
configure_cron() {
    print_status "Configuring cron jobs..."
    
    # Add Laravel scheduler to crontab
    (crontab -l 2>/dev/null; echo "* * * * * cd $DEPLOYMENT_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -
    
    # Add log rotation
    (crontab -l 2>/dev/null; echo "0 2 * * * find $DEPLOYMENT_DIR/storage/logs -name '*.log' -mtime +7 -delete") | crontab -
    
    print_success "Cron jobs configured"
}

# Function to configure supervisor
configure_supervisor() {
    print_status "Configuring Supervisor..."
    
    # Create supervisor configuration for queue workers
    cat > /etc/supervisor/conf.d/wifihyper.conf <<EOF
[program:wifihyper-queue]
process_name=%(program_name)s_%(process_num)02d
command=php $DEPLOYMENT_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$DEPLOYMENT_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF
    
    # Reload supervisor
    supervisorctl reread
    supervisorctl update
    supervisorctl start wifihyper-queue:*
    
    print_success "Supervisor configured"
}

# Function to optimize application
optimize_application() {
    print_status "Optimizing application..."
    
    cd $DEPLOYMENT_DIR
    
    # Clear and cache configuration
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Optimize autoloader
    composer install --no-dev --optimize-autoloader
    
    print_success "Application optimized"
}

# Function to setup firewall
setup_firewall() {
    print_status "Setting up firewall..."
    
    # Allow SSH
    ufw allow ssh
    
    # Allow HTTP and HTTPS
    ufw allow 80
    ufw allow 443
    
    # Enable firewall
    ufw --force enable
    
    print_success "Firewall configured"
}

# Function to setup monitoring
setup_monitoring() {
    print_status "Setting up monitoring..."
    
    # Create log monitoring script
    cat > /usr/local/bin/monitor-wifihyper <<EOF
#!/bin/bash
LOG_FILE="$DEPLOYMENT_DIR/storage/logs/laravel.log"
ERROR_COUNT=\$(grep -c "ERROR" \$LOG_FILE 2>/dev/null || echo "0")
if [ \$ERROR_COUNT -gt 10 ]; then
    echo "High error count detected: \$ERROR_COUNT" | mail -s "WIFIHYPER Alert" $ADMIN_EMAIL
fi
EOF
    
    chmod +x /usr/local/bin/monitor-wifihyper
    
    # Add monitoring to crontab
    (crontab -l 2>/dev/null; echo "*/15 * * * * /usr/local/bin/monitor-wifihyper") | crontab -
    
    print_success "Monitoring configured"
}

# Function to finalize deployment
finalize_deployment() {
    print_status "Finalizing deployment..."
    
    # Set proper ownership
    chown -R www-data:www-data $DEPLOYMENT_DIR
    
    # Restart services
    systemctl restart apache2
    systemctl restart redis-server
    systemctl restart mysql
    
    # Enable services on boot
    systemctl enable apache2
    systemctl enable redis-server
    systemctl enable mysql
    systemctl enable supervisor
    
    print_success "Deployment finalized"
}

# Function to run health checks
run_health_checks() {
    print_status "Running health checks..."
    
    # Check if services are running
    if systemctl is-active --quiet apache2; then
        print_success "Apache is running"
    else
        print_error "Apache is not running"
        exit 1
    fi
    
    if systemctl is-active --quiet mysql; then
        print_success "MySQL is running"
    else
        print_error "MySQL is not running"
        exit 1
    fi
    
    # Test application
    if curl -s -o /dev/null -w "%{http_code}" "http://$DOMAIN" | grep -q "200\|301\|302"; then
        print_success "Application is responding"
    else
        print_warning "Application may not be responding correctly"
    fi
    
    print_success "Health checks completed"
}

# Function to display deployment summary
display_summary() {
    echo -e "${GREEN}"
    echo "=================================================================="
    echo "                    DEPLOYMENT COMPLETED SUCCESSFULLY!"
    echo "=================================================================="
    echo "Domain: https://$DOMAIN"
    echo "Admin Email: $ADMIN_EMAIL"
    echo "Database: $DB_NAME"
    echo "Deployment Directory: $DEPLOYMENT_DIR"
    echo "Backup Directory: $BACKUP_DIR"
    echo ""
    echo "Admin Credentials:"
    echo "Primary Admin: $ADMIN_EMAIL"
    echo "Secondary Admin: admin2@$DOMAIN"
    echo ""
    echo "🔐 Admin Passwords (SAVE THESE SECURELY):"
    echo "Primary Admin Password: $(grep ADMIN_PASSWORD= $DEPLOYMENT_DIR/.env | head -1 | cut -d'=' -f2)"
    echo "Secondary Admin Password: $(grep ADMIN_PASSWORD_2= $DEPLOYMENT_DIR/.env | cut -d'=' -f2)"
    echo ""
    echo "⚠️  IMPORTANT SECURITY NOTES:"
    echo "- These are automatically generated secure passwords"
    echo "- Store them in a secure password manager"
    echo "- Change them regularly for security"
    echo "- Never share these credentials via unsecured channels"
    echo ""
    echo "Next Steps:"
    echo "1. Access your application at https://$DOMAIN"
    echo "2. Login with admin credentials shown above"
    echo "3. Complete the initial setup in the admin panel"
    echo "4. Configure your payment gateways (JPesa, UG SMS)"
    echo "5. Set up your Resend email configuration"
    echo "6. Test the complete workflow"
    echo ""
    echo "Monitoring:"
    echo "- Logs: $DEPLOYMENT_DIR/storage/logs/"
    echo "- Cron jobs: crontab -l"
    echo "- Supervisor: supervisorctl status"
    echo "- Services: systemctl status apache2 mysql redis-server"
    echo ""
    echo "Support: Check the logs if you encounter any issues"
    echo -e "${NC}"
}

# Main deployment function
main() {
    print_banner
    
    # Check if running as root
    check_root
    
    # Parse arguments
    parse_arguments "$@"
    
    # Validate configuration
    validate_config
    
    # Confirm deployment
    confirm_deployment
    
    print_status "Starting WIFIHYPER production deployment..."
    
    # Execute deployment steps
    update_system
    install_packages
    configure_mysql
    configure_php
    create_deployment_dir
    backup_existing
    deploy_application
    configure_environment
    setup_database
    configure_apache
    setup_ssl
    configure_cron
    configure_supervisor
    optimize_application
    setup_firewall
    setup_monitoring
    finalize_deployment
    run_health_checks
    
    # Display summary
    display_summary
    
    print_success "WIFIHYPER production deployment completed successfully!"
}

# Execute main function with all arguments
main "$@" 