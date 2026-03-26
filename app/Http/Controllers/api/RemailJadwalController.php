<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDocumentJadwalJob;
use App\Models\GenerateLink;
use App\Models\JobTask;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\GetAtasan;
use App\Services\SendEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
                $data = QuotationNonKontrak::with(['sales', 'order:no_order,no_document', 'sampling'])
                    ->select('request_quotation.*')
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->whereIn('request_quotation.flag_status', ['ordered', 'sp'])
                    ->where('request_quotation.is_approved', true)
                    ->where('request_quotation.is_emailed', true)
                    ->whereYear('request_quotation.tanggal_penawaran', $request->year)
                    ->whereIn('request_quotation.id', function ($query) {
                        $query->select('quotation_id')
                            ->from('sampling_plan')
                            ->whereNull('status_quotation')
                            ->where('is_active', 1)
                            ->groupBy('quotation_id')
                            ->havingRaw('COUNT(*) = SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END)');
                    })
                    ->orderBy('request_quotation.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with(['sales', 'order:no_order,no_document', 'sampling'])
                    ->select('request_quotation_kontrak_H.*')
                    ->where('request_quotation_kontrak_H.id_cabang', $request->cabang)
                    ->whereIn('request_quotation_kontrak_H.flag_status', ['ordered', 'sp'])
                    ->where('request_quotation_kontrak_H.is_approved', true)
                    ->where('request_quotation_kontrak_H.is_emailed', true)
                    ->whereYear('request_quotation_kontrak_H.tanggal_penawaran', $request->year)
                    ->whereIn('request_quotation_kontrak_H.id', function ($query) {
                        $query->select('quotation_id')
                            ->from('sampling_plan')
                            ->where('status_quotation', 'kontrak')
                            ->where('is_active', 1)
                            ->groupBy('quotation_id')
                            ->havingRaw('COUNT(*) = SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END)');
                    })
                    ->orderBy('request_quotation_kontrak_H.tanggal_penawaran', 'desc');
            }
            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24: // Sales Staff
                case 148:
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

            if ($request->mode == 'non_kontrak') {
                JobTask::insert([
                    'job'         => 'GenerateDocumentJadwal',
                    'status'      => 'processing',
                    'no_document' => $cek->no_document,
                    'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                $job = new GenerateDocumentJadwalJob('QT', $cek->id, $this->karyawan);
                $this->dispatch($job);
            } else {
                JobTask::insert([
                    'job'         => 'GenerateDocumentJadwal',
                    'status'      => 'processing',
                    'no_document' => $cek->no_document,
                    'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                $job = new GenerateDocumentJadwalJob('QTC', $cek->id, $this->karyawan);
                $this->dispatch($job);
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
        try {

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

            return response()->json([
                'message' => 'Email berhasil dikirim',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
