<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GenerateLink;
use App\Models\Jadwal;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\EmailJadwal;
use App\Services\GenerateDocumentJadwal;
use App\Services\GenerateQrDocument;
use App\Services\JadwalServices;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class ValidatorSPController extends Controller
{
    public function index(Request $request)
    {
        $data = SamplingPlan::withTypeModelSub()
            ->with([
                'jadwalSP' => function ($query) {
                    $query->select(
                        'id_sampling',
                        'no_quotation',
                        DB::raw('JSON_ARRAYAGG(JSON_OBJECT("tanggal", tanggal)) AS tanggal_sp'),
                        'kategori',
                        'updated_by',
                        'updated_at',
                        'created_by',
                        'created_at'
                    )
                        ->groupBy('id_sampling', 'no_quotation', 'kategori', 'updated_by', 'updated_at', 'created_by', 'created_at')
                        ->orderByRaw('COALESCE(updated_at, created_at) DESC');
                },
            ])
            ->where('is_active', true)
            ->where('status', 1)
            ->where('is_approved', 0)
            ->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->where('no_document', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('no_quotation', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('quotation', function ($sub) use ($keyword) {
                        $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
                    })->orWhereHas('quotationKontrak', function ($sub) use ($keyword) {
                        $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
                    });
                });
            })
            ->filterColumn('periode_kontrak', function ($query, $keyword) {
                $query->where('periode_kontrak', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jadwal_created_by', function ($query, $keyword) {
                $query->whereHas('jadwalSP', function ($q) use ($keyword) {
                    $q->where('updated_by', 'like', "%{$keyword}%")
                        ->orWhere('created_by', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('jadwal_created_at', function ($query, $keyword) {
                $query->whereHas('jadwalSP', function ($q) use ($keyword) {
                    $q->where('updated_at', 'like', "%{$keyword}%")
                        ->orWhere('created_at', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function getJadwalSingle(Request $request)
    {
        $data = Jadwal::select('id_sampling', 'parsial', 'no_quotation', 'nama_perusahaan', 'tanggal', 'jam_mulai', 'jam_selesai', 'kategori', 'durasi', 'status', DB::raw('group_concat(sampler) as sampler'))
            ->where('is_active', true)
            ->where('id_sampling', $request->id_sampling)
            ->groupBy('id_sampling', 'parsial', 'no_quotation', 'tanggal', 'nama_perusahaan', 'durasi', 'kategori', 'status', 'jam_mulai', 'jam_selesai');

        return Datatables::of($data)->make(true);
    }

    public function rejectJadwal(Request $request)
    {
        DB::beginTransaction();
        try {
            $cek = SamplingPlan::where('id', $request->sampling_id)->first();

            if (! is_null($cek)) {
                $cek->status        = 0;
                $cek->status_jadwal = 'cancel'; /* ['booking','fixed','jadwal','cancel',null] */
                $cek->save();
                // step cancel jadwal
                Jadwal::where('id_sampling', $cek->id)->update(['is_active' => false]);
            }

            $sales = JadwalServices::on('no_quotation', $cek->no_quotation)->getQuotation();

            $message = "No Sampling $cek->no_document/$cek->no_quotation Telah direject dari jadwal";
            Notification::where('id', $sales->sales_id)->title('Jadwal Rejected')->message($message)->url('/sampling-plan')->send();

            DB::commit();
            return response()->json([
                'message' => 'Jadwal has been rejected!',
                'status'  => '200',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => '500',
            ], 500);
        }
    }

    public function approveJadwal(Request $request)
    {

        $timestamp    = Carbon::now()->format('Y-m-d H:i:s');
        $type         = explode('/', $request->no_document)[1];
        $chekNotice   = null;
        $mailfilename = null;

        DB::beginTransaction();
        try {
            $cek = SamplingPlan::where('id', $request->sampling_id)->first();
            if ($type == 'QTC') {
                $chekNotice = QuotationKontrakH::where('no_document', $request->no_document)->first();

                if (! $chekNotice) {
                    throw new \Exception('Data quotation tidak ditemukan');
                }

                if ($chekNotice->flag_status === 'rejected') {
                    return response()->json([
                        'message' => 'No Qt ' . $request->no_document . ' sedang di reject, menunggu dari sales!',
                        'status'  => 401,
                    ], 401);
                }

                $cek->is_approved   = 1;
                $cek->approved_by   = $this->karyawan;
                $cek->approved_at   = $timestamp;
                $cek->status_jadwal = 'jadwal';
                $cek->save();

                $checkJadwal    = JadwalServices::on('no_quotation', $request->no_document)->countJadwalApproved();
                $chekQoutations = JadwalServices::on('no_quotation', $request->no_document)
                    ->on('quotation_id', $request->quotation_id)->countQuotation();

                if ($chekQoutations == $checkJadwal) {
                    // GENERATE QR
                    (new GenerateQrDocument())->insert('jadwal_kontrak', $chekNotice, $this->karyawan);

                    $mailfilename = GenerateDocumentJadwal::onKontrak($cek->quotation_id)->renderPartialKontrak();

                    if ($chekNotice != null && $mailfilename != null) {
                        $key   = $chekNotice->created_by . DATE('YmdHis');
                        $gen   = MD5($key);
                        $token = $this->encrypt($gen . '|' . $chekNotice->email_pic_order);
                        $data  = [
                            'token'            => $token,
                            'key'              => $gen,
                            'expired'          => Carbon::parse($chekNotice->expired)->addMonths(3)->format('Y-m-d'),
                            //'password' => $cek->nama_pic_order[4] . DATE('dym', strtotime($cek->add_at)),
                            'created_at'       => Carbon::parse($timestamp)->format('Y-m-d'),
                            'created_by'       => $this->karyawan,
                            // 'fileName' => json_encode($data_file) ,
                            'fileName_pdf'     => $mailfilename,
                            'is_reschedule'    => 1,
                            'quotation_status' => ($type == 'QT') ? 'non_kontrak' : 'kontrak',
                            'type'             => 'jadwal',
                            'id_quotation'     => $chekNotice->id,
                        ];
                        $dataLink                 = GenerateLink::insert($data);
                        $chekNotice->expired      = Carbon::parse($chekNotice->expired)->addMonths(1)->format('Y-m-d');
                        $chekNotice->generated_at = $timestamp;
                        $chekNotice->generated_by = $this->karyawan;
                        $chekNotice->jadwalfile   = $mailfilename;
                        $chekNotice->is_generated = true;
                        $chekNotice->is_ready_order = 1;
                        $chekNotice->save();
                    } else {
                        throw new \Exception('Gagal generate file jadwal sampling!');
                    }

                } else {
                    $response = response()->json([
                        'message' => 'Jadwal has been saved! ' . $chekQoutations . '/' . ($checkJadwal),
                        'status'  => '200',
                    ], 200);
                }

            } else if ($type == 'QT') { //else kondisi QT
                $chekNotice = QuotationNonKontrak::where('no_document', $request->no_document)->first();

                if (! $chekNotice) {
                    throw new \Exception('Data quotation tidak ditemukan');
                }

                if ($chekNotice->flag_status === 'rejected') {
                    return response()->json([
                        'message' => 'No Qt ' . $request->no_document . ' sedang di reject, menunggu dari sales!',
                        'status'  => 401,
                    ], 401);
                }

                // GENERATE QR
                (new GenerateQrDocument())->insert('jadwal_non_kontrak', $chekNotice, $this->karyawan);

                $mailfilename = GenerateDocumentJadwal::onNonKontrak($cek->quotation_id)->save();
                // dd( $mailfilename);
                $cek->is_approved   = 1;
                $cek->approved_by   = $this->karyawan;
                $cek->approved_at   = $timestamp;
                $cek->status_jadwal = 'jadwal'; /* ['booking','fixed','jadwal','cancel',null] */
                $cek->save();

                if ($chekNotice != null && $mailfilename != null) {
                    $key   = $chekNotice->created_by . DATE('YmdHis');
                    $gen   = MD5($key);
                    $token = $this->encrypt($gen . '|' . $chekNotice->email_pic_order);
                    $data  = [
                        'token'            => $token,
                        'key'              => $gen,
                        'expired'          => Carbon::parse($chekNotice->expired)->addMonths(3)->format('Y-m-d'),
                        //'password' => $cek->nama_pic_order[4] . DATE('dym', strtotime($cek->add_at)),
                        'created_at'       => Carbon::parse($timestamp)->format('Y-m-d'),
                        'created_by'       => $this->karyawan,
                        // 'fileName' => json_encode($data_file) ,
                        'fileName_pdf'     => $mailfilename,
                        'is_reschedule'    => 1,
                        'quotation_status' => ($type == 'QT') ? 'non_kontrak' : 'kontrak',
                        'type'             => 'jadwal',
                        'id_quotation'     => $chekNotice->id,
                    ];
                    $dataLink                 = GenerateLink::insert($data);
                    $chekNotice->expired      = Carbon::parse($chekNotice->expired)->addMonths(1)->format('Y-m-d');
                    $chekNotice->generated_at = $timestamp;
                    $chekNotice->generated_by = $this->karyawan;
                    $chekNotice->jadwalfile   = $mailfilename;
                    $chekNotice->is_generated = true;
                    $chekNotice->is_ready_order = 1;
                    $chekNotice->save();
                } else {
                    throw new \Exception('Gagal generate file jadwal sampling!');
                }

            }

            DB::commit();

            return $response ?? response()->json([
                'message' => 'Jadwal telah di approve!',
                'status'  => '200',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => '500',
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function injectRenderJadwal(Request $request)
    {
        DB::beginTransaction();
        try {
            $type = explode('/', $request->no_document)[1];
            if ($type == 'QTC') {
                $qt = QuotationKontrakH::where('no_document', $request->no_document)->first();
                EmailJadwal::where('quotation_id', $qt->id)->where('tanggal_penawaran', $qt->tanggal_penawaran)->renderDataJadwalSamplerH();
            } else if ($type == 'QT') {
                $qt = QuotationNonKontrak::where('no_document', $request->no_document)->first();
                EmailJadwal::where('quotation_id', $qt->id)->where('tanggal_penawaran', $qt->tanggal_penawaran)->renderDataJadwalSampler();
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'status' => '500'], 500);
        }
    }

    private function encrypt($data)
    {
        $ENCRYPTION_KEY       = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey        = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText        = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return               = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

}
