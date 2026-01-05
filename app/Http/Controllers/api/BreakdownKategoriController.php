<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

class BreakdownKategoriController extends Controller
{
    private $categoryStr = [
        'AIR LIMBAH' => [
            'Air Limbah Domestik',
            'Air Limbah Industri',
            'Air Limbah',
        ],
        'AIR BERSIH' => [
            'Air Bersih',
        ],
        'AIR MINUM' => [
            'Air Minum',
        ],
        'AIR SUNGAI' => [
            'Air Sungai',
        ],
        'AIR LAUT' => [
            'Air Laut',
        ],
        'AIR LAINNYA' => [
            'Air Tanah',
            'Air Higiene Sanitasi',
            'Air Khusus',
            'Air Kolam Renang',
            'Air Danau',
            'Air Permukaan',
            'Air Reverse Osmosis',
            'Air Higiene Sanitasi',
            'Air Lindi',
        ],
        'UDARA AMBIENT' => [
            'Udara Ambient',
        ],
        'UDARA LINGKUNGAN KERJA' => [
            'Udara Lingkungan Kerja',
        ],
        'KEBISINGAN' => [
            'Kebisingan',
            'Kebisingan (Indoor)',
            'Kebisingan (24 Jam)',
        ],
        'PENCAHAYAAN' => [
            'Pencahayaan',
        ],
        'GETARAN' => [
            'Getaran',
            'Getaran (Mesin)',
            'Getaran (Seluruh Tubuh)',
            'Getaran (Lengan & Tangan)',
            'Getaran (Kejut Bangunan)',
            'Getaran (Bangunan)',
            'Getaran (Lingkungan)',
        ],
        'IKLIM KERJA' => [
            'Iklim Kerja',
        ],
        'UDARA LAINNYA' => [
            'Ergonomi',
            'Udara Angka Kuman',
            'Kebauan',
            'Udara Swab Test',
            'Udara Umum',
            'Psikologi',
            'Kualitas Udara Dalam Ruang',
        ],
        'EMISI SUMBER BERGERAK' => [
            'Emisi Kendaraan',
            'Emisi Kendaraan (Solar)',
            'Emisi Kendaraan (Bensin)',
            'Emisi Kendaraan (Gas)',
        ],
        'EMISI SUMBER TIDAK BERGERAK' => [
            'Emisi Sumber Tidak Bergerak',
        ],
        'EMISI ISOKINETIK' => [
            'Emisi Isokinetik',
        ],
    ];

    public function getQuotations(Request $request)
    {
        $search = $request->term ?? '';
        $page = max(1, (int) ($request->page ?? 1));
        $perPage = 10; // default page size

        // Only search if minimum input length is met
        if (mb_strlen($search) < 3) {
            return response()->json([
                'results' => [],
                'pagination' => [
                    'more' => false,
                ],
            ]);
        }
        
        $query1 = QuotationNonKontrak::query()
            ->where('is_active', 1)
            ->where('no_document', 'LIKE', '%' . $search . '%');
        $query2 = QuotationKontrakH::query()
            ->where('is_active', 1)
            ->where('no_document', 'LIKE', '%' . $search . '%');
        
        // Get both count and data for pagination
        $totalCount1 = (clone $query1)->count();
        $totalCount2 = (clone $query2)->count();
        $totalCount = $totalCount1 + $totalCount2;

        $offset = ($page - 1) * $perPage;

        // For pagination with two models, simplest way is to merge and sort in PHP
        // But for large datasets, should use a union with manual pagination (not shown here for brevity)

        $data1 = $query1->select('no_document', 'id', DB::raw("'non_kontrak' as jenis"))->get()->toArray();
        $data2 = $query2->select('no_document', 'id', DB::raw("'kontrak' as jenis"))->get()->toArray();

        $allData = array_merge($data1, $data2);

        // Sort descending by id by default (or by no_document? adjust if needed)
        usort($allData, function ($a, $b) {
            return strcmp($b['no_document'], $a['no_document']);
        });

        $pagedData = array_slice($allData, $offset, $perPage);

        // Format for select2 (id/text fields)
        $results = array_map(function ($item) {
            return [
                'id' => $item['no_document'], // value returned by select2
                'text' => $item['no_document'] . ' (' . $item['jenis'] . ')', // label shown to user
                'jenis' => $item['jenis'],
            ];
        }, $pagedData);

        // Determine if more data exists for pagination (Select2 expects 'pagination'->'more' key)
        $hasMore = $offset + $perPage < $totalCount;

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $hasMore,
            ],
        ]);
    }

    public function getDetailQuotation(Request $request){
        $type = \explode('/', $request->no_qt)[1];
        if($type == 'QTC'){
            $data = QuotationKontrakH::with('sales')->where('no_document', $request->no_qt)->first();
            $dataSampling = json_decode($data->data_pendukung_sampling);

            $array = [];

            foreach ($dataSampling as $item) {
                $kategori = explode('-', $item->kategori_2)[1];
                $jumlahTitik = $item->jumlah_titik * count($item->periode);

                if (isset($array[$kategori])) {
                    $array[$kategori] += $jumlahTitik;
                } else {
                    $array[$kategori] = $jumlahTitik;
                }
            }

            $buildData = $this->buildCategoryTree($array, $this->categoryStr);

        }else{
            $data = QuotationNonKontrak::with('sales')->where('no_document', $request->no_qt)->first();
            $dataSampling = json_decode($data->data_pendukung_sampling);

            $array = [];

            foreach ($dataSampling as $item) {
                $kategori = explode('-', $item->kategori_2)[1];
                $jumlahTitik = $item->jumlah_titik;

                if (isset($array[$kategori])) {
                    $array[$kategori] += $jumlahTitik;
                } else {
                    $array[$kategori] = $jumlahTitik;
                }
            }

            $buildData = $this->buildCategoryTree($array, $this->categoryStr);
        }

        return response()->json([
            'message' => 'Data hasbeen retrieved successfully',
            'customer_detail' => $data,
            'breakdown_kategori' => $buildData,
            'status' => 200,
        ]);
    }

    private function buildCategoryTree(array $rawData, array $categoryStr): array
    {
        $result = [];
    
        foreach ($categoryStr as $parent => $childrenList) {
            $matchedChildren = [];
    
            foreach ($childrenList as $childName) {
                if (isset($rawData[$childName])) {
                    $matchedChildren[] = [
                        'label' => $childName,
                        'value' => $rawData[$childName],
                    ];
                }
            }
    
            if (empty($matchedChildren)) {
                continue;
            }
    
            // RULE: parent == child → no children
            if (
                count($matchedChildren) === 1 &&
                strtoupper($matchedChildren[0]['label']) === strtoupper($parent)
            ) {
                $result[] = [
                    'parent' => $parent,
                    'value'  => $matchedChildren[0]['value'],
                ];
            } else {
                $result[] = [
                    'parent'   => $parent,
                    'children' => $matchedChildren,
                ];
            }
        }
    
        return $result;
    }
    
}
