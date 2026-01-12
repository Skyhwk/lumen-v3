<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

use App\Models\MdlUdara;
use App\Models\Parameter;

class MdlUdaraController extends Controller
{
    public function index()
    {
        $mdlUdara = MdlUdara::with('parameter')->latest();

        return Datatables::of($mdlUdara)
            ->filterColumn('parameter.nama_lab', fn($q1, $keyword) => $q1->whereHas('parameter', fn($q2) => $q2->where('nama_lab', 'like', "%$keyword%")->where('is_active', true)))
            ->make(true);
    }

    public function fetchParameters()
    {
        $parameters = Parameter::where(['id_kategori' => 4, 'is_active' => true])->orderBy('nama_lab')->get(['id', 'nama_lab']);

        return response()->json($parameters);
    }

    public function save(Request $request)
    {
        if ($request->id) {
            $mdlUdara = MdlUdara::find($request->id);

            if ($mdlUdara->parameter_id !== $request->parameter_id) {
                $isParameterExists = MdlUdara::where(['parameter_id' => $request->parameter_id, 'is_active' => true])->where('id', '!=', $request->id)->exists();
                if ($isParameterExists) return response()->json(['message' => 'Parameter already exists'], 400);
            }

            $mdlUdara->updated_by = $this->karyawan;
            $mdlUdara->updated_at = Carbon::now();
        } else {
            $isParameterExists = MdlUdara::where(['parameter_id' => $request->parameter_id, 'is_active' => true])->exists();
            if ($isParameterExists) return response()->json(['message' => 'Parameter already exists'], 400);

            $mdlUdara = new MdlUdara();

            $mdlUdara->created_by = $this->karyawan;
            $mdlUdara->created_at = Carbon::now();
        }

        $mdlUdara->parameter_id = $request->parameter_id;
        for ($i = 1; $i <= 19; $i++) $mdlUdara->{'hasil' . $i} = $request->{'hasil' . $i};

        $mdlUdara->save();

        return response()->json(['message' => 'Saved successfully'], 200);
    }

    public function delete(Request $request)
    {
        $mdlUdara = MdlUdara::find($request->id);

        $mdlUdara->deleted_by = $this->karyawan;
        $mdlUdara->deleted_at = Carbon::now();
        $mdlUdara->is_active = false;

        $mdlUdara->save();

        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
