<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $stats = [
            'total_users' => User::count(),
            'verified_users' => User::where('kyc_status', 'verified')->count(),
            'total_transactions' => TransactionIntent::count(),
            'executed_transactions' => TransactionIntent::where('status', 'executed')->count(),
            'failed_transactions' => TransactionIntent::where('status', 'failed')->count(),
            'pending_transactions' => TransactionIntent::where('status', 'pending')->count(),
            'total_volume' => TransactionIntent::where('status', 'executed')->sum('amount'),
            'today_volume' => TransactionIntent::where('status', 'executed')
                ->whereDate('created_at', today())
                ->sum('amount'),
        ];

        $recentTransactions = TransactionIntent::with(['user', 'issuerAccount.institution'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Paneta/Admin/Dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function transactions(Request $request): Response
    {
        $query = TransactionIntent::with(['user', 'issuerAccount.institution', 'paymentInstruction']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $transactions = $query->latest()->paginate(50);

        return Inertia::render('Paneta/Admin/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'user_id']),
        ]);
    }

    public function auditLogs(Request $request): Response
    {
        $query = AuditLog::with('user');

        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->latest('created_at')->paginate(100);

        $actions = AuditLog::distinct()->pluck('action');

        return Inertia::render('Paneta/Admin/AuditLogs', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->only(['action', 'user_id']),
        ]);
    }

    public function users(): Response
    {
        $users = User::withCount(['linkedAccounts', 'transactionIntents'])
            ->paginate(50);

        return Inertia::render('Paneta/Admin/Users', [
            'users' => $users,
        ]);
    }
}
