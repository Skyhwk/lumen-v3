<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganEmisiCerobong;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Models\OrderDetail;


class TqcEmisiSumberTidakBergerakController extends Controller
{
    public function index()
    {
        $data = OrderDetail::select(
            'cfr',
            DB::raw('MAX(id) as max_id'),
            'nama_perusahaan',
            'no_quotation',
            'no_order',
            DB::raw('GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ", ") as no_sampel'),
            DB::raw('GROUP_CONCAT(DISTINCT kategori_3 SEPARATOR ", ") as kategori_3'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ", ") as tanggal_sampling'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ", ") as tanggal_terima'),
            'kategori_1',
            'konsultan',
            'parameter',
        )
            ->where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '5-Emisi')
            ->where('kategori_3',  '34-Emisi Sumber Tidak Bergerak')
            ->groupBy('cfr', 'nama_perusahaan', 'no_quotation', 'no_order', 'kategori_1', 'konsultan', 'parameter')
            ->orderBy('max_id', 'desc');

        return DataTables::of($data)
            ->filter(function ($query) {
                foreach (request('columns', []) as $col) {
                    $name = $col['data'] ?? null;
                    $search = $col['search']['value'] ?? null;

                    if ($search && in_array($name, [
                        'no_sampel',
                        'kategori_3',
                        'tanggal_sampling',
                        'tanggal_terima',
                    ])) {
                        $query->whereRaw("EXISTS (
                            SELECT 1 FROM order_detail od
                            WHERE od.cfr = order_detail.cfr
                            AND od.{$name} LIKE ?
                        )", ["%{$search}%"]);
                    }
                }
            })
            ->make(true);
    }

  public function handleApproveSelected(Request $request)
    {
        DB::beginTransaction();
        try {
            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 2,
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil diapprove.',
                'success' => true,
                'status' => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
                'success' => false,
                'status' => 500,
            ], 500);
        }
    }
    public function getTrend(Request $request)
    { 
        $orderDetails = OrderDetail::where('cfr', $request->cfr)
            ->where('status', 1)
            ->where('is_active', 1)
            ->get();

            
            $data = [];
            foreach ($orderDetails as $orderDetail) {
                $dataLapanganEmisiCerobong = DataLapanganEmisiCerobong::where('no_sampel', $orderDetail->no_sampel)->get();
            $mapHasil = fn($col) => $col->values()
                ->map(fn($hasil_uji) => json_encode([
                    'co2' => $hasil_uji->co2,
                    'co'  => $hasil_uji->co,
                    'hc'  => $hasil_uji->hc,
                    'o2'  => $hasil_uji->o2,
                ]))
                ->toArray();

            $currentDataLapangan = $dataLapanganEmisiCerobong->where('no_sampel', $orderDetail->no_sampel);
            $hasil   = $mapHasil($currentDataLapangan);
            $history = $mapHasil($dataLapanganEmisiCerobong->where('no_sampel', '!=', $orderDetail->no_sampel));

            $currentDataLapangan = $currentDataLapangan->first();

            $data[] = [
                'no_sampel' => $orderDetail->no_sampel,
                'titik' => $orderDetail->keterangan_1,
                'history' => $history,
                'hasil' => $hasil,
                'sampler' => $currentDataLapangan->created_by,
                'approved_by' => $currentDataLapangan->approved_by,


                'id' => $currentDataLapangan->id,
                'nama_perusahaan' => $orderDetail->nama_perusahaan,
                'no_order' => $orderDetail->no_order,
                'kategori_3' => $orderDetail->kategori_3,
                'data_lapangan_emisi_kendaraan' => $currentDataLapangan
            ];
        }

        return response()->json([
            'data' => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }
}
