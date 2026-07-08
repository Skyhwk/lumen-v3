<?php

namespace App\Http\Controllers\mobile;

use Carbon\Carbon;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\Datatables\Datatables;

use Illuminate\Support\Collection; // ++ Abu

use App\Models\SamplingPlan;
use App\Models\PersiapanSampelHeader;
use App\Models\PersiapanSampelDetail;
use App\Models\Jadwal;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterKaryawan;
use App\Models\BasSampelSelesai;

//cek status data lapangan
use App\Models\DataLapanganAir;
use App\Models\DataLapanganKebisingan;
use App\Models\DataLapanganKebisinganPersonal;
use App\Models\DataLapanganCahaya;
use App\Models\DataLapanganEmisiKendaraan;
use App\Models\DataLapanganGetaran;
use App\Models\DataLapanganGetaranPersonal;
use App\Models\DataLapanganIklimPanas;
use App\Models\DataLapanganIklimDingin;
use App\Models\DataLapanganPartikulatMeter;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganMicrobiologi;
use App\Models\DataLapanganMedanLM;
use App\Models\DataLapanganSinarUv;
use App\Models\DataLapanganDirectLain;
use App\Models\DataLapanganSwab;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganIsokinetikSurveiLapangan;
use App\Models\DataLapanganIsokinetikPenentuanKecepatanLinier;
use App\Models\DataLapanganIsokinetikBeratMolekul;
use App\Models\DataLapanganIsokinetikKadarAir;
use App\Models\DataLapanganIsokinetikPenentuanPartikulat;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganPsikologi;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailMicrobiologi;
use App\Models\DetailSenyawaVolatile;
use App\Models\SampelTidakSelesai;
use App\Models\QrDocument;
use App\Models\RequiredParameters;
use App\Models\TemplateStp;
use Illuminate\Support\Str;

use App\Services\SendEmail;

use Mpdf;

use DateTime;

class AppsBasController extends Controller
{
    // public function index(Request $request)
    // {
    //     // Set limit memory lebih besar secara sementara untuk proses data besar
    //     ini_set('memory_limit', '512M');
    //     try {
    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index START - User: {$this->karyawan} - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         // Filter data untuk hanya mendapatkan data yang memiliki 'sampler' sesuai dengan $this->karyawan
    //         $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)->whereIn('id_jabatan', [41, 42])->exists();

    //         $orderDetail = OrderDetail::with([
    //             'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi,email_pic_order,email_pic_sampling',
    //             'orderHeader.samplingPlan',
    //             'orderHeader.samplingPlan.jadwal' => function ($q) use ($isProgrammer) {
    //                 $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
    //                     ->where('is_active', true)
    //                     ->when(!$isProgrammer, function ($query) {
    //                         $query->where('sampler', $this->karyawan);
    //                     })
    //                     ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai']);
    //             },
    //             'orderHeader.docCodeSampling' => function ($q) {
    //                 $q->where('menu', 'STPS');
    //             }
    //         ])
    //             ->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1'])
    //             ->where('is_active', true)
    //             ->where('kategori_1', '!=', 'SD');
    //         if ($isProgrammer) {
    //             $orderDetail->whereBetween('tanggal_sampling', [
    //                 Carbon::now()->subDays(8)->toDateString(),
    //                 Carbon::now()->toDateString()
    //             ]);
    //         } else {
    //             $orderDetail->whereBetween('tanggal_sampling', [
    //                 // "2025-04-31",
    //                 Carbon::now()->subDays(8)->toDateString(),
    //                 Carbon::now()->toDateString()
    //             ]);
                
    //         }
    //         $orderDetail->groupBy(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1']);

    //         $orderDetail = $orderDetail->get()->toArray();

    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index After Query - Count: " . count($orderDetail) . " - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         $formattedData = array_reduce($orderDetail, function ($carry, $item) {
    //             if (empty($item['order_header']) || empty($item['order_header']['sampling']))
    //                 return $carry;

    //             $samplingPlan = $item['order_header']['sampling'];
    //             $periode = $item['periode'] ?? '';

    //             $targetPlan = $periode ? current(array_filter($samplingPlan, fn($plan) => isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode)) : current($samplingPlan);

    //             if (!$targetPlan)
    //                 return $carry;

    //             $results = [];
    //             $jadwal = $targetPlan['jadwal'] ?? [];

    //             // dd($jadwal);
    //             foreach ($jadwal as $schedule) {
    //                 if ($schedule['tanggal'] == $item['tanggal_sampling']) {
    //                     $results[] = [
    //                         'nomor_quotation' => $item['order_header']['no_document'] ?? '',
    //                         'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
    //                         'status_sampling' => $item['kategori_1'] ?? '',
    //                         'periode' => $periode,
    //                         'jadwal' => $schedule['tanggal'],
    //                         'durasi' => $schedule['durasi'],
    //                         'jadwal_jam_mulai' => $schedule['jam_mulai'],
    //                         'jadwal_jam_selesai' => $schedule['jam_selesai'],
    //                         'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
    //                         'sampler' => $schedule['sampler'] ?? '',
    //                         'no_order' => $item['no_order'] ?? '',
    //                         'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
    //                         'konsultan' => $item['order_header']['konsultan'] ?? '',
    //                         'is_revisi' => $item['order_header']['is_revisi'] ?? '',
    //                         'info_pendukung' => json_encode([
    //                             'nama_pic_order' => $item['order_header']['nama_pic_order'],
    //                             'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
    //                             'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
    //                             'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
    //                             'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
    //                         ]),
    //                         'info_sampling' => json_encode([
    //                             'id_sp' => $targetPlan['id'],
    //                             'id_request' => $targetPlan['quotation_id'],
    //                             'status_quotation' => $targetPlan['status_quotation'],
    //                         ]),
    //                         'email_pic_sampling' => $item['order_header']['email_pic_sampling'] ?? '',
    //                         'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'] ?? '',
    //                         'parameter' => $item['parameter'],
    //                         'kategori_2' => $item['kategori_2'],
    //                         'no_sample' => $item['no_sampel'],
    //                         'keterangan_1' => $item['keterangan_1']
    //                     ];
    //                 }
    //             }

    //             return array_merge($carry, $results);
    //         }, []);

    //         unset($orderDetail); // Free up memory
    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index After FormattedData - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         $groupedData = [];

    //         // dd(json_decode($formattedData[0]['parameters'], true));

    //         foreach ($formattedData as $item) {
    //             // Group TANPA field 'sampler'
    //             $key = implode('|', [
    //                 $item['nomor_quotation'],
    //                 $item['nama_perusahaan'],
    //                 $item['status_sampling'],
    //                 $item['periode'],
    //                 $item['jadwal'],
    //                 $item['durasi'],
    //                 $item['no_order'],
    //                 $item['alamat_sampling'],
    //                 $item['konsultan'],
    //                 $item['kategori'],
    //                 $item['info_pendukung'],
    //                 $item['jadwal_jam_mulai'],
    //                 $item['jadwal_jam_selesai'],
    //                 $item['info_sampling'],
    //                 $item['email_pic_sampling'],
    //                 $item['nama_pic_sampling'],
    //             ]);

    //             if (!isset($groupedData[$key])) {
    //                 // Simpan semua data kecuali sampler ke dalam base_data
    //                 $groupedData[$key] = [
    //                     'base_data' => [
    //                         'nomor_quotation' => $item['nomor_quotation'],
    //                         'nama_perusahaan' => $item['nama_perusahaan'],
    //                         'status_sampling' => $item['status_sampling'],
    //                         'periode' => $item['periode'],
    //                         'jadwal' => $item['jadwal'],
    //                         'durasi' => $item['durasi'],
    //                         'kategori' => $item['kategori'],
    //                         'no_order' => $item['no_order'],
    //                         'alamat_sampling' => $item['alamat_sampling'],
    //                         'konsultan' => $item['konsultan'],
    //                         'info_pendukung' => $item['info_pendukung'],
    //                         'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
    //                         'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
    //                         'info_sampling' => $item['info_sampling'],
    //                         'is_revisi' => $item['is_revisi'],
    //                         'email_pic_sampling' => $item['email_pic_sampling'],
    //                         'nama_pic_sampling' => $item['nama_pic_sampling'],
    //                         'parameter' => $item['parameter'],
    //                         'no_sample' => $item['no_sample'],
    //                         'kategori_2' => $item['kategori_2'],
    //                         'keterangan_1' => $item['keterangan_1'],
    //                     ],
    //                     'samplers' => [],
    //                 ];
    //             }

    //             // Hindari duplicate sampler
    //             if (!in_array($item['sampler'], $groupedData[$key]['samplers'])) {
    //                 $groupedData[$key]['samplers'][] = $item['sampler'];
    //             }
    //         }

    //         unset($formattedData); // Free up memory
    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index After GroupedData - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         // dd($groupedData);

    //         // Buat final result: 1 data per sampler
    //         $finalResult = [];

    //         foreach ($groupedData as $group) {
    //             foreach ($group['samplers'] as $sampler) {
    //                 $finalResult[] = array_merge($group['base_data'], [
    //                     'sampler' => $sampler
    //                 ]);
    //             }
    //         }

    //         $finalResult = array_values($finalResult);
    //         unset($groupedData);

    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index After FinalResult - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         // Ambil semua no_order dari hasil akhir
    //         $orderNos = array_column($finalResult, 'no_order');

    //         // OPTIMASI: Eager Load PersiapanSampelHeader untuk mencegah N+1 Query (Loop yang bikin OOM & Lemot)
    //         $jadwalList = array_unique(array_column($finalResult, 'jadwal'));

    //         $persiapanHeadersData = PersiapanSampelHeader::whereIn('no_order', $orderNos)
    //             ->whereIn('tanggal_sampling', $jadwalList)
    //             ->where('is_active', true)
    //             ->orderBy('id', 'desc')
    //             ->get()
    //             ->groupBy(function($item) {
    //                 return $item->no_order . '_' . $item->tanggal_sampling;
    //             });

    //         // Add detail_bas_documents to each item
    //         foreach ($finalResult as &$item) {
    //             $headerList = $persiapanHeadersData->get($item['no_order'] . '_' . $item['jadwal']);
    //             $header = $headerList ? $headerList->first() : null;

    //             // dd($persiapanHeaders);
    //             if (isset($header)) {
    //                 // dd($item);
    //                 if ($header->detail_bas_documents) {
    //                     $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true);

    //                     // Iterasi untuk setiap dokumen
    //                     foreach ($item['detail_bas_documents'] as $docIndex => $document) {
    //                         if (isset($document['tanda_tangan']) && is_array($document['tanda_tangan'])) {
    //                             foreach ($document['tanda_tangan'] as $key => $ttd) {
    //                                 // Lakukan pengecekan apakah data sudah berupa data URI (data:image/png;base64,...)    
    //                                 if (strpos($ttd['tanda_tangan'], 'data:') === 0) {
    //                                     $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
    //                                 } else {
    //                                     $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
    //                                     if ($sign->status != 'error') {
    //                                         $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
    //                                         $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan'] = $sign->base64;
    //                                     } else {
    //                                         $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
    //                                     }
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 } else {
    //                     $item['detail_bas_documents'] = [];

    //                     if ($header->catatan || $header->informasi_teknis || $header->tanda_tangan_bas || $header->waktu_mulai || $header->waktu_selesai) {
    //                         $document = [
    //                             'tanda_tangan' => [],
    //                             'filename' => $header->filename_bas ?? '',
    //                             'catatan' => $header->catatan ?? '',
    //                             'informasi_teknis' => $header->informasi_teknis ?? '',
    //                             'waktu_mulai' => $header->waktu_mulai ?? '',
    //                             'waktu_selesai' => $header->waktu_selesai ?? '',
    //                             'no_sampel' => []
    //                         ];

    //                         if ($header->tanda_tangan_bas) {
    //                             $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
    //                             $signatures = [];

    //                             foreach ($ttd_bas as $ttd) {
    //                                 $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
    //                                 if ($sign->status != 'error') {
    //                                     $signatures[] = [
    //                                         'nama' => $ttd['nama'],
    //                                         'role' => $ttd['role'],
    //                                         'tanda_tangan' => $sign->base64,
    //                                         'tanda_tangan_lama' => $ttd['tanda_tangan']
    //                                     ];
    //                                 }
    //                             }

    //                             $document['tanda_tangan'] = $signatures;
    //                         }

    //                         $item['detail_bas_documents'][] = $document;
    //                     }
    //                 }

    //                 $item['catatan'] = $header->catatan ?? '';
    //                 $item['informasi_teknis'] = $header->informasi_teknis ?? '';
    //                 $item['waktu_mulai'] = $header->waktu_mulai ?? '';
    //                 $item['waktu_selesai'] = $header->waktu_selesai ?? '';

    //                 if ($header->tanda_tangan_bas) {
    //                     $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
    //                     $signature = array_map(function ($ttd) {
    //                         $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
    //                         if ($sign->status == 'error') {
    //                             return null;
    //                         }

    //                         return [
    //                             'nama' => $ttd['nama'],
    //                             'role' => $ttd['role'],
    //                             'tanda_tangan' => $sign->base64,
    //                             'tanda_tangan_lama' => $ttd['tanda_tangan']
    //                         ];
    //                     }, $ttd_bas);
    //                     $signature = array_filter($signature, function ($i) {
    //                         return $i !== null;
    //                     });
    //                     $item['tanda_tangan_bas'] = array_values($signature);
    //                 } else {
    //                     $item['tanda_tangan_bas'] = [];
    //                 }
    //             } else {
    //                 $item['detail_bas_documents'] = [];
    //                 $item['catatan'] = '';
    //                 $item['informasi_teknis'] = '';
    //                 $item['waktu_mulai'] = '';
    //                 $item['waktu_selesai'] = '';
    //                 $item['tanda_tangan_bas'] = [];
    //             }
    //         }
    //         unset($item);
    //         unset($persiapanHeadersData); // Free up memory

    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index After EagerLoad Data - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         if ($isProgrammer) {
    //             $filteredResult = $finalResult;
    //         } else {
    //             $filteredResult = array_filter($finalResult, function ($item) {
    //                 return isset($item['sampler']) && $item['sampler'] == $this->karyawan;
    //             });
    //         }

    //         // Reindex array setelah filter jika diperlukan
    //         $filteredResult = array_values($filteredResult);
    //         unset($finalResult);

    //         // Jika tidak ada hasil yang sesuai, bisa mengembalikan pesan atau melakukan tindakan lain
    //         if (count($filteredResult) === 0) {
    //             return response()->json([
    //                 'message' => 'Data tidak ditemukan untuk sampler yang sesuai dengan karyawan.'
    //             ], 200);
    //         }

    //         // filter tanggal sampling sesuai durasi jadwal
    //         $today = Carbon::today();
    //         $filtered = [];

    //         foreach ($filteredResult as $item) {
    //             $jadwal = Carbon::parse($item['jadwal']);
    //             $durasi = (int) $item['durasi'];

    //             if ($durasi <= 1) { // sesaat ato 8jam
    //                 if ($jadwal->isSameDay($today))
    //                     $filtered[] = $item;
    //             } else {
    //                 $endDate = $jadwal->copy()->addDays($durasi - 1);
    //                 if ($today->between($jadwal, $endDate))
    //                     $filtered[] = $item;
    //             }
    //         }

    //         if ($request->has('no_order') && $request->has('tanggal_sampling')) {
    //             $orderD = OrderDetail::where('no_order', $request->no_order)
    //                 ->where('is_active', true)
    //                 ->where('tanggal_sampling', $request->tanggal_sampling)
    //                 ->get()
    //                 ->map(function ($item) {
    //                     return (object) $item->toArray(); // ubah ke stdClass
    //                 });

    //             if (!$orderD->isEmpty()) {
    //                 $detail_sampling_sampel = [];

    //                 // OPTIMASI: Eager Load queries in loop DataLapanganAir and SampelTidakSelesai
    //                 $noSampelList = $orderD->pluck('no_sampel')->unique()->toArray();
                    
    //                 $dataAirExists = DataLapanganAir::whereIn('no_sampel', $noSampelList)->pluck('no_sampel')->toArray();
    //                 $sampelTidakSelesaiList = SampelTidakSelesai::whereIn('no_sampel', $noSampelList)->pluck('no_sampel')->toArray();

    //                 foreach ($orderD as $key => $item) {
    //                     $item->no_sample = $item->no_sampel;
    //                     $isAirExist = in_array($item->no_sample, $dataAirExists);
    //                     $isTidakSelesai = in_array($item->no_sample, $sampelTidakSelesaiList);

    //                     if ($item->kategori_2 === "1-Air") {
    //                         $detail_sampling_sampel[$key]['status'] = $isAirExist ? 'selesai' : 'belum selesai';
    //                         $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
    //                         $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
    //                         $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
    //                         $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

    //                         $detail_sampling_sampel[$key]['status_sampel'] = $isTidakSelesai;

    //                     } else {
    //                         $detail_sampling_sampel[$key]['status'] = $this->getStatusSampling($item);
    //                         $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
    //                         $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
    //                         $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
    //                         $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

    //                         $detail_sampling_sampel[$key]['status_sampel'] = $isTidakSelesai;
    //                     }
    //                 }

    //                 // Gabungkan detail_sampling_sampel ke filteredResult
    //                 foreach ($filteredResult as $key => $value) {
    //                     $kategoriItems = explode(',', $value['kategori']);

    //                     $matchedDetails = [];

    //                     foreach ($kategoriItems as $item) {
    //                         $parts = explode('-', $item);
    //                         $nomor = trim(end($parts));

    //                         $katNoOrder = $value['no_order'] . '/' . $nomor;

    //                         foreach ($detail_sampling_sampel as $detail) {
    //                             if ($detail['no_sampel'] === $katNoOrder) {
    //                                 $matchedDetails[] = $detail;
    //                                 break;
    //                             }
    //                         }
    //                     }
    //                     $filteredResult[$key]['detail_sampling_sampel'] = $matchedDetails;
    //                 }
    //             }
    //         }

    //         // \Illuminate\Support\Facades\Log::info("AppsBasController::index END - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

    //         return DataTables::of($filteredResult)->make(true);
    //     } catch (\Exception $ex) {
    //         \Illuminate\Support\Facades\Log::error("AppsBasController::index ERROR: " . $ex->getMessage() . " on line " . $ex->getLine());
    //         return response()->json([
    //             'message' => $ex->getMessage(),
    //             'line' => $ex->getLine(),
    //         ], 500);
    //     }
    // }

    public function index(Request $request)
    {
        // Set limit memory lebih besar secara sementara untuk proses data besar
        ini_set('memory_limit', '512M');
        try {
            // \Illuminate\Support\Facades\Log::info("AppsBasController::index START - User: {$this->karyawan} - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

            // Filter data untuk hanya mendapatkan data yang memiliki 'sampler' sesuai dengan $this->karyawan
            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)->whereIn('id_jabatan', [41, 42])->exists();

            $orderDetail = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi,email_pic_order,email_pic_sampling',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) use ($isProgrammer) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->when(!$isProgrammer, function ($query) {
                            $query->where('sampler', $this->karyawan);
                        })
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai']);
                },
                'orderHeader.docCodeSampling' => function ($q) {
                    $q->where('menu', 'STPS');
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1'])
                ->where('is_active', true)
                ->where('kategori_1', '!=', 'SD');
            
            // --- URGENT HARDCODE EXCEPTION: BYPASS TANGGAL UNTUK QUOTATION TERTENTU ---
            if ($isProgrammer) {
                $orderDetail->where(function($query) {
                    $query->whereBetween('tanggal_sampling', [
                        Carbon::now()->subDays(8)->toDateString(),
                        Carbon::now()->toDateString()
                    ])->orWhereHas('orderHeader', function($q) {
                        $q->where('no_document', 'ISL/QT/26-VI/011494R7');
                    });
                });
            } else {
                $orderDetail->where(function($query) {
                    $query->whereBetween('tanggal_sampling', [
                        Carbon::now()->subDays(8)->toDateString(),
                        Carbon::now()->toDateString()
                    ])->orWhereHas('orderHeader', function($q) {
                        $q->where('no_document', 'ISL/QT/26-VI/011494R7');
                    });
                });
            }
            // -------------------------------------------------------------------------

            $orderDetail->groupBy(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1']);

            $orderDetail = $orderDetail->get()->toArray();

            // \Illuminate\Support\Facades\Log::info("AppsBasController::index After Query - Count: " . count($orderDetail) . " - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

            $formattedData = array_reduce($orderDetail, function ($carry, $item) {
                if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                    return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ? current(array_filter($samplingPlan, fn($plan) => isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode)) : current($samplingPlan);

                if (!$targetPlan)
                    return $carry;

                $results = [];
                $jadwal = $targetPlan['jadwal'] ?? [];

                // dd($jadwal);
                foreach ($jadwal as $schedule) {
                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        $results[] = [
                            'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                            'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                            'status_sampling' => $item['kategori_1'] ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'durasi' => $schedule['durasi'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item['no_order'] ?? '',
                            'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                            'konsultan' => $item['order_header']['konsultan'] ?? '',
                            'is_revisi' => $item['order_header']['is_revisi'] ?? '',
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                            ]),
                            'info_sampling' => json_encode([
                                'id_sp' => $targetPlan['id'],
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation'],
                            ]),
                            'email_pic_sampling' => $item['order_header']['email_pic_sampling'] ?? '',
                            'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'] ?? '',
                            'parameter' => $item['parameter'],
                            'kategori_2' => $item['kategori_2'],
                            'no_sample' => $item['no_sampel'],
                            'keterangan_1' => $item['keterangan_1']
                        ];
                    }
                }

                return array_merge($carry, $results);
            }, []);

            unset($orderDetail); // Free up memory

            $groupedData = [];

            foreach ($formattedData as $item) {
                // Group TANPA field 'sampler'
                $key = implode('|', [
                    $item['nomor_quotation'],
                    $item['nama_perusahaan'],
                    $item['status_sampling'],
                    $item['periode'],
                    $item['jadwal'],
                    $item['durasi'],
                    $item['no_order'],
                    $item['alamat_sampling'],
                    $item['konsultan'],
                    $item['kategori'],
                    $item['info_pendukung'],
                    $item['jadwal_jam_mulai'],
                    $item['jadwal_jam_selesai'],
                    $item['info_sampling'],
                    $item['email_pic_sampling'],
                    $item['nama_pic_sampling'],
                ]);

                if (!isset($groupedData[$key])) {
                    // Simpan semua data kecuali sampler ke dalam base_data
                    $groupedData[$key] = [
                        'base_data' => [
                            'nomor_quotation' => $item['nomor_quotation'],
                            'nama_perusahaan' => $item['nama_perusahaan'],
                            'status_sampling' => $item['status_sampling'],
                            'periode' => $item['periode'],
                            'jadwal' => $item['jadwal'],
                            'durasi' => $item['durasi'],
                            'kategori' => $item['kategori'],
                            'no_order' => $item['no_order'],
                            'alamat_sampling' => $item['alamat_sampling'],
                            'konsultan' => $item['konsultan'],
                            'info_pendukung' => $item['info_pendukung'],
                            'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                            'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                            'info_sampling' => $item['info_sampling'],
                            'is_revisi' => $item['is_revisi'],
                            'email_pic_sampling' => $item['email_pic_sampling'],
                            'nama_pic_sampling' => $item['nama_pic_sampling'],
                            'parameter' => $item['parameter'],
                            'no_sample' => $item['no_sample'],
                            'kategori_2' => $item['kategori_2'],
                            'keterangan_1' => $item['keterangan_1'],
                        ],
                        'samplers' => [],
                    ];
                }

                // Hindari duplicate sampler
                if (!in_array($item['sampler'], $groupedData[$key]['samplers'])) {
                    $groupedData[$key]['samplers'][] = $item['sampler'];
                }
            }

            unset($formattedData); // Free up memory

            // Buat final result: 1 data per sampler
            $finalResult = [];

            foreach ($groupedData as $group) {
                foreach ($group['samplers'] as $sampler) {
                    $finalResult[] = array_merge($group['base_data'], [
                        'sampler' => $sampler
                    ]);
                }
            }

            $finalResult = array_values($finalResult);
            unset($groupedData);

            // Ambil semua no_order dari hasil akhir
            $orderNos = array_column($finalResult, 'no_order');

            // OPTIMASI: Eager Load PersiapanSampelHeader
            $jadwalList = array_unique(array_column($finalResult, 'jadwal'));

            $persiapanHeadersData = PersiapanSampelHeader::whereIn('no_order', $orderNos)
                ->whereIn('tanggal_sampling', $jadwalList)
                ->where('is_active', true)
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy(function($item) {
                    return $item->no_order . '_' . $item->tanggal_sampling;
                });

            // Ambil semua order yang memiliki persiapan (tanpa mempedulikan tanggal_sampling)
            $ordersWithAnyPersiapan = PersiapanSampelHeader::whereIn('no_order', $orderNos)
                ->where('is_active', true)
                ->pluck('no_order')
                ->unique()
                ->toArray();

            // Add detail_bas_documents to each item
            foreach ($finalResult as &$item) {
                $headerList = $persiapanHeadersData->get($item['no_order'] . '_' . $item['jadwal']);
                // --- PERBAIKAN BUG: Cocokkan Persiapan Header berdasarkan no_sampel ---
                $header = null;
                if ($headerList) {
                    // 1. Ekstrak nomor sampel dari kolom 'kategori' di item ini
                    $itemSamples = [];
                    $kategoriItems = explode(',', $item['kategori']);
                    foreach ($kategoriItems as $katItem) {
                        if (empty(trim($katItem))) continue;
                        $parts = explode('-', $katItem);
                        $nomor = trim(end($parts));
                        $itemSamples[] = $item['no_order'] . '/' . $nomor; // misal: EAED012601/032
                    }

                    // 2. Cari header yang array no_sampel-nya beririsan dengan sampel di item ini
                    foreach ($headerList as $h) {
                        $hSamples = json_decode($h->no_sampel, true);
                        if (is_array($hSamples) && count(array_intersect($itemSamples, $hSamples)) > 0) {
                            $header = $h; // Ketemu header yang pas!
                            break;
                        }
                    }
                }

                if (isset($header)) {
                    if ($header->detail_bas_documents) {
                        $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true);

                        // Iterasi untuk setiap dokumen
                        foreach ($item['detail_bas_documents'] as $docIndex => $document) {
                            if (isset($document['tanda_tangan']) && is_array($document['tanda_tangan'])) {
                                foreach ($document['tanda_tangan'] as $key => $ttd) {
                                    if (strpos($ttd['tanda_tangan'], 'data:') === 0) {
                                        $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                    } else {
                                        $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                        if ($sign->status != 'error') {
                                            $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                            $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan'] = $sign->base64;
                                        } else {
                                            $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $item['detail_bas_documents'] = [];

                        if ($header->catatan || $header->informasi_teknis || $header->tanda_tangan_bas || $header->waktu_mulai || $header->waktu_selesai) {
                            $document = [
                                'tanda_tangan' => [],
                                'filename' => $header->filename_bas ?? '',
                                'catatan' => $header->catatan ?? '',
                                'informasi_teknis' => $header->informasi_teknis ?? '',
                                'waktu_mulai' => $header->waktu_mulai ?? '',
                                'waktu_selesai' => $header->waktu_selesai ?? '',
                                'no_sampel' => []
                            ];

                            if ($header->tanda_tangan_bas) {
                                $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                                $signatures = [];

                                foreach ($ttd_bas as $ttd) {
                                    $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                    if ($sign->status != 'error') {
                                        $signatures[] = [
                                            'nama' => $ttd['nama'],
                                            'role' => $ttd['role'],
                                            'tanda_tangan' => $sign->base64,
                                            'tanda_tangan_lama' => $ttd['tanda_tangan']
                                        ];
                                    }
                                }

                                $document['tanda_tangan'] = $signatures;
                            }

                            $item['detail_bas_documents'][] = $document;
                        }
                    }

                    $item['catatan'] = $header->catatan ?? '';
                    $item['informasi_teknis'] = $header->informasi_teknis ?? '';
                    $item['waktu_mulai'] = $header->waktu_mulai ?? '';
                    $item['waktu_selesai'] = $header->waktu_selesai ?? '';

                    if ($header->tanda_tangan_bas) {
                        $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                        $signature = array_map(function ($ttd) {
                            $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                            if ($sign->status == 'error') {
                                return null;
                            }

                            return [
                                'nama' => $ttd['nama'],
                                'role' => $ttd['role'],
                                'tanda_tangan' => $sign->base64,
                                'tanda_tangan_lama' => $ttd['tanda_tangan']
                            ];
                        }, $ttd_bas);
                        $signature = array_filter($signature, function ($i) {
                            return $i !== null;
                        });
                        $item['tanda_tangan_bas'] = array_values($signature);
                    } else {
                        $item['tanda_tangan_bas'] = [];
                    }
                    $item['has_persiapan'] = true;
                } else {
                    $item['detail_bas_documents'] = [];
                    $item['catatan'] = '';
                    $item['informasi_teknis'] = '';
                    $item['waktu_mulai'] = '';
                    $item['waktu_selesai'] = '';
                    $item['tanda_tangan_bas'] = [];
                    $item['has_persiapan'] = in_array($item['no_order'], $ordersWithAnyPersiapan);
                }
            }
            unset($item);
            unset($persiapanHeadersData); // Free up memory

            if ($isProgrammer) {
                $filteredResult = $finalResult;
            } else {
                $filteredResult = array_filter($finalResult, function ($item) {
                    return isset($item['sampler']) && $item['sampler'] == $this->karyawan;
                });
            }

            // Reindex array setelah filter jika diperlukan
            $filteredResult = array_values($filteredResult);
            unset($finalResult);

            if (count($filteredResult) === 0) {
                return response()->json([
                    'message' => 'Data tidak ditemukan untuk sampler yang sesuai dengan karyawan.'
                ], 200);
            }

            // filter tanggal sampling sesuai durasi jadwal
            $today = Carbon::today();
            $filtered = [];

            foreach ($filteredResult as $item) {
                // --- URGENT HARDCODE EXCEPTION: LOLOSKAN FILTER ARRAY ---
                if (isset($item['nomor_quotation']) && $item['nomor_quotation'] === 'ISL/QT/26-VI/011494R7') {
                    $filtered[] = $item;
                    continue;
                }
                // --------------------------------------------------------

                $jadwal = Carbon::parse($item['jadwal']);
                $durasi = (int) $item['durasi'];

                if ($durasi <= 1) { // sesaat ato 8jam
                    if ($jadwal->isSameDay($today))
                        $filtered[] = $item;
                } else {
                    $endDate = $jadwal->copy()->addDays($durasi - 1);
                    if ($today->between($jadwal, $endDate))
                        $filtered[] = $item;
                }
            }
            
            // Catatan: Jika di versi kode asli Anda variabel $filtered ini belum dipakai 
            // menimpa $filteredResult, saya tambahkan ini agar filter array berfungsi
            $filteredResult = $filtered; 

            if ($request->has('no_order') && $request->has('tanggal_sampling')) {
                $orderD = OrderDetail::select(
                        'order_detail.*',
                        'bas_sampel_selesai.id as bas_selesai_id',
                        'sampel_tidak_selesai.id as ts_id'
                    )
                    ->leftJoin('bas_sampel_selesai', 'order_detail.no_sampel', '=', 'bas_sampel_selesai.no_sampel')
                    ->leftJoin('sampel_tidak_selesai', 'order_detail.no_sampel', '=', 'sampel_tidak_selesai.no_sampel')
                    ->where('order_detail.no_order', $request->no_order)
                    ->where('order_detail.is_active', true)
                    ->where('order_detail.tanggal_sampling', $request->tanggal_sampling)
                    ->get()
                    ->map(function ($item) {
                        return (object) $item->toArray(); // ubah ke stdClass
                    });
                if (!$orderD->isEmpty()) {
                    $detail_sampling_sampel = [];

                    foreach ($orderD as $key => $item) {
                        $item->no_sample = $item->no_sampel;
                        $isSelesai = !is_null($item->bas_selesai_id);
                        $isTidakSelesai = !is_null($item->ts_id);

                        $detail_sampling_sampel[$key]['status'] = $isSelesai ? 'selesai' : 'belum selesai';
                        $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
                        $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
                        $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
                        $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

                        $detail_sampling_sampel[$key]['status_sampel'] = $isTidakSelesai;
                    }

                    // Gabungkan detail_sampling_sampel ke filteredResult
                    foreach ($filteredResult as $key => $value) {
                        $kategoriItems = explode(',', $value['kategori']);

                        $matchedDetails = [];

                        foreach ($kategoriItems as $item) {
                            $parts = explode('-', $item);
                            $nomor = trim(end($parts));

                            $katNoOrder = $value['no_order'] . '/' . $nomor;

                            foreach ($detail_sampling_sampel as $detail) {
                                if ($detail['no_sampel'] === $katNoOrder) {
                                    $matchedDetails[] = $detail;
                                    break;
                                }
                            }
                        }
                        $filteredResult[$key]['detail_sampling_sampel'] = $matchedDetails;
                    }
                }
            }

            return DataTables::of($filteredResult)->make(true);
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error("AppsBasController::index ERROR: " . $ex->getMessage() . " on line " . $ex->getLine());
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function detailData(Request $request)
    {
        try {

            // Filter data untuk hanya mendapatkan data yang memiliki 'sampler' sesuai dengan $this->karyawan
            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)->whereIn('id_jabatan', [41, 42])->exists();

            $orderDetail = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi,email_pic_order,email_pic_sampling',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai']);
                },
                'orderHeader.docCodeSampling' => function ($q) {
                    $q->where('menu', 'STPS');
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1'])
                ->where('is_active', true)
                ->where('no_order', $request->no_order)
                ->where('kategori_1', '!=', 'SD');
            if ($isProgrammer) {
                $orderDetail->where('tanggal_sampling', $request->tanggal_sampling);
            } else {
                $orderDetail->where('tanggal_sampling', $request->tanggal_sampling);
            }
            $orderDetail->groupBy(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1']);

            $orderDetail = $orderDetail->get()->toArray();

            $formattedData = array_reduce($orderDetail, function ($carry, $item) {
                if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                    return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ? current(array_filter($samplingPlan, fn($plan) => isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode)) : current($samplingPlan);

                if (!$targetPlan)
                    return $carry;

                $results = [];
                $jadwal = $targetPlan['jadwal'] ?? [];

                // dd($jadwal);
                foreach ($jadwal as $schedule) {
                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        $results[] = [
                            'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                            'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                            'status_sampling' => $item['kategori_1'] ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'durasi' => $schedule['durasi'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item['no_order'] ?? '',
                            'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                            'konsultan' => $item['order_header']['konsultan'] ?? '',
                            'is_revisi' => $item['order_header']['is_revisi'] ?? '',
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                            ]),
                            'info_sampling' => json_encode([
                                'id_sp' => $targetPlan['id'],
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation'],
                            ]),
                            'email_pic_sampling' => $item['order_header']['email_pic_sampling'] ?? '',
                            'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'] ?? '',
                            'parameter' => $item['parameter'],
                            'kategori_2' => $item['kategori_2'],
                            'no_sample' => $item['no_sampel'],
                            'keterangan_1' => $item['keterangan_1']
                        ];
                    }
                }

                return array_merge($carry, $results);
            }, []);
            // dd($formattedData);
            $groupedData = [];

            // dd(json_decode($formattedData[0]['parameters'], true));

            foreach ($formattedData as $item) {
                // Group TANPA field 'sampler'
                $key = implode('|', [
                    $item['nomor_quotation'],
                    $item['nama_perusahaan'],
                    $item['status_sampling'],
                    $item['periode'],
                    $item['jadwal'],
                    $item['durasi'],
                    $item['no_order'],
                    $item['alamat_sampling'],
                    $item['konsultan'],
                    $item['kategori'],
                    $item['info_pendukung'],
                    $item['jadwal_jam_mulai'],
                    $item['jadwal_jam_selesai'],
                    $item['info_sampling'],
                    $item['email_pic_sampling'],
                    $item['nama_pic_sampling'],
                ]);

                if (!isset($groupedData[$key])) {
                    // Simpan semua data kecuali sampler ke dalam base_data
                    $groupedData[$key] = [
                        'base_data' => [
                            'nomor_quotation' => $item['nomor_quotation'],
                            'nama_perusahaan' => $item['nama_perusahaan'],
                            'status_sampling' => $item['status_sampling'],
                            'periode' => $item['periode'],
                            'jadwal' => $item['jadwal'],
                            'durasi' => $item['durasi'],
                            'kategori' => $item['kategori'],
                            'no_order' => $item['no_order'],
                            'alamat_sampling' => $item['alamat_sampling'],
                            'konsultan' => $item['konsultan'],
                            'info_pendukung' => $item['info_pendukung'],
                            'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                            'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                            'info_sampling' => $item['info_sampling'],
                            'is_revisi' => $item['is_revisi'],
                            'email_pic_sampling' => $item['email_pic_sampling'],
                            'nama_pic_sampling' => $item['nama_pic_sampling'],
                            'parameter' => $item['parameter'],
                            'no_sample' => $item['no_sample'],
                            'kategori_2' => $item['kategori_2'],
                            'keterangan_1' => $item['keterangan_1'],
                        ],
                        'samplers' => [],
                    ];
                }

                // Hindari duplicate sampler
                if (!in_array($item['sampler'], $groupedData[$key]['samplers'])) {
                    $groupedData[$key]['samplers'][] = $item['sampler'];
                }
            }

            // dd($groupedData);

            // Buat final result: 1 data per sampler
            $finalResult = [];

            foreach ($groupedData as $group) {
                foreach ($group['samplers'] as $sampler) {
                    $finalResult[] = array_merge($group['base_data'], [
                        'sampler' => $sampler
                    ]);
                }
            }
            // $filteredResult = array_filter($finalResult, function ($item) use ($request) {
            //     return strpos($item['kategori'], $request->kategori) !== false;
            // });
            $kategoriRequest = is_array($request->kategori)
                ? $request->kategori
                : explode(',', $request->kategori);

            // Ambil semua kode dari kategori request (misal: "001", "002", dst)
            $kodeList = array_map(function ($k) {
                $parts = explode(' - ', trim($k));
                return trim(end($parts));
            }, $kategoriRequest);

            $filteredResult = array_filter($finalResult, function ($item) use ($kodeList) {
                foreach ($kodeList as $kode) {
                    if (strpos($item['kategori'], $kode) !== false) {
                        return true;
                    }
                }
                return false;
            });

            // Reset index biar mulai dari 0
            $finalResult = array_values($filteredResult);
            // $finalResult = array_values($finalResult);
            // dd($filteredResult);
            // Ambil semua no_order dari hasil akhir
            $orderNos = array_column($finalResult, 'no_order');
            $kategoriList = is_array($request['kategori'])
                ? $request['kategori']
                : (strpos($request['kategori'], ',') !== false
                    ? explode(',', $request['kategori'])
                    : [$request['kategori']]);

            foreach ($kategoriList as $kategoriItem) {
                $parts = explode(' - ', trim($kategoriItem));
                $kode = trim(end($parts)); // ambil bagian paling kanan (kode)
                $expectednoSampel[] = $request['no_order'] . '/' . $kode;
            }


            // Ambil data catatan, informasi teknis, dan tanda_tangan_bas dari tabel PersiapanSampelHeader berdasarkan no_order
            // $persiapanHeaders = PersiapanSampelHeader::whereIn('no_order', $orderNos)
            //     ->where('tanggal_sampling', $request->tanggal_sampling)
            //     ->whereJsonContains('no_sampel', $expectednoSampel[0])
            //     ->get()
            //     ->keyBy('no_order');

            $dataList = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $orderNos,
                'tanggal_sampling' => $request->tanggal_sampling,
            ])->where('is_active', true)->orderBy('id', 'desc')
                ->get();

            if ($dataList->isEmpty()) {
                $dataList = PersiapanSampelHeader::with('psDetail')->where([
                    'no_order' => $orderNos,
                ])->where('is_active', true)->orderBy('id', 'desc')
                    ->get();
            }

            // Add detail_bas_documents to each item
            foreach ($finalResult as &$item) {
                // Cari header yang memuat no_sampel dari item ini
                $header = $dataList->where('no_order', $item['no_order'])->first(function($h) use ($item) {
                    $no_sampel = json_decode($h->no_sampel, true) ?? [];
                    return in_array($item['no_sample'], $no_sampel);
                });
                
                // Fallback ke header pertama dari order tersebut
                if (!$header) {
                    $header = $dataList->where('no_order', $item['no_order'])->first();
                }

                if ($header) {
                    if ($header->detail_bas_documents) {
                        $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true);

                        // Iterasi untuk setiap dokumen
                        foreach ($item['detail_bas_documents'] as $docIndex => $document) {
                            if (isset($document['tanda_tangan']) && is_array($document['tanda_tangan'])) {
                                foreach ($document['tanda_tangan'] as $key => $ttd) {
                                    // Lakukan pengecekan apakah data sudah berupa data URI (data:image/png;base64,...)    
                                    if (strpos($ttd['tanda_tangan'], 'data:') === 0) {
                                        $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                    } else {
                                        $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                        if ($sign->status != 'error') {
                                            $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                            $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan'] = $sign->base64;
                                        } else {
                                            $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        
                        $item['detail_bas_documents'] = [];

                        if ($header->catatan || $header->informasi_teknis || $header->tanda_tangan_bas || $header->waktu_mulai || $header->waktu_selesai) {
                            $document = [
                                'tanda_tangan' => [],
                                'filename' => $header->filename_bas ?? '',
                                'catatan' => $header->catatan ?? '',
                                'informasi_teknis' => $header->informasi_teknis ?? '',
                                'waktu_mulai' => $header->waktu_mulai ?? '',
                                'waktu_selesai' => $header->waktu_selesai ?? '',
                                'no_sampel' => []
                            ];

                            if ($header->tanda_tangan_bas) {
                                $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                                $signatures = [];

                                foreach ($ttd_bas as $ttd) {
                                    $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                    if ($sign->status != 'error') {
                                        $signatures[] = [
                                            'nama' => $ttd['nama'],
                                            'role' => $ttd['role'],
                                            'tanda_tangan' => $sign->base64,
                                            'tanda_tangan_lama' => $ttd['tanda_tangan']
                                        ];
                                    }
                                }

                                $document['tanda_tangan'] = $signatures;
                            }

                            $item['detail_bas_documents'][] = $document;
                        }
                    }

                    $item['catatan'] = $header->catatan ?? '';
                    $item['informasi_teknis'] = $header->informasi_teknis ?? '';
                    $item['waktu_mulai'] = $header->waktu_mulai ?? '';
                    $item['waktu_selesai'] = $header->waktu_selesai ?? '';

                    if ($header->tanda_tangan_bas) {
                        $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                        $signature = array_map(function ($ttd) {
                            $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                            if ($sign->status == 'error') {
                                return null;
                            }

                            return [
                                'nama' => $ttd['nama'],
                                'role' => $ttd['role'],
                                'tanda_tangan' => $sign->base64,
                                'tanda_tangan_lama' => $ttd['tanda_tangan']
                            ];
                        }, $ttd_bas);
                        $signature = array_filter($signature, function ($item) {
                            return $item !== null;
                        });
                        $item['tanda_tangan_bas'] = $signature;
                    } else {
                        $item['tanda_tangan_bas'] = [];
                    }
                } else {
                    
                    $item['detail_bas_documents'] = [];
                    $item['catatan'] = '';
                    $item['informasi_teknis'] = '';
                    $item['waktu_mulai'] = '';
                    $item['waktu_selesai'] = '';
                    $item['tanda_tangan_bas'] = [];
                }
            }
            unset($item);

            if ($isProgrammer) {
                $filteredResult = $finalResult;
            } else {
                $filteredResult = array_filter($finalResult, function ($item) {
                    return isset($item['sampler']) && $item['sampler'] == $this->karyawan;
                });
            }

            // Reindex array setelah filter jika diperlukan
            $filteredResult = array_values($filteredResult);

            // Jika tidak ada hasil yang sesuai, bisa mengembalikan pesan atau melakukan tindakan lain
            if (count($filteredResult) === 0) {
                return response()->json([
                    'message' => 'Data tidak ditemukan untuk sampler yang sesuai dengan karyawan.'
                ], 401);
            }

            // filter tanggal sampling sesuai durasi jadwal
            $today = Carbon::today();
            $filtered = [];

            foreach ($filteredResult as $item) {
                $jadwal = Carbon::parse($item['jadwal']);
                $durasi = (int) $item['durasi'];

                if ($durasi <= 1) { // sesaat ato 8jam
                    if ($jadwal->isSameDay($today))
                        $filtered[] = $item;
                } else {
                    $endDate = $jadwal->copy()->addDays($durasi - 1);
                    if ($today->between($jadwal, $endDate))
                        $filtered[] = $item;
                }
            }

            $orderD = OrderDetail::select(
                    'order_detail.*',
                    'bas_sampel_selesai.id as bas_selesai_id',
                    'sampel_tidak_selesai.id as ts_id',
                    'sampel_tidak_selesai.status as ts_status',
                    'sampel_tidak_selesai.alasan as ts_alasan',
                    'sampel_tidak_selesai.keterangan as ts_keterangan',
                    'sampel_tidak_selesai.kategori as ts_kategori',
                    'sampel_tidak_selesai.tanggal_dilanjutkan as ts_tanggal_dilanjutkan',
                    'sampel_tidak_selesai.no_order as ts_no_order',
                    'sampel_tidak_selesai.created_at as ts_created_at',
                    'sampel_tidak_selesai.created_by as ts_created_by',
                    'sampel_tidak_selesai.updated_at as ts_updated_at'
                )
                ->leftJoin('bas_sampel_selesai', 'order_detail.no_sampel', '=', 'bas_sampel_selesai.no_sampel')
                ->leftJoin('sampel_tidak_selesai', 'order_detail.no_sampel', '=', 'sampel_tidak_selesai.no_sampel')
                ->where('order_detail.no_order', $request->no_order)
                ->where('order_detail.is_active', true)
                ->where('order_detail.tanggal_sampling', $request->tanggal_sampling)
                ->get()
                ->map(function ($item) {
                    return (object) $item->toArray(); // ubah ke stdClass
                });

            if (!$orderD->isEmpty()) {
                $detail_sampling_sampel = [];

                foreach ($orderD as $key => $item) {
                    $item->no_sample = $item->no_sampel;
                    $isSelesai = !is_null($item->bas_selesai_id);
                    
                    $dataSampelBelumSelesai = null;
                    if (!is_null($item->ts_id)) {
                        $dataSampelBelumSelesai = (object)[
                            'id' => $item->ts_id,
                            'no_order' => $item->ts_no_order,
                            'no_sampel' => $item->no_sampel,
                            'kategori' => $item->ts_kategori,
                            'keterangan' => $item->ts_keterangan,
                            'status' => $item->ts_status,
                            'alasan' => $item->ts_alasan,
                            'tanggal_dilanjutkan' => $item->ts_tanggal_dilanjutkan,
                            'created_at' => $item->ts_created_at,
                            'created_by' => $item->ts_created_by,
                            'updated_at' => $item->ts_updated_at
                        ];
                    }

                    $detail_sampling_sampel[$key]['status'] = $isSelesai ? 'selesai' : 'belum selesai';
                    $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
                    $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
                    $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
                    $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

                    $detail_sampling_sampel[$key]['status_sampel'] = (bool) $dataSampelBelumSelesai;
                    if ($dataSampelBelumSelesai) {
                        $detail_sampling_sampel[$key]['detail_status'] = $dataSampelBelumSelesai;
                    }
                }
                // dd($detail_sampling_sampel);

                // Gabungkan detail_sampling_sampel ke filteredResult
                foreach ($filteredResult as $key => $value) {
                    $kategoriItems = explode(',', $value['kategori']);

                    $matchedDetails = [];

                    foreach ($kategoriItems as $item) {
                        $parts = explode('-', $item);
                        $nomor = trim(end($parts));

                        $katNoOrder = $value['no_order'] . '/' . $nomor;

                        foreach ($detail_sampling_sampel as $detail) {
                            if ($detail['no_sampel'] === $katNoOrder) {
                                $matchedDetails[] = $detail;
                                break;
                            }
                        }
                    }
                    $filteredResult[$key]['detail_sampling_sampel'] = $matchedDetails;
                }

            }
            // dd($filteredResult);
            return DataTables::of($filteredResult)->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function updateData(Request $request)
    {
        // dd($request);
        DB::beginTransaction();
        try {
            if ($request->has('data') && !empty($request->data)) {
                $errors = [];
                foreach ($request->data as $item) {
                    if (!isset($item['no_quotation'])) {
                        continue;
                    }
                    // dd($item);

                    $item['expectedNoSampel'] = array_map(function ($kode) use ($item) {
                        return $item['no_order'] . '/' . $kode;
                    }, $item['no_sampel']);
                    // dd(json_encode($item['no_sampel'], JSON_UNESCAPED_SLASHES), $item['no_sampel']);
                    $dataList = PersiapanSampelHeader::where('no_order', $item['no_order'])
                        ->where('tanggal_sampling', $item['tanggal_sampling'])
                        ->where('is_active', true)
                        ->orderBy('id', 'desc')
                        ->get();

                    if ($dataList->isEmpty()) {
                        $dataList = PersiapanSampelHeader::where('no_order', $item['no_order'])
                            ->where('is_active', true)
                            ->orderBy('id', 'desc')
                            ->get();
                    }

                    if ($dataList->isEmpty()) {
                        $dataList = PersiapanSampelHeader::where('no_quotation', $item['no_quotation'])
                            ->where('is_active', true)
                            ->orderBy('id', 'desc')
                            ->get();
                    }

                    $header = $dataList->first(function ($data) use ($item) {
                        $no_sampel = json_decode($data->no_sampel, true) ?? [];
                        // Ekstrak kode sampel dari database (ambil bagian terakhir setelah '/')
                        $dbSamples = array_map(function($s) {
                            $parts = explode('/', $s);
                            return end($parts);
                        }, $no_sampel);
                        
                        return count(array_intersect($dbSamples, $item['no_sampel'])) > 0;
                    });

                    if (!$header) {
                        // Fallback ke header pertama jika tidak ada yang match no_sampel persis (meskipun seharusnya ada)
                        $header = $dataList->first();
                    }

                    if (!$header) {
                        return response()->json(['status' => 'error', 'message' => 'No quotation tidak ditemukan atau tidak sesuai dengan tanggal sampling dan no sampel.'], 404);
                    }

                    if ($header) {
                        $detailData = [
                            'catatan' => $item['catatan'] ?? '',
                            'informasi_teknis' => $item['informasi_teknis'] ?? $header->informasi_teknis,
                            'waktu_mulai' => $item['waktu_mulai'] ?? $header->waktu_mulai,
                            'waktu_selesai' => $item['waktu_selesai'] ?? $header->waktu_selesai,
                            'filename' => str_replace(
                                ['&#039;', '/', ',', '@', '"', '`'],
                                ["'",       '',  '',  '',  '',  ''],
                                $item['filename_bas'] ?? $header->filename_bas
                            ),
                            // $item['filename_bas'] ?? $header->filename_bas,
                            'no_sampel' => $item['no_sampel'] ?? [],
                            'bysubmit' => $this->karyawan ?? null,
                        ];

                        // Proses tanda tangan (jika ada)
                        $ttd_bas = [];
                        if (isset($item['tanda_tangan_bas'])) {
                            foreach ($item['tanda_tangan_bas'] as $key => $value) {
                                if (isset($value['tanda_tangan']) && strpos($value['tanda_tangan'], 'data:image') === 0) {
                                    $convert = $this->convertBase64ToImage($value['tanda_tangan']);

                                    if ($convert->status == 'error') {
                                        $errors[] = [
                                            'no_quotation' => $item['no_quotation'],
                                            'message' => $convert->message
                                        ];
                                        continue;
                                    } else {
                                        $ttd_bas[$key]['tanda_tangan'] = $convert->filename;
                                        if (isset($value['tanda_tangan_lama']) && !empty($value['tanda_tangan_lama'])) {
                                            $path = public_path('/dokumen/bas/signatures/' . $value['tanda_tangan_lama']);
                                            if (file_exists($path)) {
                                                unlink($path);
                                            }
                                        }
                                    }
                                } else if (isset($value['tanda_tangan_lama']) && !empty($value['tanda_tangan_lama'])) {
                                    $ttd_bas[$key]['tanda_tangan'] = $value['tanda_tangan_lama'];
                                } else {
                                    $errors[] = [
                                        'no_quotation' => $item['no_quotation'],
                                        'message' => 'Tanda tangan tidak valid untuk ' . $value['nama']
                                    ];
                                    continue;
                                }

                                $ttd_bas[$key]['role'] = $value['role'];
                                $ttd_bas[$key]['nama'] = $value['nama'];
                            }
                        }

                        $detailData['tanda_tangan'] = $ttd_bas;

                        $existingDetails = json_decode($header->detail_bas_documents, true) ?? [];
                        $updated = false;

                        // Bersihkan dan sort no_sampel untuk menghindari duplikat tidak terdeteksi
                        $detailData['no_sampel'] = array_values(array_unique($detailData['no_sampel']));
                        sort($detailData['no_sampel']);


                        function compareNoSampel(array $a, array $b)
                        {
                            if (count($a) !== count($b)) {
                                return false;
                            }
                            sort($a);
                            sort($b);
                            return $a == $b;
                        }

                        // Cek apakah terdapat detail dengan no_sampel yang sama.
                        $found = false;
                        foreach ($existingDetails as &$detail) {
                            if (isset($detail['no_sampel']) && compareNoSampel($detail['no_sampel'], $detailData['no_sampel'])) {
                                $detail = $detailData; // overwrite jika match
                                $found = true;
                                break;
                            }
                        }
                        unset($detail);

                        if (!$found) {
                            $existingDetails[] = $detailData; // tambahkan jika belum ada
                        }


                        $header->detail_bas_documents = json_encode($existingDetails);
                        $header->save();

                        // Hapus PDF lama agar dirender ulang dengan data terbaru (misal: ada tanda tangan baru)
                        if (isset($detailData['filename']) && !empty($detailData['filename'])) {
                            $pdfPath = public_path('dokumen/bas/' . $detailData['filename']);
                            if (file_exists($pdfPath)) {
                                unlink($pdfPath);
                            }
                        }
                        if ($request->input('is_final')) {
                            $error = \App\Services\BasSampelService::processFinalSamples(
                                $item,
                                fn($sample) => $this->getStatusSampling($sample)
                            );
                            if ($error) {
                                return response()->json($error, 200);
                            }
                        }
                    }
                }
            }

            DB::commit();

            if (!empty($errors)) {
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => $ex->getMessage()], 500);
        }
    }

    // Send Email Development
    public function sendEmail(Request $request)
    {
        //  dd($request);
        DB::beginTransaction();
        try {
            $subject = $request->input('subject');
            $content = $request->input('content');
            $to = $request->input('to');
            $cc = $request->input('cc', []);
            $attachments = $request->input('attachments', []);
            $noOrder = $request->input('no_order');
            $noDocument = $request->input('no_document');

            if (empty($subject)) {
                throw new \Exception('Subject is required');
            }
            if (empty($content)) {
                throw new \Exception('Content is required');
            }
            if (empty($to)) {
                throw new \Exception('Recipient email is required');
            }

            $ccArray = [];
            $bcc = ['faidhah@intilab.com'];

            if (!empty($cc)) {
                if (is_array($cc)) {
                    $ccArray = $cc;
                } else {
                    $ccArray = array_filter(array_map('trim', explode(',', $cc)));
                }
            }

            $emailInstance = SendEmail::where('to', $to)
                ->where('cc', $ccArray)
                ->where('bcc', $bcc)
                ->where('subject', $subject)
                ->where('body', $content)
                ->noReply();

            if (is_array($attachments) && !empty($attachments)) {
                $validAttachments = [];
                foreach ($attachments as $fileName) {
                    array_push($validAttachments, public_path() . '/dokumen/bas/' . $fileName);
                    // $filePath = base_path('public/dokumen/bas/' . $fileName);
                    // if (file_exists($filePath)) {
                    //     $validAttachments[] = $filePath;
                    // } else {
                    //     error_log("Attachment file not found: " . $fileName);
                    // }
                }

                if (!empty($validAttachments)) {
                    $emailInstance = $emailInstance->where('attachment', $validAttachments);
                }
            }

            $sent = $emailInstance->send();

            if ($sent) {

                $persiapanHeader = PersiapanSampelHeader::where('no_quotation', $noDocument)->where('no_order', $noOrder)->where('tanggal_sampling', $request->input('tanggal_sampling'))->where('is_active', true)->whereNotNull('detail_bas_documents')->first();

                if ($persiapanHeader) {
                    $persiapanHeader->is_emailed_bas = 1;
                    $persiapanHeader->emailed_bas_at = Carbon::now();
                    $persiapanHeader->save();
                    // dd($persiapanHeader);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim',
                    'details' => [
                        'to' => $to,
                        'cc' => $cc,
                        'bcc' => $bcc,
                        'subject' => $subject,
                        'attachments' => count($attachments)
                    ]
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            error_log('Email sending error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Gagal mengirim email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function preview(Request $request)
    {
        try {
            if (!$request->has('no_document') || empty($request->no_document)) {
                return response()->json([
                    'data' => [],
                ], 200);
            }

            // Jika file PDF sudah ada, langsung kembalikan tanpa render ulang
            $existingFilename = str_replace("&#039;", "'", $request->filename ?? $request->filename_old ?? '');
            if ($existingFilename && $existingFilename !== 'undefined') {
                $existingPath = public_path('dokumen/bas/' . $existingFilename);
                if (file_exists($existingPath)) {
                    return response()->json([$existingFilename], 200);
                }
            }

            // Cari filename dari detail_bas_documents yang tersimpan di PersiapanSampelHeader
            if (!$existingFilename || $existingFilename === 'undefined') {
                $savedHeaders = PersiapanSampelHeader::where('no_order', $request->no_order)
                    ->whereNotNull('detail_bas_documents')
                    ->get();
                foreach ($savedHeaders as $sh) {
                    $docs = json_decode($sh->detail_bas_documents, true);
                    if (is_array($docs)) {
                        foreach ($docs as $doc) {
                            if (isset($doc['filename']) && !empty($doc['filename'])) {
                                $cachedPath = public_path('dokumen/bas/' . $doc['filename']);
                                if (file_exists($cachedPath)) {
                                    return response()->json([$doc['filename']], 200);
                                }
                            }
                        }
                    }
                }
            }
            
            $jsonDecode = html_entity_decode($request->info_sampling);

            $infoSampling = json_decode($jsonDecode, true);
            
            $tipe = explode("/", $request->no_document);
            $request->kategori = explode(",", $request->kategori);

            // Get No Sample
            $noSample = [];
            if ($request->has('no_sampel') && is_array($request->no_sampel)) {
                $noSample = $request->no_sampel;
            } else {
                foreach ($request->kategori as $item) {
                    $parts = explode(" - ", $item);
                    if (isset($parts[1])) {
                        array_push($noSample, $request->no_order . '/' . trim($parts[1]));
                    }
                }
            }
            // Ambil data sampling plan
            $sp = SamplingPlan::where('id', $infoSampling['id_sp'])
                ->where('quotation_id', $infoSampling['id_request'])
                ->where('status_quotation', $infoSampling['status_quotation'])
                ->where('is_active', true)
                ->first();
            // dd($sp);
            if (!$sp) {
                return response()->json([
                    'message' => 'Data sampling plan tidak ditemukan.!'
                ], 401);
            } else {
                $jadwal = Jadwal::select([
                    'id_sampling',
                    'kategori',
                    'tanggal',
                    'durasi',
                    'jam_mulai',
                    'jam_selesai',
                    DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler'),
                    DB::raw('GROUP_CONCAT(id SEPARATOR ",") AS batch_id')
                ])
                    ->where('id_sampling', $sp->id)
                    ->where('tanggal', $request->tanggal_sampling)
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai'])
                    ->get()->pluck('tanggal');
            }

            if ($jadwal->isEmpty()) {
                return response()->json([
                    'message' => 'Data jadwal tidak ditemukan.!',
                ], 401);
            }

            $samplerJadwal = Jadwal::select(['sampler', 'kategori'])
                ->where([
                    ['id_sampling', '=', $sp->id],
                    ['tanggal', '=', $request->tanggal_sampling],
                    ['is_active', '=', true],
                ])
                ->get();
            // dd($samplerJadwal);

            if ($samplerJadwal->isEmpty()) {
                return response()->json([
                    'message' => 'Data jadwal tidak ditemukan.!',
                ], 401);
            }

            // Ambil data order header berdasarkan no_document dan no_order
            $orderH = OrderHeader::where('no_document', $request->no_document)
                ->where('no_order', $request->no_order)
                ->first();

            // dd($request->all());
            $expectednoSampel = [];

            $kategoriList = is_array($request['kategori'])
                ? $request['kategori']
                : (strpos($request['kategori'], ',') !== false
                    ? explode(',', $request['kategori'])
                    : [$request['kategori']]);

            foreach ($kategoriList as $kategoriItem) {
                $parts = explode(' - ', trim($kategoriItem));
                $kode = trim(end($parts)); // ambil bagian paling kanan (kode)
                $expectednoSampel[] = $request['no_order'] . '/' . $kode;
            }


            // Ambil data PersiapanSampelHeader dengan fallback yang sama dengan UpdateData
            $dataList = PersiapanSampelHeader::where('no_order', $request->no_order)
                ->where('tanggal_sampling', $request->tanggal_sampling)
                ->where('is_active', true)
                ->orderBy('id', 'desc')
                ->get();

            if ($dataList->isEmpty()) {
                $dataList = PersiapanSampelHeader::where('no_order', $request->no_order)
                    ->where('is_active', true)
                    ->orderBy('id', 'desc')
                    ->get();
            }

            if ($dataList->isEmpty()) {
                $dataList = PersiapanSampelHeader::where('no_quotation', $request->no_document)
                    ->where('is_active', true)
                    ->orderBy('id', 'desc')
                    ->get();
            }

            $persiapanHeader = $dataList->first(function ($item) use ($request) {
                $no_sampel = json_decode($item->no_sampel, true) ?? [];
                
                $dbSamples = array_map(function($s) {
                    $parts = explode('/', $s);
                    return end($parts);
                }, $no_sampel);
                
                $reqSamples = $request->no_sampel ?? [];
                if (empty($reqSamples)) {
                    return true; // Fallback jika tidak ada no_sampel dikirim
                }
                
                return count(array_intersect($dbSamples, $reqSamples)) > 0;
            });

            if (!$persiapanHeader) {
                $persiapanHeader = $dataList->first();
            }

            if ($persiapanHeader && !empty($persiapanHeader->detail_bas_documents)) {
                $orderH->detail_bas_documents = $persiapanHeader->detail_bas_documents;
            } else {
                $orderH->detail_bas_documents = json_encode([]);
            }
            
            // Ambil data order detail beserta relasi codingSampling
            $orderD = OrderDetail::with(['codingSampling'])
                ->select(
                    'order_detail.*',
                    'bas_sampel_selesai.id as bas_selesai_id',
                    'bas_sampel_selesai.created_at as bas_selesai_created_at',
                    'sampel_tidak_selesai.id as ts_id'
                )
                ->leftJoin('bas_sampel_selesai', 'order_detail.no_sampel', '=', 'bas_sampel_selesai.no_sampel')
                ->leftJoin('sampel_tidak_selesai', 'order_detail.no_sampel', '=', 'sampel_tidak_selesai.no_sampel')
                ->where('order_detail.id_order_header', $orderH->id)
                ->where('order_detail.no_order', $request->no_order)
                ->whereIn('order_detail.no_sampel', $noSample)
                ->whereIn('order_detail.tanggal_sampling', $jadwal)
                ->where('order_detail.is_active', true)
                ->get();

            $tipe = explode("/", $request->no_document);
            $tahun = "20" . explode("-", $tipe[2])[0];
            // Ambil data perdiem sesuai tipe dokumen
            if ($tipe[1] == "QT") {
                $perdiem = QuotationNonKontrak::select('perdiem_jumlah_orang', 'jumlah_orang_24jam')
                    ->where('no_document', $request->no_document)
                    ->first();
            } else if ($tipe[1] == "QTC") {
                $perdiem = QuotationKontrakH::select('perdiem_jumlah_orang', 'jumlah_orang_24jam')
                    ->where('no_document', $request->no_document)
                    ->first();
            }

            $data_sampling = [];
            $dat_param = [];
            $status = [];
            $hariTanggal = [];

            $file_name_old = str_replace("&#039;", "'", $request->filename_old);
            $file_name = str_replace("&#039;", "'", $request->filename);
            // dd($file_name_old, $file_name);
            foreach ($orderD as $vv) {

                $data_sampling[] = (object) [
                    'no_sample' => $vv->no_sampel,
                    'kategori_2' => $vv->kategori_2,
                    'kategori_3' => $vv->kategori_3,
                    'nama_perusahaan' => $vv->nama_perusahaan,
                    'koding_sampling' => $vv->koding_sampling,
                    'file_koding_sample' => $vv->file_koding_sampel,
                    'file_koding_sampling' => $vv->file_koding_sampling,
                    'konsultan' => $vv->orderHeader->konsultan,
                    'tanggal_sampling' => $vv->tanggal_sampling,
                    'keterangan_1' => $vv->keterangan_1,
                    'jumlah_label' => $vv->codingSampling->jumlah_label ?? null,
                    'status_sampling' => $vv->kategori_1,
                    'id' => $vv->id,
                    'id_order_header' => $vv->id_order_header,
                    'id_req_header' => $infoSampling['id_request'],
                    'id_req_detail' => $request->id_req_detail,
                    'periode_kontrak' => $vv->periode,
                    'tgl_order' => $orderH->tanggal_order,
                    'botol' => $vv->botol,
                    'parameter' => $vv->parameter,
                    'no_order' => $vv->orderHeader->no_order,
                    'no_document' => $request->no_document,
                ];

                if (!is_null($vv->bas_selesai_id)) {
                    $status[$vv->no_sampel] = 'selesai';
                    $hariTanggal[$vv->no_sampel] = $vv->bas_selesai_created_at;
                } else {
                    $status[$vv->no_sampel] = 'belum selesai';
                    $hariTanggal[$vv->no_sampel] = null;
                }

                // dd($data_sampling);

                if ($vv->codingSampling) {
                    $dat_param[] = $vv->codingSampling;
                }
            }

            // dd($status);
            // dd([
            //     $orderH, $data_sampling, $dat_param, $persiapanHeader, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal
            // ]);

            // dd($persiapanHeader);

            $dataPdf = self::cetakBASPDF($orderH, $data_sampling, $dat_param, $persiapanHeader, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal);
            return $dataPdf;
        } catch (\Exception $e) {
            // dd($e);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    private function cetakBASPDF($dataHeader, $dataSampling, $dataParam, $dataPersiapan, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal)
    {
        
        $psh = $dataPersiapan;
        if (!$psh) {
            return response()->json([
                'message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!',
            ], 401);
        }

        $noDocument = explode('/', $psh->no_document);
        $noDocument[1] = 'BAS';
        $noDocument = implode('/', $noDocument);

        $qr_img = '';
        $qr = QrDocument::where('id_document', $psh->id)
            ->where('type_document', 'berita_acara_sampling')
            ->whereJsonContains('data->no_document', $noDocument)
            ->first();

        if ($qr) {
            $qr_data = json_decode($qr->data, true);
            if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
                $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
            }
        }

        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => [216, 305],
            'margin_header' => 5,
            'margin_bottom' => 3,
            'margin_footer' => 3,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'orientation' => 'P',
        );
        $pdf = new Mpdf($mpdfConfig);

        $kategoriList = is_array(request()->kategori) ? request()->kategori : explode(',', request()->kategori);
        $requestedSampels = array_map(function ($kategori) {
            $parts = explode('-', $kategori);
            return trim($parts[count($parts) - 1]);
        }, $kategoriList);

        asort($requestedSampels);

        // Nama File PDF Berdasarkan Kombinasi Kategori
        // $filename = str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '_' . $sampelNumber . '.pdf');

        $microtime = sprintf("%.0f", microtime(true) * 1000000);
        // $filename = $file_name ? $file_name : str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '_' . $microtime . '.pdf');
        $filename = $file_name ?: preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '_',
            'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '_' . $microtime
        ) . '.pdf';

        $detailDocuments = json_decode($dataHeader->detail_bas_documents, true);

        // dd($detailDocuments);

        $selectedDetail = [
            'catatan' => '',
            'informasi_teknis' => '',
            'waktu_mulai' => '',
            'waktu_selesai' => '',
            'tanda_tangan' => [],
        ];
        // dd($selectedDetail)

        // Cari data detail yang cocok dengan nomor sampel (ambil dari index terakhir)
        if (is_array($detailDocuments)) {
            foreach (array_reverse($detailDocuments) as $detail) {
                if (isset($detail['no_sampel']) && is_array($detail['no_sampel']) && !empty($detail['no_sampel'])) {
                    $detailNoSampelSorted = $detail['no_sampel'];
                    sort($detailNoSampelSorted);

                    $requestedSampelsSorted = $requestedSampels;
                    sort($requestedSampelsSorted);

                    // if ($detailNoSampelSorted === $requestedSampelsSorted) {
                    //     $selectedDetail = $detail;
                    //     break;
                    // }
                    if (!empty(array_intersect($detail['no_sampel'], $requestedSampels))) {
                        $selectedDetail = $detail;
                        break;
                    }
                }
            }
        }

        // dd($selectedDetail);

        $namaHari = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        $namaBulan = [
            'January' => 'Januari',
            'February' => 'Februari',
            'March' => 'Maret',
            'April' => 'April',
            'May' => 'Mei',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'Agustus',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Desember'
        ];

        $footer = array(
            'odd' => array(
                'C' => array(
                    'content' => 'Hal {PAGENO} dari {nbpg}',
                    'font-size' => 6,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060'
                ),
                'R' => array(
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'L' => array(
                    'content' => '' . $qr_img . '',
                    'font-size' => 4,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'line' => -1,
            )
        );

        $css = '
            .custom {
                padding: 5px;
                font-size: 12px;
                font-weight: bold;
                border: 1px solid #000000;
                text-align: center;
            }
            .custom2 {
                padding-top: 8px;
                font-size: 12px;
            }
            .custom3 {
                font-size: 12px;
                padding: 10px;
                margin-bottom: 5mm;
            }
            .custom4 {
                display: flex;
                justify-content: end;
            }
            .custom5 {
                font-size: 12px;
                padding: 5px;
                border: 1px solid #000000;
                font-weight: bold;
                text-align: center;
            }
            .kolomno {
                font-size: 12px;
                border-left: 1px solid #000000;
                border-top: 1px solid #000000;
                border-bottom: 1px solid #000000;
                text-align: center;
                margin-bottom: 5mm;
            }
            .kolomttd {
                font-size: 12px;
                border-right: 1px solid #000000;
                border-top: 1px solid #000000;
                border-bottom: 1px solid #000000;
                text-align: center;
                margin-bottom: 5mm;
            }
            .kolomttd2 {
                padding: 23px;
                border: 1px solid #000000;
            }
            .kotak {
                border: 1px solid #000000;
            }
            body {
                font-size: 12px; /* Ukuran font */
                line-height: 1.5; /* Jarak antar baris */
            }
            .table {
                width: 100%;
                border-collapse: collapse;
            }
            .table td, .table th {
                padding: 8px;
                font-size: 10px;
                border: 1px solid #000;
            }
        ';

        $pdf->SetDisplayMode('fullpage');
        $pdf->setFooter($footer);

        $tanggal = $dataSampling[0]->tanggal_sampling ?? null;

        $hariInggris = date('l', strtotime($tanggal));
        $bulanInggris = date('F', strtotime($tanggal));
        
        $hari = $namaHari[$hariInggris];
        // dd($hariInggris, $hari);
        $tanggalNumber = date('d', strtotime($tanggal));
        $bulan = $namaBulan[$bulanInggris];
        $tahun = date('Y', strtotime($tanggal));

        // $namaSampler = $samplerJadwal->pluck('sampler')->unique()->values()->all();

        $waktuMulai = $selectedDetail['waktu_mulai'] ?? '';
        $waktuSelesai = $selectedDetail['waktu_selesai'] ?? '';

        if (!empty($waktuSelesai)) {
            $carbon = Carbon::parse($waktuSelesai)->locale('id');
            $jam = $carbon->format('H');
            $menit = $carbon->format('i');
            $hariSelesai = $carbon->translatedFormat('l');
            $tanggal = $carbon->translatedFormat('d F Y');
        } else {
            $jam = $menit = $hariSelesai = $tanggal = '';
        }
        $samplerKategoriMap = [];
        // dd($samplerJadwal);
        foreach ($samplerJadwal as $jadwal) {
            $samplerName = $jadwal->sampler;
            $kategoriArray = json_decode($jadwal->kategori, true);

            foreach ($kategoriArray as $kategori) {
                // Extract sample number from kategori (e.g., "Udara Lingkungan Kerja - 001" -> "001")
                $parts = explode(' - ', $kategori);
                if (count($parts) >= 2) {
                    $sampleNumber = end($parts);

                    // Support multiple samplers per sample number
                    if (!isset($samplerKategoriMap[$sampleNumber])) {
                        $samplerKategoriMap[$sampleNumber] = [];
                    }
                    if (!in_array($samplerName, $samplerKategoriMap[$sampleNumber])) {
                        $samplerKategoriMap[$sampleNumber][] = $samplerName;
                    }
                }
            }
        }
        // dd($samplerKategoriMap);

        // Group sampling data by combined samplers
        $samplingBySampler = [];
        $sampleSamplerMap = []; // Track samplers per sample

        foreach ($dataSampling as $sampling) {
            $sampleParts = explode('/', $sampling->no_sample);
            if (count($sampleParts) >= 2) {
                $sampleNumber = end($sampleParts);

                if (isset($samplerKategoriMap[$sampleNumber])) {
                    $assignedSamplers = $samplerKategoriMap[$sampleNumber];
                } else {
                    // Fallback: If not mapped, assign to all samplers
                    $assignedSamplers = $samplerJadwal->pluck('sampler')->unique()->values()->all();
                    if (empty($assignedSamplers)) {
                        $assignedSamplers = ['Petugas'];
                    }
                }

                $sampleSamplerMap[$sampling->no_sample] = $assignedSamplers;

                // Create combined key for samplers working together
                $samplerKey = count($assignedSamplers) > 2 ? implode(', ', $assignedSamplers) : implode(' & ', $assignedSamplers);

                if (!isset($samplingBySampler[$samplerKey])) {
                    $samplingBySampler[$samplerKey] = [];
                }
                $samplingBySampler[$samplerKey][] = $sampling;
            }
        }
        // dd($samplingBySampler, $sampleSamplerMap);

        $isFirstPage = true;

        // Create separate page for each sampler
        foreach ($samplingBySampler as $samplerName => $samplerSamplingData) {
            if (!$isFirstPage) {
                $pdf->AddPage();
            }
            $isFirstPage = false;

            $petugasSamList = '';
            if (!empty($sampleSamplerMap)) {
                foreach ($sampleSamplerMap as $noSample => $samplers) {
                    if ($noSample == $samplerSamplingData[0]->no_sample) {
                        if (count($samplers) == 1) {
                            $petugasSamList .= '' . $samplers[0] . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Petugas sampling)';
                        } else {
                            $i = 1;
                            foreach ($samplers as $sampler) {
                                if ($i == 1) {
                                    $petugasSamList .= '- ' . $sampler . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Petugas sampling)<br>';
                                } else {
                                    $petugasSamList .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- ' . $sampler . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Petugas sampling)<br>';
                                }
                                $i++;
                            }
                        }
                        break;
                    }
                }
            } else {
                $petugasSamList = ': ............................ (Petugas sampling)';
            }

            $header = '
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td class="custom3" width="520"></td>
                    <td class="custom5">No Order :' . $dataHeader->no_order . '</td>
                </tr>
                </table>
                <div style="height: 40px;"></div>
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;margin-bottom: 40px;">
                    <tr>
                        <td class="custom3" colspan="2">
                            Hari: ' . $hari . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                            Tanggal: ' . $tanggalNumber . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                            Bulan: ' . $bulan . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                            Tahun: ' . $tahun . '
                        </td>
                    </tr>
                    <tr>
                        <td class="custom3" style="text-align: justify;" colspan="2">
                        Sesuai dengan permintaan pihak pelanggan, melalui Berita Acara Sampling ini, bahwa pihak PT Inti Surya Laboratorium telah melakukan kegiatan pengambilan sampel / contoh uji (sampling) yang dilaksanakan sebagaimana rincian berikut :
                        </td>
                    </tr>
                    <tr>
                        <td class="custom3" width="120">Nama Perusahaan</td>
                        <td class="custom3">: ' . $dataHeader->nama_perusahaan . '</td>
                    </tr>
                    <tr>
                        <td class="custom3" width="120">Alamat</td>
                        <td class="custom3">: ' . $dataHeader->alamat_sampling . '</td>
                    </tr>
                    <tr>
                        <td class="custom3" rowspan="2" width="120">Nama Personil</td>
                        <td class="custom3">: 1. ' . $petugasSamList . '</td>
                    </tr>
                    <tr>
                        <td class="custom3">: 2. ' . $dataHeader->nama_pic_sampling . ' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; (Perwakilan Pelanggan / Perusahaan)</td>
                    </tr>
                    <tr>
                        <td class="custom3" colspan="4">
                            Mulai pelaksanaan pekerjaan pukul : ' . ($waktuMulai ? $waktuMulai : '.................. : ..................') . '
                        </td>
                    </tr>
                    <tr>
                        <td class="custom3" colspan="2">
                            Berakhir pada pukul : ' . ($jam ?: '..................') . ' : ' . ($menit ?: '..................') . '
                            ' . (!empty($hariSelesai) && !empty($tanggal)
                ? '( ' . $hariSelesai . ' / ' . $tanggal . ' )'
                : '(hari / tanggal : ' . ($hariSelesai ?: '...............') . ' / ' . ($tanggal ?: '.............................................') . ')') . '
                        </td>
                    </tr>
                </table>
            ';

            $pdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
            $pdf->SetHTMLHeader($header);
            $pdf->WriteHTML('<!DOCTYPE html>
                <html>
                <head>
                    <style>' . $css . '</style>
                </head>
                <body>');

            $p = 1;
            $pdf->WriteHTML('<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px;">');

            // Process sampling data for this specific sampler
            foreach ($samplerSamplingData as $key => $val) {
                $dataSampelTidakSelesai = \Illuminate\Support\Facades\DB::table('sampel_tidak_selesai')->where('no_sampel', $val->no_sample)->where('no_order', $val->no_order)->orderBy('id', 'desc')->first();
                $dat = explode("-", $val->kategori_3);
                $boxChecked = '&#9745;'; // ☑
                $boxUnchecked = '&#9744;'; // ☐

                $isSelesai = isset($status[$val->no_sample]) && $status[$val->no_sample] == 'selesai';
                if ($dataSampelTidakSelesai) {
                    $isSelesai = false;
                }
                $selesaiBox = $isSelesai ? $boxChecked : $boxUnchecked;
                $belumSelesaiBox = $isSelesai ? $boxUnchecked : $boxChecked;

                $raw = $hariTanggal[$val->no_sample] ?? null;

                if ($isSelesai) {
                    if ($raw) {
                        // parse & terjemahkan ke locale Indonesia
                        // $c = Carbon::parse($raw)->locale('id');
                        // $hari2 = $c->translatedFormat('l');      // e.g. "Jumat"
                        // $tgl2 = $c->translatedFormat('d F Y');  // e.g. "17 April 2025"
                        // $tanggalHtml = "Hari/Tanggal : {$hari2} / {$tgl2}";
                        $tanggalHtml = "Hari/Tanggal : ....................................";
                        
                    } else {
                        // placeholder jika belum ada
                        $tanggalHtml = "Hari/Tanggal : ....................................";
                        
                    }
                } else {
                    if (isset($dataSampelTidakSelesai) && $dataSampelTidakSelesai->status == "Dilanjutkan") {
                        $c = Carbon::parse($dataSampelTidakSelesai->tanggal_dilanjutkan)->locale('id');
                        $hari2 = $c->translatedFormat('l');      // e.g. "Jumat"
                        $tgl2 = $c->translatedFormat('d F Y');  // e.g. "17 April 2025"
                        $tanggalHtml = "Hari/Tanggal : {$hari2} / {$tgl2}";
                        $belumSelesaiBox = $boxUnchecked;
                        
                    } else {
                        $tanggalHtml = "Hari/Tanggal : ....................................";
                        
                    }
                }

                $pdf->WriteHTML('
                <tr>
                    <td class="custom" width="10">' . $p++ . '</td>
                    <td class="custom" width="120">' . $val->no_sample . '</td>
                    <td class="custom" width="80" style="white-space: wrap;">' . $dat[1] . '</td>
                    <td class="custom" width="80" style="white-space: wrap;">' . $val->keterangan_1 . '</td>
                    <td width="210" style="border: 1px solid #000000;">
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                            <tr>
                            <td style="font-size: 20px; font-weight: bold;" width="10">' . $selesaiBox . '</td>
                                <td class="custom2" style="font-weight: bold;">Selesai </td>
                            </tr>
                            <tr>
                            <td style="font-size: 20px; font-weight: bold;" width="10">' . $belumSelesaiBox . '</td>
                                <td class="custom2" style="font-weight: bold;">Belum selesai</td>
                            </tr>
                            <tr>
                            <td style="font-size: 20px; font-weight: bold;" width="10">' . (isset($dataSampelTidakSelesai) && $dataSampelTidakSelesai->status == "Dilanjutkan" ? "&#9745;" : "&#9744;") . '</td>
                                <td class="custom2" style="font-weight: bold;">dilanjutkan pada</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="custom2">' . $tanggalHtml . '</td>
                            </tr>

                        </table>
                    </td>
                    <td style="border: 1px solid #000000;" width="240">
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                            <tr>
                                <td colspan="3" class="custom2" style="font-size: 10px; font-weight: bold; padding-bottom: 4px;">Catatan belum selesai :</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Dibatalkan oleh pihak pelanggan" ? "&#9745;" : "&#9744;") . '</td>
                                <td colspan="2" class="custom2">Dibatalkan oleh pihak pelanggan</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Terbatas/kendala waktu/cuaca" ? "&#9745;" : "&#9744;") . '</td>
                                <td colspan="2" class="custom2">Terbatas / kendala waktu / cuaca</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Titik sampling tidak/belum siap" ? "&#9745;" : "&#9744;") . '</td>
                                <td colspan="2" class="custom2">Titik sampling tidak / belum siap</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Sample di pick up" ? "&#9745;" : "&#9744;") . '</td>
                                <td colspan="2" class="custom2">Sample di pick up</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') != "Dibatalkan oleh pihak pelanggan" && ($dataSampelTidakSelesai->alasan ?? '') != "Terbatas/kendala waktu/cuaca" && ($dataSampelTidakSelesai->alasan ?? '') != "Titik sampling tidak/belum siap" && ($dataSampelTidakSelesai->alasan ?? '') != "Sample di pick up" && (isset($dataSampelTidakSelesai) ? $dataSampelTidakSelesai->status != "Dilanjutkan" : true) && ($dataSampelTidakSelesai->alasan ?? '') != "" && isset($dataSampelTidakSelesai->alasan) ? "&#9745;" : "&#9744;") . '</td>
                                <td colspan="2" class="custom2">Lainnya :' . (($dataSampelTidakSelesai->alasan ?? '') != "Dibatalkan oleh pihak pelanggan" && ($dataSampelTidakSelesai->alasan ?? '') != "Terbatas/kendala waktu/cuaca" && ($dataSampelTidakSelesai->alasan ?? '') != "Titik sampling tidak/belum siap" && ($dataSampelTidakSelesai->alasan ?? '') != "Sample di pick up" && (isset($dataSampelTidakSelesai) ? $dataSampelTidakSelesai->status != "Dilanjutkan" : true) && ($dataSampelTidakSelesai->alasan ?? '') != "" && isset($dataSampelTidakSelesai->alasan) ? (($dataSampelTidakSelesai->alasan ?? '') == "Lainnya" ? ($dataSampelTidakSelesai->keterangan ?? '') : ($dataSampelTidakSelesai->alasan ?? '')) : "...............................................") . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                ');
            }

            $pdf->WriteHTML('</table>');
        }

        $catatan = $selectedDetail['catatan'] ?? '';
        $informasiTeknis = $selectedDetail['informasi_teknis'] ?? '';
        $tandaTangan = $selectedDetail['tanda_tangan'] ?? [];

        $signatureData = [];
        if (!empty($tandaTangan) && is_array($tandaTangan)) {
            $signatureData = array_map(function ($sig) {
                return [
                    'role' => $sig['role'],
                    'nama' => $sig['nama'],
                    'tanda_tangan' => $sig['tanda_tangan']
                ];
            }, $tandaTangan);
        }

        $samplers = [];
        $pelanggans = [];
        if (is_array($signatureData)) {
            foreach ($signatureData as $sig) {
                if (isset($sig['role']) && $sig['role'] === 'sampler') {
                    $samplers[] = $sig;
                } elseif (isset($sig['role']) && $sig['role'] === 'pelanggan') {
                    $pelanggans[] = $sig;
                }
            }
        }

        $samplerHtml = '';
        if (!empty($samplers)) {
            foreach ($samplers as $index => $sampler) {
                $number = $index + 1;
                $ttd_sampler = $this->decodeImageToBase64($sampler['tanda_tangan']);
                $samplerHtml .= '
                    <tr>
                        <td width="3"></td>
                        <td width="100" style="font-size: 14px; border: 1px solid #000000; padding: 10px; text-align: center;">' . $number . '. ' . ($sampler['nama'] ?? 'No Name') . '</td>
                        <td width="100" style="border: 1px solid #000000; padding: 10px; text-align: center;">' .
                    (!empty($sampler['tanda_tangan']) && $ttd_sampler->status !== 'error' ? '<img src="' . $ttd_sampler->base64 . '" alt="" style="max-width: 100px; max-height: 50px;" />' : 'Belum ada tanda tangan') .
                    '</td>
                        <td width="3"></td>
                    </tr>';
            }
        } else {
            $samplerHtml = '
                <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding: 10px; text-align: center;">1. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding: 10px; text-align: center;"></td>
                    <td width="3"></td>
                </tr>';
        }

        $pelangganHtml = '';
        if (!empty($pelanggans)) {
            foreach ($pelanggans as $index => $pelanggan) {
                $number = $index + 1;
                $ttd_pelanggan = $this->decodeImageToBase64($pelanggan['tanda_tangan']);

                $pelangganHtml .= '
                    <tr>
                        <td width="3"></td>
                        <td width="100" style="font-size: 14px; border: 1px solid #000000; padding: 10px; text-align: center;">' . $number . '. ' . ($pelanggan['nama'] ?? 'No Name') . '</td>
                        <td width="100" style="border: 1px solid #000000; padding: 10px; text-align: center;">' .
                    (!empty($pelanggan['tanda_tangan']) && $ttd_pelanggan->status !== 'error' ? '<img src="' . $ttd_pelanggan->base64 . '" alt="" style="max-width: 100px; max-height: 50px;" />' : 'Belum ada tanda tangan') .
                    '</td>
                        <td width="3"></td>
                    </tr>';
            }
        } else {
            $pelangganHtml = '
                <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding: 10px; text-align: center;">1. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding: 10px; text-align: center;"></td>
                    <td width="3"></td>
                </tr>';
        }

        $pdf->AddPage();
        $pdf->WriteHTML('
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td colspan="5" style="border: 1px solid #000000; padding-bottom: 9px;">
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-left: 12px; margin-top: 12px;">
                            <tr>
                                <td style="font-size: 14px; padding-bottom: 13px;">Catatan tambahan :</td>
                            </tr>
                            <tr>
                                <td style="font-size: 14px; padding-bottom: 13px;">
                                ' . ($catatan ? $catatan : '
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                ') . '
                                </td>
                            </tr>
                        </table>
                    </td>
                    <tr>
                    <td colspan="5" style="padding: 5px;"></td>
                    </tr>
                </tr>
                <tr>
                    <td colspan="5" style="border: 1px solid #000000; padding-bottom: 9px;">
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-left: 12px; margin-top: 12px;">
                            <tr>
                                <td style="font-size: 14px; padding-bottom: 13px;">Informasi-Informasi Teknis Yang Berkaitan Dengan Kegiatan Pengujian Selanjutnya : </td>
                            </tr>
                            <tr>
                                <td style="font-size: 14px; padding-bottom: 13px;">
                                    ' . ($informasiTeknis ? $informasiTeknis : '
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
                                    </tr>
                                    ') . '  
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="5" style="padding: 5px;"></td>
                </tr>
                <tr>
                    <td colspan="5" style="font-size: 14px;">Sesuai dengan rincian diatas maka pihak-pihak yang berkaitan dengan kegiatan, menyetujui adanya data dan informasi tersebut</td>
                </tr>
                <tr>
                    <td colspan="5" style="padding: 5px;"></td>
                </tr>
                <tr>
                    <td colspan="5">
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 16px;">
                            <tr>
                                <td style="border: 1px solid #000000;">
                                    <table width="100%" style="padding-top: 10px; padding-bottom: 10px;">
                                        <tr>
                                            <td colspan="4" style="text-align: center; font-weight: bold; font-size: 14px;">
                                                <span>Pihak Yang Menjalankan Kegiatan</span>
                                                <br/>
                                                <span style="font-style: italic;"> (Sampler) </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="text-align: center; font-weight: bold; font-size: 14px;">
                                                Nama Lengkap
                                            </td>
                                            <td colspan="2" style="text-align: center; font-weight: bold; font-size: 14px;">
                                                Tanda Tangan
                                            </td>
                                        </tr>
                                        ' . $samplerHtml . '
                                    </table>
                                </td>

                                <td style="padding: 8px;"></td>

                                <td style="border: 1px solid #000000;">
                                    <table width="100%" style="padding-top: 10px; padding-bottom: 10px;">
                                        <tr>
                                            <td colspan="4" style="text-align: center; font-weight: bold; font-size: 14px;">
                                                <span>Pihak Yang Menjalankan Kegiatan</span>
                                                <br/>
                                                <span style="font-style: italic;">(Pelanggan)</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="text-align: center; font-weight: bold; font-size: 14px;">
                                                Nama Lengkap
                                            </td>
                                            <td colspan="2" style="text-align: center; font-weight: bold; font-size: 14px;">
                                                Tanda Tangan
                                            </td>
                                        </tr>
                                    ' . $pelangganHtml . '
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>');

        $path = public_path('dokumen/bas');

        // Pastikan direktori tersedia
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // Cek apakah file lama ada dan hapus jika ditemukan
        // if ($file_name_old && file_exists($path . '/' . $file_name_old)) {
        //     unlink($path . '/' . $file_name_old);
        // }

        // Path file lengkap
        $filePath = $path . '/' . $filename;

        $pdf->Output($filePath, 'F');
        // return $pdf->Output('', 'I');
        return response()->json([$filename], 200);
    }


    public function convertBase64ToImage($base64Input)
    {
        $file = $this->cleanBase64($base64Input);
        // Pastikan input adalah string base64 yang valid
        if (!base64_decode($file, true)) {
            return (object) [
                'status' => 'error',
                'message' => 'Input base64 tidak valid'
            ];
        }

        // Decode base64
        $imageContent = base64_decode($file);

        // Deteksi tipe file berdasarkan header
        $fileType = self::detectFileType($imageContent);

        // Generate nama file unik
        $filename = 'SIGN_BAS_' . Str::uuid() . '.' . $fileType;

        // Path penyimpanan
        $path = public_path('dokumen/bas/signatures');

        // Pastikan direktori tersedia
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // Path file lengkap
        $filePath = $path . '/' . $filename;

        // Simpan file
        file_put_contents($filePath, $imageContent);

        // Kembalikan respons
        return (object) [
            'status' => 'success',
            'filename' => $filename,
            'path' => $filePath,
            'file_type' => $fileType
        ];
    }

    private function detectFileType($fileContent)
    {
        // Signature file untuk berbagai format
        $signatures = [
            'png' => "\x89PNG\x0D\x0A\x1A\x0A",
            'jpg' => "\xFF\xD8\xFF",
            'gif' => "GIF87a",
            'webp' => "RIFF",
            'svg' => '<?xml'
        ];

        foreach ($signatures as $type => $signature) {
            if (strpos($fileContent, $signature) === 0) {
                return $type;
            }
        }

        return 'bin';
    }

    /**
     * Membersihkan base64 dari header tidak perlu
     *
     * @param string $base64Input
     * @return string
     */
    public function cleanBase64($base64Input)
    {
        // Hapus header data URI jika ada
        $base64Input = preg_replace('/^data:image\/(png|jpeg|gif|webp);base64,/', '', $base64Input);

        // Hapus whitespace
        $base64Input = preg_replace('/\s+/', '', $base64Input);

        return $base64Input;
    }

    public function decodeImageToBase64($filename)
    {
        // Path penyimpanan
        $path = public_path('dokumen/bas/signatures');

        // Path file lengkap
        $filePath = $path . '/' . $filename;

        // Periksa apakah file ada
        if (!file_exists($filePath)) {
            return (object) [
                'status' => 'error',
                'message' => 'File tidak ditemukan'
            ];
        }

        // Baca konten file
        $imageContent = file_get_contents($filePath);

        // Konversi ke base64
        $base64Image = base64_encode($imageContent);

        // Deteksi tipe file
        $fileType = $this->detectFileType($imageContent);

        // Tambahkan data URI header sesuai tipe file
        $base64WithHeader = 'data:image/' . $fileType . ';base64,' . $base64Image;

        // Kembalikan respons
        return (object) [
            'status' => 'success',
            'base64' => $base64WithHeader,
            'file_type' => $fileType
        ];
    }

    private function getDataLapangan($kategori_2, $kategori_3, $no_sample, $parameter)
    {
        $data = null;

        if ($kategori_2 === "1-Air") {
            $data = DataLapanganAir::where('no_sampel', $no_sample)->get();
            if ($data->isNotEmpty()) {
                foreach ($data as &$item) {
                    $item->filled = $data->count();
                }
            }
        } else if ($kategori_2 === "4-Udara" && $kategori_3 === "23-Kebisingan") {
            $data = DataLapanganKebisingan::where('no_sampel', $no_sample)->get() ?? DataLapanganKebisinganPersonal::where('no_sampel', $no_sample)->get();
        } else if ($kategori_2 == "4-Udara" && $kategori_3 == "24-Kebisingan (24 Jam)") {
            $data = DataLapanganKebisingan::where('no_sampel', $no_sample)->get();
        } else if ($kategori_2 === "4-Udara" && $kategori_3 === "28-Pencahayaan") {
            $data = DataLapanganCahaya::where('no_sampel', $no_sample)->get();
        } else if (
            $kategori_2 === "5-Emisi" &&
            in_array($kategori_3, ["32-Emisi Kendaraan (Solar)", "31-Emisi Kendaraan (Bensin)"])
        ) {
            $data = DataLapanganEmisiKendaraan::where('no_sampel', $no_sample)->get();
        } else if (
            $kategori_2 === "4-Udara" &&
            in_array($kategori_3, ["19-Getaran (Mesin)", "15-Getaran (Kejut Bangunan)", "13-Getaran"])
        ) {
            $data = DataLapanganGetaran::where('no_sampel', $no_sample)->get();
        } else if (
            $kategori_2 === "4-Udara" &&
            in_array($kategori_3, ["17-Getaran (Lengan & Tangan)", "20-Getaran (Seluruh Tubuh)"])
        ) {
            $data = DataLapanganGetaranPersonal::where('no_sampel', $no_sample)->get();
        } else if ($kategori_2 === "4-Udara" && $kategori_3 === "21-Iklim Kerja") {
            $data = DataLapanganIklimPanas::where('no_sampel', $no_sample)->get();
            if ($data->isEmpty()) {
                $data = DataLapanganIklimDingin::where('no_sampel', $no_sample)->get();
            }
        } else if (
            $kategori_2 === "4-Udara" &&
            in_array($kategori_3, ["11-Udara Ambient", "27-Udara Lingkungan Kerja", "12-Udara Angka Kuman"])
        ) {
            $data = DataLapanganPartikulatMeter::where('no_sampel', $no_sample)->get();
            if ($data->isEmpty()) {
                if ($kategori_3 === "11-Udara Ambient") {
                    $data = DataLapanganLingkunganHidup::where('no_sampel', $no_sample)->get();
                } elseif ($kategori_3 === "27-Udara Lingkungan Kerja") {
                    $data = DataLapanganLingkunganKerja::where('no_sampel', $no_sample)->get();
                } elseif ($kategori_3 === "12-Udara Angka Kuman") {
                    $data = DataLapanganMicrobiologi::where('no_sampel', $no_sample)->get();
                }
            }
        } else if ($kategori_2 === "4-Udara" && $kategori_3 === "46-Udara Swab Test") {
            $data = DataLapanganSwab::where('no_sampel', $no_sample)->get();
        } else if ($kategori_2 === "4-Udara" && $kategori_3 === "53-Ergonomi") {
            $data = DataLapanganErgonomi::where('no_sampel', $no_sample)->get();
        } else if ($kategori_2 === "5-Emisi" && $kategori_3 === "34-Emisi Sumber Tidak Bergerak") {
            $data = DataLapanganEmisiCerobong::where('no_sampel', $no_sample)->get()
                ?? DataLapanganIsokinetikHasil::where('no_sampel', $no_sample)->get();
        }

        if ($data instanceof Collection) {
            if ($data->count() >= 2) {
                $data = $data->sortByDesc('created_at')->first();
            } else if ($data->count() === 1) {
                $data = $data->first();
            } else {
                $data = null;
            }
        }

        return $data;
    }

    public function storeSampelTidakSelesai(Request $request) // store sampelTidakSelesai
    {
        DB::beginTransaction();
        try {
            SampelTidakSelesai::updateOrCreate(
                ['no_sampel' => $request->no_sampel],
                [
                    'no_order' => $request->no_order,
                    'kategori' => $request->kategori ?? null,
                    'keterangan' => ($request->status === 'Dilanjutkan') ? null : ($request->keterangan ?? null),
                    'status' => $request->status ?? null,
                    'alasan' => ($request->status === 'Dilanjutkan') ? null : ($request->alasan ?? null),
                    'tanggal_dilanjutkan' => ($request->status === 'Belum Selesai') ? null : ($request->tanggal_dilanjutkan ?? null),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]
            );
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sampel tidak selesai berhasil disimpan',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }

    }

    private function getStatusSampling($sample)
    {
        try {
            $parametersRaw = json_decode($sample->parameter);
            
            // 1. Panggil data Template ICP di luar loop (sekali saja agar query ringan)
            // Pastikan Anda sudah meng-import: use App\Models\TemplateStp; di atas class
            $templateIcp = TemplateStp::where('name', 'icp')
                ->where('category_id', 4)
                ->first();
                
            $icpParameters = [];
            if ($templateIcp && $templateIcp->param) {
                // Decode array JSON seperti $a yang Anda berikan tadi
                $icpParameters = json_decode($templateIcp->param, true) ?? [];
            }
            
            // Panggil sekali di luar loop, bukan di dalam array_reduce
            $requiredParameters = collect($this->getRequiredParameters())
                ->where('category', $sample->kategori_2);

            $parameters = array_reduce($parametersRaw, function ($carry, $item) use ($sample, $requiredParameters) {
                $parameterName = explode(";", $item)[1] ?? null;

                if (!$parameterName) {
                    return $carry;
                }

                $matchedParameter = $requiredParameters
                    ->where('parameter', $parameterName)
                    ->first();

                if ($matchedParameter == null) {
                    Log::error("Kemungkinan Parameter: " . $parameterName);
                    throw new Exception("Kemungkinan Parameter.{$parameterName}. Belum Terdaftar di RequiredParameters Hub IT");
                }
                $carry[] = $matchedParameter;
                return $carry;
            }, []);

            $parameters = array_filter($parameters, function ($param) {
                if ($param == null) {
                    return false;
                }
                if ($param['category'] == '6-Padatan') {
                    return is_array($param);
                }
                return is_array($param) && isset($param['model']);
            });

            $status = 'selesai';
            $verifiedCount = 0;
            $totalValidParams = 0;
            if (!empty($parameters)) {
                $parameterBypass = ['Gelombang Elektro', 'N-Propil Asetat (SC)', 'Xylene secara personil sampling (SC)'];
                
                foreach ($parameters as $parameter) {
                    $paramName = $parameter['parameter']; // Ambil nama parameter untuk mempermudah pengecekan

                    if ($parameter['category'] == '6-Padatan') {
                        continue;
                    }
                    
                    if (in_array($paramName, $parameterBypass)) {
                        continue;
                    }

                    if ($sample->no_sample == 'ITEM012501/015' && in_array($paramName, ['NO2 (24 Jam)', 'PM 10 (24 Jam)', 'PM 2.5 (24 Jam)'])) {
                        continue;
                    }

                    if (in_array($sample->no_sample, ['BUIL022603/12', 'BUIL022603/14', 'BUIL022603/15', 'BUIL022603/16', 'BUIL022603/008'])) {
                        continue;
                    }

                    // --- LOGIKA BYPASS ICP TEMPLATE ---
                    // Cek apakah parameter saat ini ada di dalam list JSON Template ICP
                    if (in_array($paramName, $icpParameters)) {
                        
                        // Validasi Regex: Cari kata "jam" atau angka bergandengan huruf "j" (seperti 8j, 24j)
                        // /i = case-insensitive (Jam, jam, 8J, 8j akan terdeteksi)
                        if (!preg_match('/(jam|\d+j)/i', $paramName)) {
                            
                            // Jika TIDAK MENGANDUNG "jam" atau "8j", maka BYPASS (dianggap selesai).
                            continue; 
                        }
                        
                        // Jika MENGANDUNG "jam" atau "8j" (misal: "Pb 8J (IKM-ICP-LK)"), 
                        // kode akan mengabaikan blok if ini dan tetap lanjut diperiksa di bawah oleh verifyStatus.
                    }
                    // ----------------------------------

                    $totalValidParams++;
                    $sampleNumber = $sample->no_sampel ?? $sample->no_sample;
                    $verified = $this->verifyStatus($sampleNumber, $parameter);

                    if ($verified) {
                        $verifiedCount++;
                    }
                }

                if ($verifiedCount === 0 && $totalValidParams > 0) {
                    $hasAnyRecord = false;
                    foreach ($parameters as $parameter) {
                        $modelsToCheck = [];
                        if (isset($parameter['model'])) $modelsToCheck[] = $parameter['model'];
                        if (isset($parameter['model2'])) $modelsToCheck[] = $parameter['model2'];
                        if (isset($parameter['model3'])) $modelsToCheck[] = $parameter['model3'];
                        
                        foreach ($modelsToCheck as $m) {
                            $modelsToTest = [$m];
                            if ($m === DetailLingkunganHidup::class) $modelsToTest[] = \App\Models\DataLapanganLingkunganHidup::class;
                            if ($m === DetailLingkunganKerja::class) $modelsToTest[] = \App\Models\DataLapanganLingkunganKerja::class;
                            if ($m === DetailSenyawaVolatile::class) $modelsToTest[] = \App\Models\DataLapanganSenyawaVolatile::class;
                            if ($m === DetailMicrobiologi::class) $modelsToTest[] = \App\Models\DataLapanganMicrobiologi::class;

                            $sampleNumber = $sample->no_sampel ?? $sample->no_sample;
                            foreach ($modelsToTest as $testModel) {
                                if ($testModel::where('no_sampel', $sampleNumber)->exists()) {
                                    $hasAnyRecord = true;
                                    break 3;
                                }
                            }
                        }
                    }
                    $status = $hasAnyRecord ? 'parsial' : 'belum selesai';
                } elseif ($verifiedCount > 0 && $verifiedCount < $totalValidParams) {
                    $status = 'parsial';
                } elseif ($verifiedCount === $totalValidParams) {
                    $status = 'selesai';
                }
            } else {
                $status = 'belum selesai';
            }

            return $status;
        } catch (\Exception $th) {
            throw new Exception($th->getMessage());
        }
    }

    private function verifyStatus($sample_number, $parameter)
    {
        try {
            if (empty($parameter['model'])) {
                return true;
            }

            $model = $parameter['model'];
        $model2 = isset($parameter['model2']) ? $parameter['model2'] : null;
        $model3 = isset($parameter['model3']) ? $parameter['model3'] : null;
        $paramName = isset($parameter['parameter']) ? $parameter['parameter'] : null;
        $requiredCount = isset($parameter['requiredCount']) ? (int) $parameter['requiredCount'] : 1;

        $environmentModels = [
            DetailLingkunganHidup::class,
            DetailLingkunganKerja::class,
        ];

        if (in_array($model, $environmentModels, true)) {
            return $this->handleEnvironmentModel($sample_number, $parameter, $model, $model2, $model3);
        }

        // non-environment: hanya kembalikan builder jika count >= requiredCount, else null
        $query = $model::where('no_sampel', $sample_number);
        if (\Illuminate\Support\Facades\Schema::hasColumn((new $model)->getTable(), 'is_blocked')) {
            $query->where('is_blocked', 0);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn((new $model)->getTable(), 'is_rejected')) {
            $query->where('is_rejected', 0);
        }
        
        if ($paramName === 'Opasitas (Solar)') {
            $queryN = clone $query;
            $queryN = $queryN->first();
            if ($queryN == null) {
                if ($model2 != null) {
                    $query = $model2::where('no_sampel', $sample_number);
                    if (\Illuminate\Support\Facades\Schema::hasColumn((new $model2)->getTable(), 'is_blocked')) {
                        $query->where('is_blocked', 0);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn((new $model2)->getTable(), 'is_rejected')) {
                        $query->where('is_rejected', 0);
                    }
                } else {
                    return null;
                }
            }
        }
        $modelsWithParameter = [
            DetailLingkunganHidup::class,
            DetailSenyawaVolatile::class,
            DetailMicrobiologi::class,
            DataLapanganDirectLain::class,
        ];
        if (in_array($model, $modelsWithParameter, true) && $paramName !== null) {
            $query->where('parameter', $paramName);
        }

        $count = $query->count();
        if ($count >= $requiredCount) {
            return $query;
        }
        return null;
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error("Error in verifyStatus: " . $th->getMessage(), [
                'no_sampel' => $sample_number,
                'parameter' => $parameter,
                'line' => $th->getLine()
            ]);
            throw $th;
        }
    }

    private function handleEnvironmentModel($sample_number, $parameter, $model, $model2, $model3)
    {
        $paramName = isset($parameter['parameter']) ? $parameter['parameter'] : null;
        $requiredCount = isset($parameter['requiredCount']) ? (int) $parameter['requiredCount'] : 1;

        $hasPMParameter = in_array($paramName, ['PM 10 (24 Jam)', 'PM 2.5 (24 Jam)','Kelembaban','Suhu'], true);
        if (!$hasPMParameter) {
            $model3 = null;
        }

        if ($model3 === null || $model3 === 'App\Models\DetailMicrobiologi' ) {  
            return $this->handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2,$model3);
        } else {
            return $this->handlePMParameters($sample_number, $paramName, $requiredCount, $model, $model2, $model3);
        }
    }

    private function handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2, $model3)
    {
        // Suhu / Kelembaban: kembalikan model instance (first) atau null
        if (in_array($paramName, ['Suhu', 'Kelembaban', 'Laju Ventilasi', 'Laju Ventilasi (8 Jam)'], true)) {
            // Mapping nama kolom
            if ($paramName === 'Kelembaban') {
                $searchColumn = 'Kelembapan';
            } elseif ($paramName === 'Laju Ventilasi' || $paramName === 'Laju Ventilasi (8 Jam)') {
                $searchColumn = 'laju_ventilasi';
            } else {
                $searchColumn = $paramName;
            }

            // Jika parameternya Laju Ventilasi, hanya cari di model yang merupakan LingkunganKerja
            if ($paramName === 'Laju Ventilasi' || $paramName === 'Laju Ventilasi (8 Jam)') {
                // Cek apakah $model adalah instance LingkunganKerja
                if ($model == DetailLingkunganKerja::class) {
                    $found = $model::where('no_sampel', $sample_number)
                        ->whereNotNull($searchColumn)
                        ->first();
                    if ($found) {
                        return $found;
                    }
                }

                // Cek juga di $model2 jika ada dan juga merupakan LingkunganKerja
                if ($model2 == DetailLingkunganKerja::class) {
                    return $model2::where('no_sampel', $sample_number)
                        ->whereNotNull($searchColumn)
                        ->first();
                }

                // Jika tidak ada yang cocok
                return null;
            }

            // Untuk Suhu atau Kelembaban, cari biasa di model lalu model2
            $found = $model::where('no_sampel', $sample_number)
                ->whereNotNull($searchColumn)
                ->first();

            if ($found) {
                return $found;
            }

            if ($model2) {
                $found = $model2::where('no_sampel', $sample_number)
                    ->whereNotNull($searchColumn)
                    ->first();
                if ($found) {
                    return $found;
                }
            }

            if ($model3) {
                $found = $model3::where('no_sampel', $sample_number)
                    ->whereNotNull($searchColumn)
                    ->first();
                
                if ($found) {
                    return $found;
                }
            }

            return null;
        }

        // Default parameter: kembalikan builder jika count >= requiredCount, else null
        if ($paramName === null) {
            return null;
        }
        $query1 = $model::where('no_sampel', $sample_number)
            ->where('parameter', $paramName);
        $count1 = $query1->count();
        if ($count1 >= $requiredCount) {
            return $query1;
        }
        if ($model2) {
            $query2 = $model2::where('no_sampel', $sample_number)
                ->where('parameter', $paramName);
            $count2 = $query2->count();
            if ($count2 >= $requiredCount) {
                return $query2;
            }
        }
        return null;
    }

    private function handlePMParameters($sample_number, $paramName, $requiredCount, $model, $model2, $model3)
    {
        if ($paramName === null) {
            return null;
        }
        // Model utama
        $query1 = $model::where('no_sampel', $sample_number)
            ->where('parameter', $paramName);
        $count1 = $query1->count();
        if ($count1 >= $requiredCount) {
            return $query1;
        }
        // Model2
        if ($model2) {
            $query2 = $model2::where('no_sampel', $sample_number)
                ->where('parameter', $paramName);
            $count2 = $query2->count();
            if ($count2 >= $requiredCount) {
                return $query2;
            }
        }
        // Model3
        $query3 = $model3::where('no_sampel', $sample_number)
            ->where('parameter', $paramName);
        $count3 = $query3->count();
        if ($count3 >= $requiredCount) {
            return $query3;
        }
        return null;
    }

    private function getRequiredParameters()
    {
        // Baca dari DB (hasil input via tools UI)
        $fromDb = RequiredParameters::all()->map(function ($item) {
            return [
                "parameter"     => $item->parameter,
                "requiredCount" => $item->required_count,
                "category"      => $item->category,
                "model"         => $item->model,
                "model2"        => $item->model2,
                "model3"        => $item->model3,
            ];
        })->toArray();

        // $padatanParam tetap hardcode karena fixed, tidak perlu masuk DB
        $padatanParam = [
            "Al","Sb","Ag","As","Ba","Fe","B","Cd","Ca","Co","Mn","Na","Ni","Hg","Se","Zn","Tl","Cu","Sn","Pb","Ti","Cr","V","F",
            "NO2","Cr6+","Mo","NO3","CN","Sulfida","Cl-","OG","Chloride",
            "E.Coli (MM)", "Salmonella (MM)", "Shigella Sp. (MM)", "Vibrio Ch (MM)", "S.Aureus"
        ];

        foreach ($padatanParam as $value) {
            $fromDb[] = [
                "parameter"     => $value,
                "requiredCount" => 1,
                "category"      => "6-Padatan",
                "model"         => null,
                "model2"        => null,
            ];
        }

        return $fromDb;
    }
}
