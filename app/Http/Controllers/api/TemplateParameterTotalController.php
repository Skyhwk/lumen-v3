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

class TemplateParameterTotalController extends Controller
{
    public function index(Request $request)
    {
        $data = ParameterTotal::with('parameter')->where('is_active', 1)->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->editColumn('id_child', function ($data) {
                $id_child = json_decode($data->id_child, true);
                return $id_child;
            })
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
        $data = ParameterTotal::with('parameter')->where('id', $request->id)->first();
        $children = array_map(function ($item) {
            $param = Parameter::select('id', 'nama_lab')->find($item);
            return $param;
        }, json_decode($data->id_child, true) ?: []);

        $data->children = $children;

        $form_children = [];

        foreach ($children as $item) {
            $parameter = AnalisParameter::where('parameter_id', $item->id)->first();
            $body = AnalisInput::where('id', $parameter->id_form)->first();
            
            // Ambil nama parameter sebagai key
            $namaParameter = $item->nama_lab;

            // Decode body form menjadi array
            $formBody = json_decode($body->body, true);

            // Masukkan ke array asosiatif
            $form_children[$namaParameter] = $formBody;
        }

        $data->form_children = $form_children;
        
        return response()->json([
            'data' => $data
        ],200);
    }

    public function getParameters(Request $request)
    {
        $data = Parameter::where('id_kategori', $request->id_kategori)->where('is_active', 1)->orderBy('id', 'DESC');
        if(isset($request->search)){
            $data->where('nama_lab', 'like', '%'.$request->search.'%');
        }
        if(isset($request->exclude_id) && !empty($request->exclude_id)){
            $data->whereNotIn('id', explode(';', $request->exclude_id));
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
            $parameter_id = explode(';', $request->parameter)[0];
            $parameter_name = explode(';', $request->parameter)[1];
            $children = array_map(function($item) { return (int) explode(';', $item)[0]; }, $request->children);
            if ($request->id == null || $request->id == '') {
                $parameterTotal = new ParameterTotal();
                $parameterTotal->parameter_id = $parameter_id;
                $parameterTotal->parameter_name = $parameter_name;
                $parameterTotal->id_stp = $request->id_stp;
                $parameterTotal->id_child = json_encode($children);
                $parameterTotal->created_at = Carbon::now();
                $parameterTotal->created_by = $this->karyawan;
                $parameterTotal->save();
            }else {
                $parameterTotal = ParameterTotal::find($request->id);
                $parameterTotal->parameter_id = $parameter_id;
                $parameterTotal->parameter_name = $parameter_name;
                $parameterTotal->id_stp = $request->id_stp;
                $parameterTotal->id_child = json_encode($children);
                $parameterTotal->updated_at = Carbon::now();
                $parameterTotal->updated_by = $this->karyawan;
                $parameterTotal->save();
            }
            
            foreach ($request->children as $key => $value) {
                $param = explode(';', $value);
                $stp = TemplateStp::where('param', 'LIKE', "%$param[1]%")->where('category_id', $request->category_id)->where('is_active', 1)->first();
                $id_stp = $stp->id ?? null;
                // dd($stp);
                $analisParameter = AnalisParameter::where('parameter_id', $param[0])->where('id_stp', $id_stp)->first();
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

                $analisInput->body = json_encode($request->form_children[$param[1]]);
                $analisInput->save();
                // dd($analisParameter);
                $analisParameter->parameter_id = $param[0];
                $analisParameter->parameter_name = $param[1];
                $analisParameter->id_stp = $id_stp;
                $analisParameter->id_form = $analisInput->id;
                $analisParameter->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Data hasbeen Save.!'
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
            $data = ParameterTotal::find($id);

            if(!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $children = json_decode($data->id_child);

            $analisParameter = AnalisParameter::whereIn('parameter_id', $children)->get();
            foreach ($analisParameter as $item) {
                $analisInput = AnalisInput::find($item->id_form);
                $analisInput->is_active = false;
                $analisInput->deleted_at = Carbon::now();
                $analisInput->deleted_by = $this->karyawan;

                $item->is_active = false;
                $item->deleted_at = Carbon::now();
                $item->deleted_by = $this->karyawan;

                $analisInput->save();
                $item->save();
            }

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