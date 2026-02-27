<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\TemplateAnalyst;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\CategorySample;
use App\Models\Parameter;
use App\Models\User;
use App\Models\Usertoken;
use App\Models\Requestlog;
use App\Models\OrderDetail;
use App\Models\Titrimetri;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\DebuPersonalHeader;
use App\Models\WsValueLingkungan;
use App\Models\EmisiCerobongHeader;
use App\Models\DustFallHeader;
use App\Models\MicrobioHeader;
use App\Models\IsokinetikHeader;
use App\Models\SwabTestHeader;
use App\Models\Subkontrak;
// use App\Models\WsValueEmisiCerobong;
use App\Models\WsValueAir;
use App\Models\WsValueMicrobio;
use App\Models\WsValueSwab;
use App\Models\WsValueUdara;
use App\Models\WsValueEmisiCerobong;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganSwab;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailSenyawaVolatile;
use App\Models\DetailMicrobiologi;
use App\Models\AnalisParameter;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use App\Services\FunctionValue;
use App\Services\AnalystRender;
use App\Services\AnalystFormula;
use App\Services\AutomatedFormula;
use App\Models\AnalystFormula as Formula;
use App\Models\KuotaAnalisaParameter;
use Illuminate\Support\Facades\Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Repository;

class InputParameterController extends Controller
{
    public function index(Request $request){
		try {
			$stp = TemplateStp::with('sample')->where('id', $request->id_stp)->select('name','category_id')->first();
			if(!isset($request->category) || $request->category == null || !isset($request->tgl) || $request->tgl == null || !isset($request->id_stp) || $request->id_stp == null) {
				return response()->json([
					'message' => 'Parameter tidak lengkap (kategori, tanggal, parameter) wajib diisi',
					'code' => 400
				],400);
			}

			$join = OrderDetail::with('TrackingSatu')
                ->whereHas('TrackingSatu', function ($q) use ($request) {
                    $q->where('ftc_laboratory', 'LIKE', "%$request->tgl%")->orderBy('ftc_laboratory', 'asc');
                })
                ->where('kategori_2', $request->category)
                ->where('is_active', true)
                ->orderByRaw("JSON_LENGTH(parameter) = 1 DESC")
                ->orderBy('no_sampel', 'asc');
			$join = $join->get();

            $quota = KuotaAnalisaParameter::select('parameter_name', 'quota', 'tanggal_berlaku')
                ->where('kategori', $request->category)
                ->where('is_active', true)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->parameter_name => (object)[
                            'kuota' => $item->quota,
                            'tanggal_berlaku' => $item->tanggal_berlaku,
                        ],
                    ];
                });

			// dd($join);
			if($join->isEmpty()) {
				return response()->json([
					'message' => 'Data tidak ditemukan',
				], 404);
			}

			$par = TemplateStp::where('id', $request->id_stp)->first();
			$select = json_decode($par->param);

			$data = [];
			$inter = [];
			$ftc = [];

            $quota_count = collect();
            $repo_quota = json_decode(
                Repository::dir('filtered_quota_sampel')->key($request->tgl)->get(),
                true
            );

            // Pastikan struktur data benar sebelum diproses
            if (is_array($repo_quota) && isset($repo_quota[$request->id_stp])) {
                foreach ($repo_quota[$request->id_stp] as $parameter => $samples) {
                    // Simpan dengan struktur: [id_stp => [parameter => collection(samples)]]
                    if (!$quota_count->has($request->id_stp)) {
                        $quota_count->put($request->id_stp, collect());
                    }
                    $quota_count[$request->id_stp]->put($parameter, collect($samples));
                }
            }

            $t_coli_rest = [];
            $category_prioritized = ['5-Air Laut','54-Air Sungai','72-Air Tanah'];

            // Konversi $join ke array untuk memudahkan pencarian
            $join_array = $join->toArray(); // Jika $join adalah Collection

            // Kumpulkan sampel berdasarkan kategori prioritas terlebih dahulu
            $priority_samples = [];
            $backup_samples = [];
			$pm24_samples_excluded = [];

            foreach ($join as $key => $val) {
				// Ambil parameter
                $param = !is_null(json_decode($val->parameter))
					? array_map(function ($item) {
						return explode(';', $item)[1];
					}, json_decode($val->parameter, true))
					: [];

				$isOrderContainerPM24 = in_array('PM 10 (24 Jam)', $param) || in_array('PM10 (24 Jam)', $param) || in_array('PM2.5 (24 Jam)', $param) || in_array('PM 2.5 (24 Jam)', $param);
				
				if($stp->name == 'GRAVIMETRI' && $stp->sample->nama_kategori == 'Udara' && $isOrderContainerPM24 && $val->kategori_3 == '27-Udara Lingkungan Kerja'){
					$pm24_samples_excluded[] = $val->no_sampel;
				}
                // Cek apakah ada parameter yang mengandung 'BOD'
                $isBodExist = collect($param)->contains(function ($item) {
                    return Str::contains($item, 'BOD');
                });
                // Cek apakah ada parameter yang mengandung 'NH3'
                $isNh3Exist = collect($param)->contains(function ($item) {
                    return Str::contains($item, 'NH3');
                });
                // Cek apakah ada parameter yang mengandung 'TSS'
                $isTSSExist = collect($param)->contains(function ($item) {
                    return Str::contains($item, 'TSS');
                });

                // Gunakan hasil pengecekan
                if ((!$isBodExist && !$isNh3Exist && !$isTSSExist) || in_array($val->kategori_3, $category_prioritized)) {
                    $priority_samples[$key] = $val;
                } else {
                    $backup_samples[$key] = $val;
                }
            }
            // Gabungkan: prioritas dulu, kemudian backup
            $sorted_join = $priority_samples + $backup_samples;

            foreach($sorted_join as $key => $val) {
                $param = !is_null(json_decode($val->parameter)) ? array_map(function($item) {
                    return explode(';', $item)[1];
                }, json_decode($val->parameter, true)) : [];

                $diff = array_diff($select, $param);
                $row = array_fill_keys($diff, '-');

                foreach (array_diff($select, $diff) as $param_key => $p) {
                    $quota_exceeded = false;
                    $already_counted = false;
                    $index_counted = null;

                    $tglBerlaku = isset($quota[$p]) ? Carbon::parse($quota[$p]->tanggal_berlaku) : Carbon::parse($request->tgl)->addDay(7);
                    $tglRequest = Carbon::parse($request->tgl);

                    // if(isset($quota[$p])) {
                    //     dump($tglBerlaku, $tglRequest, $tglRequest >= $tglBerlaku, $p);
                    // }

                    // --- PERUBAHAN PENTING: Hanya proses quota jika parameter ada dalam $quota dan tanggal request lebih besar atau sama dengan tanggal berlaku
                    if (!in_array($stp->name, ['SUBKONTRAK','OTHER','Other']) && isset($quota[$p]) && $tglRequest >= $tglBerlaku) {
                        // Pastikan struktur quota_count ada
                        if (!$quota_count->has($request->id_stp)) {
                            $quota_count->put($request->id_stp, collect());
                        }
                        if (!$quota_count[$request->id_stp]->has($p)) {
                            $quota_count[$request->id_stp]->put($p, collect());
                        }

                        // --- 1️⃣ Cek apakah sample sudah pernah terhitung
                        if ($quota_count[$request->id_stp][$p]->contains($val->no_sampel)) {
                            $index_counted = $quota_count[$request->id_stp][$p]->search($val->no_sampel);
                            $already_counted = true;
                        }

                        // --- 2️⃣ Jika belum counted, cek apakah quota sudah penuh
                        if ($quota_count[$request->id_stp][$p]->count() >= $quota[$p]->kuota) {
                            $quota_exceeded = true;
                        }

                        // --- 3️⃣ Handle Total Coliform khusus untuk kategori non-prioritas
                        if(in_array($p, ['Total Coliform','Total Coliform (MPN)' ,'Total Coliform (NA)']) && !in_array($val->kategori_3, $category_prioritized)){
                            $t_coli_rest[$p][] = $val->no_sampel;
                            if ($quota_count[$request->id_stp][$p]->contains($val->no_sampel)) {
                                $quota_count[$request->id_stp][$p] = $quota_count[$request->id_stp][$p]->reject(fn($item) => $item === $val->no_sampel)->values();
                            }
                            continue;
                        }

                        // --- 4️⃣ Jika quota penuh, potong kelebihan dari belakang
                        if ($quota_exceeded || $already_counted) {
                            // --- Jika sudah counted, ambil dari index yang sama
                            if($already_counted){
                                $row[$p] = $quota_count[$request->id_stp][$p][$index_counted] ?? '-';
                            }
                            $diff_count = $quota_count[$request->id_stp][$p]->count() - $quota[$p]->kuota;
                            if ($diff_count > 0) {
                                $quota_count[$request->id_stp][$p] = $quota_count[$request->id_stp][$p]
                                    ->slice(0, $quota[$p]->kuota)
                                    ->values();
                            }
                            continue;
                        }

                        // --- 5️⃣ Jika belum penuh, tambahkan ke quota_count
                        $currentCount = $quota_count[$request->id_stp][$p]->count();
                        $maxQuota = $quota[$p]->kuota;

                        // Hanya tambahkan jika belum mencapai batas quota
                        if ($currentCount < $maxQuota) {
                            $quota_count[$request->id_stp][$p]->push($val->no_sampel);
                            $row[$p] = $val->no_sampel;
                        } else {
                            // Jika quota penuh, simpan sebagai cadangan untuk parameter ini
                            $t_coli_rest[$p][] = $val->no_sampel;
                        }

                    } else {
                        // --- PARAMETER TANPA QUOTA: Langsung tampilkan tanpa pembatasan
                        $row[$p] = $val->no_sampel;

                        // Untuk SUBKONTRAK juga langsung tampilkan
                        if (in_array($stp->name, ['SUBKONTRAK','OTHER','Other'])) {
                            $row[$p] = $val->no_sampel;
                        }
                    }
                }

                if($stp->sample->nama_kategori == 'Air') {
                    $ftc[] = (object)[
                        'no_sample' => $val->no_sampel,
                        'tanggal' => $val->TrackingSatu == null ? '-' : $val->TrackingSatu->ftc_verifier
                    ];
                }

                ksort($row);
                $data[$key] = $row;
                $inter[$key] = array_fill_keys($diff, '-');
            }

            // dd($data);

             // Handle cadangan jika quota belum terpenuhi (hanya untuk parameter dengan quota)
            // dd($quota_count->has($request->id_stp));
            if ($quota_count->has($request->id_stp)) {
                foreach ($quota_count[$request->id_stp] as $parameter => $samples) {
                    // Hanya proses parameter yang ada dalam $quota
                    $tglBerlaku = isset($quota[$parameter]) ? Carbon::parse($quota[$parameter]->tanggal_berlaku) : Carbon::parse($request->tgl)->addDay(7);
                    $tglRequest = Carbon::parse($request->tgl);

                    if (isset($quota[$parameter]->kuota) && $samples->count() < $quota[$parameter]->kuota && $tglRequest >= $tglBerlaku) {
                        $remaining_quota = $quota[$parameter]->kuota - $samples->count();

                        // Ambil dari cadangan untuk parameter ini
                        if (isset($t_coli_rest[$parameter]) && count($t_coli_rest[$parameter]) > 0) {
                            $backup_to_add = array_slice($t_coli_rest[$parameter], 0, $remaining_quota);

                            foreach ($backup_to_add as $backup_sample) {
                                if ($samples->count() < $quota[$parameter]->kuota) {
                                    $quota_count[$request->id_stp][$parameter]->push($backup_sample);

                                    // Update data untuk menambahkan sampel cadangan
                                    foreach ($data as $key => $row) {
                                        $join_no_samples = array_column($join_array, 'no_sampel');
                                        if (isset($row[$parameter]) && $row[$parameter] === '-' && in_array($backup_sample, $join_no_samples)) {
                                            $data[$key][$parameter] = $backup_sample;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $tes = [];
            $tes0 = [];
            $key_param_rest = [];

            foreach($select as $key => $param) {
                $samples = array_values(array_diff(array_column($data, $param), ['-']));
                sort($samples);
                $tes[$key] = $samples;

                if(isset($quota[$param]) && in_array($param, ['Total Coliform','Total Coliform (MPN)' ,'Total Coliform (NA)']) && count($samples) > 0){
                    $key_param_rest[$param] =  $key;
                }

                $inter_samples = array_values(array_diff(array_column($inter, $param), ['-']));
                sort($inter_samples);
                $tes0[$key] = $inter_samples;
            }

            $tes1 = $tes0;
            $approve = $tes0;

            $stp = TemplateStp::with('sample')->where('id', $request->id_stp)->select('name','category_id')->first();
			// dd($stp);

            if($stp == null) {
                return response()->json([
                    'message' => 'Data template tidak ditemukan'
                ],401);
            }else if($stp->sample == null) {
                return response()->json([
                    'message' => 'Data template sample tidak ditemukan'
                ],401);
            }

            if(( $stp->name == 'TITRIMETRI' || $stp->name == 'TITRI A' || $stp->name == 'TITRI B' ) && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
				// $analystParameter = TemplateAnalyst::where('nama', $stp->name)->where('is_active', true)->first();
				// $analystParameter = Parameter::whereIn('id', json_decode($analystParameter->parameters))->select('nama_lab')->get();
				// echo(json_encode($analystParameter));
				// dd($analystParameter);
                $parameterData = Titrimetri::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active',true)
                    ->where('is_total',false)
                    ->orderBy('parameter')
                    ->orderBy('no_sampel', 'asc')
					->get()->groupBy('parameter');
                // if($stp->sample->nama_kategori == 'Air') {
                //     $parameterData = $parameterData->with('TrackingSatu');
                // }
                // $parameterData = $parameterData->get()->groupBy('parameter');
				// dd($parameterData);

                foreach($parameterData as $param => $samples) {
                    $k = array_search($param, $select);


					// if($stp->sample->nama_kategori == 'Air') {
					// 	$ftc[$k] = $samples->map(function($item) {
					// 		return (object)[
					// 			'no_sample' => $item->no_sampel,
					// 			'tanggal' => $item->TrackingSatu->ftc_verifier == null ? '-' : $item->TrackingSatu->ftc_verifier
					// 		];
					// 	});
					// }

                    $tes1[$k] = $samples->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();
                    $approved = $samples->where('is_approved', 1)->pluck('no_sampel')->toArray();
                    sort($approved);

                    if(!empty($approved)) {
						$unapproved = array_diff($tes[$k], $approved);
                        // sort($unapproved);
                        $approve[$k] = array_replace($tes[$k], array_fill_keys(array_keys($unapproved), '-'));
                    } else {
						$approve[$k] = [];
                    }
					// dump($approve[$k]);
                }
				// dd($approve);
            // }else if($stp->name == 'GRAVIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
            }else if(($stp->name == 'GRAVIMETRI A' || $stp->name == 'GRAVIMETRI B' || $stp->name == 'GRAVIMETRI') && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
                $parameterData = Gravimetri::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
					->where('is_total',false)
                    ->orderBy('parameter')
                    ->orderBy('no_sampel', 'asc')
					->get()->groupBy('parameter');
					// if($stp->sample->nama_kategori == 'Air') {
					// 	$parameterData = $parameterData->with('TrackingSatu');
					// }
					// $parameterData = $parameterData->get()->groupBy('parameter');

                foreach($parameterData as $param => $samples) {
                    $k = array_search($param, $select);

					// if($stp->sample->nama_kategori == 'Air') {
					// 	$ftc[$k] = $samples->map(function($item) {
					// 		return (object)[
					// 			'no_sample' => $item->no_sampel,
					// 			'tanggal' => $item->TrackingSatu->ftc_verifier == null ? '-' : $item->TrackingSatu->ftc_verifier
					// 		];
					// 	});
					// }

                    $tes1[$k] = $samples->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    $approved = $samples->where('is_approved', 1)->pluck('no_sampel')->toArray();
                    sort($approved);

                    if(!empty($approved)) {
                        $unapproved = array_diff($tes[$k], $approved);
                        // sort($unapproved);
                        $approve[$k] = array_replace($tes[$k], array_fill_keys(array_keys($unapproved), '-'));
                    } else {
                        $approve[$k] = [];
                    }
                }
            }else if(
                ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'Direct Reading A' || $stp->name == 'Direct Reading B' || $stp->name == 'Direct Reading C' || $stp->name == 'Direct Reading D' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'SPEKTRO A' || $stp->name == 'SPEKTRO B' || $stp->name == 'SPEKTRO C' ||  $stp->name == 'SPEKTRO D' ||  $stp->name == 'SPEKTRO E' ||  $stp->name == 'SPEKTRO F' ||  $stp->name == 'COLORIMETER' || $stp->name == 'MERCURY ANALYZER' || $stp->name == 'KIMIA PANGAN A' || $stp->name == 'Mikrobiologi Padatan')
                &&
                ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan' || $stp->sample->nama_kategori == 'Pangan')
            ) {
                $parameterData = Colorimetri::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
					->where('is_total',false)
                    ->orderBy('parameter')
                    ->orderBy('no_sampel', 'asc')
					->get()->groupBy('parameter');
					// if($stp->sample->nama_kategori == 'Air') {
					// 	$parameterData = $parameterData->with('TrackingSatu');
					// }
					// $parameterData = $parameterData->get()->groupBy('parameter');

				// dd($parameterData);
                foreach($parameterData as $param => $samples) {
                    $k = array_search($param, $select);

					// if($stp->sample->nama_kategori == 'Air') {
					// 	$ftc[$k] = $samples->map(function($item) {
					// 		return (object)[
					// 			'no_sample' => $item->no_sampel,
					// 			'tanggal' => $item->TrackingSatu->ftc_verifier == null ? '-' : $item->TrackingSatu->ftc_verifier
					// 		];
					// 	});
					// }

                    $tes1[$k] = $samples->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    $approved = $samples->where('is_approved', 1)->pluck('no_sampel')->toArray();
                    sort($approved);

                    if(!empty($approved)) {
                        $unapproved = array_diff($tes[$k], $approved);
                        // sort($unapproved);
                        $approve[$k] = array_replace($tes[$k], array_fill_keys(array_keys($unapproved), '-'));
                    } else {
                        $approve[$k] = [];
                    }
                }
            }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Udara'){
                $loop = LingkunganHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->select('parameter')
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
                    ->groupBy('parameter')
                    ->get();
				// dump($select);
                foreach($select as $k => $parameter) {
					if($parameter == 'PM 10 (24 Jam)' || $parameter == 'PM 2.5 (24 Jam)') {
						$tes[$k] = array_values(array_diff($tes[$k], $pm24_samples_excluded));
					}
                    // Get data for Linghidup
                    $linghidupData = LingkunganHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                        ->where('parameter', $parameter)
                        ->where('is_active', true)
                        ->orderBy('no_sampel', 'asc')
                        ->get();

                    $dustfallData = DustFallHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                        ->where('parameter', $parameter)
                        ->where('is_active', true)
                        ->orderBy('no_sampel', 'asc')
                        ->get();

                    // Get data for DebuPersonal
                    $debuData = DebuPersonalHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                        ->where('parameter', $parameter)
                        ->where('is_active', true)
                        ->orderBy('no_sampel', 'asc')
                        ->get();

                    // Combine data from both sources
                    $combinedData = $linghidupData
						->concat($dustfallData)
						->concat($debuData);

                    // Map sample data
                    $tes1[$k] = $combinedData->map(function($item) {
						// dd($item);
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    // Handle approved samples
                    $approvedSamples = $combinedData->where('is_approved', true)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();
					// dd($linghidupData);
                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);

                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );
                }
            }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Emisi'){
                $emisicData = EmisiCerobongHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
                    ->orderBy('no_sampel', 'asc')
                    ->get();

				// dd('masuk');

                foreach($select as $k => $parameter) {
                    $parameterData = $emisicData->where('parameter', $parameter);

					// Map sample data dan reset key
					$tes1[$k] = array_values($parameterData->map(function ($item) {
						return (object)[
							'no_sample' => $item->no_sampel,
							'note' => $item->note
						];
					})->toArray());

					// dd($tes1[$k]);

                    // Handle approved samples
                    $approvedSamples = $parameterData->where('is_approved', 1)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();

                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);


                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );

					// dd($approve[$k]);
                }
            }else if($stp->name == 'DIRECT READING' && $stp->sample->nama_kategori == 'Udara'){
                $dustfallData = DustFallHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
                    ->orderBy('no_sampel', 'asc')
                    ->get();

                foreach($select as $k => $parameter) {
                    $parameterData = $dustfallData->where('parameter', $parameter);

                    // Map sample data
                    $tes1[$k] = $parameterData->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    // Handle approved samples
                    $approvedSamples = $parameterData->where('is_approved', 1)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();

                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);


                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );
                }
            }else if($stp->name == 'MIKROBIOLOGI' && $stp->sample->nama_kategori == 'Udara'){
                $microbioData = MicrobioHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
                    ->orderBy('no_sampel', 'asc')
                    ->get();

                foreach($select as $k => $parameter) {
                    $parameterData = $microbioData->where('parameter', $parameter);

                    // Map sample data
                    $tes1[$k] = $parameterData->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    // Handle approved samples
                    $approvedSamples = $parameterData->where('is_approved', 1)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();

                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);
                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );
                }
            }else if($stp->name == 'SWAB TEST' && $stp->sample->nama_kategori == 'Udara'){
                $swab = SwabTestHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
                    ->orderBy('no_sampel', 'asc')
                    ->get();

                foreach($select as $k => $parameter) {
                    $parameterData = $swab->where('parameter', $parameter);

                    // Map sample data
                    $tes1[$k] = $parameterData->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    // Handle approved samples
                    $approvedSamples = $parameterData->where('is_approved', 1)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();

                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);

                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );
                }
            } else if(in_array($stp->name, ['Other','OTHER']) && in_array($stp->sample->nama_kategori,['Air','Udara','Emisi','Padatan'])){
				$isokinetik = Subkontrak::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
					->where('is_total',false)
                    ->orderBy('no_sampel', 'asc')
                    ->get();

                foreach($select as $k => $parameter) {
                    $parameterData = $isokinetik->where('parameter', $parameter);

                    // Map sample data
                    $tes1[$k] = array_values($parameterData->map(function ($item) {
						return (object)[
							'no_sample' => $item->no_sampel,
							'note' => $item->note
						];
					})->toArray());

                    // Handle approved samples
                    $approvedSamples = $parameterData->where('is_approve', 1)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();

                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);

                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );
                }
			}else if($stp->name == 'ISOKINETIK' && $stp->sample->nama_kategori == 'Emisi'){
                $isokinetik = IsokinetikHeader::with('TrackingSatu')
					->whereHas('TrackingSatu', function($q) use ($request) {
						$q->where('ftc_laboratory', 'LIKE', "%$request->tgl%");
					})
                    ->whereIn('parameter', $select)
                    ->where('is_active', true)
                    ->orderBy('no_sampel', 'asc')
                    ->get();

                foreach($select as $k => $parameter) {
                    $parameterData = $isokinetik->where('parameter', $parameter);

                    // Map sample data
                    $tes1[$k] = $parameterData->map(function($item) {
                        return (object)[
                            'no_sample' => $item->no_sampel,
                            'note' => $item->note
                        ];
                    })->toArray();

                    // Handle approved samples
                    $approvedSamples = $parameterData->where('is_approved', 1)
                        ->pluck('no_sampel')
                        ->sort()
                        ->toArray();

                    $unapprovedSamples = array_diff($tes[$k], $approvedSamples);

                    $approve[$k] = array_replace(
                        $tes[$k],
                        array_fill_keys(array_keys($unapprovedSamples), '-')
                    );
                }
			}
            // }else{
            //     return response()->json([
            //         'message' => 'Template pengujian tidak ditemukan'
            //     ],401);
            // }

            $filtered_sample_request = $quota_count->map(fn($item) =>
                $item instanceof \Illuminate\Support\Collection ? $item->toArray() : (array)$item
            )->toArray();

            $filtered_sample_repo = $repo_quota;

            foreach ($filtered_sample_request as $key => $val) {
                if (isset($filtered_sample_repo[$key])) {
                    // Jika key utama (misal 7 atau 8) sudah ada di repo
                    foreach ($val as $param => $samples) {
                        if (isset($filtered_sample_repo[$key][$param])) {
                            // Gabungkan nilai array tanpa duplikasi
                            $filtered_sample_repo[$key][$param] = array_values(array_unique(array_merge(
                                $filtered_sample_repo[$key][$param],
                                $samples
                            )));
                        } else {
                            // Tambahkan parameter baru (misal COD, Fenol, dll)
                            $filtered_sample_repo[$key][$param] = $samples;
                        }
                    }
                } else {
                    // Jika key utama belum ada di repo, tambahkan seluruh isinya
                    $filtered_sample_repo[$key] = $val;
                }
            }

            // Simpan ke repository sebagai JSON
            Repository::dir('filtered_quota_sampel')
                ->key($request->tgl)
                // ->save(json_encode($filtered_sample_request, JSON_PRETTY_PRINT));
                ->save(json_encode($filtered_sample_repo, JSON_PRETTY_PRINT));

            return response()->json([
                'status'=>0,
                'columns'=>$select,
                'data' => $tes,
                'nilai' => $tes1,
                'approve' => $approve,
				'ftc' => $ftc
            ], 200);
		}catch(\Exception $e) {
            return response()->json([
				'message' => 'Gagal mengambil parameter: '.$e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			],500);
		}
    }

    public function getShiftIcp(Request $request)
	{
		try {
			$data = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->get();
			return response()->json([
				'total' => $data->count()
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Gagal mengambil data: ' . $e->getMessage(),
			], 500);
		}
	}

    public function addValueParamApi(Request $request){
		$stp = TemplateStp::with('sample')->where('id', $request->id_stp)->select('name','category_id')->first();
		// dd($request->all());
        $repo_quota = json_decode(
            Repository::dir('filtered_quota_sampel')->key($request->tgl)->get(),
            true
        );
		// $analyst = TemplateAnalyst::where('unique_id', $request->unique_id)->first();
		if($stp->name == 'TITRIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
			// dd('masuk titri');
			if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
				$result = self::HelperTitrimetri($request, $stp, $repo_quota);
				if($result->status == 200){
					return response()->json([
						'message'=> $result->message,
						'status' => $result->status
					], $result->status);
				} else {
					return response()->json([
						'message'=> $result->message,
						'status' => $result->status
					], $result->status);
				}
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 401);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 401);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
				$vts = $request->vts;
				$vtb = $request->vtb;
				$kt = $request->kt;
				$vs = $request->vs;
				$fp = $request->fp;

				$parame = $request->parameter;
				$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
				$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
				$id_po = '';
				$tgl_terima = '';

				if(isset($check->id)){
					$id_po = $check->id;
					$tgl_terima = $check->tanggal_terima;
				}
				else{
					return response()->json([
						'message'=> 'No Sample tidak ada.!!'
					], 401);
				}
				$cari = Titrimetri::where('no_sampel', $request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active',true)
						->where('is_total',false)
						->get();
				$n = count($cari);
				$datas = new FunctionValue();
				$checkParam = $datas->Titrimetri($par->id, $request, $n);
				if($checkParam == 'gagal') {
					return response()->json([
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					], 401);
				}else {
					$data = new Titrimetri;
					$data->no_sampel 			= $request->no_sample;
					$data->parameter 			= $request->parameter;
					$data->template_stp 		= $request->id_stp;
					$data->jenis_pengujian 		= $request->jenis_pengujian;
					$data->konsentrasi_titan 	= $request->kt;   //konsentrasi titran
					if ($request->has('volume_titrasi_baru')) {
						$data->vts = $request->volume_titrasi_baru;
					} elseif ($request->has('do_sampel_5_hari_baru')) {
						$data->do_sampel5 = $request->do_sampel_5_hari_baru;
						$data->do_sampel0 = $request->do_sampel_0_hari_baru;
						$data->do_blanko5 = $request->do_blanko_5_hari_baru;
						$data->do_blanko0 = $request->do_blanko_0_hari_baru;
						$data->vmb 	= $request->volume_mikroba_blanko_baru;
						$data->vms 	= $request->volume_mikroba_sampel_baru;
						$data->fp 	= $request->faktor_pengenceran_baru;
					}
							// Tambahkan penanganan untuk parameter lainnya di sini
							// Misalnya:
					$data->vts = $request->vts; // volume titrasi
					$data->fp = $request->fp; // faktor pengenceran
					$data->vtb 					= $request->vtb;  //vokume titrasi blanko
					$data->vs 					= $request->vs;  //volume sample
					$data->note 				= $request->note;
					$data->tanggal_terima 		= $tgl_terima;
					$data->created_by 			= $this->karyawan;
					$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
					$data->status 				= $n;
					// dd('stop');
					$data->save();

					$datas = new FunctionValue();
					$result = $datas->Titrimetri($par->id, $request, $n);

					// dd($result);
					WsValueAir::create($result);

					return response()->json([
						'message'=> 'Value Parameter berhasil disimpan.!',
						'par' => $request->parameter
					], 200);
				}

			}
		}else if($stp->name == 'GRAVIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
		// }else if(($analyst->nama == 'GRAVIMETRI A' || $analyst->nama == 'GRAVIMETRI B' || $analyst->nama == 'GRAVIMETRI') && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
			// dd('masuk');
			if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
				$result = self::HelperGravimetri($request, $stp, $repo_quota);
				if($result->status == 200){
					return response()->json([
						'message'=> $result->message,
					], $result->status);
				} else {
					return response()->json([
						'message'=> $result->message,
					], $result->status);
				}
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 401);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 401);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
				$parame = $request->parameter;
				$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

				$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',1)->first();
				$id_po = '';
				$tgl_terima = '';

				if(isset($check->id)){
					$id_po = $check->id;
					$tgl_terima = $check->tanggal_terima;
				}
				else{
					return response()->json([
						'message'=> 'No Sample tidak ada.!!'
					], 401);
				}
				$cari = Gravimetri::where('no_sampel', $request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active',true)
						->get();
				// dd($cari);
				$n = count($cari);

				$datas = new FunctionValue();
				// dd($request->all());
				$checkParam = $datas->Gravimetri($par->id, $request, $n);
				if($checkParam == 'gagal') {
					return response()->json([
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					], 401);
				}else {
					$data = new Gravimetri;
					$data->no_sampel 			= $request->no_sample;
					$data->parameter 			= $request->parameter;
					$data->template_stp 		= $request->id_stp;
					$data->jenis_pengujian 		= $request->jenis_pengujian;
					$data->bk_1 				= $request->bk_1;
					$data->bk_2 				= $request->bk_2;
					$data->bki_1 				= $request->bki1;
					$data->bki_2 				= $request->bki2;
					$data->vs 					= $request->vs;
					if($request->has('fp')) {
						$data->fp 					= $request->fp;
					}
					$data->note 				= $request->note;
					$data->tanggal_terima 		= $tgl_terima;
					$data->created_by 			= $this->karyawan;
					$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
					$data->status 				= $n;
					// dd($data,'retest');
					$data->save();

					$datas = new FunctionValue();
					$result = $datas->Gravimetri($par->id, $request, $n);
					// dd($result);
					WsValueAir::create($result);

					//================================Kalkulasi Mineral Nabati Otomatis===================================================================================
					$parameterList = json_decode($check->parameter);
					$filteredParameter = array_map(function ($parameter) {
						return explode(';', $parameter)[1];
					}, $parameterList);

					$m_nabati = ['OG', 'M.Mineral'];
					if(in_array($request->parameter, $m_nabati) && in_array('M.Nabati', $filteredParameter)){
						$hitung_otomatis = AutomatedFormula::where('parameter', 'M.Nabati')
							->where('required_parameter', $m_nabati)
							->where('no_sampel', $request->no_sample)
							->where('class_calculate', 'M_Nabati')
							->where('tanggal_terima', $tgl_terima)
							->calculate();
					}
					//================================End Kalkulasi Mineral Nabati Otomatis===================================================================================

					return response()->json([
						'message'=> 'Value Parameter berhasil disimpan.!',
						'par' => $request->parameter
					], 200);
				}

			}
		}else if(
			($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER')
			&&
			$stp->sample->nama_kategori == 'Air'
		) {

			if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
				if(isset($request->no_sample) && $request->no_sample!=null){
					$result = self::HelperColorimetri($request, $stp, $repo_quota);

					if($result->status == 200){
						return response()->json([
							'message'=> $result->message,
							'status' => $result->status
						], $result->status);
					} else {
						return response()->json([
							'message'=> $result->message,
							'status' => $result->status
						], $result->status);
					}
				} else {
					return response()->json([
						'message'=> 'No Sample tidak ditemukan'
					], 401);
				}
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 403);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 403);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
				if(isset($request->no_sample) && $request->no_sample!=null){
					$hp = $request->hp;
					$fp = $request->fp;

					// $cek = Colorimetri::where('no_sampel',$request->no_sample)
					// ->where('parameter', $request->parameter)
					// ->where('is_active',true)
					// ->first();

					// if(isset($cek->id)){
					// 	return response()->json([
					//         'message'=> 'No Sample Sudah ada.!!'
					//     ], 401);
					// }else{
					$parame = $request->parameter;
					$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
					$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
					$id_po = '';
					$tgl_terima = '';

					if(isset($check->id)){
						$id_po = $check->id;
						$tgl_terima = $check->tanggal_terima;
					}
					else{
						return response()->json([
							'message'=> 'No Sample tidak ada.!!'
						], 404);
					}

					$datas = new FunctionValue();
					// dd($request->nilaiBauTerkecil);
					$checkParam = $datas->Colorimetri($par->id, $request, '', '');
					if($checkParam == 'gagal') {
						return response()->json([
							'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						], 401);
					}else {
						DB::beginTransaction();
						try {
							if($par->id == 585 || $par->id == 555) {
								$hp = self::tabelMpn($request->tb1, $request->tb2, $request->tb3);
							}else {
								$hp = $request->hp;
							}
							$data = new Colorimetri;
							$data->no_sampel 			= $request->no_sample;
							$data->parameter 			= $request->parameter;
							$data->template_stp 		= $request->id_stp;
							$data->jenis_pengujian 		= $request->jenis_pengujian;
							$data->hp 					= $hp;
							if($request->parameter=='Persistent Foam'){
								$data->fp = $request->waktu;
							}else{
								$data->fp = $request->fp; //faktor pengenceran
							}
							$data->note 				= $request->note;
							$data->tanggal_terima 		= $tgl_terima;
							$data->created_by 			= $this->karyawan;
							$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
							$data->save();

							$datas = new FunctionValue();
							$result = $datas->Colorimetri($par->id, $request, '', $hp);
							// dd($result);
							WsValueAir::create($result);

							DB::commit();
							return response()->json([
								'message'=> 'Value Parameter berhasil disimpan.!',
								'par' => $request->parameter
							], 200);
						} catch (\Exception $th) {
							DB::rollBack();
							return response()->json([
								'message'=> 'Gagal Input Parameter :' . $th->getMessage()
							], 500);
						}
					}
					// }
				} else {
					return response()->json([
						'message'=> 'No Sample tidak ditemukan'
					], 403);
				}
			} else {
				return response()->json([
					'message'=> 'Pilih jenis pengujian'
				], 403);
			}
		}else if($stp->name == 'KIMIA PANGAN A' && $stp->sample->nama_kategori == 'Pangan'){
			if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
				if(isset($request->no_sample) && $request->no_sample!=null){
					$result = self::HelperKimiaPangan($request, $stp);
				} else {
					return response()->json([
						'message'=> 'No Sample tidak ditemukan'
					], 404);
				}
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 403);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 403);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
				if(isset($request->no_sample) && $request->no_sample!=null){
					$hp = $request->hp;
					$fp = $request->fp;

						$parame = $request->parameter;

						$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
						$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
						$id_po = '';
						$tgl_terima = '';

						if(isset($check->id)){
							$id_po = $check->id;
							$tgl_terima = $check->tanggal_terima;
						}
						else{
							return response()->json([
								'message'=> 'No Sample tidak ada.!!'
							], 401);
						}
						$cari = Colorimetri::where('no_sampel', $request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active',true)
						->get();
						$n = count($cari);
						$datas = new FunctionValue();
						$checkParam = $datas->Colorimetri($par->id, $request, $n, '');
						if($checkParam == 'gagal') {
							return response()->json([
								'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
							], 401);
						}else {
							DB::beginTransaction();
							try {
								$data = new Colorimetri;
								$data->no_sampel 			= $request->no_sample;
								$data->parameter 			= $request->parameter;
								$data->template_stp 		= $request->id_stp;
								$data->jenis_pengujian 		= $request->jenis_pengujian;
								if($request->has('nilaiBauTerkecil')){
									if($request->nilaiBauTerkecil == 'Tidak Berbau'){
										$data->hp 					= 'Tidak Berbau';
									}else{
										$data->hp					= $request->nilaiBauTerkecil;
									}
								}else if($request->has('nilaiTerkecil')){
									if($request->nilaiTerkecil == 'Tidak Berasa'){
										$data->hp 					= 'Tidak Berasa';
									}else{
										$data->hp 					= $request->nilaiTerkecil;
									}
								}
								$data->note 				= $request->note;
								$data->tanggal_terima 		= $tgl_terima;
								$data->created_by 			= $this->karyawan;
								$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
								$data->status 				= $n;
								$data->save();

								$datas = new FunctionValue();
								$result = $datas->Colorimetri($par->id, $request, $n, '');

								WsValueAir::create($result);

								DB::commit();
								return response()->json([
									'message'=> 'Value Parameter berhasil disimpan.!',
									'par' => $request->parameter
								], 200);
							} catch (Exception $th) {
								DB::rollBack();
								return response()->json([
									'message'=> 'Error : '.$th
								],500);
							}
						}
				} else {
					return response()->json([
						'message'=> 'No Sample tidak ditemukan'
					], 403);
				}
			} else {
				return response()->json([
					'message'=> 'Pilih jenis pengujian'
				], 403);
			}
		} else if (
			($stp->name == 'ICP' || $stp->name == 'COLORIMETER' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER' || $stp->name == 'Mikrobiologi Padatan')
			&&
			$stp->sample->nama_kategori == 'Padatan'
		) {
			if (isset($request->jenis_pengujian) && $request->jenis_pengujian == 'sample') {
				if (isset($request->no_sample) && $request->no_sample != null) {
					$result = self::HelperColorimetriPadatan($request, $stp);
                    return response()->json([
                        'message' => $result->message,
                        'status' => $result->status
                    ], $result->status);
				} else {
					return response()->json([
						'message' => 'No Sample tidak ditemukan'
					], 403);
				}
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 403);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 403);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
				if(isset($request->no_sample) && $request->no_sample!=null){
					$hp = $request->hp;
					$fp = $request->fp;

						$parame = $request->parameter;

						$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
						$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
						$id_po = '';
						$tgl_terima = '';

						if(isset($check->id)){
							$id_po = $check->id;
							$tgl_terima = $check->tanggal_terima;
						}
						else{
							return response()->json([
								'message'=> 'No Sample tidak ada.!!'
							], 404);
						}
						$cari = Colorimetri::where('no_sampel', $request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active',true)
						->get();
						$n = count($cari);

						$datas = new FunctionValue();
						$checkParam = $datas->Colorimetri($par->id, $request, $n, '');
						if($checkParam == 'gagal') {
							return response()->json([
								'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
							], 404);
						}else {
							DB::beginTransaction();
							try {
								$data = new Colorimetri;
								$data->no_sampel 			= $request->no_sample;
								$data->param 				= $request->parameter;
								$data->par 					= $request->id_stp;
								$data->jenis_pengujian 		= $request->jenis_pengujian;
								$data->hp 					= $request->hp;  //volume sample
								if($request->parameter=='Persistent Foam'){$data->fp = $request->waktu;}else{$data->fp = $request->fp;}  //faktor pengenceran
								$data->note 				= $request->note;
								$data->tanggal_terima 		= $tgl_terima;
								$data->created_by 			= $this->karyawan;
								$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
								$data->status 				= $n;
								$data->save();

								$datas = new FunctionValue();
								$result = $datas->Colorimetri($par->id, $request, $n, '');

								WsValueAir::create($result);

								DB::commit();

								return response()->json([
									'message'=> 'Value Parameter berhasil disimpan.!',
									'par' => $request->parameter
								], 200);
							} catch (\Exception $e) {
								DB::rollBack();

								return response()->json([
									'message'=> 'Gagal menyimpan value parameter. Error : '.$e->getMessage()
								], 400);
							}
						}
				} else {
					return response()->json([
						'message'=> 'No Sample tidak ditemukan'
					], 404);
				}
			} else {
				return response()->json([
					'message'=> 'Pilih jenis pengujian'
				], 403);
			}
		}else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Udara'){
			// dd($request->all(), 'alif');
			$po = OrderDetail::where('no_sampel', $request->no_sample)
				->where('is_active', true)
				->first();

			$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
			if($par->id == 633|| $par->id == 634|| $par->id == 635|| $par->id == 222){
				// dd($request->all());
				$result = self::HelperDebuPersonal($request,$stp,$po);
				if($result->status == 200){
					return response()->json([
						'message'=> $result->message,
						'status' => $result->status
					], $result->status);
				}else{
					return response()->json([
						'message'=> $result->message,
						'status' => $result->status
					], $result->status);
				}
			}else if(in_array($par->id, [223,224])){
				$po = OrderDetail::where('no_sampel', $request->no_sample)
					->where('is_active', true)
					->first();

				$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

				$header = DustFallHeader::where('no_sampel', $request->no_sample)
					->where('parameter', $request->parameter)
					->where('is_active', true)->first();
				$result = self::HelperDustFall($request, $stp, $po, $header);
				if($result->status){
					return response()->json([
						'message'=> $result->message,
						'status' => $result->status
					], $result->status);
				}
			}else{
				// dd('masuk');
				$datlapanganh = DataLapanganLingkunganHidup::where('no_sampel', $request->no_sample)->first();
				$datlapangank = DataLapanganLingkunganKerja::where('no_sampel', $request->no_sample)->first();
				$datlapanganV = DataLapanganSenyawaVolatile::where('no_sampel', $request->no_sample)->first();
				// dd($datlapanganh,$datlapangank);
				// $param = [293, 294, 295, 296, 326, 327, 328, 329, 299, 300, 289, 290, 291, 246, 247, 248, 249, 342, 343, 344, 345, 261, 256, 211, 310, 311, 312, 313, 314, 315, 568, 211, 564, 305, 306, 307, 308, 234, 569, 287, 292, 219];
				// if (!in_array($par->id, $param)) {
				// 	return response()->json([
				// 		'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
				// 	], 404);
				// } else {
                $result = self::HelperLingkungan($request, $stp, $datlapanganh, $datlapangank, $datlapanganV);
                // dd($result);
                if($result->status == 200){
                    return response()->json([
                        'message'=> $result->message,
                        'status' => $result->status
                    ], $result->status);
                }else{
                    return response()->json([
                        'message'=> $result->message,
                        'status' => $result->status,
                        'line' => $result->line ?? '',
                        'file' => $result->file ?? '',
                        'trace' => $result->trace ?? ''
                    ], $result->status);
                }
				// }
			}
		}else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Emisi'){
			$datlapangan = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sample)->first();
			if(!$datlapangan) {
				return response()->json([
					'message' => 'No Sample tidak ada di data lapangan emisi cerobong.'
				],404);
			}
			$po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active',true)->first();
			$wsemisi = EmisiCerobongHeader::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active',true)->first();
			$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

			if($wsemisi) {
				return response()->json([
					'message'=> 'Parameter sudah diinput..!!'
				], 403);
			}else {
					$param = [365, 368, 364, 360, 377, 354, 358, 378, 385, 362];
                    $gravimetri_emisi = ['Isokinetik (All)','Iso-Debu'];
					if($par->id == '355'){
						$result = self::HelperEmisiCl2($request, $stp, $po, $datlapangan);
						if($result->status){
							return response()->json([
								'message'=> $result->message,
								'status' => $result->status
							], $result->status);
						}
					}else if(in_array($request->parameter, $gravimetri_emisi)){
                        $datlapangan = DataLapanganIsokinetikHasil::where('no_sampel', $request->no_sample)->first();
                        $result = self::HelperEmisiGravimetri($request, $stp, $po, $datlapangan);
						if($result->status) {
							return response()->json([
								'message'=> $result->message,
								'status' => $result->status,
								'line' => $result->line ?? '',
								'file' => $result->file ?? ''
							], $result->status);
						}
                    }
					else {
						$result = self::HelperEmisiCerobong($request, $stp, $po, $datlapangan);
						if($result->status) {
							return response()->json([
								'message'=> $result->message,
								'status' => $result->status,
								'line' => $result->line ?? '',
								'file' => $result->file ?? ''
							], $result->status);
						}
					}
			}
		}else if($stp->name == 'DIRECT READING' && $stp->sample->nama_kategori == 'Udara'){
			if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
				if(isset($request->no_sample) && $request->no_sample!=null){
					$po = OrderDetail::where('no_sampel', $request->no_sample)
						->where('is_active', true)
						->first();

					$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

					$header = DustFallHeader::where('no_sampel', $request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active', true)->first();

					if(in_array($par->id, [223,224])) {
						$result = self::HelperDustFall($request, $stp, $po, $header);
						if($result->status){
							return response()->json([
								'message'=> $result->message,
								'status' => $result->status
							], $result->status);
						}
					} else {
						return response()->json([
							'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
						], 401);
					}
				}
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 401);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
				return response()->json([
					'message'=> 'This action not suitable'
				], 401);
			} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
				if(isset($request->no_sample) && $request->no_sample!=null){
					$po = OrderDetail::where('no_sampel', $request->no_sample)
						->where('is_active', true)
						->first();

					$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

					$header = DustfallHeader::where('no_sampel', $request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active', true)->first();

					if($par->id == '223') {
						if($header) {
							return response()->json([
								'message' => 'Parameter sudah diinput..!!'
							], 401);
						}else{
							$id_po = '';
							$tgl_terima = '';

							if(isset($po->id)){
								$id_po = $po->id;
								$tgl_terima = $po->tanggal_terima;
							}
							else{
								return response()->json([
									'message'=> 'No Sample tidak ada.!!'
								], 401);
							}
							try {
								DB::beginTransaction();

								$header = new DustfallHeader();
								$header->no_sampel = $request->no_sample;
								$header->parameter = $request->parameter;
								$header->template_stp = $request->id_stp;
								$header->id_parameter = $par->id;
								$header->note = $request->note;
								$header->tanggal_terima = $po->tanggal_terima;
								$header->is_active = true;
								$header->created_by = $this->karyawan;
								$header->created_at = Carbon::now()->format('Y-m-d H:i:s');
								$header->save();

								$rumus = new FunctionValue();
								$result = $rumus->valDustfall($id_po, $request, $par->id, $po->tanggal_terima, $this->karyawan);

								WsValueLingkungan::create($result);

								DB::commit();

								return response()->json([
									'message'=> 'Value Parameter berhasil disimpan.!',
									'par' => $request->parameter
								], 200);
							} catch (\Exception $e) {
								DB::rollback();
								return response()->json([
									'message' => 'Error: ' . $e->getMessage()
								], 401);
							}
						}
					}
				}else {
					return response()->json([
						'message'=> 'No Sample tidak ditemukan'
					], 401);
				}
			} else {
				return response()->json([
					'message'=> 'Pilih jenis pengujian'
				], 401);
			}
		}else if($stp->name == 'MIKROBIOLOGI' && $stp->sample->nama_kategori == 'Udara'){
			if (isset($request->jenis_pengujian)) {
				// Jenis Pengujian: sample
				if ($request->jenis_pengujian == 'sample') {
					if (isset($request->no_sample) && $request->no_sample != null) {
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();

						$result = self::HelperMikrobiologi($request, $stp,$po);
						if($result->status){
							return response()->json([
								'message'=> $result->message,
								'status' => $result->status
							], $result->status);
						}
					}
				}

				// Jenis Pengujian: duplo, spike, atau lainnya
				elseif (in_array($request->jenis_pengujian, ['duplo', 'spike'])) {
					return response()->json([
						'message' => 'This action not suitable'
					], 401);
				}

				// Jenis Pengujian: retest
				elseif ($request->jenis_pengujian == 'retest') {
					if (isset($request->no_sample) && $request->no_sample != null) {
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();

						$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

						$id_param = [587, 586, 266, 235, 619, 620];

						if (in_array($par->id, $id_param)) {
							$fdl = DetailMicrobiologi::where('no_sampel', $request->no_sample)
								->where('is_active', true)
								->where('parameter', $request->parameter)
								->first();

							$header = MicrobioHeader::where('no_sampel', $request->no_sample)
								->where('parameter', $request->parameter)
								->where('is_active', true)
								->first();

							if ($header) {
								return response()->json([
									'message' => 'Parameter sudah diinput..!!'
								], 401);
							}

							if ($fdl) { // Periksa apakah $fdl tidak null
								try {
									// Ambil data suhu, tekanan, dan kelembaban
									$suhu = $fdl->suhu;
									$tekanan = $fdl->tekanan_udara;
									$kelembaban = $fdl->kelembapan;

									// Decode JSON di dalam pengukuran
									$pengukuran = json_decode($fdl->pengukuran);

									// Ambil nilai Flow Rate dan Durasi
									$flowRate = (float) ($pengukuran->{"Flow Rate"} ?? null);
									$durasi = (float) preg_replace('/\D/', '', $pengukuran->Durasi) ?? null;

									$volume = ($flowRate * $durasi) / 1000;

								} catch (\Exception $e) {
									return response()->json([
										'message' => 'Error: ' . $e->getMessage()
									], 500);
								}

								try {
									// Mulai transaksi
									DB::beginTransaction();

									// Simpan data ke tabel Microbioheader
									$header = new MicrobioHeader();
									$header->no_sampel = $request->no_sample;
									$header->parameter = $request->parameter;
									$header->template_stp = $request->id_stp;
									$header->id_parameter = $par->id;
									$header->note = $request->note;
									$header->tanggal_terima = $po->tanggal_terima;
									$header->created_by = $this->karyawan;
									$header->created_at = now();
									$header->save();

									// Hitung hasil menggunakan fungsi Microbio
									$rumus = new FunctionValue();
									$result = $rumus->Microbio(
										$volume,
										$flowRate,
										$durasi,
										$suhu,
										$tekanan,
										$kelembaban,
										$po->tanggal_terima,
										$request,
										$this->karyawan,
										$par->id,
										$header->id
									);

									// Simpan hasil ke tabel ws_value_microbio
									WsValueMicrobio::create($result);

									// Commit transaksi jika semua berhasil
									DB::commit();

									return response()->json([
										'message' => 'Value Parameter berhasil disimpan.!',
										'par' => $request->parameter,
									], 200);

								} catch (\Exception $e) {
									// Rollback transaksi jika terjadi kesalahan
									DB::rollBack();

									return response()->json([
										'message' => 'Error: ' . $e->getMessage(),
									], 500);
								}
							} else {
								return response()->json([
									'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
								], 404);
							}
						}
					}
				}
				// Jika jenis pengujian tidak dikenali
				else {
					return response()->json([
						'message' => 'Pilih jenis pengujian'
					], 401);
				}
			} else {
				return response()->json([
					'message' => 'Jenis pengujian tidak ada.'
				], 401);
			}
		}else if($stp->name == 'SWAB TEST' && $stp->sample->nama_kategori == 'Udara'){
			if (isset($request->jenis_pengujian)) {
				// Jenis Pengujian: sample
				if ($request->jenis_pengujian == 'sample') {
					if (isset($request->no_sample) && $request->no_sample != null) {
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();

						$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

                        $result = self::HelperSwabTest($request, $stp, $po);
                        if($result->status){
                            return response()->json([
                                'message' => $result->message,
                                'status' => $result->status
                            ], 200);
                        }
					}
				}

				// Jenis Pengujian: duplo, spike, atau lainnya
				elseif (in_array($request->jenis_pengujian, ['duplo', 'spike'])) {
					return response()->json([
						'message' => 'This action not suitable'
					], 401);
				}

				// Jenis Pengujian: retest
				elseif ($request->jenis_pengujian == 'retest') {
					if (isset($request->no_sample) && $request->no_sample != null) {
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();

						$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

						$id_param = [337, 227, 340];

						if (in_array($par->id, $id_param)) {
							$fdl = DataLapanganSwab::where('no_sampel', $request->no_sample)->first();

							$header = SwabTestHeader::where('no_sampel', $request->no_sample)
								->where('parameter', $request->parameter)
								->where('is_active', true)
								->first();

							if ($header) {
								return response()->json([
									'message' => 'Parameter sudah diinput..!!'
								], 401);
							}

							if ($fdl) { // Periksa apakah $fdl tidak null
								try {
									// Ambil data suhu, tekanan, dan kelembaban
									$luas = $fdl->luas;

								} catch (\Exception $e) {
									return response()->json([
										'message' => 'Error: ' . $e->getMessage()
									], 500);
								}

								try {
									// Mulai transaksi
									DB::beginTransaction();

									// Simpan data ke tabel SwabTestHeader
									$header = new SwabTestHeader();
									$header->no_sampel = $request->no_sample;
									$header->parameter = $request->parameter;
									$header->template_stp = $request->id_stp;
									$header->id_parameter = $par->id;
									$header->note = $request->note;
									$header->tanggal_terima = $po->tanggal_terima;
									$header->created_by = $this->karyawan;
									$header->created_at = Carbon::now()->format('Y-m-d H:i:s');
									$header->save();

									// Hitung hasil menggunakan fungsi SwabTest
									$rumus = new FunctionValue();
									$result = $rumus->SwabTest(
										$luas,
										$po->tanggal_terima,
										$request,
										$this->karyawan,
										$par->id,
										$header->id
									);

									// Simpan hasil ke tabel ws_value_swabtest
									WsValueSwab::create($result);

									// Commit transaksi jika semua berhasil
									DB::commit();

									return response()->json([
										'message' => 'Value Parameter berhasil disimpan.!',
										'par' => $request->parameter,
									], 200);

								} catch (\Exception $e) {
									// Rollback transaksi jika terjadi kesalahan
									DB::rollBack();

									return response()->json([
										'message' => 'Error: ' . $e->getMessage(),
									], 500);
								}
							} else {
								return response()->json([
									'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
								], 404);
							}
						}
					}
				}
				// Jika jenis pengujian tidak dikenali
				else {
					return response()->json([
						'message' => 'Pilih jenis pengujian'
					], 401);
				}
			} else {
				return response()->json([
					'message' => 'Jenis pengujian tidak ada.'
				], 401);
			}
		} else if(in_array($stp->name, ['Other','OTHER']) && in_array($stp->sample->nama_kategori,['Air','Udara','Emisi','Padatan'])){
			if (isset($request->jenis_pengujian)) {
				// Jenis Pengujian: sample
				if ($request->jenis_pengujian == 'sample') {
					if (isset($request->no_sample) && $request->no_sample != null) {
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();

						$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

						$result = self::HelperOthers($request, $stp,$po, $par);
						if($result->status){
							return response()->json([
								'message'=> $result->message,
								'status' => $result->status
							], $result->status);
						}
					}
				} else if ($request->jenis_pengujian == 'retest') {

				} else {
					return response()->json([
						'message' => 'Jenis pengujian tidak ada.'
					], 401);
				}
			}
		} else if($stp->name == 'ISOKINETIK' && $stp->sample->nama_kategori == 'Emisi'){
			// if (isset($request->jenis_pengujian)) {
			// 	// Jenis Pengujian: sample
			// 	if ($request->jenis_pengujian == 'sample') {
			// 		if (isset($request->no_sample) && $request->no_sample != null) {
			// 			$po = OrderDetail::where('no_sampel', $request->no_sample)
			// 				->where('is_active', true)
			// 				->first();

			//             $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

			// 			$fdl = DataLapanganIsokinetikHasil::where('no_sampel', $request->no_sample)
			// 			->where('is_active', true)
			// 			->first();

			// 			$header = IsokinetikHeader::where('no_sampel', $request->no_sample)
			// 			->where('parameter', $request->parameter)
			// 			->where('is_active', 0)
			// 			->first();
			// 			// if ($par->id == 366) {
			// 			if ($header) {
			// 				return response()->json([
			// 					'message' => 'Parameter sudah diinput..!!'
			// 				], 401);
			// 			}else{
			// 				if ($fdl) { // Periksa apakah $fdl tidak null
			// 					try {
			// 						// Ambil data vm(volume gas)
			// 						$vm = $fdl->v_gas;

			// 					} catch (\Exception $e) {
			// 						return response()->json([
			// 							'message' => 'Error: ' . $e->getMessage()
			// 						], 500);
			// 					}
			// 				} else {
			// 					return response()->json([
			// 						'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
			// 					], 404);
			// 				}

			// 				try {
			// 					// Mulai transaksi
			// 					DB::beginTransaction();

			// 					// Simpan data ke tabel SwabTestHeader
			// 					$header = new IsokinetikHeader();
			// 					$header->no_sampel = $request->no_sample;
			// 					$header->parameter = $request->parameter;
			// 					$header->template_stp = $request->id_stp;
			// 					$header->id_parameter = $par->id;
			// 					$header->note = $request->note;
			// 					$header->tanggal_terima = $po->tanggal_terima;
			// 					$header->created_by = $this->karyawan;
			// 					$header->created_at = Carbon::now()->format('Y-m-d H:i:s');
			// 					$header->save();

			// 					// Hitung hasil menggunakan fungsi SwabTest
			// 					$rumus = new FunctionValue();
			// 					$result = $rumus->Isokinetik(
			// 						$vm,
			// 						$po->id,
			// 						$po->tanggal_terima,
			// 						$request,
			// 						$this->karyawan,
			// 						$par->id,
			// 						$header->id
			// 					);

			// 					// Simpan hasil ke tabel ws_value_swabtest
			// 					DB::table('ws_value_isokinetik')->insert($result);

			// 					// Commit transaksi jika semua berhasil
			// 					DB::commit();

			// 					return response()->json([
			// 						'message' => 'Value Parameter berhasil disimpan.!',
			// 						'par' => $request->parameter,
			// 					], 200);

			// 				} catch (\Exception $e) {
			// 					// Rollback transaksi jika terjadi kesalahan
			// 					DB::rollBack();

			// 					return response()->json([
			// 						'message' => 'Error: ' . $e->getMessage(),
			// 					], 500);
			// 				}
			// 			}
			// 		}
			// 	}

			// 	// Jenis Pengujian: duplo, spike, atau lainnya
			// 	elseif (in_array($request->jenis_pengujian, ['duplo', 'spike'])) {
			// 		return response()->json([
			// 			'message' => 'This action not suitable'
			// 		], 401);
			// 	}

			// 	// Jenis Pengujian: retest
			// 	elseif ($request->jenis_pengujian == 'retest') {
			// 		if (isset($request->no_sample) && $request->no_sample != null) {
			// 			$po = Po::where('no_sample', $request->no_sample)
			// 			->where('active', 0)
			// 			->first();

			// 			$par = Parameter::where('name', $request->parameter)
			// 			->where('category_sample', 5)
			// 			->where('active', 0)
			// 			->first();

			// 			$fdl = ValueHasilIsokinetik::where('no_sample', $request->no_sample)
			// 			->where('active', 0)
			// 			->first();

			// 			$header = IsokinetikHeader::where('no_sample', $request->no_sample)
			// 			->where('parameter', $request->parameter)
			// 			->where('active', 0)
			// 			->first();
			// 			// if ($par->id == 366) {
			// 			if ($header) {
			// 				return response()->json([
			// 					'message' => 'Parameter sudah diinput..!!'
			// 				], 401);
			// 			}else{
			// 				if ($fdl) { // Periksa apakah $fdl tidak null
			// 					try {
			// 						// Ambil data vm(volume gas)
			// 						$vm = $fdl->v_gas;

			// 					} catch (\Exception $e) {
			// 						return response()->json([
			// 							'message' => 'Error: ' . $e->getMessage()
			// 						], 500);
			// 					}
			// 				} else {
			// 					return response()->json([
			// 						'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
			// 					], 404);
			// 				}

			// 				try {
			// 					// Mulai transaksi
			// 					DB::beginTransaction();

			// 					// Simpan data ke tabel SwabTestHeader
			// 					$header = new IsokinetikHeader();
			// 					$header->id_po = $po->id;
			// 					$header->no_sample = $request->no_sample;
			// 					$header->parameter = $request->parameter;
			// 					$header->par = $request->id_stp;
			// 					$header->id_parameter = $par->id;
			// 					$header->note = $request->note;
			// 					$header->tanggal_terima = $po->tanggal_terima;
			// 					$header->created_by = $this->karyawan;
			// 					$header->created_at = Carbon::now()->format('Y-m-d H:i:s');
			// 					$header->save();

			// 					// Hitung hasil menggunakan fungsi SwabTest
			// 					$rumus = new FunctionValue();
			// 					$result = $rumus->Isokinetik(
			// 						$vm,
			// 						$po->id,
			// 						$po->tanggal_terima,
			// 						$request,
			// 						$this->karyawan,
			// 						$par->id,
			// 						$header->id
			// 					);

			// 					// Simpan hasil ke tabel ws_value_swabtest
			// 					DB::table('ws_value_isokinetik')->insert($result);

			// 					// Commit transaksi jika semua berhasil
			// 					DB::commit();

			// 					return response()->json([
			// 						'message' => 'Value Parameter berhasil disimpan.!',
			// 						'par' => $request->parameter,
			// 					], 200);

			// 				} catch (\Exception $e) {
			// 					// Rollback transaksi jika terjadi kesalahan
			// 					DB::rollBack();

			// 					return response()->json([
			// 						'message' => 'Error: ' . $e->getMessage(),
			// 					], 500);
			// 				}
			// 			}
			// 		}
			// 	}
			// 	// Jika jenis pengujian tidak dikenali
			// 	else {
			// 		return response()->json([
			// 			'message' => 'Pilih jenis pengujian'
			// 		], 401);
			// 	}
			// } else {
			// 	return response()->json([
			// 		'message' => 'Jenis pengujian tidak ada.'
			// 	], 401);
			// }
		}
    }

	public function HelperTitrimetri($request, $stp, $quota_count){
		DB::beginTransaction();
		try {
			// Cek apakah sampel sudah ada
			$cek = Titrimetri::where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->where('is_total',false)
				->where('status', 0)
				->first();

			if ($cek != null) {
				return (object)[
					'message'=> 'No Sample Sudah ada.!!',
					'status' => 401
				];
			}

			// Ambil data parameter
			$parame = $request->parameter;
			$data_parameter = Parameter::where('nama_lab', $parame)
				->where('id_kategori', $stp->category_id)
				->where('is_active', true)
				->first();

			// Cek order detail
			$check = OrderDetail::where('no_sampel', $request->no_sample)
				->where('is_active', true)
				->first();

			if(!isset($check->id)) {
				return (object)[
					'message'=> 'No Sample tidak ditemukan.!!',
					'status' => 401
				];
			}

			$id_po = $check->id;
			$tgl_terima = $check->tanggal_terima;
			// Proses kalkulasi dengan AnalystFormula
			$function = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first()->function;
			// dd($data_parameter);
			$data_parsing = $request->all();

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', (object)$data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();


			if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					'status' => 404
				];
			}

			// Buat data Titrimetri baru
			$data = new Titrimetri;
			$data->no_sampel = $request->no_sample;
			$data->parameter = $request->parameter;
			$data->template_stp = $request->id_stp;
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
			}

			// Parameter umum
			$data->vts = $request->vts; // volume titrasi
			$data->fp = $request->fp; // faktor pengenceran
			$data->vtb = $request->vtb;  //volume titrasi blanko
			$data->vs = $request->vs;  //volume sample
			$data->note = $request->note;
			$data->tanggal_terima = $tgl_terima;
			$data->created_by = $this->karyawan;
			$data->created_at = Carbon::now()->format('Y-m-d H:i:s');

			$data->save();

			// Simpan hasil kalkulasi
			$data_kalkulasi['id_titrimetri'] = $data->id;
			$data_kalkulasi['no_sampel'] = $request->no_sample;
			WsValueAir::create($data_kalkulasi);

            $parameterList = json_decode($check->parameter);
            $filteredParameter = array_map(function ($parameter) {
                return explode(';', $parameter)[1];
            }, $parameterList);

            $total_coliform = [
                "BOD","BOD (B-23-NA)","BOD (B-23)",
                "TSS", "TSS (APHA-D-23-NA)", "TSS (APHA-D-23)", "TSS (IKM-SP-NA)", "TSS (IKM-SP)",
                "NH3","NH3-N","NH3-N Bebas","NH3-N (3-03-NA)","NH3-N (3-03)","NH3-N (30-25-NA)","NH3-N (30-25)","NH3-N (T)","NH3-N (T-NA)"
            ];

            $method_t_coli = null;

            if (!empty($check->kategori_3)) {
                switch ($check->kategori_3) {
                    case '3-Air Limbah Industri':
                        $method_t_coli = 'Total_Coliform_LI';
                        break;
                    case '2-Air Limbah Domestik':
                        $method_t_coli = 'Total_Coliform_LD';
                        break;
                    default:
                        $method_t_coli = null;
                        break;
                }
            }

            $parameter_t_coli = 'Total Coliform';
            // 'Total Coliform','Total Coliform (MPN)' ,'Total Coliform (NA)'
            if(in_array('Total Coliform (MPN)', $filteredParameter)){
                $parameter_t_coli = 'Total Coliform (MPN)';
            }elseif(in_array('Total Coliform (NA)', $filteredParameter)){
                $parameter_t_coli = 'Total Coliform (NA)';
            }
            if(!is_null($method_t_coli) && (isset($quota_count[2]) && !in_array($request->no_sample, $quota_count[2][$parameter_t_coli]))){
                if(in_array($request->parameter, $total_coliform) && (in_array('Total Coliform', $filteredParameter) || in_array('Total Coliform (MPN)', $filteredParameter) || in_array('Total Coliform (NA)', $filteredParameter))){
                    $hitung_otomatis = AutomatedFormula::where('parameter', $parameter_t_coli)
                        ->where('required_parameter', $total_coliform)
                        ->where('no_sampel', $request->no_sample)
                        ->where('class_calculate', $method_t_coli)
                        ->where('tanggal_terima', $tgl_terima)
                        ->calculate();
                }
            }

			DB::commit();

			return (object)[
				'message'=> 'Value Parameter berhasil disimpan.!',
				'status' => 200
			];
		} catch (\Throwable $th) {
			DB::rollBack();
			return (object)[
				'message' => 'Gagal input parameter: '.$th->getMessage(),
				'file' => $th->getFile(),
				'line' => $th->getLine(),
				'status' => 401
			];
		}
	}

	public function HelperGravimetri($request, $stp, $quota_count) {
		// dd($request->all());
		DB::beginTransaction();
		try {
			$vts = $request->vts;
			$vtb = $request->vtb;
			$kt = $request->kt;
			$vs = $request->vs;
			$fp = $request->fp;

			$cek = Gravimetri::where('no_sampel',$request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active',true)
				->where('is_total',false)
				->where('status',0)
				->first();
			// dd($cek);
			if(isset($cek->id)){
				return (object)[
					'message'=> 'No Sample Sudah ada.!!',
					'status' =>	401
				];
			}else{
				$parame = $request->parameter;
				$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

				$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();

				if(!isset($check->id)){
					return (object) [
						'message'=> 'No Sample tidak ada.!!',
						'status' =>	401
					];
				}

				$id_po = $check->id;
				$tgl_terima = $check->tanggal_terima;
				// Proses kalkulasi dengan AnalystFormula
				$function = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first()->function;
				if($data_parameter->id == 2051 || $data_parameter->id == 2050){
					$function = isset($request->hp) ? 'Perkalian' : $function;
				}
				$data_parsing = $request->all();

				$data_kalkulasi = AnalystFormula::where('function', $function)
					->where('data', (object) $data_parsing)
					->where('id_parameter', $data_parameter->id)
					->process();


				if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}

				$data = new Gravimetri;
				$data->no_sampel 			= $request->no_sample;
				$data->parameter 			= $request->parameter;
				$data->template_stp 		= $request->id_stp;
				$data->jenis_pengujian 		= $request->jenis_pengujian;
				$data->bk_1 				= $request->bk_1;
				$data->bk_2 				= $request->bk_2;
				$data->bki_1 				= $request->bki1;
				$data->bki_2 				= $request->bki2;
				$data->vs 					= $request->vs;
				if($request->has('fp')) {
					$data->fp 					= $request->fp;
				}
				$data->note 				= $request->note;
				$data->tanggal_terima 		= $tgl_terima;
				$data->created_by 			= $this->karyawan;
				$data->created_at 			= Carbon::now()->format('Y-m-d H:i:s');
				// dd($data,'sample');
				$data->save();

				$data_kalkulasi['id_gravimetri'] = $data->id;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
				WsValueAir::create($data_kalkulasi);
				// if($request->parameter == "TDS"){
				// 	// $data->metode				= "baru";
				// 	$data->bk_1 				= $request->bk_1_baru;
				// 	$data->bk_2 				= $request->bk_2_baru;
				// 	$data->bki_1 				= $request->bi1_baru;
				// 	$data->bki_2 				= $request->bi2_baru;
				// 	$data->vs 					= $request->vs_baru;
				// }else{
				// 	// $data->metode				= "lama";
				// 	$data->bk_1 				= $request->bk_1;
				// 	$data->bk_2 				= $request->bk_2;
				// 	$data->bki_1 				= $request->bki1;
				// 	$data->bki_2 				= $request->bki2;
				// 	$data->vs 					= $request->vs;
				// 	$data->fp 					= $request->fp;
				// }

				//================================Kalkulasi Mineral Nabati Otomatis===================================================================================

				$parameterList = json_decode($check->parameter);
				$filteredParameter = array_map(function ($parameter) {
					return explode(';', $parameter)[1];
				}, $parameterList);

				// $m_nabati = ['OG', 'M.Mineral'];
				// if(in_array($request->parameter, $m_nabati) && in_array('M.Nabati', $filteredParameter)){
				// 	$hitung_otomatis = AutomatedFormula::where('parameter', 'M.Nabati')
				// 		->where('required_parameter', $m_nabati)
				// 		->where('no_sampel', $request->no_sample)
				// 		->where('class_calculate', 'M_Nabati')
				// 		->where('tanggal_terima', $tgl_terima)
				// 		->calculate();
				// }

                $total_coliform = [
                    "BOD","BOD (B-23-NA)","BOD (B-23)",
                    "TSS", "TSS (APHA-D-23-NA)", "TSS (APHA-D-23)", "TSS (IKM-SP-NA)", "TSS (IKM-SP)",
                    "NH3","NH3-N","NH3-N Bebas","NH3-N (3-03-NA)","NH3-N (3-03)","NH3-N (30-25-NA)","NH3-N (30-25)","NH3-N (T)","NH3-N (T-NA)"
                ];

                $method_t_coli = null;

                if (!empty($check->kategori_3)) {
                    switch ($check->kategori_3) {
                        case '3-Air Limbah Industri':
                            $method_t_coli = 'Total_Coliform_LI';
                            break;
                        case '2-Air Limbah Domestik':
                            $method_t_coli = 'Total_Coliform_LD';
                            break;
                        default:
                            $method_t_coli = null;
                            break;
                    }
                }

                $parameter_t_coli = 'Total Coliform';
                // 'Total Coliform','Total Coliform (MPN)' ,'Total Coliform (NA)'
                if(in_array('Total Coliform (MPN)', $filteredParameter)){
                    $parameter_t_coli = 'Total Coliform (MPN)';
                }elseif(in_array('Total Coliform (NA)', $filteredParameter)){
                    $parameter_t_coli = 'Total Coliform (NA)';
                }
                if(!is_null($method_t_coli) && (isset($quota_count[2]) && !in_array($request->no_sample, $quota_count[2][$parameter_t_coli]))){
                    if(in_array($request->parameter, $total_coliform) && (in_array('Total Coliform', $filteredParameter) || in_array('Total Coliform (MPN)', $filteredParameter) || in_array('Total Coliform (NA)', $filteredParameter))){
                        $hitung_otomatis = AutomatedFormula::where('parameter', $parameter_t_coli)
                            ->where('required_parameter', $total_coliform)
                            ->where('no_sampel', $request->no_sample)
                            ->where('class_calculate', $method_t_coli)
                            ->where('tanggal_terima', $tgl_terima)
                            ->calculate();
                    }
                }

				//================================End Kalkulasi Mineral Nabati Otomatis===================================================================================
				DB::commit();
				return (object)[
					'message'=> 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];

			}
		}catch (\Exception $th) {
			DB::rollBack();
			dd($th);
			return (object)[
				'message' => 'Gagal input parameter: '.$th->getMessage(),
				'file' => $th->getFile(),
				'line' => $th->getLine(),
				'status' => 401
			];
		}
	}

	public function HelperColorimetri($request, $stp, $quota_count) {
		DB::beginTransaction();
		try {
			$hp = $request->hp;
			$fp = $request->fp;

			$cek = Colorimetri::where('no_sampel',$request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active',true)
				->where('is_total',false)
				->first();

			if(isset($cek->id)){
				return (object)[
					'message'=> 'No Sample Sudah ada.!!',
					'status' => 401
				];
			}else{
				$parame = $request->parameter;
				$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
				$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();

				if(!isset($check->id)){
					return (object)[
						'message'=> 'No Sample tidak ada.!!',
						'status' => 401
					];
				}
				$id_po = $check->id;
				$tgl_terima = $check->tanggal_terima;
				// Proses kalkulasi dengan AnalystFormula
				$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
				// dump($functionObj);
				if (!$functionObj) {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}
				$function = $functionObj->function;
				if ($data_parameter->id == 179 || $data_parameter->id == 1955 || $data_parameter->id == 1956) { // TSS, DO (APHA-C-O3), DO (APHA-C-O3-NA)
					$function = isset($request->hp) ? 'Perkalian' : $function;
				} else if ($data_parameter->id == 58 || $data_parameter->id == 1957 || $data_parameter->id == 1958) { // DO, DO (G-03-NA), DO (G-03)
					$function = 'Direct';
				}

				$data_parsing = $request->all();
				$data_parsing = (object)$data_parsing;
				if(isset($data_parsing->nilai_terkecil)) {
					$hp = empty($data_parsing->nilai_terkecil) ? 'Tidak_Berbau' : $data_parsing->nilai_terkecil;
					$data_parsing->hp = $hp;
				}else{
					$hp = $request->hp;
				}
				
				$data_kalkulasi = AnalystFormula::where('function', $function)
					->where('data', $data_parsing)
					->where('id_parameter', $data_parameter->id)
					->process();


				if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}

				if($data_parameter->id == 585 || $data_parameter->id == 555) {
					$hp = self::tabelMpn($request->tb1, $request->tb2, $request->tb3);
				}else {
					$hp = isset($data_kalkulasi['hasil_mpn']) ? $data_kalkulasi['hasil_mpn'] : (isset($data_parsing->hp) ? $data_parsing->hp : null);
				}
				// dd($hp);

				$data = new Colorimetri;
				$data->no_sampel 			= $request->no_sample;
				$data->parameter 			= $request->parameter;
				$data->template_stp 		= $request->id_stp;
				$data->jenis_pengujian 		= $request->jenis_pengujian;
				$data->hp = $hp;
				if($request->parameter=='Persistent Foam'){
					$data->fp = $request->waktu;
				}else{
					$data->fp = $request->fp; //faktor pengenceran
				}
				$data->note 					= $request->note;
				$data->tanggal_terima 			= $tgl_terima;
				$data->created_by 				= $this->karyawan;
				$data->created_at 				= Carbon::now()->format('Y-m-d H:i:s');
				// dd($data);
				$data->save();

				$data_kalkulasi['id_colorimetri'] = $data->id;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
				if(isset($data_kalkulasi['hasil_mpn'])) unset($data_kalkulasi['hasil_mpn']);
				WsValueAir::create($data_kalkulasi);

				$parameterList = json_decode($check->parameter);
				$filteredParameter = array_map(function ($parameter) {
					return explode(';', $parameter)[1];
				}, $parameterList);
				// dd($filteredParameter);

				$no2_no3 = ['NO2-N', 'NO2-N (NA)', 'NO3-N', 'NO3-N (APHA-E-23)', 'NO3-N (IKM-SP)', 'NO3-N (SNI-7-03)'];
				if(in_array($request->parameter, $no2_no3) && in_array('NO2-N+NO3-N', $filteredParameter)){
					$hitung_otomatis = AutomatedFormula::where('parameter', "NO2-N+NO3-N")
						->where('required_parameter', $no2_no3)
						->where('no_sampel', $request->no_sample)
						->where('class_calculate', 'NO3_NO2')
						->where('tanggal_terima', $tgl_terima)
						->calculate();
				}

				// $n_total = [
				// 	'NO2-N', 'NO2-N (NA)',
				// 	'NO3-N', 'NO3-N (APHA-E-23)', 'NO3-N (IKM-SP)', 'NO3-N (SNI-7-03)',
				// 	'NH3-N', 'NH3-N (3-03-NA)', 'NH3-N (3-03)', 'NH3-N (30-25-NA)', 'NH3-N (30-25)',
				// 	'N-Organik', 'N-Organik (NA)'
				// ];
				// if(in_array($request->parameter, $n_total) && (in_array('N-Total', $filteredParameter)) || in_array('N-Total (NA)', $filteredParameter)){
				// 	$hitung_otomatis = AutomatedFormula::where('parameter', in_array('N-Total (NA)', $filteredParameter) ? 'N-Total (NA)' : 'N-Total')
				// 		->where('required_parameter', $n_total)
				// 		->where('no_sampel', $request->no_sample)
				// 		->where('class_calculate', 'N_Total')
				// 		->where('tanggal_terima', $tgl_terima)
				// 		->calculate();
				// }

				// $tkn_parameter = [
				// 	'NO2-N', 'NO2-N (NA)',
				// 	'NO3-N', 'NO3-N (APHA-E-23)', 'NO3-N (IKM-SP)', 'NO3-N (SNI-7-03)',
				// 	'NH3-N', 'NH3-N (3-03-NA)', 'NH3-N (3-03)', 'NH3-N (30-25-NA)', 'NH3-N (30-25)',
				// 	'N-Organik', 'N-Organik (NA)'
				// ];
				// if(in_array($request->parameter, $tkn_parameter) && in_array('TKN', $filteredParameter)){
				// 	$hitung_otomatis = AutomatedFormula::where('parameter', 'TKN')
				// 		->where('required_parameter', $tkn_parameter)
				// 		->where('no_sampel', $request->no_sample)
				// 		->where('class_calculate', 'TKN')
				// 		->where('tanggal_terima', $tgl_terima)
				// 		->calculate();
				// }

				$total_coliform = [
                    "BOD","BOD (B-23-NA)","BOD (B-23)",
                    "TSS", "TSS (APHA-D-23-NA)", "TSS (APHA-D-23)", "TSS (IKM-SP-NA)", "TSS (IKM-SP)",
                    "NH3","NH3-N","NH3-N Bebas","NH3-N (3-03-NA)","NH3-N (3-03)","NH3-N (30-25-NA)","NH3-N (30-25)","NH3-N (T)","NH3-N (T-NA)"
                ];

                $method_t_coli = null;

                if (!empty($check->kategori_3)) {
                    switch ($check->kategori_3) {
                        case '3-Air Limbah Industri':
                            $method_t_coli = 'Total_Coliform_LI';
                            break;
                        case '2-Air Limbah Domestik':
                            $method_t_coli = 'Total_Coliform_LD';
                            break;
                        default:
                            $method_t_coli = null;
                            break;
                    }
                }

                $parameter_t_coli = 'Total Coliform';
                // 'Total Coliform','Total Coliform (MPN)' ,'Total Coliform (NA)'
                if(in_array('Total Coliform (MPN)', $filteredParameter)){
                    $parameter_t_coli = 'Total Coliform (MPN)';
                }elseif(in_array('Total Coliform (NA)', $filteredParameter)){
                    $parameter_t_coli = 'Total Coliform (NA)';
                }
                if(!is_null($method_t_coli) && (isset($quota_count[2]) && !in_array($request->no_sample, $quota_count[2][$parameter_t_coli]))){
                    if(in_array($request->parameter, $total_coliform) && (in_array('Total Coliform', $filteredParameter) || in_array('Total Coliform (MPN)', $filteredParameter) || in_array('Total Coliform (NA)', $filteredParameter))){
                        $hitung_otomatis = AutomatedFormula::where('parameter', $parameter_t_coli)
                            ->where('required_parameter', $total_coliform)
                            ->where('no_sampel', $request->no_sample)
                            ->where('class_calculate', $method_t_coli)
                            ->where('tanggal_terima', $tgl_terima)
                            ->calculate();
                    }
                }

				//PARAM AUTO APPROVE
				$paramAutoApprove = [
					'Bau', 'Angka Bau', 'Angka Bau (NA)', 'AOX',
					'Kekeruhan', 'Kekeruhan (APHA-B-23-NA)', 'Kekeruhan (APHA-B-23)',
					'Kekeruhan (IKM-SP-NA)', 'Kekeruhan (IKM-SP)',
					'TDS', 'TDS (APHA-C-23-NA)', 'TDS (APHA-C-23)',
					'TDS (IKM-KM-NA)', 'TDS (IKM-KM)',
					'DO', 'DO (C-03-NA)', 'DO (C-03)', 'DO (G-03-NA)', 'DO (G-03)',
					'DHL', 'DTL'
				];

				if (in_array($request->parameter, $paramAutoApprove)) {
					$data->is_approved = 1;
					$data->approved_by = 'System'; 
					$data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
					$data->save();
				}

				DB::commit();
				return (object)[
					'message'=> 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];
			}
		}catch (\Exception $th) {
			DB::rollBack();
			return (object) [
				'message' => 'Gagal input parameter: '.$th->getMessage(),
				'file' => $th->getFile(),
				'line' => $th->getLine(),
				'status' => 401
			];
		}
	}

	public function HelperDebuPersonal($request, $stp, $order_detail) {
		$data_parameter = Parameter::where('nama_lab', $request->parameter)->where('id_kategori', $stp->category_id)->where('is_active', true)->first();
		$datalapangan = DataLapanganDebuPersonal::where('no_sampel', $request->no_sample)->get();
		$param = [633, 634, 222, 635]; //[PM 10 (Personil),PM 2.5 (Personil),DEBU (P8J), Karbon Hitam (8 jam)]
		// dd($datalapangan);
		if (!in_array($data_parameter->id, $param)) {
			return (object)[
				'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
				'status' => 404
			];
		} else {
			$wsling = DebuPersonalHeader::where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active', true)->first();
			// dd($wsling);
			if ($wsling) {
				return (object)[
					'message' => 'Parameter sudah diinput..!!',
					'status' => 401
				];
			}else{
				if (!$datalapangan->isEmpty()){

					try {
						$total_data = count($datalapangan);
						$avgFlow = []; // Total FLOW diambil dari rata-rata Shift L1-L8
						$avgWaktu = []; // Total waktu pengambilan sampel
						$avgTekananUdara = []; // Total tekanan udara
						$avgSuhu = []; // Total suhu
						if ($total_data > 0 || $total_data != '') {
							foreach ($datalapangan as $key => $value) {
								$dataflow = $value->flow;
								$datawaktu = $value->total_waktu;
								$datatekananudara = $value->tekanan_udara;
								$datasuhu = $value->suhu;

								// Tambahkan nilai flow, total_waktu, tekanan_udara, dan suhu ke dalam array
								$avgFlow[] = $dataflow;
								$avgWaktu[] = $datawaktu;
								$avgTekananUdara[] = $datatekananudara;
								$avgSuhu[] = $datasuhu;
							}

							// dd($avgFlow, $avgWaktu, $avgTekananUdara, $avgSuhu);
							// Menghitung total flow, total waktu, total tekanan udara, dan total suhu
							$total_flow = array_sum($avgFlow); // Jumlahkan semua nilai flow
							$total_waktu = array_sum($avgWaktu); // Jumlahkan semua nilai total_waktu
							$total_tekanan_udara = array_sum($avgTekananUdara); // Jumlahkan semua nilai tekanan_udara
							$total_suhu = array_sum($avgSuhu); // Jumlahkan semua nilai suhu

							// Jika ingin mendapatkan rata-rata, Anda bisa menghitungnya seperti ini
							$average_flow = $total_data > 0 ? number_format($total_flow / $total_data, 1) : 0; // Rata-rata FLOW diambil dari rata-rata Shift L1-L8
							$average_waktu = $total_data > 0 ? number_format($total_waktu, 1) : 0; //Total waktu pengambilan sampel
							$average_tekanan_udara = $total_data > 0 ? number_format($total_tekanan_udara / $total_data, 1) : 0; // Rata-rata tekanan udara
							$average_suhu = $total_data > 0 ? number_format($total_suhu / $total_data, 1) : 0; // Rata-rata suhu
						}
					} catch (\Exception $e) {
						return (object)[
							'message' => 'Error : ' . $e->getMessage(),
							'status' => 500
						];
					}
				}else{
					return (object)[
						'message' => 'Data Lapangan Belum Diinputkan Oleh Sampler',
						'status' => 404
					];
				}

				DB::beginTransaction();
				try {
					$inputan_analis = (object)[
						'w2' => $request->w2,
						'w1' => $request->w1,
						'b2' => $request->b2,
						'b1' => $request->b1
					];

					$header = new DebuPersonalHeader;
					$header->no_sampel = $request->no_sample;
					$header->parameter = $request->parameter;
					$header->template_stp = $request->id_stp;
					$header->id_parameter = $data_parameter->id;
					$header->note = $request->note;
					$header->tanggal_terima = $order_detail->tanggal_terima;
					$header->inputan_analis = json_encode($inputan_analis);
					$header->created_by = $this->karyawan;
					$header->created_at = Carbon::now()->format('Y-m-d H:i:s');
					$header->save();

					$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
					if (!$functionObj) {
						return (object)[
							'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
							'status' => 404
						];
					}
					$function = $functionObj->function;
					$data_parsing = $request->all();

					$data_parsing = (object)$data_parsing;
					$data_parsing->average_flow = $average_flow;
					$data_parsing->average_time = $average_waktu;
					$data_parsing->average_tekanan_udara = $average_tekanan_udara;
					$data_parsing->average_suhu = $average_suhu;
					$data_parsing->tanggal_terima = $order_detail->tanggal_terima;
					// dd($data_parsing);
					$data_kalkulasi = AnalystFormula::where('function', $function)
						->where('data', (object)$data_parsing)
						->where('id_parameter', $data_parameter->id)
						->process();

					if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
						return (object)[
							'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
							'status' => 404
						];
					}

					$data_kalkulasi['debu_personal_header_id'] = $header->id;
					$data_kalkulasi['no_sampel'] = $request->no_sample;
					$data_kalkulasi['created_by'] = $this->karyawan;
					$satuan = $data_kalkulasi['satuan'];
					unset($data_kalkulasi['satuan']);
					WsValueLingkungan::create($data_kalkulasi);

					$data_udara = array();
					$data_udara['id_debu_personal_header'] = $header->id;
					$data_udara['no_sampel'] = $request->no_sample;
					$data_udara['hasil16'] = $data_kalkulasi['C15'];
					$data_udara['hasil17'] = $data_kalkulasi['C16'];
					$data_udara['satuan'] = $satuan;
					WsValueUdara::create($data_udara);

					DB::commit();
					return (object)[
						'message' => 'Value Parameter berhasil disimpan.!',
						'par' => $request->parameter,
						'status' => 200
					];
				} catch (\Exception $e) {
					DB::rollback();
					return (object)[
						'message' => 'Error : ' . $e->getMessage(),
						'status' => 500
					];
				}
			}
		}
	}

	public function HelperLingkungan($request, $stp, $datlapanganh, $datlapangank, $datlapanganV)
	{
		// dd($request->all());
		$wsling = LingkunganHeader::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active', true)->first();
		if ($wsling) {
			return (object)[
				'message' => 'Parameter sudah diinput..!!',
				'status' => 403
			];
		} else {
			$parame = $request->parameter;
			$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori', $stp->category_id)->where('is_active', true)->first();
            $tipe_data = null;
            $isO3 = strpos($data_parameter->nama_lab, 'O3') !== false;

            // dd($datot);
            $rerata = [];
            $durasi = [];
            $tekanan_u = [];
            $suhu = [];
            $Qs = [];

            // O3 Kasus Khusus, Bagi 2 Bjir
            $rerataO3 = [];
            $durasiO3 = [];
            $tekanan_uO3 = [];
            $suhuO3 = [];
            $QsO3 = [];
            $ks_all = [];
            $kb_all = [];

			if ($datlapanganh != null || $datlapangank != null || $datlapanganV != null) {
				// dd($data_parameter);
				// if ($request->id_stp == 14) {
				// 	$parame = 'TSP';
				// }
				$lingHidup = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->where('parameter', $parame)->get();
				$lingKerja = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('parameter', $parame)->get();
				$lingVolatile = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)->where('parameter', $parame)->get();
				// dd($lingHidup, $lingKerja, $lingVolatile,$parame);
				if (!$lingHidup->isEmpty() || !$lingKerja->isEmpty() || !$lingVolatile->isEmpty()) {

					try {
						$datapangan = '';
						if (count($lingHidup) > 0) {
							$datapangan = $lingHidup;
                            $tipe_data = 'ambient';
						}
						if (count($lingKerja) > 0) {
                            $datapangan = $lingKerja;
                            $tipe_data = 'ulk';
						}
						if (count($lingVolatile) > 0) {
                            $datapangan = $lingVolatile;
                            $tipe_data = 'ulk';
						}
						// dd($datapangan);
						if ($datapangan != '') {
							$datot = count($datapangan);
						} else {
							$datot = '';
						}

						// dd($ks_all, $kb_all);
						$nilQs = '';
						if ($datot > 0 || $datot != '') {
							$parameterExplode = explode(' ', $data_parameter->nama_lab);
							$is8Jam = count($parameterExplode) > 1 ? strpos($parameterExplode[1], '8J') !== false : false;
							foreach ($datapangan as $keye => $vale) {
								$absorbansi = !is_null($vale->absorbansi) ? json_decode($vale->absorbansi) : null;
								$dat = json_decode($vale->pengukuran);
								// dd($absorbansi, $dat);
								// dd($vale);
								if ($isO3) {
									$durasii = [[], []];
									$flow = [[], []];
									// dump($absorbansi->{"data-4"});
									if (!is_null($absorbansi)) {
										$sample_penjerap_1 = [$absorbansi->{"data-1"}, $absorbansi->{"data-2"}, $absorbansi->{"data-3"}];
										$sample_penjerap_2 = [$absorbansi->{"data-4"}, $absorbansi->{"data-5"}, $absorbansi->{"data-6"}];
										$blanko_penjerap_1 = $absorbansi->blanko;
										$blanko_penjerap_2 = $absorbansi->blanko2;
										$ks = [array_sum($sample_penjerap_1) / count($sample_penjerap_1), array_sum($sample_penjerap_2) / count($sample_penjerap_2)];
										$kb = [$blanko_penjerap_1, $blanko_penjerap_2];
										// dd($ks, $kb);
										array_push($ks_all, $ks);
										array_push($kb_all, $kb);
									}
									$i = 0;
									foreach ($dat as $key => $val) {
										if ($key == 'Durasi' || $key == 'Durasi 2') {
											$formt = (int) str_replace(" menit", "", $val);
											if ($i == 0) {
												array_push($durasii[$i], $formt);
												$i++;
											} else {
												array_push($durasii[$i], $formt);
											}
										} else {
											array_push($flow[$i], $val);
										}
									}
									// dd($flow);
									$avg_flow = array_map(function ($item) use ($vale, &$QsO3, &$rerataO3, &$keye) {
										$avg = array_sum($item) / count($item);
										$Q0 = $avg * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);
										$Q0 = str_replace(",", "", number_format($Q0, 4));
										$QsO3[$keye][] =  (float) $Q0;
										$rerataO3[$keye][] =  $avg;
										return $avg;
									}, $flow);

									$tekanan_uO3[] =  $vale->tekanan_udara;
									$suhuO3[] =  $vale->suhu;

									$avg_durasi = array_map(function ($item) use ($vale, &$durasiO3, &$keye) {
										$avg = array_sum($item) / count($item);
										$durasiO3[$keye][] =  $avg;
										return $avg;
									}, $durasii);
								} else {
									$durasii = [];
									$flow = [];
									if (!is_null($absorbansi)) {
										$sample_penjerap_1 = [$absorbansi->{"data-1"}, $absorbansi->{"data-2"}, $absorbansi->{"data-3"}];
										$blanko_penjerap_1 = $absorbansi->blanko;
										$ks = array_sum($sample_penjerap_1) / count($sample_penjerap_1);
										$kb = $blanko_penjerap_1;
										array_push($ks_all, $ks);
										array_push($kb_all, $kb);
									}
									foreach ($dat as $key => $val) {
										if ($key == 'Durasi' || $key == 'Durasi 2') {
											$formt = (int) str_replace(" menit", "", $val);
											array_push($durasii, $formt);
										} else {
											array_push($flow, $val);
										}
									}
									$rera = array_sum($flow) / count($flow);
									// $Q0 = \str_replace(",", "", number_format($rera * ((298 * $vale->tekanan_u) / (($vale->suhu + 273) * 760) ** 1 / 2), 4));
									// $Q0 = \str_replace(",", "", number_format($rera * ((298 * $vale->tekanan_u) / (($vale->suhu + 273) * 760) ** 0.5), 4));

									// Menghitung Q0 sesuai rumus yang benar
									$Q0 = $rera * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);

									// Format hasil Q0 agar 4 desimal dan hilangkan koma pemisah ribuan
									$Q0 = str_replace(",", "", number_format($Q0, 4));

									$dur = array_sum($durasii);

									array_push($rerata, $rera);
									array_push($Qs, (float) $Q0);
									array_push($durasi, $dur);
									array_push($tekanan_u, $vale->tekanan_udara);
									array_push($suhu, $vale->suhu);
								}
							}

							if ($isO3) {
								if (!empty($QsO3)) {
									$index1Qs = array_column($QsO3, 0);
									$index2Qs = array_column($QsO3, 1);
									$nil1Qs = array_sum($index1Qs) / count($index1Qs);
									$nil2Qs = array_sum($index2Qs) / count($index2Qs);
								}
								// dd($nilQs);
								$index1Flow = array_column($rerataO3, 0);
								$index2Flow = array_column($rerataO3, 1);
								$rerata1Flow = \str_replace(",", "", number_format(array_sum($index1Flow) / count($index1Flow), 1));
								$rerata2Flow = \str_replace(",", "", number_format(array_sum($index2Flow) / count($index2Flow), 1));
								// if (count($durasiO3) == 1) {
								// 	$durasiFin = array_sum($durasiO3[0]) / count($durasiO3[0]);
								// } else {
								$index1Durasi = array_column($durasiO3, 0);
								$index2Durasi = array_column($durasiO3, 1);
								$rerata1Durasi = array_sum($index1Durasi) / count($index1Durasi);
								$rerata2Durasi = array_sum($index2Durasi) / count($index2Durasi);
								// }
								// $durasiO3 = array_push($durasiO3,$durasiO3[0]);
								$tekananFin = \str_replace(",", "", number_format(array_sum($tekanan_uO3) / $datot, 1));
								$suhuFin = \str_replace(",", "", number_format(array_sum($suhuO3) / $datot, 1));
								// dd($tekananFin, $suhuFin, $rerata1Flow, $rerata2Flow, $flow, $rerata1Durasi, $rerata2Durasi,$durasii, $nil1Qs, $nil2Qs, $QsO3);
							} else {
								if (!empty($Qs)) {
									$nilQs = array_sum($Qs) / $datot;
								}

								// dd($nilQs);
								// dd('RERATA', $rerata);
								$rerataFlow = \str_replace(",", "", number_format(array_sum($rerata) / $datot, 1));
								if (count($durasi) == 1) {
									$durasiFin = $durasi[0];
								} else {
									$durasiFin = array_sum($durasi) / $datot;
								}
								if ($request->parameter == 'Pb (24 Jam)' || $request->parameter == 'PM 2.5 (24 Jam)' || $request->parameter == 'PM 10 (24 Jam)' || $request->parameter == 'TSP (24 Jam)' || $data_parameter->id ==  306) {
									$l25 = '';
									if (count($lingHidup) > 0) {

										$l25 = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->where('parameter', $parame)->where('shift_pengambilan', 'L25')->first();
										if ($l25) {
											$waktu = explode(",", $l25->durasi_pengambilan);
											$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
											$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
											$durasiFin = ((int)$jam * 60) + (int)$menit;
										} else {
											$durasiFin = 24 * 60;
										}
									}
									if (count($lingKerja) > 0) {

										$l25 = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('parameter', $parame)->where('shift_pengambilan', 'L25')->first();
										// dd($l25);
										if ($l25) {
											$waktu = explode(",", $l25->durasi_pengujian);
											$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
											$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
											$durasiFin = ((int)$jam * 60) + (int)$menit;
										} else {
											$durasiFin = 24 * 60;
										}
										// dd('masukkk');
									}
									if (count($lingVolatile) > 0) {
										$l25 = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)->where('parameter', $parame)->where('shift_pengambilan', 'L25')->first();
										if ($l25) {
											$waktu = explode(",", $l25->durasi_pengujian);
											$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
											$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
											$durasiFin = ((int)$jam * 60) + (int)$menit;
										} else {
											$durasiFin = 24 * 60;
										}
									}
								}
								// dd($durasiFin);

								$tekananFin = \str_replace(",", "", number_format(array_sum($tekanan_u) / $datot, 1));
								$suhuFin = \str_replace(",", "", number_format(array_sum($suhu) / $datot, 1));
							}
						} else {
							return (object)[
								'message' => 'No sample tidak ada di lingkungan hidup atau lingkungan kerja.',
								'status' => 404
							];
						}
					} catch (\Exception $e) {
						// dd($e);
						return (object)[
							'message' => 'Error : ' . $e->getMessage(),
							'status' => 500,
							'line' => $e->getLine(),
							'file' => $e->getFile()
						];
					}
				} else {
					return (object)[
						'message' => 'No sample tidak ada pada data lingkungan hidup atau dan lingkungan kerja.',
						'status' => 404
					];
				}
			} else {
				return (object)[
					'message' => 'Data lapangan belum diinputkan oleh Sampler.',
					'status' => 404
				];
			}

            if (is_null($tipe_data)) {
                $tipe_data = 'ulk';
            }

			// dd($durasiFin, $tekananFin, $suhuFin, $nilQs, $datot, $rerataFlow);
			$check = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();

			if (!isset($check->id)) {
				return (object)[
					'message' => 'No Sample tidak ada.!!',
					'status' => 401
				];
			}
			$id_po = $check->id;
			$tgl_terima = $check->tanggal_terima;
			// Proses kalkulasi dengan AnalystFormula

			$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
			// dd($functionObj);
			if (!$functionObj) {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}
			$function = $functionObj->function;

            $ulk_ambient_parameter = [
                'Cl2' => [
                    'ambient' => 'LingkunganHidupCl2',
                    'ulk' => 'LingkunganKerjaCl2'
                ]
            ];

            if(isset($ulk_ambient_parameter[$request->parameter])) {
                $function = $ulk_ambient_parameter[$request->parameter][$tipe_data];
            }

			$data_parsing = $request->all();
			$data_parsing = (object) $data_parsing;
			$data_parsing->use_absorbansi = false;
			$data_parsing->tipe_data = $tipe_data;
			// dd($data_parsing);
			if (!$isO3) {
				$data_parsing->durasi = $durasiFin;
				$data_parsing->nilQs = $nilQs;
				$data_parsing->array_qs = $Qs;
				$data_parsing->data_total = $datot;
				$data_parsing->average_flow = $rerataFlow;
				$data_parsing->flow_array = $rerata;
				$data_parsing->durasi_array = $durasi;
			} else {
				$data_parsing->durasi = [$rerata1Durasi, $rerata2Durasi];
				$data_parsing->nilQs = [$nil1Qs, $nil2Qs];
				$data_parsing->average_flow = [$rerata1Flow, $rerata2Flow];
			}

            // dd($request->all());
			if ($isO3) {
				$data_parsing->ks = array_chunk(array_map('floatval', $request->ks), 2);
				$data_parsing->kb = array_chunk(array_map('floatval', $request->kb), 2);
			} elseif(isset($request->ks) && is_array($request->ks) && isset($request->kb) && is_array($request->kb)) {
				$data_parsing->ks = array_map('floatval', $request->ks);
				$data_parsing->kb = array_map('floatval', $request->kb);
			}

			// dd($data_parsing->ks);

			if (count($ks_all) > 0) {
				$data_parsing->use_absorbansi = true;
				$data_parsing->ks = $ks_all;
			}
			if (count($kb_all) > 0) {
				$data_parsing->kb = $kb_all;
			}
			$data_parsing->tekanan = $tekananFin;
			$data_parsing->suhu = $suhuFin;
            $data_parsing->suhu_array = $suhu;
            $data_parsing->tekanan_array = $tekanan_u;
			$data_parsing->tanggal_terima = $tgl_terima;
			// dd($data_parsing);
			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();

			if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}

			if(isset($data_kalkulasi['status']) == 'error'){
				return (object)[
					'message' => isset($data_kalkulasi['message']) ? $data_kalkulasi['message'] : null,
					'trace' => isset($data_kalkulasi['trace']) ? $data_kalkulasi['trace'] : null,
					'line' => isset($data_kalkulasi['line']) ? $data_kalkulasi['line'] : null,
					'status' => 500
				];
			}

			$saveShift = [246, 247, 248, 249, 289, 290, 291, 293, 294, 295, 296, 299, 300, 326, 327, 328, 329, 308, 307, 306];
			DB::beginTransaction();
			try {
				$data = new LingkunganHeader;
				$data->no_sampel = $request->no_sample;
				$data->parameter = $request->parameter;
				$data->template_stp = $request->id_stp;
				$data->id_parameter = $data_parameter->id;
				$data->use_absorbansi = $data_parsing->use_absorbansi;
				$data->is_approved = $data_parsing->use_absorbansi ? true : false;
				$data->note = $request->note;
				$data->tanggal_terima = $tgl_terima;
				$data->created_by = $this->karyawan;
				$data->created_at = Carbon::now()->format('Y-m-d H:i:s');
				$data->data_shift = null;
				if (in_array($data_parameter->id, $saveShift) || $request->id_stp == 13) {
					if($isO3){
                        $ks = array_chunk(array_map('floatval', $request->ks), 2);
                        $kb = array_chunk(array_map('floatval', $request->kb), 2);
                        $data_shift = array_map(function ($sample, $blanko) {
                            return (object) [
                                "sample" => number_format(array_sum($sample) / count($sample),4),
                                "blanko" => number_format(array_sum($blanko) / count($blanko),4)
                            ];
                        }, $ks, $kb);
                    }else if(is_array($request->ks) && is_array($request->kb)){
                        $data_shift = array_map(function ($sample, $blanko) {
                            return (object) [
                                "sample" => number_format($sample,4),
                                "blanko" => number_format($blanko,4)
                            ];
                        }, $request->ks, $request->kb);
                    }else{
						$data_shift = [(object)[
							'sample' => $request->ks,
							'blanko' => $request->kb,
						]];
					}
					$data->data_shift = count($data_shift) > 0 ? json_encode($data_shift) : null;
				}
                // dd(isset($data_kalkulasi['data_pershift']));
                if(isset($data_kalkulasi['data_pershift'])) $data->data_pershift = json_encode($data_kalkulasi['data_pershift']);
				$data->save();

				// dd($nilQs, $datot, $rerataFlow, $durasiFin, $po->id, $po->tgl_terima, $tekananFin, $suhuFin, $request, $this->karyawan, $par->id, $result);
				// dd($result);

                if (array_key_exists('data_pershift', $data_kalkulasi)) {
                    unset($data_kalkulasi['data_pershift']);
                }
				$data_udara['id_lingkungan_header'] = $data->id;
				$data_udara['no_sampel'] = $request->no_sample;
				$data_udara['hasil1']  = isset($data_kalkulasi['C'])   ? $data_kalkulasi['C']   : null;
                $data_udara['hasil2']  = isset($data_kalkulasi['C1'])  ? $data_kalkulasi['C1']  : null;
                $data_udara['hasil3']  = isset($data_kalkulasi['C2'])  ? $data_kalkulasi['C2']  : null;
                $data_udara['hasil4']  = isset($data_kalkulasi['C3'])  ? $data_kalkulasi['C3']  : null;
                $data_udara['hasil5']  = isset($data_kalkulasi['C4'])  ? $data_kalkulasi['C4']  : null;
                $data_udara['hasil6']  = isset($data_kalkulasi['C5'])  ? $data_kalkulasi['C5']  : null;
                $data_udara['hasil7']  = isset($data_kalkulasi['C6'])  ? $data_kalkulasi['C6']  : null;
                $data_udara['hasil8']  = isset($data_kalkulasi['C7'])  ? $data_kalkulasi['C7']  : null;
                $data_udara['hasil9']  = isset($data_kalkulasi['C8'])  ? $data_kalkulasi['C8']  : null;
                $data_udara['hasil10'] = isset($data_kalkulasi['C9'])  ? $data_kalkulasi['C9']  : null;
                $data_udara['hasil11'] = isset($data_kalkulasi['C10']) ? $data_kalkulasi['C10'] : null;
                $data_udara['hasil12'] = isset($data_kalkulasi['C11']) ? $data_kalkulasi['C11'] : null;
                $data_udara['hasil13'] = isset($data_kalkulasi['C12']) ? $data_kalkulasi['C12'] : null;
                $data_udara['hasil14'] = isset($data_kalkulasi['C13']) ? $data_kalkulasi['C13'] : null;
                $data_udara['hasil15'] = isset($data_kalkulasi['C14']) ? $data_kalkulasi['C14'] : null;
                $data_udara['hasil16'] = isset($data_kalkulasi['C15']) ? $data_kalkulasi['C15'] : null;
                $data_udara['hasil17'] = isset($data_kalkulasi['C16']) ? $data_kalkulasi['C16'] : null;
				$data_udara['satuan'] = $data_kalkulasi['satuan'];
                // dd($data_udara);
				WsValueUdara::create($data_udara);

				$data_kalkulasi['lingkungan_header_id'] = $data->id;
				$data_kalkulasi['tanggal_terima'] = $tgl_terima;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
				// unset($data_kalkulasi['id_lingkungan_header']);
				unset($data_kalkulasi['satuan']);
				// dd($data_kalkulasi);
				WsValueLingkungan::create($data_kalkulasi);

				// dd('berhasil');
				DB::commit();

				return (object)[
					'message' => 'Value Parameter berhasil disimpan.',
					'par' => $request->parameter,
					'status' => 200
				];
			} catch (\Exception $e) {
				DB::rollback();
				return (object)[
					'message' => 'Error : ' . $e->getMessage(),
					'line' => $e->getLine(),
					'file' => $e->getFile(),
					'status' => 500,
                    'trace' => $e->getTrace()
				];
			}
		}
	}

	public function HelperEmisiCl2($request,$stp, $order_detail, $data_lapangan){
		$data_ci2_json = json_decode($data_lapangan->CI2);
		$data_ci_toArray = explode(";", $data_ci2_json[0]);
		$nilaiDgm = null;
		$tekanan_meteran = null;
		$durasi = null;
		foreach ($data_ci_toArray as $item) {
			if (strpos($item, "Volume") !== false) {
				// Menghilangkan spasi di sekitar string
				$item = str_replace(' ', '', $item);
				// Memecah string berdasarkan delimiter ":"
				$volumeData = explode(":", $item);
				$nilaiDgm = $volumeData[1];
			}else if(strpos($item, "Tekanan") !== false){
				$item = str_replace(' ', '', $item);
				$tekananData = explode(":", $item);
				$tekanan_meteran = ($tekananData[1] !== "-" && isset($tekananData[1])) ? $tekananData[1] : 0;
			}else if(strpos($item, "Durasi") !== false){
				$item = str_replace(' ', '', $item);
				$durasiData = explode(":", $item);
				$durasi = ($durasiData[1] !== "-" && isset($durasiData[1])) ? $durasiData[1] : 0;
			}
		}
		// dd($datlapangan);
		$datL_suhu = $data_lapangan->suhu;
		$tekanan_udara = $data_lapangan->tekanan_udara;
		$kons_klorin = $request->konsentrasi_klorin;
		$volumeSample = $request->volume_sample;
		$kons_blanko = $request->konsentrasi_blanko;
		$note = $request->note;

		$tekananAir = number_format(self::KonversiTekananUapAir($datL_suhu),4); //udah mmHg

		$parame = $request->parameter;
		$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

		$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
		if (!$functionObj) {
			return (object)[
				'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
				'status' => 404
			];
		}
		$function = $functionObj->function;
		$data_parsing = $request->all();
		$data_parsing = (object) $data_parsing;

		$data_parsing->suhu = $datL_suhu;
		$data_parsing->tekanan_udara = $tekanan_udara;
		$data_parsing->konsentrasi_klorin = $kons_klorin;
		$data_parsing->volume_sample = $volumeSample;
		$data_parsing->konsentrasi_blanko = $kons_blanko;
		$data_parsing->note = $note;
		$data_parsing->nilaiDgm = $nilaiDgm;
		$data_parsing->tekanan_meteran = $tekanan_meteran;
		$data_parsing->tekanan_air = $tekananAir;
		$data_parsing->durasi = $durasi;
		$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

		$data_kalkulasi = AnalystFormula::where('function', $function)
			->where('data', $data_parsing)
			->where('id_parameter', $data_parameter->id)
			->process();


		if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
			return (object)[
				'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
				'status' => 404
			];
		}

		// dump($tekananAir);
		DB::beginTransaction();
		try {
			$dataHeader = new EmisiCerobongHeader;
			$dataHeader->no_sampel = $request->no_sample;
			$dataHeader->parameter = $request->parameter;
			$dataHeader->template_stp = $request->id_stp;
			$dataHeader->id_parameter = $data_parameter->id;
			$dataHeader->note = $request->note;
			$dataHeader->tanggal_terima = $order_detail->tanggal_terima;
			$dataHeader->created_by = $this->karyawan;
			$dataHeader->created_at = Carbon::now()->format('Y-m-d H:i:s');
			$dataHeader->save();

			$data_kalkulasi['id_emisi_cerobong_header'] = $dataHeader->id;
			$data_kalkulasi['no_sampel'] = $request->no_sample;
			$data_kalkulasi['created_by'] = $this->karyawan;
			WsValueEmisiCerobong::create($data_kalkulasi);

            // dd($data_kalkulasi);
			DB::commit();
			return (object)[
				'message' => 'Value Parameter berhasil disimpan.!',
				'par' => $request->parameter,
				'status' => 200
			];
		} catch (\Exception $th) {
			DB::rollback();
			return (object)[
				'line' => $th->getLine(),
				'message' => 'Error : ' . $th->getMessage(),
				'status' => 500
			];
		}
	}

	public function HelperEmisiCerobong($request, $stp, $order_detail, $data_lapangan)
	{
		DB::beginTransaction();
		try {
			if ($data_lapangan) {
				$tekanan = (float) $data_lapangan->tekanan_udara;
				$t_flue = (float) $data_lapangan->T_Flue;
				$suhu = (float) $data_lapangan->suhu;
				$nil_pv = self::penentuanPv($suhu);
				$status_par = $request->parameter;

				if ($request->parameter == 'HF') {
					$dat = json_decode($data_lapangan->HF);
				} else if ($request->parameter == 'NH3') {
					$dat = json_decode($data_lapangan->NH3);
				} else if ($request->parameter == 'HCl') {
					$dat = json_decode($data_lapangan->HCI);
				} else if ($request->parameter == 'H2S') {
                    $dat = json_decode($data_lapangan->H2S);
                } else if (in_array($request->parameter, [
					'Debu', 'Partikulat',
					'As', 'Cd', 'Co', 'Cr', 'Cu', 'Hg', 'Mn', 'Pb',
					'Sb', 'Se', 'Tl', 'Zn', 'Sn', 'Al', 'Ba', 'Be', 'Bi',
                    'Debu (P)'
				])) {
					$dat = json_decode($data_lapangan->partikulat);
					$status_par = 'Partikulat';
				}

				if ($data_lapangan->tipe == '1') {
					if($dat != null) {
						if (is_string($dat[0])) {
						$nil_dry = explode("; ", $dat[0]);
						$nil_dry = explode(":", $nil_dry[4]);
						$nil_dry = str_replace(" ", "", $nil_dry[1]);
						// dd($nil_dry);
						$tekanan_dry = (float) $nil_dry;

						$nil_vol = explode("; ", $dat[0]);
						$nil_vol = explode(":", $nil_vol[3]);
						$nil_vol = str_replace(" ", "", $nil_vol[1]);
						$volume_dry = (float) $nil_vol;

						$dura = explode("; ", $dat[0]);
						$dura = explode(":", $dura[2]);
						$dura = str_replace(" ", "", $dura[1]);
						$durasi_dry = (float) $dura;

						$awal = explode("; ", $dat[0]);
						$awal = explode(":", $awal[0]);
						$awal = str_replace(" ", "", $awal[1]);
						$awal_dry = (float) $awal;

						$akhir = explode("; ", $dat[0]);
						$akhir = explode(":", $akhir[1]);
						$akhir = str_replace(" ", "", $akhir[1]);
						$akhir_dry = (float) $akhir;
						$flow = ($akhir_dry + $awal_dry) / 2; //04-03-2025
						} else if (is_object($dat[0])) {
							$tekanan_dry = (float) $dat[0]->tekanan;
							$volume_dry = (float) $dat[0]->volume;
							$durasi_dry = (float) $dat[0]->durasi;
							$awal_dry = (float) $dat[0]->flow_awal;
							$akhir_dry = (float) $dat[0]->flow_akhir;
							$flow = ($akhir_dry + $awal_dry) / 2;
						}
						// $flow = $akhir_dry + $awal_dry / 2;
					}else {
						return (object)[
							'message' => 'Tidak ditemukan pada data lapangan parameter : ' . $status_par . '',
							'status' => 401
						];
					}
				} else if ($data_lapangan->tipe == '2') {
					$tekanan_dry = 0;
					$volume_dry = 0;
					$durasi_dry = 0;
					$awal_dry = 0;
					$akhir_dry = 0;
					$flow = 0;
				}
			} else {
				$tekanan_dry = 0;
				$volume_dry = 0;
				$durasi_dry = 0;
				$awal_dry = 0;
				$akhir_dry = 0;
				$flow = 0;
				$tekanan = 0;
				$t_flue = 0;
				$suhu = 0;
				$nil_pv = 0;
			}

			$parame = $request->parameter;
			$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori', $stp->category_id)->where('is_active', true)->first();

			$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
			if (!$functionObj) {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}
			$function = $functionObj->function;
			$data_parsing = $request->all();
			$data_parsing = (object)$data_parsing;

			$data_parsing->tekanan_dry = $tekanan_dry;
			$data_parsing->volume_dry = $volume_dry;
			$data_parsing->durasi_dry = $durasi_dry;
			$data_parsing->awal_dry = $awal_dry;
			$data_parsing->akhir_dry = $akhir_dry;
			$data_parsing->flow = $flow;
			$data_parsing->tekanan = $tekanan;
			$data_parsing->t_flue = $t_flue;
			$data_parsing->suhu = $suhu;
			$data_parsing->nil_pv = $nil_pv;
			$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();

			if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}
			// dd($stp->id);
			$data_analis = array_filter((array) $request->all(), function ($value, $key) {
                $exlude = ['jenis_pengujian', 'note','no_sample', 'parameter', 'id_stp'];
                return !in_array($key, $exlude);
            }, ARRAY_FILTER_USE_BOTH);

            $formatted_data_analis = [];
            foreach ($data_analis as $key => $value) {
                if ($key === 'ks') {
                    $formatted_data_analis['k_sampel'] = $value;
                } elseif ($key === 'kb') {
                    $formatted_data_analis['k_blanko'] = $value;
                } elseif ($key === 'vs') {
                    $formatted_data_analis['volume_sampel'] = $value;
                } elseif ($key === 'vtp') {
                    $formatted_data_analis['volume_total_pengeceran'] = $value;
                } else {
                    $formatted_data_analis[$key] = $value;
                }
            }


			$data = new EmisiCerobongHeader;
			$data->no_sampel = $request->no_sample;
			$data->parameter = $request->parameter;
			$data->template_stp = $request->id_stp;
			$data->id_parameter = $data_parameter->id;
			$data->note = $request->note;
			$data->tanggal_terima = $order_detail->tanggal_terima;
			$data->created_by = $this->karyawan;
			$data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->data_analis = json_encode((object) $formatted_data_analis);
			$data->save();

			// dd($result);
			$data_kalkulasi['id_emisi_cerobong_header'] = $data->id;
			$data_kalkulasi['no_sampel'] = $request->no_sample;
			$data_kalkulasi['created_by'] = $this->karyawan;
			WsValueEmisiCerobong::create($data_kalkulasi);

			DB::commit();
			return (object)[
				'message' => 'Value Parameter berhasil disimpan.!',
				'par' => $request->parameter,
				'status' => 200
			];
		} catch (\Exception $e) {
			DB::rollBack();
			return (object)[
				'message' => 'Gagal input data: '.$e->getMessage(),
				'status' => 500,
				'line' => $e->getLine(),
				'file' => $e->getFile()
			];
		}
	}

    public function HelperEmisiGravimetri($request, $stp, $order_detail, $data_lapangan){
        DB::beginTransaction();
		try {
			if($data_lapangan){
                $volume_gas = $data_lapangan->v_gas;
            }else{
                return (object)[
                    'message' => 'Data Lapangan Tidak Ditemukan',
                    'status' => 404
                ];
            }

			$parame = $request->parameter;
			$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori', $stp->category_id)->where('is_active', true)->first();

			$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
			if (!$functionObj) {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}
			$function = $functionObj->function;
			$data_parsing = $request->all();
			$data_parsing = (object)$data_parsing;
            $data_parsing->volume_gas = $volume_gas;
			$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();

			if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}

            $data_analis = array_filter((array) $request->all(), function ($value, $key) {
                $exlude = ['jenis_pengujian', 'note','no_sample', 'parameter', 'id_stp','tgl'];
                return !in_array($key, $exlude);
            }, ARRAY_FILTER_USE_BOTH);

            $formatted_data_analis = [];
            foreach ($data_analis as $key => $value) {
                if ($key === 'ks') {
                    $formatted_data_analis['k_sampel'] = $value;
                } elseif ($key === 'kb') {
                    $formatted_data_analis['k_blanko'] = $value;
                } elseif ($key === 'vs') {
                    $formatted_data_analis['volume_sampel'] = $value;
                } elseif ($key === 'vtp') {
                    $formatted_data_analis['volume_total_pengeceran'] = $value;
                } else {
                    $formatted_data_analis[$key] = $value;
                }
            }

			if(isset($data_kalkulasi['massa_total_partikulat'])){
				$formatted_data_analis['massa_total_partikulat'] = $data_kalkulasi['massa_total_partikulat'];
				unset($data_kalkulasi['massa_total_partikulat']);
			}
			if(isset($data_kalkulasi['vstd'])){
				$formatted_data_analis['vstd'] = $data_kalkulasi['vstd'];
				unset($data_kalkulasi['vstd']);
			}

			$data = new EmisiCerobongHeader;
			$data->no_sampel = $request->no_sample;
			$data->parameter = $request->parameter;
			$data->template_stp = $request->id_stp;
			$data->id_parameter = $data_parameter->id;
			$data->note = $request->note;
			$data->tanggal_terima = $order_detail->tanggal_terima;
			$data->created_by = $this->karyawan;
			$data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->data_analis = json_encode((object) $formatted_data_analis);
			$data->save();

			// dd($result);
			$data_kalkulasi['id_emisi_cerobong_header'] = $data->id;
			$data_kalkulasi['no_sampel'] = $request->no_sample;
			$data_kalkulasi['created_by'] = $this->karyawan;
			WsValueEmisiCerobong::create($data_kalkulasi);

			DB::commit();
			return (object)[
				'message' => 'Value Parameter berhasil disimpan.!',
				'par' => $request->parameter,
				'status' => 200
			];
		} catch (\Exception $e) {
			DB::rollBack();
			return (object)[
				'message' => 'Gagal input data: '.$e->getMessage(),
				'status' => 500,
				'line' => $e->getLine(),
				'file' => $e->getFile()
			];
		}
    }

	public function HelperDustFall($request, $stp, $order_detail, $header){
		if($header) {
			return (object)[
				'message' => 'Parameter sudah diinput..!!',
				'status' => 401
			];
		}else{
			$id_po = '';
			$tgl_terima = '';

			if(is_null($order_detail)){
				return (object)[
					'message'=> 'Parameter'. $request->parameter.' tidak ditemukan pada no sampel'. $request->no_sample.'',
					'status' => 404
				];
			}

			$data_lapangan = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->get();

			if($data_lapangan->isEmpty()){
				return (object)[
					'message'=> 'Data Lapangan tidak ditemukan untuk parameter : '.$request->parameter.'',
					'status' => 404
				];
			}else if($data_lapangan->count() < 2){
				return (object)[
					'message'=> 'Data Lapangan masih kurang untuk melakukan perhitungan hasil uji pada parameter : '.$request->parameter.'',
					'status' => 404
				];
			}

			$data_lapangan = $data_lapangan->toArray();
			$pemasangan = json_decode($data_lapangan[0]['pengukuran']);
			$pengambilan = json_decode($data_lapangan[1]['pengukuran']);

			$luas_botol_raw = $pemasangan->luas_botol;

			$luas_botol = (float) preg_replace('/[^0-9.]/', '', $luas_botol_raw);

			
			$start = Carbon::parse($pemasangan->tanggal_pemasangan . ' ' . $data_lapangan[0]['waktu_pengukuran']);
			$end   = Carbon::parse($pengambilan->tanggal_selesai . ' ' . $data_lapangan[1]['waktu_pengukuran']);
			$jam = $start->diffInHours($end, true);   // selisih dalam jam
			$selisih_hari = round($jam / 24, 1);          // konversi ke jam desimal

			$data_parameter = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
			$id_po = $order_detail->id;
			$tgl_terima = $order_detail->tanggal_terima;

			$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
			if (!$functionObj) {
				return (object)[
					'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					'status' => 404
				];
			}
			$function = $functionObj->function;
			$data_parsing = $request->all();
			$data_parsing = (object)$data_parsing;
			$data_parsing->luas_botol = $luas_botol;
			$data_parsing->selisih_hari = $selisih_hari;
			$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();

			if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					'status' => 404
				];
			}

			DB::beginTransaction();
			try {
				$inputan_analis = (object)[
					'berat_kosong_1' => $request->bk1,
					'berat_kosong_2' => $request->bk2,
					'berat_kosong_dengan_isi_1' => $request->bki1,
					'berat_kosong_dengan_isi_2' => $request->bki2,
					'volume_filtrat' => $request->vl,
					'luas_botol' => $data_parsing->luas_botol,
					'selisih_hari' => $selisih_hari
				];

				$header                 = new DustFallHeader();
				$header->no_sampel      = $request->no_sample;
				$header->parameter      = $request->parameter;
				$header->template_stp   = $request->id_stp;
				$header->id_parameter   = $data_parameter->id;
				$header->note           = $request->note;
				$header->tanggal_terima = $tgl_terima;
				$header->is_active      = true;
				$header->created_by     = $this->karyawan;
				$header->inputan_analis = json_encode($inputan_analis);
				$header->created_at     = Carbon::now()->format('Y-m-d H:i:s');
				$header->save();

				$data_lingkungan = $data_kalkulasi;
				$data_lingkungan['dustfall_header_id'] = $header->id;
				$data_lingkungan['no_sampel'] = $request->no_sample;
				$data_lingkungan['created_by'] = $this->karyawan;
				$data_lingkungan['C5'] = $data_kalkulasi['hasil'];
				unset($data_lingkungan['satuan']);
				unset($data_lingkungan['hasil']);
				WsValueLingkungan::create($data_lingkungan);

				$data_udara = array();
				$data_udara['id_dustfall_header']   = $header->id;
				$data_udara['no_sampel']            = $request->no_sample;
				$data_udara['hasil6']               = $data_kalkulasi['hasil'];
				$data_udara['satuan']               = $data_kalkulasi['satuan'];
				WsValueUdara::create($data_udara);

				DB::commit();
				return (object)[
					'message' => 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];
			} catch (\Exception $e) {
				DB::rollBack();
				return (object)[
					'message' => 'Gagal input data: '.$e->getMessage(),
					'status' => 500,
					'line' => $e->getLine(),
					'file' => $e->getFile()
				];
			}
		}
	}

	public function HelperMikrobiologi($request, $stp, $order_detail){
		$fdl = DetailMicrobiologi::where('no_sampel', $request->no_sample)
			->where('is_active', true)
			->where('parameter', $request->parameter)
			->get();

        $swab = null;
        // $swab_parameter = [
        //     'E.Coli (Swab Test)','Enterobacteriaceae (Swab Test)','Bacillus C (Swab Test)','Kapang Khamir (Swab Test)','Listeria M (Swab Test)',
        //     'Pseu Aeruginosa (Swab Test)','S.Aureus (Swab Test)','Salmonella (Swab Test)','Shigella Sp. (Swab Test)','T.Coli (Swab Test)',
        //     'Total Kuman (Swab Test)','TPC (Swab Test)','Vibrio Ch (Swab Test)','V. cholerae (SWAB)','Vibrio sp (SWAB)','B. cereus (SWAB)',
        //     'E. coli (SWAB)','Enterobacteriaceae (SWAB)','Kapang & Khamir (SWAB)','L. monocytogenes (SWAB)'
        // ];
		
        // if(in_array($request->parameter, $swab_parameter)){
		if(str_contains($request->parameter, 'Swab') || str_contains($request->parameter, 'SWAB')){
            $swab = DataLapanganSwab::where('no_sampel', $request->no_sample)->first();
        }

        $data_parameter = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

		$header = MicrobioHeader::where('no_sampel', $request->no_sample)
			->where('parameter', $request->parameter)
			->where('is_active', true)
			->first();

		if ($header) {
			return (object)[
				'message' => 'Parameter sudah diinput..!!',
				'status' => 401
			];
		}

		if (($fdl && count($fdl) > 0) || !is_null($swab)) { // Periksa apakah $fdl tidak null
			try {
				// Ambil data suhu, tekanan, dan kelembaban
				if(count($fdl) > 0){
					$suhu = [];
					$tekanan = [];
					$kelembaban = [];
					$volume = [];
					$durasi = [];
					$flowRate = [];
					foreach ($fdl as $data) {
						$suhu[] = $data->suhu ?? $swab->suhu;
						$tekanan[] = $data->tekanan_udara ?? $swab->tekanan_udara;
						$kelembaban[] = $data->kelembapan ?? $swab->kelembapan;
						$pengukuran = json_decode($data->pengukuran);
                        $flow = (float) ($pengukuran->{"Flow Rate"} ?? null);
						$flowRate[] = $flow;
                        $durasi_value = (float) preg_replace('/\D/', '', $pengukuran->Durasi) ?? null;
						$durasi[] = $durasi_value;
						$volume[] = ($flow * $durasi_value) / 1000;
					}
				}else{
					$suhu = $swab->suhu ?? 0;
					$tekanan = $swab->tekanan_udara ?? 0;
					$kelembaban = $swab->kelembapan ?? 0;
					$luas = $swab->luas_area_swab ?? 0;
					$volume = $request->volume ?? 0;
					$durasi = [];
					$flowRate = [];
				}

				// Decode JSON di dalam pengukuran
                // if(isset($fdl->pengukuran)){
                //     $pengukuran = json_decode($fdl->pengukuran);

                //     // Ambil nilai Flow Rate dan Durasi
                //     $flowRate = (float) ($pengukuran->{"Flow Rate"} ?? null);
                //     $durasi = (float) preg_replace('/\D/', '', $pengukuran->Durasi) ?? null;

                //     $volume = ($flowRate * $durasi) / 1000;

                // }else{
                //     $luas = $swab->luas_area_swab ?? 0;
                // }
			} catch (\Exception $e) {
				return (object)[
					'message' => 'Error: ' . $e->getMessage(),
					'status' => 404
				];
			}

			// Mulai transaksi
			DB::beginTransaction();
			try {
				$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
				if (!$functionObj) {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}
				$function = $functionObj->function;
				$data_parsing = $request->all();
				$data_parsing = (object) $data_parsing;

				$data_parsing->suhu = $suhu;
				$data_parsing->tekanan = $tekanan;
				$data_parsing->kelembaban = $kelembaban;
				$data_parsing->luas = $luas ?? null;
				$data_parsing->flow_rate = $flowRate ?? [];
				$data_parsing->durasi = $durasi ?? [];
				$data_parsing->volume = $volume ?? [];
				$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

				$data_kalkulasi = AnalystFormula::where('function', $function)
					->where('data', $data_parsing)
					->where('id_parameter', $data_parameter->id)
					->process();


				if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}
				// Simpan data ke tabel Microbioheader
				$header = new MicrobioHeader();
				$header->no_sampel = $request->no_sample;
				$header->parameter = $request->parameter;
				$header->template_stp = $request->id_stp;
				$header->id_parameter = $data_parameter->id;
				$header->note = $request->note;
				$header->tanggal_terima = $order_detail->tanggal_terima;
				$header->volume = is_array($volume) ? (count($volume) > 0 ? array_sum($volume) / count($volume) : null) : $volume;
				$header->flow = count($flowRate) > 0 ? array_sum($flowRate) / count($flowRate) : null;
				$header->durasi = count($durasi) > 0 ? array_sum($durasi) / count($durasi) : null;
				$data_shift = null;
                $volume_shift = null;
				$data_pershift = null;
				if(count($fdl) > 1){
					$data_shift = json_encode($request->jumlah_coloni);
					$volume_shift = json_encode($volume);
				}elseif(count($fdl) == 1){
					$data_shift = json_encode($request->jumlah_coloni);
				}
                if(isset($request->jumlah_coloni)){
                    $data_pershift = isset($data_kalkulasi['data_pershift']) ? json_encode($data_kalkulasi['data_pershift']) : null;
                }
				if(!is_null($swab)){
					$header->luas = $luas;
					$header->jumlah_mikroba = $request->jumlah_mikroba;
					$header->fp = isset($request->fp) ? $request->fp : $request->jumlah_pengencer;
				}
				$header->data_shift = $data_shift;
				$header->data_pershift = $data_pershift;
                $header->volume_shift = $volume_shift;
				$header->created_by = $this->karyawan;
				$header->created_at = Carbon::now();
				$header->save();

				// $data_kalkulasi['id_microbio_header'] = $header->id;
				// $data_kalkulasi['no_sampel'] = $request->no_sample;
				// $data_kalkulasi['created_by'] = $this->karyawan;
				// // Simpan hasil ke tabel ws_value_microbio
				// WsValueMicrobio::create($data_kalkulasi);

				$data_udara = array();
				$data_udara['id_microbiologi_header'] = $header->id;
				$data_udara['no_sampel'] = $request->no_sample;
				if(count($fdl) > 0){
					$data_udara['hasil9'] = $data_kalkulasi['hasil'];
				}else{
					$data_udara['hasil10'] = isset($data_kalkulasi['hasil']) ? $data_kalkulasi['hasil'] : null;
					$data_udara['hasil11'] = isset($data_kalkulasi['hasil2']) ? $data_kalkulasi['hasil2'] : null;
					$data_udara['hasil13'] = isset($data_kalkulasi['hasil3']) ? $data_kalkulasi['hasil3'] : null;
					$data_udara['hasil14'] = isset($data_kalkulasi['hasil4']) ? $data_kalkulasi['hasil4'] : null;
					$data_udara['hasil19'] = isset($data_kalkulasi['hasil5']) ? $data_kalkulasi['hasil5'] : null;
				}
				WsValueUdara::create($data_udara);

				// Commit transaksi jika semua berhasil
				DB::commit();

				return (object)[
					'message' => 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];

			} catch (\Exception $e) {
				// Rollback transaksi jika terjadi kesalahan
				DB::rollBack();

				return (object)[
					'message' => 'Error: ' . $e->getMessage(),
					'status' => 500,
					'line' => $e->getLine(),
					'file' => $e->getFile()
				];
			}
		} else {
			return (object)[
				'message' => 'Data lapangan tidak ditemukan untuk sample yang diberikan.',
				'status' => 404
			];
		}
	}

	public function HelperSwabTest($request, $stp, $order_detail) {
		$fdl = DataLapanganSwab::where('no_sampel', $request->no_sample)->first();
        $data_parameter = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
		$header = SwabTestHeader::where('no_sampel', $request->no_sample)
			->where('parameter', $request->parameter)
			->where('is_active', true)
			->first();

		if ($header) {
			return (object)[
				'message' => 'Parameter sudah diinput..!!',
				'status' => 401
			];
		}

		if ($fdl) { // Periksa apakah $fdl tidak null
			try {
				// Ambil data suhu, tekanan, dan kelembaban
				$luas = $fdl->luas_area_swab;

			} catch (\Exception $e) {
				return (object)[
					'message' => 'Error: ' . $e->getMessage(),
					'status' => 404
				];
			}

			// Mulai transaksi
			DB::beginTransaction();
			try {
				$functionObj = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first();
				if (!$functionObj) {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}
				$function = $functionObj->function;
				$data_parsing = $request->all();
				$data_parsing = (object) $data_parsing;

				$data_parsing->luas = $luas;
				$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

				$data_kalkulasi = AnalystFormula::where('function', $function)
					->where('data', $data_parsing)
					->where('id_parameter', $data_parameter->id)
					->process();

				if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
					return (object)[
						'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
						'status' => 404
					];
				}

				// Simpan data ke tabel SwabTestHeader
				$header = new SwabTestHeader();
				$header->no_sampel = $request->no_sample;
				$header->parameter = $request->parameter;
				$header->template_stp = $request->id_stp;
				$header->id_parameter = $data_parameter->id;
				$header->note = $request->note;
				$header->tanggal_terima = $order_detail->tanggal_terima;
				$header->created_by = $this->karyawan;
				$header->created_at = Carbon::now()->format('Y-m-d H:i:s');
				$header->save();

				// $data_kalkulasi['id_swab_header'] = $header->id;
				// $data_kalkulasi['no_sampel'] = $request->no_sample;
				// $data_kalkulasi['created_by'] = $this->karyawan;
				// // Simpan hasil ke tabel ws_value_swabtest
				// WsValueSwab::create($data_kalkulasi);

				$data_swab = array();
				$data_swab['id_swab_header'] = $header->id;
				$data_swab['no_sampel'] = $request->no_sample;
				$data_swab['hasil10'] = isset($data_kalkulasi['hasil']) ? $data_kalkulasi['hasil'] : null;
				$data_swab['hasil11'] = isset($data_kalkulasi['hasil2']) ? $data_kalkulasi['hasil2'] : null;
                $data_swab['hasil13'] = isset($data_kalkulasi['hasil3']) ? $data_kalkulasi['hasil3'] : null;
                $data_swab['hasil14'] = isset($data_kalkulasi['hasil4']) ? $data_kalkulasi['hasil4'] : null;
                $data_swab['hasil19'] = isset($data_kalkulasi['hasil5']) ? $data_kalkulasi['hasil5'] : null;
                $data_swab['created_by'] = $this->karyawan;
				WsValueUdara::create($data_swab);

				// Commit transaksi jika semua berhasil
				DB::commit();

				return (object)[
					'message' => 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];

			} catch (\Exception $e) {
				// Rollback transaksi jika terjadi kesalahan
				DB::rollBack();

				return (object)[
					'message' => 'Error: ' . $e->getMessage(),
					'status' => 500
				];
			}
		} else {
			return (object)[
				'message' => 'Data tidak ditemukan untuk sample yang diberikan.',
				'status' => 404
			];
		}
	}

	public function HelperKimiaPangan($request, $stp, $order_detail, $header) {
		$hp = $request->hp;
		$fp = $request->fp;

		$cek = Colorimetri::where('no_sampel',$request->no_sample)
		->where('parameter', $request->parameter)
		->where('is_active',true)
		->first();

		if(isset($cek->id)){
			return (object)[
				'message'=> 'No Sample Sudah ada.!!',
				'status' => 401
			];
		}else{
			$parame = $request->parameter;
			$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
			$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();

			if(is_null($check)){
				return (object)[
					'message'=> 'No Sample tidak ada.!!',
					'status' => 401
				];
			}
			$id_po = $check->id;
			$tgl_terima = $check->tanggal_terima;

			$function = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first()->function;
			// dd($data_parameter);
			$data_parsing = $request->all();
			$data_parsing = (object) $data_parsing;
			$data_parsing->tanggal_terima = $tgl_terima;

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', (object)$data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();


			if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					'status' => 404
				];
			}

			DB::beginTransaction();
			try {
				$data = new Colorimetri;
				$data->no_sampel 			= $request->no_sample;
				$data->parameter 			= $request->parameter;
				$data->template_stp 		= $request->id_stp;
				$data->jenis_pengujian 		= $request->jenis_pengujian;
				if($request->has('nilaiBauTerkecil')){
					if($request->nilaiBauTerkecil == 'Tidak Berbau'){
						$data->hp 					= 'Tidak Berbau';
					}else{
						$data->hp					= $request->nilaiBauTerkecil;
					}
				}else if($request->has('nilaiTerkecil')){
					if($request->nilaiTerkecil == 'Tidak Berasa'){
						$data->hp 					= 'Tidak Berasa';
					}else{
						$data->hp 					= $request->nilaiTerkecil;
					}
				}
				$data->note 				    = $request->note;
				$data->tanggal_terima 			= $tgl_terima;
				$data->created_by 				= $this->karyawan;
				$data->created_at 				= Carbon::now()->format('Y-m-d H:i:s');
				$data->save();

				$data_kalkulasi['id_colorimetri'] = $data->id;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
				$data_kalkulasi['created_by'] = $this->karyawan;
				// dd($result,$data);
				WsValueAir::create($data_kalkulasi);

				DB::commit();
				return (object)[
					'message'=> 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];
			} catch (\Exception $th) {
				DB::rollBack();
				return (object)[
					'message'=> 'Value Parameter gagal disimpan :' . $th->getMessage(),
					'status' => 500
				];
			}
		}
	}

	public function HelperColorimetriPadatan($request, $stp)
	{
		$hp = $request->hp;
		$fp = $request->fp;

		$cek = Colorimetri::where('no_sampel', $request->no_sample)
			->where('parameter', $request->parameter)
			->where('is_active', true)
			->first();

		if (isset($cek->id)) {
			return (object)[
				'message' => 'No Sample Sudah ada.!!',
				'status' => 401
			];
		} else {
			$parame = $request->parameter;

			$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori', $stp->category_id)->where('is_active', true)->first();
			$check = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();

			if (is_null($check)) {
				return (object)[
					'message' => 'No Sample tidak ada.!!',
					'status' => 401
				];
			}

			$id_po = $check->id;
			$tgl_terima = $check->tanggal_terima;

			$function = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first()->function;
			// dd($data_parameter);
			$data_parsing = $request->all();
			$data_parsing = (object) $data_parsing;
			$data_parsing->tanggal_terima = $tgl_terima;

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();


			if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}

			DB::beginTransaction();
			try {
				$data = new Colorimetri;
				$data->no_sampel          = $request->no_sample;
				$data->parameter          = $request->parameter;
				$data->template_stp       = $request->id_stp;
				$data->jenis_pengujian     = $request->jenis_pengujian;
				$data->hp                  = $request->hp;  //volume sample
				if ($request->parameter == 'Persistent Foam') {
					$data->fp = $request->waktu;
				} else {
					$data->fp = $request->fp;
				}  //faktor pengenceran
				$data->note               = $request->note;
				$data->tanggal_terima     = $tgl_terima;
				$data->created_by         = $this->karyawan;
				$data->created_at         = Carbon::now()->format('Y-m-d H:i:s');
				$data->save();

				// $datas = new FunctionValue();
				// $result = $datas->Colorimetri($par->id, $request, '', '');

				// WsValueAir::create($result);
                $data_kalkulasi['id_colorimetri'] = $data->id;
				$data_kalkulasi['no_sampel'] = $request->no_sample;
				if (isset($data_kalkulasi['hasil_mpn'])) unset($data_kalkulasi['hasil_mpn']);
				$kalkulasi1 = WsValueAir::create($data_kalkulasi);

				// $this->insertActivity("Sample", "Colorimetri", $stp->name, $request->no_sample, $request->parameter);

				DB::commit();
				return (object)[
					'message' => 'Value Parameter berhasil disimpan.!',
					'par' => $request->parameter,
					'status' => 200
				];
			} catch (\Exception $e) {
				DB::rollBack();
				return (object)[
					'message' => 'Error : ' . $e->getMessage(),
					'status' => 500
				];
			}
		}
	}

	private function HelperOthers($request, $stp, $order_detail, $par) {
		DB::beginTransaction();
		try {
			$parame = $request->parameter;
			$data_parameter = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
			$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();

			if(!isset($check->id)){
				return (object)[
					'message'=> 'No Sample tidak ada.!!',
					'status' => 401
				];
			}
			$id_po = $check->id;
			$tgl_terima = $check->tanggal_terima;

			$function = 'OthersSubkontrak';

			$data_parsing = $request->all();
			$data_parsing = (object)$data_parsing;
			// dd($function);

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $data_parameter->id)
				->process();


			if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					'status' => 404
				];
			}

			$exist = Subkontrak::where('no_sampel', trim($request->no_sample))
				->where('category_id', $stp->category_id)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->first();

			if (isset($exist->id)) {
				$data = Subkontrak::find($exist->id);
			} else {
				$data = new Subkontrak;
			}

			$data->no_sampel 			= trim($request->no_sample);
			$data->category_id 			= $stp->category_id;
			$data->parameter 			= $request->parameter;
			$data->jenis_pengujian 		= $request->jenis_pengujian;
			$data->hp 					= $request->hp;
			$data->fp 					= $request->fp ?? null; //faktor pengenceran
			$data->note 				= $request->note;
			$data->is_follow 			= $request->is_follow ?? false; // checkbox menyusul atau tidak
			$data->is_approve 			= true;
			$data->approved_by 			= $this->karyawan;
			$data->approved_at 			= Carbon::now()->addMinutes(5)->format('Y-m-d H:i:s');
			$data->created_by 			= $this->karyawan;
			$data->created_at 			= Carbon::now()->addMinutes(5)->format('Y-m-d H:i:s');
			if($check->status > 1 && $stp->sample->nama_kategori == 'Air'){
				$data->lhps = 1;
			}
			// dd($data);
			$data->save();

			if($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan'){
				if($stp->sample->nama_kategori == 'Air'){
					$kalkulasi1 = WsValueAir::updateOrCreate(
						['no_sampel' => trim($request->no_sample), 'id_subkontrak' => $data->id], 
						[
							'hasil' => $data_kalkulasi['hasil'],
						]);
				}else{
					$data_kalkulasi['id_subkontrak'] = $data->id;
					$data_kalkulasi['no_sampel'] = trim($request->no_sample);
					$kalkulasi1 = WsValueAir::create($data_kalkulasi);
				}
			}else if($stp->sample->nama_kategori == 'Udara'){
				$existLingkungan = LingkunganHeader::where('no_sampel', trim($request->no_sample))
					->where('parameter', $request->parameter)
					->where('is_active', true)
					->first();
				if (Carbon::parse($order_detail->tanggal_terima) < Carbon::parse('2025-11-01') && isset($existLingkungan->id)) {
					$data_udara = WsValueUdara::where('id_lingkungan_header', $existLingkungan->id)->orderBy('id', 'desc')->first();
					$data_udara->id_subkontrak  = $data->id;
					for ($i = 1; $i <= 19; $i++) { // f_koreksi_1 - f_koreksi_17
						$key = 'f_koreksi_' . $i;
						if (isset($data_udara->{$key})) {
							$data_udara->{$key} = $data_kalkulasi['hasil'];
						}
					}
					$data_udara->save();
				}else{
					$data_udara = [];
					$data_udara['id_subkontrak'] = $data->id;
					$data_udara['no_sampel'] = trim($request->no_sample);
					for ($i = 1; $i <= 19; $i++) { // f_koreksi_1 - f_koreksi_17
						$key = 'f_koreksi_' . $i;
						$data_udara[$key] = $data_kalkulasi['hasil'];
					}
					$kalkulasi1 = WsValueUdara::create($data_udara);
				}
			}else if($stp->sample->nama_kategori == 'Emisi'){
				$existEmisiCerobong = EmisiCerobongHeader::where('no_sampel', trim($request->no_sample))
					->where('parameter', $request->parameter)
					->where('is_active', true)
					->first();
				if (Carbon::parse($order_detail->tanggal_terima) < Carbon::parse('2025-11-01') && isset($existEmisiCerobong->id)) {
					$data_emisi = WsValueEmisiCerobong::where('id_emisi_cerobong_header', $existEmisiCerobong->id)->orderBy('id', 'desc')->first();
					$data_emisi->id_subkontrak  = $data->id;
					for ($i = 0; $i <= 10; $i++) { // f_koreksi_1 - f_koreksi_17
						$key = 'f_koreksi_c';
						$key .= $i == 0 ? '' : $i;
						if (isset($data_emisi->{$key})) {
							$data_emisi->{$key} = $data_kalkulasi['hasil'];
						}
					}
					$data_emisi->save();
				}else{
					$data_emisi = [];
					$data_emisi['id_subkontrak'] = $data->id;
					$data_emisi['no_sampel'] = trim($request->no_sample);
					for ($i = 0; $i <= 10; $i++) { // f_koreksi_1 - f_koreksi_17
						$key = 'f_koreksi_c';
						$key .= $i == 0 ? '' : $i;
						$data_emisi[$key] = $data_kalkulasi['hasil'];
					}
					$kalkulasi1 = WsValueEmisiCerobong::create($data_emisi);
				}
			}

			DB::commit();
			return (object)[
				'message'=> 'Value Parameter berhasil disimpan.!',
				'par' => $request->parameter,
				'status' => 200
			];
		} catch (\Exception $e) {
			DB::rollBack();
			return (object)[
				'message'=> 'Error : ' . $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile(),
				'status' => 500
			];
		}
	}

    public function getCabang()
    {
        $cabang = MasterCabang::where('is_active', true)->get();
        return response()->json($cabang);
    }

	public function printPdf(Request $request) {
		try {
			// dd($request->all());
			$decodedData = json_decode($request->collectionData, true);
			$filename = AnalystRender::renderPdf($decodedData, $request->tanggal, $request->category, $request->stp);
			// dd($filename);
			return response()->json([
				'filename' => $filename
			],200);
		}catch (\Exception $e) {
			return response()->json([
				'message' => 'Gagal mengambil data: '.$e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			]);
		}
	}

    public function getCategory()
    {
        $data = MasterKategori::where('is_active', true)->select('id','nama_kategori')->get();
        return response()->json($data);
    }

    public function getTemplate(Request $request)
    {
        try {
            $data = TemplateStp::where('is_active', true)
                ->where('category_id', $request->id_kategori)
                ->select('id','name')
                ->get();

            return response()->json($data);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: '.$e->getMessage(),
                'status' => '500'
            ],500);
        }
    }

    public function getMicroUdara(Request $request)
	{
		try {
			$data = DetailMicrobiologi::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active', true)->get();
			return response()->json([
				'data' => $data
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Gagal mengambil data: ' . $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			]);
		}
	}

    public function cekNoSample(Request $request){
        try {
            $data = OrderDetail::where('no_sampel', $request->no_sample)->where('kategori_2', $request->category)->where('is_active', true)->first();
            // $data = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
            return response()->json([
                'data' => $data
            ], 200);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil order: '.$e->getMessage(),
            ],500);
        }
    }

	public function getInputForm(Request $request){
		try {
			$data = AnalisParameter::with('input')->where('parameter_name', $request->parameter)->where('id_stp', $request->id_stp)->where('is_active', true)->first();
			if(isset($data->input)){
				$data->input->body = json_decode($data->input->body);
			}else{
				return response()->json([
					'form' => null
				], 200);
			}
			return response()->json([
				'form' => $data->input->body,
				'has_child' => $data->has_child
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Gagal mengambil data: ' . $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			], 500);
		}
	}

	// Mobile
	public function getKategori(Request $request) {
        $data = MasterKategori::where('is_active', true)->get()->makeHidden(['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by']);

        return response()->json($data);
    }

	public function getRiwayat(Request $request) {
		$perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = AnalystActivity::where('user_id', $this->user_id);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('activity', 'like', "%$search%");
            });
        }

        $activities = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($activities);
	}

	public function getDashboardData(Request $request) {
		$query = AnalystActivity::whereDate('created_at', Carbon::now()->toDateString())
			->where('user_id', $this->user_id);

		$stp = (clone $query)
			->select(DB::raw("CONCAT('Template ', stp_name) as label"), DB::raw('COUNT(*) as value'))
			->groupBy('stp_name')
			->get();

		$parameter = (clone $query)
			->select(
				DB::raw("CONCAT('Parameter ', parameter) as label"),
				DB::raw('COUNT(*) as value'))
			->groupBy('parameter')
			->get();

		$sampel = (clone $query)
			->select('no_sampel as label', DB::raw('COUNT(*) as value'))
			->groupBy('no_sampel')
			->get();

		$merged = $stp->concat($parameter)->concat($sampel)->values();

		return response()->json($merged);
	}

    public function getTemplateMobile(Request $request) {
        $id = explode('-', $request->category_id)[0];
        $data = TemplateStp::where('category_id', $id)->where('is_active', true)->get()->makeHidden(['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by','param']);

        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        $data = TemplateStp::where('id', $request->template_id)
            ->first();

        if($data){
            return response()->json(json_decode($data->param, true));
        } else {
            return response()->json(['message' => 'Data not found'], 500);
        }
    }

    public function getData(Request $request) {
        try {
            if(isset($request->tanggal) && $request->tanggal!=null && isset($request->kategori) && $request->kategori!=null && $request->template !=null){
                $parame = array();

                $join = OrderDetail::with('TrackingSatu')
				->whereHas('TrackingSatu', function ($q) use ($request) {
					$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
				})
				->where('kategori_2',$request->kategori)
				->where('is_active', true)
				->get();
                // $par = TemplateStp::where('id', $request->template)->first();

                if($join->isEmpty()){
                    return response()->json([
                        'status'=>1
                    ], 200);
                }

                $select = array($request->parameter);

                $jumlah = count($join);

				$ftc = [];

				$stp = TemplateStp::with('sample')->where('id', $request->template)->select('name','category_id')->first();

                foreach($join as $kyes=>$val){
                    $param = array_map(function($item) {
                        return explode(';', $item)[1];
                    }, json_decode($val->parameter));

                    $lis = array_diff($select, $param);

                    $beda = array_diff($select, $param);

                    foreach($beda as $num=>$kk){
                        $dat[$kk]='-';

                        $hola[$kk]='-';
                    }

                    $sama = array_diff($select , $lis);
                    foreach($sama as $mun=>$ll){
                        $dat[$ll]=$val->no_sampel;
                        $hola[$ll]='-';
                    }

					if ($stp->sample->nama_kategori == 'Air') {
						// dd($val->TrackingSatu->ftc_verifier);
						$ftc[$kyes] = (object)[
							'no_sample' => $val->no_sampel,
							'tanggal' => $val->TrackingSatu == null ? '-' : $val->TrackingSatu->ftc_verifier
						];
					}

                    ksort($dat);
                    $data[$kyes]=$dat;

                    ksort($hola);
                    $inter[$kyes]=$hola;

                }
                $kl = array("-");
                foreach($select as $key=>$tab){
                    $re= array_column($data, $tab);
                    $result = array_diff($re, $kl);
                    sort($result);
                    $tes[$key] = $result;

                }
                foreach($select as $key0=>$tab0){
                    $re0= array_column($inter, $tab0);
                    $result0 = array_diff($re0, $kl);
                    sort($result0);
                    $tes0[$key0] = $result0;
                }
                $tes1 = $tes0;
                // dd($tes0);
                $approve = $tes0;
                // dd($stp);
                if($stp->name == 'TITRIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {

                    foreach($select as $key => $val){
                        $hasil_1 = Titrimetri::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();

                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
                        } else {
                            $tes1[$key] = [];
                        }

                        $hasil_2 = Titrimetri::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }

                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);

                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }

                }else if($stp->name == 'GRAVIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {

                    foreach($select as $key => $val){
                        $hasil_1 = Gravimetri::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();

                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
                        } else {
                            $tes1[$key] = [];
                        }
                        $hasil_2 = Gravimetri::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }

                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);

                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }

                }else if(
                    ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'COLORIMETER' || $stp->name == 'MERCURY ANALYZER')
                    &&
                    ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')
                ) {
                    // dd($select);
                    foreach($select as $key => $val){
                        $hasil_1 = Colorimetri::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();
                        // dd($hasil_1);
                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;


                        } else {
                            $tes1[$key] = [];
                        }

                        $hasil_2 = Colorimetri::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
                        // dd($hasil_2);
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }

                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);

                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
                }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Udara'){
                    foreach($select as $key => $val){
                        $hasil_1 = LingkunganHeader::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();

                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;

                        } else {
                            $tes1[$key] = [];
                        }

                        $hasil_2 = LingkunganHeader::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();

                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }

                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);

                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
                }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Emisi'){
                    foreach($select as $key => $val){
                        $hasil_1 = EmisiCerobongHeader::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();

                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;

                        } else {
                            $tes1[$key] = [];
                        }

                        $hasil_2 = EmisiCerobongHeader::with('TrackingSatu')
						->whereHas('TrackingSatu', function ($q) use ($request) {
							$q->where('ftc_laboratory', 'LIKE', "%$request->tanggal%");
						})
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();

                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }

                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);

                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
                }

                $AnalisParameter = AnalisParameter::with('input')->where('parameter_name', $request->parameter)->first();
                // dd($AnalisParameter);
                $forminput = [];
                $hasChild = 0;
                if($AnalisParameter != null){
                    $forminput = json_decode($AnalisParameter->input->body, true);
                    $hasChild = $AnalisParameter->has_child;
                }
                // dd($tes1);
                return response()->json([
                    'status'=>0,
                    'columns'=>$select,
                    'data' => $tes,
                    'nilai' => $tes1,
                    'approve' => $approve,
                    'form_input' => $forminput,
                    'has_child' => $hasChild,
					'ftc' => $ftc
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data Not Found.!',
                ], 401);
            }


        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Failed To Get Data : ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 401);
        }
    }

    public function tabelMpn($tb1, $tb2, $tb3) {
		$hasil = '';

		if($tb1 == 0 && $tb2 == 0 && $tb3 == 0) {
			$hasil = '<1.8';
		}else if($tb1 == 0 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 1.8;
		}else if($tb1 == 0 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 1.8;
		}else if($tb1 == 0 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 3.6;
		}else if($tb1 == 0 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 3.7;
		}else if($tb1 == 0 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 5.5;
		}else if($tb1 == 0 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 5.6;
		}else if($tb1 == 1 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 2;
		}else if($tb1 == 1 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 4;
		}else if($tb1 == 1 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 6;
		}else if($tb1 == 1 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 4;
		}else if($tb1 == 1 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 6.1;
		}else if($tb1 == 1 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 8.1;
		}else if($tb1 == 1 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 6.1;
		}else if($tb1 == 1 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 8.2;
		}else if($tb1 == 1 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 8.3;
		}else if($tb1 == 1 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 10;
		}else if($tb1 == 1 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 11;
		}else if($tb1 == 2 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 4.5;
		}else if($tb1 == 2 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 6.8;
		}else if($tb1 == 2 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 9.1;
		}else if($tb1 == 2 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 6.8;
		}else if($tb1 == 2 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 9.2;
		}else if($tb1 == 2 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 12;
		}else if($tb1 == 2 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 9.3;
		}else if($tb1 == 2 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 12;
		}else if($tb1 == 2 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 14;
		}else if($tb1 == 2 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 12;
		}else if($tb1 == 2 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 14;
		}else if($tb1 == 2 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 15;
		}else if($tb1 == 3 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 7.8;
		}else if($tb1 == 3 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 11;
		}else if($tb1 == 3 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 13;
		}else if($tb1 == 3 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 11;
		}else if($tb1 == 3 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 14;
		}else if($tb1 == 3 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 17;
		}else if($tb1 == 3 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 14;
		}else if($tb1 == 3 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 17;
		}else if($tb1 == 3 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 20;
		}else if($tb1 == 3 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 17;
		}else if($tb1 == 3 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 21;
		}else if($tb1 == 3 && $tb2 == 3 && $tb3 == 2) {
			$hasil = 24;
		}else if($tb1 == 3 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 21;
		}else if($tb1 == 3 && $tb2 == 4 && $tb3 == 1) {
			$hasil = 24;
		}else if($tb1 == 3 && $tb2 == 5 && $tb3 == 0) {
			$hasil = 25;
		}else if($tb1 == 4 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 13;
		}else if($tb1 == 4 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 17;
		}else if($tb1 == 4 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 21;
		}else if($tb1 == 4 && $tb2 == 0 && $tb3 == 3) {
			$hasil = 25;
		}else if($tb1 == 4 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 17;
		}else if($tb1 == 4 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 21;
		}else if($tb1 == 4 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 26;
		}else if($tb1 == 4 && $tb2 == 1 && $tb3 == 3) {
			$hasil = 31;
		}else if($tb1 == 4 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 22;
		}else if($tb1 == 4 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 26;
		}else if($tb1 == 4 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 32;
		}else if($tb1 == 4 && $tb2 == 2 && $tb3 == 3) {
			$hasil = 38;
		}else if($tb1 == 4 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 27;
		}else if($tb1 == 4 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 33;
		}else if($tb1 == 4 && $tb2 == 3 && $tb3 == 2) {
			$hasil = 39;
		}else if($tb1 == 4 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 34;
		}else if($tb1 == 4 && $tb2 == 4 && $tb3 == 1) {
			$hasil = 40;
		}else if($tb1 == 4 && $tb2 == 4 && $tb3 == 2) {
			$hasil = 47;
		}else if($tb1 == 4 && $tb2 == 5 && $tb3 == 0) {
			$hasil = 41;
		}else if($tb1 == 4 && $tb2 == 5 && $tb3 == 1) {
			$hasil = 48;
		}else if($tb1 == 5 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 23;
		}else if($tb1 == 5 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 31;
		}else if($tb1 == 5 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 43;
		}else if($tb1 == 5 && $tb2 == 0 && $tb3 == 3) {
			$hasil = 58;
		}else if($tb1 == 5 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 33;
		}else if($tb1 == 5 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 46;
		}else if($tb1 == 5 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 63;
		}else if($tb1 == 5 && $tb2 == 1 && $tb3 == 3) {
			$hasil = 84;
		}else if($tb1 == 5 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 49;
		}else if($tb1 == 5 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 70;
		}else if($tb1 == 5 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 94;
		}else if($tb1 == 5 && $tb2 == 2 && $tb3 == 3) {
			$hasil = 120;
		}else if($tb1 == 5 && $tb2 == 2 && $tb3 == 4) {
			$hasil = 150;
		}else if($tb1 == 5 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 79;
		}else if($tb1 == 5 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 110;
		}else if($tb1 == 5 && $tb2 == 3 && $tb3 == 2) {
			$hasil = 140;
		}else if($tb1 == 5 && $tb2 == 3 && $tb3 == 3) {
			$hasil = 170;
		}else if($tb1 == 5 && $tb2 == 3 && $tb3 == 4) {
			$hasil = 210;
		}else if($tb1 == 5 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 130;
		}else if($tb1 == 5 && $tb2 == 4 && $tb3 == 1) {
			$hasil = 170;
		}else if($tb1 == 5 && $tb2 == 4 && $tb3 == 2) {
			$hasil = 220;
		}else if($tb1 == 5 && $tb2 == 4 && $tb3 == 3) {
			$hasil = 280;
		}else if($tb1 == 5 && $tb2 == 4 && $tb3 == 4) {
			$hasil = 350;
		}else if($tb1 == 5 && $tb2 == 4 && $tb3 == 5) {
			$hasil = 430;
		}else if($tb1 == 5 && $tb2 == 5 && $tb3 == 0) {
			$hasil = 240;
		}else if($tb1 == 5 && $tb2 == 5 && $tb3 == 1) {
			$hasil = 350;
		}else if($tb1 == 5 && $tb2 == 5 && $tb3 == 2) {
			$hasil = 540;
		}else if($tb1 == 5 && $tb2 == 5 && $tb3 == 3) {
			$hasil = 920;
		}else if($tb1 == 5 && $tb2 == 5 && $tb3 == 4) {
			$hasil = 1600;
		}else if($tb1 == 5 && $tb2 == 5 && $tb3 == 5) {
			$hasil = '>1600';
		}
		return $hasil;
	}

    public function KonversiTekananUapAir($suhu) {
		if (!is_float($suhu) && !is_double($suhu)) {
			// Jika tipe $suhu bukan float atau double, maka ubah menjadi format desimal
			$suhuTodecimal = number_format($suhu, 1, '.', '');
			$suhuToArray = explode('.', $suhuTodecimal);
		} else {
			// Jika $suhu sudah bertipe float atau double, tidak perlu diubah
			$suhuTodecimal = $suhu;
			$suhuToArray = explode('.', (string)$suhuTodecimal);
		}

		$axisY = $suhuToArray[0];
		$axisX = $suhuToArray[1];
		// dump($suhuToArray);
		$tekananUapAirJenuh = [
			0 => [0.6105, 0.6195, 0.6195, 0.6241, 0.6286, 0.6333, 0.6379, 0.6426, 0.6473, 0.6519],
			1 => [0.6567, 0.6615, 0.6663, 0.6711, 0.6759, 0.6809, 0.6858, 0.6907, 0.6958, 0.7007],
			2 => [0.7058, 0.7109, 0.7159, 0.7210, 0.7262, 0.7314, 0.7366, 0.7419, 0.7473, 0.7526],
			3 => [0.7579, 0.7633, 0.7687, 0.7742, 0.7797, 0.7851, 0.7907, 0.7963, 0.8019, 0.8077],
			4 => [0.8134, 0.8191, 0.8249, 0.8306, 0.8365, 0.8423, 0.8483, 0.8543, 0.8603, 0.8663],
			5 => [0.8723, 0.8785, 0.8846, 0.8907, 0.8970, 0.9033, 0.9095, 0.9158, 0.9222, 0.9286],
			6 => [0.9350, 0.9415, 0.9481, 0.9546, 0.9611, 0.9678, 0.9745, 0.9813, 0.9881, 0.9949],
			7 => [1.002, 1.009, 1.016, 1.022, 1.030, 1.037, 1.044, 1.051, 1.058, 1.065],
			8 => [1.073, 1.080, 1.087, 1.095, 1.102, 1.110, 1.117, 1.125, 1.132, 1.140],
			9 => [1.148, 1.156, 1.164, 1.171, 1.179, 1.187, 1.195, 1.203, 1.211, 1.219],
			10 => [1.228, 1.236, 1.244, 1.253, 1.261, 1.269, 1.278, 1.286, 1.295, 1.304],
			11 => [1.312, 1.321, 1.330, 1.3388, 1.3478, 1.3567, 1.3658, 1.3748, 1.3839, 1.3998],
			12 => [1.4023, 1.4116, 1.4209, 1.4303, 1.4397, 1.4492, 1.4587, 1.4683, 1.4779, 1.4876],
			13 => [1.4973, 1.5072, 1.5171, 1.5269, 1.5369, 1.5471, 1.5571, 1.5673, 1.5776, 1.5879],
			14 => [1.5981, 1.6085, 1.6191, 1.6296, 1.6401, 1.6508, 1.6615, 1.6723, 1.6831, 1.6940],
			15 => [1.7049, 1.7159, 1.7269, 1.7381, 1.7493, 1.7605, 1.7719, 1.7832, 1.7947, 1.8061],
			16 => [1.8177, 1.8293, 1.8410, 1.8529, 1.8648, 1.8766, 1.8886, 1.9006, 1.9128, 1.9249],
			17 => [1.9372, 1.9494, 1.9618, 1.9744, 1.9869, 1.9994, 2.0121, 2.0249, 2.0377, 2.0505],
			18 => [2.0634, 2.0765, 2.0896, 2.1028, 2.1160, 2.1293, 2.1426, 2.1560, 2.1694, 2.1830],
			19 => [2.1968, 2.2106, 2.2245, 2.2383, 2.2523, 2.2663, 2.2805, 2.2947, 2.3090, 2.3234],
			20 => [2.3378, 2.3523, 2.3669, 2.3815, 2.3963, 2.4111, 2.4261, 2.4410, 2.4561, 2.4713],
			21 => [2.4865, 2.5018, 2.5171, 2.5326, 2.5482, 2.5639, 2.5797, 2.5955, 2.6114, 2.6274],
			22 => [2.6434, 2.6595, 2.6758, 2.6922, 2.7086, 2.7251, 2.7418, 2.7584, 2.7751, 2.7919],
			23 => [2.8088, 2.8259, 2.8430, 2.8602, 2.8775, 2.8950, 2.9124, 2.9300, 2.9478, 2.9655],
			24 => [2.9834, 3.0014, 3.0195, 3.0378, 3.0560, 3.0744, 3.0928, 3.1113, 3.1299, 3.1485],
			25 => [3.1672, 3.1860, 3.2049, 3.2240, 3.2432, 3.2625, 3.2820, 3.3016, 3.3213, 3.3411],
			26 => [3.3609, 3.3809, 3.4009, 3.4211, 3.4413, 3.4616, 3.4820, 3.5025, 3.5232, 3.5440],
			27 => [3.5649, 3.5860, 3.6070, 3.6282, 3.6496, 3.6710, 3.6925, 3.7141, 3.7358, 3.7577],
			28 => [3.7796, 3.8016, 3.8237, 3.8460, 3.8683, 3.8909, 3.9135, 3.9363, 3.9693, 3.9823],
			29 => [4.0054, 4.0286, 4.0519, 4.0754, 4.0990, 4.1227, 4.1466, 4.1705, 4.1945, 4.2186],
			30 => [4.2429, 4.2672, 4.2918, 4.3164, 4.3411, 4.1659, 4.3908, 4.4159, 4.4412, 4.4667],
			31 => [4.4923, 4.5180, 4.5439, 4.5698, 4.5958, 4.6219, 4.6482, 4.6745, 4.7011, 4.7279],
			32 => [4.7547, 4.7816, 4.887, 4.8359, 4.8632, 4.8907, 4.9184, 4.9341, 4.9740, 5.0020],
			33 => [5.0301, 5.0286, 5.0869, 5.1154, 5.1441, 5.1730, 5.3030, 5.2312, 5.2605, 5.2898],
			34 => [5.3193, 5.3490, 5.3788, 5.4088, 5.4390, 5.4693, 5.4997, 5.5302, 5.5609, 5.5918],
			35 => [5.6229, 5.6541, 5.6854, 5.7169, 5.7485, 5.7802, 5.8122, 5.8443, 5.8766, 5.9088],
			36 => [5.9412, 5.9739, 6.0067, 6.0396, 6.0727, 6.1060, 6.1395, 6.1731, 6.2070, 6.2410],
			37 => [6.2751, 6.3093, 6.3437, 6.3783, 6.4131, 6.4480, 6.4831, 6.5183, 6.5537, 6.5893],
			38 => [6.6251, 6.6609, 6.6969, 6.7330, 6.7693, 6.8058, 6.8425, 6.8794, 6.9166, 6.9541],
			39 => [6.9917, 7.0294, 7.0673, 7.1053, 7.1434, 7.1817, 7.2202, 7.2589, 7.2977, 7.3367],
			40 => [7.3759, 7.414, 7.454, 7.494, 7.534, 7.574, 7.614, 7.654, 7.695, 7.737],
			41 => [7.778, 7.819, 7.861, 7.902, 7.943, 7.986, 8.029, 8.071, 8.114, 8.157],
			42 => [8.199, 8.242, 8.285, 8.329, 8.373, 8.417, 8.461, 8.505, 8.549, 8.594],
			43 => [8.639, 8.685, 8.730, 8.775, 8.821, 8.867, 8.914, 8.961, 9.007, 9.054],
			44 => [9.101, 9.147, 9.195, 9.243, 9.291, 9.339, 9.387, 9.435, 9.485, 9.534],
			45 => [9.583, 9.633, 9.682, 9.731, 9.781, 9.831, 9.882, 9.933, 9.983, 10.03],
			46 => [10.09, 10.14, 10.19, 10.24, 10.29, 10.35, 10.40, 10.40, 10.45, 10.56],
			47 => [10.61, 10.67, 10.72, 10.78, 10.83, 10.88, 10.94, 10.99, 11.05, 11.10],
			48 => [11.16, 11.22, 11.27, 11.33, 11.39, 11.45, 11.50, 11.56, 11.62, 11.68],
			49 => [11.74, 11.79, 11.85, 11.91, 11.97, 12.03, 12.09, 12.15, 12.21, 12.27],
			50 => [12.33, 12.39, 12.46, 12.52, 12.58, 12.64, 12.70, 12.77, 12.83, 12.89],
			51 => [12.96, 13.02, 13.09, 13.15, 13.22, 13.28, 13.347, 13.412, 13.479, 13.544],
			52 => [13.611, 13.678, 13.746, 13.812, 13.880, 13.948, 14.016, 14.084, 14.154, 14.223],
			53 => [14.292, 14.361, 14.431, 14.500, 14.571, 14.641, 14.712, 14.784, 14.856, 14.928],
			54 => [15.000, 15.072, 15.144, 15.217, 15.291, 15.364, 15.439, 15.513, 15.588, 15.663],
			55 => [15.737, 15.812, 15.887, 15.963, 16.040, 16.117, 16.195, 16.272, 16.349, 16.427],
			56 => [16.505, 16.585, 16.664, 16.743, 16.823, 16.903, 16.983, 17.064, 17.145, 17.227],
			57 => [17.308, 17.391, 17.473, 17.556, 17.639, 17.721, 17.805, 17.889, 17.973, 18.059],
			58 => [18.143, 18.228, 18.313, 18.400, 18.486, 18.573, 18.660, 18.748, 18.836, 18.924],
			59 => [19.012, 19.101, 19.190, 19.280, 19.369, 19.460, 19.550, 19.641, 19.732, 19.824],
			60 => [19.916, 20.008, 20.101, 20.194, 20.288, 20.381, 20.476, 20.570, 20.665, 20.760],
			61 => [20.856, 20.952, 21.048, 21.144, 21.241, 21.340, 21.438, 21.542, 21.636, 21.734],
			62 => [21.834, 21.934, 22.034, 22.134, 22.236, 22.337, 22.438, 22.541, 22.643, 22.746],
			63 => [22.849, 22.953, 23.057, 23.162, 23.267, 23.373, 23.478, 23.585, 23.691, 23.798],
			64 => [23.906, 24.013, 24.121, 24.230, 24.339, 24.449, 24.558, 24.669, 24.779, 24.891],
			65 => [25.003, 25.115, 25.227, 25.339, 25.453, 25.567, 25.682, 25.797, 25.911, 26.054],
			66 => [26.143, 26.259, 26.376, 26.494, 26.611, 26.728, 26.847, 26.966, 27.086, 27.206],
			67 => [27.326, 27.447, 27.568, 27.690, 27.812, 27.935, 28.058, 28.180, 28.304, 28.428],
			68 => [28.554, 28.679, 28.806, 28.932, 29.059, 29.186, 29.314, 29.442, 29.570, 29.699],
			69 => [29.828, 29.959, 30.090, 30.220, 30.352, 30.484, 30.617, 30.751, 30.884, 31.017],
			70 => [31.16, 31.29, 31.42, 31.56, 31.70, 31.84, 31.97, 32.12, 32.25, 32.38],
			71 => [32.52, 32.66, 32.80, 32.94, 33.08, 33.22, 33.37, 33.52, 33.65, 33.80],
			72 => [33.94, 34.09, 34.24, 34.38, 34.53, 34.68, 34.82, 34.97, 35.13, 35.28],
			73 => [35.42, 35.57, 35.73, 35.88, 36.04, 36.18, 36.34, 36.49, 36.65, 36.80],
			74 => [36.96, 37.12, 37.26, 37.42, 37.58, 37.74, 37.90, 38.06, 38.22, 38.38],
			75 => [38.54, 38.70, 38.86, 39.04, 39.20, 39.36, 39.53, 39.69, 39.85, 40.02],
			76 => [40.18, 40.36, 40.52, 40.69, 40.86, 41.02, 41.20, 41.37, 41.54, 41.72],
			77 => [41.88, 42.05, 42.22, 42.40, 42.57, 42.76, 42.93, 43.10, 43.29, 43.46],
			78 => [43.64, 43.82, 44.00, 44.18, 44.36, 44.54, 44.73, 44.90, 45.09, 45.28],
			79 => [45.46, 45.65, 45.84, 46.02, 46.21, 46.40, 46.58, 46.77, 46.96, 47.16],
			80 => [47.34, 47.53, 47.73, 47.92, 48.12, 48.32, 48.50, 48.70, 48.90, 49.10],
			81 => [49.29, 49.49, 49.69, 49.89, 50.22, 50.30, 50.64, 50.70, 50.90, 51.10],
			82 => [51.32, 51.52, 51.73, 51.93, 52.14, 52.34, 52.56, 52.77, 52.98, 53.20],
			83 => [53.41, 53.62, 53.84, 54.05, 54.26, 54.48, 54.70, 54.92, 55.13, 55.36],
			84 => [55.57, 55.78, 56.01, 56.22, 56.45, 56.68, 56.90, 57.13, 57.36, 57.58],
			85 => [57.81, 58.04, 58.26, 58.49, 58.73, 58.96, 59.18, 59.42, 59.65, 59.89],
			86 => [60.12, 60.34, 60.58, 60.82, 61.06, 61.29, 61.53, 61.77, 62.01, 62.25],
			87 => [62.49, 62.73, 62.97, 63.21, 63.46, 63.70, 63.95, 64.19, 64.45, 64.69],
			88 => [64.94, 65.19, 65.45, 65.69, 65.94, 66.19, 66.45, 66.70, 66.97, 67.22],
			89 => [67.47, 67.73, 67.99, 68.25, 68.51, 68.78, 69.03, 69.30, 69.57, 69.70],
			90 => [70.096, 70.362, 70.630, 70.898, 71.167, 71.437, 71.709, 71.981, 72.254, 72.527],
			91 => [72.801, 73.075, 73.351, 73.629, 73.907, 74.185, 74.465, 74.746, 75.027, 75.310],
			92 => [75.592, 75.876, 76.162, 76.447, 76.734, 77.022, 77.310, 77.599, 77.890, 78.182],
			93 => [78.474, 78.767, 79.060, 79.355, 79.651, 79.948, 80.245, 80.544, 80.844, 81.145],
			94 => [81.447, 81.749, 82.052, 82.356, 82.661, 82.968, 83.274, 83.582, 83.892, 84.202],
			95 => [84.513, 84.825, 85.138, 85.452, 85.766, 86.082, 86.400, 86.717, 87.036, 87.355],
			96 => [87.675, 87.997, 88.319, 88.643, 88.967, 89.293, 89.619, 89.947, 90.275, 90.605],
			97 => [90.935, 91.266, 91.598, 91.931, 92.266, 92.602, 92.939, 93.276, 93.615, 93.954],
			98 => [94.295, 94.636, 94.979, 95.323, 95.667, 96.012, 96.359, 96.707, 97.056, 97.407],
			99 => [97.757, 98.109, 98.463, 98.816, 99.172, 99.528, 99.885, 100.24, 100.60, 100.96],
			100 => [101.32, 101.69, 102.05, 102.42, 102.78, 103.15, 103.52, 103.89, 104.26, 104.63],
			101 => [105.00, 105.37, 105.75, 106.12, 106.50, 106.88, 107.26, 107.64, 108.02, 108.40]
		];

		$tekananUapAir = $tekananUapAirJenuh[$axisY][$axisX];
		// Konversi KPa ke mmHg
		$tekananuapAirmmHg = $tekananUapAir * 7.50062;
		// dd($tekananuapAirmmHg);
		return $tekananuapAirmmHg;
	}

    public function penentuanPv($suhu) {

		if($suhu > 0.0 && $suhu < 0.5 ) {
			$nil_pv = 4.6;
		}else if($suhu >= 0.5 && $suhu < 1 ) {
			$nil_pv = 4.8;
		}else if($suhu >= 1.0 && $suhu < 1.5) {
			$nil_pv = 4.9;
		}else if($suhu >= 1.5 && $suhu < 2) {
			$nil_pv = 5.1;
		}else if($suhu >= 2.0 && $suhu < 2.5) {
			$nil_pv = 5.3;
		}else if($suhu >= 2.5 && $suhu < 3) {
			$nil_pv = 5.5;
		}else if($suhu >= 3.0 && $suhu < 3.5) {
			$nil_pv = 5.7;
		}else if($suhu >= 3.5 && $suhu < 4) {
			$nil_pv = 5.9;
		}else if($suhu >= 4.0 && $suhu < 4.5) {
			$nil_pv = 6.1;
		}else if($suhu >= 4.5 && $suhu < 5) {
			$nil_pv = 6.3;
		}else if($suhu >= 5.0 && $suhu < 5.5) {
			$nil_pv = 6.5;
		}else if($suhu >= 5.5 && $suhu < 6) {
			$nil_pv = 6.8;
		}else if($suhu >= 6.0 && $suhu < 6.5) {
			$nil_pv = 7.0;
		}else if($suhu >= 6.5 && $suhu < 7) {
			$nil_pv = 7.3;
		}else if($suhu >= 7.0 && $suhu < 7.5) {
			$nil_pv = 7.5;
		}else if($suhu >= 7.5 && $suhu < 8) {
			$nil_pv = 7.8;
		}else if($suhu >= 8.0 && $suhu < 8.5) {
			$nil_pv = 8.0;
		}else if($suhu >= 8.5 && $suhu < 9) {
			$nil_pv = 8.3;
		}else if($suhu >= 9.0 && $suhu < 9.5) {
			$nil_pv = 8.6;
		}else if($suhu >= 9.5 && $suhu < 10) {
			$nil_pv = 8.9;
		}else if($suhu >= 10.0 && $suhu < 10.5) {
			$nil_pv = 9.2;
		}else if($suhu >= 10.5 && $suhu < 11) {
			$nil_pv = 9.5;
		}else if($suhu >= 11.0 && $suhu < 11.5) {
			$nil_pv = 9.8;
		}else if($suhu >= 11.5 && $suhu < 12) {
			$nil_pv = 10.2;
		}else if($suhu >= 12.0 && $suhu < 12.5) {
			$nil_pv = 10.5;
		}else if($suhu >= 12.5 && $suhu < 13) {
			$nil_pv = 10.9;
		}else if($suhu >= 13.0 && $suhu < 13.5) {
			$nil_pv = 11.2;
		}else if($suhu >= 13.5 && $suhu < 14) {
			$nil_pv = 11.6;
		}else if($suhu >= 14.0 && $suhu < 14.5) {
			$nil_pv = 12.0;
		}else if($suhu >= 14.5 && $suhu < 15) {
			$nil_pv = 12.4;
		}else if($suhu >= 15.0 && $suhu < 15.5) {
			$nil_pv = 12.8;
		}else if($suhu >= 15.5 && $suhu < 16) {
			$nil_pv = 13.2;
		}else if($suhu >= 16.0 && $suhu < 16.5) {
			$nil_pv = 13.6;
		}else if($suhu >= 16.5 && $suhu < 17) {
			$nil_pv = 14.1;
		}else if($suhu >= 17.0 && $suhu < 17.5) {
			$nil_pv = 14.5;
		}else if($suhu >= 17.5 && $suhu < 18) {
			$nil_pv = 15.0;
		}else if($suhu >= 18.0 && $suhu < 18.5) {
			$nil_pv = 15.5;
		}else if($suhu >= 18.5 && $suhu < 19) {
			$nil_pv = 16.0;
		}else if($suhu >= 19.0 && $suhu < 19.5) {
			$nil_pv = 16.5;
		}else if($suhu >= 19.5 && $suhu < 20) {
			$nil_pv = 17.0;
		}else if($suhu >= 20.0 && $suhu < 20.5) {
			$nil_pv = 17.5;
		}else if($suhu >= 20.5 && $suhu < 21) {
			$nil_pv = 18.1;
		}else if($suhu >= 21.0 && $suhu < 21.5) {
			$nil_pv = 18.7;
		}else if($suhu >= 21.5 && $suhu < 22) {
			$nil_pv = 19.2;
		}else if($suhu >= 22.0 && $suhu < 22.5) {
			$nil_pv = 19.8;
		}else if($suhu >= 22.5 && $suhu < 23) {
			$nil_pv = 20.4;
		}else if($suhu >= 23.0 && $suhu < 23.5) {
			$nil_pv = 21.1;
		}else if($suhu >= 23.5 && $suhu < 24) {
			$nil_pv = 21.7;
		}else if($suhu >= 24.0 && $suhu < 24.5) {
			$nil_pv = 22.4;
		}else if($suhu >= 24.5 && $suhu < 25) {
			$nil_pv = 23.1;
		}else if($suhu >= 25.0 && $suhu < 25.5) {
			$nil_pv = 23.8;
		}else if($suhu >= 25.5 && $suhu < 26) {
			$nil_pv = 24.5;
		}else if($suhu >= 26.0 && $suhu < 26.5) {
			$nil_pv = 25.2;
		}else if($suhu >= 26.5 && $suhu < 27) {
			$nil_pv = 26.0;
		}else if($suhu >= 27.0 && $suhu < 27.5) {
			$nil_pv = 26.7;
		}else if($suhu >= 27.5 && $suhu < 28) {
			$nil_pv = 27.5;
		}else if($suhu >= 28.0 && $suhu < 28.5) {
			$nil_pv = 28.4;
		}else if($suhu >= 28.5 && $suhu < 29) {
			$nil_pv = 29.2;
		}else if($suhu >= 29.0 && $suhu < 29.5) {
			$nil_pv = 30.1;
		}else if($suhu >= 29.5 && $suhu < 30) {
			$nil_pv = 30.9;
		}else if($suhu >= 30.0 && $suhu < 30.5) {
			$nil_pv = 31.8;
		}else if($suhu >= 30.5 && $suhu < 31) {
			$nil_pv = 32.8;
		}else if($suhu >= 31.0 && $suhu < 31.5) {
			$nil_pv = 33.7;
		}else if($suhu >= 31.5 && $suhu < 32) {
			$nil_pv = 34.7;
		}else if($suhu >= 32.0 && $suhu < 32.5) {
			$nil_pv = 35.7;
		}else if($suhu >= 32.5 && $suhu < 33) {
			$nil_pv = 36.7;
		}else if($suhu >= 33.0 && $suhu < 33.5) {
			$nil_pv = 37.7;
		}else if($suhu >= 33.5 && $suhu < 34) {
			$nil_pv = 38.8;
		}else if($suhu >= 34.0 && $suhu < 34.5) {
			$nil_pv = 39.9;
		}else if($suhu >= 34.5 && $suhu < 35) {
			$nil_pv = 41.0;
		}else if($suhu >= 35.0 && $suhu < 35.5) {
			$nil_pv = 42.2;
		}else if($suhu >= 35.5 && $suhu < 36) {
			$nil_pv = 43.4;
		}else if($suhu >= 36.0 && $suhu < 36.5) {
			$nil_pv = 44.6;
		}else if($suhu >= 36.5 && $suhu < 37) {
			$nil_pv = 45.8;
		}else if($suhu >= 37.0 && $suhu < 37.5) {
			$nil_pv = 47.1;
		}else if($suhu >= 37.5 && $suhu < 38) {
			$nil_pv = 48.4;
		}else if($suhu >= 38.0 && $suhu < 38.5) {
			$nil_pv = 49.7;
		}else if($suhu >= 38.5 && $suhu < 39) {
			$nil_pv = 51.1;
		}else if($suhu >= 39.0 && $suhu < 39.5) {
			$nil_pv = 52.5;
		}else if($suhu >= 39.5 && $suhu < 40) {
			$nil_pv = 53.9;
		}else if($suhu >= 40.0 && $suhu < 40.5) {
			$nil_pv = 55.3;
		}else if($suhu >= 40.5 && $suhu < 41) {
			$nil_pv = 56.8;
		}else if($suhu >= 41.0 && $suhu < 41.5) {
			$nil_pv = 58.4;
		}else if($suhu >= 41.5 && $suhu < 42) {
			$nil_pv = 59.9;
		}else if($suhu >= 42.0 && $suhu < 42.5) {
			$nil_pv = 61.5;
		}else if($suhu >= 42.5 && $suhu < 43) {
			$nil_pv = 63.1;
		}else if($suhu >= 43.0 && $suhu < 43.5) {
			$nil_pv = 64.8;
		}else if($suhu >= 43.5 && $suhu < 44) {
			$nil_pv = 66.5;
		}else if($suhu >= 44.0 && $suhu < 44.5) {
			$nil_pv = 68.3;
		}else if($suhu >= 44.5 && $suhu < 45) {
			$nil_pv = 70.1;
		}else if($suhu >= 45.0 && $suhu < 45.5) {
			$nil_pv = 71.9;
		}else if($suhu >= 45.5 && $suhu < 46) {
			$nil_pv = 73.7;
		}else if($suhu >= 46.0 && $suhu < 46.5) {
			$nil_pv = 75.7;
		}else if($suhu >= 46.5 && $suhu < 47) {
			$nil_pv = 77.6;
		}else if($suhu >= 47.0 && $suhu < 47.5) {
			$nil_pv = 79.6;
		}else if($suhu >= 47.5 && $suhu < 48) {
			$nil_pv = 81.6;
		}else if($suhu >= 48.0 && $suhu < 48.5) {
			$nil_pv = 83.7;
		}else if($suhu >= 48.5 && $suhu < 49) {
			$nil_pv = 85.8;
		}else if($suhu >= 49.0 && $suhu < 49.5) {
			$nil_pv = 88.0;
		}else if($suhu >= 49.5 && $suhu < 50) {
			$nil_pv = 90.2;
		}else if($suhu >= 50.0 && $suhu < 50.5) {
			$nil_pv = 92.5;
		}else if($suhu >= 50.5 && $suhu < 51) {
			$nil_pv = 94.8;
		}else if($suhu >= 51.0 && $suhu < 51.5) {
			$nil_pv = 97.2;
		}else if($suhu >= 51.5 && $suhu < 52) {
			$nil_pv = 99.6;
		}else if($suhu >= 52.0 && $suhu < 52.5) {
			$nil_pv = 102.1;
		}else if($suhu >= 52.5 && $suhu < 53) {
			$nil_pv = 104.6;
		}else if($suhu >= 53.0 && $suhu < 53.5) {
			$nil_pv = 107.2;
		}else if($suhu >= 53.5 && $suhu < 54) {
			$nil_pv = 109.8;
		}else if($suhu >= 54.0 && $suhu < 54.5) {
			$nil_pv = 112.5;
		}else if($suhu >= 54.5 && $suhu < 55) {
			$nil_pv = 115.2;
		}else if($suhu >= 55.0 && $suhu < 55.5) {
			$nil_pv = 118.0;
		}else if($suhu >= 55.5 && $suhu < 56) {
			$nil_pv = 120.9;
		}else if($suhu >= 56.0 && $suhu < 56.5) {
			$nil_pv = 123.8;
		}else if($suhu >= 56.5 && $suhu < 57) {
			$nil_pv = 126.7;
		}else if($suhu >= 57.0 && $suhu < 57.5) {
			$nil_pv = 130.8;
		}else if($suhu >= 57.5 && $suhu < 58) {
			$nil_pv = 132.9;
		}else if($suhu >= 58.0 && $suhu < 58.5) {
			$nil_pv = 136.0;
		}else if($suhu >= 58.5 && $suhu < 59) {
			$nil_pv = 139.2;
		}else if($suhu >= 59.0 && $suhu < 59.5) {
			$nil_pv = 142.5;
		}else if($suhu >= 59.5 && $suhu < 60) {
			$nil_pv = 145.9;
		}else if($suhu >= 60.0 && $suhu < 60.5) {
			$nil_pv = 149.3;
		}else if($suhu >= 60.5 && $suhu < 61) {
			$nil_pv = 152.8;
		} else {
			throw new \Exception('Error karena suhu tidak sesuai, suhu di data lapangan adalah ' . $suhu);
		}

		return $nil_pv;
	}
}
