<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganMedanLM;;

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

class FdlMedanListrikDanMagnetController extends Controller
{
    public function getSample(Request $request)
    {
        // Cek apakah input no_sample valid
        if (!isset($request->no_sample) || trim($request->no_sample) === '') {
            return response()->json(['message' => 'Fatal Error'], 401);
        }

        // Normalisasi no_sample
        $no_sampel = strtoupper(trim($request->no_sample));
        $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'listrik_dan_magnet')->where('is_active', 1)->first();
        $parameterList = json_decode($parameter->parameters, true);

        $inputListrik = json_decode(ParameterFdl::select('parameters')->where('nama_fdl', 'inputan_listrik')->where('is_active', 1)->first()->parameters, true);
        $inputMagnet = json_decode(ParameterFdl::select('parameters')->where('nama_fdl', 'inputan_magnet')->where('is_active', 1)->first()->parameters, true);


        // Ambil data order yang aktif berdasarkan no_sampel
        $data = OrderDetail::where('no_sampel', $no_sampel)
            ->where('is_active', 1)
            ->where('kategori_3', '27-Udara lingkungan Kerja')
            ->where(function($q) use ($parameterList) {
                foreach ($parameterList as $param) {
                    $q->orWhere('parameter', 'like', "%$param%");
                }
            })
            ->first();

        if (!$data) {
            return response()->json(['message' => 'No Sample tidak ditemukan..'], 401);
        }

        // Ambil subkategori untuk informasi 'jenis'
        $id_ket = explode('-', $data->kategori_3)[0];
        $id_ket2 = explode('-', $data->kategori_2)[0];
        $cek = MasterSubKategori::find($id_ket);

        // Cek apakah sudah ada data lapangan Medan
        $medan = DataLapanganMedanLM::where('no_sampel', $no_sampel)->exists();

        // Decode parameter yang seharusnya dicatat
        $paramTarget = json_decode($data->parameter, true);

        // Bersihkan paramTarget -> ambil setelah ";"
        $paramTargetClean = array_map(function($item) {
            $parts = explode(';', $item, 2);
            return $parts[1] ?? $parts[0];
        }, $paramTarget);

        // Cek di fdl
        if ($medan) {
            \DB::statement("SET SQL_MODE=''");

            // Ambil parameter yang sudah dicatat (langsung nama parameternya)
            $paramTerekam = DataLapanganMedanLM::where('no_sampel', $no_sampel)
                ->groupBy('parameter')
                ->pluck('parameter')
                ->toArray();

            // Ambil parameter yang belum dicatat
            $paramBelumDicatat = array_values(array_diff($paramTargetClean, $paramTerekam));

            return response()->json([
                'no_sample'    => $data->no_sampel,
                'jenis'        => $cek->nama_sub_kategori ?? null,
                'keterangan'   => $data->keterangan_1,
                'id_ket'       => $id_ket,
                'param'        => $paramBelumDicatat,
                'parameterList' => $parameterList,
                'paramListrik' => $inputListrik,
                'paramMagnet'  => $inputMagnet,
            ], 200);
        }

        // Jika belum ada data Medan listrik dan magnet
        return response()->json([
            'no_sample'  => $data->no_sampel,
            'jenis'      => $cek->nama_sub_kategori ?? null,
            'keterangan' => $data->keterangan_1,
            'id_ket'     => $id_ket,
            'id_ket2'    => $id_ket2,
            'parameterList' => $parameterList,
            'param'      => $paramTargetClean,
            'paramListrik' => $inputListrik,
            'paramMagnet' => $inputMagnet
        ], 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganMedanLM::where('parameter', $request->selected_parameter)->where('no_sampel', $request->no_sample)->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample dengan parameter ' . $request->selected_parameter . ' Sudah terinput'
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
                
                
                if ($request->selected_parameter == 'Medan Magnit Statis') {
                    if ($request->magnet3 != '') {
                        $magnet3 = array();
                        $o = 1;
                        for ($i = 0; $i < 5; $i++) {
                            $magnet3[] = [
                                'Data-' . $o++ => $request->magnet3[$i],
                            ];
                        }
                        $magnet30 = array();
                        $p = 1;
                        for ($i = 0; $i < 5; $i++) {
                            $magnet30[] = [
                                'Data-' . $p++ => $request->magnet30[$i],
                            ];
                        }
                        $q = 1;
                        $magnet100 = array();
                        for ($i = 0; $i < 5; $i++) {
                            $magnet100[] = [
                                'Data-' . $q++ => $request->magnet100[$i],
                            ];
                        }
                        $data = new DataLapanganMedanLM();
                        $data->no_sampel                 = strtoupper(trim($request->no_sample));
                        if ($request->keterangan != '') $data->keterangan            = $request->keterangan;
                        if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                        if ($request->koordinat != '') $data->titik_koordinat             = $request->koordinat;
                        $data->parameter                = $request->selected_parameter;
                        if ($request->latitide != '') $data->latitude                            = $request->latitide;
                        if ($request->longitude != '') $data->longitude                        = $request->longitude;
                        if ($request->category != '') $data->kategori_3                = $request->category;
                        if ($request->lokasi != '') $data->lokasi                         = $request->lokasi;
                        if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
                        if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
                        if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
                        if ($request->jam_pengambilan != '') $data->waktu_pengukuran                        = $request->jam_pengambilan;
                        if ($request->magnet3 != '') $data->magnet_3     = json_encode($magnet3);
                        if ($request->magnet30 != '') $data->magnet_30    = json_encode($magnet30);
                        if ($request->magnet100 != '') $data->magnet_100   = json_encode($magnet100);

                        if ($request->frekuensi3 != '') $data->frekuensi_3       = $request->frekuensi3;
                        if ($request->frekuensi30 != '') $data->frekuensi_30      = $request->frekuensi30;
                        if ($request->frekuensi100 != '') $data->frekuensi_100      = $request->frekuensi100;

                        if ($request->permission != '') $data->permission                      = $request->permission;
                        if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                        $data->created_by                     = $this->karyawan;
                        $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                        $data->save();
                    }
                } else if ($request->selected_parameter == 'Medan Listrik') {
                    if ($request->listrik3 != '') {
                        $listrik3 = array();
                        $r = 1;
                        for ($i = 0; $i < 5; $i++) {
                            $listrik3[] = [
                                'Data-' . $r++ => $request->listrik3[$i],
                            ];
                        }
                        $s = 1;
                        $listrik30 = array();
                        for ($i = 0; $i < 5; $i++) {
                            $listrik30[] = [
                                'Data-' . $s++ => $request->listrik30[$i],
                            ];
                        }
                        $t = 1;
                        $listrik100 = array();
                        for ($i = 0; $i < 5; $i++) {
                            $listrik100[] = [
                                'Data-' . $t++ => $request->listrik100[$i],
                            ];
                        }
                        $data = new DataLapanganMedanLM();
                        $data->no_sampel                 = strtoupper(trim($request->no_sample));
                        if ($request->keterangan != '') $data->keterangan            = $request->keterangan;
                        if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                        if ($request->koordinat != '') $data->titik_koordinat             = $request->koordinat;
                        $data->parameter                = $request->selected_parameter;

                        if ($request->latitide != '') $data->latitude                            = $request->latitide;
                        if ($request->longitude != '') $data->longitude                        = $request->longitude;
                        if ($request->category != '') $data->kategori_3                = $request->category;
                        if ($request->lokasi != '') $data->lokasi                         = $request->lokasi;
                        if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
                        if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
                        if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
                        if ($request->jam_pengambilan != '') $data->waktu_pengukuran                        = $request->jam_pengambilan;

                        if ($request->listrik3 != '') $data->listrik_3    = json_encode($listrik3);
                        if ($request->listrik30 != '') $data->listrik_30   = json_encode($listrik30);
                        if ($request->listrik100 != '') $data->listrik_100  = json_encode($listrik100);

                        if ($request->frekuensi3 != '') $data->frekuensi_3       = $request->frekuensi3;
                        if ($request->frekuensi30 != '') $data->frekuensi_30      = $request->frekuensi30;
                        if ($request->frekuensi100 != '') $data->frekuensi_100      = $request->frekuensi100;

                        if ($request->permission != '') $data->permission                      = $request->permission;
                        if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                        $data->created_by                     = $this->karyawan;
                        $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                        $data->save();
                    }
                } else if ($request->selected_parameter == 'Power Density') {
                    if ($request->magnet3 != '' && $request->listrik3 != '') {
                        $magnet3 = array();
                        $o = 1;
                        for ($i = 0; $i < 5; $i++) {
                            $magnet3[] = [
                                'Data-' . $o++ => $request->magnet3[$i],
                            ];
                        }
                        $magnet30 = array();
                        $p = 1;
                        for ($i = 0; $i < 5; $i++) {
                            $magnet30[] = [
                                'Data-' . $p++ => $request->magnet30[$i],
                            ];
                        }
                        $q = 1;
                        $magnet100 = array();
                        for ($i = 0; $i < 5; $i++) {
                            $magnet100[] = [
                                'Data-' . $q++ => $request->magnet100[$i],
                            ];
                        }
                        $listrik3 = array();
                        $r = 1;
                        for ($i = 0; $i < 5; $i++) {
                            $listrik3[] = [
                                'Data-' . $r++ => $request->listrik3[$i],
                            ];
                        }
                        $s = 1;
                        $listrik30 = array();
                        for ($i = 0; $i < 5; $i++) {
                            $listrik30[] = [
                                'Data-' . $s++ => $request->listrik30[$i],
                            ];
                        }
                        $t = 1;
                        $listrik100 = array();
                        for ($i = 0; $i < 5; $i++) {
                            $listrik100[] = [
                                'Data-' . $t++ => $request->listrik100[$i],
                            ];
                        }
                        $data = new DataLapanganMedanLM();
                        $data->no_sampel                 = strtoupper(trim($request->no_sample));
                        if ($request->keterangan != '') $data->keterangan            = $request->keterangan;
                        if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                        if ($request->koordinat != '') $data->titik_koordinat             = $request->koordinat;
                        $data->parameter                = $request->selected_parameter;

                        if ($request->latitide != '') $data->latitude                            = $request->latitide;
                        if ($request->longitude != '') $data->longitude                        = $request->longitude;
                        if ($request->category != '') $data->kategori_3                = $request->category;
                        if ($request->lokasi != '') $data->lokasi                         = $request->lokasi;
                        if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
                        if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
                        if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
                        if ($request->jam_pengambilan != '') $data->waktu_pengukuran                        = $request->jam_pengambilan;
                        if ($request->magnet3 != '') $data->magnet_3     = json_encode($magnet3);
                        if ($request->magnet30 != '') $data->magnet_30    = json_encode($magnet30);
                        if ($request->magnet100 != '') $data->magnet_100   = json_encode($magnet100);
                        if ($request->listrik3 != '') $data->listrik_3    = json_encode($listrik3);
                        if ($request->listrik30 != '') $data->listrik_30   = json_encode($listrik30);
                        if ($request->listrik100 != '') $data->listrik_100  = json_encode($listrik100);

                        if ($request->frekuensi3 != '') $data->frekuensi_3       = $request->frekuensi3;
                        if ($request->frekuensi30 != '') $data->frekuensi_30      = $request->frekuensi30;
                        if ($request->frekuensi100 != '') $data->frekuensi_100      = $request->frekuensi100;

                        if ($request->permission != '') $data->permission                      = $request->permission;
                        if ($request->foto_lokasi_sampel != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                        $data->created_by                     = $this->karyawan;
                        $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                        $data->save();
                    }
                };

                $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

                if($orderDetail->tanggal_terima == null){
                    $orderDetail->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);
                    $orderDetail->save();
                }

                InsertActivityFdl::by($this->user_id)->action('input')->target("Medan Listrik dan Magnet pada nomor sampel $request->no_sample")->save();

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling LISTRIK MAGNET Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                // if ($this->pin != null) {

                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }
                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e.getMessage(),
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

        $query = DataLapanganMedanLM::with('detail')
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
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Medan LM dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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
        $data = DataLapanganMedanLM::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample Medan LM success';

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

            'lokasi'         => $data->lokasi,
            'parameter'      => $data->parameter,
            'aktivitas'      => $data->aktivitas_pekerja,
            'sumber'         => $data->sumber_radiasi,
            'paparan'        => $data->waktu_pemaparan,
            'waktu'          => $data->waktu_pengukuran,
            'magnet_3'       => $data->magnet_3,
            'magnet_30'      => $data->magnet_30,
            'magnet_100'     => $data->magnet_100,
            'listrik_3'      => $data->listrik_3,
            'listrik_30'     => $data->listrik_30,
            'listrik_100'    => $data->listrik_100,
            'frek_3'         => $data->frekuensi_3,
            'frek_30'        => $data->frekuensi_30,
            'frek_100'        => $data->frekuensi_100,

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
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
            $cek2 = DataLapanganMedanLM::where('no_sampel', $data->no_sampel)->get();
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
                $data->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }
            $no_sample = $data->no_sampel;

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Medan LM dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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