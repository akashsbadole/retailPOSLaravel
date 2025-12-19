# Setup & Configuration Guide

## Overview

This guide provides detailed instructions for setting up and configuring all features of the SuperMart POS system, including Phase 4 reports and Phase 5 advanced features.

## System Requirements

### Minimum Requirements
- **PHP**: 8.2 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Memory**: 2GB RAM minimum
- **Storage**: 10GB available space
- **SSL**: Valid SSL certificate (required for mobile POS)

### Recommended Requirements
- **PHP**: 8.3
- **Web Server**: Nginx with PHP-FPM
- **Database**: MySQL 8.0+
- **Memory**: 4GB RAM or more
- **Storage**: 50GB SSD
- **Redis**: For caching and real-time features
- **Pusher**: For WebSocket support

## Installation Steps

### 1. Download the Application

#### Option A: Clone from Git
```bash
git clone https://github.com/mailmug/laravel-easy-pos.git
cd laravel-easy-pos
```

#### Option B: Download ZIP
1. Download from [GitHub Releases](https://github.com/mailmug/laravel-easy-pos/releases)
2. Extract to your web directory
3. Rename folder to `supermart`

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node.js dependencies
npm install
npm run build
```

### 3. Configure Environment

1. Copy environment file:
```bash
cp .env.example .env
```

2. Generate application key:
```bash
php artisan key:generate
```

3. Edit `.env` file with your settings:
```env
APP_NAME="SuperMart POS"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=supermart
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Cache Configuration
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-mail-server
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Multi-store Support
MULTI_STORE_ENABLED=true
DEFAULT_STORE_ID=1

# Barcode Scanner
BARCODE_SCANNER_ENABLED=true
BARCODE_PREFIX=BAR

# Mobile POS
MOBILE_POS_ENABLED=true
API_RATE_LIMIT=60

# Real-time Features
PUSHER_APP_KEY=your-pusher-key
PUSHER_APP_SECRET=your-pusher-secret
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_CLUSTER=mt1
WS_HOST=ws-mt1.pusher.com
WS_PORT=443
FORCE_TLS=true

# Supplier Management
SUPPLIER_MODULE_ENABLED=true
PURCHASE_APPROVAL_REQUIRED=true

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com,mobile.your-domain.com
```

### 4. Configure Web Server

#### Apache Configuration
Create `.htaccess` file in public directory:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /path/to/supermart/public;
    index index.php;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 5. Set Up Database

1. Create database:
```sql
CREATE DATABASE supermart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'supermart_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON supermart.* TO 'supermart_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Run migrations:
```bash
php artisan migrate --force
```

3. Seed initial data:
```bash
php artisan db:seed --class=DatabaseSeeder --force
```

### 6. Configure Caching

#### Redis Setup
1. Install Redis:
```bash
# Ubuntu/Debian
sudo apt-get install redis-server

# CentOS/RHEL
sudo yum install redis
```

2. Configure Redis:
```bash
# Edit /etc/redis/redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

3. Start Redis service:
```bash
sudo systemctl start redis
sudo systemctl enable redis
```

### 7. Set Up Queue Worker

#### Systemd Service
Create `/etc/systemd/system/supermart-queue.service`:
```ini
[Unit]
Description=SuperMart Queue Worker

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/supermart/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Enable and start service:
```bash
sudo systemctl enable supermart-queue
sudo systemctl start supermart-queue
```

### 8. Schedule Tasks

Add to crontab:
```bash
* * * * * cd /path/to/supermart && php artisan schedule:run >> /dev/null 2>&1
```

## Feature Configuration

### Multi-Store Setup

1. Create additional stores:
   - Navigate to **Stores** in admin panel
   - Click **Add Store**
   - Fill in store details
   - Save store

2. Configure store-specific settings:
   - Store currency
   - Tax rates
   - Business hours
   - Contact information

3. Assign users to stores:
   - Go to **Users** > **Edit User**
   - Select stores for user access
   - Set default store

### Barcode Scanner Configuration

1. Enable barcode scanner:
   - Go to **Settings > Barcode**
   - Enable "Barcode Scanner Support"
   - Configure scanner settings

2. Set up hardware:
   - Connect USB barcode scanner
   - Test scanner functionality
   - Configure scanner prefix/suffix

3. Configure barcode settings:
```env
BARCODE_SCANNER_ENABLED=true
BARCODE_PREFIX=BAR
BARCODE_AUTO_SUBMIT=true
BARCODE_SOUND_ENABLED=true
```

### Mobile POS Configuration

1. Configure API settings:
```env
MOBILE_POS_ENABLED=true
API_RATE_LIMIT=60
API_THROTTLE_REQUESTS=60
API_THROTTLE_MINUTES=1
```

2. Set up CORS:
```php
// config/cors.php
'paths' => ['api/*', 'mobile-pos/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
'max_age' => 86400,
```

3. Configure real-time features:
```env
PUSHER_APP_KEY=your-pusher-key
PUSHER_APP_SECRET=your-pusher-secret
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_CLUSTER=mt1
```

### Supplier Module Configuration

1. Enable supplier module:
```env
SUPPLIER_MODULE_ENABLED=true
PURCHASE_APPROVAL_REQUIRED=true
SUPPLIER_CREDIT_LIMIT_ENABLED=true
```

2. Configure purchase settings:
   - Navigate to **Settings > Purchases**
   - Set approval thresholds
   - Configure payment terms
   - Set default supplier categories

### Report Configuration

1. Configure report settings:
   - Go to **Settings > Reports**
   - Set default date ranges
   - Configure currency display
   - Set decimal precision

2. Set up report caching:
```env
REPORT_CACHE_ENABLED=true
REPORT_CACHE_TTL=3600
REPORT_MAX_CACHE_SIZE=100
```

## Security Configuration

### SSL Certificate

#### Let's Encrypt (Recommended)
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Obtain certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### Manual Certificate
1. Purchase SSL certificate from provider
2. Upload certificate files to server
3. Configure web server with certificate paths

### Firewall Configuration

#### UFW (Ubuntu)
```bash
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

#### iptables
```bash
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT
sudo iptables -A INPUT -j DROP
```

### File Permissions

Set proper file permissions:
```bash
# Set ownership
sudo chown -R www-data:www-data /path/to/supermart

# Set permissions
sudo find /path/to/supermart -type f -exec chmod 644 {} \;
sudo find /path/to/supermart -type d -exec chmod 755 {} \;

# Special permissions
sudo chmod -R 775 /path/to/supermart/storage
sudo chmod -R 775 /path/to/supermart/bootstrap/cache
```

## Performance Optimization

### Database Optimization

1. Create indexes:
```sql
-- Add indexes for better performance
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_orders_store_id ON orders(store_id);
```

2. Configure MySQL:
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
query_cache_size = 64M
```

### PHP Optimization

1. Configure PHP-FPM:
```ini
# /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

2. Enable OPcache:
```ini
# /etc/php/8.2/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### Web Server Optimization

#### Apache
```apache
# Enable compression
LoadModule deflate_module modules/mod_deflate.so

# Enable caching
LoadModule expires_module modules/mod_expires.so
ExpiresActive On
ExpiresByType text/css "access plus 1 month"
ExpiresByType application/javascript "access plus 1 month"
ExpiresByType image/png "access plus 1 month"
```

#### Nginx
```nginx
# Enable gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

# Enable caching
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## Backup Configuration

### Database Backup

Create backup script `/usr/local/bin/backup-supermart.sh`:
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/path/to/backups"
DB_NAME="supermart"
DB_USER="supermart_user"
DB_PASS="secure_password"

# Create backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Remove old backups (keep last 30 days)
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete
```

Make executable and schedule:
```bash
sudo chmod +x /usr/local/bin/backup-supermart.sh
# Add to crontab for daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-supermart.sh
```

### File Backup

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/path/to/backups"
APP_DIR="/path/to/supermart"

# Create file backup
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz -C $APP_DIR storage app

# Remove old backups
find $BACKUP_DIR -name "files_backup_*.tar.gz" -mtime +30 -delete
```

## Monitoring

### Health Check Endpoint

Create health check endpoint:
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version'),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::get('health_check', 'ok'),
    ]);
});
```

### Log Monitoring

1. Configure log rotation:
```bash
# /etc/logrotate.d/supermart
/path/to/supermart/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

2. Monitor errors:
```bash
# Tail error log
tail -f /path/to/supermart/storage/logs/laravel.log

# Find errors in last 24 hours
grep "$(date '+%Y-%m-%d')" /path/to/supermart/storage/logs/laravel.log | grep -i error
```

## Troubleshooting

### Common Issues

1. **404 Errors**:
   - Check web server configuration
   - Verify `.htaccess` file
   - Ensure `mod_rewrite` is enabled

2. **Database Connection Errors**:
   - Verify database credentials
   - Check database server status
   - Ensure proper permissions

3. **Permission Errors**:
   - Check file ownership
   - Verify directory permissions
   - Ensure storage is writable

4. **Performance Issues**:
   - Check database indexes
   - Verify caching configuration
   - Monitor server resources

### Debug Mode

Enable debug mode temporarily:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

Remember to disable in production!

## Maintenance

### Regular Tasks

1. **Weekly**:
   - Check error logs
   - Monitor disk space
   - Review backup integrity

2. **Monthly**:
   - Update dependencies
   - Review security patches
   - Optimize database

3. **Quarterly**:
   - Performance audit
   - Security review
   - Capacity planning

### Updates

1. Backup system before updates
2. Test updates in staging
3. Update dependencies:
```bash
composer update
npm update
```
4. Run migrations:
```bash
php artisan migrate
```
5. Clear caches:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

For additional support or questions, please refer to the feature-specific documentation files or contact support at support@supermart.com.