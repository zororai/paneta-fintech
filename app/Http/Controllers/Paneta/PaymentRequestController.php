<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\LinkedAccount;
use App\Services\PaymentRequestEngine;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentRequestController extends Controller
{
    public function __construct(
        protected PaymentRequestEngine $paymentRequestEngine,
        protected AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        $paymentRequests = PaymentRequest::where('user_id', $user->id)
            ->with('linkedAccount.institution')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $linkedAccounts = LinkedAccount::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('institution')
            ->get();

        $stats = [
            'total_requests' => PaymentRequest::where('user_id', $user->id)->count(),
            'pending_requests' => PaymentRequest::where('user_id', $user->id)->where('status', 'pending')->count(),
            'completed_requests' => PaymentRequest::where('user_id', $user->id)->where('status', 'completed')->count(),
            'total_received' => PaymentRequest::where('user_id', $user->id)->where('status', 'completed')->sum('amount_received'),
        ];

        return Inertia::render('Paneta/PaymentRequests', [
            'paymentRequests' => $paymentRequests,
            'linkedAccounts' => $linkedAccounts,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'linked_account_id' => 'nullable|exists:linked_accounts,id',
            'description' => 'nullable|string|max:255',
            'allow_partial' => 'boolean',
            'expires_in_minutes' => 'nullable|integer|min:5|max:10080',
        ]);

        $linkedAccount = null;
        if ($validated['linked_account_id']) {
            $linkedAccount = LinkedAccount::findOrFail($validated['linked_account_id']);
            if ($linkedAccount->user_id !== $request->user()->id) {
                return back()->withErrors(['linked_account_id' => 'Account does not belong to you']);
            }
        }

        $paymentRequest = $this->paymentRequestEngine->createPaymentRequest(
            user: $request->user(),
            amount: $validated['amount'],
            currency: $validated['currency'],
            linkedAccount: $linkedAccount,
            description: $validated['description'] ?? null,
            allowPartial: $validated['allow_partial'] ?? false,
            expiresInMinutes: $validated['expires_in_minutes'] ?? 60
        );

        return back()->with('success', 'Payment request created successfully.');
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest)
    {
        if ($paymentRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        $result = $this->paymentRequestEngine->cancelPaymentRequest($paymentRequest, $request->user());

        if ($result) {
            return back()->with('success', 'Payment request cancelled.');
        }

        return back()->withErrors(['error' => 'Cannot cancel this payment request.']);
    }

    public function show(Request $request, PaymentRequest $paymentRequest)
    {
        if ($paymentRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'payment_request' => $paymentRequest->load('linkedAccount.institution'),
            'qr_code_data' => $paymentRequest->qr_code_data,
        ]);
    }
}
