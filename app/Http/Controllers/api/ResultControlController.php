<?php

namespace App\Http\Controllers\api;

use App\Models\{
    OrderDetail,
    Ftc
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class ResultControlController extends Controller
{

    public function index(Request $request)
    {
        try {
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
    
            $query = OrderDetail::with('t_fct')
                ->where('is_active', true)
                ->where('kategori_2', $request->kategori);
                
    
            if (isset($mapRelasi[$request->kategori])) {
                $relasi = $mapRelasi[$request->kategori];
    
                if (is_array($relasi)) {
                    $ids = collect();
    
                    foreach ($relasi as $relation) {
                        $ids = $ids->merge(
                            OrderDetail::whereHas($relation, function ($q) {
                                $q->whereDate('created_at', '>', Carbon::create(2025,8, 1));
                            })->pluck('id')
                        );
                    }
    
                    $query->whereIn('id', $ids->unique());
                    $query->with($relasi);
                } else {
                    $query->with($relasi)->whereHas($relasi, function ($q) {
                        $q->whereDate('created_at', '>', Carbon::create(2025,8, 1));
                    });
                }
            }
    
            $query->whereHas('t_fct', function ($q) {
                $q->whereNull('ftc_fd_sampling')
                    ->where('is_active', true);
            });

            $data = $query->orderBy('tanggal_sampling', 'ASC');
    
            return DataTables::of($data)
                ->editColumn('tanggal_sampling', function ($row) {
                    return Carbon::parse($row->tanggal_sampling)->format('Y-m-d');
                })
                ->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Ftc::findOrFail($request->ftc_id);
            $data->ftc_fd_sampling = Carbon::now();
            $data->user_fd_sampling = $this->user_id;
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
