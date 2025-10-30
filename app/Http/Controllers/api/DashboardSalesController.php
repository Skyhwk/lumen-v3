<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

Carbon::setLocale('id');

use Illuminate\Http\Request;
use App\Services\getBawahan;

use App\Models\TargetSales;
use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

class DashboardSalesController extends Controller
{
    public function index(Request $request)
    {
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        if ($request->rangeFilter == 'Weekly') {
            $startDate = Carbon::now()->startOfWeek()->startOfDay();
            $endDate = Carbon::now()->endOfWeek()->endOfDay();
        }

        if ($request->rangeFilter == 'Monthly') {
            $startDate = Carbon::now()->startOfMonth()->startOfDay();
            $endDate = Carbon::now()->endOfMonth()->endOfDay();
        }

        $query = collect([QuotationKontrakH::class, QuotationNonKontrak::class])
            ->flatMap(function ($model) use ($request, $startDate, $endDate) {
                $subQuery = $model::where('is_active', true)->whereBetween('tanggal_penawaran', [$startDate, $endDate]);

                $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
                if ($jabatan == 24) {
                    $subQuery->where('sales_id', $this->user_id);
                } else if ($jabatan == 21) {
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                    array_push($bawahan, $this->user_id);

                    $subQuery->whereIn('sales_id', $bawahan);
                }

                return $subQuery->get();
            });

        return response()->json([
            'quoted' => $query->count(),
            'ordered' => $query->where('flag_status', 'ordered')->count(),
            'unordered' => $query->where('flag_status', '!=', 'ordered')->count(),
            'rejected' => $query->whereIn('flag_status', ['rejected', 'void'])->count(),
        ], 200);
    }

    public function targetSales(Request $request)
    {
        $now = Carbon::now();

        $rangeFilter = $request->rangeFilter;

        $startDate = $rangeFilter == 'Weekly' ? $now->copy()->startOfWeek() : ($rangeFilter == 'Monthly' ? $now->copy()->startOfMonth() : $now->copy())->startOfDay();
        $endDate = $rangeFilter == 'Weekly' ? $now->copy()->endOfWeek() : ($rangeFilter == 'Monthly' ? $now->copy()->endOfMonth() : $now->copy())->endOfDay();

        // Tentukan sales IDs
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        $salesIds = $jabatan == 24
            ? [$this->user_id]
            : ($jabatan == 21
                ? array_merge(MasterKaryawan::whereJsonContains('atasan_langsung', (string)$this->user_id)->where('is_active', true)->pluck('id')->toArray(), [$this->user_id])
                : MasterKaryawan::where('is_active', true)->where(fn($q) => $q->whereIn('id_jabatan', [24, 21])->orWhere('id', 41))->pluck('id')->toArray()
            );

        // Get data
        $sales = MasterKaryawan::whereIn('id', $salesIds)->where('is_active', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);
        $targets = TargetSales::whereIn('user_id', $salesIds)->where('is_active', true)->where('year', $now->format('Y'))->get()->keyBy('user_id');

        $kontrakTotals = QuotationKontrakH::whereIn('sales_id', $salesIds)->where('is_active', true)->whereBetween('tanggal_penawaran', [$startDate, $endDate])->selectRaw('sales_id, SUM(grand_total) as total')->groupBy('sales_id')->get()->keyBy('sales_id');
        $nonKontrakTotals = QuotationNonKontrak::whereIn('sales_id', $salesIds)->where('is_active', true)->whereBetween('tanggal_penawaran', [$startDate, $endDate])->selectRaw('sales_id, SUM(grand_total) as total')->groupBy('sales_id')->get()->keyBy('sales_id');

        $currentMonth = strtolower($now->format('M'));

        // Build response
        $result = [];
        foreach ($sales as $s) {
            $result[] = [
                'name' => $s->nama_lengkap,
                'target' => isset($targets[$s->id]) && isset($targets[$s->id]->$currentMonth) ? $targets[$s->id]->$currentMonth : 0,
                'actual' => (isset($kontrakTotals[$s->id]) ? $kontrakTotals[$s->id]->total : 0) + (isset($nonKontrakTotals[$s->id]) ? $nonKontrakTotals[$s->id]->total : 0)
            ];
        }

        return response()->json($result);
    }

    public function getSales(Request $request)
    {
        // $user_id = $this->user_id;
        $user_id = 890;
        $bawahan = GetBawahan::where('id', $user_id)->get()->pluck('id')->toArray();
        $bawahan = array_values(array_unique($bawahan));
        
        $salesList = MasterKaryawan::where('is_active', true)
            ->where('jabatan', 'like', '%Manager%')
            ->where('id', '!=', $user_id)
            ->whereIn('id', $bawahan)
            ->where('is_active', true)
            ->orderBy('jabatan', 'asc')
            ->select('id', 'nama_lengkap', 'jabatan')
            ->get();
        
        if($salesList){
            foreach ($salesList as $sales) {
                $bawahans = MasterKaryawan::whereIn('id', GetBawahan::where('id', $sales->id)->get()->pluck('id')->toArray())
                    ->where('is_active', true)
                    ->where('id', '!=', $sales->id)
                    ->whereIn('id_jabatan', [21, 24, 148]) // spv, sales, executive
                    ->select('id', 'nama_lengkap', 'jabatan')
                    ->orderBy('jabatan', 'asc')
                    ->get()
                    ->toArray();
                $sales->bawahan = $bawahans;
            }
            $sales = $salesList;
        } else {
            $sales = MasterKaryawan::whereIn('id', GetBawahan::where('id', $user_id)->get()->pluck('id')->toArray())
                ->where('is_active', true)
                ->whereIn('id_jabatan', [21, 24, 148]) // spv, sales, executive
                ->select('id', 'nama_lengkap', 'jabatan')
                ->orderBy('jabatan', 'asc')
                ->get()
                ->toArray();
        }


        return response()->json([
            'sales' => $sales,
            'message' => 'Sales data retrieved successfully',
        ], 200);
    }
}
