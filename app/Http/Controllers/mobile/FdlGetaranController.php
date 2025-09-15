<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganGetaran;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;


use App\Models\ParameterFdl;


// SERVICE
use App\Services\SendTelegram;
use App\Services\GetAtasan;
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

class FdlGetaranController extends Controller
{
    public function getSampel(Request $request)
    {
        if (isset($request->no_sampel) && $request->no_sampel != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->whereIn('kategori_3', ['13-Getaran', '14-Getaran (Bangunan)', '15-Getaran (Kejut Bangunan)', 
                '16-Getaran (Kenyamanan & Kesehatan)', '17-Getaran (Lengan & Tangan)','18-Getaran (Lingkungan)', 
                '19-Getaran (Mesin)', '20-Getaran (Seluruh Tubuh)'])
                ->where('is_active', 1)->first();

            $fdl = DataLapanganGetaran::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            }

            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample ini tidak ditemukan di fdl getaran'
                ], 401);
            } else 
            {
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                $paramDecoded = json_decode($data->parameter, true);
                $paramValue = explode(';', $paramDecoded[0])[0];

                return response()->json([
                    'no_sampel'    => $data->no_sampel,
                    'jenis'        => $cek->nama_sub_kategori,
                    'keterangan' => $data->keterangan_1,
                    'id_ket' => explode('-', $data->kategori_3)[0],
                    'id_ket2' => explode('-', $data->kategori_2)[0],
                    'param' => $paramValue
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 401);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganGetaran::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {

                if ($request->jam_pengambilan == '') {
                    return response()->json([
                        'message' => 'Jam pengambilan tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lokasi == '' || $request->foto_lokasi == 'false') {
                    return response()->json([
                        'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lain == '' || $request->foto_lain == 'false') {
                    return response()->json([
                        'message' => 'Foto lain-lain tidak boleh kosong .!'
                    ], 401);
                }
                try {
                    $nilai_pengukuran = array();
                    if ($request->id_kategori_3 == 13 || $request->id_kategori_3 == 14 || $request->id_kategori_3 == 15 || $request->id_kategori_3 == 16 || $request->id_kategori_3 == 18 || $request->id_kategori_3 == 19) {
                        $a = count($request->percepatan_min);
                        for ($i = 0; $i < $a; $i++) {
                            $no = $i + 1;
                            $nilai_pengukuran['Data-' . $no] = [
                                'min_per' => $request->percepatan_min[$i],
                                'max_per' => $request->percepatan_max[$i],
                                'min_kec' => $request->kecepatan_min[$i],
                                'max_kec' => $request->kecepatan_max[$i],
                            ];
                        }
                    } else if ($request->id_kategori_3 == 20) {
                        $a = count($request->percepatan_min_tangan);
                        for ($i = 0; $i < $a; $i++) {
                            $no = $i + 1;
                            $nilai_pengukuran['Data-' . $no] = [
                                'perminT' => $request->percepatan_min_tangan[$i],
                                'permaxT' => $request->percepatan_max_tangan[$i],
                                'kecminT' => $request->kecepatan_min_tangan[$i],
                                'kecmaxT' => $request->kecepatan_max_tangan[$i],
                                'perminP' => $request->percepatan_min_pinggang[$i],
                                'permaxP' => $request->percepatan_max_pinggang[$i],
                                'kecminP' => $request->kecepatan_min_pinggang[$i],
                                'kecmaxP' => $request->kecepatan_max_pinggang[$i],
                                'perminB' => $request->percepatan_min_betis[$i],
                                'permaxB' => $request->percepatan_max_betis[$i],
                                'kecminB' => $request->kecepatan_min_betis[$i],
                                'kecmaxB' => $request->kecepatan_max_betis[$i],
                            ];
                        }
                    } else if ($request->id_kategori_3 == 17) {
                        $a = count($request->percepatan_min_tangan);
                        for ($i = 0; $i < $a; $i++) {
                            $no = $i + 1;
                            $nilai_pengukuran['Data-' . $no] = [
                                'perminT' => $request->percepatan_min_tangan[$i],
                                'permaxT' => $request->percepatan_max_tangan[$i],
                                'kecminT' => $request->kecepatan_min_tangan[$i],
                                'kecmaxT' => $request->kecepatan_max_tangan[$i],
                                'perminP' => $request->percepatan_min_pinggang[$i],
                                'permaxP' => $request->percepatan_max_pinggang[$i],
                                'kecminP' => $request->kecepatan_min_pinggang[$i],
                                'kecmaxP' => $request->kecepatan_max_pinggang[$i],
                                1
                            ];
                        }
                    }

                    $data = new DataLapanganGetaran();
                    $data->no_sampel                 = strtoupper(trim($request->no_sampel));
                    if ($request->penamaan_titik != '') $data->keterangan          = $request->penamaan_titik;
                    if ($request->koordinat != '') $data->titik_koordinat           = $request->koordinat;
                    if ($request->latitude != '') $data->latitude                          = $request->latitude;
                    if ($request->longitude != '') $data->longitude                      = $request->longitude;

                    if ($request->penamaan_tambahan != '') $data->keterangan_2        = $request->penamaan_tambahan;
                    if ($request->id_kategori_3 != '') $data->kategori_3                = $request->id_kategori_3;
                    if ($request->jam_pengambilan != '') $data->waktu_pengukuran                      = $request->jam_pengambilan;
                    if ($request->sumber_getaran != '') $data->sumber_getaran                = $request->sumber_getaran;
                    if ($request->jarak_getaran != '') $data->jarak_sumber_getaran                  = $request->jarak_getaran;
                    if ($request->kondisi != '') $data->kondisi                  = $request->kondisi;
                    if ($request->intensitas != '') $data->intensitas            = $request->intensitas;
                    if ($request->frekuensi != '') $data->frekuensi                   = $request->frekuensi;

                    if ($request->satuan_kecepatan != '') $data->satuan_kecepatan                   = $request->satuan_kecepatan;
                    if ($request->satuan_percepatan != '') $data->satuan_percepatan                   = $request->satuan_percepatan;
                    // if ($request->nama_pekerja != '') $data->nama_pekerja                   = $request->nama_pekerja;
                    // if ($request->jenis_pekerja != '') $data->jenis_pekerja                   = $request->jenis_pekerja;
                    // if ($request->lokasi_unit != '') $data->lokasi_unit                   = $request->lokasi_unit;
                    $data->nilai_pengukuran            = json_encode($nilai_pengukuran);

                    if ($request->permission != '') $data->permission                    = $request->permission;
                    if ($request->foto_lokasi != '') $data->foto_lokasi_sampel      = self::convertImg($request->foto_lokasi, 1, $this->user_id);
                    if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                } catch (Exception $e) {
                    dd($e);
                }

                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sampel)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);
                
                InsertActivityFdl::by($this->user_id)->action('input')->target("Getaran pada nomor sampel $request->no_sampel")->save();
                
                DB::commit();
                return response()->json([
                    'message' => "Data Sampling GETARAN LINGKUNGAN Dengan No Sample $request->no_sampel berhasil disimpan oleh $this->karyawan"
                ], 200);
            }
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => $e.getMessage(),
                'code' => $e.getCode(),
                'line' => $e.getLine()
            ]);
        }
    }

    public function index(Request $request)
    {
        $data = DataLapanganGetaran::with('detail','sub_kategori')
            ->where('created_by', $this->karyawan)
            ->where(function ($q) {
                $q->where('is_rejected', 1)
                ->orWhere(function ($q2) {
                    $q2->where('is_rejected', 0)
                        ->whereDate('created_at', '>=', Carbon::now()->subDays(7));
                });
            });

        return Datatables::of($data)->make(true);
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaran::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Getaran dengan nomor sampel $no_sample")->save();

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

    public function detail(Request $request)
    {
        $data = DataLapanganGetaran::with('detail')->where('id', $request->id)->first();

        return response()->json([
            'id'             => $data->id,
            'id_kat'         => $data->kategori_3,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'waktu'          => $data->waktu_pengukuran,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'massage'        => "get Detail sample lapangan Getaran success",
            'sumber_get'     => $data->sumber_getaran,
            'jarak_get'      => $data->jarak_sumber_getaran,
            'kondisi'        => $data->kondisi,
            'intensitas'     => $data->intensitas,
            'frek'           => $data->frekuensi,
            'sat_kec'        => $data->satuan_kecepatan,
            'sat_per'        => $data->satuan_percepatan,
            'pengukuran'     => $data->pengukuran,
            'nilai_peng'     => $data->nilai_pengukuran,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'nama_pekerja'   => $data->nama_pekerja,
            'jenis_pekerja'  => $data->jenis_pekerja,
            'lokasi_unit'    => $data->lokasi_unit,
            'status'         => '200'
        ], 200);
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganGetaran::where('id', $request->id)->first();

                $no_sample = $data->no_sampel;

                $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_kon)) {
                    unlink($foto_kon);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }

                $data->delete();

                InsertActivityFdl::by($this->user_id)->action('delete')->target("Getaran dengan nomor sampel $no_sample")->save();

                DB::commit();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal Delete'
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