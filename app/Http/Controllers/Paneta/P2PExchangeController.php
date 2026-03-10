<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\P2POffer;
use App\Models\P2PExchangeRequest;
use App\Models\EscrowTransaction;
use App\Services\SmartEscrowService;
use App\Services\AtomicInstructionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class P2PExchangeController extends Controller
{
    protected $escrowService;
    protected $instructionService;

    public function __construct(
        SmartEscrowService $escrowService,
        AtomicInstructionService $instructionService
    ) {
        $this->escrowService = $escrowService;
        $this->instructionService = $instructionService;
    }

    /**
     * Start Exchange - Counterparty creates exchange request
     */
    public function startExchange(Request $request)
    {
        $validated = $request->validate([
            'offer_id' => ['required', 'exists:p2p_offers,id'],
            'source_account_id' => ['required', 'exists:linked_accounts,id'],
            'destination_account_id' => ['required', 'exists:linked_accounts,id'],
            'sell_amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            DB::beginTransaction();

            $offer = P2POffer::with('user')->findOrFail($validated['offer_id']);
            
            // Calculate receive amount based on exchange rate
            $buyAmount = $validated['sell_amount'] * $offer->rate;

            // Create exchange request
            $exchangeRequest = P2PExchangeRequest::create([
                'offer_id' => $offer->id,
                'counterparty_user_id' => auth()->id(),
                'initiator_user_id' => $offer->user_id,
                'counterparty_id_number' => 'USR-' . str_pad(auth()->id(), 8, '0', STR_PAD_LEFT),
                'cp_source_account_id' => $validated['source_account_id'],
                'cp_dest_account_id' => $validated['destination_account_id'],
                'sell_currency' => $offer->buy_currency,
                'sell_amount' => $validated['sell_amount'],
                'buy_currency' => $offer->sell_currency,
                'buy_amount' => $buyAmount,
                'exchange_rate' => $offer->rate,
                'status' => 'pending',
                'expires_at' => now()->addHours(24),
            ]);

            DB::commit();

            return back()->with('success', 'Exchange request sent successfully. Waiting for initiator approval.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create exchange request', [
                'user_id' => auth()->id(),
                'offer_id' => $validated['offer_id'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to create exchange request. Please try again.']);
        }
    }

    /**
     * Get exchange requests for the authenticated user
     */
    public function getExchangeRequests()
    {
        $user = auth()->user();

        // Requests where user is the initiator (offer creator)
        $receivedRequests = P2PExchangeRequest::with([
            'counterparty',
            'counterpartySourceAccount.institution',
            'counterpartyDestinationAccount.institution',
            'offer'
        ])
        ->where('initiator_user_id', $user->id)
        ->where('status', 'pending')
        ->latest()
        ->get();

        // Requests where user is the counterparty
        $sentRequests = P2PExchangeRequest::with([
            'initiator',
            'offer'
        ])
        ->where('counterparty_user_id', $user->id)
        ->latest()
        ->get();

        return response()->json([
            'received_requests' => $receivedRequests,
            'sent_requests' => $sentRequests,
        ]);
    }

    /**
     * Accept exchange request - Initiator accepts
     */
    public function acceptRequest(Request $request, P2PExchangeRequest $exchangeRequest)
    {
        // Verify user is the initiator
        if ($exchangeRequest->initiator_user_id !== auth()->id()) {
            return back()->withErrors(['error' => 'Unauthorized action.']);
        }

        if ($exchangeRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'This request has already been processed.']);
        }

        try {
            DB::beginTransaction();

            // Run Smart Escrow Preconditions
            $preconditionResult = $this->escrowService->runAllPreconditions($exchangeRequest);

            if (!$preconditionResult['passed']) {
                // Update request to declined
                $exchangeRequest->update([
                    'status' => 'declined',
                    'responded_at' => now(),
                ]);

                DB::commit();

                return back()->withErrors([
                    'error' => 'Exchange declined: ' . $preconditionResult['failure_reason']
                ]);
            }

            // Update request to accepted
            $exchangeRequest->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            // Get offer details
            $offer = $exchangeRequest->offer;

            // Calculate fees
            $initiatorFee = $this->escrowService->calculateFee($exchangeRequest->sell_amount);
            $counterpartyFee = $this->escrowService->calculateFee($exchangeRequest->buy_amount);

            // Create Escrow Transaction
            $escrow = EscrowTransaction::create([
                'exchange_request_id' => $exchangeRequest->id,
                'initiator_user_id' => $exchangeRequest->initiator_user_id,
                'counterparty_user_id' => $exchangeRequest->counterparty_user_id,
                'init_source_acct_id' => $offer->source_account_id,
                'init_dest_acct_id' => $offer->destination_account_id,
                'cp_source_acct_id' => $exchangeRequest->cp_source_account_id,
                'cp_dest_acct_id' => $exchangeRequest->cp_dest_account_id,
                'initiator_currency' => $exchangeRequest->sell_currency,
                'initiator_amount' => $exchangeRequest->sell_amount,
                'initiator_fee' => $initiatorFee,
                'initiator_total' => $exchangeRequest->sell_amount + $initiatorFee,
                'counterparty_currency' => $exchangeRequest->buy_currency,
                'counterparty_amount' => $exchangeRequest->buy_amount,
                'counterparty_fee' => $counterpartyFee,
                'counterparty_total' => $exchangeRequest->buy_amount + $counterpartyFee,
                'exchange_rate' => $exchangeRequest->exchange_rate,
                'status' => 'awaiting_confirmation',
                'precondition_checks' => $preconditionResult['checks'],
                'preconditions_passed' => true,
                'expires_at' => now()->addMinutes(10),
            ]);

            DB::commit();

            return back()->with('success', 'Exchange request accepted. Please confirm the trade summary.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept exchange request', [
                'request_id' => $exchangeRequest->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to accept request. Please try again.']);
        }
    }

    /**
     * Decline exchange request - Initiator declines
     */
    public function declineRequest(P2PExchangeRequest $exchangeRequest)
    {
        // Verify user is the initiator
        if ($exchangeRequest->initiator_user_id !== auth()->id()) {
            return back()->withErrors(['error' => 'Unauthorized action.']);
        }

        if ($exchangeRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'This request has already been processed.']);
        }

        $exchangeRequest->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Exchange request declined.');
    }

    /**
     * Confirm trade with PIN - Both users confirm
     */
    public function confirmTrade(Request $request, EscrowTransaction $escrow)
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'size:4'],
        ]);

        $user = auth()->user();

        // Verify user is part of this escrow
        if ($escrow->initiator_user_id !== $user->id && $escrow->counterparty_user_id !== $user->id) {
            return back()->withErrors(['error' => 'Unauthorized action.']);
        }

        // Verify PIN
        if (!Hash::check($validated['pin'], $user->pin)) {
            return back()->withErrors(['error' => 'Invalid PIN.']);
        }

        try {
            DB::beginTransaction();

            // Update confirmation status
            if ($escrow->initiator_user_id === $user->id) {
                $escrow->update([
                    'initiator_confirmed' => true,
                    'initiator_confirmed_at' => now(),
                ]);
            } else {
                $escrow->update([
                    'counterparty_confirmed' => true,
                    'counterparty_confirmed_at' => now(),
                ]);
            }

            // Refresh escrow
            $escrow->refresh();

            // Check if both parties have confirmed
            if ($escrow->initiator_confirmed && $escrow->counterparty_confirmed) {
                // Update status to confirmed
                $escrow->update(['status' => 'confirmed']);

                // Generate atomic instructions
                $instructionResult = $this->instructionService->generateInstructions($escrow);

                if ($instructionResult['success']) {
                    // Send instructions to institutions
                    $this->instructionService->sendToInstitutions($escrow);

                    DB::commit();

                    return back()->with('success', 'Exchange confirmed and executed successfully!');
                } else {
                    throw new \Exception('Failed to generate atomic instructions');
                }
            }

            DB::commit();

            return back()->with('success', 'Trade confirmed. Waiting for other party to confirm.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to confirm trade', [
                'escrow_id' => $escrow->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to confirm trade. Please try again.']);
        }
    }
}
