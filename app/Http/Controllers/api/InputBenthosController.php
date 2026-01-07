<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use App\Models\Colorimetri;
use App\Models\Subkontrak;
use App\Services\AnalystFormula;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Datatables;
use Illuminate\Support\Facades\DB;

class InputBenthosController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with('TrackingSatu')
            ->whereHas('TrackingSatu', function ($q) use ($request) {
                $q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
            })
            ->whereJsonContains('parameter', '27;Benthos')
            ->where('kategori_2', '1-Air')
            ->where('is_active', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('subkontrak')
                    ->whereColumn('order_detail.no_sampel', 'subkontrak.no_sampel')
                    ->where('subkontrak.parameter', 'Benthos')
                    ->where('subkontrak.is_active', true);
            })
            ->orderBy('no_sampel', 'asc');

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request){
        DB::beginTransaction();
        try {
            $data_parsing = $request->all();
            $data_parsing = (object) $data_parsing;

            $data_kalkulasi = AnalystFormula::where('function','Benthos')
                ->where('id_parameter', '27')
                ->where('data', $data_parsing)
                ->process();

            if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return response()->json([
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				], 404);
			}
            $order_detail = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

            $header = new Subkontrak();
            $header->no_sampel = $request->no_sampel;
            $header->category_id = explode('-', $order_detail->kategori_2)[0];
            $header->parameter = 'Benthos';
            $header->jenis_pengujian = 'sample';
            $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $header->created_by = $this->karyawan;
            $header->save();

            WsValueAir::insert([
                'id_subkontrak' => $header->id,
                'no_sampel' => $request->no_sampel,
                'hasil_json' => json_encode($data_kalkulasi['result']),
            ]);

            // dd('paham !!');
            DB::commit();
            return response()->json([
                'message' => 'Success Input Data Benthos No Sampel ' . $request->no_sampel,
            ], 201);
        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => 'Failed Process data' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ],500);
        }
    }
}
