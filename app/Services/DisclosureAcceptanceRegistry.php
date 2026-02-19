<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DisclosureAcceptanceRegistry
 * 
 * Tracks user acceptance of required disclaimers:
 * - Record disclosure acceptance
 * - Verify acceptance before access
 * - Compliance reporting
 */
class DisclosureAcceptanceRegistry
{
    private const DISCLOSURE_TYPES = [
        'wealth_non_advisory' => [
            'title' => 'Wealth Management Non-Advisory Disclosure',
            'version' => '1.0',
            'required_for' => ['wealth_dashboard', 'portfolio_view'],
        ],
        'fx_risk' => [
            'title' => 'Foreign Exchange Risk Disclosure',
            'version' => '1.0',
            'required_for' => ['currency_exchange', 'fx_quote'],
        ],
        'terms_of_service' => [
            'title' => 'Terms of Service',
            'version' => '2.0',
            'required_for' => ['account_creation', 'login'],
        ],
        'privacy_policy' => [
            'title' => 'Privacy Policy',
            'version' => '1.5',
            'required_for' => ['account_creation', 'data_export'],
        ],
        'transaction_authorization' => [
            'title' => 'Transaction Authorization Disclosure',
            'version' => '1.0',
            'required_for' => ['send_money', 'payment_request'],
        ],
    ];

    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Record user's acceptance of a disclosure
     */
    public function recordDisclosureAcceptance(
        User $user,
        string $disclosureType,
        array $metadata = []
    ): array {
        if (!isset(self::DISCLOSURE_TYPES[$disclosureType])) {
            throw new \RuntimeException("Unknown disclosure type: {$disclosureType}");
        }

        $disclosure = self::DISCLOSURE_TYPES[$disclosureType];
        $acceptanceId = $this->generateAcceptanceId();

        $acceptance = [
            'id' => $acceptanceId,
            'user_id' => $user->id,
            'disclosure_type' => $disclosureType,
            'disclosure_title' => $disclosure['title'],
            'disclosure_version' => $disclosure['version'],
            'accepted_at' => now()->toIso8601String(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ];

        $this->storeAcceptance($user, $disclosureType, $acceptance);

        $this->auditService->log(
            'disclosure_accepted',
            'disclosure',
            $acceptanceId,
            $user,
            [
                'disclosure_type' => $disclosureType,
                'version' => $disclosure['version'],
            ]
        );

        return $acceptance;
    }

    /**
     * Verify user has accepted required disclosure before access
     */
    public function verifyAcceptanceBeforeAccess(
        User $user,
        string $feature
    ): array {
        $requiredDisclosures = $this->getRequiredDisclosures($feature);
        $missingAcceptances = [];

        foreach ($requiredDisclosures as $disclosureType) {
            if (!$this->hasValidAcceptance($user, $disclosureType)) {
                $missingAcceptances[] = [
                    'type' => $disclosureType,
                    'title' => self::DISCLOSURE_TYPES[$disclosureType]['title'],
                    'version' => self::DISCLOSURE_TYPES[$disclosureType]['version'],
                ];
            }
        }

        $accessGranted = empty($missingAcceptances);

        if (!$accessGranted) {
            $this->auditService->log(
                'access_blocked_missing_disclosure',
                'disclosure',
                null,
                $user,
                [
                    'feature' => $feature,
                    'missing_disclosures' => $missingAcceptances,
                ]
            );
        }

        return [
            'access_granted' => $accessGranted,
            'feature' => $feature,
            'missing_acceptances' => $missingAcceptances,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if user has valid acceptance for disclosure
     */
    public function hasValidAcceptance(User $user, string $disclosureType): bool
    {
        $acceptance = $this->getAcceptance($user, $disclosureType);

        if (!$acceptance) {
            return false;
        }

        $currentVersion = self::DISCLOSURE_TYPES[$disclosureType]['version'] ?? '1.0';
        return $acceptance['disclosure_version'] === $currentVersion;
    }

    /**
     * Get user's acceptance record
     */
    public function getAcceptance(User $user, string $disclosureType): ?array
    {
        $cacheKey = "disclosure_acceptance:{$user->id}:{$disclosureType}";
        return Cache::get($cacheKey);
    }

    /**
     * Get all acceptances for user
     */
    public function getAllAcceptances(User $user): array
    {
        $acceptances = [];

        foreach (array_keys(self::DISCLOSURE_TYPES) as $type) {
            $acceptance = $this->getAcceptance($user, $type);
            if ($acceptance) {
                $acceptances[$type] = $acceptance;
            }
        }

        return $acceptances;
    }

    /**
     * Get required disclosures for a feature
     */
    public function getRequiredDisclosures(string $feature): array
    {
        $required = [];

        foreach (self::DISCLOSURE_TYPES as $type => $config) {
            if (in_array($feature, $config['required_for'])) {
                $required[] = $type;
            }
        }

        return $required;
    }

    /**
     * Get disclosure content
     */
    public function getDisclosureContent(string $disclosureType): array
    {
        if (!isset(self::DISCLOSURE_TYPES[$disclosureType])) {
            throw new \RuntimeException("Unknown disclosure type: {$disclosureType}");
        }

        $disclosure = self::DISCLOSURE_TYPES[$disclosureType];

        return [
            'type' => $disclosureType,
            'title' => $disclosure['title'],
            'version' => $disclosure['version'],
            'content' => $this->getDisclosureText($disclosureType),
            'required_for' => $disclosure['required_for'],
        ];
    }

    /**
     * Revoke acceptance (e.g., for re-consent)
     */
    public function revokeAcceptance(User $user, string $disclosureType): void
    {
        $cacheKey = "disclosure_acceptance:{$user->id}:{$disclosureType}";
        Cache::forget($cacheKey);

        $this->auditService->log(
            'disclosure_acceptance_revoked',
            'disclosure',
            null,
            $user,
            [
                'disclosure_type' => $disclosureType,
                'revoked_at' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * Get compliance report
     */
    public function getComplianceReport(): array
    {
        $report = [];

        foreach (self::DISCLOSURE_TYPES as $type => $config) {
            $report[$type] = [
                'title' => $config['title'],
                'current_version' => $config['version'],
                'required_for' => $config['required_for'],
            ];
        }

        return [
            'disclosures' => $report,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Store acceptance in cache/database
     */
    private function storeAcceptance(User $user, string $disclosureType, array $acceptance): void
    {
        $cacheKey = "disclosure_acceptance:{$user->id}:{$disclosureType}";
        Cache::put($cacheKey, $acceptance, now()->addYears(2));
    }

    /**
     * Generate acceptance ID
     */
    private function generateAcceptanceId(): string
    {
        return 'ACC-' . now()->format('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Get disclosure text content
     */
    private function getDisclosureText(string $disclosureType): string
    {
        $texts = [
            'wealth_non_advisory' => 
                'The wealth management features provided by PANÉTA Capital are for informational purposes only. ' .
                'This service does not constitute financial advice, investment advice, or any other type of advice. ' .
                'You should consult with a qualified financial advisor before making any investment decisions.',
            
            'fx_risk' => 
                'Foreign exchange trading involves significant risk of loss. Exchange rates can be volatile and ' .
                'you may lose money on currency conversions. Past performance is not indicative of future results. ' .
                'PANÉTA Capital does not guarantee any exchange rates or outcomes.',
            
            'terms_of_service' => 
                'By using PANÉTA Capital services, you agree to be bound by our Terms of Service. ' .
                'Please read the full terms carefully before proceeding.',
            
            'privacy_policy' => 
                'PANÉTA Capital collects and processes your personal data in accordance with our Privacy Policy. ' .
                'By continuing, you acknowledge that you have read and understood our data practices.',
            
            'transaction_authorization' => 
                'By initiating this transaction, you authorize PANÉTA Capital to process the payment instruction ' .
                'through the designated financial institutions. You confirm the transaction details are accurate.',
        ];

        return $texts[$disclosureType] ?? '';
    }
}
