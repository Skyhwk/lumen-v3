<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Illuminate\Http\Request;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\DataTables;

class CustomPromoDataTable extends CollectionDataTable
{
    public $filteredCollection;

    protected function filterRecords()
    {
        parent::filterRecords();
        $this->filteredCollection = clone $this->collection;
    }
}

class KeberhasilanPromoController extends Controller
{
    /**
     * filter_type: year | month | all
     * Rentang [start, end) agar index tanggal_penawaran bisa dipakai (hindari whereYear/whereMonth).
     */
    private function applyTanggalPenawaranFilter($query, Request $request)
    {
        $range = $this->resolveTanggalPenawaranRange($request);

        if ($range === null) {
            return $query;
        }

        return $query->where('tanggal_penawaran', '>=', $range[0])
            ->where('tanggal_penawaran', '<', $range[1]);
    }

    private function resolveTanggalPenawaranRange(Request $request)
    {
        $filterType = $request->input('filter_type', 'year');

        if ($filterType === 'all') {
            return null;
        }

        $year = (int) ($request->year ?? date('Y'));

        if ($filterType === 'month') {
            $month = (int) ($request->month ?? date('n'));
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-d', strtotime($start . ' +1 month'));

            return [$start, $end];
        }

        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-01-01', $year + 1);

        return [$start, $end];
    }

    private function aggregatePromoByKode($modelClass, Request $request)
    {
        $query = $modelClass::query()
            ->whereNotNull('kode_promo')
            ->where('kode_promo', '!=', '')
            ->where('is_active', 1);

        $this->applyTanggalPenawaranFilter($query, $request);

        return $query
            ->selectRaw('TRIM(kode_promo) as kode_promo')
            ->selectRaw('COUNT(*) as jumlah_qt')
            ->selectRaw("SUM(CASE WHEN flag_status = 'ordered' THEN 1 ELSE 0 END) as jumlah_order")
            ->selectRaw('COALESCE(SUM(biaya_akhir), 0) as nominal_qt')
            ->selectRaw("COALESCE(SUM(CASE WHEN flag_status = 'ordered' THEN biaya_akhir ELSE 0 END), 0) as nominal_order")
            ->groupByRaw('TRIM(kode_promo)')
            ->get();
    }

    private function mergePromoAggregates($rowsA, $rowsB)
    {
        $merged = [];

        foreach ([$rowsA, $rowsB] as $rows) {
            foreach ($rows as $row) {
                $kode = $row->kode_promo;
                if (!isset($merged[$kode])) {
                    $merged[$kode] = [
                        'kode_promo' => $kode,
                        'jumlah_qt' => 0,
                        'jumlah_order' => 0,
                        'nominal_qt' => 0.0,
                        'nominal_order' => 0.0,
                    ];
                }
                $merged[$kode]['jumlah_qt'] += (int) $row->jumlah_qt;
                $merged[$kode]['jumlah_order'] += (int) $row->jumlah_order;
                $merged[$kode]['nominal_qt'] += (float) $row->nominal_qt;
                $merged[$kode]['nominal_order'] += (float) $row->nominal_order;
            }
        }

        return collect($merged)->map(function ($item) {
            $qt = (int) $item['jumlah_qt'];
            $order = (int) $item['jumlah_order'];
            $item['presentase'] = $qt > 0 ? ($order / $qt) * 100 : 0;

            return $item;
        })->values();
    }

    public function index(Request $request)
    {
        $nonKontrakStats = $this->aggregatePromoByKode(QuotationNonKontrak::class, $request);
        $kontrakStats = $this->aggregatePromoByKode(QuotationKontrakH::class, $request);

        $data = $this->mergePromoAggregates($nonKontrakStats, $kontrakStats);

        $dt = new CustomPromoDataTable($data);
        $json = $dt->make(true);
        $response = $json->getData();

        $filtered = $dt->filteredCollection ?? $data;
        $response->total_qt = (int) $filtered->sum('jumlah_qt');
        $response->total_order = (int) $filtered->sum('jumlah_order');
        $response->total_nominal_qt = (float) $filtered->sum('nominal_qt');
        $response->total_nominal_order = (float) $filtered->sum('nominal_order');

        return response()->json($response);
    }

    public function detail(Request $request)
    {
        $kode_promo = $request->kode_promo;
        
        if (!$kode_promo || $kode_promo === '') {
            return response()->json([
                'error' => true,
                'message' => 'Kode promo tidak valid'
            ], 404);
        }

        $status = $request->status; // 'ordered' or 'not_ordered'

        $nonKontrak = QuotationNonKontrak::where('kode_promo', $kode_promo)
            ->where('is_active', 1)
            ->tap(function ($query) use ($request) {
                $this->applyTanggalPenawaranFilter($query, $request);
            })
            ->get()
            ->each(function ($item) {
                $item->tipe_quotation = 'Non Kontrak';
                if (empty($item->no_document) && !empty($item->no_quotation)) {
                    $item->no_document = $item->no_quotation;
                }
            });

        $kontrak = QuotationKontrakH::where('kode_promo', $kode_promo)
            ->where('is_active', 1)
            ->tap(function ($query) use ($request) {
                $this->applyTanggalPenawaranFilter($query, $request);
            })
            ->get()
            ->each(function ($item) {
                $item->tipe_quotation = 'Kontrak';
                if (empty($item->no_document) && !empty($item->no_quotation)) {
                    $item->no_document = $item->no_quotation;
                }
            });

        $data = $nonKontrak->concat($kontrak);

        if ($status === 'ordered') {
            $data = $data->where('flag_status', 'ordered')->values();
        } elseif ($status === 'not_ordered') {
            $data = $data->where('flag_status', '!=', 'ordered')->values();
        }

        $dt = new CustomPromoDataTable($data);
        $json = $dt->make(true);
        $response = $json->getData();

        $filtered = $dt->filteredCollection ?? $data;
        $response->total_nominal = (float) $filtered->sum('biaya_akhir');
        $response->total_count = $filtered->count();

        return response()->json($response);
    }
    
}
