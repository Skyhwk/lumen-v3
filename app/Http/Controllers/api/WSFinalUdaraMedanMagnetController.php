<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Datatables;
use Carbon\Carbon;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\WsValueUdara;
use App\Models\DataLapanganMedanLM;
use App\Models\MasterKaryawan;
use App\Models\Subkontrak;
use App\Models\MasterBakumutu;

use App\Models\LingkunganHeader;
use App\Models\DirectLainHeader;
use App\Models\ErgonomiHeader;
use App\Models\SinarUvHeader;
use App\Models\MedanLmHeader;
use App\Models\DebuPersonalHeader;

class WSFinalUdaraMedanMagnetController extends Controller
{
    private $categoryLingkunganKerja = [27];
    public function index(Request $request)
    {
        $parameters = [
            "563;Medan Magnet",
            "316;Power Density",
            "277;Medan Listrik",
            "236;Gelombang Elektro",
        ];

        $whereParts = [];
        foreach ($parameters as $p) {
            $whereParts[] = 'JSON_SEARCH(parameter, "one", "'.$p.'") IS NOT NULL';
        }

        $whereRaw = '(' . implode(' OR ', $whereParts) . ')';

        $data = OrderDetail::select(
                DB::raw("MAX(id) as max_id"),
                DB::raw("GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ', ') as tanggal_sampling"),
                DB::raw("GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ', ') as tanggal_terima"),
                'no_order',
                'nama_perusahaan',
                'cfr',
                'kategori_2',
                'kategori_3',
            )
            ->where('is_active', $request->is_active)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', '27-Udara Lingkungan Kerja')
            ->where('status', 0)
            ->whereNotNull('tanggal_terima')
            ->whereRaw($whereRaw)
            ->when($request->date, fn($q) => $q->whereYear('tanggal_sampling', explode('-', $request->date)[0])->whereMonth('tanggal_sampling', explode('-', $request->date)[1]))
            ->groupBy('cfr', 'kategori_2', 'kategori_3', 'nama_perusahaan', 'no_order')
            ->orderBy('tanggal_sampling');

        return Datatables::of($data)
            ->make(true);
    }

    public function getDetailCfr(Request $request)
    {
        $data = OrderDetail::where('cfr', $request->cfr)
            ->orderByDesc('id')
            ->get()
            ->where('status', 0)
            ->map(function ($item) {
                $item->getAnyDataLapanganUdara();
                return $item;
            })->values();

        return response()->json([
            'data' => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }


    public function detail(Request $request)
    {
        try {
            $parameters = json_decode(html_entity_decode($request->parameter), true);
            $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
            $idParameter = isset($parameterArray[0]) ? $parameterArray[0] : null;

            // if (
            //     $parameterArray[1] == 'Medan Magnit Statis' ||
            //     $parameterArray[1] == 'Medan Listrik' ||
            //     $parameterArray[1] == 'Power Density' ||
            //     $parameterArray[1] == 'Gelombang Elektro'
            // ) {
                $data = MedanLmHeader::with('datalapangan')->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', true)
                    ->where('is_active', true)
                    ->select('*')
                    ->addSelect(DB::raw("'medan_lm' as data_type"))
                    ->get();
                $method = Parameter::where('id', $idParameter)->first()->method ?? '-';

                // foreach ($data as $item) {
                //     $waktu = $item->datalapangan->waktu_pemaparan ?? null;

                //     if ($waktu !== null) {
                //         if ($waktu >= 1 && $waktu < 5) {
                //             $item->nab = 0.05;
                //         } elseif ($waktu >= 5 && $waktu < 10) {
                //             $item->nab = 0.01;
                //         } elseif ($waktu >= 10 && $waktu < 15) {
                //             $item->nab = 0.005;
                //         } elseif ($waktu >= 15 && $waktu < 30) {
                //             $item->nab = 0.0033;
                //         } elseif ($waktu >= 30 && $waktu < 60) {
                //             $item->nab = 0.0017;
                //         } elseif ($waktu >= 60 && $waktu < 120) {
                //             $item->nab = 0.0008;
                //         } elseif ($waktu >= 120 && $waktu < 240) {
                //             $item->nab = 0.0004;
                //         } elseif ($waktu >= 240 && $waktu < 480) {
                //             $item->nab = 0.0002;
                //         } elseif ($waktu >= 480) {
                //             $item->nab = 0.0001;
                //         } else {
                //             $item->nab = null;
                //         }
                //     } else {
                //         $item->nab = null;
                //     }

                // }

                // return Datatables::of($data)
                //     ->addColumn('method', function ($item) use ($method) {
                //         return $method;
                //     })->make(true);
            // }

            $subkontrak = Subkontrak::with(['ws_value_linkungan'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                ->addSelect(DB::raw("'subKontrak' as data_type"))
                ->get();

            $combinedData = collect()
                ->merge($data)
                ->merge($subkontrak);


            $processedData = $combinedData->map(function ($item) {
                switch ($item->data_type) {
                    case 'medan_lm':
                        $item->source = 'medan_lm';
                        break;
                }
                return $item;
            });

            $id_regulasi = explode("-", json_decode($request->regulasi)[0])[0];
            foreach ($processedData as $item) {
                $dataLapangan = DataLapanganMedanLM::where('no_sampel', $item->no_sampel)
                    ->select('waktu_pemaparan')
                    ->where('parameter', $item->parameter)
                    ->first();
                $wsUdara = WsValueUdara::where('no_sampel', $item->no_sampel)
                    ->where('id_medan_lm_header', $item->id)
                    ->where('is_active', true)
                    ->first();
                $bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method')
                    ->first();

                $hasil1 = json_decode($wsUdara->hasil1 ?? '{}', true);

                $item->waktu_pemaparan = $dataLapangan->waktu_pemaparan ?? null;
                $item->sumber_radiasi = $dataLapangan->sumber_radiasi ?? null;
                $item->waktu_pengukuran = $dataLapangan->waktu_pengukuran ?? null;
                $item->penamaan_titik = $dataLapangan->keterangan ?? null;
                $item->lokasi = $dataLapangan->lokasi ?? null;
                $item->satuan = $bakuMutu->satuan ?? null;
                $item->baku_mutu = $bakuMutu->baku_mutu ?? null;
                $item->method = $bakuMutu->method ?? null;
                $item->nab = $wsUdara->nab ?? null;
                $item->nab_power_density = $wsUdara->nab_power_density ?? null;
                $item->nab_medan_listrik = $wsUdara->nab_medan_listrik ?? null;
                $item->nab_medan_magnet = $wsUdara->nab_medan_magnet ?? null;
                $item->tindakan = $wsUdara->tindakan ?? null;
                $item->medan_magnet_am = $hasil1['medan_magnet_am'] ?? null;
                $item->hasil_watt       = $hasil1['hasil_watt'] ?? null;
                $item->hasil_mwatt      = $hasil1['hasil_mwatt'] ?? null;
                $item->rata_magnet      = $hasil1['rata_magnet'] ?? $hasil1['medan_magnet'] ?? null;
                $item->rata_listrik     = $hasil1['rata_listrik'] ?? $hasil1['medan_listrik'] ?? null;
                $item->rata_frekuensi   = $hasil1['rata_frekuensi'] ?? null;
            }
            
            return Datatables::of($processedData)->make(true);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 401);
        }
    }

    public function detailLapangan(Request $request)
    {
        $parameterNames = [];
        
        if (is_array($request->parameter)) {
            foreach ($request->parameter as $param) {
                $paramParts = $param;
                if (isset($paramParts[1])) {
                    $parameterNames[] = trim($paramParts);
                }
            }
        }
        if ($request->kategori == 27) {
            try {
                $noOrder = explode('/', $request->no_sampel)[0] ?? null;
                $Lapangan = OrderDetail::where('no_order', $noOrder)
                    ->where(function ($q) use ($parameterNames) {
                        foreach ($parameterNames as $param) {
                            $q->orWhereJsonContains('parameter', $param);
                        }
                    })
                    ->get();
                $lapangan2 = $Lapangan->map(function ($item) {
                    return $item->no_sampel;
                })->unique()->sortBy(function ($item) {
                    return (int) explode('/', $item)[1];
                })->values();
                $totLapangan = $lapangan2->count();

                $parameterNamesClean = array_map(function($p) {
                    return explode(';', $p)[1] ?? $p;
                }, $parameterNames);

                $data = DataLapanganMedanLM::where('no_sampel', $request->no_sampel)->first();
                if ($data) {
                    // hitung urutan
                    $urutan = $lapangan2->search($data->no_sampel);
                    $urutanDisplay = $urutan + 1;
                    $totLapangan = $lapangan2->count();

                    $dataArray = $data->toArray();
                    $dataArray['urutan'] = "{$urutanDisplay}/{$totLapangan}";

                    // Tentukan parameter yang aktif
                    if (in_array('Medan Magnit Statis', $parameterNamesClean)) {
                        $dataArray['parameter'] = 'Medan Magnit Statis';
                    } elseif (in_array('Medan Listrik', $parameterNamesClean)) {
                        $dataArray['parameter'] = 'Medan Listrik';
                    } elseif (in_array('Power Density', $parameterNamesClean)) {
                        $dataArray['parameter'] = 'Power Density';
                    } elseif (in_array('Gelombang Elektro', $parameterNamesClean)) {
                        $dataArray['parameter'] = 'Gelombang Elektro';
                    }

                    return response()->json([
                        'data' => $dataArray,
                        'message' => 'Berhasil mendapatkan data',
                        'success' => true,
                        'status' => 200
                    ]);
                }

            } catch (\Exception $ex) {
                return response()->json([
                    'line' => $ex->getLine(),
                    'message' => 'error ' . $ex->getMessage(),
                ], 500);
            }
        } else {
            $data = [];
        }
    }

    public function rejectAnalys(Request $request)
    {
        try {
            if (in_array($request->kategori, $this->categoryLingkunganKerja)) {
                if ($request->data_type == 'subKontrak') {
                    $data = Subkontrak::where('id', $request->id)->update([
                        'is_approve' => 0,
                        'is_active' => 0,
                        'notes_reject' => $request->note,
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => Carbon::now(),

                    ]);
                }else if ($request->data_type == 'medan_lm') {
                    // Update data for 'direct'
                    $data = MedanLmHeader::where('id', $request->id)->update([
                        'is_approve' => 0,
                        'notes_reject' => $request->note,
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => Carbon::now(),

                    ]);
                } else {
                    // If neither 'lingkungan' nor 'direct', return an error message
                    return response()->json(['message' => 'Invalid data_type provided.'], 400);
                }

                if ($data) {
                    return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
                } else {
                    return response()->json(['message' => 'Gagal', 'success' => false, 'status' => 400]);
                }
            } else {
                $data = [];
            }
        } catch (\Exception $ex) {
            dd($ex);
        }
    }

    public function approveWSApi(Request $request)
    {
        if ($request->id) {

            if (in_array($request->kategori, $this->categoryLingkunganKerja)) {
                if ($request->data_type == 'subKontrak') {

                    $data = Subkontrak::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();


                    if ($data) {
                        $cek = Subkontrak::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status' => 201,
                        ], 201);
                    } else {
                        $dat = Subkontrak::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status' => 200,
                        ], 200);
                    }
                }else if ($request->data_type == 'medan_lm') {
                    $data = MedanLmHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    // dd($data);
                    if ($data) {
                        $cek = MedanLmHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status' => 201,
                        ], 201);
                    } else {
                        $dat = MedanLmHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status' => 200,
                        ], 200);
                    }
                }else {
                    return response()->json([
                        'message' => 'Data Tidak Ditemukan',
                        'status' => 401,
                    ], 401);
                }
            } else {
                $data = [];
            }
        } else {
            return response()->json([
                'message' => 'Gagal Approve',
                'status' => 401,
            ], 401);
        }
    }

    public function AddSubKontrak(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->subCategory == 11 || $request->subCategory == 27) {
                $data = new Subkontrak();
                $data->no_sampel = $request->no_sampel;
                $data->category_id = $request->category;
                $data->parameter = $request->parameter;
                $data->note = $request->keterangan;
                $data->jenis_pengujian = $request->jenis_pengujian;
                $data->is_active = true;
                $data->is_approve = 1;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->created_by = $this->karyawan;
                $data->save();

                $ws = new WsValueLingkungan();
                $ws->no_sampel = $request->no_sampel;
                $ws->id_subkontrak = $data->id;
                $ws->flow = $request->flow;
                $ws->durasi = $request->durasi;
                $ws->C = $request->C;
                $ws->C1 = $request->C1;
                $ws->C2 = $request->C2;
                $ws->is_active = true;
                $ws->status = 0;
                $ws->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data has ben Added',
                'success' => true,
                'status' => 200,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function validasiApproveWSApi(Request $request)
    {
        DB::beginTransaction();
        try {

            if ($request->id) {
                $data = OrderDetail::where('id', $request->id)->first();
                $data->status = 1;
                $data->keterangan_1 = $request->keterangan_1;
                $data->save();

                HistoryAppReject::insert([
                    'no_lhp' => $data->cfr,
                    'no_sampel' => $data->no_sampel,
                    'kategori_2' => $data->kategori_2,
                    'kategori_3' => $data->kategori_3,
                    'menu' => 'WS Final Udara',
                    'status' => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);

                DB::commit();
                $this->resultx = 'Data hasbeen Approved.!';
                return response()->json([
                    'message' => $this->resultx,
                    'status' => 200,
                    'success' => true,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data Not Found.!',
                    'status' => 401,
                    'success' => false,
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function getKaryawan(Request $request)
    {
        return MasterKaryawan::where('is_active', true)->get();
    }

    public function updateTindakan(Request $request)
    {

        try {

            $data = WsValueUdara::where('id', $request->id)->first();
            $data->tindakan = $request->tindakan;
            $data->save();

            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 401
            ], 401);
        }
    }
    public function updateBagianTubuh(Request $request)
    {
        try {
            $data = MedanLmHeader::where('id', $request->id)->first();
            $data->bagian_tubuh = $request->bag_tubuh;
            $data->save();

            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function updateNab(Request $request)
    {
        try {

            $ws = WsValueUdara::where('id', $request->id)->first();

            if (!empty($request->nab)) {
                $ws->nab = $request->nab;
            }

            if (!empty($request->nab_power_density)) {
                $ws->nab_power_density = $request->nab_power_density;
            }

            if (!empty($request->nab_medan_listrik)) {
                $ws->nab_medan_listrik = $request->nab_medan_listrik;
            }

            if (!empty($request->nab_medan_magnet)) {
                $ws->nab_medan_magnet = $request->nab_medan_magnet;
            }
            $ws->save();

            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            DataLapanganMedanLM::where('no_sampel', $request->no_sampel)->update([
                'is_approve' => 0,
            ]);

            MedanLmHeader::where('no_sampel', $request->no_sampel)
                ->update([
                    'is_approve' => 0,
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

    public function handleApproveAll(Request $request)
    {
        DB::beginTransaction();
        try {
           OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 1,
                ]);

            MedanLmHeader::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'lhps' => 1,
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
}
