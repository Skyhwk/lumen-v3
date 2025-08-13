<?php

namespace App\Http\Controllers\api;

use App\Models\QcLapangan;
use App\Models\DataLapanganAir;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;

// service
use App\Services\SaveFileServices;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class QcLapanganController extends Controller
{
    public function addQcLapangan(Request $request)
    {
        try {
            if (!$request->no_sample || $request->no_sample == null) {
                return response()->json([
                    'message' => 'NO Sample tidak boleh kosong!.'
                ], 401);
            } else {
                $check = DataLapanganAir::where('no_sampel', $request->no_sample)->first();
                if (is_null($check)) {
                    return response()->json([
                        'message' => 'No Sample tidak ditemukan!.'
                    ], 401);
                } else {
                    $jenis_botol = \str_replace("\u2082", "₂", utf8_decode(json_encode($request->jenis_botol)));
                    $jenis_botol = \str_replace("\u2084", "₄", $jenis_botol);
                    $jenis_botol = \str_replace("\u2083", "₃", $jenis_botol);
                    
                    $data = new QcLapangan;
                    if($request->idPo!='')$data->id_po = $request->idPo;
                    if($request->no_sample!='')$data->no_sampel = $request->no_sample;
                    if($request->Sjenis!='') $data->status_jenis = $request->Sjenis;
                    if($request->Npengawet!='') $data->status_pengawet = $request->Npengawet;
                    if($request->ph!='')$data->ph = $request->ph;
                    if($request->dhl!='')$data->dhl = $request->dhl;
                    if($request->suhu_air!='')$data->suhu_air = $request->suhu_air;

                    if($request->ph_lab!='')$data->ph_lab = $request->ph_lab;
                    if($request->dhl_lab!='')$data->dhl_lab = $request->dhl_lab;
                    if($request->suhu_air_lab!='')$data->suhu_air_lab = $request->suhu_air_lab;
                    if($request->jenis_botol!='')$data->jenis_botol = $jenis_botol;

                    if($request->foto_sampl!='')$data->foto_sample = self::convertImg($request->foto_sampl, 1 , $this->user_id);
                    if($request->foto_lain!='')$data->foto_lain = self::convertImg($request->foto_lain, 6 , $this->user_id);
                    if($request->keterangan!='')$data->keterangan = $request->keterangan;

                    $data->created_by = $this->karyawan;
                    $data->created_at = date('Y-m-d H:i:s');
                    $data->save();

                    return response()->json([
                        'message' => 'Data berhasil disimpan.'
                    ], 200);
                }
            }
        }catch (\Exception $e) {
            dd($e);
        }
            
    }

          public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $path = 'dokumentasi/qc';
        $service = new SaveFileServices();
        $service->saveFile($path ,  $safeName, $file);
        return $safeName;
    }

    public function getDataLapangan(Request $request) {
        try {
            if(isset($request->no_sample) && $request->no_sample != null) {
            $data = DataLapanganAir::where('no_sampel', $request->no_sample)->first();
                if($data == null){
                    return response()->json([
                        'message' => 'Data Not Found.!'
                    ], 401);
                }else {
                    return response()->json([
                        'message' => 'Data tersedia',
                        'noSampel' => $data->no_sampel,
                        'idPo' => $data->id_po,
                        'jenisSampel' => $data->jenis_sample,
                        'jenisPengawet' => $data->jenis_pengawet,
                        'dhl' => $data->dhl,
                        'ph' => $data->ph,
                        'suhu_air' =>$data->suhu_air
                    ]);
                }
            } else {
                return response()->json([
                    'message' => 'Data Not Found.!'
                ], 401);
            }
        }catch(\Exception $e){
            dd($e);
        }
    }

    function getDataQc(Request $request)
    {
        try {
            $data = array();
            $data = QcLapangan::with('detail')->where('is_active', true)->orderBy('id', 'desc');
            $this->resultx = 'Show Data QC Success';
            return Datatables::of($data)->make(true);
        }catch(\Exception $e) {
            dd($e);
        }
    }

    public function indexApps(Request $request){
        $data = QcLapangan::with('detail')->where('is_active', true)->where('created_by', $this->karyawan)->orderBy('id', 'desc')->get();
            return response()->json([
                'data'  => $data
            ], 200);
    }

    public function detailQc(Request $request){
        $data = QcLapangan::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan success';
        return response()->json([
            'id'                => $data->id,
            'id_po'             => $data->id_po,
            'no_sample'         => $data->no_sampel,
            'status_jenis'      => $data->status_jenis,
            'status_pengawet'   => $data->status_pengawet,
            'ph'                => $data->ph,
            'dhl'               => $data->dhl,
            'suhu_air'          => $data->suhu_air,

            'ph_lab'            => $data->ph_lab,
            'dhl_lab'           => $data->dhl_lab,
            'suhu_air_lab'      => $data->suhu_air_lab,
            'jenis_botol'       => $data->jenis_botol,


            'foto_sample'       => $data->foto_sample,
            'foto_lain'         => $data->foto_lain,
            'add_by'            => $data->created_by,
            'add_at'            => $data->created_at,
            'status'            => '200'
        ], 200);
    }

    public function deleteQc(Request $request){
        try{
            $data = QcLapangan::where('id', $request->id)->first();
            if(!isset($data->id)) {
                $this->resultx = 'Gagal delete QC';
                return response()->json([
                    'massage'=> $this->resultx,
                    'status' => '200'
                ], 200);
            }
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = date('Y-m-d H:i:s');
            $data->save();
            $this->resultx = 'QC berhasil di hapus';

            return response()->json([
                'massage'=> $this->resultx,
                'status' => '200'
            ], 200);
        }catch(\Exception $e){
        
        }
    }

    public function indexDelete(Request $request){
        $data = QcLapangan::with()->where('is_active', false);
        return Datatables::of($data)->make(true);
    }

    public function forceQc(Request $request){
        $foto = QcLapangan::where('id', $request->id)->first();
            $sample = $foto->foto_sample;
            $lain = $foto->foto_lain;
            $foto_sample = public_path().'/dokumentasi/qc/'.$sample;
            $foto_lain = public_path().'/dokumentasi/qc/'.$lain;
            unlink($foto_sample);
            unlink($foto_lain);
            $data = QcLapangan::where('id', $request->id)->delete();
                $this->resultx = 'QC berhasil di hapus';
                return response()->json([
                    'massage'=> $this->resultx,
                    'status' => '200'
                ], 200);
    }

    public function restoreQc(Request $request){
        $data = QcLapangan::where('id', $request->id)->first();
        $data->is_active = true;
        $data->deleted_by = $this->karyawan;
        $data->deleted_at = date('Y-m-d H:i:s');
        $data->save();
        $this->resultx = 'QC berhasil di Restore';

        return response()->json([
            'massage'=> $this->resultx,
            'status' => '200'
        ], 200);
    }
    
}