<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ParameterTotal;
use App\Models\Parameter;
use App\Models\MasterKaryawan;
use App\Models\MasterKategori;
use App\Models\TemplateAnalyst;
use App\Models\AnalisInput;
use App\Models\AnalisParameter;
use App\Models\TemplateStp;
use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use App\Services\AnalystFormula;
use App\Services\AutomatedFormula;
use App\Models\AnalystFormula as Formula;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use DB;

class TemplateInputFormController extends Controller
{
    public function index(Request $request)
    {
        $data = AnalisParameter::with(['parameter','template'])->where('is_active', 1)->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->filterColumn('parameter_name', function ($query, $keyword) {
                $query->where('parameter_name', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function getTemplate(Request $request)
    {
        $data = TemplateStp::where('is_active', true)
            ->where('name', 'like', '%' . $request->search . '%')
            ->where('category_id', $request->id_kategori)
            ->select('id','name')
            ->get();
        return response()->json($data);
    }

    public function show(Request $request)
    {
        $data = AnalisParameter::with(['parameter','input','template'])->where('id', $request->id)->where('is_active', 1)->first();
        $data->input->body = json_decode($data->input->body) ?? [];
        
        return response()->json([
            'data' => $data
        ],200);
    }

    public function getParameters(Request $request)
    {
        $stp = TemplateStp::where('id', $request->id_stp)->first();
        $parameterList = json_decode($stp->param);
        $analisInput = AnalisParameter::where('id_stp', $stp->id)->where('is_active', 1)->get()->pluck('parameter_name')->toArray();
        $selisih = array_diff($parameterList, $analisInput);
        // dd($selisih);
        $data = Parameter::whereIn('nama_lab', $selisih)->where('id_kategori', $stp->category_id)->where('is_active', 1)->orderBy('id', 'DESC');
        if(isset($request->search)){
            $data->where('nama_lab', 'like', '%'.$request->search.'%');
        }
        $data = $data->get();

        return response()->json([
            'data' => $data
        ],200);
    }

    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            // dd($request->all());
            $param = explode(';', $request->parameter);
            $stp = TemplateStp::where('id', $request->id_stp)->where('is_active', 1)->first();
            $id_stp = $stp->id ?? null;
            // dd($stp);
            $analisParameter = AnalisParameter::where('parameter_id', $param[0])->where('id_stp', $id_stp)->where('is_active', true)->first();
            if ($analisParameter) {
                $analisParameter->updated_at = Carbon::now();
                $analisParameter->updated_by = $this->karyawan;
            }else{
                $analisParameter = new AnalisParameter();
                $analisParameter->created_at = Carbon::now();
                $analisParameter->created_by = $this->karyawan;
            }

            $analisInput = AnalisInput::where('id', $analisParameter->id_form)->first();
            if (isset($analisInput->id)) {
                // dd($analisInput);
                $analisInput->updated_at = Carbon::now();
                $analisInput->updated_by = $this->karyawan;
            }else{
                $analisInput = new AnalisInput();
                $analisInput->created_at = Carbon::now();
                $analisInput->created_by = $this->karyawan;
            }

            $analisInput->body = json_encode($request->form_fields);
            $analisInput->save();
            // dd($analisParameter);
            $analisParameter->parameter_id = $param[0];
            $analisParameter->parameter_name = $param[1];
            $analisParameter->id_stp = $id_stp;
            $analisParameter->has_child = $request->hasChild == 'true' ? true : false;
            $analisParameter->id_form = $analisInput->id;
            $analisParameter->save();
            // dd($analisInput, $analisParameter);
            DB::commit();
            return response()->json([
                'message' => 'Parameter Input Form Has Been Saved'
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function delete($id) {
        DB::beginTransaction();
        try {
            $data = AnalisParameter::find($id);
            $data->is_active = false;
            $data->deleted_at = Carbon::now();
            $data->deleted_by = $this->karyawan;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }
}