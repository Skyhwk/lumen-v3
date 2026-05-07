<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use DataTables;
use Carbon\Carbon;
use App\Models\SampelSD;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\Models\CategoryValue;
use App\Models\QuotationKontrakH;
use App\Models\DataSampleDiantar;
use App\Models\SampelDiantar;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationNonKontrak;
use App\Models\MasterSubKategori;
// use App\Jobs\RenderSampelSD;

use App\Services\RenderSD;

class SampelDatangController extends Controller
{
    
    public function index()
    {
        try {
            $samples = SampelDiantar::with(['order.orderDetail' => function ($query) {
                $query->where('is_active', true)->where('kategori_1', 'SD');
            },'detail'])->orderBy('id','desc')->get();
            return DataTables::of($samples)
            ->make(true);
        } catch (\Exception $e) {
            return response()->json(["message"=>$e->getMessage(),"line"=>$e->getLine()],404);
            //throw $th;
        }
    }

    public function getNoPenawaran(Request $request)
    {
        $models = [
            'Kontrak' => QuotationKontrakH::class,
            'Non-Kontrak' => QuotationNonKontrak::class
        ];

        $data = $models[$request->tipe_penawaran]::with('order')
            ->where('no_document', 'LIKE', '%' . $request->no_quotation . '%')
            ->whereHas('order')
            ->whereNotIn('flag_status', ['void', 'rejected'])
            ->where(fn($q) => $q->where('status_sampling', 'SD')->orWhereNull('status_sampling'))
            ->where('is_active', true)
            ->limit(10)
            ->get()
            ->map(fn($item) => ['no_quotation' => $item->no_document, 'no_order' => optional($item->order)->no_order]);

        return response()->json($data, 200);
    }

    public function updateSampelSD(Request $request)
    {
        try {
            $sampel = SampelSD::find($request->id);

            if($request->no_penawaran) $sampel->no_quotation = $request->no_penawaran;
            if($request->no_order) $sampel->no_order = $request->no_order;
            if($request->tanggal_sampel_diterima) $sampel->tanggal_sampel_diterima = $request->tanggal_sampel_diterima;
            if($request->waktu_sampel_diterima) $sampel->waktu_sampel_diterima = $request->waktu_sampel_diterima;
            if($request->kondisi_keamanan_wadah_sampel) $sampel->kondisi_keamanan_wadah_sampel = json_encode(!is_array($request->kondisi_keamanan_wadah_sampel) ? [$request->kondisi_keamanan_wadah_sampel] : $request->kondisi_keamanan_wadah_sampel);
            $sampel->updated_by = $this->karyawan;
            $sampel->updated_at = date('Y-m-d H:i:s');

            $sampel->save();

            return response()->json(['message' => 'Saved Successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }

    public function getNoSampel(Request $request)
    {
        $data = OrderDetail::with('headerSD')
            ->select('no_sampel')
            ->where('no_order', $request->no_order)
            ->where('no_sampel', 'LIKE', '%' . $request->no_sampel . '%')
            ->whereNull('tanggal_terima')
            ->get();

        return response()->json($data, 200);
    }

    public function getJenisSampelAir(Request $request)
    {
        $subCategories = MasterSubKategori::where([
            'nama_kategori'=> 'AIR',
            'is_active' => true
        ])->get();

        $selectedSubCategory = OrderDetail::where('no_sampel', $request->no_sampel)->first()->kategori_3;

        return response()->json(['sub_categories' => $subCategories, 'selected_sub_category' => $selectedSubCategory], 200);
    }

    public function createSampelSD(Request $request)
    {
        try {
            $orderDetail = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('no_order', $request->no_order)
                ->where('is_active', true)
                // ->whereNull('tanggal_terima')
                ->first();

            // $orderDetail->tanggal_terima = date('Y-m-d H:i:s');

            // $orderDetail->save();

            if ($orderDetail) {
                $dataSample = new DataSampleDiantar();
                $dataSample->sampel_datang_id = $request->id;
                $dataSample->no_sample = $request->no_sampel;
                $dataSample->ph = $request->ph_sampel;
                $dataSample->suhu_air = $request->suhu_air;
                $dataSample->warna = $request->warna_sampel;
                $dataSample->jenis_sampel = explode('-', $request->jenis_sampel_air)[1];
                $dataSample->deskripsi_sampel = $request->deskripsi_titik;
                $dataSample->bau = $request->bau_sampel;
                $dataSample->dhl = $request->dhl;
                $dataSample->keruh = $request->keruh;
                $dataSample->no_order = $request->no_order;
                $dataSample->ph_sampel_lapangan = ($request->ph_sampel_lapangan != '') ? $request->ph_sampel_lapangan : null;
                $dataSample->suhu_air_lapangan = ($request->suhu_air_lapangan != '') ? $request->suhu_air_lapangan : null;
                $dataSample->dhl_lapangan = ($request->dhl_lapangan != '') ? $request->dhl_lapangan : null;
                $dataSample->created_by = $this->karyawan;
                $dataSample->created_at = date('Y-m-d H:i:s');

                $dataSample->save();
            }

            return response()->json(['message' => 'Saved Successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }

    public function detailSampelSD(Request $request)
    {
        $samples = DataSampleDiantar::where('no_order', $request->no_order)->where('is_active', true)->orderBy('no_sample')->get();

        return Datatables::of($samples, 200)->make(true);
    }

    public function viewPdf(Request $request)
    {
        try {

            $render = new RenderSD();
            if(!$request->mode){
                $render->renderHeader($request->id,null,null);
            }
            else if($request->mode == 'terima'){
                $render->renderHeader($request->id,$request->periode,$request->mode);
            }else if($request->mode == 'full'){
                $render->renderHeader($request->id,$request->periode,$request->mode);
            }else if($request->mode == 'lampiran_data'){
                
                $render->renderHeader($request->id,$request->periode,$request->mode);
            }
            // dd($render);
            // $job = new RenderSampelSD($request->id);
            // $this->dispatch($job);

            $data = SampelDiantar::where('id', $request->id)->first();
            return response()->json($data->filename, 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'failed',
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }

    public function deleteSampelSD(Request $request)
    {
        try {
            $orderDetail = OrderDetail::with('sampleDiantar')
                ->where(['no_sampel' => $request->no_sampel, 'no_order' => $request->no_order])
                ->first();

            if ($orderDetail) {
                $orderDetail->tanggal_terima = null;
                $orderDetail->save();

                $sampleDiantar = DataSampleDiantar::where('no_sample', $request->no_sampel)->first();
                $sampleDiantar->is_active = false;
                $sampleDiantar->deleted_by = $this->karyawan;
                $sampleDiantar->deleted_at = Carbon::now();
                $sampleDiantar->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Saved Succesfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }
}
