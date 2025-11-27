<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{GenerateLink, LinkLhp, MasterKaryawan, OrderDetail, OrderHeader, QuotationKontrakH, QuotationNonKontrak, EmailLhp};
use App\Services\{GetAtasan, SendEmail};

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Validator;

class GenerateHasilPengujianController extends Controller
{
    public function index()
    {
        $linkLhp = LinkLhp::with('token')->where('is_emailed', false)->latest();

        return Datatables::of($linkLhp)
            ->filterColumn('is_completed', function ($query, $keyword) {
                if ($keyword) $query->where('is_completed', $keyword);
            })
            ->make(true);
    }

    public function searchOrders(Request $request)
    {
        $search = $request->input('q');

        $results = OrderHeader::with('orderDetail')
            ->where('no_order', 'like', "%{$search}%")
            ->where('is_active', true)
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $linkLhp = LinkLhp::where('no_order', $item->no_order);
                if ($linkLhp->exists()) {
                    $listPeriode = $linkLhp->pluck('periode')->toArray();
                    if (count($listPeriode) > 0) {
                        $filteredDetail = $item->orderDetail->filter(fn($detail) => !in_array($detail->periode, $listPeriode))->values();
                        $item->setRelation('order_detail', $filteredDetail);
                        return $item->makeHidden(['id']);
                    } else {
                        return null;
                    }
                } else {
                    return $item->makeHidden(['id']);
                }
            })
            ->filter()
            ->values();

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
                    'lhps_padatan',
                    'lhps_emisi',
                    'lhps_emisi_c',
                    'lhps_getaran',
                    'lhps_kebisingan',
                    'lhps_kebisingan_personal',
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
                        $item->lhps_padatan,
                        $item->lhps_emisi,
                        $item->lhps_emisi_c,
                        $item->lhps_getaran,
                        $item->lhps_kebisingan,
                        $item->lhps_kebisingan_personal,
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

                    if ($tglSampling) $steps['sampling'] = ['label' => $labelSampling, 'date' => $tglSampling];

                    $tglAnalisa = optional($track)->ftc_laboratory ?? ($lhps->created_at ?? null);
                                
                    $kategori_validation = ['13-Getaran', "14-Getaran (Bangunan)", '15-Getaran (Kejut Bangunan)', '16-Getaran (Kenyamanan & Kesehatan)', "17-Getaran (Lengan & Tangan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)",  "20-Getaran (Seluruh Tubuh)", "21-Iklim Kerja", "23-Kebisingan", "24-Kebisingan (24 Jam)", "25-Kebisingan (Indoor)", "28-Pencahayaan"];
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
                $finalFilename = $request->periode ? $request->no_order . '_' . $request->periode . '.pdf' : $request->no_order . '.pdf';
                $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

                if (!File::isDirectory($finalDirectoryPath)) {
                    File::makeDirectory($finalDirectoryPath, 0777, true);
                }

                $httpClient = Http::asMultipart();
                $fileMetadata = [];

                foreach ($lhpRilis as $cfrItem) {
                    foreach ($cfrItem['order_details'] as $detailItem) {
                        foreach (['lhps_air', 'lhps_padatan', 'lhps_emisi', 'lhps_emisi_c', 'lhps_getaran', 'lhps_kebisingan', 'lhps_ling', 'lhps_medanlm', 'lhps_pencahayaan', 'lhps_sinaruv', 'lhps_iklim', 'lhps_ergonomi'] as $lhpsKey) {
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

                $pythonServiceUrl = env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge';
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
                $linkLhp->jumlah_lhp_rilis = $lhpRilis->count();
                $linkLhp->list_lhp_rilis = json_encode($lhpRilis->pluck('cfr')->toArray());
                $linkLhp->jumlah_lhp = $request->jumlah_lhp;
                $linkLhp->is_completed = $request->jumlah_lhp == $lhpRilis->count();
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

    public function getEmailInfo(Request $request)
    {
        if (str_contains($request->no_quotation, 'QTC')) {
            $emailInfo = QuotationKontrakH::where('no_document', $request->no_quotation)->first();
        } else {
            $emailInfo = QuotationNonKontrak::where('no_document', $request->no_quotation)->first();
        }

        return response()->json([
            'data' => $emailInfo,
            'message' => 'Quotation info retrieved successfully',
        ], 200);
    }

    public function getEmailCC(Request $request)
    {
        $emails = ['sales@intilab.com', 'Billing@intilab.com', 'sales.draft@intilab.com', 'adminlhp@intilab.com'];
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
        $emailCC = null;
        $emailTo = null;

        $emailLhp = EmailLhp::where('no_order', $request->no_order)->first();

        if($emailLhp) {
            $emailCC = explode(',', $emailLhp->email_cc);
            $emailTo = $emailLhp->email_to;
        }

        return response()->json(
            [
                'email_cc' => $emailCC,
                'email_to' => $emailTo,
                'email_bcc' => $emails,
            ], 200);
    }

    public function getUser()
    {
        $users = MasterKaryawan::with(['divisi', 'jabatan'])->where('id', $this->user_id)->first();

        return response()->json($users);
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            if (is_array($request->cc) && count($request->cc) === 1 && $request->cc[0] === "") {
                $request->cc = [];
            }

            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('replyto', ['adminlhp@intilab.com'])
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->fromLhp()
                ->send();

            if ($email) {
                $linkLhp = LinkLhp::find($request->id);
                $linkLhp->update([
                    'is_emailed' => true,
                    'count_email' => $linkLhp->count_email + 1,
                    'emailed_by' => $this->karyawan,
                    'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                DB::commit();
                return response()->json(['message' => 'Email berhasil dikirim'], 200);
            } else {
                DB::rollBack();
                return response()->json(['message' => 'Email gagal dikirim'], 400);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function rerenderPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:link_lhp,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $linkLhp = LinkLhp::find($request->id);
            if (!$linkLhp) return response()->json(['message' => 'Data Link LHP tidak ditemukan.'], 404);

            $listCfrRilis = json_decode($linkLhp->list_lhp_rilis);
            // $finalFilename = $linkLhp->filename;
            // if (empty($listCfrRilis) || !$finalFilename) return response()->json(['message' => 'Record ini tidak memiliki list LHP rilis atau nama file. Tidak ada yang bisa di-render ulang.'], 400);

            $finalDirectoryPath = public_path('laporan/hasil_pengujian');
            $finalFilename = $linkLhp->periode ? $linkLhp->no_order . '_' . $linkLhp->periode . '.pdf' : $linkLhp->no_order . '.pdf';
            $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

            if (!File::isDirectory($finalDirectoryPath)) {
                File::makeDirectory($finalDirectoryPath, 0777, true);
            }

            $httpClient = Http::asMultipart();
            $fileMetadata = [];

            foreach ($listCfrRilis as $noLhp) {
                $fileLhp = 'LHP-' . str_replace('/', '-', $noLhp) . '.pdf';

                $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $fileLhp);

                if (File::exists($lhpPath)) {
                    $httpClient->attach('pdfs[]', File::get($lhpPath), $fileLhp);
                    $fileMetadata[] = 'skyhwk12';
                }
            }

            $httpClient->attach('metadata', json_encode($fileMetadata));
            // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

            $pythonServiceUrl = env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999') . '/merge';
            $response = $httpClient->post($pythonServiceUrl);

            if (!$response->successful()) {
                throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
            }

            File::put($finalFullPath, $response->body());

            $linkLhp->filename = $finalFilename;
            $linkLhp->save();

            return response()->json([
                'message' => 'File PDF berhasil di-render ulang.',
                'file_ditimpa' => $finalFilename,
            ], 200);
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke PDF Merger Service. Pastikan service sudah jalan.',
                'error_detail' => $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal render ulang: ' . $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }
}
