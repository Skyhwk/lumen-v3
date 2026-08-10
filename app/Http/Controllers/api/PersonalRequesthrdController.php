<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PersonalRequest;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PersonalRequesthrdController extends Controller
{
    /**
     * Get list of personal requests for DataTables
     */
    public function index(Request $request)
    {
        $query = PersonalRequest::with(['masterDivisi', 'masterJabatan', 'masterCabang'])
            ->orderBy('id', 'desc');

        if ($request->has('year') && !empty($request->year)) {
            $query->whereYear('created_at', $request->year);
        }

        return Datatables::of($query)
            ->addColumn('status_label', function ($row) {
                if (isset($row->is_publish) && $row->is_publish == 1) {
                    return 'Published';
                }
                if (isset($row->is_approve) && $row->is_approve == 1) {
                    return 'Approved';
                }
                if (isset($row->is_rejected) && $row->is_rejected == 1) {
                    return 'Rejected';
                }
                if (isset($row->is_reject) && $row->is_reject == 1) {
                    return 'Rejected';
                }
                return 'Pending';
            })
            ->addColumn('request_by', function ($row) {
                return $row->created_by ?? 'Admin / HRD';
            })
            ->editColumn('divisi', function ($row) {
                return $row->masterDivisi->nama_divisi ?? $row->divisi ?? '-';
            })
            ->editColumn('posisi', function ($row) {
                return $row->masterJabatan->nama_jabatan ?? $row->posisi ?? '-';
            })
            ->editColumn('lokasi_penempatan_cabang', function ($row) {
                return $row->masterCabang->nama_cabang ?? $row->lokasi_penempatan_cabang ?? '-';
            })
            ->filterColumn('no_request', function ($q, $keyword) {
                $q->where('no_request', 'like', "%{$keyword}%");
            })
            ->filterColumn('request_type', function ($q, $keyword) {
                $q->where('request_type', 'like', "%{$keyword}%");
            })
            ->filterColumn('divisi', function ($q, $keyword) {
                $q->whereHas('masterDivisi', function ($sq) use ($keyword) {
                    $sq->where('nama_divisi', 'like', "%{$keyword}%");
                })->orWhere('divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('posisi', function ($q, $keyword) {
                $q->whereHas('masterJabatan', function ($sq) use ($keyword) {
                    $sq->where('nama_jabatan', 'like', "%{$keyword}%");
                })->orWhere('posisi', 'like', "%{$keyword}%");
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
                    $q->where('is_rejected', 1)->orWhere('is_reject', 1);
                } elseif (strpos('pending', $keyword) !== false) {
                    $q->where('is_approve', 0)->where('is_rejected', 0)->where('is_reject', 0);
                }
            })
            ->filterColumn('request_by', function ($q, $keyword) {
                // Mocked / fallback column
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
            return response()->json(['message' => 'Request ID not found'], 400);
        }

        $item = PersonalRequest::with(['masterDivisi', 'masterJabatan', 'masterCabang'])->find($id);
        if (!$item) {
            return response()->json(['message' => 'Personnel request data not found'], 404);
        }

        $data = $item->toArray();
        $data['divisi'] = $item->masterDivisi->nama_divisi ?? $item->divisi;
        $data['posisi'] = $item->masterJabatan->nama_jabatan ?? $item->posisi;
        $data['lokasi_penempatan_cabang'] = $item->masterCabang->nama_cabang ?? $item->lokasi_penempatan_cabang;

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
            return response()->json(['message' => 'Request ID not found'], 400);
        }

        $data = PersonalRequest::find($id);
        if (!$data) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        try {
            $data->update([
                'is_approve'  => 1,
                'approved_at' => Carbon::now(),
                'approved_by' => $this->karyawan ?? null
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Personnel request {$data->no_request} has been successfully approved.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to approve request: ' . $e->getMessage(),
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
            return response()->json(['message' => 'Request ID not found'], 400);
        }

        $data = PersonalRequest::find($id);
        if (!$data) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        try {
            $data->update([
                'is_reject'   => 1,
                'rejected_at' => Carbon::now(),
                'rejected_by' => $this->karyawan ?? null
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Personnel request {$data->no_request} has been successfully rejected.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reject request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Publish personal request with division alias
     */
    public function publish(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'Request ID not found'], 400);
        }

        $data = PersonalRequest::find($id);
        if (!$data) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        try {
            $data->update([
                'divisi_alias'     => $request->input('divisi_alias'),
                'minimum_matching' => $request->input('minimum_matching'),
                'is_publish'       => 1,
                'published_by'     => $this->karyawan ?? null,
                'published_at'     => Carbon::now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Personnel request {$data->no_request} has been successfully published.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to publish request: ' . $e->getMessage(),
            ], 500);
        }
    }
}
