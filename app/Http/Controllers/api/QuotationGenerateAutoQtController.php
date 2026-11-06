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
     * Phase/bulan/promo diambil dari job file artisan.
     */
    public function run(Request $request)
    {
        return $this->execute(false);
    }

    /**
     * Mode tes: limit kecil / email ke --email.
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
            $phase = $job['phase'];
            $result = (new QuotationGenerateAutoQtRunner())->run([
                'phase' => $phase,
                'month' => $job['month'],
                'promo_id' => $job['promo_id'],
                'test' => $test || !empty($job['test']),
                'email' => $job['email'] ?? null,
                'limit' => $job['limit'] ?? null,
                'tanggal_penawaran' => $job['tanggal_penawaran'] ?? null,
            ]);

            $labels = [
                QuotationAutoQtJobState::PHASE_BUILD => 'Generate data Auto QT',
                QuotationAutoQtJobState::PHASE_RENDER => 'Render PDF Auto QT',
                QuotationAutoQtJobState::PHASE_EMAIL => 'Email Auto QT',
                QuotationAutoQtJobState::PHASE_ALL => 'Generate + render + email Auto QT',
            ];
            $label = $labels[$phase] ?? 'Auto QT';

            return response()->json([
                'message' => $test
                    ? $label . ' (test) selesai.'
                    : $label . ' selesai.',
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
