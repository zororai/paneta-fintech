<?php

use App\Http\Controllers\Paneta\AdminController;
use App\Http\Controllers\Paneta\AuditLogController;
use App\Http\Controllers\Paneta\CurrencyExchangeController;
use App\Http\Controllers\Paneta\DashboardController;
use App\Http\Controllers\Paneta\LinkedAccountController;
use App\Http\Controllers\Paneta\TransactionController;
use App\Http\Controllers\Paneta\WealthController;
use App\Http\Controllers\Paneta\MerchantController;
use App\Http\Controllers\Paneta\PaymentRequestController;
use App\Http\Controllers\Paneta\P2PEscrowController;
use App\Http\Controllers\Paneta\FXMarketplaceController;
use App\Http\Controllers\Paneta\DemoController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Diagnostic route for production deployment - DELETE AFTER USE
Route::get('/paneta-diagnose-2026', function () {
    $results = [];
    
    // Check storage paths
    $paths = [
        'storage_path' => storage_path(),
        'views_path' => storage_path('framework/views'),
        'cache_path' => storage_path('framework/cache'),
        'sessions_path' => storage_path('framework/sessions'),
        'logs_path' => storage_path('logs'),
        'bootstrap_cache' => base_path('bootstrap/cache'),
    ];
    
    foreach ($paths as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        
        // Try to create if doesn't exist
        if (!$exists) {
            @mkdir($path, 0775, true);
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
        }
        
        $results[$name] = [
            'path' => $path,
            'exists' => $exists ? 'YES' : 'NO',
            'writable' => $writable ? 'YES' : 'NO',
        ];
    }
    
    // Check config
    $config = [
        'APP_ENV' => config('app.env'),
        'APP_DEBUG' => config('app.debug') ? 'true' : 'false',
        'VIEW_COMPILED_PATH' => config('view.compiled', 'NOT SET'),
    ];
    
    // Try to clear caches
    $cleared = [];
    try {
        \Artisan::call('config:clear');
        $cleared['config'] = 'CLEARED';
    } catch (\Exception $e) {
        $cleared['config'] = 'FAILED: ' . $e->getMessage();
    }
    
    try {
        \Artisan::call('view:clear');
        $cleared['views'] = 'CLEARED';
    } catch (\Exception $e) {
        $cleared['views'] = 'FAILED: ' . $e->getMessage();
    }
    
    try {
        \Artisan::call('cache:clear');
        $cleared['cache'] = 'CLEARED';
    } catch (\Exception $e) {
        $cleared['cache'] = 'FAILED: ' . $e->getMessage();
    }
    
    return response()->json([
        'status' => 'Diagnostic Complete',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'paths' => $results,
        'config' => $config,
        'caches_cleared' => $cleared,
        'next_steps' => [
            '1. Ensure all paths show EXISTS: YES and WRITABLE: YES',
            '2. If any path shows NO, create the folder manually via cPanel',
            '3. Set folder permissions to 775',
            '4. DELETE this route from routes/web.php after fixing',
        ],
    ], 200, [], JSON_PRETTY_PRINT);
});

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// PANÉTA Routes
Route::middleware(['auth', 'verified'])->prefix('paneta')->name('paneta.')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Linked Accounts
    Route::get('/accounts', [LinkedAccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [LinkedAccountController::class, 'store'])->name('accounts.store');
    Route::post('/accounts/{linkedAccount}/revoke', [LinkedAccountController::class, 'revoke'])->name('accounts.revoke');
    Route::post('/accounts/{linkedAccount}/refresh', [LinkedAccountController::class, 'refresh'])->name('accounts.refresh');

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Currency Exchange
    Route::get('/currency-exchange', [CurrencyExchangeController::class, 'index'])->name('currency-exchange.index');
    Route::post('/currency-exchange/quote', [CurrencyExchangeController::class, 'getQuote'])->name('currency-exchange.quote');

    // Wealth Management (Read-Only)
    Route::get('/wealth', [WealthController::class, 'index'])->name('wealth.index');

    // Payment Requests
    Route::get('/payment-requests', [PaymentRequestController::class, 'index'])->name('payment-requests.index');
    Route::post('/payment-requests', [PaymentRequestController::class, 'store'])->name('payment-requests.store');
    Route::post('/payment-requests/{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel'])->name('payment-requests.cancel');
    Route::get('/payment-requests/{paymentRequest}', [PaymentRequestController::class, 'show'])->name('payment-requests.show');

    // Merchant SoftPOS
    Route::get('/merchant', [MerchantController::class, 'index'])->name('merchant.index');
    Route::post('/merchant/register', [MerchantController::class, 'register'])->name('merchant.register');
    Route::post('/merchant/{merchant}/settlement', [MerchantController::class, 'setSettlementAccount'])->name('merchant.settlement');
    Route::post('/merchant/{merchant}/devices', [MerchantController::class, 'registerDevice'])->name('merchant.devices.store');
    Route::post('/merchant/{merchant}/devices/{device}/deactivate', [MerchantController::class, 'deactivateDevice'])->name('merchant.devices.deactivate');
    Route::post('/merchant/{merchant}/qr', [MerchantController::class, 'generateQr'])->name('merchant.qr');

    // P2P FX Escrow
    Route::get('/p2p-escrow', [P2PEscrowController::class, 'index'])->name('p2p-escrow.index');
    Route::post('/p2p-escrow/offers', [P2PEscrowController::class, 'createOffer'])->name('p2p-escrow.offers.store');
    Route::post('/p2p-escrow/offers/{offer}/cancel', [P2PEscrowController::class, 'cancelOffer'])->name('p2p-escrow.offers.cancel');
    Route::get('/p2p-escrow/offers/{offer}/matches', [P2PEscrowController::class, 'findMatches'])->name('p2p-escrow.offers.matches');
    Route::post('/p2p-escrow/offers/{offer}/accept/{counterOffer}', [P2PEscrowController::class, 'acceptMatch'])->name('p2p-escrow.offers.accept');

    // FX Marketplace
    Route::get('/fx-marketplace', [FXMarketplaceController::class, 'index'])->name('fx-marketplace.index');
    Route::get('/fx-marketplace/order-book', [FXMarketplaceController::class, 'getOrderBook'])->name('fx-marketplace.order-book');
    Route::post('/fx-marketplace/offers/{offer}/take', [FXMarketplaceController::class, 'takeOffer'])->name('fx-marketplace.offers.take');

    // Demo Simulation (for testing)
    Route::get('/demo/status', [DemoController::class, 'status'])->name('demo.status');
    Route::post('/demo/offers/{offer}/simulate-accept', [DemoController::class, 'simulateAcceptOffer'])->name('demo.offers.accept');
    Route::post('/demo/payment-requests/{paymentRequest}/simulate-pay', [DemoController::class, 'simulatePayRequest'])->name('demo.payment-requests.pay');
    Route::post('/demo/seed-marketplace', [DemoController::class, 'seedMarketplace'])->name('demo.seed-marketplace');

    // Admin Routes (Read-only regulator view)
    Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::post('/users/{user}/activate', [AdminController::class, 'activateUser'])->name('users.activate');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
    });
});

require __DIR__.'/settings.php';
