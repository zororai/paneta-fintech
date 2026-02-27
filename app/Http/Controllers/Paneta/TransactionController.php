<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\TransactionIntent;
use App\Services\OrchestrationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly OrchestrationEngine $orchestrationEngine
    ) {}

    public function index(Request $request): Response
    {
        $transactions = $request->user()
            ->transactionIntents()
            ->with(['issuerAccount.institution', 'paymentInstruction'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Paneta/Transactions', [
            'transactions' => $transactions,
        ]);
    }

    public function show(Request $request, TransactionIntent $transaction): Response
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load(['issuerAccount.institution', 'paymentInstruction']);

        return Inertia::render('Paneta/TransactionDetail', [
            'transaction' => $transaction,
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = $request->user()
            ->linkedAccounts()
            ->with('institution')
            ->where('status', 'active')
            ->get();

        $institutions = \App\Models\Institution::active()->get();

        return Inertia::render('Paneta/SendMoney', [
            'accounts' => $accounts,
            'institutions' => $institutions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:manual,scan,link'],
            'source_account_id' => ['required', 'exists:linked_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source_currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:500'],
            'destination_country' => ['required', 'string', 'size:2'],
            'destination_institution_id' => ['required', 'exists:institutions,id'],
            'destination_account' => ['required', 'string', 'max:255'],
            'destination_currency' => ['required', 'string', 'size:3'],
            
            // Legacy support for old form
            'issuer_account_id' => ['nullable', 'exists:linked_accounts,id'],
            'acquirer_identifier' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        
        // Support both new and old form field names
        $sourceAccountId = $validated['source_account_id'] ?? $validated['issuer_account_id'] ?? null;
        $destinationIdentifier = $validated['destination_account'] ?? $validated['acquirer_identifier'] ?? null;
        
        $issuerAccount = LinkedAccount::findOrFail($sourceAccountId);

        // Verify ownership
        if ($issuerAccount->user_id !== $user->id) {
            return back()->withErrors([
                'source_account_id' => 'Account does not belong to you',
            ]);
        }

        // Detect if this is a cross-border transaction
        $isCrossBorder = $validated['source_currency'] !== $validated['destination_currency'];

        // Create transaction intent with additional metadata
        $result = $this->orchestrationEngine->createTransactionIntent(
            $user,
            $issuerAccount,
            $destinationIdentifier,
            $validated['amount'],
            $issuerAccount->currency,
            [
                'payment_method' => $validated['payment_method'] ?? 'manual',
                'description' => $validated['description'] ?? null,
                'destination_country' => $validated['destination_country'] ?? null,
                'destination_institution_id' => $validated['destination_institution_id'] ?? null,
                'destination_currency' => $validated['destination_currency'] ?? null,
                'is_cross_border' => $isCrossBorder,
            ]
        );

        if (!$result->success) {
            return back()->withErrors([
                'amount' => $result->error,
            ])->withInput();
        }

        // TODO: Route to Pre-Execution Controls stage instead of immediate execution
        // For now, we'll execute immediately but this should be updated to show
        // the Pre-Execution Controls page as described in the process flows
        
        // Execute the transaction
        $executionResult = $this->orchestrationEngine->confirmAndExecute($result->intent);

        if (!$executionResult->success) {
            return back()->withErrors([
                'amount' => $executionResult->error,
            ])->withInput();
        }

        return redirect()->route('paneta.transactions.show', $executionResult->intent)
            ->with('success', 'Transaction executed successfully');
    }
}
