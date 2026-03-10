<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Models\SmartAlert;
use Illuminate\Http\Request;

class SmartAlertController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'currency_pair' => 'required|string|max:10',
            'target_rate' => 'required|numeric|min:0',
            'alert_type' => 'required|in:above,below,change_1_percent',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
        ]);

        $alert = SmartAlert::create([
            'user_id' => $request->user()->id,
            'currency_pair' => $validated['currency_pair'],
            'target_rate' => $validated['target_rate'],
            'alert_type' => $validated['alert_type'],
            'email_notifications' => $validated['email_notifications'] ?? false,
            'sms_notifications' => $validated['sms_notifications'] ?? false,
            'push_notifications' => $validated['push_notifications'] ?? false,
            'status' => 'active',
        ]);

        return back()->with('success', 'Smart alert created successfully.');
    }

    public function destroy(Request $request, SmartAlert $smartAlert)
    {
        if ($smartAlert->user_id !== $request->user()->id) {
            abort(403);
        }

        $smartAlert->delete();

        return back()->with('success', 'Smart alert deleted successfully.');
    }
}
