<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ParameterTotal;
use App\Models\Parameter;
use App\Models\MasterKaryawan;
use App\Models\MasterKategori;
use App\Models\TemplateAnalyst;
use App\Models\AnalisInput;
use App\Models\AnalisParameter;
use App\Models\TemplateStp;
use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use App\Services\AnalystFormula;
use App\Services\AutomatedFormula;
use App\Models\AnalystFormula as Formula;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use DB;

class InputParameterTotalController extends Controller
{
    public function index(Request $request)
    {
        $data = ParameterTotal::with('parameter')->where('is_active', 1)->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->editColumn('id_child', function ($data) {
                $id_child = json_decode($data->id_child, true);
                return $id_child;
            })
            ->filterColumn('parameter_name', function ($query, $keyword) {
                $query->where('parameter_name', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function getSampel(Request $request)
	{
		try {
			if (!isset($request->category) || $request->category == null || !isset($request->tgl) || $request->tgl == null) {
				return response()->json([
					'message' => 'Parameter tidak lengkap (kategori, tanggal) wajib diisi',
					'code' => 400
				],400);
			}
            
			$stp = TemplateStp::where('name', 'PARAMETER TOTAL')->where('category_id', $request->category)->first();
            if ($stp == null) {
				return response()->json([
					'message' => 'Data template tidak ditemukan'
				], 401);
			}

			$join = OrderDetail::with('TrackingSatu')
				->whereHas('TrackingSatu', function ($q) use ($request) {
					$q->whereDate('ftc_laboratory', $request->tgl);
				})
				->where('kategori_2', $request->category)
				->where('is_active', true)
				->orderBy('no_sampel', 'asc')
                ->get();
			// dd($join);
			if ($join->isEmpty()) {
				return response()->json([
					'message' => 'Data tidak ditemukan',
				], 404);
			}

			$select = json_decode($stp->param);

			$data = [];
			$inter = [];
			$ftc = [];

			foreach ($join as $key => $val) {
				$param = !is_null(json_decode($val->parameter)) ? array_map(function ($item) {
					return explode(';', $item)[1];
				}, json_decode($val->parameter, true)) : [];

				$diff = array_diff($select, $param);

				$row = array_fill_keys($diff, '-');
				foreach (array_diff($select, $diff) as $p) {
					$row[$p] = $val->no_sampel;
				}

				// dd($val);
				if ($stp->sample->nama_kategori == 'Air') {
					// dd($val->TrackingSatu->ftc_verifier);
					$ftc[$key] = (object)[
						'no_sample' => $val->no_sampel,
						'tanggal' => $val->TrackingSatu == null ? '-' : $val->TrackingSatu->ftc_verifier
					];
				}

				ksort($row);
				$data[$key] = $row;
				$inter[$key] = array_fill_keys($diff, '-');
			}

			// dd($join);
			$tes = [];
			$tes0 = [];

			foreach ($select as $key => $param) {
				$samples = array_values(array_diff(array_column($data, $param), ['-']));
				sort($samples);
				$tes[$key] = $samples;

				$inter_samples = array_values(array_diff(array_column($inter, $param), ['-']));
				sort($inter_samples);
				$tes0[$key] = $inter_samples;
			}

			$tes1 = $tes0;
			$approve = $tes0;

			if ($stp->name == 'PARAMETER TOTAL' && $stp->category_id == 1) {
				$parameterData = Colorimetri::with('TrackingSatu')
					->whereHas('TrackingSatu', function ($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
					->whereIn('parameter', $select)
					->where('is_active', true)
                    ->where('is_total', false)
					->orderBy('parameter')
					->orderBy('no_sampel', 'asc')
					->get()->groupBy('parameter');
				// if($stp->sample->nama_kategori == 'Air') {
				// 	$parameterData = $parameterData->with('TrackingSatu');
				// }
				// $parameterData = $parameterData->get()->groupBy('parameter');

				// dd($parameterData);
				foreach ($parameterData as $param => $samples) {
					$k = array_search($param, $select);

					// if($stp->sample->nama_kategori == 'Air') {
					// 	$ftc[$k] = $samples->map(function($item) {
					// 		return (object)[
					// 			'no_sample' => $item->no_sampel,
					// 			'tanggal' => $item->TrackingSatu->ftc_verifier == null ? '-' : $item->TrackingSatu->ftc_verifier
					// 		];
					// 	});
					// }

					$tes1[$k] = $samples->map(function ($item) {
						return (object)[
							'no_sample' => $item->no_sampel,
							'note' => $item->note
						];
					})->toArray();

					$approved = $samples->where('is_approved', 1)->pluck('no_sampel')->toArray();
					sort($approved);

					if (!empty($approved)) {
						$unapproved = array_diff($tes[$k], $approved);
						// sort($unapproved);
						$approve[$k] = array_replace($tes[$k], array_fill_keys(array_keys($unapproved), '-'));
					} else {
						$approve[$k] = [];
					}
				}
			} 

			return response()->json([
				'status' => 0,
				'columns' => $select,
				'data' => $tes,
				'nilai' => $tes1,
				'approve' => $approve,
				'ftc' => $ftc,
                'id_stp' => $stp->id
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			], 500);
		}
	}

    public function storeSampel(Request $request){
        DB::beginTransaction();
        try {
            $stp = TemplateStp::with('sample')->where('param', 'like', '%'.$request->parameter_child.'%')->where('category_id', $request->id_kategori)->first();
            if($stp == null){
                return response()->json([
                    'message' => 'Data template tidak ditemukan'
                ], 404);
            }

            $parameter = Parameter::where('nama_lab', $request->parameter_child)->where('id_kategori', $request->id_kategori)->where('is_active', 1)->first();
            if($parameter == null){
                return response()->json([
                    'message' => 'Data parameter tidak ditemukan'
                ], 404);
            }

            $cekOrder = OrderDetail::where('no_sampel', $request->no_sample)
                ->where('is_active', true)
                ->first();

            if (!$cekOrder) {
                return response()->json([
                    'message' => 'Order detail tidak ditemukan',
                ],400);
            }

            $tgl_terima = $cekOrder->tanggal_terima;

            $m_nabati = ['OG','M.Mineral'];
            if(in_array($request->parameter_child, $m_nabati)){
                $stp = TemplateStp::find($request->id_stp_child);                
            }
            // dd($stp);

            if ( ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER')
                &&
                $stp->sample->nama_kategori == 'Air'
            )
            {
                $check = Colorimetri::where('no_sampel', $request->no_sample)
                    ->where('parameter', $request->parameter_child)
                    ->where('template_stp', $stp->id)
                    ->where('is_active', true)
                    ->where('is_total', true)
                    ->first();

                if ($check) {
                    return response()->json([
                        'message' => 'Parameter'. $request->parameter_child .' sampel sudah ada pada no sampel' . $request->no_sample,
                    ],400);
                }

                $functionObj = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first();
                if(!$functionObj){
                    return response()->json([
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter_child . '',
                        'parameter' => $parameter
                    ], 404);
                }
                $function = $functionObj->function;
                // dd($data_parameter);
                $data_parsing = $request->all();

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', (object)$data_parsing)
                    ->where('id_parameter', $parameter->id)
                    ->process();

                if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return response()->json([
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter_child . '',
                        'parameter' => $parameter
                    ], 404);
                }

                $data 						= new Colorimetri;
				$data->no_sampel 			= $request->no_sample;
				$data->parameter 			= $request->parameter_child;
				$data->template_stp 		= $stp->id;
				$data->jenis_pengujian 		= $request->jenis_pengujian;
				$data->hp 					= $request->hp;
                $data->fp 				    = $request->fp; //faktor pengenceran
				$data->note 				= $request->note;
				$data->tanggal_terima 		= $tgl_terima;
				$data->created_by 			= $this->karyawan;
				$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
                $data->is_total             = 1;
				// dd($data);
				$data->save();

				$data_kalkulasi['id_colorimetri'] = $data->id;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
				if (isset($data_kalkulasi['hasil_mpn'])) unset($data_kalkulasi['hasil_mpn']);
				$kalkulasi1 = WsValueAir::create($data_kalkulasi);
            } else if ($stp->name == 'TITRIMETRI' && $stp->sample->nama_kategori == 'Air'){
                $check = Titrimetri::where('no_sampel', $request->no_sample)
                    ->where('parameter', $request->parameter_child)
                    ->where('template_stp', $stp->id)
                    ->where('is_active', true)
                    ->where('is_total', true)
                    ->first();

                if ($check) {
                    return response()->json([
                        'message' => 'Parameter'. $request->parameter_child .' sampel sudah ada pada no sampel' . $request->no_sample,
                    ],400);
                }

                $functionObj = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first();
                if(!$functionObj){
                    return response()->json([
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter_child . '',
                        'parameter' => $parameter
                    ], 404);
                }
                $function = $functionObj->function;
                // dd($data_parameter);
                $data_parsing = $request->all();

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', (object)$data_parsing)
                    ->where('id_parameter', $parameter->id)
                    ->process();

                if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return response()->json([
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter_child . '',
                        'parameter' => $parameter
                    ], 404);
                }

                $data = new Titrimetri;
                $data->no_sampel = $request->no_sample;
                $data->parameter = $request->parameter_child;
                $data->template_stp = $stp->id;
                $data->jenis_pengujian = $request->jenis_pengujian;
                $data->konsentrasi_titan = $request->kt;   //konsentrasi titran

                // Penanganan parameter khusus
                if ($request->has('volume_titrasi_baru')) {
                    $data->vts = $request->volume_titrasi_baru;
                } elseif ($request->has('do_sampel_5_hari_baru')) {
                    $data->do_sampel5 = $request->do_sampel_5_hari_baru;
                    $data->do_sampel0 = $request->do_sampel_0_hari_baru;
                    $data->do_blanko5 = $request->do_blanko_5_hari_baru;
                    $data->do_blanko0 = $request->do_blanko_0_hari_baru;
                    $data->vmb = $request->volume_mikroba_blanko_baru;
                    $data->vms = $request->volume_mikroba_sampel_baru;
                    $data->fp = $request->faktor_pengenceran_baru;
                } else {
                    $data->vts = $request->vts; // volume titrasi
                }

                // Parameter umum
                $data->fp               = $request->fp; // faktor pengenceran
                $data->vtb              = $request->vtb;  //volume titrasi blanko
                $data->vs               = $request->vs;  //volume sample
                $data->note             = $request->note;
                $data->tanggal_terima   = $tgl_terima;
                $data->created_by       = $this->karyawan;
                $data->created_at       = Carbon::now()->format('Y-m-d H:i:s');
                $data->is_total         = 1;

                $data->save();

                // Simpan hasil kalkulasi
                $data_kalkulasi['id_titrimetri'] = $data->id;
                $data_kalkulasi['no_sampel'] = $request->no_sample;
                WsValueAir::create($data_kalkulasi);
            } else if($stp->name == 'GRAVIMETRI' && $stp->sample->nama_kategori == 'Air'){
                $check = Gravimetri::where('no_sampel', $request->no_sample)
                    ->where('parameter', $request->parameter_child)
                    ->where('template_stp', $stp->id)
                    ->where('is_active', true)
                    ->where('is_total', true)
                    ->first();

                if ($check) {
                    return response()->json([
                        'message' => 'Parameter'. $request->parameter_child .' sampel sudah ada pada no sampel' . $request->no_sample,
                    ],400);
                }

                $functionObj = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first();
                if(!$functionObj){
                    return response()->json([
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter_child . '',
                        'parameter' => $parameter
                    ], 404);
                }
                $function = $functionObj->function;
                // dd($data_parameter);
                $data_parsing = $request->all();

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', (object)$data_parsing)
                    ->where('id_parameter', $parameter->id)
                    ->process();

                if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return response()->json([
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter_child . '',
                        'parameter' => $parameter
                    ], 404);
                }

                $data = new Gravimetri;
				$data->no_sampel 			= $request->no_sample;
				$data->parameter 			= $request->parameter_child;
				$data->template_stp 		= $stp->id;
				$data->jenis_pengujian 		= $request->jenis_pengujian;
				$data->bk_1 				= $request->bk_1;
				$data->bk_2 				= $request->bk_2;
				$data->bki_1 				= $request->bki1;
				$data->bki_2 				= $request->bki2;
				$data->vs 					= $request->vs;
				if ($request->has('fp')) {
					$data->fp 					= $request->fp;
				}
				$data->note 				= $request->note;
				$data->tanggal_terima 		= $tgl_terima;
				$data->created_by 			= $this->karyawan;
				$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
                $data->is_total             = 1;
				// dd($data,'sample');
				$data->save();

				$data_kalkulasi['id_gravimetri'] = $data->id;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
                // dd('berhasil');
				WsValueAir::create($data_kalkulasi);
            }

            $parameterList = json_decode($cekOrder->parameter);
            $filteredParameter = array_map(function ($parameter) {
                return explode(';', $parameter)[1];
            }, $parameterList);

            $no2_no3 = ['NO2-N', 'NO2-N (NA)', 'NO3-N', 'NO3-N (APHA-E-23)', 'NO3-N (IKM-SP)', 'NO3-N (SNI-7-03)'];
            if (in_array($request->parameter_child, $no2_no3) && in_array('NO2-N+NO3-N', $filteredParameter)) {
                $hitung_otomatis = AutomatedFormula::where('parameter', $request->parameter)
                    ->where('required_parameter', $no2_no3)
                    ->where('no_sampel', $request->no_sample)
                    ->where('class_calculate', 'NO3_NO2')
                    ->where('tanggal_terima', $tgl_terima)
                    ->calculate();
            }

            $n_total = [
                'NO2-N',
                'NO2-N (NA)',
                'NO3-N',
                'NO3-N (APHA-E-23)',
                'NO3-N (IKM-SP)',
                'NO3-N (SNI-7-03)',
                'NH3-N',
                'NH3-N (3-03-NA)',
                'NH3-N (3-03)',
                'NH3-N (30-25-NA)',
                'NH3-N (30-25)',
                'N-Organik',
                'N-Organik (NA)'
            ];
            if ((in_array($request->parameter_child, $n_total) || $request->parameter == 'TKN') && (in_array('N-Total', $filteredParameter)) || in_array('N-Total (NA)', $filteredParameter)) {
                $hitung_otomatis = AutomatedFormula::where('parameter', $request->parameter)
                    ->where('required_parameter', $n_total)
                    ->where('no_sampel', $request->no_sample)
                    ->where('class_calculate', 'N_Total')
                    ->where('tanggal_terima', $tgl_terima)
                    ->calculate();
            }

            $tkn_parameter = [
                'NO2-N', 'NO2-N (NA)',
                'NO3-N', 'NO3-N (APHA-E-23)', 'NO3-N (IKM-SP)', 'NO3-N (SNI-7-03)',
                'NH3-N', 'NH3-N (3-03-NA)', 'NH3-N (3-03)', 'NH3-N (30-25-NA)', 'NH3-N (30-25)',
                'N-Organik', 'N-Organik (NA)'
            ];
            if((in_array($request->parameter, $tkn_parameter) || $request->parameter === 'N-Total') && in_array('TKN', $filteredParameter)){
                $hitung_otomatis = AutomatedFormula::where('parameter', 'TKN')
                    ->where('required_parameter', $tkn_parameter)
                    ->where('no_sampel', $request->no_sample)
                    ->where('class_calculate', 'TKN')
                    ->where('tanggal_terima', $tgl_terima)
                    ->calculate();
            }

            $m_nabati = ['OG', 'M.Mineral'];
            if (in_array($request->parameter_child, $m_nabati) && in_array('M.Nabati', $filteredParameter)) {
                $hitung_otomatis = AutomatedFormula::where('parameter', $request->parameter)
                    ->where('required_parameter', $m_nabati)
                    ->where('no_sampel', $request->no_sample)
                    ->where('class_calculate', 'M_Nabati')
                    ->where('tanggal_terima', $tgl_terima)
                    ->calculate();
            }
            // dd($data, $kalkulasi1);
            // dd($data);
            DB::commit();
            return response()->json([
                'message' => 'Data berhasil disimpan',
                'par' => $request->parameter_child
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan data: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getTemplate(Request $request)
    {
        $data = TemplateStp::where('is_active', true)
            ->where('name', 'like', '%' . $request->search . '%')
            ->where('category_id', $request->id_kategori)
            ->select('id','name')
            ->get();
        return response()->json($data);
    }

    public function show(Request $request)
    {
        $data = ParameterTotal::with('parameter')->where('id', $request->id)->first();
        $children = array_map(function ($item) {
            $param = Parameter::select('id', 'nama_lab')->find($item);
            return $param;
        }, json_decode($data->id_child, true) ?: []);

        $data->children = $children;

        $form_children = [];

        foreach ($children as $item) {
            $parameter = AnalisParameter::where('parameter_id', $item->id)->first();
            $body = AnalisInput::where('id', $parameter->id_form)->first();
            
            // Ambil nama parameter sebagai key
            $namaParameter = $item->nama_lab;

            // Decode body form menjadi array
            $formBody = json_decode($body->body, true);

            // Masukkan ke array asosiatif
            $form_children[$namaParameter] = $formBody;
        }

        $data->form_children = $form_children;
        
        return response()->json([
            'data' => $data
        ],200);
    }

    public function getInputForm(Request $request)
    {  
        try{
            $data = ParameterTotal::with('parameter')->where('parameter_name', $request->name)->first();
            $ids = json_decode($data->id_child);
            $analisParams = AnalisParameter::with('input')->where('is_active', 1)->whereIn('parameter_id', $ids)
                ->get()->map(function ($item) use ($request) {
                    $stp = TemplateStp::where('id', $item->id_stp)->first();
                    if ($stp == null){
                        $header = null;
                    }

                    if($stp->name == 'GRAVIMETRI' && $stp->category_id == 1){
                        $header = Gravimetri::with('ws_value')
                            ->where('no_sampel', $request->no_sample)
                            ->where('parameter', $item->parameter_name)
                            ->where('template_stp', $item->id_stp)
                            ->where('is_active', 1)
                            ->where('is_total', 1)
                            ->first();
                    }else if($stp->name == 'TITRIMETRI' && $stp->category_id == 1){
                        $header = Titrimetri::with('ws_value')
                            ->where('no_sampel', $request->no_sample)
                            ->where('parameter', $item->parameter_name)
                            ->where('template_stp', $item->id_stp)
                            ->where('is_active', 1)
                            ->where('is_total', 1)
                            ->first();
                    }else if (( ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER')
                    &&
                    $stp->category_id == 1
                    )) {
                        $header = Colorimetri::with('ws_value')
                            ->where('no_sampel', $request->no_sample)
                            ->where('parameter', $item->parameter_name)
                            ->where('template_stp', $item->id_stp)
                            ->where('is_active', 1)
                            ->where('is_total', 1)
                            ->first();
                    }else{
                        $header = null;
                    }
                    $item->input_value = $header;
                    return $item;
                });
            $data->analis_params = $analisParams;
            return response()->json([
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ],500);
        }
    }

    public function getCategories(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();

        return response()->json([
            'data' => $data
        ],200);
    }

    public function getParameters(Request $request)
    {
        $data = Parameter::where('id_kategori', $request->id_kategori)->where('is_active', 1)->orderBy('id', 'DESC');
        if(isset($request->search)){
            $data->where('nama_lab', 'like', '%'.$request->search.'%');
        }
        if(isset($request->exclude_id) && !empty($request->exclude_id)){
            $data->whereNotIn('id', explode(';', $request->exclude_id));
        }
        $data = $data->get();

        return response()->json([
            'data' => $data
        ],200);
    }

    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            $parameter_id = explode(';', $request->parameter)[0];
            $parameter_name = explode(';', $request->parameter)[1];
            $children = array_map(function($item) { return (int) explode(';', $item)[0]; }, $request->children);
            if ($request->id == null || $request->id == '') {
                $parameterTotal = new ParameterTotal();
                $parameterTotal->parameter_id = $parameter_id;
                $parameterTotal->parameter_name = $parameter_name;
                $parameterTotal->id_stp = $request->id_stp;
                $parameterTotal->id_child = json_encode($children);
                $parameterTotal->created_at = Carbon::now();
                $parameterTotal->created_by = $this->karyawan;
                $parameterTotal->save();
            }else {
                $parameterTotal = ParameterTotal::find($request->id);
                $parameterTotal->parameter_id = $parameter_id;
                $parameterTotal->parameter_name = $parameter_name;
                $parameterTotal->id_stp = $request->id_stp;
                $parameterTotal->id_child = json_encode($children);
                $parameterTotal->updated_at = Carbon::now();
                $parameterTotal->updated_by = $this->karyawan;
                $parameterTotal->save();
            }
            
            foreach ($request->children as $key => $value) {
                $param = explode(';', $value);
                $stp = TemplateStp::where('param', 'LIKE', "%$param[1]%")->where('category_id', $request->category_id)->where('is_active', 1)->first();
                $id_stp = $stp->id ?? null;
                // dd($stp);
                $analisParameter = AnalisParameter::where('parameter_id', $param[0])->where('id_stp', $id_stp)->first();
                if ($analisParameter) {
                    $analisParameter->updated_at = Carbon::now();
                    $analisParameter->updated_by = $this->karyawan;
                }else{
                    $analisParameter = new AnalisParameter();
                    $analisParameter->created_at = Carbon::now();
                    $analisParameter->created_by = $this->karyawan;
                }

                $analisInput = AnalisInput::where('id', $analisParameter->id_form)->first();
                if (isset($analisInput->id)) {
                    // dd($analisInput);
                    $analisInput->updated_at = Carbon::now();
                    $analisInput->updated_by = $this->karyawan;
                }else{
                    $analisInput = new AnalisInput();
                    $analisInput->created_at = Carbon::now();
                    $analisInput->created_by = $this->karyawan;
                }

                $analisInput->body = json_encode($request->form_children[$param[1]]);
                $analisInput->save();
                // dd($analisParameter);
                $analisParameter->parameter_id = $param[0];
                $analisParameter->parameter_name = $param[1];
                $analisParameter->id_stp = $id_stp;
                $analisParameter->id_form = $analisInput->id;
                $analisParameter->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Data hasbeen Save.!'
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function getParameter(Request $request)
    {
        $idKategori = $request->input('id_kategori');

        $data = Parameter::where('is_active', 1)
            ->where('id_kategori', $idKategori)
            ->select('id', 'nama_lab')
            ->get();

        return response()->json([
            'message' => 'Data has been shown',
            'data' => $data,
        ], 200); // pakai 200 OK untuk GET-like response
    }

    public function delete($id) {
        DB::beginTransaction();
        try {
            $data = ParameterTotal::find($id);

            if(!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $children = json_decode($data->id_child);

            $analisParameter = AnalisParameter::whereIn('parameter_id', $children)->get();
            foreach ($analisParameter as $item) {
                $analisInput = AnalisInput::find($item->id_form);
                $analisInput->is_active = false;
                $analisInput->deleted_at = Carbon::now();
                $analisInput->deleted_by = $this->karyawan;

                $item->is_active = false;
                $item->deleted_at = Carbon::now();
                $item->deleted_by = $this->karyawan;

                $analisInput->save();
                $item->save();
            }

            $data->is_active = false;
            $data->deleted_at = Carbon::now();
            $data->deleted_by = $this->karyawan;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }
}