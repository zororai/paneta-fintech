<?php

namespace App\Services;

use App\Models\User;
use App\Models\ComplianceCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SanctionsScreeningService
{
    const CACHE_TTL = 86400; // 24 hours

    protected array $sanctionsLists = [
        'OFAC_SDN' => 'US OFAC Specially Designated Nationals',
        'UN_SC' => 'UN Security Council Consolidated List',
        'EU_FS' => 'EU Financial Sanctions',
        'UK_HMT' => 'UK HM Treasury Sanctions List',
    ];

    public function screenIndividual(
        string $name,
        string $dateOfBirth = null,
        string $nationality = null,
        array $identifiers = []
    ): array {
        $results = [
            'screened_at' => now()->toIso8601String(),
            'input' => [
                'name' => $name,
                'date_of_birth' => $dateOfBirth,
                'nationality' => $nationality,
            ],
            'hits' => [],
            'match_found' => false,
            'risk_score' => 0,
        ];

        // Normalize name for matching
        $normalizedName = $this->normalizeName($name);

        foreach ($this->sanctionsLists as $listCode => $listName) {
            $hits = $this->searchList($listCode, $normalizedName, $dateOfBirth, $nationality);
            
            foreach ($hits as $hit) {
                $results['hits'][] = [
                    'list' => $listCode,
                    'list_name' => $listName,
                    'matched_name' => $hit['name'],
                    'match_score' => $hit['score'],
                    'entity_type' => $hit['entity_type'] ?? 'individual',
                    'listed_date' => $hit['listed_date'] ?? null,
                    'program' => $hit['program'] ?? null,
                ];
            }
        }

        $results['match_found'] = !empty($results['hits']);
        $results['risk_score'] = $this->calculateRiskScore($results['hits']);

        if ($results['match_found']) {
            Log::warning('Sanctions screening hit', [
                'name' => $name,
                'hits' => count($results['hits']),
            ]);
        }

        return $results;
    }

    public function screenOrganization(string $name, string $country = null): array
    {
        $results = [
            'screened_at' => now()->toIso8601String(),
            'input' => [
                'name' => $name,
                'country' => $country,
            ],
            'hits' => [],
            'match_found' => false,
            'risk_score' => 0,
        ];

        $normalizedName = $this->normalizeName($name);

        foreach ($this->sanctionsLists as $listCode => $listName) {
            $hits = $this->searchListOrganization($listCode, $normalizedName, $country);
            
            foreach ($hits as $hit) {
                $results['hits'][] = [
                    'list' => $listCode,
                    'list_name' => $listName,
                    'matched_name' => $hit['name'],
                    'match_score' => $hit['score'],
                    'entity_type' => 'organization',
                    'country' => $hit['country'] ?? null,
                ];
            }
        }

        $results['match_found'] = !empty($results['hits']);
        $results['risk_score'] = $this->calculateRiskScore($results['hits']);

        return $results;
    }

    public function screenUser(User $user): array
    {
        $cacheKey = "sanctions_screen:user:{$user->id}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $result = $this->screenIndividual(
            $user->name,
            $user->date_of_birth ?? null,
            $user->nationality ?? null
        );

        $result['user_id'] = $user->id;

        // Cache result
        Cache::put($cacheKey, $result, self::CACHE_TTL);

        // If hit found, create compliance case
        if ($result['match_found']) {
            $this->createComplianceCase($user, $result);
        }

        return $result;
    }

    public function screenCounterparty(string $name, string $accountIdentifier = null, string $country = null): array
    {
        return $this->screenIndividual($name, null, $country);
    }

    public function batchScreen(array $subjects): array
    {
        $results = [];
        
        foreach ($subjects as $index => $subject) {
            if (isset($subject['name'])) {
                $results[$index] = $this->screenIndividual(
                    $subject['name'],
                    $subject['date_of_birth'] ?? null,
                    $subject['nationality'] ?? null
                );
            }
        }

        return [
            'screened_at' => now()->toIso8601String(),
            'total_screened' => count($results),
            'total_hits' => collect($results)->where('match_found', true)->count(),
            'results' => $results,
        ];
    }

    public function isPep(string $name, string $country = null): array
    {
        // PEP (Politically Exposed Person) screening
        // In production, this would check PEP databases
        
        $normalizedName = $this->normalizeName($name);
        
        // Mock PEP check - in production use a real PEP database
        $pepMatch = $this->mockPepCheck($normalizedName);

        return [
            'is_pep' => $pepMatch['match'],
            'pep_category' => $pepMatch['category'] ?? null,
            'pep_position' => $pepMatch['position'] ?? null,
            'country' => $pepMatch['country'] ?? null,
            'screened_at' => now()->toIso8601String(),
        ];
    }

    public function getScreeningReport(User $user, int $days = 30): array
    {
        $cases = ComplianceCase::where('user_id', $user->id)
            ->whereIn('type', [ComplianceCase::TYPE_SANCTIONS_HIT, ComplianceCase::TYPE_PEP_MATCH])
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'user_id' => $user->id,
            'period_days' => $days,
            'total_screenings' => $cases->count(),
            'open_cases' => $cases->where('status', '!=', 'closed')->count(),
            'false_positives' => $cases->where('status', 'closed_false_positive')->count(),
            'confirmed_matches' => $cases->where('status', 'closed_action_taken')->count(),
            'cases' => $cases->map(function ($case) {
                return [
                    'reference' => $case->case_reference,
                    'type' => $case->type,
                    'status' => $case->status,
                    'created_at' => $case->created_at->toIso8601String(),
                ];
            })->toArray(),
        ];
    }

    protected function searchList(string $listCode, string $name, ?string $dob, ?string $nationality): array
    {
        // In production, this would query actual sanctions databases
        // For MVP, return empty (no matches) most of the time with occasional mock matches
        
        // Simulate API latency
        usleep(rand(50000, 150000)); // 50-150ms
        
        // Mock implementation - very low match rate
        if (rand(1, 1000) === 1) {
            return [[
                'name' => $name,
                'score' => rand(70, 95),
                'entity_type' => 'individual',
                'listed_date' => '2020-01-15',
                'program' => 'MOCK_PROGRAM',
            ]];
        }

        return [];
    }

    protected function searchListOrganization(string $listCode, string $name, ?string $country): array
    {
        // Mock implementation
        return [];
    }

    protected function mockPepCheck(string $name): array
    {
        // Mock PEP check - returns no match for MVP
        return [
            'match' => false,
            'category' => null,
            'position' => null,
            'country' => null,
        ];
    }

    protected function normalizeName(string $name): string
    {
        // Remove titles, normalize whitespace, convert to uppercase
        $name = preg_replace('/\b(Mr|Mrs|Ms|Dr|Jr|Sr|III|II|IV)\b\.?/i', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return strtoupper($name);
    }

    protected function calculateRiskScore(array $hits): int
    {
        if (empty($hits)) {
            return 0;
        }

        $maxScore = 0;
        foreach ($hits as $hit) {
            if (($hit['match_score'] ?? 0) > $maxScore) {
                $maxScore = $hit['match_score'];
            }
        }

        // Scale to 0-100
        return min(100, $maxScore);
    }

    protected function createComplianceCase(User $user, array $screeningResult): ComplianceCase
    {
        return ComplianceCase::create([
            'case_reference' => ComplianceCase::generateReference(),
            'user_id' => $user->id,
            'related_type' => User::class,
            'related_id' => $user->id,
            'type' => ComplianceCase::TYPE_SANCTIONS_HIT,
            'status' => ComplianceCase::STATUS_OPEN,
            'priority' => 'critical',
            'risk_level' => 'very_high',
            'description' => 'Potential sanctions list match detected during screening',
            'metadata' => [
                'screening_result' => $screeningResult,
            ],
            'due_date' => now()->addHours(24),
        ]);
    }
}
