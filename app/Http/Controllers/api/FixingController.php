<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


use App\Models\QuotationKontrakH;

class FixingController extends Controller
{
    public function fixDetailStructure(Request $request)
    {

        try {
            $dataList = QuotationKontrakH::with('quotationKontrakD')
                ->whereIn('no_document', $request->no_document)
                ->where('is_active', true)
                ->get();

            $fixedCount   = 0;
            $skippedCount = 0;
            $errorCount   = 0;
            $errorDetails = [];

            foreach ($dataList as $data) {
                DB::beginTransaction();
                try {
                    foreach ($data->quotationKontrakD as $detailIndex => $detail) {
                        $dsDetail = json_decode($detail->data_pendukung_sampling, true);

                        // ⚙️ 1. Cek apakah sudah sesuai struktur (sudah punya "periode_kontrak")
                        $isValidStructure = false;

                        if (is_array($dsDetail)) {
                            foreach ($dsDetail as $key => $item) {

                                // 🔹 Case 1: Struktur lama tapi sudah dikonversi (pakai key angka dan ada periode_kontrak)
                                if (is_array($item) && array_key_exists('periode_kontrak', $item)) {
                                    $isValidStructure = true;
                                    break;
                                }

                                // 🔹 Case 2: Format array langsung [{ "periode_kontrak": ..., "data_sampling": ... }]
                                if (array_key_exists('periode_kontrak', $dsDetail)) {
                                    $isValidStructure = true;
                                    break;
                                }
                            }
                        }

                        if ($isValidStructure) {
                            // ✅ Sudah sesuai struktur → skip
                            $skippedCount++;
                            continue;
                        }

                        // ⚙️ 2. Kalau belum sesuai, bentuk ulang struktur
                        $originalStructure = [
                            $detailIndex + 1 => [
                                "periode_kontrak" => $detail->periode_kontrak,
                                "data_sampling"   => $dsDetail,
                            ],
                        ];

                        $detail->data_pendukung_sampling = json_encode($originalStructure);
                        $detail->save();

                        $fixedCount++;
                    }

                    DB::commit();
                } catch (Throwable $th) {
                    DB::rollback();
                    $errorCount++;
                    $errorDetails[] = [
                        'document' => $data->no_document,
                        'error'    => $th->getMessage(),
                    ];
                    Log::error('Error fixing detail structure: ' . $data->no_document, [
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString(),
                    ]);
                }
            }

            return response()->json([
                'message'         => 'Fix detail structure completed',
                'fixed'           => $fixedCount,
                'skipped'         => $skippedCount,
                'errors'          => $errorCount,
                'total_documents' => count($dataList),
                'error_details'   => $errorDetails,
            ], 200);
        } catch (Throwable $th) {
            Log::error('System error in fixDetailStructure', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error'   => app()->environment('local') ? $th->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

}
