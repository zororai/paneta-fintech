<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LinkedAccountController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| PANÉTA Zero-Custody Orchestration Platform API Routes
|
| API Versioning Strategy:
| - All routes prefixed with /api/v1/
| - Breaking changes require new version
| - Old versions supported minimum 12 months
| - Deprecation header: X-API-Deprecated: true
|
*/

// Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'version' => config('paneta.api.current_version'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

// API Version 1
Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::patch('/user/kyc-status', [AuthController::class, 'updateKycStatus']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Linked Accounts
        Route::get('/institutions', [LinkedAccountController::class, 'institutions']);
        Route::get('/linked-accounts', [LinkedAccountController::class, 'index']);
        Route::post('/link-account/initiate', [LinkedAccountController::class, 'initiateLink']);
        Route::post('/link-account', [LinkedAccountController::class, 'completeLink']);
        Route::post('/linked-accounts/{linkedAccount}/revoke', [LinkedAccountController::class, 'revoke']);
        Route::post('/linked-accounts/{linkedAccount}/refresh', [LinkedAccountController::class, 'refresh']);

        // Transactions
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
        Route::post('/send-money', [TransactionController::class, 'sendMoney']);

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        // Admin routes (read-only regulator view)
        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::get('/transactions', [AdminController::class, 'transactions']);
            Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/stats', [AdminController::class, 'stats']);
        });
    });
});

// Legacy routes (without version prefix) - redirect to v1
// These will be deprecated in future versions
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::patch('/user/kyc-status', [AuthController::class, 'updateKycStatus']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/institutions', [LinkedAccountController::class, 'institutions']);
    Route::get('/linked-accounts', [LinkedAccountController::class, 'index']);
    Route::post('/link-account/initiate', [LinkedAccountController::class, 'initiateLink']);
    Route::post('/link-account', [LinkedAccountController::class, 'completeLink']);
    Route::post('/linked-accounts/{linkedAccount}/revoke', [LinkedAccountController::class, 'revoke']);
    Route::post('/linked-accounts/{linkedAccount}/refresh', [LinkedAccountController::class, 'refresh']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/send-money', [TransactionController::class, 'sendMoney']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/stats', [AdminController::class, 'stats']);
    });
});
