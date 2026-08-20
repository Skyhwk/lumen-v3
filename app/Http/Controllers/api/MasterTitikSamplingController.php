<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;

use App\Services\GetBawahan;

use App\Models\MasterKaryawan;
use App\Models\MasterTargetSales;
use App\Models\MasterKuotaTarget;
use App\Models\OrderDetail;
use App\Models\OrderHeader;

class MasterTitikSamplingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);

            $query = OrderHeader::with(['sales' => function ($q) {
                    $q->select('id', 'nama_lengkap', 'email', 'nik_karyawan');
                }])
                ->select(
                    DB::raw('MAX(id) as id'),
                    DB::raw('MAX(sales_id) as sales_id'),
                    'id_pelanggan',
                    'nama_perusahaan'
                )
                ->where('is_active', true);

            // General Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id_pelanggan', 'LIKE', "%{$search}%")
                      ->orWhere('nama_perusahaan', 'LIKE', "%{$search}%");
                });
            }

            // Per-column filters
            if ($request->filled('id_pelanggan')) {
                $query->where('id_pelanggan', 'LIKE', "%{$request->id_pelanggan}%");
            }
            if ($request->filled('nama_perusahaan')) {
                $query->where('nama_perusahaan', 'LIKE', "%{$request->nama_perusahaan}%");
            }
            if ($request->filled('nama_sales')) {
                $salesSearch = $request->nama_sales;
                $query->whereHas('sales', function ($q) use ($salesSearch) {
                    $q->where('nama_lengkap', 'LIKE', "%{$salesSearch}%");
                });
            }

            // filter hak akses mirip di requestquotationcontroller
            if ($request->attributes->has('user') && isset($request->attributes->get('user')->karyawan)) {
                $userKaryawan = $request->attributes->get('user')->karyawan;
                $jabatan = $userKaryawan->id_jabatan;

                switch ($jabatan) {
                    case 24: // Sales Staff
                        $query->where('sales_id', $this->user_id);
                        break;
                    case 21: // Sales Supervisor
                        $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                            ->pluck('id')
                            ->toArray();
                        array_push($bawahan, $this->user_id);
                        $query->whereIn('sales_id', $bawahan);
                        break;
                }
            }

            $data = $query->groupBy('id_pelanggan', 'nama_perusahaan')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    // public function detail(Request $request){
    //     $header_id = $request->header_id;

    //     $data = OrderHeader::where('id', $header_id)
            
    // }


    public function view(Request $request)
    {
        try {
            $data = OrderDetail::query()
                ->where('is_active', true)
                ->where('id_order_header', $request->id_order_header)
                ->select([
                    'id',
                    'id_order_header',
                    'kategori_3',
                    'keterangan_1',
                    'is_active'
                ])
                ->get()
                ->groupBy('kategori_3')
                ->map(function ($items) {
                    return $items->pluck('keterangan_1')
                        ->map(function ($val) {
                            return trim($val);
                        })
                        ->filter()
                        ->unique()
                        ->values();
                });

            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}