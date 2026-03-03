<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FXProviderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Paneta/FXProvider');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'trading_name' => 'required|string|max:255',
            'trading_volume' => 'required|string|max:255',
            'daily_limit' => 'required|string|max:255',
            'licenses' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'certificates' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'license_validity' => 'required|date',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'physical_address' => 'required|string|max:500',
            'country_of_origin' => 'required|string|max:2',
            'settlement_accounts' => 'required|string',
            'key_services' => 'required|string',
            'member_since' => 'required|date',
            'trading_as' => 'required|string|max:100',
            'processing_fee' => 'required|string|max:100',
            'tax_clearance' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tax_id' => 'required|string|max:100',
        ]);

        // Store file uploads
        if ($request->hasFile('licenses')) {
            $validated['licenses_path'] = $request->file('licenses')->store('fx-provider/licenses', 'public');
        }

        if ($request->hasFile('certificates')) {
            $validated['certificates_path'] = $request->file('certificates')->store('fx-provider/certificates', 'public');
        }

        if ($request->hasFile('tax_clearance')) {
            $validated['tax_clearance_path'] = $request->file('tax_clearance')->store('fx-provider/tax', 'public');
        }

        // Create FX Provider registration record
        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'pending';
        
        \App\Models\FxProviderRegistration::create($validated);

        return redirect()->back()->with('success', 'Your FX Dealer registration has been submitted for verification. You will be notified once approved.');
    }
}
