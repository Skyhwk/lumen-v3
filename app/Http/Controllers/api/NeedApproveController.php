<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{QuotationNonKontrak, QuotationKontrakH};
use App\Models\{OrderHeader, OrderDetail};
use App\Models\SamplingPlan;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use Exception;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NeedApproveController extends Controller
{
    public function getCabang()
    {
        $data = MasterCabang::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function index(Request $request)
    {
        if ($request->mode == 'non_kontrak') {
            $data = QuotationNonKontrak::where('is_active', $request->is_active)
                ->where('id_cabang', $request->id_cabang)
                ->where('is_approved', $request->is_approve)
                ->where('is_active', true)
                ->where('flag_status', $request->flag)
                ->whereYear('tanggal_penawaran', $request->periode);
        } else if ($request->mode == 'kontrak') {
            $data = QuotationKontrakH::where('is_active', $request->is_active)
                ->where('id_cabang', $request->id_cabang)
                ->where('is_approved', $request->is_approve)
                ->where('is_active', true)
                ->where('flag_status', $request->flag)
                ->whereYear('tanggal_penawaran', $request->periode);
        }

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $data->where('sales_id', $this->user_id);
                break;
            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, (string) $this->user_id);
                $data->whereIn('sales_id', $bawahan);
                break;
        }

        return Datatables::of($data)
            ->orderColumn('nama_sales', function ($query, $order) {
                $query->orderBy('sales_id', $order);
            })->make(true);
    }

    public function approve(Request $request)
    {
        /*if ($request->mode == 'non_kontrak') {
            $data = QuotationNonKontrak::where('id', $request->id)->update([
                'is_approved' => true,
                'approved_by' => $this->karyawan,
                'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        } else if ($request->mode == 'kontrak') {
            $data = QuotationKontrakH::where('id', $request->id)->update([
                'is_approved' => true,
                'approved_by' => $this->karyawan,
                'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        } else {
            return response()->json([
                "message" => "Module not found",
            ], 400);
        }

        return response()->json([
            "message" => "Data Quotation Approved",
        ], 200);*/

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
                $cek_sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', true)->first();

                if (!is_null($order_h)) {
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');

                    $order_h->is_active = true;
                    $order_h->is_revisi = false;
                    $order_h->save();

                    $order_d = OrderDetail::where('id_order_header', $order_h->id);
                    $no_sampels = $order_d->pluck('no_sampel');
                    $order_d->update(['is_active' => true]);

                    Ftc::whereIn('no_sample', $no_sampels)->update(['is_active' => true]);
                    FtcT::whereIn('no_sample', $no_sampels)->update(['is_active' => true]);

                    $data->flag_status = 'ordered';
                } else if ($cek_sp != null && $data->is_emailed == 1) {
                    $data->flag_status = 'sp';
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                } else if ($cek_sp == null && $data->is_emailed == 1) {
                    $data->flag_status = 'emailed';
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                } else if ($data->flag_status == 'draft') {
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $data->flag_status = 'draft';
                }
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Request Quotation number ' . $data->no_document . ' success approved.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot approved data.!',
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

    public function rejectData(Request $request)
    {
        /*if ($request->mode == 'non_kontrak') {
            $data = QuotationNonKontrak::where('id', $request->id)->update([
                'is_approved' => false,
                'approved_by' => NULL,
                'approved_at' => NULL,
                'flag_status' => 'rejected',
                'is_rejected' => true,
                'rejected_by' => $this->karyawan,
                'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'keterangan_reject' => $request->keterangan
            ]);
        } else if ($request->mode == 'kontrak') {
            $data = QuotationKontrakH::where('id', $request->id)->update([
                'is_approved' => false,
                'approved_by' => NULL,
                'approved_at' => NULL,
                'flag_status' => 'rejected',
                'is_rejected' => true,
                'rejected_by' => $this->karyawan,
                'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'keterangan_reject' => $request->keterangan
            ]);
        } else {
            return response()->json([
                "message" => "Module not found",
            ], 400);
        }

        return response()->json([
            "message" => "Data Quotation Rejected",
        ], 200);*/

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
}
