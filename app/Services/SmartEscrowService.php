<?php

namespace App\Services;

use App\Models\User;
use App\Models\LinkedAccount;
use App\Models\P2PExchangeRequest;
use Illuminate\Support\Facades\Log;

class SmartEscrowService
{
    const PANETA_FEE_PERCENTAGE = 0.99;

    /**
     * Run all precondition checks for both users
     */
    public function runAllPreconditions(P2PExchangeRequest $request): array
    {
        $checks = [
            'balance_check' => false,
            'aml_check' => false,
            'sanctions_check' => false,
            'behavioral_check' => false,
            'jurisdiction_check' => false,
        ];

        try {
            // Get offer details
            $offer = $request->offer()->with(['user', 'sourceAccount', 'destinationAccount'])->first();
            
            // Balance sufficiency check
            $checks['balance_check'] = $this->checkBalanceSufficiency($request, $offer);
            
            // AML check
            $checks['aml_check'] = $this->performAMLCheck($request, $offer);
            
            // Sanctions check
            $checks['sanctions_check'] = $this->performSanctionsCheck($request, $offer);
            
            // Behavioral rules check
            $checks['behavioral_check'] = $this->checkBehavioralRules($request, $offer);
            
            // Jurisdiction FX rules check
            $checks['jurisdiction_check'] = $this->checkJurisdictionRules($request, $offer);
            
            $allPassed = !in_array(false, $checks, true);
            
            return [
                'passed' => $allPassed,
                'checks' => $checks,
                'failure_reason' => $allPassed ? null : $this->getFailureReason($checks),
            ];
            
        } catch (\Exception $e) {
            Log::error('Smart Escrow Preconditions Failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'passed' => false,
                'checks' => $checks,
                'failure_reason' => 'System error during precondition checks',
            ];
        }
    }

    /**
     * Check if both users have sufficient balance
     */
    private function checkBalanceSufficiency(P2PExchangeRequest $request, $offer): bool
    {
        // Calculate fees
        $initiatorFee = $this->calculateFee($request->sell_amount);
        $counterpartyFee = $this->calculateFee($request->buy_amount);
        
        // Get initiator's source account
        $initiatorSourceAccount = $offer->sourceAccount;
        $initiatorRequiredBalance = $request->sell_amount + $initiatorFee;
        
        // Get counterparty's source account
        $counterpartySourceAccount = LinkedAccount::find($request->cp_source_account_id);
        $counterpartyRequiredBalance = $request->buy_amount + $counterpartyFee;
        
        // Check balances
        $initiatorHasSufficientBalance = $initiatorSourceAccount->mock_balance >= $initiatorRequiredBalance;
        $counterpartyHasSufficientBalance = $counterpartySourceAccount->mock_balance >= $counterpartyRequiredBalance;
        
        return $initiatorHasSufficientBalance && $counterpartyHasSufficientBalance;
    }

    /**
     * Perform AML checks
     */
    private function performAMLCheck(P2PExchangeRequest $request, $offer): bool
    {
        $initiator = $offer->user;
        $counterparty = User::find($request->counterparty_user_id);
        
        // Check transaction limits (example: max $50,000 per transaction)
        if ($request->sell_amount > 50000 || $request->buy_amount > 50000) {
            return false;
        }
        
        // Check user verification status
        if (!$initiator->email_verified_at || !$counterparty->email_verified_at) {
            return false;
        }
        
        // Velocity check - simplified (in production, check daily/weekly limits)
        // For now, we'll pass this check
        
        return true;
    }

    /**
     * Perform sanctions checks
     */
    private function performSanctionsCheck(P2PExchangeRequest $request, $offer): bool
    {
        $initiator = $offer->user;
        $counterparty = User::find($request->counterparty_user_id);
        
        // Check if users are suspended
        if ($initiator->is_suspended || $counterparty->is_suspended) {
            return false;
        }
        
        // In production, check against sanctions lists (OFAC, UN, EU, etc.)
        // For now, simplified check based on user status
        
        // Check if accounts are active
        $initiatorAccount = $offer->sourceAccount;
        $counterpartyAccount = LinkedAccount::find($request->cp_source_account_id);
        
        if ($initiatorAccount->status !== 'active' || $counterpartyAccount->status !== 'active') {
            return false;
        }
        
        return true;
    }

    /**
     * Check behavioral rules
     */
    private function checkBehavioralRules(P2PExchangeRequest $request, $offer): bool
    {
        $initiator = $offer->user;
        $counterparty = User::find($request->counterparty_user_id);
        
        // Check account age (minimum 7 days for P2P exchange)
        $minAccountAge = now()->subDays(7);
        if ($initiator->created_at > $minAccountAge || $counterparty->created_at > $minAccountAge) {
            return false;
        }
        
        // In production, check:
        // - Trust score
        // - Previous transaction success rate
        // - Dispute history
        // - User ratings
        
        return true;
    }

    /**
     * Check jurisdiction FX rules
     */
    private function checkJurisdictionRules(P2PExchangeRequest $request, $offer): bool
    {
        // Get account countries
        $initiatorAccount = $offer->sourceAccount;
        $counterpartyAccount = LinkedAccount::find($request->cp_source_account_id);
        
        $initiatorCountry = $initiatorAccount->country;
        $counterpartyCountry = $counterpartyAccount->country;
        
        // Check if currency pair is allowed
        $currencyPair = $request->sell_currency . '/' . $request->buy_currency;
        
        // In production, check:
        // - Currency controls for specific countries
        // - Cross-border restrictions
        // - Regulatory compliance requirements
        // - Sanctioned currency pairs
        
        // Simplified check: ensure countries are set
        if (empty($initiatorCountry) || empty($counterpartyCountry)) {
            return false;
        }
        
        return true;
    }

    /**
     * Calculate PANÉTA fee
     */
    public function calculateFee(float $amount): float
    {
        return round($amount * (self::PANETA_FEE_PERCENTAGE / 100), 2);
    }

    /**
     * Get failure reason from checks
     */
    private function getFailureReason(array $checks): string
    {
        $failures = [];
        
        if (!$checks['balance_check']) {
            $failures[] = 'Insufficient balance';
        }
        if (!$checks['aml_check']) {
            $failures[] = 'AML check failed';
        }
        if (!$checks['sanctions_check']) {
            $failures[] = 'Sanctions check failed';
        }
        if (!$checks['behavioral_check']) {
            $failures[] = 'Behavioral rules not met';
        }
        if (!$checks['jurisdiction_check']) {
            $failures[] = 'Jurisdiction FX rules not met';
        }
        
        return implode(', ', $failures);
    }
}
