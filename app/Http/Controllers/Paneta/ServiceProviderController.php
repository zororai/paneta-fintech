<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\FxProvider;
use App\Models\FxQuote;
use App\Models\CrossBorderTransactionIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProviderController extends Controller
{
    public function dashboard(): Response
    {
        $user = auth()->user();
        $provider = FxProvider::find($user->fx_provider_id);

        if (!$provider) {
            abort(403, 'No FX Provider associated with this account');
        }

        // Get statistics
        $stats = [
            'total_offers' => FxQuote::where('fx_provider_id', $provider->id)->count(),
            'active_offers' => FxQuote::where('fx_provider_id', $provider->id)
                ->where('status', 'active')
                ->count(),
            'total_trades' => CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
                ->where('status', 'executed')
                ->count(),
            'total_volume' => CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
                ->where('status', 'executed')
                ->sum('amount'),
            'pending_requests' => CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
                ->where('status', 'pending')
                ->count(),
            'revenue_this_month' => CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
                ->where('status', 'executed')
                ->whereMonth('created_at', now()->month)
                ->sum('fee_amount'),
        ];

        // Recent offers
        $recentOffers = FxQuote::where('fx_provider_id', $provider->id)
            ->with('fxProvider')
            ->latest()
            ->limit(5)
            ->get();

        // Recent trades
        $recentTrades = CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
            ->with(['user', 'issuerAccount'])
            ->latest()
            ->limit(10)
            ->get();

        // Performance metrics
        $performanceMetrics = [
            'success_rate' => $this->calculateSuccessRate($provider->id),
            'avg_execution_time' => $this->calculateAvgExecutionTime($provider->id),
            'customer_satisfaction' => $provider->rating ?? 0,
            'volume_trend' => $this->getVolumeTrend($provider->id),
        ];

        return Inertia::render('Paneta/ServiceProvider/Dashboard', [
            'provider' => $provider,
            'stats' => $stats,
            'recentOffers' => $recentOffers,
            'recentTrades' => $recentTrades,
            'performanceMetrics' => $performanceMetrics,
        ]);
    }

    public function offers(Request $request): Response
    {
        $user = auth()->user();
        $provider = FxProvider::find($user->fx_provider_id);

        $query = FxQuote::where('fx_provider_id', $provider->id)
            ->with('fxProvider');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->currency_pair) {
            $query->where('currency_pair', $request->currency_pair);
        }

        $offers = $query->latest()->paginate(20);

        return Inertia::render('Paneta/ServiceProvider/Offers', [
            'provider' => $provider,
            'offers' => $offers,
            'filters' => $request->only(['status', 'currency_pair']),
        ]);
    }

    public function trades(Request $request): Response
    {
        $user = auth()->user();
        $provider = FxProvider::find($user->fx_provider_id);

        $query = CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
            ->with(['user', 'issuerAccount']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $trades = $query->latest()->paginate(20);

        return Inertia::render('Paneta/ServiceProvider/Trades', [
            'provider' => $provider,
            'trades' => $trades,
            'filters' => $request->only(['status']),
        ]);
    }

    public function reports(): Response
    {
        $user = auth()->user();
        $provider = FxProvider::find($user->fx_provider_id);

        // Business Report
        $businessReport = [
            'total_customers' => CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
                ->distinct('user_id')
                ->count('user_id'),
            'repeat_customers' => $this->getRepeatCustomers($provider->id),
            'market_share' => $this->calculateMarketShare($provider->id),
            'growth_rate' => $this->calculateGrowthRate($provider->id),
        ];

        // Performance Report
        $performanceReport = [
            'trades_by_month' => $this->getTradesByMonth($provider->id),
            'volume_by_currency' => $this->getVolumeByCurrency($provider->id),
            'success_rate_trend' => $this->getSuccessRateTrend($provider->id),
            'avg_spread' => FxQuote::where('fx_provider_id', $provider->id)
                ->where('status', 'active')
                ->avg('spread_percentage'),
        ];

        // Financial Report
        $financialReport = [
            'revenue_by_month' => $this->getRevenueByMonth($provider->id),
            'revenue_by_currency_pair' => $this->getRevenueByCurrencyPair($provider->id),
            'total_revenue_ytd' => CrossBorderTransactionIntent::where('fx_provider_id', $provider->id)
                ->where('status', 'executed')
                ->whereYear('created_at', now()->year)
                ->sum('fee_amount'),
            'projected_revenue' => $this->calculateProjectedRevenue($provider->id),
        ];

        return Inertia::render('Paneta/ServiceProvider/Reports', [
            'provider' => $provider,
            'businessReport' => $businessReport,
            'performanceReport' => $performanceReport,
            'financialReport' => $financialReport,
        ]);
    }

    // Helper methods
    private function calculateSuccessRate($providerId): float
    {
        $total = CrossBorderTransactionIntent::where('fx_provider_id', $providerId)->count();
        if ($total === 0) return 0;

        $successful = CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->count();

        return round(($successful / $total) * 100, 2);
    }

    private function calculateAvgExecutionTime($providerId): float
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->whereNotNull('executed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, executed_at)) as avg_time')
            ->value('avg_time') ?? 0;
    }

    private function getVolumeTrend($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->selectRaw('DATE(created_at) as date, SUM(amount) as volume')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->toArray();
    }

    private function getRepeatCustomers($providerId): int
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
    }

    private function calculateMarketShare($providerId): float
    {
        $providerVolume = CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->sum('amount');

        $totalVolume = CrossBorderTransactionIntent::where('status', 'executed')
            ->sum('amount');

        return $totalVolume > 0 ? round(($providerVolume / $totalVolume) * 100, 2) : 0;
    }

    private function calculateGrowthRate($providerId): float
    {
        $currentMonth = CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $lastMonth = CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        return $lastMonth > 0 ? round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2) : 0;
    }

    private function getTradesByMonth($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }

    private function getVolumeByCurrency($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->select('currency', DB::raw('SUM(amount) as total_volume'))
            ->groupBy('currency')
            ->orderByDesc('total_volume')
            ->get()
            ->toArray();
    }

    private function getSuccessRateTrend($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->selectRaw('DATE(created_at) as date, 
                COUNT(*) as total,
                SUM(CASE WHEN status = "executed" THEN 1 ELSE 0 END) as successful')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($item) {
                $item->success_rate = $item->total > 0 ? round(($item->successful / $item->total) * 100, 2) : 0;
                return $item;
            })
            ->toArray();
    }

    private function getRevenueByMonth($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(fee_amount) as revenue')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }

    private function getRevenueByCurrencyPair($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->join('fx_quotes', 'cross_border_transaction_intents.fx_quote_id', '=', 'fx_quotes.id')
            ->select('fx_quotes.currency_pair', DB::raw('SUM(cross_border_transaction_intents.fee_amount) as revenue'))
            ->groupBy('fx_quotes.currency_pair')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    private function calculateProjectedRevenue($providerId): float
    {
        $avgMonthlyRevenue = CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->whereYear('created_at', now()->year)
            ->selectRaw('AVG(monthly_revenue) as avg')
            ->from(DB::raw('(SELECT SUM(fee_amount) as monthly_revenue FROM cross_border_transaction_intents WHERE fx_provider_id = ' . $providerId . ' AND status = "executed" GROUP BY YEAR(created_at), MONTH(created_at)) as monthly_data'))
            ->value('avg') ?? 0;

        return round($avgMonthlyRevenue * 12, 2);
    }
}
