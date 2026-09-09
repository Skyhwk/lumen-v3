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
    public function index(Request $request)
    {
        $year = $request->year ?? date('Y');

        $nonKontrak = QuotationNonKontrak::whereNotNull('kode_promo')
            ->where('kode_promo', '!=', '')
            ->where('is_active', 1)
            ->when($year, function ($query) use ($year) {
                $query->whereYear('tanggal_penawaran', $year);
            })
            ->get()
            ->each(function ($item) {
                $item->tipe_quotation = 'non_kontrak';
            });

        $kontrak = QuotationKontrakH::whereNotNull('kode_promo')
            ->where('kode_promo', '!=', '')
            ->where('is_active', 1)
            ->when($year, function ($query) use ($year) {
                $query->whereYear('tanggal_penawaran', $year);
            })
            ->get()
            ->each(function ($item) {
                $item->tipe_quotation = 'kontrak';
            });

        $grouped = $nonKontrak->concat($kontrak)->groupBy(function ($item) {
            return trim($item->kode_promo);
        });

        $data = $grouped->map(function ($items, $kodePromo) {
            return [
                'kode_promo'        => $kodePromo,
                'jumlah_qt'   => $items->count(),
                'jumlah_order'     => $items->where('flag_status', 'ordered')->count(),
                'nominal_qt'     => (float) $items->sum('biaya_akhir'),
                'nominal_order'     => (float) $items->where('flag_status', 'ordered')->sum('biaya_akhir'),
                'presentase'     => $items->where('flag_status', 'ordered')->count() / $items->count() * 100,
            ];
        })->values();

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

        $year = $request->year;
        $status = $request->status; // 'ordered' or 'not_ordered'

        $nonKontrak = QuotationNonKontrak::where('kode_promo', $kode_promo)
            ->where('is_active', 1)
            ->when($year, function ($query) use ($year) {
                $query->whereYear('tanggal_penawaran', $year);
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
            ->when($year, function ($query) use ($year) {
                $query->whereYear('tanggal_penawaran', $year);
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
