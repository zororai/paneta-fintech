<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LinkedAccount;
use App\Services\AuditService;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinkedAccountController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request): Response
    {
        $accounts = $request->user()
            ->linkedAccounts()
            ->with('institution')
            ->get();

        $institutions = Institution::active()->get();

        return Inertia::render('Paneta/LinkedAccounts', [
            'accounts' => $accounts,
            'institutions' => $institutions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'country' => ['required', 'string', 'size:2'],
            'account_number' => ['required', 'string', 'min:5', 'max:50'],
            'account_holder_name' => ['required', 'string', 'min:2', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $user = $request->user();
        $institution = Institution::findOrFail($validated['institution_id']);

        // Check if already linked
        $existingAccount = $user->linkedAccounts()
            ->where('institution_id', $institution->id)
            ->where('account_identifier', $validated['account_number'])
            ->where('status', 'active')
            ->first();

        if ($existingAccount) {
            return back()->withErrors([
                'account_number' => 'This account is already linked',
            ]);
        }

        $account = $this->consentService->completeConsent(
            $user,
            $institution,
            $validated['currency'],
            $validated['account_number'],
            $validated['account_holder_name'],
            $validated['country']
        );

        $this->auditService->logAccountLinked($user, $account->id, [
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'country' => $validated['country'],
            'account_holder_name' => $validated['account_holder_name'],
            'account_number' => $validated['account_number'],
            'currency' => $validated['currency'],
        ]);

        return back()->with('success', 'Account linked successfully');
    }

    public function revoke(Request $request, LinkedAccount $linkedAccount): RedirectResponse
    {
        $user = $request->user();

        if ($linkedAccount->user_id !== $user->id) {
            abort(403);
        }

        $this->consentService->revokeConsent($linkedAccount);

        $this->auditService->logAccountRevoked($user, $linkedAccount->id, [
            'institution_id' => $linkedAccount->institution_id,
        ]);

        return back()->with('success', 'Account consent revoked');
    }

    public function refresh(Request $request, LinkedAccount $linkedAccount): RedirectResponse
    {
        $user = $request->user();

        if ($linkedAccount->user_id !== $user->id) {
            abort(403);
        }

        $refreshedAccount = $this->consentService->refreshConsent($linkedAccount);

        $this->auditService->log(
            'consent_refreshed',
            'linked_account',
            $linkedAccount->id,
            $user,
            ['new_expiry' => $refreshedAccount->consent_expires_at->toIso8601String()]
        );

        return back()->with('success', 'Consent refreshed successfully');
    }
}
