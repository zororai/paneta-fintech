<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TransactionIntent;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\LinkedAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function dashboard(Request $request): Response
    {
        $period = $request->get('period', 'all');
        $dateRange = $this->getDateRange($period);

        $totalVolume = TransactionIntent::where('status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->sum('amount');
        $todayVolume = TransactionIntent::where('status', 'executed')
            ->whereDate('created_at', today())
            ->sum('amount');
        
        // Calculate revenue breakdown
        $platformFeeRate = 0.0099; // 0.99%
        $transactionFees = $totalVolume * $platformFeeRate;
        
        // Subscription revenue
        $subscriptionRevenue = Subscription::where('status', 'active')
            ->when($dateRange, fn($q) => $q->whereBetween('started_at', $dateRange))
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum(DB::raw('CASE WHEN subscriptions.billing_cycle = "annual" THEN subscription_plans.annual_price ELSE subscription_plans.monthly_price END'));
        
        // Mock ads revenue (placeholder)
        $adsRevenue = 0;
        
        $totalRevenue = $transactionFees + $subscriptionRevenue + $adsRevenue;

        // Transaction metrics with detailed breakdown
        $transactionStats = $this->getTransactionStats($dateRange);
        
        // User demographics
        $demographics = $this->getUserDemographics();
        
        // Flagged items
        $flaggedData = $this->getFlaggedData();
        
        // Linked accounts stats
        $linkedAccountsStats = $this->getLinkedAccountsStats();
        
        // User breakdown by role
        $usersByRole = $this->getUsersByRole();

        $stats = [
            'total_users' => User::count(),
            'verified_users' => User::where('kyc_status', 'verified')->count(),
            'total_transactions' => TransactionIntent::when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'executed_transactions' => TransactionIntent::where('status', 'executed')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'failed_transactions' => TransactionIntent::where('status', 'failed')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'pending_transactions' => TransactionIntent::where('status', 'pending')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'total_volume' => $totalVolume,
            'today_volume' => $todayVolume,
            'transaction_fees' => round($transactionFees, 2),
            'subscription_revenue' => round($subscriptionRevenue, 2),
            'ads_revenue' => round($adsRevenue, 2),
            'total_revenue' => round($totalRevenue, 2),
            'platform_fee_rate' => $platformFeeRate * 100,
            'transaction_stats' => $transactionStats,
            'demographics' => $demographics,
            'flagged_data' => $flaggedData,
            'linked_accounts_stats' => $linkedAccountsStats,
            'users_by_role' => $usersByRole,
            'current_period' => $period,
        ];

        $recentTransactions = TransactionIntent::with(['user', 'issuerAccount.institution', 'destinationInstitution'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Paneta/Admin/Dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    private function getDateRange($period)
    {
        return match($period) {
            'daily' => [Carbon::today(), Carbon::tomorrow()],
            'weekly' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'biweekly' => [Carbon::now()->subWeeks(2), Carbon::now()],
            'monthly' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'quarterly' => [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()],
            'yearly' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            '5year' => [Carbon::now()->subYears(5), Carbon::now()],
            '10year' => [Carbon::now()->subYears(10), Carbon::now()],
            default => null,
        };
    }

    private function getTransactionStats($dateRange)
    {
        $executed = TransactionIntent::where('status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->select(
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_completion_time'),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        $pending = TransactionIntent::where('status', 'pending')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->select(
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, NOW())) as avg_pending_time'),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        $failed = TransactionIntent::where('status', 'failed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->count();

        return [
            'executed' => [
                'count' => $executed->count ?? 0,
                'avg_completion_time' => round($executed->avg_completion_time ?? 0, 2),
            ],
            'pending' => [
                'count' => $pending->count ?? 0,
                'avg_pending_time' => round($pending->avg_pending_time ?? 0, 2),
            ],
            'failed' => [
                'count' => $failed,
            ],
        ];
    }

    private function getUserDemographics()
    {
        $byCountry = User::select('country', DB::raw('count(*) as count'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $byGender = User::select('gender', DB::raw('count(*) as count'))
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->get();

        $byAgeGroup = User::select(
            DB::raw('CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN "Under 18"
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 24 THEN "18-24"
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 25 AND 34 THEN "25-34"
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 35 AND 44 THEN "35-44"
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 45 AND 54 THEN "45-54"
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 55 AND 64 THEN "55-64"
                ELSE "65+"
            END as age_group'),
            DB::raw('count(*) as count')
        )
        ->whereNotNull('date_of_birth')
        ->groupBy('age_group')
        ->get();

        return [
            'by_country' => $byCountry,
            'by_gender' => $byGender,
            'by_age_group' => $byAgeGroup,
        ];
    }

    private function getFlaggedData()
    {
        $flaggedTransactions = AuditLog::where('action', 'LIKE', '%flagged%')
            ->orWhere('action', 'LIKE', '%suspicious%')
            ->with('user')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'type' => 'transaction',
                    'reference' => $log->metadata['reference'] ?? 'N/A',
                    'user_id' => $log->user_id,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'reason' => $log->metadata['reason'] ?? 'Suspicious activity detected',
                    'recommended_action' => $this->getRecommendedAction($log),
                    'created_at' => $log->created_at,
                ];
            });

        $flaggedUsers = User::where('risk_tier', 'high')
            ->orWhere('is_suspended', true)
            ->limit(20)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'email' => $user->email,
                    'risk_tier' => $user->risk_tier,
                    'is_suspended' => $user->is_suspended,
                    'reason' => $user->risk_tier === 'high' ? 'High risk tier' : 'Account suspended',
                    'recommended_action' => $user->is_suspended ? 'Review suspension' : 'Monitor account',
                ];
            });

        return [
            'transactions' => $flaggedTransactions,
            'users' => $flaggedUsers,
        ];
    }

    private function getRecommendedAction($log)
    {
        $action = $log->action;
        $metadata = $log->metadata ?? [];
        
        if (str_contains($action, 'high_value')) {
            return 'Monitor account';
        }
        if (str_contains($action, 'suspicious')) {
            return 'Freeze account';
        }
        if (isset($metadata['amount']) && $metadata['amount'] > 100000) {
            return 'Report to regulator';
        }
        return 'Review transaction';
    }

    private function getLinkedAccountsStats()
    {
        $total = LinkedAccount::count();
        $active = LinkedAccount::where('status', 'active')->count();
        $failed = LinkedAccount::where('status', 'failed')->count();
        $pending = LinkedAccount::where('status', 'pending')->count();

        $failureReasons = AuditLog::where('action', 'account_link_failed')
            ->select(
                DB::raw('JSON_EXTRACT(metadata, "$.reason") as reason'),
                DB::raw('count(*) as count')
            )
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return [
            'total' => $total,
            'active' => $active,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
            'failure_reasons' => $failureReasons,
        ];
    }

    private function getUsersByRole()
    {
        return User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->mapWithKeys(fn($item) => [$item->role => $item->count]);
    }

    public function getSubscriptionRevenueDetails(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $request->get('period', 'all');
        $dateRange = $this->getDateRange($period);

        $subscriptionDetails = Subscription::where('status', 'active')
            ->when($dateRange, fn($q) => $q->whereBetween('started_at', $dateRange))
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select(
                'subscription_plans.name as tier',
                'subscription_plans.code',
                DB::raw('COUNT(subscriptions.id) as user_count'),
                DB::raw('SUM(CASE WHEN subscriptions.billing_cycle = "annual" THEN subscription_plans.annual_price ELSE subscription_plans.monthly_price END) as revenue'),
                DB::raw('AVG(CASE WHEN subscriptions.billing_cycle = "annual" THEN subscription_plans.annual_price ELSE subscription_plans.monthly_price END) as avg_revenue_per_user')
            )
            ->groupBy('subscription_plans.id', 'subscription_plans.name', 'subscription_plans.code')
            ->get();

        $totalRevenue = $subscriptionDetails->sum('revenue');

        return response()->json([
            'details' => $subscriptionDetails,
            'total_revenue' => round($totalRevenue, 2),
        ]);
    }

    public function getTransactionFeeDetails(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $request->get('period', 'all');
        $dateRange = $this->getDateRange($period);

        $feeDetails = TransactionIntent::where('status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(amount) as total_volume'),
                DB::raw('SUM(amount * 0.0099) as fees_collected')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'details' => $feeDetails,
            'total_fees' => round($feeDetails->sum('fees_collected'), 2),
        ]);
    }

    public function transactions(Request $request): Response
    {
        $query = TransactionIntent::with(['user', 'issuerAccount.institution', 'destinationInstitution', 'paymentInstruction']);

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
