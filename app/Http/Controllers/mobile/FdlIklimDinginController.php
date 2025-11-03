<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganIklimDingin;

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

class FdlIklimDinginController extends Controller
{
    public function getSampel(Request $request)
    {
        if (isset($request->no_sampel) && $request->no_sampel != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'iklim_dingin')->where('is_active', 1)->first();
            $listParameter = json_decode($parameter->parameters, true);

            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))
            ->where('kategori_3', '21-Iklim Kerja')
            ->where(function ($q) use ($listParameter) {
                foreach ($listParameter as $keyword) {
                    $q->orWhere('parameter', 'like', "%$keyword%");
                }
            })
            ->where('is_active', 1)
            ->first();

            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sampel tidak ditemukan di kategori iklim kerja'
                ], 401);
            } else {
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                return response()->json([
                    'no_sampel'    => $data->no_sampel,
                    'sub_kategori'        => $cek->nama_sub_kategori,
                    'keterangan' => $data->keterangan_1,
                    'id_kategori_3' => explode('-', $data->kategori_3)[0],
                    'id_kategori_2' => explode('-', $data->kategori_2)[0],
                    'parameter' => $data->parameter
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
        try {
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
            if ($request->foto_lokasi == '') {
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
            $cek_nil = DataLapanganIklimDingin::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
            foreach ($cek_nil as $key => $value) {
                $durasi = $value->shift_pengambilan;
                $durasi = explode("-", $durasi);
                $durasi = $durasi[1];
                $nilai_array[$key] = str_replace('"', "", $durasi);
            }

            if (in_array($request->shift_pengambilan, $nilai_array)) {
                return response()->json([
                    'message' => 'Pengambilan Shift ' . $request->shift_pengambilan . ' sudah ada !'
                ], 401);
            }

            $shift_pengambilan = $request->kategori_uji . '-' . $request->shift_pengambilan;
            
            $a = count($request->suhu_kering);
            $nilai_pengukuran = array();
            for ($i = 0; $i < $a; $i++) {
                $no = $i + 1;
                $nilai_pengukuran['Data-' . $no] = [
                    'suhu_kering' => $request->suhu_kering[$i],
                    'kecepatan_angin' => $request->laju_ventilasi[$i]
                ];
            }
            $data = new DataLapanganIklimDingin();
            $data->no_sampel                 = strtoupper(trim($request->no_sampel));
            if ($request->penamaan_titik != '') $data->keterangan            = $request->penamaan_titik;
            if ($request->penamaan_tambahan != '') $data->keterangan_2          = $request->penamaan_tambahan;
            if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
            if ($request->lat != '') $data->latitude                            = $request->lat;
            if ($request->longi != '') $data->longitude                        = $request->longi;
            if ($request->id_kategori_3 != '') $data->kategori_3                  = $request->id_kategori_3;
            if ($request->jenis_ruangan != '') $data->lokasi                         = $request->jenis_ruangan;
            if ($request->sumber_dingin != '') $data->sumber_dingin                      = $request->sumber_dingin;
            if ($request->jarak_sumber_dingin != '') $data->jarak_sumber_dingin                        = $request->jarak_sumber_dingin;
            if ($request->waktu_paparan != '') $data->akumulasi_waktu_paparan                    = $request->waktu_paparan;
            if ($request->waktu_kerja != '') $data->waktu_kerja                        = $request->waktu_kerja;
            if ($request->jam_pengambilan != '') $data->jam_awal_pengukuran                        = $request->jam_pengambilan;
            if ($request->apd_khusus != '') $data->apd_khusus                        = $request->apd_khusus;
            if ($request->tipe_alat != '') $data->tipe_alat                            = $request->tipe_alat;
            if ($request->aktifitas_fisik != '') $data->aktifitas                = $request->aktifitas_fisik;
            if ($request->aktivitas_kerja != '') $data->aktifitas_kerja                = $request->aktivitas_kerja;
            if ($request->kategori_uji != '') $data->kategori_pengujian                  = $request->kategori_uji;
            $data->shift_pengambilan = $shift_pengambilan;
            $data->pengukuran = json_encode($nilai_pengukuran);
            if ($request->jam_selesai != '') $data->jam_akhir_pengujian                      = $request->jam_selesai;
            if ($request->permission != '') $data->permission                      = $request->permission;
            if ($request->foto_lokasi != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi, 1, $this->user_id);
            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
            $data->created_by                     = $this->karyawan;
            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();
    
            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();

            if($orderDetail->tanggal_terima == null){
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d H:i:s');
                $orderDetail->save();
            }

            $this->resultx = "Data Sampling IKLIM DINGIN Dengan No Sample $request->no_sampel berhasil disimpan oleh $this->karyawan";

            InsertActivityFdl::by($this->user_id)->action('input')->target("Iklim Dingin pada nomor sampel $request->no_sampel")->save();

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganIklimDingin::with('detail')
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
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();

            $no_sampel = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Iklim Dingin dengan nomor sampel $no_sampel")->save();

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
        $data = DataLapanganIklimDingin::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sampel'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'   => $data->keterangan_2,
                'lat'            => $data->latitude,
                'long'           => $data->longitude,

                'kateg_i'        => $data->kategori_pengujian,
                'apd'            => $data->apd_khusus,
                'aktifitas'      => $data->aktifitas,
                'lokasi'         => $data->lokasi,
                'sumber'         => $data->sumber_dingin,
                'jarak'          => $data->jarak_sumber_dingin,
                'paparan'        => $data->akumulasi_waktu_paparan,
                'kerja'          => $data->waktu_kerja,
                'mulai'          => $data->jam_awal_pengukuran,
                'shift'          => $data->shift_pengambilan,
                'tac_in'         => $data->tac_in,
                'tac_out'        => $data->tac_out,
                'ventilasi'      => $data->ventilasi,
                'akhir'          => $data->jam_akhir_pengujian,
                'pengukuran'     => $data->pengukuran,
                'tipe_alat'      => $data->tipe_alat,
                'aktifitas_kerja' => $data->aktifitas_kerja,

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
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();

            $no_sampel = $data->no_sampel;

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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Iklim Dingin dengan nomor sampel $no_sampel")->save();

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