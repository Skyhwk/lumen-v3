<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\QuotationGenerate\QuotationAutoQtJobState;
use App\Services\QuotationGenerate\QuotationGenerateAutoQtRunner;
use Illuminate\Http\Request;

class QuotationGenerateAutoQtController extends Controller
{
    /**
     * Dipanggil Python /lumen/invoke → POST /api/route (body kosong).
     * Bulan target diambil dari job file yang ditulis artisan generateAutoQt.
     */
    public function run(Request $request)
    {
        return $this->execute(false);
    }

    /**
     * Mode tes: 2 QT, email hanya ke harold@intilab.com.
     */
    public function runTest(Request $request)
    {
        return $this->execute(true);
    }

    private function execute(bool $test)
    {
        @set_time_limit(0);

        try {
            $job = QuotationAutoQtJobState::read();
            $result = (new QuotationGenerateAutoQtRunner())->run([
                'month' => $job['month'],
                'test' => $test || !empty($job['test']),
            ]);

            return response()->json([
                'message' => $test
                    ? 'Generate Auto QT (test) selesai.'
                    : 'Generate Auto QT selesai.',
                'data' => $result,
            ], $result['success'] ? 200 : 500);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'processed' => false,
            ], 500);
        }
    }
}
