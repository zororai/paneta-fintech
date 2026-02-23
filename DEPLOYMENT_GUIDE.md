# PANÉTA Production Deployment Guide

**Version:** 1.0  
**Last Updated:** February 2026

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Server Setup](#server-setup)
4. [Application Installation](#application-installation)
5. [Environment Configuration](#environment-configuration)
6. [Database Setup](#database-setup)
7. [Build & Compile Assets](#build--compile-assets)
8. [Web Server Configuration](#web-server-configuration)
9. [SSL/TLS Setup](#ssltls-setup)
10. [Queue & Scheduler Setup](#queue--scheduler-setup)
11. [Security Hardening](#security-hardening)
12. [Performance Optimization](#performance-optimization)
13. [Monitoring & Logging](#monitoring--logging)
14. [Backup Strategy](#backup-strategy)
15. [Deployment Checklist](#deployment-checklist)
16. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Requirements
- **PHP:** 8.2 or higher
- **Database:** MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+
- **Node.js:** 18.x or higher
- **NPM:** 9.x or higher
- **Composer:** 2.x
- **Web Server:** Nginx 1.18+ or Apache 2.4+
- **Memory:** 2GB RAM minimum (4GB recommended)
- **Storage:** 10GB minimum

### PHP Extensions Required
```
- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- cURL
- GD or Imagick (for QR code generation)
```

### Recommended Server Specifications
- **CPU:** 2+ cores
- **RAM:** 4GB+
- **Storage:** SSD with 20GB+
- **Network:** 100Mbps+

---

## Pre-Deployment Checklist

Before deploying to production, ensure you have:

- [ ] Production server with SSH access
- [ ] Domain name configured and DNS pointing to server
- [ ] SSL certificate (Let's Encrypt recommended)
- [ ] Database server (MySQL/PostgreSQL) or SQLite file
- [ ] SMTP server for email notifications
- [ ] Backup solution configured
- [ ] Monitoring tools setup
- [ ] Firewall rules configured
- [ ] Root or sudo access to server

---

## Server Setup

### 1. Update System Packages

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt upgrade -y
```

**CentOS/RHEL:**
```bash
sudo yum update -y
```

### 2. Install PHP 8.2+

**Ubuntu/Debian:**
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
    php8.2-mysql php8.2-pgsql php8.2-sqlite3 php8.2-mbstring \
    php8.2-xml php8.2-bcmath php8.2-curl php8.2-gd php8.2-zip \
    php8.2-intl php8.2-redis
```

**Verify PHP Installation:**
```bash
php -v
```

### 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 4. Install Node.js & NPM

**Using NodeSource:**
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

### 5. Install Database Server

**MySQL:**
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

**PostgreSQL:**
```bash
sudo apt install -y postgresql postgresql-contrib
```

**SQLite (already included with PHP):**
```bash
# No additional installation needed
```

### 6. Install Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

---

## Application Installation

### 1. Create Application Directory

```bash
sudo mkdir -p /var/www/paneta
sudo chown -R $USER:$USER /var/www/paneta
cd /var/www/paneta
```

### 2. Clone or Upload Application

**Option A: Git Clone (if using version control)**
```bash
git clone https://github.com/your-repo/paneta.git .
```

**Option B: Upload Files**
```bash
# Upload files via SCP, SFTP, or rsync
# Example using rsync:
rsync -avz --exclude 'node_modules' --exclude 'vendor' \
    /local/path/to/paneta/ user@server:/var/www/paneta/
```

### 3. Set Correct Permissions

```bash
cd /var/www/paneta
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## Environment Configuration

### 1. Create Production Environment File

```bash
cd /var/www/paneta
cp .env.example .env
nano .env
```

### 2. Configure Production Environment Variables

**Critical Production Settings:**

```env
# Application
APP_NAME="Panéta Capital"
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_KEY
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Locale
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

# Security
BCRYPT_ROUNDS=12

# Logging
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database - MySQL Example
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paneta_production
DB_USERNAME=paneta_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Database - PostgreSQL Example
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=paneta_production
# DB_USERNAME=paneta_user
# DB_PASSWORD=STRONG_PASSWORD_HERE

# Database - SQLite Example (for smaller deployments)
# DB_CONNECTION=sqlite
# DB_DATABASE=/var/www/paneta/database/database.sqlite

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=yourdomain.com
SESSION_SECURE_COOKIE=true

# Cache
CACHE_STORE=redis
CACHE_PREFIX=paneta_cache

# Queue
QUEUE_CONNECTION=redis

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail - Production SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# AWS (if using S3 for file storage)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Filesystem
FILESYSTEM_DISK=local

# Broadcasting
BROADCAST_CONNECTION=log

# Vite
VITE_APP_NAME="${APP_NAME}"
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

**IMPORTANT:** Never share or commit your `APP_KEY` to version control!

---

## Database Setup

### 1. Create Production Database

**MySQL:**
```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE paneta_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'paneta_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON paneta_production.* TO 'paneta_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**PostgreSQL:**
```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE paneta_production;
CREATE USER paneta_user WITH PASSWORD 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE paneta_production TO paneta_user;
\q
```

**SQLite:**
```bash
touch /var/www/paneta/database/database.sqlite
chmod 664 /var/www/paneta/database/database.sqlite
sudo chown www-data:www-data /var/www/paneta/database/database.sqlite
```

### 2. Run Database Migrations

```bash
cd /var/www/paneta
php artisan migrate --force
```

**Expected Output:**
```
Migration table created successfully.
Migrating: 2014_10_12_000000_create_users_table
Migrated:  2014_10_12_000000_create_users_table (XX.XXms)
...
```

### 3. Seed Initial Data (Optional)

```bash
php artisan db:seed --force
```

---

## Build & Compile Assets

### 1. Install PHP Dependencies

```bash
cd /var/www/paneta
composer install --optimize-autoloader --no-dev
```

**Flags Explained:**
- `--optimize-autoloader`: Optimizes autoloader for production
- `--no-dev`: Excludes development dependencies

### 2. Install Node Dependencies

```bash
npm ci --production
```

**Note:** `npm ci` is preferred over `npm install` for production as it installs exact versions from `package-lock.json`.

### 3. Build Frontend Assets

```bash
npm run build
```

**Expected Output:**
```
vite v7.0.4 building for production...
✓ built in XXXms
```

### 4. Clear and Cache Configuration

```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Optimize Autoloader

```bash
composer dump-autoload --optimize
```

---

## Web Server Configuration

### Nginx Configuration

#### 1. Create Nginx Server Block

```bash
sudo nano /etc/nginx/sites-available/paneta
```

**Configuration:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/paneta/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;
    
    # Logging
    access_log /var/log/nginx/paneta-access.log;
    error_log /var/log/nginx/paneta-error.log;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;
    
    # Client Max Body Size
    client_max_body_size 20M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### 2. Enable Site and Restart Nginx

```bash
sudo ln -s /etc/nginx/sites-available/paneta /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Apache Configuration (Alternative)

#### 1. Create Apache Virtual Host

```bash
sudo nano /etc/apache2/sites-available/paneta.conf
```

**Configuration:**
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    
    DocumentRoot /var/www/paneta/public
    
    <Directory /var/www/paneta/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/paneta-error.log
    CustomLog ${APACHE_LOG_DIR}/paneta-access.log combined
</VirtualHost>
```

#### 2. Enable Modules and Site

```bash
sudo a2enmod rewrite ssl
sudo a2ensite paneta.conf
sudo systemctl restart apache2
```

---

## SSL/TLS Setup

### Using Let's Encrypt (Recommended)

#### 1. Install Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
```

#### 2. Obtain SSL Certificate

```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

#### 3. Auto-Renewal Setup

```bash
sudo certbot renew --dry-run
```

Certbot automatically sets up a cron job for renewal.

---

## Queue & Scheduler Setup

### 1. Install Supervisor (Queue Worker Manager)

```bash
sudo apt install -y supervisor
```

### 2. Create Supervisor Configuration

```bash
sudo nano /etc/supervisor/conf.d/paneta-worker.conf
```

**Configuration:**
```ini
[program:paneta-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/paneta/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/paneta/storage/logs/worker.log
stopwaitsecs=3600
```

### 3. Start Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start paneta-worker:*
```

### 4. Setup Laravel Scheduler

Add to crontab:
```bash
sudo crontab -e -u www-data
```

Add this line:
```
* * * * * cd /var/www/paneta && php artisan schedule:run >> /dev/null 2>&1
```

---

## Security Hardening

### 1. Disable Directory Listing

Already handled in Nginx/Apache config.

### 2. Secure File Permissions

```bash
cd /var/www/paneta
sudo chown -R www-data:www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache
```

### 3. Protect Sensitive Files

```bash
# Ensure .env is not accessible
sudo chmod 600 .env
```

### 4. Configure Firewall

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 5. Install Fail2Ban (Brute Force Protection)

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 6. Disable PHP Version Exposure

Edit PHP-FPM config:
```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Set:
```ini
expose_php = Off
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

---

## Performance Optimization

### 1. Enable OPcache

Edit PHP config:
```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Add/Update:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### 2. Install and Configure Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 3. Configure PHP-FPM Pool

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Optimize:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

### 4. Database Optimization

**MySQL:**
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Add:
```ini
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 200
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

---

## Monitoring & Logging

### 1. Application Logs

Located at: `/var/www/paneta/storage/logs/`

View logs:
```bash
tail -f /var/www/paneta/storage/logs/laravel.log
```

### 2. Web Server Logs

**Nginx:**
```bash
tail -f /var/log/nginx/paneta-access.log
tail -f /var/log/nginx/paneta-error.log
```

### 3. Setup Log Rotation

```bash
sudo nano /etc/logrotate.d/paneta
```

```
/var/www/paneta/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 4. Monitoring Tools (Optional)

- **New Relic** - Application performance monitoring
- **Sentry** - Error tracking
- **Datadog** - Infrastructure monitoring
- **Prometheus + Grafana** - Metrics and dashboards

---

## Backup Strategy

### 1. Database Backup Script

Create backup script:
```bash
sudo nano /usr/local/bin/paneta-backup.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/paneta"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="paneta_production"
DB_USER="paneta_user"
DB_PASS="YOUR_PASSWORD"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Application files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/paneta \
    --exclude='/var/www/paneta/storage/logs' \
    --exclude='/var/www/paneta/node_modules' \
    --exclude='/var/www/paneta/vendor'

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

Make executable:
```bash
sudo chmod +x /usr/local/bin/paneta-backup.sh
```

### 2. Schedule Daily Backups

```bash
sudo crontab -e
```

Add:
```
0 2 * * * /usr/local/bin/paneta-backup.sh >> /var/log/paneta-backup.log 2>&1
```

### 3. Off-Site Backup (Recommended)

Use services like:
- AWS S3
- Google Cloud Storage
- Backblaze B2
- rsync to remote server

---

## Deployment Checklist

### Pre-Deployment
- [ ] Server meets minimum requirements
- [ ] Domain DNS configured
- [ ] SSL certificate obtained
- [ ] Database created and credentials set
- [ ] SMTP configured for emails
- [ ] Backup solution in place

### Deployment Steps
- [ ] Upload/clone application code
- [ ] Install Composer dependencies (`composer install --no-dev --optimize-autoloader`)
- [ ] Install NPM dependencies (`npm ci --production`)
- [ ] Configure `.env` file with production settings
- [ ] Generate application key (`php artisan key:generate`)
- [ ] Run database migrations (`php artisan migrate --force`)
- [ ] Build frontend assets (`npm run build`)
- [ ] Cache configuration (`php artisan config:cache`)
- [ ] Cache routes (`php artisan route:cache`)
- [ ] Cache views (`php artisan view:cache`)
- [ ] Set correct file permissions
- [ ] Configure web server (Nginx/Apache)
- [ ] Setup SSL/TLS
- [ ] Configure queue workers (Supervisor)
- [ ] Setup scheduler (cron)
- [ ] Test application functionality

### Post-Deployment
- [ ] Verify site loads correctly
- [ ] Test user registration and login
- [ ] Test transaction flows
- [ ] Verify email sending
- [ ] Check logs for errors
- [ ] Monitor performance
- [ ] Setup monitoring and alerts
- [ ] Document deployment date and version

---

## Troubleshooting

### Issue: 500 Internal Server Error

**Solution:**
```bash
# Check Laravel logs
tail -f /var/www/paneta/storage/logs/laravel.log

# Check web server logs
tail -f /var/log/nginx/paneta-error.log

# Ensure storage is writable
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Issue: Assets Not Loading

**Solution:**
```bash
# Rebuild assets
npm run build

# Clear cache
php artisan cache:clear
php artisan view:clear

# Check public directory permissions
ls -la /var/www/paneta/public
```

### Issue: Database Connection Failed

**Solution:**
```bash
# Verify database credentials in .env
cat .env | grep DB_

# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check database service
sudo systemctl status mysql
```

### Issue: Queue Jobs Not Processing

**Solution:**
```bash
# Check Supervisor status
sudo supervisorctl status paneta-worker:*

# Restart workers
sudo supervisorctl restart paneta-worker:*

# Check worker logs
tail -f /var/www/paneta/storage/logs/worker.log
```

### Issue: Permission Denied Errors

**Solution:**
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/paneta

# Fix permissions
sudo find /var/www/paneta -type f -exec chmod 644 {} \;
sudo find /var/www/paneta -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache
```

---

## Quick Deployment Commands

For quick reference, here's a complete deployment script:

```bash
#!/bin/bash
cd /var/www/paneta

# Pull latest code (if using Git)
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci --production

# Build assets
npm run build

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart paneta-worker:*
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

echo "Deployment completed successfully!"
```

---

## Support & Maintenance

### Regular Maintenance Tasks

**Daily:**
- Monitor logs for errors
- Check queue worker status
- Verify backup completion

**Weekly:**
- Review application performance
- Check disk space usage
- Update security patches

**Monthly:**
- Review and rotate logs
- Database optimization
- Security audit
- Dependency updates

### Getting Help

- Check Laravel documentation: https://laravel.com/docs
- Review application logs: `/var/www/paneta/storage/logs/`
- Check web server logs: `/var/log/nginx/` or `/var/log/apache2/`

---

**End of Deployment Guide**
