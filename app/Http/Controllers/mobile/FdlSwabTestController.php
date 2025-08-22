<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganSwab;

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

class FdlSwabTestController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'swab_test')->where('is_active', 1)->first();
            $listParameter = json_decode($parameter->parameters, true);
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->where(function ($q) use ($listParameter) {
                foreach ($listParameter as $keyword) {
                    $q->orWhere('parameter', 'like', '%' . $keyword . '%');
                }
            })
            ->where('is_active', 1)->first();

            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan pada kategori Swab Test'
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
        try {
            $fdl = DataLapanganSwab::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

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
            if ($request->foto_alat == '') {
                return response()->json([
                    'message' => 'Foto alat tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {
                $data = new DataLapanganSwab();
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->penamaan_titik != '') $data->keterangan            = $request->penamaan_titik;
                if ($request->penamaan_tambahan != '') $data->keterangan_2          = $request->penamaan_tambahan;
                if ($request->id_kategori_3 != '') $data->kategori_3                = $request->id_kategori_3;
                if ($request->kondisi_tempat_sampling != '') $data->kondisi_tempat_sampling            = $request->kondisi_tempat_sampling;
                if ($request->kondisi_sampel != '') $data->kondisi_sampel                    = $request->kondisi_sampel;
                if ($request->jam_pengambilan != '') $data->waktu_pengukuran                        = $request->jam_pengambilan;
                if ($request->suhu != '') $data->suhu                          = $request->suhu;
                if ($request->kelembaban != '') $data->kelembapan                        = $request->kelembaban;
                if ($request->tekanan_udara != '') $data->tekanan_udara                     = $request->tekanan_udara;
                if ($request->luas_area != '') $data->luas_area_swab                          = $request->luas_area;
                if ($request->catatan_sampler != '') $data->catatan                          = $request->catatan_sampler;
                if ($request->permission != '') $data->permission                      = $request->permission;
                if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                if ($request->foto_alat != '') $data->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                                                  = $this->karyawan;
                $data->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                InsertActivityFdl::by($this->user_id)->action('input')->target("Swab Test pada nomor sampel $request->no_sample")->save();

                DB::commit();
                return response()->json([
                    'message' => "Data Sampling FDL SWAB Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
                ], 200);
            }
        
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganSwab::with('detail')
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
            $data = DataLapanganSwab::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;
            $data->is_approve     = true;
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

    public function detail(Request $request)
    {
        $data = DataLapanganSwab::with('detail')->where('id', $request->id)->first();
        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order ?? null,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'   => $data->keterangan_2,
                'lat'            => $data->latitude,
                'long'           => $data->longitude,

                'waktu'          => $data->waktu_pengukuran,
                'kondisi_tem'    => $data->kondisi_tempat_sampling,
                'kondisi'        => $data->kondisi_sampel,
                'luas'           => $data->luas_area_swab,
                'suhu'           => $data->suhu,
                'kelem'          => $data->kelembapan,
                'catatan'        => $data->catatan,
                'tekanan_u'      => $data->tekanan_udara,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'foto_kon'       => $data->foto_kondisi_sampel,
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
            $data = DataLapanganSwab::where('id', $request->id)->first();

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