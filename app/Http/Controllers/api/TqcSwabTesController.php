<?php
namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Models\DetailLingkunganKerja;
use App\Models\HistoryAppReject;
use App\Models\MasterBakumutu;
use App\Models\MicrobioHeader;
use App\Models\OrderDetail;
use App\Models\SwabTestHeader;
use App\Models\SubKontrak;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class TqcSwabTesController extends Controller
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
            GROUP_CONCAT(DISTINCT kategori_1 SEPARATOR ",") as kategori_1,
            GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") as tanggal_sampling,
            GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ",") as tanggal_terima,
            MIN(tanggal_terima) as tanggal_terima_min')
            ->with([
                'lhps_swab_udara',
                'orderHeader:id,nama_pic_order,jabatan_pic_order,no_pic_order,email_pic_order,alamat_sampling',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '46-Udara Swab Test')
            ->where('status', 1)
            ->groupBy('cfr')
            ->orderBy('tanggal_terima_min')
            ->get();

        return Datatables::of($data)->make(true);
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
                    'menu'        => 'TQC Swab Tes',
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

    public function getTrend(Request $request)
    {
        $orderDetails = OrderDetail::where('cfr', $request->cfr)
            ->where('status', 1)
            ->where('is_active', 1)
            ->get();

        $data      = [];
        $getSatuan = new HelperSatuan;

        foreach ($orderDetails as $orderDetail) {
            $header = SwabTestHeader::with('ws_udara')
                ->where('no_sampel', $orderDetail->no_sampel)
                ->get();

            if ($header->isEmpty()) {
                $header = MicrobioHeader::with('ws_udara')
                    ->where('no_sampel', $orderDetail->no_sampel)
                    ->get();
            }

            $header2 = SubKontrak::with(['ws_value_linkungan', 'ws_udara'])
                ->where('no_sampel', $orderDetail->no_sampel)
                ->where('is_active', 1)
                ->get();

            $merge = $header->merge($header2);

            // 2. Ambil id_regulasi dari field regulasi di OrderDetail
            $id_regulasi = null;

            if (! empty($orderDetail->regulasi)) {
                $regArr = json_decode($orderDetail->regulasi, true);

                if (is_array($regArr) && count($regArr) > 0) {
                    $first       = $regArr[0];              // "143-Peraturan ...."
                    $parts       = explode('-', $first, 2); // ["143", "Peraturan ...."]
                    $id_regulasi = (int) $parts[0];
                }
            }

            foreach ($merge as $item) {
                $parameter = Parameter::where('nama_lab', $item->parameter)
                    ->where('is_active', 1)
                    ->first();
                $bakuMutu = MasterBakumutu::where("id_parameter", $parameter->id)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method', 'nama_header')
                    ->first();

                $ws = $item->ws_udara ?? $item->ws_value_linkungan ?? null;

                $nilai = '-';

                if ($ws) {
                    $hasil = $ws->toArray();

                    $index = $getSatuan->udara($bakuMutu->satuan ?? null);

                    if ($index === null) {
                        // cari f_koreksi_1..17 dulu
                        for ($i = 1; $i <= 17; $i++) {
                            $key = "f_koreksi_$i";
                            if (isset($hasil[$key]) && $hasil[$key] !== '' && $hasil[$key] !== null) {
                                $nilai = $hasil[$key];
                                break;
                            }
                        }

                        // kalau masih kosong, cari hasil1..17
                        if ($nilai === '-' || $nilai === null || $nilai === '') {
                            for ($i = 1; $i <= 17; $i++) {
                                $key = "hasil$i";
                                if (isset($hasil[$key]) && $hasil[$key] !== '' && $hasil[$key] !== null) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }
                    } else {
                        $fKoreksiHasil = "f_koreksi_$index";
                        $fhasil        = "hasil$index";

                        if (isset($hasil[$fKoreksiHasil]) && $hasil[$fKoreksiHasil] !== '' && $hasil[$fKoreksiHasil] !== null) {
                            $nilai = $hasil[$fKoreksiHasil];
                        } elseif (isset($hasil[$fhasil]) && $hasil[$fhasil] !== '' && $hasil[$fhasil] !== null) {
                            $nilai = $hasil[$fhasil];
                        }
                    }
                }

                $data[] = [
                    'no_sampel'   => $orderDetail->no_sampel,
                    'parameter'   => $item->parameter,
                    'baku_mutu'   => $bakuMutu->baku_mutu ?? null,
                    'satuan'      => $bakuMutu->satuan ?? null,
                    'method'      => $bakuMutu->method ?? null,
                    'nama_header' => $bakuMutu->nama_header ?? null,
                    'nilai_uji'   => $nilai,
                    'verifikator' => $item->approved_by ?? null,
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil mendapatkan data',
            'data'    => $data,
        ]);
    }
    public function detailLapangan(Request $request)
    {
        try {
            $data = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->first();
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

    public function handleRejectSelected(Request $request)
    {
        DB::beginTransaction();
        try {

            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 0,
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
}
