<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganKebisingan;

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


class FdlKebisinganController extends Controller
{
    public function getSample(Request $request)
    {
        if (!empty($request->no_sample)) {

            $order = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_2', '4-Udara')
                ->where('kategori_3', 'LIKE', '%-Kebisingan%')
                ->where('is_active', 1)
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            }

            $cek = MasterSubKategori::where('id', explode('-', $order->kategori_3)[0])->first();

            // ✅ Decode TANPA menimpa $order
            $parameter = json_decode($order->parameter, true);

            $param = null;

            if (!empty($parameter[0])) {
                $parts = explode(';', $parameter[0], 2);
                $param = $parts[1] ?? null;
            }

            $waktu = "Sesaat";

            if (!empty($param) && preg_match('/\(([^)]+)\)/', $param, $match)) {
                $waktu = $match[1];
            }

            return response()->json([
                'no_sample'          => $order->no_sampel,
                'jenis'              => $cek->nama_sub_kategori ?? null,
                'keterangan'         => $order->keterangan_1,
                'id_ket'             => explode('-', $order->kategori_3)[0],
                'id_ket2'            => explode('-', $order->kategori_2)[0],
                'param'              => $order->parameter,
                'kategori_pengujian' => $waktu
            ], 200);
        }

        return response()->json([
            'message' => 'Fatal Error'
        ], 401);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $cek = DataLapanganKebisingan::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            $nilai_array = [];
            foreach ($cek as $key => $value) {
                if ($value->jenis_durasi_sampling == 'Sesaat') {
                    if ($request->jenis_durasi == $value->jenis_durasi_sampling) {
                        return response()->json([
                            'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                        ], 401);
                    }
                } else {
                    $aa = $value->jenis_durasi_sampling;
                    $ab = explode("-", $aa);
                    array_push($nilai_array, str_replace('"', "", $ab[1]));
                }
            }

            if(!isset($request->foto_lok)){
                return response()->json([
                    'message' => 'Tolong tambahkan Dokumentasi Lokasi !'
                ], 401);
            }

            if(!isset($request->jenis_durasi)){
                return response()->json([
                    'message' => 'Pilih Jenis Durasi !'
                ], 401);
            }

            if (in_array($request->durasi_sampl, $nilai_array)) {
                return response()->json([
                    'message' => 'Shift Pengambilan ' . $request->durasi_sampl . ' sudah ada !'
                ], 401);
            }

            $jendur = $request->jenis_durasi;
            if ($request->jenis_durasi == "24 Jam" || $request->jenis_durasi == '8 Jam') {
                $jendur = $request->jenis_durasi . '-' . json_encode($request->durasi_sampl);
            }

            $data = new DataLapanganKebisingan();

            $data->no_sampel = strtoupper(trim($request->no_sample));

            if ($request->keterangan_4) {
                $data->keterangan = $request->keterangan_4;
            }

            if ($request->information) {
                $data->informasi_tambahan = $request->information;
            }

            if ($request->posisi) {
                $data->titik_koordinat = $request->posisi;
            }


            if ($request->latitude) {
                $data->latitude = $request->latitude;
            }

            if ($request->longitude) {
                $data->longitude = $request->longitude;
            }

            if ($request->jenis_frekuensi) {
                $data->jenis_frekuensi_kebisingan = $request->jenis_frekuensi;
            }

            if ($request->waktu) {
                $data->waktu = $request->waktu;
            }

            if ($request->sumber_keb) {
                $data->sumber_kebisingan = $request->sumber_keb;
            }

            if ($request->kategori_kebisingan) {
                $data->jenis_kategori_kebisingan = $request->kategori_kebisingan;
            }

            if ($request->jenis_durasi) {
                $data->jenis_durasi_sampling = $jendur;
            }

            if ($request->kebisingan) {
                $nilai = [];
                foreach ($request->kebisingan as $value) {

                    // ubah ke string agar desimal tidak hilang
                    $str = (string)$value;

                    // cek ada titik atau tidak
                    if (strpos($str, '.') !== false) {
                        // pisahkan integer dan desimal
                        [$int, $des] = explode('.', $str);

                        // ambil hanya 1 digit desimal tanpa pembulatan
                        $des = substr($des, 0, 1);

                        // jika desimal kosong (misal "63."), set jadi "0"
                        if ($des === "") $des = "0";

                        $nilai[] = $int . '.' . $des;
                    } else {
                        // tidak ada desimal → tambahkan ".0"
                        $nilai[] = $str . '.0';
                    }
                }


                $data->value_kebisingan = json_encode($nilai);
            }
            if ($request->jam_pemaparan) {
                $data->jam_pemaparan = $request->jam_pemaparan;
            }

            if ($request->suhu_udara) {
                $data->suhu_udara = $request->suhu_udara;
            }

            if ($request->kelembapan_udara) {
                $data->kelembapan_udara = $request->kelembapan_udara;
            }

            if ($request->permission) {
                $data->permission = $request->permission;
            }

            if ($request->foto_lok) {
                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
            }

            if ($request->foto_lain) {
                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
            }

            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

            if($orderDetail->tanggal_terima == null){
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                $orderDetail->save();
            }

            InsertActivityFdl::by($this->user_id)->action('input')->target("Kebisingan pada nomor sampel $request->no_sample")->save();

            DB::commit();
            return response()->json([
                'message' => "Data Sampling KEBISINGAN Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);
        }
    }

    public function index(Request $request)
    {
        $data = DataLapanganKebisingan::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereIn('is_rejected', [0, 1])
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7));
            
        return Datatables::of($data)->make(true);
    }

    public function detail(Request $request)
    {
        $data = DataLapanganKebisingan::with('detail')->where('id', $request->id)->first();

        $this->resultx = 'get Detail sample lapangan Kebisingan success';

        return response()->json([
            'id'                    => $data->id,
            'no_sample'             => $data->no_sampel,
            'no_order'              => $data->detail->no_order,
            'sampler'               => $data->created_by,
            'categori'              => explode('-', $data->detail->kategori_3)[1],
            // 'id_sub_kategori'       => explode('-', $data->detail->kategori_3)[0],
            'jam'                   => $data->waktu,
            'corp'                  => $data->detail->nama_perusahaan,
            'keterangan'            => $data->keterangan,
            'lat'                   => $data->latitude,
            'long'                  => $data->longitude,
            'coor'                  => $data->titik_koordinat,
            'massage'               => $this->resultx,
            'info_tambahan'         => $data->informasi_tambahan,
            'keterangan'            => $data->keterangan,
            'sumber_keb'            => $data->sumber_kebisingan,
            'jenis_frek'            => $data->jenis_frekuensi_kebisingan,
            'jenis_kate'            => $data->jenis_kategori_kebisingan,
            'jenis_durasi'          => $data->jenis_durasi_sampling,
            'suhu_udara'            => $data->suhu_udara,
            'kelem_udara'           => $data->kelembapan_udara,
            'val_kebisingan'        => $data->value_kebisingan,
            'tikoor'                => $data->titik_koordinat,
            'foto_lok'              => $data->foto_lokasi_sampel,
            'foto_lain'             => $data->foto_lain,
            'status'                => '200'
        ], 200);
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;
            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Kebisingan dengan nomor sampel $no_sample")->save();

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

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();
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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Kebisingan dengan nomor sampel $no_sample")->save();

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