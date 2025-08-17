<?php

namespace App\Http\Controllers\api;

use App\Models\PurchaseRequest;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class PurchaseRequestController extends Controller
{
    public function index()
    {
        try {
            $data = PurchaseRequest::where('request_by', $this->karyawan)
                ->where('is_active', true)
                ->orderBy('id', 'desc');
            return Datatables::of($data)
                ->addColumn('reff', function($row) {
                    $filePath = public_path('purchase_request/' . $row->no_katalog);
                    if (file_exists($filePath) && is_file($filePath)) {
                        return file_get_contents($filePath);
                    } else {
                        return 'File not found';
                    }
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function showPurchasing(Request $request){
        try {
            $data = PurchaseRequest::where(function($query) use($request){
                if(is_array($request->mode)){
                    $query->whereIn('status',$request->mode);
                }else{
                    $query->where('status', $request->mode);
                }
            })
            ->where('is_active',1)
            ->orderBy('id', 'desc');
            return Datatables::of($data)
                ->addColumn('reff', function($row) {
                    $filePath = public_path('purchase_request/' . $row->no_katalog);
                    if (file_exists($filePath) && is_file($filePath)) {
                        return file_get_contents($filePath);
                    } else {
                        return 'File not found or is a directory';
                    }
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function store(Request $request)
    {
        try {
            if (empty($request->id)) {
            $data = new PurchaseRequest();
            $data->request_by = $this->karyawan;
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->request_time = DATE('Y-m-d H:i:s');
            $data->uniq_id = \str_replace(".", "/", microtime(true));
            $message = 'Purchase Request Berhasil Ditambahkan';
            } else {
                $data = PurchaseRequest::find($request->id);
                if (!$data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Purchase Request tidak ditemukan'
                    ], 404);
                }
                $data->updated_by = $this->karyawan;
                $data->updated_at = DATE('Y-m-d H:i:s');
                $message = 'Purchase Request Berhasil Diperbarui';
            }

            $data->nama_barang = $request->nama_barang;
            $data->merk = $request->merk;
            $data->keperluan = $request->keperluan;
            $data->quantity = $request->quantity;
            $data->satuan = $request->satuan;
            if($request->urgent === 'on'){
                $data->request_status = 'URGENT';
                $data->due_date = $request->due_date;
                $data->is_urgent = true;
            }
            $batch_id = \str_replace(".", "-", microtime(true)) . '.txt';
            $data->status = 'WAITING PROCESS';
            $filename = $batch_id;
            $content = $request->no_katalog;
            file_put_contents(public_path('purchase_request/' . $filename), $content);
            $data->no_katalog = $batch_id;

            // $data->no_katalog = $request->no_katalog;

            $data->save();

                return response()->json([
                    'success' => true,
                    'message' => $message
                ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses Purchase Request: ' . $e->getMessage()
            ], 500);
        }
    }

    public function voidData(Request $request){
        try {
            $data = PurchaseRequest::where('id', $request->id)->first();
            $data->status = 'VOID';
            $data->void_by = $this->karyawan;
            $data->void_time = DATE('Y-m-d H:i:s');
            $data->void_notes = $request->notes;
            $data->save();
            $message = 'Purchase Request Berhasil Divoid';

            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses void Purchase Request: ' . $e->getMessage()
            ], 500);
        }
    }

    public function setPurchasing(Request $request){
        try {
            //code...
            $date = DATE('Y-m-d H:i:s');
            $data = PurchaseRequest::where('id', $request->id)->first();
            
            if($request->status == 'DONE'){
                $data->done_time =$date;
                $data->done_notes =$request->reason;
                $data->done_by = $this->karyawan;
                $message = 'Approve';
            }else if($request->status == 'PENDING'){
                $data->pending_time =$date;
                $data->pending_notes =$request->reason;
                $data->pending_by = $this->karyawan;
                $message = 'Pending';
            }else if($request->status == 'REJECT'){
                $data->reject_time =$date;
                $data->reject_notes =$request->reason;
                $data->reject_by = $this->karyawan;
                $message = 'Reject';
            }else if($request->status == 'PROCESS'){
                $data->process_time =$date;
                $data->process_by = $this->karyawan;
                $message = 'Process';
            }else if($request->status == 'VOID'){
                $data->void_time =$date;
                $data->void_notes =$request->reason;
                $data->void_by = $this->karyawan;
                $data->is_active = 0;
                $message = 'Void';
                
            }
            
            $data->status = $request->status;
            $data->process_by = $this->karyawan;
            $data->save();
            
            if($data){
                return response()->json(["message"=>"berhasil di ". $message],200);
            }else{
                return response()->json(["message"=>"ada yang salah"],401);
            }
        } catch (\Exception $ex) {
            //throw $th;
            dd($ex);
        }
    }

}