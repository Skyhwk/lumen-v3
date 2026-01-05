<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TemplateSatuan;
use Carbon\Carbon;
use DataTables;
use Illuminate\Support\Facades\DB;

class TemplateSatuanController extends Controller
{
    public function index(Request $request)
    {
        $data = TemplateSatuan::where('is_active', true);
        
        return DataTables::of($data)->make(true);
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $microtime = microtime(true);
            $no_dokumen = str_replace('.', '/', $microtime);

            $salesIn = new TemplateSatuan();            
            $salesIn->id_kategori = \explode('-', $request->kategori)[0];
            $salesIn->kategori = \explode('-', $request->kategori)[1];
            $salesIn->satuan = $request->satuan;
            $salesIn->column_number = $request->column_number;
            $salesIn->created_by = $this->karyawan;
            $salesIn->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $salesIn->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil ditambahkan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function update(Request $request)
    {
        try {
            $data = TemplateSatuan::find($request->id);
            if($request->column == 'kategori') {
                $data->id_kategori = \explode('-', $request->value)[0];
                $data->kategori = \explode('-', $request->value)[1];
            } else {
                $column = $request->column;
                $value = $request->value;
                $data->$column = $value;
            }
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            return response()->json(['message' => 'Data berhasil diubah']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = TemplateSatuan::find($request->id);
            $data->is_active = false;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

}