<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TransactionIntent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function transactions(Request $request): JsonResponse
    {
        $query = TransactionIntent::with(['user', 'issuerAccount.institution', 'paymentInstruction']);

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        // Optional filters
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest('created_at')->paginate(100);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $users = User::withCount(['linkedAccounts', 'transactionIntents'])
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function stats(): JsonResponse
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

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
