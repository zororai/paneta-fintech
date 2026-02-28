<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ServiceProvider::create([
            'provider_type' => 'fx_provider',
            'company_name' => 'Global FX Solutions Ltd',
            'trading_name' => 'GlobalFX',
            'registration_number' => 'FX-2024-001',
            'tax_id' => 'TAX123456789',
            'email' => 'provider@globalfx.com',
            'password' => bcrypt('password'),
            'phone' => '+1-555-0100',
            'country' => 'United States',
            'address' => '123 Wall Street',
            'city' => 'New York',
            'postal_code' => '10005',
            'regulatory_body' => 'SEC',
            'license_number' => 'SEC-FX-2024-001',
            'license_expiry' => '2025-12-31',
            'verification_status' => 'verified',
            'verified_at' => now(),
            'business_description' => 'Leading foreign exchange provider offering competitive rates and fast settlements.',
            'services_offered' => ['currency_exchange', 'fx_hedging', 'international_transfers'],
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'JPY', 'ZWL', 'ZAR'],
            'supported_countries' => ['United States', 'United Kingdom', 'Zimbabwe', 'South Africa'],
            'minimum_transaction' => 100.00,
            'maximum_transaction' => 1000000.00,
            'commission_rate' => 0.50,
            'contact_person_name' => 'John Smith',
            'contact_person_email' => 'john.smith@globalfx.com',
            'contact_person_phone' => '+1-555-0101',
            'contact_person_position' => 'Chief Operations Officer',
            'bank_name' => 'Chase Bank',
            'bank_account_number' => '1234567890',
            'bank_swift_code' => 'CHASUS33',
            'is_active' => true,
            'can_create_offers' => true,
            'can_execute_trades' => true,
            'rating' => 5,
            'email_verified_at' => now(),
        ]);

        \App\Models\ServiceProvider::create([
            'provider_type' => 'fx_provider',
            'company_name' => 'Zimbabwe Currency Exchange',
            'trading_name' => 'ZimFX',
            'registration_number' => 'ZW-FX-2024-002',
            'email' => 'provider@zimfx.co.zw',
            'password' => bcrypt('password'),
            'phone' => '+263-4-123456',
            'country' => 'Zimbabwe',
            'address' => '45 Samora Machel Avenue',
            'city' => 'Harare',
            'postal_code' => '00263',
            'regulatory_body' => 'Reserve Bank of Zimbabwe',
            'license_number' => 'RBZ-FX-2024-002',
            'license_expiry' => '2025-12-31',
            'verification_status' => 'verified',
            'verified_at' => now(),
            'business_description' => 'Premier Zimbabwe-based FX provider specializing in ZWL and USD exchanges.',
            'services_offered' => ['currency_exchange', 'remittances'],
            'supported_currencies' => ['ZWL', 'USD', 'ZAR', 'GBP'],
            'supported_countries' => ['Zimbabwe', 'South Africa'],
            'minimum_transaction' => 50.00,
            'maximum_transaction' => 500000.00,
            'commission_rate' => 0.75,
            'contact_person_name' => 'Tendai Moyo',
            'contact_person_email' => 'tendai@zimfx.co.zw',
            'contact_person_phone' => '+263-77-1234567',
            'contact_person_position' => 'Managing Director',
            'bank_name' => 'CBZ Bank',
            'bank_account_number' => 'ZW9876543210',
            'is_active' => true,
            'can_create_offers' => true,
            'can_execute_trades' => true,
            'rating' => 4,
            'email_verified_at' => now(),
        ]);
    }
}
