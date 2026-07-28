<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use App\Models\MasterKaryawan;
use App\Models\Lims\OrderDetail;

class CocDocumentController extends Controller
{
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

            $myPrivileges = $this->privilageCabang ?? [];
            $isOrangPusat = in_array("0", $myPrivileges) || in_array("1", $myPrivileges) || in_array(1, $myPrivileges);
            
            $query = OrderDetail::query();
            if (!$isOrangPusat) {
                $query->whereHas('orderHeader.samplingPlan.jadwal', function ($q) use ($myPrivileges) {
                    $q->whereIn('id_cabang', $myPrivileges);
                });
            }
            
            $data = $query->with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) use ($isOrangPusat, $myPrivileges) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                    if (!$isOrangPusat) {
                        $q->whereIn('id_cabang', $myPrivileges);
                    }
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                ->whereBetween('tanggal_sampling', [
                    $periode_awal,
                    $periode_akhir
                ])
                ->groupBy(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling']);

            $data = $data->get()->toArray();

            $formattedData = array_reduce($data, function ($carry, $item) use($isOrangPusat, $myPrivileges, $isProgrammer, $doneList) {

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
                $results = [];
                foreach ($jadwal as $schedule) {
                    $cabangId = $schedule['id_cabang'] ?? null;
                    if (!$isOrangPusat && !in_array($cabangId, $myPrivileges)) {
                        continue;
                    }

                   
                    if (!$isProgrammer) {
                        $samplers = array_map('trim', explode(',', $schedule['sampler'] ?? ''));
                        
                        // Menangani logika 'untuk sampler pertama yang terjadwal' -> index [0]
                        if (count($samplers) > 0 && $samplers[0] !== trim($this->karyawan)) {
                            continue;
                        }
                    }
                    // -----------------------

                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        
                        // Cek status is_proccess dari doneList
                        $currentSamplers = explode(',', $schedule['sampler'] ?? '');
                        $is_proccess = false;
                        foreach ($currentSamplers as $singleSampler) {
                            $cleanTargetName = strtolower(trim($singleSampler));
                            if (empty($cleanTargetName)) continue;
                            
                            $checkKey = sprintf('%s|%s|%s',
                                trim($item['no_order']),
                                trim($schedule['tanggal']),
                                $cleanTargetName
                            );
                            if (isset($doneList[$checkKey])) {
                                $is_proccess = true;
                                break;
                            }
                        }

                        $results[] = [
                            'is_proccess' => $is_proccess,
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
                            'id_cabang' => $schedule['id_cabang'] ?? null,
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
                        'is_proccess' => $item['is_proccess'],
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
                    ];
                } else {
                    $groupedData[$key]['sampler'] .= ',' . $item['sampler'];
                }

                $uniqueSampler = explode(',', $groupedData[$key]['sampler']);
                $uniqueSampler = array_unique($uniqueSampler);
                $groupedData[$key]['sampler'] = implode(',', $uniqueSampler);
            }
            $finalResult = array_values($groupedData);
            return response()->json([
                'data' => $finalResult
            ], 200);

        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }

    public function cetakCoc(Request $request)
    {
        try {
            if (!$request->has('no_sampel') || !is_array($request->no_sampel)) {
                $request->merge(['no_sampel' => []]);
            }
            $controller = app(\App\Http\Controllers\api\LimsChainOfCustodyController::class);
            $response = $controller->pdf($request);
            
            // LimsChainOfCustodyController returns JSON with base64 data, 
            // but mobile app expects a raw PDF Blob (like FPPS).
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $content = $response->getData(true);
                if (isset($content['data'])) {
                    $pdfContent = base64_decode($content['data']);
                    $fileName = $content['filename'] ?? 'COC.pdf';
                    
                    return response($pdfContent, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                        'X-Filename' => $fileName
                    ]);
                }
            }
            
            return $response;
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile(),
            ], 500);
        }
    }
}
