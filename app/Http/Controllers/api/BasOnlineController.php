<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

use Illuminate\Support\Collection; // ++ Abu

use App\Models\PersiapanSampelDetail;
use App\Models\MasterKaryawan;

use App\Models\SamplingPlan;
use App\Models\Jadwal;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\PersiapanSampelHeader;
use Carbon\Carbon;
use Mpdf\Mpdf;
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
use DateTime;
use Exception;

class BasOnlineController extends Controller
{
    public function index(Request $request)
    {
       try {

            $existingWork = DB::table('persiapan_sampel_header')
            ->select('no_order', 'tanggal_sampling', 'sampler_jadwal', 'is_downloaded', 'is_printed')
            ->where('is_active', true)
            ->whereNotNull('detail_bas_documents')
            ->whereBetween('tanggal_sampling', [$request->periode_awal, $request->periode_akhir])
            ->get();
            $doneList = [];
            // LOOPING PERTAMA: Membangun Daftar Orang yang Sudah Selesai
            foreach ($existingWork as $row) {
                // PENTING: Pecah nama di sini juga! 
                $headerSamplers = explode(',', $row->sampler_jadwal ?? '');
                foreach ($headerSamplers as $name) {
                    $cleanName = strtolower(trim($name));
                    if (empty($cleanName)) continue;
                    // Kuncinya: Order + Tanggal + Nama Orang
                    $key = sprintf('%s|%s|%s', 
                        trim($row->no_order), 
                        trim($row->tanggal_sampling), 
                        $cleanName
                    );
                    $doneList[$key] =[
                        'is_proccess' => true,
                        'is_downloaded' => $row->is_downloaded,
                        'is_printed' => $row->is_printed
                    ];
                }
            }
            // 1. Ambil Data (Eager Loading Optimized)
            $myPrivileges = $this->privilageCabang; // Contoh: ["1", "4"] atau ["4"]
            $isOrangPusat = in_array("1", $myPrivileges);
            $query =OrderDetail::query();
            $data = $query->with([
                'orderHeader' => function ($q) {
                    $q->select([
                        'id', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'no_document', 
                        'alamat_sampling', 'nama_pic_order', 'nama_pic_sampling', 
                        'no_tlp_pic_sampling', 'jabatan_pic_sampling', 'jabatan_pic_order', 'is_revisi'
                    ]);
                },
                'orderHeader.samplingPlan' => function ($q) {
                    $q->select(['id', 'periode_kontrak', 'quotation_id', 'status_quotation', 'is_active'])
                    ->where('is_active', true); // Pastikan plan aktif
                },
                'orderHeader.samplingPlan.jadwal' => function ($q) use ($isOrangPusat, $myPrivileges) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        // Group Concat sampler di level database agar array PHP lebih ringan
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                    if (!$isOrangPusat) {
                        $q->whereIn('id_cabang', $myPrivileges);
                    }
                }
            ])
            ->select(['id_order_header', 'no_order', 'kategori_1', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [$request->periode_awal, $request->periode_akhir])
            ->get(); // Hati-hati, load semua ke memori

            // 2. Mapping Manual (High Performance PHP Array)
            $cabangMap = [
                1 => 'HEAD OFFICE',
                4 => 'RO-KARAWANG',
                5 => 'RO-PEMALANG'
            ];

            $groupedData = [];

            foreach ($data as $item) {
                // Early exit jika relasi tidak lengkap
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {continue;}
                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                $targetPlan = null;
                // Prioritas 1: Cari yang periodenya COCOK
                if ($periode) {
                    $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
                }
                
                // Prioritas 2: Jika tidak ada periode spesifik, atau tidak ketemu, ambil yang pertama
                if (!$targetPlan) {
                    $targetPlan = $orderHeader->sampling->first();
                }

                // Validasi keberadaan jadwal
                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                    continue;
                }

                // Cache info JSON untuk efisiensi
                $infoPendukung = json_encode([
                    'nama_pic_order'       => $orderHeader->nama_pic_order,
                    'nama_pic_sampling'    => $orderHeader->nama_pic_sampling,
                    'no_tlp_pic_sampling'  => $orderHeader->no_tlp_pic_sampling,
                    'jabatan_pic_sampling' => $orderHeader->jabatan_pic_sampling,
                    'jabatan_pic_order'    => $orderHeader->jabatan_pic_order
                ]);

                $infoSampling = json_encode([
                    'id_request'       => $targetPlan->quotation_id,
                    'status_quotation' => $targetPlan->status_quotation,
                    'id_sp' => $targetPlan->id
                ]);

                // Loop Jadwal
                foreach ($targetPlan->jadwal as $schedule) {
                    // Strict check: Tanggal jadwal HARUS sama dengan tanggal sampling di OrderDetail
                    if (!$isOrangPusat && !in_array($schedule->id_cabang, $this->privilageCabang)) {
                        continue; 
                    }
                    if ($schedule->tanggal !== $item->tanggal_sampling) {
                        continue;
                    }
                     // LOGIKA FILTER DETIL (ATOMIC CHECK)
                    // 2. Cek Satu Per Satu (ABSENSI)
                    $currentSamplers = explode(',', $schedule->sampler ?? '');
                    $pendingSamplers = [];
                    $statusRow =[
                        "is_process" =>0,
                        "is_downloaded" =>0,
                        'is_printed' =>0
                    ];
                    foreach ($currentSamplers as $singleSampler) {
                        $cleanTargetName = strtolower(trim($singleSampler));
                        if (empty($cleanTargetName)) continue;

                        $checkKey = sprintf('%s|%s|%s', 
                            trim($item->no_order), 
                            trim($schedule->tanggal), 
                            $cleanTargetName
                        );

                        // Logic: Jika TIDAK ADA di doneList, berarti dia BELUM selesai -> Masukkan ke pending
                        if (isset($doneList[$checkKey])) {
                            $pendingSamplers[] = trim($singleSampler);
                            $dataDb =$doneList[$checkKey];
                            $statusRow['is_downloaded']    = $dataDb['is_downloaded']; 
                            $statusRow['is_printed'] = $dataDb['is_printed'];
                        }
                    }
                    // 3. Keputusan Akhir untuk Row Ini
                    // Jika pending kosong, berarti SEMUA orang di jadwal ini sudah selesai -> HILANGKAN ROW
                    if (empty($pendingSamplers)) {
                        continue; 
                    }

                    // 4. Update Tampilan Sampler
                    // Jika aslinya 3 orang, tapi "Adji" sudah selesai, maka implode ulang sisa 2 orang saja.
                    // Sehingga nanti pas di Grouping, yang muncul hanya yang belum selesai.
                    $schedule->sampler = implode(',', $pendingSamplers);

                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $namaCabang = $cabangMap[$schedule->id_cabang] ?? 'HEAD OFFICE (Default)';

                    // Key Unik untuk Grouping (Composite Key)
                    $key = $orderHeader->no_document . '|' . 
                        $item->no_order . '|' . 
                        $schedule->tanggal . '|' . 
                        $schedule->jam_mulai . '|' .
                        $kategori; // Key dipersingkat agar hash lebih cepat

                    if (isset($groupedData[$key])) {
                        // Jika data sudah ada, gabungkan Sampler-nya saja
                        $existingSamplers = explode(',', $groupedData[$key]['sampler']);
                        $newSamplers = explode(',', $schedule->sampler ?? '');
                        
                        // Merge & Unique
                        $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                        $groupedData[$key]['sampler'] = implode(',', array_filter($merged));
                    } else {
                        // Data Baru
                        $groupedData[$key] = [
                            'nomor_quotation'    => $orderHeader->no_document ?? '',
                            'nama_perusahaan'    => $orderHeader->nama_perusahaan ?? '',
                            'status_sampling'    => $item->kategori_1 ?? '',
                            'periode'            => $periode,
                            'jadwal'             => $schedule->tanggal,
                            'kategori'           => $kategori,
                            'sampler'            => $schedule->sampler ?? '',
                            'no_order'           => $item->no_order ?? '',
                            'alamat_sampling'    => $orderHeader->alamat_sampling ?? '',
                            'konsultan'          => $orderHeader->konsultan ?? '',
                            'info_pendukung'     => $infoPendukung,
                            'jadwal_jam_mulai'   => $schedule->jam_mulai,
                            'jadwal_jam_selesai' => $schedule->jam_selesai,
                            'info_sampling'      => $infoSampling,
                            'is_revisi'          => $orderHeader->is_revisi,
                            'nama_cabang'        => $namaCabang,
                            'is_downloaded'      => (int)$statusRow['is_downloaded'],
                            'is_printed'      => (int)$statusRow['is_printed'],
                        ];
                    }
                }
            }

            // 3. Return ke DataTables (Collection Client Side)
            // Karena data sudah berupa Array, kita bungkus dengan collect()
            return DataTables::of(collect(array_values($groupedData)))
                ->make(true);

        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }

    public function indexRekap (Request $request)
    {
        try {
            
            $existingWork = DB::table('persiapan_sampel_header')
            ->select('no_order', 'tanggal_sampling', 'sampler_jadwal','is_downloaded','is_printed')
            ->where('is_active', true)
            
            ->whereNotNull('detail_bas_documents')
            ->whereBetween('tanggal_sampling', [
                    $request->periode_awal,
                    $request->periode_akhir
                ])
            ->get();
            $doneList = [];
            // LOOPING PERTAMA: Membangun Daftar Orang yang Sudah Selesai
            foreach ($existingWork as $row) {
                // PENTING: Pecah nama di sini juga! 
                $headerSamplers = explode(',', $row->sampler_jadwal ?? '');
                foreach ($headerSamplers as $name) {
                    $cleanName = strtolower(trim($name));
                    if (empty($cleanName)) continue;
                    // Kuncinya: Order + Tanggal + Nama Orang
                    $key = sprintf('%s|%s|%s', 
                        trim($row->no_order), 
                        trim($row->tanggal_sampling), 
                        $cleanName
                    );
                    $doneList[$key] =[
                        'is_proccess' => true,
                        'is_downloaded' => $row->is_downloaded,
                        'is_printed' => $row->is_printed
                    ];
                }
            }
            // 1. Ambil Data (Eager Loading Optimized)
            $myPrivileges = $this->privilageCabang; // Contoh: ["1", "4"] atau ["4"]
            $isOrangPusat = in_array("1", $myPrivileges);
            $query =OrderDetail::query();
            $data = $query->with([
                'orderHeader' => function ($q) {
                    $q->select([
                        'id', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'no_document', 
                        'alamat_sampling', 'nama_pic_order', 'nama_pic_sampling', 
                        'no_tlp_pic_sampling', 'jabatan_pic_sampling', 'jabatan_pic_order', 'is_revisi'
                    ]);
                },
                'orderHeader.samplingPlan' => function ($q) {
                    $q->select(['id', 'periode_kontrak', 'quotation_id', 'status_quotation', 'is_active'])
                    ->where('is_active', true); // Pastikan plan aktif
                },
                'orderHeader.samplingPlan.jadwal' => function ($q) use ($isOrangPusat, $myPrivileges) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        // Group Concat sampler di level database agar array PHP lebih ringan
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                    if (!$isOrangPusat) {
                        $q->whereIn('id_cabang', $myPrivileges);
                    }
                }
            ])
            ->select(['id_order_header', 'no_order', 'kategori_1', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [
                    $request->periode_awal,
                    $request->periode_akhir
                ])
            ->get(); // Hati-hati, load semua ke memori

            // 2. Mapping Manual (High Performance PHP Array)
            $cabangMap = [
                1 => 'HEAD OFFICE',
                4 => 'RO-KARAWANG',
                5 => 'RO-PEMALANG'
            ];

            $groupedData = [];

            foreach ($data as $item) {
                // Early exit jika relasi tidak lengkap
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {continue;}
                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                $targetPlan = null;
                // Prioritas 1: Cari yang periodenya COCOK
                if ($periode) {
                    $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
                }
                
                // Prioritas 2: Jika tidak ada periode spesifik, atau tidak ketemu, ambil yang pertama
                if (!$targetPlan) {
                    $targetPlan = $orderHeader->sampling->first();
                }

                // Validasi keberadaan jadwal
                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                    continue;
                }

                // Cache info JSON untuk efisiensi
                $infoPendukung = json_encode([
                    'nama_pic_order'       => $orderHeader->nama_pic_order,
                    'nama_pic_sampling'    => $orderHeader->nama_pic_sampling,
                    'no_tlp_pic_sampling'  => $orderHeader->no_tlp_pic_sampling,
                    'jabatan_pic_sampling' => $orderHeader->jabatan_pic_sampling,
                    'jabatan_pic_order'    => $orderHeader->jabatan_pic_order
                ]);

                $infoSampling = json_encode([
                    'id_request'       => $targetPlan->quotation_id,
                    'status_quotation' => $targetPlan->status_quotation,
                    'id_sp' => $targetPlan->id
                ]);

                // Loop Jadwal
                foreach ($targetPlan->jadwal as $schedule) {
                    // Strict check: Tanggal jadwal HARUS sama dengan tanggal sampling di OrderDetail
                    if (!$isOrangPusat && !in_array($schedule->id_cabang, $this->privilageCabang)) {
                        continue; 
                    }
                    if ($schedule->tanggal !== $item->tanggal_sampling) {
                        continue;
                    }
                     // LOGIKA FILTER DETIL (ATOMIC CHECK)
                    // 2. Cek Satu Per Satu (ABSENSI)
                    $currentSamplers = explode(',', $schedule->sampler ?? '');
                    $pendingSamplers = [];
                    // Variabel untuk menandai status baris ini
                    $statusRow =[
                        "is_process" =>0,
                        "is_downloaded" =>0,
                        'is_printed' =>0
                    ];
                    foreach ($currentSamplers as $singleSampler) {
                        $cleanTargetName = strtolower(trim($singleSampler));
                        if (empty($cleanTargetName)) continue;

                        $checkKey = sprintf('%s|%s|%s', 
                            trim($item->no_order), 
                            trim($schedule->tanggal), 
                            $cleanTargetName
                        );

                       // CEK STATUS
                        if (isset($doneList[$checkKey])) {
                            $pendingSamplers[] = trim($singleSampler);
                            $dataDb =$doneList[$checkKey];
                            $statusRow['is_downloaded']    = $dataDb['is_downloaded']; 
                            $statusRow['is_printed'] = $dataDb['is_printed'];
                        }
                    }
                    
                    if (empty($pendingSamplers)) {
                        continue; 
                    }

                    // 4. Update Tampilan Sampler
                    // Jika aslinya 3 orang, tapi "Adji" sudah selesai, maka implode ulang sisa 2 orang saja.
                    // Sehingga nanti pas di Grouping, yang muncul hanya yang belum selesai.
                    $schedule->sampler = implode(',', $pendingSamplers);

                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $namaCabang = $cabangMap[$schedule->id_cabang] ?? 'HEAD OFFICE (Default)';

                    // Key Unik untuk Grouping (Composite Key)
                    $key = $orderHeader->no_document . '|' . 
                        $item->no_order . '|' . 
                        $schedule->tanggal . '|' . 
                        $schedule->jam_mulai . '|' .
                        $kategori; // Key dipersingkat agar hash lebih cepat

                    if (isset($groupedData[$key])) {
                        // Jika data sudah ada, gabungkan Sampler-nya saja
                        $existingSamplers = explode(',', $groupedData[$key]['sampler']);
                        $newSamplers = explode(',', $schedule->sampler ?? '');
                        
                        // Merge & Unique
                        $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                        $groupedData[$key]['sampler'] = implode(',', array_filter($merged));
                    } else {
                        // Data Baru
                        $groupedData[$key] = [
                            'nomor_quotation'    => $orderHeader->no_document ?? '',
                            'nama_perusahaan'    => $orderHeader->nama_perusahaan ?? '',
                            'status_sampling'    => $item->kategori_1 ?? '',
                            'periode'            => $periode,
                            'jadwal'             => $schedule->tanggal,
                            'kategori'           => $kategori,
                            'sampler'            => $schedule->sampler ?? '',
                            'no_order'           => $item->no_order ?? '',
                            'alamat_sampling'    => $orderHeader->alamat_sampling ?? '',
                            'konsultan'          => $orderHeader->konsultan ?? '',
                            'info_pendukung'     => $infoPendukung,
                            'jadwal_jam_mulai'   => $schedule->jam_mulai,
                            'jadwal_jam_selesai' => $schedule->jam_selesai,
                            'info_sampling'      => $infoSampling,
                            'is_revisi'          => $orderHeader->is_revisi,
                            'nama_cabang'        => $namaCabang,
                            'is_downloaded'      => (int)$statusRow['is_downloaded'],
                            'is_printed'      => (int)$statusRow['is_printed'],
                        ];
                    }
                }
            }

            // 3. Return ke DataTables (Collection Client Side)
            // Karena data sudah berupa Array, kita bungkus dengan collect()
            return DataTables::of(collect(array_values($groupedData)))
                ->make(true);

        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
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

            // setup data
            $jsonDecode = html_entity_decode($request->info_sampling);
            $infoSampling = json_decode($jsonDecode, true);

            $tipe = explode("/", $request->no_document);

            $request->kategori = explode(",", $request->kategori);

            // get No Sample
            $noSample = [];
            // dd($request->kategori);
            foreach ($request->kategori as $item) {
                $parts = explode(" - ", $item);
                array_push($noSample, $request->no_order . '/' . $parts[1]);
            }


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
            // Kelompokkan data kategori
            $groupedData = [];
            $insample = [];
            $orderH = OrderHeader::where('no_document', $request->no_document)
                ->where('no_order', $request->no_order)->first();
            $orderD = OrderDetail::with(['codingSampling'])
                ->where('id_order_header', $orderH->id)
                ->where('no_order', $request->no_order)
                ->whereIn('no_sampel', $noSample)
                ->whereIn('tanggal_sampling', $jadwal)
                // ->where('is_active', true)

                ->get();

            $tipe = explode("/", $request->no_document);
            $tahun = "20" . explode("-", $tipe[2])[0];

            // Get perdiem data

            if ($tipe[1] == "QT") {
                $perdiem = QuotationNonKontrak::select('perdiem_jumlah_orang', 'jumlah_orang_24jam')->where('no_document', $request->no_document)->first();
            } else if ($tipe[1] == "QTC") {
                $perdiem = QuotationKontrakH::select('perdiem_jumlah_orang', 'jumlah_orang_24jam')->where('no_document', $request->no_document)->first();
            }

            $dataList = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
            ])
                ->get();

            $psHeader = $dataList->first(function ($item) use ($noSample) {
                $no_sampel = json_decode($item->no_sampel, true) ?? [];
                return count(array_intersect($no_sampel, $noSample)) > 0;
            });
            //  dd($psHeader);
            if ($psHeader) {
                $bsDocument = ($psHeader->detail_bas_documents != null && $psHeader->is_emailed_bas) ? json_decode($psHeader->detail_bas_documents) : null;
                if ($bsDocument != null) {
                    $temNosampel = $orderD->pluck('no_sampel')->toArray();
                    $cleanedSamples = array_map(fn($s) => explode('/', $s)[1] ?? null, $temNosampel);
                    $matchedDocument = [];
                    foreach ($bsDocument as $index => $doc) {
                        array_push($matchedDocument, $doc->filename);

                    }
                    $bsDocument = $matchedDocument;

                }
            } else {
                $bsDocument = null;
            }


            // Get order details untuk semua kategori sekaligus
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

                if ($vv->codingSampling) {
                    $dat_param[] = $vv->codingSampling;
                }
            }
           
            $dataPdf = self::cetakBASPDF($orderH, $data_sampling, $dat_param, $bsDocument, $psHeader);
            return $dataPdf;
            // return response()->json([
            //     'data_sampling' => $data_sampling,
            //     'data_param' => $dat_param,
            // ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }

    }

    private function cetakBASPDF($dataHeader, $dataSampling, $dataParam, $bsDocument, $psh)
    {
        try {
          
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
            // $strReplace = Helpers::escapeStr('BAS_' . $data[0]->no_document . '_' . $data[0]->nama_perusahaan);
            // $filename = $strReplace . '.pdf';
            // $filename = str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '.pdf');
            $filename = 'BAS_' . preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '_',
                trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan)
            ) . '.pdf';

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
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            // Set style
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

            $header = '
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td class="custom5"><b>No Dokumen: ' . $noDocument . '</b></td>
                    <td class="custom3" width="40%" ></td>
                    <td class="custom5">No Order: ' . $dataHeader->no_order . '</td>
                </tr>
            </table>
            <div style="height: 40px;"></div>
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;margin-bottom: 40px;">
            <tr>
                <td class="custom3" colspan="2">
                Hari : ............................ Tanggal : ............................
                Bulan : ............................ Tahun : ............................
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
                <td class="custom3" rowspan="2" width="120">Nama Personil :</td>
                <td class="custom3">1. ........................................................................................... (Petugas sampling)</td>
            </tr>
            <tr>
                <td class="custom3">2. ........................................................................................... (Perwakilan Pelanggan / Perusahaan)</td>
            </tr>
            <tr>
                <td class="custom3" colspan="2">Mulai pelaksanaan pekerjaan pukul : .................. : ..................</td>
            </tr>
            <tr>
                <td class="custom3" colspan="2">Berakhir pada pukul : .................. : .................. (hari / tanggal : ............... / .............................................)</td>
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
            // dd(collect($dataSampling)->pluck('kategori_3')->toArray());
            foreach ($dataSampling as $key => $val) {
                $dat = explode("-", $val->kategori_3);
                $dat1 = $dat[0] !== '' ? $dat[1] : "";
                $pdf->WriteHTML('
            <tr>
                <td class="custom" width="10">' . $p++ . '</td>
                <td class="custom" width="120">' . $val->no_sample . '</td>
                <td class="custom" width="80" style="white-space: wrap;">' . $dat1 . '</td>
                <td class="custom" width="80" style="white-space: wrap;">' . $val->keterangan_1 . '</td>
                <td width="210" style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2" style="font-weight: bold; text-decoration: underline;">Selesai </td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Belum selesai / dilanjutkan pada :</td>
                    </tr>
                    <tr>
                    <td colspan="2" class="custom2">Hari /Tanggal : ....................................</td>
                    </tr>
                    <tr>
                    <td colspan="2" class="custom2">Sisa Sampling : ...............................Titik</td>
                    </tr>
                </table>
                </td>
                <td style="border: 1px solid #000000;" width="240">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin: 8px;">
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Dibatalkan oleh pihak pelanggan</td>
                    <td class="custom2">..... Titik</td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Terbatas / kendala waktu / cuaca</td>
                    <td class="custom2">..... Titik</td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td class="custom2">Titik sampling tidak / belum siap</td>
                    <td class="custom2">..... Titik</td>
                    </tr>
                    <tr>
                    <td style="font-size: 20px; font-weight: bold;" width="10">&#9744;</td>
                    <td colspan="2" class="custom2">Lainnya : ...............................................</td>
                    </tr>
                </table>
                </td>
            </tr>
            ');
            }
            $pdf->WriteHTML('</table>');

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
                </table>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="padding: 5px;"></td>
            </tr>
            <tr>
                <td colspan="5" style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px; margin-left: 12px;">
                    <tr>
                    <td style="font-size: 14px; padding-bottom: 13px;">Informasi-Informasi Teknis Yang Berkaitan Dengan Kegiatan Pengujian Selanjutnya : </td>
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
                    <tr>
                    <td style="padding-bottom: 13px;">...........................................................................................................................................................................................................................................</td>
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
                <td style="padding: 8px;"></td>
                <td style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px;">
                    <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold; font-size: 14px;">
                        <span style="text-decoration: underline;">Pihak Yang Menjalankan Kegiatan</span>
                        <span style="font-style: italic;">(Sampler)</span>
                    </td>
                    </tr>
                    <tr>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Nama Lengkap</td>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Tanda Tangan</td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">1. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">2. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">3. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">4. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td style="padding: 8px;"></td>
                    </tr>
                </table>
                </td>
                <td style="padding: 8px;"></td>
                <td style="border: 1px solid #000000;">
                <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; margin-top: 12px;">
                    <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold; font-size: 14px;">
                        <span style="text-decoration: underline;">Pihak Yang Menjalankan Kegiatan</span>
                        <span style="font-style: italic;">(Pelanggan)</span>
                    </td>
                    </tr>
                    <tr>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Nama Lengkap</td>
                    <td colspan="2" style="font-weight: bold; font-size: 14px; text-align: center; padding: 8px;">Tanda Tangan</td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">1. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">2. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">3. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td width="3"></td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;">4. ......................</td>
                    <td width="100" style="border: 1px solid #000000; padding-top: 23px; padding-bottom: 23px; text-align: center;"></td>
                    <td width="3"></td>
                    </tr>
                    <tr>
                    <td style="padding: 8px;"></td>
                    </tr>
                </table>
                </td>
                <td style="padding: 8px;"></td>
            </tr>
            </table>
        </body>
        </html>');
            $pdf->Output(public_path() . '/bas/' . $filename, 'F');
            if ($bsDocument !== null) {
                // dd($bsDocument);
                return response()->json(['status' => true, 'data' => $bsDocument], 200);
            } else {
                // dd([$filename]);
                return response()->json(['status' => false, 'data' => [$filename]], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    public function previewGenerate(Request $request)
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
            $requestSamples = explode(",", $request->kategori);
            $requestSamples = array_map(function ($item) {
                preg_match('/(\d+)$/', trim($item), $matches);
                return $matches[1] ?? null;
            }, $requestSamples);
            $requestSamples = array_filter($requestSamples);

            $persiapanHeaderKategori = PersiapanSampelHeader::where('no_order', $request->no_order)
            ->where('no_quotation', $request->no_document)
            ->where('tanggal_sampling', $request->tanggal_sampling)
            ->where('is_active', true)
            ->where(function ($q) use ($requestSamples) {
                foreach ($requestSamples as $sample) {
                    $q->orWhere('no_sampel', 'like', '%/' . $sample . '%');
                }
            })->first();
            
            if ($persiapanHeaderKategori && $persiapanHeaderKategori->is_emailed_bas == 1) {
                $dataBas = json_decode($persiapanHeaderKategori->detail_bas_documents, true);
                
                return $dataBas[0]["filename"];
            }
        //    if ($persiapanHeaderKategori && $persiapanHeaderKategori->detail_bas_documents != null) {
        //         $dataBas = json_decode($persiapanHeaderKategori->detail_bas_documents, true);
                
        //         return $dataBas[0]["filename"];
        //     }
            // $kategori_request = json_decode($persiapanHeaderKategori->detail_bas_documents)[0]->no_sampel;
            $noSample = json_decode($persiapanHeaderKategori->no_sampel, true);
            

            // Get No Sample
            // $noSample = [];
            // foreach ($kategori_request as $item) {
            //     // $parts = explode(" - ", $item);
            //     array_push($noSample, $request->no_order . '/' . $item);
            // }
          
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

            // Ambil data PersiapanSampelHeader berdasarkan no_quotation kode lama menggunakan no_order
            $dataList = PersiapanSampelHeader::with('psDetail')->where([
                'no_order' => $request->no_order,
                'no_quotation' => $request->no_document,
                'tanggal_sampling' => $request->tanggal_sampling,
                'is_active' =>1,
            ])->get();
           
            
            $persiapanHeader = $dataList->first(function ($item) use ($noSample) {
                $no_sampel = json_decode($item->no_sampel, true) ?? [];
                return count(array_intersect($no_sampel, $noSample)) > 0;
            });
            
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
                // ->where('is_active', true)
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
                
                $dataLapangan = $this->getDataLapangan(
                    $sample->kategori_2,
                    $sample->kategori_3,
                    $sample->no_sample,
                    $sample->parameter
                );
                // dump($sample->no_sample);
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
            $dataPdf = self::cetakBASPDF2($orderH, $data_sampling, $dat_param, $persiapanHeader, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal);
            return $dataPdf;
        } catch (\Exception $e) {
            // dd($e);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }
    
    public function isDownladed (Request $request)
    {
        try {
            $DB = PersiapanSampelHeader::where('no_quotation',$request->nomor_quotation)
            ->where('tanggal_sampling',$request->jadwal)
            ->where('sampler_jadwal',$request->sampler)
            ->where('is_active',true)
            ->first();
            if($DB != NULL){
                $DB->is_downloaded = true;
                $DB->save();
                return response()->json(["message"=>"succes","status"=>true],200);
            }
            
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],500);
        }
    }
    public function isPrinted (Request $request)
    {
        try {
            $DB = PersiapanSampelHeader::where('no_quotation',$request->nomor_quotation)
            ->where('tanggal_sampling',$request->jadwal)
            ->where('sampler_jadwal',$request->sampler)
            ->where('is_active',true)
            ->first();
            if($DB != NULL){
                $DB->is_printed = true;
                $DB->save();
                return response()->json(["message"=>"succes","status"=>true],200);
            }
            
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],500);
        }
    }

    /* private */
    private function cetakBASPDF2($dataHeader, $dataSampling, $dataParam, $dataPersiapan, $file_name_old, $file_name, $samplerJadwal, $status, $hariTanggal)
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
        $filename = $file_name ? $file_name : str_replace(["/", " "], "_", 'BAS_' . trim($dataHeader->no_document) . '_' . trim($dataHeader->nama_perusahaan) . '_' . $microtime . '.pdf');

        $detailDocuments = json_decode($dataHeader->detail_bas_documents, true);

        

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
            // if (is_array($detail['no_sampel']) && !empty($detail['no_sampel'])) {
            //     $detailNoSampelSorted = $detail['no_sampel'];
            //     sort($detailNoSampelSorted);

            //     $requestedSampelsSorted = $requestedSampels;
            //     sort($requestedSampelsSorted);

            //     if ($detailNoSampelSorted === $requestedSampelsSorted) {
            //         $selectedDetail = $detail;
            //         break;
            //     }
            // }

            // if (in_array($detail['no_sampel'], $requestedSampels)) {
                $selectedDetail = $detail;
            //     break;
            // }
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
                } else {
                    $keyFirst = array_key_first($samplerKategoriMap);
                    // $assignedSamplers = $samplerKategoriMap['001'];
                    $assignedSamplers = $samplerKategoriMap[$keyFirst];
                    
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
                        <td class="custom5"><b>No Dokumen: ' . $noDocument . '</b></td>
                        <td class="custom3" width="40%" ></td>
                        <td class="custom5">No Order: ' . $dataHeader->no_order . '</td>
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
                $dataSampelTidakSelesai = SampelTidakSelesai::where('no_sampel', $val->no_sample)->where('no_order', $val->no_order)->first();
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
                        // $c = Carbon::parse($raw)->locale('id');
                        // $hari2 = $c->translatedFormat('l');      // e.g. "Jumat"
                        // $tgl2 = $c->translatedFormat('d F Y');  // e.g. "17 April 2025"
                        $tanggalHtml = "Hari/Tanggal : ....................................";
                        $sisa_sampling = "Sisa Sampling : .................................... Titik";
                    } else {
                        // placeholder jika belum ada
                        $tanggalHtml = "Hari/Tanggal : ....................................";
                        $sisa_sampling = "Sisa Sampling : .................................... Titik";
                    }
                } else {
                    if (isset($dataSampelTidakSelesai) && $dataSampelTidakSelesai->status == "Dilanjutkan") {
                        $c = Carbon::parse($dataSampelTidakSelesai->tanggal_dilanjutkan)->locale('id');
                        $hari2 = $c->translatedFormat('l');      // e.g. "Jumat"
                        $tgl2 = $c->translatedFormat('d F Y');  // e.g. "17 April 2025"
                        $tanggalHtml = "Hari/Tanggal : {$hari2} / {$tgl2}";
                        $sisa_sampling = "Sisa Sampling : 1 Titik";
                    } else {
                        $tanggalHtml = "Hari/Tanggal : ....................................";
                        $sisa_sampling = "Sisa Sampling : 1 Titik";
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
                                <td colspan="2" class="custom2">' . $sisa_sampling . '</td>
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
        // dd($pelanggans,$selectedDetail);
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
    /**
     * Get the status of the sampling process for a given sample.
     *
     * @param mixed $sample The sample to check the status of.
     * @return string The status of the sampling process: 'selesai' or 'belum selesai'.
     */
    private function getStatusSampling($sample) // return selesai / blm selesai
    {
        // dump($sample->no_sample);
        try {
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

                if($matchedParameter == null){
                    throw new Exception("Kemungkinan Parameter.$parameterName. Belum Terdaftar di RequiredParameters Hub IT");
                }
                $carry[] = $matchedParameter;
                return $carry;
            }, []);
            $parameters = array_filter($parameters, function ($param) {
                if($param == null){
                    return false;
                }
                if($param['category'] == '6-Padatan'){
                    return is_array($param);
                }
                return is_array($param) && isset($param['model']);
            });
        

            $status = 'selesai';
            if (!empty($parameters)) {
                foreach ($parameters as $parameter) {
                    // dump($sample->no_sample);
                    if($parameter['category'] == '6-Padatan'){
                        continue; // Skip Padatan
                    }
                    if ($parameter['parameter'] == 'Gelombang Elektro' || $parameter['parameter'] == 'N-Propil Asetat (SC)' || $parameter['parameter'] == 'Xylene secara personil sampling (SC)') {
                        continue; // Skip Gelombang Elektro and N-Propil Asetat (SC)
                    }

                    if($sample->no_sample == 'ITEM012501/015' && $parameter['parameter'] == 'NO2 (24 Jam)'){
                        continue; // Skip NO2 (24 Jam) for sample ITEM012501/015
                    }

                    
                    $verified = $this->verifyStatus($sample->no_sample, $parameter);
                    
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
            //throw $th;
            throw new Exception($th->getMessage());
        }
    }
    private function verifyStatus($sample_number, $parameter)
    {
        
        if (empty($parameter['model'])) {
            return null;
        }
        // dump($sample_number);
        $model = $parameter['model'];
        $model2 = isset($parameter['model2']) ? $parameter['model2'] : null;
        $model3 = isset($parameter['model3']) ? $parameter['model3'] : null;
        $paramName = isset($parameter['parameter']) ? $parameter['parameter'] : null;
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
    }

    private function handleEnvironmentModel($sample_number, $parameter, $model, $model2, $model3)
    {
        
        $paramName = isset($parameter['parameter']) ? $parameter['parameter'] : null;
        $requiredCount = isset($parameter['requiredCount']) ? (int) $parameter['requiredCount'] : 1;
        
        $hasPMParameter = in_array($paramName, ['PM 10 (24 Jam)', 'PM 2.5 (24 Jam)', 'PM 10 (8 Jam)', 'PM 2.5 (8 Jam)','Kelembaban','Suhu'], true);
        if (!$hasPMParameter) {
            $model3 = null;
        }
       
        if ($model3 === null || $model3 === 'App\Models\DetailMicrobiologi' ) {    
           
            return $this->handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2,$model3);
        } else {
            return $this->handlePMParameters($sample_number, $paramName, $requiredCount, $model, $model2, $model3);
        }
    }

    private function handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2,$model3)
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
                    $found = $model2::where('no_sampel', $sample_number)
                        ->whereNotNull($searchColumn)
                        ->first();
                    if ($found) {
                        return $found;
                    }
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
        // gini aja lah pake sub kategori mlh ngawur mls bgt
        $data_parameters = [
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
                "parameter" => "Xylene secara personil sampling (SC)",
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
                "parameter" => "VOC (SC)",
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
                "parameter" => "O2 (ESTB)",
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
                "parameter" => "E. coli (SWAB)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "S. aureus (SWAB)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Salmonella (SWAB)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
                "model2" => null
            ],
            [
                "parameter" => "Shigella sp. (SWAB)",
                "requiredCount" => 3,
                "category" => "4-Udara",
                "model" => DataLapanganSwab::class,
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
                "model2" => DetailLingkunganKerja::class,
                "model3" => DetailMicrobiologi::class
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
                "model2" => DetailLingkunganKerja::class,
                "model3" => DataLapanganPartikulatMeter::class
            ],
            [
                "parameter" => "PM 2.5 (24 Jam)",
                "requiredCount" => 5,
                "category" => "4-Udara",
                "model" => DetailLingkunganHidup::class,
                "model2" => DetailLingkunganKerja::class,
                "model3" => DataLapanganPartikulatMeter::class
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
                "model2" => DetailLingkunganKerja::class,
                "model3" => DetailMicrobiologi::class
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
                "parameter" => "Medan Magnet",
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
                "model2" => DataLapanganPartikulatMeter::class
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
                "model2" => DataLapanganPartikulatMeter::class
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
            ],
            [
                "parameter" => "N-Propil Asetat (SC)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DataLapanganMedanLM::class,
                "model2" => null
            ],
            [
                "parameter" => "Asetaldehid (SC)",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => DetailLingkunganHidup::class
            ],
            [
                "parameter" => "HC (sebagai CH4)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "CH4 (SC)",
                "requiredCount" => 1,
                "category" => "5-Emisi",
                "model" => DataLapanganEmisiCerobong::class,
                "model2" => null
            ],
            [
                "parameter" => "Isopropil Alkohol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => DetailSenyawaVolatile::class
            ],
            [
                "parameter" => "Metanol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => DetailLingkunganHidup::class
            ],
            [
                "parameter" => "Isopropil Alkohol",
                "requiredCount" => 1,
                "category" => "4-Udara",
                "model" => DetailLingkunganKerja::class,
                "model2" => DetailSenyawaVolatile::class
            ]
        ];
        $padatanParam = ["Al","Sb","Ag","As","Ba","Fe","B","Cd","Ca","Co","Mn","Na","Ni","Hg","Se","Zn","Tl","Cu","Sn","Pb","Ti","Cr","V","F","NO2","Cr6+","Mo","NO3","CN","Sulfida","Cl-","OG","Chloride", "E.Coli (MM)", "Salmonella (MM)", "Shigella Sp. (MM)", "Vibrio Ch (MM)", "S.Aureus"];
        foreach ($padatanParam as $key => $value) {
            $data_parameters[] = [
                "parameter" => $value,
                "requiredCount" => 1,
                "category" => "6-Padatan",
                "model" => null,
                "model2" => null
            ];
        }
        return $data_parameters;
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

}