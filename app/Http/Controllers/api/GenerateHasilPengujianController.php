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
use App\Services\{GetAtasan, GroupedCfrByLhp, SendEmail};

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
                    $listPeriode = $linkLhp->pluck('periode')->filter()->values()->toArray();
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

    public function save(Request $request)
    {
        try {
            $orderHeader = OrderHeader::where('no_document', $request->no_quotation)->where('is_active', true)->first();
            if (!$orderHeader) return response()->json(['message' => 'Order tidak ditemukan'], 404);

            $groupedCfr = (new GroupedCfrByLhp($orderHeader, $request->periode))->get();

            $lhpRilis = collect($groupedCfr)
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

        $order = OrderHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
        if (str_contains($order->no_document, 'QTC')) {
            $emailInfo = QuotationKontrakH::where('no_document', $order->no_document)->first();
        } else {
            $emailInfo = QuotationNonKontrak::where('no_document', $order->no_document)->first();
        }

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

            $emails[] = $item;
        }
        
        $emails = array_merge($emails, ['admsales03@intilab.com', 'admsales04@intilab.com']);

        $emailCC = null;
        $emailTo = null;

        $emailLhp = EmailLhp::where('no_order', $request->no_order)->first();

        if ($emailLhp) {
            $emailCC = explode(',', $emailLhp->email_cc);
            $emailTo = $emailLhp->email_to;
        }

        if($emailInfo) {
            $emailCC = json_decode($emailInfo->email_cc, true);
        }

        if($emailCC != null) {
            array_unique($emailCC);
        }

        return response()->json(
            [
                'email_cc' => $emailCC,
                'email_to' => $emailTo,
                'email_bcc' => $emails,
            ],
            200
        );
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

    public function fixAllLhps(Request $request)
    {
        DB::beginTransaction();
        try {
            $relations = [
                'lhps_air',
                'lhps_padatan',
                'lhps_emisi',
                'lhps_emisi_c',
                'lhps_emisi_isokinetik',
                'lhps_getaran',
                'lhps_kebisingan',
                'lhps_kebisingan_personal',
                'lhps_ling',
                'lhps_medanlm',
                'lhps_pencahayaan',
                'lhps_sinaruv',
                'lhps_ergonomi',
                'lhps_iklim',
                'lhps_swab_udara',
                'lhps_microbiologi',
            ];

            OrderDetail::with($relations)
                ->where('status', 3)
                ->where('is_approve', true)
                ->where('is_active', true)
                ->chunk(200, function ($orderDetails) use ($relations) {
                    foreach ($orderDetails as $orderDetail) {
                        // cek relasi yg kepasang
                        $foundRelations = collect($relations)
                            ->filter(fn($r) => $orderDetail->$r)
                            ->values()
                            ->toArray();

                        // lebih dari 1? skip dulu biar aman
                        if (count($foundRelations) > 1) {
                            \Log::info('[FIX LHP RELEASE] : ❌ SKIPPED (Multiple relations was found)', [
                                'no_sampel'  => $orderDetail->no_sampel,
                                'relations'  => $foundRelations,
                            ]);
                            continue;
                        }

                        // ga ada relasi sama sekali? skip
                        if (count($foundRelations) === 0) {
                            continue;
                        }

                        $relation = $foundRelations[0];
                        $lhps = $orderDetail->$relation;

                        // cuma update kalau approved_at nya null
                        if ($lhps && !$lhps->approved_at) {
                            // cek order detail-nya udah approved belum
                            if (!$orderDetail->is_approve || !$orderDetail->approved_by || !$orderDetail->approved_at) {
                                \Log::info('[FIX LHP RELEASE] : ❌ SKIPPED (Status 3 tapi belum approved)', [
                                    'no_sampel' => $orderDetail->no_sampel,
                                    'order_detail' => $orderDetail->toArray(),
                                ]);
                                continue;
                            }

                            $hasApproved     = array_key_exists('is_approved', $lhps->getAttributes());
                            $hasApprovedAlt  = array_key_exists('is_approve', $lhps->getAttributes());

                            $hasApprovedBy    = array_key_exists('approved_by', $lhps->getAttributes());
                            $hasApprovedByAlt = array_key_exists('approve_by', $lhps->getAttributes());

                            $hasApprovedAt    = array_key_exists('approved_at', $lhps->getAttributes());
                            $hasApprovedAtAlt = array_key_exists('approve_at', $lhps->getAttributes());

                            if ($hasApproved) $lhps->is_approved = true;
                            if ($hasApprovedAlt) $lhps->is_approve = true;

                            if ($hasApprovedBy) $lhps->approved_by = $orderDetail->approved_by;
                            if ($hasApprovedByAlt) $lhps->approve_by = $orderDetail->approved_by;

                            if ($hasApprovedAt) $lhps->approved_at = $orderDetail->approved_at;
                            if ($hasApprovedAtAlt) $lhps->approve_at = $orderDetail->approved_at;

                            $lhps->save();

                            \Log::info('[FIX LHP RELEASE] : ✅ UPDATED LHPS', [
                                'no_sampel' => $orderDetail->no_sampel,
                                'lhps'      => $lhps->toArray(),
                            ]);
                        }
                    }
                });

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
        }
    }
}
