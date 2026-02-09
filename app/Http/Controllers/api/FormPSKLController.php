<?php

namespace App\Http\Controllers\api;

use App\Models\FormPSKL;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterPelanggan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Services\GetBawahan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FormPSKLController extends Controller
{
    /**
     * Helper untuk memfilter query berdasarkan jabatan
     */
    private function applyJabatanFilter($query, $request)
    {
        $user = $request->attributes->get('user');
        $jabatan = $user->karyawan->id_jabatan;
        $userId = $user->id; 

        if (in_array($jabatan, [24, 86, 148])) {
            return $query->where($query->getModel()->getTable() . '.sales_id', $userId);
        } 
        
        if (in_array($jabatan, [21, 15, 154, 157])) {
            $bawahan = GetBawahan::where('id', $userId)->pluck('id')->toArray();
            $bawahan[] = $userId;
            return $query->whereIn($query->getModel()->getTable() . '.sales_id', $bawahan);
        }

        return $query;
    }

    public function index(Request $request) 
    {
        $query = FormPSKL::where('is_active', 1)
            ->when($request->status == 'atas', 
                fn($q) => $q->whereIn('status', ['WAITING PROCESS', 'PROCESSED', "REJECTED"]),
                fn($q) => $q->where('status', 'DONE')
            );
        // Panggil helper di sini
        $data = $this->applyJabatanFilter($query, $request)->get();

        return Datatables::of($data)->make(true);
    }

    public function getPelanggan(Request $request)
    {
        $term = trim($request->term);
        if (!$term || strlen($term) < 3) return response()->json(['data' => []]);

        $query = MasterPelanggan::where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('id_pelanggan', 'like', "%{$term}%")
                ->orWhere('nama_pelanggan', 'like', "%{$term}%"); // Tambahkan orWhere agar pencarian lebih user-friendly
            });

        $data = $this->applyJabatanFilter($query, $request)
            ->select([
                'id_pelanggan',
                'nama_pelanggan',
                'wilayah',
                'sales_penanggung_jawab'
            ])
            ->get();


        return response()->json(['data' => $data]);
    }

    // Endpoint baru untuk get No Order berdasarkan ID Pelanggan
    public function getNoOrderByPelanggan(Request $request)
    {
        $idPelanggan = $request->id_pelanggan;
        $term = trim($request->term);
        
        if (!$idPelanggan) {
            return response()->json(['data' => []]);
        }

        if (!$term || strlen($term) < 3) {
            return response()->json(['data' => []]);
        }

        // Query dengan join untuk memastikan hanya ambil order yang punya detail aktif
        $data = OrderHeader::where('order_header.id_pelanggan', $idPelanggan)
            ->where('order_header.no_order', 'like', "%{$term}%")
            ->where('order_header.is_active', true)
            ->join('order_detail', function($join) {
                $join->on('order_header.id', '=', 'order_detail.id_order_header')
                    ->where('order_detail.is_active', true);
            })
            ->select([
                'order_header.id',
                'order_header.no_order',
                'order_header.id_pelanggan',
            ])
            ->distinct() // Penting untuk menghindari duplikat jika ada multiple details
            ->get();

        return response()->json(['data' => $data]);
    }

    // Endpoint untuk get detail Order berdasarkan id_order_header
    public function getOrderDetail(Request $request)
    {
        $idOrderHeader = $request->id_order_header;
        $noOrder = $request->no_order;
        
        if (!$idOrderHeader) {
            return response()->json(['message' => 'ID Order Header required'], 400);
        }

        // Query dengan selectRaw untuk ambil min tanggal dan GROUP_CONCAT periode
        $query = OrderDetail::where('id_order_header', $idOrderHeader)
            ->where('is_active', true);
        
        if ($noOrder) {
            $query->where('no_order', $noOrder);
        }

        // Aggregate query - ambil min tanggal_sampling dan semua periode unique
        $aggregateData = $query->selectRaw('
                GROUP_CONCAT(DISTINCT 
                    CASE 
                        WHEN periode IS NOT NULL AND TRIM(periode) != "" 
                        THEN periode 
                    END 
                    ORDER BY periode ASC 
                    SEPARATOR ", "
                ) as periode_string,
                no_order
            ')
            ->groupBy('no_order')
            ->first();

        if (!$aggregateData) {
            return response()->json(['message' => 'No Order Tidak Aktif'], 404);
        }

        // Split periode string menjadi array
        $periodeList = [];
        if ($aggregateData->periode_string) {
            $periodeList = array_filter(
                array_map('trim', explode(', ', $aggregateData->periode_string)),
                function($val) {
                    return $val !== '';
                }
            );
            $periodeList = array_values($periodeList); // Reset index
        }

        // Ambil data dari OrderHeader
        $orderHeader = OrderHeader::where('id', $idOrderHeader)->first();

        return response()->json([
            'data' => [
                'id_order_header' => $idOrderHeader,
                'no_order' => $aggregateData->no_order,
                'tanggal_sampling' => $aggregateData->tanggal_sampling_min, // Tanggal terkecil
                'periode_list' => $periodeList, // Array untuk select option
                'has_periode' => !empty($periodeList),
                'no_invoice' => $orderHeader->invoice->no_invoice ?? null,
                'no_quotation' => $orderHeader->no_quotation ?? null,
            ]
        ]);
    }

    public function getTanggalSamplingByPeriode(Request $request)
    {
        $noOrder = $request->no_order;
        $periode = $request->periode; // bisa null

        if (!$noOrder) {
            return response()->json([
                'message' => 'No Order wajib diisi'
            ], 400);
        }

        $query = OrderDetail::where('no_order', $noOrder)
            ->where('is_active', true);

        if ($periode !== null && $periode !== '') {
            $query->where('periode', $periode);
        } else {
            $query->whereNull('periode');
        }

        // LANGSUNG ambil nilai MIN
        $tanggalSampling = $query->min('tanggal_sampling');

        return response()->json([
            'data' => [
                'tanggal_sampling' => $tanggalSampling
            ]
        ]);
    }

    public function store (Request $request)
    {
        DB::beginTransaction();
        try {
            if($request->filled('id')){
                $data = FormPSKL::where('id', $request->id)->first();
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }else{
                $data = new FormPSKL();
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->batch_id = str_replace('.', '', microtime(true));
            }

            $data->id_pelanggan = $request->id_pelanggan;
            $data->nama_pelanggan = $request->nama_pelanggan;
            $data->sales_penanggung_jawab = $request->sales_penanggung_jawab;
            $data->tanggal_sampling = $request->tanggal_sampling;
            $data->no_order = $request->no_order;
            $data->wilayah = $request->wilayah;
            $data->periode = $request->periode;
            $data->kategori_sk = $request->kategori_sk;
            $data->jumlah_sk = $request->jumlah_sk;
            $data->is_evaluasi_titik = $request->is_evaluasi_titik;
            $data->is_survey_ulang = $request->is_survey_ulang;
            $data->catatan = $request->catatan;
            $data->status = $request->status;
            $data->sales_id = $this->user_id;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 400);
        }
        
    }

    public function delete(Request $request) {
        $data = FormPSKL::where('id', $request->id)->first();
        $data->deleted_by = $this->karyawan;
        $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->is_active = false;
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus'], 200);
    }

}