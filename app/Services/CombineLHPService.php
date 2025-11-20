<?php

namespace App\Services;

use Carbon\Carbon;

use App\Models\LinkLhp;
use App\Models\OrderHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $orderHeader = OrderHeader::with('orderDetail')->where('no_order', $noOrder)->where('is_active', true)->latest()->first();
        if (!$orderHeader) {
            Log::error("CombineLHPService: Order tidak ditemukan (noOrder: {$noOrder})");
            return;
        }

        if (str_contains($orderHeader->no_document, 'QTC') && !$periode) {
            Log::error("CombineLHPService: Order QTC tanpa periode (noOrder: {$noOrder})");
            return;
        }

        DB::beginTransaction();
        try {
            $finalDirectoryPath = public_path('laporan/hasil_pengujian');
            $finalFilename = $periode ? $noOrder . '_' . $periode . '.pdf' : $noOrder . '.pdf';
            $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;
            if (!File::isDirectory($finalDirectoryPath)) {
                File::makeDirectory($finalDirectoryPath, 0777, true);
                // Log::info("CombineLHPService: Created directory {$finalDirectoryPath}");
            }

            $httpClient = Http::asMultipart();
            $fileMetadata = [];

            $linkLhp = LinkLhp::where('no_order', $noOrder);
            if ($periode) $linkLhp->where('periode', $periode);
            $linkLhp = $linkLhp->latest()->first();

            if ($linkLhp) {
                if ($linkLhp->list_lhp_rilis) {
                    $lhpRilis = json_decode($linkLhp->list_lhp_rilis, true);
                    $lhpRilis = array_unique(array_merge($lhpRilis, [$noLhp]));
                    sort($lhpRilis, SORT_NATURAL);

                    // Log::info("CombineLHPService: Existing LHP list for {$noOrder}", $lhpRilis);

                    foreach ($lhpRilis as $item) {
                        $existingFile = "LHP-" . str_replace('/', '-', $item) . ".pdf";
                        if ($existingFile !== $fileLhp) {
                            $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $existingFile);
                            if (File::exists($lhpPath)) {
                                $httpClient->attach('pdfs[]', File::get($lhpPath), $existingFile);
                                $fileMetadata[] = 'skyhwk12';
                                // Log::info("CombineLHPService: Attached {$existingFile}");
                            } else {
                                Log::warning("CombineLHPService: File not found {$lhpPath}");
                            }
                        } else {
                            $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);
                            if (File::exists($lhpPath)) {
                                $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                                $fileMetadata[] = 'skyhwk12';
                                // Log::info("CombineLHPService: Attached {$existingFile}");
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
                        // Log::info("CombineLHPService: Attached {$fileLhp}");
                    } else {
                        Log::warning("CombineLHPService: File not found {$lhpPath}");
                    }
                }
            }

            $httpClient->attach('metadata', json_encode($fileMetadata));
            // $httpClient->attach('final_password', $orderHeader->id_pelanggan);
            // Log::info("CombineLHPService: Sending to PDF combiner with " . count($fileMetadata) . " files");

            $response = $httpClient->post(env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge');

            if (!$response->successful()) {
                throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
            }

            File::put($finalFullPath, $response->body());
            // Log::info("CombineLHPService: Combined PDF saved at {$finalFullPath}");

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
                    $linkLhp->jumlah_lhp = $orderHeader->orderDetail->where('periode', $periode)->where('is_active', true)->pluck('cfr')->unique()->count();
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
                $linkLhp->jumlah_lhp = $orderHeader->orderDetail->where('periode', $periode)->where('is_active', true)->pluck('cfr')->unique()->count();
                $linkLhp->is_completed = $orderHeader->orderDetail->where('periode', $periode)->where('is_active', true)->pluck('cfr')->unique()->count() == 1;
                $linkLhp->created_by = $karyawan;
                $linkLhp->created_at = Carbon::now();
                // Log::info("CombineLHPService: Created new LinkLHP for {$noOrder}");
            }

            $linkLhp->filename = $finalFilename;

            $linkLhp->updated_by = $karyawan;
            $linkLhp->updated_at = Carbon::now();

            $linkLhp->save();
            DB::commit();
            // Log::info("CombineLHPService: Combine process completed successfully for {$noOrder}");
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("CombineLHPService: Exception for {$noOrder} - {$th->getMessage()}");
            Log::error($th);
        }
    }
}
