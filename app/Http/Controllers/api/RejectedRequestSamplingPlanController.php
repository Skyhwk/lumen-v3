<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;

class RejectedRequestSamplingPlanController extends Controller
{
    public function index(Request $request)
    {
        // Ambil ID terakhir untuk setiap group (no_quotation + periode_kontrak)
        $last = SamplingPlan::selectRaw('
        MAX(id) as id,
        no_quotation,
        periode_kontrak
    ')
            ->groupBy('no_quotation', 'periode_kontrak');

        $query = SamplingPlan::withTypeModelSub()->select('sampling_plan.*')
            ->joinSub($last, 't', function ($join) {
                $join->on('sampling_plan.id', '=', 't.id');
            })
            ->where('sampling_plan.is_active', false)
            ->whereNotNull('sampling_plan.deleted_by')
            ->whereNotNull('sampling_plan.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sampling_plan as sp2')
                    ->whereColumn('sp2.no_quotation', 't.no_quotation')
                    ->whereColumn('sp2.periode_kontrak', 't.periode_kontrak')
                    ->where('sp2.is_active', true);
            })
            ->orderBy('sampling_plan.id', 'DESC')
            ->get();

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $query = $query->where('created_by', $this->karyawan);
                break;
            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('nama_lengkap')
                    ->toArray();
                array_push($bawahan, $this->user_id);
                $query = $query->whereIn('created_by', $bawahan);
                break;
        }


        return Datatables::of($query)
            // ->filterColumn('created_at', function ($query, $keyword) {
            //     $query->where('created_at', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('no_document', function ($query, $keyword) {
            //     $query->where('no_document', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('no_quotation', function ($query, $keyword) {
            //     $query->where('no_quotation', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('nama_perusahaan', function ($query, $keyword) {
            //     $query->where(function ($q) use ($keyword) {
            //         $q->whereHas('quotation', function ($sub) use ($keyword) {
            //             $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
            //         })->orWhereHas('quotationKontrak', function ($sub) use ($keyword) {
            //             $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
            //         });
            //     });
            // })
            // ->filterColumn('wilayah', function ($query, $keyword) {
            //     $query->where(function ($q) use ($keyword) {
            //         $q->whereHas('quotation', function ($sub) use ($keyword) {
            //             $sub->where('wilayah', 'like', "%{$keyword}%");
            //         })->orWhereHas('quotationKontrak', function ($sub) use ($keyword) {
            //             $sub->where('wilayah', 'like', "%{$keyword}%");
            //         });
            //     });
            // })
            // ->filterColumn('opsi_1', function ($query, $keyword) {
            //     $query->where('opsi_1', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('opsi_2', function ($query, $keyword) {
            //     $query->where('opsi_2', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('periode_kontrak', function ($query, $keyword) {
            //     $query->where('periode_kontrak', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('is_sabtu', function ($query, $keyword) {
            //     $query->where('is_sabtu', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('is_minggu', function ($query, $keyword) {
            //     $query->where('is_minggu', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('is_malam', function ($query, $keyword) {
            //     $query->where('is_malam', 'like', '%' . $keyword . '%');
            // })
            // ->filterColumn('created_by', function ($query, $keyword) {
            //     $query->where('created_by', 'like', '%' . $keyword . '%');
            // })
            ->make(true);
    }
}
