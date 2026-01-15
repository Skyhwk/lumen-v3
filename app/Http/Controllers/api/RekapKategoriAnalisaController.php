<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\{MasterKaryawan, QuotationKontrakH, QuotationNonKontrak};

class RekapKategoriAnalisaController extends Controller
{
    public function getSales()
    {
        $sales = MasterKaryawan::where('is_active', true)
            ->whereIn('id_jabatan', [24, 148])
            ->orWhere('nama_lengkap', 'Novva Novita Ayu Putri Rukmana')
            ->get();

        return response()->json($sales);
    }

    public function getDetailSales(Request $request)
    {
        [$year, $month] = explode('-', $request->filter);

        $samplingDateFilter = fn($q) => $q->whereYear('tanggal_sampling', $year)->whereMonth('tanggal_sampling', $month);

        $quotations = collect([QuotationKontrakH::class, QuotationNonKontrak::class])
            ->flatMap(fn($model) => $model::with(['orderHeader.orderDetail' => $samplingDateFilter])
                ->where(['sales_id' => $request->sales_id, 'is_active' => true])
                ->whereHas('orderHeader', fn($q1) => $q1->whereHas('orderDetail', fn($q2) => $samplingDateFilter($q2)))
                ->get());
        $masterKategori = config('kategori.kategori.id');
        $result = collect($masterKategori)->mapWithKeys(fn($childs, $parent) => [
            $parent => collect($childs)->mapWithKeys(fn($child) => [
                $child => $quotations->filter(fn($q) => $q->orderHeader->orderDetail->contains('kategori_3', $child))->count()
            ])
        ]);

        return response()->json($result);
    }
}
