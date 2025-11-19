<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganKebisinganPersonal;

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

class FdlKebisinganPersonalController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', '23-Kebisingan')
                ->where('parameter', 'LIKE', '%P8J%')
                ->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else 
            {
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
    
    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganKebisinganPersonal::with('detail')
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

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
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

            $durasiKerja = $this->hitungDurasiKerja($request->jam_pengambilan, $request->jam_selesai, $request->waktu_istirahat);

            $cek2 = DataLapanganKebisinganPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($cek2) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {

                $data = new DataLapanganKebisinganPersonal;
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->penamaan_titik != '') $data->keterangan       = $request->penamaan_titik;
                if ($request->posisi != '') $data->titik_koordinat        = $request->posisi;
                if ($request->lat != '') $data->latitude                       = $request->lat;
                if ($request->longi != '') $data->longitude                   = $request->longi;
                if ($request->penamaan_tambahan != '') $data->keterangan_2     = $request->penamaan_tambahan;
                if ($request->id_kategori_3 != '') $data->kategori_3             = $request->id_kategori_3;

                if ($request->departemen != '') $data->departemen            = $request->departemen;
                if ($request->sumber_kebisingan != '') $data->sumber_kebisingan             = $request->sumber_kebisingan;
                if ($request->jam_pengambilan != '') $data->jam_mulai_pengujian             = $request->jam_pengambilan;
                if ($request->waktu_istirahat != '') $data->total_waktu_istirahat_personal             = $request->waktu_istirahat;
                if ($request->jam_selesai != '') $data->jam_akhir_pengujian             = $request->jam_selesai;
                if ($durasiKerja) $data->waktu_pengukuran = $durasiKerja;
                if ($request->jarak_kebisingan != '') $data->jarak_sumber_kebisingan                   = $request->jarak_kebisingan;
                if ($request->aktifitas_kerja != '') $data->aktifitas                   = $request->aktifitas_kerja;

                if ($request->permission != '') $data->permission                 = $request->permission;
                if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                     = $this->karyawan;
                $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

                if($orderDetail->tanggal_terima == null){
                    $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d H:i:s');
                    $orderDetail->save();
                }

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling KEBISINGAN PERSONAL Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                InsertActivityFdl::by($this->user_id)->action('input')->target("Kebisingan Personal pada nomor sampel $request->no_sample")->save();

                DB::commit();

                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganKebisinganPersonal::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Kebisingan Personal success';

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
            'mulai'          => $data->jam_mulai_pengujian,
            'selesai'        => $data->jam_akhir_pengujian,
            'istirahat'      => $data->total_waktu_istirahat_personal,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'massage'        => $this->resultx,
            'departemen'     => $data->departemen,
            'sumber'         => $data->sumber_kebisingan,
            'jarak'          => $data->jarak_sumber_kebisingan,
            'paparan'        => $data->waktu_paparan,
            'aktifitas'      => $data->aktifitas,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapangankebisinganPersonal::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Kebisingan Personal dengan nomor sampel $no_sample")->save();

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
            $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Kebisingan Personal dengan nomor sampel $no_sample")->save();

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

    private function hitungDurasiKerja($jamMulai, $jamSelesai, $istirahatMenit = 0)
    {
        $mulai = Carbon::createFromFormat('H:i', $jamMulai);
        $selesai = Carbon::createFromFormat('H:i', $jamSelesai);

        // Jika selesai < mulai → berarti shift sampai besok
        if ($selesai->lessThan($mulai)) {
            $selesai->addDay();
        }

        // Hitung durasi total
        $durasiMenit = $selesai->diffInMinutes($mulai);

        // Cek apakah melewati jam istirahat (12:00–13:00)
        $istirahatMulai = $mulai->copy()->setTime(12, 0);
        $istirahatSelesai = $mulai->copy()->setTime(13, 0);

        if ($mulai->lessThanOrEqualTo($istirahatMulai) && $selesai->greaterThanOrEqualTo($istirahatSelesai)) {
            $durasiMenit -= $istirahatMenit;
        }

        // Format hasil
        $jam = floor($durasiMenit / 60);
        $menit = $durasiMenit % 60;

        return sprintf("%02d Jam %02d Menit", $jam, $menit);
    }


    // private function hitungDurasiKerja($jamMulai, $jamSelesai, $istirahatMenit = 0)
    // {
    //     $mulai = Carbon::createFromFormat('H:i', $jamMulai);
    //     $selesai = Carbon::createFromFormat('H:i', $jamSelesai);

    //     if ($selesai->lessThan($mulai)) {
    //         // Jika jam selesai lebih kecil dari jam mulai, anggap keesokan harinya
    //         $selesai->addDay();
    //     }

    //     $durasiMenit = $selesai->diffInMinutes($mulai);

    //     // Periksa apakah waktu kerja melewati jam makan siang (12:00 - 13:00)
    //     $mulaiKerjaMenit = $mulai->hour * 60 + $mulai->minute;
    //     $selesaiKerjaMenit = $selesai->hour * 60 + $selesai->minute;

    //     $jamIstirahatMulai = 12 * 60;
    //     $jamIstirahatSelesai = 13 * 60;

    //     if ($mulaiKerjaMenit <= $jamIstirahatMulai && $selesaiKerjaMenit >= $jamIstirahatSelesai) {
    //         $durasiMenit -= $istirahatMenit;
    //     }

    //     // Format hasil: xx Jam xx Menit
    //     $jamKerja = floor($durasiMenit / 60);
    //     $menitKerja = $durasiMenit % 60;

    //     return str_pad($jamKerja, 2, '0', STR_PAD_LEFT) . ' Jam ' . str_pad($menitKerja, 2, '0', STR_PAD_LEFT) . ' Menit';
    // }

}