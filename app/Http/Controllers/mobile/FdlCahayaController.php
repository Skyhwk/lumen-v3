<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganCahaya;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;

use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlCahayaController extends Controller
{
    public function getSampel(Request $request)
    {
        if (isset($request->no_sampel) && $request->no_sampel != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('kategori_3', '28-Pencahayaan')->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan di FDL Pencahayaan'
                ], 404);
            } else {
                $fdl = DataLapanganCahaya::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                if ($fdl) {
                    return response()->json([
                        'message' => 'No Sample sudah diinput!.'
                    ], 401);
                }
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                return response()->json([
                    'no_sampel'    => $data->no_sampel,
                    'jenis'        => $cek->nama_sub_kategori,
                    'keterangan' => $data->keterangan_1,
                    'kategori_3' => explode('-', $data->kategori_3)[0],
                    'kategori_2' => explode('-', $data->kategori_2)[0],
                    'parameter' => $data->parameter
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 404);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganCahaya::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {
                if ($request->foto_lokasi == '') {
                    return response()->json([
                        'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lain == '') {
                    return response()->json([
                        'message' => 'Foto Roadmap tidak boleh kosong .!'
                    ], 401);
                }

                if (in_array($request->kategori_pencahayaan, ['Pencahayaan Umum', 'Pencahayaan Setempat'])) {
                    
                    // Validasi untuk Pencahayaan Umum
                    if ($request->kategori_pencahayaan == 'Pencahayaan Umum' && $request->jam_selesai_pengambilan == '') {
                        return response()->json([
                            'message' => 'Jam selesai tidak boleh kosong .!'
                        ], 401);
                    }

                    if ($request->kategori_pencahayaan == 'Pencahayaan Umum' && $request->jam_mulai_pengambilan == '') {
                        return response()->json([
                            'message' => 'Jam mulai tidak boleh kosong .!'
                        ], 401);
                    }

                    if ($request->kategori_pencahayaan == 'Pencahayaan Setempat' && $request->waktu_pengambilan == '') {
                        return response()->json([
                            'message' => 'Waktu Pengambilan tidak boleh kosong .!'
                        ], 401);
                    }
                
                    // Ambil data sesuai jenis pencahayaan
                    $rata_rata   = $request->rata_rata;
                    $keterangan  = $request->keterangan_pengukuran;
                    $kendala     = $request->kendala_lampu;
                    $warna       = $request->warna_lampu;
                
                    if (!empty($rata_rata)) {
                        $pengukuran = [];
                        foreach ($rata_rata as $i => $nilai) {
                            $pengukuran['titik-' . ($i + 1)] = $nilai . '; ' . $keterangan[$i] . '; ' . $kendala[$i] . '; ' . $warna[$i];
                        }
                    }
                
                    if (!empty($keterangan)) {
                        $nilai_pengukuran = [];
                        foreach ($keterangan as $i => $ket) {
                            $nilai_pengukuran[] = [
                                'ulangan-1' => $request->ulangan1[$i] ?? '',
                                'ulangan-2' => $request->ulangan2[$i] ?? '',
                                'ulangan-3' => $request->ulangan3[$i] ?? '',
                                'rata-rata' => $rata_rata[$i] ?? '',
                                'keterangan' => $keterangan[$i],
                                'kendala' => $kendala[$i],
                                'warna' => $warna[$i],
                            ];
                        }
                    }
                }
                
                $data = new DataLapanganCahaya;

                $data->no_sampel                 = strtoupper(trim($request->no_sampel)) ?? null;
                $data->keterangan                = $request->penamaan_titik ?? null;
                $data->informasi_tambahan        = $request->penamaan_tambahan ?? null;
                $data->waktu_pengambilan         = $request->waktu_pengambilan ?? null;
                $data->panjang                   = $request->panjang_area ?? null;
                $data->kategori                  = $request->kategori_pencahayaan ?? null;
                $data->jenis_tempat_alat_sensor  = $request->jenis_tempat_sensor ?? null;
                $data->lebar                     = $request->lebar_area ?? null;
                $data->luas                      = $request->luas_area ?? null;
                $data->jumlah_titik_pengujian    = $request->jumlah_titik ?? null;
                $data->titik_pengujian_sampler   = $request->titik_pengujian ?? null;
                $data->jenis_cahaya              = $request->jenis_cahaya ?? null;
                $data->jenis_lampu               = $request->jenis_lampu ?? null;
                $data->jumlah_tenaga_kerja       = $request->jumlah_tenaga_kerja ?? null;
                $data->jam_mulai_pengukuran      = $request->jam_mulai_pengambilan ?? null;
                $data->jam_selesai_pengukuran    = $request->jam_selesai_pengambilan ?? null;
                $data->aktifitas                 = $request->aktivitas_area ?? null;
                $data->permission                = $request->permission ?? null;
                
                $data->pengukuran                = isset($pengukuran) ? json_encode($pengukuran) : null;
                $data->nilai_pengukuran          = isset($nilai_pengukuran) ? json_encode($nilai_pengukuran) : null;
                
                $data->foto_lokasi_sampel        = $request->foto_lokasi ? self::convertImg($request->foto_lokasi, 1, $this->user_id) : null;
                $data->foto_lain                 = $request->foto_lain ? self::convertImg($request->foto_lain, 3, $this->user_id) : null;
                
                $data->created_by                = $this->karyawan;
                $data->created_at                = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('is_active', 1)->first();

                if($orderDetail->tanggal_terima == null){
                    $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                    $orderDetail->save();
                }

                InsertActivityFdl::by($this->user_id)->action('input')->target("$data->kategori pada nomor sampel $request->no_sampel")->save();

                DB::commit();
                return response()->json([
                    'message' => "Data Sampling PENCAHAYAAN Dengan No Sample $request->no_sampel berhasil disimpan oleh $this->karyawan"
                ], 200);
            }
        }catch(\Exception $e){
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

        $query = DataLapanganCahaya::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereIn('is_rejected', [0, 1])
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
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();
            $no_sampel = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("$data->kategori dengan nomor sampel $no_sampel")->save();


            return response()->json([
                'message' => "Data dengan no Sampel $no_sampel berhasil diapprove oleh $this->karyawan",
                'cat' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();
            $no_sampel = $data->no_sampel;
            $kategori = $data->kategori;
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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("$kategori dengan nomor sampel $no_sampel")->save();

            return response()->json([
                'message' => "Data dengan no Sampel $no_sampel berhasil dihapus oleh $this->karyawan",
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