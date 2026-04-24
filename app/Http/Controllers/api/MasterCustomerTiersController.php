<?php
namespace App\Http\Controllers\api;

use App\Models\MasterCustomerTiers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class MasterCustomerTiersController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterCustomerTiers::all();

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        $data = new MasterCustomerTiers();
        $data->name = $request->nama_tier;
        $data->min_point = $request->min_point;
        $data->max_point = $request->max_point;
        $data->level     = $request->level;
        $data->icon = $request->hasFile('icon')
                    ? $this->uploadFile($request->file('icon'))
                    : null;
        $data->created_at = Carbon::now();
        $data->save();

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'status'  => 200
        ]);
    }

    public function update(Request $request)
    {
        $data = MasterCustomerTiers::findOrFail($request->id);
        $data->name = $request->nama_tier;
        $data->min_point = $request->min_point;
        $data->max_point = $request->max_point;
        $data->level     = $request->level;
        $data->updated_at = Carbon::now();

        if ($request->hasFile('icon')) {

            // hapus file lama kalau ada
            if (!empty($data->icon)) {
                $oldPath = public_path('uploads/icons/' . $data->icon);

                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // upload file baru
            $data->icon = $this->uploadFile($request->file('icon'));
        }

        $data->save();

        return response()->json([
            'message' => 'Data berhasil diupdate',
            'status'  => 200
        ]);
    }

    private function uploadFile($file)
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = date('YmdHis') . '.' . $extension;
        
        $destinationPath = public_path('uploads/icons/');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0775, true);
        }

        $file->move($destinationPath, $fileName);

        return $fileName;
    }
}