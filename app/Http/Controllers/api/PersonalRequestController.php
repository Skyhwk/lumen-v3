<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PersonalRequestController extends Controller
{
    /**
     * Get list of personal requests for DataTables
     */
    public function index(Request $request)
    {
        $query = DB::table('personal_requests');

        if ($request->has('year') && !empty($request->year)) {
            $query->whereYear('created_at', $request->year);
        }

        return Datatables::of($query)
            ->addColumn('status_label', function ($row) {
                if (isset($row->is_approve) && $row->is_approve == 1) {
                    return 'Approved';
                }
                if (isset($row->is_rejected) && $row->is_rejected == 1) {
                    return 'Rejected';
                }
                return 'Pending';
            })
            ->addColumn('request_by', function ($row) {
                return 'Admin / HRD';
            })
            ->filterColumn('no_request', function ($q, $keyword) {
                $q->where('no_request', 'like', "%{$keyword}%");
            })
            ->filterColumn('request_type', function ($q, $keyword) {
                $q->where('request_type', 'like', "%{$keyword}%");
            })
            ->filterColumn('divisi', function ($q, $keyword) {
                $q->where('divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('posisi', function ($q, $keyword) {
                $q->where('posisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('jumlah_personal', function ($q, $keyword) {
                $q->where('jumlah_personal', 'like', "%{$keyword}%");
            })
            ->filterColumn('prioritas', function ($q, $keyword) {
                $q->where('prioritas', 'like', "%{$keyword}%");
            })
            ->filterColumn('status_label', function ($q, $keyword) {
                $keyword = strtolower($keyword);
                if (strpos('approved', $keyword) !== false) {
                    $q->where('is_approve', 1);
                } elseif (strpos('rejected', $keyword) !== false) {
                    $q->where('is_rejected', 1);
                } elseif (strpos('pending', $keyword) !== false) {
                    $q->where('is_approve', 0)->where('is_rejected', 0);
                }
            })
            ->filterColumn('request_by', function ($q, $keyword) {
                // Mocked column, no DB filtering needed
            })
            ->make(true);
    }

    /**
     * Get detail of a personal request
     */
    public function show(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personal_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data personel request tidak ditemukan'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }

    /**
     * Approve personal request
     */
    public function approve(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personal_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            DB::table('personal_requests')->where('id', $id)->update([
                'is_approve' => 1,
                'is_rejected' => 0,
                'updated_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Personel request {$data->no_request} berhasil disetujui (Approved).",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyetujui request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject personal request
     */
    public function reject(Request $request)
    {
        $id = $request->input('id');

        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personal_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            DB::table('personal_requests')->where('id', $id)->update([
                'is_approve' => 0,
                'is_rejected' => 1,
                'updated_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Personel request {$data->no_request} berhasil ditolak (Rejected).",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menolak request: ' . $e->getMessage(),
            ], 500);
        }
    }
}
