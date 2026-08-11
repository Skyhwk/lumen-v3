<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

use App\Models\PersonnelRequest;

class PersonnelRequesthrdController extends Controller
{
    /**
     * Get list of personal requests for DataTables
     */
    public function index(Request $request)
    {
        try {
            $query = PersonnelRequest::with(['masterJabatan', 'masterDivisi'])->orderBy('id', 'desc');

            if ($request->has('year') && !empty($request->year)) {
                $query->whereYear('created_at', $request->year);
            }

            return Datatables::of($query)
                ->editColumn('posisi', function ($row) {
                    if ($row->masterJabatan && !empty($row->masterJabatan->nama_jabatan)) {
                        return $row->masterJabatan->nama_jabatan;
                    }
                    return $row->posisi ?: '-';
                })
                ->filterColumn('posisi', function ($q, $keyword) {
                    $q->where(function ($sub) use ($keyword) {
                        $sub->where('posisi', 'like', "%{$keyword}%")
                            ->orWhereHas('masterJabatan', function ($j) use ($keyword) {
                                $j->where('nama_jabatan', 'like', "%{$keyword}%");
                            });
                    });
                })
                ->editColumn('divisi', function ($row) {
                    if ($row->masterDivisi && !empty($row->masterDivisi->nama_divisi)) {
                        return $row->masterDivisi->nama_divisi;
                    }
                    return $row->divisi_alias ?: ($row->divisi ?: '-');
                })
                ->filterColumn('divisi', function ($q, $keyword) {
                    $q->where(function ($sub) use ($keyword) {
                        $sub->where('divisi', 'like', "%{$keyword}%")
                            ->orWhere('divisi_alias', 'like', "%{$keyword}%")
                            ->orWhereHas('masterDivisi', function ($d) use ($keyword) {
                                $d->where('nama_divisi', 'like', "%{$keyword}%");
                            });
                    });
                })
                ->addColumn('status_label', function ($row) {
                    if (isset($row->is_publish) && $row->is_publish == 1) {
                        return 'Published';
                    }
                    if (isset($row->is_approve) && $row->is_approve == 1) {
                        return 'Approved';
                    }
                    if ((isset($row->is_rejected) && $row->is_rejected == 1) || (isset($row->is_reject) && $row->is_reject == 1)) {
                        return 'Rejected';
                    }
                    return 'Pending';
                })
                ->addColumn('request_by', function ($row) {
                    return $row->created_by ?: 'Admin / HRD';
                })
                ->filterColumn('no_request', function ($q, $keyword) {
                    $q->where('no_request', 'like', "%{$keyword}%");
                })
                ->filterColumn('request_type', function ($q, $keyword) {
                    $q->where('request_type', 'like', "%{$keyword}%");
                })
                ->filterColumn('jumlah_personal', function ($q, $keyword) {
                    $q->where('jumlah_personal', 'like', "%{$keyword}%");
                })
                ->filterColumn('prioritas', function ($q, $keyword) {
                    $q->where('prioritas', 'like', "%{$keyword}%");
                })
                ->filterColumn('status_label', function ($q, $keyword) {
                    $keyword = strtolower($keyword);
                    if (strpos('published', $keyword) !== false) {
                        $q->where('is_publish', 1);
                    } elseif (strpos('approved', $keyword) !== false) {
                        $q->where('is_approve', 1);
                    } elseif (strpos('rejected', $keyword) !== false) {
                        $q->where(function ($sub) {
                            $sub->where('is_rejected', 1)->orWhere('is_reject', 1);
                        });
                    } elseif (strpos('pending', $keyword) !== false) {
                        $q->where('is_approve', 0)->where('is_rejected', 0);
                    }
                })
                ->filterColumn('request_by', function ($q, $keyword) {
                    $q->where('created_by', 'like', "%{$keyword}%");
                })
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],501);
        }
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

        $data = DB::table('personnel_requests')->where('id', $id)->first();
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

        $data = DB::table('personnel_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            DB::table('personnel_requests')->where('id', $id)->update([
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

        $data = DB::table('personnel_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            DB::table('personnel_requests')->where('id', $id)->update([
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
