<?php

namespace App\Http\Controllers\api;

use App\Models\{
    OrderDetail,
    FtcT
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class SendDraftController extends Controller
{

    public function index(Request $request)
    {
        $mapRelasi = [
            '1-Air'   => 'dataLapanganAir',
            '4-Udara' => [
                'dataLapanganIklimPanas',
                'dataLapanganIklimDingin',
                'dataLapanganKebisingan',
                'dataLapanganGetaran',
                'dataLapanganGetaranPersonal',
                'dataLapanganCahaya',
                'data_lapangan_ergonomi',
                'dataLapanganSinarUV',
                'dataLapanganMedanLM',
                'dataLapanganKebisinganPersonal',
                'dataLapanganDebuPersonal',

            ],
            '5-Emisi' => [
                'dataLapanganEmisiKendaraan',
                'dataLapanganEmisiCerobong',
            ]
        ];

        $query = OrderDetail::with('t_fct', 't_ftc_t')
            ->where('is_active', true)
            ->where('kategori_2', $request->kategori)
            ->orderBy('tanggal_sampling', 'asc');

        if (isset($mapRelasi[$request->kategori])) {
            $relasi = $mapRelasi[$request->kategori];

            if (is_array($relasi)) {
                $ids = collect();

                foreach ($relasi as $relation) {
                    $ids = $ids->merge(
                        OrderDetail::whereHas($relation, function ($q) {
                            $q->whereDate('created_at', '>', Carbon::create(2025, 8, 1));
                        })->pluck('id')
                    );
                }

                $query->whereIn('id', $ids->unique());
                $query->with($relasi);
            } else {
                $query->with($relasi)->whereHas($relasi, function ($q) {
                    $q->whereDate('created_at', '>', Carbon::create(2025, 8, 1));
                });
            }
        }

        $query->whereHas('t_fct', function ($q) {
            $q->whereNotNull('ftc_fd_sampling')
                ->where('is_active', true);
        });
        $query->whereHas('t_ftc_t', function ($q) {
            $q->whereNull('ftc_draft_send')
                ->where('is_active', true);
        });

        $data = $query->get();

        return DataTables::of($data)
            ->editColumn('tanggal_sampling', function ($row) {
                return Carbon::parse($row->tanggal_sampling)->format('Y-m-d');
            })
            ->make(true);
    }

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = FtcT::findOrFail($request->ftcT_id);
            $data->ftc_draft_send = Carbon::now();
            $data->user_draft_send = $this->user_id;
            $data->save();
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan', 'status' => 200, 'success' => true], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}
