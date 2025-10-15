<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganEmisiKendaraan;
use App\Models\DataLapanganEmisiOrder;
use App\Models\MasterQr;
use App\Models\MasterKendaraan;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\MasterRegulasi;
use App\Models\MasterBakumutu;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FdlEmisiKendaraanController extends Controller
{
    public function detail(Request $request)
    {
        if (isset($request->qr) && $request->qr != null) {
            $data = MasterQr::where('kode', $request->qr)->where('is_active', true)->first();
            if ($data != null) {
                if ($data->id_kendaraan != null && $data->status == 1) {
                    $order = DataLapanganEmisiOrder::where('id_qr', $data->id)->get();
                    $kendaraan = MasterKendaraan::where('id', $data->id_kendaraan)->first();
                    $jumlah = count($order);
                    foreach ($order as $key => $value) {
                        $cek_fdl = DataLapanganEmisiKendaraan::where('no_sampel', $value->no_sampel)->first();

                        $cek_bakumutu = MasterBakumutu::where('id_regulasi', $value->id_regulasi)->where('is_active', true)->get();

                        $status = 'Parameter Tidak di uji';
                        $status_co = 'Parameter Tidak di uji';
                        $status_hc = 'Parameter Tidak di uji';
                        foreach ($cek_bakumutu as $keys => $val) {
                            if ($val->parameter == 'CO' || $val->parameter == 'CO (Bensin)') {
                                if ($cek_fdl->co <= $val->baku_mutu) {
                                    $status_co = 'Memenuhi Baku Mutu';
                                } else {
                                    $status_co = 'Tidak Memenuhi Baku Mutu';
                                }
                            } else if ($val->parameter == 'HC' || $val->parameter == 'HC (Bensin)') {
                                if ($cek_fdl->hc <= $val->baku_mutu) {
                                    $status_hc = 'Memenuhi Baku Mutu';
                                } else {
                                    $status_hc = 'Tidak Memenuhi Baku Mutu';
                                }
                            } else if ($val->parameter == 'Opasitas' || $val->parameter == 'Opasitas (Solar)') {
                                if ($cek_fdl->opasitas <= $val->baku_mutu) {
                                    $status = 'Memenuhi Baku Mutu';
                                } else {
                                    $status = 'Tidak Memenuhi Baku Mutu';
                                }
                            }
                        }

                        $datas[$key]['tgl_uji'] = DATE('Y-m-d', strtotime($cek_fdl->created_at));
                        $datas[$key]['merk_kendaraan'] = $kendaraan->merk_kendaraan;
                        $datas[$key]['transmisi'] = $kendaraan->transmisi;
                        $datas[$key]['tahun_pembuatan'] = $kendaraan->tahun_pembuatan;
                        $datas[$key]['no_polisi'] = $kendaraan->plat_nomor;
                        $datas[$key]['no_mesin'] = $kendaraan->no_mesin;
                        $datas[$key]['bahan_bakar'] = $kendaraan->jenis_bbm;
                        $datas[$key]['kapasitas_cc'] = $kendaraan->cc . ' CC';
                        $datas[$key]['co'] = $cek_fdl->co;
                        $datas[$key]['hc'] = $cek_fdl->hc;
                        $datas[$key]['opasitas'] = $cek_fdl->opasitas;
                    }
                    return response()->json([
                        'record' => $jumlah,
                        'data' => $datas,
                        'message' => 'Data has ben Show'
                    ], 201);
                } else {
                    $this->resultx = 'Qr Available';
                    return response()->json([
                        'record' => 0,
                        'data' => [],
                        'message' => $this->resultx
                    ], 201);
                }
            } else {
                $this->resultx = 'Qr Code tidak diterbitkan oleh INTILAB';
                return response()->json([
                    'message' => $this->resultx
                ], 401);
            }
        } else {
            $this->resultx = 'Pastikan Qr Code Terbaca / Terisi';
            return response()->json([
                'message' => $this->resultx
            ], 401);
        }
    }

    public function cekQr(Request $request)
    {
        if (isset($request->qr) && $request->qr != null) {
            $data = MasterQr::where('kode', $request->qr)->where('is_active', true)->first();
            if ($data != null) {
                if ($data->id_kendaraan != null && $data->status == 1) {
                    $order = DataLapanganEmisiOrder::where('id_qr', $data->id)->where('is_active', true)->get();
                    $kendaraan = MasterKendaraan::where('id', $data->id_kendaraan)->where('is_active', true)->first();
                    $jumlah = count($order);
                    return response()->json([
                        'record' => $jumlah,
                        'id_kendaraan' => $data->id_kendaraan,
                        'order_id' => $order[0]->id ?? null,
                        'id_qr' => $data->id,
                        'bbm' => $kendaraan->id_bbm,
                        'jenis_bbm' => $kendaraan->jenis_bbm,
                        'plat' => $kendaraan->plat_nomor,
                        'no_mesin' => $kendaraan->no_mesin,
                        'merk' => $kendaraan->merk_kendaraan,
                        'transmisi' => $kendaraan->transmisi,
                        'tahun' => $kendaraan->tahun_pembuatan,
                        'cc' => $kendaraan->cc,
                        'km' => $kendaraan->km,
                        'kategori' => $kendaraan->kategori_kendaraan,
                        'bobot' => $kendaraan->bobot_kendaraan,
                        'message' => 'Qr Available'
                    ], 201);
                } else {
                    return response()->json([
                        'record' => 0,
                        'id_qr' => $data->id,
                        'message' => 'Qr Available'
                    ], 201);
                }
            } else {
                return response()->json([
                    'message' => 'Qr Code tidak diterbitkan oleh INTILAB'
                ], 401);
            }
        } else if (isset($request->no_sampel) && $request->no_sampel != null) {
            $po_s = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            return response()->json([
                'client' => $po_s->nama_perusahaan,
                'kategori_3' => explode('-', $po_s->kategori_3)[0],
                'message' => 'Client berhasil didapatkan'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Pastikan Qr Code Terbaca'
            ], 401);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if (isset($request->kode_qr) && $request->kode_qr != null) {
                
                $fdlKendaraan = DataLapanganEmisiKendaraan::where('no_sampel', $request->no_sampel)->where('is_active',true)->first();
                if($fdlKendaraan){
                    return response()->json([
                        'message'=>'No Sampel sudah terinput'
                    ],401);
                }
                $id_regulasi = explode('-',  $request->regulasi[0])[0];
                $cek_qr = MasterQr::where('kode', $request->kode_qr)->first();
                if ($cek_qr->id_kendaraan != null) {
                    $kendaraan = MasterKendaraan::where('id', $cek_qr->id_kendaraan);
                    $cek_po = OrderDetail::where('kategori_2', '5-Emisi')->whereIn('kategori_3', array('31-Emsisi Kendaraan (Bensin)', '32-Emisi Kendaraan (Solar)'))->orderBy('id', 'DESC')->first();
                    $no_sequen = 'EMISI' . $cek_po->id;
                    $array1 = ["Co", "HC"];
                    $array2 = ["Opasitas (Solar)"];
                    $keterangan = $request->merk . ', ' . $request->tahun;

                    $cek_po = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    if ($cek_po != null) {
                        $co2 = NULL;
                        $co = NULL;
                        $hc = NULL;
                        $o2 = NULL;
                        $opasitas = NULL;
                        $nilai_k = NULL;
                        $rpm = NULL;
                        $oli = NULL;
                        $data_co2 = NULL;
                        $data_co = NULL;
                        $data_hc = NULL;
                        $data_o2 = NULL;
                        $data_opasitas = NULL;
                        if ($request->jenis_kendaraan == 31) {
                            if ($request->co2[0] != NULL && $request->co2[1] != NULL && $request->co2[2] != NULL) {
                                $co2 = \str_replace(",", "", number_format(array_sum($request->co2) / 3, 2));
                                $data_co2 = json_encode($request->co2);
                            }
                            if ($request->co[0] != NULL && $request->co[1] != NULL && $request->co[2] != NULL) {
                                $co  = \str_replace(",", "", number_format(array_sum($request->co) / 3, 2));
                                $data_co = json_encode($request->co);
                            }
                            if ($request->hc[0] != NULL && $request->hc[1] != NULL && $request->hc[2] != NULL) {
                                $hc  = \str_replace(",", "", number_format(array_sum($request->hc) / 3, 2));
                                $data_hc = json_encode($request->hc);
                            }
                            if ($request->o2[0] != NULL && $request->o2[1] != NULL && $request->o2[2] != NULL) {
                                $o2  =  \str_replace(",", "", number_format(array_sum($request->o2) / 3, 2));
                                $data_o2 = json_encode($request->o2);
                            }
                        } else if ($request->jenis_kendaraan == 32) {
                            if ($request->opasitas[0] != NULL && $request->opasitas[1] != NULL && $request->opasitas[2] != NULL) {
                                $opasitas  =  \str_replace(",", "", number_format(array_sum($request->opasitas) / 3, 2));
                                $data_opasitas = json_encode($request->opasitas);
                            }
                            if ($request->nilai_k[0] != NULL && $request->nilai_k[1] != NULL && $request->nilai_k[2] != NULL) $nilai_k  =  \str_replace(",", "", number_format(array_sum($request->nilai_k) / 3, 2));
                            if ($request->rpm[0] != NULL && $request->rpm[1] != NULL && $request->rpm[2] != NULL) $rpm  =  \str_replace(",", "", number_format(array_sum($request->rpm) / 3, 2));
                            if ($request->oli[0] != NULL && $request->oli[1] != NULL && $request->oli[2] != NULL) $oli  =  \str_replace(",", "", number_format(array_sum($request->oli) / 3, 2));
                        }
                        $data_fdl = new DataLapanganEmisiKendaraan;
                        // $data_fdl->id_po 	= $cek_po->id;
                        $data_fdl->no_sampel = strtoupper($request->no_sampel);
                        $data_fdl->data_co     = $data_co;
                        $data_fdl->data_co2     = $data_co2;
                        $data_fdl->data_hc     = $data_hc;
                        $data_fdl->data_o2     = $data_o2;
                        $data_fdl->data_opasitas     = $data_opasitas;
                        $data_fdl->km        = $request->km;
                        $data_fdl->co2        = $co2;
                        $data_fdl->co        = $co;
                        $data_fdl->hc        = $hc;
                        $data_fdl->o2        = $o2;
                        $data_fdl->lamda    = $request->lamda;
                        $data_fdl->opasitas = $opasitas;
                        $data_fdl->nilai_km = $nilai_k;
                        $data_fdl->rpm        = $rpm;
                        $data_fdl->suhu_oli = $oli;
                        if ($request->foto_lok != '')
                            $data_fdl->foto_depan = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '')
                            $data_fdl->foto_belakang = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '')
                            $data_fdl->foto_sampling = self::convertImg($request->foto_lain, 3, $this->user_id);
                        $data_fdl->created_by    = $this->karyawan;
                        $data_fdl->created_at    = Carbon::now()->format('Y-m-d H:i:s');
                        $data_fdl->save();

                        $data_order = new DataLapanganEmisiOrder;
                        // $data_order->id_po			= $cek_po->id;
                        $data_order->no_sampel            = strtoupper($request->no_sampel);
                        $data_order->id_qr            = $cek_qr->id;
                        $data_order->id_fdl            = $data_fdl->id;
                        $data_order->id_kendaraan    = $cek_qr->id_kendaraan;
                        $data_order->id_regulasi    = $id_regulasi;
                        $data_order->created_by            = $this->karyawan;
                        $data_order->created_at            = Carbon::now()->format('Y-m-d H:i:s');
                        $data_order->save();

                        DB::table('order_detail')
                            ->where('no_sampel', strtoupper(trim($request->no_sampel)))
                            ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                        DB::commit();
                        return response()->json([
                            'status' => 21,
                            'success' => true,
                            'message' => 'Data Emisi Add Succesfully'
                        ], 201);
                    } else {
                        return response()->json([
                            'message' => 'No Sample Not Exist.!'
                        ], 401);
                    }
                } else {
                    $cek_po = OrderDetail::where('kategori_2', '5-Emisi')->whereIn('kategori_3', array('31-Emsisi Kendaraan (Bensin)', '32-Emisi Kendaraan (Solar)'))->orderBy('id', 'DESC')->first();
                    $no_sequen = 'EMISI' . $cek_po->id;
                    $array1 = ["Co", "HC"];
                    $array2 = ["Opasitas (Solar)"];
                    $keterangan = $request->merk . ', ' . $request->tahun;

                    $cek_po = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    if ($cek_po != null) {
                        $co2 = NULL;
                        $co = NULL;
                        $hc = NULL;
                        $o2 = NULL;
                        $opasitas = NULL;
                        $nilai_k = NULL;
                        $rpm = NULL;
                        $oli = NULL;
                        $data_co2 = NULL;
                        $data_co = NULL;
                        $data_hc = NULL;
                        $data_o2 = NULL;
                        $data_opasitas = NULL;
                        if ($request->jenis_kendaraan == 31) {
                            if ($request->co2[0] != NULL && $request->co2[1] != NULL && $request->co2[2] != NULL) {
                                $co2 = \str_replace(",", "", number_format(array_sum($request->co2) / 3, 2));
                                $data_co2 = json_encode($request->co2);
                            }
                            if ($request->co[0] != NULL && $request->co[1] != NULL && $request->co[2] != NULL) {
                                $co  = \str_replace(",", "", number_format(array_sum($request->co) / 3, 2));
                                $data_co = json_encode($request->co);
                            }
                            if ($request->hc[0] != NULL && $request->hc[1] != NULL && $request->hc[2] != NULL) {
                                $hc  = \str_replace(",", "", number_format(array_sum($request->hc) / 3, 2));
                                $data_hc = json_encode($request->hc);
                            }
                            if ($request->o2[0] != NULL && $request->o2[1] != NULL && $request->o2[2] != NULL) {
                                $o2  =  \str_replace(",", "", number_format(array_sum($request->o2) / 3, 2));
                                $data_o2 = json_encode($request->o2);
                            }
                        } else if ($request->jenis_kendaraan == 32) {
                            if ($request->opasitas[0] != NULL && $request->opasitas[1] != NULL && $request->opasitas[2] != NULL) {
                                $opasitas  =  \str_replace(",", "", number_format(array_sum($request->opasitas) / 3, 2));
                                $data_opasitas = json_encode($request->opasitas);
                            }
                            if ($request->nilai_k[0] != NULL && $request->nilai_k[1] != NULL && $request->nilai_k[2] != NULL) $nilai_k  =  \str_replace(",", "", number_format(array_sum($request->nilai_k) / 3, 2));
                            if ($request->rpm[0] != NULL && $request->rpm[1] != NULL && $request->rpm[2] != NULL) $rpm  =  \str_replace(",", "", number_format(array_sum($request->rpm) / 3, 2));
                            if ($request->oli[0] != NULL && $request->oli[1] != NULL && $request->oli[2] != NULL) $oli  =  \str_replace(",", "", number_format(array_sum($request->oli) / 3, 2));
                        }

                        $kendaraan = MasterKendaraan::where('id', $cek_qr->id_kendaraan)->first();
                        if (!isset($kendaraan->id_kendaraan) || $kendaraan->id_kendaraan == null) {
                            $data_kendaraan = new MasterKendaraan;
                            $data_kendaraan->merk_kendaraan     = ucfirst($request->merk);
                            $data_kendaraan->id_bbm        = $request->jenis_kendaraan;
                            if ($request->jenis_kendaraan == 31) $data_kendaraan->jenis_bbm     = "Bensin";
                            if ($request->jenis_kendaraan == 32) $data_kendaraan->jenis_bbm     = "Solar";
                            $data_kendaraan->plat_nomor         = $request->no_plat;
                            $data_kendaraan->bobot_kendaraan    = $request->bobot;
                            $data_kendaraan->tahun_pembuatan    = $request->tahun;
                            $data_kendaraan->no_mesin            = $request->no_mesin;
                            $data_kendaraan->transmisi            = $request->transmisi;
                            $data_kendaraan->kategori_kendaraan    = $request->kategori_kendaraan;
                            $data_kendaraan->km                 = $request->km;
                            $data_kendaraan->cc                 = $request->cc;
                            $data_kendaraan->created_by                = $this->karyawan;
                            $data_kendaraan->created_at             = Carbon::now()->format('Y-m-d H:i:s');
                            $data_kendaraan->save();

                            $qr = MasterQr::where('kode', $request->kode_qr)->first();
                            $qr->status = 1;
                            $qr->id_kendaraan = $data_kendaraan->id;
                            $qr->save();
                        } else {
                            $qr = MasterQr::where('kode', $request->kode_qr)->first();
                            $qr->status = 1;
                            $qr->save();
                        }
                        // if($co!=null && $co < 0.02) $co = "<0.02";
                        // if($co2!=null && $co2 < 0.10) $co2 = "<0.10";

                        $data_fdl = new DataLapanganEmisiKendaraan;
                        // $data_fdl->id_po 	= $cek_po->id;
                        $data_fdl->no_sampel = strtoupper($request->no_sampel);
                        $data_fdl->data_co     = $data_co;
                        $data_fdl->data_co2     = $data_co2;
                        $data_fdl->data_hc     = $data_hc;
                        $data_fdl->data_o2     = $data_o2;
                        $data_fdl->data_opasitas     = $data_opasitas;
                        $data_fdl->km        = $request->km;
                        $data_fdl->co2        = $co2;
                        $data_fdl->co        = $co;
                        $data_fdl->hc        = $hc;
                        $data_fdl->o2        = $o2;
                        $data_fdl->lamda    = $request->lamda;
                        $data_fdl->opasitas = $opasitas;
                        $data_fdl->nilai_km = $nilai_k;
                        $data_fdl->rpm        = $rpm;
                        $data_fdl->suhu_oli = $oli;
                        if ($request->foto_lok != '')
                            $data_fdl->foto_depan = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '')
                            $data_fdl->foto_belakang = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '')
                            $data_fdl->foto_sampling = self::convertImg($request->foto_lain, 3, $this->user_id);
                        $data_fdl->created_by    = $this->karyawan;
                        $data_fdl->created_at    = Carbon::now()->format('Y-m-d H:i:s');
                        $data_fdl->save();

                        $data_order = new DataLapanganEmisiOrder;
                        // $data_order->id_po			= $cek_po->id;
                        $data_order->no_sampel            = strtoupper($request->no_sampel);
                        $data_order->id_qr            = $cek_qr->id;
                        $data_order->id_fdl            = $data_fdl->id;
                        $data_order->id_kendaraan    = $data_kendaraan->id;
                        $data_order->id_regulasi    = $id_regulasi;
                        $data_order->created_by            = $this->karyawan;
                        $data_order->created_at            = Carbon::now()->format('Y-m-d H:i:s');
                        $data_order->save();


                        $update_order = OrderDetail::where('no_sampel', strtoupper($request->no_sampel))->where('is_active', 1)->update([
                            'tanggal_terima' => Carbon::now()->format('Y-m-d'),
                        ]);
                        DB::commit();
                        return response()->json([
                            'status' => 201,
                            'success' => true,
                            'message' => 'Data Emisi Add Succesfully'
                        ], 201);
                    } else {
                        return response()->json([
                            'message' => 'No Sample Not Exist.!'
                        ], 401);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Qr Code Not Found.!'
                ], 401);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    public function index(Request $request)
    {
        // $data = DataLapanganEmisiKendaraan::withAppsEmissions($request->is_active, $this->karyawan)->get();
        // // return Datatables::of($data)->make(true);
        // return response()->json(['data' => $data], 200);
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganEmisiKendaraan::withAppsEmissions(1, $this->karyawan);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sampel', 'like', "%$search%")
                    ->orWhereHas('detail', function ($q2) use ($search) {
                        $q2->where('nama_perusahaan', 'like', "%$search%");
                    });
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }

    public function showDetailEmisi(Request $request)
    {
        $key = $request->header('key');
        if (isset($key) && $key == 'eb928269046b298bc2223eb1bacd797b') { //md2 (intisuryalaboratorium)
            if (isset($request->qr) && $request->qr != null) {
                $data = MasterQr::where('kode', $request->qr)->where('is_active', true)->first();
                if ($data != null) {
                    if ($data->id_kendaraan != null && $data->status == 1) {
                        $order = DataLapanganEmisiOrder::where('id_qr', $data->id)->where('is_active', true)->get();
                        $kendaraan = MasterKendaraan::where('id', $data->id_kendaraan)->where('is_active', true)->first();
                        $jumlah = count($order);
                        foreach ($order as $key => $value) {
                            $cek_fdl = DataLapanganEmisiKendaraan::where('no_sampel', $value->no_sampel)->where('is_active', true)->first();
                            $cek_bakumutu = MasterBakumutu::where('id_regulasi', $value->id_regulasi)->where('is_active', true)->get();
                            $regulasi = MasterRegulasi::where('id', $value->id_regulasi)->where('is_active', true)->first();
                            $status = 'Parameter Tidak di uji';
                            $status_co = 'Parameter Tidak di uji';
                            $status_hc = 'Parameter Tidak di uji';
                            foreach ($cek_bakumutu as $keys => $val) {
                                if ($val->parameter == 'CO' || $val->parameter == 'CO (Bensin)') {
                                    if ($cek_fdl->co <= $val->baku_mutu) {
                                        $status_co = 'Memenuhi Baku Mutu';
                                    } else {
                                        $status_co = 'Tidak Memenuhi Baku Mutu';
                                    }
                                } else if ($val->parameter == 'HC' || $val->parameter == 'HC (Bensin)') {
                                    if ($cek_fdl->hc <= $val->baku_mutu) {
                                        $status_hc = 'Memenuhi Baku Mutu';
                                    } else {
                                        $status_hc = 'Tidak Memenuhi Baku Mutu';
                                    }
                                } else if ($val->parameter == 'Opasitas' || $val->parameter == 'Opasitas (Solar)') {
                                    if ($cek_fdl->opasitas <= $val->baku_mutu) {
                                        $status = 'Memenuhi Baku Mutu';
                                    } else {
                                        $status = 'Tidak Memenuhi Baku Mutu';
                                    }
                                }
                            }
                            $datas[$key]['tgl_uji'] = DATE('Y-m-d', strtotime($cek_fdl->created_at));
                            $datas[$key]['parameters'][0]['parameter'] = "CO";
                            $datas[$key]['parameters'][0]['hasil'] = $cek_fdl->co . ' %';
                            $datas[$key]['parameters'][0]['status'] =  $status_co;
                            $datas[$key]['parameters'][1]['parameter'] = "HC";
                            $datas[$key]['parameters'][1]['hasil'] = $cek_fdl->hc . ' ppm';
                            $datas[$key]['parameters'][1]['status'] =  $status_hc;
                            $datas[$key]['parameters'][2]['parameter'] = "Opasitas";
                            $datas[$key]['parameters'][2]['hasil'] = $cek_fdl->opasitas . ' %';
                            $datas[$key]['parameters'][2]['status'] =  $status;
                        }

                        $data_detail['tipe_analisa'] = "Emisi Kendaraan";
                        $data_detail['qr'] = $request->qr;
                        $data_detail['merk_kendaraan'] = $kendaraan->merk_kendaraan;
                        $data_detail['transmisi'] = $kendaraan->transmisi;
                        $data_detail['tahun_pembuatan'] = $kendaraan->tahun_pembuatan;
                        $data_detail['no_polisi'] = $kendaraan->plat_nomor;
                        $data_detail['no_mesin'] = $kendaraan->no_mesin;
                        $data_detail['bahan_bakar'] = $kendaraan->jenis_bbm;
                        $data_detail['kapasitas_cc'] = $kendaraan->cc . ' CC';
                        $data_detail['regulasi'] = $regulasi->peraturan;
                        $data_detail['tgl_uji'] = DATE('Y-m-d', strtotime($cek_fdl->created_at));

                        return response()->json([
                            'detail' => $data_detail,
                            'record' => $jumlah,
                            'hasil' => $datas,
                            'message' => 'Data has ben Show'
                        ], 201);
                    } else {
                        $this->resultx = 'Qr Available';
                        return response()->json([
                            'detail' => [],
                            'record' => 0,
                            'hasil' => [],
                            'message' => $this->resultx
                        ], 201);
                    }
                } else {
                    $this->resultx = 'Qr Code tidak diterbitkan oleh INTILAB';
                    return response()->json([
                        'message' => $this->resultx
                    ], 401);
                }
            } else {
                $this->resultx = 'Pastikan Qr Code Terbaca / Terisi';
                return response()->json([
                    'message' => $this->resultx
                ], 401);
            }
        } else {
            $this->resultx = "sorry you don't have security access";
            return response()->json([
                'message' => $this->resultx
            ], 401);
        }
    }

    public function getRegulasi(Request $request)
    {
        $options = [];

        if ($request->id != null) {
            $order = DataLapanganEmisiOrder::join('master_regulasi', 'data_lapangan_emisi_order.id_regulasi', '=', 'master_regulasi.id')
                ->select('master_regulasi.peraturan', 'master_regulasi.id')
                ->where('data_lapangan_emisi_order.id_fdl', $request->id)
                ->where('data_lapangan_emisi_order.is_active', true)
                ->where('master_regulasi.is_active', true)
                ->first();

            if ($order) {
                $options[] = [
                    'id' => $order->id,
                    'peraturan' => $order->peraturan,
                    'selected' => true
                ];
            }

            $data = DB::table('master_regulasi')
                ->select('peraturan', 'id')
                ->where('is_active', true)
                ->where('id_kategori', 5)
                ->groupBy('peraturan', 'id')
                ->get();

            foreach ($data as $val) {
                if (!$order || $val->id != $order->id) {
                    $options[] = [
                        'id' => $val->id,
                        'peraturan' => $val->peraturan,
                        'selected' => false
                    ];
                }
            }
        } else {
            $data = DB::table('master_regulasi')
                ->select('peraturan', 'id')
                ->where('is_active', true)
                ->where('id_kategori', 5)
                ->groupBy('peraturan', 'id')
                ->get();

            $options[] = [
                'id' => '',
                'peraturan' => '-',
                'selected' => false
            ];

            foreach ($data as $val) {
                $options[] = [
                    'id' => $val->id,
                    'peraturan' => $val->peraturan,
                    'selected' => $val->id == $request->val
                ];
            }
        }

        return response()->json($options);
    }

    public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(3);
        $data = DataLapanganEmisiKendaraan::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function getPerusahaan(Request $request)
    {
        $data = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->select('nama_perusahaan')->first();
        return response()->json(['data' => $data], 200);
    }
}