<?php
namespace App\Services;
use Carbon\Carbon;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
Carbon::setLocale('id');
class SnapshotPersiapanService
{
    public function SnapShot()
    {
        try {
            $periode_awal = Carbon::now()->format('Y-m-d');
            $periode_akhir = Carbon::now()->format('Y-m-d');
            $existingWork = DB::table('persiapan_sampel_header')
                ->select('no_order', 'tanggal_sampling', 'sampler_jadwal')
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
                    $doneList[$key] = true;
                }
            }
            $data = OrderDetail::with([
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
            $cabangMap = [1 => 'HEAD OFFICE', 4 => 'RO-KARAWANG', 5 => 'RO-PEMALANG'];
            $groupedData = [];
            foreach ($data as $item) {
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) continue;
    
                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                
                // Cari Target Plan
                $targetPlan = null;
                if ($periode) $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
                if (!$targetPlan) $targetPlan = $orderHeader->sampling->first();
    
                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) continue;
    
                // Cache Info
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
                    if ($schedule->tanggal !== $item->tanggal_sampling) continue;
                    $currentSamplers = explode(',', $schedule->sampler ?? '');
                    $pendingSamplers = [];
                    foreach ($currentSamplers as $singleSampler) {
                        $cleanTargetName = strtolower(trim($singleSampler));
                        if (empty($cleanTargetName)) continue;
    
                        $checkKey = sprintf('%s|%s|%s', 
                            trim($item->no_order), 
                            trim($schedule->tanggal), 
                            $cleanTargetName
                        );
                        if (!isset($doneList[$checkKey])) {
                            $pendingSamplers[] = trim($singleSampler);
                        }
                    }
    
                    // Jika semua sudah selesai, skip baris ini (Sama seperti Controller)
                    if (empty($pendingSamplers)) continue;
    
                    // Override sampler dengan sisa yang belum selesai
                    $schedule->sampler = implode(',', $pendingSamplers);
                    // -------------------------------------------
    
                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $namaCabang = $cabangMap[$schedule->id_cabang] ?? 'HEAD OFFICE (Default)';
    
                    $key = $orderHeader->no_document . '|' . 
                        $item->no_order . '|' . 
                        $schedule->tanggal . '|' . 
                        $schedule->jam_mulai;
    
                    if (isset($groupedData[$key])) {
                        $existingSamplers = explode(',', $groupedData[$key]['sampler']);
                        $newSamplers = explode(',', $schedule->sampler ?? '');
                        $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                        $groupedData[$key]['sampler'] = implode(',', array_filter($merged));
                    } else {
                        $groupedData[$key] = [
                            'nomor_quotation'    => $orderHeader->no_document ?? '',
                            'periode'            => $periode,
                            'jadwal'             => $schedule->tanggal,
                            'kategori'           => $kategori,
                            'sampler'            => $schedule->sampler ?? ''
                        ];
                    }
                }
            }
            $finalResult = array_values($groupedData);
            $jsonString = json_encode($finalResult);
            $path = storage_path('logs/snapshots');
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $filename = 'snapshot_weekly_' . date('o-W') . '.log';
            $fullPath = $path . '/' . $filename;
            // 2. Format Data Enak Dibaca
            $header  = "=========================================================" . PHP_EOL;
            $header .= " CAPTURE TIME: " . Carbon::now()->isoFormat('dddd, D MMMM Y HH:mm') . PHP_EOL;
            $header .= " TOTAL DATA  : " . count($finalResult) . " Items" . PHP_EOL;
            $header .= "=========================================================";
            $content = json_encode($finalResult, JSON_UNESCAPED_UNICODE);
            
            // 3. Gabungkan Header + Content + Footer Spacer
            $logEntry = $header . PHP_EOL . $content . PHP_EOL . PHP_EOL;
            // 4. Simpan (FILE_APPEND akan menambahkan ke bawah terus)
            file_put_contents($fullPath, $logEntry, FILE_APPEND);
            return true;
        } catch (\Exception $th) {
            Log::error("GAGAL SNAPSHOT PERSIAPAN", [
                'message' => $th->getMessage(),
                'line'    => $th->getLine(),
                'file'    => $th->getFile(), // PERBAIKAN: getFile() harus pakai kurung ()
            ]);
        }

    }
}