<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganIsokinetikBeratMolekul;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganIsokinetikKadarAir;
use App\Models\DataLapanganIsokinetikPenentuanKecepatanLinier;
use App\Models\DataLapanganIsokinetikPenentuanPartikulat;
use App\Models\DataLapanganIsokinetikSurveiLapangan;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\IsokinetikHeader;
use App\Models\WsValueEmisiCerobong;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlPartikulatIsokinetikController extends Controller
{
    public function getSurvei(Request $request)
    {
        if ($request->method == 2) {
            $data = DataLapanganIsokinetikSurveiLapangan::where('no_survei', $request->no_survei)->first();
            $check = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_survei', $request->no_survei)->first();

            if ($data) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Survei tidak boleh sama.'
                    ], 401);
                } else {
                    if ($data->kategUp !== 'Tidak dapat dilakukan sampling' || $data->kategDown !== 'Tidak dapat dilakukan sampling') {
                        return response()->json([
                            'id' => $data->id,
                            'diameter' => $data->diameter,
                            'titik_lintas' => $data->titikLin_s,
                            // pending
                            'jumlah_lubang' => $data->jumlah_lubang,
                            'jarakLin_s' => $data->jarakLin_s,
                            // pending
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => 'No. Survei tidak dapat dilakukan sampling.'
                        ], 401);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data berdasarkan No. Survei tersebut.'
                ], 401);
            }
        } else if ($request->method == 3) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sample', strtoupper(trim($request->no_sample)))->first();

            if ($data) {
                return response()->json([
                    'id' => $data->id_lapangan,
                    'diameter' => $data->diameter,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 2 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        } else if ($request->method == 4) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sample', strtoupper(trim($request->no_sample)))->first();
            $data2 = DataLapanganIsokinetikBeratMolekul::where('no_sample', strtoupper(trim($request->no_sample)))->where('shift', 'L1')->first();
            $check = DataLapanganIsokinetikKadarAir::where('no_sample', strtoupper(trim($request->no_sample)))->first();

            if ($data2) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Sample sudah di input.'
                    ], 401);
                } else {
                    return response()->json([
                        'id' => $data2->id_lapangan,
                        'diameter' => $data2->diameter,
                        'Md' => $data2->MdMole,
                        'Ts' => $data2->Ts,
                        'Kp' => $data->kp,
                        'Cp' => $data->cp,
                        'dP' => $data->dP,
                        'Ps' => $data->Ps,
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 3 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        } else if ($request->method == 5) {
            try {
                $no_sample = strtoupper(trim($request->no_sample));
                $check = DataLapanganIsokinetikPenentuanPartikulat::where('no_sample', strtoupper(trim($request->no_sample)))->first();
                $data = DB::select("
                            SELECT 
                                data_survei_lapangan.diameter as diameter,
                                data_survei_lapangan.id as id_lapangan,
                                data_survei_lapangan.titikPar_s as titikPar_s,
                                data_survei_lapangan.jumlah_lubang as jumlah_lubang,
                                data_survei_keclinier.TM as tm,
                                data_survei_keclinier.cp as cp,
                                data_survei_keclinier.Ps as ps,
                                data_survei_keclinier.suhu as suhu,
                                data_survei_keclinier.dP as reratadp,
                                data_survei_keclinier.kp as kp,
                                data_survei_keclinier.tekanan_u as pbar,
                                data_survei_beratmolekul.Ts as ts,
                                data_survei_beratmolekul.CO2 as CO2,
                                data_survei_beratmolekul.CO as CO,
                                data_survei_beratmolekul.NOx as NOx,
                                data_survei_beratmolekul.SO2 as SO2,
                                data_survei_beratmolekul.MdMole as md,
                                data_survei_kadarair.bws as bws,
                                data_survei_kadarair.ms as ms,
                                data_survei_kadarair.vs as vs_m4
                            FROM 
                                data_survei_lapangan
                            LEFT JOIN 
                                data_survei_keclinier ON data_survei_lapangan.id = data_survei_keclinier.id_lapangan
                            LEFT JOIN 
                                data_survei_kadarair ON data_survei_lapangan.id = data_survei_kadarair.id_lapangan
                            LEFT JOIN 
                                data_survei_beratmolekul ON data_survei_lapangan.id = data_survei_beratmolekul.id_lapangan
                            WHERE 
                                data_survei_keclinier.no_sample = ?", [$no_sample]);
                // $data = DB::select("SELECT data_survei_lapangan.diameter as diameter, data_survei_lapangan.id as id_lapangan, data_survei_lapangan.lintasPartikulat as lintasPartikulat, data_survei_lapangan.jumlah_lubang as jumlah_lubang, data_survei_keclinier.TM as tm, data_survei_keclinier.cp as cp, data_survei_keclinier.Ps as ps, data_survei_keclinier.suhu as suhu,data_survei_keclinier.dP as reratadp, data_survei_keclinier.kp as kp, data_survei_keclinier.tekanan_u as pbar, data_survei_beratmolekul.Ts as ts, data_survei_beratmolekul.CO2 as CO2,data_survei_beratmolekul.CO as CO,data_survei_beratmolekul.NOx as NOx,data_survei_beratmolekul.SO2 as SO2,data_survei_beratmolekul.MdMole as md, data_survei_kadarair.bws as bws, data_survei_kadarair.ms as ms  FROM `data_survei_lapangan` LEFT JOIN data_survei_keclinier on data_survei_lapangan.id = data_survei_keclinier.id_lapangan LEFT JOIN data_survei_kadarair on data_survei_lapangan.id = data_survei_kadarair.id_lapangan LEFT JOIN data_survei_beratmolekul ON data_survei_lapangan.id = data_survei_beratmolekul.id_lapangan WHERE data_survei_keclinier.no_sample = 'strtoupper(trim($request->no_sample))'");
                $data4 = DataLapanganIsokinetikKadarAir::where('no_sample', strtoupper(trim($request->no_sample)))->first();
            } catch (Exception $e) {
                dd($e);
            }
            // dd($data);
            if ($data4) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Sample sudah di input.'
                    ], 401);
                } else {
                    return response()->json([
                        'data' => $data,
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 4 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        } else if ($request->method == 6) {
            try {
                $check = DataLapanganIsokinetikHasil::where('no_sample', strtoupper(trim($request->no_sample)))->first();
                $data = DB::select("
                            SELECT 
                                data_survei_lapangan.diameter AS diameter, 
                                data_survei_lapangan.lintasPartikulat AS lintasPartikulat, 
                                data_survei_keclinier.TM AS tm, 
                                data_survei_keclinier.cp AS cp, 
                                data_survei_keclinier.Ps AS ps, 
                                data_survei_keclinier.suhu AS suhu, 
                                data_survei_keclinier.dP AS reratadp, 
                                data_survei_beratmolekul.Ts AS ts, 
                                data_survei_beratmolekul.CO2 AS CO2, 
                                data_survei_beratmolekul.CO AS CO, 
                                data_survei_beratmolekul.NOx AS NOx, 
                                data_survei_beratmolekul.SO2 AS SO2, 
                                data_survei_beratmolekul.MdMole AS md,  
                                data_survei_kadarair.bws AS bws, 
                                data_survei_kadarair.ms AS ms, 
                                data_survei_penetuan_partikulat.pbar AS pbar, 
                                data_survei_penetuan_partikulat.impinger1 AS impinger1, 
                                data_survei_penetuan_partikulat.impinger2 AS impinger2, 
                                data_survei_penetuan_partikulat.impinger3 AS impinger3, 
                                data_survei_penetuan_partikulat.impinger4 AS impinger4, 
                                data_survei_penetuan_partikulat.data_Y AS data_Y, 
                                data_survei_penetuan_partikulat.dH AS dH, 
                                data_survei_penetuan_partikulat.DGM AS DGM,
                                data_survei_penetuan_partikulat.data_total_vs AS Vs, 
                                data_survei_penetuan_partikulat.dgmAwal AS dgmAwal,
                                data_survei_penetuan_partikulat.PaPs AS PaPs, 
                                data_survei_penetuan_partikulat.dn_req AS dn_req, 
                                data_survei_penetuan_partikulat.dn_actual AS dn_actual, 
                                data_survei_penetuan_partikulat.Meter AS Meter, 
                                data_survei_penetuan_partikulat.Stack AS Stack, 
                                data_survei_penetuan_partikulat.id_lapangan AS id_lapangan,
                                data_survei_penetuan_partikulat.Total_time AS Total_time,
                                data_survei_penetuan_partikulat.temperatur_stack AS temperatur_stack
                            FROM 
                                data_survei_lapangan 
                            LEFT JOIN 
                                data_survei_keclinier ON data_survei_lapangan.id = data_survei_keclinier.id_lapangan 
                            LEFT JOIN 
                                data_survei_kadarair ON data_survei_lapangan.id = data_survei_kadarair.id_lapangan 
                            LEFT JOIN 
                                data_survei_beratmolekul ON data_survei_lapangan.id = data_survei_beratmolekul.id_lapangan 
                            LEFT JOIN 
                                data_survei_penetuan_partikulat ON data_survei_lapangan.id = data_survei_penetuan_partikulat.id_lapangan 
                            WHERE 
                                data_survei_keclinier.no_sample = ?
                        ", [trim(strtoupper($request->no_sample))]);

                $data4 = DataLapanganIsokinetikPenentuanPartikulat::where('no_sample', strtoupper(trim($request->no_sample)))->first();

            } catch (Exception $e) {
                dd($e);
            }

            if ($data4) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Sample sudah di input.'
                    ], 401);
                } else {
                    return response()->json([
                        'data' => $data,
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 5 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        }
    }

    public function index(Request $request)
    {
        if ($request->method == 1) {
            $this->autoBlock(DataLapanganIsokinetikSurveiLapangan::class);
            $data = DataLapanganIsokinetikSurveiLapangan::with('detail');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_survei', function ($query, $keyword) {
                    $query->where('no_survei', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('titik_koordinat', function ($query, $keyword) {
                    $query->where('titik_koordinat', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('sumber_emisi', function ($query, $keyword) {
                    $query->where('sumber_emisi', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('merk', function ($query, $keyword) {
                    $query->where('merk', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kecepatan', function ($query, $keyword) {
                    $query->where('kecepatan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('diameter_cerobong', function ($query, $keyword) {
                    $query->where('diameter_cerobong', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('bentuk_cerobong', function ($query, $keyword) {
                    $query->where('bentuk_cerobong', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('jam_operasi', function ($query, $keyword) {
                    $query->where('jam_operasi', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('proses_filtrasi', function ($query, $keyword) {
                    $query->where('proses_filtrasi', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('waktu_survei', function ($query, $keyword) {
                    $query->where('waktu_survei', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('ukuran_lubang', function ($query, $keyword) {
                    $query->where('ukuran_lubang', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('jumlah_lubang_sampling', function ($query, $keyword) {
                    $query->where('jumlah_lubang_sampling', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('lebar_platform', function ($query, $keyword) {
                    $query->where('lebar_platform', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('jarak_upstream', function ($query, $keyword) {
                    $query->where('jarak_upstream', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('jarak_downstream', function ($query, $keyword) {
                    $query->where('jarak_downstream', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kategori_upstream', function ($query, $keyword) {
                    $query->where('kategori_upstream', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kategori_downstream', function ($query, $keyword) {
                    $query->where('kategori_downstream', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('lintas_partikulat', function ($query, $keyword) {
                    $query->where('lintas_partikulat', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kecepatan_linier', function ($query, $keyword) {
                    $query->where('kecepatan_linier', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } else if ($request->method == 2) {
            $this->autoBlock(DataLapanganIsokinetikPenentuanKecepatanLinier::class);
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::with('detail','survei');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('survei', function ($query, $keyword) {
                    $query->whereHas('survei', function ($q) use ($keyword) {
                        $q->where('no_survei', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('no_sampel', function ($query, $keyword) {
                    $query->where('no_sampel', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('id_lapangan', function ($query, $keyword) {
                    $query->where('id_lapangan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('diameter_cerobong', function ($query, $keyword) {
                    $query->where('diameter_cerobong', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('suhu', function ($query, $keyword) {
                    $query->where('suhu', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kelembapan', function ($query, $keyword) {
                    $query->where('kelembapan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('tekanan_udara', function ($query, $keyword) {
                    $query->where('tekanan_udara', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kp', function ($query, $keyword) {
                    $query->where('kp', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('cp', function ($query, $keyword) {
                    $query->where('cp', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('tekPa', function ($query, $keyword) {
                    $query->where('tekPa', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                    $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('dP', function ($query, $keyword) {
                    $query->where('dP', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('TM', function ($query, $keyword) {
                    $query->where('TM', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('Ps', function ($query, $keyword) {
                    $query->where('Ps', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kecLinier', function ($query, $keyword) {
                    $query->where('kecLinier', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } else if ($request->method == 3) {
            $this->autoBlock(DataLapanganIsokinetikBeratMolekul::class);
            $data = DataLapanganIsokinetikBeratMolekul::with('detail','survei');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('survei', function ($query, $keyword) {
                    $query->whereHas('survei', function ($q) use ($keyword) {
                        $q->where('no_survei', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('no_sampel', function ($query, $keyword) {
                    $query->where('no_sampel', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('diameter', function ($query, $keyword) {
                    $query->where('diameter', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('waktu', function ($query, $keyword) {
                    $query->where('waktu', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('suhu_cerobong', function ($query, $keyword) {
                    $query->where('suhu_cerobong', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('O2', function ($query, $keyword) {
                    $query->where('O2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('CO', function ($query, $keyword) {
                    $query->where('CO', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('CO2', function ($query, $keyword) {
                    $query->where('CO2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('NO', function ($query, $keyword) {
                    $query->where('NO', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('NOx', function ($query, $keyword) {
                    $query->where('NOx', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('NO2', function ($query, $keyword) {
                    $query->where('NO2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('SO2', function ($query, $keyword) {
                    $query->where('SO2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('O2Mole', function ($query, $keyword) {
                    $query->where('O2Mole', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('CO2Mole', function ($query, $keyword) {
                    $query->where('CO2Mole', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('COMole', function ($query, $keyword) {
                    $query->where('COMole', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('Ts', function ($query, $keyword) {
                    $query->where('Ts', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('N2Mole', function ($query, $keyword) {
                    $query->where('N2Mole', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('MdMole', function ($query, $keyword) {
                    $query->where('MdMole', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } else if ($request->method == 4) {
            $this->autoBlock(DataLapanganIsokinetikKadarAir::class);
            $data = DataLapanganIsokinetikKadarAir::with('detail','survei');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_sampel', function ($query, $keyword) {
                    $query->where('no_sampel', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('survei', function ($query, $keyword) {
                    $query->whereHas('survei', function ($q) use ($keyword) {
                        $q->where('no_survei', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('diameter_cerobong', function ($query, $keyword) {
                    $query->where('diameter_cerobong', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('waktu', function ($query, $keyword) {
                    $query->where('waktu', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('bws', function ($query, $keyword) {
                    $query->where('bws', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('ms', function ($query, $keyword) {
                    $query->where('ms', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('vs', function ($query, $keyword) {
                    $query->where('vs', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } else if ($request->method == 5) {
            $this->autoBlock(DataLapanganIsokinetikPenentuanPartikulat::class);
            $data = DataLapanganIsokinetikPenentuanPartikulat::with('detail','survei');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_sampel', function ($query, $keyword) {
                    $query->where('no_sampel', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('survei', function ($query, $keyword) {
                    $query->whereHas('survei', function ($q) use ($keyword) {
                        $q->where('no_survei', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('diameter', function ($query, $keyword) {
                    $query->where('diameter', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('titik_lintas_partikulat', function ($query, $keyword) {
                    $query->where('titik_lintas_partikulat', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('data_Y', function ($query, $keyword) {
                    $query->where('data_Y', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('Delta_H', function ($query, $keyword) {
                    $query->where('Delta_H', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('dn_req', function ($query, $keyword) {
                    $query->where('dn_req', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('k_iso', function ($query, $keyword) {
                    $query->where('k_iso', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('delta_H_req', function ($query, $keyword) {
                    $query->where('delta_H_req', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('waktu', function ($query, $keyword) {
                    $query->where('waktu', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('dn_actual', function ($query, $keyword) {
                    $query->where('dn_actual', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger1', function ($query, $keyword) {
                    $query->where('impinger1', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger2', function ($query, $keyword) {
                    $query->where('impinger2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger3', function ($query, $keyword) {
                    $query->where('impinger3', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger4', function ($query, $keyword) {
                    $query->where('impinger4', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('Vs', function ($query, $keyword) {
                    $query->where('Vs', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('dgmAwal', function ($query, $keyword) {
                    $query->where('dgmAwal', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } else if ($request->method == 6) {
            $this->autoBlock(DataLapanganIsokinetikHasil::class);
            $data = DataLapanganIsokinetikHasil::with('detail','survei');

            return Datatables::of($data)
                ->filterColumn('created_by', function ($query, $keyword) {
                    $query->where('created_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_sampel', function ($query, $keyword) {
                    $query->where('no_sampel', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('survei', function ($query, $keyword) {
                    $query->whereHas('survei', function ($q) use ($keyword) {
                        $q->where('no_survei', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('impinger1', function ($query, $keyword) {
                    $query->where('impinger1', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger2', function ($query, $keyword) {
                    $query->where('impinger2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger3', function ($query, $keyword) {
                    $query->where('impinger3', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('impinger4', function ($query, $keyword) {
                    $query->where('impinger4', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('totalBobot', function ($query, $keyword) {
                    $query->where('totalBobot', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('Collector', function ($query, $keyword) {
                    $query->where('Collector', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('v_wtr', function ($query, $keyword) {
                    $query->where('v_wtr', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('v_gas', function ($query, $keyword) {
                    $query->where('v_gas', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('bws_frac', function ($query, $keyword) {
                    $query->where('bws_frac', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('bws_aktual', function ($query, $keyword) {
                    $query->where('bws_aktual', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('ps', function ($query, $keyword) {
                    $query->where('ps', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('avgVs', function ($query, $keyword) {
                    $query->where('avgVs', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('qs', function ($query, $keyword) {
                    $query->where('qs', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('qs_act', function ($query, $keyword) {
                    $query->where('qs_act', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('avg_Tm', function ($query, $keyword) {
                    $query->where('avg_Tm', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('persenIso', function ($query, $keyword) {
                    $query->where('persenIso', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        }
    }

    public function indexApps(Request $request)
    {
        $data = array();
        if ($request->method == 1) {
            $data = DataLapanganIsokinetikSurveiLapangan::with('detail')->where('is_blocked', false)->where('created_by', $this->karyawan)->orderBy('created_at', 'desc');
        } else if ($request->method == 2) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::with('detail')->where('is_blocked', false)->where('created_by', $this->karyawan)->orderBy('created_at', 'desc');
        } else if ($request->method == 3) {
            $data = DataLapanganIsokinetikBeratMolekul::with('detail')->where('is_blocked', false)->where('created_by', $this->karyawan)->orderBy('created_at', 'desc');
        } else if ($request->method == 4) {
            $data = DataLapanganIsokinetikKadarAir::with('detail')->where('is_blocked', false)->where('created_by', $this->karyawan)->orderBy('created_at', 'desc');
        } else if ($request->method == 5) {
            $data = DataLapanganIsokinetikPenentuanPartikulat::with('detail')->where('is_blocked', false)->where('created_by', $this->karyawan)->orderBy('created_at', 'desc');
        } else if ($request->method == 6) {
            $data = DataLapanganIsokinetikHasil::with('detail')->where('is_blocked', false)->where('created_by', $this->karyawan)->orderBy('created_at', 'desc');
        }

        $this->resultx = 'Show Partikulat Isokinetik Success';
        return Datatables::of($data)->make(true);
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->method == 1) {
                $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                
                if($data->bentuk_cerobong == "Persegi"){
                    $data->luas_penampang = number_format($data->lfw * $data->lnw, 2, '.', ',');
                }else{
                    $data->luas_penampang = number_format(3.14 * 0.25 * $data->diameter_cerobong * $data->diameter_cerobong, 2, '.', ',');
                }
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->rejected_by = null;
                $data->rejected_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 2) {

                DB::beginTransaction();
                try {
                    $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                    $order = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', 1)->first();

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->rejected_by = null;
                    $data->rejected_at = null;
                    $data->save();

                    DB::commit();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                        'cat' => 1
                    ], 200);
                } catch (\Exception $e) {
                    dd($e);
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Gagal Approve Data',
                        'error' => $e->getMessage()
                    ], 401);
                }
            } else if ($request->method == 3) {
                $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->rejected_by = null;
                $data->rejected_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 4) {

                $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->rejected_by = null;
                $data->rejected_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 5) {
                DB::beginTransaction();
                try {
                    $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();

                    $method3 = DataLapanganIsokinetikBeratMolekul::where('no_sampel', $data->no_sampel)
                        ->where('is_approve', true)
                        ->get();
                    $method4 = DataLapanganIsokinetikKadarAir::where('no_sampel', $data->no_sampel)
                        ->where('is_approve', true)
                        ->first();

                    $notApproved = [];
                    if (!$method3)
                        $notApproved[] = 'Berat Molekul';
                    if (!$method4)
                        $notApproved[] = 'Kadar Air';

                    if (count($notApproved)) {
                        return response()->json([
                            'message' => 'Data berikut belum dilakukan approved: ' . implode(', ', $notApproved)
                        ], 400);
                    }


                    // rata-rata dari method 3
                    $avgO2Mole = sprintf("%.7f", $method3->avg('O2Mole'));
                    $avgCO2Mole = sprintf("%.7f", $method3->avg('CO2Mole'));
                    $avgCOMole = sprintf("%.7f", $method3->avg('COMole'));
                    $avgCO2 = sprintf("%.7f", $method3->avg('CO2'));
                    $avgCO = sprintf("%.7f", $method3->avg('CO'));

                    // data dari method 4
                    $bws4 = $method4->bws;

                    // Perhitungan
                    $n2Mole = sprintf("%.7f", $avgO2Mole + $avgCO2Mole + $avgCOMole);
                    $md = sprintf("%.7f", (44 * $avgCO2Mole) + (28 * $avgCOMole) + (32 * $avgO2Mole) + (28 * $n2Mole));
                    
                    $nCO2 = sprintf("%.7f", ($avgCO2 * 10000 * 44 * 1000) / 21500);
                    
                    $combustion = ($nCO2 + $avgCO) == 0 
                                ? 0 
                                : sprintf("%.7f", ($nCO2 / ($nCO2 + $avgCO)) * 100);

                    // Asumsi mdMole sama dengan $md (kalau memang kamu ada variabel mdMole sebelumnya, bisa ganti)
                    $mdMole = (float) $md;
                    $ms = sprintf("%.7f", ($mdMole * (1 - $bws4) + (18 * $bws4)));

                    // Update data
                    $data->o2_mole = $avgO2Mole;
                    $data->co2_mole = $avgCO2Mole;
                    $data->co_mole = $avgCOMole;
                    $data->md = $md;
                    $data->ms = $ms;
                    $data->n2_mole = $n2Mole;
                    $data->nco2 = $nCO2;
                    $data->combustion = $combustion;

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->rejected_by = null;
                    $data->rejected_at = null;
                    $data->save();

                    $method6 = DataLapanganIsokinetikHasil::where('no_sampel', $data->no_sampel)->first();
                    
                    // GAS VOL
                    $nilaiDGM = $data->DGM[0]['nilaiDGM'];

                    $lastHole = array_key_last($nilaiDGM);

                    $lastValue = end($nilaiDGM[$lastHole]);

                    $hitung = $lastValue - $data->dgmAwal;

                    $gas_vol = number_format($hitung / 1000, 4, '.', ',');
                    
                    $method6->gas_vol = $gas_vol;
                    $method6->save();

                    DB::commit();
                    return response()->json([
                        'message' => 'Data has been approved',
                        'cat' => 1
                    ], 200);

                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Terjadi kesalahan saat menyimpan data.',
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ], 500);
                }
            } else if ($request->method == 6) {
                try {

                    $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                    $method1 = DataLapanganIsokinetikSurveiLapangan::where('id', $data->id_lapangan)->where('is_approve', 1)->first();
                    $method2 = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', $data->no_sampel)->where('is_approve', 1)->first();
                    $method3 = DataLapanganIsokinetikBeratMolekul::where('no_sampel', $data->no_sampel)->where('is_approve', 1)->first();
                    $method4 = DataLapanganIsokinetikKadarAir::where('no_sampel', $data->no_sampel)->where('is_approve', 1)->first();
                    $method5 = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', $data->no_sampel)->where('is_approve', 1)->first();
                    
                    $notApproved = [];
                    if (!$method1)
                        $notApproved[] = 'Survei Lapangan';
                    if (!$method2)
                        $notApproved[] = 'Penentuan Kecepatan Linier';
                    if (!$method3)
                        $notApproved[] = 'Berat Molekul';
                    if (!$method4)
                        $notApproved[] = 'Kadar Air';
                    if (!$method5)
                        $notApproved[] = 'Penentuan Partikulat';

                    if (count($notApproved)) {
                        return response()->json([
                            'message' => 'Data berikut belum dilakukan approved: ' . implode(', ', $notApproved)
                        ], 400);
                    }

                    // Fungsi Rata-rata
                    function getAverageFromData($data) {
                        if (!is_array($data) || empty($data)) {
                            return null;
                        }

                        try {
                            $allValues = [];

                            foreach ($data as $obj) {
                                // Cari key yang diawali dengan 'lubang'
                                foreach ($obj as $key => $value) {
                                    if (strpos($key, 'lubang') === 0 && is_array($value)) {
                                        // Gabungkan semua nilai ke array utama
                                        foreach ($value as $v) {
                                            $allValues[] = floatval($v);
                                        }
                                    }
                                }
                            }

                            if (empty($allValues)) {
                                return null;
                            }

                            $total = array_sum($allValues);
                            return $total / count($allValues);
                        } catch (Exception $e) {
                            error_log("Error processing data: " . $e->getMessage());
                            return null;
                        }
                    }

                    $konstanta4 = getAverageFromData($method5->dP);
                    $averagePaPs = getAverageFromData($method5->PaPs);
                    $tekananUdara = floatval($method2->tekanan_udara);
                    $selisih = NULL;

                    if (is_numeric($averagePaPs) && $tekananUdara != 0) {
                        $selisih = abs($averagePaPs - $tekananUdara);
                    }

                    $konstanta1 = $selisih;

                    $ukuranLubang = $method1->ukuran_lubang * 10; // Convert cm to mm
                    $diameterCerobong = NULL;

                    if($method1->bentuk_cerobong == "Persegi"){
                        $diameterCm = $method1->diameter_cerobong ?? NULL;

                        if(isset($diameterCm)){
                            $radiusMeter = ($diameterCm / 100) / 2;
                            $diameterCerobong = M_PI * $radiusMeter * $radiusMeter;
                        }
                    } else {
                        $panjang = $method1->lfw ?? 0;
                        $lebar   = $method1->lnw ?? 0;

                        // Konversi dari cm ke meter, lalu hitung luas persegi panjang
                        $diameterCerobong = ($panjang / 100) * ($lebar / 100);
                    }

                    $order = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', 1)->first();

                    if (!$order) {
                        return response()->json(['message' => 'Order tidak ditemukan.'], 404);
                    }

                    $parameterOrder = is_string($order->parameter)
                        ? json_decode($order->parameter, true)
                        : $order->parameter;

                    $listParameters = [
                        "395;Iso-Debu",
                        "396;Iso-Traverse",
                        "397;Iso-Velo",
                        "398;Iso-DMW",
                        "399;Iso-Moisture",
                        "400;Iso-Percent",
                        "401;Iso-Combust",
                        "402;Iso-ResTime"
                    ];

                    foreach ($listParameters as $item) {
                        [$id_param, $nama_param] = explode(';', $item);
                        
                        if (is_array($parameterOrder) && in_array("$id_param;$nama_param", $parameterOrder)) {
                            $header = IsokinetikHeader::where('no_sampel', $data->no_sampel)
                                ->where('id_parameter', $id_param)
                                ->where('is_active', 1)
                                ->first();

                            if (!$header) {
                                $header = new IsokinetikHeader();
                                $header->no_sampel = $data->no_sampel;
                                $header->id_lapangan = $data->id_lapangan;
                                $header->id_parameter = $id_param;
                                $header->parameter = $nama_param;
                                $header->template_stp = 55;
                                $header->tanggal_terima = $order->tanggal_terima ?? now();
                                $header->is_approve = true;
                                $header->approved_by = $this->karyawan;
                                $header->approved_at = Carbon::now();
                                $header->created_by = $this->karyawan;
                                $header->created_at = Carbon::now();
                            } else {
                                $header->id_lapangan = $data->id_lapangan;
                                $header->tanggal_terima = $order->tanggal_terima ?? now();
                                $header->is_approve = true;
                                $header->approved_by = $this->karyawan;
                                $header->approved_at = Carbon::now();
                                $header->created_by = $this->karyawan;
                                $header->created_at = Carbon::now();
                            }

                            // Simpan dulu supaya $header->id ada
                            $header->save();

                            // --- Ambil atau buat wsValue ---
                            $wsValue = WsValueEmisiCerobong::where('no_sampel', $data->no_sampel)
                                ->where('id_isokinetik', $header->id)
                                ->where('is_active', 1)
                                ->first();

                            if (!$wsValue) {
                                $wsValue = new WsValueEmisiCerobong();
                                $wsValue->no_sampel = $data->no_sampel;
                                $wsValue->id_isokinetik = $header->id;
                                $wsValue->is_active = 1;
                            }

                            // Tambahkan hasil_isokinetik sesuai jenis parameter
                            switch ($nama_param) {
                                case 'Iso-Velo':
                                    $header->rata_rata_tekanan_pitot = $konstanta4;
                                    $header->selisih_tekanan_barometer = $selisih;

                                    $hasilIso = [
                                        'rata_rata_tekanan_pitot' => $konstanta4,
                                        'selisih_tekanan_barometer' => $selisih,
                                        'kp' => $method2->kp,
                                        'cp' => $method2->cp,
                                        'tekanan_barometer' => $method2->tekanan_udara,
                                        'kecepatan_linier' => $data->avgVs,
                                        'kecepatan_volumetrik_aktual' => $data->qs_act
                                    ];
                                    break;

                                case 'Iso-Debu':
                                    $header->konstanta_4 = $konstanta4;
                                    $header->Konstanta_1 = $selisih;

                                    $hasilIso = [
                                        'koefisien_dry_gas' => $method2->kp,
                                        'delta_h_calibrate' => $method2->cp,
                                        'konstanta_1' => $selisih,
                                        'konstanta_2' => $method2->tekanan_udara,
                                        'konstanta_4' => $konstanta4,
                                        'konstanta_5' => $data->avgVs,
                                        'volume_sampel_dari_dry_gas' => $data->qs_act,
                                        'volume_sampel_gas_standar' => $data->qs_act,
                                        'rata_rata_suhu_gas_buang' => $data->qs_act,
                                        'tekanan_gas_buang' => $data->qs_act,
                                        'diameter_nozzle' => $data->qs_act,
                                        'luas_penampang_nozzle' => $data->qs_act,
                                    ];
                                    break;

                                case 'Iso-Traverse':
                                    $header->ukuran_lubang = $ukuranLubang;
                                    $header->diameter_cerobong = $diameterCerobong;

                                    $hasilIso = [
                                        'traverse_poin_partikulat_1' => $method1->lintas_partikulat,
                                        'traverse_poin_kecepatan_linier' => $method1->kecepatan_linier,
                                        'diameter_cerobong' => $diameterCerobong,
                                        'ukuran_lubang_sampling' => $ukuranLubang,
                                        'jumlah_lubang_sampling' => $method1->jumlah_lubang_sampling,
                                        'luas_penampang_cerobong' => $method1->luas_penampang,
                                        'jarak_upstream' => $method1->jarak_upstream,
                                        'jarak_downstream' => $method1->jarak_downstream,
                                        'kategori_upstream' => $method1->kategori_upstream,
                                        'kategori_downstream' => $method1->kategori_downstream,
                                    ];
                                    break;
                                case 'Iso-Combust':
                                    $hasilIso = [
                                        'effisiensi_pembakaran' => $method5->combustion,  
                                    ];
                                    break;
                                case 'Iso-DMW':
                                    $hasilIso = [
                                        'berat_molekul_kering' => $method5->md,
                                        'berat_molekul_basah' => $method5->ms,
                                        'co2_mole' => $method5->co2_mole,
                                        'co_mole' => $method5->co_mole,
                                        'o2_mole' => $method5->o2_mole,
                                        'n2_mole' => $method5->n2_mole,
                                        'co2_dmw' => $method5->CO2,
                                        'co_dmw' => $method5->CO,
                                        'nox_dmw' => $method5->NOx,
                                        'so2_dmw' => $method5->SO2,
                                        'rata_suhu_cerobong' => $method5->temperatur_stack
                                    ];
                                    break;
                                case 'Iso-Moisture':
                                    $hasilIso = [
                                        'durasi_waktu' => $method5->Total_time,
                                        'volume_sampel_gas_standar' => $data->gas_vol,
                                        'volume_uap_air_sampel_gas_standar' => $data->v_wtr,
                                        'kecepatan_volumetrik_standar' => $data->qs,
                                        'rata_suhu_gas_standar' => $method2->suhu,
                                        'uap_air_dalam_aliran_gas_hide' => $data->bws_aktual,
                                        'kadar_uap_air' => $data->bws_aktual,
                                    ];
                                    break;
                                case 'Iso-Percent':
                                    $hasilIso = [
                                        'persen_sampling_isokinetik' => $data->persenIso
                                    ];
                                    break;
                                default:
                                    $hasilIso = [];
                            }

                            // Simpan hasil_isokinetik di kedua tabel
                            $wsValue->hasil_isokinetik = json_encode($hasilIso);

                            $wsValue->created_by = $this->karyawan;
                            $wsValue->created_at = Carbon::now();
                            $wsValue->save();

                            $header->save();
                        }
                    }


                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->rejected_by = null;
                    $data->rejected_at = null;
                    $data->save();

                    DB::commit();

                    return response()->json([
                        'message' => "Data berhasil diapprove oleh " . $this->karyawan,
                        'cat' => 1
                    ], 200);
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                        'line' => $e->getLine()
                    ], 500);
                }
            }
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->method == 1) {
                $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                $data->is_approve = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben rejected',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 2) {
                $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                $data->is_approve = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben rejected',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 3) {
                $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                $data->is_approve = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben rejected',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 4) {

                $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                $data->is_approve = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben rejected',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 5) {

                $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                $data->is_approve = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben rejected',
                    'cat' => 1
                ], 200);
            } else if ($request->method == 6) {

                $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                $data->is_approve = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben rejected',
                    'cat' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Reject'
            ], 401);
        }
    }
    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->method == 1) {
                if ($request->is_blocked == true) {
                    $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            } else if ($request->method == 2) {
                if ($request->is_blocked == true) {
                    $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            } else if ($request->method == 3) {
                if ($request->is_blocked == true) {
                    $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            } else if ($request->method == 4) {
                if ($request->is_blocked == true) {
                    $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            } else if ($request->method == 5) {
                if ($request->is_blocked == true) {
                    $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            } else if ($request->method == 6) {
                if ($request->is_blocked == true) {
                    $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'message' => 'Gagal Reject'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        try {
            if (isset($request->id) && $request->id != null) {
                if ($request->method == 1) {
                    $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                    $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                    $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                    if (is_file($foto_lokasi)) {
                        unlink($foto_lokasi);
                    }
                    if (is_file($foto_kondisi)) {
                        unlink($foto_kondisi);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $data->delete();
                    DataSurveiKecLinier::where('id_lapangan', $data->id)->delete();
                    DataSurveiBeratMolekul::where('id_lapangan', $data->id)->delete();
                    DataSurveiKadarAir::where('id_lapangan', $data->id)->delete();
                    DataSurveiPenentuanPartikulat::where('id_lapangan', $data->id)->delete();
                    DataSurveiHasilIsokinetik::where('id_lapangan', $data->id)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else if ($request->method == 2) {
                    $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                    $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                    $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                    if (is_file($foto_lokasi)) {
                        unlink($foto_lokasi);
                    }
                    if (is_file($foto_kondisi)) {
                        unlink($foto_kondisi);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $data->delete();
                    DataSurveiBeratMolekul::where('id_lapangan', $data->id)->delete();
                    DataSurveiKadarAir::where('id_lapangan', $data->id)->delete();
                    DataSurveiPenentuanPartikulat::where('id_lapangan', $data->id)->delete();
                    DataSurveiHasilIsokinetik::where('id_lapangan', $data->id)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else if ($request->method == 3) {
                    $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                    $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                    $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                    if (is_file($foto_lokasi)) {
                        unlink($foto_lokasi);
                    }
                    if (is_file($foto_kondisi)) {
                        unlink($foto_kondisi);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $data->delete();
                    DataSurveiKadarAir::where('id_lapangan', $data->id)->delete();
                    DataSurveiPenentuanPartikulat::where('id_lapangan', $data->id)->delete();
                    DataSurveiHasilIsokinetik::where('id_lapangan', $data->id)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else if ($request->method == 4) {

                    $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                    $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                    $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                    if (is_file($foto_lokasi)) {
                        unlink($foto_lokasi);
                    }
                    if (is_file($foto_kondisi)) {
                        unlink($foto_kondisi);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $data->delete();
                    DataSurveiPenentuanPartikulat::where('id_lapangan', $data->id)->delete();
                    DataSurveiHasilIsokinetik::where('id_lapangan', $data->id)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else if ($request->method == 5) {

                    $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                    $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                    $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                    if (is_file($foto_lokasi)) {
                        unlink($foto_lokasi);
                    }
                    if (is_file($foto_kondisi)) {
                        unlink($foto_kondisi);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $data->delete();
                    DataSurveiHasilIsokinetik::where('id_lapangan', $data->id)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else if ($request->method == 6) {

                    $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                    $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                    $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                    if (is_file($foto_lokasi)) {
                        unlink($foto_lokasi);
                    }
                    if (is_file($foto_kondisi)) {
                        unlink($foto_kondisi);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $data->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                }
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function detail(Request $request)
    {
        if ($request->method == 1) {
            $data = DataLapanganIsokinetikSurveiLapangan::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            return response()->json([
                'id' => $data->id,
                'sampler' => $data->created_by,
                'no_survei' => $data->no_survei,
                'nama_titik' => $data->keterangan,
                'nama_perusahaan' => $data->nama_perusahaan,
                'sumber_emisi' => $data->sumber_emisi,
                'merk' => $data->merk,
                'bahan_bakar' => $data->bahan_bakar,
                'cuaca' => $data->cuaca,
                'kecepatan' => $data->kecepatan, // (m/s)
                'jam_operasi' => $data->jam_operasi,
                'proses_filtrasi' => $data->proses_filtrasi,
                'koordinat' => $data->titik_koordinat,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'waktu_survei' => $data->waktu_survei,
                'diameter_cerobong' => $data->diameter_cerobong, // (m)
                'ukuran_lubang' => $data->ukuran_lubang, // (Cm)
                'jumlah_lubang_sampling' => $data->jumlah_lubang_sampling,
                'lebar_platform' => $data->lebar_platform, // (m)
                'bentuk_cerobong' => $data->bentuk_cerobong,
                'jarak_upstream' => $data->jarak_upstream, // (m)
                'jarak_downstream' => $data->jarak_downstream, // (m)
                'kategori_upstream' => $data->kategori_upstream, // (D)
                'kategori_downstream' => $data->kategori_downstream, // (D)
                'lintas_partikulat' => $data->lintas_partikulat, // (titik)
                'kecepatan_linier' => $data->kecepatan_linier, // (titik)
                'foto_lokasi' => $data->foto_lokasi_sampel,
                'foto_kondisi' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'lfw' => $data->lfw,
                'lnw' => $data->lnw,
                'titik_lintas_partikulat_s' => $data->titik_lintas_partikulat_s,
                'titik_lintas_kecepatan_linier_s' => $data->titik_lintas_kecepatan_linier_s,
                'jarak_partikulat_s' => $data->jarak_partikulat_s,
                'jarak_linier_s' => $data->jarak_linier_s,
                'filename_denah' => $data->filename_denah,
                'status' => '200',
            ], 200);
        } else if ($request->method == 2) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            $perusahaan = '-';
            if (!empty($data->detail)) {
                $perusahaan = $data->detail->nama_perusahaan;
            }
            return response()->json([
                'id' => $data->id,
                'no_survei' => $data->no_survei,
                'sampler' => $data->created_by,
                'no_sampel' => $data->no_sampel,
                'nama' => $perusahaan,
                'diameter_cerobong' => $data->diameter_cerobong, // (m)
                'suhu' => $data->suhu, // ('C)
                'kelembapan' => $data->kelembapan, // (%RH)
                'tekanan_udara' => $data->tekanan_udara, // (mmHg)
                'kp' => $data->kp,
                'cp' => $data->cp,
                'waktu_pengukuran' => $data->waktu_pengukuran,
                'kecLinier' => $data->kecLinier,
                'tekPa' => $data->tekPa, // (mmH2O)
                'dataDp' => $data->dataDp,
                'dP' => $data->dP, // average dataDp
                'TM' => $data->TM, // (K)
                'Ps' => $data->Ps, // (mmHg)
                'foto_lokasi' => $data->foto_lokasi_sample,
                'foto_kondisi' => $data->foto_kondisi_sample,
                'foto_lain' => $data->foto_lain,
                'rerata_suhu' => $data->rerata_suhu,
                'rerata_paps' => $data->rerata_paps,
                'jaminan_mutu' => $data->jaminan_mutu,
                'status_test' => $data->status_test,
                'uji_aliran' => $data->uji_aliran,
                'status' => '200',
            ], 200);
        } else if ($request->method == 3) {
            $data = DataLapanganIsokinetikBeratMolekul::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            $perusahaan = '-';
            if (!empty($data->detail)) {
                $perusahaan = $data->detail->nama_perusahaan;
            }
            return response()->json([
                'id' => $data->id,
                'sampler' => $data->created_by,
                'no_sample' => $data->no_sampel,
                'diameter' => $data->diameter,
                'nama' => $perusahaan,
                'waktu' => $data->waktu,
                'o2' => $data->O2,
                'co' => $data->CO,
                'co2' => $data->CO2,
                'no' => $data->NO,
                'nox' => $data->NOx,
                'no2' => $data->NO2,
                'so2' => $data->SO2,
                'suhu' => $data->suhu_cerobong,
                'co2mole' => $data->CO2Mole,
                'comole' => $data->COMole,
                'o2mole' => $data->O2Mole,
                'n2mole' => $data->N2Mole,
                'md' => $data->MdMole,
                'ts' => $data->Ts,
                'foto_lokasi' => $data->foto_lokasi_sample,
                'foto_kondisi' => $data->foto_kondisi_sample,
                'foto_lain' => $data->foto_lain,
                'nCO2' => $data->nCO2,
                'shift' => $data->shift,
                'combustion' => $data->combustion,
            ], 200);
        } else if ($request->method == 4) {
            $data = DataLapanganIsokinetikKadarAir::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            $perusahaan = '-';
            if (!empty($data->detail)) {
                $perusahaan = $data->detail->nama_perusahaan;
            }
            return response()->json([
                'id' => $data->id,
                'sampler' => $data->created_by,
                'no_sample' => $data->no_sampel,
                'id_lapangan' => $data->id_lapangan,
                'metode_uji' => $data->metode_uji,
                'kadar_air' => $data->kadar_air,
                'nama' => $perusahaan,
                'laju_aliran' => $data->laju_aliran,
                'data_impinger' => $data->data_impinger,
                'nilai_y' => $data->nilai_y,
                'pm' => $data->Pm,
                'suhu_cerobong' => $data->suhu_cerobong,
                'data_dgmterbaca' => $data->data_dgmterbaca,
                'data_kalkulasi_dgm' => $data->data_kalkulasi_dgm,
                'jaminan_mutu' => $data->jaminan_mutu,
                'data_dgm_test' => $data->data_dgm_test,
                'dgm_test' => $data->dgm_test,
                'waktu_test' => $data->waktu_test,
                'laju_alir_test' => $data->laju_alir_test,
                'tekV_test' => $data->tekV_test,
                'hasil_test' => $data->hasil_test,
                'vwc' => $data->vwc,
                'vmstd' => $data->vmstd,
                'vwsg' => $data->vwsg,
                'bws' => $data->bws,
                'ms' => $data->ms,
                'vs' => $data->vs,
                'foto_lokasi' => $data->foto_lokasi_sample,
                'foto_kondisi' => $data->foto_kondisi_sample,
                'foto_lain' => $data->foto_lain,
            ], 200);
        } else if ($request->method == 5) {
            try {
                $data = DataLapanganIsokinetikPenentuanPartikulat::with('addby', 'appby')->where('id', $request->id)->first();
                $this->resultx = 'get Detail partikulat isokinetik success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    // 'no_order'                   => $dataLap->detail->no_order,
                    'no_sample' => $data->no_sampel,
                    'diameter' => $data->diameter,
                    'data_Y' => $data->data_Y,
                    'Delta_H' => $data->Delta_H,
                    'impinger1' => $data->impinger1,
                    'impinger2' => $data->impinger2,
                    'impinger3' => $data->impinger3,
                    'impinger4' => $data->impinger4,
                    'k_iso' => $data->k_iso,
                    'titik_lintas_partikulat' => $data->titik_lintas_partikulat,
                    'waktu' => $data->waktu,
                    // 'corp'                       => $dataLap->detail->nama,
                    'CO' => $data->CO,
                    'CO2' => $data->CO2,
                    'NOx' => $data->NOx,
                    'SO2' => $data->SO2,
                    'bobot' => $data->bobot,
                    'DGM' => $data->DGM,
                    'SelisihDGM' => $data->rataselisihdgm,
                    'dP' => $data->dP,
                    'PaPs' => $data->PaPs,
                    'dH' => $data->dH,
                    'Stack' => $data->Stack,
                    'Meter' => $data->Meter,
                    'Vp' => $data->Vp,
                    'SebelumPengujian' => $data->sebelumpengujian,
                    'SesudahPengujian' => $data->sesudahpengujian,
                    'Filter' => $data->Filter,
                    'Oven' => $data->Oven,
                    'exit_impinger' => $data->exit_impinger,
                    'Probe' => $data->Probe,
                    'Vs' => $data->Vs,
                    'data_total_vs' => $data->data_total_vs,
                    'delta_vm' => $data->delta_vm,
                    'pbar' => $data->pbar,
                    'temperatur_stack' => $data->temperatur_stack,
                    'Total_time' => $data->Total_time,
                    'foto_lokasi' => $data->foto_lokasi_sample,
                    'foto_kondisi' => $data->foto_kondisi_sample,
                    'foto_lain' => $data->foto_lain,
                ], 200);
            } catch (Exception $e) {
                dd($e);
            }

        } else if ($request->method == 6) {
            try {
                $data = DataLapanganIsokinetikHasil::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail partikulat isokinetik success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'no_sample' => $data->no_sampel,
                    'impinger1' => $data->impinger1,
                    'impinger2' => $data->impinger2,
                    'impinger3' => $data->impinger3,
                    'impinger4' => $data->impinger4,
                    'totalBobot' => $data->totalBobot,
                    'Collector' => $data->Collector,
                    'v_wtr' => $data->v_wtr,
                    'v_gas' => $data->v_gas,
                    'bws_frac' => $data->bws_frac,
                    'bws_aktual' => $data->bws_aktual,
                    'ps' => $data->ps,
                    'avgVs' => $data->avgVs,
                    'qs' => $data->qs,
                    'qs_act' => $data->qs_act,
                    'avg_Tm' => $data->avg_Tm,
                    'avgTS' => $data->avgTS,
                    'persenIso' => $data->persenIso,
                    'recovery' => $data->recoveryacetone,
                    'foto_lokasi' => $data->foto_lok,
                    'foto_kondisi' => $data->foto_sampl,
                    'foto_lain' => $data->foto_lain,
                ], 200);
            } catch (Exception $e) {
                dd($e);
            }
        }
    }

    protected function autoBlock($model)
    {
        $tgl = Carbon::now()->subDays(3);
        $data = $model::where('is_blocked', false)->orWhere('is_blocked', null)->where('created_at', '<=', $tgl)->update(['is_blocked' => true, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}