<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Exception;

use App\Models\BackdateOrder;
use App\Models\BackdateOrderDetail;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\JadwalBackdate;

use App\Services\RenderNonKontrak;
use App\Services\RenderKontrak;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
use App\Services\RenderPersiapanSample;
use App\Services\RenderCOC;
use App\Http\Controllers\api\GenerateHasilPengujianController;

class BackdateOrderController extends Controller
{
    protected $lhpRelations = [
        'lhps_air' => 'LhpsAirHeader',
        'lhps_emisi' => 'LhpsEmisiHeader',
        'lhps_emisi_c' => 'LhpsEmisiCHeader',
        'lhps_emisi_isokinetik' => 'LhpsEmisiIsokinetikHeader',
        'lhps_getaran' => 'LhpsGetaranHeader',
        'lhps_kebisingan' => 'LhpsKebisinganHeader',
        'lhps_kebisingan_personal' => 'LhpsKebisinganPersonalHeader',
        'lhps_ling' => 'LhpsLingHeader',
        'lhps_medanlm' => 'LhpsMedanlmHeader',
        'lhps_pencahayaan' => 'LhpsPencahayaanHeader',
        'lhps_sinaruv' => 'LhpsSinaruvHeader',
        'lhps_ergonomi' => 'DraftErgonomiFile',
        'lhps_iklim' => 'LhpsIklimHeader',
        'lhps_swab_udara' => 'LhpsSwabTesHeader',
        'lhps_microbiologi' => 'LhpsMicrobiologiHeader',
        'lhps_padatan' => 'LhpsPadatanHeader',
        'lhp_psikologi' => 'LhpUdaraPsikologiHeader',
        'lhps_hygiene_sanitasi' => 'LhpsHygieneSanitasiHeader',
        'lhp_manual' => 'LhpManual'
    ];

    /**
     * Datatable list of Backdate Orders
     */
    public function index(Request $request)
    {
        try {
            $query = BackdateOrder::query()->latest('id');

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            return DataTables::of($query)
                ->addColumn('action', function ($row) {
                    return [
                        'id' => $row->id,
                        'no_order' => $row->no_order,
                        'status' => $row->status
                    ];
                })
                ->rawColumns(['action'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data backdate order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check order eligibility and return full preview of flow data
     */
    public function checkOrder(Request $request)
    {
        try {
            $noOrder = trim($request->input('no_order', ''));

            if (empty($noOrder)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nomor order wajib diisi.'
                ], 400);
            }

            // 1. Cek di tabel order_berjalan
            $order = DB::table('order_berjalan')
                ->where('no_order', $noOrder)
                ->first();

            if (!$order) {
                // Fallback cek di order_header
                $orderHeader = OrderHeader::where('no_order', $noOrder)->first();
                if (!$orderHeader) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Order dengan nomor '{$noOrder}' tidak ditemukan di sistem."
                    ], 404);
                }
            }

            // 2. Validasi LHP Rilis (Harus 100% selesai)
            $details = [];
            if ($order && !empty($order->dataOrderDetail)) {
                $details = is_string($order->dataOrderDetail) ? json_decode($order->dataOrderDetail, true) : $order->dataOrderDetail;
            }

            $totalLhp = 0;
            $selesaiLhp = 0;
            $isComplete = true;

            if (!empty($details) && is_array($details)) {
                foreach ($details as $periodGroup) {
                    $totalLhp += (int)($periodGroup['jumlah_lhp'] ?? 0);
                    $selesaiLhp += (int)($periodGroup['jumlah_lhp_selesai'] ?? 0);
                    if (empty($periodGroup['status_selesai'])) {
                        $isComplete = false;
                    }
                }
            }

            // Jika belum selesai semua, blokir!
            if ($totalLhp > 0 && ($selesaiLhp < $totalLhp || !$isComplete)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Order '{$noOrder}' belum dapat diproses backdate karena seluruh LHP belum rilis (baru {$selesaiLhp} dari {$totalLhp} LHP yang rilis). Harap selesaikan seluruh LHP terlebih dahulu.",
                    'data' => [
                        'total_lhp' => $totalLhp,
                        'selesai_lhp' => $selesaiLhp,
                        'status_selesai' => false
                    ]
                ], 422);
            }

            $noPenawaran = $order->no_penawaran ?? ($orderHeader->no_quotation ?? null);
            $namaPerusahaan = $order->nama_perusahaan ?? ($orderHeader->nama_perusahaan ?? '-');

            // 3. Ambil Data Quotation (QT)
            $quotationData = null;
            if (!empty($noPenawaran)) {
                $qtNonKontrak = QuotationNonKontrak::where('no_document', $noPenawaran)->first();
                if ($qtNonKontrak) {
                    $quotationData = [
                        'type' => 'non_kontrak',
                        'id' => $qtNonKontrak->id,
                        'no_quotation' => $qtNonKontrak->no_document,
                        'tanggal_penawaran' => $qtNonKontrak->tanggal_penawaran,
                        'is_backdate' => (bool)$qtNonKontrak->is_backdate,
                        'created_at' => $qtNonKontrak->created_at ? Carbon::parse($qtNonKontrak->created_at)->format('Y-m-d') : null,
                    ];
                } else {
                    $qtKontrak = QuotationKontrakH::where('no_document', $noPenawaran)->first();
                    if ($qtKontrak) {
                        $quotationData = [
                            'type' => 'kontrak',
                            'id' => $qtKontrak->id,
                            'no_quotation' => $qtKontrak->no_document,
                            'tanggal_penawaran' => $qtKontrak->tanggal_penawaran,
                            'is_backdate' => (bool)$qtKontrak->is_backdate,
                            'created_at' => $qtKontrak->created_at ? Carbon::parse($qtKontrak->created_at)->format('Y-m-d') : null,
                        ];
                    }
                }
            }

            // 4. Ambil Data Jadwal
            $listJadwal = DB::table('jadwal')
                ->where('no_quotation', $noPenawaran)
                ->where('is_active', true)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'tanggal' => $item->tanggal ?? null,
                        'tanggal_jadwal' => $item->tanggal ?? null,
                        'periode' => $item->periode ?? null,
                        'sampler' => $item->sampler ?? null,
                        'wilayah' => $item->wilayah ?? null,
                        'created_at' => $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : null,
                    ];
                });

            // 5. Ambil Data Sampling & Order Detail
            $orderDetails = OrderDetail::where('no_order', $noOrder)
                ->where('is_active', true)
                ->get()
                ->map(function ($od) {
                    return [
                        'id' => $od->id,
                        'no_sampel' => $od->no_sampel,
                        'cfr' => $od->cfr,
                        'kategori' => $od->kategori_3 ?? $od->kategori_2,
                        'titik_lokasi' => $od->keterangan_1,
                        'tanggal_sampling' => $od->tanggal_sampling,
                        'tanggal_terima' => $od->tanggal_terima,
                        'created_at' => $od->created_at ? Carbon::parse($od->created_at)->format('Y-m-d') : null,
                    ];
                });

            // 6. Ambil Step Timeline dari dataOrderDetail
            $stepList = [];
            if (!empty($details) && is_array($details)) {
                foreach ($details as $pGroup) {
                    if (isset($pGroup['detail']) && is_array($pGroup['detail'])) {
                        foreach ($pGroup['detail'] as $cfrItem) {
                            $stepList[] = [
                                'cfr' => $cfrItem['cfr'] ?? '-',
                                'points' => $cfrItem['points'] ?? [],
                                'order_date' => $cfrItem['steps']['order']['date'] ?? null,
                                'sampling_date' => $cfrItem['steps']['sampling']['date'] ?? null,
                                'analisa_date' => $cfrItem['steps']['analisa']['date'] ?? null,
                                'drafting_date' => $cfrItem['steps']['drafting']['date'] ?? null,
                                'lhp_release_date' => $cfrItem['steps']['lhp_release']['date'] ?? null,
                            ];
                        }
                    }
                }
            }

            // 7. Ambil Dokumentasi Data Lapangan (Foto)
            $photoList = [];
            $odWithPhotos = OrderDetail::withAnyDataLapangan()
                ->where('no_order', $noOrder)
                ->where('is_active', true)
                ->get();

            $possibleFields = [
                'foto_lokasi_sampel' => 'Foto Lokasi Sampel',
                'foto_kondisi_sampel' => 'Foto Kondisi Sampel',
                'foto_lain' => 'Foto Lain',
                'foto_lain2' => 'Foto Lain 2',
                'foto_lain3' => 'Foto Lain 3',
                'foto_asap' => 'Foto Asap',
                'foto_struk' => 'Foto Struk',
                'foto_sampling' => 'Foto Sampling',
                'foto_lapangan' => 'Foto Lapangan',
                'foto_lokasi' => 'Foto Lokasi',
                'file_foto' => 'File Foto',
                'foto' => 'Foto',
                'attachment' => 'Attachment',
                'file' => 'File',
            ];

            $photoIndex = 0;
            foreach ($odWithPhotos as $od) {
                $lapanganItems = $od->any_data_lapangan;
                if ($lapanganItems && $lapanganItems->count() > 0) {
                    foreach ($lapanganItems as $lapItem) {
                        foreach ($possibleFields as $f => $label) {
                            $filename = $lapItem->{$f} ?? null;
                            if (!empty($filename) && is_string($filename) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $filename)) {
                                $fullPublicPath = public_path('dokumentasi/sampling/' . $filename);
                                $exists = File::exists($fullPublicPath);

                                $photoList[] = [
                                    'index' => $photoIndex++,
                                    'table_name' => $lapItem->getTable(),
                                    'record_id' => $lapItem->id,
                                    'field_name' => $f,
                                    'field_label' => $label,
                                    'no_sampel' => $od->no_sampel,
                                    'cfr' => $od->cfr,
                                    'titik' => $od->keterangan_1,
                                    'filename' => $filename,
                                    'url' => url('dokumentasi/sampling/' . $filename),
                                    'file_exists' => $exists,
                                ];
                            }
                        }
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order memenuhi syarat dan siap di-backdate.',
                'data' => [
                    'order' => [
                        'no_order' => $noOrder,
                        'no_penawaran' => $noPenawaran,
                        'nama_perusahaan' => $namaPerusahaan,
                        'tgl_order' => $order->tgl_order ?? ($orderHeader->tanggal_order ?? null),
                        'tgl_penawaran' => $order->tgl_penawaran ?? null,
                        'total_lhp' => $totalLhp,
                        'selesai_lhp' => $selesaiLhp,
                    ],
                    'quotation' => $quotationData,
                    'jadwal' => $listJadwal,
                    'sampling' => $orderDetails,
                    'steps' => $stepList,
                    'photos' => $photoList,
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memeriksa order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit and Apply Backdate Order
     */
    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            $noOrder = trim($request->input('no_order', ''));
            if (empty($noOrder)) {
                return response()->json(['status' => 'error', 'message' => 'No Order wajib diisi.'], 400);
            }

            $catatan = $request->input('catatan', 'Backdate order proses');
            $user = $request->header('user') ?? 'system';

            $orderBerjalan = DB::table('order_berjalan')->where('no_order', $noOrder)->first();
            $orderHeader = OrderHeader::where('no_order', $noOrder)->first();

            $noPenawaran = $orderBerjalan->no_penawaran ?? ($orderHeader->no_quotation ?? null);
            $namaPerusahaan = $orderBerjalan->nama_perusahaan ?? ($orderHeader->nama_perusahaan ?? null);

            // Buat record header Backdate
            $backdateOrder = BackdateOrder::create([
                'no_order' => $noOrder,
                'no_quotation' => $noPenawaran,
                'nama_perusahaan' => $namaPerusahaan,
                'status' => 'backdated',
                'catatan' => $catatan,
                'created_by' => $user,
            ]);

            // 1. PROSES QUOTATION: Set Tanggal & is_backdate = 1
            $newTglPenawaran = $request->input('tgl_penawaran');
            if ($noPenawaran) {
                // Non Kontrak
                $qtNon = QuotationNonKontrak::where('no_document', $noPenawaran)->first();
                if ($qtNon) {
                    if ($newTglPenawaran && $newTglPenawaran !== $qtNon->tanggal_penawaran) {
                        BackdateOrderDetail::create([
                            'backdate_order_id' => $backdateOrder->id,
                            'category' => 'quotation',
                            'table_name' => 'request_quotation',
                            'record_id' => $qtNon->id,
                            'field_name' => 'tanggal_penawaran',
                            'original_value' => $qtNon->tanggal_penawaran,
                            'backdate_value' => $newTglPenawaran,
                        ]);
                        $qtNon->tanggal_penawaran = $newTglPenawaran;
                    }

                    BackdateOrderDetail::create([
                        'backdate_order_id' => $backdateOrder->id,
                        'category' => 'quotation',
                        'table_name' => 'request_quotation',
                        'record_id' => $qtNon->id,
                        'field_name' => 'is_backdate',
                        'original_value' => $qtNon->is_backdate ? '1' : '0',
                        'backdate_value' => '1',
                    ]);
                    $qtNon->is_backdate = 1;
                    $qtNon->save();
                }

                // Kontrak
                $qtKon = QuotationKontrakH::where('no_document', $noPenawaran)->first();
                if ($qtKon) {
                    if ($newTglPenawaran && $newTglPenawaran !== $qtKon->tanggal_penawaran) {
                        BackdateOrderDetail::create([
                            'backdate_order_id' => $backdateOrder->id,
                            'category' => 'quotation',
                            'table_name' => 'request_quotation_kontrak_H',
                            'record_id' => $qtKon->id,
                            'field_name' => 'tanggal_penawaran',
                            'original_value' => $qtKon->tanggal_penawaran,
                            'backdate_value' => $newTglPenawaran,
                        ]);
                        $qtKon->tanggal_penawaran = $newTglPenawaran;
                    }

                    BackdateOrderDetail::create([
                        'backdate_order_id' => $backdateOrder->id,
                        'category' => 'quotation',
                        'table_name' => 'request_quotation_kontrak_H',
                        'record_id' => $qtKon->id,
                        'field_name' => 'is_backdate',
                        'original_value' => $qtKon->is_backdate ? '1' : '0',
                        'backdate_value' => '1',
                    ]);
                    $qtKon->is_backdate = 1;
                    $qtKon->save();
                }
            }

            // 2. PROSES JADWAL: Duplikat ke jadwal_backdate & Update Tanggal di jadwal utama (tidak di-close)
            $jadwalUpdates = $request->input('jadwal', []);
            if (!empty($jadwalUpdates) && is_array($jadwalUpdates)) {
                foreach ($jadwalUpdates as $jItem) {
                    $jadwalId = $jItem['id'] ?? null;
                    $newDate = $jItem['tanggal'] ?? ($jItem['tanggal_jadwal'] ?? null);

                    if ($jadwalId && $newDate) {
                        $jadwal = DB::table('jadwal')->where('id', $jadwalId)->first();
                        if ($jadwal) {
                            // a. Snapshot nilai asli di tabel jadwal utama
                            BackdateOrderDetail::create([
                                'backdate_order_id' => $backdateOrder->id,
                                'category' => 'jadwal',
                                'table_name' => 'jadwal',
                                'record_id' => $jadwalId,
                                'field_name' => 'tanggal',
                                'original_value' => $jadwal->tanggal,
                                'backdate_value' => $newDate,
                            ]);

                            // b. Buat duplikat record full ke tabel jadwal_backdate
                            $cloneData = (array)$jadwal;
                            unset($cloneData['id']); // Buat id baru di jadwal_backdate
                            $cloneData['original_jadwal_id'] = $jadwal->id;
                            $cloneData['backdate_order_id'] = $backdateOrder->id;
                            $cloneData['tanggal'] = $newDate;
                            $cloneData['updated_at'] = Carbon::now();

                            DB::table('jadwal_backdate')->insert($cloneData);

                            // c. Update tanggal di tabel jadwal utama (tidak di-close / is_active tetap)
                            DB::table('jadwal')->where('id', $jadwalId)->update([
                                'tanggal' => $newDate,
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }
                }
            }

            // 3. PROSES ORDER & SAMPLING (order_detail) + DATA LAPANGAN (created_at shifting)
            $samplingUpdates = $request->input('sampling', []);
            if (!empty($samplingUpdates) && is_array($samplingUpdates)) {
                foreach ($samplingUpdates as $sItem) {
                    $odId = $sItem['id'] ?? null;
                    $newTglSampling = $sItem['tanggal_sampling'] ?? null;

                    if ($odId) {
                        $od = OrderDetail::withAnyDataLapangan()->find($odId);
                        if ($od) {
                            if ($newTglSampling && $newTglSampling !== $od->tanggal_sampling) {
                                BackdateOrderDetail::create([
                                    'backdate_order_id' => $backdateOrder->id,
                                    'category' => 'sampling',
                                    'table_name' => 'order_detail',
                                    'record_id' => $od->id,
                                    'field_name' => 'tanggal_sampling',
                                    'original_value' => $od->tanggal_sampling,
                                    'backdate_value' => $newTglSampling,
                                ]);
                                $od->tanggal_sampling = $newTglSampling;

                                // --- SKENARIO 1: GESER CREATED_AT DI DATA LAPANGAN (FDL) ---
                                $lapanganItems = $od->any_data_lapangan;
                                if ($lapanganItems && $lapanganItems->count() > 0) {
                                    foreach ($lapanganItems as $lapItem) {
                                        $originalCreatedAt = $lapItem->created_at ? (string)$lapItem->created_at : null;
                                        if ($originalCreatedAt) {
                                            $timePart = Carbon::parse($originalCreatedAt)->format('H:i:s');
                                            $newCreatedAt = "{$newTglSampling} {$timePart}";

                                            BackdateOrderDetail::create([
                                                'backdate_order_id' => $backdateOrder->id,
                                                'category' => 'data_lapangan',
                                                'table_name' => $lapItem->getTable(),
                                                'record_id' => $lapItem->id,
                                                'field_name' => 'created_at',
                                                'original_value' => $originalCreatedAt,
                                                'backdate_value' => $newCreatedAt,
                                            ]);

                                            DB::table($lapItem->getTable())
                                                ->where('id', $lapItem->id)
                                                ->update([
                                                    'created_at' => $newCreatedAt,
                                                    'updated_at' => Carbon::now(),
                                                ]);
                                        }
                                    }
                                }
                            }
                            $od->save();
                        }
                    }
                }

                // Sinkronkan juga ke persiapan_sampel_header jika ada perubahan tanggal sampling
                $firstSamplingDate = collect($samplingUpdates)->pluck('tanggal_sampling')->filter()->first();
                if ($firstSamplingDate && Schema::hasTable('persiapan_sampel_header')) {
                    $psHeaders = DB::table('persiapan_sampel_header')->where('no_order', $noOrder)->get();
                    foreach ($psHeaders as $psH) {
                        if ($psH->tanggal_sampling !== $firstSamplingDate) {
                            BackdateOrderDetail::create([
                                'backdate_order_id' => $backdateOrder->id,
                                'category' => 'sampling',
                                'table_name' => 'persiapan_sampel_header',
                                'record_id' => $psH->id,
                                'field_name' => 'tanggal_sampling',
                                'original_value' => (string)$psH->tanggal_sampling,
                                'backdate_value' => $firstSamplingDate,
                            ]);
                            DB::table('persiapan_sampel_header')->where('id', $psH->id)->update([
                                'tanggal_sampling' => $firstSamplingDate,
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }
                }
            }

            // 4. PROSES ORDER HEADER, T_FTC, DAN LHPS_*
            $newTglOrder = $request->input('tgl_order');

            // Sinkronkan ke tabel order_header
            if ($orderHeader && $newTglOrder && $orderHeader->tanggal_order !== $newTglOrder) {
                BackdateOrderDetail::create([
                    'backdate_order_id' => $backdateOrder->id,
                    'category' => 'order_header',
                    'table_name' => 'order_header',
                    'record_id' => $orderHeader->id,
                    'field_name' => 'tanggal_order',
                    'original_value' => (string)$orderHeader->tanggal_order,
                    'backdate_value' => $newTglOrder,
                ]);
                $orderHeader->tanggal_order = $newTglOrder;
                $orderHeader->save();
            }

            // 5. PROSES T_FTC (ANALISA) DAN LHPS_* (DRAFTING & LHP RELEASE)
            // Catatan: tabel order_berjalan tidak diupdate manual karena ada mekanisme auto-update dari sistem
            $stepsInput = $request->input('steps', []);
            if (!empty($stepsInput) && is_array($stepsInput)) {
                foreach ($stepsInput as $s) {
                    $cfr = $s['cfr'] ?? '';
                    if (empty($cfr)) continue;

                    // a. TANGGAL ANALISA -> Update tabel t_ftc pada kolom ftc_laboratory
                    if (!empty($s['analisa_date'])) {
                        $odList = OrderDetail::where('cfr', $cfr)->get();
                        foreach ($odList as $odRow) {
                            if (!empty($odRow->no_sampel)) {
                                $ftc = DB::table('t_ftc')->where('no_sample', $odRow->no_sampel)->first();
                                if ($ftc) {
                                    $timePart = $ftc->ftc_laboratory ? Carbon::parse($ftc->ftc_laboratory)->format('H:i:s') : '08:00:00';
                                    $newFtc = "{$s['analisa_date']} {$timePart}";

                                    if ($ftc->ftc_laboratory !== $newFtc) {
                                        BackdateOrderDetail::create([
                                            'backdate_order_id' => $backdateOrder->id,
                                            'category' => 'analisa',
                                            'table_name' => 't_ftc',
                                            'record_id' => $ftc->id,
                                            'field_name' => 'ftc_laboratory',
                                            'original_value' => (string)$ftc->ftc_laboratory,
                                            'backdate_value' => $newFtc,
                                        ]);

                                        DB::table('t_ftc')->where('id', $ftc->id)->update([
                                            'ftc_laboratory' => $newFtc,
                                        ]);
                                    }
                                }
                            }
                        }
                    }

                    // b. TANGGAL DRAFTING -> Update tabel lhps_* pada kolom created_at
                    if (!empty($s['drafting_date'])) {
                        $lhpRecords = $this->findLhpHeaderRecords($noOrder, $cfr);
                        foreach ($lhpRecords as $lhp) {
                            $originalCreatedAt = $lhp['row']->created_at ? (string)$lhp['row']->created_at : null;
                            if ($originalCreatedAt) {
                                $timePart = Carbon::parse($originalCreatedAt)->format('H:i:s');
                                $newDraftingDate = "{$s['drafting_date']} {$timePart}";

                                if ($originalCreatedAt !== $newDraftingDate) {
                                    BackdateOrderDetail::create([
                                        'backdate_order_id' => $backdateOrder->id,
                                        'category' => 'drafting',
                                        'table_name' => $lhp['table'],
                                        'record_id' => $lhp['id'],
                                        'field_name' => 'created_at',
                                        'original_value' => $originalCreatedAt,
                                        'backdate_value' => $newDraftingDate,
                                    ]);

                                    DB::table($lhp['table'])->where('id', $lhp['id'])->update([
                                        'created_at' => $newDraftingDate,
                                    ]);
                                }
                            }
                        }
                    }

                    // c. TANGGAL LHP RELEASE -> Update tabel lhps_* pada kolom tanggal_lhp atau tanggal_rilis_lhp
                    if (!empty($s['lhp_release_date'])) {
                        $lhpRecords = $this->findLhpHeaderRecords($noOrder, $cfr);
                        foreach ($lhpRecords as $lhp) {
                            $releaseCol = in_array('tanggal_lhp', $lhp['columns']) ? 'tanggal_lhp' : (in_array('tanggal_rilis_lhp', $lhp['columns']) ? 'tanggal_rilis_lhp' : null);
                            if ($releaseCol) {
                                $origLhpDate = $lhp['row']->{$releaseCol} ? (string)$lhp['row']->{$releaseCol} : null;
                                if ($origLhpDate !== $s['lhp_release_date']) {
                                    BackdateOrderDetail::create([
                                        'backdate_order_id' => $backdateOrder->id,
                                        'category' => 'lhp',
                                        'table_name' => $lhp['table'],
                                        'record_id' => $lhp['id'],
                                        'field_name' => $releaseCol,
                                        'original_value' => $origLhpDate,
                                        'backdate_value' => $s['lhp_release_date'],
                                    ]);

                                    DB::table($lhp['table'])->where('id', $lhp['id'])->update([
                                        $releaseCol => $s['lhp_release_date'],
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            // 5. PROSES PENGGANTIAN FOTO LAPANGAN (Mempertahankan Filename Asli)
            $backupDir = storage_path("app/backdate_backups/{$noOrder}");
            if (!File::exists($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            $allFiles = $request->allFiles();
            foreach ($allFiles as $inputKey => $uploadedFile) {
                if (str_starts_with($inputKey, 'photo_replace_')) {
                    $index = str_replace('photo_replace_', '', $inputKey);
                    $targetFilename = $request->input("photo_filename_{$index}");
                    $tableName = $request->input("photo_table_{$index}");
                    $recordId = $request->input("photo_record_id_{$index}");
                    $fieldName = $request->input("photo_field_{$index}");

                    if ($targetFilename && $uploadedFile->isValid()) {
                        $originalPublicPath = public_path('dokumentasi/sampling/' . $targetFilename);
                        $backupFilePath = $backupDir . '/' . $targetFilename;

                        // Backup file asli jika ada
                        if (File::exists($originalPublicPath)) {
                            File::copy($originalPublicPath, $backupFilePath);
                        }

                        // Timpa file lama dengan file baru menggunakan filename yang persis sama
                        $uploadedFile->move(public_path('dokumentasi/sampling'), $targetFilename);

                        BackdateOrderDetail::create([
                            'backdate_order_id' => $backdateOrder->id,
                            'category' => 'foto',
                            'table_name' => $tableName ?: 'data_lapangan',
                            'record_id' => $recordId ?: null,
                            'field_name' => $fieldName ?: 'foto_sampling',
                            'original_value' => $targetFilename,
                            'backdate_value' => $targetFilename,
                            'backup_file_path' => $backupFilePath,
                        ]);
                    }
                }
            }

            DB::commit();

            // Render ulang semua dokumen yang tanggalnya berubah
            $renderedDocs = $this->rerenderAllDocuments($noOrder, $noPenawaran);

            return response()->json([
                'status' => 'success',
                'message' => "Backdate untuk order '{$noOrder}' berhasil disimpan dan seluruh dokumen terkait telah dirender ulang.",
                'data' => [
                    'backdate_order_id' => $backdateOrder->id,
                    'no_order' => $noOrder,
                    'rendered_documents' => $renderedDocs
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan backdate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to find LHP header records across relations
     */
    private function findLhpHeaderRecords(string $noOrder, ?string $cfr = null, ?string $noSampel = null): array
    {
        $results = [];
        foreach ($this->lhpRelations as $modelName) {
            $className = "App\\Models\\{$modelName}";
            if (!class_exists($className)) continue;
            $instance = new $className();
            $table = $instance->getTable();
            $columns = Schema::getColumnListing($table);

            $query = DB::table($table);
            if (in_array('no_order', $columns)) {
                $query->where('no_order', $noOrder);
            }

            $hasCondition = false;
            if ($noSampel && in_array('no_sampel', $columns)) {
                $query->where('no_sampel', $noSampel);
                $hasCondition = true;
            } elseif ($cfr) {
                if (in_array('no_cfr', $columns)) {
                    $query->where('no_cfr', $cfr);
                    $hasCondition = true;
                } elseif (in_array('no_lhp', $columns)) {
                    $query->where('no_lhp', $cfr);
                    $hasCondition = true;
                }
            }

            if ($hasCondition) {
                $rows = $query->get();
                foreach ($rows as $r) {
                    $results[] = [
                        'table' => $table,
                        'id' => $r->id,
                        'columns' => $columns,
                        'row' => $r,
                    ];
                }
            }
        }
        return $results;
    }

    /**
     * Restore original dates and photos
     */
    public function restore(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->input('id');
            $backdateOrder = BackdateOrder::with('details')->find($id);

            if (!$backdateOrder) {
                return response()->json(['status' => 'error', 'message' => 'Data backdate tidak ditemukan.'], 404);
            }

            if ($backdateOrder->status === 'restored') {
                return response()->json(['status' => 'error', 'message' => 'Order ini sudah berada pada tanggal asli (restored).'], 400);
            }

            $user = $request->header('user') ?? 'system';

            foreach ($backdateOrder->details as $detail) {
                $tableName = $detail->table_name;
                $recordId = $detail->record_id;
                $fieldName = $detail->field_name;
                $originalValue = $detail->original_value;

                if ($detail->category === 'foto') {
                    // Restore foto dari backup
                    if (!empty($detail->backup_file_path) && File::exists($detail->backup_file_path)) {
                        $targetPublicPath = public_path('dokumentasi/sampling/' . $detail->original_value);
                        File::copy($detail->backup_file_path, $targetPublicPath);
                    }
                } elseif ($recordId && $tableName && $fieldName) {
                    DB::table($tableName)
                        ->where('id', $recordId)
                        ->update([$fieldName => $originalValue]);
                }
            }

            // Hapus cloned data dari jadwal_backdate untuk order ini
            DB::table('jadwal_backdate')->where('backdate_order_id', $backdateOrder->id)->delete();

            $backdateOrder->status = 'restored';
            $backdateOrder->restored_by = $user;
            $backdateOrder->restored_at = Carbon::now();
            $backdateOrder->save();

            DB::commit();

            // Render ulang semua dokumen ke tanggal aslinya
            $this->rerenderAllDocuments($backdateOrder->no_order, $backdateOrder->no_quotation);

            return response()->json([
                'status' => 'success',
                'message' => "Order '{$backdateOrder->no_order}' berhasil di-restore ke tanggal aslinya.",
                'data' => $backdateOrder
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal merestore tanggal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Switch back to Backdate values
     */
    public function switchBackdate(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->input('id');
            $backdateOrder = BackdateOrder::with('details')->find($id);

            if (!$backdateOrder) {
                return response()->json(['status' => 'error', 'message' => 'Data backdate tidak ditemukan.'], 404);
            }

            if ($backdateOrder->status === 'backdated') {
                return response()->json(['status' => 'error', 'message' => 'Order ini sudah berstatus backdate.'], 400);
            }

            $user = $request->header('user') ?? 'system';

            foreach ($backdateOrder->details as $detail) {
                $tableName = $detail->table_name;
                $recordId = $detail->record_id;
                $fieldName = $detail->field_name;
                $backdateValue = $detail->backdate_value;

                if ($detail->category !== 'foto' && $recordId && $tableName && $fieldName) {
                    DB::table($tableName)
                        ->where('id', $recordId)
                        ->update([$fieldName => $backdateValue]);
                }
            }

            // Re-clone ke jadwal_backdate jika belum ada
            $jadwalDetails = $backdateOrder->details->where('category', 'jadwal')->where('field_name', 'tanggal');
            foreach ($jadwalDetails as $jDetail) {
                $existing = DB::table('jadwal_backdate')
                    ->where('backdate_order_id', $backdateOrder->id)
                    ->where('original_jadwal_id', $jDetail->record_id)
                    ->first();

                if (!$existing) {
                    $jadwal = DB::table('jadwal')->where('id', $jDetail->record_id)->first();
                    if ($jadwal) {
                        $clone = (array)$jadwal;
                        unset($clone['id']);
                        $clone['original_jadwal_id'] = $jadwal->id;
                        $clone['backdate_order_id'] = $backdateOrder->id;
                        $clone['tanggal'] = $jDetail->backdate_value;
                        $clone['updated_at'] = Carbon::now();
                        DB::table('jadwal_backdate')->insert($clone);
                    }
                }
            }

            $backdateOrder->status = 'backdated';
            $backdateOrder->save();

            DB::commit();

            // Render ulang semua dokumen ke tanggal aslinya
            $this->rerenderAllDocuments($backdateOrder->no_order, $backdateOrder->no_quotation);

            return response()->json([
                'status' => 'success',
                'message' => "Order '{$backdateOrder->no_order}' berhasil di-switch kembali ke tanggal backdate.",
                'data' => $backdateOrder
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal switch backdate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detail Backdate and audit differences
     */
    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            $backdateOrder = BackdateOrder::with('details')->find($id);

            if (!$backdateOrder) {
                return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan.'], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $backdateOrder
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render ulang semua dokumen yang tanggalnya berubah setelah simpan backdate / restore
     */
    private function rerenderAllDocuments(string $noOrder, ?string $noPenawaran = null): array
    {
        $renderedDocs = [];

        // 1. RENDER ULANG QUOTATION (JIKA PENAWARAN ADA)
        if ($noPenawaran) {
            try {
                $qtNon = QuotationNonKontrak::where('no_document', $noPenawaran)->first();
                if ($qtNon) {
                    $render = new RenderNonKontrak();
                    $render->renderHeader($qtNon->id, 'id');
                    $render->renderHeader($qtNon->id, 'en');
                    $renderedDocs[] = "Quotation Non-Kontrak: {$noPenawaran}";
                }

                $qtKon = QuotationKontrakH::where('no_document', $noPenawaran)->first();
                if ($qtKon) {
                    $render = new RenderKontrak();
                    $render->renderDataQuotation($qtKon->id, 'id');
                    $render->renderDataQuotation($qtKon->id, 'en');
                    $renderedDocs[] = "Quotation Kontrak: {$noPenawaran}";
                }
            } catch (\Throwable $e) {
                \Log::warning("Backdate Order [{$noOrder}]: Gagal render ulang Quotation - " . $e->getMessage());
            }
        }

        // 2. RENDER ULANG SAMPLING PLAN (JIKA ADA)
        if ($noPenawaran && Schema::hasTable('sampling_plan')) {
            try {
                $spList = DB::table('sampling_plan')
                    ->where('no_quotation', $noPenawaran)
                    ->orWhere('no_document', $noPenawaran)
                    ->get();
                foreach ($spList as $sp) {
                    try {
                        if ($sp->status_quotation == 'kontrak') {
                            RenderSamplingPlanService::onKontrak($sp->id)->save();
                        } else {
                            RenderSamplingPlanService::onNonKontrak($sp->id)->save();
                        }
                        $renderedDocs[] = "Sampling Plan: id {$sp->id}";
                    } catch (\Throwable $e) {
                        \Log::warning("Backdate Order [{$noOrder}]: Gagal render Sampling Plan {$sp->id} - " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning("Backdate Order [{$noOrder}]: Gagal query Sampling Plan - " . $e->getMessage());
            }
        }

        // 3. RENDER ULANG PERSIAPAN SAMPEL (STPS) & COC (JIKA ADA)
        if (Schema::hasTable('persiapan_sampel_header')) {
            try {
                $psHeaders = DB::table('persiapan_sampel_header')->where('no_order', $noOrder)->get();
                foreach ($psHeaders as $ps) {
                    try {
                        $renderPs = new RenderPersiapanSample();
                        $renderPs->renderPdf($ps->id);
                        $renderedDocs[] = "Persiapan Sampel (STPS): {$ps->no_order}";
                    } catch (\Throwable $e) {
                        \Log::warning("Backdate Order [{$noOrder}]: Gagal render STPS {$ps->id} - " . $e->getMessage());
                    }

                    try {
                        $renderCoc = new RenderCOC();
                        $renderCoc->renderPdf($ps->id);
                        $renderedDocs[] = "COC: {$ps->no_order}";
                    } catch (\Throwable $e) {
                        \Log::warning("Backdate Order [{$noOrder}]: Gagal render COC {$ps->id} - " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning("Backdate Order [{$noOrder}]: Gagal query Persiapan Sampel - " . $e->getMessage());
            }
        }

        // 4. RENDER ULANG SELURUH INDIVIDUAL LHP
        $controllerMap = [
            'lhps_air_header' => 'DraftAirController',
            'lhps_padatan_header' => 'DraftPadatanController',
            'lhps_ling_header' => 'DraftUdaraAmbientController',
            'lhps_emisi_header' => 'DraftEmisiSumberTidakBergerakController',
            'lhps_emisi_c_header' => 'DraftEmisiSumberBergerakController',
            'lhps_emisi_isokinetik_header' => 'DraftEmisiSumberTidakBergerakIsokinetikController',
            'lhps_getaran_header' => 'DraftUdaraGetaranController',
            'lhps_kebisingan_header' => 'DraftUdaraKebisinganController',
            'lhps_kebisingan_personal_header' => 'DraftUdaraKebisinganPersonalController',
            'lhps_pencahayaan_header' => 'DraftUdaraPencahayaanController',
            'lhps_sinaruv_header' => 'DraftUlkSinarUvController',
            'draft_ergonomi_file' => 'DraftUlkErgonomiController',
            'lhps_iklim_header' => 'DraftUdaraIklimKerjaController',
            'lhps_swab_tes_header' => 'DraftSwabTestController',
            'lhps_microbiologi_header' => 'DraftUdaraMikrobiologiController',
            'lhps_hygiene_sanitasi_header' => 'DraftHygieneController',
        ];

        foreach ($this->lhpRelations as $modelName) {
            $className = "App\\Models\\{$modelName}";
            if (!class_exists($className)) continue;
            $instance = new $className();
            $table = $instance->getTable();

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'no_order')) continue;

            $rows = DB::table($table)->where('no_order', $noOrder)->get();
            if ($rows->isEmpty()) continue;

            $ctrlName = $controllerMap[$table] ?? null;
            if ($ctrlName) {
                $ctrlClass = "App\\Http\\Controllers\\api\\{$ctrlName}";
                if (class_exists($ctrlClass) && method_exists($ctrlClass, 'updateTanggalLhp')) {
                    $controller = app($ctrlClass);
                    foreach ($rows as $row) {
                        try {
                            $tglLhp = $row->tanggal_lhp ?? ($row->tanggal_rilis_lhp ?? date('Y-m-d'));
                            $req = new Request(['id' => $row->id, 'value' => $tglLhp]);
                            $controller->updateTanggalLhp($req);
                            $renderedDocs[] = "LHP ({$table}): " . ($row->no_sampel ?? ($row->no_lhp ?? $row->id));
                        } catch (\Throwable $e) {
                            \Log::warning("Backdate Order [{$noOrder}]: Gagal render LHP {$table} id {$row->id} - " . $e->getMessage());
                        }
                    }
                }
            }
        }

        // 5. RENDER ULANG LINK LHP (MERGED LHP FILE) JIKA ADA
        if (Schema::hasTable('link_lhp')) {
            try {
                $linkLhp = DB::table('link_lhp')->where('no_order', $noOrder)->first();
                if ($linkLhp) {
                    $reqMerge = new Request(['id' => $linkLhp->id]);
                    app(GenerateHasilPengujianController::class)->rerenderPdf($reqMerge);
                    $renderedDocs[] = "Merged LHP (link_lhp): {$linkLhp->filename}";
                }
            } catch (\Throwable $e) {
                \Log::warning("Backdate Order [{$noOrder}]: Gagal merge link_lhp - " . $e->getMessage());
            }
        }

        return $renderedDocs;
    }
}
