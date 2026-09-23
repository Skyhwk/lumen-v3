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
use App\Services\FdlOrderDetailService;
use App\Support\FdlLingkunganSharedParameters;

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
        if ($response = $this->ensureSamplerCheckedInForSample($request)) {
             return $response;
        }

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
                        $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                        $orderLabels = FdlLingkunganSharedParameters::parseOrderParameterLabels($data->parameter);

                        return response()->json([
                            'no_sample'    => $data->no_sampel,
                            'jenis'        => $cek->nama_sub_kategori,
                            'keterangan' => $data->keterangan_1,
                            'id_ket' => explode('-', $data->kategori_3)[0],
                            'param' => $orderLabels,
                            'list_parameter' => $listParameter,
                            'available_shifts' => FdlLingkunganSharedParameters::getAvailableShifts(
                                $data->no_sampel,
                                $orderLabels,
                                DetailLingkunganHidup::class
                            ),
                        ], 200);
                }else {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    $orderLabels = FdlLingkunganSharedParameters::parseOrderParameterLabels($data->parameter);
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
                        'param' => $data->parameter,
                        'list_parameter' => $listParameter,
                        'available_shifts' => FdlLingkunganSharedParameters::getAvailableShifts(
                            $data->no_sampel,
                            $orderLabels,
                            DetailLingkunganHidup::class
                        ),
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
                            ->whereDate('created_at', '>=', Carbon::now()->subDays(config('app.fdl_index_subdays')));
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
        try {
            $parameter_tsp = ParameterFdl::select("parameters")
                ->where('is_active', 1)
                ->where('nama_fdl', 'parameter_tsp_lh')
                ->first();
    
            if (!$parameter_tsp) {
                throw new \Exception("ParameterFdl dengan nama_fdl='parameter_tsp_lh' dan is_active=1 tidak ditemukan.");
            }
    
            $data = DetailLingkunganHidup::where('no_sampel', $request->no_sample);
            $lh_parameter = DetailLingkunganHidup::where('no_sampel', $request->no_sample);
    
            if ($request->shift == 'L1') {
                $data = $data->where(function ($query) {
                    $query->where('shift_pengambilan', 'Sesaat')
                        ->orWhere('shift_pengambilan', 'L1');
                })->first();
    
                $lh_parameter = $lh_parameter->where(function ($query) {
                    $query->where('shift_pengambilan', 'Sesaat')
                        ->orWhere('shift_pengambilan', 'L1');
                })->pluck('parameter')->toArray();
            } else {
                $data = $data->where('shift_pengambilan', $request->shift)->first();
    
                $lh_parameter = $lh_parameter->where('shift_pengambilan', $request->shift)
                    ->pluck('parameter')
                    ->toArray();
            }
    
            $po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
    
            if (!$po) {
                throw new \Exception("OrderDetail dengan no_sampel='{$request->no_sample}' dan is_active=1 tidak ditemukan.");
            }
    
            \DB::statement("SET SQL_MODE=''");
    
            $listParameter = ParameterFdl::select('parameters')->where('nama_fdl', 'lingkungan_hidup')->where('is_active', 1)->first();
    
            $orderParameterLabels = FdlLingkunganSharedParameters::parseOrderParameterLabels($po->parameter);
            $param_fin_array = FdlLingkunganSharedParameters::buildPendingParametersForShift(
                $request->no_sample,
                $request->shift,
                $orderParameterLabels,
                DetailLingkunganHidup::class
            );

            if (empty($param_fin_array)) {
                return response()->json([
                    'message' => 'Shift ' . $request->shift . ' sudah lengkap untuk no sample ini',
                ], 422);
            }

            $param_fin = json_encode($param_fin_array, JSON_UNESCAPED_UNICODE);
    
            $parameterVolatile = ParameterFdl::select("parameters")
                ->where('is_active', 1)
                ->where('nama_fdl', 'senyawa_volatile_lh')
                ->first();
    
            if (!$parameterVolatile) {
                throw new \Exception("ParameterFdl dengan nama_fdl='senyawa_volatile_lh' dan is_active=1 tidak ditemukan.");
            }
    
            $volatile_array = json_decode($parameterVolatile->parameters, true) ?? [];
            $volatile_lower = array_map('strtolower', $volatile_array);
    
            $form_mappings = [];
            foreach ($param_fin_array as $pr) {
                $pLower = strtolower($pr);
                $kateg = '';
    
                if (str_contains($pLower, '24 jam') || str_contains($pLower, '24j')) {
                    $kateg = '24 Jam';
                } else if (str_contains($pLower, '8 jam') || str_contains($pLower, '8j')) {
                    $kateg = '8 Jam';
                } else if (str_contains($pLower, '6 jam')) {
                    $kateg = '6 Jam';
                }
    
                $satuan = '(L/m)';
                if (str_contains($pLower, 'tsp') || str_contains($pLower, 'pm 10') || str_contains($pLower, 'pm 2.5')) {
                    $satuan = '(m3/menit)';
                }
    
                $type = 6;
                if (in_array($pLower, ["tsp (24 jam)", "tsp 24j (ua)", "pm 10 (24 jam)", "pm 10 (8 jam)", "pm 2.5 (24 jam)", "pm 2.5 (8 jam)"])) {
                    $type = 1;
                } else if (str_starts_with($pLower, "o3") || $pLower === "ox") {
                    $type = 2;
                } else if (in_array($pLower, $volatile_lower)) {
                    $type = 3;
                } else if (str_contains($pLower, "dustfall")) {
                    $type = 4;
                } else if (in_array($pLower, ["passive so2", "passive no2"])) {
                    $type = 5;
                }
    
                $form_mappings[$pr] = [
                    'type' => $type,
                    'kateg' => $kateg,
                    'satuan' => $satuan
                ];
            }
    
            if ($data) {
                return response()->json([
                    'non'                  => 1,
                    'keterangan'           => $data->keterangan,
                    'keterangan_2'         => $data->keterangan_2,
                    'titik_koordinat'      => $data->titik_koordinat,
                    'id_ket'               => explode('-', $po->kategori_3)[0],
                    'lat'                  => $data->latitude,
                    'longi'                => $data->longitude,
                    'lokasi'               => $data->lokasi,
                    'cuaca'                => $data->cuaca,
                    'waktu'                => $data->waktu_pengukuran,
                    'kecepatan'            => $data->kecepatan_angin,
                    'arah_angin'           => $data->arah_angin,
                    'jarak'                => $data->jarak_sumber_cemaran,
                    'suhu'                 => $data->suhu,
                    'kelem'                => $data->kelembapan,
                    'intensitas'           => $data->intensitas,
                    'tekanan_u'            => $data->tekanan_udara,
                    'desk_bau'             => $data->deskripsi_bau,
                    'metode'               => $data->metode_pengukuran,
                    'satuan'               => $data->satuan,
                    'catatan'              => $data->catatan_kondisi_lapangan,
                    'durasi_pengambilan'   => $data->durasi_pengambilan,
                    'foto_lokasi_sample'   => $data->foto_lokasi_sampel,
                    'foto_kondisi_sample'  => $data->foto_kondisi_sampel,
                    'foto_lain'            => $data->foto_lain,
                    'parameterList'        => $listParameter ? json_decode($listParameter->parameters, true) : [],
                    'param'                => json_decode($param_fin, true),
                    'is_filled'            => true,
                    'parameter_tsp'        => json_decode($parameter_tsp->parameters, true),
                    'parameter_volatile'   => json_decode($parameterVolatile->parameters, true),
                    'form_mappings'                  => $form_mappings,
                    'excluded_multiselect_parameters' => FdlLingkunganSharedParameters::excludedFromMultiselect(),
                ], 200);
            } else {
                return response()->json([
                    'non'                => 2,
                    'no_sample'          => $po->no_sampel,
                    'keterangan'         => $po->keterangan_1,
                    'id_ket'             => explode('-', $po->kategori_3)[0],
                    'param'              => json_decode($param_fin, true),
                    'parameterList'      => $listParameter ? json_decode($listParameter->parameters, true) : [],
                    'is_filled'          => false,
                    'parameter_tsp'      => json_decode($parameter_tsp->parameters, true),
                    'parameter_volatile' => json_decode($parameterVolatile->parameters, true),
                    'form_mappings'                  => $form_mappings,
                    'excluded_multiselect_parameters' => FdlLingkunganSharedParameters::excludedFromMultiselect(),
                ], 200);
            }
        } catch (\Exception $th) {
            \Log::error('getShift (Lingkungan Hidup) error: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);
    
            return response()->json([
                'message' => $th->getMessage(),
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $savedDetailCount = 0;
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
                    
            $selectedParams = $request->param;
            if (!is_array($selectedParams)) {
                $selectedParams = ($selectedParams !== null && $selectedParams !== '') ? [$selectedParams] : [];
            }

            if (count($selectedParams) > 0) {
                foreach ($selectedParams as $in => $a) {
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
                        // dd($request->diameter_botol[$a]);
                        if ($request->keterangan_alat[$a] != '') {
                            if($request->keterangan_alat[$a] == 'pemasangan_alat'){
                                $pengukuran = [
                                    'keterangan' => $request->keterangan_alat[$a] ?? null,
                                    'tanggal_pemasangan' => $request->tanggal_pemasangan[$a] ?? null,
                                    'diameter_botol' => $request->diameter_botol[$a] . ' cm' ?? null,
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
                    
                    $kategUji = FdlLingkunganSharedParameters::resolveKategoriForStore(
                        $request->kateg_uji[$in] ?? null,
                        $a
                    );
                    $shiftFields = FdlLingkunganSharedParameters::resolveShiftFieldsForStore(
                        $kategUji,
                        $request->shift_pengambilan
                    );
                    $shift_peng = $shiftFields['kategori_pengujian'];
                    $shift2 = $shiftFields['shift_pengambilan'];
                    
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
                    $savedDetailCount++;
                }
            }

            $order_detail = OrderDetail::select('parameter')->where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($order_detail) {
                $rawOrderParams = json_decode($order_detail->parameter, true) ?? [];

                $orderParameters = array_map(function ($item) {
                    $parts = explode(';', $item);
                    return isset($parts[1]) ? trim($parts[1]) : null;
                }, $rawOrderParams);

                $filteredParameters = FdlLingkunganSharedParameters::matchAmbientParametersFromOrder($orderParameters);

                if (count($selectedParams) === 0 && empty($filteredParameters)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Parameter tidak ditemukan dalam order'
                    ], 401);
                }

                foreach ($filteredParameters as $a) {
                    $satuan = '';

                    if ($a == 'Kelembaban' || $a == 'Kelembaban (24 Jam)') {
                        $satuan = ' %';
                    } else if ($a == 'Suhu' || $a == 'Suhu (24 Jam)') {
                        $satuan = ' °C';
                    } else if ($a == 'Laju Ventilasi') {
                        $satuan = ' m/s';
                    } else if ($a == 'Laju Ventilasi (8 Jam)') {
                        $satuan = ' m/s';
                    } else if ($a == 'Pertukaran Udara') {
                        $satuan = ' m3';
                    } else if ($a == 'Tekanan Udara (LK)') {
                        $satuan = ' mmHg';
                    }

                    // Parameter tanpa durasi di header hanya di L1; yang ada durasi di nama ikut shift aktif.
                    $isDurasi = FdlLingkunganSharedParameters::parameterHasDurasiInName($a);

                    if ($request->shift_pengambilan != 'L1' && !$isDurasi) {
                        continue; // skip parameter ini
                    }

                    $shiftAmbientFields = FdlLingkunganSharedParameters::resolveShiftFieldsForStore(
                        FdlLingkunganSharedParameters::resolveKategoriFromParameterName($a),
                        $request->shift_pengambilan
                    );

                    $alreadyStored = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->where('parameter', $a)
                        ->where('kategori_pengujian', $shiftAmbientFields['kategori_pengujian'])
                        ->where('shift_pengambilan', $shiftAmbientFields['shift_pengambilan'])
                        ->exists();
                    if ($alreadyStored) {
                        continue;
                    }

                    $fdlvalue = new DetailLingkunganHidup();
                    $fdlvalue->no_sampel = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $fdlvalue->keterangan = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $fdlvalue->keterangan_2 = $request->keterangan_2;
                    if ($request->koordinat != '') $fdlvalue->titik_koordinat = $request->koordinat;
                    if ($request->latitude != '') $fdlvalue->latitude = $request->latitude;
                    if ($request->longitude != '') $fdlvalue->longitude = $request->longitude;
                    if ($request->lok != '') $fdlvalue->lokasi = $request->lok;
                    $fdlvalue->parameter = $a;

                    if ($request->cuaca != '') $fdlvalue->cuaca              = $request->cuaca;
                    if ($request->kecepatan != '') $fdlvalue->kecepatan_angin              = $request->kecepatan;
                    if ($request->arah_angin != '') $fdlvalue->arah_angin              = $request->arah_angin;
                    if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran              = $request->jarak;
                    if ($request->jam_pengambilan != '') $fdlvalue->waktu_pengukuran = $request->jam_pengambilan;
                    if ($request->intensitas != '') $fdlvalue->intensitas                        = $request->intensitas;

                    if ($request->laju_ventilasi != '') $fdlvalue->laju_ventilasi = $request->laju_ventilasi;
                    if ($request->aktifitas_pekerja != '') $fdlvalue->aktifitas = $request->aktifitas_pekerja;
                    if ($request->jarak_sumber_cemaran != '') $fdlvalue->jarak_sumber_cemaran = $request->jarak_sumber_cemaran;

                    $fdlvalue->kategori_pengujian = $shiftAmbientFields['kategori_pengujian'];
                    $fdlvalue->shift_pengambilan = $shiftAmbientFields['shift_pengambilan'];

                    if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan = $request->catatan;
                    if ($request->suhu != '') $fdlvalue->suhu = $request->suhu;
                    if ($request->kelem != '') $fdlvalue->kelembapan = $request->kelem;
                    if ($request->tekU != '') $fdlvalue->tekanan_udara = $request->tekU;
                    if ($request->desk_bau != '') $fdlvalue->deskripsi_bau = $request->desk_bau;
                    if ($request->metode != '') $fdlvalue->metode_pengukuran = $request->metode;
                    if ($request->permission != '') $fdlvalue->permission = $request->permission;

                    $fdlvalue->satuan = $satuan;

                    if ($request->statFoto == 'adaFoto') {
                        if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel = self::convertImg($request->foto_alat, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                    } else {
                        if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                        if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel = self::convertImg($request->foto_alat, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                    }

                    $fdlvalue->created_by = $this->karyawan;
                    $fdlvalue->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $fdlvalue->save();
                    $savedDetailCount++;
                }
            }

            if ($savedDetailCount === 0) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Tidak ada data baru untuk shift ' . $request->shift_pengambilan . '. Shift ini sudah lengkap.',
                ], 422);
            }
            
            if (is_null($fdl)) {
                $data = new DataLapanganLingkunganHidup();
                if ($request->categori != '') $data->kategori_3                 = $request->categori;
                $data->no_sampel                                                = strtoupper(trim($request->no_sample));
                $data->permission                                               = $request->permission;
                $data->created_by                                               = $this->karyawan;
                $data->created_at                                               = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
            }else{
                $data = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $data->is_rejected = 0;
                $data->save();
            }

            

            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

            if($orderDetail->tanggal_terima == null){
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                $orderDetail->save();
            }

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

            $this->resultx = "Data Sampling FDL Lingkungan Hidup Dengan No Sample $no_sampel berhasil hapus oleh $this->karyawan";

            $header->delete();

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Lingkungan Hidup Udara pada nomor sampel $no_sampel")->save();

            FdlOrderDetailService::nullTanggalTerimaByNoSampel($no_sampel);


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

            FdlOrderDetailService::finalizeLingkunganHidupPartialDelete($request->no_sampel);

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

            FdlOrderDetailService::finalizeLingkunganHidupPartialDelete($request->no_sampel);

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
        }

        FdlOrderDetailService::finalizeLingkunganHidupPartialDelete($noSampel);

        return response()->json([
            'message' => $message,
            'kategori' => 2
        ], 201);
    }

    /**
     * Handle individual parameter deletion
     */
    private function handleIndividualParameterDeletion($request)
    {
        $detail = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
        $detailToDelete = DetailLingkunganHidup::where('id', $request->id)->first();

        $header = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
        if ($header) {
            $header->is_rejected = 0;
            $header->save();
        }

        $nama = $this->karyawan;
        $message = "Fdl LH parameter {$detail->first()->parameter} di no sample {$detail->first()->no_sampel} berhasil dihapus oleh {$nama}!";
        $kategori = $detail->count() > 1 ? 1 : 2;

        $detailToDelete->delete();
        FdlOrderDetailService::finalizeLingkunganHidupPartialDelete($request->no_sampel);

        return response()->json([
            'message' => $message,
            'kategori' => $kategori
        ], 201);
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