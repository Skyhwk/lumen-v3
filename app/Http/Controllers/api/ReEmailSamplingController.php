<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Models\MasterKaryawan;
use App\Models\Jadwal;
use App\Models\JadwalLibur;
use App\Models\JobTask;
use App\Models\PraNoSample;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Jobs\RenderSamplingPlan;
use App\Services\JadwalServices;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Email;
use App\Jobs\RenderAndEmailJadwal;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;

class ReEmailSamplingController extends Controller
{


    public function resendEmailJadwal (Request $request)
    {
        try{

            JobTask::insert([
                'job' => 'RenderAndEmailJadwal',
                'status' => 'processing',
                'no_document' => $request->no_document,
                'timestamp' => $timestamp
            ]);
            $dataRequest = (object) [];
            foreach ($request->all() as $key => $val) {
                $dataRequest->$key = $val;
            }
            $dataRequest->karyawan = $this->karyawan;
            $dataRequest->karyawan_id = $this->user_id;
            $dataRequest->timestamp = $timestamp;

            $job = new RenderAndEmailJadwal($dataRequest, $value);
            $this->dispatch($job);


            $resendEmail =new Email();
           
         
            if($request->mode == 're-email'){
              
                $jadwal =$resendEmail->ReemailJadwalSampling($request);
                return $jadwal;
            }else{
                return response()->json(['message' => $jadwal]);
            }
        }catch(\Exception $ex){
            dd($ex);
        }
        
    }
    public function tableReemail(Request $request)
    {
        try {
            $tempArray=[];
            $countTotalPeriode=0;
            $countTotalActive=0;
            $namaperusahaan =(string)$request->no_quotation;
            $query = SamplingPlan::with(['qoutationKontrak', 'qoutation'])
            ->where('active',0)
            ->distinct() 
            ->get()
            ->groupBy('no_quotation'); 

            
    
            $result = [];

        
            foreach ($query as $no_qt => $items) {
                $totalPeriode = $items->count(); // Total periode (jumlah item dalam grup)
                $totalIsApprove = $items->filter(function ($item) {
                    return $item->is_approve == 1 && $item->status == 1; // Hitung total yang is_approve = 1
                })->count();
            
                // Ambil item pertama dalam koleksi untuk memeriksa periode_kontrak
                $firstItem = $items->first(); // Ambil item pertama dari koleksi
                $nama_perusahaan = ($firstItem->periode_kontrak !== null) 
                    ? optional($firstItem->qoutationKontrak)->nama_perusahaan 
                    : optional($firstItem->qoutation)->nama_perusahaan;
                $id_quotation =($firstItem->periode_kontrak !== null) ? optional($firstItem->qoutationKontrak)->id : optional($firstItem->qoutation)->id;
                $id_sample =($firstItem->periode_kontrak !== null) ? $firstItem->id : $firstItem->id;
                // Simpan hasil ke dalam result
                $result[] = [
                    'no_quotation' => $no_qt, // Nomor Quotation
                    'nama_perusahaan' => $nama_perusahaan, // Nama perusahaan terkait
                    'total_periode' => $totalPeriode, // Total periode yang terkait
                    'total_is_approve' => $totalIsApprove, // Total yang sudah diapprove
                    'id_quotation' =>$id_quotation,
                    'sample_id' =>$id_sample,
                ];
            }
            // dd($result);
            
            return Datatables::of($result)->make(true);
        } catch (\Exception $ex) {
            //throw $th;
            dd($ex);
        }
    }
}