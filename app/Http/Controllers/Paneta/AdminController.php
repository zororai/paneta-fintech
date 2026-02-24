<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TransactionIntent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function dashboard(): Response
    {
        $totalVolume = TransactionIntent::where('status', 'executed')->sum('amount');
        $todayVolume = TransactionIntent::where('status', 'executed')
            ->whereDate('created_at', today())
            ->sum('amount');
        
        // Calculate 0.99% platform fee
        $platformFeeRate = 0.0099; // 0.99%
        $totalFeesCollected = $totalVolume * $platformFeeRate;
        $todayFeesCollected = $todayVolume * $platformFeeRate;

        $stats = [
            'total_users' => User::count(),
            'verified_users' => User::where('kyc_status', 'verified')->count(),
            'total_transactions' => TransactionIntent::count(),
            'executed_transactions' => TransactionIntent::where('status', 'executed')->count(),
            'failed_transactions' => TransactionIntent::where('status', 'failed')->count(),
            'pending_transactions' => TransactionIntent::where('status', 'pending')->count(),
            'total_volume' => $totalVolume,
            'today_volume' => $todayVolume,
            'total_fees_collected' => round($totalFeesCollected, 2),
            'today_fees_collected' => round($todayFeesCollected, 2),
            'platform_fee_rate' => $platformFeeRate * 100, // Convert to percentage for display
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

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'kyc_status' => ['required', 'string', 'in:pending,verified,rejected'],
            'risk_tier' => ['required', 'string', 'in:low,medium,high'],
            'role' => ['required', 'string', 'in:user,admin,regulator'],
        ]);

        $oldKycStatus = $user->kyc_status;
        $user->update($validated);

        if ($oldKycStatus !== $validated['kyc_status']) {
            $this->auditService->logKycStatusChanged($user, $oldKycStatus, $validated['kyc_status']);
        }

        $this->auditService->log(
            'admin_user_updated',
            'user',
            $user->id,
            auth()->user(),
            [
                'updated_fields' => $validated,
                'admin_id' => auth()->id(),
            ]
        );

        return redirect()->back()->with('success', 'User updated successfully');
    }

    public function suspendUser(User $user): RedirectResponse
    {
        $user->update(['is_suspended' => true]);

        $this->auditService->log(
            'admin_user_suspended',
            'user',
            $user->id,
            auth()->user(),
            [
                'user_email' => $user->email,
                'admin_id' => auth()->id(),
            ]
        );

        return redirect()->back()->with('success', 'User suspended successfully');
    }

    public function activateUser(User $user): RedirectResponse
    {
        $user->update(['is_suspended' => false]);

        $this->auditService->log(
            'admin_user_activated',
            'user',
            $user->id,
            auth()->user(),
            [
                'user_email' => $user->email,
                'admin_id' => auth()->id(),
            ]
        );

        return redirect()->back()->with('success', 'User activated successfully');
    }

    public function deleteUser(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account');
        }

        $userEmail = $user->email;
        $userId = $user->id;

        $this->auditService->log(
            'admin_user_deleted',
            'user',
            $userId,
            auth()->user(),
            [
                'deleted_user_email' => $userEmail,
                'deleted_user_name' => $user->name,
                'admin_id' => auth()->id(),
            ]
        );

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully');
    }
}
