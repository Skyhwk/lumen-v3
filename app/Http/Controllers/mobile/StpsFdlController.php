<?php

namespace App\Http\Controllers\mobile;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\PersiapanSampelHeader;
use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\QrDocument;
use App\Models\MasterKaryawan;
use App\Http\Controllers\api\PersiapanSampleController;

use Carbon\Carbon;

use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Parameter;
use Mpdf\Mpdf;

class StpsFdlController extends Controller
{

    public function index(Request $request)
    {
        try {
            $periode_awal = Carbon::parse($request->periode_awal); // format dari frontend YYYY-MM
            $periode_akhir = Carbon::parse($request->periode_akhir)->endOfMonth(); // mengambil tanggal terakhir dari bulan terpilih
            $interval = $periode_awal->diff($periode_akhir);

            $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)
                ->whereIn('id_jabatan', [41, 42])
                ->exists();

            $karyawan = $this->karyawan;

            $periodeDate = Carbon::parse($request->periode_awal);
            $periode     = $periodeDate->format('Y-m'); // 👉 "2026-01"

            if ($interval->days > 91)
                return response()->json(['message' => 'Periode tidak boleh lebih dari 1 bulan'], 403);

            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.persiapanSampelHeaderFdl' => function ($q) use ($periode) {
                    $q->where('periode', $periode)
                    ->where('is_active', true);
                },
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'periode', 'id_cabang', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'periode', 'id_cabang']);
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                ->whereBetween('tanggal_sampling', [
                    $periode_awal->format('Y-m-01'),
                    $periode_akhir->format('Y-m-t')
                ])
                // ->where('no_quotation', 'ISL/QTC/24-I/001924')
                ->groupBy(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling']);

            $data = $data->get()->toArray();
            // dd($data);
            $formattedData = array_reduce($data, function ($carry, $item) use ($isProgrammer, $karyawan, $periode_awal) {
                if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                    return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ?
                    current(array_filter(
                        $samplingPlan,
                        fn($plan) =>
                        isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode
                    )) :
                    current($samplingPlan);
                if (!$targetPlan)
                    return $carry;

                $jadwal = $targetPlan['jadwal'] ?? [];

                $ps = $item['order_header']['persiapan_sampel_header_fdl'] ?? null;
                $rawFilename = $ps ? $ps['filename'] : null;

                // replace PS → STPS
                $filename = $rawFilename
                    ? str_replace('_', '-', str_replace('PS', 'STPS', $rawFilename))
                    : null;

                $results = [];

                foreach ($jadwal as $schedule) {
                    // ===== FILTER SAMPLER =====
                    $samplers = array_map('trim', explode(',', $schedule['sampler']));
                    if (!$isProgrammer && $karyawan && !in_array($karyawan, $samplers)) {
                        continue;
                    }

                    // ===== FILTER TANGGAL + DURASI =====
                    $jadwalMulai    = Carbon::parse($schedule['tanggal'])->startOfDay();
                    $durasi = (int) ($schedule['durasi'] ?? 0);
                    $jadwalSelesai  = Carbon::parse($schedule['tanggal'])->addDays($durasi)->endOfDay();

                    $tanggalSampling = Carbon::parse($periode_awal);
                    // kurang dari jadwal mulai atau lebih dari jadwal selesai
                    if ($tanggalSampling->lt($jadwalMulai) || $tanggalSampling->gt($jadwalSelesai)) {
                        continue;
                    }

                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        // dump(json_decode($schedule['sampler'], true));
                        $results[] = [
                            'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                            'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                            'status_sampling' => $item['kategori_1'] ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item['no_order'] ?? '',
                            'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                            'konsultan' => $item['order_header']['konsultan'] ?? '',
                            'filename' => $filename,
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                            ]),
                            'info_sampling' => json_encode([
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation']
                            ]),
                            'is_revisi' => $item['order_header']['is_revisi'],
                            'nama_cabang' => isset($schedule['id_cabang']) ? (
                                $schedule['id_cabang'] == 4 ? 'RO-KARAWANG' : ($schedule['id_cabang'] == 5 ? 'RO-PEMALANG' : ($schedule['id_cabang'] == 1 ? 'HEAD OFFICE' : 'UNKNOWN'))
                            ) : 'HEAD OFFICE (Default)',
                        ];
                    }
                }

                return array_merge($carry, $results);
            }, []);

            $groupedData = [];
            foreach ($formattedData as $item) {
                $key = implode('|', [
                    $item['nomor_quotation'],
                    $item['nama_perusahaan'],
                    $item['status_sampling'],
                    $item['periode'],
                    $item['jadwal'],
                    $item['no_order'],
                    $item['alamat_sampling'],
                    $item['konsultan'],
                    $item['kategori'],
                    $item['info_pendukung'],
                    $item['jadwal_jam_mulai'],
                    $item['jadwal_jam_selesai'],
                    $item['info_sampling'],
                    $item['nama_cabang'] ?? '',
                ]);

                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'nomor_quotation' => $item['nomor_quotation'],
                        'nama_perusahaan' => $item['nama_perusahaan'],
                        'status_sampling' => $item['status_sampling'],
                        'periode' => $item['periode'],
                        'jadwal' => $item['jadwal'],
                        'kategori' => $item['kategori'],
                        'sampler' => $item['sampler'],
                        'no_order' => $item['no_order'],
                        'alamat_sampling' => $item['alamat_sampling'],
                        'konsultan' => $item['konsultan'],
                        'info_pendukung' => $item['info_pendukung'],
                        'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                        'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                        'info_sampling' => $item['info_sampling'],
                        'is_revisi' => $item['is_revisi'],
                        'nama_cabang' => $item['nama_cabang'] ?? '',
                        'filename' => $item['filename'] ?? null,
                    ];
                } else {
                    $groupedData[$key]['sampler'] .= ',' . $item['sampler'];
                }

                $uniqueSampler = explode(',', $groupedData[$key]['sampler']);
                $uniqueSampler = array_unique($uniqueSampler);
                $groupedData[$key]['sampler'] = implode(',', $uniqueSampler);
            }

            $finalResult = array_values($groupedData);

            return DataTables::of($finalResult)->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

}
