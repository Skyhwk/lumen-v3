<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\{MasterKaryawan, QuotationKontrakH, QuotationNonKontrak};

class RekapKategoriAnalisaController extends Controller
{
    private $categoryStr = [
        'AIR LIMBAH' => [
            '2-Air Limbah Domestik',
            '3-Air Limbah Industri',
            '51-Air Limbah',
        ],
        'AIR BERSIH' => [
            '1-Air Bersih',
            '63-Air Higiene Sanitasi',
            '62-Air Higiene Sanitasi',
        ],
        'AIR MINUM' => [
            '4-Air Minum',
        ],
        'AIR SUNGAI' => [
            '54-Air Sungai',
        ],
        'AIR LAUT' => [
            '5-Air Laut',
        ],
        'AIR LAINNYA' => [
            '72-Air Tanah',
            '64-Air Khusus',
            '40-Air Kolam Renang',
            '56-Air Danau',
            '6-Air Permukaan',
            '117-Air Reverse Osmosis',
            '112-Air Lindi',
        ],
        'UDARA AMBIENT' => [
            '11-Udara Ambient',
        ],
        'UDARA LINGKUNGAN KERJA' => [
            '27-Udara Lingkungan Kerja',
        ],
        'KEBISINGAN' => [
            '23-Kebisingan',
            '25-Kebisingan (Indoor)',
            '24-Kebisingan (24 Jam)',
        ],
        'PENCAHAYAAN' => [
            '28-Pencahayaan',
        ],
        'GETARAN' => [
            '13-Getaran',
            '19-Getaran (Mesin)',
            '20-Getaran (Seluruh Tubuh)',
            '17-Getaran (Lengan & Tangan)',
            '15-Getaran (Kejut Bangunan)',
            '14-Getaran (Bangunan)',
            '18-Getaran (Lingkungan)',
        ],
        'IKLIM KERJA' => [
            '21-Iklim Kerja',
        ],
        'UDARA LAINNYA' => [
            '53-Ergonomi',
            '12-Udara Angka Kuman',
            '22-Kebauan',
            '46-Udara Swab Test',
            '29-Udara Umum',
            '118-Psikologi',
            '26-Kualitas Udara Dalam Ruang',
        ],
        'EMISI SUMBER BERGERAK' => [
            '30-Emisi Kendaraan',
            '32-Emisi Kendaraan (Solar)',
            '31-Emisi Kendaraan (Bensin)',
            '116-Emisi Kendaraan (Gas)',
        ],
        'EMISI SUMBER TIDAK BERGERAK' => [
            '34-Emisi Sumber Tidak Bergerak',
        ],
        'EMISI ISOKINETIK' => [
            '119-Emisi Isokinetik',
        ],
    ];

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

        $result = collect($this->categoryStr)->mapWithKeys(fn($childs, $parent) => [
            $parent => collect($childs)->mapWithKeys(fn($child) => [
                $child => $quotations->filter(fn($q) => $q->orderHeader->orderDetail->contains('kategori_3', $child))->count()
            ])
        ]);

        return response()->json($result);
    }
}
