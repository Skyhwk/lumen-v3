<?php

namespace App\Services;

use Carbon\Carbon;

use App\Models\LinkLhp;
use App\Models\OrderHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CombineLHPService
{
    public function combine($noLhp, $fileLhp, $noOrder, $karyawan, $periode = null)
    {
        if (!$noLhp) return response()->json(['message' => 'No. LHP is required'], 400);
        if (!$fileLhp) return response()->json(['message' => 'LHP File is required'], 400);
        if (!$noOrder) return response()->json(['message' => 'No. Order is required'], 400);
        
        DB::beginTransaction();
        try {
            $finalDirectoryPath = public_path('laporan/hasil_pengujian');
            $finalFilename = $periode ? $noOrder . '_' . $periode . '.pdf' : $noOrder . '.pdf';
            $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;
            if (!File::isDirectory($finalDirectoryPath)) File::makeDirectory($finalDirectoryPath, 0777, true);

            $httpClient = Http::asMultipart();
            $fileMetadata = [];

            $linkLhp = LinkLhp::where('no_order', $noOrder);
            if ($periode) $linkLhp->where('periode', $periode);
            $linkLhp = $linkLhp->latest()->first();

            if ($linkLhp) {
                if ($linkLhp->list_lhp_rilis) {
                    $lhpRilis = json_decode($linkLhp->list_lhp_rilis, true);
                    array_push($lhpRilis, $noLhp);
                    sort($lhpRilis, SORT_NATURAL);

                    foreach ($lhpRilis as $item) {
                        $existingFile = "LHP-" . str_replace('/', '-', $item) . ".pdf";

                        if ($existingFile !== $fileLhp) {
                            $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $existingFile);

                            if (File::exists($lhpPath)) {
                                $httpClient->attach('pdfs[]', File::get($lhpPath), $existingFile);
                                $fileMetadata[] = 'skyhwk12';
                            }
                        } else {
                            $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);

                            if (File::exists($lhpPath)) {
                                $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                                $fileMetadata[] = 'skyhwk12';
                            }
                        }
                    }
                } else { // kalo blm ada samsek
                    $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);

                    if (File::exists($lhpPath)) {
                        $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                        $fileMetadata[] = 'skyhwk12';
                    }
                }
            }

            $httpClient->attach('metadata', json_encode($fileMetadata));
            // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

            $response = $httpClient->post(env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge');

            if (!$response->successful()) {
                throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
            }

            File::put($finalFullPath, $response->body());

            $orderHeader = OrderHeader::with('orderDetail')->where('no_order', $noOrder)->where('is_active', true)->latest()->first();
            if (!$orderHeader) return response()->json(['message' => 'Order tidak ditemukan'], 404);

            $linkLhp = LinkLhp::where('no_order', $noOrder);
            if ($periode) $linkLhp = $linkLhp->where('periode', $periode);
            $linkLhp = $linkLhp->first();

            if ($linkLhp) {
                $listLhpRilis = json_decode($linkLhp->list_lhp_rilis ?: '[]', true);
                if (!in_array($noLhp, $listLhpRilis)) {
                    $listLhpRilis[] = $noLhp;

                    sort($listLhpRilis, SORT_NATURAL);

                    $linkLhp->list_lhp_rilis = json_encode($listLhpRilis);
                    $linkLhp->jumlah_lhp_rilis = count($listLhpRilis);

                    $linkLhp->is_completed = $linkLhp->jumlah_lhp == count($listLhpRilis);
                }
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
                $linkLhp->created_by = $karyawan;
                $linkLhp->created_at = Carbon::now();
            }

            $linkLhp->filename = $finalFilename;

            $linkLhp->updated_by = $karyawan;
            $linkLhp->updated_at = Carbon::now();

            $linkLhp->save();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
        }
    }
}
