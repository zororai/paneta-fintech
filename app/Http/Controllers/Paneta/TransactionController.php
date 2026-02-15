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

        return Inertia::render('Paneta/SendMoney', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'issuer_account_id' => ['required', 'exists:linked_accounts,id'],
            'acquirer_identifier' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $user = $request->user();
        $issuerAccount = LinkedAccount::findOrFail($validated['issuer_account_id']);

        // Verify ownership
        if ($issuerAccount->user_id !== $user->id) {
            return back()->withErrors([
                'issuer_account_id' => 'Account does not belong to you',
            ]);
        }

        // Create transaction intent
        $result = $this->orchestrationEngine->createTransactionIntent(
            $user,
            $issuerAccount,
            $validated['acquirer_identifier'],
            $validated['amount'],
            $issuerAccount->currency
        );

        if (!$result->success) {
            return back()->withErrors([
                'amount' => $result->error,
            ])->withInput();
        }

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
