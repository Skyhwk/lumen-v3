<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganLingkunganHidup;

// DETAIL LAPANGAN
use App\Models\DetailLingkunganHidup;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\ParameterFdl;
use App\Services\InsertActivityFdl;

// SERVICE
use App\Services\SendTelegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlLingkunganHidupController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'lingkungan_hidup')->where('is_active', 1)->first();
            $listParameter = json_decode($parameter->parameters, true);
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', '11-Udara Ambient')
                ->where(function ($q) use ($listParameter) {
                    foreach ($listParameter as $keyword) {
                        $q->orWhere('parameter', 'like', "%$keyword%");
                    }
                })
                ->where('is_active', 1)->first();
            
            
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                $detailLingkanganHidup = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $dataLingkanganHidup = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                
                if ($detailLingkanganHidup !== NULL) {
                    // if ($this->karyawan != $dataLingkanganHidup->created_by) {
                    //     $user = MasterKaryawan::where('nama_lengkap', $dataLingkanganHidup->created_by)->first();
                    //     if ($user) {
                    //         $samplerName = $user->nama_lengkap;
                    //     } else {
                    //         $samplerName = "Unknown"; // atau sesuai kebutuhan Anda
                    //     }

                    //     return response()->json([
                    //         'message' => "No Sample $request->no_sample harus di input oleh sampler $samplerName"
                    //     ], 401);
                    // } else {
                        \DB::statement("SET SQL_MODE=''");
                        $parameter = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                        $parNonSes = array();
                        foreach ($parameter as $value) {
                            if ($value->shift_pengambilan != 'Sesaat') {
                                $p = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $value->parameter)->get();
                                $l = $value->shift_pengambilan;
                                $li = explode("-", $l);
                                $shift = '';
                                if (str_contains($value->parameter, 'PM')) {
                                    if ($li[0] == '24 Jam') {
                                        $shift = 25;
                                    } else if ($li[0] == '8 Jam') {
                                        $shift = 8;
                                    } else if ($li[0] == '6 Jam') {
                                        $shift = 6;
                                    }
                                } else if (str_contains($value->parameter, 'TSP')) {
                                    if ($li[0] == '24 Jam') {
                                        $shift = 25;
                                    } else if ($li[0] == '8 Jam') {
                                        $shift = 8;
                                    } else if ($li[0] == '6 Jam') {
                                        $shift = 6;
                                    }
                                } else {
                                    if ($li[0] == '24 Jam') {
                                        $shift = 4;
                                    } else if ($li[0] == '8 Jam') {
                                        $shift = 3;
                                    } else if ($li[0] == '6 Jam') {
                                        $shift = 6;
                                    }
                                }
                                if ($shift > count($p)) {
                                    $parNonSes[] = $value->parameter;
                                }
                            }
                        }

                        $p = json_decode($data->parameter);
                        $nilai_param = array();
                        $nilai_param2 = array();
                        foreach ($parameter as $key => $value) {
                            $nilai_param[] =  $value->parameter;
                        }
                        $param1 = array_diff($p, $nilai_param);
                        foreach ($param1 as $ke => $val) {
                            $nilai_param2[] =  $val;
                        }
                        $pp1 = str_replace("[", "", json_encode($nilai_param2));
                        $pp2 = str_replace("]", "", $pp1);
                        $pp3 = str_replace("[", "", json_encode($parNonSes));
                        $pp4 = str_replace("]", "", $pp3);

                        if ($pp2 == '') {
                            $param_fin = json_encode($parNonSes);
                        } else if ($pp4 == "") {
                            $param_fin = '[' . $pp2 . ']';
                        } else if ($pp2 !== "") {
                            $param_fin = '[' . $pp4 . ',' . $pp2 . ']';
                        }
                        $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                        return response()->json([
                            'no_sample'    => $data->no_sampel,
                            'jenis'        => $cek->nama_sub_kategori,
                            'keterangan' => $data->keterangan_1,
                            'id_ket' => explode('-', $data->kategori_3)[0],
                            'param' => $param_fin,
                            'list_parameter' => $listParameter
                        ], 200);
                    // }
                }else {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
                        'param' => $data->parameter,
                        'list_parameter' => $listParameter
                    ], 200);
                }
            }
        }
    }

    public function index(Request $request)
    { 
        try {
            $perPage = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search');

            $query = DataLapanganLingkunganHidup::with(['detail', 'detailLingkunganHidup'])
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

            $modified = $data->getCollection()->map(function ($item) {
                $item->grouped_shift = $item->detailLingkunganHidup
                    ->groupBy('shift_pengambilan')
                    ->map(function ($group) {
                        return $group->values(); 
                    });

                return $item;
            });

            $data->setCollection($modified);

            return response()->json($data);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal Get Data'
            ]);
        }
    }


    public function getShift(Request $request)
    {
        // $importantKeyword = [
        //     'As', 'Ba', 'Cl-', 'Cl2', 'Co', 'Cr', 'Cu', 'Dustfall', 'Fe', 'H2S', 'HCl',
        //     'HF', 'Hg', 'Kelembaban', 'Mn', 'NH3', 'Ni', 'NO2', 'NOx', 'O3', 'Ox',
        //     'Passive NO2', 'Passive SO2', 'Pb', 'PM 10', 'PM 2.5', 'Sb', 'Se', 'Sn',
        //     'SO2', 'Suhu', 'TSP', 'Zn', 'Aluminium'
        // ];

        $parameter_tsp = ParameterFdl::select("parameters")->where('is_active', 1)->where('nama_fdl','parameter_tsp_lh')->first();

        // $parameter_no2 = [
        //     "NO2", "NO2 (24 Jam)", "NO2 (8 Jam)", "NO2 (6 Jam)", "NOx","NO2 8J (LK-pm)","NO2 8J (LK-µg)","NO2 SS (LK-pm)","NO2 SS (LK-µg)",
        // ];
        $data = DetailLingkunganHidup::where('no_sampel', $request->no_sample);
        $lh_parameter = DetailLingkunganHidup::where('no_sampel', $request->no_sample);
        if($request->shift == 'L1'){
            $data = $data->where(function ($query) {
                $query->where('shift_pengambilan', 'Sesaat')
                    ->orWhere('shift_pengambilan', 'L1');
            })->first();

            $lh_parameter = $lh_parameter->where(function ($query) {
                $query->where('shift_pengambilan', 'Sesaat')
                    ->orWhere('shift_pengambilan', 'L1');
            })->pluck('parameter')->toArray();
        }else{
            $data = $data->where('shift_pengambilan', 'Sesaat')->first();

            $lh_parameter = $lh_parameter->where('shift_pengambilan', 'Sesaat')
                ->pluck('parameter')
                ->toArray();
        }

        $po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
        \DB::statement("SET SQL_MODE=''");
        $param = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->groupBy('parameter')->get();

        $listParameter = ParameterFdl::select('parameters')->where('nama_fdl', 'lingkungan_hidup')->where('is_active', 1)->first();
        $parNonSes = array();
        foreach ($param as $value) {
            // pengecualian untuk Dustfall
            if (str_contains($value->parameter, 'Dustfall')) {
                $p = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
                    ->where('parameter', $value->parameter)->get();

                $shift = 2; // Batas shift untuk Dustfall

                if ($shift > count($p)) {
                    $parNonSes[] = $value->parameter;
                }
            // } else if ($value->kategori_pengujian != 'Sesaat') {
            } else {
                $p = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
                    ->where('parameter', $value->parameter)->get();
                $l = $value->kategori_pengujian;
                $li = explode("-", $l);
                $shift = '';
                if (str_contains($value->parameter, 'PM')) {
                    if ($li[0] == '24 Jam') {
                        $shift = 25;
                    } else if ($li[0] == '8 Jam') {
                        $shift = 8;
                    } else if ($li[0] == '6 Jam') {
                        $shift = 6;
                    }
                } else if (str_contains($value->parameter, 'TSP')) {
                    if ($li[0] == '24 Jam') {
                        $shift = 25;
                    } else if ($li[0] == '8 Jam') {
                        $shift = 8;
                    } else if ($li[0] == '6 Jam') {
                        $shift = 6;
                    }
                } else {
                    if ($li[0] == '24 Jam') {
                        $shift = 4;
                    } else if ($li[0] == '8 Jam') {
                        $shift = 3;
                    } else if ($li[0] == '6 Jam') {
                        $shift = 6;
                    }else if ($li[0] == '3 Jam') {
                        $shift = 3;
                    }
                }
                if ($shift > count($p)) {
                    $parNonSes[] = $value->parameter;
                }
            }
        }
        $p = json_decode($po->parameter);
        $nilai_param = array();
        $nilai_param2 = array();
        // Membersihkan array $p agar hanya menyimpan bagian setelah ";"
        $cleaned_p = array_map(function($item) {
            $parts = explode(";", $item);
            return $parts[1] ?? ''; // Ambil bagian setelah ";"
        }, $p);

        // Bandingkan dengan array yang sudah bersih
        $param1 = array_diff($cleaned_p, $nilai_param);

        foreach ($param1 as $ke => $val) {
            $nilai_param2[] =  $val;
        }

        $pp1 = str_replace("[", "", json_encode($nilai_param2));
        $pp2 = str_replace("]", "", $pp1);
        $pp3 = str_replace("[", "", json_encode($parNonSes));
        $pp4 = str_replace("]", "", $pp3);

        if ($pp2 == '') {
            $param_fin = json_encode($parNonSes);
        } else if ($pp4 == "") {
            $param_fin = '[' . $pp2 . ']';
        } else if ($pp2 !== "") {
            $param_fin = '[' . $pp4 . ',' . $pp2 . ']';
        }
        
        
        // Hapus parameter yang ada di $existing_parameters
        $filtered_param = array_values(array_diff($nilai_param2, $lh_parameter));
        // Buat output JSON yang sesuai
        $param_fin = json_encode($filtered_param, JSON_UNESCAPED_UNICODE);
        $parameterVolatile = ParameterFdl::select("parameters")->where('is_active', 1)->where('nama_fdl','senyawa_volatile_lh')->first();
        if ($data) {
            return response()->json([
                'non'      => 1,
                'keterangan'      => $data->keterangan,
                'keterangan_2'    => $data->keterangan_2,
                'titik_koordinat' => $data->titik_koordinat,
                'id_ket' => explode('-', $po->kategori_3)[0],
                'lat'             => $data->latitude,
                'longi'           => $data->longitude,
                'lokasi'          => $data->lokasi,
                'cuaca'           => $data->cuaca,
                'waktu'           => $data->waktu_pengukuran,
                'kecepatan'       => $data->kecepatan_angin,
                'arah_angin'      => $data->arah_angin,
                'jarak'           => $data->jarak_sumber_cemaran,
                'suhu'            => $data->suhu,
                'kelem'           => $data->kelembapan,
                'intensitas'      => $data->intensitas,
                'tekanan_u'       => $data->tekanan_udara,
                'desk_bau'        => $data->deskripsi_bau,
                'metode'          => $data->metode_pengukuran,
                'satuan'          => $data->satuan,
                'catatan'          => $data->catatan_kondisi_lapangan,
                'durasi_pengambilan'          => $data->durasi_pengambilan,
                'foto_lokasi_sample'          => $data->foto_lokasi_sampel,
                'foto_kondisi_sample'          => $data->foto_kondisi_sampel,
                'foto_lain'          => $data->foto_lain,
                'parameterList' => $listParameter ? json_decode($listParameter->parameters, true) : [],
                'param' => json_decode($param_fin, true),
                'is_filled' => true,
                // 'important_keyword' => $importantKeyword,
                'parameter_tsp' => json_decode($parameter_tsp->parameters, true),
                'parameter_volatile' => json_decode($parameterVolatile->parameters, true),
                // 'parameter_no2' => $parameter_no2

            ], 200);
            $this->resultx = 'get shift sample lingkuhan hidup success';
        } else {
            return response()->json([
                'non'      => 2,
                'no_sample'    => $po->no_sampel,
                'keterangan' => $po->keterangan_1,
                'id_ket' => explode('-', $po->kategori_3)[0],
                'param' => json_decode($param_fin, true),
                'parameterList' => $listParameter ? json_decode($listParameter->parameters, true) : [],
                'is_filled' => false,
                // 'important_keyword' => $importantKeyword,
                'parameter_tsp' => json_decode($parameter_tsp->parameters, true),
                'parameter_volatile' => json_decode($parameterVolatile->parameters, true),
                // 'parameter_no2' => $parameter_no2
            ], 200);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $fdl = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
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
                    'message' => 'Foto lokasi alat tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    $cek = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                    if ($request->shift_pengambilan !== "Sesaat") {
                        $nilai_array = array();
                        foreach ($cek as $key => $value) {
                            $durasi = $value->shift_pengambilan;
                            $durasi = explode("-", $durasi);
                            $durasi = $durasi;
                            $nilai_array[$key] = str_replace('"', "", $durasi);
                        }
                        if (in_array($request->shift_pengambilan, $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift_pengambilan . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            
            
            if(isset($request->param) && $request->param != null){
                foreach ($request->param as $in => $a) {
                    $pengukuran = array();
                    $durasii = null;
                    if ($a == 'TSP (24 Jam)' || $a == 'Pb (24 Jam)' || $a == 'PM 10 (24 Jam)' || $a == 'PM 10 (8 Jam)' || $a == 'PM 2.5 (24 Jam)' || $a == 'PM 2.5 (8 Jam)') {
                        if ($request->shift_pengambilan == 'L25') {
                            $pengukuran = [
                                'Flow' => $request->flow1[$in],
                            ];
                            if ($request->durasi[$in] != '' || $request->durasi2[$in] != '') {
                                $jam = ($request->durasi[$in] != '' && $request->durasi[$in] != 0 && $request->durasi[$in] != '-') ? $request->durasi[$in] . ' Jam, ' : '';
                                $menit = ($request->durasi2[$in] != '' && $request->durasi2[$in] != 0 && $request->durasi2[$in] != '-') ? $request->durasi2[$in] . ' Menit' : '';
                                $durasii = $jam . $menit;
                            }
                        } else {
                            $pengukuran = [
                                'Flow' => $request->flow1[$in],
                            ];
                        }
                    } else if (str_contains($a, 'Dustfall')) {
                        if ($request->keterangan_alat[$a] != '') {
                            if($request->keterangan_alat[$a] == 'pemasangan_alat'){
                                $pengukuran = [
                                    'keterangan' => $request->keterangan_alat[$a] ?? null,
                                    'tanggal_pemasangan' => $request->tanggal_pemasangan[$a] ?? null,
                                    'luas_botol' => ($request->luas_botol[$a] ?? null) ? $request->luas_botol[$a] . ' m2' : null,
                                ];
                            }else{
                                $pengukuran = [
                                    'keterangan' => $request->keterangan_alat[$a] ?? null,
                                    'tanggal_selesai' => $request->tanggal_selesai[$a] ?? null,
                                    'volume_filtrat' => ($request->volume_filtrat[$a] ?? null) ? $request->volume_filtrat[$a] . ' liter' : null,
                                ];
                            }
                        }
                    } else if (
                        $a == "Al. Hidrokarbon" ||
                        $a == "Al. Hidrokarbon (8 Jam)" ||
                        $a == "Acetone" ||
                        $a == "Alkana Gas" ||
                        $a == "Butanon" ||
                        $a == "Asam Asetat" ||
                        $a == "Benzene" ||
                        $a == "Benzene (8 Jam)" ||
                        $a == "Cyclohexanone" ||
                        $a == "EA" ||
                        $a == "Ethanol" ||
                        $a == "HCl (8 Jam)" ||
                        $a == "HCl" ||
                        $a == "HF" ||
                        $a == "IPA" ||
                        $a == "MEK" ||
                        $a == "Stirena" ||
                        $a == "Stirena (8 Jam)" ||
                        $a == "Toluene" ||
                        $a == "Toluene (8 Jam)" ||
                        $a == "Xylene" ||
                        $a == "Xylene (8 Jam)" ||
                        $a == "NH3" ||
                        $a == "H2S"
                    ) {
    
                        $pengukuran = [
                            'Flow 1' => $request->flow1[$in],
                            'Flow 2' => $request->flow2[$in],
                            'Flow 3' => $request->flow3[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                        ];
                    } else if (str_contains($a, 'O3') || $a == 'Ox') {
                        $pengukuran = [
                            'Flow 1' => $request->flow1[$in],
                            'Flow 2' => $request->flow2[$in],
                            'Flow 3' => $request->flow3[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                            'Flow 4' => $request->flow4[$in],
                            'Flow 5' => $request->flow5[$in],
                            'Flow 6' => $request->flow6[$in],
                            'Durasi 2' => $request->durasi2[$in] . ' menit',
                        ];
                    } else if ($a == 'Passive SO2' || $a == 'Passive NO2') {
                        $pengukuran = [
                            'Durasi 2' => $request->durasi[$in] . ' menit',
                        ];
                    } else {
                        $pengukuran = [
                            'Flow 1' => $request->flow1[$in],
                            'Flow 2' => $request->flow2[$in],
                            'Flow 3' => $request->flow3[$in],
                            'Flow 4' => $request->flow4[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                        ];
                    }
                    $absorbansi = '';
                    if ($request->paramAb != null) {
                        foreach ($request->paramAb as $pr => $pa) {
                            if ($pa == $a) {
                                $absorbansi = array();
                                if (str_contains($pa, 'O3') || $pa == 'Ox') {
                                    $absorbansi = [
                                        'blanko' => $request->blanko[$pr],
                                        'data-1' => $request->data1[$pr],
                                        'data-2' => $request->data2[$pr],
                                        'data-3' => $request->data3[$pr],
                                        'blanko2' => $request->blanko2[$pr],
                                        'data-4' => $request->data4[$pr],
                                        'data-5' => $request->data5[$pr],
                                        'data-6' => $request->data6[$pr],
                                    ];
                                } else {
                                    $absorbansi = [
                                        'blanko' => $request->blanko[$pr],
                                        'data-1' => $request->data1[$pr],
                                        'data-2' => $request->data2[$pr],
                                        'data-3' => $request->data3[$pr],
                                    ];
                                }
                            }
                        }
                    }
                    
                    $shift2 = $request->shift_pengambilan;
                    if ($request->kateg_uji[$in] == null || $request->kateg_uji[$in] == '') {
                        $shift_peng = 'Sesaat';
                        $shift2 = 'Sesaat';
                    } else if ($request->kateg_uji[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift_pengambilan);
                    } else if ($request->kateg_uji[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift_pengambilan);
                    } else if ($request->kateg_uji[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift_pengambilan);
                    }
                    
                    $fdlvalue = new DetailLingkunganHidup();
                    $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                    if ($request->koordinat != '') $fdlvalue->titik_koordinat             = $request->koordinat;
                    if ($request->latitude != '') $fdlvalue->latitude                            = $request->latitude;
                    if ($request->longitude != '') $fdlvalue->longitude                        = $request->longitude;
                    if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
                    $fdlvalue->parameter                         = $a;
    
                    if ($request->cuaca != '') $fdlvalue->cuaca              = $request->cuaca;
                    if ($request->kecepatan != '') $fdlvalue->kecepatan_angin              = $request->kecepatan;
                    if ($request->arah_angin != '') $fdlvalue->arah_angin              = $request->arah_angin;
                    if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran              = $request->jarak;
                    if ($request->jam_pengambilan != '') $fdlvalue->waktu_pengukuran                        = $request->jam_pengambilan;
                    if ($request->intensitas != '') $fdlvalue->intensitas                        = $request->intensitas;
                    $fdlvalue->satuan                        = $request->satuan[$in];
                    $fdlvalue->kategori_pengujian                   = $shift_peng;
                    $fdlvalue->shift_pengambilan                   = $shift2;
                    if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan                          = $request->catatan;
                    if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                    if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                    if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                    if ($request->desk_bau != '') $fdlvalue->deskripsi_bau                     = $request->desk_bau;
                    if ($request->metode != '') $fdlvalue->metode_pengukuran                     = $request->metode;
                    $fdlvalue->durasi_pengambilan       = $durasii;
                    $fdlvalue->pengukuran     = json_encode($pengukuran);
                    if ($absorbansi != '') $fdlvalue->absorbansi     = json_encode($absorbansi);
    
                    if ($request->permission != '') $fdlvalue->permission                      = $request->permission;
                    if ($request->statFoto == 'adaFoto') {
                        if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    } else {
                        if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    }
                    $fdlvalue->created_by                     = $this->karyawan;
                    $fdlvalue->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                    $fdlvalue->save();
                }
            }else{
                $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $listParameter = json_decode($orderDetail->parameter);

                // ambil nama dari parameter (skip id)
                $names = array_map(function($item) {
                    [, $name] = explode(";", $item);
                    return $name;
                }, $listParameter);

                $parameter = ['Suhu', 'Kelembaban', 'Laju Ventilasi'];

                $matched = array_intersect($names, $parameter);

                if (count($matched) > 0) {
                    foreach ($matched as $a) {
                        $fdlvalue = new DetailLingkunganHidup();
                        $fdlvalue->no_sampel = strtoupper(trim($request->no_sample));

                        if ($request->keterangan_4 != '') $fdlvalue->keterangan = $request->keterangan_4;
                        if ($request->keterangan_2 != '') $fdlvalue->keterangan_2 = $request->keterangan_2;
                        if ($request->koordinat != '')   $fdlvalue->titik_koordinat = $request->koordinat;
                        if ($request->latitude != '')    $fdlvalue->latitude = $request->latitude;
                        if ($request->longitude != '')   $fdlvalue->longitude = $request->longitude;
                        if ($request->lok != '')         $fdlvalue->lokasi = $request->lok;

                        $fdlvalue->parameter = $a;

                        if ($request->cuaca != '')       $fdlvalue->cuaca = $request->cuaca;
                        if ($request->intensitas != '')  $fdlvalue->intensitas = $request->intensitas;
                        if ($request->aktifitas != '')   $fdlvalue->aktifitas = $request->aktifitas;
                        if ($request->jarak != '')       $fdlvalue->jarak_sumber_cemaran = $request->jarak;
                        if ($request->jam_pengambilan != '') $fdlvalue->waktu_pengukuran = $request->jam_pengambilan;
                        if ($request->kecepatan != '')         $fdlvalue->kecepatan_angin = $request->kecepatan;
                        $fdlvalue->kategori_pengujian = 'Sesaat';
                        $fdlvalue->shift_pengambilan  = 'Sesaat';

                        if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan = $request->catatan;
                        if ($request->suhu != '')    $fdlvalue->suhu = $request->suhu;
                        if ($request->kelem != '')   $fdlvalue->kelembapan = $request->kelem;
                        if ($request->tekU != '')    $fdlvalue->tekanan_udara = $request->tekU;
                        if ($request->desk_bau != '')$fdlvalue->deskripsi_bau = $request->desk_bau;
                        if ($request->metode != '')  $fdlvalue->metode_pengukuran = $request->metode;
                        if ($request->permission != '') $fdlvalue->permission = $request->permission;

                        if ($request->statFoto == 'adaFoto') {
                            if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                            if ($request->foto_alat != '')          $fdlvalue->foto_kondisi_sampel = self::convertImg($request->foto_alat, 2, $this->user_id);
                            if ($request->foto_lain != '')          $fdlvalue->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                        } else {
                            if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                            if ($request->foto_alat != '')          $fdlvalue->foto_kondisi_sampel = self::convertImg($request->foto_alat, 2, $this->user_id);
                            if ($request->foto_lain != '')          $fdlvalue->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                        }

                        $fdlvalue->created_by = $this->karyawan;
                        $fdlvalue->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $fdlvalue->save();
                    }
                }else{
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Parameter Suhu, Laju Ventilasi, dan Kelembaban tidak di order pada no sampel ini .!'
                    ], 401);
                }
            }
            
            if (is_null($fdl)) {
                $data = new DataLapanganLingkunganHidup();
                if ($request->categori != '') $data->kategori_3                 = $request->categori;
                $data->no_sampel                                                = strtoupper(trim($request->no_sample));
                $data->permission                                               = $request->permission;
                $data->created_by                                               = $this->karyawan;
                $data->created_at                                               = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
            }

            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $header = DB::table('lingkungan_header')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            InsertActivityFdl::by($this->user_id)->action('input')->target(" nomor sampel $request->no_sample")->save();
            
            DB::commit();
            
            return response()->json([
                'message' => "Data Sampling LINGKUNGAN HIDUP Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage().$e->getLine()], 401);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->id) {
                return response()->json(['message' => 'Gagal Delete, ID tidak valid'], 400);
            }

            $header = DataLapanganLingkunganHidup::find($request->id);
            if (!$header) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $no_sampel = strtoupper(trim($header->no_sampel));
            DetailLingkunganHidup::where('no_sampel', $no_sampel)->delete();

            $this->resultx = "Data Sampling FDL Lingkungan Hidup Dengan No Sample $no_sampel berhasil disimpan oleh $this->karyawan";

            $header->delete();

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Lingkungan Hidup Udara pada nomor sampel $no_sampel")->save();

            DB::commit();

            return response()->json([
                'message' => $this->resultx,
            ]);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Gagal Delete',
                // 'error' => $e->getMessage(), // Aktifkan jika debugging
            ], 500);
        }
    }

    public function deleteParameter(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->where('id', $request->id)
                ->first();

            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $parameter = $data->parameter;

            $data->delete();

            InsertActivityFdl::by($this->user_id)
                ->action('delete')
                ->target("parameter $parameter di nomor sampel {$request->no_sampel}")
                ->save();

            DB::commit();

            return response()->json([
                'message' => "Fdl LH parameter $parameter di no sample {$request->no_sampel} berhasil dihapus oleh {$this->karyawan}.!",
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal Delete',
            ], 500);
        }
    }


    public function deleteShift(Request $request)
    {
        DB::beginTransaction();
        try {
            DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))
            ->where('shift_pengambilan', $request->shift)
            ->delete();
            
            InsertActivityFdl::by($this->user_id)->action('delete')->target(" shift $request->shift di nomor sampel $request->no_sampel")->save();

            DB::commit();

            return response()->json([
                'message' => "Fdl LH shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh {$this->karyawan}.!",
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 500);
        }   
    }

    /**
     * Handle parameter deletion response
     */
    private function handleParameterDeletion($noSampel, $statusParameters)
    {
        $remainingDetails = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($noSampel)))->count();
        $nama = $this->karyawan;
        $message = "Fdl LH parameter {$statusParameters} di no sample {$noSampel} berhasil dihapus oleh {$nama}!";
        
        if ($remainingDetails > 0) {
            return response()->json([
                'message' => $message,
                'kategori' => 1
            ], 201);
        } else {
            DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($noSampel)))->delete();
            return response()->json([
                'message' => $message,
                'kategori' => 2
            ], 201);
        }
    }

    /**
     * Handle individual parameter deletion
     */
    private function handleIndividualParameterDeletion($request)
    {
        $detail = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
        $detailToDelete = DetailLingkunganHidup::where('id', $request->id)->first();
        
        $nama = $this->karyawan;
        $message = "Fdl LH parameter {$detail->first()->parameter} di no sample {$detail->first()->no_sampel} berhasil dihapus oleh {$nama}!";
        
        if ($detail->count() > 1) {
            $detailToDelete->delete();
            return response()->json([
                'message' => $message,
                'kategori' => 1
            ], 201);
        } else {
            DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->delete();
            $detailToDelete->delete();
            return response()->json([
                'message' => $message,
                'kategori' => 2
            ], 201);
        }
    }

    public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        // if (!file_exists(public_path() . '/dokumentasi/'.DATE('Ymd'))) {
        //     mkdir(public_path() . '/dokumentasi/'.DATE('Ymd') , 0777, true);
        // }
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }
}