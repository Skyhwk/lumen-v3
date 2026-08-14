<?php

namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Models\DataLapanganSwab;
use App\Models\HistoryAppReject;
use App\Models\KebisinganHeader;
use App\Models\LingkunganHeader;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MdlUdara;
use App\Models\MicrobioHeader;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\Subkontrak;
use App\Models\SwabTestHeader;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use Carbon\Carbon;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WsFinalSwabTesController extends Controller
{

    public function index(Request $request)
    {
        $data = OrderDetail::selectRaw('
            cfr,
            GROUP_CONCAT(no_sampel SEPARATOR ",") as no_sampel,
            MAX(nama_perusahaan) as nama_perusahaan,
            MAX(konsultan) as konsultan,
            MAX(no_quotation) as no_quotation,
            MAX(no_order) as no_order,
            MAX(parameter) as parameter,
            MAX(regulasi) as regulasi,
            MAX(kategori_2) as kategori_2,
            MAX(kategori_3) as kategori_3,
            GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") as tanggal_sampling,
            GROUP_CONCAT(DISTINCT   tanggal_terima SEPARATOR ",") as tanggal_terima,
            MIN(tanggal_terima) as tanggal_terima_min')
            ->with([
                'lhps_swab_udara',
                'orderHeader:id,nama_pic_order,jabatan_pic_order,no_pic_order,email_pic_order,alamat_sampling',
            ])
            ->where('is_active', true)
            ->where('kategori_2', '7-Swab Test')
            ->where('status', 0)
            ->when($request->from && $request->to, function ($q) use ($request) {
                $from = $request->from . '-01';
                $to = date('Y-m-t', strtotime($request->to . '-01'));
                $q->whereBetween('tanggal_sampling', [$from, $to]);
            })
            ->groupBy('cfr')
            ->orderBy('tanggal_sampling');

        $data = $data->get();
        $data = \App\Services\WsFinalApprovalService::appendProgressAndFilter($data, $request);

        return Datatables::of($data)->make(true);
    }

    public function getDetailCfr(Request $request)
    {
        $data = OrderDetail::with(['swabTesHeader', 'dataLapanganSwab', 'udaraSubKontrak'])
            ->where('cfr', $request->cfr)
            ->where('status', 0)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data'    => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }

    public function detail(Request $request)
    {
        DB::beginTransaction();
        try {
            // Decode parameter dari JSON string
            $parameters = json_decode($request->parameter, true);

            // Extract nama parameter (bagian setelah semicolon)
            $parameterNames = [];
            if (is_array($parameters)) {
                foreach ($parameters as $param) {
                    $parts = explode(';', $param);
                    if (isset($parts[1])) {
                        $parameterNames[] = trim($parts[1]);
                    }
                }
            }

            $regulasiArray = json_decode($request->regulasi, true);

            // Extract ID regulasi (bagian sebelum dash)
            $id_regulasi = null;
            if (is_array($regulasiArray) && count($regulasiArray) > 0) {
                $regulasiString = $regulasiArray[0];                // Ambil elemen pertama
                $parts          = explode('-', $regulasiString, 2); // Split hanya di dash pertama
                $id_regulasi    = ((int) trim($parts[0]));          // Ambil bagian sebelum dash
            }

            $data = SwabTestHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('status', 0)
                ->whereIn('parameter', $parameterNames)
                ->where('is_active', 1)
                ->get();

            $data2 = SubKontrak::with(['ws_value_linkungan', 'ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->whereIn('parameter', $parameterNames)
                ->where('is_active', 1)
                ->get();

            $merge = $data->merge($data2);

            foreach ($merge as $item) {
                $parameter = Parameter::where('nama_lab', $item->parameter)
                    ->where('is_active', 1)
                    ->first();
                $bakuMutu = MasterBakumutu::where("id_parameter", $parameter->id)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method', 'nama_header')
                    ->first();

                $item->durasi      = $dataLapangan->durasi_pengambilan ?? null;
                $item->satuan      = $bakuMutu->satuan ?? null;
                $item->baku_mutu   = $bakuMutu->baku_mutu ?? null;
                $item->method      = $bakuMutu->method ?? null;
                $item->nama_header = $bakuMutu->nama_header ?? null;
            }

            $getSatuan  = new HelperSatuan;
            $parameters = collect(json_decode($request->parameter))->map(fn($item) => ['id' => explode(";", $item)[0], 'parameter' => explode(";", $item)[1]]);
            $mdlUdara   = MdlUdara::whereIn('parameter_id', $parameters->pluck('id'))->get();

            $getHasilUji = function ($index, $parameterId, $hasilUji) use ($mdlUdara) {
                if ($hasilUji && $hasilUji !== "-" && !str_contains($hasilUji, '<')) {
                    $colToSearch = "hasil" . ($index ?: 1);
                    $mdl         = $mdlUdara->where('parameter_id', $parameterId)->whereNotNull($colToSearch)->first();
                    if ($mdl && (float) $mdl->$colToSearch > (float) $hasilUji) {
                        $hasilUji = "<" . $mdl->$colToSearch;
                    }
                }

                return $hasilUji;
            };

            return Datatables::of($merge)
                ->addColumn('nilai_uji', function ($item) use ($getSatuan, $getHasilUji) {
                    $satuan = $item->satuan ?? null;
                    $index  = $getSatuan->udara($satuan);

                    $source = $item->ws_udara ?? $item->ws_value_linkungan ?? null;
                    if (!$source) {
                        return 'noWs';
                    }

                    $hasil = is_array($source) ? $source : $source->toArray();
                    $has   = function ($key) use ($hasil) {
                        return isset($hasil[$key]) && $hasil[$key] !== null && $hasil[$key] !== '';
                    };

                    if ($index === null) {
                        // 1) f_koreksi_c (tanpa nomor) lalu f_koreksi_c1..f_koreksi_c16
                        if ($has('f_koreksi_c')) return $getHasilUji(1, $item->id_parameter, $hasil['f_koreksi_c']);

                        for ($i = config('column_ws.ws_value_lingkungan.min'); $i <= config('column_ws.ws_value_lingkungan.max'); $i++) {
                            $k = "f_koreksi_c{$i}";
                            if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
                        }

                        // 2) C (tanpa nomor) lalu C1..C16
                        if ($has('C')) return $hasil['C'];
                        for ($i = config('column_ws.ws_value_lingkungan.min'); $i <= config('column_ws.ws_value_lingkungan.max'); $i++) {
                            $k = "C{$i}";
                            if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
                        }

                        // 3) f_koreksi_1..f_koreksi_17
                        for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
                            $k = "f_koreksi_{$i}";
                            if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
                        }

                        // 4) hasil1..hasil17
                        for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
                            $k = "hasil{$i}";
                            if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
                        }

                        return '-';
                    }

                    $CIndex     = $index == 1 ? '' : $index - 1;
                    $keysToTry  = [
                        "f_koreksi_c{$index}",
                        "C{$CIndex}",
                        "f_koreksi_{$index}",
                        "hasil{$index}",
                    ];

                    if ($index == 17) {
                        foreach ($keysToTry as $k) {
                            if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                        foreach (['f_koreksi_c2', 'C2', 'f_koreksi_2', 'hasil2'] as $k) {
                            if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                    } elseif ($index == 15) {
                        foreach ($keysToTry as $k) {
                            if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                        foreach (['f_koreksi_c3', 'C3', 'f_koreksi_3', 'hasil3'] as $k) {
                            if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                    } elseif ($index == 16) {
                        foreach ($keysToTry as $k) {
                            if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                        foreach (['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'] as $k) {
                            if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                    } else {
                        foreach ($keysToTry as $k) {
                            if ($has($k) && isset($hasil[$k])) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                        foreach (['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'] as $k) {
                            if ($has($k) && isset($hasil[$k])) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
                        }
                    }

                    return '-';
                })
                ->make(true);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function detailLapangan(Request $request)
    {
        $parameterNames = [];

        if (!isset($request->parameter) || $request->parameter == null || $request->parameter == '') {
            return response()->json(['message' => 'Parameter tidak ditemukan'], 401);
        }

        if (is_array($request->parameter)) {
            foreach ($request->parameter as $param) {
                $paramParts = explode(";", $param);
                if (isset($paramParts[1])) {
                    $parameterNames[] = trim($paramParts[1]);
                }
            }
        }

        $noOrder = explode('/', $request->no_sampel)[0] ?? null;

        $Lapangan = OrderDetail::where('no_order', $noOrder)->get();

        $lapangan2 = $Lapangan->map(function ($item) {
            return $item->no_sampel;
        })->unique()->sortBy(function ($item) {
            return (int) explode('/', $item)[1];
        })->values();

        $totLapangan = $lapangan2->count();

        try {
            $data = DataLapanganSwab::where('no_sampel', $request->no_sampel)->first();

            if (!$data) {
                return response()->json(['message' => 'Data Lapangan Tidak Ditemukan'], 401);
            }

            $urutan            = $lapangan2->search($data->no_sampel);
            $urutanDisplay     = $urutan + 1;
            $data['urutan']    = "{$urutanDisplay}/{$totLapangan}";
            $data['parameter'] = $parameterNames[0];

            return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
        } catch (\Exception $ex) {
            dd($ex);
        }
    }

    public function rejectAnalys(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = SwabTestHeader::where('id', $request->id)->update([
                'is_approved'  => 0,
                'notes_reject' => $request->note,
                'rejected_by'  => $this->karyawan,
                'rejected_at'  => Carbon::now(),
                'approved_by'  => null,
                'approved_at'  => null,
            ]);
            if ($data) {
                DB::commit();
                return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
            } else {
                DB::rollBack();
                return response()->json(['message' => 'Gagal Reject']);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal Reject']);
        }
    }

    public function AddSubKontrak(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->subCategory == 11 || $request->subCategory == 27) {
                $data                  = new Subkontrak();
                $data->no_sampel       = $request->no_sampel;
                $data->category_id     = $request->category;
                $data->parameter       = $request->parameter;
                $data->note            = $request->keterangan;
                $data->jenis_pengujian = $request->jenis_pengujian;
                $data->is_active       = true;
                $data->is_approve      = 1;
                $data->approved_at     = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by     = $this->karyawan;
                $data->created_at      = Carbon::now()->format('Y-m-d H:i:s');
                $data->created_by      = $this->karyawan;
                $data->save();

                $ws                = new WsValueLingkungan();
                $ws->no_sampel     = $request->no_sampel;
                $ws->id_subkontrak = $data->id;
                $ws->flow          = $request->flow;
                $ws->durasi        = $request->durasi;
                $ws->C             = $request->C;
                $ws->C1            = $request->C1;
                $ws->C2            = $request->C2;
                $ws->is_active     = true;
                $ws->status        = 0;
                $ws->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data has ben Added',
                'success' => true,
                'status'  => 200,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 401,
            ], 401);
        }
    }

    public function validasiApproveWSApi(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id) {
                $data               = OrderDetail::where('id', $request->id)->first();
                $data->status       = 1;
                $data->keterangan_1 = $request->keterangan_1;
                $data->save();

                HistoryAppReject::insert([
                    'no_lhp'      => $data->cfr,
                    'no_sampel'   => $data->no_sampel,
                    'kategori_2'  => $data->kategori_2,
                    'kategori_3'  => $data->kategori_3,
                    'menu'        => 'WS Final Udara',
                    'status'      => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan,
                ]);

                DB::commit();
                $this->resultx = 'Data hasbeen Approved.!';
                return response()->json([
                    'message' => $this->resultx,
                    'status'  => 200,
                    'success' => true,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data Not Found.!',
                    'status'  => 401,
                    'success' => false,
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function getKaryawan(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)->get();
        return $data;
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = SwabTestHeader::where('no_sampel', $request->no_sampel)->update([
                'is_approved'  => 0,
                'is_active'    => 0,
                'notes_reject' => $request->note,
                'rejected_by'  => $this->karyawan,
                'rejected_at'  => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Data berhasil direject.',
                'success' => true,
                'status'  => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mereject data: ' . $th->getMessage(),
                'success' => false,
                'status'  => 500,
            ], 500);
        }
    }

    public function handleApproveSelected(Request $request)
    {
        DB::beginTransaction();
        try {
            $orderDetails = OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->get();

            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 1]);

            foreach ($orderDetails as $detail) {
                HistoryAppReject::insert([
                    'no_lhp'      => $detail->cfr,
                    'no_sampel'   => $detail->no_sampel,
                    'kategori_2'  => $detail->kategori_2,
                    'kategori_3'  => $detail->kategori_3,
                    'menu'        => 'WS Final Udara',
                    'status'      => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan,
                ]);
            }

            $header = SwabTestHeader::whereIn('no_sampel', $request->no_sampel_list)
                ->update(['lhps' => 1]);

            $header2 = SubKontrak::whereIn('no_sampel', $request->no_sampel_list)
                ->update(['lhps' => 1]);

            \App\Services\WsFinalApprovalService::finalizeSamples($orderDetails, true, $this->karyawan);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil diapprove.',
                'success' => true,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::where('id_kategori', 7)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => $data,
        ], 200);
    }

    public function getTableRegulasi(Request $request)
    {
        $data = DB::table('tabel_regulasi')
            ->whereJsonContains('id_regulasi', (string) $request->id)
            ->first();

        return response()->json([
            'data' => $data,
        ], 200);
    }
}