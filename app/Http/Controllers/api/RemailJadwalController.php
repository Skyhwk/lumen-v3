<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GenerateLink;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\GetAtasan;
use App\Services\SendEmail;
use App\Services\GenerateDocumentJadwal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class RemailJadwalController extends Controller
{
    public function getCabang()
    {
        $data = MasterCabang::where('is_active', 1)->get();
        return response()->json($data);
    }


    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with(['sales', 'order:no_order,no_document'])
                    ->select('request_quotation.*') // tambahkan ini
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->where('request_quotation.flag_status', 'ordered')
                    ->where('request_quotation.is_approved', true)
                    ->where('request_quotation.is_emailed', true)
                    ->whereYear('request_quotation.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with(['sales', 'order:no_order,no_document'])
                    ->select('request_quotation_kontrak_H.*')
                    ->where('request_quotation_kontrak_H.id_cabang', $request->cabang)
                    ->where('request_quotation_kontrak_H.flag_status', 'ordered')
                    ->where('request_quotation_kontrak_H.is_approved', true)
                    ->where('request_quotation_kontrak_H.is_emailed', true)
                    ->whereYear('request_quotation_kontrak_H.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation_kontrak_H.tanggal_penawaran', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24: // Sales Staff
                    $data->where('sales_id', $this->user_id);
                    break;
                case 21: // Sales Supervisor
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                        ->pluck('id')
                        ->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn('sales_id', $bawahan);
                    break;
            }

            return DataTables::of($data)
                ->filterColumn('order.no_order', function ($query, $keyword) {
                    $query->whereHas('order', function ($query) use ($keyword) {
                        $query->where('no_order', 'like', '%' . $keyword . '%');
                    });
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateFile(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        DB::beginTransaction();
        try {
            if ($request->mode == 'non_kontrak') {
                $cek = QuotationNonKontrak::where('id', $request->id)->first();

            } else {
                $cek = QuotationKontrakH::where('id', $request->id)->first();
            }

            if (! $cek) {
                throw new \Exception('Data quotation tidak ditemukan');
            }

            if ($cek->jadwalfile) {
                return response()->json([
                    'message' => 'Silahkan hubungi IT, file jadwal sudah pernah dibuat sebelumnya',
                ], 401);
            }

            $filename = ($request->mode == 'non_kontrak')
                ? GenerateDocumentJadwal::onNonKontrak($cek->id)->save()
                : GenerateDocumentJadwal::onKontrak($cek->id)->renderPartialKontrak();

            if (! $filename) {
                throw new \Exception('Gagal membuat dokumen jadwal');
            }

            if ($filename && $cek) {
                $key   = $cek->created_by . DATE('YmdHis');
                $gen   = MD5($key);
                $token = $this->encrypt($gen . '|' . $cek->email_pic_order);
                $data  = [
                    'token'            => $token,
                    'key'              => $gen,
                    'expired'          => Carbon::parse($cek->expired)->addMonths(3)->format('Y-m-d'),
                    //'password' => $cek->nama_pic_order[4] . DATE('dym', strtotime($cek->add_at)),
                    'created_at'       => Carbon::parse($timestamp)->format('Y-m-d'),
                    'created_by'       => $this->karyawan,
                    // 'fileName' => json_encode($data_file) ,
                    'fileName_pdf'     => $filename,
                    'is_reschedule'    => 1,
                    'quotation_status' => $request->mode,
                    'type'             => 'jadwal',
                    'id_quotation'     => $cek->id,
                ];
                $dataLink          = GenerateLink::insert($data);
                $cek->expired      = Carbon::parse($cek->expired)->addMonths(1)->format('Y-m-d');
                $cek->generated_at = $timestamp;
                $cek->generated_by = $this->karyawan;
                $cek->jadwalfile   = $filename;
                $cek->is_generated = true;
                $cek->save();
            } else {
                throw new \Exception('Gagal membuat dokumen jadwal, silahkan coba lagi atau hubungi IT.');
            }

            DB::commit();
            return response()->json([
                'message' => 'Dokumen jadwal berhasil dibuat',
            ], 200, );
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan server saat membuat dokumen jadwal',
                'error'   => $th->getMessage(),
            ], 500);
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

    public function viewDokumen(Request $request)
    {
        if ($request->mode == 'non_kontrak') {
            $data = QuotationNonKontrak::where('id', $request->id)->first();
        } else if ($request->mode == 'kontrak') {
            $data = QuotationKontrakH::where('id', $request->id)->first();
        }

        return response()->json($data);
    }

    public function getEmailCC(Request $request)
    {
        $emails       = ['sales@intilab.com'];
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
            foreach (json_decode($emailCC) as $item) {
                $emails[] = $item;
            }

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

    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['divisi', 'jabatan'])->where('id', $this->user_id)->first();

        return response()->json($users);
    }

    public function getLink(Request $request)
    {
        // dd($request->id, $request->mode);
        $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => $request->mode, 'type' => 'jadwal'])->latest()->first();
        // dd($link);
        return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $status_sampling = [];
            $nonPengujian    = false;
            if ($request->mode == 'non_kontrak') {
                $data              = QuotationNonKontrak::with('sales')->where('id', $request->id)->first();
                $data->flag_status = 'emailed';
                $data->is_emailed  = true;
                $data->emailed_at  = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by  = $this->karyawan;

                if (empty(json_decode($data->data_pendukung_sampling, true))) {
                    $nonPengujian = true;
                }

                array_push($status_sampling, $data->status_sampling);
            } else if ($request->mode == 'kontrak') {
                $data              = QuotationKontrakH::with('sales')->where('id', $request->id)->first();
                $data->flag_status = 'emailed';
                $data->is_emailed  = true;
                $data->emailed_at  = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by  = $this->karyawan;

                $detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $request->id)->get();
                foreach ($detail as $key => $v) {
                    array_push($status_sampling, $v->status_sampling);
                }
            }

            // $emails = GetAtasan::where('id', $data->sales_id)->get()->pluck('email');
            // Jika $request->cc adalah array dengan satu elemen kosong, ubah menjadi array kosong
            if (is_array($request->cc) && count($request->cc) === 1 && $request->cc[0] === "") {
                $request->cc = [];
            }

            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->fromAdmsales()
                ->send();

            if ($email) {
                if ($data->data_lama !== null && $data->data_lama !== 'null') {
                    $data_lama = json_decode($data->data_lama);
                    if ($data_lama->status_sp == 'false') {
                        $cek_sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', 1)->where('is_approved', 1)->exists();
                        if ($cek_sp) {
                            $data->flag_status    = 'sp';
                            $data->is_ready_order = 1;
                        }
                    }
                }

                $status_sampling = array_unique($status_sampling);
                if (count($status_sampling) == 1) {
                    if (in_array('SD', $status_sampling)) {
                        $data->flag_status    = 'sp';
                        $data->is_ready_order = 1;
                    } else if ($nonPengujian) {
                        $data->flag_status    = 'sp';
                        $data->is_ready_order = 1;
                    }
                }

                if ($data->is_generate_data_lab == 0) {
                    $data->flag_status    = 'sp';
                    $data->is_ready_order = 1;
                    // $data->is_konfirmasi_order = 1;
                }

                $data->save();
                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim',
                ], 400);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
