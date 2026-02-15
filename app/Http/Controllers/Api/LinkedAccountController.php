<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LinkedAccount;
use App\Services\AuditService;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LinkedAccountController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()
            ->linkedAccounts()
            ->with('institution')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function institutions(): JsonResponse
    {
        $institutions = Institution::active()->get();

        return response()->json([
            'success' => true,
            'data' => $institutions,
        ]);
    }

    public function initiateLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
        ]);

        $institution = Institution::findOrFail($validated['institution_id']);

        if (!$institution->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Institution is not available',
            ], 400);
        }

        $consentData = $this->consentService->initiateConsent(
            $request->user(),
            $institution
        );

        return response()->json([
            'success' => true,
            'data' => $consentData,
        ]);
    }

    public function completeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $user = $request->user();
        $institution = Institution::findOrFail($validated['institution_id']);

        // Check if already linked
        $existingAccount = $user->linkedAccounts()
            ->where('institution_id', $institution->id)
            ->where('currency', $validated['currency'])
            ->where('status', 'active')
            ->first();

        if ($existingAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Account already linked for this institution and currency',
            ], 400);
        }

        $account = $this->consentService->completeConsent(
            $user,
            $institution,
            $validated['currency']
        );

        $this->auditService->logAccountLinked($user, $account->id, [
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'currency' => $validated['currency'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $account->load('institution'),
            'message' => 'Account linked successfully',
        ], 201);
    }

    public function revoke(Request $request, LinkedAccount $linkedAccount): JsonResponse
    {
        $user = $request->user();

        if ($linkedAccount->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->consentService->revokeConsent($linkedAccount);

        $this->auditService->logAccountRevoked($user, $linkedAccount->id, [
            'institution_id' => $linkedAccount->institution_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account consent revoked',
        ]);
    }

    public function refresh(Request $request, LinkedAccount $linkedAccount): JsonResponse
    {
        $user = $request->user();

        if ($linkedAccount->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $refreshedAccount = $this->consentService->refreshConsent($linkedAccount);

        $this->auditService->log(
            'consent_refreshed',
            'linked_account',
            $linkedAccount->id,
            $user,
            ['new_expiry' => $refreshedAccount->consent_expires_at->toIso8601String()]
        );

        return response()->json([
            'success' => true,
            'data' => $refreshedAccount->load('institution'),
            'message' => 'Consent refreshed successfully',
        ]);
    }
}
