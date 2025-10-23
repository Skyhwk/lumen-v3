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

class FdlPartikulatIsokinetikMethod5Controller extends Controller
{
    public function getSample(Request $request)
    {
        
        try {
            $no_sample = strtoupper(trim($request->no_sample));
            $check = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            $data = DB::select("
                        SELECT 
                            data_lapangan_isokinetik_survei_lapangan.diameter_cerobong as diameter,
                            data_lapangan_isokinetik_survei_lapangan.id as id_lapangan,
                            data_lapangan_isokinetik_survei_lapangan.titik_lintas_partikulat_s as titikPar_s,
                            data_lapangan_isokinetik_survei_lapangan.jumlah_lubang_sampling as jumlah_lubang,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.TM as tm,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.cp as cp,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.Ps as ps,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.suhu as suhu,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.dP as reratadp,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.kp as kp,
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.tekanan_udara as pbar,
                            data_lapangan_isokinetik_berat_molekul.Ts as ts,
                            data_lapangan_isokinetik_berat_molekul.CO2 as CO2,
                            data_lapangan_isokinetik_berat_molekul.CO as CO,
                            data_lapangan_isokinetik_berat_molekul.NOx as NOx,
                            data_lapangan_isokinetik_berat_molekul.SO2 as SO2,
                            data_lapangan_isokinetik_berat_molekul.MdMole as md,
                            data_lapangan_isokinetik_kadar_air.bws as bws,
                            data_lapangan_isokinetik_kadar_air.ms as ms,
                            data_lapangan_isokinetik_kadar_air.vs as vs_m4
                        FROM 
                            data_lapangan_isokinetik_survei_lapangan
                        LEFT JOIN 
                            data_lapangan_isokinetik_penentuan_kecepatan_linier ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_penentuan_kecepatan_linier.id_lapangan
                        LEFT JOIN 
                            data_lapangan_isokinetik_kadar_air ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_kadar_air.id_lapangan
                        LEFT JOIN 
                            data_lapangan_isokinetik_berat_molekul ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_berat_molekul.id_lapangan
                        WHERE 
                            data_lapangan_isokinetik_penentuan_kecepatan_linier.no_sampel = ?", [$no_sample]);
            // $data = DB::select("SELECT data_lapangan_isokinetik_survei_lapangan.diameter as diameter, data_lapangan_isokinetik_survei_lapangan.id as id_lapangan, data_lapangan_isokinetik_survei_lapangan.lintasPartikulat as lintasPartikulat, data_lapangan_isokinetik_survei_lapangan.jumlah_lubang as jumlah_lubang, data_lapangan_isokinetik_penentuan_kecepatan_linier.TM as tm, data_lapangan_isokinetik_penentuan_kecepatan_linier.cp as cp, data_lapangan_isokinetik_penentuan_kecepatan_linier.Ps as ps, data_lapangan_isokinetik_penentuan_kecepatan_linier.suhu as suhu,data_lapangan_isokinetik_penentuan_kecepatan_linier.dP as reratadp, data_lapangan_isokinetik_penentuan_kecepatan_linier.kp as kp, data_lapangan_isokinetik_penentuan_kecepatan_linier.tekanan_u as pbar, data_lapangan_isokinetik_berat_molekul.Ts as ts, data_lapangan_isokinetik_berat_molekul.CO2 as CO2,data_lapangan_isokinetik_berat_molekul.CO as CO,data_lapangan_isokinetik_berat_molekul.NOx as NOx,data_lapangan_isokinetik_berat_molekul.SO2 as SO2,data_lapangan_isokinetik_berat_molekul.MdMole as md, data_lapangan_isokinetik_kadar_air.bws as bws, data_lapangan_isokinetik_kadar_air.ms as ms  FROM `data_lapangan_isokinetik_survei_lapangan` LEFT JOIN data_lapangan_isokinetik_penentuan_kecepatan_linier on data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_penentuan_kecepatan_linier.id_lapangan LEFT JOIN data_lapangan_isokinetik_kadar_air on data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_kadar_air.id_lapangan LEFT JOIN data_lapangan_isokinetik_berat_molekul ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_berat_molekul.id_lapangan WHERE data_lapangan_isokinetik_penentuan_kecepatan_linier.no_sample = 'strtoupper(trim($request->no_sample))'");
            $data4 = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

            // dd($data);
            if ($data4) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Sample sudah di input.'
                    ], 401);
                } else {
                    return response()->json([
                        'data' => $data[0],
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 4 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
        
    }

    private function mapPengukuran(string $prefix, string $avgPrefix) {
        $result = [];
        foreach (request()->all() as $key => $value) {
            if (preg_match('/^'.$prefix.'\[(\d+)\]$/', $key, $matches)) {
                $index = $matches[1];
                
                // Handle both array and non-array values
                $formattedValue = is_array($value) ? $value : [$value];
                
                $result[] = (object) [
                    'lubang ' . $index => $formattedValue,$avgPrefix . ' lubang ' . $index => request()->input($avgPrefix . "[$index]"),
                ];
            }
        }
        return $result;
    }

    public function store(Request $request)
    {
        // DB::beginTransaction();
        // try {
        //     $pengukuranDGM = $this->mapPengukuran('dgm', 'lfdgm');
        //     $pengukurandP = $this->mapPengukuran('DpmmH2O', 'avgDpmmH2O');
        //     $pengukuranPaPs = $this->mapPengukuran('Paps', 'avgPaps');        
        //     $pengukurandH = $this->mapPengukuran('dHmmH2O', 'avgdHmmH2O');
        //     $pengukuranStack = $this->mapPengukuran('Stack', 'avgStack');    
        //     $pengukuranMeter = $this->mapPengukuran('Meter', 'avgMeter');
        //     $pengukuranVp = $this->mapPengukuran('VPinHg', 'avgVPinHg');
        //     $pengukuranFilter = $this->mapPengukuran('Filter', 'avgFilter');
        //     $pengukuranOven = $this->mapPengukuran('Oven', 'avgOven');
        //     $pengukuranexit_impinger = $this->mapPengukuran('ExitImpinger', 'avgExitImpinger');
        //     $pengukuranProbe = $this->mapPengukuran('Probe', 'avgProbe');

        //     if ($request->jam_pengambilan == '') {
        //         return response()->json([
        //             'message' => 'Waktu Pengambilan tidak boleh kosong.'
        //         ], 401);
        //     }

        //     $arrsebelumpengujian = [
        //         'volume_dgm' => $request->input('volumeDGMSebelum', null),
        //         'total_waktu_test' => $request->input('totalWaktuTestSebelum', null),
        //         'laju_alir' => $request->input('lajuAlirSebelum', null),
        //         'tekanan_vakum' => $request->input('tekananVakumSebelum', null),
        //         'hasil' => $request->input('hasilSebelum', null),
        //     ];

        //     $arrsesudahpengujian = [
        //         'volume_dgm' => $request->input('volumeDGMSesudah', null),
        //         'total_waktu_test' => $request->input('totalWaktuTestSesudah', null),
        //         'laju_alir' => $request->input('lajuAlirSesudah', null),
        //         'tekanan_vakum' => $request->input('tekananVakumSesudah', null),
        //         'hasil' => $request->input('hasilSesudah', null),
        //     ];

        //     // AVERAGE STACK
        //     $totalAvgStack = 0;
        //     $count = 0;

        //     foreach (request()->all() as $key => $value) {
        //         if (preg_match('/^Stack\[(\d+)\]$/', $key, $matches)) {
        //             $index = $matches[1];
        //             $avg = (float) $request->input("avgStack[$index]");
                    
        //             $totalAvgStack += $avg;
        //             $count++;
        //         }
        //     }
        //     // dd('masuk');

        //     // Menghitung rata-rata jika ada data
        //     $average = $count > 0 ? $totalAvgStack / $count : 0;
        //     $TemperaturStack = $average +  273.15; // KONVERSI DARI CELSIUS KE KELVIN
        //     $TemperaturStackFormatted = number_format($TemperaturStack, 2);

        //     // END AVERAGE STACK

        //     // AVERAGE DGM VM
        //     // DGM AWAL
        //     $dgmAwal = (float) $request->dgmAwal; // atau sesuai dengan indeks yang diinginkan

        //     // RATA-RATA SELISIH DGM
        //     $selisihrataDGM = $request->rataRataSelisihDGM;

        //     $pengukuranDGMVM = [];
        //     if (!empty($request->DGM) && is_array($request->DGM)) {
        //         $totalSelisihKeseluruhan = 0; // Total selisih untuk seluruh data
        //         $count = 0;

        //         $allDGMData = [];
        //         foreach ($request->DGM as $subArray) {
        //             if (is_array($subArray)) {
        //                 $allDGMData = array_merge($allDGMData, $subArray);
        //             }
        //         }

        //         // Lakukan perhitungan selisih setelah menggabungkan
        //         foreach ($allDGMData as $index => $value) {
        //             $value = (float) $value; // Pastikan nilai adalah float
        //             if ($index === 0) {
        //                 // Untuk data pertama, kurangkan dengan DGM Awal
        //                 $selisih = $value - $dgmAwal; // Menggunakan dgmAwal yang terpisah
        //             } else {
        //                 // Untuk data selanjutnya, kurangkan dengan data sebelumnya
        //                 $previousValue = (float) $allDGMData[$index - 1];
        //                 $selisih = $value - $previousValue;
        //             }


        //             if ($value) { // hanya hitung jika ada nilai
        //                 $totalSelisihKeseluruhan += $selisih; // Tambahkan ke total keseluruhan
        //                 $count++;
        //             }

        //             // Menghitung persentase selisih
        //             $persentaseSelisih = abs(($selisih - $selisihrataDGM) / $selisihrataDGM * 100);

        //             // Simpan data ke dalam array
        //             $selisihKey = "selisihDGM" . ($index + 1); // Menentukan kunci berdasarkan indeks
        //             array_push($pengukuranDGMVM, (object) [
        //                 'nilaiDGM' . ($index + 1) => $value,
        //                 $selisihKey => number_format($persentaseSelisih, 1), // Menyimpan selisih per data
        //             ]);
        //         }
        //     }
        //     // END AVERAGE VM DGM


        //     // VS DP
        //     // AVERAGE
        //     $arraydP1 = [];
        //     $totalCountPaPs = 0;
        //     $totalAvgPaPs = 0;

        //     // Get all PaPs values
        //     $allPaPs = [];
        //     foreach (request()->all() as $key => $value) {
        //         if (preg_match('/^PaPs\[(\d+)\]$/', $key, $matches)) {
        //             $index = $matches[1];
        //             $allPaPs[$index] = is_array($value) ? $value : [$value];
        //             $avgValue = request()->input("avgPaPs[$index]", 0);
        //             $totalAvgPaPs += (float)$avgValue;
        //             $totalCountPaPs++;
        //         }
        //     }

        //     $averageAvgPaPs = $totalCountPaPs > 0 ? $totalAvgPaPs / $totalCountPaPs : 0;
        //     $pengukuranavgPaPs = floatval(number_format($averageAvgPaPs, 2));
        //     // Hitung rata-rata

        //     // Tambahkan rata-rata ke output
        //     // $pengukuranavgPaPs[] = (object) [
        //     //     'Rata-rata Avg PaPs' => number_format($averageAvgPaPs, 2) // Format dengan 2 desimal
        //     // ];
        //     $pengukuranavgPaPs = floatval(number_format($averageAvgPaPs, 2));

        //     $metode2 = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
        //     $metode4 = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
        //     // Loop untuk menggabungkan data dan menerapkan rumus
        //     // dd($pengukurandP);
        //     $i = 1;
        //     foreach ($pengukurandP as $item) {
        //         // dd($item);
        //         foreach ($item as $key => $value) {
        //             // Jika nilai adalah array, kita bisa menggabungkannya
        //             if (is_array($value)) {
        //                 foreach ($value as $val) {
        //                     // Terapkan rumus
        //                     $dP = (float)$val; // Ambil nilai dP dari array
        //                     $hasil = floatval($metode2->kp) * floatval($metode2->cp) * pow((floatval($TemperaturStack) / ((floatval($metode2->tekanan_udara) - $pengukuranavgPaPs) * floatval($metode4->ms))), 0.5) * pow($dP, 0.5);
        //                     $hasil = round($hasil, 2);
        //                     // Simpan hasil ke dalam pengukurandP1
        //                     $arraydP1[] = [
        //                         'nilai_' . $i => $dP,
        //                         'hasil_' . $i => number_format($hasil, 1, '.', ''), // Menyimpan hasil perhitungan rumus
        //                     ];
        //                     $i++;
        //                 }
        //             }
        //         }
        //     }
        //     $check = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
        //     if ($check) {
        //         return response()->json([
        //             'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 5.!'
        //         ], 401);
        //     } else {
        //         $data = new DataLapanganIsokinetikPenentuanPartikulat();
        //         if ($request->id_lapangan != '')
        //             $data->id_lapangan = $request->id_lapangan;
        //         if (strtoupper(trim($request->no_sample)) != '')
        //             $data->no_sampel = strtoupper(trim($request->no_sample));
        //         if ($request->diameter != '')
        //             $data->diameter = $request->diameter;
        //         if ($request->titik_lintas_partikulat != '')
        //             $data->titik_lintas_partikulat = $request->titik_lintas_partikulat;
        //         if ($request->data_Y != '')
        //             $data->data_Y = $request->data_Y;
        //         if ($request->pbarm5 != '')
        //             $data->pbar = $request->pbarm5;
        //         if ($request->Delta_H != '')
        //             $data->Delta_H = $request->Delta_H;
        //         if ($request->dn_req != '')
        //             $data->dn_req = $request->dn_req;
        //         if ($request->k_iso != '')
        //             $data->k_iso = $request->k_iso;
        //         if ($request->delta_H_req != '')
        //             $data->delta_H_req = $request->delta_H_req;
        //         if ($request->dgmAwal != '')
        //             $data->dgmAwal = $request->dgmAwal;
        //         if ($request->jam_pengambilan != '')
        //             $data->waktu = $request->jam_pengambilan;
        //         if ($request->dn_actual != '')
        //             $data->dn_actual = $request->dn_actual;
        //         if ($request->impinger1 != '')
        //             $data->impinger1 = $request->impinger1;
        //         if ($request->impinger2 != '')
        //             $data->impinger2 = $request->impinger2;
        //         if ($request->impinger3 != '')
        //             $data->impinger3 = $request->impinger3;
        //         if ($request->impinger4 != '')
        //             $data->impinger4 = $request->impinger4;
        //         if ($request->Vs != '')
        //             $data->Vs = $request->Vs;
        //         if ($request->rataRataSelisihDGM != '')
        //             $data->rataselisihdgm = $request->rataRataSelisihDGM;
        //         $data->temperatur_stack = $TemperaturStackFormatted;
        //         $data->data_total_vs = json_encode($arraydP1);
        //         $data->delta_vm = json_encode($pengukuranDGMVM);
        //         $data->DGM = json_encode($pengukuranDGM);
        //         $data->dP = json_encode($pengukurandP);
        //         $data->PaPs = json_encode($pengukuranPaPs);
        //         $data->dH = json_encode($pengukurandH);
        //         $data->Stack = json_encode($pengukuranStack);
        //         $data->Meter = json_encode($pengukuranMeter);
        //         $data->Vp = json_encode($pengukuranVp);
        //         $data->Filter = json_encode($pengukuranFilter);
        //         $data->Oven = json_encode($pengukuranOven);
        //         $data->exit_impinger = json_encode($pengukuranexit_impinger);
        //         $data->Probe = json_encode($pengukuranProbe);
        //         $data->sebelumpengujian = json_encode($arrsebelumpengujian);
        //         $data->sesudahpengujian = json_encode($arrsesudahpengujian);
        //         if ($request->CO2 != '')
        //             $data->CO2 = $request->CO2;
        //         if ($request->CO != '')
        //             $data->CO = $request->CO;
        //         if ($request->NOx != '')
        //             $data->NOx = $request->NOx;
        //         if ($request->SO2 != '')
        //             $data->SO2 = $request->SO2;
        //         if ($request->Total_time != '')
        //             $data->Total_time = $request->Total_time;
        //         if ($request->foto_lok != '')
        //             $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
        //         if ($request->foto_sampl != '')
        //             $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
        //         if ($request->foto_lain != '')
        //             $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
        //         if ($request->permis != '')
        //             $data->permission = $request->permis;
        //         $data->created_by = $this->karyawan;
        //         $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
        //         dd($data);
        //         $data->save();

        //         // UPDATE ORDER DETAIL
        //         DB::table('order_detail')
        //             ->where('no_sampel', strtoupper(trim($request->no_sample)))
        //             ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

        //         InsertActivityFdl::by($this->user_id)->action('input')->target("Penentuan Partikulat pada nomor sampel $request->no_sample")->save();
                
                
        //         DB::commit();
        //         return response()->json([
        //             'message' => 'Data berhasil disimpan.'
        //         ], 200);
        //     }
        // } catch (Exception $e) {
        //     DB::rollBack();
        //     return response()->json([
        //         'message' => $e.getMessage(),
        //         'line' => $e.getLine(),
        //         'code' => $e.getCode()
        //     ], 401);
        // }

        DB::beginTransaction();
            try {
                $pengukuranDGM = [];

                if (!empty($request->dgm) && is_array($request->dgm)) {
                    $nilaiDGM = [];

                    foreach ($request->dgm as $key => $value) {
                        // index +1 biar mulai dari "1" bukan "0"
                        $nilaiDGM[(string)($key + 1)] = $value;
                    }

                    $pengukuranDGM[] = [
                        'nilaiDGM' => $nilaiDGM,
                        'avgDGM'   => $request->lfDGM,
                    ];
                }

                $pengukurandP = [];
                foreach ($request->DpmmH2O as $key => $value) {

                    array_push($pengukurandP, (object) [
                        'lubang ' . $key => $request->DpmmH2O[$key],
                        'avgDp lubang ' . $key => $request->avgDpmmH2O[$key],
                    ]);
                }

                $pengukuranPaPs = [];

                // ZAKI
                foreach ($request->PaPs as $key => $value) {

                    array_push($pengukuranPaPs, (object) [
                        'lubang ' . $key => $request->PaPs[$key],
                        'avgPaPs lubang ' . $key => $request->avgPaPs[$key],
                    ]);
                }

                $pengukurandH = [];
                // ZAKI
                foreach ($request->dHmmH2O as $key => $value) {

                    array_push($pengukurandH, (object) [
                        'lubang ' . $key => $request->dHmmH2O[$key],
                        'avgdH lubang ' . $key => $request->avgdHmmH2O[$key],
                    ]);
                }

                $pengukuranStack = [];
                foreach ($request->Stack as $key => $value) {

                    array_push($pengukuranStack, (object) [
                        'lubang ' . $key => $request->Stack[$key],
                        'avgStack lubang ' . $key => $request->avgStack[$key],
                    ]);
                }

                $pengukuranMeter = [];
                foreach ($request->Meter as $key => $value) {

                    array_push($pengukuranMeter, (object) [
                        'lubang ' . $key => $request->Meter[$key],
                        'avgMeter lubang ' . $key => $request->avgMeter[$key],
                    ]);
                }

                $pengukuranVp = [];
                foreach ($request->VPinHg as $key => $value) {

                    array_push($pengukuranVp, (object) [
                        'lubang ' . $key => $request->VPinHg[$key],
                        'avgVP lubang ' . $key => $request->avgVPinHg[$key],
                    ]);
                }

                $pengukuranFilter = [];
                foreach ($request->Filter as $key => $value) {

                    array_push($pengukuranFilter, (object) [
                        'lubang ' . $key => $request->Filter[$key],
                        'avgFilter lubang ' . $key => $request->avgFilter[$key],
                    ]);
                }

                $pengukuranOven = [];
                foreach ($request->Oven as $key => $value) {

                    array_push($pengukuranOven, (object) [
                        'lubang ' . $key => $request->Oven[$key],
                        'avgOven lubang ' . $key => $request->avgOven[$key],
                    ]);
                }

                $pengukuranexit_impinger = [];
                foreach ($request->ExitImpinger as $key => $value) {

                    array_push($pengukuranexit_impinger, (object) [
                        'lubang ' . $key => $request->ExitImpinger[$key],
                        'avgExImp lubang ' . $key => $request->avgExitImpinger[$key],
                    ]);
                }

                $pengukuranProbe = [];
                foreach ($request->Probe as $key => $value) {

                    array_push($pengukuranProbe, (object) [
                        'lubang ' . $key => $request->Probe[$key],
                        'avgProbe lubang ' . $key => $request->avgProbe[$key],
                    ]);
                }

                if ($request->jam_pengambilan == '') {
                    return response()->json([
                        'message' => 'Waktu Pengambilan tidak boleh kosong.'
                    ], 401);
                }

                $arrsebelumpengujian = [
                    'volume_dgm' => $request->input('volumeDGMSebelum', ''),
                    'total_waktu_test' => $request->input('totalWaktuTestSebelum', ''),
                    'laju_alir' => $request->input('lajuAlirSebelum', ''),
                    'tekanan_vakum' => $request->input('tekananVakumSebelum', ''),
                    'hasil' => $request->input('hasilSebelum', ''),
                ];

                $arrsesudahpengujian = [
                    'volume_dgm' => $request->input('volumeDGMSesudah', ''),
                    'total_waktu_test' => $request->input('totalWaktuTestSesudah', ''),
                    'laju_alir' => $request->input('lajuAlirSesudah', ''),
                    'tekanan_vakum' => $request->input('tekananVakumSesudah', ''),
                    'hasil' => $request->input('hasilSesudah', ''),
                ];

                // AVERAGE STACK
                $totalAvgStack = 0;
                $count = 0;

                foreach ($request->Stack as $key => $value) {
                    // Menambahkan nilai avgStack ke total
                    $totalAvgStack += $request->avgStack[$key];
                    $count++; // Menghitung jumlah data
                }

                // Menghitung rata-rata jika ada data
                $average = $count > 0 ? $totalAvgStack / $count : 0;
                $TemperaturStack = $average +  273.15; // KONVERSI DARI CELSIUS KE KELVIN
                $TemperaturStackFormatted = number_format($TemperaturStack, 2);

                // END AVERAGE STACK

                // AVERAGE DGM VM
                // DGM AWAL
                $dgmAwal = (float) $request->dgmAwal; // atau sesuai dengan indeks yang diinginkan

                // RATA-RATA SELISIH DGM
                $selisihrataDGM = $request->rataRataSelisihDGM;

                if (!empty($request->dgm) && is_array($request->dgm)) {
                    $pengukuranDGMVM = [];
                    $totalSelisihKeseluruhan = 0; // Total selisih untuk seluruh data
                    $count = 0;

                    $allDGMData = [];
                    foreach ($request->dgm as $subArray) {
                        if (is_array($subArray)) {
                            $allDGMData = array_merge($allDGMData, $subArray);
                        }
                    }

                    // Lakukan perhitungan selisih setelah menggabungkan
                    foreach ($allDGMData as $index => $value) {
                        $value = (float) $value; // Pastikan nilai adalah float
                        if ($index === 0) {
                            // Untuk data pertama, kurangkan dengan DGM Awal
                            $selisih = $value - $dgmAwal; // Menggunakan dgmAwal yang terpisah
                        } else {
                            // Untuk data selanjutnya, kurangkan dengan data sebelumnya
                            $previousValue = (float) $allDGMData[$index - 1];
                            $selisih = $value - $previousValue;
                        }
                        

                        if ($value) { // hanya hitung jika ada nilai
                            $totalSelisihKeseluruhan += $selisih; // Tambahkan ke total keseluruhan
                            $count++;
                        }

                        // Menghitung persentase selisih
                        $persentaseSelisih = abs(($selisih - $selisihrataDGM) / $selisihrataDGM * 100);
                        // Simpan data ke dalam array
                        $selisihKey = "selisihDGM" . ($index + 1); // Menentukan kunci berdasarkan indeks
                        array_push($pengukuranDGMVM, (object) [
                            'nilaiDGM' . ($index + 1) => $value,
                            $selisihKey => number_format($persentaseSelisih, 1), // Menyimpan selisih per data
                        ]);
                    }
                }

                // VS DP
                // AVERAGE
                $arraydP1 = []; // Array untuk menyimpan hasil akhir


                $totalCountPaPs = count($request->avgPaPs); // Hitung total count dari avgPaPs
                $totalAvgPaPs = 0; // Untuk menghitung total nilai avgPaPs

                foreach ($request->PaPs as $key => $value) {
                    // Pastikan avgPaPs[$key] ada
                    $avgPaPsValue = isset($request->avgPaPs[$key]) ? $request->avgPaPs[$key] : 0;
                    $totalAvgPaPs += $avgPaPsValue; // Tambahkan nilai avgPaPs ke total
                    $averageAvgPaPs = $totalCountPaPs > 0 ? $totalAvgPaPs / $totalCountPaPs : 0;
                }
                // Hitung rata-rata

                // Tambahkan rata-rata ke output
                // $pengukuranavgPaPs[] = (object) [
                //     'Rata-rata Avg PaPs' => number_format($averageAvgPaPs, 2) // Format dengan 2 desimal
                // ];
                $pengukuranavgPaPs = floatval(number_format($averageAvgPaPs, 2));

                $metode2 = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $metode4 = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                // Loop untuk menggabungkan data dan menerapkan rumus
                // dd($pengukurandP);
                $i = 1;
                foreach ($pengukurandP as $item) {
                    // dd($item);
                    foreach ($item as $key => $value) {
                        // Jika nilai adalah array, kita bisa menggabungkannya
                        if (is_array($value)) {
                            foreach ($value as $val) {
                                // Terapkan rumus
                                $dP = (float)$val; // Ambil nilai dP dari array
                                $hasil = floatval($metode2->kp) * floatval($metode2->cp) * pow((floatval($TemperaturStack) / ((floatval($metode2->tekanan_udara) - $pengukuranavgPaPs) * floatval($metode4->ms))), 0.5) * pow($dP, 0.5);
                                $hasil = round($hasil, 2);
                                // Simpan hasil ke dalam pengukurandP1
                                $arraydP1[] = [
                                    'nilai_' . $i => $dP,
                                    'hasil_' . $i => number_format($hasil, 1, '.', ''), // Menyimpan hasil perhitungan rumus
                                ];
                                $i++;
                            }
                        }
                    }
                }
                $check = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                if ($check) {
                    return response()->json([
                        'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 5.!'
                    ], 401);
                } else {
                    $data = new DataLapanganIsokinetikPenentuanPartikulat();
                    $data->id_lapangan = $metode2->id_lapangan;
                    if (strtoupper(trim($request->no_sample)) != '')
                        $data->no_sampel = strtoupper(trim($request->no_sample));
                    if ($request->diameter != '')
                        $data->diameter = $request->diameter;
                    if ($request->titik_lintas_partikulat != '')
                        $data->titik_lintas_partikulat = $request->titik_lintas_partikulat;
                    if ($request->data_Y != '')
                        $data->data_Y = $request->data_Y;
                    if ($request->pbarm5 != '')
                        $data->pbar = $request->pbarm5;
                    if ($request->Delta_H != '')
                        $data->Delta_H = $request->Delta_H;
                    if ($request->dn_req != '')
                        $data->dn_req = $request->dn_req;
                    if ($request->k_iso != '')
                        $data->k_iso = $request->k_iso;
                    if ($request->delta_H_req != '')
                        $data->delta_H_req = $request->delta_H_req;
                    if ($request->dgmAwal != '')
                        $data->dgmAwal = $request->dgmAwal;
                    if ($request->jam_pengambilan != '')
                        $data->waktu = $request->jam_pengambilan;
                    if ($request->dn_actual != '')
                        $data->dn_actual = $request->dn_actual;
                    if ($request->impinger1 != '')
                        $data->impinger1 = $request->impinger1;
                    if ($request->impinger2 != '')
                        $data->impinger2 = $request->impinger2;
                    if ($request->impinger3 != '')
                        $data->impinger3 = $request->impinger3;
                    if ($request->impinger4 != '')
                        $data->impinger4 = $request->impinger4;
                    if ($request->Vs != '')
                        $data->Vs = $request->Vs;
                    if ($request->rataRataSelisihDGM != '')
                        $data->rataselisihdgm = $request->rataRataSelisihDGM;
                    $data->temperatur_stack = $TemperaturStackFormatted;
                    $data->data_total_vs = $arraydP1;
                    $data->delta_vm = $pengukuranDGMVM;
                    $data->DGM = $pengukuranDGM;
                    $data->dP = $pengukurandP;
                    $data->PaPs = $pengukuranPaPs;
                    $data->dH = $pengukurandH;
                    $data->Stack = $pengukuranStack;
                    $data->Meter = $pengukuranMeter;
                    $data->Vp = $pengukuranVp;
                    $data->Filter = $pengukuranFilter;
                    $data->Oven = $pengukuranOven;
                    $data->exit_impinger = $pengukuranexit_impinger;
                    $data->Probe = $pengukuranProbe;
                    $data->sebelumpengujian = $arrsebelumpengujian;
                    $data->sesudahpengujian = $arrsesudahpengujian;
                    if ($request->CO2 != '')
                        $data->CO2 = $request->CO2;
                    if ($request->CO != '')
                        $data->CO = $request->CO;
                    if ($request->NOx != '')
                        $data->NOx = $request->NOx;
                    if ($request->SO2 != '')
                        $data->SO2 = $request->SO2;
                    if ($request->Total_time != '')
                        $data->Total_time = $request->Total_time;
                    if ($request->foto_lokasi_sampel != '')
                        $data->foto_lokasi_sampel = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                    if ($request->foto_sampl != '')
                        $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '')
                        $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                    if ($request->permission != '')
                        $data->permission = $request->permission;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

                    if($orderDetail->tanggal_terima == null){
                        $orderDetail->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);
                        $orderDetail->save();
                    }

                    InsertActivityFdl::by($this->user_id)->action('input')->target("Isokinetik Penentuan Partikulat pada nomor sampel $request->no_sample")->save();
                    
                    DB::commit();
                    return response()->json([
                        'message' => 'Data berhasil disimpan.'
                    ], 200);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e.getMessage(),
                    'line' => $e.getLine(),
                    'code' => $e.getCode()
                ], 401);
            }
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganIsokinetikPenentuanPartikulat::with(['detail', 'method2'])
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7));

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
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
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