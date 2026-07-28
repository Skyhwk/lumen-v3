<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Lims\OrderDetail;
use App\Models\WsValueAir;
use App\Models\Colorimetri;
use App\Models\Subkontrak;
use App\Services\AnalystFormula;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Datatables;
use Illuminate\Support\Facades\DB;

class LimsInputNectonController extends Controller
{
    public function index(Request $request)
    {
        $ftcSamples = \App\Models\Ftc::where('ftc_laboratory', 'LIKE', "%$request->tanggal%")->pluck('no_sample')->toArray();

        $data = OrderDetail::with('TrackingSatu')
            ->whereIn('no_sampel', $ftcSamples)
            ->whereJsonContains('parameter', '107;Necton')
            ->where('kategori_2', '1-Air')
            ->where('is_active', true)
            ->whereNotExists(function ($query) {
                $dbDefault = env('DB_DATABASE');
                $query->select(DB::raw(1))
                    ->from("{$dbDefault}.subkontrak as subkontrak")
                    ->whereColumn('order_detail.no_sampel', 'subkontrak.no_sampel')
                    ->where('subkontrak.parameter', 'Necton')
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

            $data_kalkulasi = AnalystFormula::where('function','Necton')
                ->where('id_parameter', '107')
                ->where('data', $data_parsing)
                ->process();

            if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return response()->json([
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				], 404);
			}

            $header = new Subkontrak();
            $header->no_sampel = $request->no_sampel;
            $header->parameter = 'Necton';
            $header->jenis_pengujian = 'sample';
            $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $header->created_by = $this->karyawan;
            $header->save();

            WsValueAir::create([
                'id_subkontrak' => $header->id,
                'no_sampel' => $request->no_sampel,
                'hasil_json' => json_encode($data_kalkulasi),
            ]);

            // dd('paham !!');
            DB::commit();
            return response()->json([
                'message' => 'Success Input Data Necton No Sampel ' . $request->no_sampel,
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
