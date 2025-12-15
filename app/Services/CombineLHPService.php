<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

use App\Models\LinkLhp;
use App\Models\OrderHeader;

class CombineLHPService
{
    public function combine($noLhp, $fileLhp, $noOrder, $karyawan, $periode = null)
    {
        if (!$noLhp) {
            Log::error("CombineLHPService: No. LHP is required (noOrder: {$noOrder})");
            return;
        }

        if (!$fileLhp) {
            Log::error("CombineLHPService: LHP File is required (noOrder: {$noOrder})");
            return;
        }

        if (!$noOrder) {
            Log::error("CombineLHPService: No. Order is required");
            return;
        }

        $orderHeader = OrderHeader::with('orderDetail')->where(['no_order' => $noOrder, 'is_active' => true])->latest()->first();
        if (!$orderHeader) {
            Log::error("CombineLHPService: Order tidak ditemukan (noOrder: {$noOrder})");
            return;
        }

        if (str_contains($orderHeader->no_document, 'QTC') && !$periode) {
            Log::error("CombineLHPService: Order QTC tanpa periode (noOrder: {$noOrder})");
            return;
        }

        $linkLhp = LinkLhp::where('no_order', $noOrder)->when($periode, fn($q) => $q->where('periode', $periode))->latest()->first();
        if (!$linkLhp) {
            Log::error("CombineLHPService: Link LHP tidak ditemukan (noOrder: {$noOrder})");
            return;
        }

        $finalDirectoryPath = public_path('laporan/hasil_pengujian');
        $finalFilename = $periode ? $noOrder . '_' . $periode . '.pdf' : $noOrder . '.pdf';
        $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;
        if (!File::isDirectory($finalDirectoryPath)) {
            File::makeDirectory($finalDirectoryPath, 0777, true);
        }

        DB::beginTransaction();
        try {
            $httpClient = Http::asMultipart();
            $fileMetadata = [];

            if ($linkLhp->list_lhp_rilis) {
                $lhpRilis = json_decode($linkLhp->list_lhp_rilis, true);
                $lhpRilis = array_unique(array_merge($lhpRilis, [$noLhp]));
                sort($lhpRilis, SORT_NATURAL);

                foreach ($lhpRilis as $item) {
                    $existingFile = "LHP-" . str_replace('/', '-', $item) . ".pdf";
                    if ($existingFile !== $fileLhp) {
                        $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $existingFile);
                        if (File::exists($lhpPath)) {
                            $httpClient->attach('pdfs[]', File::get($lhpPath), $existingFile);
                            $fileMetadata[] = 'skyhwk12';
                        } else {
                            Log::warning("CombineLHPService: File not found {$lhpPath}");
                        }
                    } else {
                        $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);
                        if (File::exists($lhpPath)) {
                            $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                            $fileMetadata[] = 'skyhwk12';
                        } else {
                            Log::warning("CombineLHPService: File not found {$lhpPath}");
                        }
                    }
                }
            } else { // kalo blm ada samsek
                $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);

                if (File::exists($lhpPath)) {
                    $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                    $fileMetadata[] = 'skyhwk12';
                } else {
                    Log::warning("CombineLHPService: File not found {$lhpPath}");
                }
            }

            $httpClient->attach('metadata', json_encode($fileMetadata));
            // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

            $response = $httpClient->post(env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge');
            if (!$response->successful()) {
                throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
            }

            File::put($finalFullPath, $response->body());

            $listLhpRilis = json_decode($linkLhp->list_lhp_rilis ?: '[]', true);
            if (!in_array($noLhp, $listLhpRilis)) {
                $countLhp = $orderHeader->orderDetail->when($periode, fn($q) => $q->where('periode', $periode))->where('is_active', true)->pluck('cfr')->unique()->count();
                $listLhpRilis[] = $noLhp;

                sort($listLhpRilis, SORT_NATURAL);

                $linkLhp->list_lhp_rilis = json_encode($listLhpRilis);
                $linkLhp->jumlah_lhp_rilis = count($listLhpRilis);
                $linkLhp->jumlah_lhp = $countLhp;
                $linkLhp->is_completed = $countLhp == count($listLhpRilis);
            }

            $linkLhp->filename = $finalFilename;

            $linkLhp->updated_by = $karyawan;
            $linkLhp->updated_at = Carbon::now();

            $linkLhp->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            Log::error("CombineLHPService: Exception Error for {$noOrder} - {$th->getMessage()}");
            Log::error($th);
        }
    }
}
