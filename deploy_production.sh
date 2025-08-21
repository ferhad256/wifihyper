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
DOMAIN=""
ADMIN_EMAIL=""
DB_NAME="wifihyper"
DB_USER="wifihyper"
DB_PASS=""
SKIP_SSL=false
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
    apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release
    
    # Nginx
    apt install -y nginx
    
    # PHP 8.2 and extensions
    apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-redis php8.2-ldap
    
    # MySQL
    apt install -y mysql-server
    
    # Redis
    apt install -y redis-server
    
    # Certbot for SSL
    if [[ "$SKIP_SSL" == false ]]; then
        apt install -y certbot python3-certbot-nginx
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
    mysql -u root -p$DB_PASS <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    print_success "MySQL configured"
}

# Function to configure PHP
configure_php() {
    print_status "Configuring PHP..."
    
    # PHP-FPM configuration
    sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 100M/' /etc/php/8.2/fpm/php.ini
    sed -i 's/post_max_size = 8M/post_max_size = 100M/' /etc/php/8.2/fpm/php.ini
    sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
    sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
    
    # Restart PHP-FPM
    systemctl restart php8.2-fpm
    
    print_success "PHP configured"
}

# Function to configure Nginx
configure_nginx() {
    print_status "Configuring Nginx..."
    
    # Create Nginx configuration
    cat > /etc/nginx/sites-available/wifihyper <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $DEPLOYMENT_DIR/public;
    index index.php index.html index.htm;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/javascript;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF
    
    # Enable site
    ln -sf /etc/nginx/sites-available/wifihyper /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    
    # Test Nginx configuration
    nginx -t
    
    # Reload Nginx
    systemctl reload nginx
    
    print_success "Nginx configured"
}

# Function to setup SSL certificate
setup_ssl() {
    if [[ "$SKIP_SSL" == true ]]; then
        print_warning "SSL setup skipped"
        return 0
    fi
    
    print_status "Setting up SSL certificate..."
    
    # Stop Nginx temporarily
    systemctl stop nginx
    
    # Obtain SSL certificate
    certbot certonly --standalone -d $DOMAIN -d www.$DOMAIN --email $ADMIN_EMAIL --agree-tos --non-interactive
    
    # Update Nginx configuration for SSL
    cat > /etc/nginx/sites-available/wifihyper <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name $DOMAIN www.$DOMAIN;
    root $DEPLOYMENT_DIR/public;
    index index.php index.html index.htm;
    
    # SSL configuration
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-SHA256:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/javascript;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF
    
    # Start Nginx
    systemctl start nginx
    
    # Test Nginx configuration
    nginx -t
    
    # Reload Nginx
    systemctl reload nginx
    
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
    
    # Clone or pull repository
    if [[ -d "$DEPLOYMENT_DIR/.git" ]]; then
        cd $DEPLOYMENT_DIR
        git pull origin main
    else
        cd /tmp
        git clone https://github.com/yourusername/wifihyper.git $DEPLOYMENT_DIR
        cd $DEPLOYMENT_DIR
    fi
    
    # Install Composer dependencies
    composer install --no-dev --optimize-autoloader
    
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
    
    print_success "Environment configured"
}

# Function to setup database
setup_database() {
    print_status "Setting up database..."
    
    cd $DEPLOYMENT_DIR
    
    # Run migrations
    php artisan migrate --force
    
    # Seed database if needed
    php artisan db:seed --force
    
    print_success "Database setup completed"
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
    systemctl restart php8.2-fpm
    systemctl restart nginx
    systemctl restart redis-server
    systemctl restart mysql
    
    # Enable services on boot
    systemctl enable php8.2-fpm
    systemctl enable nginx
    systemctl enable redis-server
    systemctl enable mysql
    systemctl enable supervisor
    
    print_success "Deployment finalized"
}

# Function to run health checks
run_health_checks() {
    print_status "Running health checks..."
    
    # Check if services are running
    if systemctl is-active --quiet nginx; then
        print_success "Nginx is running"
    else
        print_error "Nginx is not running"
        exit 1
    fi
    
    if systemctl is-active --quiet php8.2-fpm; then
        print_success "PHP-FPM is running"
    else
        print_error "PHP-FPM is not running"
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
    echo "Next Steps:"
    echo "1. Access your application at https://$DOMAIN"
    echo "2. Complete the initial setup in the admin panel"
    echo "3. Configure your payment gateways (Yo! Payments, UG SMS)"
    echo "4. Set up your Resend email configuration"
    echo "5. Test the complete workflow"
    echo ""
    echo "Monitoring:"
    echo "- Logs: $DEPLOYMENT_DIR/storage/logs/"
    echo "- Cron jobs: crontab -l"
    echo "- Supervisor: supervisorctl status"
    echo "- Services: systemctl status nginx php8.2-fpm mysql redis-server"
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
    configure_nginx
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