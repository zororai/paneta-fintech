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
        
        $merchants = Merchant::where('user_id', $user->id)->get();
        $linkedAccounts = LinkedAccount::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('institution')
            ->get();
        
        $allDevices = [];
        $allPayments = [];
        $totalStats = [
            'total_transactions' => 0,
            'total_volume' => 0,
            'active_devices' => 0,
            'today_transactions' => 0,
        ];

        foreach ($merchants as $merchant) {
            $devices = $merchant->devices()->get();
            $allDevices = array_merge($allDevices, $devices->toArray());
            
            $payments = $merchant->payments()->latest()->take(10)->get();
            $allPayments = array_merge($allPayments, $payments->toArray());
            
            $stats = $this->getMerchantStats($merchant);
            $totalStats['total_transactions'] += $stats['total_transactions'];
            $totalStats['total_volume'] += $stats['total_volume'];
            $totalStats['active_devices'] += $stats['active_devices'];
            $totalStats['today_transactions'] += $stats['today_transactions'];
        }

        return Inertia::render('Paneta/Merchant', [
            'merchants' => $merchants,
            'devices' => $allDevices,
            'linkedAccounts' => $linkedAccounts,
            'recentPayments' => $allPayments,
            'stats' => $totalStats,
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'business_registration_number' => 'nullable|string|max:100',
            'business_sector' => 'nullable|string|max:100',
            'country' => 'required|string|size:2',
            'business_logo' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:100',
            'reporting_currency' => 'nullable|string|size:3',
            'settlement_account_id' => 'nullable|exists:linked_accounts,id',
            'other_settlement_accounts' => 'nullable|array',
            'other_settlement_accounts.*' => 'exists:linked_accounts,id',
        ]);

        $merchant = $this->merchantEngine->registerMerchant(
            user: $request->user(),
            businessName: $validated['business_name'],
            businessRegistrationNumber: $validated['business_registration_number'] ?? null,
            businessType: $validated['business_type'] ?? null,
            businessSector: $validated['business_sector'] ?? null,
            country: $validated['country'],
            taxId: $validated['tax_id'] ?? null,
            businessLogo: $validated['business_logo'] ?? null,
            reportingCurrency: $validated['reporting_currency'] ?? null,
            settlementAccountId: $validated['settlement_account_id'] ?? null,
            otherSettlementAccounts: $validated['other_settlement_accounts'] ?? null
        );

        return back()->with('success', 'Business registered successfully.');
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
