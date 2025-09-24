<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use App\Services\Notification;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Models\RequestQR;
use App\Models\Parameter;
use App\Models\HargaParameter;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\MasterPelanggan;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;

class RecapRequestQrController extends Controller
{
    public function getCabang()
    {
        $data = MasterCabang::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function index(Request $request)
    {
        try {
            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            $id_jabatan = [21, 24]; // Can't View All
            $periode = $request->periode;

            // Buat query builder tanpa execute get() dulu
            $dataQuery = RequestQR::where('tipe', $request->mode)
                ->whereYear('created_at', $periode);

            if (in_array($jabatan, $id_jabatan)) {
                $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
                if ($jabatan == 21) {
                    $dataQuery->whereIn('created_by', $getBawahan);
                } else if ($jabatan == 24) {
                    $dataQuery->where('created_by', $this->karyawan);
                }
            }

            $dataQuery->orderBy('created_at', 'desc');

            // PERBAIKAN: Gunakan query builder, bukan collection untuk DataTables
            return DataTables::of($dataQuery) // Gunakan $dataQuery bukan $requestQRData
                ->editColumn('data_pendukung_sampling', function ($item) {
                    return json_decode($item->data_pendukung_sampling);
                })
                ->filterColumn('status', function ($query, $keyword) {
                    if (Str::contains($keyword, 'Exp') || Str::contains($keyword, 'exp')) {
                        $query->where('is_active', 0)->where('is_rejected', 0)->where('is_processed', 0);
                    } elseif (Str::contains($keyword, 'Pro') || Str::contains($keyword, 'pro')) {
                        $query->where('is_processed', 1);
                    } elseif (Str::contains($keyword, 'Rej') || Str::contains($keyword, 'rej')) {
                        $query->where('is_rejected', 1);
                    } else if (Str::contains($keyword, 'Dr') || Str::contains($keyword, 'dr')) {
                        $query->where('is_active', 1)->where('is_rejected', 0)->where('is_processed', 0);
                    }
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(), 
                'line' => $e->getLine(), 
                'file' => $e->getFile()
            ], 500);
        }
    }

     // public function index(Request $request)
    // {
    //     try{
    //         $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
    //         // $jabatan = 21;
    //         $id_jabatan = [21,24]; // Can't View All
    //         $data = RequestQR::where('is_active', true)
    //             ->where('tipe', $request->mode);
    //         if(!in_array($jabatan, $id_jabatan)){
    //                 $data->where(function ($query) {
    //                     $query->where('is_rejected', false)
    //                         ->where('is_processed', false);
    //                 });
                    
    //         }else{
    //             $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
    //             if($jabatan == 21){
    //                 $data->whereIn('created_by', $getBawahan);
    //             }else if($jabatan == 24){
    //                 $data->where('created_by', $this->karyawan);
    //             }
    //         }
    //         $data = $data->get();
    //         $data->map(function($item){
    //             $item['data_pendukung_sampling'] = json_decode($item['data_pendukung_sampling']);
    //             // $exist = MasterPelanggan::where('sales_penanggung_jawab', $item['created_by'])
    //             //     ->where(function($query) use ($item) {
    //             //         $query->where('konsultan', $item['konsultan'])
    //             //             ->orWhere('konsultan', $item['nama_pelanggan']);
    //             //     })->first();
    //             $exist = MasterPelanggan::where('sales_penanggung_jawab', 'Yeni Novia')
    //                 ->where(function($query) use ($item) {
    //                     if($item['konsultan'] != null){
    //                         $query->where('nama_pelanggan', $item['konsultan']);
                            
    //                     }else{
    //                         $query->where('nama_pelanggan', $item['nama_pelanggan']);
    //                     }
    //                 })->first();

    //             if($exist != null){
    //                 $item['can_processed'] = true;
    //                 $item['id_pelanggan'] = $exist->id_pelanggan;
    //                 $item['sales_id'] = $exist->sales_id;
    //             }else{
    //                 $item['can_processed'] = false;
    //                 $item['id_pelanggan'] = null;
    //                 $item['sales_id'] = null;
    //             }
    //             return $item;
    //         });
    //         return DataTables::of($data)->make(true);
    //     }catch(\Exception $e){
    //         return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
    //     }
    // }

}
