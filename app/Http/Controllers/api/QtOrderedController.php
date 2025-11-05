<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\QuotationNonKontrak;
use App\Models\{AlasanVoidQt, QuotationKontrakH, QuotationKontrakD};
use App\Models\MasterCabang;
use App\Models\OrderHeader;
use App\Models\SamplingPlan;
use App\Models\OrderDetail;
use App\Models\Jadwal;
use App\Models\MasterKaryawan;
use App\Models\Ftc;
use App\Models\Ftct;
use App\Models\JobTask;
use Validator;
use App\Jobs\RenderPdfPenawaran;
use App\Services\Notification;
use App\Services\GetAtasan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\SamplingPlanServices;
use App\Jobs\RenderSamplingPlan;
use Carbon\Carbon;


class QtOrderedController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with(['sales', 'sampling', 'konfirmasi', 'order:no_order,no_document'])
                    ->select('request_quotation.*') // tambahkan ini
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->where('request_quotation.flag_status', 'ordered')
                    ->where('request_quotation.is_approved', true)
                    ->where('request_quotation.is_emailed', true)
                    ->whereYear('request_quotation.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with(['sales', 'detail', 'sampling', 'konfirmasi', 'order:no_order,no_document'])
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
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->filterColumn('konfirmasi', function ($query, $keyword) {
                    // dd($query, $keyword);
                })
                ->filterColumn('order.no_order', function ($query, $keyword) {
                    $query->whereHas('order', function ($query) use ($keyword) {
                        $query->where('no_order', 'like', '%' . $keyword . '%');
                    });
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getCabang(Request $request)
    {
        $data = MasterCabang::where('is_active', true)->get();
        return response()->json($data);
    }

    public function reschedule(Request $request)
    {
        try {
            $dataArray = (object) [
                "no_document" => $request->no_document,
                "no_quotation" => $request->no_quotation,
                "quotation_id" => $request->quotation_id,
                "karyawan" => $this->karyawan,
                "tanggal_sampling" => $request->tanggal_sampling,
                "jam_sampling" => $request->jam_sampling,
                "tambahan" => $request->tambahan,
                "keterangan_lain" => $request->keterangan_lain,
                "tanggal_penawaran" => $request->tanggal_penawaran,
            ];

            if ($request->sample_id && $request->periode) {
                $dataArray->sample_id = $request->sample_id;
                $dataArray->periode = $request->periode;
                $spServices = SamplingPlanServices::on('insertSingleKontrak', $dataArray)->insertSPSingleKontrak();
            } else {
                $spServices = SamplingPlanServices::on('insertSingleNon', $dataArray)->insertSPSingle();
            }

            if ($spServices) {
                $job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
                $this->dispatch($job);

                return response()->json(['message' => 'Reschedule Request Sampling Plan Success', 'status' => 200], 200);
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Reschedule Request Sampling Plan Failed: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'status' => 401], 401);
        }
    }

    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->mode == 'non_kontrak' || $request->mode == "null") {
                $data = QuotationNonKontrak::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation';
                if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                    $data->is_ready_order = 1;
                }
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation_kontrak';
            }

            $data->is_approved = false;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->keterangan_reject = $request->keterangan_reject;

            $order_h = OrderHeader::where('no_document', $data->no_document)->first();
            $order_h->is_revisi = true;
            $order_h->save();

            $json = [
                'id_qt' => $data->id,
                'no_qt' => $order_h->no_document,
                'no_order' => $order_h->no_order,
                'id_order' => $order_h->id,
                'status_sp' => $request->perubahan_sp
            ];

            $data->data_lama = json_encode($json);
            $data->flag_status = 'rejected';
            $data->is_rejected = true;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->save();

            DB::commit();
            return response()
                ->json(['message' => 'Success Reject Quotation Order!', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function copy(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->status_quotation == 'non_kontrak' || $request->status_quotation == 'null') {
                $cek = QuotationNonKontrak::where('id_cabang', $this->idcabang)
                    ->whereYear('tanggal_penawaran', Carbon::now()->year)
                    ->where('no_document', 'not like', '%R%')
                    ->where('no_document', 'like', '%/' . date('y') . '-%')
                    ->orderBy('no_quotation', 'DESC')
                    ->first();

                $no_urut = '1';

                if ($cek != null)
                    $no_urut = floatval(explode('/', $cek->no_document)[3]) + 1;

                $no_quotation = sprintf('%06d', ($no_urut));
                $no_document = 'ISL/QT/' . DATE('y') . '-' . self::romawi(DATE('m')) . '/' . $no_quotation;

                $query = QuotationNonKontrak::where('id', $request->id)->firstOrFail();

                $newQuery = $query->replicate();
                $newQuery->no_quotation = $no_quotation;
                $newQuery->no_document = $no_document;
                $newQuery->konsultan = $query->konsultan;
                $newQuery->flag_status = null;
                $newQuery->tanggal_penawaran = Carbon::now()->format('Y-m-d');
                $newQuery->created_by = $this->karyawan;
                $newQuery->created_at = Carbon::now();
                $newQuery->updated_by = null;
                $newQuery->updated_at = null;
                $newQuery->data_lama = null;
                $newQuery->keterangan_reject = null;
                $newQuery->is_approved = 0;
                $newQuery->approved_by = null;
                $newQuery->approved_at = null;
                $newQuery->is_emailed = 0;
                $newQuery->is_ready_order = 0;
                $newQuery->emailed_at = null;
                $newQuery->emailed_by = null;
                $newQuery->is_generated = 0;
                $newQuery->generated_at = null;
                $newQuery->generated_by = null;
                $newQuery->id_token = null;
                $newQuery->save();

                JobTask::insert([
                    'job' => 'RenderPdfPenawaran',
                    'status' => 'processing',
                    'no_document' => $newQuery->no_document,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                DB::commit();

                $job = new RenderPdfPenawaran($newQuery->id, 'non kontrak');
                $this->dispatch($job);

                $array_id_user = GetAtasan::where('id', $newQuery->sales_id)->get()->pluck('id')->toArray();

                Notification::whereIn('id', $array_id_user)
                    ->title('Penawaran telah diperbarui')
                    ->message('Penawaran dengan nomor ' . $query->no_document . ' telah berhasil di salin ke nomor ' . $newQuery->no_document . '.')
                    ->url('/quote-request')
                    ->send();

                return response()->json([
                    'message' => "Request Quotation number $no_document success created",
                    'status' => 200
                ], 200);
            } else {
                $db = DATE('Y');

                $cek = QuotationKontrakH::where('id_cabang', $this->idcabang)
                    ->whereYear('tanggal_penawaran', $db)
                    ->where('no_document', 'not like', '%R%')
                    ->where('no_document', 'like', '%/' . date('y') . '-%')
                    ->orderBy('no_quotation', 'DESC')
                    ->first();
                $no_urut = '1';
                if ($cek != null)
                    $no_urut = floatval(explode('/', $cek->no_document)[3]) + 1;

                $no_quotation = sprintf('%06d', ($no_urut));
                $no_document = 'ISL/QTC/' . DATE('y') . '-' . self::romawi(DATE('m')) . '/' . $no_quotation;

                $query = QuotationKontrakH::where('id', $request->id)->firstOrFail();

                $newQuery = $query->replicate();
                $newQuery->no_quotation = $no_quotation;
                $newQuery->no_document = $no_document;
                $newQuery->konsultan = $query->konsultan;
                $newQuery->flag_status = null;
                $newQuery->tanggal_penawaran = Carbon::now()->format('Y-m-d');
                $newQuery->created_by = $this->karyawan;
                $newQuery->created_at = Carbon::now();
                $newQuery->updated_by = null;
                $newQuery->updated_at = null;
                $newQuery->data_lama = null;
                $newQuery->is_approved = 0;
                $newQuery->approved_by = null;
                $newQuery->approved_at = null;
                $newQuery->is_emailed = 0;
                $newQuery->is_ready_order = 0;
                $newQuery->emailed_at = null;
                $newQuery->emailed_by = null;
                $newQuery->is_generated = 0;
                $newQuery->generated_at = null;
                $newQuery->generated_by = null;
                $newQuery->id_token = null;
                $newQuery->save();

                $query1 = QuotationKontrakD::where('id_request_quotation_kontrak_h', $request->id)->get();
                foreach ($query1 as $value => $a) {

                    $query2 = QuotationKontrakD::where('id', $a->id)->firstOrFail();
                    $newQuery2 = $query2->replicate();
                    $newQuery2->id_request_quotation_kontrak_h = $newQuery->id;
                    $newQuery2->save();
                }

                JobTask::insert([
                    'job' => 'RenderPdfPenawaran',
                    'status' => 'processing',
                    'no_document' => $newQuery->no_document,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                DB::commit();

                $job = new RenderPdfPenawaran($newQuery->id, 'kontrak');
                $this->dispatch($job);

                $array_id_user = GetAtasan::where('id', $query->sales_id)->get()->pluck('id')->toArray();

                Notification::whereIn('id', $array_id_user)
                    ->title('Penawaran telah diperbarui')
                    ->message('Penawaran dengan nomor ' . $cek->no_document . ' telah berhasil di salin ke nomor ' . $newQuery->no_document . '.')
                    ->url('/quote-request')
                    ->send();

                return response()
                    ->json(['message' => "Request Quotation number $no_document success created", 'status' => 200], 200);
            }
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function void(Request $request)
    {
        /*DB::beginTransaction();
        try {
            if ($request->mode == 'non_kontrak' || $request->mode == "null") {
                $data = QuotationNonKontrak::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation';
                if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                    $data->is_ready_order = 1;
                }
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation_kontrak';
            }
            $order_h = OrderHeader::where('no_document', $data->no_document)
                ->update(['is_active' => false, 'is_revisi' => 1]);

            $get_id_header = OrderHeader::where('no_document', $data->no_document)
                ->first();

            // UPDATE TABLE DETAIL MENJADI NON AKTIF
            $order_h = OrderDetail::where('id_order_header', $get_id_header->id)
                ->update(['is_active' => false]);

            // UPDATE SAMPLING PLAN TABLE
            $sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)
                ->where('is_active', true)
                // ->get();
                ->update(['is_active' => false]);

            // if($sampling_plan->isNotEmpty()){

            // }

            // UPDATE JADWAL
            $jadwal = Jadwal::where('no_quotation', $data->no_document)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // UPDATE TABLE PRODUKSI
            // DB::connection(env('DB_PRODUKSI'))->table('t_po')->where('no_order', $get_id_header->no_order)->update(['active' => 1]);

            // $order_d = DB::table('order_detail')->where('id_order_header', $get_id_header->id)->get();

            // foreach($order_d as $key => $v){
            //     // UPDATE TABLE PRODUKSI
            //     DB::connection(env('DB_PRODUKSI'))->table('t_ftc')->where('no_sample', $v->no_sample)->update(['active' => 1]);
            //     DB::connection(env('DB_PRODUKSI'))->table('t_ftc_t')->where('no_sample', $v->no_sample)->update(['active' => 1]);

            //     // UPDATE TABLE APPS BARU
            //     DB::table('t_ftc')->where('no_sample', $v->no_sample)->update(['active' => 1]);
            //     DB::table('t_ftc_t')->where('no_sample', $v->no_sample)->update(['active' => 1]);
            // }

            $data->flag_status = 'void';
            $data->is_active = false;
            $data->document_status = 'Non Aktif';
            $data->save();

            DB::commit();
            return response()
                ->json(['message' => 'Success Void Quotation Order!', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }*/

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

                $order_h = OrderHeader::where('no_document', $data->no_document)->first();
                $order_h->is_active = false;
                $order_h->is_revisi = true;
                $order_h->save();

                $order_d = OrderDetail::where('id_order_header', $order_h->id);
                $no_sampels = $order_d->pluck('no_sampel');
                $order_d->update(['is_active' => false]);

                Ftc::whereIn('no_sample', $no_sampels)->update(['is_active' => false]);
                FtcT::whereIn('no_sample', $no_sampels)->update(['is_active' => false]);

                $sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)->update(['is_active' => false]);
                $jadwal = Jadwal::where('no_quotation', $data->no_document)->update(['is_active' => false]);

                $data->flag_status = 'void';
                $data->is_active = false;
                $data->document_status = 'Non Aktif';
                $data->deleted_by = $this->karyawan;
                $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $keterangan = [];
                if ($request->tanggal_next_fu) {
                    $keterangan[] = ['tanggal_next_fu' => $request->tanggal_next_fu];
                }
                if ($request->nama_lab_lain) {
                    $keterangan[] = ['nama_lab_lain' => $request->nama_lab_lain];
                }
                if ($request->budget_customer) {
                    $keterangan[] = ['budget_customer' => $request->budget_customer];
                }
                if ($request->penawaran_yg_akan_dikirim) {
                    $keterangan[] = ['penawaran_yg_akan_dikirim' => $request->penawaran_yg_akan_dikirim];
                }
                if ($request->blacklist) {
                    $keterangan[] = ['blacklist' => $request->blacklist];
                }
                if ($request->keterangan) {
                    $keterangan[] = ['keterangan' => $request->keterangan];
                }

                $alasanVoidQt = new AlasanVoidQt();
                $alasanVoidQt->no_quotation = $data->no_document;
                $alasanVoidQt->alasan = $request->alasan;
                $alasanVoidQt->keterangan = json_encode($keterangan);
                $alasanVoidQt->voided_by = $this->karyawan;
                $alasanVoidQt->voided_at = Carbon::now()->format('Y-m-d H:i:s');
                $alasanVoidQt->save();

                DB::commit();
                return response()->json([
                    'message' => 'Success void request Quotation number ' . $data->no_document . '.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot void data.!',
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
}
