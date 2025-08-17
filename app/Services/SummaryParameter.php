<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;
use App\Models\Parameter;
use Carbon\Carbon;

class SummaryParameter
{
    public static function run()
    {
        // Ambil parameter yang aktif dengan select kolom yang dibutuhkan saja
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
            } catch (\Throwable $th) {
                DB::rollBack();
                Log::channel('summary_parameter')->error('SummaryParameter gagal dijalankan: ' . $th->getMessage());
            }

        } else {
            Log::channel('summary_parameter')->warning('SummaryParameter dijalankan, namun tidak ada data yang diinsert.');
        }
    }
}
