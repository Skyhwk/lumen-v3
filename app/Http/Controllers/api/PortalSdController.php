<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
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
                    $search = QuotationKontrakH::with(['detail' => function ($q) {
                        $q->where('status_sampling', 'SD');
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
                    $q->where('status_sampling', 'SD');
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
                    ->where('status_sampling', 'SD')
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
                $data->nama_pengantar_sampel = $request->nama_pengantar;
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

                // conver to img
                $base64ttd_1 = $request->base64ttd_1;
                $fileName1 = null; // Default null jika tidak ada tanda tangan
                if (!empty($base64ttd_1) && strpos($base64ttd_1, 'data:image') === 0) {
                    @list($type, $data1) = explode(';', $base64ttd_1); // Gunakan $data1 agar tidak bentrok
                    @list(,$data1) = explode(',', $data1);
                    $ttd_1 = base64_decode($data1);
                    
                    $path = public_path('dokumen/sd');

                    // Pastikan direktori ada, buat jika belum ada
                    if (!File::exists($path)) {
                        File::makeDirectory($path, 0777, true, true);
                    }

                    $fileName1 = 'signature_pengirim_' . time() . '.png'; // Nama file unik untuk pengirim
                    $filePath1 = $path . DIRECTORY_SEPARATOR . $fileName1;
                    file_put_contents($filePath1, $ttd_1);
                }

                // --- Proses Tanda Tangan Penerima ---
                $base64ttd_2 = $request->base64ttd_2;
                $fileName2 = null; // Default null jika tidak ada tanda tangan
                if (!empty($base64ttd_2) && strpos($base64ttd_2, 'data:image') === 0) {
                    @list($type, $data2) = explode(';', $base64ttd_2); // Gunakan $data2 agar tidak bentrok
                    @list(,$data2) = explode(',', $data2);
                    $ttd_2 = base64_decode($data2);

                    $path = public_path('dokumen/sd'); // Path bisa sama

                    // Pastikan direktori ada, buat jika belum ada (cek lagi tidak masalah, sudah ada akan dilewati)
                    if (!File::exists($path)) {
                        File::makeDirectory($path, 0777, true, true);
                    }

                    $fileName2 = 'signature_penerima_' . time() . '.png'; // Nama file unik untuk penerima
                    $filePath2 = $path . DIRECTORY_SEPARATOR . $fileName2;
                    file_put_contents($filePath2, $ttd_2);
                }

                // --- Simpan Nama File ke Model Anda ---
                $data->ttd_pengirim = $fileName1;
                $data->ttd_penerima = $fileName2; // Perbaiki typo 'peneriam' menjadi 'penerima'
                // $data->nomor_pic = $request->nomor_pic;
                // $data->ekspedisi = $request->ekspedisi;

                $data->no_document = "{$prefix}/{$year}-{$month}/{$newNumber}";
                $data->created_at = DATE('Y-m-d H:i:s');
                $data->save();
                $getId = $data->id;
                DB::commit();
                return response()->json(['data' => $getId], 200);
            } else {
                $chek->nama_pengantar_sampel = $request->nama_pengantar;
                $chek->no_hp_pengantar = $request->no_hp_pengantar;
                $chek->ekspedisi = $request->ekspedisi;
                $chek->suhu = $request->suhu;
                $chek->tercatat = $request->tercatat;
                $chek->volume = $request->volume;
                $chek->kondisi_ubnormal = json_encode($request->kondisi_ubnormal);
                $chek->alamat_perusahaan = $request->alamat_perusahaan;
                $chek->updated_at = DATE('Y-m-d H:i:s');
                $chek->save();
                DB::commit();
                return response()->json(['data' => $chek->id], 200);
            }
            
        } catch (\Exception $ex) {
            //throw $th;
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage(),
                "line" => $ex->getLine(),
                "file" => $ex->getFile()
            ], 500);
        }
    }

    private function storeHeaderEksternal($request)
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
            return response()->json([
                "message" => $ex->getMessage(),
                "line" => $ex->getLine(),
                "file" => $ex->getFile()
            ], 500);
        }
    }

    public function saveStep(Request $request)
    {
        try {
            
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
                $dataSave = SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                    ->where('periode', $request->periode)
                    ->first();

                if ($dataSave == null) {
                    $dataSave = $this->storeHeaderEksternal($request);
                }

                $incoming = $request->external_data;
                $incoming = is_array($incoming) ? $incoming : json_decode($incoming, true);
                $merged = $incoming;
                $currentDateTime = date('Y-m-d H:i:s');

                if ($dataSave !== null) {
                    $existing = [];

                    if (isset($dataSave->eksternal_data) && !empty($dataSave->eksternal_data)) {
                        $existing = json_decode($dataSave->eksternal_data, true) ?? [];
                    }

                    if (!empty($existing)) {
                        $indexed = [];

                        foreach ($existing as $item) {
                            // pastikan history selalu array dari data lama
                            if (!isset($item['history']) || !is_array($item['history'])) {
                                $item['history'] = [];
                            }
                            $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                            $indexed[$key] = $item;
                        }

                        foreach ($incoming as $item) {
                            $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];

                            if (!isset($indexed[$key])) {
                                // Data baru
                                $item['date_time'] = $currentDateTime;
                                $item['is_active'] = true;
                                $item['history']   = [];
                            } else {
                                $existingItem  = $indexed[$key];
                                $isChanged     = false;
                                $changedFields = [];

                                // 1. Cek field sederhana
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

                                // 2. Cek jenis_wadah jika ada
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

                                // Ambil history lama
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
                        // existing kosong — data pertama kali masuk
                        foreach ($incoming as &$item) {
                            $item['date_time'] = $currentDateTime;
                            $item['is_active'] = true;
                            $item['history']   = [];
                        }
                        unset($item);
                        $merged = $incoming;
                    }

                    // Simpan hasil
                    if ($request->idSampelDiantar != null) {
                        SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                            ->where('periode', $request->periode)
                            ->update([
                                'eksternal_data'                      => json_encode($merged),
                                'petugas_pengambilan_sampel'          => $request->sampler,
                                'update_at'                           => $currentDateTime,
                                'is_ukur_suhu'                        => $request->is_ukur_suhu,
                                'tanggal_diambil_oleh_pihak_pelanggan'=> $request->tanggal_diambil_oleh_pihak_pelanggan,
                                'tujuan_pengujian'                    => json_encode($request->tujuan_pengujian),
                                'waktu_diambil_pelanggan'             => $request->waktu_diambil_pelanggan,
                                'nama_sertifikat'                     => $request->nama_sertifikat,
                                'metode_standar'                      => $request->metode_standar,
                                'sampler'                             => $request->sampler,
                                'cara_pengambilan_sample'             => $request->cara_pengambilan_sample,
                            ]);
                    } else {
                        $dataCreate = [
                            'id_header'                           => $dataSave->id,
                            'eksternal_data'                      => json_encode($merged),
                            'petugas_pengambilan_sampel'          => $request->sampler,
                            'is_ukur_suhu'                        => $request->is_ukur_suhu,
                            'tanggal_diambil_oleh_pihak_pelanggan'=> $request->tanggal_diambil_oleh_pihak_pelanggan,
                            'tujuan_pengujian'                    => json_encode($request->tujuan_pengujian),
                            'waktu_diambil_pelanggan'             => $request->waktu_diambil_pelanggan,
                            'nama_sertifikat'                     => $request->nama_sertifikat,
                            'metode_standar'                      => $request->metode_standar,
                            'sampler'                             => $request->sampler,
                            'cara_pengambilan_sample'             => $request->cara_pengambilan_sample,
                            'created_at'                          => $currentDateTime,
                            'created_by'                          => 'start Eksternal',
                        ];
                        SampelDiantarDetail::create($dataCreate);
                    }
                }

                return response()->json([
                    'sampeldiantarid' => $dataSave->id,
                    'periode'         => $request->periode,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' =>$e->getLine(),
                'file' => $e->getFile()
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
                        $q->where('status_sampling', 'SD');
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
                    $q->where('periode', $request->periode);
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

    public function chekSD(Request $request)
    {
        if ($request->status_quotation == 'kontrak') {
            $chekSD = QuotationKontrakH::with(['detail' => function ($q) {
                $q->where('status_sampling', 'SD');
            }])
                ->where('id', $request->id)
                //->where('flag_status', 'ordered')
                ->where('is_active', true)->first();

            if ($chekSD != null) {
                $hasSD = collect($chekSD->detail)->contains('status_sampling', 'SD');
                return response()->json(["status" => $hasSD], 200);
            } else {
                return response()->json(["status" => false], 200);
            }
        } else {
            $chekSD = QuotationNonKontrak::where('id', $request->id)
                ->where('status_sampling', 'SD')
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
        $mode = $request->mode;

        // 1. Ambil Data Induk
        $sampelDiantar = SampelDiantar::with(['detail' => function ($q) use ($request) {
            if ($request->periode && $request->periode !== 'null') {
                $q->where('periode', $request->periode);
            } else {
                $q->whereNull('periode');
            }
        }])
        ->where('no_quotation', $request->no_document)
        //->where('no_order', $request->no_order)
        ->where('periode_kontrak', $request->periode)
        ->first();

        // 2. Cek Mode Terima
        if ($mode === 'terima') {
            if ($sampelDiantar && $sampelDiantar->nama_pengantar_sampel != null) {
                return response()->json(['status' => true], 200);
            }
            return response()->json(['status' => false], 200);
        }

        // 3. Validasi Keberadaan Data
        if (!in_array($mode, ['internal_data', 'eksternal_data']) || !$sampelDiantar) {
            return response()->json([
                'status' => false, 
                'message' => 'Data sampel diantar tidak ditemukan atau mode tidak valid.'
            ], 200);
        }

        // 4. Ambil Detail Spesifik
        $detailForPeriod = null;
        if ($sampelDiantar->detail->isNotEmpty()) {
            $requestedPeriode = ($request->periode === 'null' || !$request->periode) ? null : $request->periode;
            $detailForPeriod = $sampelDiantar->detail->first(function ($item) use ($requestedPeriode) {
                return $item->periode == $requestedPeriode;
            });
        }

        if (!$detailForPeriod) {
            return response()->json([
                'status' => false, 
                'message' => 'Detail sampel untuk periode yang diminta tidak ditemukan.'
            ], 200);
        }

        // 5. Ambil Data Referensi dari OrderDetail
        $orderDetails = OrderDetail::where('kategori_1', 'SD')
            ->where('no_order', $request->no_order)
            ->where('no_quotation', $request->no_document)
            ->where('is_active', true)
            ->where('periode', ($request->periode === 'null' || !$request->periode) ? null : $request->periode)
            ->get(['no_sampel', 'kategori_3']);
        if($orderDetails->isEmpty()) {
            $type = explode('/', $request->no_document);
            if($type[1] != 'QTC') {
               $dataQt = QuotationKontrakH::with(['detail' => function ($q) {
                        $q->where('status_sampling', 'SD');
                    }])->where('no_document', $request->no_document)
                    ->where('is_active', 1)
                    ->first(); 
                if($dataQt != null){
                    $detailColumn   = $dataQt->detail;
                    $generatedSampel = [];
                    $counter         = 1; // nomor urut sampel

                    foreach ($detailColumn as $detail) {
                        $pendukung = json_decode($detail->data_pendukung_sampling, true) ?? [];

                        foreach ($pendukung as $periodeKey => $periodeData) {
                            $periode     = $periodeData['periode_kontrak'] ?? null;
                            $dataSampling = $periodeData['data_sampling'] ?? [];

                            foreach ($dataSampling as $item) {
                                $jumlahTitik    = (int) ($item['jumlah_titik'] ?? 1);
                                foreach ($item['penamaan_titik'] as $titik) {
                                    foreach ($titik as $key => $namaLokasi) {
                                        // Format: TPTT01****/001
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
                                            'periode' => $periode,
                                            'tanggal_sampling'=>null,
                                            'no_order'=>null,
                                            'nama_perusahaan'=> $dataQt->nama_perusahaan,
                                            'alamat_perusahaan'=> $dataQt->alamat_kantor,
                                            'is_active' => true, // default aktif
                                            'no_quotation' => $dataQt->no_document,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    $orderDetails = collect($generatedSampel);
                }
            }else{
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
                    $orderDetails = collect($generatedSampel);
                }
            }
        }

        $targetCount = $orderDetails->count();
        
        // 6. Persiapan untuk Sanitisasi
        $orderSampelNumbers = $orderDetails->pluck('no_sampel')->toArray();
        $orderKategoriRaw = $orderDetails->pluck('kategori_3')->toArray();
        $cleanedKategori = array_map(fn($item) => explode('-', $item, 2)[1] ?? $item, $orderKategoriRaw);
        $normalizedKategori = array_map(fn($item) => strtolower(trim($item)), $cleanedKategori);
        
        // 7. Ambil & Sanitasi Data untuk Mode yang Diminta
        $dataField = $mode === 'internal_data' ? 'internal_data' : 'eksternal_data';
        $jsonData = json_decode($detailForPeriod->$dataField ?? '[]', true);
        $jsonDataModified = false;

        foreach ($jsonData as &$item) {
            if (!isset($item['no_sampel'])) continue;

            $notInSampel = !in_array($item['no_sampel'], $orderSampelNumbers);
            $jenisSampel = strtolower(trim($item['jenis_sampel'] ?? ''));
            $notInKategori = !in_array($jenisSampel, $normalizedKategori);
            
            if ($notInSampel || $notInKategori) {
                if (($item['is_active'] ?? true) !== false) {
                    $item['is_active'] = false;
                    $jsonDataModified = true;
                }
            }
        }
        unset($item);

        // 8. Simpan jika ada perubahan
        if ($jsonDataModified) {
            $detailForPeriod->update([$dataField => json_encode($jsonData)]);
            $detailForPeriod->$dataField = json_encode($jsonData);
        }

        // 9. Helper: Hitung item aktif dari JSON string
        $countActiveInJson = function($jsonStr) {
            $data = json_decode($jsonStr ?? '[]', true);
            $count = 0;
            foreach ($data as $d) {
                if (!isset($d['is_active']) || $d['is_active'] === true) {
                    $count++;
                }
            }
            return $count;
        };

        // 10. LOGIKA VALIDASI BERTINGKAT
        $isValid = false;
        $message = '';
        $currentActiveCount = $countActiveInJson($detailForPeriod->$dataField);

        if ($mode === 'internal_data') {
            // ✅ INTERNAL: Cukup cek apakah sudah lengkap
            if (empty($jsonData) || count($jsonData) === 0) {
                $isValid = false;
                $message = 'Data Internal belum diisi sama sekali.';
            } else {
                $isValid = ($currentActiveCount === $targetCount);
                $message = $isValid ? 'Validasi Internal Berhasil.' : "Data Internal belum lengkap ($currentActiveCount dari $targetCount).";
            }
        } 
        elseif ($mode === 'eksternal_data') {
            // ✅ EKSTERNAL: CEK INTERNAL DULU!
            $internalData = json_decode($detailForPeriod->$mode ?? '[]', true);
            $internalCount = $countActiveInJson($detailForPeriod->$mode);
            
            // Cek apakah internal sudah diisi
            if (empty($internalData) || count($internalData) === 0) {
                $isValid = false;
                $message = 'Data Internal belum diisi. Selesaikan Internal Data terlebih dahulu.';
            } 
            // Cek apakah internal sudah lengkap
            elseif ($internalCount !== $targetCount) {
                $isValid = false;
                $message = "Data Internal belum lengkap ($internalCount dari $targetCount). Selesaikan Internal Data terlebih dahulu.";
            } 
            // Jika internal sudah OK, baru cek eksternal
            else {
                if (empty($jsonData) || count($jsonData) === 0) {
                    $isValid = false;
                    $message = 'Data Eksternal belum diisi sama sekali.';
                } else {
                    $isValid = ($currentActiveCount === $targetCount);
                    $message = $isValid ? 'Validasi Eksternal Berhasil.' : "Data Eksternal belum lengkap ($currentActiveCount dari $targetCount).";
                }
            }
        }

        // 11. Override jika sudah finished
        if (($detailForPeriod->is_finished ?? 0) == 1) {
            $isValid = true;
            $message = 'Data sudah selesai (Finished).';
        }

        return response()->json([
            'status' => $isValid,
            'message' => $message,
            'od_count' => $targetCount,
            'sd_count' => $currentActiveCount,
            'mode_checked' => $mode
        ], 200);
    }

    

}
