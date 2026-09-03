<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterKategori;
use App\Models\OrderDetail;
use App\Models\TemplateStp;
use App\Models\Parameter;
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
            ->get(['id', 'nama_lab']);

        $selects = [];
        $bindings = [];
        foreach ($parameters as $i => $parameter) {
            $selects[] = "SUM(parameter LIKE ?) as p{$i}";
            $bindings[] = '%' . $parameter->nama_lab . '%';
        }

        $row = null;
        if (!empty($selects)) {
            $row = DB::table('order_detail')
                ->whereBetween('tanggal_terima', [$request->tgl_mulai, $request->tgl_akhir])
                ->where('kategori_2', $request->kategori)
                ->where('is_active', true)
                ->selectRaw(implode(', ', $selects), $bindings)
                ->first();
        }

        foreach ($parameters as $i => $parameter) {
            $alias = 'p' . $i;
            $data->push([
                'param' => $parameter->id . ";" . $parameter->nama_lab,
                'total_analisa' => (int) ($row->$alias ?? 0),
                'sudah_analisa' => 0,
                'belum_analisa' => 0,
            ]);
        }

        return Datatables::of($data)->make(true);
    }

    private function handleParameterMode(Request $request, $data)
    {
        $params = is_array($request->parameter) ? $request->parameter : [$request->parameter];
        $paramSet = array_flip($params);
        $paramNames = [];

        // mengambil nama parameter dari parameter yang dikirim
        // explode(';', "1;pH") → ["1", "pH"]
        foreach ($params as $param) {
            $parts = explode(';', $param);
            if (isset($parts[1])) {
                $paramNames[] = $parts[1];
            }
        }

        // mengambil semua sample yang sudah masuk lab
        $orders = DB::table('order_detail as od')
            ->joinSub($this->ftcSampleQuery($request), 'ftc', function ($join) {
                // cek apakah no_sampel ada di t_ftc
                $join->on('ftc.no_sample', '=', 'od.no_sampel');
            })
            ->where('od.kategori_2', $request->kategori)
            ->where('od.is_active', true)
            ->get(['od.no_sampel', 'od.parameter']);

        $analyzedMap = [];
        // membuat map apakah sample sudah di analisa atau belum
        // contoh output:
        // AI25001 → pH  = sudah
        // AI25001 → COD = sudah
        // AI25002 → pH  = sudah
        if (!empty($paramNames)) {
            foreach (DB::query()->fromSub($this->analyzedSampleQuery($request, $paramNames), 'an')->get() as $row) {
                $analyzedMap[$row->no_sampel][$row->parameter] = true;
            }
        }

        $counts = [];
        // mwmbuat map untuk menghitung total dan yang sudah dianalisa
        // contoh output:
        // $counts["1;pH"]  = total 0, sudah 0
        // $counts["34;COD"] = total 0, sudah 0
        foreach ($params as $param) {
            $counts[$param] = ['total' => 0, 'sudah' => 0];
        }

        foreach ($orders as $order) {
            $decoded = json_decode($order->parameter, true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $item) {
                if (!isset($paramSet[$item])) {
                    continue;
                }

                $counts[$item]['total']++;
                $paramName = explode(';', $item)[1] ?? '';
                if ($paramName !== '' && isset($analyzedMap[$order->no_sampel][$paramName])) {
                    $counts[$item]['sudah']++;
                }
            }
        }

        foreach ($params as $param) {
            $totalAnalisa = $counts[$param]['total'];
            $sudahAnalisa = $counts[$param]['sudah'];

            $data->push([
                'param' => $param,
                'total_analisa' => $totalAnalisa,
                'sudah_analisa' => $sudahAnalisa,
                'belum_analisa' => $totalAnalisa - $sudahAnalisa,
            ]);
        }

        return Datatables::of($data)->make(true);
    }

    private function ftcSampleQuery(Request $request)
    {
        return DB::table('t_ftc')
            ->select('no_sample')
            ->where('ftc_laboratory', '>=', $request->tgl_mulai)
            ->where('ftc_laboratory', '<=', $request->tgl_akhir . ' 23:59:59')
            ->distinct();
    }

    private function analyzedSampleQuery(Request $request, array $paramNames)
    {
        $tables = [
            'colorimetri' => 'id_colorimetri',
            'gravimetri' => 'id_gravimetri',
            'titrimetri' => 'id_titrimetri',
            'subkontrak' => 'id_subkontrak',
        ];

        $unions = [];
        foreach ($tables as $table => $fk) {
            $unions[] = DB::table('t_ftc as ftc')
                ->selectRaw('STRAIGHT_JOIN a.no_sampel, t.parameter')
                ->join('ws_value_air as a', function ($join) {
                    $join->on('a.no_sampel', '=', 'ftc.no_sample')
                        ->where('a.is_active', true);
                })

                ->join($table . ' as t', function ($join) use ($fk) {
                    $join->on('a.' . $fk, '=', 't.id')
                        ->where('t.is_active', true)
                        ->where('t.jenis_pengujian', 'sample');
                })
                ->where('ftc.ftc_laboratory', '>=', $request->tgl_mulai)
                ->where('ftc.ftc_laboratory', '<=', $request->tgl_akhir . ' 23:59:59')
                ->whereIn('t.parameter', $paramNames);
        }

        $query = array_shift($unions);
        foreach ($unions as $union) {
            $query->union($union);
        }

        return $query;
    }

    private function getAnalyzedOrders($request, $param)
    {
        $paramName = explode(';', $param)[1] ?? '';
        if ($paramName === '') {
            return DB::table('ws_value_air')->select('no_sampel')->whereRaw('0 = 1');
        }

        return DB::query()
            ->fromSub($this->analyzedSampleQuery($request, [$paramName]), 'an')
            ->select('no_sampel');
    }

    private function getBaseQuery($request, $param)
    {
        return DB::table('order_detail')
            ->whereIn('no_sampel', $this->ftcSampleQuery($request))
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
            $baseQuery = $this->getBaseQuery($request, $request->parameter);

            switch ($request->mode) {
                case 'total':
                    $data = $baseQuery;
                    break;
                case 'sudah':
                    $data = $baseQuery->whereIn('no_sampel', $this->getAnalyzedOrders($request, $request->parameter));
                    break;
                case 'belum':
                    $data = $baseQuery->whereNotIn('no_sampel', $this->getAnalyzedOrders($request, $request->parameter));
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
