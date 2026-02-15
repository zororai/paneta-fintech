<?php

use App\Http\Controllers\Paneta\AdminController;
use App\Http\Controllers\Paneta\AuditLogController;
use App\Http\Controllers\Paneta\CurrencyExchangeController;
use App\Http\Controllers\Paneta\DashboardController;
use App\Http\Controllers\Paneta\LinkedAccountController;
use App\Http\Controllers\Paneta\TransactionController;
use App\Http\Controllers\Paneta\WealthController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

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

    // Admin Routes (Read-only regulator view)
    Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
    });
});

require __DIR__.'/settings.php';
