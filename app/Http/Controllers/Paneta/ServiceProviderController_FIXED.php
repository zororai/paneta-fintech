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
                ->sum('source_amount'),
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
        // Using updated_at as proxy since executed_at column doesn't exist
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_time')
            ->value('avg_time') ?? 0;
    }

    private function getVolumeTrend($providerId): array
    {
        return CrossBorderTransactionIntent::where('fx_provider_id', $providerId)
            ->where('status', 'executed')
            ->selectRaw('DATE(created_at) as date, SUM(source_amount) as volume')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->toArray();
    }
}
