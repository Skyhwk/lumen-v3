<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\RenderKontrakCopy;
use App\Jobs\RenderNonKontrakCopy;
use App\Jobs\RenderPdfPenawaran;
use App\Models\ExpiredLink;
use App\Models\JobTask;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

                        if (! is_array($dsDetail)) {
                            $errorCount++;
                            continue;
                        }

                        $isValidStructure = false;
                        $needsFix         = false;

                        foreach ($dsDetail as $key => $item) {
                            if (is_array($item) && array_key_exists('periode_kontrak', $item)) {
                                $isValidStructure = true;
                                if (isset($item['data_sampling']) && is_string($item['data_sampling'])) {
                                    $decoded = json_decode($item['data_sampling'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $dsDetail[$key]['data_sampling'] = $decoded;
                                        $needsFix                        = true;
                                    }
                                }
                                break;
                            }

                            if (array_key_exists('periode_kontrak', $dsDetail)) {
                                $isValidStructure = true;
                                if (isset($dsDetail['data_sampling']) && is_string($dsDetail['data_sampling'])) {
                                    $decoded = json_decode($dsDetail['data_sampling'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $dsDetail['data_sampling'] = $decoded;
                                        $needsFix                  = true;
                                    }
                                }

                                // ✅ Gunakan key dinamis sesuai urutan detail
                                $dsDetail = [
                                    (string) ($detailIndex + 1) => $dsDetail,
                                ];
                                $needsFix = true;
                                break;
                            }
                        }

                        if ($isValidStructure && $needsFix) {
                            $detail->data_pendukung_sampling = json_encode($dsDetail);
                            $detail->save();
                            $fixedCount++;
                            continue;
                        }

                        if ($isValidStructure && ! $needsFix) {
                            $skippedCount++;
                            continue;
                        }

                        // Struktur lama → bungkus jadi struktur baru dengan key dinamis
                        $originalStructure = [
                            (string) ($detailIndex + 1) => [
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

    public function searchQuotation(Request $request)
    {
        try {
            $data           = QuotationKontrakH::where('is_active', true)->where('no_document', 'like', '%' . $request->no_document . '%')->first();
            $tipe_penawaran = 'kontrak';

            if (! $data) {
                $data           = QuotationNonKontrak::where('is_active', true)->where('no_document', 'like', '%' . $request->no_document . '%')->first();
                $tipe_penawaran = 'non kontrak';
            }

            if (! $data) {
                return response()->json([
                    'message' => 'Data Quotation tidak ditemukan!',
                ], 404);
            }

            return response()->json([
                'data'           => $data,
                'tipe_penawaran' => $tipe_penawaran,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function renderPdfQuotation(Request $request)
    {
        try {
            if ($request->mode == 'copy') {
                if ($request->tipe_penawaran == 'kontrak') {
                    $render = new RenderKontrakCopy();
                    $render->renderDataQuotation($request->id, 'id');
                } else {
                    $render = new RenderNonKontrakCopy();
                    $render->renderHeader($request->id, 'id');
                }
                return response()->json(['message' => 'Render copy selesai.']);
            } else {
                if ($request->tipe_penawaran == 'kontrak') {
                    $data      = QuotationKontrakH::find($request->id);
                    $jobTaskId = JobTask::insertGetId([
                        'job'         => 'RenderPdfPenawaran',
                        'status'      => 'processing',
                        'no_document' => $data->no_document,
                        'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    $job = new RenderPdfPenawaran($request->id, 'kontrak');
                    $this->dispatch($job);

                } else {
                    $data      = QuotationNonKontrak::find($request->id);
                    $jobTaskId = JobTask::insertGetId([
                        'job'         => 'RenderPdfPenawaran',
                        'status'      => 'processing',
                        'no_document' => $data->no_document,
                        'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    $job = new RenderPdfPenawaran($request->id, 'non kontrak');
                    $this->dispatch($job);
                }

                return response()->json([
                    'message'     => 'Job rendering PDF sedang diproses.',
                    'job_task_id' => $jobTaskId,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 401);
        }
    }

    public function searchTokenExpired(Request $request)
    {
        try {
            $data = ExpiredLink::where('id_quotation', $request->id)
                ->first();

            if (! $data) {
                return response()->json([
                    'message' => 'Data token expired link tidak ditemukan!',
                ], 404);
            }

            return response()->json([
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function generateToken(Request $request)
    {

        if (! $request->token) {
            return response()->json(['message' => 'Token not found.!', 'status' => '404'], 401);
        }
        $token       = $request->token;
        $expired     = Carbon::now()->addMonths(3)->format('Y-m-d');
        $get_expired = DB::table('expired_link_quotation')
            ->where('token', $token)
            ->first();

        if ($get_expired) {
            $bodyToken = (object) $get_expired; 
            unset($bodyToken->id);             
            $bodyToken->expired = $expired;     

            $id_quotation     = $bodyToken->id_quotation;
            $quotation_status = $bodyToken->quotation_status;

            if ($quotation_status == 'non_kontrak') {
                $data = QuotationNonKontrak::where('id', $id_quotation)->first();
            } else if ($quotation_status == 'kontrak') {
                $data = QuotationKontrakH::where('id', $id_quotation)->first();
            }

            $bodyToken = (array) $bodyToken;
            if ($data) {
                $id_token       = GenerateLink::insertGetId($bodyToken);
                $data->expired  = $expired;
                $data->id_token = $id_token;
                $data->save();

                return response()->json(['message' => 'Token has been reactivated', 'status' => '200'], 200);
            } else {
                return response()->json(['message' => 'Token not found', 'status' => '404'], 200);
            }
        } else {
            return response()->json(['message' => 'Token not found', 'status' => '404'], 200);
        }
    }

}
