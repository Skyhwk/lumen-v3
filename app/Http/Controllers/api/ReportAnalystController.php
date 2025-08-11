<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterKategori;
use App\Models\TemplateStp;
use App\Models\Parameter;
use App\Models\OrderDetail;
use Yajra\Datatables\Datatables;
use DB;

class ReportAnalystController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = collect();

            if ($request->mode == 'toplist') {
                return $this->handleToplistMode($request, $data);
            }

            if (!empty($request->parameter)) {
                return $this->handleParameterMode($request, $data);
            }

            return Datatables::of($data)->make(true);

        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    private function handleToplistMode(Request $request, $data)
    {
        $parameters = Parameter::where('id_kategori', explode('-', $request->kategori)[0])
            ->where('is_active', true)
            ->get();

        foreach ($parameters as $parameter) {
            $totalAnalisa = OrderDetail::where('parameter', 'LIKE', "%{$parameter->nama_lab}%")
                ->whereBetween('tanggal_terima', [$request->tgl_mulai, $request->tgl_akhir])
                ->where('kategori_2', $request->kategori)
                ->where('is_active', true)
                ->count();

            $data->push([
                'param' => $parameter->id . ";" . $parameter->nama_lab,
                'total_analisa' => $totalAnalisa,
                'sudah_analisa' => 0,
                'belum_analisa' => 0,
            ]);
        }

        return Datatables::of($data)->make(true);
    }

    private function handleParameterMode(Request $request, $data)
    {
        $get_no_sampel = DB::table('t_ftc')
            ->whereDate('ftc_laboratory', '>=', $request->tgl_mulai)
            ->whereDate('ftc_laboratory', '<=', $request->tgl_akhir)
            ->pluck('no_sample')
            ->unique()
            ->toArray();

        foreach ($request->parameter as $param) {
            $analyzedOrdersQuery = $this->getAnalyzedOrders($request, $param, $get_no_sampel);
            $baseQuery = $this->getBaseQuery($request, $param, $get_no_sampel);

            $totalAnalisa = (clone $baseQuery)->count();
            $sudahAnalisa = (clone $baseQuery)->whereIn('no_sampel', $analyzedOrdersQuery)->count();
            $belumAnalisa = (clone $baseQuery)->whereNotIn('no_sampel', $analyzedOrdersQuery)->count();

            $data->push([
                'param' => $param,
                'total_analisa' => $totalAnalisa,
                'sudah_analisa' => $sudahAnalisa,
                'belum_analisa' => $belumAnalisa,
            ]);
        }

        return Datatables::of($data)->make(true);
    }

    private function getAnalyzedOrders($request, $param, $get_no_sampel)
    {
        return DB::table('ws_value_air AS a')
            ->leftJoin('colorimetri AS b', $this->joinCondition('b', 'a.id_colorimetri'))
            ->leftJoin('gravimetri AS c', $this->joinCondition('c', 'a.id_gravimetri'))
            ->leftJoin('titrimetri AS d', $this->joinCondition('d', 'a.id_titrimetri'))
            ->leftJoin('subkontrak AS e', $this->joinCondition('e', 'a.id_subkontrak'))
            ->where('a.is_active', true)
            ->whereIn('a.no_sampel', $get_no_sampel)
            ->where(function ($query) use ($param) {
                $paramName = explode(';', $param)[1];
                $query->where('b.parameter', $paramName)
                    ->orWhere('c.parameter', $paramName)
                    ->orWhere('d.parameter', $paramName)
                    ->orWhere('e.parameter', $paramName);
            })
            ->pluck('a.no_sampel')
            ->toArray();
    }

    private function joinCondition($table, $foreignKey)
    {
        return function ($join) use ($table, $foreignKey) {
            $join->on($foreignKey, '=', "$table.id")
                ->where("$table.is_active", true)
                ->where("$table.jenis_pengujian", 'sample');
        };
    }

    private function getBaseQuery($request, $param, $get_no_sampel)
    {
        return DB::table('order_detail')
            ->whereIn('no_sampel', $get_no_sampel)
            ->whereJsonContains('parameter', (string) $param)
            ->where('kategori_2', $request->kategori)
            ->where('is_active', true);
    }

    public function getAllKategori(Request $request)
    {
        $kategori = MasterKategori::select('id', 'nama_kategori')->get();
        return response()->json($kategori, 200);
    }

    public function getTemplate(Request $request)
    {
        $kategori = TemplateStp::select('id', 'name')
            ->where('category_id', explode('-', $request->kategori)[0])
            ->get();
        return response()->json($kategori, 200);
    }

    public function getParameter(Request $request)
    {
        $kategoriIds = explode('-', $request->kategori);

        if (!isset($kategoriIds[1])) {
            return response()->json(['error' => 'Kategori tidak valid'], 400);
        }

        $kategori = Parameter::select('id', 'nama_lab')
            ->whereIn('id_kategori', [$kategoriIds[0]])
            ->where('is_active', true)
            ->get();

        return response()->json($kategori, 200);
    }

    public function getParameterTemplate(Request $request)
    {
        $kategori = TemplateStp::select('param')
            ->whereIn('id', $request->template)
            ->get()
            ->flatMap(function ($item) {
                return collect(json_decode($item->param))
                    ->map(function ($paramName) {
                        return Parameter::select('id', 'nama_lab')
                            ->where('nama_lab', $paramName)
                            ->first() ?: [];
                    })
                    ->filter()
                    ->values();
            })
            ->toArray();

        return response()->json($kategori, 200);
    }

    public function getSample(Request $request)
    {
        try {
            $get_no_sampel = DB::table('t_ftc')
                ->whereDate('ftc_laboratory', '>=', $request->tgl_mulai)
                ->whereDate('ftc_laboratory', '<=', $request->tgl_akhir)
                ->pluck('no_sample')
                ->unique()
                ->toArray();

            $baseQuery = $this->getBaseQuery($request, $request->parameter, $get_no_sampel);

            switch ($request->mode) {
                case 'total':
                    $data = $baseQuery;
                    break;
                case 'sudah':
                    $analyzedOrders = $this->getAnalyzedOrders($request, $request->parameter, $get_no_sampel);
                    $data = $baseQuery->whereIn('no_sampel', $analyzedOrders);
                    break;
                case 'belum':
                    $analyzedOrders = $this->getAnalyzedOrders($request, $request->parameter, $get_no_sampel);
                    $data = $baseQuery->whereNotIn('no_sampel', $analyzedOrders);
                    break;
                default:
                    $data = $baseQuery;
            }

            return DataTables::of($data)->make(true);

        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }
}