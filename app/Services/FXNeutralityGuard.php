<?php

namespace App\Services;

use App\Models\FxQuote;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * FXNeutralityGuard
 * 
 * Enforces FX neutrality to prevent platform from acting as an FX dealer:
 * - Explicit prevention of internal FX conversion
 * - Block internal netting between users
 * - Prohibit price warehousing
 * - Ensure all FX execution goes through external providers
 */
class FXNeutralityGuard
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Validate that FX execution goes through external provider only
     */
    public function validateExternalExecutionOnly(FxQuote $quote): bool
    {
        if (!$quote->fx_provider_id) {
            $this->logViolation('missing_external_provider', $quote);
            throw new \RuntimeException('FX quote must have an external provider');
        }

        $provider = Institution::find($quote->fx_provider_id);

        if (!$provider || $provider->type !== 'fx_provider') {
            $this->logViolation('invalid_provider_type', $quote, [
                'provider_id' => $quote->fx_provider_id,
                'provider_type' => $provider?->type,
            ]);
            throw new \RuntimeException('FX execution must go through a registered FX provider');
        }

        if ($this->isInternalProvider($provider)) {
            $this->logViolation('internal_provider_detected', $quote, [
                'provider_id' => $provider->id,
            ]);
            throw new \RuntimeException('Internal FX conversion is prohibited');
        }

        return true;
    }

    /**
     * Reject any attempt at internal conversion
     */
    public function rejectInternalConversionAttempt(
        User $user,
        string $sourceCurrency,
        string $destinationCurrency,
        float $amount
    ): void {
        $this->auditService->log(
            'fx_internal_conversion_rejected',
            'fx_neutrality',
            null,
            $user,
            [
                'source_currency' => $sourceCurrency,
                'destination_currency' => $destinationCurrency,
                'amount' => $amount,
                'reason' => 'Internal FX conversion is prohibited - must use external provider',
            ]
        );

        throw new \RuntimeException(
            'Direct currency conversion is not available. Please request a quote from an external FX provider.'
        );
    }

    /**
     * Enforce that settlement goes through external provider
     */
    public function enforceProviderSettlementPath(FxQuote $quote): bool
    {
        if (!$quote->institution_id) {
            $this->logViolation('no_settlement_institution', $quote);
            throw new \RuntimeException('FX quote must specify settlement institution');
        }

        $provider = Institution::find($quote->institution_id);

        if (!$provider) {
            $this->logViolation('settlement_institution_not_found', $quote);
            throw new \RuntimeException('Settlement institution not found');
        }

        if (!$this->hasExternalSettlementCapability($provider)) {
            $this->logViolation('no_external_settlement', $quote, [
                'institution_id' => $provider->id,
            ]);
            throw new \RuntimeException('Institution does not support external settlement');
        }

        return true;
    }

    /**
     * Validate quote is from external source
     */
    public function validateExternalQuoteSource(array $quoteData): bool
    {
        $requiredFields = ['provider_id', 'rate', 'expires_at'];

        foreach ($requiredFields as $field) {
            if (!isset($quoteData[$field])) {
                throw new \RuntimeException("Quote missing required external field: {$field}");
            }
        }

        if (isset($quoteData['is_internal']) && $quoteData['is_internal'] === true) {
            throw new \RuntimeException('Internal quotes are not permitted');
        }

        return true;
    }

    /**
     * Block internal netting between users
     */
    public function blockInternalNetting(User $userA, User $userB, string $currency, float $amount): void
    {
        $this->auditService->log(
            'fx_internal_netting_blocked',
            'fx_neutrality',
            null,
            $userA,
            [
                'user_a_id' => $userA->id,
                'user_b_id' => $userB->id,
                'currency' => $currency,
                'amount' => $amount,
                'reason' => 'Internal netting between users is prohibited',
            ]
        );

        throw new \RuntimeException(
            'Internal netting between users is not permitted. All FX transactions must go through external providers.'
        );
    }

    /**
     * Prevent price warehousing
     */
    public function preventPriceWarehousing(FxQuote $quote): bool
    {
        if ($quote->expires_at && $quote->expires_at->isPast()) {
            $this->logViolation('expired_quote_execution_attempt', $quote);
            throw new \RuntimeException('Cannot execute expired quote - price warehousing is prohibited');
        }

        $maxQuoteLifetimeMinutes = 10;
        $quoteAge = $quote->created_at->diffInMinutes(now());

        if ($quoteAge > $maxQuoteLifetimeMinutes) {
            $this->logViolation('quote_too_old', $quote, [
                'quote_age_minutes' => $quoteAge,
                'max_lifetime_minutes' => $maxQuoteLifetimeMinutes,
            ]);
            throw new \RuntimeException('Quote has exceeded maximum lifetime - please request a new quote');
        }

        return true;
    }

    /**
     * Validate no spread manipulation
     */
    public function validateNoSpreadManipulation(FxQuote $quote, float $marketRate): bool
    {
        $maxSpreadPercentage = 5.0;
        $actualSpread = abs(($quote->rate - $marketRate) / $marketRate) * 100;

        if ($actualSpread > $maxSpreadPercentage) {
            $this->logViolation('excessive_spread_detected', $quote, [
                'quote_rate' => $quote->rate,
                'market_rate' => $marketRate,
                'spread_percentage' => $actualSpread,
                'max_spread' => $maxSpreadPercentage,
            ]);

            Log::warning('Excessive FX spread detected', [
                'quote_id' => $quote->id,
                'spread' => $actualSpread,
            ]);
        }

        return true;
    }

    /**
     * Check if provider is internal (platform-owned)
     */
    private function isInternalProvider(Institution $provider): bool
    {
        $internalMarkers = ['paneta', 'internal', 'platform'];

        foreach ($internalMarkers as $marker) {
            if (stripos($provider->name, $marker) !== false) {
                return true;
            }
        }

        return $provider->metadata['is_internal'] ?? false;
    }

    /**
     * Check if institution supports external settlement
     */
    private function hasExternalSettlementCapability(Institution $provider): bool
    {
        $supportedTypes = ['fx_provider', 'bank', 'payment_provider', 'remittance'];
        return in_array($provider->type, $supportedTypes);
    }

    /**
     * Log FX neutrality violation
     */
    private function logViolation(string $violationType, FxQuote $quote, array $additionalData = []): void
    {
        $this->auditService->log(
            'fx_neutrality_violation',
            'fx_quote',
            $quote->id,
            null,
            array_merge([
                'violation_type' => $violationType,
                'quote_id' => $quote->id,
                'source_currency' => $quote->source_currency,
                'destination_currency' => $quote->destination_currency,
            ], $additionalData)
        );

        Log::warning('FX Neutrality violation detected', [
            'violation_type' => $violationType,
            'quote_id' => $quote->id,
        ]);
    }
}
