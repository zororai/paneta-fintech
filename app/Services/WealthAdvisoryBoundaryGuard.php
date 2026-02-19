<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * WealthAdvisoryBoundaryGuard
 * 
 * Enforces no-advisory boundaries for wealth management:
 * - AI insight framing validation
 * - Prevent directive language
 * - Log insight generation
 * - Enforce no execution path from insights
 */
class WealthAdvisoryBoundaryGuard
{
    private const PROHIBITED_PHRASES = [
        'you should',
        'you must',
        'we recommend',
        'we advise',
        'buy this',
        'sell this',
        'invest in',
        'guaranteed returns',
        'risk-free',
        'certain profit',
        'will increase',
        'will decrease',
        'definitely',
        'certainly',
    ];

    private const REQUIRED_DISCLAIMERS = [
        'informational_only' => 'This information is provided for informational purposes only and does not constitute financial advice.',
        'not_recommendation' => 'This is not a recommendation to buy, sell, or hold any security.',
        'seek_professional' => 'Please consult a qualified financial advisor before making investment decisions.',
        'past_performance' => 'Past performance is not indicative of future results.',
    ];

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Validate that insight language is non-directive
     */
    public function validateNonDirectiveLanguage(string $insightText): array
    {
        $violations = [];
        $lowercaseText = strtolower($insightText);

        foreach (self::PROHIBITED_PHRASES as $phrase) {
            if (str_contains($lowercaseText, $phrase)) {
                $violations[] = [
                    'phrase' => $phrase,
                    'type' => 'prohibited_directive_language',
                ];
            }
        }

        $isValid = empty($violations);

        if (!$isValid) {
            $this->auditService->log(
                'advisory_boundary_violation',
                'wealth_insight',
                null,
                null,
                [
                    'violations' => $violations,
                    'insight_preview' => substr($insightText, 0, 100) . '...',
                ]
            );

            Log::warning('Advisory boundary violation detected', [
                'violation_count' => count($violations),
            ]);
        }

        return [
            'valid' => $isValid,
            'violations' => $violations,
            'sanitized_text' => $isValid ? $insightText : $this->sanitizeInsightText($insightText),
        ];
    }

    /**
     * Log insight generation for compliance
     */
    public function logInsightGeneration(
        User $user,
        string $insightType,
        array $insightData,
        string $source
    ): void {
        $this->auditService->log(
            'wealth_insight_generated',
            'wealth_insight',
            null,
            $user,
            [
                'insight_type' => $insightType,
                'source' => $source,
                'data_points_used' => array_keys($insightData),
                'generated_at' => now()->toIso8601String(),
                'disclaimer_attached' => true,
            ]
        );
    }

    /**
     * Enforce no execution path from insights
     */
    public function enforceNoExecutionPath(string $insightId, string $attemptedAction): void
    {
        $this->auditService->log(
            'execution_from_insight_blocked',
            'wealth_insight',
            $insightId,
            null,
            [
                'attempted_action' => $attemptedAction,
                'blocked_at' => now()->toIso8601String(),
                'reason' => 'Direct execution from insights is prohibited to maintain advisory boundary',
            ]
        );

        throw new \RuntimeException(
            'Direct trading or investment actions cannot be initiated from insights. ' .
            'Please use the appropriate trading interface if you wish to act on this information.'
        );
    }

    /**
     * Get required disclaimers for insight type
     */
    public function getRequiredDisclaimers(string $insightType): array
    {
        $disclaimerMapping = [
            'portfolio_analysis' => ['informational_only', 'not_recommendation', 'past_performance'],
            'performance_metrics' => ['informational_only', 'past_performance'],
            'risk_assessment' => ['informational_only', 'not_recommendation', 'seek_professional'],
            'allocation_view' => ['informational_only', 'not_recommendation'],
            'market_data' => ['informational_only', 'past_performance'],
            'default' => ['informational_only', 'not_recommendation', 'seek_professional'],
        ];

        $requiredKeys = $disclaimerMapping[$insightType] ?? $disclaimerMapping['default'];

        return array_map(
            fn ($key) => self::REQUIRED_DISCLAIMERS[$key],
            $requiredKeys
        );
    }

    /**
     * Wrap insight with appropriate disclaimers
     */
    public function wrapWithDisclaimers(string $insightText, string $insightType): array
    {
        $validation = $this->validateNonDirectiveLanguage($insightText);
        $disclaimers = $this->getRequiredDisclaimers($insightType);

        return [
            'insight' => $validation['valid'] ? $insightText : $validation['sanitized_text'],
            'disclaimers' => $disclaimers,
            'is_advisory' => false,
            'is_informational_only' => true,
            'generated_at' => now()->toIso8601String(),
            'validation_passed' => $validation['valid'],
        ];
    }

    /**
     * Validate insight request is within boundaries
     */
    public function validateInsightRequest(User $user, string $requestType, array $parameters): bool
    {
        $allowedRequestTypes = [
            'portfolio_summary',
            'asset_allocation',
            'performance_history',
            'risk_metrics',
            'currency_exposure',
            'sector_breakdown',
            'geographic_distribution',
        ];

        if (!in_array($requestType, $allowedRequestTypes)) {
            $this->auditService->log(
                'invalid_insight_request',
                'wealth_insight',
                null,
                $user,
                [
                    'request_type' => $requestType,
                    'reason' => 'Request type not in allowed list',
                ]
            );

            return false;
        }

        $prohibitedParameters = ['recommendation', 'advice', 'action', 'trade', 'buy', 'sell'];
        foreach ($prohibitedParameters as $prohibited) {
            if (isset($parameters[$prohibited])) {
                $this->auditService->log(
                    'prohibited_parameter_in_request',
                    'wealth_insight',
                    null,
                    $user,
                    [
                        'prohibited_parameter' => $prohibited,
                    ]
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize insight text to remove directive language
     */
    private function sanitizeInsightText(string $text): string
    {
        $replacements = [
            'you should' => 'you may consider',
            'you must' => 'you might',
            'we recommend' => 'for your information',
            'we advise' => 'you may want to review',
            'buy this' => 'this asset',
            'sell this' => 'this position',
            'invest in' => 'consider researching',
            'guaranteed returns' => 'historical returns',
            'risk-free' => 'lower-risk',
            'certain profit' => 'potential return',
            'will increase' => 'may increase',
            'will decrease' => 'may decrease',
            'definitely' => 'potentially',
            'certainly' => 'possibly',
        ];

        $sanitized = $text;
        foreach ($replacements as $find => $replace) {
            $sanitized = str_ireplace($find, $replace, $sanitized);
        }

        return $sanitized;
    }
}
