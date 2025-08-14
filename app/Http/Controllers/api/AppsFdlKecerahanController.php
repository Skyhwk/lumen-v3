<?php

namespace App\Http\Controllers\api;

// DATA LAPANGAN
use App\Models\DataLapanganKecerahan;

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
use App\Services\SendTelegram;
use App\Services\GetAtasan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class AppsFdlKecerahanController extends Controller
{
    public function getSampel(Request $request)
    {
        if (isset($request->no_sampel) && $request->no_sampel != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('kategori_2', '1-Air')
                ->where(function($query) {
                    $query->where('parameter', 'like', '%Kecerahan%');
                })
                ->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'Paramater Kecerahan tidak ditemukan di no sampel tersebut'
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
        }
    }

    public function index(Request $request)
    {
        $data = DataLapanganKecerahan::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
            ->subDays(3))
            ->orderBy('id', 'desc');
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request){
        $fdl = DataLapanganKecerahan::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
        $order_detail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('is_active', 1)->first();
        $parameterString = $order_detail->parameter;

        // Bersihkan tanda kutip dan decode string ke array
        $parameterArray = json_decode($parameterString, true);

        // Filter parameter yang mengandung "Kecerahan"
        $kecerahanParam = collect($parameterArray)->first(function ($item) {
            return stripos($item, 'Kecerahan') !== false;
        });

        $namaParameter = explode(';', $kecerahanParam)[1] ?? null;

        if ($fdl) {
            return response()->json([
                'message' => 'No Sample sudah diinput!.'
            ], 401);
        } else {
            if ($request->foto_aktifitas == '') {
                return response()->json([
                    'message' => 'Foto Aktifitas tidak boleh kosong .!'
                ], 401);
            }
            if ($request->jam_mulai == '') {
                return response()->json([
                    'message' => 'Jam Mulai Pengambilan tidak boleh kosong .!'
                ], 401);
            }
            $data = new DataLapanganKecerahan();

            $data->no_sampel                                                     = strtoupper(trim($request->no_sampel));
            $data->parameter                                                     = $namaParameter;
            if ($request->penamaan_titik != '') $data->keterangan                = $request->penamaan_titik;
            if ($request->informasi_tambahan != '') $data->informasi_tambahan    = $request->informasi_tambahan;
            if ($request->jam_mulai != '') $data->waktu_pengambilan              = $request->jam_mulai;
            if ($request->kedalaman_air != '') $data->kedalaman_air              = $request->kedalaman_air;
            if ($request->kedalaman_secchi_1 != '') $data->kedalaman_secchi_1    = $request->kedalaman_secchi_1;
            if ($request->kedalaman_secchi_2 != '') $data->kedalaman_secchi_2    = $request->kedalaman_secchi_2;
            if ($request->kedalaman_secchi_3 != '') $data->kedalaman_secchi_3    = $request->kedalaman_secchi_3;
            if ($request->nilai_kecerahan != '') $data->nilai_kecerahan          = $request->nilai_kecerahan;
            if ($request->permission != '') $data->permission                    = $request->permission;
            if ($request->foto_aktifitas != '') $data->foto_aktifitas_sampling   = self::convertImg($request->foto_aktifitas, 1, $this->user_id);
            $data->created_by                                                    = $this->karyawan;
            $data->created_at                                                    = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $this->resultx = "Data Sampling FDL SAMPAH Dengan No Sample $request->no_sampel berhasil disimpan oleh $this->karyawan";

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganKecerahan::with('detail')->where('id', $request->id)->first();

        return response()->json([
            'id'                    => $data->id,
            'no_sample'             => $data->no_sampel,
            'parameter'             => $data->parameter,
            'no_order'              => $data->detail->no_order,
            'sampler'               => $data->created_by,
            'nama_perusahaan'       => $data->detail->nama_perusahaan,
            'keterangan'            => $data->keterangan,
            'info_tambahan'         => $data->informasi_tambahan,
            'waktu'                 => $data->waktu_pengambilan,
            'foto_aktifitas'        => $data->foto_aktifitas_sampling,
            'kedalaman_air'         => $data->kedalaman_air,
            'kedalaman_secchi_1'    => $data->kedalaman_secchi_1,
            'kedalaman_secchi_2'    => $data->kedalaman_secchi_2,
            'kedalaman_secchi_3'    => $data->kedalaman_secchi_3,
            'nilai_kecerahan'       => $data->nilai_kecerahan,
            'status'                => '200'
        ], 200);
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKecerahan::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_aktifitas = public_path() . '/dokumentasi/sampling/' . $data->foto_aktifitas;
            if (is_file($foto_aktifitas)) {
                unlink($foto_aktifitas);
            }

            $data->delete();

            return response()->json([
                'message' => "Data Sampling Observasi Kecerahan Dengan No Sampel $no_sample berhasil dihapus oleh $this->karyawan"
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
