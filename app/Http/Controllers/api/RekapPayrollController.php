<?php

namespace App\Http\Controllers\api;

use App\Models\PayrollHeader;
use App\Models\Payroll;
use App\Models\Kasbon;
use App\Models\PencadanganUpah;
use App\Models\DendaKaryawan;
use App\Models\MasterKaryawan;
use App\Models\RekapLiburKalender;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class RekapPayrollController extends Controller
{
    
    public function index(Request $request){
        try {
            $data = PayrollHeader::select(
                'payroll_header.*',
                DB::raw('SUM(payroll.take_home_pay) as total_take_home_pay'),
                DB::raw('COUNT(DISTINCT payroll.id) as total_payroll')
            )
            ->leftJoin('payroll', function($join) {
                $join->on('payroll.payroll_header_id', '=', 'payroll_header.id')
                     ->where('payroll.is_active', true);
            })
            ->where('payroll_header.is_active', true)
            ->where('payroll_header.is_approve', true)
            ->where('payroll_header.deleted_by', null)
            ->where('payroll_header.status', '=', 'TRANSFER')
            ->where('payroll_header.periode_payroll', 'like', $request->search . '%')
            ->groupBy(
                'payroll_header.id',
                'payroll_header.no_document',
                'payroll_header.status_karyawan',
                'payroll_header.periode_payroll',
                'payroll_header.status',
                'payroll_header.tgl_transfer',
                'payroll_header.keterangan',
                'payroll_header.is_active',
                'payroll_header.is_approve',
                'payroll_header.is_download',
                'payroll_header.created_at',
                'payroll_header.created_by',
                'payroll_header.deleted_at',
                'payroll_header.deleted_by'
            )
            ->orderBy('payroll_header.status', 'desc')
            ->orderBy('payroll_header.id', 'desc')
            ->get();

            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function paidPayroll(Request $request){
        DB::beginTransaction();
        try {
            $payroll = Payroll::find($request->id);
            if (!$payroll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll not found'
                ], 404);
            }

            $payroll->status = $request->status;
            $payroll->updated_by = $this->karyawan;
            $payroll->updated_at = DATE('Y-m-d H:i:s');
            $payroll->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Payroll successfully $request->status"
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => "Failed to $request->status payroll: " . $e->getMessage()
            ], 500);
        }
    }
}