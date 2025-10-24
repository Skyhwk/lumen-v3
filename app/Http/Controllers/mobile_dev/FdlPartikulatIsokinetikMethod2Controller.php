<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganIsokinetikBeratMolekul;
use App\Models\DataLapanganIsokinetikPenentuanKecepatanLinier;
use App\Models\DataLapanganIsokinetikPenentuanPartikulat;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganIsokinetikKadarAir;
use App\Models\DataLapanganIsokinetikSurveiLapangan;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// SERVICE
use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlPartikulatIsokinetikMethod2Controller extends Controller
{
    public function getSurvei(Request $request)
    {
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
                        'diameter' => $data->diameter_cerobong,
                        'titik_lintas' => $data->titik_lintas_kecepatan_linier_s,
                        // pending
                        'jumlah_lubang' => $data->jumlah_lubang_sampling,
                        'jarakLin_s' => json_decode($data->jarak_linier_s, true) ?? [],
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
    }

    public function store(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Waktu Pengambilan tidak boleh kosong.'
                ], 401);
            }
            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

            if(!$orderDetail) {
                return response()->json([
                    'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Tidak Ditemukan'
                ], 400);
            }

            $check = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($check) {
                return response()->json([
                    'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 2.!'
                ], 401);
            } else {

                $pengDb = array();
                $tot = count($request->dp);
                // dd($tot);
                for ($p = 0; $p < $tot; $p++) {
                    $pengu = [];
                    foreach ($request->dp[$p] as $key => $val) {
                        array_push($pengu, (object)[
                            'nilaiDp' => $request->dp[$p][$key],
                            'suhu' => $request->suhu[$p][$key],
                            'paps' => $request->paps[$p][$key],
                        ]);
                    }
                    array_push($pengDb, (object) [
                        'pengukuran' => $pengu,
                        'reratadp' => $request->average_dp_lubang[$p],
                        'reratasuhu' => $request->average_suhu_lubang[$p],
                        'reratapaps' => $request->average_paps_lubang[$p],
                    ]);
                }
                $ujialiran = [];
                $pengalir = [];
                // dd($pengDb);
                if ($request->delta) {
                    foreach ($request->delta as $k => $v) {
                        array_push($pengalir, (object)[
                            'delta' => $request->delta[$k],
                            'sudut' => $request->sudut[$k],
                        ]);
                    }
                    array_push($ujialiran, (object)[
                        'pengukuran' => $pengalir,
                        'rdelta' => $request->rata_delta,
                        'rsudut' => $request->rata_sudut,
                    ]);
                }

                $survei = DataLapanganIsokinetikSurveiLapangan::where('no_survei', $request->no_survei)->first();

                // dd($pengDb, $ujialiran);

                $data = new DataLapanganIsokinetikPenentuanKecepatanLinier();
                if ($request->no_survei != '')
                    $data->no_survei = $request->no_survei;
                $data->id_lapangan = $survei->id;
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->diameter != '')
                    $data->diameter_cerobong = $request->diameter;
                if ($request->suhu_single != '')
                    $data->suhu = $request->suhu_single;
                if ($request->kelem != '')
                    $data->kelembapan = $request->kelem;
                if ($request->tekU != '')
                    $data->tekanan_udara = $request->tekU;
                if ($request->linKec != '')
                    $data->kecLinier = $request->linKec;
                if ($request->kp != '')
                    $data->kp = $request->kp;
                if ($request->cp != '')
                    $data->cp = $request->cp;
                if ($request->tekPa != '')
                    $data->tekPa = $request->tekPa;
                if ($request->waktu != '')
                    $data->waktu_pengukuran = $request->waktu;
                if ($request->reratadataDp != '')
                    $data->dP = $request->reratadataDp;
                if ($request->TM != '')
                    $data->TM = $request->TM;
                if ($request->Ps != '')
                    $data->Ps = $request->Ps;
                if ($request->reratadataSuhu != '')
                    $data->rerata_suhu = $request->reratadataSuhu;
                if ($request->reratadataPaps != '')
                    $data->rerata_paps = $request->reratadataPaps;
                if ($request->status_test != '')
                    $data->status_test = $request->status_test;
                if ($request->jaminan_mutu != '')
                    $data->jaminan_mutu = json_encode($request->jaminan_mutu);
                $data->dataDp = json_encode($pengDb);
                $data->uji_aliran = json_encode($ujialiran);
                if ($request->foto_lok != '')
                    $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_sampl != '')
                    $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                if ($request->foto_lain != '')
                    $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                if ($request->permission != '')
                    $data->permission = $request->permission;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                // dd($data);
                $data->save();

                // UPDATE ORDER DETAIL
                $update = DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                InsertActivityFdl::by($this->user_id)->action('input')->target("Kecepatan Linear pada nomor sampel $request->no_sample")->save();

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e.getMessage(),
                'line' => $e.getLineNumber(),
                'code' => $e.getCode()
            ], 401);
        }
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganIsokinetikPenentuanKecepatanLinier::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(3));

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

    public function approve(Request $request)
    {
        if ($request->method == 1) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } else if ($request->method == 2) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } else if ($request->method == 3) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } else if ($request->method == 4) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } else if ($request->method == 5) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } else if ($request->method == 6) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
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
                'sumber' => $data->sumber_emisi,
                'merk' => $data->merk,
                'bakar' => $data->bahan_bakar,
                'cuaca' => $data->cuaca,
                'kecepatan' => $data->kecepatan, // (m/s)
                'durasiOp' => $data->jam_operasi,
                'filtrasi' => $data->proses_filtrasi,
                'coor' => $data->titik_koordinat,
                'lat' => $data->latitude,
                'long' => $data->longitude,
                'waktu' => $data->waktu_survei,
                'diameter' => $data->diameter_cerobong, // (m)
                'lubang' => $data->ukuran_lubang, // (Cm)
                'jumlah_lubang' => $data->jumlah_lubang_sampling,
                'lebar' => $data->lebar_platform, // (m)
                'bentuk' => $data->bentuk_cerobong,
                'jarakUp' => $data->jarak_upstream, // (m)
                'jarakDown' => $data->jarak_downstream, // (m)
                'kategUp' => $data->kategori_upstream, // (D)
                'kategDown' => $data->kategori_downstream, // (D)
                'lintasPartikulat' => $data->lintas_partikulat, // (titik)
                'kecLinier' => $data->kecepatan_linier, // (titik)
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'lfw' => $data->lfw,
                'lnw' => $data->lnw,
                'titikPar_s' => $data->titik_lintas_partikulat_s,
                'titikLin_s' => $data->titik_lintas_kecepatan_linier_s,
                'jarakPar_s' => $data->jarak_partikulat_s,
                'jarakLin_s' => $data->jarak_linier_s,
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
                'no_sample' => $data->no_sampel,
                'nama' => $perusahaan,
                'diameter' => $data->diameter_cerobong, // (m)
                'suhu' => $data->suhu, // ('C)
                'kelem' => $data->kelembapan, // (%RH)
                'tekanan_u' => $data->tekanan_udara, // (mmHg)
                'kp' => $data->kp,
                'cp' => $data->cp,
                'waktu' => $data->waktu_pengukuran,
                'kecLinier' => $data->kecLinier,
                'tekPa' => $data->tekPa, // (mmH2O)
                'dataDp' => $data->dataDp,
                'dP' => $data->dP, // average dataDp
                'TM' => $data->TM, // (K)
                'Ps' => $data->Ps, // (mmHg)
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
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
                $perusahaan = $data->detail;
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
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
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
                'nama' =>  $perusahaan,
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
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
            ], 200);
        } else if ($request->method == 5) {
            try {
                $data = DataLapanganIsokinetikPenentuanPartikulat::with('detail')->where('id', $request->id)->first();
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
                    'foto_lok' => $data->foto_lokasi_sampel,
                    'foto_kon' => $data->foto_kondisi_sampel,
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
                    'foto_lok' => $data->foto_lokasi_sampel,
                    'foto_kon' => $data->foto_kondisi_sampel,
                    'foto_lain' => $data->foto_lain,
                ], 200);
            } catch (Exception $e) {
                dd($e);
            }
        }
    }

    public function delete(Request $request)
    {
        try {
            if ($request->method == 1) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikPenentuanKecepatanLinier::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikBeratMolekul::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikKadarAir::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id)->delete();
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 2) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikBeratMolekul::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikKadarAir::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 3) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikKadarAir::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 4) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 5) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 6) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            }
        } catch (Exception $e) {
            dd($e);
        }
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
}