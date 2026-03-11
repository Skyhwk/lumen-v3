<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\LhpsMedanLMDetail;
use App\Models\LhpsMedanLMHeader;
use App\Models\OrderDetail;
use App\Models\WsValueUdara;

use App\Models\MedanLmHeader;
use App\Models\Subkontrak;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\LhpsKebisinganDetail;
use App\Models\LhpsKebisinganHeader;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TqcMedanMagnetController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::select(
            'cfr',
            DB::raw('MAX(id) as max_id'),
            'nama_perusahaan',
            'no_quotation',
            'no_order',
            DB::raw('GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ", ") as no_sampel'),
            DB::raw('GROUP_CONCAT(DISTINCT kategori_3 SEPARATOR ", ") as kategori_3'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ", ") as tanggal_sampling'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ", ") as tanggal_terima'),
            'kategori_1',
            'konsultan',
            'parameter'
        )
            ->where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', '27-Udara Lingkungan Kerja');

        $data->where(function ($q) {
            $q->whereJsonContains('parameter', "563;Medan Magnet")
                ->orWhereJsonContains('parameter', "316;Power Density")
                ->orWhereJsonContains('parameter', "277;Medan Listrik")
                ->orWhereJsonContains('parameter', "236;Gelombang Elektro");
        });


        $data->groupBy('cfr', 'nama_perusahaan', 'no_quotation', 'no_order', 'kategori_1', 'konsultan','parameter')
            ->orderBy('tanggal_terima');

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
                    'no_lhp' => $data->cfr,
                    'no_sampel' => $data->no_sampel,
                    'kategori_2' => $data->kategori_2,
                    'kategori_3' => $data->kategori_3,
                    'menu' => 'TQC Udara',
                    'status' => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data tqc no sample ' . $data->no_sampel . ' berhasil diapprove'
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }
    public function rejectData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('cfr', $request->cfr)->where('status', 1)->update([
                'status' => 0
            ]);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data tqc no lhp ' . $request->cfr . ' berhasil direject'
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    public function detail(Request $request)
    {
        try {

            if ($request->kategori == 11 || $request->kategori == 27) {
                $medanlmData = MedanLmHeader::with(['ws_udara'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                    ->addSelect(DB::raw("'medanlm' as data_type"))
                    ->get();

                $subkontrak = Subkontrak::with(['ws_value_linkungan'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'is_active')
                    ->addSelect(DB::raw("'subkontrak' as data_type"))
                    ->get();

                $combinedData = $medanlmData->merge($subkontrak);
                
                $processedData = $combinedData->map(function ($item) {
                    $item->source = $item->data_type === 'medanlm' ? 'Medan LM' : 'Subkontrak';
                    if($item->source == 'Medan LM') $item->ws_udara->hasil1 = json_decode($item->ws_udara->hasil1); 
                    return $item;
                });

                return Datatables::of($processedData)->make(true);
            } else if ($request->kategori == 23 || $request->kategori == 24 || $request->kategori == 25 || $request->kategori == 26) {
                $data = KebisinganHeader::with(['ws_udara'])->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', 1)
                    ->where('status', 0);

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 28) {
                $data = PencahayaanHeader::with(['data_lapangan', 'ws_udara'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', 1)
                    ->where('status', 0);

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 21) {
                $data = IklimHeader::with(['iklim_panas', 'iklim_dingin', 'ws_udara'])->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->where('status', 0);

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 17 || $request->kategori == 19 || $request->kategori == 20 || $request->kategori == 14 || $request->kategori == 15 || $request->kategori == 18 || $request->kategori == 13 || $request->kategori == 16) {

                $data = GetaranHeader::with(['lapangan_getaran', 'lapangan_getaran_personal', 'ws_udara'])->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->where('status', 0);

                return Datatables::of($data)->make(true);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function detailPsikologi(Request $request)
    {
        $data = PsikologiHeader::with('data_lapangan')
            ->where('no_sampel', $request->no_sampel)
            ->where('is_approve', true)
            ->where('is_active', true)
            ->select('*')
            ->addSelect(DB::raw("'psikologi' as data_type"))
            ->first();
        $data->data_lapangan->hasil = json_decode($data->data_lapangan->hasil);
        return response()->json($data, 200);
    }

    public function detailLapangan(Request $request)
    {
        if ($request->kategori == 11) {
            try {
                $data = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->first();
                if ($data) {
                    return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else if ($request->kategori == 27) {
            try {
                $data = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->first();
                if ($data) {
                    return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else if ($request->kategori == 23 || $request->kategori == 24 || $request->kategori == 25 || $request->kategori == 26) {
            try {
                $data = DataLapanganKebisingan::where('no_sampel', $request->no_sampel)->first();
                if ($data) {
                    return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else if ($request->kategori == 28) {
            try {
                $data = DataLapanganCahaya::where('no_sampel', $request->no_sampel)->first();
                if ($data) {
                    return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else if ($request->kategori == 21) {
            try {
                $data = IklimHeader::where('no_sampel', $request->no_sampel)->where('parameter', 'like', '%ISBB%')
                    ->first();

                if ($data) {
                    $data2 = DataLapanganIklimPanas::where('no_sampel', $request->no_sampel)->first();
                    if ($data2) {

                        return response()->json(['data' => $data2, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                    }
                } else {
                    $data2 = DataLapanganIklimDingin::where('no_sampel', $request->no_sampel)->first();
                    if ($data2) {

                        return response()->json(['data' => $data2, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                    }
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else if ($request->kategori == 17 || $request->kategori == 19 || $request->kategori == 20 || $request->kategori == 14 || $request->kategori == 15 || $request->kategori == 18 || $request->kategori == 13 || $request->kategori == 16) {
            $detailOrder = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', 1)->first();
            if (str_contains($detailOrder->parameter, 'Getaran (LK) TL') || str_contains($detailOrder->parameter, "Getaran (LK) ST")) {
                try {
                    $data = DataLapanganGetaranPersonal::where('no_sampel', $request->no_sampel)->first();

                    if ($data) {
                        return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                    }
                } catch (\Exception $ex) {
                    dd($ex);
                }
            } else {
                try {
                    $data = DataLapanganGetaran::where('no_sampel', $request->no_sampel)->first();

                    if ($data) {
                        return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                    }
                } catch (\Exception $ex) {
                    dd($ex);
                }
            }
        } else {
            $data = [];
        }
    }

    public function getTrend(Request $request)
    {
        $orderDetails = OrderDetail::where('cfr', $request->cfr)
            ->where('status', 1)
            ->where('is_active', 1)
            ->get();

        $lhpsMedanLmHeader = LhpsMedanLMHeader::where('no_lhp', $request->cfr)->first();
        $data = [];
        foreach ($orderDetails as $orderDetail) {

            $lhpsMedanLmHeader = LhpsMedanLMHeader::where('nama_pelanggan', $orderDetail->nama_perusahaan)->first();
            $lhpsMedanLmDetail = LhpsMedanLMDetail::where('lokasi_keterangan', $orderDetail->keterangan_1)
                ->pluck('hasil')
                ->toArray();

            $ws = WsValueUdara::where('no_sampel', $orderDetail->no_sampel)->orderByDesc('id')->first();
            $header = MedanLmHeader::where('no_sampel', $orderDetail->no_sampel)->first();
            $hasilWs = json_decode($ws->hasil1, true);
            $data[] = [
                'no_sampel' => $orderDetail->no_sampel,
                'titik' => $orderDetail->keterangan_1,
                'history' => $lhpsMedanLmDetail,
                'parameter' => $header->parameter ?? null,
                'nab' => $ws->nab ?? null,
                'hasil_mwatt' => $hasilWs['hasil_mwatt'] ?? null,
                'rata_magnet' => $hasilWs['medan_magnet_am'] ?? $hasilWs['rata_magnet'] ?? $hasilWs['medan_magnet'] ?? null,
                'rata_listrik' => $hasilWs['rata_listrik'] ?? $hasilWs['medan_listrik'] ?? null,
                'rata_frekuensi' => $hasilWs['rata_frekuensi'] ?? null,
                'nab_power_density' => $ws->nab_power_density ?? null,
                'nab_medan_listrik' => $ws->nab_medan_listrik ?? null,
                'nab_medan_magnet' => $ws->nab_medan_magnet ?? null,
                'analyst' => optional($lhpsMedanLmHeader)->created_by,
                'approved_by' => optional($lhpsMedanLmHeader)->approved_by
            ];
        }

        return response()->json([
            'data' => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
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
                'status' => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
                'success' => false,
                'status' => 500,
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
                'status' => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mereject data: ' . $th->getMessage(),
                'success' => false,
                'status' => 500,
            ], 500);
        }
    }
}