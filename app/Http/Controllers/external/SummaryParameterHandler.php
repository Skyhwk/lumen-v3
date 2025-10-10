<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use DB;
use App\Models\OrderDetail;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SummaryParameterHandler extends BaseController
{
    public function index(Request $request) {
        // Begin database transaction
        // DB::beginTransaction();
        // try {
        //     // Retrieve active parameters
        //     $list_parameter = Parameter::where('is_active', 1)
        //         ->select(DB::raw('CONCAT(id, ";", nama_lab) as full_param'), 'id', 'nama_lab', 'nama_kategori')
        //         ->get();

        //     $list_order_detail = [];

        //     foreach (array_chunk($list_parameter->toArray(), 200) as $chunk_parameters) {
        //         foreach ($chunk_parameters as $parameter) {
        //             // Check order details for each parameter
        //             $orderDetails = OrderDetail::where('parameter', 'like', '%' . $parameter['full_param'] . '%')
        //                 ->where('is_active', 1)
        //                 ->select(DB::raw('COUNT(*) as jumlah_order'), DB::raw('YEAR(created_at) as year'))
        //                 ->groupBy(DB::raw('YEAR(created_at)'))
        //                 ->get();

        //             // If no order details found, add entry with jumlah_order 0
        //             if ($orderDetails->isEmpty()) {
        //                 $list_order_detail[] = [
        //                     'id_parameter' => $parameter['id'],
        //                     'nama_parameter' => $parameter['nama_lab'],
        //                     'jumlah_order' => 0,
        //                     'nama_kategori' => $parameter['nama_kategori'],
        //                     'year' => null,
        //                 ];
        //             } else {
        //                 foreach ($orderDetails as $orderDetail) {
        //                     $list_order_detail[] = [
        //                         'id_parameter' => $parameter['id'],
        //                         'nama_parameter' => $parameter['nama_lab'],
        //                         'jumlah_order' => $orderDetail->jumlah_order,
        //                         'nama_kategori' => $parameter['nama_kategori'],
        //                         'year' => $orderDetail->year,
        //                     ];
        //                 }
        //             }
        //         }
        //     }

        //     // Check if list_order_detail is not empty before inserting
        //     if (!empty($list_order_detail)) {
        //         DB::table('summary_parameter')->truncate();
        //         DB::table('summary_parameter')->insert($list_order_detail);
        //         DB::commit();
        //         return response()->json(['status' => 'success']);
        //     }else{
        //         return response()->json(['status' => 'Data Parameter Kosong'], 404);
        //     }
        // } catch (\Exception $e) {
        //     // Rollback the transaction on exception
        //     DB::rollBack();
        //     return response()->json([
        //         'message' => $e->getMessage(),
        //         'line' => $e->getLine(),
        //         'file' => $e->getFile(),
        //     ], 500);
        // }
        $list_parameter = Parameter::where('is_active', 1)
            ->select('id', 'nama_lab', 'nama_kategori')
            ->orderBy('nama_kategori', 'asc')
            ->get()
            ->map(function($param) {
                $param->full_param = $param->id . ';' . $param->nama_lab;
                return $param;
            });

        $list_order_detail = [];
        $chunk_size = 50; // Kurangi ukuran chunk untuk mengurangi beban memori
        $number_of_chunk = 1;
        foreach ($list_parameter->chunk($chunk_size) as $chunk_parameters) {
            $number_of_chunk++;
            Log::channel('summary_parameter')->info('Processing chunk parameters ke ' . $number_of_chunk);
            foreach ($chunk_parameters as $parameter) {
                // Query order detail per parameter (where like per parameter)
                $orderDetails = OrderDetail::where('is_active', 1)
                    ->where('parameter', 'like', '%' . $parameter->full_param . '%')
                    ->select(
                        DB::raw('COUNT(*) as jumlah_order'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('YEAR(created_at) as year')
                    )
                    ->groupBy(DB::raw('MONTH(created_at)'), DB::raw('YEAR(created_at)'))
                    ->orderBy('jumlah_order', 'desc')
                    ->get()
                    ->map(function($detail) {
                        $detail->tahun_bulan = $detail->year . '-' . str_pad($detail->month, 2, '0', STR_PAD_LEFT);
                        return $detail;
                    });

                if ($orderDetails->isNotEmpty()) {
                    foreach ($orderDetails as $detail) {
                        $list_order_detail[] = [
                            'id_parameter' => $parameter->id,
                            'nama_parameter' => $parameter->nama_lab,
                            'jumlah_order' => $detail->jumlah_order,
                            'nama_kategori' => $parameter->nama_kategori,
                            'tahun_bulan' => $detail->tahun_bulan,
                            'bulan' => Carbon::createFromDate(null, $detail->month, 1)->locale('id')->isoFormat('MMMM'),
                            'tahun' => $detail->year,
                            'created_at' => Carbon::now(),
                        ];
                    }
                }
            }
            Log::channel('summary_parameter')->info('Processing chunk parameters ke ' . $number_of_chunk .': ', $list_order_detail);
        }

        // Cek jika list_order_detail tidak kosong sebelum insert
        if (!empty($list_order_detail)) {
            DB::beginTransaction();
            try {
                DB::table('summary_parameter')->truncate();
                DB::table('summary_parameter')->insert($list_order_detail);
                Log::channel('summary_parameter')->info('SummaryParameter berhasil dijalankan');
                DB::commit();

                return response()->json(['status' => 'success'], 200);
            } catch (\Throwable $th) {
                DB::rollBack();
                Log::channel('summary_parameter')->error('SummaryParameter gagal dijalankan: ' . $th->getMessage());
                return response()->json(['status' => 'error', 'message' => $th->getMessage()], 500);
            }

        } else {
            Log::channel('summary_parameter')->warning('SummaryParameter dijalankan, namun tidak ada data yang diinsert.');
            return response()->json(['status' => 'Data Parameter Kosong'], 404);
        }
    }
}