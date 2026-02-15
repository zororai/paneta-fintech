<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            [
                'name' => 'First National Bank',
                'type' => 'bank',
                'country' => 'ZA',
                'api_endpoint' => 'https://mock.fnb.co.za/api',
                'is_active' => true,
            ],
            [
                'name' => 'Standard Bank',
                'type' => 'bank',
                'country' => 'ZA',
                'api_endpoint' => 'https://mock.standardbank.co.za/api',
                'is_active' => true,
            ],
            [
                'name' => 'Absa Bank',
                'type' => 'bank',
                'country' => 'ZA',
                'api_endpoint' => 'https://mock.absa.co.za/api',
                'is_active' => true,
            ],
            [
                'name' => 'Nedbank',
                'type' => 'bank',
                'country' => 'ZA',
                'api_endpoint' => 'https://mock.nedbank.co.za/api',
                'is_active' => true,
            ],
            [
                'name' => 'PayPal',
                'type' => 'wallet',
                'country' => 'US',
                'api_endpoint' => 'https://mock.paypal.com/api',
                'is_active' => true,
            ],
            [
                'name' => 'XE Currency',
                'type' => 'fx_provider',
                'country' => 'US',
                'api_endpoint' => 'https://mock.xe.com/api',
                'is_active' => true,
            ],
            [
                'name' => 'OFX',
                'type' => 'fx_provider',
                'country' => 'AU',
                'api_endpoint' => 'https://mock.ofx.com/api',
                'is_active' => true,
            ],
            [
                'name' => 'EcoCash',
                'type' => 'wallet',
                'country' => 'ZW',
                'api_endpoint' => 'https://mock.ecocash.co.zw/api',
                'is_active' => true,
            ],
            [
                'name' => 'OneMoney',
                'type' => 'wallet',
                'country' => 'ZW',
                'api_endpoint' => 'https://mock.onemoney.co.zw/api',
                'is_active' => true,
            ],
            [
                'name' => 'InnBucks',
                'type' => 'wallet',
                'country' => 'ZW',
                'api_endpoint' => 'https://mock.innbucks.co.zw/api',
                'is_active' => true,
            ],
            [
                'name' => 'Mukuru',
                'type' => 'remittance',
                'country' => 'ZW',
                'api_endpoint' => 'https://mock.mukuru.com/api',
                'is_active' => true,
            ],
            [
                'name' => 'Western Union',
                'type' => 'remittance',
                'country' => 'US',
                'api_endpoint' => 'https://mock.westernunion.com/api',
                'is_active' => true,
            ],
            [
                'name' => 'CBZ Bank',
                'type' => 'bank',
                'country' => 'ZW',
                'api_endpoint' => 'https://mock.cbz.co.zw/api',
                'is_active' => true,
            ],
            [
                'name' => 'ZB Bank',
                'type' => 'bank',
                'country' => 'ZW',
                'api_endpoint' => 'https://mock.zb.co.zw/api',
                'is_active' => true,
                'capabilities' => ['READ_BALANCE', 'READ_TRANSACTIONS'],
            ],
            // FX Providers
            [
                'name' => 'CurrencyFair',
                'type' => 'fx_provider',
                'country' => 'IE',
                'api_endpoint' => 'https://mock.currencyfair.com/api',
                'is_active' => true,
                'capabilities' => ['READ_BALANCE', 'FX_EXECUTION'],
            ],
            [
                'name' => 'Travelex',
                'type' => 'fx_provider',
                'country' => 'GB',
                'api_endpoint' => 'https://mock.travelex.com/api',
                'is_active' => true,
                'capabilities' => ['FX_EXECUTION'],
            ],
            [
                'name' => 'WorldRemit',
                'type' => 'remittance',
                'country' => 'GB',
                'api_endpoint' => 'https://mock.worldremit.com/api',
                'is_active' => true,
                'capabilities' => ['READ_BALANCE', 'FX_EXECUTION'],
            ],
            // Brokers & Custodians
            [
                'name' => 'Interactive Brokers',
                'type' => 'broker',
                'country' => 'US',
                'api_endpoint' => 'https://mock.interactivebrokers.com/api',
                'is_active' => true,
                'capabilities' => ['WEALTH_READ'],
            ],
            [
                'name' => 'Charles Schwab',
                'type' => 'broker',
                'country' => 'US',
                'api_endpoint' => 'https://mock.schwab.com/api',
                'is_active' => true,
                'capabilities' => ['WEALTH_READ'],
            ],
            [
                'name' => 'Fidelity',
                'type' => 'broker',
                'country' => 'US',
                'api_endpoint' => 'https://mock.fidelity.com/api',
                'is_active' => true,
                'capabilities' => ['WEALTH_READ'],
            ],
            [
                'name' => 'EasyEquities',
                'type' => 'broker',
                'country' => 'ZA',
                'api_endpoint' => 'https://mock.easyequities.co.za/api',
                'is_active' => true,
                'capabilities' => ['WEALTH_READ'],
            ],
            [
                'name' => 'Saxo Bank',
                'type' => 'custodian',
                'country' => 'DK',
                'api_endpoint' => 'https://mock.saxobank.com/api',
                'is_active' => true,
                'capabilities' => ['WEALTH_READ', 'READ_BALANCE'],
            ],
            // Card Issuers
            [
                'name' => 'Visa',
                'type' => 'card',
                'country' => 'US',
                'api_endpoint' => 'https://mock.visa.com/api',
                'is_active' => true,
                'capabilities' => ['READ_TRANSACTIONS'],
            ],
            [
                'name' => 'Mastercard',
                'type' => 'card',
                'country' => 'US',
                'api_endpoint' => 'https://mock.mastercard.com/api',
                'is_active' => true,
                'capabilities' => ['READ_TRANSACTIONS'],
            ],
        ];

        foreach ($institutions as $institution) {
            Institution::updateOrCreate(
                ['name' => $institution['name']],
                $institution
            );
        }
    }
}
