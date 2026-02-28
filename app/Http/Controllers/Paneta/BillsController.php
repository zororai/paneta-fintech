<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\SavedBiller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $linkedAccounts = LinkedAccount::with('institution')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $savedBillers = SavedBiller::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Paneta/Bills', [
            'linkedAccounts' => $linkedAccounts,
            'savedBillers' => $savedBillers,
        ]);
    }

    public function payBill(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|in:electricity,airtime,internet,rent',
            'provider' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'required|exists:linked_accounts,id',
        ]);

        $user = $request->user();

        // Verify the payment account belongs to the user
        $paymentAccount = LinkedAccount::where('id', $validated['payment_account_id'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Check if account has sufficient balance
        if ($paymentAccount->mock_balance < $validated['amount']) {
            return back()->withErrors(['amount' => 'Insufficient balance in selected account.']);
        }

        // Deduct the amount from the account
        $paymentAccount->decrement('mock_balance', $validated['amount']);

        // Log the bill payment (you can create a BillPayment model if needed)
        // For now, we'll just return success

        return back()->with('success', 'Bill payment of $' . number_format($validated['amount'], 2) . ' to ' . $validated['provider'] . ' was successful.');
    }

    public function saveBiller(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|in:electricity,airtime,internet,rent',
            'provider' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'due_date' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        SavedBiller::create([
            'user_id' => $user->id,
            'category' => $validated['category'],
            'provider' => $validated['provider'],
            'account_number' => $validated['account_number'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'due_date' => $validated['due_date'] ?? 'Auto',
        ]);

        return back()->with('success', 'Biller saved successfully.');
    }
}
