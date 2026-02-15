<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrchestrationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OrchestrationEngine $orchestrationEngine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $dashboardData = $this->orchestrationEngine->getDashboardData($user);

        return response()->json([
            'success' => true,
            'data' => $dashboardData,
        ]);
    }
}
