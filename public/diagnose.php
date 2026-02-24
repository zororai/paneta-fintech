<?php
/**
 * PANÉTA Production Diagnostic Script
 * DELETE THIS FILE AFTER USE FOR SECURITY!
 * 
 * Visit: https://panetacapital.co.zw/diagnose.php
 */

header('Content-Type: text/plain');

echo "=== PANÉTA DIAGNOSTIC ===\n\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n\n";

// Get the base path (one level up from public)
$basePath = dirname(__DIR__);
echo "Base Path: $basePath\n\n";

// Check critical directories
echo "=== DIRECTORY CHECK ===\n";
$dirs = [
    'storage' => $basePath . '/storage',
    'storage/framework' => $basePath . '/storage/framework',
    'storage/framework/views' => $basePath . '/storage/framework/views',
    'storage/framework/cache' => $basePath . '/storage/framework/cache',
    'storage/framework/sessions' => $basePath . '/storage/framework/sessions',
    'storage/logs' => $basePath . '/storage/logs',
    'bootstrap/cache' => $basePath . '/bootstrap/cache',
];

$allGood = true;
foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    
    // Try to create if missing
    if (!$exists) {
        @mkdir($path, 0775, true);
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
    }
    
    $status = $exists ? ($writable ? '✓ OK' : '✗ NOT WRITABLE') : '✗ MISSING';
    if (!$writable) $allGood = false;
    
    echo "$name: $status\n";
    echo "  Path: $path\n";
}

echo "\n=== .ENV FILE ===\n";
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    echo "✓ .env exists\n";
    
    // Check key settings (without exposing sensitive data)
    $env = file_get_contents($envFile);
    
    if (preg_match('/APP_KEY=(.+)/', $env, $matches)) {
        $key = trim($matches[1]);
        echo "APP_KEY: " . ($key ? '✓ SET (' . strlen($key) . ' chars)' : '✗ EMPTY') . "\n";
    } else {
        echo "APP_KEY: ✗ NOT FOUND\n";
    }
    
    if (preg_match('/APP_ENV=(.+)/', $env, $matches)) {
        echo "APP_ENV: " . trim($matches[1]) . "\n";
    }
    
    if (preg_match('/APP_DEBUG=(.+)/', $env, $matches)) {
        echo "APP_DEBUG: " . trim($matches[1]) . "\n";
    }
    
    if (preg_match('/DB_CONNECTION=(.+)/', $env, $matches)) {
        echo "DB_CONNECTION: " . trim($matches[1]) . "\n";
    }
    
    if (preg_match('/DB_HOST=(.+)/', $env, $matches)) {
        echo "DB_HOST: " . trim($matches[1]) . "\n";
    }
    
    if (preg_match('/DB_DATABASE=(.+)/', $env, $matches)) {
        echo "DB_DATABASE: " . trim($matches[1]) . "\n";
    }
} else {
    echo "✗ .env DOES NOT EXIST!\n";
    echo "  You must create .env file from .env.example\n";
}

echo "\n=== BOOTSTRAP CACHE ===\n";
$cacheFiles = [
    'config.php' => $basePath . '/bootstrap/cache/config.php',
    'routes-v7.php' => $basePath . '/bootstrap/cache/routes-v7.php',
    'services.php' => $basePath . '/bootstrap/cache/services.php',
];

foreach ($cacheFiles as $name => $path) {
    if (file_exists($path)) {
        echo "$name: EXISTS (delete if having issues)\n";
    } else {
        echo "$name: not cached\n";
    }
}

echo "\n=== DATABASE TEST ===\n";
// Try to load env and test DB connection
if (file_exists($envFile)) {
    $env = file_get_contents($envFile);
    
    // Parse env values
    $dbConn = 'mysql';
    $dbHost = 'localhost';
    $dbName = '';
    $dbUser = '';
    $dbPass = '';
    
    if (preg_match('/DB_CONNECTION=(.+)/', $env, $m)) $dbConn = trim($m[1]);
    if (preg_match('/DB_HOST=(.+)/', $env, $m)) $dbHost = trim($m[1]);
    if (preg_match('/DB_DATABASE=(.+)/', $env, $m)) $dbName = trim($m[1]);
    if (preg_match('/DB_USERNAME=(.+)/', $env, $m)) $dbUser = trim($m[1]);
    if (preg_match('/DB_PASSWORD=(.+)/', $env, $m)) $dbPass = trim($m[1]);
    
    if ($dbConn === 'mysql' && $dbName) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            echo "✓ MySQL connection successful\n";
        } catch (PDOException $e) {
            echo "✗ MySQL connection FAILED: " . $e->getMessage() . "\n";
        }
    } elseif ($dbConn === 'sqlite') {
        $sqlitePath = $basePath . '/database/database.sqlite';
        if (file_exists($sqlitePath)) {
            echo "✓ SQLite database exists\n";
        } else {
            echo "✗ SQLite database NOT FOUND at: $sqlitePath\n";
        }
    } else {
        echo "DB connection type: $dbConn\n";
    }
}

echo "\n=== QUICK FIX COMMANDS ===\n";
echo "If you have SSH access, run these commands:\n\n";
echo "cd $basePath\n";
echo "php artisan config:clear\n";
echo "php artisan cache:clear\n";
echo "php artisan view:clear\n";
echo "php artisan route:clear\n";
echo "chmod -R 775 storage bootstrap/cache\n";

echo "\n=== DELETE THIS FILE AFTER USE ===\n";
echo "For security, delete: public/diagnose.php\n";

if ($allGood) {
    echo "\n✓ All directories OK. Issue may be with database or .env configuration.\n";
} else {
    echo "\n✗ Some directories have issues. Fix them via cPanel File Manager.\n";
}
