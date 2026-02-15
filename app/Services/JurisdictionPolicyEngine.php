<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class JurisdictionPolicyEngine
{
    protected array $jurisdictionPolicies = [
        'US' => [
            'name' => 'United States',
            'currency' => 'USD',
            'kyc_required' => true,
            'kyc_threshold' => 0,
            'reporting_threshold' => 10000,
            'ctr_required' => true, // Currency Transaction Report
            'sar_required' => true, // Suspicious Activity Report
            'pep_screening' => true,
            'sanctions_screening' => true,
            'tax_reporting' => true,
            'max_daily_limit' => 50000,
            'max_single_transaction' => 25000,
            'enhanced_due_diligence_threshold' => 10000,
            'restricted_countries' => ['KP', 'IR', 'SY', 'CU'],
        ],
        'GB' => [
            'name' => 'United Kingdom',
            'currency' => 'GBP',
            'kyc_required' => true,
            'kyc_threshold' => 0,
            'reporting_threshold' => 10000,
            'ctr_required' => false,
            'sar_required' => true,
            'pep_screening' => true,
            'sanctions_screening' => true,
            'tax_reporting' => true,
            'max_daily_limit' => 40000,
            'max_single_transaction' => 20000,
            'enhanced_due_diligence_threshold' => 15000,
            'restricted_countries' => ['KP', 'IR', 'SY', 'RU', 'BY'],
        ],
        'ZA' => [
            'name' => 'South Africa',
            'currency' => 'ZAR',
            'kyc_required' => true,
            'kyc_threshold' => 0,
            'reporting_threshold' => 25000, // ZAR
            'ctr_required' => true,
            'sar_required' => true,
            'pep_screening' => true,
            'sanctions_screening' => true,
            'tax_reporting' => true,
            'max_daily_limit' => 500000, // ZAR
            'max_single_transaction' => 100000,
            'enhanced_due_diligence_threshold' => 50000,
            'restricted_countries' => ['KP', 'IR', 'SY'],
            'exchange_control' => true,
            'sarb_approval_threshold' => 1000000, // SARB approval for large transfers
        ],
        'EU' => [
            'name' => 'European Union',
            'currency' => 'EUR',
            'kyc_required' => true,
            'kyc_threshold' => 0,
            'reporting_threshold' => 15000,
            'ctr_required' => false,
            'sar_required' => true,
            'pep_screening' => true,
            'sanctions_screening' => true,
            'tax_reporting' => true,
            'max_daily_limit' => 50000,
            'max_single_transaction' => 15000,
            'enhanced_due_diligence_threshold' => 15000,
            'restricted_countries' => ['KP', 'IR', 'SY', 'RU', 'BY'],
        ],
    ];

    public function getPolicy(string $jurisdictionCode): ?array
    {
        return $this->jurisdictionPolicies[strtoupper($jurisdictionCode)] ?? null;
    }

    public function validateTransaction(
        string $jurisdiction,
        float $amount,
        string $currency,
        User $user,
        string $destinationCountry = null
    ): array {
        $policy = $this->getPolicy($jurisdiction);
        
        if (!$policy) {
            return [
                'allowed' => true,
                'warnings' => ['Unknown jurisdiction, default policies applied'],
                'requirements' => [],
            ];
        }

        $violations = [];
        $warnings = [];
        $requirements = [];

        // Check KYC requirement
        if ($policy['kyc_required'] && $user->kyc_status !== 'verified') {
            $violations[] = [
                'code' => 'KYC_REQUIRED',
                'message' => 'KYC verification required for transactions in ' . $policy['name'],
            ];
        }

        // Check transaction limits
        if ($amount > $policy['max_single_transaction']) {
            $violations[] = [
                'code' => 'SINGLE_LIMIT_EXCEEDED',
                'message' => "Transaction exceeds single transaction limit of {$policy['max_single_transaction']} {$policy['currency']}",
            ];
        }

        // Check reporting threshold
        if ($amount >= $policy['reporting_threshold']) {
            $requirements[] = [
                'code' => 'REPORTING_REQUIRED',
                'message' => 'Transaction meets reporting threshold',
                'report_type' => $policy['ctr_required'] ? 'CTR' : 'Internal',
            ];
        }

        // Check EDD threshold
        if ($amount >= $policy['enhanced_due_diligence_threshold']) {
            $requirements[] = [
                'code' => 'EDD_REQUIRED',
                'message' => 'Enhanced due diligence required',
            ];
        }

        // Check restricted countries
        if ($destinationCountry && in_array($destinationCountry, $policy['restricted_countries'] ?? [])) {
            $violations[] = [
                'code' => 'RESTRICTED_DESTINATION',
                'message' => "Transfers to {$destinationCountry} are restricted",
            ];
        }

        // South Africa specific - Exchange control
        if ($jurisdiction === 'ZA' && isset($policy['sarb_approval_threshold'])) {
            if ($amount >= $policy['sarb_approval_threshold']) {
                $requirements[] = [
                    'code' => 'SARB_APPROVAL',
                    'message' => 'SARB approval required for amounts exceeding R1,000,000',
                ];
            }
        }

        return [
            'allowed' => empty($violations),
            'violations' => $violations,
            'warnings' => $warnings,
            'requirements' => $requirements,
            'policy' => [
                'jurisdiction' => $jurisdiction,
                'name' => $policy['name'],
            ],
        ];
    }

    public function requiresSanctionsScreening(string $jurisdiction): bool
    {
        $policy = $this->getPolicy($jurisdiction);
        return $policy['sanctions_screening'] ?? true;
    }

    public function requiresPepScreening(string $jurisdiction): bool
    {
        $policy = $this->getPolicy($jurisdiction);
        return $policy['pep_screening'] ?? true;
    }

    public function getReportingThreshold(string $jurisdiction): float
    {
        $policy = $this->getPolicy($jurisdiction);
        return $policy['reporting_threshold'] ?? 10000;
    }

    public function getTransactionLimits(string $jurisdiction): array
    {
        $policy = $this->getPolicy($jurisdiction);
        
        return [
            'max_single' => $policy['max_single_transaction'] ?? 25000,
            'max_daily' => $policy['max_daily_limit'] ?? 50000,
            'currency' => $policy['currency'] ?? 'USD',
        ];
    }

    public function isCountryRestricted(string $jurisdiction, string $targetCountry): bool
    {
        $policy = $this->getPolicy($jurisdiction);
        return in_array($targetCountry, $policy['restricted_countries'] ?? []);
    }

    public function getRestrictedCountries(string $jurisdiction): array
    {
        $policy = $this->getPolicy($jurisdiction);
        return $policy['restricted_countries'] ?? [];
    }

    public function getAllJurisdictions(): array
    {
        return array_map(function ($code, $policy) {
            return [
                'code' => $code,
                'name' => $policy['name'],
                'currency' => $policy['currency'],
            ];
        }, array_keys($this->jurisdictionPolicies), $this->jurisdictionPolicies);
    }

    public function determineUserJurisdiction(User $user): string
    {
        // Logic to determine user's primary jurisdiction based on their profile
        // This could use address, phone number, or primary account location
        
        // For MVP, default to US
        return $user->country ?? 'US';
    }
}
