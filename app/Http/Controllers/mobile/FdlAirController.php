<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganAir;

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

class FdlAirController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganAir::with('detail')
        ->where('created_by', $this->karyawan)
        ->where(function ($q) {
            $q->where('is_rejected', 1)
            ->orWhere(function ($q2) {
                $q2->where('is_rejected', 0)
                    ->whereDate('created_at', '>=', Carbon::now()->subDays(7));
            });
        });


        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('jenis_sampel', 'like', "%$search%")
                ->orWhere('lokasi_titik_pengambilan', 'like', "%$search%")
                ->orWhere('keterangan', 'like', "%$search%");
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }


    public function dashboardData()
    {
        $datas = DataLapanganAir::where('created_by', $this->karyawan)
        ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
        ->orderBy('id', 'desc')->get();

        $permukaan = 0;
        $limbah = 0;
        $laut = 0;
        $tanah = 0;
        $bersih = 0;
        $khusus = 0;

        if(!empty($datas) && count($datas) > 0){
            foreach ($datas as $data) {
                $status = $this->checkJenisSample($data->jenis_sampel);
                if($status == 'permukaan'){
                    $permukaan++;
                } else if($status == 'limbah'){
                    $limbah++;  
                } else if($status == 'tanah'){
                    $tanah++;
                } else if($status == 'bersih'){
                    $bersih++;
                } else if($status == 'lain'){
                    if($data->lokasi_titik_pengambilan != null || $data->arah_arus != null){
                        $laut++;
                    } else if($data->lokasi_titik_pengambilan == null && $data->jenis_sampel == null && $data->keterangan != null){
                        $khusus++;
                    }
                }
            }
        }

        return response()->json([
            'data' => (object)[
                'permukaan' => $permukaan,
                'limbah' => $limbah,
                'laut' => $laut,
                'tanah' => $tanah,
                'bersih' => $bersih,
                'khusus' => $khusus
            ]
            ]);
    }

    private function checkJenisSample($jenis) {
        switch ($jenis) {
            case 'Air Sungai':
            case 'Air Danau':
            case 'Air Waduk':
            case 'Air Situ':
            case 'Air Akuifer':
            case 'Air Rawa':
            case 'Air Muara':
            case 'Air dari Mata Air':    
            case 'Air Mata Air':    
            case 'Air Lindi':    
                return 'permukaan';
                break;
            case 'Limbah Domestik':
            case 'Limbah Industri':
            case 'Limbah':
            case 'Air Limbah':
            case 'Air Limbah Terintegrasi':
            case 'Air Limbah Industri':
            case 'Air Limbah Domestik':
                return 'limbah';
                break;
            case 'Air Sumur Bor':
            case 'Air Sumur Gali':
            case 'Air Sumur Pantek':
            case 'Air Tanah':
                return 'tanah';
                break;
            case 'Air Keperluan Hygiene Sanitasi':
            case 'Air Khusus RS':
            case 'Air Dalam Kemasan':
            case 'Air RO':
            case 'Air Reverse Osmosis':
                return 'bersih';
                break;
            default:
                return 'lain';
                break;
        }
    }

    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->where('kategori_2', '1-Air')
            ->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan di kategori Air'
                ], 401);
            } else {
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                return response()->json([
                    'no_sample'    => $data->no_sampel,
                    'jenis_sample'  => $cek->nama_sub_kategori,
                    'keterangan' => $data->keterangan_1,
                    'kategori3' => explode('-', $data->kategori_3)[0],
                    'kategori2' => explode('-', $data->kategori_2)[0],
                    'param' => $data->parameter
                ], 200);
            }
        }else {
            return response()->json([
                'message' => 'Nomor sample tidak boleh kosong'
            ], 401);
        }
    }

    public function store(Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {
            if (isset($request->koordinat) && $request->koordinat != null) {
                if (!$request->no_sampel || $request->no_sampel == null) {
                    return response()->json([
                        'message' => 'NO Sample tidak boleh kosong!.'
                    ], 401);
                } else {
                    //==========Check no sample==========
                    $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('is_active', true)->first();
                    if (is_null($check)) {
                        return response()->json([
                            'message' => 'No Sample tidak ditemukan!.'
                        ], 401);
                    } else {
                        //==============final input=================
                        $cek = DataLapanganAir::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                        $u    = MasterKaryawan::where('nama_lengkap', $this->user_id)->first();

                        if ($request->jam_pengambilan == '') {
                            return response()->json([
                                'message' => 'Jam pengambilan tidak boleh kosong .!'
                            ], 401);
                        }
                        if ($request->foto_lok == '') {
                            return response()->json([
                                'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                            ], 401);
                        }
                        if ($request->foto_sampl == '') {
                            return response()->json([
                                'message' => 'Foto kondisi sample tidak boleh kosong .!'
                            ], 401);
                        }

                        if ($cek) {
                            return response()->json([
                                'message' => 'No Sample sudah diinput!.'
                            ], 401);
                        } else {
                            if (in_array('kimia', $request->parent_pengawet) == true) {
                                $pengawet = str_replace("[", "", json_encode($request->parent_pengawet));
                                $pengawet = str_replace("]", "", $pengawet);
                                $pengawet = str_replace('"', "", $pengawet);
                                $pengawet = str_replace(",", ", ", $pengawet);
                                $pengawet = $pengawet . '-' . json_encode($request->jenis_pengawet);
                            } else {
                                $pengawet = str_replace("[", "", json_encode($request->parent_pengawet));
                                $pengawet = str_replace("]", "", $pengawet);
                                $pengawet = str_replace('"', "", $pengawet);
                            }

                            if ($request->jam_pengamatan != null) {
                                $a = count($request->jam_pengamatan);
                                $pasang_surut = array();
                                for ($i = 0; $i < $a; $i++) {
                                    $pasang_surut[] = [
                                        'jam' => $request->jam_pengamatan[$i],
                                        'hasil_pengamatan' => $request->hasil_pengamatan[$i]
                                    ];
                                }
                            }
                            if ($request->jenis_sample2 != '') {
                                $cek = MasterSubKategori::where('id', $request->jenis_sample2)->first();
                                $jenis_sample = $cek->name;
                            } else {
                                $jenis_sample = $request->jenis_sample;
                            }

                            $data = new DataLapanganAir();

                            $data->no_sampel = strtoupper(trim($request->no_sampel));
                            $data->jenis_sampel = $request->jenis_sample;
                            $data->kedalaman_titik = $request->kedalaman_titik ?? '';
                            $data->jenis_produksi = $request->jenis_produksi ?? '';
                            $data->lokasi_titik_pengambilan = $request->lokasi_titik_pengambilan ?? '';
                            $data->jenis_fungsi_air = is_array($request->jenis_fungsi) ? json_encode($request->jenis_fungsi) : $request->jenis_fungsi ?? '';
                            $data->jumlah_titik_pengambilan = $request->jumlah_titik_pengambilan  ?? '';
                            $data->status_kesediaan_ipal = $request->ipal ?? '';
                            $data->lokasi_sampling = $request->lokasi_sampling ?? '';
                            $data->keterangan = $request->keterangan ?? '';
                            $data->informasi_tambahan = $request->information ?? '';
                            $data->titik_koordinat = $request->koordinat ?? '';
                            $data->latitude = $request->latitude ?? '';
                            $data->longitude = $request->longitude ?? '';
                            $data->diameter_sumur = $request->diameter_sumur ?? '';
                            $data->kedalaman_sumur1 = $request->kedalaman_sumur_pertama ?? '';
                            $data->kedalaman_sumur2 = $request->kedalaman_sumur_kedua ?? '';
                            $data->kedalaman_air_terambil = $request->kedalaman_sumur_terambil ?? '';
                            $data->total_waktu = $request->total_waktu ?? '';
                            $data->teknik_sampling = $request->teknik_sampling ?? '';
                            $data->jam_pengambilan = $request->jam_pengambilan ?? '';
                            $data->volume = $request->volume ?? '';
                            $data->jenis_pengawet = $pengawet ?? '';
                            $data->perlakuan_penyaringan = $request->penyaringan ?? '';
                            $data->pengendalian_mutu = $request->mutu !== 'null' ? json_encode($request->mutu) : '';
                            $data->teknik_pengukuran_debit = $request->pengukuran_debit ?? '';
                            $data->debit_air = $request->debit_air ?? '';

                            if ($request->selected_type_debit == 'Input Data' && $request->debit_air != '' && $request->satuan_debit != '') {
                                $data->debit_air = $request->debit_air . ' ' . $request->satuan_debit;
                            } else if ($request->selected_type_debit == 'Data By Customer') {
                                if ($request->selected_type_customer == 'Email') {
                                    $data->debit_air = 'Data By Customer( Email )';
                                } else if ($request->selected_type_customer == 'Input Data' && $request->debit_air != '') {
                                    if ($request->satuan_debit != '' && $request->debit_air != '') {
                                        $data->debit_air = 'Data By Customer(' . $request->debit_air . ' ' . $request->satuan_debit . ')';
                                    } else if ($request->debit_air != '') {
                                        $data->debit_air = 'Data By Customer(' . $request->debit_air . ')';
                                    }
                                }
                            }

                            $data->do = $request->do ?? '';
                            $data->ph = $request->ph ?? '';
                            $data->suhu_air = $request->suhu_air ?? '';
                            $data->suhu_udara = $request->suhu_udara ?? '';
                            $data->dhl = $request->dhl ?? '';
                            $data->warna = $request->warna ?? '';
                            $data->bau = $request->bau ?? '';
                            $data->salinitas = $request->salinitas ?? '';
                            $data->kecepatan_arus = $request->kecepatan_arus ?? '';
                            $data->arah_arus = $request->arah_arus ?? '';
                            $data->pasang_surut = $request->jam_pengamatan != null ? json_encode($pasang_surut) : '';
                            $data->kecerahan = $request->kecerahan ?? '';
                            $data->lapisan_minyak = $request->minyak ?? '';
                            $data->cuaca = $request->cuaca ?? '';
                            $data->sampah = $request->sampah ?? '';
                            $data->lokasi_submit = $request->lok_submit ?? '';
                            $data->klor_bebas = $request->klor ?? '';

                            if ($request->foto_lok != '') {
                                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                            }

                            if ($request->foto_sampl != '') {
                                $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                            }

                            if ($request->foto_lain != '') {
                                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                            }

                            $data->permission = $request->permission ?? '';
                            $data->created_by = $this->karyawan;
                            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');

                            $data->save();

                            $nama = $this->karyawan;
                            $this->resultx = "Data Sampling AIR dengan No Sample $request->no_sampel berhasil disimpan oleh $nama";
                            InsertActivityFdl::by($this->user_id)->action('input')->target("Air ($data->jenis_sample) pada nomor sampel $request->no_sampel")->save();
                            
                            DB::commit(); 
                            return response()->json([
                                'message' => $this->resultx
                            ], 200);
                        }
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Lokasi tidak ditemukan'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack(); 
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $data = DataLapanganAir::with('detail')->where('id', $request->id)->first();

            $po = OrderDetail::where('no_sampel', $data->no_sampel)->first();
            
            if($po){
                if ($data->debit_air == null) {
                    $debit = 'Data By Customer';
                } else {
                    $debit = $data->debit_air;
                }
    
                return response()->json([
                    'id'                        => $data->id,
                    'no_sample'                 => $data->no_sampel,
                    'no_order'                  => $data->detail->no_order,
                    'sampler'                   => $data->created_by,
                    'jam'                       => $data->jam_pengambilan,
                    'corp'                      => $data->detail->nama_perusahaan,
                    'jenis'                     => explode('-', $data->detail->kategori_3)[1],
                    'keterangan'                => $data->keterangan,
                    'jenis_produksi'            => $data->jenis_produksi,
                    'pengawet'                  => $data->jenis_pengawet,
                    'teknik'                    => $data->teknik_sampling,
                    'warna'                     => $data->warna,
                    'bau'                       => $data->bau,
                    'volume'                    => $data->volume,
                    'suhu_air'                  => $data->suhu_air,
                    'suhu_udara'                => $data->suhu_udara,
                    'ph'                        => $data->ph,
                    'tds'                       => $data->tds,
                    'dhl'                       => $data->dhl,
                    'do'                        => $data->do,
                    'debit'                     => $debit,
                    'lat'                       => $data->latitude,
                    'long'                      => $data->longitude,
                    'coor'                      => $data->titik_koordinat,
                    'massage'                   => "get Detail sample lapangan success",
                    'jumlah_titik_pengambilan'  => $data->jumlah_titik_pengambilan,
                    'jenis_fungsi_air'          => $data->jenis_fungsi_air,
                    'perlakuan_penyaringan'     => $data->perlakuan_penyaringan,
                    'pengendalian_mutu'         => $data->pengendalian_mutu,
                    'teknik_pengukuran_debit'   => $data->teknik_pengukuran_debit,
                    'klor_bebas'                => $data->klor_bebas,
                    'kat_id'                    => explode('-', $data->detail->kategori_3)[0],
                    'jenis_sample'              => $data->jenis_sample,
                    'ipal'                      => $data->status_kesediaan_ipal,
                    'lok_sampling'              => $data->lokasi_sampling,
                    'diameter'                  => $data->diameter_sumur,
                    'kedalaman1'                => $data->kedalaman_sumur1,
                    'kedalaman2'                => $data->kedalaman_sumur2,
                    'kedalamanair'              => $data->kedalaman_air_terambil,
                    'total_waktu'               => $data->total_waktu,
                    'kedalaman_titik'           => $data->kedalaman_titik,
                    'lokasi_pengambilan'        => $data->lokasi_titik_pengambilan,
                    'salinitas'                 => $data->salinitas,
                    'kecepatan_arus'            => $data->kecepatan_arus,
                    'arah_arus'                 => $data->arah_arus,
                    'pasang_surut'              => $data->pasang_surut,
                    'kecerahan'                 => $data->kecerahan,
                    'lapisan_minyak'            => $data->lapisan_minyak,
                    'cuaca'                     => $data->cuaca,
                    'info_tambahan'             => $data->informasi_tambahan,
                    'keterangan'                => $data->keterangan,
                    'foto_lok'                  => $data->foto_lokasi_sampel,
                    'foto_kondisi'              => $data->foto_kondisi_sampel,
                    'foto_lain'                 => $data->foto_lain,
                    'sampah'                    => $data->sampah,
                    'status'                    => '200'
                ], 200);
            }

        } catch (\exception $err) {
            dd($err);
        }
    }

    public function delete(Request $request){
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganAir::where('id', $request->id)->first();
                $no_sample = $data->no_sampel;
                $jenis_sampel = $data->jenis_sample;
                $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                if (is_file($foto_lokasi)) {
                    unlink($foto_lokasi);
                }
                if (is_file($foto_kondisi)) {
                    unlink($foto_kondisi);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $data->delete();

                InsertActivityFdl::by($this->user_id)->action('delete')->target("Air ($jenis_sampel) dengan nomor sampel $no_sample")->save();
                
                DB::commit();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 1
                ], 201);
            } catch (\exception $err) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganAir::where('id', $request->id)->first();

                $no_sample = $data->no_sampel;

                $data->is_approve     = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                InsertActivityFdl::by($this->user_id)->action('approve')->target("Air ($data->jenis_sample) dengan nomor sampel $no_sample")->save();

                DB::commit();

                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } catch (\exception $err) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
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

    // Select Category
    public function cmbCategory(Request $request)
    {
        echo "<option value=''>--Pilih Kategori--</option>";

        $data = MasterKategori::where('is_active', true)->get();

        foreach ($data as $q) {

            $id = $q->id;
            $nm = $q->nama_kategori;
            if ($id == $request->value) {
                echo "<option value='$id' selected> $nm </option>";
            } else {
                echo "<option value='$id'> $nm </option>";
            }
        }
    }

    // SELECT VOLUME
    public function SelectVolume(Request $request)
    {
        $vm = [];
        $a = 100;
        for ($i = 1; $i <= $a; $i++) {
            $nn = $i . '00';
            array_push($vm, $nn);
        }

        return response()->json([
            'data' => $vm
        ], 201);
    }
}
