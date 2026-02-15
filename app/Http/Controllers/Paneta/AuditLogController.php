<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = $request->user()
            ->auditLogs()
            ->latest('created_at')
            ->paginate(50);

        return Inertia::render('Paneta/AuditLogs', [
            'logs' => $logs,
        ]);
    }
}
