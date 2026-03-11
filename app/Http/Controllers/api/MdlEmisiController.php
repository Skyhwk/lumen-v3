<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

use App\Models\MdlEmisi;
use App\Models\Parameter;

class MdlEmisiController extends Controller
{
    public function index()
    {
        $mdlEmisi = MdlEmisi::with('parameter')->where('is_active', true)->latest();

        return Datatables::of($mdlEmisi)
            ->filterColumn('parameter.nama_lab', fn($q1, $keyword) => $q1->whereHas('parameter', fn($q2) => $q2->where('nama_lab', 'like', "%$keyword%")->where('is_active', true)))
            ->make(true);
    }

    public function fetchParameters()
    {
        $parameters = Parameter::where(['id_kategori' => 5, 'is_active' => true])->orderBy('nama_lab')->get(['id', 'nama_lab']);

        return response()->json($parameters);
    }

    public function save(Request $request)
    {
        if ($request->id) {
            $mdlEmisi = MdlEmisi::find($request->id);

            if ($mdlEmisi->parameter_id !== $request->parameter_id) {
                $isParameterExists = MdlEmisi::where(['parameter_id' => $request->parameter_id, 'is_active' => true])->where('id', '!=', $request->id)->exists();
                if ($isParameterExists) return response()->json(['message' => 'Parameter already exists'], 400);
            }

            $mdlEmisi->updated_by = $this->karyawan;
            $mdlEmisi->updated_at = Carbon::now();
        } else {
            $isParameterExists = MdlEmisi::where(['parameter_id' => $request->parameter_id, 'is_active' => true])->exists();
            if ($isParameterExists) return response()->json(['message' => 'Parameter already exists'], 400);

            $mdlEmisi = new MdlEmisi();

            $mdlEmisi->created_by = $this->karyawan;
            $mdlEmisi->created_at = Carbon::now();
        }

        $mdlEmisi->parameter_id = $request->parameter_id;
        for ($i = 0; $i <= 11; $i++) {
            if (!$i) {
                $mdlEmisi->C = $request->C;
            } else {
                $mdlEmisi->{"C$i"} = $request->{"C$i"};
            }
        };

        $mdlEmisi->save();

        return response()->json(['message' => 'Saved successfully'], 200);
    }

    public function delete(Request $request)
    {
        $mdlEmisi = MdlEmisi::find($request->id);

        $mdlEmisi->deleted_by = $this->karyawan;
        $mdlEmisi->deleted_at = Carbon::now();
        $mdlEmisi->is_active = false;

        $mdlEmisi->save();

        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
