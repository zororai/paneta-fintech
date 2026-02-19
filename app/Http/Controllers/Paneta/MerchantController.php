<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantDevice;
use App\Models\LinkedAccount;
use App\Services\MerchantOrchestrationEngine;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantController extends Controller
{
    public function __construct(
        protected MerchantOrchestrationEngine $merchantEngine,
        protected AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        $merchant = Merchant::where('user_id', $user->id)->first();
        $devices = $merchant ? $merchant->devices()->get() : collect();
        $linkedAccounts = LinkedAccount::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('institution')
            ->get();
        
        $recentPayments = [];
        if ($merchant) {
            $recentPayments = $merchant->payments()
                ->latest()
                ->take(10)
                ->get()
                ->toArray();
        }

        return Inertia::render('Paneta/Merchant', [
            'merchant' => $merchant,
            'devices' => $devices,
            'linkedAccounts' => $linkedAccounts,
            'recentPayments' => $recentPayments,
            'stats' => $this->getMerchantStats($merchant),
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_registration_number' => 'nullable|string|max:100',
            'business_type' => 'nullable|string|max:100',
            'country' => 'required|string|size:2',
        ]);

        $merchant = $this->merchantEngine->registerMerchant(
            user: $request->user(),
            businessName: $validated['business_name'],
            businessRegistrationNumber: $validated['business_registration_number'] ?? null,
            businessType: $validated['business_type'] ?? null,
            country: $validated['country']
        );

        return back()->with('success', 'Merchant registration submitted for verification.');
    }

    public function setSettlementAccount(Request $request, Merchant $merchant)
    {
        if ($merchant->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'linked_account_id' => 'required|exists:linked_accounts,id',
        ]);

        $account = LinkedAccount::findOrFail($validated['linked_account_id']);
        
        $this->merchantEngine->setSettlementAccount($merchant, $account);

        return back()->with('success', 'Settlement account updated.');
    }

    public function registerDevice(Request $request, Merchant $merchant)
    {
        if ($merchant->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'device_name' => 'nullable|string|max:100',
            'device_type' => 'nullable|string|in:terminal,mobile,tablet',
        ]);

        $device = $this->merchantEngine->registerDevice(
            merchant: $merchant,
            deviceName: $validated['device_name'] ?? null,
            deviceType: $validated['device_type'] ?? null
        );

        return back()->with('success', 'Device registered successfully.');
    }

    public function deactivateDevice(Request $request, Merchant $merchant, MerchantDevice $device)
    {
        if ($merchant->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->merchantEngine->deactivateDevice($device);

        return back()->with('success', 'Device deactivated.');
    }

    public function generateQr(Request $request, Merchant $merchant)
    {
        if ($merchant->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'device_id' => 'required|exists:merchant_devices,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $device = MerchantDevice::findOrFail($validated['device_id']);

        try {
            $qrData = $this->merchantEngine->generatePaymentQr(
                merchant: $merchant,
                device: $device,
                amount: $validated['amount'],
                description: $validated['description'] ?? null
            );

            return response()->json([
                'success' => true,
                'qr_data' => $qrData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    protected function getMerchantStats(?Merchant $merchant): array
    {
        if (!$merchant) {
            return [
                'total_transactions' => 0,
                'total_volume' => 0,
                'active_devices' => 0,
                'today_transactions' => 0,
            ];
        }

        return [
            'total_transactions' => $merchant->payments()->count(),
            'total_volume' => $merchant->payments()->sum('amount'),
            'active_devices' => $merchant->devices()->where('status', 'active')->count(),
            'today_transactions' => $merchant->payments()->whereDate('created_at', today())->count(),
        ];
    }
}
