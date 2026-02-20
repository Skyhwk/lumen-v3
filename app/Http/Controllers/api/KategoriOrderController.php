<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Carbon\Carbon;

use App\Models\{QuotationNonKontrak, QuotationKontrakH};

class KategoriOrderController extends Controller
{
    public function index(Request $request)
    {
        $quotations = collect([QuotationNonKontrak::class, QuotationKontrakH::class])
            ->flatMap(
                fn($model) => $model::where(['flag_status' => 'ordered', 'is_active' => true])
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->when($model === QuotationKontrakH::class, fn($q) => $q->with('detail'))
                    ->get()
            );

        $groupedQuotations = $quotations->flatMap(function ($item) {
            $month = Carbon::parse($item->tanggal_penawaran)->month;

            if ($item instanceof QuotationNonKontrak) {
                $dataPendukungSampling = json_decode($item->data_pendukung_sampling, true);

                return collect($dataPendukungSampling)
                    ->pluck('kategori_2')
                    ->unique()
                    ->map(fn($category) => ['category' => $category, 'month' => $month]);
            }

            return $item->detail->flatMap(function ($detail) {
                $dataPendukungSampling = (object) collect(json_decode($detail->data_pendukung_sampling, true))
                    ->where('periode_kontrak', $detail->periode_kontrak)
                    ->first();

                return collect(optional($dataPendukungSampling)->data_sampling)
                    ->pluck('kategori_2')
                    ->unique()
                    ->map(fn($category) => ['category' => $category, 'month' => Carbon::parse($detail->periode_kontrak)->month]);
            });
        })
            ->groupBy('category')
            ->map(fn($group) => $group->countBy('month'));

        $kategoriOrder = collect(config('kategori.id'))->map(
            fn($subCategories) => collect($subCategories)->map(fn($category) => [
                $category => collect(range(1, 12))->mapWithKeys(fn($month) => [
                    $month => optional($groupedQuotations->get($category))->get($month) ?? 0
                ])
            ])
        );

        return response()->json([
            'data' => $kategoriOrder,
            'message' => 'Kategori order retrieved successfully',
        ], 200);
    }
}
