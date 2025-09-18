<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganGetaranPersonal;

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

class FdlGetaranPersonalController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameterList = ParameterFdl::select('parameters')->where('is_active', 1)->where('nama_fdl','getaran_personal')->where('kategori','4-Udara')->first();
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->where(function($q) use ($parameterList) {
                foreach ($parameterList as $param) {
                    $q->orWhere('parameter', 'like', "%$param%");
                }
            })
            ->where('is_active', 1)->first();
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
            $fdl = DataLapanganGetaranPersonal::where('no_sampel', $request->no_sampel)->first();
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

                $pengukuran = [];
                $a = count($request->x1);
                for ($i = 0; $i < $a; $i++) {
                    $no = $i + 1;
                    $pengukuran['Data-' . $no] = !isset($request->x4) ? [
                        'x1' => $request->x1[$i],
                        'x2' => $request->x2[$i],
                        'y1' => $request->y1[$i],
                        'y2' => $request->y2[$i],
                        'z1' => $request->z1[$i],
                        'z2' => $request->z2[$i],
                        'percepatan1' => $request->percepatan1[$i],
                        'percepatan2' => $request->percepatan2[$i],
                        'durasi_paparan' => $request->paparan[$i],
                    ] : 
                    [
                        'x1' => $request->x1[$i],
                        'x2' => $request->x2[$i],
                        'x3' => $request->x3[$i],
                        'x4' => $request->x4[$i],
                        'y1' => $request->y1[$i],
                        'y2' => $request->y2[$i],
                        'y3' => $request->y3[$i],
                        'y4' => $request->y4[$i],
                        'z1' => $request->z1[$i],
                        'z2' => $request->z2[$i],
                        'z3' => $request->z3[$i],
                        'z4' => $request->z4[$i],
                        'percepatan1' => $request->percepatan1[$i],
                        'percepatan2' => $request->percepatan2[$i],
                        'percepatan3' => $request->percepatan3[$i],
                        'percepatan4' => $request->percepatan4[$i],
                        'durasi_paparan' => $request->paparan[$i],
                    ];
                }

                $data = new DataLapanganGetaranPersonal();
                $data->no_sampel                                                = $request->no_sample;
                if ($request->keterangan != '') $data->keterangan             = $request->keterangan;
                if ($request->koordinat != '') $data->titik_koordinat              = $request->koordinat;
                if ($request->latitude != '') $data->latitude                        = $request->latitude;
                if ($request->longitude != '') $data->longitude                     = $request->longitude;
                if ($request->id_kateg != '') $data->kategori_3                 = $request->id_kateg;
                if ($request->metode_peng != '') $data->metode                  = $request->metode_peng;
                if ($request->sumber != '') $data->sumber_getaran               = $request->sumber;
                if ($request->keterangan_2 != '') $data->keterangan_2           = $request->keterangan_2;
                if ($request->paparan != '') $data->durasi_paparan              = json_encode($request->paparan);
                if ($request->jam_pengambilan != '') $data->waktu_pengukuran    = $request->jam_pengambilan;
                if ($request->kerja != '') $data->durasi_kerja                  = $request->kerja;
                if ($request->kondisi != '') $data->kondisi                     = $request->kondisi;
                if ($request->intensitas != '') $data->intensitas               = $request->intensitas;
                if ($request->satuanKecepatanX != '') $data->satuan_kecepatan_x = $request->satuanKecepatanX;
                if ($request->satuanKecepatanY != '') $data->satuan_kecepatan_y = $request->satuanKecepatanY;
                if ($request->satuanKecepatanZ != '') $data->satuan_kecepatan_z = $request->satuanKecepatanZ;
                if ($request->satuanAeq != '') $data->satuan_kecepatan_aeq      = $request->satuanAeq;
                if ($request->nama_pekerja != '') $data->nama_pekerja           = $request->nama_pekerja;
                if ($request->jenis_pekerja != '') $data->jenis_pekerja         = $request->jenis_pekerja;
                if ($request->lokasi_unit != '') $data->lokasi_unit             = $request->lokasi_unit;
                if ($request->alat_ukur != '') $data->alat_ukur                 = $request->alat_ukur;
                if ($request->durasi_pengukuran != '') $data->durasi_pengukuran = $request->durasi_pengukuran;
                if ($request->adaptor != '') $data->adaptor                     = $request->adaptor;
                $data->pengukuran                                               = json_encode($pengukuran);
                if (isset($request->ke)) $data->bobot_frekuensi                 = json_encode(["ke" => $request->ke, "kd" => $request->kd, "kf" => $request->kf]);
                if ($request->koordinat_pengukuran != '') $data->posisi_pengukuran = $request->koordinat_pengukuran;
                if ($request->permission != '') $data->permission                   = $request->permission;
                if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                                               = $this->karyawan;
                $data->created_at                                               = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;

                InsertActivityFdl::by($this->user_id)->action('input')->target("Getaran Personal pada nomor sampel $request->no_sample")->save();

                DB::commit();
                return response()->json([
                    'message' => "Data Sampling GETARAN PERSONAL Dengan No Sample $request->no_sample berhasil disimpan oleh $nama"
                ], 200);
            }
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],500);
        }
        
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganGetaranPersonal::with('detail')
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
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Getaran Personal dengan nomor sampel $no_sample")->save();

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
        $data = DataLapanganGetaranPersonal::with('detail')->where('id', $request->id)->first();

        return response()->json([
            'id'             => $data->id,
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
            'massage'        => 'get Detail sample lapangan Getaran Personal success',
            'sumber_get'     => $data->sumber_getaran,
            'metode'         => $data->metode,
            'posisi_peng'    => $data->posisi_penguji,
            'Dpaparan'       => $data->durasi_paparan,
            'Dkerja'         => $data->durasi_kerja,
            'kondisi'        => $data->kondisi,
            'intensitas'     => $data->intensitas,
            'satuan'         => $data->satuan,
            'pengukuran'     => $data->pengukuran,
            'tangan'         => $data->tangan,
            'pinggang'       => $data->pinggang,
            'betis'          => $data->betis,
            'satKec'         => $data->satuan_kecepatan,
            'satPer'         => $data->satuan_percepatan,
            'satKecX'        => $data->satuan_kecepatan_x,
            'satKecY'        => $data->satuan_kecepatan_y,
            'satKecZ'        => $data->satuan_kecepatan_z,
            'nama_pekerja'   => $data->nama_pekerja,
            'jenis_pekerja'  => $data->jenis_pekerja,
            'lokasi_unit'    => $data->lokasi_unit,
            'alat_ukur'      => $data->alat_ukur,
            'dur_pengukuran' => $data->durasi_pengukuran,
            'adaptor'        => $data->adaptor,
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
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();

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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Getaran Personal dengan nomor sampel $no_sample")->save();

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
