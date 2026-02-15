<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WealthController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $brokerAccounts = $user->linkedAccounts()
            ->with('institution')
            ->whereHas('institution', fn($q) => $q->whereIn('type', ['broker', 'custodian']))
            ->where('status', 'active')
            ->get();

        $mockHoldings = $this->getMockHoldings();
        $mockAnalytics = $this->getMockAnalytics();

        return Inertia::render('Paneta/Wealth', [
            'brokerAccounts' => $brokerAccounts,
            'holdings' => $mockHoldings,
            'analytics' => $mockAnalytics,
            'isReadOnly' => true,
        ]);
    }

    private function getMockHoldings(): array
    {
        return [
            [
                'asset_class' => 'Equities',
                'holdings' => [
                    ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'quantity' => 50, 'current_price' => 178.50, 'market_value' => 8925.00, 'allocation_pct' => 25.5, 'sector' => 'Technology', 'region' => 'North America'],
                    ['symbol' => 'MSFT', 'name' => 'Microsoft Corp.', 'quantity' => 30, 'current_price' => 378.20, 'market_value' => 11346.00, 'allocation_pct' => 32.4, 'sector' => 'Technology', 'region' => 'North America'],
                    ['symbol' => 'NPN', 'name' => 'Naspers Ltd.', 'quantity' => 20, 'current_price' => 3250.00, 'market_value' => 3513.51, 'allocation_pct' => 10.0, 'sector' => 'Technology', 'region' => 'Africa'],
                ],
            ],
            [
                'asset_class' => 'ETFs',
                'holdings' => [
                    ['symbol' => 'VOO', 'name' => 'Vanguard S&P 500', 'quantity' => 15, 'current_price' => 450.30, 'market_value' => 6754.50, 'allocation_pct' => 19.3, 'sector' => 'Diversified', 'region' => 'Global'],
                ],
            ],
            [
                'asset_class' => 'Bonds',
                'holdings' => [
                    ['symbol' => 'BND', 'name' => 'Vanguard Total Bond', 'quantity' => 50, 'current_price' => 72.50, 'market_value' => 3625.00, 'allocation_pct' => 10.4, 'sector' => 'Fixed Income', 'region' => 'North America'],
                ],
            ],
            [
                'asset_class' => 'Crypto',
                'holdings' => [
                    ['symbol' => 'BTC', 'name' => 'Bitcoin', 'quantity' => 0.02, 'current_price' => 42500.00, 'market_value' => 850.00, 'allocation_pct' => 2.4, 'sector' => 'Digital Assets', 'region' => 'Global'],
                ],
            ],
        ];
    }

    private function getMockAnalytics(): array
    {
        return [
            'total_portfolio_value' => 35014.01,
            'base_currency' => 'USD',
            'risk_score' => 6.5,
            'twr' => 12.45,
            'irr' => 11.82,
            'volatility' => 15.3,
            'asset_allocation' => [
                ['name' => 'Equities', 'value' => 67.9, 'color' => '#3B82F6'],
                ['name' => 'ETFs', 'value' => 19.3, 'color' => '#10B981'],
                ['name' => 'Bonds', 'value' => 10.4, 'color' => '#F59E0B'],
                ['name' => 'Crypto', 'value' => 2.4, 'color' => '#8B5CF6'],
            ],
            'currency_exposure' => [
                ['currency' => 'USD', 'percentage' => 89.0],
                ['currency' => 'ZAR', 'percentage' => 10.0],
                ['currency' => 'EUR', 'percentage' => 1.0],
            ],
            'sector_exposure' => [
                ['sector' => 'Technology', 'percentage' => 67.9],
                ['sector' => 'Diversified', 'percentage' => 19.3],
                ['sector' => 'Fixed Income', 'percentage' => 10.4],
                ['sector' => 'Digital Assets', 'percentage' => 2.4],
            ],
            'geographic_exposure' => [
                ['region' => 'North America', 'percentage' => 78.6],
                ['region' => 'Global', 'percentage' => 11.7],
                ['region' => 'Africa', 'percentage' => 10.0],
            ],
            'performance' => [
                ['period' => '1M', 'return' => 2.3],
                ['period' => '3M', 'return' => 5.8],
                ['period' => 'YTD', 'return' => 12.4],
                ['period' => '1Y', 'return' => 18.2],
            ],
        ];
    }
}
