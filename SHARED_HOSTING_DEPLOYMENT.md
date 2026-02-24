# PANÉTA Shared Hosting Deployment Guide

**For:** cPanel / Shared Hosting Environments  
**Version:** 1.0  
**Last Updated:** February 2026

---

## Table of Contents

1. [Pre-Deployment Preparation](#pre-deployment-preparation)
2. [Build Assets Locally](#build-assets-locally)
3. [Upload Files to Server](#upload-files-to-server)
4. [Configure Public Directory](#configure-public-directory)
5. [Environment Configuration](#environment-configuration)
6. [Database Setup](#database-setup)
7. [File Permissions](#file-permissions)
8. [Clear Caches](#clear-caches)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)

---

## Pre-Deployment Preparation

### What You Need

- ✅ cPanel access or FTP/SFTP credentials
- ✅ Domain name (e.g., panetacapital.co.zw)
- ✅ MySQL database credentials
- ✅ SMTP credentials for email
- ✅ Local development environment working

### Files to Prepare

Before uploading, ensure you have:
- All application files
- Built frontend assets (compiled)
- Correct `.env` file for production

---

## Build Assets Locally

**IMPORTANT:** Build assets on your local machine BEFORE uploading to shared hosting.

### Step 1: Install Dependencies

```bash
cd c:\Users\Mazarura\Herd\paneta

# Install PHP dependencies (production only)
composer install --optimize-autoloader --no-dev

# Install Node dependencies
npm ci --production
```

### Step 2: Build Frontend Assets

```bash
# Build for production
npm run build
```

This creates compiled assets in `public/build/`.

### Step 3: Optimize Laravel

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Optimize autoloader
composer dump-autoload --optimize
```

---

## Upload Files to Server

### Method 1: Using cPanel File Manager (Recommended)

#### Step 1: Create a ZIP Archive

On your local machine:

```bash
# Create a zip of your entire project
# Exclude unnecessary files
```

**Or manually zip these folders/files:**
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `storage/`
- `vendor/` (if you built locally)
- `.env.example`
- `artisan`
- `composer.json`
- `composer.lock`
- `package.json`

**DO NOT include:**
- `node_modules/` (too large)
- `.git/`
- `.env` (create separately on server)
- `tests/`

#### Step 2: Upload via cPanel

1. Log into **cPanel**
2. Go to **File Manager**
3. Navigate to your domain's root directory (usually `public_html` or `domains/yourdomain.com`)
4. Upload the ZIP file
5. Right-click → **Extract**
6. Delete the ZIP file after extraction

### Method 2: Using FTP/SFTP (FileZilla)

1. Connect to your server via FTP/SFTP
2. Navigate to your domain's directory
3. Upload all files and folders (this will take time)
4. Ensure `storage/` and `bootstrap/cache/` folders are uploaded

---

## Configure Public Directory

**CRITICAL:** Your domain must point to the `public/` folder, not the root.

### Option 1: Using cPanel (Recommended)

1. Go to **cPanel → Domains**
2. Find your domain (panetacapital.co.zw)
3. Click **Manage**
4. Change **Document Root** to: `/home/username/public_html/public`
5. Save changes

### Option 2: Using .htaccess Redirect

If you can't change document root, create `.htaccess` in your root:

**File:** `/public_html/.htaccess`

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

## Environment Configuration

### Step 1: Create .env File

Via **cPanel File Manager**:

1. Navigate to your application root (where `artisan` file is)
2. Click **+ File**
3. Name it: `.env`
4. Right-click → **Edit**
5. Paste the following configuration:

```env
APP_NAME="Panéta Capital"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://panetacapital.co.zw

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=panetacapital.co.zw
SESSION_SECURE_COOKIE=true

# Cache
CACHE_STORE=database
CACHE_PREFIX=paneta_cache

# Queue
QUEUE_CONNECTION=database

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourmailserver.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@panetacapital.co.zw"
MAIL_FROM_NAME="${APP_NAME}"

# Filesystem
FILESYSTEM_DISK=local

# Broadcasting
BROADCAST_CONNECTION=log

# Vite
VITE_APP_NAME="${APP_NAME}"
```

### Step 2: Generate Application Key

You need to generate an `APP_KEY`. Use the diagnostic script:

1. Upload `public/diagnose.php` (already created)
2. Visit: `https://panetacapital.co.zw/diagnose.php`
3. Or manually generate via SSH (if available):

```bash
php artisan key:generate
```

**Manual Generation (if no SSH):**

Create a temporary route in `routes/web.php`:

```php
Route::get('/generate-key-temp', function () {
    Artisan::call('key:generate');
    return 'Key generated! Check your .env file. DELETE THIS ROUTE NOW!';
});
```

Visit: `https://panetacapital.co.zw/generate-key-temp`

Then **immediately delete** this route from `routes/web.php`.

---

## Database Setup

### Step 1: Create Database via cPanel

1. Go to **cPanel → MySQL Databases**
2. Create new database: `paneta_production`
3. Create new user: `paneta_user`
4. Set a strong password
5. Add user to database with **ALL PRIVILEGES**
6. Note down:
   - Database name
   - Username
   - Password
   - Host (usually `localhost`)

### Step 2: Update .env with Database Credentials

Edit `.env` file and update:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=paneta_production
DB_USERNAME=paneta_user
DB_PASSWORD=your_strong_password
```

### Step 3: Run Migrations

**Option A: Via Temporary Route**

Add to `routes/web.php`:

```php
Route::get('/run-migrations-temp', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return 'Migrations completed! DELETE THIS ROUTE NOW!<br><pre>' . Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Migration failed: ' . $e->getMessage();
    }
});
```

Visit: `https://panetacapital.co.zw/run-migrations-temp`

**DELETE THIS ROUTE IMMEDIATELY AFTER USE!**

**Option B: Via SSH (if available)**

```bash
php artisan migrate --force
```

---

## File Permissions

**CRITICAL:** Set correct permissions for Laravel to work.

### Via cPanel File Manager

1. Navigate to `storage/` folder
2. Right-click → **Permissions**
3. Set to: `775` (or `755`)
4. Check **"Recurse into subdirectories"**
5. Apply

Repeat for:
- `storage/` → `775`
- `storage/framework/` → `775`
- `storage/framework/cache/` → `775`
- `storage/framework/sessions/` → `775`
- `storage/framework/views/` → `775`
- `storage/logs/` → `775`
- `bootstrap/cache/` → `775`

### Ensure These Folders Exist

If missing, create them via **File Manager → New Folder**:

```
storage/
├── app/
│   └── public/
├── framework/
│   ├── cache/
│   │   └── data/
│   ├── sessions/
│   └── views/
└── logs/

bootstrap/
└── cache/
```

---

## Clear Caches

### Option 1: Via Temporary Route

Add to `routes/web.php`:

```php
Route::get('/clear-cache-temp', function () {
    $results = [];
    
    try {
        Artisan::call('config:clear');
        $results[] = '✓ Config cache cleared';
    } catch (\Exception $e) {
        $results[] = '✗ Config clear failed: ' . $e->getMessage();
    }
    
    try {
        Artisan::call('cache:clear');
        $results[] = '✓ Application cache cleared';
    } catch (\Exception $e) {
        $results[] = '✗ Cache clear failed: ' . $e->getMessage();
    }
    
    try {
        Artisan::call('view:clear');
        $results[] = '✓ View cache cleared';
    } catch (\Exception $e) {
        $results[] = '✗ View clear failed: ' . $e->getMessage();
    }
    
    try {
        Artisan::call('route:clear');
        $results[] = '✓ Route cache cleared';
    } catch (\Exception $e) {
        $results[] = '✗ Route clear failed: ' . $e->getMessage();
    }
    
    return '<h1>Cache Clearing Results</h1><pre>' . implode("\n", $results) . '</pre><p><strong>DELETE THIS ROUTE NOW!</strong></p>';
});
```

Visit: `https://panetacapital.co.zw/clear-cache-temp`

**DELETE THIS ROUTE IMMEDIATELY!**

### Option 2: Manual File Deletion

Via **cPanel File Manager**, delete these files:

- `bootstrap/cache/config.php`
- `bootstrap/cache/routes-v7.php`
- `bootstrap/cache/services.php`
- All files in `storage/framework/views/`
- All files in `storage/framework/cache/data/`

---

## Testing

### Step 1: Run Diagnostic

Visit: `https://panetacapital.co.zw/diagnose.php`

Check that all shows:
- ✓ Directories exist
- ✓ Directories writable
- ✓ Database connection successful

### Step 2: Test Login Page

Visit: `https://panetacapital.co.zw/login`

Should load without errors.

### Step 3: Register Test User

1. Visit: `https://panetacapital.co.zw/register`
2. Create a test account
3. Verify email works (if configured)
4. Login successfully

### Step 4: Test Core Features

- ✓ Dashboard loads
- ✓ Can link accounts
- ✓ Can create transactions
- ✓ Can create payment requests

---

## Troubleshooting

### Issue: 500 Internal Server Error

**Solution:**

1. Check `.env` file exists and has correct values
2. Check `APP_KEY` is set
3. Check database credentials are correct
4. Check file permissions (storage and bootstrap/cache must be writable)
5. Visit `/diagnose.php` to see detailed errors

### Issue: "View [app] not found"

**Solution:**

1. Ensure `storage/framework/views/` folder exists
2. Set permissions to `775`
3. Clear view cache
4. Ensure `config/view.php` exists

### Issue: Database Connection Failed

**Solution:**

1. Verify database exists in cPanel
2. Verify user has privileges
3. Check `.env` credentials match cPanel
4. Try `DB_HOST=localhost` or `DB_HOST=127.0.0.1`

### Issue: Assets Not Loading (CSS/JS)

**Solution:**

1. Ensure you ran `npm run build` locally before uploading
2. Check `public/build/` folder exists and has files
3. Verify `APP_URL` in `.env` matches your domain
4. Check `.htaccess` in `public/` folder exists

### Issue: "Please provide a valid cache path"

**Solution:**

1. Create `storage/framework/views/` folder
2. Create `storage/framework/sessions/` folder
3. Set permissions to `775`
4. Upload `config/view.php` file
5. Clear config cache

---

## Security Checklist

After deployment:

- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Delete `public/diagnose.php`
- [ ] Delete all temporary routes from `routes/web.php`
- [ ] Ensure `.env` file is not publicly accessible
- [ ] Enable SSL/HTTPS (via cPanel → SSL/TLS)
- [ ] Set strong database password
- [ ] Configure firewall rules (if available)

---

## Deployment Checklist

### Pre-Upload
- [ ] Run `composer install --no-dev --optimize-autoloader`
- [ ] Run `npm ci --production`
- [ ] Run `npm run build`
- [ ] Clear all local caches
- [ ] Create ZIP of project files

### Upload
- [ ] Upload files via cPanel or FTP
- [ ] Extract files (if using ZIP)
- [ ] Set document root to `public/` folder

### Configuration
- [ ] Create `.env` file with production settings
- [ ] Generate `APP_KEY`
- [ ] Create MySQL database
- [ ] Update database credentials in `.env`
- [ ] Run migrations

### Permissions
- [ ] Set `storage/` to `775` (recursive)
- [ ] Set `bootstrap/cache/` to `775`
- [ ] Ensure all required folders exist

### Testing
- [ ] Visit `/diagnose.php` - all checks pass
- [ ] Visit `/login` - page loads
- [ ] Register test user
- [ ] Test core functionality

### Cleanup
- [ ] Delete `/diagnose.php`
- [ ] Delete temporary routes
- [ ] Set `APP_DEBUG=false`
- [ ] Enable HTTPS

---

## Quick Reference Commands

### Generate APP_KEY (via route)

```php
Route::get('/gen-key', function() {
    Artisan::call('key:generate');
    return 'Done! Delete this route.';
});
```

### Run Migrations (via route)

```php
Route::get('/migrate', function() {
    Artisan::call('migrate', ['--force' => true]);
    return 'Done! Delete this route.';
});
```

### Clear Caches (via route)

```php
Route::get('/clear', function() {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    return 'Done! Delete this route.';
});
```

**⚠️ ALWAYS DELETE THESE ROUTES AFTER USE!**

---

## Support

If you encounter issues:

1. Check `/diagnose.php` output
2. Check `storage/logs/laravel.log`
3. Verify all checklist items completed
4. Check cPanel error logs

---

## Updates & Redeployment

When updating the application:

1. Build assets locally: `npm run build`
2. Upload changed files only
3. Clear caches via temporary route
4. Test functionality

---

**End of Shared Hosting Deployment Guide**
