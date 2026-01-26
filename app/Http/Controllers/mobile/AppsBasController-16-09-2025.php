<?php

namespace App\Http\Controllers\mobile;

use Carbon\Carbon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
use App\Models\MasterKaryawan;
use Illuminate\Support\Str;

use App\Services\SendEmail;

use App\Services\MpdfService as Mpdf;

use DateTime;

class AppsBasController extends Controller
{
    // public function index(Request $request)
    // {
    //     try {
    //         $orderDetail = OrderDetail::with([
    //             'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi,email_pic_order,email_pic_sampling',
    //             'orderHeader.samplingPlan',
    //             'orderHeader.samplingPlan.jadwal' => function ($q) {
    //                 $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
    //                     ->where('is_active', true)
    //                     ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai']);
    //             },
    //             'orderHeader.docCodeSampling' => function ($q) {
    //                 $q->where('menu', 'STPS');
    //             }
    //         ])
    //             ->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1'])
    //             ->where('is_active', true)
    //             ->where('kategori_1', '!=', 'SD')
    //             ->whereBetween('tanggal_sampling', [
    //                 // "2025-04-31",
    //                 Carbon::now()->subWeeks(8)->toDateString(),
    //                 // Carbon::now()->subDays(8)->toDateString(),
    //                 Carbon::now()->toDateString()
    //             ])
    //             ->groupBy(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1']);

    //         $orderDetail = $orderDetail->get()->toArray();

    //         // dd($orderDetail);
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

    //         // Ambil semua no_order dari hasil akhir
    //         $orderNos = array_column($finalResult, 'no_order');

    //         // Ambil data catatan, informasi teknis, dan tanda_tangan_bas dari tabel PersiapanSampelHeader berdasarkan no_order
    //         $persiapanHeaders = PersiapanSampelHeader::whereIn('no_order', $orderNos)->get()->keyBy('no_order');

    //         // Add detail_bas_documents to each item
    //         foreach ($finalResult as &$item) {
    //             if (isset($persiapanHeaders[$item['no_order']])) {
    //                 $header = $persiapanHeaders[$item['no_order']];

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
    //                     $signature = array_filter($signature, function ($item) {
    //                         return $item !== null;
    //                     });
    //                     $item['tanda_tangan_bas'] = $signature;
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

    //         // Filter data untuk hanya mendapatkan data yang memiliki 'sampler' sesuai dengan $this->karyawan
    //         $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)->whereIn('id_jabatan', [41, 42])->exists();
    //         if ($isProgrammer) {
    //             $filteredResult = $finalResult;
    //         } else {
    //             $filteredResult = array_filter($finalResult, function ($item) {
    //                 return isset($item['sampler']) && $item['sampler'] == $this->karyawan;
    //             });
    //         }

    //         // Reindex array setelah filter jika diperlukan
    //         $filteredResult = array_values($filteredResult);
    //         // dd($filteredResult);

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

    //         $orderD = OrderDetail::where('no_order', $request->no_order)
    //             ->where('is_active', true)
    //             ->where('tanggal_sampling', $request->tanggal_sampling)
    //             ->get()
    //             ->map(function ($item) {
    //                 return (object) $item->toArray(); // ubah ke stdClass
    //             });

    //         if (!$orderD->isEmpty()) {
    //             $detail_sampling_sampel = [];

    //             foreach ($orderD as $key => $item) {
    //                 $item->no_sample = $item->no_sampel;
    //                 if ($item->kategori_2 === "1-Air") {
    //                     $exists = DataLapanganAir::where('no_sampel', $item->no_sample)->exists();
    //                     $detail_sampling_sampel[$key]['status'] = $exists ? 'selesai' : 'belum selesai';
    //                     $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
    //                     $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
    //                     $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
    //                     $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

    //                     $dataSampelBelumSelesai = SampelTidakSelesai::where('no_sampel', $item->no_sample)->first();
    //                     $detail_sampling_sampel[$key]['status_sampel'] = (bool) $dataSampelBelumSelesai;

    //                 } else {
    //                     $detail_sampling_sampel[$key]['status'] = $this->getStatusSampling($item);
    //                     $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
    //                     $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
    //                     $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
    //                     $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

    //                     $dataSampelBelumSelesai = SampelTidakSelesai::where('no_sampel', $item->no_sample)->first();
    //                     $detail_sampling_sampel[$key]['status_sampel'] = (bool) $dataSampelBelumSelesai;
    //                 }
    //             }
    //             // dd($detail_sampling_sampel);

    //             // Gabungkan detail_sampling_sampel ke filteredResult
    //             foreach ($filteredResult as $key => $value) {
    //                 $kategoriItems = explode(',', $value['kategori']);

    //                 $matchedDetails = [];

    //                 foreach ($kategoriItems as $item) {
    //                     $parts = explode('-', $item);
    //                     $nomor  = trim(end($parts)); 

    //                     $katNoOrder = $value['no_order'] . '/' . $nomor;

    //                     foreach ($detail_sampling_sampel as $detail) {
    //                         if ($detail['no_sampel'] === $katNoOrder) {
    //                             $matchedDetails[] = $detail;
    //                             break;
    //                         }
    //                     }
    //                 }
    //                 $filteredResult[$key]['detail_sampling_sampel'] = $matchedDetails;
    //             }

    //         }

    //         return DataTables::of($filteredResult)->make(true);
    //     } catch (\Exception $ex) {
    //         return response()->json([
    //             'message' => $ex->getMessage(),
    //             'line' => $ex->getLine(),
    //         ], 500);
    //     }
    // }

    public function index(Request $request)
    {
        try {
            $orderDetails = OrderDetail::with([
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
                ->where('kategori_1', '!=', 'SD')
                ->whereBetween('tanggal_sampling', [
                    Carbon::now()->subWeeks(8)->toDateString(),
                    Carbon::now()->toDateString()
                ])
                ->get();

            // ============= Prepare PersiapanSampelHeader ====================
            $noOrders = $orderDetails->pluck('orderHeader.no_order')->filter()->unique()->toArray();
            $persiapanHeaders = PersiapanSampelHeader::whereIn('no_order', $noOrders)->get()->keyBy('no_order');

            // ============= Prepare DataLapanganAir & SampelTidakSelesai =============
            $noSampels = $orderDetails->pluck('no_sampel')->filter()->unique()->toArray();
            $airExists = DataLapanganAir::whereIn('no_sampel', $noSampels)->pluck('no_sampel')->flip();
            $sampelBelumSelesai = SampelTidakSelesai::whereIn('no_sampel', $noSampels)->pluck('no_sampel')->flip();

            // ============= Format Data ================
            $formattedData = collect($orderDetails)->flatMap(function ($item) {
                if (empty($item->orderHeader) || empty($item->orderHeader->samplingPlan)) {
                    return [];
                }

                $samplingPlan = $item->orderHeader->samplingPlan;
                $periode = $item->periode ?? '';

                $targetPlan = $periode
                    ? collect($samplingPlan)->firstWhere('periode_kontrak', $periode)
                    : collect($samplingPlan)->first();

                if (!$targetPlan) return [];

                $results = [];
                $jadwal = $targetPlan['jadwal'] ?? [];

                foreach ($jadwal as $schedule) {
                    if ($schedule['tanggal'] == $item->tanggal_sampling) {
                        $results[] = [
                            'nomor_quotation' => $item->orderHeader->no_document ?? '',
                            'nama_perusahaan' => $item->orderHeader->nama_perusahaan ?? '',
                            'status_sampling' => $item->kategori_1 ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'durasi' => $schedule['durasi'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item->no_order ?? '',
                            'alamat_sampling' => $item->orderHeader->alamat_sampling ?? '',
                            'konsultan' => $item->orderHeader->konsultan ?? '',
                            'is_revisi' => $item->orderHeader->is_revisi ?? '',
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item->orderHeader->nama_pic_order,
                                'nama_pic_sampling' => $item->orderHeader->nama_pic_sampling,
                                'no_tlp_pic_sampling' => $item->orderHeader->no_tlp_pic_sampling,
                                'jabatan_pic_sampling' => $item->orderHeader->jabatan_pic_sampling,
                                'jabatan_pic_order' => $item->orderHeader->jabatan_pic_order
                            ]),
                            'info_sampling' => json_encode([
                                'id_sp' => $targetPlan['id'],
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation'],
                            ]),
                            'email_pic_sampling' => $item->orderHeader->email_pic_sampling ?? '',
                            'nama_pic_sampling' => $item->orderHeader->nama_pic_sampling ?? '',
                            'parameter' => $item->parameter,
                            'kategori_2' => $item->kategori_2,
                            'no_sample' => $item->no_sampel,
                            'keterangan_1' => $item->keterangan_1
                        ];
                    }
                }
                return $results;
            });

            // ============= Group Data ================
            $grouped = [];
            foreach ($formattedData as $item) {
                $key = json_encode([
                    $item['nomor_quotation'], $item['nama_perusahaan'], $item['status_sampling'], $item['periode'],
                    $item['jadwal'], $item['durasi'], $item['no_order'], $item['alamat_sampling'],
                    $item['konsultan'], $item['kategori'], $item['info_pendukung'],
                    $item['jadwal_jam_mulai'], $item['jadwal_jam_selesai'], $item['info_sampling'],
                    $item['email_pic_sampling'], $item['nama_pic_sampling']
                ]);

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'base_data' => $item,
                        'samplers' => []
                    ];
                }

                if (!in_array($item['sampler'], $grouped[$key]['samplers'])) {
                    $grouped[$key]['samplers'][] = $item['sampler'];
                }
            }

            // ============= Flatten ================
            $finalResult = [];
            foreach ($grouped as $group) {
                foreach ($group['samplers'] as $sampler) {
                    $finalResult[] = array_merge($group['base_data'], ['sampler' => $sampler]);
                }
            }

            // ============= Tanda tangan + detail_bas_documents ================
            foreach ($finalResult as &$item) {
                $header = $persiapanHeaders[$item['no_order']] ?? null;

                $item['detail_bas_documents'] = [];
                if ($header) {
                    $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true) ?? [];
                    $item['catatan'] = $header->catatan ?? '';
                    $item['informasi_teknis'] = $header->informasi_teknis ?? '';
                    $item['waktu_mulai'] = $header->waktu_mulai ?? '';
                    $item['waktu_selesai'] = $header->waktu_selesai ?? '';

                    // Tanda tangan bas
                    $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                    $item['tanda_tangan_bas'] = collect($ttd_bas)->map(function ($ttd) {
                        $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                        return $sign->status != 'error' ? [
                            'nama' => $ttd['nama'],
                            'role' => $ttd['role'],
                            'tanda_tangan' => $sign->base64,
                            'tanda_tangan_lama' => $ttd['tanda_tangan']
                        ] : null;
                    })->filter()->values()->toArray();
                } else {
                    $item['catatan'] = $item['informasi_teknis'] = $item['waktu_mulai'] = $item['waktu_selesai'] = '';
                    $item['tanda_tangan_bas'] = [];
                }
            }

            // ============= Filter Sampler & tanggal ================
            $today = Carbon::today();
            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)->whereIn('id_jabatan', [41, 42])->exists();

            $filtered = collect($finalResult)->filter(function ($item) use ($today, $isProgrammer) {
                $jadwal = Carbon::parse($item['jadwal']);
                $durasi = (int) $item['durasi'];

                if (!$isProgrammer && (!isset($item['sampler']) || $item['sampler'] != $this->karyawan)) {
                    return false;
                }

                if ($durasi <= 1) return $jadwal->isSameDay($today);
                return $today->between($jadwal, $jadwal->copy()->addDays($durasi - 1));
            })->values();

            return DataTables::of($filtered)->make(true);
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

                    // Kode lama sebelum diubah menggunakan no_order
                    $header = PersiapanSampelHeader::where('no_quotation', $item['no_quotation'])->first();
                    // dd($header);

                    if ($header) {
                        $detailData = [
                            'catatan' => $item['catatan'] ?? $header->catatan,
                            'informasi_teknis' => $item['informasi_teknis'] ?? $header->informasi_teknis,
                            'waktu_mulai' => $item['waktu_mulai'] ?? $header->waktu_mulai,
                            'waktu_selesai' => $item['waktu_selesai'] ?? $header->waktu_selesai,
                            'filename' => $item['filename_bas'] ?? $header->filename_bas,
                            'no_sampel' => $item['no_sampel'] ?? []
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
            $cc  = $request->input('cc', []); 
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

            $ccArray      = [];

            if (!empty($cc)) {
                if (is_array($cc)) {
                    $ccArray = $cc;
                } else {
                    $ccArray = array_filter(array_map('trim', explode(',', $cc)));
                }
            }

            $emailInstance = SendEmail::where('to', $to)
                ->where('cc', $ccArray)
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
                DB::commit();
                return response()->json([
                    'status' => 200,
                    'message' => 'Email berhasil dikirim',
                    'details' => [
                        'to' => $to,
                        'cc' => $cc,
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
        // dd($request->all());
        try {
            if (!$request->has('no_document') || empty($request->no_document)) {
                return response()->json([
                    'data' => [],
                ], 200);
            }

            $jsonDecode = html_entity_decode($request->info_sampling);

            $infoSampling = json_decode($jsonDecode, true);

            $tipe = explode("/", $request->no_document);
            $request->kategori = explode(",", $request->kategori);

            // Get No Sample
            $noSample = [];
            if(!empty($request->no_sampel)) {
                $noSample = $request->no_sampel;
            }else {
                foreach ($request->kategori as $item) {
                    $parts = explode("-", $item);
                    array_push($noSample, $request->no_order . '/' . $parts[1]);
                }
            }
            // dd($noSample, $request->no_sampel);

            // Ambil data sampling plan
            $sp = SamplingPlan::where('id', $infoSampling['id_sp'])
                ->where('quotation_id', $infoSampling['id_request'])
                ->where('status_quotation', $infoSampling['status_quotation'])
                ->where('is_active', true)
                ->first();

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

            // $samplerJadwal = Jadwal::where('id_sampling', $sp->id)
            //     ->where('tanggal', $request->tanggal_sampling)
            //     ->where('is_active', true)
            //     ->get()->pluck('sampler');

            // $samplerJadwal = Jadwal::select(['sampler', 'kategori'])
            //     ->where('id_sampling', $sp->id)
            //     ->where('tanggal', $request->tanggal_sampling)
            //     ->where('is_active', true)
            //     ->get();

            $samplerJadwal = Jadwal::select(['sampler', 'kategori'])
            ->where([
                ['id_sampling', '=', $sp->id],
                ['tanggal', '=', $request->tanggal_sampling],
                ['is_active', '=', true],
            ])
            ->get();


            if ($samplerJadwal->isEmpty()) {
                return response()->json([
                    'message' => 'Data jadwal tidak ditemukan.!',
                ], 401);
            }

            // Ambil data order header berdasarkan no_document dan no_order
            $orderH = OrderHeader::where('no_document', $request->no_document)
                ->where('no_order', $request->no_order)
                ->first();

            // Ambil data PersiapanSampelHeader berdasarkan no_quotation kode lama menggunakan no_order
            $persiapanHeader = PersiapanSampelHeader::where('no_quotation', $request->no_document)->first();

            // dd($persiapanHeader);

            if ($persiapanHeader && !empty($persiapanHeader->detail_bas_documents)) {
                $orderH->detail_bas_documents = $persiapanHeader->detail_bas_documents;
            } else {
                $orderH->detail_bas_documents = json_encode([]);
            }

            // Ambil data order detail beserta relasi codingSampling
            $orderD = OrderDetail::with(['codingSampling'])
                ->where('id_order_header', $orderH->id)
                ->where('no_order', $request->no_order)
                ->whereIn('no_sampel', $noSample)
                ->whereIn('tanggal_sampling', $jadwal)
                ->where('is_active', true)
                ->get();
            // dd($orderD); 

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

            $file_name_old = $request->filename_old;
            $file_name = $request->filename;

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

                // dd($data_sampling);

                if ($vv->codingSampling) {
                    $dat_param[] = $vv->codingSampling;
                }
            }

            $status = [];
            $hariTanggal = [];
            foreach ($data_sampling as $sample) {
                // dd($sample);
                $dataLapangan = $this->getDataLapangan(
                    $sample->kategori_2,
                    $sample->kategori_3,
                    $sample->no_sample,
                    $sample->parameter
                );

                // $status[$sample->no_sample] = !is_null($dataLapangan) ? 'selesai' : 'belum selesai';
                // $status[$sample->no_sample] = $this->getStatusSampling($sample);

                if ($sample->kategori_2 === "1-Air") {
                    $exists = DataLapanganAir::where('no_sampel', $sample->no_sample)->exists();
                    $status[$sample->no_sample] = $exists ? 'selesai' : 'belum selesai';
                } else {
                    $status[$sample->no_sample] = $this->getStatusSampling($sample);
                }

                if ($dataLapangan && $dataLapangan->created_at && $status[$sample->no_sample] === 'selesai') {
                    if (!isset($hariTanggal[$sample->no_sample]) || $dataLapangan->created_at > $hariTanggal[$sample->no_sample]) {
                        $hariTanggal[$sample->no_sample] = $dataLapangan->created_at;
                    }
                } else {
                    if (!isset($hariTanggal[$sample->no_sample])) {
                        $hariTanggal[$sample->no_sample] = null;
                    }
                }

            }

            // dd($status);
            // dd([
            //     $orderH, $data_sampling, $dat_param, $persiapanHeader, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal
            // ]);

            // dd($persiapanHeader);

            $dataPdf = self::cetakBASPDF($orderH, $data_sampling, $dat_param, $persiapanHeader, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal, $noSample);
            return $dataPdf;
        } catch (\Exception $e) {
            // dd($e);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    private function cetakBASPDF($dataHeader, $dataSampling, $dataParam, $dataPersiapan, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal, $noSample)
    {
        // dd($dataSampling);
        // dd($hariTanggal);
        // dd($samplerJadwal);
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
        // $requestedSampels = array_map(function ($kategori) {
        //     $parts = explode('-', $kategori);
        //     return trim($parts[count($parts) - 1]);
        // }, $kategoriList);
        $requestedSampels = $noSample;

        asort($requestedSampels);

        // Nama File PDF Berdasarkan Kombinasi Kategori
        // $filename = str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '_' . $sampelNumber . '.pdf');

        $microtime = sprintf("%.0f", microtime(true) * 1000000);
        $filename = $file_name ? $file_name : str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '_' . $microtime . '.pdf');

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

        // Cari data detail yang cocok dengan nomor sampel
        foreach ($detailDocuments as $detail) {
            if (is_array($detail['no_sampel']) && !empty($detail['no_sampel'])) {
                $detailNoSampelSorted = $detail['no_sampel'];
                sort($detailNoSampelSorted);

                $requestedSampelsSorted = $requestedSampels;
                sort($requestedSampelsSorted); 

                if ($detailNoSampelSorted === $requestedSampelsSorted) {
                    $selectedDetail = $detail;
                    break;
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
            $hari = $carbon->translatedFormat('l');
            $tanggal = $carbon->translatedFormat('d F Y');
        } else {
            $jam = $menit = $hari = $tanggal = '';
        }
        $samplerKategoriMap = [];
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
                    $sampleSamplerMap[$sampling->no_sample] = $assignedSamplers;
                    
                    // Create combined key for samplers working together
                    $samplerKey = count($assignedSamplers) > 2 ? implode(', ', $assignedSamplers) : implode(' & ', $assignedSamplers);
                    
                    if (!isset($samplingBySampler[$samplerKey])) {
                        $samplingBySampler[$samplerKey] = [];
                    }
                    $samplingBySampler[$samplerKey][] = $sampling;
                }
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
                            ' . (!empty($hari) && !empty($tanggal)
                    ? '( ' . $hari . ' / ' . $tanggal . ' )'
                    : '(hari / tanggal : ' . ($hari ?: '...............') . ' / ' . ($tanggal ?: '.............................................') . ')') . '
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
                $dataSampelTidakSelesai = SampelTidakSelesai::where('no_sampel', $val->no_sample)->where('no_order', $val->no_order)->first();
                // dd($dataSampelTidakSelesai);
                $dat = explode("-", $val->kategori_3);
                $boxChecked = '&#9745;'; // ☑
                $boxUnchecked = '&#9744;'; // ☐

                $isSelesai = isset($status[$val->no_sample]) && $status[$val->no_sample] == 'selesai';
                $selesaiBox = $isSelesai ? $boxChecked : $boxUnchecked;
                $belumSelesaiBox = $isSelesai ? $boxUnchecked : $boxChecked;

                $raw = $hariTanggal[$val->no_sample] ?? null;

                if ($isSelesai) {
                    if ($raw) {
                        // parse & terjemahkan ke locale Indonesia
                        $c = Carbon::parse($raw)->locale('id');
                        $hari2 = $c->translatedFormat('l');      // e.g. "Jumat"
                        $tgl2 = $c->translatedFormat('d F Y');  // e.g. "17 April 2025"
                        $tanggalHtml = "Hari/Tanggal : {$hari2} / {$tgl2}";
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
                                <td class="custom2" style="font-weight: bold;">Belum selesai / dilanjutkan pada :</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="custom2">' . $tanggalHtml . '</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="custom2">Sisa Sampling : ' . ($isSelesai ? '0' : '1') . ' Titik</td>
                            </tr>
                        </table>
                    </td>
                    <td style="border: 1px solid #000000;" width="240">
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Dibatalkan oleh pihak pelanggan" ? "&#9745;" : "&#9744;") . '</td>
                                <td class="custom2">Dibatalkan oleh pihak pelanggan</td>
                                <td class="custom2">' . (($dataSampelTidakSelesai->alasan ?? '') == "Dibatalkan oleh pihak pelanggan" ? "1" : "......") . ' Titik</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Terbatas/kendala waktu/cuaca" ? "&#9745;" : "&#9744;") . '</td>
                                <td class="custom2">Terbatas / kendala waktu / cuaca</td>
                                <td class="custom2">' . (($dataSampelTidakSelesai->alasan ?? '') == "Terbatas/kendala waktu/cuaca" ? "1" : "......") . ' Titik</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') == "Titik sampling tidak/belum siap" ? "&#9745;" : "&#9744;") . '</td>
                                <td class="custom2">Titik sampling tidak / belum siap</td>
                                <td class="custom2">' . (($dataSampelTidakSelesai->alasan ?? '') == "Titik sampling tidak/belum siap" ? "1" : "......") . ' Titik</td>
                            </tr>
                            <tr>
                                <td style="font-size: 20px; font-weight: bold;" width="10">' . (($dataSampelTidakSelesai->alasan ?? '') != "Dibatalkan oleh pihak pelanggan" && ($dataSampelTidakSelesai->alasan ?? '') != "Terbatas/kendala waktu/cuaca" && ($dataSampelTidakSelesai->alasan ?? '') != "Titik sampling tidak/belum siap" && isset($dataSampelTidakSelesai->alasan) ? "&#9745;" : "&#9744;") . '</td>
                                <td colspan="2" class="custom2">Lainnya :' . (($dataSampelTidakSelesai->alasan ?? '') != "Dibatalkan oleh pihak pelanggan" && ($dataSampelTidakSelesai->alasan ?? '') != "Terbatas/kendala waktu/cuaca" && ($dataSampelTidakSelesai->alasan ?? '') != "Titik sampling tidak/belum siap" && isset($dataSampelTidakSelesai->alasan) ? ($dataSampelTidakSelesai->alasan ?? '') : "...............................................") . '</td>
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
            SampelTidakSelesai::create([
                'no_order' => $request->no_order,
                'no_sampel' => $request->no_sampel,
                'kategori' => $request->kategori ?? null,
                'keterangan' => $request->keterangan ?? null,
                'status' => $request->status ?? null,
                'alasan' => $request->alasan ?? null,
                'tanggal_dilanjutkan' => $request->tanggal_dilanjutkan ?? null,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);
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
    // KODE LAMA
    // private function getStatusSampling($sample) // return selesai / blm selesai
    // {
    //     $parameters = json_decode($sample->parameter);
    //     $parameters = array_reduce($parameters, function ($carry, $item) use ($sample) {
    //         $parameterName = explode(";", $item)[1];
    //         $carry[] = collect($this->getRequiredParameters())
    //             ->where('category', $sample->kategori_2)
    //             ->where('parameter', $parameterName)
    //             ->first();

    //         return $carry;
    //     }, []);

    //     $status = 'selesai';
    //     foreach ($parameters as $parameter) {
    //         $verified = $this->verifyStatus($sample->no_sample, $parameter);
    //         if (!$verified) {
    //             $status = 'belum selesai';
    //             break;
    //         };
    //     }

    //     return $status;
    // }

    private function getStatusSampling($sample) // return selesai / blm selesai
    {
        // dd($sample);
        $parametersRaw = json_decode($sample->parameter);
        $parameters = array_reduce($parametersRaw, function ($carry, $item) use ($sample) {
            $parameterName = explode(";", $item)[1] ?? null;

            if (!$parameterName) {
                return $carry;
            }

            $matchedParameter = collect($this->getRequiredParameters())
                ->where('category', $sample->kategori_2)
                ->where('parameter', $parameterName)
                ->first();

            $carry[] = $matchedParameter;
            return $carry;
        }, []);

        $parameters = array_filter($parameters, function ($param) {
            return is_array($param) && isset($param['model']);
        });
        // dd($parameters);

        $status = 'selesai';

        foreach ($parameters as $parameter) {
            $verified = $this->verifyStatus($sample->no_sample, $parameter);
            if (!$verified) {
                $status = 'belum selesai';
                break;
            }
        }

        return $status;
    }


    private function verifyStatus($sample_number, $parameter)
    {
        if (!$parameter['model'])
            return true;

        $model = $parameter['model'];
        $model2 = $parameter['model2']; // alternate model

        $verified = $model::where('no_sampel', $sample_number);

        if (
            $model == DetailLingkunganHidup::class
            || $model == DetailSenyawaVolatile::class
            || $model == DetailMicrobiologi::class
            || $model == DataLapanganDirectLain::class
        )
            $verified->where('parameter', $parameter['parameter']);

        if ($model == DetailLingkunganHidup::class) { // kalo gda di lingkungan hidup cari di lingkungan kerja
            if($parameter['parameter'] == 'Suhu' || $parameter['parameter'] == 'Kelembapan') {
                $verified = $model::where('no_sampel', $sample_number)->whereNotNull($parameter['parameter'])
                ->first();
                
            } else if ($verified->count() < $parameter['requiredCount']) {
                $verified = $model2::where('no_sampel', $sample_number)
                    ->where('parameter', $parameter['parameter']);
            };
        }else if ($model == DetailLingkunganKerja::class) { 
            if($parameter['parameter'] == 'Suhu' || $parameter['parameter'] == 'Kelembapan') {
                $verified = $model::where('no_sampel', $sample_number)->whereNotNull($parameter['parameter'])
                ->first();
                
            } else if ($verified->count() < $parameter['requiredCount']) {
                $verified = $model2::where('no_sampel', $sample_number)
                    ->where('parameter', $parameter['parameter']);
            }
            ;
        }



        // // development only !!!!!
        // if ($verified->count() < $parameter['requiredCount']) {
        //     dd("
        //         😎 ==== CHECKPOINT ==== 😎

        //         status       : 'belum selesai 
        //         parameter    : " . $parameter['parameter'] . " 
        //         butuh        : " . $parameter['requiredCount'] . "
        //         yg udah      : " . $verified->count() . "
        //         no sample    : $sample_number 
        //         kategori     : " . $parameter['category'] . " 
        //     ");
        // };
        
        // dd($verified->count());
        return $verified->count() >= $parameter['requiredCount'];
    }

    private function getRequiredParameters()
    {
        // gini aja lah pake sub kategori mlh ngawur mls bgt
        return [
            [
                "parameter" => "Air",
                "requiredCount" => 1,
                "category" => "1-Air",
                "model" => DataLapanganAir::class,
                "model2" => null
            ],
            [
                "parameter" => "Debu (P8J)",
                "requiredCount" => 2,
                "category" => "4-Udara",
                "model" => DataLapanganDebuPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "Karbon Hitam (8 jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganDebuPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "PM 10 (Personil)",
                "requiredCount" => 2,
                "category" => "4-Udara",
                "model" => DataLapanganDebuPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "PM 2.5 (Personil)",
                "requiredCount" => 2,
                "category" => "4-Udara",
                "model" => DataLapanganDebuPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "C O",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "Co",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "CO (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "CO (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "CO (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "CO2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "CO2 (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "CO2 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "H2CO",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "HCHO (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "O2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "VOC",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "VOC (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganDirectLain::class,
                "model2" => null
            ],
            [
                "parameter" => "As",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Beban Emisi",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "C O",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Cd",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Cl2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Co",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "CO (P)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "CO2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Cr",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Cu",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Debu",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Debu (P)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Effisiensi Pembakaran",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "H2S",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "HC",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "HCl",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "HF",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Hg",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Mn",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NH3",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NO",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NO2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NO2-Nox (P)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NO-NO2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NOx",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "NOx-NO2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "O2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "O2 (P)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Opasitas",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Partikulat",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Pb",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Sb",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Se",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Sn",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "SO2",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "SO2 (P)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Suhu",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Tekanan Udara",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Tl",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Velocity",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Zn",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "CO (Bensin)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "CO (Gas)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "CO2 (Bensin)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "CO-cor (Bensin)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "HC (Bensin)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "HC (Gas)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "O2 (Bensin)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "Opasitas (Solar)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiKendaraan::class,
                "model2" => null
            ],
            [
                "parameter" => "Ergonomi",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganErgonomi::class,
                "model2" => null
            ],
            [
                "parameter" => "Get. Badan",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaran::class,
                "model2" => null
            ],
            [
                "parameter" => "Get. Bangunan",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaran::class,
                "model2" => null
            ],
            [
                "parameter" => "Get. Bangunan (24J)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DataLapanganGetaran::class,
                "model2" => null
            ],
            [
                "parameter" => "Get. Mesin",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaran::class,
                "model2" => null
            ],
            [
                "parameter" => "Get. Tangan Lengan",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaran::class,
                "model2" => null
            ],
            [
                "parameter" => "Getaran",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaran::class,
                "model2" => null
            ],
            [
                "parameter" => "Getaran (LK) ST",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaranPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "Getaran (LK) TL",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganGetaranPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "K3-KB",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "K3-KFK",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "K3-KFPBP",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "K3-KFS",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "K3-KRU",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "K3-KTRTHK",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "K3-KUV",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => null,
                "model2" => null
            ],
            [
                "parameter" => "IKD (CS)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganIklimDingin::class,
                "model2" => null
            ],
            [
                "parameter" => "Iklim Kerja Dingin (Cold Stress) - 8 Jam",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganIklimDingin::class,
                "model2" => null
            ],
            [
                "parameter" => "ISBB",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganIklimPanas::class,
                "model2" => null
            ],
            [
                "parameter" => "ISBB (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganIklimPanas::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-Combust",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-Debu",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-DMW",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Isokinetik (All)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-Moisture",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-Percent",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-ResTime",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-Traverse",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Iso-Velo",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganIsokinetikHasil::class,
                "model2" => null
            ],
            [
                "parameter" => "Kebisingan",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganKebisingan::class,
                "model2" => null
            ],
            [
                "parameter" => "Kebisingan (24 Jam)",
                "requiredCount" => 7,
                "category" => "4-Udara",
                "model" => DataLapanganKebisingan::class,
                "model2" => null
            ],
            [
                "parameter" => "Kebisingan (8 Jam)",
                "requiredCount" => 8,
                "category" => "4-Udara",
                "model" => DataLapanganKebisingan::class,
                "model2" => null
            ],
            [
                "parameter" => "Kebisingan (P8J)",
                "requiredCount" => 2,
                "category" => "4-Udara",
                "model" => DataLapanganKebisinganPersonal::class,
                "model2" => null
            ],
            [
                "parameter" => "Aluminium (Al)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "As",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Asam Asetat",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Asbestos",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Ba",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Carbon Dust",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Cd",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Cl-",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Cl2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Cl2 (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Cr",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Cu",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Dustfall",
                "requiredCount" => 2,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Dustfall (S)",
                "requiredCount" => 2,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Fe",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Fe (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "H2S",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "H2S (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "H2S (3 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "H2S (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "H2SO4",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "HCl",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "HCl (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "HF",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Hg",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Kelembaban",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Laju Ventilasi",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Laju Ventilasi (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Mn",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NH3",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NH3 (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NH3 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Ni",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NO2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NO2 (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NO2 (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NO2 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "NOx",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "O3",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "O3 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Oil Mist",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Ortho Cresol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Ox",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Passive NO2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Passive SO2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Pb",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Pb (24 Jam)",
                "requiredCount" => 5,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Pb (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Pb (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Pertukaran Udara",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "PM 10 (24 Jam)",
                "requiredCount" => 5,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "PM 2.5 (24 Jam)",
                "requiredCount" => 5,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Sb",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Se",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Silica Crystaline 8 Jam",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Sn",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "SO2",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "SO2 (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "SO2 (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "SO2 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Suhu",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "TSP",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "TSP (24 Jam)",
                "requiredCount" => 5,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "TSP (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "TSP (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Zn",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class
            ],
            [
                "parameter" => "Gelombang Elektro",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganMedanLM::class,
                "model2" => null
            ],
            [
                "parameter" => "Medan Listrik",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganMedanLM::class,
                "model2" => null
            ],
            [
                "parameter" => "Medan Magnit Statis",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganMedanLM::class,
                "model2" => null
            ],
            [
                "parameter" => "Power Density",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganMedanLM::class,
                "model2" => null
            ],
            [
                "parameter" => "Bacterial Counts",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "E.Coli",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "E.Coli (KB)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "Fungal Counts",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "Jumlah Bakteri Total",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "T. Bakteri (1 Jam)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "T. Bakteri (KUDR - 8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "T. Jamur (1 Jam)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "T. Jamur (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "T. Jamur (KUDR - 8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "T.Bakteri (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "Total Bakteri",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "Total Bakteri (KB)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "Total Coliform",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailMicrobiologi::class,
                "model2" => null
            ],
            [
                "parameter" => "Pencahayaan",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganCahaya::class,
                "model2" => null
            ],
            [
                "parameter" => "Psikologi",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganPsikologi::class,
                "model2" => null
            ],
            [
                "parameter" => "PM 10",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => DataLapanganPartikulatMeter::class
            ],
            [
                "parameter" => "PM 10 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => null
            ],
            [
                "parameter" => "PM 2.5",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => DataLapanganPartikulatMeter::class
            ],
            [
                "parameter" => "PM 2.5 (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => null
            ],
            [
                "parameter" => "Acetone",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Adverse Odor",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Al. Hidrokarbon",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Al. Hidrokarbon (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Alcohol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Alkana Gas",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Asetonitril",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Benzene",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Benzene (8 Jam)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Butanon",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "CH4",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "CH4 (24 Jam)",
                "requiredCount" => 4,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Cyclohexanone",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "EA",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Eter",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Ethanol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Etil Benzene",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Fenol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "HC",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "HC (3 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "HC (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "HC (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "HCNM",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => DetailLingkunganHidup::class
            ],
            [
                "parameter" => "HCNM (3 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => DetailLingkunganHidup::class
            ],
            [
                "parameter" => "HCNM (6 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => DetailLingkunganHidup::class
            ],
            [
                "parameter" => "HCNM (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => DetailLingkunganHidup::class
            ],
            [
                "parameter" => "IPA",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Keton",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Kloroform",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "MEK",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Methacrylates",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Methanol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Metil Merkaptan",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Metil Merkaptan (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Metil Sulfida",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Metil Sulfida (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "MIBK",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "MK",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Naphthalene",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "N-Hexane (Faktor Kimia)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "N-Hexane Personil (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Siklohexane - 8 Jam",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Stirena",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Stirena (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Stirone",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Toluene",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Toluene (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Xylene",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Xylene (8 Jam)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DetailSenyawaVolatile::class,
                "model2" => null
            ],
            [
                "parameter" => "Sinar UV",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSinarUv::class,
                "model2" => null
            ],
            [
                "parameter" => "Bacillus C (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "E.Coli (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Enterobacteriaceae (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Kapang Khamir (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Listeria M (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Pseu Aeruginosa (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "S.Aureus (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Salmonella (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Shigella Sp. (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "T.Coli (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Total Kuman (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "TPC (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Vibrio Ch (Swab Test)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ]
        ];
    }
}
