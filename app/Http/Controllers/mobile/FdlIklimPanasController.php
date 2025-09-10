<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganIklimPanas;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\ParameterFdl;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// SERVICE
use App\Services\SendTelegram;
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

class FdlIklimPanasController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'iklim_panas')->where('is_active', 1)->first();
            $listParameter = json_decode($parameter->parameters, true);

            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->where('kategori_3', '21-Iklim Kerja')
            ->where(function ($q) use ($listParameter) {
                foreach ($listParameter as $keyword) {
                    $q->orWhere('parameter', 'like', "%$keyword%");
                }
            })
            ->first();
            
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                return response()->json([
                    'no_sample'    => $data->no_sampel,
                    'jenis'        => $cek->nama_sub_kategori,
                    'keterangan' => $data->keterangan_1,
                    'id_ket' => explode('-', $data->kategori_3)[0],
                    'id_ket2' => explode('-', $data->kategori_2)[0],
                    'param' => $data->parameter
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
            if ($request->jam_pengambilan == '') {
                return response()->json([
                    'message' => 'Jam mulai tidak boleh kosong .!'
                ], 401);
            }

            if ($request->jam_selesai == '') {
                return response()->json([
                    'message' => 'Jam selesai tidak boleh kosong .!'
                ], 401);
            }

            if ($request->foto_lokasi_sampel == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }

            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }


                $nilai_array = [];
                $cek_nil = DataLapanganIklimPanas::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
                foreach ($cek_nil as $key => $value) {
                    // salah field 05/02/2025
                    // $durasi = $value->shift;
                    $durasi = $value->shift_pengujian;
                    $durasi = explode("-", $durasi);
                    $durasi = $durasi[1];
                    $nilai_array[$key] = str_replace('"', "", $durasi);
                }

                if (in_array($request->shift, $nilai_array)) {
                    return response()->json([
                        'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada !'
                    ], 401);
                }

                $shift_peng = $request->kategori_uji . '-' . $request->shift;

                $a = count($request->ta_in);
                $nilai_pengukuran = array();
                for ($i = 0; $i < $a; $i++) {
                    $no = $i + 1;
                    $nilai_pengukuran['Data-' . $no] = [
                        'tac_in' => $request->ta_in[$i],
                        'tac_out' => $request->ta_out[$i],
                        'tgc_in' => $request->tg_in[$i],
                        'tgc_out' => $request->tg_out[$i],
                        'rh_in' => $request->rh_in[$i],
                        'rh_out' => $request->rh_out[$i],
                        'wbtgc_in' => $request->wbgt_in[$i],
                        'wbtgc_out' => $request->wbgt_out[$i],
                        'wb_in' => $request->wb_in[$i],
                        'wb_out' => $request->wb_out[$i],
                    ];
                }

                $data = new DataLapanganIklimPanas;
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->keterangan != '') $data->keterangan            = $request->keterangan;
                if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                if ($request->koordinat != '') $data->titik_koordinat             = $request->koordinat;
                if ($request->latitide != '') $data->latitude                            = $request->latitide;
                if ($request->longiitude != '') $data->longitude                        = $request->longiitude;
                if ($request->category != '') $data->kategori_3                  = $request->category;
                if ($request->lokasi != '') $data->lokasi                         = $request->lokasi;
                if ($request->sumber != '') $data->sumber_panas                      = $request->sumber;
                if ($request->jarak != '') $data->jarak_sumber_panas                        = $request->jarak;
                if ($request->paparan != '') $data->akumulasi_waktu_paparan                    = $request->paparan;
                if ($request->kerja != '') $data->waktu_kerja                        = $request->kerja;
                if ($request->jam_pengambilan != '') $data->jam_awal_pengukuran                        = $request->jam_pengambilan;
                if ($request->kategori_uji != '') $data->kategori_pengujian                  = $request->kategori_uji;
                $data->shift_pengujian = $shift_peng;
                $data->pengukuran = json_encode($nilai_pengukuran);
                if ($request->cuaca != '') $data->cuaca                       = $request->cuaca;
                if ($request->pakaian != '') $data->pakaian_yang_digunakan                   = $request->pakaian;
                if ($request->matahari != '') $data->terpapar_panas_matahari                 = $request->matahari;
                if ($request->tipe_alat != '') $data->tipe_alat                = $request->tipe_alat;
                if ($request->jam_selesai != '') $data->jam_akhir_pengukuran                      = $request->jam_selesai;
                if ($request->aktifitas != '') $data->aktifitas                      = $request->aktifitas;

                if ($request->permission != '') $data->permission                      = $request->permission;
                if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                     = $this->karyawan;
                $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling IKLIM PANAS Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                InsertActivityFdl::by($this->user_id)->action('input')->target("Iklim Panas pada nomor sampel $request->no_sample")->save();


                // if ($this->pin != null) {

                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }
                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
        }catch(Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'code' => $e.getCode(),
                'line' => $e.getLine()
            ]);
        }
        
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganIklimPanas::with('detail')
            ->where('created_by', $this->karyawan)
            ->where(function ($q) {
                $q->where('is_rejected', 1)
                ->orWhere(function ($q2) {
                    $q2->where('is_rejected', 0)
                        ->whereDate('created_at', '>=', Carbon::now()->subDays(7));
                });
            });

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
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Iklim Panas dengan nomor sampel $no_sample")->save();

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
        $data = DataLapanganIklimPanas::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'   => $data->keterangan_2,
                'lat'            => $data->latitude,
                'long'           => $data->longitude,

                'kateg_i'        => $data->kateg_i,
                'lokasi'         => $data->lokasi,
                'sumber'         => $data->sumber_panas,
                'jarak'          => $data->jarak_sumber_panas,
                'paparan'        => $data->akumulasi_waktu_paparan,
                'kerja'          => $data->waktu_kerja,
                'mulai'          => $data->jam_awal_pengukuran,
                'shift'          => $data->shift_pengujian,
                'tac_in'         => $data->tac_in,
                'tac_out'        => $data->tac_out,
                'tgc_in'         => $data->tgc_in,
                'tgc_out'        => $data->tgc_out,
                'wbtgc_in'       => $data->wbtgc_in,
                'wbtgc_out'      => $data->wbtgc_out,
                'rh_in'          => $data->rh_in,
                'rh_out'         => $data->rh_out,
                'ventilasi'      => $data->ventilasi,
                'akhir'          => $data->jam_akhir_pengukuran,
                'pengukuran'     => $data->pengukuran,
                'tipe_alat'      => $data->tipe_alat,
                'aktifitas'      => $data->aktifitas,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'coor'           => $data->titik_koordinat,
                'status'         => '200'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Data tidak ditemukan..'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();

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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Iklim Panas dengan nomor sampel $no_sample")->save();

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
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