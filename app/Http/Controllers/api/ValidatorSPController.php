<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\JobTask;
use App\Models\Jadwal;
use App\Services\JadwalServices;
use App\Services\Notification;
use App\Services\EmailJadwal;
use App\Jobs\RenderAndEmailJadwal;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


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
                }
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

            if (!is_null($cek)) {
                $cek->status = 0;
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
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '500'
            ], 500);
        }
    }

    public function approveJadwal(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $type = explode('/', $request->no_document)[1];
        DB::beginTransaction();
        try {
            if ($type == 'QTC') {
                $chekNotice = QuotationKontrakH::where('no_document', $request->no_document)->where('flag_status', 'rejected')->first();
                if ($chekNotice) {
                    return response()->json([
                        'message' => 'No Qt ' . $request->no_document . ' sedang di reject,menunggu dari sales!',
                        'status' => '401'
                    ], 401);
                }

                $cek = SamplingPlan::where('id', $request->sampling_id)->first();
                $cek->is_approved = 1;
                $cek->approved_by = $this->karyawan;
                $cek->approved_at = $timestamp;
                $cek->status_jadwal = 'jadwal'; /* ['booking','fixed','jadwal','cancel',null] */
                $cek->save();

                $checkJadwal = JadwalServices::on('no_quotation', $request->no_document)->countJadwalApproved();
                $chekQoutations = JadwalServices::on('no_quotation', $request->no_document)
                    ->on('quotation_id', $request->quotation_id)->countQuotation();

                if ($chekQoutations == $checkJadwal) {//$request->no_document
                    $data = Jadwal::select([
                        'periode',
                        DB::raw("GROUP_CONCAT(DISTINCT tanggal ORDER BY tanggal ASC) as tanggal"),
                        DB::raw("MIN(jam_mulai) as jam_mulai"),
                        DB::raw("MAX(jam_selesai) as jam_selesai"),
                        DB::raw("GROUP_CONCAT(DISTINCT sampler) as sampler")
                    ])
                    ->where('no_quotation', $request->no_document)
                    ->where('is_active', true)
                    ->groupBy('periode')
                    ->get();

                    $value = [];
                    if($data->isNotEmpty()){
                        foreach ($data as $row) {
                            $periode = $row->periode; // contoh: '2025-03'
                            $value[$periode] = [
                                'tanggal' => array_unique(explode(',', $row->tanggal)),
                                'jam_mulai' => $row->jam_mulai,
                                'jam_selesai' => $row->jam_selesai,
                                'sampler' => array_unique(explode(',', $row->sampler)),
                            ];
                        }
                    }
                    ksort($value);
                    JobTask::insert([
                        'job' => 'RenderAndEmailJadwal',
                        'status' => 'processing',
                        'no_document' => $request->no_document,
                        'timestamp' => $timestamp
                    ]);
                    $dataRequest = (object) [];
                    foreach ($request->all() as $key => $val) {
                        $dataRequest->$key = $val;
                    }
                    $dataRequest->karyawan = $this->karyawan;
                    $dataRequest->karyawan_id = $this->user_id;
                    $dataRequest->timestamp = $timestamp;

                    $job = new RenderAndEmailJadwal($dataRequest, $value);
                    $this->dispatch($job);

                    // $sales = JadwalServices::on('no_quotation', $request->no_document)->getQuotation();
                    // $message = "Jadwal No Quotation $request->no_document Sudah Di Aprrove & Melakukan Pengriman Email Ke Client";
                    // Notification::where('id', $sales->sales_id)->title('Recap_QT')->message($message)->url('url')->send();
                    DB::commit();
                    return response()->json([
                        'message' => 'Jadwal has been successfully sent!',
                        'status' => '200'
                    ], 200);
                } else {
                    $sales = JadwalServices::on('no_quotation', $cek->no_quotation)->getQuotation();
                    $message = "No Sampling $cek->no_document/$cek->no_quotation sedang melakukan approve dari $checkJadwal ke $chekQoutations jumlah kontrak";
                    Notification::where('id', $sales->sales_id)->title('Jadwal Approved')->message($message)->url('/sampling-plan')->send();

                    DB::commit();
                    return response()->json([
                        'message' => 'Jadwal has been saved! ' . $chekQoutations . '/' . ($checkJadwal),
                        'status' => '200'
                    ], 200);
                }
            } else if ($type == 'QT') { //else kondisi QT
                $cek = SamplingPlan::where('id', $request->sampling_id)->first();
                $cek->is_approved = 1;
                $cek->approved_by = $this->karyawan;
                $cek->approved_at = $timestamp;
                $cek->status_jadwal = 'jadwal'; /* ['booking','fixed','jadwal','cancel',null] */
                $cek->save();

                $data = Jadwal::select(
                    DB::raw("GROUP_CONCAT(DISTINCT(tanggal) SEPARATOR ',') as tanggal"),
                    DB::raw("MIN(jam_mulai) as jam_mulai"),
                    DB::raw("MAX(jam_selesai) as jam_selesai"),
                    DB::raw("GROUP_CONCAT(DISTINCT(sampler) SEPARATOR ',') as sampler"),
                )
                    ->where('no_quotation', $request->no_document)
                    ->where('is_active', true)
                    ->groupBy('no_quotation')
                    ->first();

                $value = [];
                if ($data != null) {
                    $value['tanggal'] = \explode(',', $data->tanggal);
                    $value['jam_mulai'] = $data->jam_mulai;
                    $value['jam_selesai'] = $data->jam_selesai;
                    $value['sampler'] = \explode(',', $data->sampler);
                }

                JobTask::insert([
                    'job' => 'RenderAndEmailJadwal',
                    'status' => 'processing',
                    'no_document' => $request->no_document,
                    'timestamp' => $timestamp
                ]);

                $dataRequest = (object) [];
                foreach ($request->all() as $key => $val) {
                    $dataRequest->$key = $val;
                }
                $dataRequest->karyawan = $this->karyawan;
                $dataRequest->karyawan_id = $this->user_id;
                $dataRequest->timestamp = $timestamp;

                $job = new RenderAndEmailJadwal($dataRequest, $value);
                $this->dispatch($job);

                $sales = JadwalServices::on('no_quotation', $cek->no_quotation)->getQuotation();
                $message = "No Sampling $request->no_document sedang melakukan pengriman email ke client";
                Notification::where('id', $sales->sales_id)->title('Jadwal Approved')->message($message)->url('/sampling-plan')->send();

                DB::commit();
                return response()->json([
                    'message' => 'Jadwal has been Save.! and Send Email',
                    'status' => '200'
                ], 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '500'
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

}
