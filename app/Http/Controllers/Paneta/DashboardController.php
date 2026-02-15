<?php

namespace App\Http\Controllers\Paneta;

use App\Http\Controllers\Controller;
use App\Services\OrchestrationEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OrchestrationEngine $orchestrationEngine
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $dashboardData = $this->orchestrationEngine->getDashboardData($user);

        return Inertia::render('Paneta/Dashboard', [
            'dashboardData' => $dashboardData,
            'user' => $user,
        ]);
    }
}
