<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LinkedAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WealthController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $linkedInstitutions = $this->getLinkedInstitutions($user);
        $mockHoldings = $this->getMockHoldings();
        $mockAnalytics = $this->getMockAnalytics();

        return Inertia::render('Paneta/Wealth', [
            'linkedInstitutions' => $linkedInstitutions,
            'holdings' => $mockHoldings,
            'analytics' => $mockAnalytics,
            'isReadOnly' => true,
        ]);
    }

    private function getLinkedInstitutions($user): array
    {
        $accounts = $user->linkedAccounts()
            ->with('institution')
            ->where('status', 'active')
            ->get()
            ->groupBy('institution_id');

        $institutions = [];
        foreach ($accounts as $institutionId => $accountGroup) {
            $firstAccount = $accountGroup->first();
            $institution = $firstAccount->institution;
            
            $status = 'connected';
            if ($firstAccount->consent_expires_at && $firstAccount->consent_expires_at->isPast()) {
                $status = 'expired';
            }

            $institutions[] = [
                'id' => $institutionId,
                'name' => $institution->name ?? 'Unknown',
                'type' => $institution->type ?? 'bank',
                'status' => $status,
                'last_synced' => $firstAccount->updated_at?->diffForHumans() ?? 'Never',
                'account_count' => $accountGroup->count(),
                'institution' => [
                    'name' => $institution->name ?? 'Unknown Institution',
                    'logo_url' => $institution->logo_url ?? null,
                ],
            ];
        }

        if (empty($institutions)) {
            $institutions = $this->getMockLinkedInstitutions();
        }

        return $institutions;
    }

    private function getMockLinkedInstitutions(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Bank of X',
                'type' => 'bank',
                'status' => 'connected',
                'last_synced' => '2 hours ago',
                'account_count' => 2,
                'institution' => ['name' => 'Bank of X', 'logo_url' => null],
            ],
            [
                'id' => 2,
                'name' => 'ABC Broker',
                'type' => 'broker',
                'status' => 'connected',
                'last_synced' => '1 hour ago',
                'account_count' => 1,
                'institution' => ['name' => 'ABC Broker', 'logo_url' => null],
            ],
            [
                'id' => 3,
                'name' => 'Global Fund Admin',
                'type' => 'custodian',
                'status' => 'connected',
                'last_synced' => '30 minutes ago',
                'account_count' => 3,
                'institution' => ['name' => 'Global Fund Admin', 'logo_url' => null],
            ],
            [
                'id' => 4,
                'name' => 'Crypto Custodian',
                'type' => 'crypto',
                'status' => 'expired',
                'last_synced' => '3 days ago',
                'account_count' => 1,
                'institution' => ['name' => 'Crypto Custodian', 'logo_url' => null],
            ],
        ];
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
