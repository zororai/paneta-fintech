<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TransactionIntent;
use App\Models\User;
use App\Models\LinkedAccount;
use App\Models\Institution;
use App\Services\RegulatorAccessGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class RegulatorController extends Controller
{
    public function __construct(
        protected RegulatorAccessGateway $regulatorGateway
    ) {}

    public function dashboard(Request $request): Response
    {
        $period = $request->get('period', 'all');
        $dateRange = $this->getDateRange($period);

        // 1. Total number of transactions initiated via the platform
        $totalTransactions = TransactionIntent::when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count();
        
        $transactionsByStatus = [
            'total' => $totalTransactions,
            'executed' => TransactionIntent::where('status', 'executed')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'pending' => TransactionIntent::where('status', 'pending')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'failed' => TransactionIntent::where('status', 'failed')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
        ];

        // 2. Total queries raised, success and failure rates
        $queriesStats = $this->getQueriesStats($dateRange);
        
        // 3. Flagged transactions and users
        $flaggedData = $this->getFlaggedData($dateRange);
        
        // 4. Transaction parties (users and institutions)
        $transactionParties = $this->getTransactionParties($dateRange);
        
        // 5. Transaction volume and currency exchange metrics
        $volumeMetrics = $this->getVolumeMetrics($dateRange);
        
        // 6. Performance reports data
        $performanceReports = $this->getPerformanceReports();
        
        // 7. Audit trail summary
        $auditTrailSummary = $this->getAuditTrailSummary($dateRange);
        
        // 8. Capital flows and consolidated reports
        $capitalFlows = $this->getCapitalFlows($dateRange);

        $stats = [
            'current_period' => $period,
            'transactions' => $transactionsByStatus,
            'queries' => $queriesStats,
            'flagged_data' => $flaggedData,
            'transaction_parties' => $transactionParties,
            'volume_metrics' => $volumeMetrics,
            'performance_reports' => $performanceReports,
            'audit_trail_summary' => $auditTrailSummary,
            'capital_flows' => $capitalFlows,
        ];

        // Log regulator access
        $this->regulatorGateway->logRegulatorAccess(
            auth()->user(),
            'regulator_dashboard',
            ['period' => $period]
        );

        return Inertia::render('Paneta/Regulator/Dashboard', [
            'stats' => $stats,
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
            default => null,
        };
    }

    private function getQueriesStats($dateRange)
    {
        $totalQueries = AuditLog::where('action', 'LIKE', '%query%')
            ->orWhere('action', 'LIKE', '%issue%')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->count();

        $resolvedQueries = AuditLog::where('action', 'LIKE', '%query_resolved%')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->count();

        $successRate = $totalQueries > 0 ? round(($resolvedQueries / $totalQueries) * 100, 2) : 0;

        return [
            'total' => $totalQueries,
            'resolved' => $resolvedQueries,
            'pending' => $totalQueries - $resolvedQueries,
            'success_rate' => $successRate,
            'failure_rate' => 100 - $successRate,
        ];
    }

    private function getFlaggedData($dateRange)
    {
        $flaggedTransactions = AuditLog::where(function($q) {
                $q->where('action', 'LIKE', '%flagged%')
                  ->orWhere('action', 'LIKE', '%suspicious%')
                  ->orWhere('action', 'LIKE', '%alert%');
            })
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->with('user')
            ->latest()
            ->limit(50)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'reference' => $log->metadata['reference'] ?? 'N/A',
                    'user_id' => $log->user_id,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'user_email' => $log->user->email ?? 'N/A',
                    'reason' => $log->metadata['reason'] ?? 'Flagged for review',
                    'action' => $log->action,
                    'created_at' => $log->created_at,
                ];
            });

        $flaggedUsers = User::where('risk_tier', 'high')
            ->orWhere('is_suspended', true)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'risk_tier' => $user->risk_tier,
                    'is_suspended' => $user->is_suspended,
                    'kyc_status' => $user->kyc_status,
                    'total_transactions' => $user->transactionIntents()->count(),
                ];
            });

        return [
            'transactions' => $flaggedTransactions,
            'users' => $flaggedUsers,
        ];
    }

    private function getTransactionParties($dateRange)
    {
        $transactions = TransactionIntent::with(['user', 'issuerAccount.institution', 'destinationInstitution'])
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->latest()
            ->limit(100)
            ->get()
            ->map(function($txn) {
                return [
                    'reference' => $txn->reference,
                    'user' => [
                        'id' => $txn->user->id,
                        'name' => $txn->user->name,
                        'email' => $txn->user->email,
                    ],
                    'issuer_institution' => [
                        'id' => $txn->issuerAccount->institution->id ?? null,
                        'name' => $txn->issuerAccount->institution->name ?? 'Unknown',
                        'type' => $txn->issuerAccount->institution->type ?? 'N/A',
                        'country' => $txn->issuerAccount->institution->country ?? 'N/A',
                    ],
                    'destination_institution' => [
                        'id' => $txn->destinationInstitution->id ?? null,
                        'name' => $txn->destinationInstitution->name ?? 'Unknown',
                        'type' => $txn->destinationInstitution->type ?? 'N/A',
                        'country' => $txn->destinationInstitution->country ?? 'N/A',
                    ],
                    'amount' => $txn->amount,
                    'currency' => $txn->currency,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at,
                ];
            });

        $institutionsSummary = Institution::withCount('linkedAccounts')
            ->get()
            ->map(function($inst) {
                return [
                    'name' => $inst->name,
                    'type' => $inst->type,
                    'country' => $inst->country,
                    'linked_accounts_count' => $inst->linked_accounts_count,
                ];
            });

        return [
            'recent_transactions' => $transactions,
            'institutions_summary' => $institutionsSummary,
            'total_users' => User::count(),
            'total_institutions' => Institution::count(),
        ];
    }

    private function getVolumeMetrics($dateRange)
    {
        $totalVolume = TransactionIntent::where('status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->sum('amount');

        $volumeByCurrency = TransactionIntent::where('status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->select('currency', DB::raw('SUM(amount) as total_volume'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('currency')
            ->get();

        $volumeByInstitution = TransactionIntent::where('transaction_intents.status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('transaction_intents.created_at', $dateRange))
            ->join('linked_accounts', 'transaction_intents.issuer_account_id', '=', 'linked_accounts.id')
            ->join('institutions', 'linked_accounts.institution_id', '=', 'institutions.id')
            ->select('institutions.name', DB::raw('SUM(transaction_intents.amount) as total_volume'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('institutions.id', 'institutions.name')
            ->orderByDesc('total_volume')
            ->limit(10)
            ->get();

        $currencyExchangeActivity = TransactionIntent::where('status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->whereNotNull('destination_currency')
            ->where('currency', '!=', DB::raw('destination_currency'))
            ->select('currency as from_currency', 'destination_currency as to_currency', DB::raw('COUNT(*) as exchange_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('currency', 'destination_currency')
            ->get();

        return [
            'total_volume' => round($totalVolume, 2),
            'by_currency' => $volumeByCurrency,
            'by_institution' => $volumeByInstitution,
            'currency_exchange' => $currencyExchangeActivity,
        ];
    }

    private function getPerformanceReports()
    {
        return [
            'available_reports' => [
                ['type' => 'daily', 'label' => 'Daily Performance Report', 'last_generated' => Carbon::yesterday()],
                ['type' => 'weekly', 'label' => 'Weekly Performance Report', 'last_generated' => Carbon::now()->subWeek()],
                ['type' => 'biweekly', 'label' => 'Bi-Weekly Performance Report', 'last_generated' => Carbon::now()->subWeeks(2)],
                ['type' => 'monthly', 'label' => 'Monthly Performance Report', 'last_generated' => Carbon::now()->subMonth()],
                ['type' => 'quarterly', 'label' => 'Quarterly Performance Report', 'last_generated' => Carbon::now()->subQuarter()],
                ['type' => 'yearly', 'label' => 'Yearly Performance Report', 'last_generated' => Carbon::now()->subYear()],
            ],
        ];
    }

    private function getAuditTrailSummary($dateRange)
    {
        $totalAuditLogs = AuditLog::when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count();

        $logsByAction = AuditLog::when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $logsByInstitution = AuditLog::when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
            ->where('entity_type', 'institution')
            ->select('entity_id', DB::raw('COUNT(*) as count'))
            ->groupBy('entity_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'total_logs' => $totalAuditLogs,
            'by_action' => $logsByAction,
            'by_institution_count' => $logsByInstitution->count(),
        ];
    }

    private function getCapitalFlows($dateRange)
    {
        $inflows = TransactionIntent::where('transaction_intents.status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('transaction_intents.created_at', $dateRange))
            ->whereNotNull('transaction_intents.destination_country')
            ->select('transaction_intents.destination_country', DB::raw('SUM(transaction_intents.amount) as total_inflow'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('transaction_intents.destination_country')
            ->orderByDesc('total_inflow')
            ->get();

        $outflows = TransactionIntent::where('transaction_intents.status', 'executed')
            ->when($dateRange, fn($q) => $q->whereBetween('transaction_intents.created_at', $dateRange))
            ->join('linked_accounts', 'transaction_intents.issuer_account_id', '=', 'linked_accounts.id')
            ->select('linked_accounts.country', DB::raw('SUM(transaction_intents.amount) as total_outflow'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('linked_accounts.country')
            ->orderByDesc('total_outflow')
            ->get();

        $netFlows = [];
        foreach ($inflows as $inflow) {
            $outflow = $outflows->firstWhere('country', $inflow->destination_country);
            $netFlows[] = [
                'country' => $inflow->destination_country,
                'inflow' => $inflow->total_inflow,
                'outflow' => $outflow->total_outflow ?? 0,
                'net_flow' => $inflow->total_inflow - ($outflow->total_outflow ?? 0),
            ];
        }

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'net_flows' => $netFlows,
        ];
    }

    public function transactions(Request $request): Response
    {
        $query = TransactionIntent::with(['user', 'issuerAccount.institution', 'destinationInstitution', 'paymentInstruction']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('institution_id') && $request->institution_id) {
            $query->whereHas('issuerAccount', function($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }

        $transactions = $query->latest()->paginate(50);

        // Get transaction parties data
        $transactionParties = $this->getTransactionParties(null);
        
        // Get volume metrics
        $volumeMetrics = $this->getVolumeMetrics(null);
        
        // Get currency exchange activity
        $currencyExchange = TransactionIntent::where('transaction_intents.status', 'executed')
            ->whereNotNull('destination_currency')
            ->where('currency', '!=', DB::raw('destination_currency'))
            ->select('currency as from_currency', 'destination_currency as to_currency', DB::raw('COUNT(*) as exchange_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('currency', 'destination_currency')
            ->get();

        $this->regulatorGateway->logRegulatorAccess(
            auth()->user(),
            'transactions_view',
            $request->only(['status', 'institution_id'])
        );

        return Inertia::render('Paneta/Regulator/Transactions', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'institution_id']),
            'transaction_parties' => $transactionParties,
            'volume_metrics' => $volumeMetrics,
            'currency_exchange' => $currencyExchange,
        ]);
    }

    public function auditTrail(Request $request): Response
    {
        $query = AuditLog::with('user');

        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }

        if ($request->has('entity_type') && $request->entity_type) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest('created_at')->paginate(100);

        $actions = AuditLog::distinct()->pluck('action');

        // Get summary statistics
        $summary = [
            'total_logs' => AuditLog::count(),
            'by_action' => AuditLog::select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->get(),
            'by_entity_type' => AuditLog::select('entity_type', DB::raw('COUNT(*) as count'))
                ->groupBy('entity_type')
                ->orderByDesc('count')
                ->get(),
            'by_date' => AuditLog::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderByDesc('date')
                ->limit(30)
                ->get(),
        ];

        $this->regulatorGateway->logRegulatorAccess(
            auth()->user(),
            'audit_trail_view',
            $request->only(['action', 'entity_type', 'date_from', 'date_to'])
        );

        return Inertia::render('Paneta/Regulator/AuditTrail', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->only(['action', 'entity_type', 'date_from', 'date_to']),
            'summary' => $summary,
        ]);
    }

    public function generateReport(Request $request)
    {
        $reportType = $request->get('type', 'daily');
        $dateRange = $this->getDateRange($reportType);

        $report = [
            'type' => $reportType,
            'generated_at' => now(),
            'period' => $dateRange ? [
                'from' => $dateRange[0]->toDateString(),
                'to' => $dateRange[1]->toDateString(),
            ] : 'all_time',
            'transactions' => $this->getTransactionReport($dateRange),
            'volume_metrics' => $this->getVolumeMetrics($dateRange),
            'capital_flows' => $this->getCapitalFlows($dateRange),
            'audit_summary' => $this->getAuditTrailSummary($dateRange),
        ];

        $this->regulatorGateway->logRegulatorAccess(
            auth()->user(),
            'report_generated',
            ['report_type' => $reportType]
        );

        return response()->json($report);
    }

    private function getTransactionReport($dateRange)
    {
        return [
            'total' => TransactionIntent::when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'executed' => TransactionIntent::where('status', 'executed')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'pending' => TransactionIntent::where('status', 'pending')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
            'failed' => TransactionIntent::where('status', 'failed')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))->count(),
        ];
    }
}
