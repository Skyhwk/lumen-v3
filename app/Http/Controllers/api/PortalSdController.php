<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\{
    OrderHeader,
    QuotationNonKontrak,
    QuotationKontrakH,
    SampelDiantar,
    OrderDetail,
    SampelDiantarDetail
};

class PortalSdController extends Controller
{
    public function search(Request $request)
    {
        try {
            if (!str_contains($request->no_document, '/')) return response()->json(['message' => 'No. Dokumen Tidak Valid'], 400);

            $type = explode('/', $request->no_document);
            $search = OrderHeader::with(['orderDetail' => function ($q) {
                $q->where('kategori_1', 'SD');
                $q->where('is_active', true);
            }, 'SampelDiantar'],)
                ->where('no_document', $request->no_document)
                ->where('is_revisi',0)
                ->first();

            // logic untuk pra-order:
            if($search == null){
                if ($type[1] == 'QTC') {
                    $search = QuotationKontrakH::with(['SampelDiantar','detail' => function ($q) {
                        $q->whereIn('status_sampling', ['SD', 'SAR']);
                    }])->where('no_document', $request->no_document)
                    ->where('is_active', 1)
                    ->first();

                    if ($search) {
                        $detailColumn   = $search->detail;
                        $generatedSampel = [];
                        $counter         = 1; // nomor urut sampel

                        foreach ($detailColumn as $detail) {
                            // Struktur kontrak: { "8": { periode_kontrak, data_sampling: [...] } }
                            $pendukung = json_decode($detail->data_pendukung_sampling, true) ?? [];

                            foreach ($pendukung as $periodeKey => $periodeData) {
                                $periode     = $periodeData['periode_kontrak'] ?? null;
                                $dataSampling = $periodeData['data_sampling'] ?? [];

                                foreach ($dataSampling as $item) {
                                    $jumlahTitik    = (int) ($item['jumlah_titik'] ?? 1);
                                    foreach ($item['penamaan_titik'] as $titik) {
                                        foreach ($titik as $key => $namaLokasi) {
                                            // Format: TPTT01****/001
                                            $noSampel = $search->pelanggan_ID . '****/'. $key;
                                            $generatedSampel[] = [
                                                'no_sampel'    => $noSampel,
                                                'keterangan_1' => $namaLokasi,
                                                'kategori_2'   => $item['kategori_1'] ?? '',
                                                'kategori_3'   => $item['kategori_2'] ?? '',
                                                'parameter'    => json_encode($item['parameter'] ?? []),
                                                'persiapan'    => json_encode([
                                                    ['volume' => $item['volume'] ?? 0]  // ← ambil dari sini
                                                ]),
                                                'periode' => $periode,
                                                'tanggal_sampling'=>null,
                                                'no_order'=>null,
                                                'nama_perusahaan'=> $search->nama_perusahaan,
                                                'alamat_perusahaan'=> $search->alamat_kantor,
                                                'is_active' => true, // tambahkan flag is_active untuk pra-order
                                                'no_quotation' => $search->no_document,
                                            ];
                                        }
                                    }
                                }
                            }
                        }

                        $search->order_detail  = $generatedSampel;
                        $search->no_order      = null; // pastikan no_order null untuk pra-order
                        $search->sampel_diantar = [];
                        $search->is_pra_order  = true;
                    }
                }else{
                    $search = QuotationNonKontrak::with(['SampelDiantar'])->where('no_document',$request->no_document)
                    ->where('is_active',1)
                    ->first();
                     if ($search) {
                        // Generate no_sampel dari penamaan_titik
                        $pendukung = json_decode($search->data_pendukung_sampling, true) ?? [];
                        $generatedSampel = [];
                        foreach ($pendukung as $item) {
                            foreach ($item['penamaan_titik'] as $titik) {
                                foreach ($titik as $key => $namaLokasi) {
                                    // Format: TPTT01****/001
                                    $noSampel = $search->pelanggan_ID . '****/'. $key;
                                    $generatedSampel[] = [
                                        'no_sampel'    => $noSampel,
                                        'keterangan_1' => $namaLokasi,
                                        'kategori_2'   => $item['kategori_1'] ?? '',
                                        'kategori_3'   => $item['kategori_2'] ?? '',
                                        'parameter'    => json_encode($item['parameter'] ?? []),
                                         'persiapan'    => json_encode([
                                            ['volume' => $item['volume'] ?? 0]  // ← ambil dari sini
                                        ]),
                                        'periode' => null,
                                        'tanggal_sampling'=>null,
                                        'no_order'=>null,
                                        'nama_perusahaan'=> $search->nama_perusahaan,
                                        'alamat_perusahaan'=> $search->alamat_kantor
                                    ];
                                }
                            }
                        }
                    
                        // Inject sebagai virtual order_detail
                        $search->order_detail = $generatedSampel;
                        $search->no_order = null; // pastikan no_order null untuk pra-order
                        $search->sampel_diantar = [];
                        $search->is_pra_order = true;
                     }

                }
            }

            if ($type[1] == 'QTC') {
                return response()->json(['type' => 'kontrak', 'data' => $search], 200);
            } else {
                return response()->json(['type' => 'non_kontrak', 'data' => $search], 200);
            }
        } catch (\Exception $ex) {
            //throw $th;
            Log::error('PortalSdController methodsearch', [
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile()
            ], 500);
        }
    }

    public function belumOrder(Request $request)
    {
        try {
            $type = explode('/', $request->no_document);

            $linkPath = env('APP_URL') . '/public/quotation/';
            if ($type[1] === 'QTC') {
                $data = QuotationKontrakH::with(['detail' => function ($q) use ($request) {
                    $q->whereIn('status_sampling', ['SD', 'SAR']);
                }])
                    ->where('no_document', $request->no_document)
                    ->where('is_active', true)
                    ->first();
                $formatData = (object)[
                    'id_quotation' => $data->id,
                    'status_quotation' => 'kontrak',
                    "filedocument" => $linkPath . $data->filename,
                    "no_document" => $data->no_document,
                    "pelanggan_ID" => $data->pelanggan_ID,
                    "nama_perusahaan" => $data->nama_perusahaan,
                    "nama_pic_order" => $data->nama_pic_order,
                ];
            } else {
                $data = QuotationNonKontrak::where('no_document', $request->no_document)
                    ->where('flag_status', 'ordered')
                    ->whereIn('status_sampling', ['SD', 'SAR'])
                    ->where('is_active', true)
                    ->first();
                $formatData = (object)[
                    'id_quotation' => $data->id,
                    'status_quotation' => 'non_kontrak',
                    "filedocument" => $linkPath . $data->filename,
                    "no_document" => $data->no_document,
                    "pelanggan_ID" => $data->pelanggan_ID,
                    "nama_perusahaan" => $data->nama_perusahaan,
                    "nama_pic_order" => $data->nama_pic_order,
                ];
            }
            return response()->json($formatData, 200);
        } catch (\Exception $ex) {
            //throw $th;
            \Log::error('belumOrder ERROR', [
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile()
            ], 500);
        }
        //cek apakah sudah pernah order sebelumnya
    }

    public function storeHeader(Request $request)
    {
        
        DB::beginTransaction();
        try {
            $sampelDiantarID = $request->idSampelDiantar;
            if ($sampelDiantarID != null && $sampelDiantarID != "") {
                $chek = SampelDiantar::where('id', $sampelDiantarID)->first();
            } else {
                $chek = null;
            }
            if ($chek == null) {
                $data = new SampelDiantar;

                $bulanRomawi = [
                    1 => 'I',
                    2 => 'II',
                    3 => 'III',
                    4 => 'IV',
                    5 => 'V',
                    6 => 'VI',
                    7 => 'VII',
                    8 => 'VIII',
                    9 => 'IX',
                    10 => 'X',
                    11 => 'XI',
                    12 => 'XII',
                ];

                $prefix = 'ISL/TSD';
                $year = date('y'); // 2 digit tahun
                $month = $bulanRomawi[intval(date('n'))]; // bulan dalam Romawi
                $lastDocument = SampelDiantar::latest('no_document')->first();

                if ($lastDocument) {
                    $lastNumber = intval(substr($lastDocument->no_document, -6));
                    $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = '000001';
                }
                $data->no_quotation = $request->no_quotation;
                $data->no_order = $request->no_order;
                $data->nama_perusahaan = $request->nama_perusahaan;
                $data->alamat_perusahaan = $request->alamat_perusahaan;
                $data->nama_pengantar_sampel = $this->normalizePengantarName($request->nama_pengantar);
                $data->no_hp_pengantar = $request->no_hp_pengantar;
                $data->ekspedisi = $request->ekspedisi;
                $data->suhu = $request->suhu;
                $data->tercatat = $request->tercatat;
                $data->volume = $request->volume;
                $data->kondisi_ubnormal = json_encode($request->kondisi_ubnormal);
                $data->periode_kontrak = $request->periode;
                $data->tanggal_awal = $request->start_date;
                $data->tanggal_akhir = $request->end_date;
                $data->estimasi = $request->estimasi;
                $data->tanda_persetujuan_pengirim = $request->pihak_pengirim;
                $data->tanda_persetujuan_penerima = $request->pihak_penerima;
                $data->nama_penerima = $request->nama_penerima;
                $data->tanggal_sepakatan = $request->tanggal_sepakatan;

                $ttdFiles = $this->storeSignatureFiles($request);
                $data->ttd_pengirim = $ttdFiles['pengirim'];
                $data->ttd_penerima = $ttdFiles['penerima'];
                // $data->nomor_pic = $request->nomor_pic;
                // $data->ekspedisi = $request->ekspedisi;

                $data->no_document = "{$prefix}/{$year}-{$month}/{$newNumber}";
                $data->created_at = DATE('Y-m-d H:i:s');
                $data->save();
                $getId = $data->id;

                if ($request->no_order !== null) {
                    // Looping langsung dari data request
                    foreach ($request->tanggal_sampling as $item) {
                        OrderDetail::where('no_sampel', $item['no_sampel'])
                            ->where('kategori_1', 'SD')
                            ->where('is_active', 1)
                            ->update([
                                // Mengambil string tanggalnya saja, bukan array
                                'tanggal_sampling' => $item['tanggal_sampling'] 
                            ]);
                    }
                }
                DB::commit();
                return response()->json(['data' => $getId], 200);
            } else {
                $chek->nama_pengantar_sampel = $this->normalizePengantarName($request->nama_pengantar);
                $chek->no_hp_pengantar = $request->no_hp_pengantar;
                $chek->ekspedisi = $request->ekspedisi;
                $chek->suhu = $request->suhu;
                $chek->tercatat = $request->tercatat;
                $chek->volume = $request->volume;
                $chek->kondisi_ubnormal = json_encode($request->kondisi_ubnormal);
                $chek->alamat_perusahaan = $request->alamat_perusahaan;
                if ($request->filled('no_order')) {
                    $chek->no_order = $request->no_order;
                }
                if ($request->exists('nama_penerima')) {
                    $chek->nama_penerima = $request->nama_penerima;
                }
                if ($request->exists('pihak_pengirim')) {
                    $chek->tanda_persetujuan_pengirim = $request->pihak_pengirim;
                }
                if ($request->exists('pihak_penerima')) {
                    $chek->tanda_persetujuan_penerima = $request->pihak_penerima;
                }
                if ($request->exists('tanggal_sepakatan')) {
                    $chek->tanggal_sepakatan = $request->tanggal_sepakatan;
                }

                $ttdFiles = $this->storeSignatureFiles($request);
                if (!empty($ttdFiles['pengirim'])) {
                    $chek->ttd_pengirim = $ttdFiles['pengirim'];
                }
                if (!empty($ttdFiles['penerima'])) {
                    $chek->ttd_penerima = $ttdFiles['penerima'];
                }

                $chek->updated_at = DATE('Y-m-d H:i:s');
                $chek->save();

                if ($request->no_order !== null) {
                    // Looping langsung dari data request
                    foreach ($request->tanggal_sampling as $item) {
                        OrderDetail::where('no_sampel', $item['no_sampel'])
                            ->where('kategori_1', 'SD')
                            ->where('is_active', 1)
                            ->update([
                                // Mengambil string tanggalnya saja, bukan array
                                'tanggal_sampling' => $item['tanggal_sampling'] 
                            ]);
                    }
                }
                DB::commit();
                return response()->json(['data' => $chek->id], 200);
            }
            
        } catch (\Exception $ex) {
            //throw $th;
            DB::rollback();
            \Log::error('storeHeader ERROR', [
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'request' => $request->all(),
            ]);
            return response()->json([
                "message" => $ex->getMessage(),
                "line" => $ex->getLine(),
                "file" => $ex->getFile()
            ], 500);
        }
    }

    private function storeHeaderEksternal($request, $traceId = null)
    {
        $traceId = $traceId ?: ($request->input('trace_id') ?: uniqid('storeHeaderEksternal_', true));

        DB::beginTransaction();
        try {
            $sampelDiantarID = $request->idSampelDiantar;
            if ($sampelDiantarID != null && $sampelDiantarID != "") {
                $chek = SampelDiantar::where('id', $sampelDiantarID)->first();
            } else {
                $chek = null;
            }
            if ($chek !== null) {
                DB::commit();
                Log::info('[PortalSd][storeHeaderEksternal] Header sudah ada', [
                    'trace_id' => $traceId,
                    'id' => $chek->id,
                ]);
                return $chek;
            }
            if ($chek == null) {
                $data = new SampelDiantar;

                $bulanRomawi = [
                    1 => 'I',
                    2 => 'II',
                    3 => 'III',
                    4 => 'IV',
                    5 => 'V',
                    6 => 'VI',
                    7 => 'VII',
                    8 => 'VIII',
                    9 => 'IX',
                    10 => 'X',
                    11 => 'XI',
                    12 => 'XII',
                ];

                $prefix = 'ISL/TSD';
                $year = date('y'); // 2 digit tahun
                $month = $bulanRomawi[intval(date('n'))]; // bulan dalam Romawi
                $lastDocument = SampelDiantar::latest('no_document')->first();

                if ($lastDocument) {
                    $lastNumber = intval(substr($lastDocument->no_document, -6));
                    $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = '000001';
                }
                $data->no_quotation = $request->no_document;
                $data->no_order = $request->no_order;
                $data->nama_perusahaan = $request->nama_perusahaan;
                $data->no_document = "{$prefix}/{$year}-{$month}/{$newNumber}";
                $data->created_at = DATE('Y-m-d H:i:s');
                $data->save();
                $getId = $data;
                DB::commit();
                return $getId;
            }
            
        } catch (\Exception $ex) {
            //throw $th;
            DB::rollback();
            Log::error('[PortalSd][storeHeaderEksternal] ERROR', [
                'trace_id' => $traceId,
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'request' => $request->all(),
            ]);
            return response()->json([
                "error" => true,
                "message" => $ex->getMessage(),
                "line" => $ex->getLine(),
                "file" => $ex->getFile(),
                "trace_id" => $traceId,
            ], 500);
        }
    }

    private function normalizePeriode($periode)
    {
        if ($periode === null || $periode === '' || $periode === 'null' || $periode === 'undefined') {
            return null;
        }

        return $periode;
    }

    private function findSampelDiantarDetail($headerId, $periode)
    {
        if (!$headerId) {
            return null;
        }

        $query = SampelDiantarDetail::where('id_header', $headerId);
        if ($periode === null) {
            $query->where(function ($q) {
                $q->whereNull('periode')
                    ->orWhere('periode', '')
                    ->orWhere('periode', 'null');
            });
        } else {
            $query->where('periode', $periode);
        }

        return $query->first();
    }

    public function saveStep(Request $request)
    {
        $traceId = $request->input('trace_id') ?: uniqid('lumen_saveStep_', true);

        try {
            Log::info('[PortalSd][saveStep] Start', [
                'trace_id' => $traceId,
                'mode' => $request->mode,
                'idSampelDiantar' => $request->idSampelDiantar,
                'periode' => $request->periode,
                'no_order' => $request->no_order,
                'no_document' => $request->no_document,
                'has_external_data' => !empty($request->external_data),
                'has_internal_data' => !empty($request->internal_data),
                'sample_count' => is_array($request->external_data)
                    ? count($request->external_data)
                    : (is_array($request->internal_data) ? count($request->internal_data) : null),
            ]);

            if ($request->mode == 'internal') {
                $dataSave = SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                    ->where('periode', $request->periode)
                    ->first();
                $incoming = $request->internal_data;
                $incoming = is_array($incoming) ? $incoming : json_decode($incoming, true);
                $currentDateTime = date('Y-m-d H:i:s');

                if ($dataSave !== null) {
                    if ($dataSave->internal_data !== null) {
                        $existing = json_decode($dataSave->internal_data);
                        $indexed = [];

                        foreach ($existing as $item) {
                            $item = (array) $item;
                            // pastikan history selalu array (jaga-jaga dari data lama)
                            if (!isset($item['history']) || !is_array($item['history'])) {
                                $item['history'] = [];
                            }
                            $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                            $indexed[$key] = $item;
                        }

                        foreach ($incoming as $item) {
                            $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];

                            if (!isset($indexed[$key])) {
                                // Data baru — belum pernah ada
                                $item['date_time'] = $currentDateTime;
                                $item['is_active'] = true;
                                $item['history']   = []; // baru, belum ada perubahan
                            } else {
                                $existingItem = $indexed[$key];
                                $isChanged    = false;
                                $changedFields = [];

                                // 1. Cek field sederhana
                                $fieldsToCheck = ['ph', 'dhl', 'sistem_lock', 'jenis_sampel', 'warna', 'keruh', 'bau', 'suhu'];
                                foreach ($fieldsToCheck as $field) {
                                    $newValue = $item[$field] ?? null;
                                    $oldValue = $existingItem[$field] ?? null;
                                    if ($newValue != $oldValue) {
                                        $isChanged = true;
                                        $changedFields[$field] = [
                                            'old' => $oldValue,
                                            'new' => $newValue,
                                        ];
                                    }
                                }

                                // 2. Cek jenis_wadah
                                $newWadah = $item['jenis_wadah'] ?? [];
                                $oldWadah = $existingItem['jenis_wadah'] ?? [];
                                if (!is_array($newWadah)) $newWadah = (array) $newWadah;
                                if (!is_array($oldWadah)) $oldWadah = (array) $oldWadah;
                                sort($newWadah);
                                sort($oldWadah);
                                if (json_encode($newWadah) !== json_encode($oldWadah)) {
                                    $isChanged = true;
                                    $changedFields['jenis_wadah'] = [
                                        'old' => $existingItem['jenis_wadah'] ?? [],
                                        'new' => $item['jenis_wadah'] ?? [],
                                    ];
                                }

                                // Ambil history lama
                                $oldHistory = $existingItem['history'] ?? [];
                                if (!is_array($oldHistory)) $oldHistory = [];

                                if ($isChanged) {
                                    // Ada perubahan — append ke history, update date_time
                                    $oldHistory[] = [
                                        'changed_at'     => $currentDateTime,
                                        'changed_fields' => $changedFields,
                                    ];
                                    $item['date_time'] = $currentDateTime;
                                } else {
                                    // Tidak ada perubahan — pertahankan date_time lama
                                    $item['date_time'] = $existingItem['date_time'];
                                }

                                // History selalu dipertahankan, isi atau kosong
                                $item['history']   = $oldHistory;
                                $item['is_active'] = $existingItem['is_active'] ?? true;
                            }

                            $indexed[$key] = $item;
                        }

                        $merged = array_values($indexed);
                        $dataToSave = [
                            'internal_data' => json_encode($merged),
                            'update_at'     => $currentDateTime,
                        ];
                        SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                            ->where('periode', $request->periode)
                            ->update($dataToSave);

                    } else {
                        // Record ada tapi internal_data masih null — insert pertama kali
                        foreach ($incoming as &$item) {
                            $item['date_time'] = $currentDateTime;
                            $item['is_active'] = true;
                            $item['history']   = [];
                        }
                        unset($item);

                        $dataToSave = [
                            'periode'          => $request->periode,
                            'tanggal_sampling' => date('Y-m-d'),
                            'update_at'        => $currentDateTime,
                            'update_by'        => 'start Internal',
                            'internal_data'    => json_encode($incoming),
                        ];
                        SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                            ->where('periode', $request->periode)
                            ->update($dataToSave);
                    }
                } else {
                    // Record belum ada sama sekali — create baru
                    foreach ($incoming as &$item) {
                        $item['date_time'] = $currentDateTime;
                        $item['is_active'] = true;
                        $item['history']   = [];
                    }
                    unset($item);

                    $dataToSave = [
                        'id_header'        => $request->idSampelDiantar,
                        'periode'          => $request->periode,
                        'tanggal_sampling' => date('Y-m-d'),
                        'created_at'       => $currentDateTime,
                        'internal_data'    => json_encode($incoming),
                    ];
                    SampelDiantarDetail::create($dataToSave);
                }

                return response()->json([
                    'sampeldiantarid' => $request->idSampelDiantar,
                    'periode'         => $request->periode,
                ], 200);
            } else if ($request->mode == 'external') {
                $periode = $this->normalizePeriode($request->periode);
                $headerId = $request->idSampelDiantar;
                $dataSave = $this->findSampelDiantarDetail($headerId, $periode);
                $isExistingDetail = $dataSave instanceof SampelDiantarDetail;

                if (!$isExistingDetail) {
                    Log::info('[PortalSd][saveStep] Detail belum ada, storeHeaderEksternal', [
                        'trace_id' => $traceId,
                        'idSampelDiantar' => $headerId,
                        'periode' => $periode,
                    ]);
                    $createdHeader = $this->storeHeaderEksternal($request, $traceId);
                    if ($createdHeader instanceof \Illuminate\Http\JsonResponse) {
                        Log::error('[PortalSd][saveStep] storeHeaderEksternal gagal', [
                            'trace_id' => $traceId,
                            'response' => $createdHeader->getContent(),
                        ]);
                        return $createdHeader;
                    }
                    $headerId = $createdHeader->id ?? $headerId;
                    $dataSave = $this->findSampelDiantarDetail($headerId, $periode);
                    $isExistingDetail = $dataSave instanceof SampelDiantarDetail;
                } else {
                    $headerId = $dataSave->id_header ?: $headerId;
                }

                $incoming = $request->external_data;
                $incoming = is_array($incoming) ? $incoming : json_decode($incoming, true);
                if (!is_array($incoming) || empty($incoming)) {
                    Log::error('[PortalSd][saveStep] external_data kosong/tidak valid', [
                        'trace_id' => $traceId,
                        'idSampelDiantar' => $headerId,
                    ]);
                    return response()->json([
                        'error' => true,
                        'message' => 'Data eksternal kosong, tidak ada yang disimpan.',
                        'trace_id' => $traceId,
                    ], 422);
                }

                $merged = $incoming;
                $currentDateTime = date('Y-m-d H:i:s');
                $existing = [];

                if ($isExistingDetail && !empty($dataSave->eksternal_data)) {
                    $existing = json_decode($dataSave->eksternal_data, true) ?? [];
                }

                if (!empty($existing)) {
                    $indexed = [];

                    foreach ($existing as $item) {
                        if (!isset($item['history']) || !is_array($item['history'])) {
                            $item['history'] = [];
                        }
                        $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                        $indexed[$key] = $item;
                    }

                    foreach ($incoming as $item) {
                        $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];

                        if (!isset($indexed[$key])) {
                            $item['date_time'] = $currentDateTime;
                            $item['is_active'] = true;
                            $item['history']   = [];
                        } else {
                            $existingItem  = $indexed[$key];
                            $isChanged     = false;
                            $changedFields = [];

                            $fieldsToCheck = [
                                'ph', 'dhl', 'suhu', 'is_pengawetan', 'is_uji_insitu',
                                'deskripsi_titik', 'is_pencucian_wadah', 'is_blanko_pencucian',
                                'cara_pengambilan_sampel', 'waktu_diambil_pelanggan',
                                'deskripsi_blanko_pencucian', 'tanggal_diambil_oleh_pihak_pelanggan'
                            ];

                            foreach ($fieldsToCheck as $field) {
                                $newValue = $item[$field] ?? null;
                                $oldValue = $existingItem[$field] ?? null;
                                if ($newValue != $oldValue) {
                                    $isChanged = true;
                                    $changedFields[$field] = [
                                        'old' => $oldValue,
                                        'new' => $newValue,
                                    ];
                                }
                            }

                            if (isset($item['jenis_wadah'])) {
                                $newWadah = $item['jenis_wadah'] ?? [];
                                $oldWadah = $existingItem['jenis_wadah'] ?? [];
                                if (!is_array($newWadah)) $newWadah = (array) $newWadah;
                                if (!is_array($oldWadah)) $oldWadah = (array) $oldWadah;
                                sort($newWadah);
                                sort($oldWadah);
                                if (json_encode($newWadah) !== json_encode($oldWadah)) {
                                    $isChanged = true;
                                    $changedFields['jenis_wadah'] = [
                                        'old' => $existingItem['jenis_wadah'] ?? [],
                                        'new' => $item['jenis_wadah'] ?? [],
                                    ];
                                }
                            }

                            $oldHistory = $existingItem['history'] ?? [];
                            if (!is_array($oldHistory)) $oldHistory = [];

                            if ($isChanged) {
                                $oldHistory[] = [
                                    'changed_at'     => $currentDateTime,
                                    'changed_fields' => $changedFields,
                                ];
                                $item['date_time'] = $currentDateTime;
                            } else {
                                $item['date_time'] = $existingItem['date_time'];
                            }

                            $item['history']   = $oldHistory;
                            $item['is_active'] = $existingItem['is_active'] ?? true;
                        }

                        $indexed[$key] = $item;
                    }

                    $merged = array_values($indexed);
                } else {
                    foreach ($incoming as &$item) {
                        $item['date_time'] = $currentDateTime;
                        $item['is_active'] = true;
                        $item['history']   = [];
                    }
                    unset($item);
                    $merged = $incoming;
                }

                $savePayload = [
                    'eksternal_data'                       => json_encode($merged),
                    'petugas_pengambilan_sampel'           => $request->sampler,
                    'update_at'                            => $currentDateTime,
                    'is_ukur_suhu'                         => $request->is_ukur_suhu,
                    'tanggal_diambil_oleh_pihak_pelanggan' => $request->tanggal_diambil_oleh_pihak_pelanggan,
                    'tujuan_pengujian'                     => json_encode($request->tujuan_pengujian),
                    'waktu_diambil_pelanggan'              => $request->waktu_diambil_pelanggan,
                    'nama_sertifikat'                      => $request->nama_sertifikat,
                    'metode_standar'                       => $request->metode_standar,
                    'sampler'                              => $request->sampler,
                    'cara_pengambilan_sample'              => $request->cara_pengambilan_sample,
                    'periode'                              => $periode,
                ];

                if ($isExistingDetail) {
                    $affected = SampelDiantarDetail::where('id', $dataSave->id)->update($savePayload);
                    Log::info('[PortalSd][saveStep] Update detail eksternal', [
                        'trace_id' => $traceId,
                        'detail_id' => $dataSave->id,
                        'id_header' => $headerId,
                        'periode' => $periode,
                        'affected_rows' => $affected,
                    ]);
                    if ($affected < 1) {
                        return response()->json([
                            'error' => true,
                            'message' => 'Update data eksternal tidak mengenai baris manapun.',
                            'trace_id' => $traceId,
                        ], 500);
                    }
                } else {
                    if (!$headerId) {
                        Log::error('[PortalSd][saveStep] Header/detail eksternal null sebelum create', [
                            'trace_id' => $traceId,
                            'idSampelDiantar' => $request->idSampelDiantar,
                            'periode' => $periode,
                        ]);
                        return response()->json([
                            'error' => true,
                            'message' => 'Gagal menyimpan data eksternal: header sampel diantar tidak ditemukan.',
                            'trace_id' => $traceId,
                        ], 500);
                    }

                    $dataSave = SampelDiantarDetail::create(array_merge($savePayload, [
                        'id_header'  => $headerId,
                        'created_at' => $currentDateTime,
                        'created_by' => 'start Eksternal',
                    ]));

                    Log::info('[PortalSd][saveStep] Create detail eksternal', [
                        'trace_id' => $traceId,
                        'detail_id' => $dataSave->id,
                        'id_header' => $headerId,
                        'periode' => $periode,
                    ]);
                }

                $tanggalTerimaMap = collect($request->tanggal_terima ?? [])
                    ->filter(fn($item) => !empty($item['no_sampel']) && !empty($item['tanggal_terima']))
                    ->keyBy('no_sampel');

                if ($tanggalTerimaMap->isNotEmpty()) {
                    $perluDiupdate = OrderDetail::whereIn('no_sampel', $tanggalTerimaMap->keys())
                        ->whereNull('tanggal_terima')
                        ->pluck('no_sampel');

                    if ($perluDiupdate->isNotEmpty()) {
                        foreach ($perluDiupdate as $noSampel) {
                            OrderDetail::where('no_sampel', $noSampel)
                                ->update([
                                    'tanggal_terima' => $tanggalTerimaMap[$noSampel]['tanggal_terima'],
                                ]);
                        }
                    }
                }

                Log::info('[PortalSd][saveStep] External success', [
                    'trace_id' => $traceId,
                    'sampeldiantarid' => $headerId,
                    'detail_id' => $dataSave->id ?? null,
                    'periode' => $periode,
                    'action' => $isExistingDetail ? 'update' : 'create',
                ]);

                return response()->json([
                    'sampeldiantarid' => $headerId,
                    'periode'         => $periode,
                    'trace_id'        => $traceId,
                ], 200);
            }

            Log::warning('[PortalSd][saveStep] Mode tidak dikenali', [
                'trace_id' => $traceId,
                'mode' => $request->mode,
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Mode saveStep tidak dikenali: ' . (string) $request->mode,
                'trace_id' => $traceId,
            ], 422);
        } catch (\Exception $ex) {
            Log::error('[PortalSd][saveStep] ERROR', [
                'trace_id' => $traceId,
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'mode' => $request->mode,
                'idSampelDiantar' => $request->idSampelDiantar,
                'periode' => $request->periode,
                'request' => $request->except(['trace_id']),
            ]);
            return response()->json([
                'error' => true,
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile(),
                'trace_id' => $traceId,
            ], 500);
        }
    }
    public function listSampel(Request $request)
    {
        try {
            
            $type = explode('/', $request->no_document);
            $datas = OrderDetail::where('kategori_1', 'SD')
                ->where('no_order', $request->no_order?: null)
                ->where('is_active', true)
                ->where('periode', (isset($request->periode)) ? $request->periode : null)
                ->get();
            /**
             * jika no qt belum order
             * agar tidak mengganggu flow yang sudah berjalan, tetap pakai variabale $datas untuk menampung data order detail, walaupun sebenarnya data order detail tidak ada karena belum order, tapi dengan cara ini tidak perlu banyak ubahan di frontend, karena frontend tetap bisa mengakses $datas untuk menampilkan data sampel, hanya saja datanya kosong, dan untuk data sampel yang sudah diisi di pra-order tetap bisa ditampilkan karena sudah di merge di function search sebelumnya
             */
            if ($type[1] == 'QTC') {
                if($datas->isEmpty()){
                    $datas = QuotationKontrakH::with(['detail' => function ($q) {
                        $q->whereIn('status_sampling', ['SD', 'SAR']);
                    }])->where('no_document', $request->no_document)
                    ->where('is_active', 1)
                    ->first();

                    if ($datas) {
                        $detailColumn   = $datas->detail;
                        $generatedSampel = [];
                        $counter         = 1; // nomor urut sampel

                        foreach ($detailColumn as $detail) {
                            // Struktur kontrak: { "8": { periode_kontrak, data_sampling: [...] } }
                            $pendukung = json_decode($detail->data_pendukung_sampling, true) ?? [];

                            foreach ($pendukung as $periodeKey => $periodeData) {
                                $periode     = $periodeData['periode_kontrak'] ?? null;
                                $dataSampling = $periodeData['data_sampling'] ?? [];
                                if($periode == $request->periode){
                                    foreach ($dataSampling as $item) {
                                        $jumlahTitik    = (int) ($item['jumlah_titik'] ?? 1);
                                        foreach ($item['penamaan_titik'] as $titik) {
                                            foreach ($titik as $key => $namaLokasi) {
                                                // Format: TPTT01****/001
                                                $noSampel = $datas->pelanggan_ID . '****/'. $key;
                                                $generatedSampel[] = [
                                                    'no_sampel'    => $noSampel,
                                                    'keterangan_1' => $namaLokasi,
                                                    'kategori_2'   => $item['kategori_1'] ?? '',
                                                    'kategori_3'   => $item['kategori_2'] ?? '',
                                                    'parameter'    => json_encode($item['parameter'] ?? []),
                                                    'persiapan'    => json_encode([
                                                        ['volume' => $item['volume'] ?? 0]  // ← ambil dari sini
                                                    ]),
                                                    'periode' => $periode,
                                                    'tanggal_sampling'=>null,
                                                    'no_order'=>null,
                                                    'nama_perusahaan'=> $datas->nama_perusahaan,
                                                    'alamat_perusahaan'=> $datas->alamat_kantor,
                                                    'is_active' => true, // tambahkan flag is_active untuk pra-order
                                                    'no_quotation' => $datas->no_document,
                                                ];
                                            }
                                        }
                                    }   
                                }
                            }
                        }

                        $datas  = collect($generatedSampel);
                    }
                }
                $sampelDiantarID = SampelDiantar::with(['detail' => function ($q) use ($request) {
                    // $q->where('periode', $request->periode);
                }])
                ->where('no_quotation', $request->no_document)
                ->where('no_order', $request->no_order)
                ->where('periode_kontrak',$request->periode)->first();
                if ($sampelDiantarID !== null) {
                    return response()->json(['type' => 'kontrak', 'data' => $datas, 'sd' => $sampelDiantarID], 200);
                } else {
                    return response()->json(['type' => 'kontrak', 'data' => $datas, 'sd' => null], 200);
                }
            } else {
                if($datas->isEmpty()){
                    $dataQt = QuotationNonKontrak::where('no_document',$request->no_document)
                    ->where('is_active',1)
                    ->where('status_sampling','SD')
                    ->where('is_active',true)
                    ->first();
                    if($dataQt != null){
                        $dataPendukung = json_decode($dataQt->data_pendukung_sampling, true) ?? [];
                        $generatedSampel = [];
                        foreach ($dataPendukung as $item) {
                            foreach ($item['penamaan_titik'] as $titik) {
                                foreach ($titik as $key => $namaLokasi) {
                                    $noSampel = $dataQt->pelanggan_ID . '****/'. $key;
                                    $generatedSampel[] = [
                                        'no_sampel'    => $noSampel,
                                        'keterangan_1' => $namaLokasi,
                                        'kategori_2'   => $item['kategori_1'] ?? '',
                                        'kategori_3'   => $item['kategori_2'] ?? '',
                                        'parameter'    => json_encode($item['parameter'] ?? []),
                                        'persiapan'    => json_encode([
                                            ['volume' => $item['volume'] ?? 0]  // ← ambil dari sini
                                        ]),
                                        'periode' => null,
                                        'tanggal_sampling'=>null,
                                        'no_order'=>null,
                                        'nama_perusahaan'=> $dataQt->nama_perusahaan,
                                        'alamat_perusahaan'=> $dataQt->alamat_kantor
                                    ];
                                }
                            }
                        }
                    }
                        // Inject sebagai virtual order_detail
                    $datas = collect($generatedSampel); // buat collection agar konsisten dengan format sebelumnya

                }
                $sampelDiantarID = SampelDiantar::with(['detail' => function ($q) use ($request) {
                    $periode = $this->normalizePeriode($request->periode);
                    if ($periode === null) {
                        $q->where(function ($qq) {
                            $qq->whereNull('periode')
                                ->orWhere('periode', '')
                                ->orWhere('periode', 'null');
                        });
                    } else {
                        $q->where('periode', $periode);
                    }
                }])
                ->where('no_quotation', $request->no_document)
                ->where('no_order', $request->no_order ?: null)
                ->where('periode_kontrak',$request->periode ?: null)->first();
                if ($sampelDiantarID !== null) {
                    return response()->json(['type' => 'non_kontrak', 'data' => $datas, 'sd' => $sampelDiantarID], 200);
                } else {
                    return response()->json(['type' => 'non_kontrak', 'data' => $datas, 'sd' => null], 200);
                }
            }
        } catch (\Exception $ex) {
            //throw $th;
            \Log::error('listSampel ERROR', [
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile()
            ], 500);
        }
    }

    public function getFile(Request $request)
    {
        $linkPath = env('APP_URL') . '/public/quotation/';
        $type = explode('/', $request->no_document);
        if ($type[1] === 'QTC') {
            $data = QuotationKontrakH::where('no_document', $request->no_document)
                ->where('is_active', true)
                ->where('flag_status', null)
                ->first();

            $formatData = (object)[
                'id_quotation' => $data->id,
                'status_quotation' => 'kontrak',
                "filedocument" => $linkPath . $data->filename,
                "no_document" => $data->no_document,
                "pelanggan_ID" => $data->pelanggan_ID,
                "nama_perusahaan" => $data->nama_perusahaan,
                "nama_pic_order" => $data->nama_pic_order,
            ];
        } else {
            $data = QuotationNonKontrak::where('no_document', $request->no_document)
                ->where('is_active', true)
                ->where('flag_status', null)
                ->first();
            $formatData = (object)[
                'id_quotation' => $data->id,
                'status_quotation' => 'kontrak',
                "filedocument" => $linkPath . $data->filename,
                "no_document" => $data->no_document,
                "pelanggan_ID" => $data->pelanggan_ID,
                "nama_perusahaan" => $data->nama_perusahaan,
                "nama_pic_order" => $data->nama_pic_order,
            ];
        }

        return response()->json($formatData, 200);
    }

    private function storeSignatureFiles(Request $request): array
    {
        $path = public_path('dokumen/sd');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        $save = function ($base64, $prefix) use ($path) {
            if (empty($base64) || strpos($base64, 'data:image') !== 0) {
                return null;
            }
            @list($type, $data) = explode(';', $base64);
            @list(, $data) = explode(',', $data);
            $binary = base64_decode($data);
            if ($binary === false) {
                return null;
            }
            $fileName = $prefix . time() . '.png';
            file_put_contents($path . DIRECTORY_SEPARATOR . $fileName, $binary);
            return $fileName;
        };

        return [
            'pengirim' => $save($request->base64ttd_1, 'signature_pengirim_'),
            'penerima' => $save($request->base64ttd_2, 'signature_penerima_'),
        ];
    }

    public function chekSD(Request $request)
    {
        if ($request->status_quotation == 'kontrak') {
            $chekSD = QuotationKontrakH::with(['detail' => function ($q) {
                $q->whereIn('status_sampling', ['SD', 'SAR']);
            }])
                ->where('id', $request->id)
                //->where('flag_status', 'ordered')
                ->where('is_active', true)->first();

            if ($chekSD != null) {
                $hasSD = collect($chekSD->detail)->contains(fn ($item) => in_array($item->status_sampling, ['SD', 'SAR']));
                return response()->json(["status" => $hasSD], 200);
            } else {
                return response()->json(["status" => false], 200);
            }
        } else {
            $chekSD = QuotationNonKontrak::where('id', $request->id)
                ->whereIn('status_sampling', ['SD', 'SAR'])
                //->where('flag_status', 'ordered')
                ->where('is_active', true)->first();
            if ($chekSD != null) {
                return response()->json(["status" => true], 200);
            } else {
                return response()->json(["status" => false], 200);
            }
        }
    }


    public function chekStepSd(Request $request)
    {
        try {
            $mode = $request->mode;
            $periode = $this->normalizePeriode($request->periode);
            $noOrder = ($request->no_order === 'undefined' || $request->no_order === '')
                ? null
                : $request->no_order;

            $sampelDiantar = $this->findSampelDiantarHeader($request->no_document, $periode, $noOrder);

            if ($mode === 'terima') {
                $isValid = $this->isPenerimaanComplete($sampelDiantar);
                Log::info('[PortalSd][chekStepSd] terima', [
                    'no_document' => $request->no_document,
                    'periode' => $periode,
                    'header_id' => $sampelDiantar->id ?? null,
                    'nama_pengantar' => $sampelDiantar->nama_pengantar_sampel ?? null,
                    'no_hp_pengantar' => $sampelDiantar->no_hp_pengantar ?? null,
                    'suhu' => $sampelDiantar->suhu ?? null,
                    'ttd_pengirim' => $sampelDiantar->ttd_pengirim ?? null,
                    'status' => $isValid,
                ]);
                return response()->json([
                    'status' => $isValid,
                    'message' => $isValid
                        ? 'Penerimaan sudah lengkap.'
                        : 'Penerimaan belum lengkap. Lengkapi tanda terima sampel terlebih dahulu.',
                    'mode_checked' => $mode,
                ], 200);
            }

            // 3. Validasi Keberadaan Data
            if (!in_array($mode, ['internal_data', 'eksternal_data']) || !$sampelDiantar) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data sampel diantar tidak ditemukan atau mode tidak valid.'
                ], 200);
            }

            // 4. Ambil Detail Spesifik
            $detailForPeriod = null;
            if ($sampelDiantar->detail->isNotEmpty()) {
                $detailForPeriod  = $sampelDiantar->detail->first(function ($item) use ($periode) {
                    return $this->normalizePeriode($item->periode) === $periode;
                });
                if (!$detailForPeriod) {
                    $detailForPeriod = $sampelDiantar->detail->first();
                }
            }

            if (!$detailForPeriod) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Detail sampel untuk periode yang diminta tidak ditemukan.'
                ], 200);
            }

            // 5. Ambil Data Referensi dari OrderDetail
            $orderQuery = OrderDetail::where('kategori_1', 'SD')
                ->where('is_active', true)
                ->where('periode', $periode);

            if ($noOrder) {
                $orderQuery->where('no_order', $noOrder);
            } else {
                $orderQuery->where('no_quotation', $request->no_document);
            }

            $orderDetails = $orderQuery->get(['no_sampel', 'kategori_3']);

            if ($orderDetails->isEmpty()) {
                $type = explode('/', $request->no_document);

                if ($type[1] == 'QTC') {
                    $dataQt = QuotationKontrakH::with(['detail' => function ($q) {
                        $q->whereIn('status_sampling', ['SD', 'SAR']);
                    }])->where('no_document', $request->no_document)
                    ->where('is_active', 1)
                    ->first();

                    if ($dataQt != null) {
                        $detailColumn    = $dataQt->detail;
                        $generatedSampel = [];
                        $counter         = 1;

                        foreach ($detailColumn as $detail) {
                            $pendukung = json_decode($detail->data_pendukung_sampling, true) ?? [];

                            foreach ($pendukung as $periodeKey => $periodeData) {
                                $periodeKontrak = $periodeData['periode_kontrak'] ?? null;
                                $dataSampling = $periodeData['data_sampling'] ?? [];

                                if ($this->normalizePeriode($periodeKontrak) !== $periode) {
                                    continue;
                                }

                                foreach ($dataSampling as $item) {
                                    $jumlahTitik   = (int) ($item['jumlah_titik'] ?? 1);
                                    $penamaanTitik = $item['penamaan_titik'] ?? '';

                                    foreach ($penamaanTitik as $titik) {
                                        foreach ($titik as $key => $namaLokasi) {
                                            $noSampel = $dataQt->pelanggan_ID . '****/'. $key;
                                            $generatedSampel[] = [
                                                'no_sampel'    => $noSampel,
                                                'keterangan_1' => $namaLokasi,
                                                'kategori_2'   => $item['kategori_1'] ?? '',
                                                'kategori_3'   => $item['kategori_2'] ?? '',
                                                'parameter'    => json_encode($item['parameter'] ?? []),
                                                'persiapan'    => json_encode([
                                                    ['volume' => $item['volume'] ?? 0]
                                                ]),
                                                'periode' => $periodeKontrak,
                                                'tanggal_sampling'=>null,
                                                'no_order'=>null,
                                                'nama_perusahaan'=> $dataQt->nama_perusahaan,
                                                'alamat_perusahaan'=> $dataQt->alamat_kantor,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                        $orderDetails = collect($generatedSampel);
                    }

                } else {
                    $dataQt = QuotationNonKontrak::where('no_document', $request->no_document)
                        ->where('is_active', 1)
                        ->whereIn('status_sampling', ['SD', 'SAR'])
                        ->first();

                    if ($dataQt != null) {
                        $dataPendukung   = json_decode($dataQt->data_pendukung_sampling, true) ?? [];
                        $generatedSampel = [];

                        foreach ($dataPendukung as $item) {
                            foreach ($item['penamaan_titik'] as $titik) {
                                foreach ($titik as $key => $namaLokasi) {
                                    $noSampel = $dataQt->pelanggan_ID . '****/'. $key;
                                    $generatedSampel[] = [
                                        'no_sampel'        => $noSampel,
                                        'keterangan_1'     => $namaLokasi,
                                        'kategori_2'       => $item['kategori_1'] ?? '',
                                        'kategori_3'       => $item['kategori_2'] ?? '',
                                        'parameter'        => json_encode($item['parameter'] ?? []),
                                        'persiapan'        => json_encode([['volume' => $item['volume'] ?? 0]]),
                                        'periode'          => null,
                                        'tanggal_sampling' => null,
                                        'no_order'         => null,
                                        'nama_perusahaan'  => $dataQt->nama_perusahaan,
                                        'alamat_perusahaan'=> $dataQt->alamat_kantor,
                                    ];
                                }
                            }
                        }
                        $orderDetails = collect($generatedSampel);
                    }
                }
            }

            $targetCount        = $orderDetails->count();
            $orderSampelNumbers = $orderDetails->pluck('no_sampel')->filter()->values()->toArray();

            $dataField = $mode === 'internal_data' ? 'internal_data' : 'eksternal_data';
            $filledSampel = $this->collectActiveNoSampel($detailForPeriod->$dataField ?? '[]');
            $currentActiveCount = $this->countMatchedSamples($filledSampel, $orderSampelNumbers);

            $isValid = false;
            $message = '';

            if ($targetCount < 1) {
                $isValid = false;
                $message = 'Daftar no_sampel belum bisa ditentukan, step ini belum bisa dianggap selesai.';
            } elseif ($mode === 'internal_data') {
                $isValid = ($currentActiveCount === $targetCount);
                $message = $isValid
                    ? 'Validasi Internal Berhasil.'
                    : "Data Internal belum lengkap ($currentActiveCount dari $targetCount).";
            } elseif ($mode === 'eksternal_data') {
                $internalFilled = $this->collectActiveNoSampel($detailForPeriod->internal_data ?? '[]');
                $internalCount = $this->countMatchedSamples($internalFilled, $orderSampelNumbers);

                if ($internalCount !== $targetCount) {
                    $isValid = false;
                    $message = "Data Internal belum lengkap ($internalCount dari $targetCount). Selesaikan Internal Data terlebih dahulu.";
                } else {
                    $isValid = ($currentActiveCount === $targetCount);
                    $message = $isValid
                        ? 'Validasi Eksternal Berhasil.'
                        : "Data Eksternal belum lengkap ($currentActiveCount dari $targetCount).";
                }
            }

            Log::info('[PortalSd][chekStepSd] result', [
                'mode' => $mode,
                'no_document' => $request->no_document,
                'no_order' => $noOrder,
                'periode' => $periode,
                'header_id' => $sampelDiantar->id ?? null,
                'detail_id' => $detailForPeriod->id ?? null,
                'target_count' => $targetCount,
                'filled_count' => $currentActiveCount,
                'expected_samples' => $orderSampelNumbers,
                'filled_samples' => $filledSampel,
                'status' => $isValid,
                'message' => $message,
            ]);

            return response()->json([
                'status'       => $isValid,
                'message'      => $message,
                'od_count'     => $targetCount,
                'sd_count'     => $currentActiveCount,
                'mode_checked' => $mode,
            ], 200);

        } catch (\Exception $ex) {
            \Log::error('chekStepSd ERROR', [
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
                'file'    => $ex->getFile(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan server.',
                'error'   => $ex->getMessage(),
                'line'    => $ex->getLine(),
            ], 500);
        }
    }

    private function findSampelDiantarHeader($noDocument, $periode, $noOrder = null)
    {
        $withDetail = function ($q) use ($periode) {
            if ($periode === null) {
                $q->where(function ($qq) {
                    $qq->whereNull('periode')
                        ->orWhere('periode', '')
                        ->orWhere('periode', 'null');
                });
            } else {
                $q->where('periode', $periode);
            }
        };

        $query = SampelDiantar::with(['detail' => $withDetail])
            ->where('no_quotation', $noDocument);

        if ($periode === null) {
            $query->where(function ($q) {
                $q->whereNull('periode_kontrak')
                    ->orWhere('periode_kontrak', '')
                    ->orWhere('periode_kontrak', 'null');
            });
        } else {
            $query->where('periode_kontrak', $periode);
        }

        $header = $query->first();
        if ($header) {
            return $header;
        }

        $fallback = SampelDiantar::with(['detail' => $withDetail])
            ->where('no_quotation', $noDocument);

        if ($noOrder) {
            $fallback->where('no_order', $noOrder);
        }

        return $fallback->first();
    }

    private function normalizePengantarName($value): ?string
    {
        $pengantar = trim((string) ($value ?? ''));
        if ($pengantar === '' || $pengantar === '-' || strcasecmp($pengantar, 'null') === 0) {
            return null;
        }

        return $pengantar;
    }

    private function isPenerimaanComplete($header): bool
    {
        if (!$header) {
            return false;
        }

        $pengantar = trim((string) ($header->nama_pengantar_sampel ?? ''));
        $hasRealPengantar = $pengantar !== ''
            && $pengantar !== '-'
            && strcasecmp($pengantar, 'null') !== 0;

        $hasTtd = !empty($header->ttd_pengirim) || !empty($header->ttd_penerima);
        $hasPenerima = trim((string) ($header->nama_penerima ?? '')) !== '';
        $hasHp = trim((string) ($header->no_hp_pengantar ?? '')) !== '';
        $hasSuhu = trim((string) ($header->suhu ?? '')) !== '';

        // Header stub dari eksternal/internal (nama "-") belum berarti penerimaan selesai.
        // Setelah form penerimaan disimpan, no HP / suhu / TTD / penerima terisi.
        return $hasRealPengantar || $hasTtd || $hasPenerima || $hasHp || $hasSuhu;
    }

    private function collectActiveNoSampel($jsonStr): array
    {
        $data = json_decode($jsonStr ?? '[]', true);
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $item) {
            if (empty($item['no_sampel'])) {
                continue;
            }
            $active = !isset($item['is_active'])
                || $item['is_active'] === true
                || $item['is_active'] === 1
                || $item['is_active'] === '1';
            if ($active) {
                $out[] = $item['no_sampel'];
            }
        }

        return array_values(array_unique($out));
    }

    private function countMatchedSamples(array $filled, array $expected): int
    {
        $matched = 0;
        $used = [];
        foreach ($expected as $exp) {
            foreach ($filled as $idx => $item) {
                if (isset($used[$idx])) {
                    continue;
                }
                if ($this->noSampelEquals($exp, $item)) {
                    $matched++;
                    $used[$idx] = true;
                    break;
                }
            }
        }

        return $matched;
    }

    private function noSampelEquals($left, $right): bool
    {
        $a = strtolower(trim((string) $left));
        $b = strtolower(trim((string) $right));
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        $partsA = explode('/', $a);
        $partsB = explode('/', $b);
        if (count($partsA) === 2 && count($partsB) === 2 && $partsA[1] === $partsB[1]) {
            return strpos($partsA[0], '****') !== false
                || strpos($partsB[0], '****') !== false
                || $partsA[0] === $partsB[0];
        }

        return false;
    }

}
