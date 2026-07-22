<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\PersiapanSampelHeader;
use App\Models\QrDocument;
use App\Models\PengesahanDokumenSampling;

use Carbon\Carbon;

use App\Models\Lims\OrderDetail;
use App\Models\Lims\OrderHeader;
use App\Models\Parameter;
use Mpdf;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LimsFppsController extends Controller
{
    private const FPPS_FONT_JUDUL_KATEGORI = '10px';
    private const FPPS_FONT_HEADER_TABEL = '9px';
    private const FPPS_FONT_ISI_TABEL = '9px';
    private const FPPS_KATEGORI_KOMPLEKS = [
        // Sub 1, 2, 3 (Air Limbah / Air Bersih / Air Permukaan, dst) — tabel ±40 baris
        'Air Limbah Domestik', 'Air Limbah Industri', 'Air Limbah', 'Air Limbah Terintegrasi', 'Air Lindi',
        'Air Bersih', 'Air Minum', 'Air Kolam Renang', 'Air Higiene Sanitasi', 'Air Khusus', 'Air Tanah',
        'Air Mata Air', 'Air Reverse Osmosis',
        'Air Permukaan', 'Air Sungai', 'Air Danau', 'Air Waduk', 'Air Situ', 'Air Rawa', 'Air Muara', 'Air Laut',
        // Sub 4 (Udara Ambient) — tabel ±35 baris
        'Udara Ambient',
    ];
    public function index(Request $request)
    {
       try {
            $existingWork = DB::table('persiapan_sampel_header')
            ->select('no_order', 'tanggal_sampling', 'sampler_jadwal','is_downloaded_stps','is_printed_stps')
            ->where('is_active', true)
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
                        'is_downloaded_stps' => $row->is_downloaded_stps,
                        'is_printed_stps' => $row->is_printed_stps
                    ];
                }
            }
            // 1. Ambil Data (Eager Loading Optimized)
            $myPrivileges = $this->privilageCabang; // Contoh: ["1", "4"] atau ["4"]
            $isOrangPusat = in_array("1", $myPrivileges);
            $query =OrderDetail::query();
            if (!$isOrangPusat) {
                $query->whereHas('orderHeader.samplingPlan.jadwal', function ($q) use ($myPrivileges) {
                    $q->where('is_active',true);
                    $q->whereIn('id_cabang', $myPrivileges);
                });
            }
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
                    'status_quotation' => $targetPlan->status_quotation
                ]);

                // Loop Jadwal
                foreach ($targetPlan->jadwal as $schedule) {
                    // Strict check: Tanggal jadwal HARUS sama dengan tanggal sampling di 
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
                        'is_proccess' => true,
                        'is_downloaded_stps' => 0,
                        'is_printed_stps' => 0
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
                            $statusRow['is_downloaded_stps']    = $dataDb['is_downloaded_stps']; 
                            $statusRow['is_printed_stps'] = $dataDb['is_printed_stps'];
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
                            'is_downloaded'      => (int)$statusRow['is_downloaded_stps'],
                            'is_printed'      => (int)$statusRow['is_printed_stps'],
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

    public function indexRekap(Request $request)
    {
        try {
            $existingWork = DB::table('persiapan_sampel_header')
            ->select('no_order', 'tanggal_sampling', 'sampler_jadwal', 'is_downloaded_stps', 'is_printed_stps')
            ->where('is_active', true)
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
                        'is_downloaded_stps' => $row->is_downloaded_stps,
                        'is_printed_stps' => $row->is_printed_stps
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
                    'status_quotation' => $targetPlan->status_quotation
                ]);

                // Loop Jadwal
                foreach ($targetPlan->jadwal as $schedule) {
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
                        'is_proccess' => true,
                        'is_downloaded_stps' => 0,
                        'is_printed_stps' => 0
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
                            $statusRow['is_downloaded_stps']    = $dataDb['is_downloaded_stps']; 
                            $statusRow['is_printed_stps'] = $dataDb['is_printed_stps'];
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
                            'is_downloaded'      => (int)$statusRow['is_downloaded_stps'],
                            'is_printed'      => (int)$statusRow['is_printed_stps'],
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


    public function cetakDataQs(Request $request)
    {
        try {
            $kategori = str_replace('&quot;', '"', $request->kategori);
            $kategori_ = str_replace('&amp;', '&', $kategori);
            if ($request->has('no_document')) {
                if ($request->no_document != null || $request->no_document != '') {
                    $tipe = explode("/", $request->no_document);

                    $kategori = str_replace('&quot;', '"', $request->kategori);
                    $kategori = str_replace('&amp;', '&', $kategori);

                    $jadwal = Jadwal::where('no_qt', $request->no_document)
                        ->where('tanggal', DB::raw("CAST('" . $request->tgl_sampling . "' AS DATE)"))
                        ->where('kategori', $kategori)
                        ->where('durasi', $request->durasi)
                        ->where('active', 0)
                        ->first();

                    $sp = DB::table('sampling_plan')->where('id', $jadwal->sample_id)->where('active', 1)->first();

                    $data_qt = [];
                    $data_sampling = [];
                    // dd($jadwal->kategori);
                    if ($sp) {
                        $groupedData = [];
                        $insample = [];
                        foreach (json_decode($jadwal->kategori) as $item) {
                            $parts = explode(" - ", $item);
                            $kategori = $parts[0];
                            array_push($insample, $parts[1]);
                            if (array_key_exists($kategori, $groupedData)) {
                                $groupedData[$kategori]++;
                            } else {
                                $groupedData[$kategori] = 1;
                            }
                        }

                        $type_qt = \explode('/', $request->no_document)[1];
                        if ($type_qt == 'QT') {
                            $data_qt = DB::table('request_quotation')->where('id', $sp->qoutation_id)->first();
                            $sales = DB::table('users')->where('id', $data_qt->add_by)->first();
                        } else if ($type_qt == 'QTC') {
                            $data_qt = DB::table('request_quotation_kontrak_H')->where('id', $sp->qoutation_id)->first();
                            $sales = DB::table('users')->where('id', $data_qt->add_by)->first();
                        } else {
                            return response()->json([
                                'message' => 'Data Not Found.!',
                            ], 401);
                        }

                        $data_sampling = [];
                        try {
                            foreach ($groupedData as $k => $y) {
                                $kateg = DB::table('sub_kategori_sample')->where('nama', $k)->where('active', 0)->first();
                                if (is_null($kateg)) {
                                    return response()->json([
                                        'message' => "Kategori $k dalam QT $request->no_document diJadwal Belum diupdate.! ",
                                    ], 401);
                                }
                                $kateg1 = $kateg->id . '-' . $kateg->nama;
                                $act = $request->menu;

                                $order_detail = OrderD::select(DB::raw('DISTINCT order_detail.*, order_header.tgl_order as tgl_order, order_header.no_order as no_order, order_header.konsultan as konsultan, order_header.nama_perusahaan as nama_perusahaan, order_header.nama_pic_order as nama_pic_order, order_header.nama_pic_sampling as nama_pic_sampling, order_header.no_tlp_pic_sampling as no_tlp_pic_sampling, order_header.jabatan_pic_sampling as jabatan_pic_sampling, order_header.jabatan_pic_order as jabatan_pic_order, order_header.alamat_sampling as alamat_sampling, coding_sampling.jumlah_label as jumlah_label'))

                                    ->leftJoin('coding_sampling', function ($join) {
                                        $join->on('order_detail.no_sample', '=', 'coding_sampling.no_sample');
                                        $join->where('coding_sampling.id', '=', DB::raw("(select max(`id`) from coding_sampling)"));
                                    })

                                    ->leftJoin('order_header', 'order_detail.id_order_header', '=', 'order_header.id')
                                    ->where('order_detail.id_order_header', $request->id_order_header)
                                    ->where('order_detail.kategori_3', $kateg1)
                                    ->where('order_detail.tgl_sampling', $request->tgl_sampling)
                                    ->where('order_detail.active', 0)
                                    ->whereIN(DB::raw('RIGHT(order_detail.no_sample, 3)'), $insample)

                                    ->orderBy('order_detail.no_sample', 'ASC')
                                    ->get();
                                foreach ($order_detail as $c => $vv) {

                                    $par = json_decode($vv->param);
                                    $param = [];
                                    foreach ($par as $key => $val) {
                                        array_push($param, \explode(";", $val)[1]);
                                    }

                                    $par = json_decode($vv->regulasi);
                                    $reg = [];
                                    if ($par != '') {
                                        foreach ($par as $key => $val) {
                                            if ($val != '') {
                                                array_push($reg, \explode("-", $val)[1]);
                                            }
                                        }
                                    }
                                    $vol = null;
                                    if ($vv->botol != null) {
                                        $volume = 0;
                                        foreach (json_decode($vv->botol) as $key => $value) {
                                            $volume += $value->volume;
                                        }
                                        $vol = $volume;
                                    }

                                    array_push($data_sampling, (object) [
                                        'no_sample' => $vv->no_sample,
                                        'konsultan' => $vv->konsultan,
                                        'alamat_sampling' => $vv->alamat_sampling,
                                        'nama_perusahaan' => $vv->nama_perusahaan,
                                        'nama_pic_order' => $vv->nama_pic_order,
                                        'nama_pic_sampling' => $vv->nama_pic_sampling,
                                        'no_tlp_pic_sampling' => $vv->no_tlp_pic_sampling,
                                        'jabatan_pic_sampling' => $vv->jabatan_pic_sampling,
                                        'jabatan_pic_order' => $vv->jabatan_pic_order,
                                        'kategori_2' => $vv->kategori_2,
                                        'kategori_3' => $vv->kategori_3,
                                        'no_order' => $vv->no_order,
                                        'tambahan' => $sp->tambahan,
                                        'status_sampling' => $vv->kategori_1,
                                        'keterangan_lain' => $sp->keterangan_lain,
                                        'nama_lengkap' => $sales->nama_lengkap,
                                        'no_telpon' => $sales->no_telpon,
                                        'tgl_sampling' => $jadwal->tanggal,
                                        'jam_sampling' => $jadwal->jam,
                                        'keterangan_1' => $vv->keterangan_1,
                                        'jumlah_label' => $vv->jumlah_label,
                                        // 'status_sampling' => $vv->status_sampling,
                                        'id' => $vv->id,
                                        'id_order_header' => $vv->id_order_header,
                                        'id_req_header' => $request->id_req_header,
                                        'periode_kontrak' => $sp->periode_kontrak,
                                        'tgl_order' => $vv->tgl_order,
                                        'botol' => $vv->botol,
                                        'parameter' => $param,
                                        'no_document' => $request->no_document,
                                        'regulasi' => $reg,
                                        'volume' => $vol,
                                    ]);
                                }
                            }
                            $collection2 = collect($data_sampling);
                            $data_sampling = $collection2->sortBy('no_sample');
                        } catch (\Throwable $th) {
                            return response()->json([
                            'message' => $th->getMessage(),
                        ], 401);    
                        }
                    } else {
                        return response()->json([
                            'message' => 'Data sampling plan tidak ditemukan.!',
                        ], 401);
                    }
                } else {
                    $data_sampling = [];
                }
            }

            if ($request->type_document == 'STPS') {
                if ($request->status_pr == 'downloadDoc') {
                    $cetak = self::cetakPDFSTPS($data_sampling, $data_qt, $request->sampler, "", $request->action, $request->type_document, $request->status_pr, $request->tgl_sample, $request->durasi, $request->kategori);
                } else if ($request->status_pr == 'printDoc') {
                    try {
                        $cetak = self::cetakPDFSTPS($data_sampling, $data_qt, $request->sampler, "", $request->action, $request->type_document, $request->status_pr, $request->tgl_sample, $request->durasi, $request->kategori);

                        $link = 'http://' . $request->ip_print . '/public/printing';
                        $url = request()->headers->all()['origin'][0];

                        $response = Http::asForm()
                            ->post($link, [
                                'printer' => $request->printer,
                                'file' => $url . '/utc/apps/public/fpps/' . $cetak,
                            ]);
                    } catch (Exception $e) {
                        return response()->json([
                            'message' => $e->getMessage(),
                        ], 401);
                    }
                }

                try {
                    $cek = DB::table('doc_coding_sample')
                        ->where('no_qt', $request->no_document)
                        ->where('print_by', $this->userid)
                        ->where('tgl_sample', $request->tgl_sampling)
                        ->where('durasi', $request->durasi)
                        ->where('kategori', $kategori_)
                        ->where('menu', $request->menu)
                        ->first();

                    $status_qt = '';

                    if (explode('/', $request->no_document)[1] == 'QTC') {
                        $status_qt = 'kontrak';
                    } else {
                        $status_qt = 'non_kontrak';
                    }

                    if (!is_null($cek)) {
                        $status_pr = json_decode($cek->status_pr, true);
                        $hist = json_decode($cek->history_time, true);
                        array_push($hist, date('Y-m-d H:i:s'));
                        $key = array_search($request->action, array_column($status_pr, 'action'));

                        if ($key !== false) {
                            $status_pr[$key]['total_print'] += 1;
                        } else {
                            $status_pr[] = ['action' => $request->action, 'total_print' => 1];
                        }

                        $cek = DB::table('doc_coding_sample')
                            ->where('no_qt', $request->no_document)
                            ->where('print_by', $this->userid)
                            ->where('tgl_sample', $request->tgl_sampling)
                            ->where('durasi', $request->durasi)
                            ->where('kategori', $kategori_)
                            ->where('menu', $request->menu)
                            ->update([
                                'status_pr' => json_encode($status_pr),
                                'history_time' => json_encode($hist),
                            ]);
                    } else {
                        $insert = DB::table('doc_coding_sample')->insert([
                            'no_qt' => $request->no_document,
                            'status_qt' => $status_qt,
                            'tgl_sample' => $request->tgl_sampling,
                            'durasi' => $request->durasi,
                            'kategori' => $kategori_,
                            'status_pr' => json_encode([['action' => $request->action, 'total_print' => 1]]),
                            'history_time' => json_encode([date('Y-m-d H:i:s')]),
                            'menu' => $request->menu,
                            'print_by' => $this->userid
                        ]);
                    }
                    return response()->json([
                        'message' => 'Berhasil cetak data STPS.!',
                        'link' => $cetak
                    ], 200);
                } catch (\Exception $th) {
                    dd($th);
                }
            }
        } catch (Exception $e) {
            dd($e);
        }
    }
    
    public function cetakPDFSTPS(Request $request)
    {
        
        try {
            // ==============================================COLLECT DATA========================================
            $tipe_penawaran = \explode('/', $request->nomor_quotation)[1];
            if ($tipe_penawaran == 'QTC') {
                if ($request->periode == null || $request->periode == "") {
                    return response()->json(['message' => 'Periode tidak ditemukan.!'], 401);
                }

                $dataPenawaran = QuotationKontrakH::with(['order', 'sampling', 'detail'])->where('no_document', $request->nomor_quotation)->first();
                $dataOrder = $dataPenawaran->order;
                $dataSampling = $dataPenawaran->sampling;
                
                $unik_kategori = $dataOrder->orderDetail()->where('periode', $request->periode)
                    ->where('is_active', true)->get()->pluck('kategori_3')->unique()->toArray();

                $getLabelSp = $dataPenawaran->detail()->where('periode_kontrak',$request->periode)->first(['status_sampling']);
                $labelStatusSampling = '';
                if ($getLabelSp) {
                    $status = $getLabelSp->status_sampling;
                    if ($status === 'SP') $labelStatusSampling = '<span><i>Sample Pickup</i></span>';
                    else if ($status === 'S24') $labelStatusSampling = '<span>Sampling 24 Jam</span>';
                    else if ($status === 'S') $labelStatusSampling = '<span>Sampling</span>';
                    else if ($status === 'SD') $labelStatusSampling = '<span>Sampling Diantar</span>';
                    else if ($status === 'RS') $labelStatusSampling = '<span>Re-Sampling</span>';
                }

                $pra_no_sample = [];
                $kategori_sample = [];
                foreach (\explode(',', $request->kategori) as $kat) {
                    $split = explode(' - ', $kat);
                    $kategori_sample[] = html_entity_decode($split[0]);
                    $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
                }
                $pra_no_sample = array_unique($pra_no_sample);

                foreach ($unik_kategori as $kategori) {
                    $split = explode('-', $kategori);
                    $id = $split[0];
                    $nama_kategori = $split[1];

                    if (in_array($nama_kategori, $kategori_sample)) {
                        $kategori_sample = array_map(function ($item) use ($id, $nama_kategori) {
                            if ($item == $nama_kategori) return $id . '-' . $nama_kategori;
                            return $item;
                        }, $kategori_sample);
                    }
                }

                $getPeriodeSampling = array_filter($dataSampling->toArray(), function ($item) use ($request) {
                    return $item['periode_kontrak'] == $request->periode;
                });
                
                $dataSampling = array_values($getPeriodeSampling)[0]['jadwal'];
                foreach ($dataSampling as $key => $value) {
                    $keysToRemove = ['id', 'nama_perusahaan', 'wilayah', 'alamat', 'tanggal', 'periode', 'jam', 'jam_mulai', 'jam_selesai', 'kategori', 'sampler', 'userid', 'driver', 'warna', 'note', 'durasi', 'status', 'flag', 'created_by', 'created_at', 'updated_by', 'updated_at', 'canceled_by', 'canceled_at', 'notif', 'urutan', 'kendaraan'];
                    foreach ($keysToRemove as $k) unset($dataSampling[$key][$k]);
                }
                    
                $dataSampling = array_values(array_filter(array_unique($dataSampling, SORT_REGULAR), function ($item) {
                    return isset($item['is_active']) && $item['is_active'] == 1;
                }));
                
                if (count($dataSampling) > 1) {
                    $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
                        ->select('kategori_3', 'periode', 'kategori_1', 'kategori_2', 'regulasi', 'keterangan_1', 'parameter', 'persiapan', 'no_sampel')
                        ->where('periode', $request->periode)
                        ->whereIn('kategori_3', $kategori_sample)
                        ->whereIn('no_sampel', $pra_no_sample)
                        ->where('kategori_1', '!=', 'SD')
                        ->where('is_active', 1)
                        ->orderBy('periode')
                        ->orderBy('no_sampel')
                        ->get()
                        ->groupBy(['kategori_3', 'regulasi', 'parameter'])
                        ->map(function ($kategori3Group) {
                            return $kategori3Group->map(function ($regulasiGroup) {
                                return $regulasiGroup->map(function ($parameterGroup) {
                                    $jumlahTitik = $parameterGroup->count();
                                    return [
                                        'kategori_3' => \explode('-', $parameterGroup->first()->kategori_3)[1],
                                        'kategori_1' => $parameterGroup->first()->kategori_1,
                                        'periode' => $parameterGroup->first()->periode,
                                        'kategori_2' => \explode('-', $parameterGroup->first()->kategori_2)[1],
                                        'regulasi' => $parameterGroup->first()->regulasi ? array_map(function ($item) {
                                            return explode('-', $item)[1];
                                        }, json_decode($parameterGroup->first()->regulasi) ?? []) : [],
                                        'keterangan_1' => $parameterGroup->first()->keterangan_1,
                                        'parameter_raw' => json_decode($parameterGroup->first()->parameter) ?? [],
                                        'parameter' => array_map(function ($item) {
                                            $paramId = explode(';', $item)[0];
                                            $param = Parameter::find($paramId);
                                            return $param ? $param->nama_lab : null;
                                        }, json_decode($parameterGroup->first()->parameter)),
                                        'persiapan' => ($parameterGroup->first()->kategori_2 == '1-Air' ? '( ' . number_format((array_sum(array_map(function ($item) {
                                            if (!is_object($item)) return 0;
                                            $persiapan = json_decode($item->persiapan, true);
                                            return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                        }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
                                        'total_parameter' => count(json_decode($parameterGroup->first()->parameter) ?: []),
                                        'jumlah_titik' => $jumlahTitik,
                                        'no_sampel' => $parameterGroup->pluck('no_sampel')->toArray(),
                                    ];
                                })->values();
                            })->collapse();
                        })->collapse()
                        ->values()
                        ->toArray();

                    usort($dataOrderDetailPerPeriode, function ($a, $b) {
                        preg_match('/\/(\d+)$/', $a['no_sampel'][0], $matchesA);
                        preg_match('/\/(\d+)$/', $b['no_sampel'][0], $matchesB);
                        $numA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
                        $numB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;
                        return $numA <=> $numB; 
                    });
                } else {
                    $data_detail_penawaran = json_decode($dataPenawaran->detail()->where('periode_kontrak', $request->periode)->first()->data_pendukung_sampling, true);
                    $data_detail_penawaran = array_map(function ($item) use ($dataOrder, $pra_no_sample) {
                        $maping = array_map(function ($data_sampling) use ($item, $dataOrder, $pra_no_sample) {
                            
                            $sampleNumbersFromOrder = $dataOrder->orderDetail()
                                ->where('kategori_1', '!=', 'SD')
                                ->where('kategori_2', $data_sampling['kategori_1'])
                                ->where('kategori_3', $data_sampling['kategori_2'])
                                ->where('periode', $item['periode_kontrak'])
                                ->whereIn('no_sampel', $pra_no_sample)
                                ->where('is_active', 1)
                                ->get();
                            $penawaran_keys = array_merge(...array_map('array_keys', $data_sampling['penamaan_titik']));

                            $sampleNumbers = [];
                            foreach ($sampleNumbersFromOrder as $orderDetail) {
                                $orderParameter = json_decode($orderDetail->parameter, true) ?? [];
                                $inputParameter = $data_sampling['parameter'];
                                
                                $parameterMatch = !empty(array_intersect($orderParameter, $inputParameter));
                                $totalParameterSame = count($orderParameter) === count($inputParameter);

                                if ($parameterMatch && $totalParameterSame) {
                                    $number = explode('/', $orderDetail->no_sampel)[1];
                                    $idRegulasiOrder = array_map(fn($item) => explode('-', $item)[0], json_decode($orderDetail->regulasi, true) ?? []);
                                    $idRegulasiPenawaran = !empty($data_sampling['regulasi']) ? array_map(fn($item) => explode('-', $item)[0], $data_sampling['regulasi']) : [];

                                    if (!empty($idRegulasiOrder) && !empty($idRegulasiPenawaran)) {
                                        $regulasiMatch = !empty(array_intersect($idRegulasiOrder, $idRegulasiPenawaran));
                                        if (in_array($number, $penawaran_keys) && $regulasiMatch) {
                                            $sampleNumbers[] = $orderDetail->no_sampel;
                                        }
                                    } else {
                                        if (!$idRegulasiOrder && !$idRegulasiPenawaran) $sampleNumbers[] = $orderDetail->no_sampel;
                                    }
                                }
                            }

                            if (empty($sampleNumbers)) return null; 

                            return [
                                'kategori_3' => \explode('-', $data_sampling['kategori_2'])[1],
                                'periode' => $item['periode_kontrak'],
                                'kategori_2' => \explode('-', $data_sampling['kategori_1'])[1],
                                'regulasi' => (!empty($data_sampling['regulasi']) && is_array($data_sampling['regulasi'])) ? array_map(function ($item) {
                                    return explode('-', $item)[1] ?? '';
                                }, $data_sampling['regulasi']) : [],
                                'keterangan_1' => $data_sampling['penamaan_titik'],
                                'parameter_raw' => $data_sampling['parameter'],
                                'parameter' => array_map(function ($parameter) {
                                    return \explode(';', $parameter)[1];
                                }, $data_sampling['parameter']),
                                'persiapan' => isset($data_sampling['volume']) && !empty($data_sampling['volume']) ?
                                    '( ' . number_format($data_sampling['volume'] / 1000, 1) . ' L )' : '',
                                'total_parameter' => $data_sampling['total_parameter'],
                                'jumlah_titik' => $data_sampling['jumlah_titik'],
                                'no_sampel' => $sampleNumbers,
                            ];
                        }, $item['data_sampling']);
                        $maping = array_values(array_filter($maping));
                        if (empty($maping)) {
                            throw new \Exception("Tidak ditemukan kecocokan sample pada penawaran ini. Periode: " . ($item['periode_kontrak'] ?? '-'));
                        }
                        return $maping;
                    }, $data_detail_penawaran);

                    $dataOrderDetailPerPeriode = array_values(array_values($data_detail_penawaran)[0]);
                }

            } else {
                // PROSES UNTUK NON-KONTRAK
                $dataPenawaran = QuotationNonKontrak::with(['order', 'sampling'])->where('no_document', $request->nomor_quotation)->where('is_active',true)->first();
                $dataOrder = $dataPenawaran->order;
                
                if ($dataOrder->is_revisi == 1) return response()->json(['message' => 'Quotation sedang dalam revisi.!'], 401);
                
                $dataSampling = $dataPenawaran->sampling;
                $unik_kategori = $dataOrder->orderDetail()->get()->pluck('kategori_3')->unique()->toArray();
                
                $pra_no_sample = [];
                $kategori_sample = [];
                $labelStatusSampling = '';

                if($dataPenawaran){
                    $status = $dataPenawaran->status_sampling;
                    if ($status === 'SP') $labelStatusSampling = '<span><i>Sample Pickup</i></span>';
                    else if ($status === 'S24') $labelStatusSampling = '<span>Sampling 24 Jam</span>';
                    else if ($status === 'S') $labelStatusSampling = '<span>Sampling</span>';
                    else if ($status === 'SD') $labelStatusSampling = '<span>Sampling Diantar</span>';
                    else if ($status === 'RS') $labelStatusSampling = '<span>Re-Sampling</span>';
                }

                foreach (\explode(',', $request->kategori) as $kat) {
                    $split = explode(' - ', $kat);
                    $kategori_sample[] = html_entity_decode($split[0]);
                    $pra_no_sample[] = $dataOrder->no_order . '/' . $split[1];
                }
                $pra_no_sample = array_unique($pra_no_sample);

                foreach ($unik_kategori as $kategori) {
                    if ($kategori == null) continue;
                    $split = explode('-', $kategori);
                    $id = $split[0];
                    $nama_kategori = $split[1];

                    if (in_array($nama_kategori, $kategori_sample)) {
                        $kategori_sample = array_map(function ($item) use ($id, $nama_kategori) {
                            if ($item == $nama_kategori) return $id . '-' . $nama_kategori;
                            return $item;
                        }, $kategori_sample);
                    }
                }
                
                $dataSampling = array_values($dataSampling->toArray())[0]['jadwal'];
                foreach ($dataSampling as $key => $value) {
                    $keysToRemove = ['id', 'nama_perusahaan', 'wilayah', 'alamat', 'tanggal', 'periode', 'jam', 'jam_mulai', 'jam_selesai', 'kategori', 'sampler', 'userid', 'driver', 'warna', 'note', 'durasi', 'status', 'flag', 'created_by', 'created_at', 'updated_by', 'updated_at', 'canceled_by', 'canceled_at', 'notif', 'urutan', 'kendaraan'];
                    foreach ($keysToRemove as $k) unset($dataSampling[$key][$k]);
                }
                $dataSampling = array_values(array_filter(array_unique($dataSampling, SORT_REGULAR), function ($item) {
                    return isset($item['is_active']) && $item['is_active'] == 1;
                }));
                
                if (count($dataSampling) > 1) {
                    $dataOrderDetailPerPeriode = $dataOrder->orderDetail()
                        ->select('no_sampel', 'kategori_3', 'kategori_2', 'kategori_1', 'regulasi', 'keterangan_1', 'parameter', 'persiapan')
                        ->whereIn('kategori_3', $kategori_sample)
                        ->whereIn('no_sampel', $pra_no_sample)
                        ->where('kategori_1', '!=', 'SD')
                        ->where('is_active', 1)
                        ->orderBy('no_sampel')
                        ->get()
                        ->groupBy(['kategori_3', 'regulasi', 'parameter'])
                        ->map(function ($kategori3Group) {
                            return $kategori3Group->map(function ($regulasiGroup) {
                                return $regulasiGroup->map(function ($parameterGroup) {
                                    $first = $parameterGroup->first();
                                    return [
                                        'kategori_3' => \explode('-', $first->kategori_3)[1],
                                        'kategori_1' => $first->kategori_1,
                                        'periode' => NULL,
                                        'kategori_2' => \explode('-', $first->kategori_2)[1],
                                        'regulasi' => $first->regulasi ? array_map(function ($item) {
                                            return ($item !== "" ? explode('-', $item)[1] : "");
                                        }, json_decode($first->regulasi) ?? []) : [],
                                        'keterangan_1' => $first->keterangan_1,
                                        'parameter_raw' => json_decode($first->parameter) ?? [],
                                        'parameter' => array_map(function ($item) {
                                            $paramId = explode(';', $item)[0];
                                            $param = Parameter::find($paramId);
                                            return $param ? $param->nama_lab : null;
                                        }, json_decode($first->parameter)),
                                        'persiapan' => ($first->kategori_2 == '1-Air' ? '( ' . number_format((array_sum(array_map(function ($item) {
                                            if (!is_object($item)) {
                                                if (is_array($item) && isset($item['persiapan'])) {
                                                    $itemObj = (object) $item;
                                                    $persiapan = json_decode($itemObj->persiapan, true);
                                                    return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                                }
                                            } else return 0;
                                            $persiapan = json_decode($item->persiapan, true);
                                            return $persiapan ? array_sum(array_column($persiapan, 'volume')) : 0;
                                        }, $parameterGroup->toArray())) / 1000), 1) . ' L )' : ''),
                                        'total_parameter' => count(json_decode($first->parameter) ?: []),
                                        'jumlah_titik' => $parameterGroup->count(),
                                        'no_sampel' => $parameterGroup->pluck('no_sampel')->toArray()
                                    ];
                                })->values();
                            })->collapse();
                        })->collapse()
                        ->values()
                        ->toArray();

                    usort($dataOrderDetailPerPeriode, function ($a, $b) {
                        preg_match('/\/(\d+)$/', $a['no_sampel'][0], $matchesA);
                        preg_match('/\/(\d+)$/', $b['no_sampel'][0], $matchesB);
                        $numA = isset($matchesA[1]) ? (int) $matchesA[1] : 0;
                        $numB = isset($matchesB[1]) ? (int) $matchesB[1] : 0;
                        return $numA <=> $numB; 
                    });
                } else {
                    $data_detail_penawaran = json_decode($dataPenawaran->data_pendukung_sampling, true);
                    
                    $data_detail_penawaran = array_map(function ($data_sampling) use ($dataOrder, $pra_no_sample) {
                        $sampleNumbersFromOrder = $dataOrder->orderDetail()
                                ->where('kategori_1', '!=', 'SD')
                                ->where('kategori_2', $data_sampling['kategori_1'])
                                ->where('kategori_3', $data_sampling['kategori_2'])
                                ->whereIn('no_sampel', $pra_no_sample)
                                ->where('is_active', 1)
                                ->get();
                        $penawaran_keys = array_merge(...array_map('array_keys', $data_sampling['penamaan_titik']));
                        $sampleNumbers = [];
                        foreach ($sampleNumbersFromOrder as $orderDetail) {
                            $orderParameter = json_decode($orderDetail->parameter, true) ?? [];
                            $inputParameter = $data_sampling['parameter'];
                            
                            $parameterMatch = !empty(array_intersect($orderParameter, $inputParameter));
                            $totalParameterSame = count($orderParameter) === count($inputParameter);

                            if ($parameterMatch && $totalParameterSame) {
                                $number = explode('/', $orderDetail->no_sampel)[1];
                                $idRegulasiOrder = array_map(fn($item) => explode('-', $item)[0], json_decode($orderDetail->regulasi, true) ?? []);
                                $idRegulasiPenawaran = !empty($data_sampling['regulasi']) ? array_map(fn($item) => explode('-', $item)[0], $data_sampling['regulasi']) : [];

                                if (!empty($idRegulasiOrder) && !empty($idRegulasiPenawaran)) {
                                    $regulasiMatch = !empty(array_intersect($idRegulasiOrder, $idRegulasiPenawaran));
                                    if (in_array($number, $penawaran_keys) && $regulasiMatch) {
                                        $sampleNumbers[] = $orderDetail->no_sampel;
                                    }
                                } else {
                                    $sampleNumbers[] = $orderDetail->no_sampel;
                                }
                            }
                        }
                        return [
                            'kategori_3' => \explode('-', $data_sampling['kategori_2'])[1],
                            'periode' => NULL,
                            'kategori_2' => \explode('-', $data_sampling['kategori_1'])[1],
                            'regulasi' => $data_sampling['regulasi'] ? array_map(function ($item) {
                                return explode('-', $item)[1];
                            }, $data_sampling['regulasi']) : [],
                            'keterangan_1' => $data_sampling['penamaan_titik'],
                            'parameter_raw' => $data_sampling['parameter'],
                            'parameter' => array_map(function ($parameter) {
                                return \explode(';', $parameter)[1];
                            }, $data_sampling['parameter']),
                            'persiapan' => isset($data_sampling['volume']) && !empty($data_sampling['volume']) ?
                                '( ' . number_format($data_sampling['volume'] / 1000, 1) . ' L )' : '',
                            'total_parameter' => $data_sampling['total_parameter'],
                            'jumlah_titik' => $data_sampling['jumlah_titik'],
                            'no_sampel' => $sampleNumbers,
                        ];
                    }, $data_detail_penawaran);

                    $dataOrderDetailPerPeriode = array_values($data_detail_penawaran);
                }
            }

            if (in_array($dataPenawaran->status_sampling, ['SD', 'SAR'])) {
                return response()->json(['message' => 'Sample diantar tidak memiliki STPS.!'], 401);
            }

            $psController = new PersiapanSampleController($request);
            $pshModel = PersiapanSampelHeader::class;

            $psHeader = $pshModel::where('no_quotation', $request->nomor_quotation)
                ->where('no_order', $dataOrder->no_order)
                ->where('tanggal_sampling', $request->jadwal)
                ->where('is_active', 1)
                ->where('sampler_jadwal', $request->sampler);

            if ($request->periode) $psHeader = $psHeader->where('periode', $request->periode);

            $psHeader = $psHeader->first();
            
            if (!$psHeader) {
                $request->no_document = $request->nomor_quotation;
                $request->no_sampel = $pra_no_sample;

                $response = $psController->preview($request);
                $preview = json_decode($response->getContent(), true);
            
                $isMustPrepared = false;
                foreach (['air', 'udara', 'emisi', 'padatan'] as $kategori) {
                    foreach ($preview[$kategori] as $sampel) {
                        if (isset($sampel['no_sampel'])) {
                            $isMustPrepared = true;
                            break;
                        };
                    }
                }

                if ($isMustPrepared) {
                    return response()->json(['message' => 'Sampel belum disiapkan, Silahkan melakukan update terlebih dahulu.!'], 401);
                } else {
                    $requestPsData = new Request([
                        'no_order' => $request->no_order,
                        'no_quotation' => $request->no_document,
                        'tanggal_sampling' => $request->jadwal,
                        'nama_perusahaan' => $request->nama_perusahaan,
                        'kategori_jadwal' => $request->kategori,
                        'sampler_jadwal' => $request->sampler,
                        'periode' => $request->periode,
                        'analis_berangkat' => null,
                        'sampler_berangkat' => null,
                        'analis_pulang' => null,
                        'sampler_pulang' => null,
                        'masker' => ['disiapkan' => 2, 'tambahan' => ""],
                        'sarung_tangan_karet' => ['disiapkan' => 2, 'tambahan' => ""],
                        'sarung_tangan_bintik' => ['disiapkan' => 2, 'tambahan' => ""],
                        'detail' => [],
                        'plastik_benthos' => ["tambahan" => "", "disiapkan" => ""],
                        'media_petri_dish' => ["tambahan" => "", "disiapkan" => ""],
                        'media_tabung' => ["tambahan" => "", "disiapkan" => ""],
                    ]);

                    $psController->save($requestPsData);

                    $psHeader = $pshModel::where('no_quotation', $request->nomor_quotation)
                        ->where('no_order', $dataOrder->no_order)
                        ->where('tanggal_sampling', $request->jadwal)
                        ->where('sampler_jadwal', $request->sampler)
                        ->first();
                }
            }

            $noDocument = explode('/', $psHeader->no_document);
            $noDocument[1] = 'FPPS';
            $noDocument = implode('/', $noDocument);

            $qr_img = '';
            $qr = QrDocument::where('id_document', $psHeader->id)
                ->where('type_document', 'surat_tugas_pengambilan_sampel')
                ->whereJsonContains('data->no_document', $noDocument)
                ->first();
            
            if ($qr) {
                $qr_data = json_decode($qr->data, true);
                if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
                    $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                }
            }

            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 10,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ];

            $pdf = new Mpdf($mpdfConfig);
            $pdf->shrink_tables_to_fit = 0; // Cegah mPDF mengecilkan font otomatis pada tabel besar
            $pdf->SetProtection(['print'], '', 'skyhwk12');
            $pdf->showWatermarkImage = true;

            $footer = [
                'odd' => [
                    'C' => [
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ],
                    'R' => [
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'L' => [
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'line' => -1,
                ]
            ];

            $pdf->setFooter($footer);

            $konsultant = $dataPenawaran->konsultan ? strtoupper($dataPenawaran->konsultan) : '';
            $perusahaan = $dataPenawaran->konsultan ? ' (' . $dataPenawaran->nama_perusahaan . ') ' : $dataPenawaran->nama_perusahaan;

            $nama_pic = $dataPenawaran->nama_pic_sampling .
                ($dataPenawaran->jabatan_pic_sampling ? ' (' . $dataPenawaran->jabatan_pic_sampling . ')' : '(-)') .
                ($dataPenawaran->no_tlp_pic_sampling ? ' - ' . $dataPenawaran->no_tlp_pic_sampling : '');

            $no_document = $noDocument;
            $tanggal = $request->jadwal;

            // Generate QR pengesahan (Dipertahankan logikanya untuk DB, tapi HTML Tanda tangannya sudah dihapus)
            $noPengesahan = 'pengesahan_'. str_replace('/','_',$no_document);
            $path = public_path() . "/qr_documents/" . $noPengesahan . '.svg';

            $latestPengesahan = null;
            if ($tanggal) {
                $latestPengesahan = PengesahanDokumenSampling::orderByDesc('berlaku_mulai')->first();
            }

            if($latestPengesahan !== null && !file_exists($path)){
                try {
                    $link = 'https://www.intilab.com/validation/';
                    $unique = 'isldc' . (int) floor(microtime(true) * 1000);

                    QrCode::size(200)->generate($link . $unique, $path);
                    $dataQr = [
                        'type_document' => 'stps',
                        'kode_qr' => $unique,
                        'file' => $noPengesahan,
                        'data' => json_encode([
                            'no_document' => $no_document,
                            'nama_customer' => $perusahaan,
                            'type_document' => 'Surat Tugas Pengambilan Sampel',
                            'Tanggal_Pengesahan' => Carbon::parse($tanggal)->locale('id')->isoFormat('DD MMMM YYYY'),
                            'Disahkan_Oleh' => $latestPengesahan->nama_karyawan,
                            'Jabatan' => $latestPengesahan->jabatan_karyawan
                        ]),
                        'created_at' => Carbon::now(),
                        'created_by' => 'System',
                    ];

                    DB::table('qr_documents')->insert($dataQr);
                } catch (\Exception $e) {
                    \Log::warning('Gagal generate QR pengesahan STPS: ' . $e->getMessage());
                }
            }

            $html = '';
            if (str_contains($request->sampler, ',')) {
                $datsa = explode(",", $request->sampler);
                foreach ($datsa as $s => $dat) {
                    $html .= ($s + 1) . '. ' . $dat . '<br>';
                }
            } else {
                $html .= '1. ' . $request->sampler;
            }
            $alamatSampling = nl2br($dataPenawaran->alamat_sampling);

            // ===================== HEADER HALAMAN PERTAMA (Lengkap) =====================
            $headerLengkap='
                <table width="100%">
                    <tr>
                        <td width="60%"></td>
                        <td>
                            <table class="table table-bordered" width="100%">
                                <tr>
                                    <td width="50%" style="text-align: center; font-size: 13px;"><b>No Order</b></td>
                                    <td style="text-align: center; font-size: 13px;"><b>' . $dataOrder->no_order . '</b></td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; font-size: 12px;" colspan="2"><b>' . $labelStatusSampling . '</b></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table width="100%">
                    <tr>
                        <td style="width: 55%;"></td>
                        <td style="text-align:center">
                            <span style="font-size:14px;"><b><u>Form Perencanaan Persiapan Sampling</u></b></span><br>
                            <span style="font-size:11px;" id="no_document">' . $no_document . '</span>
                        </td>
                    </tr>
                </table>
                <table style="font-size:13px;font-weight:700;width:100%;margin-top:20px;">
                    <tr>
                        <td>' . $konsultant . $perusahaan . '</td>
                    </tr>
                    <tr>
                        <td width="65%" style="font-size:10px;">
                            <u>Informasi Sampling :</u><br>
                            <span id="tgl_sampling">' . ($tanggal ? self::tanggal_indonesia($tanggal, 'hari') : 'Belum dijadwalkan') . '</span><br>
                            <span id="alamat_sampling">' . $alamatSampling . '</span><br>
                            <span id="pic_order">PIC : ' . $nama_pic . '</span>
                        </td>
                        <td style="vertical-align:top;font-size:10px">
                            <u>Petugas Sampling :</u><br>
                            <span id="petugas_sampling">' . $html . '</span>
                        </td>
                    </tr>
                </table>
            ';

            // ===================== HEADER HALAMAN LANJUTAN (Ringkas: hanya kanan) =====================
            $headerRingkas = '
                <table width="100%">
                    <tr>
                        <td width="55%"></td>
                        <td width="45%">
                            <table class="table table-bordered" width="100%">
                                <tr>
                                    <td width="50%" style="text-align: center; font-size: 11px;"><b>No Order</b></td>
                                    <td style="text-align: center; font-size: 11px;"><b>' . $dataOrder->no_order . '</b></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table width="100%">
                    <tr>
                        <td style="width: 55%;"></td>
                        <td style="text-align:center">
                            <span style="font-size:12px;"><b><u>Form Perencanaan Persiapan Sampling</u></b></span><br>
                            <span style="font-size:10px;">' . $no_document . '</span>
                        </td>
                    </tr>
                </table>
            ';

            // Terapkan header lengkap untuk halaman pertama
            $pdf->SetHTMLHeader($headerLengkap);

            // Definisikan header ringkas dan jadwalkan untuk halaman berikutnya (termasuk auto-break)
            $pdf->WriteHTML('
                <htmlpageheader name="HeaderRingkas" style="display:none;">
                    ' . $headerRingkas . '
                </htmlpageheader>
                <sethtmlpageheader name="HeaderRingkas" page="ALL" value="on" show-this-page="0" />
            ');

            // ===================== BAGIAN KATEGORI & DETAIL =====================
            $i = 1;
            foreach ($dataOrderDetailPerPeriode as $key => $value) {
                $value = (object) $value;
            
                if (!isset($value->kategori_3)) {
                    throw new \Exception("Field 'kategori_3' hilang pada data index ke-$key");
                }
            
                $templateHTML = $this->getTemplateKategoriHTML($value->kategori_3);
                $isKompleks = in_array(trim($value->kategori_3), self::FPPS_KATEGORI_KOMPLEKS);
            
                // Kategori kompleks mulai di halaman baru agar tabel besar tidak terpotong
                if ($i > 1) {
                    if ($isKompleks) {
                        $pdf->AddPage();
                    } else {
                        // Deteksi jarak dari atas kertas. A4 = 297mm.
                        // Jika posisi Y sudah lebih dari 230 (sisa ruang kurang dari ~5cm di bawah), 
                        // kita paksa pindah halaman agar judul tidak terpisah sendirian.
                        if ($pdf->y > 230) {
                            $pdf->AddPage();
                        } else {
                            $pdf->WriteHTML('<br>');
                        }
                    }
                }
            
                // Judul kategori
                $htmlOutput = '
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
                    <tr>
                        <td style="width: 140px; font-weight: bold; font-size: ' . self::FPPS_FONT_JUDUL_KATEGORI . '; padding: 2px 0;">Kategori Pengujian</td>
                        <td style="width: 10px; font-weight: bold; font-size: ' . self::FPPS_FONT_JUDUL_KATEGORI . '; padding: 2px 0;">:</td>
                        <td style="font-weight: bold; font-size: ' . self::FPPS_FONT_JUDUL_KATEGORI . '; padding: 2px 0;">' . strtoupper($value->kategori_3) . '</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; font-size: ' . self::FPPS_FONT_JUDUL_KATEGORI . '; padding: 2px 0;">Jumlah Titik</td>
                        <td style="font-weight: bold; font-size: ' . self::FPPS_FONT_JUDUL_KATEGORI . '; padding: 2px 0;">:</td>
                        <td style="font-weight: bold; font-size: ' . self::FPPS_FONT_JUDUL_KATEGORI . '; padding: 2px 0;">' . $value->jumlah_titik . '</td>
                    </tr>
                </table>';

                $pdf->WriteHTML($htmlOutput);
            
                if (!empty($templateHTML)) {
                    $pdf->WriteHTML($templateHTML);
                } else {
                    $pdf->WriteHTML($this->getPlaceholderKategoriHTML());
                }
            
                if (strtolower(trim($value->kategori_2)) == 'air' && !empty($value->parameter_raw)) {
                    $infoTableHtml = $this->buildInformasiPerencanaanSamplingTable($value->parameter_raw);
                    if (!empty($infoTableHtml)) {
                        $pdf->WriteHTML($infoTableHtml);
                    }
                }

                $i++;
            }



            // ===================== OUTPUT PDF =====================
            $fileName = str_replace('/', '-', $no_document) . '.pdf';
            $pdfContent = $pdf->Output('', 'S');
            
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'X-Filename' => $fileName // frontend will need to read this if possible, or fallback to generated name
            ]);

        } catch (\Throwable $th) {
             
            return response()->json([
                'message' => 'Data STPS tidak bisa dicetak karena data tidak sinkron dengan data jadwal.!',
                "line" =>$th->getLine(),
                'pesan' =>$th->getMessage(),
                'file' =>$th->getFile()
            ], 500);
        }
    }
    private function getPlaceholderKategoriHTML()
    {
        return '
        <table border="1" cellpadding="6" cellspacing="0" width="100%"
            style="font-size: ' . self::FPPS_FONT_ISI_TABEL . '; border-collapse: collapse; table-layout: fixed;">
            <tr style="background-color:#f2f2f2;">
                <th style="font-size: ' . self::FPPS_FONT_HEADER_TABEL . '; text-align:left; padding:6px;">
                    Rincian Persiapan Sampling
                </th>
            </tr>
            <tr>
                <td style="text-align:left; padding:8px; color:#555;">
                    Kategori ini tidak memerlukan rincian persiapan alat/bahan khusus
                    (form persiapan default cukup mengacu pada SOP umum sampling).
                </td>
            </tr>
        </table>';
    }


    private function buildInformasiPerencanaanSamplingTable($parameterRaw)
    {
        $paramIds = [];
        $paramNames = [];
        foreach($parameterRaw as $item) {
            $parts = explode(';', $item);
            if (count($parts) >= 2) {
                $paramIds[] = $parts[0];
                $paramNames[$parts[0]] = $parts[1] ?? '';
            }
        }

        if (empty($paramIds)) return '';

        $hargaParams = \App\Models\HargaParameter::whereIn('id_parameter', $paramIds)
            ->where('is_active', true)
            ->where('status', 0)
            ->whereNotNull('regen')
            ->get(['id_parameter', 'regen', 'volume'])
            ->keyBy('id_parameter');

        $groups = [];

        foreach ($paramIds as $id) {
            $nama = $paramNames[$id] ?? '';
            $regen = isset($hargaParams[$id]) ? $hargaParams[$id]->regen : 'ORI';
            $volume = isset($hargaParams[$id]) ? (float)$hargaParams[$id]->volume : 0;

            $analisisSegeraParams = ['pH', 'DO', 'Suhu', 'Salinitas', 'CO2', 'Residual Klorin', 'Bau'];
            
            $isAnalisisSegera = false;
            foreach($analisisSegeraParams as $asp) {
                if (stripos($nama, $asp) !== false) {
                    $isAnalisisSegera = true;
                    break;
                }
            }

            $isBOD = (stripos($nama, 'BOD') !== false);
            $isOG = (stripos($nama, 'OG') !== false || stripos($nama, 'Minyak Lemak') !== false);

            $wadah = '';
            if (stripos($regen, 'HNO3') !== false) {
                $wadah = 'P';
            } elseif (stripos($regen, 'H2SO4') !== false) {
                $wadah = $isOG ? 'G, Mulut Lebar' : 'P';
            } elseif (stripos($regen, 'M100') !== false) {
                $wadah = 'G';
            } elseif (stripos($regen, 'ORI') !== false || $regen == 'ORI') {
                $wadah = $isBOD ? 'Winkler' : 'P';
            } else {
                $wadah = 'P';
            }

            $perlakuan = '';
            if ($isAnalisisSegera) {
                $perlakuan = 'Analisis Segera';
            } elseif (stripos($regen, 'H2SO4') !== false) {
                $perlakuan = 'Tambahkan H2SO4 sampai pH < 2 ; Dinginkan pada suhu ≤ 6°C';
            } elseif (stripos($regen, 'HNO3') !== false) {
                $perlakuan = 'Tambahkan HNO3 sampai pH < 2 ; Dinginkan pada suhu ≤ 6°C';
            } elseif (stripos($regen, 'M100') !== false) {
                $perlakuan = 'Dinginkan pada suhu < 10°C';
            } else {
                $perlakuan = 'Dinginkan pada suhu ≤ 6°C';
            }

            $groupKey = md5($perlakuan . '|' . $wadah);

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'parameters' => [],
                    'wadah' => $wadah,
                    'volume' => 0,
                    'perlakuan' => $perlakuan,
                    'tipe_contoh' => 'S',
                    'holding_time' => '-'
                ];
            }

            $groups[$groupKey]['parameters'][] = $nama;
            $groups[$groupKey]['volume'] += $volume;
        }

        if (empty($groups)) return '';

        $showParameterColumn = false; // Set ke true jika sewaktu-waktu diminta untuk menampilkan kolom Parameter

        $html1 = '<br><br><table style="border-collapse: collapse; width: 100%; margin-bottom: 10px; font-size: ' . self::FPPS_FONT_ISI_TABEL . ';" border="1">
            <thead>
                <tr>
                    <th colspan="' . ($showParameterColumn ? '7' : '6') . '" style="text-align: center; background-color: #f2f2f2; padding: 2px;">INFORMASI PERENCANAAN PROSES SAMPLING</th>
                </tr>
                <tr>
                    <th style="padding: 2px; text-align: center; width: 5%;">No</th>';
        
        if ($showParameterColumn) {
            $html1 .= '<th style="padding: 2px; text-align: center; width: 25%;">Parameter</th>';
        }
        
        $html1 .= '
                    <th style="padding: 2px; text-align: center; width: ' . ($showParameterColumn ? '10' : '30') . '%;">Wadah</th>
                    <th style="padding: 2px; text-align: center; width: 15%;">Volume Contoh Uji (mL)</th>
                    <th style="padding: 2px; text-align: center; width: 10%;">Tipe Contoh</th>
                    <th style="padding: 2px; text-align: center; width: ' . ($showParameterColumn ? '25' : '30') . '%;">Perlakuan</th>
                    <th style="padding: 2px; text-align: center; width: 10%;">Holding Time</th>
                </tr>
            </thead>
            <tbody>';
        
        $no = 1;
        foreach ($groups as $g) {
            $paramsStr = implode(', ', $g['parameters']);
            $html1 .= '<tr>
                <td style="padding: 2px; text-align: center;">' . $no++ . '</td>';
            
            if ($showParameterColumn) {
                $html1 .= '<td style="padding: 2px;">' . $paramsStr . '</td>';
            }
            
            $html1 .= '
                <td style="padding: 2px; text-align: center;">' . $g['wadah'] . '</td>
                <td style="padding: 2px; text-align: center;">' . $g['volume'] . '</td>
                <td style="padding: 2px; text-align: center;">' . $g['tipe_contoh'] . '</td>
                <td style="padding: 2px;">' . $g['perlakuan'] . '</td>
                <td style="padding: 2px; text-align: center;">' . $g['holding_time'] . '</td>
            </tr>';
        }
        $html1 .= '</tbody></table>';

        $html2 = '<table style="border-collapse: collapse; width: 100%; margin-bottom: 10px; font-size: ' . self::FPPS_FONT_ISI_TABEL . ';" border="1">
            <thead>
                <tr>
                    <th colspan="' . ($showParameterColumn ? '5' : '4') . '" style="text-align: center; background-color: #f2f2f2; padding: 2px;">TOTAL WADAH CONTOH UJI</th>
                </tr>
                <tr>
                    <th style="padding: 2px; text-align: center; width: 5%;">NO</th>
                    <th style="padding: 2px; text-align: center; width: ' . ($showParameterColumn ? '35' : '50') . '%;">Perlakuan</th>';
        
        if ($showParameterColumn) {
            $html2 .= '<th style="padding: 2px; text-align: center; width: 35%;">Parameter</th>';
        }
        
        $html2 .= '
                    <th style="padding: 2px; text-align: center; width: ' . ($showParameterColumn ? '15' : '30') . '%;">Jenis Wadah</th>
                    <th style="padding: 2px; text-align: center; width: ' . ($showParameterColumn ? '10' : '15') . '%;">Volume (mL)</th>
                </tr>
            </thead>
            <tbody>';
        
        $no = 1;
        $totalVolumeAll = 0;
        foreach ($groups as $g) {
            $paramsStr = implode(', ', $g['parameters']);
            $vol = floatval($g['volume']);
            $totalVolumeAll += $vol;
            $html2 .= '<tr>
                <td style="padding: 2px; text-align: center;">' . $no++ . '</td>
                <td style="padding: 2px; text-align: center;">' . $g['perlakuan'] . '</td>';
            
            if ($showParameterColumn) {
                $html2 .= '<td style="padding: 2px; text-align: center;">' . $paramsStr . '</td>';
            }
            
            $html2 .= '
                <td style="padding: 2px; text-align: center;">' . $g['wadah'] . '</td>
                <td style="padding: 2px; text-align: center;">' . $vol . '</td>
            </tr>';
        }

        $html2 .= '<tr>
                <td colspan="' . ($showParameterColumn ? '4' : '3') . '" style="padding: 2px; text-align: center; font-weight: bold;">JUMLAH TOTAL</td>
                <td style="padding: 2px; text-align: center; font-weight: bold;">' . $totalVolumeAll . '</td>
            </tr>';

        $html2 .= '</tbody></table>';
        return $html1 . $html2;
    }

    private function getTemplateKategoriHTML($katName)
    {
        $katName = trim($katName);
    
        $sub1 = ['Air Limbah Domestik', 'Air Limbah Industri', 'Air Limbah', 'Air Limbah Terintegrasi', 'Air Lindi'];
        $sub2 = ['Air Bersih', 'Air Minum', 'Air Kolam Renang', 'Air Higiene Sanitasi', 'Air Khusus', 'Air Tanah', 'Air Mata Air', 'Air Reverse Osmosis'];
        $sub3 = ['Air Permukaan', 'Air Sungai', 'Air Danau', 'Air Waduk', 'Air Situ', 'Air Rawa', 'Air Muara', 'Air Laut'];
        $sub4 = ['Udara Ambient'];
        $sub5 = ['Emisi Sumber Tidak Bergerak', 'Emisi Isokinetik'];
        $sub6 = ['Emisi Kendaraan (Gas)', 'Emisi Kendaraan (Bensin)', 'Emisi Kendaraan (Solar)'];
        $sub7 = ['Kebauan'];
        $sub8 = ['Kebisingan (24 Jam)', 'Kebisingan (Indoor)', 'Kebisingan'];
        $sub9 = ['Getaran (Lengan & Tangan)', 'Getaran (Seluruh Tubuh)'];
    
        // Sub 10 -> TIDAK DIPROSES (kembalikan string kosong; ditangani oleh
        // getPlaceholderKategoriHTML() di pemanggilnya)
        $sub10 = [
            'Getaran', 'Getaran (Bangunan)', 'Getaran (Kejut Bangunan)', 'Getaran (Kenyamanan & Kesehatan)',
            'Getaran (Lingkungan)', 'Getaran (Mesin)', 'Iklim Kerja', 'Kualitas Udara Dalam Ruang',
            'Udara Lingkungan Kerja', 'Pencahayaan', 'Udara Umum', 'Mikrobiologi Udara', 'Udara Swab Test',
            'Udara Kultur Bangunan', 'Ergonomi', 'Kelembapan', 'Higiene Sanitasi', 'Psikologi', 'Udara Angka Kuman'
        ];
    
        if (in_array($katName, $sub10)) {
            return "";
        }
    
        // Ukuran font isi tabel form disamakan untuk SEMUA kategori (lihat catatan di atas).
        $fs = self::FPPS_FONT_ISI_TABEL;
        $tableStyle = 'font-size: ' . $fs . '; text-align: center; border-collapse: collapse; table-layout: fixed;';
    
        $html = '<div style="font-family: sans-serif;">';
    
        // ================== LOGIKA SUB KATEGORI 1, 2, 3 ==================
        if (in_array($katName, $sub1) || in_array($katName, $sub2) || in_array($katName, $sub3)) {
            $isSub1 = in_array($katName, $sub1);
            $isSub2 = in_array($katName, $sub2);
    
            $html .= '
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#fff2cc;">
                    <th width="30%">KATEGORI</th><th width="50%">ALAT/BAHAN</th><th width="10%">Jumlah</th><th width="10%">Ceklis</th>
                </tr>
                <tr><td style="font-weight:bold;">Tujuan Pengambilan Sampel</td><td>Pemantauan Kualitas Air Terhadap Lingkungan Hidup</td><td colspan="2">V</td></tr>
                <tr>
                    <td rowspan="4" style="font-weight:bold;">Alat Pengambilan Contoh Uji</td>
                    <td>Sederhana(Gayung)</td><td>2</td><td>V</td>
                </tr>
                <tr><td>Sederhana(Botol)</td><td>2</td><td>V</td></tr>
                <tr><td>'. ($isSub2 ? 'Bailer' : 'Well Water Sampler') .'</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>'. ($isSub2 ? 'Well Water Sampler' : 'Horizontal/Vertikal Water Sampler') .'</td><td>1</td><td>'. ($isSub1 || $isSub2 ? '-' : 'V') .'</td></tr>
    
                <tr>
                    <td rowspan="10" style="font-weight:bold;">Alat Ukur Parameter Lapangan</td>
                    <td>pH Meter</td><td>1</td><td>V</td>
                </tr>
                <tr><td>Thermometer</td><td>1</td><td>V</td></tr>
                <tr><td>Conductivity Meter</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>DO Meter</td><td>1</td><td>V</td></tr>
                <tr><td>Thermohygrometer</td><td>1</td><td>V</td></tr>
                <tr><td>Chlorine Meter</td><td>1</td><td>V</td></tr>
                <tr><td>GPS</td><td>1</td><td>V</td></tr>
                <tr><td>Secchi Disk</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>Alat Ukur Kedalaman</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>Meteran</td><td>1</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="9" style="font-weight:bold;">Alat Pendukung</td>
                    <td>Pipet Tetes</td><td>1</td><td>V</td>
                </tr>
                <tr><td>pH Kertas Universal</td><td>1</td><td>V</td></tr>
                <tr><td>Coolbox</td><td>1</td><td>V</td></tr>
                <tr><td>P3K</td><td>1</td><td>V</td></tr>
                <tr><td>Ember Tampung</td><td>1</td><td>V</td></tr>
                <tr><td>Vakum Penyaringan</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>Corong Penyaringan</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>Kabel Roll</td><td>1</td><td>'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>Tali Tambang</td><td>1</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="4" style="font-weight:bold;">Dokumen Pendukung</td>
                    <td>Surat Tugas Pengambilan Sampel</td><td>1</td><td>V</td>
                </tr>
                <tr><td>Berita Acara Sampling</td><td>1</td><td>V</td></tr>
                <tr><td>Jobs Safety Analisis</td><td>1</td><td>V</td></tr>
                <tr><td>Form Perencanaan Sampling</td><td>1</td><td>V</td></tr>
            </table>
            <br>
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr>
                    <td rowspan="9" width="30%" style="font-weight:bold; background-color:#fff2cc;">Jenis Bahan Wadah</td>
                    <td width="50%">Form Pengambilan Sampel</td><td width="10%">1</td><td width="10%">V</td>
                </tr>
                <tr><td>Chain Of Custody</td><td>1</td><td>V</td></tr>
                <tr><td>Polyethylene (PE) 500 mL</td><td>8</td><td>V</td></tr>
                <tr><td>Polyethylene (PE) 1000 mL</td><td>8</td><td>V</td></tr>
                <tr><td>Botol Winkler 300 mL</td><td>4</td><td>V</td></tr>
                <tr><td>Amber Glass 100 mL</td><td>4</td><td>V</td></tr>
                <tr><td>Amber Glass 250 mL</td><td>4</td><td>V</td></tr>
                <tr><td>Amber Glass 250 mL (Steril)</td><td>4</td><td>V</td></tr>
                <tr><td>Glass 1000 mL</td><td>2</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="3" style="font-weight:bold; background-color:#fff2cc;">Penanganan Wadah Contoh</td>
                    <td>Dibilas dengan 1:1 HNO3</td><td>8</td><td>V</td>
                </tr>
                <tr><td>Dibilas dengan 1:1 HCl</td><td>2</td><td>V</td></tr>
                <tr><td>Botol Steril</td><td>4</td><td>V</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
                <tr><td>a</td><td>Frekuensi Pengambilan (Kali)</td><td colspan="2">1</td></tr>
                <tr><td>b</td><td>Waktu Pengambilan (Menit)</td><td colspan="2">60</td></tr>
                <tr><td>c</td><td>Dokumentasi Lokasi</td><td colspan="2">Dengan Foto</td></tr>
                <tr><td>d</td><td>Dokumentasi Titik Sampling</td><td colspan="2">Dengan Foto</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN PROSES SAMPEL</td></tr>
                <tr>
                    <td rowspan="9" style="font-weight:bold; background-color:#fff2cc;">Pengawetan</td>
                    <td>Pendinginan suhu <= 6°C</td><td colspan="2">V</td>
                </tr>
                <tr><td>Pendinginan suhu <= 10°C</td><td colspan="2">V</td></tr>
                <tr><td>HNO3 Pekat</td><td colspan="2">'. ($isSub1 ? '-' : 'V') .'</td></tr>
                <tr><td>H2SO4 Pekat</td><td colspan="2">V</td></tr>
                <tr><td>HCl</td><td colspan="2">V</td></tr>
                <tr><td>Zink Asetat</td><td colspan="2">'. ($isSub1 ? 'V' : '-') .'</td></tr>
                <tr><td>Dinatrium EDTA</td><td colspan="2">'. ($isSub1 ? 'V' : '-') .'</td></tr>
                <tr><td>NaOH Pekat</td><td colspan="2">'. ($isSub1 ? 'V' : '-') .'</td></tr>
                <tr><td>Sodium Thiosulfat</td><td colspan="2">'. ($isSub1 ? 'V' : '-') .'</td></tr>
    
                <tr>
                    <td rowspan="3" style="font-weight:bold; background-color:#fff2cc;">Teknik Pengambilan</td>
                    <td>Sesaat</td><td colspan="2">V</td>
                </tr>
                <tr><td>Gabungan Tempat</td><td colspan="2">-</td></tr>
                <tr><td>Gabungan Waktu</td><td colspan="2">-</td></tr>
            </table>';
        }
    
        // ================== LOGIKA SUB KATEGORI 4 ==================
        elseif (in_array($katName, $sub4)) {
            $html .= '
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#fff2cc;">
                    <th width="30%">KATEGORI</th><th width="50%">ALAT/BAHAN</th><th width="10%">Jumlah</th><th width="10%">Ceklis</th>
                </tr>
                <tr><td style="font-weight:bold;">Tujuan Pengambilan Sampel</td><td>Pemantauan Kualitas Udara Terhadap Lingkungan Hidup</td><td colspan="2">V</td></tr>
                <tr>
                    <td rowspan="4" style="font-weight:bold;">Alat Pengambilan Contoh Uji</td>
                    <td>Impinger Udara</td><td>2</td><td>V</td>
                </tr>
                <tr><td>HVAS (TSP)</td><td>2</td><td>V</td></tr>
                <tr><td>HVAS (PM10)</td><td>2</td><td>V</td></tr>
                <tr><td>HVAS (PM2,5)</td><td>2</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="8" style="font-weight:bold;">Alat Ukur Parameter Lapangan</td>
                    <td>CO Analyzer</td><td>1</td><td>V</td>
                </tr>
                <tr><td>Spektrofotometer</td><td>1</td><td>V</td></tr>
                <tr><td>Barometer</td><td>1</td><td>V</td></tr>
                <tr><td>Thermohygrometer</td><td>2</td><td>V</td></tr>
                <tr><td>Anemometer</td><td>2</td><td>V</td></tr>
                <tr><td>Kompas</td><td>2</td><td>V</td></tr>
                <tr><td>GPS</td><td>1</td><td>V</td></tr>
                <tr><td>Stopwatch</td><td>2</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="11" style="font-weight:bold;">Alat Pendukung</td>
                    <td>Kabel Roll</td><td>8</td><td>V</td>
                </tr>
                <tr><td>Genset</td><td>1</td><td>-</td></tr>
                <tr><td>Kuvet</td><td>5</td><td>V</td></tr>
                <tr><td>Aquadest</td><td>2</td><td>V</td></tr>
                <tr><td>Toolbox</td><td>1</td><td>V</td></tr>
                <tr><td>Tripot</td><td>2</td><td>V</td></tr>
                <tr><td>Pinset</td><td>2</td><td>V</td></tr>
                <tr><td>Pipet Volume</td><td>5</td><td>V</td></tr>
                <tr><td>Gelas Ukur</td><td>2</td><td>V</td></tr>
                <tr><td>Kuas</td><td>2</td><td>V</td></tr>
                <tr><td>Pre-Filter</td><td>1</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="6" style="font-weight:bold;">K3 / ALAT PELINDUNG DIRI</td>
                    <td>Masker</td><td>4</td><td>V</td>
                </tr>
                <tr><td>Sarung Tangan</td><td>4</td><td>V</td></tr>
                <tr><td>Kacamata Safety</td><td>4</td><td>V</td></tr>
                <tr><td>Helm Safety</td><td>4</td><td>V</td></tr>
                <tr><td>Baju Lengan Panjang</td><td>4</td><td>V</td></tr>
                <tr><td>Sepatu Safety</td><td>4</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="2" style="font-weight:bold;">Jenis Bahan Wadah Penyimpanan</td>
                    <td>Plastik</td><td>6</td><td>V</td>
                </tr>
                <tr><td>Glass</td><td>6</td><td>V</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
                <tr><td colspan="2">Frekuensi Pengambilan (Kali)</td><td colspan="2">1</td></tr>
                <tr><td colspan="2">Waktu Pengambilan (Menit)</td><td colspan="2">24 Jam</td></tr>
                <tr><td colspan="2">Dokumentasi Lokasi</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Dokumentasi Titik Sampling</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Acuan Metode Sampling</td><td colspan="2">IKM/ISL/7.3.11 - IKM/ISL/7.3.5 - SNI 7119.14-2016 - SNI 7119.15-2016 SNI 8457:2017</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN PENGATURAN JAMINAN TERHADAP SAMPEL</td></tr>
                <tr>
                    <td rowspan="3" style="font-weight:bold;">Pengendalian Mutu</td>
                    <td colspan="2">Blanko Lapangan</td><td>V</td>
                </tr>
                <tr><td colspan="2">Blanko Peralatan</td><td>-</td></tr>
                <tr><td colspan="2">Uji Kinerja Alat</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="4" style="font-weight:bold;">Pengamanan Contoh</td>
                    <td colspan="2">Identifikasi Contoh</td><td>V</td>
                </tr>
                <tr><td colspan="2">Segel Wadah</td><td>V</td></tr>
                <tr><td colspan="2">Box Khusus Sampel</td><td>V</td></tr>
                <tr><td colspan="2">Tindakan Pencegahan Selama Transportasi</td><td>V</td></tr>
            </table>';
        }
    
        // ================== LOGIKA SUB KATEGORI 5 & 6 ==================
        elseif (in_array($katName, $sub5) || in_array($katName, $sub6)) {
            $isSub5 = in_array($katName, $sub5);
    
            $html .= '
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#f2f2f2;">
                    <th width="5%">No</th><th width="35%">Kategori</th><th width="60%" colspan="2">Nama Alat/bahan</th>
                </tr>
                <tr><td>a</td><td>Tujuan Pengambilan Sampel</td><td colspan="2">Monitoring Lingkungan</td></tr>
                <tr><td>b</td><td>Frekuensi Pengambilan</td><td colspan="2">'. ($isSub5 ? 'Form survei' : '3 kali pengukuran') .'</td></tr>
                <tr><td>c</td><td>Durasi Pengambilan</td><td colspan="2">'. ($isSub5 ? '4 Jam' : '2 Jam') .'</td></tr>
                <tr><td>d</td><td>Dokumentasi Lokasi</td><td colspan="2">'. ($isSub5 ? 'Form Survei' : 'Form Survei : ISL-02-FSTA-250125') .'</td></tr>
                <tr><td>e</td><td>Dokumentasi Titik Sampling</td><td colspan="2">'. ($isSub5 ? 'Form Survei' : 'Form Survei : ISL-02-FSTA-250125') .'</td></tr>
    
                <tr style="background-color:#f2f2f2;"><td colspan="4" style="font-weight:bold;">Acuan Metode Sampling</td></tr>';
    
                if ($isSub5) {
                    $html .= '
                    <tr>
                        <td style="font-weight:bold;">Parameter</td><td style="font-weight:bold;">Metode Sampling</td>
                        <td style="font-weight:bold;">Parameter</td><td style="font-weight:bold;">Metode Sampling</td>
                    </tr>
                    <tr><td>CO</td><td>IKM/ISL/7.2.147 (Combustion Gas Analyzer)</td><td>Iso-Velo</td><td>SNI 7117.14 : 2009</td></tr>
                    <tr><td>NO2</td><td>IKM/ISL/7.2.148 (Elektrokimia)</td><td>Iso-DMW</td><td>SNI 7117.15 : 2009</td></tr>
                    <tr><td>SO2</td><td>IKM/ISL/7.2.49 (Elektrokimia)</td><td>Iso-Moisture</td><td>SNI 7117.16 : 2009</td></tr>
                    <tr><td>Opasitas</td><td>SNI 19-7117.11 : 2005</td><td>Iso-Percent</td><td>SNI 7117.17 : 2009</td></tr>';
                } else {
                    $html .= '
                    <tr><td colspan="2" style="font-weight:bold;">Parameter</td><td colspan="2" style="font-weight:bold;">Metode Sampling</td></tr>
                    <tr><td colspan="2">CO</td><td colspan="2">SNI 09.7118.1:2005 , SNI 09.7118.3:2005</td></tr>
                    <tr><td colspan="2">HC</td><td colspan="2">SNI 09.7118.1:2005 , SNI 09.7118.3:2005</td></tr>
                    <tr><td colspan="2">Opasitas</td><td colspan="2">SNI 7118-2:2018</td></tr>';
                }
    
            $html .= '
            </table><br>
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#f2f2f2;">
                    <th width="30%">Kategori</th><th width="50%">Nama Alat</th><th width="10%">Jumlah</th><th width="10%">Ceklis</th>
                </tr>
                <tr>
                    <td rowspan="4" style="font-weight:bold;">Alat Pengambilan Contoh Uji</td>
                    <td>'. ($isSub5 ? 'Combustion Gas Analyzer' : 'Automotive Gas Analyzer') .'</td><td>1</td><td>V</td>
                </tr>
                <tr><td>'. ($isSub5 ? 'Ringelmann Smoke Opacity Meter' : 'Smoke Opacimeter') .'</td><td>1</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Isokinetik Sampling Train Method 5' : 'Barometer') .'</td><td>1</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Isokinetik Sampling Train Method 29' : 'Thermohygrometer') .'</td><td>1</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="9" style="font-weight:bold;">Alat Pendukung</td>
                    <td>Kabel Roll</td><td>2</td><td>V</td>
                </tr>
                <tr><td>'. ($isSub5 ? 'Gelas Ukur 100 mL' : 'Tissue') .'</td><td>2</td><td>V</td></tr>
                <tr><td>Kuas</td><td>1</td><td>V</td></tr>
                <tr><td>Silika Gel</td><td>'. ($isSub5 ? '2' : '-') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Aquadest' : 'Sikat Pipa Tube Brushes') .'</td><td>'. ($isSub5 ? '2' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Toolbox' : 'GPS') .'</td><td>1</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Tripot' : 'Stopwatch') .'</td><td>1</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Pinset' : 'Meteran') .'</td><td>2</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Holder Oven' : 'Jangka Sorong') .'</td><td>'. ($isSub5 ? '2' : '1') .'</td><td>V</td></tr>
    
                <tr>
                    <td rowspan="9" style="font-weight:bold;">K3 / ALAT PELINDUNG DIRI</td>
                    <td>'. ($isSub5 ? 'Body Harnest' : 'Respirator') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td>
                </tr>
                <tr><td>'. ($isSub5 ? 'Respirator' : 'Sarung Tangan Keselamatan(Panas)') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Sarung Tangan Keselamatan(Panas)' : 'Sarung Tangan Keselamatan(Nitril)') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Sarung Tangan Keselamatan(Nitril)' : 'Kacamata Safety') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Kacamata Safety' : 'Helm Safety') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Helm Safety' : 'Baju Lengan Panjang') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Baju Lengan Panjang' : 'Ear Plug') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>'. ($isSub5 ? 'Ear Plug' : 'Sepatu Safety') .'</td><td>'. ($isSub5 ? '3' : '1') .'</td><td>V</td></tr>
                <tr><td>Sepatu Safety</td><td>3</td><td>V</td></tr>
            </table>';
        }
    
        // ================== LOGIKA SUB KATEGORI 7 ==================
        elseif (in_array($katName, $sub7)) {
            $html .= '
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#fff2cc;">
                    <th width="30%">KATEGORI</th><th width="50%">ALAT/BAHAN</th><th width="10%">Jumlah</th><th width="10%">Ceklis</th>
                </tr>
                <tr><td style="font-weight:bold;">Tujuan Pengambilan Sampel</td><td>Pemantauan Kualitas Udara Terhadap Lingkungan Hidup</td><td colspan="2">V</td></tr>
                <tr><td rowspan="3" style="font-weight:bold;">Alat Pengambilan Contoh Uji</td><td>Impinger Udara</td><td>2</td><td>V</td></tr>
                <tr><td>Pump</td><td>2</td><td>-</td></tr>
                <tr><td>Dray Gas Meter</td><td>1</td><td>-</td></tr>
                <tr><td rowspan="6" style="font-weight:bold;">Alat Ukur Parameter Lapangan</td><td>Barometer</td><td>1</td><td>V</td></tr>
                <tr><td>Thermohygrometer</td><td>2</td><td>V</td></tr>
                <tr><td>Anemometer</td><td>2</td><td>V</td></tr>
                <tr><td>Kompas</td><td>2</td><td>V</td></tr>
                <tr><td>GPS</td><td>1</td><td>V</td></tr>
                <tr><td>Stopwatch</td><td>2</td><td>V</td></tr>
                <tr><td rowspan="9" style="font-weight:bold;">Alat Pendukung</td><td>Kabel Roll</td><td>2</td><td>V</td></tr>
                <tr><td>Genset</td><td>1</td><td>-</td></tr>
                <tr><td>Aquadest</td><td>2</td><td>V</td></tr>
                <tr><td>Toolbox</td><td>1</td><td>V</td></tr>
                <tr><td>Tripot</td><td>2</td><td>V</td></tr>
                <tr><td>Pinset</td><td>2</td><td>V</td></tr>
                <tr><td>Pipet Volume</td><td>2</td><td>V</td></tr>
                <tr><td>Gelas Ukur</td><td>2</td><td>V</td></tr>
                <tr><td>Pre-Filter</td><td>1</td><td>V</td></tr>
                <tr><td rowspan="6" style="font-weight:bold;">K3 / ALAT PELINDUNG DIRI</td><td>Masker</td><td>4</td><td>V</td></tr>
                <tr><td>Sarung Tangan</td><td>4</td><td>V</td></tr>
                <tr><td>Kacamata Safety</td><td>4</td><td>V</td></tr>
                <tr><td>Helm Safety</td><td>4</td><td>V</td></tr>
                <tr><td>Baju Lengan Panjang</td><td>4</td><td>V</td></tr>
                <tr><td>Sepatu Safety</td><td>4</td><td>V</td></tr>
                <tr><td rowspan="2" style="font-weight:bold;">Jenis Bahan Wadah Penyimpanan</td><td>Plastik</td><td>6</td><td>V</td></tr>
                <tr><td>Botol Glass</td><td>6</td><td>V</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
                <tr><td colspan="2">Frekuensi Pengambilan (Kali)</td><td colspan="2">1</td></tr>
                <tr><td colspan="2">Waktu Pengambilan (Menit)</td><td colspan="2">60 Menit</td></tr>
                <tr><td colspan="2">Dokumentasi Lokasi</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Dokumentasi Titik Sampling</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Acuan Metode Sampling</td><td colspan="2">IKM/ISL/7.3.11 - IKM/ISL/7.3.5 - SNI 7119.1-2005</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN PENGATURAN JAMINAN TERHADAP SAMPEL</td></tr>
                <tr><td rowspan="3" style="font-weight:bold;">Pengendalian Mutu</td><td colspan="2">Blanko Lapangan</td><td>V</td></tr>
                <tr><td colspan="2">Blanko Peralatan</td><td>-</td></tr>
                <tr><td colspan="2">Uji Kinerja Alat</td><td>V</td></tr>
                <tr><td rowspan="4" style="font-weight:bold;">Pengamanan Contoh</td><td colspan="2">Identifikasi Contoh</td><td>V</td></tr>
                <tr><td colspan="2">Segel Wadah</td><td>V</td></tr>
                <tr><td colspan="2">Box Khusus Sampel</td><td>V</td></tr>
                <tr><td colspan="2">Tindakan Pencegahan Selama Transportasi ke laboratorium</td><td>V</td></tr>
                <tr><td rowspan="2" style="font-weight:bold;">Alat Pengujian</td><td colspan="2">Form Uji Kinerja Alat</td><td>V</td></tr>
                <tr><td colspan="2">Sertifikat Kalibrasi Alat</td><td>V</td></tr>
            </table>';
        }
    
        // ================== LOGIKA SUB KATEGORI 8 ==================
        elseif (in_array($katName, $sub8)) {
            $html .= '
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#fff2cc;">
                    <th width="30%">KATEGORI</th><th width="50%">ALAT/BAHAN</th><th width="10%">Jumlah</th><th width="10%">Ceklis</th>
                </tr>
                <tr><td style="font-weight:bold;">Tujuan Pengambilan Sampel</td><td>Pemantauan Kualitas Kebisingan Terhadap Lingkungan Hidup</td><td colspan="2">V</td></tr>
                <tr><td rowspan="5" style="font-weight:bold;">Alat Ukur Parameter Lapangan</td><td>Thermohygrometer</td><td>2</td><td>V</td></tr>
                <tr><td>GPS</td><td>1</td><td>V</td></tr>
                <tr><td>Sound Level Meter</td><td>1</td><td>V</td></tr>
                <tr><td>Sound Calibrator</td><td>1</td><td>V</td></tr>
                <tr><td>Stopwatch</td><td>2</td><td>V</td></tr>
                <tr><td rowspan="3" style="font-weight:bold;">Alat Pendukung</td><td>Tripot</td><td>1</td><td>V</td></tr>
                <tr><td>Windscreen</td><td>1</td><td>V</td></tr>
                <tr><td>Kabel Roll</td><td>1</td><td>V</td></tr>
                <tr><td rowspan="8" style="font-weight:bold;">K3 / ALAT PELINDUNG DIRI</td><td>Masker</td><td>2</td><td>V</td></tr>
                <tr><td>Sarung Tangan</td><td>2</td><td>V</td></tr>
                <tr><td>Earmuff</td><td>2</td><td>V</td></tr>
                <tr><td>Earplug</td><td>2</td><td>V</td></tr>
                <tr><td>Kacamata Safety</td><td>2</td><td>V</td></tr>
                <tr><td>Helm Safety</td><td>2</td><td>V</td></tr>
                <tr><td>Baju Lengan Panjang</td><td>2</td><td>V</td></tr>
                <tr><td>Sepatu Safety</td><td>2</td><td>V</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
                <tr><td colspan="2">Frekuensi Pengambilan (Kali)</td><td colspan="2">24</td></tr>
                <tr><td colspan="2">Waktu Pengambilan (Menit)</td><td colspan="2">10</td></tr>
                <tr><td colspan="2">Dokumentasi Lokasi</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Dokumentasi Titik Sampling</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Acuan Metode Sampling</td><td colspan="2">SNI 7231.2009 - SNI 8427:2017</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN PENGATURAN JAMINAN TERHADAP SAMPEL</td></tr>
                <tr><td rowspan="2" style="font-weight:bold;">Pengendalian Mutu</td><td colspan="2">Form Uji Kinerja Alat</td><td>V</td></tr>
                <tr><td colspan="2">Sertifikat Kalibrasi Alat</td><td>V</td></tr>
            </table>';
        }
    
        // ================== LOGIKA SUB KATEGORI 9 ==================
        elseif (in_array($katName, $sub9)) {
            $html .= '
            <table border="1" cellpadding="4" cellspacing="0" width="100%" style="' . $tableStyle . '">
                <tr style="background-color:#fff2cc;">
                    <th width="30%">KATEGORI</th><th width="50%">ALAT/BAHAN</th><th width="10%">Jumlah</th><th width="10%">Ceklis</th>
                </tr>
                <tr><td style="font-weight:bold;">Tujuan Pengambilan Sampel</td><td>Menilai tingkat pajanan getaran pada pekerja selama melakukan aktivitas kerja</td><td colspan="2">V</td></tr>
                <tr><td rowspan="2" style="font-weight:bold;">Alat Ukur Parameter Lapangan</td><td>Human Vibration meter</td><td>2</td><td>V</td></tr>
                <tr><td>Stopwatch</td><td>2</td><td>V</td></tr>
                <tr><td rowspan="8" style="font-weight:bold;">K3 / ALAT PELINDUNG DIRI</td><td>Masker</td><td>2</td><td>V</td></tr>
                <tr><td>Sarung Tangan</td><td>2</td><td>V</td></tr>
                <tr><td>Earmuff</td><td>2</td><td>V</td></tr>
                <tr><td>Earplug</td><td>2</td><td>V</td></tr>
                <tr><td>Kacamata Safety</td><td>2</td><td>V</td></tr>
                <tr><td>Helm Safety</td><td>2</td><td>V</td></tr>
                <tr><td>Baju Lengan Panjang</td><td>2</td><td>V</td></tr>
                <tr><td>Sepatu Safety</td><td>2</td><td>V</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
                <tr><td colspan="2">Frekuensi Pengambilan (Kali)</td><td colspan="2">1</td></tr>
                <tr><td colspan="2">Waktu Pengambilan (Menit)</td><td colspan="2">108</td></tr>
                <tr><td colspan="2">Dokumentasi Lokasi</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Dokumentasi Titik Sampling</td><td colspan="2">Dengan Foto / Video</td></tr>
                <tr><td colspan="2">Acuan Metode Sampling</td><td colspan="2">SNI 7186.2021 - SNI 7054.2019</td></tr>
    
                <tr><td colspan="4" style="font-weight:bold; background-color:#fff2cc;">INFORMASI PERENCANAAN PENGATURAN JAMINAN TERHADAP SAMPEL</td></tr>
                <tr><td rowspan="2" style="font-weight:bold;">Pengendalian Mutu</td><td colspan="2">Form Uji Kinerja Alat</td><td>V</td></tr>
                <tr><td colspan="2">Sertifikat Kalibrasi Alat</td><td>V</td></tr>
                <tr><td rowspan="4" style="font-weight:bold;">Pengamanan Contoh</td><td colspan="2">Identifikasi Contoh</td><td>V</td></tr>
                <tr><td colspan="2">Segel Wadah</td><td>V</td></tr>
                <tr><td colspan="2">Box Khusus Sampel</td><td>V</td></tr>
                <tr><td colspan="2">Tindakan Pencegahan Selama Transportasi ke laboratorium</td><td>V</td></tr>
            </table>';
        }
    
        $html .= '</div>';
        
        // Mengganti semua karakter 'V' (sebagai ceklis) menjadi simbol checkmark kotak klasik (☑) warna abu-abu
        $html = str_replace('>V<', '><span style="font-family: DejaVu Sans; font-size: 16px; color: #555555;">&#9745;</span><', $html);

        return $html;
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
                $DB->is_downloaded_stps = true;
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
                $DB->is_printed_stps = true;
                $DB->save();
                return response()->json(["message"=>"succes","status"=>true],200);
            }
            
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],500);
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function tanggal_indonesia($tanggal, $mode = '')
    {
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $hari_map = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => "Jum'at", 'Sat' => 'Sabtu'];

        $hari = $hari_map[date('D', strtotime($tanggal))];
        $var = explode('-', $tanggal);

        if ($mode == 'period')
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        if ($mode == 'hari')
            return $hari . ' / ' . $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];

        return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
    }
}
