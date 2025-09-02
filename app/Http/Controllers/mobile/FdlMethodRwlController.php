<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganErgonomi;

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
use App\Services\GetAtasan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlMethodRwlController extends Controller
{
    public function getSample(Request $request)
    {
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

        // Check if method is valid and execute accordingly
        $method = 5;
        if ($method >= 1 && $method <= 10) {
            return $this->processMethod($request, $fdl, $method);
        }

        return response()->json(['message' => 'Method not found.'], 400);
    }

    public function store(Request $request)
    {
        $po = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
        DB::beginTransaction();
        try {
            $inputs = $request->all();
            $lok = [];
            $asimetris = [];

            // TABEL FREKUENSI
            $tabelFrekuensi = [
                0.2 => ['<1 Jam' => ['<75' => 1, '>=75' => 1], '1-2 Jam' => ['<75' => 0.95, '>=75' => 0.95], '2-8 Jam' => ['<75' => 0.85, '>=75' => 0.85]],
                0.5 => ['<1 Jam' => ['<75' => 0.97, '>=75' => 0.97], '1-2 Jam' => ['<75' => 0.92, '>=75' => 0.92], '2-8 Jam' => ['<75' => 0.81, '>=75' => 0.81]],
                1   => ['<1 Jam' => ['<75' => 0.94, '>=75' => 0.94], '1-2 Jam' => ['<75' => 0.88, '>=75' => 0.88], '2-8 Jam' => ['<75' => 0.75, '>=75' => 0.75]],
                2   => ['<1 Jam' => ['<75' => 0.91, '>=75' => 0.91], '1-2 Jam' => ['<75' => 0.84, '>=75' => 0.84], '2-8 Jam' => ['<75' => 0.65, '>=75' => 0.65]],
                3   => ['<1 Jam' => ['<75' => 0.88, '>=75' => 0.88], '1-2 Jam' => ['<75' => 0.79, '>=75' => 0.79], '2-8 Jam' => ['<75' => 0.55, '>=75' => 0.55]],
                4   => ['<1 Jam' => ['<75' => 0.84, '>=75' => 0.84], '1-2 Jam' => ['<75' => 0.72, '>=75' => 0.72], '2-8 Jam' => ['<75' => 0.45, '>=75' => 0.45]],
                5   => ['<1 Jam' => ['<75' => 0.8, '>=75' => 0.8], '1-2 Jam' => ['<75' => 0.6, '>=75' => 0.6], '2-8 Jam' => ['<75' => 0.35, '>=75' => 0.35]],
                6   => ['<1 Jam' => ['<75' => 0.75, '>=75' => 0.75], '1-2 Jam' => ['<75' => 0.5, '>=75' => 0.5], '2-8 Jam' => ['<75' => 0.27, '>=75' => 0.27]],
                7   => ['<1 Jam' => ['<75' => 0.7, '>=75' => 0.7], '1-2 Jam' => ['<75' => 0.42, '>=75' => 0.42], '2-8 Jam' => ['<75' => 0.22, '>=75' => 0.22]],
                8   => ['<1 Jam' => ['<75' => 0.6, '>=75' => 0.6], '1-2 Jam' => ['<75' => 0.35, '>=75' => 0.35], '2-8 Jam' => ['<75' => 0.18, '>=75' => 0.18]],
                9   => ['<1 Jam' => ['<75' => 0.52, '>=75' => 0.52], '1-2 Jam' => ['<75' => 0.3, '>=75' => 0.3], '2-8 Jam' => ['<75' => 0, '>=75' => 0.15]],
                10  => ['<1 Jam' => ['<75' => 0.45, '>=75' => 0.45], '1-2 Jam' => ['<75' => 0.26, '>=75' => 0.26], '2-8 Jam' => ['<75' => 0, '>=75' => 0.13]],
                11  => ['<1 Jam' => ['<75' => 0.41, '>=75' => 0.41], '1-2 Jam' => ['<75' => 0, '>=75' => 0.23], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                12  => ['<1 Jam' => ['<75' => 0.37, '>=75' => 0.37], '1-2 Jam' => ['<75' => 0, '>=75' => 0.21], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                13  => ['<1 Jam' => ['<75' => 0, '>=75' => 0.34], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                14  => ['<1 Jam' => ['<75' => 0, '>=75' => 0.31], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                15  => ['<1 Jam' => ['<75' => 0, '>=75' => 0.28], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                16  => ['<1 Jam' => ['<75' => 0, '>=75' => 0], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
            ];


            foreach ($inputs as $key => $value) {
                if (strpos($key, 'lokasi_tangan') === 0) {
                    $label = $key;
                    $lok[$label] = $value;
                }
                if (strpos($key, 'sudut_asimetris') === 0) {
                    $label = $key;
                    $asimetris[$label] = $value;
                }
            };

            $A1 = 23;
            $A2 = 23;

            $H1 = (float)($request->jarak_vertikal);
            $H2 = $H1;

            $I1 = (float)($request->berat_beban);
            $I2 = $I1;

            $J1 = (float)($request->frek_jml_angkatan);
            if ($J1 < 0.2) {
                $J1 = 0.2;
            } else if ($J1 > 0.2 && $J1 < 1) {
                $J1 = 0.5;
            } else if ($J1 > 15) {
                $J1 = 16;
            } else {
                $J1 = (int)floor($J1);
            }
            $J2 = $J1;

            if ($J1 < 0.2) {
                $j_1_konversi = '<0.2';
            } else if ($J1 > 0.2 && $J1 < 1) {
                $j_1_konversi = 0.5;
            } else if ($J1 > 15) {
                $j_1_konversi = '>15';
            } else {
                $j_1_konversi = (int)floor($J1);
            }
            $j_2_konversi = $j_1_konversi;

            $K1 = (float)($request->durasi_jam_kerja);

            if ($K1 < 1) {
                $durasiKategori = '<1 Jam';
            } elseif ($K1 >= 1 && $K1 <= 2) {
                $durasiKategori = '1-2 Jam';
            } elseif ($K1 > 2 && $K1 <= 8) {
                $durasiKategori = '2-8 Jam';
            } else {
                $durasiKategori = '>8 Jam'; // Opsional: Kalau lebih dari 8 jam
            }
            $k_1 = ($durasiKategori);
            $K2 = $k_1;

            $L1 = $request->kopling_tangan;
            $L2 = $L1;

            $dataunparsed = $request->all();
            $parsed = [];

            foreach ($dataunparsed as $key => $value) {
                if (preg_match('/^([^\[]+)\[([^\]]+)\]$/', $key, $matches)) {
                    $mainKey = $matches[1]; // contoh: 'lokasi_tangan'
                    $subKey = $matches[2];  // contoh: 'Horizontal Awal'

                    $parsed[$mainKey][$subKey] = $value;
                } else {
                    $parsed[$key] = $value;
                }
            }
            // Sekarang kamu bisa akses:
            $M1 = (float)($parsed['lokasi_tangan']['Horizontal Awal']);
            $M2 = (float)($parsed['lokasi_tangan']['Horizontal Akhir']);
            $N1 = (float)($parsed['lokasi_tangan']['Vertikal Awal']);
            $N2 = (float)($parsed['lokasi_tangan']['Vertikal Akhir']);
            $O1 = (float)($parsed['sudut_asimetris']['Awal']);
            $O2 = (float)($parsed['sudut_asimetris']['Akhir']);

            function getR($N, $L)
            {
                if ($L === "Jelek") return 0.9;
                if ($L === "Sedang" && $N < 75) return 0.95;
                return 1;
            }

            $R1 = getR($N1, $L1);
            $R2 = getR($N2, $L2);

            $B1 = number_format((($M1 <= 25) ? 1 : (($M1 > 63) ? 0 : (25 / $M1))), 4);
            $B2 = number_format((($M2 <= 25) ? 1 : (($M2 > 63) ? 0 : (25 / $M2))), 4);

            $C1 = number_format((($N1 < 175) ? (1 - (0.003 * abs($N1 - 75))) : 0), 4);
            $C2 = number_format((($N2 < 175) ? (1 - (0.003 * abs($N2 - 75))) : 0), 4);

            $D1 = number_format((($H1 <= 25) ? 1 : (($H1 > 175) ? 0 : (0.82 + (4.5 / $H1)))), 4);
            $D2 = number_format((($H2 <= 25) ? 1 : (($H2 > 175) ? 0 : (0.82 + (4.5 / $H2)))), 4);

            $E1 = number_format((($O1 <= 135) ? (1 - (0.0032 * $O1)) : 0), 4);
            $E2 = number_format((($O2 <= 135) ? (1 - (0.0032 * $O2)) : 0), 4);

            if ($N1 < 75) {
                $n_1 = '<75';
            } else {
                $n_1 = '>=75';
            }

            if ($N2 < 75) {
                $n_2 = '<75';
            } else {
                $n_2 = '>=75';
            }

            $Q1 = $tabelFrekuensi[$J1][$k_1][$n_1];
            $Q2 = $tabelFrekuensi[$J2][$K2][$n_2];

            $G1 = $Q1;
            $G2 = $Q2;

            // NILAI RWL (BEBAN YANG DIANGKAT) 
            $rwl_awal = number_format($A1 * $B1 * $C1 * $D1 * $E1 * $G1 * $R1, 4);
            $rwl_akhir = number_format($A2 * $B2 * $C2 * $D2 * $E2 * $G2 * $R2, 4);

            // NILAI LIFTING
            $li_awal = ($rwl_awal > 0) ? number_format($I1 / $rwl_awal, 4) : 0;
            $li_akhir = ($rwl_akhir > 0) ? number_format($I2 / $rwl_akhir, 4) : 0;

            // Kesimpuna LI
            if ($li_awal < 1) {
                $kesimpulan_awal = "Tidak ada masalah dengan pekerjaan mengangkat, maka tidak diperlukan perbaikan terhadap pekerjaan, tetapi tetap terus mendapatkan perhatian sehingga nilai LI dapat dipertahankan <1";
            } else if ($li_awal >= 1 && $li_awal < 3) {
                $kesimpulan_awal = "Ada beberapa masalah dari beberapa parameter angkat, sehingga perlu dilakukan pengecekan dan redesain segera pada parameter yang menyebabkan nilai RWL tinggi";
            } else {
                $kesimpulan_awal = "Terdapat banyak permasalahan dari parameter angkat, sehingga diperlukan pengecekan dan perbaikan sesegera mungkin secara menyeluruh terhadap parameter yang menyebabkan nilai tinggi";
            }

            if ($li_akhir < 1) {
                $kesimpulan_akhir = "Tidak ada masalah dengan pekerjaan mengangkat, maka tidak diperlukan perbaikan terhadap pekerjaan, tetapi tetap terus mendapatkan perhatian sehingga nilai LI dapat dipertahankan <1";
            } else if ($li_akhir >= 1 && $li_akhir < 3) {
                $kesimpulan_akhir = "Ada beberapa masalah dari beberapa parameter angkat, sehingga perlu dilakukan pengecekan dan redesain segera pada parameter yang menyebabkan nilai RWL tinggi";
            } else {
                $kesimpulan_akhir = "Terdapat banyak permasalahan dari parameter angkat, sehingga diperlukan pengecekan dan perbaikan sesegera mungkin secara menyeluruh terhadap parameter yang menyebabkan nilai tinggi";
            }

            $pengukuran = [
                "lokasi_tangan" => $request->lokasi_tangan,
                "sudut_asimetris" => $request->sudut_asimetris,
                'nilai_beban_rwl_awal' => $rwl_awal,
                'nilai_beban_rwl_akhir' => $rwl_akhir,
                'lifting_index_awal' => $li_awal,
                'lifting_index_akhir' => $li_akhir,
                'konstanta_beban_awal' => $A1,
                'konstanta_beban_akhir' => $A2,
                'pengali_horizontal_awal' => $B1,
                'pengali_horizontal_akhir' => $B2,
                'pengali_vertikal_awal' => $C1,
                'pengali_vertikal_akhir' => $C2,
                'pengali_jarak_awal' => $D1,
                'pengali_jarak_akhir' => $D2,
                'pengali_asimetris_awal' => $E1,
                'pengali_asimetris_akhir' => $E2,
                'pengali_frekuensi_awal' => $G1,
                'pengali_frekuensi_akhir' => $G2,
                'pengali_kopling_awal' => $R1,
                'pengali_kopling_akhir' => $R2,
                'durasi_jam_kerja_awal' => $k_1,
                'durasi_jam_kerja_akhir' => $K2,
                'frekuensi_jumlah_awal' => $j_1_konversi,
                'frekuensi_jumlah_akhir' => $j_2_konversi,
                'kesimpulan_nilai_li_awal' => $kesimpulan_awal,
                'kesimpulan_nilai_li_akhir' => $kesimpulan_akhir
            ];

            $data = new DataLapanganErgonomi();
            if ($request->no_order != '')
                $data->no_order = $request->no_order;

            if (strtoupper(trim($request->no_sample)) != '')
                $data->no_sampel = strtoupper(trim($request->no_sample));
            if ($request->pekerja != '')
                $data->nama_pekerja = $request->pekerja;
            if ($request->divisi != '')
                $data->divisi = $request->divisi;
            if ($request->usia != '')
                $data->usia = $request->usia;
            $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
            if ($request->kelamin != '')
                $data->jenis_kelamin = $request->kelamin;
            if ($request->waktu_bekerja != '')
                $data->waktu_bekerja = $request->waktu_bekerja;
            if ($request->aktivitas != '')
                $data->aktivitas = $request->aktivitas;
            $data->method = 5;
            $data->berat_beban = $request->berat_beban;
            $data->pengukuran = json_encode($pengukuran);
            $data->frekuensi_jumlah_angkatan = str_replace(',', '.', $request->frek_jml_angkatan);
            $data->kopling_tangan = $request->kopling_tangan;
            $data->jarak_vertikal = $request->jarak_vertikal;
            $data->durasi_jam_kerja = $request->durasi_jam_kerja;
            if ($request->foto_samping_kiri != '')
                $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
            if ($request->foto_samping_kanan != '')
                $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
            if ($request->foto_depan != '')
                $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
            if ($request->foto_belakang != '')
                $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
            $data->aktivitas_ukur = $request->aktivitas_ukur;
            $data->permission = $request->permis;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // UPDATE ORDER DETAIL
            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', 'LIKE', '%27-%')
                ->orWhere('kategori_3', 'LIKE', '%53-%')
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->update([
                    'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil disimpan.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ], 401);
        }
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $data = array();
    //         if ($request->tipe != '') {
    //             $data = DataLapanganErgonomi::with('detail')->orderBy('id', 'desc');
    //         } else {
    //             if ($request->method == 2) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 2)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             }
    //         }
    //         return Datatables::of($data)->make(true);
    //     } catch (Exception $e) {
    //         dd($e);
    //     }
    // }


    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganErgonomi::with('detail')
            ->where('created_by', $this->karyawan)->where('method', 5)
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
        try {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganErgonomi::where('id', $request->id)->first();
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function detail(Request $request)
    {
        if ($request->tipe != '') {
            $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
            $po = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            $this->resultx = 'get Detail ergonomi success';
            return response()->json([
                'data_lapangan' => $data,
                'data_po' => $po,
            ], 200);
        } else {
            if ($request->method == 1) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'sebelum_kerja' => $data->sebelum_kerja,
                    'setelah_kerja' => $data->setelah_kerja,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                ], 200);
            } else if ($request->method == 2) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';
                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 3) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 4) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 5) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'berat_beban' => $data->berat_beban,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'frek_jml_angkatan' => $data->frekuensi_jumlah_angkatan,
                    'kopling_tangan' => $data->kopling_tangan,
                    'jarak_vertikal' => $data->jarak_vertikal,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 6) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 7) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 8) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 9) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 10) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            }
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $cek = DataLapanganErgonomi::where('id', $request->id)->first();
            $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
            $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
            $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
            $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
            $no_sample = $cek->no_sampel;
            if (is_file($foto_samping_kiri)) {
                unlink($foto_samping_kiri);
            }
            if (is_file($foto_samping_kanan)) {
                unlink($foto_samping_kanan);
            }
            if (is_file($foto_depan)) {
                unlink($foto_depan);
            }
            if (is_file($foto_belakang)) {
                unlink($foto_belakang);
            }
            $cek->delete();
            InsertActivityFdl::by($this->user_id)->action('delete')->target("Method R dengan nomor sampel $no_sample")->save();
            return response()->json([
                'message' => 'Data has ben Deleted',
                'cat' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    private function processMethod($request, $fdl, $method)
    {
        try {
            // Check for the existence of the sample with the appropriate category and parameter
            $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->whereIn('kategori_3', ['27-Udara Lingkungan Kerja', '11-Udara Ambient', '53-Ergonomi'])
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->where('is_active', true)
                ->first();

            // Check if the data for the given method already exists
            $data = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('method', $method)
                ->first();

            // Respond based on whether the data already exists
            if ($check) {
                if ($data) {
                    return response()->json(['message' => 'No. Sample sudah di input.'], 401);
                } else {
                    return response()->json([
                        'message' => 'Successful.',
                        'data' => $fdl
                    ], 200);
                }
            } else {
                return response()->json(['message' => 'Tidak ada parameter Ergonomi berdasarkan No. Sample tersebut.'], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    // JUMLAH SKOR POSTUR TUBUH
    private function calculateTotalDurasi($data)
    {
        $totalDurasi = 0;

        // Periksa apakah data adalah array
        if (is_array($data)) {
            foreach ($data as $section => $values) {
                // Periksa apakah $values adalah array
                if (is_array($values)) {
                    foreach ($values as $subSection => $details) {
                        // Periksa apakah detail memiliki 'Durasi Gerakan'
                        if (isset($details['Durasi Gerakan']) && $details['Durasi Gerakan'] !== 'Tidak') {
                            // Cek apakah nilai 'Durasi Gerakan' dapat dipisah dengan benar
                            $durasi = explode(';', $details['Durasi Gerakan'])[0];

                            // Pastikan durasi adalah angka
                            if (is_numeric($durasi)) {
                                $totalDurasi += (int)$durasi;
                            } else {
                                // Tambahkan log untuk kasus durasi yang tidak valid
                                // Misalnya, jika nilai 'Durasi Gerakan' tidak bisa diproses
                                Log::warning("Durasi Gerakan tidak valid: {$details['Durasi Gerakan']}");
                            }
                        }
                    }
                }
            }
        }

        return $totalDurasi;
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
