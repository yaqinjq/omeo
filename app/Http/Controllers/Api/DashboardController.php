<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function summary(DashboardService $service)
    {
        return response()->json([
            'ok' => true,
            'data' => $service->summary(),
        ]);
    }
}
