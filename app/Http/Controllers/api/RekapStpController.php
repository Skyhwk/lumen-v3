<?php

namespace App\Http\Controllers\api;

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\OrderDetail;
use App\Models\Ftc;
use App\Models\MasterKategori;
use App\Services\StpRender;
use Illuminate\Http\Request;

class RekapStpController extends Controller
{

    public function index(Request $request)
    {
        try {
            if(isset($request->tgl) && $request->tgl!=null && isset($request->category) && $request->category!=null && $request->id_stp!=null){
                // Mendapatkan tanggal dari request
                $date = $request->tgl;

                // Membuat Carbon instance untuk manipulasi tanggal/waktu
                $targetDate = \Carbon\Carbon::parse($date);

                // Cek apakah hari Senin
                $isMondayAdjustment = $targetDate->dayOfWeek === \Carbon\Carbon::MONDAY;

                // Jika hari Senin, sesuaikan tanggal awal (mundur 2 hari)
                $startDateBase = $isMondayAdjustment ? 
                $targetDate->copy()->subDays(2) : 
                $targetDate->copy();

                // Membuat range 12 jam sebelum dan 12 jam setelah
                // Untuk hari Senin, range dimulai dari Sabtu jam tertentu
                $startDate = $startDateBase->copy()->subHours(12)->format('Y-m-d H:i:s');
                $endDate = $targetDate->copy()->addHours(12)->format('Y-m-d H:i:s');
                
                // Query dengan range waktu
                $join = Ftc::with('order_detail')
                    ->where('ftc_laboratory', '>=', $startDate)
                    ->where('ftc_laboratory', '<=', $endDate)
                    ->whereHas('order_detail', function($query) use ($request) {
                        $query->where('kategori_2', $request->category)
                            ->where('is_active', 1);
                    })
                    ->get();

                $data = [];

                $template = TemplateStp::where('id', $request->id_stp)->first();
                if(!$template){
                    return response()->json([
                        'message' => 'Template tidak ditemukan',               
                    ], 404);
                }
                $par = json_decode($template->param);

                foreach ($par as $paramKey) {
                    $data[$paramKey] = [];
                }
                        
                if($join->isEmpty()){
                    return response()->json([
                        'message' => 'Data tidak ditemukan',               
                    ], 404);
                }

                $join->each(function($item) use (&$data, $par) {
                    foreach(json_decode($item->order_detail->parameter) as $key => $value){
                        $paramKey = explode(';', $value)[1];
                        if(!isset($data[$paramKey])) {
                            $data[$paramKey] = [];
                        }
                        if(in_array($paramKey, $par)) {
                            $data[$paramKey][] = (object) ['no_sampel' => $item->no_sample, 'lab_sample' => $item->ftc_laboratory];
                        }
                    }
                });

                $data = array_filter($data, function($value, $key) use ($par) {
                    if(!in_array($key, $par)){
                        return false;
                    }else{
                        return true;
                    }
                }, ARRAY_FILTER_USE_BOTH);

                return response()->json([
                    'message' => 'Sukses mendapatkan data',
                    'data' => $data,
                ], 200);
            } else if(isset($request->tgl) && $request->tgl!=null && isset($request->category) && $request->category!=null){
                // Mendapatkan tanggal dari request
                $date = $request->tgl;

                // Membuat Carbon instance untuk manipulasi tanggal/waktu
                $targetDate = \Carbon\Carbon::parse($date);

                // Cek apakah hari Senin
                $isMondayAdjustment = $targetDate->dayOfWeek === \Carbon\Carbon::MONDAY;

                // Jika hari Senin, sesuaikan tanggal awal (mundur 2 hari)
                $startDateBase = $isMondayAdjustment ? 
                $targetDate->copy()->subDays(2) : 
                $targetDate->copy();

                // Membuat range 12 jam sebelum dan 12 jam setelah
                // Untuk hari Senin, range dimulai dari Sabtu jam tertentu
                $startDate = $startDateBase->copy()->subHours(12)->format('Y-m-d H:i:s');
                $endDate = $targetDate->copy()->addHours(12)->format('Y-m-d H:i:s');

                // Query dengan range waktu
                $join = Ftc::with('order_detail')
                    ->where('ftc_laboratory', '>=', $startDate)
                    ->where('ftc_laboratory', '<=', $endDate)
                    ->whereHas('order_detail', function($query) use ($request) {
                        $query->where('kategori_2', $request->category)
                            ->where('is_active', 1);
                    })
                    ->get();

                $data = array();

                if($join->isEmpty()){
                    return response()->json([
                        'message' => 'Data tidak ditemukan',                
                    ], 404);
                }

                // Using use($data) to access the $data variable inside the closure
                // Or better, define $data inside the closure
                $data = [];
                $join->each(function($item) use (&$data) {
                    foreach(json_decode($item->order_detail->parameter) as $key => $value){
                        $paramKey = explode(';', $value)[1];
                        if(!isset($data[$paramKey])) {
                            $data[$paramKey] = [];
                        }
                        $data[$paramKey][] = (object) ['no_sampel' => $item->no_sample, 'lab_sample' => $item->ftc_laboratory];
                    }
                });

                return response()->json([
                    'message' => 'Sukses mendapatkan data',
                    'data' => $data,
                ], 200);

            }else{
                return response()->json([
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }
        } catch (\Exception $th) {
            return response()->json([
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function printPdf(Request $request) {
		try {
			// dd($request->all());
			$decodedData = json_decode($request->collectionData, true);
			$filename = StpRender::renderPdf($decodedData, $request->tanggal, $request->category, $request->stp);
			// dd($filename);
			return response()->json([
				'filename' => $filename
			],200);
		}catch (\Exception $e) {
			return response()->json([
				'message' => 'Gagal mengambil data: '.$e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			]);
		}
	}

    public function getCategory()
    {
        $data = MasterKategori::where('is_active', true)->select('id','nama_kategori')->get();
        return response()->json($data);
    }

    public function getTemplate(Request $request)
    {
        try {
            $data = TemplateStp::where('is_active', true)
                ->where('category_id', $request->id_kategori)
                ->select('id','name')
                ->get();

            return response()->json($data);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: '.$e->getMessage(),
                'status' => '500'
            ],500);
        }
    }


}