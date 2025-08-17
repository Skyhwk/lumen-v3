<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;

// DATA LAPANGAN
use App\Models\DataLapanganDirectLain;

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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlDirectLainController extends Controller
{
    public function getSample(Request $request)
    {
        // dd($request->all());
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('is_active', 1)->where('nama_fdl','direct_lain')->where('kategori','4-Udara')->first();
            $listParameter = json_decode($parameter->parameters, true);
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', '27-Udara lingkungan Kerja')
                ->where(function($q) use ($parameterList) {
                    foreach ($parameterList as $param) {
                        $q->orWhere('parameter', 'like', "%;$param");
                    }
                })
                ->where('is_active', 1)->first();


            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                $direct = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

                if ($direct !== NULL) {
                    \DB::statement("SET SQL_MODE=''");
                
                    $noSampel = strtoupper(trim($request->no_sample));
                    $pDecoded = json_decode($data->parameter);
                    $pDecoded = array_map(function ($item) {
                        return explode(';', $item)[1];
                    }, $pDecoded);

                    // Ambil parameter dari data lapangan
                    $par = DataLapanganDirectLain::where('no_sampel', $noSampel)->groupBy('parameter')->pluck('parameter')->toArray();
                    $par2 = DataLapanganDirectLain::where('no_sampel', $noSampel)
                        ->where('shift', '!=', 'Sesaat')
                        ->groupBy('parameter')
                        ->pluck('parameter')
                        ->toArray();
                
                    // Hitung parameter yang belum masuk ke $par
                    $paramBelumAda = array_diff($pDecoded, $par);
                    // Gabungkan hasil param2 (shift != Sesaat) dan param yang belum ada
                    $gabungParam = array_unique(array_merge($par2, $paramBelumAda));
                
                    // Encode final
                    $param_fin = json_encode(array_values($gabungParam));
                    $cek = MasterSubKategori::find(explode('-', $data->kategori_3)[0]);
                    return response()->json([
                        'no_sample'  => $data->no_sampel,
                        'jenis'      => $cek->nama_sub_kategori ?? '-',
                        'keterangan' => $data->keterangan_1,
                        'id_ket'     => explode('-', $data->kategori_3)[0],
                        'param'      => $param_fin,
                        'parameterList' => json_decode($parameterList->parameters,true)
                    ], 200);
                }                
                else {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    $pDecoded = json_decode($data->parameter);
                    $pDecoded = array_map(function ($item) {
                        return explode(';', $item)[1];
                    }, $pDecoded);

                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
                        'param' => $pDecoded,
                        'parameterList' => json_decode($parameterList->parameters,true)
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
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganDirectLain::with('detail')
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

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', true)->first();
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan masing kosong .!'
                ], 401);
            }
            
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    if ($request->foto_lain1[$en] == '') {
                        return response()->json([
                            'message' => 'Foto lain parameter ' . $ab . ' masing kosong .!'
                        ], 401);
                    }
                    if ($request->shift1[$en] !== "Sesaat") {
                        $nilai_array = array();
                        $cek = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                        foreach ($cek as $key => $value) {
                            if ($value->shift == 'Sesaat') {
                                if ($request->shift1 == $value->shift) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                $durasi = $value->shift;
                                $durasi = explode("-", $durasi);
                                $durasi = $durasi[1];
                                $nilai_array[$key] = str_replace('"', "", $durasi);
                            }
                        }
                        if (in_array($request->shift1[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift1[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            
            if ($request->param2 != null) {
                foreach ($request->param2 as $en => $ab) {
                    if ($request->foto_lain2[$en] == '') {
                        return response()->json([
                            'message' => 'Foto lain parameter ' . $ab . ' masing kosong .!'
                        ], 401);
                    }
                    if ($request->shift2[$en] !== "Sesaat") {
                        $nilai_array = array();
                        $cek = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                        foreach ($cek as $key => $value) {
                            if ($value->shift == 'Sesaat') {
                                if ($request->shift2 == $value->shift) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                $durasi = $value->shift;
                                $durasi = explode("-", $durasi);
                                $durasi = $durasi[1];
                                $nilai_array[$key] = str_replace('"', "", $durasi);
                            }
                        }
                        if (in_array($request->shift2[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift2[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            if ($request->param3 != null) {
                foreach ($request->param3 as $en => $ab) {
                    if ($request->foto_lain3[$en] == '') {
                        return response()->json([
                            'message' => 'Foto lain parameter ' . $ab . ' masing kosong .!'
                        ], 401);
                    }
                    if ($request->shift3[$en] !== "Sesaat") {
                        $nilai_array = array();
                        $cek = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                        foreach ($cek as $key => $value) {
                            if ($value->shift == 'Sesaat') {
                                if ($request->shift3 == $value->shift) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                $durasi = $value->shift;
                                $durasi = explode("-", $durasi);
                                $durasi = $durasi[1];
                                $nilai_array[$key] = str_replace('"', "", $durasi);
                            }
                        }
                        if (in_array($request->shift3[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift3[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }

            if ($request->param != null) {
                $pe = 0;
                $pf = 10;
                foreach ($request->param as $in => $a) {
                    $pe++;
                    $pf++;
                    $pengukuran = array();
                    $pengukuran = [
                        'data-1' => $request->data1[$in],
                        'data-2' => $request->data2[$in],
                        'data-3' => $request->data3[$in],
                        'data-4' => $request->data4[$in],
                        'data-5' => $request->data5[$in],
                    ];

                    $img2 = str_replace('data:image/jpeg;base64,', '', $request->foto_lain1[$in]);
                    $file2 = base64_decode($img2);
                    $safeName2 = DATE('YmdHis') . '_' . $this->user_id . $pf . '.jpeg';
                    $destinationPath2 = public_path() . '/dokumentasi/sampling/';
                    $success2 = file_put_contents($destinationPath2 . $safeName2, $file2);

                    if ($request->kateg_uji[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift1[$in]);
                    } else if ($request->kateg_uji[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift1[$in]);
                    } else if ($request->kateg_uji[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift1[$in]);
                    } else {
                        $shift_peng = 'Sesaat';
                    }
                    $data = new DataLapanganDirectLain();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                    if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                    if ($request->lat != '') $data->latitude                            = $request->lat;
                    if ($request->longi != '') $data->longitude                        = $request->longi;
                    if ($request->categori != '') $data->kategori_3                = $request->categori;
                    if ($request->lok != '') $data->lokasi                         = $request->lok;
                    $data->parameter                         = $a;

                    if ($request->kon_lapangan != '') $data->kondisi_lapangan              = $request->kon_lapangan;
                    if ($request->jenis_peng != '') $data->jenis_pengukuran              = $request->jenis_peng;
                    if ($request->waktu != '') $data->waktu                        = $request->waktu;
                    $data->shift                   = $shift_peng;
                    if ($request->suhu != '') $data->suhu                          = $request->suhu;
                    if ($request->kelem != '') $data->kelembaban                        = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                    $data->pengukuran     = json_encode($pengukuran);

                    if ($request->permission != '') $data->permission                      = $request->permission;
                    $data->foto_lain        = $safeName2;
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                     = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }

            if ($request->param2 != null) {
                $pe = 20;
                $pf = 30;
                foreach ($request->param2 as $in => $a) {
                    $pe++;
                    $pf++;
                    $pengukuran = array();
                    $pengukuran = [
                        'data-1' => $request->data6[$in],
                        'data-2' => $request->data7[$in],
                        'data-3' => $request->data8[$in],
                        'data-4' => $request->data9[$in],
                        'data-5' => $request->data10[$in],
                    ];

                    $img2 = str_replace('data:image/jpeg;base64,', '', $request->foto_lain2[$in]);
                    $file2 = base64_decode($img2);
                    $safeName2 = DATE('YmdHis') . '_' . $this->user_id . $pf . '.jpeg';
                    $destinationPath2 = public_path() . '/dokumentasi/sampling/';
                    $success2 = file_put_contents($destinationPath2 . $safeName2, $file2);
                    if ($request->kateg_uji2[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji2[$in] . '-' . json_encode($request->shift2[$in]);
                    } else if ($request->kateg_uji2[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji2[$in] . '-' . json_encode($request->shift2[$in]);
                    } else if ($request->kateg_uji2[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji2[$in] . '-' . json_encode($request->shift2[$in]);
                    } else {
                        $shift_peng = 'Sesaat';
                    }
                    $data = new DataLapanganDirectLain();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                    if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                    if ($request->lat != '') $data->latitude                            = $request->lat;
                    if ($request->longi != '') $data->longitude                        = $request->longi;
                    if ($request->categori != '') $data->kategori_3                = $request->categori;
                    if ($request->lok != '') $data->lokasi                         = $request->lok;
                    $data->parameter                         = $a;

                    if ($request->jenis_peng != '') $data->jenis_pengukuran              = $request->jenis_peng;
                    if ($request->kon_lapangan != '') $data->kondisi_lapangan              = $request->kon_lapangan;
                    if ($request->waktu != '') $data->waktu                        = $request->waktu;
                    $data->shift                   = $shift_peng;
                    if ($request->suhu != '') $data->suhu                          = $request->suhu;
                    if ($request->kelem != '') $data->kelembaban                        = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                    $data->pengukuran     = json_encode($pengukuran);

                    if ($request->permission != '') $data->permission                    = $request->permission;
                    $data->foto_lain        = $safeName2;
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                     = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }

            if ($request->param3 != null) {
                $pe = 40;
                $pf = 50;
                foreach ($request->param3 as $in => $a) {
                    $pe++;
                    $pf++;
                    $pengukuran = array();
                    $pengukuran = [
                        'data-1' => $request->data11[$in],
                        'data-2' => $request->data12[$in],
                        'data-3' => $request->data13[$in],
                        'data-4' => $request->data14[$in],
                        'data-5' => $request->data15[$in],
                    ];

                    $img2 = str_replace('data:image/jpeg;base64,', '', $request->foto_lain3[$in]);
                    $file2 = base64_decode($img2);
                    $safeName2 = DATE('YmdHis') . '_' . $this->user_id . $pf . '.jpeg';
                    $destinationPath2 = public_path() . '/dokumentasi/sampling/';
                    $success2 = file_put_contents($destinationPath2 . $safeName2, $file2);
                    if ($request->kateg_uji3[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji3[$in] . '-' . json_encode($request->shift3[$in]);
                    } else if ($request->kateg_uji3[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji3[$in] . '-' . json_encode($request->shift3[$in]);
                    } else if ($request->kateg_uji3[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji3[$in] . '-' . json_encode($request->shift3[$in]);
                    } else {
                        $shift_peng = 'Sesaat';
                    }
                    $data = new DataLapanganDirectLain();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                    if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                    if ($request->lat != '') $data->latitude                            = $request->lat;
                    if ($request->longi != '') $data->longitude                        = $request->longi;
                    if ($request->categori != '') $data->kategori_3                = $request->categori;
                    if ($request->lok != '') $data->lokasi                         = $request->lok;
                    $data->parameter                         = $a;

                    if ($request->jenis_peng != '') $data->jenis_pengukuran              = $request->jenis_peng;
                    if ($request->kon_lapangan != '') $data->kondisi_lapangan              = $request->kon_lapangan;
                    if ($request->waktu != '') $data->waktu                        = $request->waktu;
                    $data->shift                   = $shift_peng;
                    if ($request->suhu != '') $data->suhu                          = $request->suhu;
                    if ($request->kelem != '') $data->kelembaban                        = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                    $data->pengukuran     = json_encode($pengukuran);

                    if ($request->permission != '') $data->permission                    = $request->permission;
                    $data->foto_lain        = $safeName2;
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                     = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            InsertActivityFdl::by($this->user_id)->action('input')->target("Direct Lain pada nomor sampel $request->no_sample")->save();


            DB::commit();
            return response()->json([
                'message' => "Data Sampling DIRECT LAIN Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
            ], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'line' => $e.getLine()
            ]);
        }
        
    }

    public function detail(Request $request)
    {
        $data = DataLapanganDirectLain::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample Direct lainnya success';

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
            'kon_lapangan'   => $data->kondisi_lapangan,

            'lokasi'         => $data->lokasi,
            'jenis_peng'     => $data->jenis_pengukuran,
            'waktu'          => $data->waktu,
            'shift'          => $data->shift,
            'suhu'           => $data->suhu,
            'kelem'          => $data->kelembaban,
            'tekanan_u'      => $data->tekanan_udara,
            'pengukuran'     => $data->pengukuran,

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
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('approve')->target("Direct Lain dengan nomor sampel $no_sample")->save();

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
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
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

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Direct Lain dengan nomor sampel $no_sample")->save();

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
