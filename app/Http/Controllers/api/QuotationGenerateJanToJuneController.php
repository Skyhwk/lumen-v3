<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\QuotationGenerate\QuotationGenerateJanToJuneRunner;
use Illuminate\Http\Request;

class QuotationGenerateJanToJuneController extends Controller
{
    /**
     * Dipanggil Python /lumen/invoke → POST /api/route (body kosong).
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
            $result = (new QuotationGenerateJanToJuneRunner())->run(['test' => $test]);

            return response()->json([
                'message' => $test
                    ? 'Generate Jan to June (test) selesai.'
                    : 'Generate Jan to June selesai.',
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
