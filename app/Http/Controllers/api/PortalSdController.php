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

    public function saveStep(Request $request)
    {
        try {
            if ($request->mode == 'internal') {
                $dataSave = SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                    ->where('periode', $request->periode)
                    ->first();

                $incoming = $request->internal_data;
                // Pastikan array
                $incoming = is_array($incoming) ? $incoming : json_decode($incoming, true);
                $currentDateTime =date('Y-m-d H:i:s');
                if ($dataSave !== null) {
                    $existing = json_decode($dataSave->internal_data);
                    // Buat index dari existing berdasarkan no_sampel + jenis_sampel
                    $indexed = [];
                    foreach ($existing as $item) {
                        $item = (array) $item;
                        $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                        $indexed[$key] = $item;
                    }

                    // Sekarang proses incoming data
                    foreach ($incoming as $item) {
                        $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                        // $currentDateTime = date('Y-m-d H:i:s');

                        if (!isset($indexed[$key])) {
                            $item['date_time'] = $currentDateTime;
                        } else {
                            // Data sudah ada, cek apakah ada perubahan
                            $existingItem = $indexed[$key];
                            $isChanged = false;
                            $fieldsToCheck = ['ph', 'dhl', 'hasil_uji', 'sistem_lock', 'jenis_sampel'];
                            foreach ($fieldsToCheck as $field) {
                                $newValue = $item[$field] ?? null;
                                $oldValue = $existingItem[$field] ?? null;
                                if ($newValue != $oldValue) {
                                    $isChanged = true;
                                    break;
                                }
                            }

                            // Khusus jenis_wadah (array)
                            $newWadah = $item['jenis_wadah'] ?? [];
                            $oldWadah = $existingItem['jenis_wadah'] ?? [];

                            if (count($newWadah) != count($oldWadah) || array_diff($newWadah, $oldWadah) || array_diff($oldWadah, $newWadah)) {
                                $isChanged = true;
                            }

                            // Khusus warna (nested array/object)
                            $newWarna = $item['warna'] ?? [];
                            $oldWarna = $existingItem['warna'] ?? [];
                            $oldWarna = is_string($oldWarna) ? json_decode($oldWarna, true) : (array) $oldWarna;
                            ksort($newWarna);
                            ksort($oldWarna);
                            if (json_encode($newWarna) !== json_encode($oldWarna)) {
                                $isChanged = true;

                            }
                            // Set date_time tergantung apakah berubah atau tidak
                            // dump($oldWarna);
                            $item['date_time'] = $isChanged ? $currentDateTime : $existingItem['date_time'];
                            // $indexed[$key] = $item; // replace or insert
                        }
                        $indexed[$key] = $item;

                    }

                    // dd($indexed);
                    // Hasil akhir
                    $merged = array_values($indexed); // hilangkan key numerik, jadi array kembali
                    $dataToSave = [
                        'internal_data'   => json_encode($merged)
                    ];
                    // Update existing
                    $dataToSave['update_at'] = date('Y-m-d H:i:s');
                    SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                        ->where('periode', $request->periode)
                        ->update($dataToSave);
                } else {
                    // Add periode to array for new insert

                    $dataToSave = [
                        'id_header' => $request->idSampelDiantar,
                        'periode' => $request->periode,
                        'tanggal_sampling' => date('Y-m-d'),
                        'created_at' => date('Y-m-d H:i:s'),
                        'internal_data' => json_encode($incoming) // langsung saja
                    ];
                    SampelDiantarDetail::create($dataToSave);
                }

                return response()->json([
                    'sampeldiantarid' => $request->idSampelDiantar,
                    'periode' => $request->periode,
                ], 200);
            } else if ($request->mode == 'external') {
                $dataSave = SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                    ->where('periode', $request->periode)
                    ->first();
                $incoming = $request->external_data;
                // Pastikan array
                $incoming = is_array($incoming) ? $incoming : json_decode($incoming, true);

                $merged = $incoming;
                if ($dataSave !== null) {
                    // Update existing record
                    $existing = [];
                    $currentDateTime = date('Y-m-d H:i:s');
                     // Hanya decode jika eksternal_data bukan null dan string valid JSON
                    if (!empty($dataSave->eksternal_data)) {
                        $existing = json_decode($dataSave->eksternal_data, true) ?? [];
                    }
                    // Merge jika existing tidak kosong
                    if (!empty($existing)) {
                        $indexed = [];
                        // Index existing by key: no_sampel + jenis_sampel
                        foreach ($existing as $item) {
                            $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                            $indexed[$key] = $item;
                        }
                        // Merge or replace with incoming
                        foreach ($incoming as $item) {
                            $key = $item['no_sampel'] . '_' . $item['jenis_sampel'];
                            // $indexed[$key] = $item;
                            if (!isset($indexed[$key])) {
                                $item['date_time'] = $currentDateTime;
                            }else{
                                $existingItem = $indexed[$key];
                                $isChanged = false;
                                $fieldsToCheck = ['ph', 'dhl', 'suhu','is_pengawetan','is_uji_insitu','deskripsi_titik','is_pencucian_wadah','is_blanko_pencucian','cara_pengambilan_sampel','waktu_diambil_pelanggan','deskripsi_blanko_pencucian','tanggal_diambil_oleh_pihak_pelanggan'];

                                foreach ($fieldsToCheck as $field) {
                                    $newValue = $item[$field] ?? null;
                                    $oldValue = $existingItem[$field] ?? null;
                                    if ($newValue != $oldValue) {
                                        $isChanged = true;
                                        break;
                                    }
                                }
                                $item['date_time'] = $isChanged ? $currentDateTime : $existingItem['date_time'];
                            }
                            $indexed[$key] = $item;
                        }
                        $merged = array_values($indexed);
                    }
                     // Simpan hasil
                    SampelDiantarDetail::where('id_header', $request->idSampelDiantar)
                    ->where('periode', $request->periode)
                    ->update([
                        'eksternal_data' => json_encode($merged),
                        'petugas_pengambilan_sampel' => $request->sampler,
                        'update_at' => date('Y-m-d H:i:s'),
                        'is_ukur_suhu' => $request->is_ukur_suhu,
                        'tanggal_diambil_oleh_pihak_pelanggan' => $request->tanggal_diambil_oleh_pihak_pelanggan,
                        'tujuan_pengujian' => json_encode($request->tujuan_pengujian),
                        'waktu_diambil_pelanggan' => $request->waktu_diambil_pelanggan,
                        'nama_sertifikat'=>$request->nama_sertifikat,
                        'metode_standar'=>$request->metode_standar,
                        'sampler'=>$request->sampler,
                        'cara_pengambilan_sample'=>$request->cara_pengambilan_sample,
                    ]);
                }

                return response()->json([
                    'sampeldiantarid' => $request->idSampelDiantar,
                    'periode' => $request->periode,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' =>$e->getLine()
            ], 500);
        }
    }
    public function listSampel(Request $request)
    {
        try {
            $type = explode('/', $request->no_document);
            $datas = OrderDetail::where('kategori_1', 'SD')
                ->where('no_order', $request->no_order)
                ->where('is_active', true)
                ->where('periode', (isset($request->periode)) ? $request->periode : null)
                ->get();
            if ($type[1] == 'QTC') {
                $sampelDiantarID = SampelDiantar::with(['detail' => function ($q) use ($request) {
                    $q->where('periode', $request->periode);
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
                $sampelDiantarID = SampelDiantar::with(['detail' => function ($q) use ($request) {
                    $q->where('periode', $request->periode);
                }])
                ->where('no_quotation', $request->no_document)
                ->where('no_order', $request->no_order)
                ->where('periode_kontrak',$request->periode)->first();
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
                ->where('flag_status', 'ordered')
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
                ->where('flag_status', 'ordered')
                ->where('is_active', true)->first();
            if ($chekSD != null) {
                return response()->json(["status" => true], 200);
            } else {
                return response()->json(["status" => false], 200);
            }
        }
    }

    /* public function chekStepSd(Request $request)
    {
        // dd($request->all());
        $type = explode('/', $request->no_document)[1] ?? null;
        $mode = $request->mode;

        $sampelDiantar = SampelDiantar::with(['detail' => function ($q) use ($request) {
            $q->where('periode', $request->periode);
        }])
        ->where('no_quotation', $request->no_document)
        ->where('no_order', $request->no_order)
        ->first();

        if ($mode === 'terima') {
            return response()->json(['status' => $sampelDiantar !== null], 200);
        }

        if (!in_array($mode, ['internal_data', 'eksternal_data']) || !$sampelDiantar) {
            return response()->json(['status' => false], 200);
        }

        $detail = optional($sampelDiantar->detail);

        $dataField = $mode === 'internal_data' ? 'internal_data' : 'eksternal_data';
        $jsonData = json_decode($detail[0]->$dataField ?? '[]', true);
        $isFinished =$detail[0]->is_finished ?? 0;
        // dd($detail[0]);

        $orderDetails = OrderDetail::where('kategori_1', 'SD')
        ->where('no_order', $request->no_order)
        ->where('no_quotation', $request->no_document)
        ->where('is_active', true)
        ->where('periode', $request->periode === 'null' ? null : $request->periode)
        ->get(['no_sampel','kategori_3']);

        foreach ($jsonData as $key => $value) {
            # code...
        }

        $isValid = false;
        if ($isFinished == 1) {
            $isValid = true;
        } else {
            $isValid = count($orderDetails) === count($jsonData);
        }
        return response()->json(['status' => $isValid,'od'=>count($orderDetails),'sd'=>count($jsonData)], 200);
    } */

    public function chekStepSd(Request $request)
    {
        // $type = explode('/', $request->no_document)[1] ?? null; // Tidak digunakan
        $mode = $request->mode;

        $sampelDiantar = SampelDiantar::with(['detail' => function ($q) use ($request) {
            // Pastikan periode tidak null dan tidak string 'null' sebelum query
            if ($request->periode && $request->periode !== 'null') {
                $q->where('periode', $request->periode);
            } else {
                // Jika periode adalah null atau 'null', kita mungkin ingin mencari detail tanpa periode spesifik
                // atau detail di mana periode adalah NULL di database. Sesuaikan ini.
                $q->whereNull('periode');
            }
        }])
            ->where('no_quotation', $request->no_document)
            ->where('no_order', $request->no_order)
            ->where('periode_kontrak',$request->periode)
            ->first();

        if ($mode === 'terima') {
            return response()->json(['status' => $sampelDiantar !== null], 200);
        }

        // Jika mode bukan 'terima', $sampelDiantar harus ada
        if (!in_array($mode, ['internal_data', 'eksternal_data']) || !$sampelDiantar) {
            return response()->json(['status' => false, 'message' => 'Data sampel diantar tidak ditemukan atau mode tidak valid.'], 200);
        }

        // Dapatkan record detail spesifik yang menyimpan array JSON.
        // Asumsi: ada satu record detail per periode yang menyimpan array ini.
        // Atau, jika detail adalah koleksi item sampel individual, logika ini perlu diubah.
        $detailForPeriod = null;
        if ($sampelDiantar->detail->isNotEmpty()) {
            // Jika Anda punya cara spesifik untuk mengidentifikasi record detail utama, gunakan itu.
            // Untuk saat ini, kita ambil yang pertama yang cocok dengan periode request (jika ada).
            $requestedPeriode = ($request->periode === 'null' || !$request->periode) ? null : $request->periode;
            $detailForPeriod = $sampelDiantar->detail->first(function ($item) use ($requestedPeriode) {
                return $item->periode == $requestedPeriode;
            });
        }

        if (!$detailForPeriod) {
            return response()->json(['status' => false, 'message' => 'Detail sampel untuk periode yang diminta tidak ditemukan.'], 200);
        }

        $dataField = $mode === 'internal_data' ? 'internal_data' : 'eksternal_data';
        $jsonData = json_decode($detailForPeriod->$dataField ?? '[]', true);
        $isFinished = $detailForPeriod->is_finished ?? 0;

        $orderDetails = OrderDetail::where('kategori_1', 'SD')
            ->where('no_order', $request->no_order)
            ->where('no_quotation', $request->no_document)
            ->where('is_active', true)
            ->where('periode', ($request->periode === 'null' || !$request->periode) ? null : $request->periode)
            ->get(['no_sampel', 'kategori_3']);



        // Jika status sudah 'finished', mungkin tidak perlu proses update 'is_active' lagi
        // Kecuali jika ada logika bisnis lain yang mengharuskannya.
        // Untuk saat ini, kita lanjutkan proses modifikasi jsonData terlepas dari $isFinished,
        // karena $isValid hanya menentukan status awal.

        // Buat array yang berisi no_sampel dari $orderDetails untuk pencarian cepat
        $orderSampelNumbers = $orderDetails->pluck('no_sampel')->toArray();
        $orderKategoriRaw = $orderDetails->pluck('kategori_3')->toArray();
        $cleanedKategori = array_map(fn($item) => explode('-', $item, 2)[1] ?? $item, $orderKategoriRaw);
        $normalizedKategori = array_map(fn($item) => strtolower(trim($item)), $cleanedKategori);
        $jsonDataModified = false;

        // Iterasi pada $jsonData dengan reference (&) agar perubahan langsung terjadi pada array
        foreach ($jsonData as &$item) {
            if (!isset($item['no_sampel'])) {
                continue;
            }

            $notInSampel = !in_array($item['no_sampel'], $orderSampelNumbers);
            $jenisSampel = strtolower(trim($item['jenis_sampel']));
            $notInKategori = !in_array($jenisSampel, $normalizedKategori);
            if ($notInSampel || $notInKategori) {
                if (($item['is_active'] ?? true) !== false) {
                    $item['is_active'] = false;
                    // $item['date_time'] = date('Y-m-d H:i:s');
                    $jsonDataModified = true;
                }
            }
        }
        unset($item); // Hapus referensi setelah loop

        // Jika ada modifikasi pada $jsonData, update record $detailForPeriod
        if ($jsonDataModified) {
            $detailForPeriod->update([
                $dataField => json_encode($jsonData) // Simpan seluruh array jsonData yang sudah dimodifikasi
            ]);
        }

        $activeSdCount = 0;
        // Loop lagi pada $jsonData (yang mungkin sudah dimodifikasi) untuk menghitung item aktif
        foreach ($jsonData as $item) {
            // Item dianggap aktif jika 'is_active' tidak ada (implisit aktif)
            // ATAU jika 'is_active' secara eksplisit bernilai true.
            if (!isset($item['is_active']) || $item['is_active'] === true) {
                $activeSdCount++;
            }
        }

        $isValid = false;
        if ($isFinished == 1) {
            $isValid = true;
        } else {
            // Validasi awal: jumlah sampel di JSON harus cocok dengan jumlah order detail
            // Anda mungkin ingin validasi yang lebih kompleks di sini
            $isValid = count($orderDetails) === $activeSdCount;
        }

        // Status akhir bisa jadi berdasarkan $isValid ATAU hasil pengecekan setelah modifikasi.
        // Misalnya, apakah semua item di jsonData sekarang memiliki status 'is_active' yang sesuai.
        // Untuk saat ini, kita kembalikan $isValid yang ditentukan di awal.
        return response()->json([
            'status' => $isValid, // Atau status lain yang lebih relevan setelah update
            'message' => $isValid ? 'Validasi berhasil.' : 'Validasi gagal atau data tidak lengkap.',
            'od_count' => count($orderDetails),
            'sd_count' => $activeSdCount,
            // 'updated_data_preview' => $jsonData // Opsional: untuk debugging
        ], 200);
    }

}
