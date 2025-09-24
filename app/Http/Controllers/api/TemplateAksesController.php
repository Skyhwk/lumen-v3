<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TemplateAkses;
use App\Models\MasterKaryawan;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TemplateAksesController extends Controller
{
    public function index()
    {
        if ($this->user_id == 1 || $this->user_id == 127) { // hak seluruh template
            $data = TemplateAkses::where('is_active', true);

            return DataTables::of($data)->make(true);
        } else {
            $data = TemplateAkses::where('is_active', true)->where('userid', $this->user_id);
            return DataTables::of($data)->make(true);
        }
    }

    public function store(Request $request)
    {
        $aksesArray = json_decode($request->akses, true);
        if ($request->id) {
            $data = TemplateAkses::find($request->id);
            $data->update([
                'nama_template' => $request->nama_template,
                'akses' => $aksesArray,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);
        } else {
            $data = new TemplateAkses;
            $data->nama_template = $request->nama_template;
            $data->akses = json_encode($aksesArray);
            $data->userid = $this->user_id;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();
        }

        return response()->json(['message' => 'Data berhasil disimpan']);
    }
    public function delete(Request $request)
    {
        $data = TemplateAkses::find($request->id);
        $data->is_active = false;
        $data->save();

        return response()->json(['message' => 'Data berhasil dihapus']);
    }
}
