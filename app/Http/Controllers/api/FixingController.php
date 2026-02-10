<?php
namespace App\Http\Controllers\api;

use App\Helpers\Slice;
use App\Http\Controllers\Controller;
use App\Jobs\RenderPdfPenawaran;
use App\Models\AksesMenu;
use App\Models\ExpiredLink;
use App\Models\GenerateLink;
use App\Models\Jadwal;
use App\Models\JobTask;
use App\Models\Menu;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\RenderJadwalKontrakCopy;
use App\Services\RenderKontrakCopy;
use App\Services\RenderNonKontrakCopy;
use App\Services\SalesKpiMonthly;
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

                } catch (\Throwable $th) {
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

        } catch (\Throwable $th) {
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
                $tipe_penawaran = 'non_kontrak';
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
                    'message' => 'Job rendering PDF sedang diproses.',
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
                ->where('quotation_status', $request->tipe_penawaran)
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
        $token = $request['token']['token'];
        if (! $token) {
            return response()->json(['message' => 'Token not found.!', 'status' => '404'], 401);
        }
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

    // public function FixStatusJadwal(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         // 1. Ambil semua no_quotation aktif (distinct)
    //         $dataJadwal = Jadwal::where('is_active', true)
    //             ->whereNotNull('no_quotation')
    //             ->where('status', '0')
    //             ->distinct()
    //             ->pluck('no_quotation')
    //             ->toArray();

    //         // dd($dataJadwal);

    //         if (empty($dataJadwal)) {
    //             return response()->json([
    //                 'updated_quotations' => [],
    //                 'skipped_quotations' => [],
    //                 'count_updated'      => 0,
    //                 'count_skipped'      => 0,
    //             ]);
    //         }
    //         // 2. Ambil semua quotation non kontrak sekaligus
    //         $nonKontrak = QuotationNonKontrak::whereIn('no_document', $dataJadwal)
    //             ->get(['no_document', 'data_lama']);

    //         // 3. Ambil semua quotation kontrak sekaligus
    //         $kontrak = QuotationKontrakH::whereIn('no_document', $dataJadwal)
    //             ->get(['no_document', 'data_lama']);

    //         // 4. Gabung ke dalam 1 map: no_document => data_lama
    //         $quotationMap = [];

    //         foreach ($nonKontrak as $row) {
    //             $quotationMap[$row->no_document] = $row->data_lama;
    //         }

    //         foreach ($kontrak as $row) {
    //             // jangan overwrite kalau sudah ada dari NonKontrak
    //             if (! array_key_exists($row->no_document, $quotationMap)) {
    //                 $quotationMap[$row->no_document] = $row->data_lama;
    //             }
    //         }

    //         $updatedQuotations = [];
    //         $skippedQuotations = [];

    //         // 5. Proses tiap no_quotation
    //         foreach ($dataJadwal as $noQuotation) {
    //             // Kalau tidak ada quotation sama sekali ⇒ skip
    //             if (! array_key_exists($noQuotation, $quotationMap)) {
    //                 $skippedQuotations[] = $noQuotation;
    //                 continue;
    //             }

    //             $dataLamaRaw = $quotationMap[$noQuotation];
    //             $hasIdOrder  = false;

    //             if (! empty($dataLamaRaw)) {
    //                 $dataLama = json_decode($dataLamaRaw, true);
    //                 if (! empty($dataLama['id_order'] ?? null)) {
    //                     $hasIdOrder = true;
    //                 }
    //             }

    //             if (! $hasIdOrder) {
    //                 $skippedQuotations[] = $noQuotation;
    //                 continue;
    //             }

    //             // Masukkan ke list updated, nanti di-update sekaligus
    //             $updatedQuotations[] = $noQuotation;
    //         }

    //         // 6. Update Jadwal hanya untuk quotation yang valid, sekaligus
    //         if (! empty($updatedQuotations)) {
    //             Jadwal::whereIn('no_quotation', $updatedQuotations)
    //                 ->where('is_active', true)
    //                 ->update([
    //                     'status' => 1,
    //                 ]);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'updated_quotations' => $updatedQuotations,
    //             'skipped_quotations' => $skippedQuotations,
    //             'count_updated'      => count($updatedQuotations),
    //             'count_skipped'      => count($skippedQuotations),
    //         ]);
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    public function updateCustomer(Request $request)
    {
        DB::beginTransaction();
        try {
            if (str_contains($request->quotation_number, 'QTC')) {
                $quotation = QuotationKontrakH::where('no_document', $request->quotation_number)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            } else if (str_contains($request->quotation_number, 'QT/')) {
                $quotation = QuotationNonKontrak::where('no_document', $request->quotation_number)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            }

            if (! $quotation) {
                return response()->json(['message' => 'Quotation not found'], 404);
            }

            $quotation->nama_perusahaan = $request->customer_name;
            $quotation->konsultan       = $request->consultant_name ?: null;
            $quotation->save();

            $orderHeader = OrderHeader::where('no_document', $quotation->no_document)
                ->where('is_active', true)
                ->latest('id')
                ->first();

            if ($orderHeader) {
                $orderHeader->nama_perusahaan = $request->customer_name;
                $orderHeader->konsultan       = $request->consultant_name ?: null;
                $orderHeader->save();

                OrderDetail::where('id_order_header', $orderHeader->id)
                    ->update([
                        'nama_perusahaan' => $request->customer_name,
                        'konsultan'       => $request->consultant_name ?: null,
                    ]);
            }

            Jadwal::where('no_quotation', $request->quotation_number)
                ->where('is_active', true)
                ->update(['nama_perusahaan' => $request->customer_name]);

            DB::commit();
            return response()->json(['message' => 'Customer updated successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update customer'], 500);
        }
    }

    public function renderJadwalCopy(Request $request)
    {
        try {

            $timestamp = Carbon::now()->format('Y-m-d H:i:s');

            $dataRequest = (object) [
                'no_document'  => $request->no_quotation,
                'quotation_id' => $request->quotation_id,
                'karyawan'     => $this->karyawan,
                'karyawan_id'  => $this->user_id,
                'timestamp'    => $timestamp,
            ];
            (new RenderJadwalKontrakCopy($dataRequest))
                ->where('quotation_id', $request->quotation_id)
                ->where('tanggal_penawaran', $request->tanggal_penawaran)
                ->servisRenderJadwal();

            return response()->json([
                'status'  => true,
                'message' => 'Berhasil generate jadwal',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function encrypt(Request $request)
    {
        return response()->json(['slice' => Slice::makeSlice($request->controller_name, $request->method_name)], 200);
    }

    public function decrypt(Request $request)
    {
        $decryptedSlice = Slice::makeDecrypt($request->slice);
        $route          = json_decode($decryptedSlice);

        if (empty($route->controller) || empty($route->function)) {
            return response()->json(['error' => 'Invalid slice'], 400);
        }

        return response()->json(['controller' => $route->controller, 'method' => $route->function], 200);
    }

    public function test()
    {
        $data = SalesKpiMonthly::run();

        return response()->json($data, 200);
    }


    public function updateParentAksesMenu(Request $request)
    {

        try {
            $data = Menu::where('is_active', true)->get();

            $parentMap = $this->buildParentMapping($data);

            $aksesMenus = AksesMenu::all();

            $updated = 0;
            $details = [];

            foreach ($aksesMenus as $aksesMenu) {
                $akses = $aksesMenu->akses;

                if (is_array($akses) && ! empty($akses)) {
                    $updatedAkses = collect($akses)->map(function ($item) use ($parentMap) {
                        $menuName       = $item['name'] ?? '';
                        $item['parent'] = $parentMap[$menuName] ?? '';

                        return $item;
                    })->toArray();

                    $aksesMenu->akses = $updatedAkses;
                    $aksesMenu->save();

                    $updated++;
                    $details[] = [
                        'user_id'    => $aksesMenu->user_id,
                        'total_menu' => count($updatedAkses),
                    ];
                }
            }

            return response()->json([
                'message' => "Successfully updated parent for {$updated} akses menu records",
                'updated_count' => $updated,
                'details'       => $details,
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function buildParentMapping($menus)
    {
        $parentMap = [];

        foreach ($menus as $menu) {
            $parentName = $menu->menu;

            if (isset($menu->submenu) && is_array($menu->submenu)) {
                foreach ($menu->submenu as $submenu) {
                    $submenu     = (object) $submenu;
                    $submenuName = $submenu->nama_inden_menu;

                    $parentMap[$submenuName] = $parentName;

                    // Level 3: Menu di bawah submenu
                    if (isset($submenu->sub_menu) && is_array($submenu->sub_menu)) {
                        foreach ($submenu->sub_menu as $subMenuItem) {
                            $parentMap[$subMenuItem] = $parentName . '/' . $submenuName;
                        }
                    }
                }
            }
        }

        return $parentMap;
    }

}
