<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\EmisiCerobongHeader;
use App\Models\HistoryAppReject;
use App\Models\MasterBakumutu;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Helpers\HelperSatuan;
use App\Models\MdlEmisi;

class TqcEmisiSumberTidakBergerakController extends Controller
{
    public function index()
    {
        $data = OrderDetail::where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '5-Emisi')
            ->where('kategori_3', '34-Emisi Sumber Tidak Bergerak')
            ->where('parameter', 'not like', '%Iso-%')
            ->orderBy('tanggal_terima');

        return DataTables::of($data)
            ->filter(function ($query) {
                foreach (request('columns', []) as $col) {
                    $name   = $col['data'] ?? null;
                    $search = $col['search']['value'] ?? null;

                    if ($search && in_array($name, [
                        'no_sampel',
                        'kategori_3',
                        'tanggal_sampling',
                        'tanggal_terima',
                    ])) {
                        $query->whereRaw("EXISTS (
                            SELECT 1 FROM order_detail od
                            WHERE od.cfr = order_detail.cfr
                            AND od.{$name} LIKE ?
                        )", ["%{$search}%"]);
                    }
                }
            })
            ->make(true);
    }

    public function detail(Request $request)
    {
        try {
            $parameter = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)
                ->first()->parameter;

            $parameterArray = json_decode($parameter, true);

            $parameterNames = array_map(function ($param) {
                $parts = explode(';', $param);
                return trim(end($parts));
            }, $parameterArray);

            $cerobong = EmisiCerobongHeader::with(['ws_value_cerobong'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('status', 0)
                ->whereIn('parameter', $parameterNames)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->get();

            // $id_regulasi = explode("-", json_decode($request->regulasi)[0])[0];
            $id_regulasi = $request->regulasi;
            $getSatuan   = new HelperSatuan;

            $parameters = collect(json_decode($parameter))->map(fn($item) => ['id' => explode(";", $item)[0], 'parameter' => explode(";", $item)[1]]);
            $mdlEmisi = MdlEmisi::whereIn('parameter_id', $parameters->pluck('id'))->get();
            
            $getHasilUji = function ($index, $parameterId, $hasilUji) use ($mdlEmisi) {
                if ($hasilUji && $hasilUji !== "-" && !str_contains($hasilUji, '<')) {
                    $colToSearch = "C$index";
                    $mdlEmisi = $mdlEmisi->where('parameter_id', $parameterId)->whereNotNull($colToSearch)->first();
                    if ($mdlEmisi && (float) $mdlEmisi->$colToSearch > (float) $hasilUji) {
                        $hasilUji = "<" . $mdlEmisi->$colToSearch;
                    }
                }

                return $hasilUji;
            };

            foreach ($cerobong as $item) {
                $dataLapangan = DataLapanganEmisiCerobong::where('no_sampel', $item->no_sampel)
                    ->select('waktu_pengambilan')
                    ->first();
                $bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method')
                    ->first();
                $item->durasi      = $dataLapangan->waktu_pengambilan ?? null;
                $item->satuan      = $bakuMutu->satuan ?? null;
                $item->baku_mutu   = $bakuMutu->baku_mutu ?? null;
                $item->method      = $bakuMutu->method ?? null;
                $item->nama_header = $bakuMutu->nama_header ?? null;

                $index = $getSatuan->emisi($item->satuan);
                $ws    = $item->ws_value_cerobong ?? null;
                if (!$ws) return "noWs";

                $ws    = $ws->toArray();
                $nilai = null;

                if ($index === null) {
                    // Cari dari f_koreksi_c...f_koreksi_c10
                    for ($i = 0; $i <= 10; $i++) {
                        $key = $i === 0 ? 'f_koreksi_c' : "f_koreksi_c$i";
                        if (! empty($ws[$key])) {
                            $nilai = $ws[$key];
                            break;
                        }
                    }

                    // Kalau belum ketemu, cari dari C...C10
                    if (empty($nilai)) {
                        for ($i = 0; $i <= 10; $i++) {
                            $key = $i === 0 ? 'C' : "C$i";

                            // Khusus C3, kalau kosong ambil dari C3_persen
                            if ($i === 3) {
                                $nilai = ! empty($ws[$key]) ? $ws[$key] : ($ws['C3_persen'] ?? null);
                            } elseif (! empty($ws[$key])) {
                                $nilai = $ws[$key];
                            }

                            if (! empty($nilai)) {
                                break;
                            }

                        }
                    }

                    $nilai = $nilai ?? '-';
                } else {
                    $fKoreksiKey = "f_koreksi_c$index";
                    $hasilKey    = "C$index";

                    $nilai = $ws[$fKoreksiKey] ?? $ws[$hasilKey] ?? '-';
                }

                $item->nilai_uji = $getHasilUji($index, $item->id_parameter, $nilai);
            }

            return Datatables::of($cerobong)
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    public function detailLapangan(Request $request)
    {
        try {
            $data = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sampel)->first();
            if ($data) {
                return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
            } else {
                return response()->json(['message' => 'Data lapangan tidak ditemukan', 'success' => false, 'status' => 404]);
            }
        } catch (\Exception $ex) {
            dd($ex);
        }
    }

    public function handleApproveSelected(Request $request)
    {
        DB::beginTransaction();
        try {
            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 2,
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil diapprove.',
                'success' => true,
                'status'  => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
                'success' => false,
                'status'  => 500,
            ], 500);
        }
    }
    public function getTrend(Request $request)
    {
        $orderDetails = OrderDetail::where('cfr', $request->cfr)
            ->where('status', 1)
            ->where('is_active', 1)
            ->get();

        $data = [];
        foreach ($orderDetails as $orderDetail) {
            $dataLapanganEmisiCerobong = DataLapanganEmisiCerobong::where('no_sampel', $orderDetail->no_sampel)->get();
            $mapHasil                  = fn($col) => $col->values()
                ->map(fn($hasil_uji) => json_encode([
                    'co2' => $hasil_uji->co2,
                    'co'  => $hasil_uji->co,
                    'hc'  => $hasil_uji->hc,
                    'o2'  => $hasil_uji->o2,
                ]))
                ->toArray();

            $currentDataLapangan = $dataLapanganEmisiCerobong->where('no_sampel', $orderDetail->no_sampel);
            $hasil               = $mapHasil($currentDataLapangan);
            $history             = $mapHasil($dataLapanganEmisiCerobong->where('no_sampel', '!=', $orderDetail->no_sampel));

            $currentDataLapangan = $currentDataLapangan->first();

            $data[] = [
                'no_sampel'                     => $orderDetail->no_sampel,
                'titik'                         => $orderDetail->keterangan_1,
                'history'                       => $history,
                'hasil'                         => $hasil,
                'sampler'                       => $currentDataLapangan->created_by,
                'approved_by'                   => $currentDataLapangan->approved_by,

                'id'                            => $currentDataLapangan->id,
                'nama_perusahaan'               => $orderDetail->nama_perusahaan,
                'no_order'                      => $orderDetail->no_order,
                'kategori_3'                    => $orderDetail->kategori_3,
                'data_lapangan_emisi_kendaraan' => $currentDataLapangan,
            ];
        }

        return response()->json([
            'data'    => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }

    public function approveData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            if ($data) {
                $data->status = 2;
                $data->save();
                HistoryAppReject::insert([
                    'no_lhp'      => $data->cfr,
                    'no_sampel'   => $data->no_sampel,
                    'kategori_2'  => $data->kategori_2,
                    'kategori_3'  => $data->kategori_3,
                    'menu'        => 'TQC Emisi Sumber Tidak Bergerak',
                    'status'      => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan,
                ]);
                DB::commit();
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Data tqc no sample ' . $data->no_sampel . ' berhasil diapprove',
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage(),
            ]);
        }
    }

    public function rejectData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            if ($data) {
                $data->status = 0;
                $data->save();
                DB::commit();
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Data tqc no sample ' . $data->no_sampel . ' berhasil direject',
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage(),
            ]);
        }
    }
}
