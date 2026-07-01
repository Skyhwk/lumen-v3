<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class TrackingFdlController extends Controller
{
    private function getInnerOrderQuery(Request $request, $bulan, $tahun)
    {
        $query = DB::table('order_detail')
            ->select('no_sampel')
            ->where('is_active', true);

        if (!empty($bulan) && $bulan !== 'all' && !empty($tahun) && $tahun !== 'all') {
            $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->toDateString();
            $endDate = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->toDateString();
            $query->whereBetween('tanggal_sampling', [$startDate, $endDate]);
        }

        $columns = $request->input('columns');
        if (is_array($columns)) {
            foreach ($columns as $col) {
                $colName = $col['data'] ?? '';
                $searchVal = $col['search']['value'] ?? '';
                if ($searchVal !== '') {
                    if ($colName === 'no_sampel') {
                        $query->where('no_sampel', 'like', "%{$searchVal}%");
                    } elseif ($colName === 'kategori_3') {
                        $query->where('kategori_3', 'like', "%{$searchVal}%");
                    } elseif ($colName === 'keterangan_1') {
                        $query->where('keterangan_1', 'like', "%{$searchVal}%");
                    }
                }
            }
        }

        return $query;
    }

    private function getUnionSql($innerOrderQuery)
    {
        $relations = (new OrderDetail)->getAnyDataLapanganRelations();
        $queries = [];
        
        $innerSql = $innerOrderQuery->toSql();
        $bindings = $innerOrderQuery->getBindings();
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : DB::getPdo()->quote($binding);
            $innerSql = preg_replace('/\?/', $value, $innerSql, 1);
        }

        foreach ($relations as $relation) {
            $model = new OrderDetail();
            $relInstance = $model->$relation();
            $relatedModel = $relInstance->getRelated();
            $table = $relatedModel->getTable();
            
            $queries[] = "SELECT dl.no_sampel, MAX(dl.created_at) as tanggal_input_fdl 
                          FROM `$table` dl
                          WHERE dl.no_sampel IN ($innerSql)
                          GROUP BY dl.no_sampel";
        }
        
        $unionSql = implode(" UNION ALL ", $queries);
        return "SELECT no_sampel, MAX(tanggal_input_fdl) as tanggal_input_fdl FROM ($unionSql) as combined_fdl GROUP BY no_sampel";
    }

    private function filterDateColumn($query, $column, $keyword)
    {
        $keyword = trim(strtolower($keyword));
        if (empty($keyword)) {
            return;
        }

        $indonesianMonths = [
            'januari' => '01', 'jan' => '01',
            'februari' => '02', 'feb' => '02',
            'maret' => '03', 'mar' => '03',
            'april' => '04', 'apr' => '04',
            'mei' => '05',
            'juni' => '06', 'jun' => '06',
            'juli' => '07', 'jul' => '07',
            'agustus' => '08', 'agu' => '08', 'agt' => '08',
            'september' => '09', 'sep' => '09',
            'oktober' => '10', 'okt' => '10',
            'november' => '11', 'nov' => '11',
            'desember' => '12', 'des' => '12',
        ];

        if (isset($indonesianMonths[$keyword])) {
            $query->whereMonth($column, $indonesianMonths[$keyword]);
            return;
        }

        $parts = preg_split('/\s+/', $keyword);

        if (count($parts) === 3) {
            $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $monthName = $parts[1];
            $year = $parts[2];

            $month = isset($indonesianMonths[$monthName]) ? $indonesianMonths[$monthName] : null;
            if ($month && is_numeric($day) && is_numeric($year)) {
                $query->whereDate($column, "$year-$month-$day");
                return;
            }
        } elseif (count($parts) === 2) {
            $part1 = $parts[0];
            $part2 = $parts[1];

            if (isset($indonesianMonths[$part1]) && is_numeric($part2)) {
                $query->whereMonth($column, $indonesianMonths[$part1])
                      ->whereYear($column, $part2);
                return;
            } elseif (is_numeric($part1) && isset($indonesianMonths[$part2])) {
                $day = str_pad($part1, 2, '0', STR_PAD_LEFT);
                $query->whereDay($column, $day)
                      ->whereMonth($column, $indonesianMonths[$part2]);
                return;
            }
        }

        if (is_numeric($keyword)) {
            if (strlen($keyword) == 4) {
                $query->whereYear($column, $keyword);
            } else {
                $query->where(function($q) use ($column, $keyword) {
                    $q->whereDay($column, $keyword)
                      ->orWhereMonth($column, $keyword);
                });
            }
        } else {
            $query->where($column, 'like', "%{$keyword}%");
        }
    }

    public function getInputtedFdl(Request $request) {
        try {
            $bulan = $request->get('bulan', '');
            $tahun = $request->get('tahun', '');
            
            $innerOrderQuery = $this->getInnerOrderQuery($request, $bulan, $tahun);
            $unionSql = $this->getUnionSql($innerOrderQuery);

            $query = OrderDetail::from('order_detail')
                ->join(DB::raw("($unionSql) as fdl"), 'fdl.no_sampel', '=', 'order_detail.no_sampel')
                ->where('order_detail.is_active', true);

            if (!empty($bulan) && $bulan !== 'all' && !empty($tahun) && $tahun !== 'all') {
                $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->toDateString();
                $endDate = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->toDateString();
                $query->whereBetween('order_detail.tanggal_sampling', [$startDate, $endDate]);
            }

            $query->select('order_detail.*', 'fdl.tanggal_input_fdl');

            return DataTables::of($query)
                ->filterColumn('no_sampel', function($query, $keyword) {
                    $query->where('order_detail.no_sampel', 'like', "%{$keyword}%");
                })
                ->filterColumn('tanggal_sampling', function($query, $keyword) {
                    $this->filterDateColumn($query, 'order_detail.tanggal_sampling', $keyword);
                })
                ->filterColumn('tanggal_input_fdl', function($query, $keyword) {
                    $this->filterDateColumn($query, 'fdl.tanggal_input_fdl', $keyword);
                })
                ->filterColumn('kategori_3', function($query, $keyword) {
                    $query->where('order_detail.kategori_3', 'like', "%{$keyword}%");
                })
                ->filterColumn('keterangan_1', function($query, $keyword) {
                    $query->where('order_detail.keterangan_1', 'like', "%{$keyword}%");
                })
                ->filterColumn('parameter', function ($query, $keyword) {
                    $query->whereRaw(
                        'JSON_LENGTH(order_detail.parameter) = ?',
                        [(int) $keyword]
                    );
                })
                ->make(true);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }

    public function getNotInputtedFdl(Request $request) {
        try {
            $bulan = $request->get('bulan', '');
            $tahun = $request->get('tahun', '');
            
            $innerOrderQuery = $this->getInnerOrderQuery($request, $bulan, $tahun);
            $unionSql = $this->getUnionSql($innerOrderQuery);

            $query = OrderDetail::from('order_detail')
                ->leftJoin(DB::raw("($unionSql) as fdl"), 'fdl.no_sampel', '=', 'order_detail.no_sampel')
                ->where('order_detail.is_active', true)
                ->whereNull('fdl.no_sampel');

            if (!empty($bulan) && $bulan !== 'all' && !empty($tahun) && $tahun !== 'all') {
                $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->toDateString();
                $endDate = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->toDateString();
                $query->whereBetween('order_detail.tanggal_sampling', [$startDate, $endDate]);
            }

            $query->select('order_detail.*');

            return DataTables::of($query)
                ->filterColumn('no_sampel', function($query, $keyword) {
                    $query->where('order_detail.no_sampel', 'like', "%{$keyword}%");
                })
                ->filterColumn('tanggal_sampling', function($query, $keyword) {
                    $this->filterDateColumn($query, 'order_detail.tanggal_sampling', $keyword);
                })
                ->filterColumn('kategori_3', function($query, $keyword) {
                    $query->where('order_detail.kategori_3', 'like', "%{$keyword}%");
                })
                ->filterColumn('keterangan_1', function($query, $keyword) {
                    $query->where('order_detail.keterangan_1', 'like', "%{$keyword}%");
                })
                ->filterColumn('parameter', function ($query, $keyword) {
                    $query->whereRaw(
                        'JSON_LENGTH(order_detail.parameter) = ?',
                        [(int) $keyword]
                    );
                })
                ->make(true);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }

    public function getAvailableYears(Request $request) {
        try {
            $years = OrderDetail::where('is_active', true)
                ->whereNotNull('tanggal_sampling')
                ->selectRaw('DISTINCT YEAR(tanggal_sampling) as tahun')
                ->orderBy('tahun', 'desc')
                ->pluck('tahun');

            return response()->json([
                'success' => true,
                'data' => $years
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }
}