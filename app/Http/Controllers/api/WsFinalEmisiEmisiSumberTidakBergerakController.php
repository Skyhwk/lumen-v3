<?php
namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\EmisiCerobongHeader;
use App\Models\HistoryAppReject;
use App\Models\IsokinetikHeader;
use App\Models\MasterBakumutu;
use App\Models\MasterRegulasi;
use App\Models\MdlEmisi;
use App\Models\OrderDetail;
use App\Models\Subkontrak;
use App\Models\WsValueEmisiCerobong;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class WsFinalEmisiEmisiSumberTidakBergerakController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with(['dataLapanganEmisiKendaraan', 'dataLapanganEmisiCerobong'])->where('is_active', $request->is_active)
            ->where('kategori_2', '5-Emisi')
            ->where('kategori_3', '34-Emisi Sumber Tidak Bergerak')
        	->where('parameter', 'not like', '%Iso-%')
            ->where('status', 0)
            ->whereNotNull('tanggal_terima')
            ->whereMonth('tanggal_terima', explode('-', $request->date)[1])
            ->whereYear('tanggal_terima', explode('-', $request->date)[0])
            ->orderByDesc('tanggal_terima');

        return Datatables::of($data)->make(true);
    }
    public function detail(Request $request)
    {
        // $paramOrder = $request->paramOrder;
        // dd($paramOrder);
        $parameter = OrderDetail::where('no_sampel', $request->no_sampel)
            ->where('is_active', true)
            ->first()->parameter;

        $parameterArray = json_decode($parameter, true);
        
        $parameterNames = array_map(function ($param) {
            $parts = explode(';', $param);
            return trim(end($parts));
        }, $parameterArray);

        $data1 = IsokinetikHeader::with(['ws_value'])
            ->where('is_approved', 1)
            ->where('is_active', 1)
            ->where('parameter', '!=', 'Iso-ResTime')
            ->whereIn('parameter', $parameterNames)
            ->where('no_sampel', $request->no_sampel)
            ->get()->map(function ($item) {
            $item['data_type'] = 'isokinetik_header';
            return $item;
        });

        $data2 = EmisiCerobongHeader::with(['ws_value_cerobong', 'data_lapangan'])
            ->where('no_sampel', $request->no_sampel)
            ->where('is_approved', 1)
            ->whereIn('parameter', $parameterNames)
            ->where('is_active', 1)
            ->get()
            ->map(function ($item) {
                $item['data_type'] = 'emisi_cerobong_header';

                if (
                    isset($item['data_lapangan']['arah_pengamat_opasitas']) &&
                    is_string($item['data_lapangan']['arah_pengamat_opasitas'])
                ) {
                    $item['data_lapangan']['arah_pengamat_opasitas'] = json_decode($item['data_lapangan']['arah_pengamat_opasitas'], true);
                }

                if (
                    isset($item['data_lapangan']['jarak_pengamat']) &&
                    is_string($item['data_lapangan']['jarak_pengamat'])
                ) {
                    $item['data_lapangan']['jarak_pengamat'] = json_decode($item['data_lapangan']['jarak_pengamat'], true);
                }

                if (
                    isset($item['data_lapangan']['warna_emisi']) &&
                    is_string($item['data_lapangan']['warna_emisi'])
                ) {
                    $item['data_lapangan']['warna_emisi'] = json_decode($item['data_lapangan']['warna_emisi'], true);
                }

                if (
                    isset($item['data_lapangan']['warna_latar']) &&
                    is_string($item['data_lapangan']['warna_latar'])
                ) {
                    $item['data_lapangan']['warna_latar'] = json_decode($item['data_lapangan']['warna_latar'], true);
                }

                return $item;
            });

            $data3 = Subkontrak::with(['ws_value_cerobong'])
            ->where('no_sampel', $request->no_sampel)
            ->where('is_approve', 1)
        // ->whereIn('parameter', $paramOrder)
            ->where('is_active', 1)
            ->get()
            ->map(function ($item) {
                $item['data_type'] = 'subkontrak';
                return $item;
            });

        $data1Arr = $data1->toArray();
        $data2Arr = $data2->toArray();
        $data3Arr = $data3->toArray();

        $data = array_merge($data1Arr, $data2Arr, $data3Arr);
        // dd($data);
            
        foreach ($data as &$item) {
            $item['method']            = null;
            $item['baku_mutu']         = null;
            $item['satuan']            = null;
            $item['jenis_persyaratan'] = null;
            if ($request->regulasi) {
                $bakuMutu = MasterBakumutu::where('id_regulasi', explode('-', $request->regulasi)[0])
                    ->where('parameter', $item['parameter'])
                    ->where('is_active', 1)
                    ->first();

                if ($bakuMutu) {
                    $item['method']            = $bakuMutu->method;
                    $item['baku_mutu']         = $bakuMutu->baku_mutu;
                    $item['satuan']            = $bakuMutu->satuan;
                    $item['jenis_persyaratan'] = $bakuMutu->nama_header;
                }
            }
        }

        $getSatuan = new HelperSatuan;

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

        return Datatables::of($data)
            ->addColumn('nilai_uji', function ($item) use ($getSatuan, $getHasilUji) {

                $satuan = $item['satuan'] ?? '-';
                $index  = $getSatuan->emisi($satuan);

                $ws = $item['ws_value_cerobong'] ?? null;
                if (!$ws) return "noWs";

                $ws = (array) $ws;
                $idParameter = $item['id_parameter'] ?? null;

                if ($index === null) {

                    // ✅ HEADER TANPA PARAMETER
                    if ($idParameter === null) {
                        return $ws['f_koreksi_c'] ?? '-';
                    }

                    $nilai = null;

                    for ($i = 0; $i <= 10; $i++) {
                        $key = $i === 0 ? 'f_koreksi_c' : 'f_koreksi_c' . $i;
                        if (!empty($ws[$key])) {
                            $nilai = $ws[$key];
                            break;
                        }
                    }

                    if ($nilai === null) {
                        for ($i = 0; $i <= 10; $i++) {
                            $key = $i === 0 ? 'C' : 'C' . $i;
                            if ($i == 3) {
                                $nilai = !empty($ws[$key]) ? $ws[$key] : ($ws['C3_persen'] ?? null);
                                break;
                            } elseif (!empty($ws[$key])) {
                                $nilai = $ws[$key];
                                break;
                            }
                        }
                    }

                    $nilai = $nilai ?? '-';

                    return $getHasilUji('', $idParameter, $nilai);
                }

                // ===== index !== null =====

                $field       = $index;
                $hasilKey    = "C$field";
                $fKoreksiKey = "f_koreksi_c$field";

                $nilai = $ws[$fKoreksiKey] ?? $ws[$hasilKey] ?? null;

                if ($nilai !== null) {
                    return $getHasilUji($index, $idParameter, $nilai);
                }

                $nilai = $ws['f_koreksi_c'] ?? $ws['C'] ?? '-';

                return $getHasilUji($index, $idParameter, $nilai);
            })
            ->make(true);

    }

    public function detailLapangan(Request $request)
    {
        try {

            $data = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sampel)
                ->get();

            return response()->json([
                'data'    => $data,
                'success' => true,
                'status'  => 200,
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'success' => false,
                'status'  => 400,
            ]);
        }
    }

    public function koreksiO2(Request $request)
    {
        // dd('masuk');
        DB::beginTransaction();
        try {

            $faktor_koreksi = (float) $request->faktor_koreksi;
            $dataLapangan   = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sampel)->first();

            $order_detail = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

            if ($order_detail != null) {

                // Ambil seluruh data dari model EmisiC
                $emisiCerobong = EmisiCerobongHeader::where('no_sampel', $request->no_sampel)
                    ->where('parameter', $request->parameter)
                    ->where('is_active', true)
                    ->first();

                if ($emisiCerobong != null) {

                    $ws_value = WsValueEmisiCerobong::where('no_sampel', $request->no_sampel)
                        ->where('id_emisi_cerobong_header', $emisiCerobong->id)
                        ->where('is_active', true)
                        ->first();
                    if ($request->faktor_koreksi > 0) {
                        $C1_value = $ws_value->f_koreksi_c1 !== null ? floatval($ws_value->f_koreksi_c1) : floatval($ws_value->C1);
                        $hasil    = ((21 - $faktor_koreksi) / (21 - floatval($dataLapangan->O2))) * $C1_value;
                        if ($ws_value != null) {
                            $ws_value->input_koreksi = $request->faktor_koreksi;
                            $ws_value->nil_koreksi   = number_format((float) $hasil, 4, '.', '');
                                                                                                               // $ws_value->keterangan_koreksi = json_encode($request->jenis_koreksi);
                            $keterangan                   = $request->jenis_koreksi;                           // Jenis Koreksi
                            $keterangan[]                 = 'Angka koreksi ' . $request->faktor_koreksi . '%'; // Menambahkan keterangan angka koreksi
                            $ws_value->keterangan_koreksi = json_encode($keterangan);
                            $ws_value->C3_persen          = $dataLapangan->O2;
                            $ws_value->is_active          = true;
                            $ws_value->updated_at         = Carbon::now();
                            $ws_value->updated_by         = $this->karyawan;
                            // dd($ws_value);
                            $ws_value->save();
                        } else {
                            return response()->json([
                                'message' => 'Data C1 tidak ditemukan.',
                                'success' => false,
                                'status'  => 404,
                            ], 404);
                        }
                    } else {
                        if ($ws_value != null) {
                            $ws_value->keterangan_koreksi = json_encode($request->jenis_koreksi);
                            $ws_value->updated_at         = Carbon::now();
                            $ws_value->updated_by         = $this->karyawan;
                            // dd($ws_value);
                            $ws_value->save();
                        } else {
                            return response()->json([
                                'message' => 'Data C1 tidak ditemukan.',
                                'success' => false,
                                'status'  => 404,
                            ], 404);
                        }
                    }

                    DB::commit();
                    return response()->json(['message' => 'Data berhasil diupdate.', 'success' => true], 200);
                } else {
                    return response()->json(['message' => 'Data Subkontrak tidak ditemukan.'], 404);
                }
            } else {
                return response()->json(['message' => 'Data tidak ditemukan di kategori EMISI.'], 404);
            }
        } catch (\Exception $e) {
            DB::rollback(); // Rollback transaksi jika ada kesalahan
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function rejectAnalys(Request $request)
    {
        try {
            if ($request->type == 'emisi_cerobong_header') {
                $data = EmisiCerobongHeader::where('id', $request->id)->update([
                    'is_approved'  => 0,
                    'notes_reject' => $request->note,
                    'rejected_by'  => $this->karyawan,
                    'rejected_at'  => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                if ($data) {
                    return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
                } else {
                    return response()->json(['message' => 'Gagal']);
                }
            } else if ($request->type == 'subkontrak') {
                $data = SubKontrak::where('id', $request->id)->update([
                    'is_approve'   => 0,
                    'is_active'    => false,
                    'notes_reject' => $request->note,
                    'rejected_by'  => $this->karyawan,
                    'rejected_at'  => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                if ($data) {
                    return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
                } else {
                    return response()->json(['message' => 'Gagal']);
                }
            } else {
                return response()->json([
                    'message' => 'Data Tidak Ditemukan',
                ], 401);
            }
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage(), 'success' => false, 'status' => 400]);
        }
    }

    public function approveWSApi(Request $request)
    {

        if ($request->type == "emisi_cerobong_header") {
            $data = EmisiCerobongHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
            // dd($data);
            if ($data) {
                $cek       = EmisiCerobongHeader::where('id', $data->id)->first();
                $cek->lhps = 0;
                $cek->save();

                $has       = EmisiCerobongHeader::where('id', $request->id)->first();
                $has->lhps = 0;
                $has->save();

                return response()->json([
                    'message' => 'Data has ben Rejected',
                    'success' => true,
                    'status'  => 200,
                ], 201);
            } else {
                $dat       = EmisiCerobongHeader::where('id', $request->id)->first();
                $dat->lhps = 1;
                $dat->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'success' => true,
                    'status'  => 200,
                ], 200);
            }
        } else if ($request->type == "subkontrak") {
            $cek = Subkontrak::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();

            if ($cek) {
                $cek = Subkontrak::where([
                    'id' => $cek->id,
                ])->update([
                    'lhps'        => 0,
                    'is_reject'   => 1,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan,
                ]);
                $cek = Subkontrak::where([
                    'id' => $request->id,
                ])->update([
                    'lhps'        => 0,
                    'is_reject'   => 1,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan,
                ]);

                return response()->json([
                    'message' => 'Data has ben Rejected',
                    'success' => true,
                    'status'  => 200,
                ], 201);
            }

            $data = Subkontrak::where([
                'id' => $request->id,
            ])->update([
                'lhps' => 1,
            ]);

            if ($data) {
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'success' => true,
                    'status'  => 200,
                ], 200);
            }
        } else if ($request->type == "isokinetik_header") {
            $data = IsokinetikHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
            // dd($data);
            if ($data) {
                $cek       = IsokinetikHeader::where('id', $data->id)->first();
                $cek->lhps = 0;
                $cek->save();

                $has       = IsokinetikHeader::where('id', $request->id)->first();
                $has->lhps = 0;
                $has->save();

                return response()->json([
                    'message' => 'Data has ben Rejected',
                    'success' => true,
                    'status'  => 200,
                ], 201);
            } else {
                $dat       = IsokinetikHeader::where('id', $request->id)->first();
                $dat->lhps = 1;
                $dat->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'success' => true,
                    'status'  => 200,
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Approve',
                'success' => false,
                'status'  => 401,
            ], 401);
        }
    }

    public function KalkulasiKoreksi(Request $request)
    {
        try {
            $id        = $request->id;
            $no_sampel = $request->no_sample;
            $parameter = $request->parameter;

            $faktor_koreksi = (float) $request->faktor_koreksi;
            $hasilujic      = $request->hasil_c;
            $satuan         = $request->satuan;

            $hasil = $this->hitungKoreksi($request, $id, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $satuan);
            if (is_numeric($hasil)) {
                $hasil = number_format((float) $hasil, 4, '.', '');
            }

            return response()->json(['hasil' => $hasil]);
        } catch (\Exception $e) {
            dd($e);
            \Log::error('Error dalam KalkulasiKoreksi: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function hitungKoreksi($request, $id, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $satuan)
    {
        try {
            $hasil = 0;
            $hasil = $this->rumusEmisiC($request, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $satuan);

            return $hasil;
        } catch (\Exception $e) {
            dd($e);
            \Log::error('Error dalam hitungKoreksi: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rumusEmisiC($request, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $satuan)
    {
        function removeSpecialChars($value)
        {
            return is_string($value) ? str_replace('<', '', $value) : $value;
        }
        function cekSpecialChar($value)
        {
            return is_string($value) && strpos($value, '<') !== false;
        }

        function applyFormula($value, float $factor, $parameter)
        {
            $cleanedValue = removeSpecialChars($value);
            if ($cleanedValue == null || $cleanedValue === '') {
                return '';
            }
            $hasil = '';
            $MDL   = floatval($cleanedValue);
            if (! is_nan($MDL)) {
                if (cekSpecialChar($value)) {
                    $hasil = $factor;
                    return $hasil;
                } else {
                    $hasil = ($MDL * ($factor / 100));
                    return number_format((float) $hasil, 4, '.', '');
                }
            }
            return '';
        }

        $hasil = ['hasilc' => ''];
        $cases = [
            'SO2',
            'NO2',
            'NOx',
            'NO',
            "C O",
        ];

        foreach ($cases as $case) {
            if ($case == $parameter) {
                $hasil['hasilc'] = (empty($hasilujic)) ? '-' : applyFormula($hasilujic, $faktor_koreksi, $parameter);
                break;
            }
        }

        return $hasil;
    }

    private function mapSatuanKeField($satuan)
    {
        $map = [
            "ug/nm3"   => "f_koreksi_c",
            "mg/nm3"   => "f_koreksi_c1",
            "ppm"      => "f_koreksi_c2",
            "ug/m3"    => "f_koreksi_c3",
            "mg/m3"    => "f_koreksi_c4",
            "%"        => "f_koreksi_c5",
            "°c"       => "f_koreksi_c6",
            "g/gmol"   => "f_koreksi_c7",
            "m3/s"     => "f_koreksi_c8",
            "m/s"      => "f_koreksi_c9",
            "kg/tahun" => "f_koreksi_c10",
        ];

        // Normalisasi satuan (lowercase, hilangkan spasi, ubah ³ jadi 3)
        $key = strtolower(str_replace(['³', ' '], ['3', ''], $satuan));

        return $map[$key] ?? null;
    }

    private function handleEmisic($request, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $satuan)
    {
        DB::beginTransaction();
        try {

            $targetField = $this->mapSatuanKeField($satuan);

            if (! $targetField) {
                return response()->json([
                    'message' => "Satuan tidak dikenali untuk koreksi: {$satuan}",
                    'success' => false,
                    'status'  => 400,
                ], 400);
            }

            // Ambil seluruh data dari model EmisiC
            $emisiC = EmisiCerobongHeader::where('no_sampel', $no_sampel)
                ->where('parameter', $parameter)
                ->where('is_active', 1)
                ->first();

            if ($emisiC != null) {
                $valuews = WsValueEmisiCerobong::where('no_sampel', $no_sampel)
                    ->where('id_emisi_cerobong_header', $emisiC->id)
                    ->where('is_active', 1)
                    ->first();

                if ($emisiC->tipe_koreksi == null) {
                    $nomor = 1;
                } else {
                    if ($emisiC->tipe_koreksi < 3) {
                        $nomor = $emisiC->tipe_koreksi + 1;
                    } else {
                        return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.', 'success' => false, 'status' => 400], 400);
                    }
                }

                $emisiC->tipe_koreksi = $nomor;
                $emisiC->save();

                $raw = trim($hasilujic);
                if ($raw === '' || $raw === null) {
                    $nilai = null;
                } elseif (str_contains($raw, '<')) {
                    // MDL case, simpan apa adanya, bersih kan spasi
                    $nilai = str_replace(' ', '', $raw);
                } else {
                    $nilai = number_format((float) $raw, 4, '.', '');
                }

                if ($valuews) {
                    $valuews->input_koreksi = $faktor_koreksi;
                    $valuews->satuan        = $satuan;
                    $valuews->$targetField  = $nilai;
                    $valuews->save();
                } else {
                    return response()->json(['message' => 'Data Valuews tidak ditemukan.', 'success' => false, 'status' => 404], 404);
                }

                DB::commit(); // Commit transaksi jika semua berhasil
                return response()->json(['message' => 'Data berhasil diupdate.', 'vlue' => $valuews, 'success' => true, 'status' => 200], 200);
            }
        } catch (\Exception $e) {
            DB::rollback(); // Rollback transaksi jika ada kesalahan
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage() . $e->getLine()], 500);
        }
    }

    public function saveData(Request $request)
    {
        $type_koreksi = $request->type;

        $id        = $request->id;
        $no_sampel = $request->no_sampel;
        $parameter = $request->parameter;
        $satuan    = $request->satuan;
        $hasilujic = $request->hasil_c;
        if ($hasilujic == null || $hasilujic == '-') {
            return response()->json(['message' => 'Hasil Uji tidak boleh kosong atau - .'], 400);
        }
        $faktor_koreksi = (float) $request->faktor_koreksi;

        if ($type_koreksi) {
            switch ($type_koreksi) {
                case 'emisic':
                    // Proses untuk emisic
                    return $this->handleEmisic($request, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $satuan);

                default:
                    return response()->json(['message' => 'Type koreksi tidak valid.'], 400);
            }
        } else {
            return response()->json(['message' => 'Type koreksi harus diisi.'], 400);
        }
    }

    public function validasiApproveWSApi(Request $request)
    {
        // dd($request->all());
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
                    'menu'        => 'WS Final Emisi',
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

    public function addValue(Request $request)
    {
        DB::beginTransaction();
        try {
            $subKontrak = Subkontrak::create([
                'category_id'     => $request->category_id,
                'no_sampel'       => $request->no_sampel,
                'parameter'       => explode(",", $request->parameter)[1],
                'jenis_pengujian' => "sample",
                'lhps'            => 0,
                'is_approve'      => 1,
                'approved_by'     => $this->karyawan,
                'created_at'      => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by'      => $this->karyawan,
            ]);

            $data                = new WsValueEmisiCerobong();
            $data->id_subkontrak = $subKontrak->id;
            $data->no_sampel     = $request->no_sampel;
            $data->id_parameter  = explode(",", $request->parameter)[0];
            $data->suhu          = $request->suhu;
            $data->Pa            = $request->Pa;
            $data->C             = $request->C;
            $data->C1            = $request->C1;
            $data->is_active     = true;
            $data->created_at    = Carbon::now()->format('Y-m-d H:i:s');
            $data->created_by    = $this->karyawan;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil Disimpan',
                'status'  => 200,
                'success' => true,
            ], 200);
        } catch (exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 500,
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::where('id_kategori', $request->id_kategori)
            ->where('is_active', '1')->get();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function ubahRegulasi(Request $request)
    {
        DB::beginTransaction();
        try {
            $regulasi       = MasterRegulasi::where('id', $request->regulasi)->first();
            $new_regulasi   = [$request->regulasi . '-' . $regulasi->peraturan];
            $data           = OrderDetail::where('id', $request->id)->first();
            $data->regulasi = $new_regulasi;
            $data->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Regulasi berhasil diubah!',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }
}
