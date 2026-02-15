<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\TransactionIntent;
use App\Services\OrchestrationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly OrchestrationEngine $orchestrationEngine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->transactionIntents()
            ->with(['issuerAccount.institution', 'paymentInstruction'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    public function show(Request $request, TransactionIntent $transaction): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $transaction->load(['issuerAccount.institution', 'paymentInstruction']);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    public function sendMoney(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'issuer_account_id' => ['required', 'exists:linked_accounts,id'],
            'acquirer_identifier' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $user = $request->user();
        $issuerAccount = LinkedAccount::findOrFail($validated['issuer_account_id']);

        // Verify ownership
        if ($issuerAccount->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account does not belong to you',
            ], 403);
        }

        // Verify currency matches
        if ($issuerAccount->currency !== $validated['currency']) {
            return response()->json([
                'success' => false,
                'message' => 'Currency mismatch with source account',
            ], 400);
        }

        // Create transaction intent
        $result = $this->orchestrationEngine->createTransactionIntent(
            $user,
            $issuerAccount,
            $validated['acquirer_identifier'],
            $validated['amount'],
            $validated['currency']
        );

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->error,
                'compliance_checks' => $result->complianceChecks,
            ], 400);
        }

        // Execute the transaction
        $executionResult = $this->orchestrationEngine->confirmAndExecute($result->intent);

        if (!$executionResult->success) {
            return response()->json([
                'success' => false,
                'message' => $executionResult->error,
                'data' => [
                    'transaction' => $executionResult->intent,
                    'instruction' => $executionResult->instruction,
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction executed successfully',
            'data' => [
                'transaction' => $executionResult->intent->load('issuerAccount.institution'),
                'instruction' => $executionResult->instruction,
                'external_reference' => $executionResult->externalReference,
            ],
        ], 201);
    }
}
