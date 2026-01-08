<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterPelanggan;

class BreakdownKategoriController extends Controller
{
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

    public function getIdPelanggan(Request $request)
    {
        $search = $request->term ?? '';
        $page = max(1, (int)($request->page ?? 1));
        $perPage = 10; // default page size

        // Hanya pencarian jika minimal 3 karakter
        if (mb_strlen($search) < 3) {
            return response()->json([
                'results' => [],
                'pagination' => [
                    'more' => false,
                ],
            ]);
        }

        // Query unik berdasarkan id_pelanggan dan ada order aktif
        $query = MasterPelanggan::query()
            ->select('master_pelanggan.id_pelanggan', 'master_pelanggan.nama_pelanggan')
            ->join('order_header', 'order_header.id_pelanggan', '=', 'master_pelanggan.id_pelanggan')
            ->where('order_header.is_active', 1)
            ->where('master_pelanggan.is_active', 1)
            ->where('master_pelanggan.id_pelanggan', 'like', '%' . $search . '%')
            ->groupBy('master_pelanggan.id_pelanggan', 'master_pelanggan.nama_pelanggan');

        $totalCount = $query->count();

        $data = $query
            ->orderBy('master_pelanggan.id_pelanggan', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $results = [];
        foreach ($data as $item) {
            $results[] = [
                'id' => $item->id_pelanggan,
                'text' => $item->id_pelanggan . ' - ' . $item->nama_pelanggan,
                'jenis' => $item->nama_pelanggan,
            ];
        }

        $hasMore = ($page * $perPage) < $totalCount;

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $hasMore,
            ],
        ]);
    }

    public function getDetailQuotation(Request $request){
        $data = OrderHeader::with('orderDetail')->where('id_pelanggan', $request->id_pelanggan)->where('is_active', 1)->get();
        dd($data);
        // $type = \explode('/', $request->no_qt)[1];
        // if($type == 'QTC'){
        //     $data = QuotationKontrakH::with('sales')->where('no_document', $request->no_qt)->first();
        //     $dataSampling = json_decode($data->data_pendukung_sampling);

        //     $array = [];

        //     foreach ($dataSampling as $item) {
        //         $kategori = explode('-', $item->kategori_2)[1];
        //         $jumlahTitik = $item->jumlah_titik * count($item->periode);

        //         if (isset($array[$kategori])) {
        //             $array[$kategori] += $jumlahTitik;
        //         } else {
        //             $array[$kategori] = $jumlahTitik;
        //         }
        //     }

        //     $buildData = $this->buildCategoryTree($array, $this->categoryStr);

        // }else{
        //     $data = QuotationNonKontrak::with('sales')->where('no_document', $request->no_qt)->first();
        //     $dataSampling = json_decode($data->data_pendukung_sampling);

        //     $array = [];

        //     foreach ($dataSampling as $item) {
        //         $kategori = explode('-', $item->kategori_2)[1];
        //         $jumlahTitik = $item->jumlah_titik;

        //         if (isset($array[$kategori])) {
        //             $array[$kategori] += $jumlahTitik;
        //         } else {
        //             $array[$kategori] = $jumlahTitik;
        //         }
        //     }

        //     $buildData = $this->buildCategoryTree($array, $this->categoryStr);
        // }

        // return response()->json([
        //     'message' => 'Data hasbeen retrieved successfully',
        //     'customer_detail' => $data,
        //     'breakdown_kategori' => $buildData,
        //     'status' => 200,
        // ]);
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
