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
use App\Models\WsValueUdara;
use App\Models\MicrobioHeader;
use App\Models\Subkontrak;
use Carbon\Carbon;
use DataTables;
use Exception;
use App\Services\ParameterResultService;
class PerubahanParameterController extends Controller
{
    protected $parameterResultService;

    public function __construct(Request $request, ParameterResultService $parameterResultService)
    {
        parent::__construct($request);
        $this->parameterResultService = $parameterResultService;
    }

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
        $data = Parameter::whereHas('hargaParameter')->where('is_active', 1)->where('id_kategori', $request->id_kategori)->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function cekParameter(Request $request)
    {
        $data = OrderDetail::select(['no_sampel', 'parameter', 'kategori_2'])
            ->where('no_sampel', $request->no_sampel)
            ->where('kategori_2', $request->id_kategori)
            ->first();

        if (!$data) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        $parameterRaw = json_decode($data->parameter, true) ?? [];

        $namaYangSudahAda = $this->parameterResultService->getNamaYangSudahAda(
            $request->no_sampel,
            $request->id_kategori
        );

        $result = $this->parameterResultService->mapParameter($parameterRaw, $namaYangSudahAda);

        return response()->json(['status' => 'success', 'data' => $result]);
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
            $missingMicrobio = [];

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

                if (!$data) {
                    continue;
                }

                $oldParameterId = count($existing_parts) > 1 ? trim($existing_parts[0]) : null;
                $newParameterId = count($pengganti_parts) > 1 ? trim($pengganti_parts[0]) : null;
                
                $this->syncMicrobioHeader(
                    $data,
                    $oldParameterId,
                    $newParameterId,
                    $parameter_existing_value,
                    $parameter_pengganti_value,
                    $missingMicrobio
                );


    
                // Cari parameter secara dinamis di titrimetri, gravimetri, colorimetri, atau subkontrak
                $relations = ['titrimetri', 'gravimetri', 'colorimetri', 'subkontrak'];
                $wsValueAir = WsValueAir::with($relations)
                    ->where('no_sampel', $data->no_sampel)
                    ->where('is_active', 1)
                    ->where(function($query) use ($parameter_existing_value, $relations) {
                        foreach ($relations as $rel) {
                            $query->orWhereHas($rel, function($q) use ($parameter_existing_value) {
                                $q->where('parameter', $parameter_existing_value);
                                $q->whereNull('rejected_at');
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

            if (!empty($missingMicrobio)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Perubahan parameter di Order Detail berhasil, tetapi header Microbio tidak ditemukan.',
                    'warning' => 'Header Microbio tidak ditemukan untuk: ' . implode('; ', $missingMicrobio) . '. Data Order Detail sudah diubah. Silakan hubungi tim IT terkait kendala ini.',
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Perubahan parameter berhasil diupdate'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Perubahan parameter gagal diupdate',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function syncMicrobioHeader($orderDetail, $oldParameterId, $newParameterId, $oldParameterName, $newParameterName, array &$missingMicrobio)
    {
        $isUdara = stripos((string) $orderDetail->kategori_2, 'Udara') !== false;
        if (!$isUdara) {
            return;
        }

        $headers = collect();

        if ($oldParameterId !== null && $oldParameterId !== '') {
            $headers = MicrobioHeader::where('no_sampel', $orderDetail->no_sampel)
                ->where('id_parameter', $oldParameterId)
                ->get();
        }

        if ($headers->isEmpty() && $oldParameterName) {
            $headers = MicrobioHeader::where('no_sampel', $orderDetail->no_sampel)
                ->where('parameter', $oldParameterName)
                ->get();
        }

        if ($headers->isEmpty() || $newParameterId === null || $newParameterId === '' || $newParameterName === null || $newParameterName === '') {
            $missingMicrobio[] = $orderDetail->no_sampel . ' / ' . ($oldParameterName ?: '-') . ' (ID ' . ($oldParameterId ?: '-') . ')';
            return;
        }

        $headerIds = $headers->pluck('id')->all();
        $newToken = $newParameterId . ';' . $newParameterName;
        $oldToken = ($oldParameterId !== null && $oldParameterId !== '' && $oldParameterName)
            ? $oldParameterId . ';' . $oldParameterName
            : null;

        MicrobioHeader::whereIn('id', $headerIds)->update([
            'id_parameter' => $newParameterId,
            'parameter' => $newParameterName,
            'is_active' => 1,
        ]);

        WsValueUdara::whereIn('id_microbiologi_header', $headerIds)->update([
            'is_active' => 1,
        ]);

        $details = OrderDetail::where('no_sampel', $orderDetail->no_sampel)
            ->where(function ($query) use ($orderDetail, $oldToken, $newToken) {
                $query->where('id', $orderDetail->id);
                if ($oldToken) {
                    $query->orWhereJsonContains('parameter', $oldToken);
                }
                $query->orWhereJsonContains('parameter', $newToken);
            })
            ->get();

        foreach ($details as $detail) {
            $params = json_decode($detail->parameter, true);
            if (!is_array($params)) {
                $params = [];
            }

            foreach ($params as $index => $item) {
                $itemId = explode(';', (string) $item)[0];
                if ($item === $oldToken || $item === $newToken || ($oldParameterId !== null && $oldParameterId !== '' && (string) $itemId === (string) $oldParameterId)) {
                    $params[$index] = $newToken;
                }
            }

            $params = array_values(array_unique($params));
            if (!in_array($newToken, $params, true)) {
                $params[] = $newToken;
            }

            $detail->parameter = json_encode($params);
            $detail->is_active = 1;
            $detail->updated_by = $this->karyawan;
            $detail->updated_at = Carbon::now();
            $detail->save();
        }
    }

    private function getBaseName($nama)
    {
        return trim(preg_replace('/\s*\(.*?\)/', '', $nama));
    }


}
