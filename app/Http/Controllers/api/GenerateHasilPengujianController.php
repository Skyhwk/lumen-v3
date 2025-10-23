<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{CoverLhp, GenerateLink, LaporanHasilPengujian, LinkLhp, MasterKaryawan, OrderDetail, OrderHeader, PengesahanLhp, PersiapanSampelHeader, QrDocument, QuotationKontrakH, QuotationNonKontrak};
use App\Services\GetAtasan;
use Illuminate\Http\Client\ConnectionException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateHasilPengujianController extends Controller
{
    public function index()
    {
        $linkLhp = LinkLhp::with('token')->latest()->get();

        return Datatables::of($linkLhp)->make(true);
    }

    public function searchQuotations(Request $request)
    {
        $search = $request->input('q');

        $kontrak = QuotationKontrakH::with(['detail:id_request_quotation_kontrak_h,periode_kontrak', 'order:id,no_document,no_order', 'order.orderDetail:id_order_header,periode,tanggal_sampling,cfr'])
            ->select('id', 'no_document', 'nama_perusahaan', 'alamat_sampling')
            ->where('no_document', 'LIKE', "%{$search}%")
            ->whereNotIn('flag_status', ['rejected', 'void'])
            ->where('is_active', true)
            ->limit(5)
            ->get();

        $nonKontrak = QuotationNonKontrak::with(['order:id,no_document,no_order', 'order.orderDetail:id_order_header,tanggal_sampling,cfr'])
            ->select('id', 'no_document', 'nama_perusahaan', 'alamat_sampling')
            ->where('no_document', 'LIKE', "%{$search}%")
            ->whereNotIn('flag_status', ['rejected', 'void'])
            ->where('is_active', true)
            ->limit(5)
            ->get();

        $results = $kontrak->merge($nonKontrak);
        $results->makeHidden(['id']);

        return response()->json($results, 200);
    }

    public function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    // public function decrypt($data = null)
    // {
    //     $ENCRYPTION_KEY = 'intilab_jaya';
    //     $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
    //     $EncryptionKey = base64_decode($ENCRYPTION_KEY);
    //     list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
    //     $data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
    //     $extand = explode("|", $data);
    //     return $extand;
    // }

    private function initializeSteps($orderDate)
    {
        return [
            'order' => ['label' => 'Order', 'date' => $orderDate],
            'sampling' => ['label' => 'Sampling', 'date' => null],
            'analisa' => ['label' => 'Analisa', 'date' => null],
            'drafting' => ['label' => 'Drafting', 'date' => null],
            'lhp_release' => ['label' => 'LHP Release', 'date' => null],
        ];
    }

    private function detectActiveStep($steps)
    {
        $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
            ->search(fn($step) => empty($steps[$step]['date']));

        return $search === false ? 5 : $search;
    }

    private function detectActiveStepByGroup($details)
    {
        $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
            ->search(fn($step) => $details->contains(fn($d) => empty($d->steps[$step]['date'])));

        return $search === false ? 5 : $search;
    }

    private function getGroupedCFRs($orderHeader, $periode = null)
    {
        try {
            $orderDetails = OrderDetail::select('id', 'id_order_header', 'cfr', 'periode', 'no_sampel', 'keterangan_1', 'tanggal_terima', 'status', 'kategori_2', 'kategori_3', 'kategori_1')
                ->with([
                    'TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory',
                    'lhps_air',
                    'lhps_emisi',
                    'lhps_emisi_c',
                    'lhps_getaran',
                    'lhps_kebisingan',
                    'lhps_ling',
                    'lhps_medanlm',
                    'lhps_pencahayaan',
                    'lhps_sinaruv',
                    'lhps_iklim',
                    'lhps_ergonomi',
                ])
                ->where([
                    'id_order_header' => $orderHeader->id,
                    'is_active' => true
                ]);

            if ($periode) $orderDetails = $orderDetails->where('periode', $periode);

            $orderDetails = $orderDetails->get();

            $groupedData = $orderDetails->groupBy(['cfr', 'periode'])->map(fn($periodGroups) =>
            $periodGroups->map(function ($itemGroup) use ($orderHeader) {
                $mappedDetails = $itemGroup->map(function ($item) use ($orderHeader) {
                    $steps = $this->initializeSteps($orderHeader->tanggal_order);

                    $track = $item->TrackingSatu;

                    $lhps = collect([
                        $item->lhps_air,
                        $item->lhps_emisi,
                        $item->lhps_emisi_c,
                        $item->lhps_getaran,
                        $item->lhps_kebisingan,
                        $item->lhps_ling,
                        $item->lhps_medanlm,
                        $item->lhps_pencahayaan,
                        $item->lhps_sinaruv,
                        $item->lhps_iklim,
                        $item->lhps_ergonomi,
                    ])->first(fn($lhps) => $lhps !== null);

                    $tglSampling = optional($track)->ftc_verifier
                        ?? optional($track)->ftc_sd
                        ?? ($lhps->created_at ?? null)
                        ?? $item->tanggal_terima;

                    $labelSampling = optional($track)->ftc_verifier
                        ? 'Sampling'
                        : (optional($track)->ftc_sd
                            ? 'Sampel Diterima'
                            : (($lhps->created_at ?? null)
                                ? 'Direct'
                                : ($item->tanggal_terima ? 'Sampling' : null)));
                    $kategori_validation = ['13-Getaran', "14-Getaran (Bangunan)", '15-Getaran (Kejut Bangunan)', '16-Getaran (Kenyamanan & Kesehatan)', "17-Getaran (Lengan & Tangan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)",  "20-Getaran (Seluruh Tubuh)", "21-Iklim Kerja", "23-Kebisingan", "24-Kebisingan (24 Jam)", "25-Kebisingan (Indoor)", "28-Pencahayaan"];
                    if ($tglSampling) $steps['sampling'] = ['label' => $labelSampling, 'date' => $tglSampling];

                    $tglAnalisa = optional($track)->ftc_laboratory ?? ($lhps->created_at ?? null);

                    if (in_array($item->kategori_3, $kategori_validation)) {
                        $steps['analisa']['date'] = $tglSampling;
                    } else {
                        if ($tglAnalisa) $steps['analisa']['date'] = $tglAnalisa;
                    }

                    $steps['drafting']['date'] = $lhps->created_at ?? null;

                    $steps['lhp_release']['date'] = $lhps->approved_at ?? null;

                    $steps['activeStep'] = $this->detectActiveStep($steps);

                    $item->steps = $steps;

                    return $item;
                });

                $stepsByCFR = $this->initializeSteps($orderHeader->tanggal_order);
                foreach (['sampling', 'analisa', 'drafting', 'lhp_release'] as $step) {
                    // Cek SEMUA detail sudah punya tanggal untuk step ini
                    $allCompleted = $mappedDetails->every(function ($detail) use ($step) {
                        return !empty($detail->steps[$step]['date']);
                    });

                    if ($allCompleted) {
                        // ...isi tanggal parent-nya, ambil yang paling awal.
                        $earliestDate = $mappedDetails->pluck("steps.{$step}.date")->filter()->min();
                        $label = $mappedDetails->first()->steps[$step]['label']; // Ambil label dari item pertama
                        $stepsByCFR[$step] = ['label' => $label, 'date' => $earliestDate];
                    }
                }

                $stepsByCFR['activeStep'] = $this->detectActiveStepByGroup($mappedDetails);

                return [
                    'cfr' => $itemGroup->first()->cfr,
                    'periode' => $itemGroup->first()->periode,
                    'keterangan_1' => $itemGroup->pluck('keterangan_1')->toArray(),
                    'kategori_1' => $itemGroup->pluck('kategori_1')->toArray(),
                    'kategori_3' => $itemGroup->pluck('kategori_3')->toArray(),
                    'no_sampel' => $itemGroup->pluck('no_sampel')->toArray(),
                    'total_no_sampel' => $itemGroup->count(),
                    'order_details' => $mappedDetails->toArray(),
                    'steps' => $stepsByCFR
                ];
            }))->flatten(1)->values();

            return $groupedData;
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function save(Request $request)
    {
        try {
            $orderHeader = OrderHeader::where('no_document', $request->no_quotation)->where('is_active', true)->first();
            if (!$orderHeader) return response()->json(['message' => 'Order tidak ditemukan'], 404);

            $lhpRilis = collect($this->getGroupedCFRs($orderHeader, $request->periode))
                ->filter(fn($cfr) => !empty($cfr['steps']['lhp_release']['date']))
                ->values();

            if ($lhpRilis->isEmpty()) { // save tanpa lhp
                $linkLhp = new LinkLhp();
                $linkLhp->no_quotation = $request->no_quotation;
                $linkLhp->periode = $request->periode;
                $linkLhp->no_order = $request->no_order;
                $linkLhp->nama_perusahaan = $request->nama_perusahaan;
                $linkLhp->jumlah_lhp = $request->jumlah_lhp;
                $linkLhp->created_by = $this->karyawan;
                $linkLhp->created_at = Carbon::now();
                $linkLhp->updated_by = $this->karyawan;
                $linkLhp->updated_at = Carbon::now();
                $linkLhp->save();

                $key = $request->no_order;
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $tokenId = GenerateLink::insertGetId([
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $linkLhp->id,
                    'quotation_status' => "lhp_rilis",
                    'type' => 'lhp_rilis',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                $linkLhp->update([
                    'id_token' => $tokenId,
                    'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
                ]);

                return response()->json(['message' => 'Hasil Pengujian berhasil digenerate'], 200);
            } else { // save dgn lhp
                $finalDirectoryPath = public_path('laporan/hasil_pengujian');
                $finalFilename = $request->no_order . '.pdf';
                $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

                if (!File::isDirectory($finalDirectoryPath)) {
                    File::makeDirectory($finalDirectoryPath, 0777, true);
                }

                $httpClient = Http::asMultipart();
                $fileMetadata = [];

                foreach ($lhpRilis as $cfrItem) {
                    foreach ($cfrItem['order_details'] as $detailItem) {
                        foreach (['lhps_air', 'lhps_emisi', 'lhps_emisi_c', 'lhps_getaran', 'lhps_kebisingan', 'lhps_ling', 'lhps_medanlm', 'lhps_pencahayaan', 'lhps_sinaruv', 'lhps_iklim', 'lhps_ergonomi'] as $lhpsKey) {
                            $lhps = $detailItem[$lhpsKey] ?? null;
                            if (!$lhps || empty($lhps['file_lhp'])) continue;

                            $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $lhps['file_lhp']);

                            if (File::exists($lhpPath)) {
                                $httpClient->attach('pdfs[]', File::get($lhpPath), $lhps['file_lhp']);
                                $fileMetadata[] = 'skyhwk12';
                            }
                        }
                    }
                }

                $httpClient->attach('metadata', json_encode($fileMetadata));
                // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

                $pythonServiceUrl = env('PDF_COMBINER_SERVICE', 'http://127.0.01:2999') . '/merge';
                $response = $httpClient->post($pythonServiceUrl);

                if (!$response->successful()) {
                    throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
                }

                File::put($finalFullPath, $response->body());

                $linkLhp = new LinkLhp();
                $linkLhp->no_quotation = $request->no_quotation;
                $linkLhp->periode = $request->periode;
                $linkLhp->no_order = $request->no_order;
                $linkLhp->nama_perusahaan = $request->nama_perusahaan;
                $linkLhp->jumlah_lhp = $request->jumlah_lhp;
                $linkLhp->jumlah_lhp_rilis = $lhpRilis->count();
                $linkLhp->filename = $finalFilename;
                $linkLhp->created_by = $this->karyawan;
                $linkLhp->created_at = Carbon::now();
                $linkLhp->updated_by = $this->karyawan;
                $linkLhp->updated_at = Carbon::now();
                $linkLhp->save();

                $key = $request->no_order;
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $tokenId = GenerateLink::insertGetId([
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $linkLhp->id,
                    'quotation_status' => "lhp_rilis",
                    'type' => 'lhp_rilis',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'fileName_pdf' => $finalFilename,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                $linkLhp->update([
                    'id_token' => $tokenId,
                    'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
                ]);

                return response()->json(['message' => 'Hasil Pengujian berhasil digenerate'], 200);
            }
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke PDF Merger Service. Pastikan service sudah jalan.',
                'error_detail' => $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal generate: ' . $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    public function getEmailCC(Request $request)
    {
        $emails = ['sales@intilab.com'];
        $filterEmails = [
            'inafitri@intilab.com',
            'kika@intilab.com',
            'trialif@intilab.com',
            'manda@intilab.com',
            'amin@intilab.com',
            'daud@intilab.com',
            'faidhah@intilab.com',
            'budiono@intilab.com',
            'yeni@intilab.com',
            'riri@intilab.com',
            'shalsa@intilab.com',
            'rudi@intilab.com',
        ];

        if ($request->email_cc) {
            $emailCC = json_encode($request->email_cc);
            foreach (json_decode($emailCC) as $item)
                $emails[] = $item;
        }
        $users = GetAtasan::where('id', $request->sales_id ?: $this->user_id)->get()->pluck('email');
        foreach ($users as $item) {
            if ($item === 'novva@intilab.com') {
                $emails[] = 'sales02@intilab.com';
                continue;
            }

            if (in_array($item, $filterEmails)) {
                $emails[] = 'admsales04@intilab.com';
            }

            $emails[] = $item;
        }

        return response()->json($emails);
    }

    public function getUser()
    {
        $users = MasterKaryawan::with(['divisi', 'jabatan'])->where('id', $this->user_id)->first();

        return response()->json($users);
    }

    public function getLink(Request $request)
    {
        $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'lhp_rilis', 'type' => 'lhp_rilis'])->latest()->first();

        return response()->json(['link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $link->token], 200);
    }

    // private function initializeSteps($orderDate)
    // {
    //     return [
    //         'order' => ['label' => 'Order', 'date' => $orderDate],
    //         'sampling' => ['label' => 'Sampling', 'date' => null],
    //         'analisa' => ['label' => 'Analisa', 'date' => null],
    //         'drafting' => ['label' => 'Drafting', 'date' => null],
    //         'lhp_release' => ['label' => 'LHP Release', 'date' => null],
    //     ];
    // }

    // private function detectActiveStep($steps)
    // {
    //     $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
    //         ->search(fn($step) => empty($steps[$step]['date']));

    //     return $search === false ? 5 : $search;
    // }

    // private function detectActiveStepByGroup($details)
    // {
    //     $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
    //         ->search(fn($step) => $details->contains(fn($d) => empty($d->steps[$step]['date'])));

    //     return $search === false ? 5 : $search;
    // }

    // private function getGroupedCFRs($orderHeader, $periode = null)
    // {
    //     try {
    //         $orderDetails = OrderDetail::select('id', 'id_order_header', 'cfr', 'periode', 'no_sampel', 'keterangan_1', 'tanggal_terima', 'status', 'kategori_2', 'kategori_3', 'kategori_1')
    //             ->with([
    //                 'TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory',
    //                 'lhps_air',
    //                 'lhps_emisi',
    //                 'lhps_emisi_c',
    //                 'lhps_getaran',
    //                 'lhps_kebisingan',
    //                 'lhps_ling',
    //                 'lhps_medanlm',
    //                 'lhps_pencahayaan',
    //                 'lhps_sinaruv',
    //                 'lhps_iklim',
    //                 'lhps_ergonomi',
    //             ])
    //             ->where([
    //                 'id_order_header' => $orderHeader->id,
    //                 'is_active' => true
    //             ]);

    //         if ($periode) $orderDetails = $orderDetails->where('periode', $periode);

    //         $orderDetails = $orderDetails->get();

    //         $groupedData = $orderDetails->groupBy(['cfr', 'periode'])->map(fn($periodGroups) =>
    //         $periodGroups->map(function ($itemGroup) use ($orderHeader) {
    //             $mappedDetails = $itemGroup->map(function ($item) use ($orderHeader) {
    //                 $steps = $this->initializeSteps($orderHeader->tanggal_order);

    //                 $track = $item->TrackingSatu;

    //                 $lhps = collect([
    //                     $item->lhps_air,
    //                     $item->lhps_emisi,
    //                     $item->lhps_emisi_c,
    //                     $item->lhps_getaran,
    //                     $item->lhps_kebisingan,
    //                     $item->lhps_ling,
    //                     $item->lhps_medanlm,
    //                     $item->lhps_pencahayaan,
    //                     $item->lhps_sinaruv,
    //                     $item->lhps_iklim,
    //                     $item->lhps_ergonomi,
    //                 ])->first(fn($lhps) => $lhps !== null);

    //                 $tglSampling = optional($track)->ftc_verifier
    //                     ?? optional($track)->ftc_sd
    //                     ?? ($lhps->created_at ?? null)
    //                     ?? $item->tanggal_terima;

    //                 $labelSampling = optional($track)->ftc_verifier
    //                     ? 'Sampling'
    //                     : (optional($track)->ftc_sd
    //                         ? 'Sampel Diterima'
    //                         : (($lhps->created_at ?? null)
    //                             ? 'Direct'
    //                             : ($item->tanggal_terima ? 'Sampling' : null)));
    //                 $kategori_validation = ['13-Getaran', "14-Getaran (Bangunan)", '15-Getaran (Kejut Bangunan)', '16-Getaran (Kenyamanan & Kesehatan)', "17-Getaran (Lengan & Tangan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)",  "20-Getaran (Seluruh Tubuh)", "21-Iklim Kerja", "23-Kebisingan", "24-Kebisingan (24 Jam)", "25-Kebisingan (Indoor)", "28-Pencahayaan"];
    //                 if ($tglSampling) $steps['sampling'] = ['label' => $labelSampling, 'date' => $tglSampling];

    //                 $tglAnalisa = optional($track)->ftc_laboratory ?? ($lhps->created_at ?? null);

    //                 if (in_array($item->kategori_3, $kategori_validation)) {
    //                     $steps['analisa']['date'] = $tglSampling;
    //                 } else {
    //                     if ($tglAnalisa) $steps['analisa']['date'] = $tglAnalisa;
    //                 }

    //                 $steps['drafting']['date'] = $lhps->created_at ?? null;

    //                 $steps['lhp_release']['date'] = $lhps->approved_at ?? null;

    //                 $steps['activeStep'] = $this->detectActiveStep($steps);

    //                 $item->steps = $steps;

    //                 return $item;
    //             });

    //             $stepsByCFR = $this->initializeSteps($orderHeader->tanggal_order);
    //             foreach (['sampling', 'analisa', 'drafting', 'lhp_release'] as $step) {
    //                 // Cek SEMUA detail sudah punya tanggal untuk step ini
    //                 $allCompleted = $mappedDetails->every(function ($detail) use ($step) {
    //                     return !empty($detail->steps[$step]['date']);
    //                 });

    //                 if ($allCompleted) {
    //                     // ...isi tanggal parent-nya, ambil yang paling awal.
    //                     $earliestDate = $mappedDetails->pluck("steps.{$step}.date")->filter()->min();
    //                     $label = $mappedDetails->first()->steps[$step]['label']; // Ambil label dari item pertama
    //                     $stepsByCFR[$step] = ['label' => $label, 'date' => $earliestDate];
    //                 }
    //             }

    //             $stepsByCFR['activeStep'] = $this->detectActiveStepByGroup($mappedDetails);

    //             return [
    //                 'cfr' => $itemGroup->first()->cfr,
    //                 'periode' => $itemGroup->first()->periode,
    //                 'keterangan_1' => $itemGroup->pluck('keterangan_1')->toArray(),
    //                 'kategori_1' => $itemGroup->pluck('kategori_1')->toArray(),
    //                 'kategori_3' => $itemGroup->pluck('kategori_3')->toArray(),
    //                 'no_sampel' => $itemGroup->pluck('no_sampel')->toArray(),
    //                 'total_no_sampel' => $itemGroup->count(),
    //                 'order_details' => $mappedDetails->toArray(),
    //                 'steps' => $stepsByCFR
    //             ];
    //         }))->flatten(1)->values();

    //         return $groupedData;
    //     } catch (\Throwable $th) {
    //         dd($th);
    //     }
    // }

    // public function detail(Request $request)
    // {
    //     $orderHeader = OrderHeader::where('no_document', $request->no_quotation)->where('is_active', true)->first();

    //     $groupedData = $this->getGroupedCFRs($orderHeader, $request->periode);

    //     return response()->json(['groupedCFRs' => $groupedData], 200);
    // }

    // private function generateQr($noDocument)
    // {
    //     $filename = str_replace("/", "_", $noDocument);
    //     $dir = public_path("qr_documents");

    //     if (!file_exists($dir)) mkdir($dir, 0755, true);

    //     $path = $dir . "/$filename.svg";
    //     $link = 'https://www.intilab.com/validation/';
    //     $unique = 'isldc' . (int) floor(microtime(true) * 1000);

    //     QrCode::size(200)->generate($link . $unique, $path);

    //     return $unique;
    // }

    // public function generatePdf(Request $request)
    // {
    //     try {
    //         $orderHeader = OrderHeader::where('no_document', $request->no_quotation)->where('is_active', true)->first();
    //         if (!$orderHeader) {
    //             return response()->json(['message' => 'Order tidak ditemukan'], 404);
    //         }

    //         $groupedCFRs = collect($this->getGroupedCFRs($orderHeader, $request->periode))
    //             ->filter(fn($cfr) => !empty($cfr['steps']['lhp_release']['date']))
    //             ->values();

    //         if ($groupedCFRs->isEmpty()) {
    //             return response()->json(['message' => 'Dokumen belum siap dirilis'], 400);
    //         }

    //         // $PengesahanLhp = PengesahanLhp::where('berlaku_mulai', '<=', Carbon::now())->orderBy('berlaku_mulai', 'desc')->first();
    //         // $nama_perilis  = $PengesahanLhp->nama_karyawan ?? 'Abidah Walfathiyyah';
    //         // $jabatan_perilis = $PengesahanLhp->jabatan_karyawan ?? 'Technical Control Supervisor';

    //         $arrayOfSamplingStatus = $groupedCFRs->flatMap(fn($cfr) => $cfr['kategori_1'])->filter()->unique()->values()->toArray();
    //         $arrayOfCategories = $groupedCFRs->flatMap(fn($cfr) => $cfr['kategori_3'])->unique()->values()->toArray();

    //         $detail = [];
    //         foreach ($arrayOfCategories as $category) {
    //             $titikCount = $groupedCFRs
    //                 ->filter(fn($item) => in_array($category, $item['kategori_3']))
    //                 ->flatMap(fn($item) => $item['keterangan_1'])
    //                 ->count();

    //             $aliases = [
    //                 'Udara Lingkungan Kerja' => 'Lingkungan Kerja',
    //                 'Debu' => 'Lingkungan Kerja',
    //                 'Pencahayaan' => 'Lingkungan Kerja',
    //                 'Kebisingan Personal' => 'Lingkungan Kerja',
    //                 'Frekuensi Radio' => 'Lingkungan Kerja',
    //                 'Medan Magnet' => 'Lingkungan Kerja',
    //                 'Medan Listrik' => 'Lingkungan Kerja',
    //                 'Power Density' => 'Lingkungan Kerja',
    //                 'Iklim Kerja' => 'Lingkungan Kerja',
    //                 'Suhu' => 'Lingkungan Kerja',
    //                 'Kelembapan' => 'Lingkungan Kerja',
    //                 'Sinar UV' => 'Lingkungan Kerja',
    //                 'PM 10' => 'Lingkungan Kerja',
    //                 'Getaran (Lengan & Tangan)' => 'Lingkungan Kerja',
    //                 'Getaran (Seluruh Tubuh)' => 'Lingkungan Kerja',
    //                 'Angka Kuman' => 'Lingkungan Kerja',
    //                 'Air Bersih' => 'Air untuk Keperluan Higiene Sanitasi',
    //                 'Air Limbah Domestik' => 'Air Limbah',
    //                 'Air Limbah Industri' => 'Air Limbah',
    //                 'Air Permukaan' => 'Air Sungai',
    //                 'Air Kolam Renang' => 'Air Kolam Renang',
    //                 'Air Higiene Sanitasi' => 'Air untuk Keperluan Higiene Sanitasi',
    //                 'Air Khusus' => 'Air Reverse Osmosis',
    //                 'Air Limbah Terintegrasi' => 'Air Limbah',
    //             ];

    //             $categoryName = explode('-', $category)[1] ?? $category;
    //             if (array_key_exists($categoryName, $aliases)) {
    //                 $categoryName = $aliases[$categoryName];
    //             }
    //             $detail[] = "$categoryName - $titikCount Titik";
    //         }
    //         $detail = array_unique($detail);

    //         $bas = PersiapanSampelHeader::where('no_quotation', $request->no_quotation);
    //         if ($request->periode) $bas->where('periode', $request->periode);
    //         if ($request->no_bas) $bas->where('no_document', str_replace('BAS', 'PS', $request->no_bas));
    //         $noDocs = $bas->pluck('no_document')->toArray();
    //         $no_bas = array_map(fn($doc) => str_replace('PS', 'BAS', $doc), $noDocs);

    //         $formattedOrderDate = Carbon::parse($orderHeader->tanggal_order)->translatedFormat('d F Y');
    //         $formattedNowDate = Carbon::now()->translatedFormat('d F Y');

    //         $data = (object) [
    //             'nama_perusahaan' => $orderHeader->nama_perusahaan,
    //             'alamat_sampling' => $orderHeader->alamat_sampling,
    //             'periode' => "$formattedOrderDate - $formattedNowDate",
    //             'no_order' => $orderHeader->no_order,
    //             'no_quotation' => $orderHeader->no_document,
    //             'no_bas' => $no_bas,
    //             'status_sampling' => implode(', ', array_map(fn($s) => $s === 'SD' ? 'Sampel Diantar' : 'Sampling', $arrayOfSamplingStatus)),
    //             'detail' => $detail
    //         ];

    //         $mpdfCover = new Mpdf([
    //             // 'mode' => 'utf-8',
    //             'format' => 'A4',
    //             'orientation' => 'L',
    //             'margin_top' => 30,
    //             'margin_bottom' => 30,
    //             'margin_left' => 30,
    //             'margin_right' => 30,
    //             // 'margin_header' => 5,
    //             // 'margin_footer' => 5
    //         ]);

    //         $mpdfCover->setDisplayMode('fullpage');
    //         // $mpdf->SetWatermarkImage(public_path() . '/logo-watermark.png');
    //         // $mpdf->showWatermarkImage = true;
    //         // $mpdf->watermarkImageAlpha = 0.1;
    //         $htmlCover = view('reports.laporan_hasil_pengujian', compact('data'))->render();

    //         $mpdfCover->SetTitle('Laporan Hasil Pengujian');
    //         $mpdfCover->SetAuthor('PT Inti Surya Laboratorium');
    //         $mpdfCover->SetSubject('Laporan Hasil Pengujian');

    //         $mpdfCover->WriteHTML($htmlCover);

    //         $basList = '';
    //         if ($data->status_sampling != "Sampel Diantar") {
    //             foreach ($data->no_bas as $no_bas_item) {
    //                 $basList .= "<li>{$no_bas_item}</li>";
    //             }
    //         } else {
    //             $basList = '-';
    //         }

    //         // $qr = new QrDocument();

    //         // $qr->id_document = $permintaanDokumentasiSampling->id;
    //         // $qr->type_document = 'permintaan_dokumentasi_sampling';
    //         // $qr_kode_qr = $this->generateQr($permintaanDokumentasiSampling->no_document);
    //         // $qr_file = str_replace("/", "_", $permintaanDokumentasiSampling->no_document);

    //         // $qr->data = json_encode([
    //         //     'no_document' => $permintaanDokumentasiSampling->no_document,
    //         //     'type_document' => 'permintaan_dokumentasi_sampling',
    //         //     'no_quotation' => $request->no_quotation,
    //         //     'no_order' => $request->no_order,
    //         //     'periode' => Carbon::parse($request->periode)->translatedFormat('F Y'),
    //         //     'tanggal_sampling' => Carbon::parse($request->tanggal_sampling)->translatedFormat('d F Y'),
    //         //     'nama_perusahaan' => $request->nama_perusahaan
    //         // ]);

    //         // $qr->created_by = $this->karyawan;
    //         // $qr->created_at = Carbon::now();

    //         // $qr->save();

    //         $qr_img = isset($qr->file) ? '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="30px" height="30px"><br>' . $qr->kode_qr : '';

    //         $mpdfCover->SetHTMLFooter('
    //             <table class="sampling-signature">
    //                 <tr>
    //                     <td class="sampling-cell">
    //                         <div class="section-title">Sampling</div>
    //                         <table style="margin-top: 15px;">
    //                             <tr>
    //                                 <td colspan="3" style="font-size: 8px;">Dokumen Pendukung</td>
    //                             </tr>
    //                             <tr>
    //                                 <td style="font-size: 10px;">No. Order</td>
    //                                 <td style="font-size: 10px;">:</td>
    //                                 <td style="font-size: 10px;">' . $data->no_order . '</td>
    //                             </tr>
    //                             <tr>
    //                                 <td style="font-size: 10px;">No. Quote</td>
    //                                 <td style="font-size: 10px;">:</td>
    //                                 <td style="font-size: 10px;">' . $data->no_quotation . '</td>
    //                             </tr>
    //                             <tr>
    //                                 <td style="font-size: 10px;">No. BAS</td>
    //                                 <td style="font-size: 10px;">:</td>
    //                                 <td style="font-size: 10px;"></td>
    //                             </tr>
    //                             <tr>
    //                                 <td colspan="3" style="padding-left: 10px;">
    //                                     <ul>' . $basList . '</ul>
    //                                 </td>
    //                             </tr>
    //                         </table>
    //                     </td>
    //                     <td class="signature-cell" style="font-size: 10px;">
    //                         Tangerang, ' . $formattedNowDate . '<br />
    //                         ' . $qr_img . '
    //                     </td>
    //                 </tr>
    //             </table>
    //             <div class="footer-text">
    //                 Keseluruhan hasil pengujian yang terkandung di dalam Laporan Hasil Pengujian merupakan kerahasiaan dan hak eksklusifitas pelanggan, sesuai dengan penamaan yang tercantum di dalam keseluruhan Laporan Hasil Pengujian ini. PT Inti Surya Laboratorium tidak bertanggung jawab terhadap apapun apabila terjadi penyalahgunaan Laporan Hasil Pengujian termasuk didalamnya, walaupun tidak terbatas, penggandaan dan atau pemalsuan baik data maupun dokumen secara sebagian maupun seluruhnya, yang dimana tanpa sepengetahuan dan ataupun persetujuan secara resmi dari pihak PT Inti Surya Laboratorium.
    //             </div>
    //             <div class="footer-company">
    //                 Ruko Icon Business Park Blok O No.5 - 6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br />
    //                 T: 021-5088-9889 / contact@intilab.com
    //             </div>
    //         ');

    //         $tempDir = storage_path('app/temp_pdf/temp_lhp_' . uniqid());
    //         if (!File::isDirectory($tempDir)) {
    //             File::makeDirectory($tempDir, 0777, true);
    //         }

    //         $coverPdfPath = $tempDir . '/00_cover.pdf';
    //         $mpdfCover->Output($coverPdfPath, 'F');

    //         // COMBINE PDF VIA PYTHON SERVICE ==============================

    //         $finalDirectoryPath = public_path('laporan/hasil_pengujian');
    //         $finalFilename = 'LHP-GABUNGAN-' . $request->no_order . '-' . Carbon::now()->format('YmdHis') . '.pdf';
    //         $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

    //         if (!File::isDirectory($finalDirectoryPath)) {
    //             File::makeDirectory($finalDirectoryPath, 0777, true);
    //         }

    //         // 1. Siapin request multipart
    //         $httpClient = Http::asMultipart();
    //         $fileMetadata = [];

    //         // 2. Attach file cover (tanpa password)
    //         $httpClient->attach('pdfs[]', File::get($coverPdfPath), '00_cover.pdf');
    //         $fileMetadata[] = null; // Cover-nya nggak pake password

    //         // 3. Attach file LHP (yang ada password)
    //         // $groupedCFRs = $groupedCFRs->sortByDesc('cfr');
    //         foreach ($groupedCFRs as $cfrItem) {
    //             foreach ($cfrItem['order_details'] as $detailItem) {
    //                 foreach (['lhps_air', 'lhps_emisi', 'lhps_emisi_c', 'lhps_getaran', 'lhps_kebisingan', 'lhps_ling', 'lhps_medanlm', 'lhps_pencahayaan', 'lhps_sinaruv', 'lhps_iklim', 'lhps_ergonomi'] as $lhpsKey) {
    //                     $lhps = $detailItem[$lhpsKey] ?? null;
    //                     if (!$lhps || empty($lhps['file_lhp'])) continue;

    //                     $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $lhps['file_lhp']);

    //                     if (File::exists($lhpPath)) {
    //                         $httpClient->attach('pdfs[]', File::get($lhpPath), $lhps['file_lhp']);
    //                         $fileMetadata[] = 'skyhwk12';
    //                     }
    //                 }
    //             }
    //         }

    //         // 4. Attach file BAS (tanpa password)
    //         $basFiles = $bas->get();
    //         foreach ($basFiles as $bas) {
    //             if ($bas->detail_bas_documents) {
    //                 $file = json_decode($bas->detail_bas_documents, true)[0]['filename'] ?? null;
    //                 if (!$file) continue;

    //                 $basPath = public_path('dokumen/bas/' . $file);
    //                 if (File::exists($basPath)) {
    //                     $httpClient->attach('pdfs[]', File::get($basPath), $file);
    //                     $fileMetadata[] = null; // BAS nggak pake password
    //                 }
    //             }
    //         }

    //         // 5. Attach metadata (list password) dan password final
    //         $httpClient->attach('metadata', json_encode($fileMetadata));
    //         // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

    //         // 6. Tembak ke service Python
    //         $pythonServiceUrl = 'http://127.0.0.1:5000/merge';
    //         $response = $httpClient->post($pythonServiceUrl);

    //         // 7. Cek response
    //         if (!$response->successful()) {
    //             throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
    //         }

    //         // 8. Simpan hasil (response body-nya adalah raw PDF)
    //         File::put($finalFullPath, $response->body());

    //         // Simpan ke DB
    //         $laporanHasilPengujian = LaporanHasilPengujian::where([
    //             'no_quotation' => $request->no_quotation,
    //             'no_order' => $orderHeader->no_order,
    //         ]);
    //         if ($request->periode) $laporanHasilPengujian = $laporanHasilPengujian->where('periode', $request->periode);
    //         $laporanHasilPengujian = $laporanHasilPengujian->first();

    //         if ($laporanHasilPengujian) {
    //             if ($request->no_bas) { // generate specified BAS
    //                 $savedBasList = collect(json_decode($laporanHasilPengujian->no_bas ?? '[]', true));
    //                 if (!$savedBasList->contains($request->no_bas)) {
    //                     $savedBasList->push($request->no_bas);
    //                 }
    //                 $laporanHasilPengujian->no_bas = $savedBasList->values()->toJson();

    //                 $savedBasFiles = collect(json_decode($laporanHasilPengujian->separated_bas_file ?? '[]', true));

    //                 $existingIndex = $savedBasFiles->search(fn($item) => $item['no_bas'] === $request->no_bas);

    //                 if ($existingIndex !== false) {
    //                     $item = $savedBasFiles[$existingIndex];
    //                     $item['filename'] = $finalFilename;

    //                     $savedBasFiles->put($existingIndex, $item);
    //                 } else {
    //                     $savedBasFiles->push(['no_bas' => $request->no_bas, 'filename' => $finalFilename]);
    //                 }

    //                 $laporanHasilPengujian->separated_bas_file = $savedBasFiles->values()->toJson();
    //             } else { // generate all
    //                 $laporanHasilPengujian->no_bas = json_encode($no_bas);

    //                 $savedBasFiles = collect([]);
    //                 foreach ($no_bas as $basItem) {
    //                     $savedBasFiles->push(['no_bas' => $basItem, 'filename' => $finalFilename]);
    //                 }
    //                 $laporanHasilPengujian->separated_bas_file = $savedBasFiles->values()->toJson();
    //                 $laporanHasilPengujian->bundled_file = $finalFilename;
    //             }
    //         } else {
    //             $laporanHasilPengujian = new LaporanHasilPengujian();

    //             $laporanHasilPengujian->no_quotation = $request->no_quotation;
    //             $laporanHasilPengujian->no_order = $orderHeader->no_order;
    //             if ($request->periode) $laporanHasilPengujian->periode = $request->periode;

    //             if ($request->no_bas) { // generate specified BAS
    //                 $laporanHasilPengujian->no_bas = json_encode($request->no_bas);
    //                 $laporanHasilPengujian->separated_bas_file = json_encode([['no_bas' => $request->no_bas, 'filename' => $finalFilename]]);
    //             } else { // generate all
    //                 $laporanHasilPengujian->separated_bas_file = json_encode([]);
    //                 $laporanHasilPengujian->no_bas = json_encode($no_bas);
    //                 $laporanHasilPengujian->bundled_file = $finalFilename;
    //             }
    //             $laporanHasilPengujian->created_by = $this->karyawan;
    //             $laporanHasilPengujian->created_at = Carbon::now();
    //         }

    //         $laporanHasilPengujian->updated_by = $this->karyawan;
    //         $laporanHasilPengujian->updated_at = Carbon::now();

    //         $laporanHasilPengujian->save();

    //         return response()->json(['success' => true, 'message' => 'Berhasil generate Laporan Hasil Pengujian'], 200);
    //     } catch (ConnectionException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Gagal terhubung ke PDF Merger Service. Pastikan service Python (port 5000) sudah jalan.',
    //             'error_detail' => $e->getMessage()
    //         ], 503);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'message' => 'Gagal membuat laporan: ' . $e->getMessage(), 'line' => $e->getLine()], 500);
    //     } finally {
    //         if (File::isDirectory($tempDir)) {
    //             File::deleteDirectory($tempDir);
    //         }
    //     }
    // }
}
