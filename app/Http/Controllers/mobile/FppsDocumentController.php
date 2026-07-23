<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\PersiapanSampelHeader;
use App\Models\MasterKaryawan;

use Carbon\Carbon;

use App\Models\Lims\OrderDetail;
use App\Models\Lims\OrderHeader;
use App\Models\Parameter;

class FppsDocumentController extends Controller
{
    /**
     * Menampilkan daftar FPPS untuk sampler yang sedang login.
     * Data diambil dari logic yang sama dengan LimsFppsController@index,
     * namun tanpa DataTables dan difilter per user login.
     */
    public function index(Request $request)
    {
        try {
            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)
                ->whereHas('jabatan', function ($q) {
                    $q->where('nama_jabatan', 'like', '%IT Programming%');
                })
                ->exists();
            $periode_awal = Carbon::now()->startOfMonth()->toDateString(); 
            $periode_akhir = Carbon::now()->endOfMonth()->toDateString();
            $existingWork = DB::table('persiapan_sampel_header')
                ->select('no_order', 'tanggal_sampling', 'sampler_jadwal', 'is_downloaded_stps', 'is_printed_stps')
                ->where('is_active', true)
                ->whereBetween('tanggal_sampling', [$periode_awal, $periode_akhir])
                ->get();

            $doneList = [];

            

            foreach ($existingWork as $row) {
                $headerSamplers = explode(',', $row->sampler_jadwal ?? '');
                foreach ($headerSamplers as $name) {
                    $cleanName = strtolower(trim($name));
                    if (empty($cleanName)) continue;
                    $key = sprintf('%s|%s|%s',
                        trim($row->no_order),
                        trim($row->tanggal_sampling),
                        $cleanName
                    );
                    $doneList[$key] = [
                        'is_proccess' => true,
                        'is_downloaded_stps' => $row->is_downloaded_stps,
                        'is_printed_stps' => $row->is_printed_stps
                    ];
                }
            }

            $myPrivileges = $this->privilageCabang;
            $isOrangPusat = in_array("1", $myPrivileges);

            $query = OrderDetail::query();
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
                        ->where('is_active', true);
                },
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                }
            ])
            ->select(['id_order_header', 'no_order', 'kategori_1', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [$periode_awal, $periode_akhir])
            ->get();

            $cabangMap = [
                1 => 'HEAD OFFICE',
                4 => 'RO-KARAWANG',
                5 => 'RO-PEMALANG'
            ];

            $groupedData = [];

            foreach ($data as $item) {
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {
                    continue;
                }

                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                $targetPlan = null;

                if ($periode) {
                    $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
                }
                if (!$targetPlan) {
                    $targetPlan = $orderHeader->sampling->first();
                }
                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                    continue;
                }

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

                foreach ($targetPlan->jadwal as $schedule) {
                    if ($schedule->tanggal !== $item->tanggal_sampling) {
                        continue;
                    }

                    // Filter: hanya tampilkan jadwal milik sampler yang login (kecuali programmer)
                    if (!$isProgrammer) {
                        $samplers = array_map('trim', explode(',', $schedule->sampler ?? ''));
                        if (!in_array(trim($this->karyawan), $samplers)) {
                            continue;
                        }
                    }

                    $currentSamplers = explode(',', $schedule->sampler ?? '');
                    $pendingSamplers = [];
                    $statusRow = [
                        'is_proccess' => false,
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

                        if (isset($doneList[$checkKey])) {
                            $pendingSamplers[] = trim($singleSampler);
                            $dataDb = $doneList[$checkKey];
                            $statusRow['is_proccess'] = true;
                            $statusRow['is_downloaded_stps'] = $dataDb['is_downloaded_stps'];
                            $statusRow['is_printed_stps'] = $dataDb['is_printed_stps'];
                        }
                    }

                    // Jika pending kosong (belum ada persiapan sama sekali), tetap tampilkan
                    if (empty($pendingSamplers)) {
                        // Ambil semua sampler (belum ada yang selesai)
                        $pendingSamplers = array_filter(array_map('trim', $currentSamplers));
                    }

                    $schedule->sampler = implode(',', $pendingSamplers);
                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $namaCabang = $cabangMap[$schedule->id_cabang] ?? 'HEAD OFFICE (Default)';

                    $key = $orderHeader->no_document . '|' .
                        $item->no_order . '|' .
                        $schedule->tanggal . '|' .
                        $schedule->jam_mulai . '|' .
                        $kategori;

                    if (isset($groupedData[$key])) {
                        $existingSamplers = explode(',', $groupedData[$key]['sampler']);
                        $newSamplers = explode(',', $schedule->sampler ?? '');
                        $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                        $groupedData[$key]['sampler'] = implode(',', array_filter($merged));
                    } else {
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
                            'is_downloaded'      => (int) $statusRow['is_downloaded_stps'],
                            'is_printed'         => (int) $statusRow['is_printed_stps'],
                            'is_proccess'        => $statusRow['is_proccess'],
                        ];
                    }
                }
            }

            return response()->json([
                'data' => array_values($groupedData)
            ], 200);

        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }

    /**
     * Proxy ke LimsFppsController@cetakFpps untuk generate PDF FPPS.
     * Menggunakan instance dari LimsFppsController agar logic PDF tidak duplikat.
     */
    public function cetakFpps(Request $request)
    {
        try {
            $controller = app(\App\Http\Controllers\api\LimsFppsController::class);
            return $controller->cetakFpps($request);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }
}
