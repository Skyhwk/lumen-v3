<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebsiteStatistikController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return response()->json(['message' => 'Format period tidak valid (YYYY-MM)'], 422);
        }

        $token = env(
            'WEBSITE_STATS_TOKEN',
            '036dbf9759059fb69c1356c3c7780470ddaa0fd23ad7d24783980b86547b609a'
        );

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->get('https://www.intilab.com/api/monitoring/stats', [
                    'period' => $period,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Gagal mengambil statistik website',
                ], $response->status());
            }

            return response()->json($response->json(), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik: ' . $e->getMessage(),
            ], 500);
        }
    }
}
