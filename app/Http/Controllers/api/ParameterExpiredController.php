<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\MasterKaryawan;
use App\Models\TemplateAnalyst;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ParameterExpiredController extends Controller
{
    public function index(Request $request)
    {
        $data = Parameter::where('is_active', 1)->where('is_expired', 1)->orderBy('updated_at', 'DESC');

        return Datatables::of($data)
            ->filterColumn('nama_lab', function ($query, $keyword) {
                $query->where('nama_lab', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_regulasi', function ($query, $keyword) {
                $query->where('nama_regulasi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_lhp', function ($query, $keyword) {
                $query->where('nama_lhp', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_kategori', function ($query, $keyword) {
                $query->where('nama_kategori', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('status', function ($query, $keyword) {
                $query->where('status', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('updated_at', function ($query, $keyword) {
                $query->where('updated_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('updated_by', function ($query, $keyword) {
                $query->where('updated_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->orderColumn('nama_lab', function ($query, $order) {
                $query->orderBy('nama_lab', $order);
            })
            ->orderColumn('nama_regulasi', function ($query, $order) {
                $query->orderBy('nama_regulasi', $order);
            })
            ->orderColumn('nama_lhp', function ($query, $order) {
                $query->orderBy('nama_lhp', $order);
            })
            ->orderColumn('nama_kategori', function ($query, $order) {
                $query->orderBy('nama_kategori', $order);
            })
            ->orderColumn('status', function ($query, $order) {
                $query->orderBy('status', $order);
            })
            ->orderColumn('updated_at', function ($query, $order) {
                $query->orderBy('updated_at', $order);
            })
            ->orderColumn('updated_by', function ($query, $order) {
                $query->orderBy('updated_by', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('created_at', $order);
            })
            ->orderColumn('created_by', function ($query, $order) {
                $query->orderBy('created_by', $order);
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = null;
            if ($request->mode === 'edit') {
                $data = Parameter::where('id', $request->parameter)
                    ->where('id_kategori', $request->category)
                    ->where('is_expired', 1)
                    ->where('is_active', 1)
                    ->first();
                if ($data) {
                    $data->id_parameter_pengganti = $request->id_parameter_pengganti;
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                } else {
                    return response()->json([
                        'message' => 'Parameter (expired) not found for edit mode.'
                    ], 404);
                }
            } else {
                $data = Parameter::where('id', $request->parameter_id)
                    ->where('id_kategori', $request->category_id)
                    ->where('is_expired', 0)
                    ->where('is_active', 1)
                    ->first();
                if ($data) {
                    $data->is_expired = 1;
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                } else {
                    return response()->json([
                        'message' => 'Parameter not found'
                    ], 404);
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Parameter $request->parameter_nama set to expired.!",
                'success' => true,

            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }


        return response()->json([
            'message' => 'Data hasbeen Save.!'
        ], 200);
    }

    public function getParameter(Request $request)
    {
        $idKategori = $request->input('id_kategori');
        if ($request->mode == 'edit') {
            $data = Parameter::where('is_active', 1)
                ->where('is_expired', 1)
                ->where('id_kategori', $idKategori)
                ->select('id', 'nama_lab')
                ->get();
        } else {
            $data = Parameter::where('is_active', 1)
                ->where('is_expired', 0)
                ->where('id_kategori', $idKategori)
                ->select('id', 'nama_lab')
                ->get();
        }

        return response()->json([
            'message' => 'Data has been shown',
            'data' => $data,
        ], 200); // pakai 200 OK untuk GET-like response
    }


    public function delete(Request $request)
    {
        $data = Parameter::where('id', $request->id)
            ->where('is_expired', 1)
            ->where('is_active', 1)
            ->first();

        if (!$data) {
            return response()->json([
                'message' => 'Parameter not found'
            ], 404);
        }

        $data->is_expired = false;
        $data->updated_by = $this->karyawan;
        $data->updated_by = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus dari parameter expired'], 200);
    }
}
