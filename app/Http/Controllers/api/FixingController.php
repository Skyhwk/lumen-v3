<?php
namespace App\Http\Controllers\api;

use App\Helpers\Fixing;
use App\Helpers\Slice;
use App\Http\Controllers\Controller;
use App\Jobs\RenderPdfPenawaran;
use App\Models\AksesMenu;
use App\Models\ExpiredLink;
use App\Models\GenerateLink;
use App\Models\Jadwal;
use App\Models\JobTask;
use App\Models\MasterKaryawan;
use App\Models\Menu;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterPelanggan;
use App\Models\TemplateAkses;
use App\Services\RenderJadwalKontrakCopy;
use App\Services\RenderKontrakCopy;
use App\Services\RenderNonKontrakCopy;
use App\Services\SalesKpiMonthly;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    public function searchDataPelanggan(Request $request)
    {
        try {
            $data = MasterPelanggan::where('id_pelanggan', $request->id_pelanggan)
            ->where('is_active', true)
            ->whereNotIn('sales_id', ['127'])   
            ->first();
            if (! $data) {
                return response()->json([
                    'message' => 'Data pelanggan tidak ditemukan!',
                ], 404);
            }

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line'    => $th->getLine(),
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

    public function updateCustomer(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            if ($request->tipe_penawaran == 'kontrak') {
                $quotation = QuotationKontrakH::where('no_document', $request->no_document)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            } else {
                $quotation = QuotationNonKontrak::where('no_document', $request->no_document)
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
            }

            if (! $quotation) {
                return response()->json(['message' => 'Quotation not found'], 404);
            }

            $quotation->nama_perusahaan = $request->nama_pelanggan;
            $quotation->konsultan       = $request->nama_konsultan ?: null;
            $quotation->alamat_sampling = $request->alamat_sampling ?: null;
            $quotation->save();

            $orderHeader = OrderHeader::where('no_document', $quotation->no_document)
                ->where('is_active', true)
                ->latest('id')
                ->first();

            if (! $orderHeader) {
                return response()->json(['message' => 'Order Header not found'], 404);
            }

        
            $orderHeader->nama_perusahaan = $request->nama_pelanggan;
            $orderHeader->konsultan       = $request->nama_konsultan ?: null;
            $orderHeader->alamat_sampling = $request->alamat_sampling ?: null;
            $orderHeader->save();

            OrderDetail::where('id_order_header', $orderHeader->id)
                ->update([
                    'nama_perusahaan' => $request->nama_pelanggan,
                    'konsultan'       => $request->nama_konsultan ?: null,
                ]);
            
            Jadwal::where('no_quotation', $request->no_document)
                ->where('is_active', true)
                ->update(['nama_perusahaan' => $request->nama_pelanggan], ['alamat' => $request->alamat_sampling]);

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

    // ===================== PERUBAHAN NO SAMPEL ====================
    public function updateDataNoSampel(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach ($request->list_data as $item) {
                $noSampelLama = $item['no_sampel_lama'];
                $noSampelBaru = $item['no_sampel_baru'];

                // =====================
                // VALIDASI KATEGORI
                // =====================
                $detailLama = OrderDetail::where('no_sampel', $noSampelLama)
                    ->where('is_active', 1)
                    ->first(['kategori_2', 'kategori_3', 'parameter', 'tanggal_terima']);

                $detailBaru = OrderDetail::where('no_sampel', $noSampelBaru)
                    ->where('is_active', 1)
                    ->first(['kategori_2', 'kategori_3', 'parameter']);

                $kategoriLama       = preg_replace('/^\d+-/', '', $detailLama->kategori_2);
                $kategoriBaru       = preg_replace('/^\d+-/', '', $detailBaru->kategori_2);
                $kategoriDetailLama = preg_replace('/^\d+-/', '', $detailLama->kategori_3);
                $kategoriDetailBaru = preg_replace('/^\d+-/', '', $detailBaru->kategori_3);

                if ($kategoriLama !== $kategoriBaru || $kategoriDetailLama !== $kategoriDetailBaru) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Kategori sampel tidak sama: {$noSampelLama} ({$kategoriLama} - {$kategoriDetailLama}) vs {$noSampelBaru} ({$kategoriBaru} - {$kategoriDetailBaru})",
                    ], 500);
                }

                // =====================
                // VALIDASI PARAMETER
                // =====================
               $paramLama = collect(is_array($detailLama->parameter) 
                    ? $detailLama->parameter 
                    : (json_decode($detailLama->parameter, true) ?? [])
                )->sort()->values()->toArray();

                $paramBaru = collect(is_array($detailBaru->parameter) 
                    ? $detailBaru->parameter 
                    : (json_decode($detailBaru->parameter, true) ?? [])
                )->sort()->values()->toArray();

                if ($paramLama !== $paramBaru) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Parameter sampel tidak sama: {$noSampelLama} vs {$noSampelBaru}",
                        'detail' => [
                            'lama' => $paramLama,
                            'baru' => $paramBaru,
                        ]
                    ], 500);
                }

                $tanggalTerima = OrderDetail::where('no_sampel', $noSampelLama)
                    ->where('is_active', 1)
                    ->value('tanggal_terima');

                if ($tanggalTerima) {
                    OrderDetail::where('no_sampel', $noSampelBaru)
                        ->where('is_active', 1)
                        ->update(['tanggal_terima' => $tanggalTerima]);
                }

                $models = Fixing::KategoriModel($kategoriLama);

                if (empty($models)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Kategori '{$kategoriLama}' tidak ditemukan di helper Fixing",
                    ], 422);
                }

                // =====================
                // BACKUP + DUPLICATE (model per kategori)
                // =====================
                $this->backupToSql($models, $noSampelLama);
                $this->backupToSql($models, $noSampelBaru);
                $this->duplicateData($models, $noSampelLama, $noSampelBaru);

                // =====================
                // UPDATE (model global: tftc, tftct)
                // =====================
                $globalModels = Fixing::GlobalUpdateModel();
                $this->updateFromLamaKeBaru($globalModels, $noSampelLama, $noSampelBaru);
            }

            DB::commit();
            return response()->json(['message' => 'Berhasil update no sampel'], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }



    private function backupToSql(array $models, string $noSampel, string $prefix = ''): void
    {
        $safeName  = str_replace('/', '_', $noSampel);
        $directory = public_path('fixing/bckp');
        $fileName  = $prefix ? "{$safeName}_{$prefix}.sql" : "{$safeName}.sql";
        $filePath  = "{$directory}/{$fileName}";

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $sqlContent  = "-- Backup untuk no_sampel: {$noSampel}\n";
        $sqlContent .= "-- Dibuat pada: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($models as $modelClass) {
            $records = $modelClass::where('no_sampel', $noSampel)->get();

            if ($records->isEmpty()) continue;

            $table       = (new $modelClass)->getTable();
            $sqlContent .= "-- Tabel: {$table}\n";

            foreach ($records as $record) {
                $data    = $record->getAttributes();
                $columns = implode(', ', array_keys($data));
                $values  = implode(', ', array_map(function ($val) {
                    if (is_null($val)) return 'NULL';
                    return "'" . addslashes($val) . "'";
                }, array_values($data)));

                $sqlContent .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
            }

            $sqlContent .= "\n";
        }

        file_put_contents($filePath, $sqlContent);
    }


    private function duplicateData(array $models, string $noSampelLama, string $noSampelBaru): void
    {
        $wsValueAirClass = \App\Models\WsValueAir::class;
        $relasiWs        = Fixing::WsValueAirRelasi(); // ['id_colorimetri' => Colorimetri::class, ...]

        // Map: old_id => new_id untuk tiap model relasi
        // Ini dipakai nanti buat update FK di WsValueAir
        $idMapping = []; // ['id_colorimetri' => [old_id => new_id], ...]

        foreach ($models as $modelClass) {
            // WsValueAir diproses terakhir secara terpisah
            if ($modelClass === $wsValueAirClass) continue;

            $records = $modelClass::where('no_sampel', $noSampelLama)->get();

            foreach ($records as $record) {
                $oldId    = $record->id;
                $newData  = $record->replicate()->fill(['no_sampel' => $noSampelBaru]);
                $newData->save();
                $newId = $newData->id;

                // Cek apakah model ini punya FK di WsValueAir
                foreach ($relasiWs as $fkColumn => $relasiModel) {
                    if ($modelClass === $relasiModel) {
                        $idMapping[$fkColumn][$oldId] = $newId;
                    }
                }
            }
        }

        // =====================
        // Sekarang proses WsValueAir
        // =====================
        $wsRecords = $wsValueAirClass::where('no_sampel', $noSampelLama)->get();

        foreach ($wsRecords as $wsRecord) {
            $newWs = $wsRecord->replicate()->fill(['no_sampel' => $noSampelBaru]);

            // Update semua FK ke ID yang baru
            foreach ($relasiWs as $fkColumn => $relasiModel) {
                $oldFkId = $wsRecord->$fkColumn;

                if (!is_null($oldFkId) && isset($idMapping[$fkColumn][$oldFkId])) {
                    $newWs->$fkColumn = $idMapping[$fkColumn][$oldFkId];
                }
            }

            $newWs->save();
        }
    }

    private function updateFromLamaKeBaru(array $models, string $noSampelLama, string $noSampelBaru): void
    {
        $skipKolom = Fixing::SkipKolom();

        foreach ($models as $modelClass) {
            $dataLama = $modelClass::where('no_sample', $noSampelLama)->first();

            if (!$dataLama) continue;

            $dataBaru = $modelClass::where('no_sample', $noSampelBaru)->first();

            if (!$dataBaru) continue;

            // Ambil semua kolom dari data lama yang nilainya tidak null
            // dan bukan kolom yang di-skip
            $kolommYangDicopy = collect($dataLama->getAttributes())
                ->filter(function ($value, $key) use ($skipKolom) {
                    return !is_null($value) && !in_array($key, $skipKolom);
                })
                ->toArray();

            if (empty($kolommYangDicopy)) continue;

            $dataBaru->update($kolommYangDicopy);
        }
    }

    // ===================== END PERUBAHAN NO SAMPEL ====================







    // ==================================== INI PEMISAH TESTING DAN QUERY BACKEND ====================================

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

            foreach ($aksesMenus as $aksesMenu) {
                $akses = $aksesMenu->akses;

                if (is_string($akses)) {
                    $akses = json_decode($akses, true);
                }

                
                if (is_array($akses) && ! empty($akses)) {
                    $updatedAkses = collect($akses)->map(function ($item) use ($parentMap) {
                        $menuName       = $item['name'] ?? '';
                        $item['parent'] = $parentMap[$menuName] ?? '';

                        return $item;
                    })->toArray();

                    $aksesMenu->akses = $updatedAkses;
                    $aksesMenu->save();

                    $updated++;
                }
            }

            return response()->json([
                'message' => "Successfully updated parent for {$updated} akses menu records",
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

    public function exportPelangganBelumOrder(Request $request)
{
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    try {
        $data = MasterPelanggan::with([
            'kontak_pelanggan:id,pelanggan_id,no_tlp_perusahaan',
            'pic_pelanggan:id,pelanggan_id,nama_pic,no_tlp_pic,jabatan_pic',
            'currentSales:id,nama_lengkap',
        ])
        ->whereNotIn('sales_id', [127])
        ->where('is_active', 1)
        ->whereDoesntHave('quotasiNonKontrak')
        ->whereDoesntHave('quotasiKontrak')
        ->whereDoesntHave('kontak_pelanggan', function ($query) {
            $query->whereHas('logWebphone', function ($q) {
                $q->where('created_at', '>=', Carbon::now()->subMonths(3));
            });
        })
        ->get();

        // Setup Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $title = "LAPORAN PELANGGAN BELUM KELUAR QUOTATION - " . strtoupper(Carbon::now()->locale('id')->isoFormat('MMMM YYYY'));

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = [
            'No',
            'ID Pelanggan',
            'Nama Pelanggan',
            'No. Telp Perusahaan',
            'Nama PIC',
            'No. Telp PIC',
            'Jabatan PIC',
            'Sales',
        ];
        $sheet->fromArray($headers, null, 'A2');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343A40']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A2:H2')->applyFromArray($headerStyle);

        $row = 3;
        $no  = 1;

        foreach ($data as $item) {
            $kontak = $item->kontak_pelanggan->first();
            $pic    = $item->pic_pelanggan->first();

            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item->id_pelanggan);
            $sheet->setCellValue('C' . $row, $item->nama_pelanggan);
            $sheet->setCellValue('D' . $row, $kontak->no_tlp_perusahaan ?? '-');
            $sheet->setCellValue('E' . $row, $pic->nama_pic ?? '-');
            $sheet->setCellValue('F' . $row, $pic->no_tlp_pic ?? '-');
            $sheet->setCellValue('G' . $row, $pic->jabatan_pic ?? '-');
            $sheet->setCellValue('H' . $row, $item->currentSales->nama_lengkap ?? '-');

            $row++;
        }

        $lastRow = $row - 1;

        foreach (range('A', 'H') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->getStyle('A3:H' . $lastRow)->getAlignment()->setWrapText(false);
        $sheet->getStyle('A3:H' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A2:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A3');

        $writer   = new Xlsx($spreadsheet);
        $fileName = "Pelanggan_Belum_Quotation_" . date('Ymd_His') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;

    } catch (\Exception $e) {
        dd($e->getMessage(), $e->getLine(), $e->getFile());
    }
}
    

 

}
