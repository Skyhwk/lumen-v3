<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\SamplingPlan;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\User;
use App\Models\GenerateLink;
use App\Models\JobTask;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Jobs\RenderPdfPenawaran;
use App\Services\GenerateQrDocument;
use App\Services\GenerateToken;
use App\Services\RenderNonKontrak;
use App\Services\RenderKontrak;
use App\Services\GetAtasan;
use App\Services\SendEmail;
use App\Services\Printing;
use Illuminate\Support\Facades\DB;


class QtApproveController extends Controller
{
    public function getCabang()
    {
        $data = MasterCabang::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function index(Request $request)
    {
        if ($request->mode == 'non_kontrak') {
            $data = QuotationNonKontrak::with(['sales', 'addby', 'updateby'])->where('is_active', $request->is_active)
                ->where('id_cabang', $request->id_cabang)
                ->where('is_approved', $request->is_approve)
                ->where('is_active', true)
                ->where('flag_status', $request->flag)
                ->where('is_emailed', false)
                ->whereYear('tanggal_penawaran', $request->periode)->get();
        } else if ($request->mode == 'kontrak') {
            $data = QuotationKontrakH::with(['sales', 'addby', 'updateby'])->where('is_active', $request->is_active)
                ->where('id_cabang', $request->id_cabang)
                ->where('is_approved', $request->is_approve)
                ->where('is_active', true)
                ->where('flag_status', $request->flag)
                ->where('is_emailed', false)
                ->whereYear('tanggal_penawaran', $request->periode)->get();
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
         foreach ($data as $key => $value) {
            $value->email_cc = json_decode($value->email_cc);
        };
        return Datatables::of($data)->make(true);
    }
 

    public function approve(Request $request)
    {
        if ($request->mode == 'non_kontrak') {
            $quotationNonKontrak = QuotationNonKontrak::where('id', $request->id)->first();

            // GENERATE QR
            GenerateQrDocument::insert('quotation_non_kontrak', $quotationNonKontrak, $this->karyawan);

            // GENERATE DOCUMENT
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $quotationNonKontrak->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($quotationNonKontrak->id, 'non kontrak');
            $this->dispatch($job);

            // GENERATE LINK & TOKEN
            $token = GenerateToken::save('non_kontrak', $quotationNonKontrak, $this->karyawan, 'quotation');

            $quotationNonKontrak->is_generated = true;
            $quotationNonKontrak->generated_by = $this->karyawan;
            $quotationNonKontrak->generated_at = Carbon::now()->format('Y-m-d H:i:s');
            $quotationNonKontrak->id_token = $token->id;
            $quotationNonKontrak->expired = $token->expired;

            $quotationNonKontrak->save();
        } else if ($request->mode == 'kontrak') {
            $quotationKontrak = QuotationKontrakH::where('id', $request->id)->first();

            // GENERATE QR
            GenerateQrDocument::insert('quotation_kontrak', $quotationKontrak, $this->karyawan);

            // GENERATE DOCUMENT
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $quotationKontrak->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($quotationKontrak->id, 'kontrak');
            $this->dispatch($job);

            // GENERATE LINK & TOKEN
            $token = GenerateToken::save('kontrak', $quotationKontrak, $this->karyawan, 'quotation');

            $quotationKontrak->is_generated = true;
            $quotationKontrak->generated_by = $this->karyawan;
            $quotationKontrak->generated_at = Carbon::now()->format('Y-m-d H:i:s');
            $quotationKontrak->id_token = $token->id;
            $quotationKontrak->expired = $token->expired;

            $quotationKontrak->save();
        } else {
            return response()->json(["message" => "Module not found",], 400);
        }

        return response()->json(["message" => "Data Quotation has been generated"], 200);
    }

    public function rejectData(Request $request)
    {
        // if ($request->mode == 'non_kontrak') {
        //     $data = QuotationNonKontrak::where('id', $request->id)->update([
        //         'is_approved' => false,
        //         'approved_by' => NULL,
        //         'approved_at' => NULL,
        //         'flag_status' => NULL,
        //         'is_emailed' => false,
        //         'emailed_at' => NULL,
        //         'emailed_by' => NULL,
        //     ]);
        // } else if ($request->mode == 'kontrak') {
        //     $data = QuotationKontrakH::where('id', $request->id)->update([
        //         'is_approved' => false,
        //         'approved_by' => NULL,
        //         'approved_at' => NULL,
        //         'flag_status' => NULL,
        //         'is_emailed' => false,
        //         'emailed_at' => NULL,
        //         'emailed_by' => NULL,
        //     ]);
        // } else {
        //     return response()->json([
        //         "message" => "Module not found",
        //     ], 400);
        // }

        // return response()->json([
        //     "message" => "Data Quotation Rejected",
        // ], 200);

        /*
            Update Muhammad Afryan Saputra
            2025-03-12
        */
        DB::beginTransaction();
        try {
            if (isset($request->id) || $request->id != '') {
                if ($request->mode == 'non_kontrak') {
                    $data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation';
                    if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                        $data->is_ready_order = 1;
                    }
                } else if ($request->mode == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation_kontrak';
                }

                $data->is_approved = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->flag_status = 'rejected';
                $data->is_rejected = true;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->keterangan_reject = $request->keterangan;
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Request Quotation number ' . $data->no_document . ' success rejected.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot rejected data.!',
                    'status' => '401'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
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


    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['divisi', 'jabatan'])->where('id', $this->user_id)->first();

        return response()->json($users);
    }

    public function getLink(Request $request)
    {
        // dd($request->id, $request->mode);
        $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => $request->mode, 'type' => 'quotation'])->latest()->first();
        // dd($link);
        return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $status_sampling = [];
            $nonPengujian = false;
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with('sales')->where('id', $request->id)->first();
                $data->flag_status = 'emailed';
                $data->is_emailed = true;
                $data->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by = $this->karyawan;

                if (empty(json_decode($data->data_pendukung_sampling, true))) {
                    $nonPengujian = true;
                }

                array_push($status_sampling, $data->status_sampling);
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with('sales')->where('id', $request->id)->first();
                $data->flag_status = 'emailed';
                $data->is_emailed = true;
                $data->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by = $this->karyawan;

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
                /* Fix bug and optimize by 565: 2025-06-18
                if ($data->data_lama !== null && $data->data_lama !== 'null') {
                    $data_lama = json_decode($data->data_lama);
                    if ($data_lama->status_sp == 'false') {
                        $cek_sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', 1)->where('is_approved', 1)->first();
                        if ($cek_sp != null) {
                            $data->flag_status = 'sp';
                            $data->is_ready_order = 1;
                        }
                    }
                } else {
                    $status_sampling = array_unique($status_sampling);

                    if (count($status_sampling) == 1) {
                        $status_sampling = implode($status_sampling);
                        if ($status_sampling == 'SD') {
                            $data->flag_status = 'sp';
                            $data->is_ready_order = 1;
                        } else if ($nonPengujian) {
                            $data->flag_status = 'sp';
                            $data->is_ready_order = 1;
                        }
                    }
                }*/

                if ($data->data_lama !== null && $data->data_lama !== 'null') {
                    $data_lama = json_decode($data->data_lama);
                    if ($data_lama->status_sp == 'false') {
                        $cek_sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', 1)->where('is_approved', 1)->exists();
                        if ($cek_sp) {
                            $data->flag_status = 'sp';
                            $data->is_ready_order = 1;
                        }
                    }
                }

                $status_sampling = array_unique($status_sampling);
                if (count($status_sampling) == 1) {
                    if (in_array('SD', $status_sampling)) {
                        $data->flag_status = 'sp';
                        $data->is_ready_order = 1;
                    } else if ($nonPengujian) {
                        $data->flag_status = 'sp';
                        $data->is_ready_order = 1;
                    }
                }

                $data->save();
                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
