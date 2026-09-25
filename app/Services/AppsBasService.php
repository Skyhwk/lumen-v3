<?php

namespace App\Services;

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

Carbon::setLocale('id');

class AppsBasService
{
    protected $karyawan;
    protected $user_id;

    public function __construct($karyawan = null, $user_id = null)
    {
        $this->karyawan = $karyawan;
        $this->user_id = $user_id;
    }

    public function index(Request $request)
    {
        // Set limit memory lebih besar secara sementara untuk proses data besar

        ini_set('memory_limit', '512M');
        try {
            // \Illuminate\Support\Facades\Log::info("AppsBasController::index START - User: {$this->karyawan} - Memory: " . (memory_get_usage(true) / 1024 / 1024) . " MB");

            // Filter data untuk hanya mendapatkan data yang memiliki 'sampler' sesuai dengan $this->karyawan
            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)->whereIn('id_jabatan', [41, 42])->exists();
            // $urgentQuotes = [
            //     'ISL/QT/26-I/001678R5','ISL/QT/26-V/009328R1','ISL/QT/26-V/009463','ISL/QT/26-V/009358','ISL/QT/26-V/009324','ISL/QT/26-IV/005704R1','ISL/QT/26-IV/005684R1','ISL/QTC/26-I/000308R5','ISL/QT/26-III/004305R3','ISL/QT/26-V/009373R1','ISL/QT/26-V/008607R1','ISL/QTC/25-XII/002426R1','ISL/QTC/25-XII/002427R1','ISL/QTC/25-XII/002319R1','ISL/QTC/26-II/000408R2','ISL/QTC/26-V/000728R1','ISL/QT/26-V/010207R1','ISL/QTC/26-IV/000592R6','ISL/QT/26-V/010558R4'
            // ];
            $urgentQuotes = [];
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
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1'])

                ->where('is_active', true)
                ->where('kategori_1', '!=', 'SD');

            // --- URGENT HARDCODE EXCEPTION: BYPASS TANGGAL UNTUK QUOTATION TERTENTU ---
            // Kembalikan query database ke awal
            if ($isProgrammer) {
                $orderDetail->whereBetween('tanggal_sampling', [
                    Carbon::now()->subDays(8)->toDateString(),
                    Carbon::now()->toDateString()
                ]);
            } else {
                $orderDetail->whereBetween('tanggal_sampling', [
                    Carbon::now()->subDays(8)->toDateString(),
                    Carbon::now()->toDateString()
                ]);
            }

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
                // Group TANPA field 'sampler' tapi MENGGUNAKAN 'kategori' agar parsial terpisah
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
                } else {
                    // Gabungkan kategori jika berbeda (walaupun dengan key kategori harusnya sama, jaga-jaga format beda)
                    $existingKategori = explode(',', $groupedData[$key]['base_data']['kategori']);
                    $newKategori = explode(',', $item['kategori']);
                    $mergedKategori = array_unique(array_filter(array_merge($existingKategori, $newKategori)));
                    $groupedData[$key]['base_data']['kategori'] = implode(',', $mergedKategori);
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

            // Deteksi jika ada jadwal parsial pada tanggal yang sama dengan sampler yang sama dan periode yang sama
            $samplerCounts = [];
            foreach ($finalResult as $res) {
                $samplerKey = $res['nomor_quotation'] . '|' . $res['periode'] . '|' . $res['jadwal'] . '|' . $res['sampler'];
                if (!isset($samplerCounts[$samplerKey])) {
                    $samplerCounts[$samplerKey] = 0;
                }
                $samplerCounts[$samplerKey]++;
            }

            foreach ($finalResult as &$res) {
                $samplerKey = $res['nomor_quotation'] . '|' . $res['periode'] . '|' . $res['jadwal'] . '|' . $res['sampler'];
                // $res['is_sampler_duplicate'] = $samplerCounts[$samplerKey] > 1;
                $res['is_sampler_duplicate'] = false;
            }
            unset($res);

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
                ->groupBy(function ($item) {
                    return $item->no_order . '_' . $item->tanggal_sampling;
                });

            // Check persiapan by exact order and date match

            // Cek sampel mana yang masih tidak selesai (is_finished = 0).
            // Orphan STS yang sudah ada di bas_sampel_selesai tidak dihitung unfinished,
            // tapi tidak dihapus di sini agar Moment 2 (rewrite) masih terdeteksi di form.
            $completedSamplesList = BasSampelSelesai::whereIn('no_order', $orderNos)
                ->pluck('no_sampel')
                ->unique()
                ->toArray();
            $unfinishedSamplesList = array_values(array_diff(
                \App\Models\SampelTidakSelesai::whereIn('no_order', $orderNos)
                    ->where(function ($query) {
                        $query->where('is_finished', 0)->orWhereNull('is_finished');
                    })
                    ->pluck('no_sampel')
                    ->unique()
                    ->toArray(),
                $completedSamplesList
            ));

            // Add detail_bas_documents to each item
            foreach ($finalResult as &$item) {
                // 1. Ekstrak nomor sampel dari kolom 'kategori' di item ini
                $itemSamples = [];
                $kategoriItems = explode(',', $item['kategori']);
                foreach ($kategoriItems as $katItem) {
                    if (empty(trim($katItem))) continue;
                    $parts = explode('-', $katItem);
                    $nomor = trim(end($parts));
                    $itemSamples[] = $item['no_order'] . '/' . $nomor; // misal: EAED012601/032
                }

                // Cek apakah item (schedule) ini punya sampel yang belum selesai
                $item['has_unfinished_samples'] = count(array_intersect($itemSamples, $unfinishedSamplesList)) > 0;

                $headerList = $persiapanHeadersData->get($item['no_order'] . '_' . $item['jadwal']);
                // --- PERBAIKAN BUG: Cocokkan Persiapan Header berdasarkan no_sampel ---
                $header = null;
                if ($headerList) {

                    // 2. Cari header yang array no_sampel-nya beririsan dengan sampel di item ini
                    foreach ($headerList as $h) {
                        $hSamples = json_decode($h->no_sampel, true);
                        if (is_array($hSamples)) {
                            // Bersihkan hSamples dari prefix untuk pencocokan yang aman
                            $hSamplesClean = array_map(function ($s) {
                                $parts = explode('/', $s);
                                return end($parts);
                            }, $hSamples);

                            // Bersihkan itemSamples dari prefix
                            $itemSamplesClean = array_map(function ($s) {
                                $parts = explode('/', $s);
                                return end($parts);
                            }, $itemSamples);

                            if (count(array_intersect($itemSamplesClean, $hSamplesClean)) > 0) {
                                $header = $h; // Ketemu header yang pas!
                                break;
                            }
                        }
                    }
                }

                $item['id_persiapan'] = $header ? $header->id : null;
                if (isset($header)) {
                    if ($header->detail_bas_documents) {
                        $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true);

                        // Iterasi untuk setiap dokumen
                        foreach ($item['detail_bas_documents'] as $docIndex => $document) {
                            $item['detail_bas_documents'][$docIndex]['email_pending'] = (bool) ($document['email_pending'] ?? false);
                            $item['detail_bas_documents'][$docIndex]['email_sent_at'] = $document['email_sent_at'] ?? null;
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
                    $item['has_persiapan'] = false;
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
                if (isset($item['nomor_quotation']) && in_array($item['nomor_quotation'], $urgentQuotes)) {
                    $filtered[] = $item;
                    continue;
                }
                // --------------------------------------------------------

                $jadwal = Carbon::parse($item['jadwal']);
                $durasi = (int) $item['durasi'];

                if ($durasi <= 1) { // sesaat ato 8jam
                    // if ($jadwal->isSameDay($today))
                        $filtered[] = $item;
                } else {

                    // if ($today->between($jadwal, $endDate))
                    $filtered[] = $item;
                }
            }

            // Catatan: Jika di versi kode asli Anda variabel $filtered ini belum dipakai 
            // menimpa $filteredResult, saya tambahkan ini agar filter array berfungsi
            $filteredResult = $filtered;

            if ($request->has('no_order') && $request->has('tanggal_sampling')) {
                $orderD = OrderDetail::select(
                    'order_detail.*',
                    DB::raw('(SELECT bss.id FROM bas_sampel_selesai bss WHERE bss.no_sampel = order_detail.no_sampel ORDER BY bss.id DESC LIMIT 1) as bas_selesai_id'),
                    DB::raw('(SELECT sts.id FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_id')
                )
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

                        if (!$isSelesai) {
                            if ($item->kategori_2 === "1-Air") {
                                $isSelesai = DataLapanganAir::where('no_sampel', $item->no_sample)->exists();
                            } else if ($item->kategori_3 === "118-Psikologi") {
                                $isSelesai = true;
                            } else {
                                $status_sample = $this->getStatusSampling($item);
                                $isSelesai = ($status_sample === 'parsial' || $status_sample === 'selesai');
                            }
                        }

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
                // Group TANPA field 'sampler' tapi MENGGUNAKAN 'kategori' agar tidak tergabung saat parsial
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
                } else {
                    // Gabungkan kategori jika berbeda
                    $existingKategori = explode(',', $groupedData[$key]['base_data']['kategori']);
                    $newKategori = explode(',', $item['kategori']);
                    $mergedKategori = array_unique(array_filter(array_merge($existingKategori, $newKategori)));
                    $groupedData[$key]['base_data']['kategori'] = implode(',', $mergedKategori);
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

            // Resolve only an unambiguous header containing every requested sample.
            foreach ($finalResult as &$item) {
                $header = BasDocumentScope::resolve([
                    'id_persiapan' => $request->id_persiapan,
                    'no_order' => $item['no_order'],
                    'tanggal_sampling' => $request->tanggal_sampling,
                    'no_sampel' => $kodeList,
                ]);
                $item['id_persiapan'] = $header->id;

                // Block fallback mewariskan dokumen dihapus atas permintaan user

                if ($header) {
                    if ($header->detail_bas_documents) {
                        $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true);

                        // Iterasi untuk setiap dokumen
                        foreach ($item['detail_bas_documents'] as $docIndex => $document) {
                            $item['detail_bas_documents'][$docIndex]['email_pending'] = (bool) ($document['email_pending'] ?? false);
                            $item['detail_bas_documents'][$docIndex]['email_sent_at'] = $document['email_sent_at'] ?? null;
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
                DB::raw('(SELECT bss.id FROM bas_sampel_selesai bss WHERE bss.no_sampel = order_detail.no_sampel ORDER BY bss.id DESC LIMIT 1) as bas_selesai_id'),
                DB::raw('(SELECT sts.id FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_id'),
                DB::raw('(SELECT sts.status FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_status'),
                DB::raw('(SELECT sts.alasan FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_alasan'),
                DB::raw('(SELECT sts.keterangan FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_keterangan'),
                DB::raw('(SELECT sts.kategori FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_kategori'),
                DB::raw('(SELECT sts.tanggal_dilanjutkan FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_tanggal_dilanjutkan'),
                DB::raw('(SELECT sts.no_order FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_no_order'),
                DB::raw('(SELECT sts.created_at FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_created_at'),
                DB::raw('(SELECT sts.created_by FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_created_by'),
                DB::raw('(SELECT sts.updated_at FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.created_at DESC LIMIT 1) as ts_updated_at')
            )
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
                    $isSelesai = BasSampelService::isCompleted($item, fn($sample) => $this->getStatusSampling($sample));

                    $decisionHeader = $filteredResult[0]['id_persiapan'] ?? null;
                    $savedDecision = SampelTidakSelesai::where('no_order', $request->no_order)
                        ->where('no_sampel', $item->no_sampel)->where('id_persiapan', $decisionHeader)
                        ->orderBy('id', 'desc')->first();
                    $dataSampelBelumSelesai = $savedDecision;
                    $detail_sampling_sampel[$key]['status'] = $isSelesai ? 'selesai' : 'belum selesai';
                    $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
                    $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
                    $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
                    $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

                    $detail_sampling_sampel[$key]['id_persiapan'] = $decisionHeader;
                    $detail_sampling_sampel[$key]['status_sampel'] = BasDocumentScope::validDecision($savedDecision, $request->tanggal_sampling);
                    if ($dataSampelBelumSelesai) {
                        $detail_sampling_sampel[$key]['detail_status'] = $dataSampelBelumSelesai;
                    }
                }
                // dd($detail_sampling_sampel);

                // Gabungkan detail_sampling_sampel ke filteredResult
                foreach ($filteredResult as $key => $value) {
                    // Hanya gunakan kategori yang di-request jika ada, jika tidak fallback ke kategori default grup
                    if ($request->has('kategori') && !empty($request->kategori)) {
                        $kategoriItems = is_array($request->kategori) ? $request->kategori : explode(',', $request->kategori);
                    } else {
                        $kategoriItems = explode(',', $value['kategori']);
                    }

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
        DB::beginTransaction();
        try {
            if ($request->has('data') && !empty($request->data)) {
                $errors = [];
                $emailRequired = false;
                foreach ($request->data as $item) {
                    if (!is_array($item) || empty($item['no_quotation'])) {
                        throw new \InvalidArgumentException('Quotation wajib diisi.');
                    }
                    $item['no_sampel'] = BasDocumentScope::samples($item['no_sampel'] ?? null, $item['no_order'] ?? null);

                    // Pastikan $item['no_sampel'] hanya berisi kode (tanpa no_order)
                    if (isset($item['no_sampel']) && is_array($item['no_sampel'])) {
                        $item['no_sampel'] = array_map(function ($s) {
                            $parts = explode('/', $s);
                            return end($parts);
                        }, $item['no_sampel']);
                    }

                    $item['expectedNoSampel'] = array_map(function ($kode) use ($item) {
                        return $item['no_order'] . '/' . $kode;
                    }, $item['no_sampel']);

                    $header = BasDocumentScope::resolve($item, true);
                    $item['id_persiapan'] = $header->id;

                    if ($header) {
                        $isFinal = $request->boolean('is_final');
                        $noteFields = [
                            'catatan' => 'Catatan tambahan',
                            'informasi_teknis' => 'Informasi teknis',
                        ];
                        foreach ($noteFields as $field => $label) {
                            $raw = isset($item[$field]) ? trim((string) $item[$field]) : '';
                            if ($raw === '-') {
                                $raw = '';
                            }
                            if ($raw === '') {
                                if ($isFinal) {
                                    throw new \InvalidArgumentException($label . ' wajib diisi.');
                                }
                                $item[$field] = '';
                                continue;
                            }
                            $noteError = BasDocumentScope::freeTextError($raw, $label);
                            if ($noteError !== '') {
                                throw new \InvalidArgumentException($noteError);
                            }
                            $item[$field] = $raw;
                        }

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

                        // Bersihkan dan sort no_sampel untuk menghindari duplikat tidak terdeteksi
                        $detailData['no_sampel'] = array_values(array_unique($detailData['no_sampel']));
                        sort($detailData['no_sampel']);

                        if (!BasDocumentScope::filename($detailData['filename'])) {
                            throw new \InvalidArgumentException('Filename BAS harus basename PDF yang valid.');
                        }
                        $found = false;
                        foreach ($existingDetails as &$detail) {
                            $sameSamples = !empty($detail['no_sampel']) && BasDocumentScope::samples($detail['no_sampel'], $item['no_order']) === $item['expectedNoSampel'];
                            if (!$sameSamples && ($detail['filename'] ?? null) === $detailData['filename']) {
                                throw new \InvalidArgumentException('Filename sudah digunakan dokumen lain.');
                            }
                            if ($sameSamples) {
                                // Draft diblokir saat email pending; Submit Selesai (Moment 2) boleh rewrite
                                // agar PDF + STS/BSS ikut diperbarui sebelum email.
                                if (
                                    !$request->boolean('is_final')
                                    && !empty($detail['email_pending'])
                                    && empty($detail['email_sent_at'])
                                ) {
                                    throw new \InvalidArgumentException('Kirim email dokumen pending sebelum mengubah BAS.');
                                }
                                if ($request->boolean('is_final')) {
                                    // Rewrite Moment 2: wajib email ulang PDF terbaru.
                                    $detailData['email_pending'] = true;
                                    $detailData['email_sent_at'] = null;
                                } else {
                                    $detailData['email_pending'] = (bool) ($detail['email_pending'] ?? false);
                                    $detailData['email_sent_at'] = $detail['email_sent_at'] ?? null;
                                }
                                $detail = $detailData;
                                $found = true;
                                break;
                            }
                        }
                        unset($detail);

                        if (!$found) {
                            $detailData['email_pending'] = $request->boolean('is_final');
                            $detailData['email_sent_at'] = null;
                            $existingDetails[] = $detailData;
                        }

                        $header->detail_bas_documents = json_encode($existingDetails);
                        $header->save();

                        // Preview regenerates the PDF; do not delete files inside a DB transaction.

                        if ($request->boolean('is_final')) {
                            BasSampelService::processFinalSamples($item, fn($sample) => $this->getStatusSampling($sample));
                            $emailRequired = !empty($detailData['email_pending']) && empty($detailData['email_sent_at']);
                            if ($emailRequired) {
                                $header->is_emailed_bas = 0;
                                $header->emailed_bas_at = null;
                            }
                        }
                        $allSamples = json_decode($header->no_sampel, true);
                        if (is_string($allSamples)) $allSamples = json_decode($allSamples, true);
                        $allSamples = BasDocumentScope::samples($allSamples, $item['no_order']);
                        $completed = BasSampelSelesai::where('no_order', $item['no_order'])
                            ->where('tanggal_sampling', $item['tanggal_sampling'])
                            ->whereIn('no_sampel', $allSamples)->pluck('no_sampel')->all();
                        $header->is_completed = !array_diff($allSamples, $completed) ? 1 : 0;
                        $header->save();
                    }
                }
            }

            if (!empty($errors)) {
                throw new \InvalidArgumentException(implode('; ', array_column($errors, 'message')));
            }
            if (!is_array($request->data) || empty($request->data)) {
                throw new \InvalidArgumentException('Data BAS wajib diisi.');
            }
            DB::commit();
            return response()->json([
                'status' => 'success',
                'email_required' => $emailRequired,
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => $ex->getMessage()], $ex instanceof \InvalidArgumentException ? 422 : 500);
        }
    }


    // Send Email Development
    public function sendEmail(Request $request)
    {
        
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
            if (!is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Recipient email tidak valid.');
            }
            foreach ($ccArray as $address) {
                if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('CC email tidak valid.');
                }
            }
            if (!is_array($attachments) || count($attachments) !== 1 || empty($request->id_persiapan)
                || !BasDocumentScope::date($request->tanggal_sampling) || !$noOrder || !$noDocument) {
                throw new \InvalidArgumentException('Pilih satu dokumen dan header, order, quotation, tanggal yang tepat.');
            }
            $fileName = reset($attachments);
            if (!BasDocumentScope::filename($fileName)) throw new \InvalidArgumentException('Attachment tidak valid.');
            $base = realpath(public_path('dokumen/bas'));
            $path = realpath(public_path('dokumen/bas/' . $fileName));
            if (!$base || !$path || dirname($path) !== $base || !is_file($path) || !is_readable($path)) {
                throw new \InvalidArgumentException('File attachment tidak ditemukan.');
            }
            $persiapanHeader = PersiapanSampelHeader::where('id', $request->id_persiapan)
                ->where('no_order', $noOrder)->where('no_quotation', $noDocument)
                ->where('tanggal_sampling', $request->tanggal_sampling)->where('is_active', true)
                ->lockForUpdate()->first();
            if (!$persiapanHeader) throw new \InvalidArgumentException('Header attachment tidak sesuai.');
            $documents = json_decode($persiapanHeader->detail_bas_documents, true) ?? [];
            $documentKeys = array_keys(array_filter($documents, function ($doc) use ($fileName) {
                return ($doc['filename'] ?? null) === $fileName;
            }));
            if (count($documentKeys) !== 1) throw new \InvalidArgumentException('Attachment bukan dokumen unik pada header ini.');
            $documentKey = $documentKeys[0];
            $document = $documents[$documentKey];
            // if (!empty($document['email_sent_at'])) {
            //     throw new \InvalidArgumentException(
            //         'Email dokumen ini sudah terkirim pada ' . $document['email_sent_at'] . '.'
            //     );
            // }
            // if (empty($document['email_pending'])) {
            //     throw new \InvalidArgumentException('Dokumen belum final. Submit BAS final dulu sebelum kirim email.');
            // }
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
            
            if ($sent === true) {
                $documents[$documentKey]['email_pending'] = false;
                $documents[$documentKey]['email_sent_at'] = Carbon::now()->format('Y-m-d H:i:s');
                $persiapanHeader->detail_bas_documents = json_encode($documents);
                if ($persiapanHeader) {
                    $persiapanHeader->is_emailed_bas = 1;
                    $persiapanHeader->emailed_bas_at = \Carbon\Carbon::now();
                    $persiapanHeader->save();

                    // Insert ke log_bas
                    try {
                        $preview = $this->previewLogBas($persiapanHeader, is_array($attachments) ? $attachments : []);
                        $this->persistLogBas($preview);
                    } catch (\Exception $exLog) {
                        \Illuminate\Support\Facades\Log::error('Failed to insert log_bas: ' . $exLog->getMessage());
                    }
                }

                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'id_persiapan' => $persiapanHeader->id,
                    'filename' => $fileName,
                    'email_pending' => false,
                    'email_sent_at' => $documents[$documentKey]['email_sent_at'],
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
                    'status' => 'error',
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim email: ' . $e->getMessage()
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    public function previewLogBas($persiapanHeader, $attachments = [])
    {
        $attachments = is_array($attachments) ? $attachments : [];

        $decodeJadwalKategori = function ($kat) {
            if (is_string($kat)) {
                $decoded = json_decode($kat, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
                return [];
            }
            return is_array($kat) ? $kat : [];
        };

        $kategoriSampleCode = function ($kategoriItem) {
            $parts = explode('/', (string) $kategoriItem);
            $tail = trim(end($parts));
            $parts = explode(' - ', $tail);
            return trim(end($parts));
        };

        $detailBas = null;
        $details = json_decode($persiapanHeader->detail_bas_documents, true);
        if (!is_array($details)) {
            $details = [];
        }
        foreach ($details as $detail) {
            if (!is_array($detail) || empty($detail['filename'])) {
                continue;
            }
            if (in_array($detail['filename'], $attachments)) {
                $detailBas = $detail;
                break;
            }
        }
        if (!$detailBas) {
            $validDetails = array_values(array_filter($details, function ($detail) {
                return is_array($detail);
            }));
            if (count($validDetails) === 1) {
                $detailBas = $validDetails[0];
            }
        }

        $dataBas = null;
        $noSampelAll = [];
        $noSampelWithOrder = [];
        if ($detailBas) {
            $noSampelAll = is_array($detailBas['no_sampel'] ?? null) ? $detailBas['no_sampel'] : [];

            $noSampelWithOrder = array_map(function ($s) use ($persiapanHeader) {
                return $persiapanHeader->no_order . '/' . $s;
            }, $noSampelAll);

            $noSampelTidakSelesai = \App\Models\SampelTidakSelesai::whereIn('no_sampel', $noSampelWithOrder)
                ->select('no_sampel', 'keterangan', 'status', 'alasan')
                ->get();

            $noSampelSelesaiWithOrder = array_diff($noSampelWithOrder, $noSampelTidakSelesai->pluck('no_sampel')->toArray());

            $dataBas = [
                'no_sampel_tidak_selesai' => $noSampelTidakSelesai->toArray(),
                'no_sampel_selesai' => array_values($noSampelSelesaiWithOrder),
                'catatan' => $detailBas['catatan'] ?? null,
                'informasi_teknis' => $detailBas['informasi_teknis'] ?? null,
                'submit' => $detailBas['bysubmit'] ?? null,
                'tanda_tangan' => $detailBas['tanda_tangan'] ?? null,
            ];
        }

        if (empty($noSampelAll)) {
            $headerSamples = json_decode($persiapanHeader->no_sampel, true);
            if (!is_array($headerSamples)) {
                $headerSamples = [];
            }
            $noSampelAll = array_values(array_filter(array_map(function ($s) {
                $parts = explode('/', (string) $s);
                return trim(end($parts));
            }, $headerSamples)));
            $noSampelWithOrder = array_map(function ($s) use ($persiapanHeader) {
                return $persiapanHeader->no_order . '/' . $s;
            }, $noSampelAll);
        }

        $sampleCodes = array_values(array_filter(array_map(function ($s) use ($kategoriSampleCode) {
            return $kategoriSampleCode($s);
        }, $noSampelAll)));

        $jadwals = \App\Models\Jadwal::where('no_quotation', $persiapanHeader->no_quotation)
            ->where('tanggal', $persiapanHeader->tanggal_sampling)
            ->where('is_active', true)
            ->get();

        $matchingJadwals = $jadwals->filter(function ($j) use ($decodeJadwalKategori, $kategoriSampleCode, $sampleCodes) {
            if (empty($sampleCodes)) {
                return true;
            }
            foreach ($decodeJadwalKategori($j->kategori) as $item) {
                if (in_array($kategoriSampleCode($item), $sampleCodes, true)) {
                    return true;
                }
            }
            return false;
        });

        $jadwalSource = $matchingJadwals->isNotEmpty() ? $matchingJadwals : $jadwals;
        $jadwal = $jadwalSource->sortByDesc(function ($j) {
            return $j->updated_at ?? $j->created_at;
        })->first();

        $allKategori = [];
        $allSamplers = [];
        foreach ($jadwalSource as $j) {
            if ($j->sampler && !in_array($j->sampler, $allSamplers)) {
                $allSamplers[] = $j->sampler;
            }
            $allKategori = array_merge($allKategori, $decodeJadwalKategori($j->kategori));
        }

        $kategori = [];
        if (!empty($sampleCodes)) {
            foreach ($sampleCodes as $code) {
                foreach ($allKategori as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }
                    if ($kategoriSampleCode($item) === $code && !in_array($item, $kategori, true)) {
                        $kategori[] = $item;
                        break;
                    }
                }
            }
        } else {
            $kategori = array_values(array_unique(array_filter($allKategori)));
        }
        $kategori = !empty($kategori) ? array_values($kategori) : null;

        $ttdSamplers = [];
        if ($detailBas && !empty($detailBas['tanda_tangan']) && is_array($detailBas['tanda_tangan'])) {
            foreach ($detailBas['tanda_tangan'] as $ttd) {
                if (($ttd['role'] ?? '') !== 'sampler') {
                    continue;
                }
                $nama = trim((string) ($ttd['nama'] ?? ''));
                if ($nama !== '' && !in_array($nama, $ttdSamplers, true)) {
                    $ttdSamplers[] = $nama;
                }
            }
        }

        if (!empty($persiapanHeader->sampler_jadwal)) {
            $samplerString = $persiapanHeader->sampler_jadwal;
        } elseif (!empty($ttdSamplers)) {
            $samplerString = implode(',', $ttdSamplers);
        } elseif (!empty($allSamplers)) {
            $samplerString = implode(',', $allSamplers);
        } else {
            $samplerString = null;
        }

        $durasiMap = [
            '0' => 'Sesaat',
            '1' => '8 Jam',
            '2' => '1x24 Jam',
            '3' => '2x24 Jam',
            '4' => '3x24 Jam',
            '5' => '4x24 Jam',
            '6' => '5x24 Jam',
            '7' => '6x24 Jam',
            '8' => '7x24 Jam',
            '9' => '8x24 Jam',
        ];

        $durasiString = null;
        if ($jadwal && isset($jadwal->durasi) && isset($durasiMap[(string) $jadwal->durasi])) {
            $durasiString = $durasiMap[(string) $jadwal->durasi];
        }

        $salesPenanggungJawab = null;
        if (strpos($persiapanHeader->no_quotation, 'ISL/QTC/') !== false) {
            $qtc = \App\Models\QuotationKontrakH::where('no_document', $persiapanHeader->no_quotation)->with('sales')->first();
            $salesPenanggungJawab = $qtc && $qtc->sales ? $qtc->sales->nama_lengkap : null;
        } else {
            $qt = \App\Models\QuotationNonKontrak::where('no_document', $persiapanHeader->no_quotation)->with('sales')->first();
            $salesPenanggungJawab = $qt && $qt->sales ? $qt->sales->nama_lengkap : null;
        }

        $filenameCs = null;
        $csDocs = json_decode($persiapanHeader->detail_cs_documents, true);
        if (is_array($csDocs)) {
            $firstCs = isset($csDocs[0]) && is_array($csDocs[0]) ? $csDocs[0] : (isset($csDocs['filename_cs']) ? $csDocs : null);
            if (is_array($firstCs)) {
                $filenameCs = $firstCs['filename_cs'] ?? null;
            }
        }

        $noStps = str_replace('ISL/PS/', 'ISL/STPS/', $persiapanHeader->no_document);
        $filenameStps = str_replace('/', '-', $noStps) . '.pdf';
        $pathStps = public_path('stps/' . $filenameStps);
        if (!file_exists($pathStps)) {
            $filenameStps = null;
        }

        $payload = [
            'periode' => $persiapanHeader->periode,
            'no_quotation' => $persiapanHeader->no_quotation,
            'no_order' => $persiapanHeader->no_order,
            'sales_penanggung_jawab' => $salesPenanggungJawab,
            'tanggal_tugas' => $persiapanHeader->tanggal_sampling,
            'durasi' => $durasiString,
            'sampler' => $samplerString,
            'kategori' => $kategori,
            'admin_jadwal' => $jadwal ? (!empty($jadwal->updated_by) ? $jadwal->updated_by : $jadwal->created_by) : null,
            'tanggal_dijadwalkan' => $jadwal ? (!empty($jadwal->updated_at) ? $jadwal->updated_at : $jadwal->created_at) : null,
            'admin_persiapan' => $persiapanHeader->created_by,
            'tanggal_persiapan' => $persiapanHeader->created_at,
            'no_persiapan' => $persiapanHeader->no_document,
            'filename_persiapan' => $persiapanHeader->filename,
            'no_stps' => $noStps,
            'filename_stps' => $filenameStps,
            'no_cs' => str_replace('ISL/PS/', 'ISL/CS/', $persiapanHeader->no_document),
            'filename_cs' => $filenameCs,
            'no_bas' => str_replace('ISL/PS/', 'ISL/BAS/', $persiapanHeader->no_document),
            'filename_bas' => $detailBas ? ($detailBas['filename'] ?? null) : null,
            'data_bas' => $dataBas,
            'no_sampel' => !empty($noSampelWithOrder) ? $noSampelWithOrder : null,
            'is_completed' => (int) ($persiapanHeader->is_completed ?? 0),
        ];

        $daysToAdd = 0;
        if ($jadwal && isset($jadwal->durasi)) {
            $durasiVal = (int) $jadwal->durasi;
            if ($durasiVal >= 2 && $durasiVal <= 9) {
                $daysToAdd = $durasiVal - 1;
            }
        }

        $tanggalSamplingStart = \Carbon\Carbon::parse($persiapanHeader->tanggal_sampling)->startOfDay();
        $deadline = $tanggalSamplingStart->copy()->addDays($daysToAdd)->endOfDay();
        $now = \Carbon\Carbon::now();

        $logMessage = null;
        if ($now->lessThan($tanggalSamplingStart)) {
            $logMessage = 'waktu mendahului';
        } elseif ($now->greaterThan($deadline)) {
            $logMessage = 'email lewat batas waktu';
        }

        $kategoriCodes = array_values(array_filter(array_map($kategoriSampleCode, $kategori ?? [])));
        $missingKategori = array_values(array_diff($sampleCodes, $kategoriCodes));
        $extraKategori = array_values(array_diff($kategoriCodes, $sampleCodes));

        return [
            'payload' => $payload,
            'window' => [
                'log_message' => $logMessage,
                'tanggal_sampling_start' => $tanggalSamplingStart->toDateTimeString(),
                'deadline' => $deadline->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'target' => $logMessage ? 'sampling_channel' : 'log_bas',
            ],
            'kelayakan' => [
                'kategori_count' => count($kategori ?? []),
                'no_sampel_count' => count($noSampelWithOrder),
                'count_match' => count($kategori ?? []) === count($noSampelWithOrder),
                'kategori_codes' => $kategoriCodes,
                'no_sampel_codes' => $sampleCodes,
                'missing_kategori_for_sampel' => $missingKategori,
                'extra_kategori' => $extraKategori,
                'sampler_source' => !empty($persiapanHeader->sampler_jadwal) ? 'persiapan.sampler_jadwal' : (!empty($ttdSamplers) ? 'tanda_tangan' : 'jadwal'),
            ],
            'persiapan' => [
                'id' => $persiapanHeader->id,
                'no_document' => $persiapanHeader->no_document,
                'sampler_jadwal' => $persiapanHeader->sampler_jadwal,
                'filename_bas' => $payload['filename_bas'],
            ],
        ];
    }

    public function persistLogBas(array $preview)
    {
        $payload = $preview['payload'] ?? null;
        if (empty($payload)) {
            return [
                'saved' => false,
                'target' => null,
                'reason' => 'payload kosong',
            ];
        }

        $logMessage = $preview['window']['log_message'] ?? null;
        if ($logMessage) {
            $samplerName = $payload['sampler'] ?? 'Unknown Sampler';
            \Illuminate\Support\Facades\Log::channel('sampling')->info("emailbas - [$logMessage] - Sampler: $samplerName - " . json_encode($payload));
            return [
                'saved' => false,
                'target' => 'sampling_channel',
                'log_message' => $logMessage,
            ];
        }

        $row = \App\Models\LogBas::updateOrCreate(
            [
                'no_order' => $payload['no_order'] ?? null,
                'no_bas' => $payload['no_bas'] ?? null,
            ],
            $payload
        );

        return [
            'saved' => true,
            'target' => 'log_bas',
            'id' => $row ? $row->id : null,
        ];
    }

    public function preview(Request $request)
    {
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
            if ($request->has('no_sampel') && is_array($request->no_sampel)) {
                $noSample = BasDocumentScope::samples($request->no_sampel, $request->no_order);
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

            if ($samplerJadwal->isEmpty()) {
                return response()->json([
                    'message' => 'Data jadwal tidak ditemukan.!',
                ], 401);
            }

            // Ambil data order header berdasarkan no_document dan no_order
            $orderH = OrderHeader::where('no_document', $request->no_document)
                ->where('no_order', $request->no_order)
                ->first();

            $expectednoSampel = [];
            $kategoriList = is_array($request['kategori'])
                ? $request['kategori']
                : (strpos($request['kategori'], ',') !== false
                    ? explode(',', $request['kategori'])
                    : [$request['kategori']]);

            foreach ($kategoriList as $kategoriItem) {
                $parts = explode(' - ', trim($kategoriItem));
                $kode = trim(end($parts));
                // Sekarang hanya gunakan kodenya saja agar sesuai dengan yang disimpan di updateData
                $expectednoSampel[] = $kode;
            }

            $persiapanHeader = BasDocumentScope::resolve([
                'id_persiapan' => $request->id_persiapan,
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
                'no_sampel' => $request->no_sampel ?: $expectednoSampel,
            ]);

            if ($persiapanHeader && !empty($persiapanHeader->detail_bas_documents)) {
                $orderH->detail_bas_documents = $persiapanHeader->detail_bas_documents;
            } else {
                $orderH->detail_bas_documents = json_encode([]);
            }

            // Ambil data order detail beserta relasi codingSampling
            // Menggunakan subquery agar tidak duplikasi baris saat LEFT JOIN memiliki multiple match
            $orderD = OrderDetail::with(['codingSampling'])
                ->select(
                    'order_detail.*',
                    DB::raw('(SELECT bss.id FROM bas_sampel_selesai bss WHERE bss.no_sampel = order_detail.no_sampel ORDER BY bss.id DESC LIMIT 1) as bas_selesai_id'),
                    DB::raw('(SELECT bss.created_at FROM bas_sampel_selesai bss WHERE bss.no_sampel = order_detail.no_sampel ORDER BY bss.id DESC LIMIT 1) as bas_selesai_created_at'),
                    DB::raw('(SELECT sts.id FROM sampel_tidak_selesai sts WHERE sts.no_sampel = order_detail.no_sampel ORDER BY sts.id DESC LIMIT 1) as ts_id')
                )
                ->where('order_detail.id_order_header', $orderH->id)
                ->where('order_detail.no_order', $request->no_order)
                ->whereIn('order_detail.no_sampel', $noSample)
                ->whereIn('order_detail.tanggal_sampling', $jadwal)
                ->where('order_detail.is_active', true)
                ->get();

            $status = [];
            $hariTanggal = [];
            $data_sampling = [];
            $dat_param = [];

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
                    if ($vv->kategori_2 === "1-Air") {
                        $exists = DataLapanganAir::where('no_sampel', $vv->no_sampel)->exists();
                        $status[$vv->no_sampel] = $exists ? 'selesai' : 'belum selesai';
                    } else if ($vv->kategori_3 === "118-Psikologi") {
                        $status[$vv->no_sampel] = 'selesai';
                    } else {
                        $status_sample = $this->getStatusSampling($vv);
                        $status[$vv->no_sampel] = ($status_sample === 'parsial' || $status_sample === 'selesai') ? 'selesai' : 'belum selesai';
                    }

                    if ($status[$vv->no_sampel] === 'selesai') {
                        $dataLapangan = $this->getDataLapangan($vv->kategori_2, $vv->kategori_3, $vv->no_sampel, $vv->parameter);
                        if ($dataLapangan && isset($dataLapangan->created_at)) {
                            $hariTanggal[$vv->no_sampel] = $dataLapangan->created_at;
                        } else {
                            $hariTanggal[$vv->no_sampel] = null;
                        }
                    } else {
                        $hariTanggal[$vv->no_sampel] = null;
                    }
                }

                if ($vv->codingSampling) {
                    $dat_param[] = $vv->codingSampling;
                }
            }
            // Gunakan nama file dari request agar sinkron dengan frontend
            $file_name_old = $request->filename_old ?? null;
            $file_name = $request->filename ?? null;
            if ($file_name !== null && !BasDocumentScope::filename($file_name)) {
                throw new \InvalidArgumentException('Filename BAS tidak valid.');
            }
            $previewSamples = BasDocumentScope::samples($request->no_sampel ?: $expectednoSampel, $request->no_order);
            foreach (json_decode($persiapanHeader->detail_bas_documents, true) ?? [] as $doc) {
                if (!empty($doc['no_sampel']) && BasDocumentScope::samples($doc['no_sampel'], $request->no_order) === $previewSamples) {
                    $file_name = $doc['filename'] ?? $file_name;
                    if (!empty($doc['email_pending']) && BasDocumentScope::filename($file_name) && is_file(public_path('dokumen/bas/' . $file_name))) {
                        return response()->json([$file_name], 200);
                    }
                } elseif ($file_name && ($doc['filename'] ?? null) === $file_name) {
                    throw new \InvalidArgumentException('Filename digunakan dokumen lain.');
                }
            }

            $generatedFilename = self::cetakBASPDF($orderH, $data_sampling, $dat_param, $persiapanHeader, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal);

            // Sinkronisasikan mapping record dokumen ke database
            if ($persiapanHeader && $generatedFilename) {
                $existingDocs = json_decode($persiapanHeader->detail_bas_documents, true) ?? [];
                $isMatched = false;
                foreach ($existingDocs as &$doc) {
                    if (!empty($doc['no_sampel']) && BasDocumentScope::samples($doc['no_sampel'], $request->no_order) === $previewSamples) {
                        $doc['filename'] = $generatedFilename;
                        $isMatched = true;
                        break;
                    }
                }
                if (!$isMatched) {
                    $existingDocs[] = [
                        'no_sampel' => $expectednoSampel,
                        'filename' => $generatedFilename,
                        'catatan' => '',
                        'informasi_teknis' => '',
                        'waktu_mulai' => '',
                        'waktu_selesai' => '',
                        'tanda_tangan' => []
                    ];
                }
                $persiapanHeader->detail_bas_documents = json_encode($existingDocs);
                $persiapanHeader->save();
            }

            return response()->json([$generatedFilename], 200);
        } catch (\Exception $e) {
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
                $dataSampelTidakSelesai = \Illuminate\Support\Facades\DB::table('sampel_tidak_selesai')->where('no_sampel', $val->no_sample)->where('no_order', $val->no_order)->orderBy('created_at', 'desc')->first();
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
                        if (!empty($dataSampelTidakSelesai->tanggal_dilanjutkan)) {
                            $c = Carbon::parse($dataSampelTidakSelesai->tanggal_dilanjutkan)->locale('id');
                            $hari2 = $c->translatedFormat('l');
                            $tgl2 = $c->translatedFormat('d F Y');
                            $tanggalHtml = "Hari/Tanggal : {$hari2} / {$tgl2}";
                        } else {
                            $tanggalHtml = "Hari/Tanggal : ....................................";
                        }
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

        // Cukup kembalikan string nama file murni agar dibaca oleh fungsi induknya di atas
        return $filename;
    }

    public function cetakBASPDFWeb($dataHeader, $dataSampling, $dataParam, $dataPersiapan, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal, $lastEntry = null)
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

        if ($lastEntry !== null && is_array($lastEntry)) {
            $selectedDetail = array_merge($selectedDetail, $lastEntry);
        }

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
        $samplingBySampler = [];
        $sampleSamplerMap = [];

        if ($lastEntry !== null) {
            $tandaTanganEntry = $lastEntry['tanda_tangan'] ?? [];
            $semuaSamplerUnik = [];
            foreach ($tandaTanganEntry as $ttd) {
                if (isset($ttd['role']) && $ttd['role'] === 'sampler' && !empty($ttd['nama'])) {
                    $nama = trim($ttd['nama']);
                    if ($nama !== '' && !in_array($nama, $semuaSamplerUnik)) {
                        $semuaSamplerUnik[] = $nama;
                    }
                }
            }
            if (empty($semuaSamplerUnik)) {
                $semuaSamplerUnik = ['Petugas Sampler'];
            }

            foreach ($dataSampling as $sampling) {
                $sampleSamplerMap[$sampling->no_sample] = $semuaSamplerUnik;
            }

            $samplingBySampler = [
                implode(', ', $semuaSamplerUnik) => $dataSampling,
            ];
        } else {
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
        }

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
                $dataSampelTidakSelesai = \Illuminate\Support\Facades\DB::table('sampel_tidak_selesai')->where('no_sampel', $val->no_sample)->where('no_order', $val->no_order)->orderBy('created_at', 'desc')->first();
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
                        if (!empty($dataSampelTidakSelesai->tanggal_dilanjutkan)) {
                            $c = Carbon::parse($dataSampelTidakSelesai->tanggal_dilanjutkan)->locale('id');
                            $hari2 = $c->translatedFormat('l');
                            $tgl2 = $c->translatedFormat('d F Y');
                            $tanggalHtml = "Hari/Tanggal : {$hari2} / {$tgl2}";
                        } else {
                            $tanggalHtml = "Hari/Tanggal : ....................................";
                        }
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

        // Cukup kembalikan string nama file murni agar dibaca oleh fungsi induknya di atas
        return $filename;
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

    public function downloadBasFile($filename)
    {
        $path = public_path('dokumen/bas/' . str_replace('..', '', $filename));

        if (!file_exists($path)) {
            return response()->json(['message' => 'File tidak ditemukan di storage server.'], 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
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
        } else if ($kategori_2 === "4-Udara" && $kategori_3 === "118-Psikologi") {
            $data = DataLapanganPsikologi::where('no_sampel', $no_sample)->get();
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
        if (empty($request->status)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status sampel wajib diisi',
            ], 422);
        }

        if ($request->status === 'Belum Selesai' && empty($request->alasan) && empty($request->keterangan)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alasan wajib diisi jika status Belum Selesai',
            ], 422);
        }

        if ($request->status === 'Belum Selesai' && $request->alasan === 'Lainnya') {
            $lainnyaError = BasDocumentScope::lainnyaKeteranganError($request->keterangan);
            if ($lainnyaError !== '') {
                return response()->json([
                    'status' => 'error',
                    'message' => $lainnyaError,
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            if (!in_array($request->status, ['Dilanjutkan', 'Belum Selesai'], true)) {
                throw new \InvalidArgumentException('Status sampel tidak valid.');
            }
            $scope = $request->all();
            $scope['no_sampel'] = [$request->no_sampel];
            $header = BasDocumentScope::resolve($scope, true);
            $id_persiapan = $header->id;
            $noSampel = BasDocumentScope::samples($scope['no_sampel'], $request->no_order)[0];
            if (!BasDocumentScope::validDecision((object) $request->all(), $request->tanggal_sampling)) {
                throw new \InvalidArgumentException('Alasan wajib diisi; Lainnya wajib keterangan. Jika tanggal dilanjutkan diisi, harus valid dan tidak sebelum sampling.');
            }
            foreach (json_decode($header->detail_bas_documents, true) ?? [] as $doc) {
                if (!empty($doc['email_pending']) && in_array($noSampel, BasDocumentScope::samples($doc['no_sampel'], $request->no_order), true)) {
                    throw new \InvalidArgumentException('Keputusan dokumen pending tidak dapat diubah sebelum email dikirim.');
                }
            }

            SampelTidakSelesai::updateOrCreate(
                ['no_sampel' => $noSampel, 'no_order' => $request->no_order, 'id_persiapan' => $id_persiapan],
                [
                    'no_order' => $request->no_order,
                    'id_persiapan' => $id_persiapan,
                    'kategori' => $request->kategori ?? null,
                    'keterangan' => ($request->status === 'Dilanjutkan') ? null : ($request->keterangan ?? null),
                    'status' => $request->status ?? null,
                    'alasan' => ($request->status === 'Dilanjutkan') ? null : ($request->alasan ?? null),
                    'tanggal_dilanjutkan' => ($request->status === 'Belum Selesai') ? null : (trim((string) ($request->tanggal_dilanjutkan ?? '')) ?: null),
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
            ], $th instanceof \InvalidArgumentException ? 422 : 500);
        }
    }



    private function ulkHygieneK3Names()
    {
        return ['K3-KB', 'K3-KFK', 'K3-KFPBP', 'K3-KFS', 'K3-KRU', 'K3-KTRTHK'];
    }

    private function isUlkHygieneK3Parameter($item, $kategori3)
    {
        if ($kategori3 !== '27-Udara Lingkungan Kerja') {
            return false;
        }

        $tokens = ['570;K3-KB', '571;K3-KFK', '574;K3-KFPBP', '573;K3-KFS', '575;K3-KRU', '572;K3-KTRTHK'];
        if (in_array($item, $tokens, true)) {
            return true;
        }

        $name = explode(';', (string) $item)[1] ?? (string) $item;
        return in_array($name, $this->ulkHygieneK3Names(), true);
    }

    private function isUlkHygieneK3Completed($noSampel)
    {
        return DataLapanganLingkunganKerja::where('no_sampel', $noSampel)->exists()
            || DataLapanganPartikulatMeter::where('no_sampel', $noSampel)->exists();
    }

    private function getStatusSampling($sample)
    {
        try {
            $parametersRaw = json_decode($sample->parameter);
            if (!is_array($parametersRaw)) {
                $parametersRaw = [];
            }

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

            $hasK3Hygiene = false;
            $parameters = array_reduce($parametersRaw, function ($carry, $item) use ($sample, $requiredParameters, &$hasK3Hygiene) {
                $parameterName = explode(";", $item)[1] ?? null;

                if (!$parameterName) {
                    return $carry;
                }

                if ($this->isUlkHygieneK3Parameter($item, $sample->kategori_3)) {
                    $hasK3Hygiene = true;
                    return $carry;
                }

                $matchedParameter = $requiredParameters
                    ->where('parameter', $parameterName)
                    ->first();

                if ($matchedParameter == null) {
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

            if ($hasK3Hygiene && empty($parameters)) {
                $sampleNumber = $sample->no_sampel ?? $sample->no_sample;
                return $this->isUlkHygieneK3Completed($sampleNumber) ? 'selesai' : 'belum selesai';
            }

            $status = 'selesai';
            if (!empty($parameters)) {
                $parameterBypass = ['Gelombang Elektro', 'N-Propil Asetat (SC)', 'Xylene secara personil sampling (SC)', 'Psikologi'];

                foreach ($parameters as $parameter) {
                    $paramName = $parameter['parameter']; // Ambil nama parameter untuk mempermudah pengecekan

                    if ($parameter['category'] == '6-Padatan') {
                        continue;
                    }

                    if (in_array($paramName, $parameterBypass)) {
                        continue;
                    }

                    $sampleNumber = $sample->no_sampel ?? $sample->no_sample;

                    if ($sampleNumber == 'ITEM012501/015' && in_array($paramName, ['NO2 (24 Jam)', 'PM 10 (24 Jam)', 'PM 2.5 (24 Jam)'])) {
                        continue;
                    }

                    if (in_array($sampleNumber, ['BUIL022603/12', 'BUIL022603/14', 'BUIL022603/15', 'BUIL022603/16', 'BUIL022603/008'])) {
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

                    $verified = $this->verifyStatus($sampleNumber, $parameter);

                    if (!$verified) {
                        $status = 'belum selesai';
                        break;
                    }
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

            // --- HARDCODE KHUSUS LINGKUNGAN KERJA & PARTIKULAT METER ---
            if (in_array($paramName, ['PM 10 (24 Jam)', 'PM 2.5 (24 Jam)'])) {
                $sampleData = \App\Models\OrderDetail::where('no_sampel', $sample_number)->first();
                if ($sampleData && stripos($sampleData->kategori_3, 'Lingkungan Kerja') !== false) {
                    $parameter['requiredCount'] = 4; // Timpa langsung di array
                }
            }

            $requiredCount = isset($parameter['requiredCount']) ? (int) $parameter['requiredCount'] : 1;

            $environmentModels = [
                DetailLingkunganHidup::class,
                DetailLingkunganKerja::class,
                DetailMicrobiologi::class,
            ];

            if (in_array($model, $environmentModels, true)) {
                return $this->handleEnvironmentModel($sample_number, $parameter, $model, $model2, $model3);
            }

            // non-environment: hanya kembalikan builder jika count >= requiredCount, else null
            $query = $model::where('no_sampel', $sample_number);
            // if (\Illuminate\Support\Facades\Schema::hasColumn((new $model)->getTable(), 'is_blocked')) {
            //     $query->where('is_blocked', 0);
            // }
            // if (\Illuminate\Support\Facades\Schema::hasColumn((new $model)->getTable(), 'is_rejected')) {
            //     $query->where('is_rejected', 0);
            // }

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

        $hasPMParameter = in_array($paramName, ['PM 10 (24 Jam)', 'PM 2.5 (24 Jam)', 'Kelembaban', 'Suhu'], true);
        if (!$hasPMParameter) {
            $model3 = null;
        }

        if ($model3 === null || $model3 === 'App\Models\DetailMicrobiologi') {
            return $this->handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2, $model3);
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
            "Al",
            "Sb",
            "Ag",
            "As",
            "Ba",
            "Fe",
            "B",
            "Cd",
            "Ca",
            "Co",
            "Mn",
            "Na",
            "Ni",
            "Hg",
            "Se",
            "Zn",
            "Tl",
            "Cu",
            "Sn",
            "Pb",
            "Ti",
            "Cr",
            "V",
            "F",
            "NO2",
            "Cr6+",
            "Mo",
            "NO3",
            "CN",
            "Sulfida",
            "Cl-",
            "OG",
            "Chloride",
            "E.Coli (MM)",
            "Salmonella (MM)",
            "Shigella Sp. (MM)",
            "Vibrio Ch (MM)",
            "S.Aureus"
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
