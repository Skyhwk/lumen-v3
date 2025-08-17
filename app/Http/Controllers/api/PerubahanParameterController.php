<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\WsValueAir;
use App\Models\Subkontrak;
use Carbon\Carbon;
use DataTables;
use Exception;

class PerubahanParameterController extends Controller
{
    public function index(Request $request)
    {
        // $data = OrderDetail::with(['orderHeader', 'TrackingSatu', 'TrackingDua', 'union', 'tc_order_detail'])->whereNotNull('tanggal_terima')->where('is_active', 1);
        $data = OrderDetail::where('is_active', 1)->orderBy('id', 'desc');

        $data = $data->orderBy('id', 'desc');

        return DataTables::of($data)
            ->addIndexColumn()
            ->make(true);
    }

    public function getParameter(Request $request)
    {
        $data = Parameter::where('is_active', 1)->where('id_kategori', $request->id_kategori)->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function updatePerubahanParameter(Request $request)
    {
        // Validasi request
        if(count($request->parameterPairs) == 0){
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter tidak boleh kosong'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Ambil data OrderDetail berdasarkan id dan parameter_existing
            foreach($request->parameterPairs as $key => $pair){
                $parameter_existing = $pair['parameter_existing'];
                $parameter_pengganti = $pair['parameter_pengganti'];

                $data = OrderDetail::where('id', $request->id)
                    ->whereJsonContains('parameter', $parameter_existing)
                    ->first();

                if($data){
                    $parameter = json_decode($data->parameter, true);
                    if (!is_array($parameter)) $parameter = [];
                    $key = array_search($parameter_existing, $parameter);
                    unset($parameter[$key]);
                    $parameter[] = $parameter_pengganti;
                    $parameter = array_values($parameter);
                    $data->parameter = json_encode($parameter);
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now();
                    $data->save();
                }
    
                $parameter_existing_value = null;
                $parameter_pengganti_value = null;
                $existing_parts = explode(";", $parameter_existing);
                $pengganti_parts = explode(";", $parameter_pengganti);
    
                if (count($existing_parts) > 1) {
                    $parameter_existing_value = trim($existing_parts[1]);
                } else {
                    $parameter_existing_value = trim($parameter_existing);
                }
    
                if (count($pengganti_parts) > 1) {
                    $parameter_pengganti_value = trim($pengganti_parts[1]);
                } else {
                    $parameter_pengganti_value = trim($parameter_pengganti);
                }
    
                // Cari parameter secara dinamis di titrimetri, gravimetri, colorimetri, atau subkontrak
                $relations = ['titrimetri', 'gravimetri', 'colorimetri', 'subkontrak'];
                $wsValueAir = WsValueAir::with($relations)
                    ->where('no_sampel', $data->no_sampel)
                    ->where('is_active', 1)
                    ->where(function($query) use ($parameter_existing_value, $relations) {
                        foreach ($relations as $rel) {
                            $query->orWhereHas($rel, function($q) use ($parameter_existing_value) {
                                $q->where('parameter', $parameter_existing_value);
                            });
                        }
                    })
                    ->first();
    
                if ($wsValueAir) {
                    foreach ($relations as $rel) {
                        if ($wsValueAir->$rel) {
                            // Pastikan parameter yang diupdate memang sama dengan parameter_existing_value
                            if ($wsValueAir->$rel->parameter == $parameter_existing_value) {
                                $wsValueAir->$rel->parameter = $parameter_pengganti_value;
                                $wsValueAir->$rel->save();
                            }
                        }
                    }
                }
                
            }


            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Perubahan parameter berhasil diupdate'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            // Log error untuk debugging, jangan gunakan dd di production
            \Log::error('Gagal update perubahan parameter: '.$th->getMessage(), [
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Perubahan parameter gagal diupdate',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function getBaseName($nama)
    {
        return trim(preg_replace('/\s*\(.*?\)/', '', $nama));
    }


}
