<?php

namespace App\Services;

use Carbon\Carbon;

use App\Models\LinkLhp;
use App\Models\OrderHeader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CombineLHPService
{
    public function combine($noLhp, $fileLhp, $noOrder, $periode = null)
    {
        if (!$noLhp) return response()->json(['message' => 'No. LHP is required'], 400);
        if (!$fileLhp) return response()->json(['message' => 'LHP File is required'], 400);
        if (!$noOrder) return response()->json(['message' => 'No. Order is required'], 400);

        $finalDirectoryPath = public_path('laporan/hasil_pengujian');
        $finalFilename = $noOrder . $periode ? '_' . $periode : '' . '.pdf';
        $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

        if (!File::isDirectory($finalDirectoryPath)) File::makeDirectory($finalDirectoryPath, 0777, true);

        $httpClient = Http::asMultipart();
        $fileMetadata = [];

        $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);
        if (File::exists($lhpPath)) {
            $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
            $fileMetadata[] = 'skyhwk12';
        }

        $httpClient->attach('metadata', json_encode($fileMetadata));
        // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

        $pythonServiceUrl = env('PDF_COMBINER_SERVICE', 'http://127.0.01:2999') . '/merge';
        $response = $httpClient->post($pythonServiceUrl);

        if (!$response->successful()) {
            throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
        }

        File::put($finalFullPath, $response->body());

        try {
            $orderHeader = OrderHeader::with('orderDetail')->where('no_order', $noOrder)->where('is_active', true)->latest()->first();
            if (!$orderHeader) return response()->json(['message' => 'Order tidak ditemukan'], 404);

            $linkLhp = LinkLhp::where('no_order', $noOrder);
            if ($periode) $linkLhp = $linkLhp->where('periode', $periode);
            $linkLhp = $linkLhp->first();

            if ($linkLhp) {
                $listLhpRilis = json_decode($linkLhp->list_lhp_rilis ?: '[]', true);
                if (in_array($noLhp, $listLhpRilis)) {
                    $linkLhp->filename = $finalFilename;
                } else {
                    $listLhpRilis[] = $noLhp;

                    sort($listLhpRilis, SORT_NATURAL);

                    $linkLhp->list_lhp_rilis = json_encode($listLhpRilis);
                    $linkLhp->jumlah_lhp_rilis = count($listLhpRilis);

                    $linkLhp->is_completed = $linkLhp->jumlah_lhp == count($listLhpRilis);
                }

                $linkLhp->updated_by = $this->karyawan;
                $linkLhp->updated_at = Carbon::now();

                $linkLhp->save();
            } else {
                $linkLhp = new LinkLhp();

                $linkLhp->no_quotation = $orderHeader->no_document;
                $linkLhp->periode = $periode;
                $linkLhp->no_order = $noOrder;
                $linkLhp->nama_perusahaan = $orderHeader->nama_perusahaan;
                $linkLhp->jumlah_lhp_rilis = 1;
                $linkLhp->list_lhp_rilis = json_encode([$noLhp]);
                $linkLhp->jumlah_lhp = $orderHeader->orderDetail->pluck('cfr')->unique()->count();
                $linkLhp->is_completed = $orderHeader->orderDetail->pluck('cfr')->unique()->count() == 1;
                $linkLhp->filename = $finalFilename;
                $linkLhp->created_by = $this->karyawan;
                $linkLhp->created_at = Carbon::now();
                $linkLhp->updated_by = $this->karyawan;
                $linkLhp->updated_at = Carbon::now();

                $linkLhp->save();
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}
