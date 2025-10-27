<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganDebuPersonal;

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

class FdlDebuPersonalController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                $debu = DataLapanganDebuPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift', 'like', '%L1%')->first();
                if ($debu !== NULL) {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $debu->keterangan,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
                        'param' => $data->parameter
                    ], 200);
                }else{
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
            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 401);
        }
    }

    public function index(Request $request)
    {
        $data = DataLapanganDebuPersonal::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $nilai_array = [];
            $cek_nil = DataLapanganDebuPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            foreach ($cek_nil as $key => $value) {
                $durasi = $value->shift;
                $durasi = explode("-", $durasi);
                $durasi = $durasi[1];
                $nilai_array[$key] = str_replace('"', "", $durasi);
            }

            if (in_array($request->shift, $nilai_array)) {
                return response()->json([
                    'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada !'
                ], 401);
            }

            $shift_peng = $request->kateg_uji . '-' . $request->shift;

            $data = new DataLapanganDebuPersonal;
            $data->no_sampel = strtoupper(trim($request->no_sample));
            if ($request->keterangan_4 != '')
                $data->keterangan = $request->keterangan_4;
            if ($request->keterangan_2 != '')
                $data->keterangan_2 = $request->keterangan_2;
            if ($request->posisi != '')
                $data->titik_koordinat = $request->posisi;
            if ($request->lat != '')
                $data->latitude = $request->lat;
            if ($request->longitude != '')
                $data->longi = $request->longi;
            if ($request->lok_submit != '')
                $data->lokasi_submit = $request->lok_submit;

            if ($request->categori != '')
                $data->kategori_3 = $request->categori;
            if ($request->nama_pekerja != '') $data->nama_pekerja = $request->nama_pekerja;
            if ($request->divisi != '') $data->divisi = $request->divisi;
            if ($request->suhu != '') $data->suhu = $request->suhu;
            if ($request->kelem != '') $data->kelembaban = $request->kelem;
            if ($request->tekU != '') $data->tekanan_udara = $request->tekU;
            if ($request->aktivitas != '') $data->aktivitas = $request->aktivitas;
            if ($request->apd != '') $data->apd = $request->apd;
            if ($request->jam_mulai != '') $data->jam_mulai = $request->jam_mulai;
            if ($request->jam_pengambilan != '') $data->jam_pengambilan = $request->jam_pengambilan;
            if ($request->flow != '') $data->flow = $request->flow;
            if ($request->jam_selesai != '') $data->jam_selesai = $request->jam_selesai;
            if ($request->total_waktu != '') $data->total_waktu = $request->total_waktu;
            $data->shift = $shift_peng;
            if ($request->permission != '')
                $data->permission = $request->permission;
            if ($request->foto_lok != '')
                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
            if ($request->foto_sampl != '')
                $data->foto_alat = self::convertImg($request->foto_sampl, 2, $this->user_id);
            if ($request->foto_lain != '')
                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            InsertActivityFdl::by($this->user_id)->action('input')->target("Debu Personal pada nomor sampel $request->no_sampel")->save();

            DB::commit();
            return response()->json([
                'message' => "Data Sampling FDL Debu Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
            ], 200);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Debu Personal dengan nomor sampel $no_sample")->save();

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
        $data = DataLapanganDebuPersonal::with('detail')->where('id', $request->id)->first();

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

                'nama_pekerja'   => $data->nama_pekerja,
                'divisi'         => $data->divisi,
                'suhu'           => $data->suhu,
                'kelem'          => $data->kelembaban,
                'tekanan_u'      => $data->tekanan_udara,
                'shift'          => $data->shift,
                'aktivitas'      => $data->aktivitas,
                'apd'            => $data->apd,
                'jam_mulai'      => $data->jam_mulai,
                'jam_pengambilan'=> $data->jam_pengambilan,
                'flow'           => $data->flow,
                'jam_selesai'    => $data->jam_selesai,
                'total_waktu'    => $data->total_waktu,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'foto_alat'      => $data->foto_alat,
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
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_alat = public_path() . '/dokumentasi/sampling/' . $data->foto_alat;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_alat)) {
                unlink($foto_alat);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Debu Personal dengan nomor sampel $no_sample")->save();


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