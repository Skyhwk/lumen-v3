<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\PaymentPerformance\PaymentPerformanceService;
use Illuminate\Http\Request;

class DashboardPaymentPerformanceController extends Controller
{
    public function index(Request $request)
    {
        /** @var PaymentPerformanceService $service */
        $service = app(PaymentPerformanceService::class);
        $year = $service->resolveYear($request->input('year'));

        return response()->json($service->getDashboardData($year), 200);
    }
}
