<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganSampah;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;


// SERVICE
use App\Services\SaveFileServices;
use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlSampahController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->where('kategori_2', '1-Air')
            ->where(function($query) {
                $query->where('parameter', 'like', '%Sampah%');
            })->where('is_active', 1)->first();

            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak memiliki parameter Sampah'
                ], 401);
            } else {
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                return response()->json([
                    'no_sampel'     => $data->no_sampel,
                    'nama_kategori' => $cek->nama_sub_kategori,
                    'keterangan'    => $data->keterangan_1,
                    'id_ket'        => explode('-', $data->kategori_3)[0],
                    'id_ket2'       => explode('-', $data->kategori_2)[0],
                    'parameter'     => $data->parameter
                ], 200);
            }
        }else{
            return response()->json([
                'message' => 'No Sample tidak boleh kosong'
            ]);
        }
    }

    public function index(Request $request)
    {
        $data = DataLapanganSampah::with('detail')
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

    public function store(Request $request){
        $fdl = DataLapanganSampah::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
        if ($fdl) {
            return response()->json([
                'message' => 'No Sample sudah diinput!.'
            ], 401);
        } else {
            if ($request->foto_lokasi_barat == '') {
                return response()->json([
                    'message' => 'Foto lokasi Barat tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lokasi_utara == '') {
                return response()->json([
                    'message' => 'Foto lokasi Utara tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lokasi_selatan == '') {
                return response()->json([
                    'message' => 'Foto lokasi Selatan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lokasi_timur == '') {
                return response()->json([
                    'message' => 'Foto lokasi Timur tidak boleh kosong .!'
                ], 401);
            }

            $data = new DataLapanganSampah();
            $arah = $request->input('arah', []);
            $manual = $request->input('manual', []);
            // Mapping dari key input ke nama field database
            $arahMap = [
                'utara' => 'arah_utara',
                'timur_laut' => 'arah_timur_laut',
                'timur' => 'arah_timur',
                'tenggara' => 'arah_tenggara',
                'selatan' => 'arah_selatan',
                'barat_daya' => 'arah_barat_daya',
                'barat' => 'arah_barat',
                'barat_laut' => 'arah_barat_laut',
            ];

            foreach ($arahMap as $key => $fieldName) {
                if (isset($arah[$key])) {
                    $value = $arah[$key];
                    if ($value === 'manual') {
                        $data->$fieldName = $manual[$key] ?? ''; // ambil dari manual jika dipilih manual
                    } else {
                        $data->$fieldName = $value;
                    }
                }
            }
            $data->no_sampel                                                    = strtoupper(trim($request->no_sampel));
            if ($request->keterangan != '') $data->keterangan                   = $request->keterangan;
            if ($request->penamaan_tambahan != '') $data->informasi_tambahan   = $request->penamaan_tambahan;
            if ($request->koordinat != '') $data->titik_koordinat               = $request->koordinat;
            if ($request->latitude != '') $data->latitude                       = $request->latitude;
            if ($request->longitude != '') $data->longitude                     = $request->longitude;
            if ($request->catatan_sampler != '') $data->catatan_sampler                     = $request->catatan_sampler;
            if ($request->waktu_pengambilan != '') $data->waktu_pengambilan                 = $request->waktu_pengambilan;
            if ($request->permission != '') $data->permission                   = $request->permission;
            if ($request->foto_lokasi_utara != '') $data->foto_lokasi_utara     = self::convertImg($request->foto_lokasi_utara, 1, $this->user_id);
            if ($request->foto_lokasi_selatan != '') $data->foto_lokasi_selatan = self::convertImg($request->foto_lokasi_selatan, 2, $this->user_id);
            if ($request->foto_lokasi_timur != '') $data->foto_lokasi_timur     = self::convertImg($request->foto_lokasi_timur, 3, $this->user_id);
            if ($request->foto_lokasi_barat != '') $data->foto_lokasi_barat     = self::convertImg($request->foto_lokasi_barat, 4, $this->user_id);
            $data->created_by                                                   = $this->karyawan;
            $data->created_at                                                   = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $this->resultx = "Data Sampling FDL SAMPAH Dengan No Sample $request->no_sampel berhasil disimpan oleh $this->karyawan";
            InsertActivityFdl::by($this->user_id)->action('input')->target("Observasi Sampah pada nomor sampel $request->no_sampel")->save();

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganSampah::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Cahaya success';


        return response()->json([
            'id'                    => $data->id,
            'no_sample'             => $data->no_sampel,
            'no_order'              => $data->detail->no_order,
            'sampler'               => $data->created_by,
            'nama_perusahaan'       => $data->detail->nama_perusahaan,
            'keterangan'            => $data->keterangan,
            'lat'                   => $data->latitude,
            'long'                  => $data->longitude,
            'info_tambahan'         => $data->informasi_tambahan,
            'waktu'                 => $data->waktu_pengambilan,
            'tikoor'                => $data->titik_koordinat,
            'foto_lokasi_selatan'   => $data->foto_lokasi_selatan,
            'foto_lokasi_timur'     => $data->foto_lokasi_timur,
            'foto_lokasi_barat'     => $data->foto_lokasi_barat,
            'foto_lokasi_utara'     => $data->foto_lokasi_utara,
            'arah_utara'            => $data->arah_utara,
            'arah_timur_laut'       => $data->arah_timur_laut,
            'arah_timur'            => $data->arah_timur,
            'arah_tenggara'         => $data->arah_tenggara,
            'arah_selatan'          => $data->arah_selatan,
            'arah_barat_daya'       => $data->arah_barat_daya,
            'arah_barat'            => $data->arah_barat,
            'arah_barat_laut'       => $data->arah_barat_laut,
            'status'                => '200'
        ], 200);
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSampah::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_utara = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_utara;
            $foto_barat = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_barat;
            $foto_timur = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_timur;
            $foto_selatan = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_selatan;
            if (is_file($foto_utara)) {
                unlink($foto_utara);
            }
            if (is_file($foto_barat)) {
                unlink($foto_barat);
            }
            if (is_file($foto_timur)) {
                unlink($foto_timur);
            }
            if (is_file($foto_selatan)) {
                unlink($foto_selatan);
            }
            InsertActivityFdl::by($this->user_id)->action('delete')->target("Observasi Sampah pada nomor sampel $data->no_sampel")->save();
            $data->delete();

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 1
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
        $path = 'dokumentasi/sampling';
        $service = new SaveFileServices();
        $service->saveFile($path ,  $safeName, $file);
        return $safeName;
    }
}
