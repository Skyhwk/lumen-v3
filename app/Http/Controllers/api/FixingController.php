<?php

namespace App\Http\Controllers\api;

use App\Helpers\Fixing;
use App\Helpers\Slice;
use App\Http\Controllers\Controller;
use App\Jobs\RenderPdfPenawaran;
use App\Models\AksesMenu;
use App\Models\ExpiredLink;
use App\Models\GenerateLink;
use App\Models\HistoryLevelSampler;
use App\Models\Invoice;
use App\Models\Jadwal;
use App\Models\JobTask;
use App\Models\LinkLhp;
use App\Models\MasterFeeSampling;
use App\Models\MasterKaryawan;
use App\Models\Menu;
use App\Models\OrderDetail;
use App\Models\DataLapanganCahaya;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterPelanggan;
use App\Models\PengajuanFeeSampling;
use App\Models\PengajuanFeeSamplingDetail;
use App\Models\RecordPembayaranInvoice;
use App\Models\SalesInDetail;
use App\Models\SummaryInvoice;
use App\Models\TemplateAkses;
use App\Models\Withdraw;

// model LHP
use App\Models\LhpsAdverseOdorHeader;
use App\Models\LhpsAirHeader;
use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsEmisiIsokinetikHeader;
use App\Models\LhpsErgonomiHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsHygieneSanitasiHeader;
use App\Models\LhpsIklimHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsKebisinganPersonalHeader;
use App\Models\LhpsLingHeader;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMicrobiologiHeader;
use App\Models\LhpsPadatanHeader;
use App\Models\LhpsPencahayaanHeader;
use App\Models\LhpsSinarUVHeader;
use App\Models\LhpsSwabTesHeader;
use App\Models\LhpUdaraPsikologiHeader;
// end model LHP


use App\Services\GenerateFeeSampling;
use App\Services\RenderInvoice;
use App\Services\RenderInvoiceTitik;
use App\Services\RenderJadwalKontrakCopy;
use App\Services\RenderKontrakCopy;
use App\Services\RenderNonKontrakCopy;
use App\Services\SalesKpiMonthly;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FixingController extends Controller
{

    public function syncLinkLhpRilis(Request $request)
    {
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        $limit = (int) $request->input('limit', 0);

        $summary = [
            'checked' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_empty_lhp' => 0,
            'missing_order_berjalan' => 0,
            'errors' => 0,
            'dry_run' => $dryRun,
        ];
        $changes = [];
        $missing = [];
        $errors = [];

        try {
            $query = LinkLhp::query()
                ->select([
                    'id',
                    'no_order',
                    'periode',
                    'jumlah_lhp',
                    'jumlah_lhp_rilis',
                    'list_lhp_rilis',
                    'is_completed',
                ])
                ->orderBy('id');

            if ($limit > 0) {
                $query->limit($limit);
            }

            $linkRows = $query->get();
            $orderBerjalanByOrder = DB::table('order_berjalan')
                ->whereIn('no_order', $linkRows->pluck('no_order')->filter()->unique()->values())
                ->pluck('dataOrderDetail', 'no_order');

            foreach ($linkRows as $linkLhp) {
                $summary['checked']++;

                try {
                    $dataOrderDetail = $orderBerjalanByOrder[$linkLhp->no_order] ?? null;
                    if (!$dataOrderDetail) {
                        $summary['missing_order_berjalan']++;
                        $missing[] = [
                            'id' => $linkLhp->id,
                            'no_order' => $linkLhp->no_order,
                            'periode' => $linkLhp->periode,
                        ];
                        continue;
                    }

                    $calculated = $this->calculateLhpRilisFromOrderBerjalan($dataOrderDetail, $linkLhp->periode);
                    if ($calculated['jumlah_lhp'] === 0) {
                        $summary['skipped_empty_lhp']++;
                        continue;
                    }

                    $currentList = $this->normalizeJsonArray($linkLhp->list_lhp_rilis);

                    $needsUpdate =
                        (int) $linkLhp->jumlah_lhp !== $calculated['jumlah_lhp'] ||
                        (int) $linkLhp->jumlah_lhp_rilis !== $calculated['jumlah_lhp_rilis'] ||
                        (int) $linkLhp->is_completed !== (int) $calculated['is_completed'] ||
                        $currentList !== $calculated['list_lhp_rilis'];

                    if (!$needsUpdate) {
                        $summary['unchanged']++;
                        continue;
                    }

                    $change = [
                        'id' => $linkLhp->id,
                        'no_order' => $linkLhp->no_order,
                        'periode' => $linkLhp->periode,
                        'before' => [
                            'jumlah_lhp' => (int) $linkLhp->jumlah_lhp,
                            'jumlah_lhp_rilis' => (int) $linkLhp->jumlah_lhp_rilis,
                            'is_completed' => (int) $linkLhp->is_completed,
                            'list_lhp_rilis' => $currentList,
                        ],
                        'after' => $calculated,
                    ];
                    $changes[] = $change;

                    if (!$dryRun) {
                        $linkLhp->update([
                            'jumlah_lhp' => $calculated['jumlah_lhp'],
                            'jumlah_lhp_rilis' => $calculated['jumlah_lhp_rilis'],
                            'list_lhp_rilis' => json_encode($calculated['list_lhp_rilis']),
                            'is_completed' => $calculated['is_completed'],
                            'updated_by' => $this->karyawan,
                            'updated_at' => Carbon::now(),
                        ]);
                    }

                    $summary['updated']++;
                } catch (\Throwable $th) {
                    $summary['errors']++;
                    $errors[] = [
                        'id' => $linkLhp->id,
                        'no_order' => $linkLhp->no_order,
                        'periode' => $linkLhp->periode,
                        'message' => $th->getMessage(),
                    ];
                }
            }

            return response()->json([
                'message' => $dryRun ? 'Preview sync link LHP selesai.' : 'Sync link LHP selesai.',
                'summary' => $summary,
                'changes' => array_slice($changes, 0, 100),
                'missing_order_berjalan' => array_slice($missing, 0, 100),
                'errors' => array_slice($errors, 0, 100),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal sync link LHP: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function calculateLhpRilisFromOrderBerjalan($dataOrderDetail, ?string $periode = null): array
    {
        $decoded = is_string($dataOrderDetail)
            ? json_decode($dataOrderDetail, true)
            : $dataOrderDetail;

        if (!is_array($decoded)) {
            throw new \Exception('dataOrderDetail bukan JSON valid.');
        }

        $periodGroups = collect($decoded)
            ->filter(function ($group) use ($periode) {
                if ($periode === null || $periode === '') {
                    return true;
                }

                return (string) ($group['periode'] ?? '') === (string) $periode;
            })
            ->values();

        $details = $periodGroups
            ->flatMap(function ($group) {
                return $group['detail'] ?? [];
            })
            ->filter(fn ($detail) => !empty($detail['cfr']))
            ->unique('cfr')
            ->values();

        $listLhpRilis = $details
            ->filter(fn ($detail) => (bool) ($detail['lhp_rilis'] ?? false))
            ->pluck('cfr')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values()
            ->toArray();

        $jumlahLhp = $details->count();
        $jumlahLhpRilis = count($listLhpRilis);

        return [
            'jumlah_lhp' => $jumlahLhp,
            'jumlah_lhp_rilis' => $jumlahLhpRilis,
            'list_lhp_rilis' => $listLhpRilis,
            'is_completed' => $jumlahLhp > 0 && $jumlahLhp === $jumlahLhpRilis ? 1 : 0,
        ];
    }

    private function normalizeJsonArray($value): array
    {
        if (is_array($value)) {
            $data = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $data = json_decode($value, true);
        } else {
            $data = [];
        }

        if (!is_array($data)) {
            return [];
        }

        return collect($data)
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values()
            ->toArray();
    }

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
        $decryptedSlice = Slice::makeDecrypt($request->slice, false);
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
                $paramLama = collect(
                    is_array($detailLama->parameter)
                        ? $detailLama->parameter
                        : (json_decode($detailLama->parameter, true) ?? [])
                )->sort()->values()->toArray();

                $paramBaru = collect(
                    is_array($detailBaru->parameter)
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

    public function searchFeeSampling(Request $request)
    {
        $res = $this->processFeeSampling($request->batch_id, $request->tanggal);

        if (isset($res['error'])) {
            return response()->json(['message' => $res['error']], $res['code']);
        }

        $isDifferent = $res['current_fee'] != $res['updated_fee'];

        return response()->json([
            'message' => $isDifferent
                ? 'Berhasil mengambil data terbaru'
                : 'Tidak terdapat perbedaan fee sampling',
            'status' => $isDifferent,
            'data' => [
                'current_fee' => $res['current_fee'],
                'updated_fee' => $res['updated_fee'],
                'details' => $res['details'],
            ]
        ]);
    }

    public function updateFeeSampling(Request $request)
    {
        DB::beginTransaction();

        try {
            $res = $this->processFeeSampling($request->batch_id, $request->tanggal);

            if (isset($res['error'])) {
                return response()->json(['message' => $res['error']], $res['code']);
            }

            $fee = $res['fee_sampling'];
            $rekap = $res['rekap'];

            $fee->update([
                'total_fee_request' => $res['updated_fee']
            ]);

            foreach ($rekap['harian'] as $item) {
                PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $fee->id)
                    ->where('tanggal', $item['tanggal'])
                    ->update([
                        'total_fee' => $item['total_fee'],
                        'fee_pokok' => $item['fee_pokok'],
                        'fee_tambahan' => $item['fee_tambahan'],
                        'jumlah_tempat' => $item['jumlah_tempat'],
                        'rincian_fee_pokok' => $item['rincian_fee_pokok'],
                        'fee_tambahan_rincian' => $item['fee_tambahan_rincian'],
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Berhasil mengupdate fee sampling',
                'status' => true
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => $th->getMessage(),
                'status' => false
            ], 500);
        }
    }

    private function processFeeSampling($batch_id, $tanggal = [])
    {
        $fee_sampling = PengajuanFeeSampling::with('detail_fee')
            ->where('batch_id', $batch_id)
            ->first();

        if (!$fee_sampling) {
            return ['error' => 'Data tidak ditemukan', 'code' => 404];
        }
        $tanggal = $fee_sampling->detail_fee->pluck('tanggal')->toArray();
        $master = MasterKaryawan::find($fee_sampling->user_id);

        if (!$master || !$master->warna) {
            return ['error' => 'Level Sampler Belum Ditentukan', 'code' => 401];
        }

        $history = HistoryLevelSampler::where('user_id', $master->user_id)
            ->latest()
            ->first();
        $warnaFinal = $master->warna;
        if ($history && !empty($tanggal)) {
            $changeDate = Carbon::parse($history->created_at)->startOfDay();

            $before = collect($tanggal)->contains(fn($t) => Carbon::parse($t)->lt($changeDate));
            $after  = collect($tanggal)->contains(fn($t) => Carbon::parse($t)->gte($changeDate));

            if ($before && $after) {
                return [
                    'error' => 'Tanggal pengajuan tidak boleh melewati perubahan level sampler',
                    'code' => 422
                ];
            }

            $warnaFinal = $before ? $history->old_warna : $history->new_warna;
        }
        $level = MasterFeeSampling::where('warna', $warnaFinal)
            ->where('is_active', true)
            ->first();

        if (!$level) {
            return ['error' => 'Level Sampler Tidak Ditemukan', 'code' => 404];
        }

        $generate = new GenerateFeeSampling();

        $rekap = $generate->rekapFeeSampling(
            $fee_sampling->user_id,
            $level->kategori,
            $fee_sampling->detail_fee->pluck('tanggal')->toArray()
        );

        $detailNow = $fee_sampling->detail_fee->keyBy('tanggal');

        $details = collect($rekap['harian'])->map(function ($item) use ($detailNow) {
            $current = $detailNow[$item['tanggal']] ?? null;

            return [
                'tanggal' => $item['tanggal'],

                'current' => [
                    'total_fee' => $current->total_fee ?? 0,
                    'fee_pokok' => $current->fee_pokok ?? 0,
                    'fee_tambahan' => $current->fee_tambahan ?? 0,
                    'jumlah_tempat' => $current->jumlah_tempat ?? 0,
                    'rincian_fee_pokok' => $current->rincian_fee_pokok ?? null,
                    'fee_tambahan_rincian' => $current->fee_tambahan_rincian ?? null,
                ],

                'updated' => [
                    'total_fee' => $item['total_fee'] ?? 0,
                    'fee_pokok' => $item['fee_pokok'] ?? 0,
                    'fee_tambahan' => $item['fee_tambahan'] ?? 0,
                    'jumlah_tempat' => $item['jumlah_tempat'] ?? 0,
                    'rincian_fee_pokok' => $item['rincian_fee_pokok'] ?? null,
                    'fee_tambahan_rincian' => $item['fee_tambahan_rincian'] ?? null,
                ]
            ];
        })->values();

        return [
            'fee_sampling' => $fee_sampling,
            'rekap' => $rekap,
            'current_fee' => $fee_sampling->total_fee_request,
            'updated_fee' => $rekap['total_mingguan'],
            'details' => $details,
        ];
    }

    // public function exportPelangganBelumOrder(Request $request)
    // {
    //     ini_set('memory_limit', '-1');
    //     set_time_limit(0);

    //     try {
    //         $data = MasterPelanggan::with([
    //             'kontak_pelanggan:id,pelanggan_id,no_tlp_perusahaan',
    //             'pic_pelanggan:id,pelanggan_id,nama_pic,no_tlp_pic,jabatan_pic',
    //         ])
    //         ->whereNotIn('sales_id', [127])
    //         ->whereNotNull('sales_id')
    //         ->where('is_active', 1)
    //         ->whereDoesntHave('quotasiNonKontrak')
    //         ->whereDoesntHave('quotasiKontrak')
    //         ->whereYear('created_at', Carbon::now()->subYear(1))
    //         // ->whereDoesntHave('kontak_pelanggan', function ($query) {
    //         //     $query->whereHas('logWebphone', function ($q) {
    //         //         $q->where('created_at', '>=', Carbon::now()->subMonths(3));
    //         //     });
    //         // })
    //         ->get();

    //         // Setup Spreadsheet
    //         $spreadsheet = new Spreadsheet();
    //         $sheet       = $spreadsheet->getActiveSheet();

    //         $title = "LAPORAN PELANGGAN BELUM KELUAR QUOTATION - " . strtoupper(Carbon::now()->locale('id')->isoFormat('MMMM YYYY'));

    //         $sheet->setCellValue('A1', $title);
    //         $sheet->mergeCells('A1:G1'); // 7 kolom A-G
    //         $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    //         $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //         $headers = [
    //             'No',
    //             'ID Pelanggan',
    //             'Nama Pelanggan',
    //             'No. Telp Perusahaan',
    //             'Nama PIC',
    //             'No. Telp PIC',
    //             'Jabatan PIC',
    //         ];
    //         $sheet->fromArray($headers, null, 'A2');

    //         $headerStyle = [
    //             'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    //             'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    //             'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343A40']],
    //             'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    //         ];
    //         $sheet->getStyle('A2:G2')->applyFromArray($headerStyle); // fix: A2:G2

    //         $row = 3;
    //         $no  = 1;

    //         foreach ($data as $item) {
    //             $kontak = $item->kontak_pelanggan->first();
    //             $pic    = $item->pic_pelanggan->first();

    //             $sheet->setCellValue('A' . $row, $no++);
    //             $sheet->setCellValue('B' . $row, $item->id_pelanggan);
    //             $sheet->setCellValue('C' . $row, $item->nama_pelanggan);
    //             $sheet->setCellValue('D' . $row, $kontak->no_tlp_perusahaan ?? '-');
    //             $sheet->setCellValue('E' . $row, $pic->nama_pic ?? '-');
    //             $sheet->setCellValue('F' . $row, $pic->no_tlp_pic ?? '-');
    //             $sheet->setCellValue('G' . $row, $pic->jabatan_pic ?? '-');

    //             $row++;
    //         }

    //         $lastRow = $row - 1;

    //         foreach (range('A', 'G') as $columnID) { // fix: A-G
    //             $sheet->getColumnDimension($columnID)->setAutoSize(true);
    //         }

    //         $sheet->getStyle('A3:G' . $lastRow)->getAlignment()->setWrapText(false); // fix: A3:G
    //         $sheet->getStyle('A3:G' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    //         $sheet->getStyle('A2:G' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN); // fix: A2:G
    //         $sheet->freezePane('A3');

    //         $writer   = new Xlsx($spreadsheet);
    //         $fileName = "Pelanggan_Belum_Quotation_" . date('Ymd_His') . ".xlsx";

    //         header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //         header('Content-Disposition: attachment; filename="' . $fileName . '"');
    //         header('Cache-Control: max-age=0');

    //         $writer->save('php://output');
    //         exit;

    //     } catch (\Exception $e) {
    //         dd($e->getMessage(), $e->getLine(), $e->getFile());
    //     }
    // }

    public function checkInvoiceDetail(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        try {
            $invoiceRows = Invoice::with(['recordPembayaran.sales_in_detail.header', 'recordWithdraw.sales_in_detail.header'])
                ->where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $summary = SummaryInvoice::where('no_invoice', $noInvoice)->first();
            $payments = RecordPembayaranInvoice::with('sales_in_detail.header')
                ->where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('tgl_pembayaran')
                ->orderBy('id')
                ->get();

            $withdraws = Withdraw::with('sales_in_detail.header')
                ->where('no_invoice', $noInvoice)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $salesInDetails = SalesInDetail::with('header')
                ->where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            $firstInvoice = $invoiceRows->first();
            $totalTagihan = (float) ($summary->total_tagihan ?? 0);
            if ($totalTagihan <= 0) {
                $totalTagihan = (float) $invoiceRows->sum(function ($item) {
                    return (float) ($item->nilai_tagihan ?? $item->total_tagihan ?? 0);
                });
            }

            $totalPembayaran = (float) $payments->sum('nilai_pembayaran');
            $totalPengurangan = (float) $payments->sum('nilai_pengurangan');
            $totalWithdraw = (float) $withdraws->sum('nilai_pembayaran');
            $nilaiPelunasanInvoice = (float) $invoiceRows->sum('nilai_pelunasan');
            $lebihBayar = max(
                (float) $invoiceRows->sum('lebih_bayar'),
                (float) $payments->sum('lebih_bayar'),
                max(0, ($totalPembayaran + $totalPengurangan) - $totalTagihan)
            );
            $sisaBayar = max(0, $totalTagihan - $totalPembayaran - $totalPengurangan);

            if ($lebihBayar > 0) {
                $status = 'Kelebihan Pembayaran';
            } elseif ($sisaBayar <= 0) {
                $status = 'Lunas';
            } elseif ($totalPembayaran > 0 || $totalPengurangan > 0 || $totalWithdraw > 0) {
                $status = 'Belum Lunas';
            } else {
                $status = 'Belum Ada Pembayaran';
            }

            $filename = $summary->filename ?? $firstInvoice->filename ?? null;

            return response()->json([
                'message' => 'Data invoice ditemukan.',
                'data' => [
                    'invoice' => $firstInvoice,
                    'invoice_rows' => $invoiceRows->values(),
                    'summary' => $summary,
                    'calculation' => [
                        'total_tagihan' => $totalTagihan,
                        'total_pembayaran' => $totalPembayaran,
                        'total_pengurangan' => $totalPengurangan,
                        'total_withdraw' => $totalWithdraw,
                        'nilai_pelunasan_invoice' => $nilaiPelunasanInvoice,
                        'lebih_bayar' => $lebihBayar,
                        'sisa_bayar' => $sisaBayar,
                        'status' => $summary->status_lunas ?? $status,
                    ],
                    'payments' => $payments->values(),
                    'withdraws' => $withdraws->values(),
                    'sales_in_details' => $salesInDetails->values(),
                    'pdf' => [
                        'filename' => $filename,
                        'path' => $filename ? 'invoice/' . $filename : null,
                    ],
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
    public function checkSmallInvoiceSignature(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $totalTagihan = $this->getInvoiceTotalTagihan($noInvoice, $invoiceRows);
            $firstInvoice = $invoiceRows->first();

            return response()->json([
                'message' => 'Data invoice ditemukan.',
                'data' => [
                    'invoice' => $firstInvoice,
                    'total_tagihan' => $totalTagihan,
                    'is_below_threshold' => $totalTagihan < 5000000,
                    'can_use_fixing' => $totalTagihan < 5000000,
                    'filename' => $firstInvoice->filename,
                    'upload_file' => $firstInvoice->upload_file,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function renderSmallInvoiceSignature(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $totalTagihan = $this->getInvoiceTotalTagihan($noInvoice, $invoiceRows);
            if ($totalTagihan >= 5000000) {
                return response()->json([
                    'message' => 'Invoice ini sudah Rp 5 juta ke atas. Gunakan flow invoice biasa.',
                ], 422);
            }

            $render = new RenderInvoice();
            $render->renderInvoice($noInvoice, true, true);

            $invoice = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'message' => 'Template invoice dengan area tanda tangan berhasil dirender.',
                'data' => [
                    'filename' => $invoice->filename,
                    'path' => $invoice->filename ? 'invoice/' . $invoice->filename : null,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function uploadSmallInvoiceSignature(Request $request)
    {
        $noInvoice = trim((string) $request->no_invoice);

        if ($noInvoice === '') {
            return response()->json([
                'message' => 'Nomor invoice wajib diisi.',
            ], 422);
        }

        $file = $request->file('file_input');
        if (!$file || strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            return response()->json([
                'message' => 'File tidak valid. Harus PDF.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $invoiceRows = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($invoiceRows->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $totalTagihan = $this->getInvoiceTotalTagihan($noInvoice, $invoiceRows);
            if ($totalTagihan >= 5000000) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invoice ini sudah Rp 5 juta ke atas. Gunakan flow invoice biasa.',
                ], 422);
            }

            $fileName = 'INVOICE_' . preg_replace('/\\//', '_', $noInvoice) . '.pdf';
            $folder = public_path('invoice-upload');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            $file->move($folder, $fileName);

            Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->update([
                    'upload_file' => $fileName,
                ]);

            DB::commit();

            $render = new RenderInvoice();
            $render->renderInvoice($noInvoice);

            $invoice = Invoice::where('no_invoice', $noInvoice)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'message' => 'PDF tanda tangan berhasil diupload dan invoice final berhasil dirender.',
                'data' => [
                    'filename' => $invoice->filename,
                    'upload_file' => $invoice->upload_file,
                    'path' => $invoice->filename ? 'invoice/' . $invoice->filename : null,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    private function getInvoiceTotalTagihan(string $noInvoice, $invoiceRows): float
    {
        $summary = SummaryInvoice::where('no_invoice', $noInvoice)->first();
        $totalTagihan = (float) ($summary->total_tagihan ?? 0);

        if ($totalTagihan <= 0) {
            $totalTagihan = (float) $invoiceRows->sum(function ($item) {
                return (float) ($item->nilai_tagihan ?? $item->total_tagihan ?? 0);
            });
        }

        return $totalTagihan;
    }
    public function renderInvoice(Request $request)
    {
        if ($request->is_copy && $request->is_copy != 'false') {
            foreach ($request->invoice_numbers as $item) {
                $render = new RenderInvoiceTitik();
                $render->renderInvoice($item);
            }
        } else {
            foreach ($request->invoice_numbers as $item) {
                $render = new RenderInvoice();
                $render->renderInvoice($item);
            }
        }

        return response()->json(['message' => 'Invoice has been rendered successfully'], 200);
    }

    public function fixInvoiceNumber(Request $request)
    {

        $oldInvoiceNumber = trim($request->no_invoice);
        $targetYear = (string) $request->tahun;
        $lockName = 'invoice-fix-' . $targetYear;
        $lockAcquired = false;
        $transactionStarted = false;

        try {
            $lockResult = DB::select('SELECT GET_LOCK(?, 30) AS acquired', [$lockName]);
            $lockAcquired = isset($lockResult[0]) && (int) $lockResult[0]->acquired === 1;

            if (!$lockAcquired) {
                return response()->json([
                    'message' => 'Gagal mendapatkan lock untuk generate nomor invoice.',
                ], 423);
            }

            $invoice = Invoice::where('no_invoice', $oldInvoiceNumber)
                ->where('is_active', true)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'message' => 'Invoice tidak ditemukan atau tidak aktif.',
                ], 404);
            }

            $newInvoiceNumber = $this->generateFixedInvoiceNumber($invoice, $targetYear);

            if ($newInvoiceNumber === $oldInvoiceNumber) {
                return response()->json([
                    'message' => 'Nomor invoice sudah sesuai dengan tahun yang dipilih.',
                    'data' => [
                        'old_invoice' => $oldInvoiceNumber,
                        'new_invoice' => $newInvoiceNumber,
                    ],
                ], 200);
            }

            $exists = Invoice::where('no_invoice', $newInvoiceNumber)
                ->where('id', '!=', $invoice->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => "Nomor invoice {$newInvoiceNumber} sudah digunakan.",
                ], 422);
            }

            DB::beginTransaction();
            $transactionStarted = true;

            $updatedTables = [];
            $updatedTables['invoice'] = Invoice::where('id', $invoice->id)->update([
                'no_invoice' => $newInvoiceNumber,
                'tgl_invoice' => $this->replaceYear($invoice->tgl_invoice, $targetYear),
                'filename' => null,
                'is_generate' => 0,
            ]);

            foreach ($this->invoiceReferenceTables() as $table) {
                $updated = $this->updateInvoiceReference($table, 'no_invoice', $oldInvoiceNumber, $newInvoiceNumber);
                if ($updated > 0) {
                    $updatedTables[$table] = $updated;
                }
            }

            $this->updateInvoiceQrDocument($oldInvoiceNumber, $newInvoiceNumber, $invoice, $targetYear);

            DB::commit();
            $transactionStarted = false;

            $render = new RenderInvoice();
            $render->renderInvoice($newInvoiceNumber);

            return response()->json([
                'message' => "Nomor invoice berhasil diubah menjadi {$newInvoiceNumber} dan invoice sudah dirender ulang.",
                'data' => [
                    'old_invoice' => $oldInvoiceNumber,
                    'new_invoice' => $newInvoiceNumber,
                    'updated_tables' => $updatedTables,
                ],
            ], 200);
        } catch (\Throwable $th) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        } finally {
            if ($lockAcquired) {
                DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    private function generateFixedInvoiceNumber(Invoice $invoice, string $targetYear): string
    {
        $rekening = $invoice->rekening == '4976688988' ? 'ppn' : 'non-ppn';
        $shortYear = substr($targetYear, -2);

        $lastInvoice = Invoice::where('rekening', $invoice->rekening)
            ->where('id', '!=', $invoice->id)
            ->whereYear('tgl_invoice', $targetYear)
            ->where('no_invoice', 'like', '%' . $shortYear . '%')
            ->orderBy('no_invoice', 'desc')
            ->value('no_invoice');

        $prefix = $rekening == 'ppn' ? 'INV' : 'IV';
        $defaultNo = $targetYear == '2024'
            ? ($rekening == 'ppn' ? '06767' : '00531')
            : '00001';

        $no = $lastInvoice ? str_pad(intval(substr($lastInvoice, -5)) + 1, 5, '0', STR_PAD_LEFT) : $defaultNo;

        return "ISL/{$prefix}/{$shortYear}{$no}";
    }

    private function replaceYear($date, string $targetYear): string
    {
        $invoiceDate = $date ? Carbon::parse($date) : Carbon::now();
        $day = min($invoiceDate->day, Carbon::create((int) $targetYear, $invoiceDate->month, 1)->daysInMonth);

        return $invoiceDate
            ->setYear((int) $targetYear)
            ->setDay($day)
            ->format('Y-m-d H:i:s');
    }

    private function invoiceReferenceTables(): array
    {
        return [
            'record_pembayaran_invoice',
            'sales_in_detail',
            'withdraw',
            'summary_invoice',
            'billing_list_detail',
            'distribusi_invoice_detail',
            'claim_fee_external',
            'claim_fee_external_expense',
            'claim_fee_external_tax',
        ];
    }

    private function updateInvoiceReference(string $table, string $column, string $oldInvoiceNumber, string $newInvoiceNumber): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->where($column, $oldInvoiceNumber)
            ->update([$column => $newInvoiceNumber]);
    }

    private function updateInvoiceQrDocument(string $oldInvoiceNumber, string $newInvoiceNumber, Invoice $invoice, string $targetYear): void
    {
        if (!Schema::hasTable('qr_documents')) {
            return;
        }

        $oldFile = str_replace('/', '_', $oldInvoiceNumber);
        $newFile = str_replace('/', '_', $newInvoiceNumber);
        $qr = DB::table('qr_documents')
            ->where('type_document', 'invoice')
            ->where('file', $oldFile)
            ->first();

        if (!$qr) {
            return;
        }

        $data = json_decode($qr->data, true) ?: [];
        $data['no_document'] = $newInvoiceNumber;
        $data['Tanggal_Pengesahan'] = Carbon::parse($this->replaceYear($invoice->tgl_invoice, $targetYear))
            ->locale('id')
            ->isoFormat('DD MMMM YYYY');

        DB::table('qr_documents')
            ->where('id', $qr->id)
            ->update([
                'file' => $newFile,
                'data' => json_encode($data),
            ]);

        $oldPath = public_path('qr_documents/' . $oldFile . '.svg');
        $newPath = public_path('qr_documents/' . $newFile . '.svg');

        if (file_exists($oldPath) && !file_exists($newPath)) {
            @rename($oldPath, $newPath);
        }
    }

    // INI PUNYA DATA LAPANGAN CAHAYA
    public function updateJenisCahaya(Request $request)
    {
        $dataCahaya = DataLapanganCahaya::where('id', $request->id)->first();
        $dataCahaya->jenis_cahaya = $request->jenis_cahaya;
        $dataCahaya->save();
        return response()->json([
            'message' => 'Update jenis cahaya selesai.',
        ], 200);
    }

    public function cekNoSampel(Request $request)
    {
        $noSampel = $request->input('no_sampel');
        $dataCahaya = DataLapanganCahaya::where('no_sampel', $noSampel)->first();
        return response()->json([
            'data' => $dataCahaya,
        ], 200);
    }

    // END PUNYA DATA LAPANGAN CAHAYA

    public function cekLhpDouble(Request $request)
    {
        $noOrder = $request->input('no_order');
        if (!$noOrder) {
            return response()->json(['message' => 'no_order is required'], 400);
        }

        $link = LinkLhp::where('no_order', $noOrder)->get();
        if ($link->isEmpty()) {
            return response()->json(['message' => 'Link Lhp not found'], 404);
        }

        $models = [
            LhpsAdverseOdorHeader::class,
            LhpsAirHeader::class,
            LhpsEmisiCHeader::class,
            LhpsEmisiHeader::class,
            LhpsEmisiIsokinetikHeader::class,
            LhpsErgonomiHeader::class,
            LhpsGetaranHeader::class,
            LhpsHygieneSanitasiHeader::class,
            LhpsIklimHeader::class,
            LhpsKebisinganHeader::class,
            LhpsKebisinganPersonalHeader::class,
            LhpsLingHeader::class,
            LhpsMedanLMHeader::class,
            LhpsMicrobiologiHeader::class,
            LhpsPadatanHeader::class,
            LhpsPencahayaanHeader::class,
            LhpsSinarUVHeader::class,
            LhpsSwabTesHeader::class,
            LhpUdaraPsikologiHeader::class,
        ];

        $duplicates = [];

        foreach ($models as $modelClass) {
            if (!class_exists($modelClass)) continue;
            
            $table = (new $modelClass)->getTable();
            $hasLhp = \Illuminate\Support\Facades\Schema::hasColumn($table, 'no_lhp');
            $hasCfr = \Illuminate\Support\Facades\Schema::hasColumn($table, 'no_cfr');
            $hasIsApprove = \Illuminate\Support\Facades\Schema::hasColumn($table, 'is_approve');
            $hasIsApproved = \Illuminate\Support\Facades\Schema::hasColumn($table, 'is_approved');
            
            $approveColumn = $hasIsApprove ? 'is_approve' : ($hasIsApproved ? 'is_approved' : null);
            
            // Step 1: Find no_lhp that appear more than once for this no_order
            if ($hasLhp) {
                $dupNos = DB::table($table)
                    ->select('no_lhp')
                    ->where('no_order', $noOrder)
                    ->whereNotNull('no_lhp')
                    ->where('no_lhp', '!=', '')
                    ->groupBy('no_lhp')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('no_lhp');

                if ($dupNos->isNotEmpty()) {
                    $selectColumns = ['id', 'no_lhp', 'no_order'];
                    if ($approveColumn) {
                        $selectColumns[] = $approveColumn;
                    }
                    $records = $modelClass::where('no_order', $noOrder)
                        ->whereIn('no_lhp', $dupNos)
                        ->get($selectColumns);
                    
                    foreach ($records as $record) {
                        $duplicates[] = [
                            'id' => $record->id,
                            'no_lhp' => $record->no_lhp,
                            'no_order' => $record->no_order,
                            'is_approve' => $approveColumn ? $record->{$approveColumn} : null,
                            'model' => class_basename($modelClass)
                        ];
                    }
                }
            }

            // Step 2: Find no_cfr that appear more than once for this no_order
            if ($hasCfr) {
                $dupCfrs = DB::table($table)
                    ->select('no_cfr')
                    ->where('no_order', $noOrder)
                    ->whereNotNull('no_cfr')
                    ->where('no_cfr', '!=', '')
                    ->groupBy('no_cfr')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('no_cfr');

                if ($dupCfrs->isNotEmpty()) {
                    $selectColumns = ['id', 'no_cfr', 'no_order'];
                    if ($hasLhp) {
                        $selectColumns[] = 'no_lhp';
                    }
                    if ($approveColumn) {
                        $selectColumns[] = $approveColumn;
                    }
                    $recordsCfr = $modelClass::where('no_order', $noOrder)
                        ->whereIn('no_cfr', $dupCfrs)
                        ->get($selectColumns);
                    
                    foreach ($recordsCfr as $record) {
                        // Avoid adding duplicates if already added by no_lhp check
                        $alreadyAdded = false;
                        foreach ($duplicates as $dup) {
                            if ($dup['id'] == $record->id && $dup['model'] == class_basename($modelClass)) {
                                $alreadyAdded = true;
                                break;
                            }
                        }

                        if (!$alreadyAdded) {
                            $duplicates[] = [
                                'id' => $record->id,
                                'no_lhp' => ($hasLhp && !empty($record->no_lhp)) ? $record->no_lhp : $record->no_cfr,
                                'no_order' => $record->no_order,
                                'is_approve' => $approveColumn ? $record->{$approveColumn} : null,
                                'model' => class_basename($modelClass)
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'data' => $duplicates,
            'links' => $link
        ], 200);
    }

    public function hapusLhpDouble(Request $request)
    {
        $id = $request->input('id');
        $modelName = $request->input('model');

        if (!$id || !$modelName) {
            return response()->json(['message' => 'id and model are required'], 400);
        }

        $modelClass = "\\App\\Models\\" . $modelName;
        if (!class_exists($modelClass)) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        $record = $modelClass::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        try {
            DB::beginTransaction();

            $detailModelName = str_replace('Header', 'Detail', $modelName);
            $detailModelClass = "\\App\\Models\\" . $detailModelName;
            
            if (class_exists($detailModelClass)) {
                $detailModelClass::where('id_header', $id)->delete();
            }

            $record->delete();
            
            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }
}
