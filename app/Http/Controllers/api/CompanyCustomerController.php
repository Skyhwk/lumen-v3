<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\CompanyCustomer;
use Illuminate\Http\Request;
use DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class CompanyCustomerController extends Controller
{
    public function index(){
        $data = CompanyCustomer::where('is_active', true)->get();
        $data->map(function ($item) {
            $imagePath = public_path('profile/customer/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/customer/' . $item->image;
            }
            return $item;
        });
        return Datatables::of($data)->make(true);
    }

    public function indexApi(){
        $data = CompanyCustomer::where('is_active', true)->get();
        $data->map(function ($item) {
            $imagePath = public_path('profile/customer/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/customer/' . $item->image;
            }
            return $item;
        });
        return response()->json($data,200);
    }

    public function store(Request $request) {
        DB::beginTransaction();
        try{
            if($request->id == null || $request->id == '') {
                $data = new CompanyCustomer();
            } else {
                $data = CompanyCustomer::find($request->id);
            }

            if($request->hasFile('image')) {
                $file = $request->file('image');
                $originalExtension = $file->getClientOriginalExtension();
                $uniqueId = uniqid('IMG');
                $filename_img = "ISL-CUSTOMER-" . $uniqueId . "." . $originalExtension;
                $path = public_path('profile/customer');
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $file->move($path, $filename_img);
                $data->image = $filename_img;
            }

            $data->name = $request->name;

            if($request->id == null || $request->id == '') {
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }
            $data->save();
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $path = public_path('profile/customer/' . $filename_img);
            if (file_exists($path)) {
                unlink($path);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}