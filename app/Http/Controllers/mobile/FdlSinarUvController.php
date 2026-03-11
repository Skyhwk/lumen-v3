<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganSinarUV;

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

class FdlSinarUvController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('kategori_3', '27-Udara Lingkungan Kerja')
                ->where(function($query) {
                    $query->where('parameter', 'like', '%Sinar UV%');
                })
                ->where('is_active', 1)->first();
            $fdl = DataLapanganSinarUV::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if($fdl){
                return response()->json([
                    'message' => 'No Sampel sudah terinput di data lapangan sinar UV'
                ],401);
            }

            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sampel tidak ditemukan di kategori sinar UV'
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
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
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
            $mata = [];
            foreach ($request->mata as $i => $value) {
                $mata[] = ['Data-' . ($i + 1) => $value];
            }
            $betis = [];
            foreach ($request->betis as $i => $value) {
                $betis[] = ['Data-' . ($i + 1) => $value];
            }
            $siku = [];
            foreach ($request->siku as $i => $value) {
                $siku[] = ['Data-' . ($i + 1) => $value];
            }

            $data = new DataLapanganSinarUV();
            $data->no_sampel                 = strtoupper(trim($request->no_sample));
            if ($request->penamaan_titik != '') $data->keterangan            = $request->penamaan_titik;
            if ($request->penamaan_tambahan != '') $data->keterangan_2            = $request->penamaan_tambahan;
            if ($request->koordinat != '') $data->titik_koordinat             = $request->koordinat;
            if ($request->latitude != '') $data->latitude                            = $request->latitude;
            if ($request->longitude != '') $data->longitude                        = $request->longitude;
            if ($request->id_kategori_3 != '') $data->kategori_3                = $request->id_kategori_3;
            if ($request->lokasi != '') $data->lokasi                         = $request->lokasi;
            if ($request->aktivitas_pekerja != '') $data->aktivitas_pekerja                  = $request->aktivitas_pekerja;
            if ($request->sumber_radiasi != '') $data->sumber_radiasi                        = $request->sumber_radiasi;
            if ($request->waktu_pemaparan != '') $data->waktu_pemaparan                        = $request->waktu_pemaparan;
            if ($request->jam_pengambilan != '') $data->waktu_pengukuran                        = $request->jam_pengambilan;
            if ($request->mata != '') $data->mata         = json_encode($mata);
            if ($request->siku != '') $data->siku         = json_encode($siku);
            if ($request->betis != '') $data->betis        = json_encode($betis);

            if ($request->catatan_sampler != '') $data->catatan_sampler                      = $request->catatan_sampler;
            if ($request->permission != '') $data->permission                      = $request->permission;
            if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
            $data->created_by                     = $this->karyawan;
            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

            if($orderDetail->tanggal_terima == null){
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                $orderDetail->save();
            }

            InsertActivityFdl::by($this->user_id)->action('input')->target("Sinar UV pada nomor sampel $request->no_sample")->save();

            DB::commit();
            return response()->json([
                'message' => "Data Sampling SINAR UV Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
            ], 200);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'message'   => $e->getMessage(),
                'line'      => $e.getLine(),
                'code'      => $e.getCode()
            ]);
        }
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganSinarUV::with('detail')
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

            $data = DataLapanganSinarUV::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve  = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => 'Data has ben Approved',
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganSinarUV::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

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
            'parameter'      => $data->parameter,

            'lokasi'         => $data->lokasi,
            'aktivitas'      => $data->aktivitas_pekerja,
            'sumber'         => $data->sumber_radiasi,
            'paparan'        => $data->waktu_pemaparan,
            'waktu'          => $data->waktu_pengukuran,
            'mata'           => $data->mata,
            'siku'           => $data->siku,
            'betis'          => $data->betis,

            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSinarUV::where('id', $request->id)->first();
            $cek2 = DataLapanganSinarUv::where('no_sampel', $data->no_sampel)->get();
            if ($cek2->count() > 1) {
                $data->delete();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            } else {
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
                
                InsertActivityFdl::by($this->user_id)->action('delete')->target("Sinar UV pada nomor sampel $data->no_sampel")->save();
                $data->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }

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