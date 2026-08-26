<?php

namespace App\Http\Controllers\api;

use App\Jobs\ApproveWsParameterJob;
use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;

use App\Models\MasterRegulasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Datatables;
use Carbon\Carbon;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganSinarUV;
use App\Models\DataLapanganMedanLM;
use App\Models\DataLapanganDebuPersonal;
use App\Models\MasterKaryawan;
use App\Models\Subkontrak;
use App\Models\MasterBakumutu;
use App\Models\Parameter;
use App\Services\AnalystFormula;

use App\Models\LingkunganHeader;
use App\Models\DirectLainHeader;
use App\Models\PartikulatHeader;
use App\Models\ErgonomiHeader;
use App\Models\SinarUvHeader;
use App\Models\MedanLmHeader;
use App\Models\DebuPersonalHeader;
use App\Models\DustFallHeader;
use App\Models\MdlUdara;

class WsFinalUdaraUdaraLingkunganHidupController extends Controller
{
	private $categoryLingkunganKerja = [11, 27];

	public function index(Request $request)
	{
		$data = OrderDetail::where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->whereIn('kategori_3', ["11-Udara Ambient"])
			->where('status', 0)
			->whereNotNull('tanggal_terima')
			->whereJsonDoesntContain('parameter', ["318;Psikologi"])
            ->when($request->filled('year'), function ($q) use ($request) {
				return $q->whereYear('tanggal_sampling', $request->year);
			})
			->orderBy('tanggal_sampling');

		$data = $data->get();
		$data = \App\Services\WsFinalApprovalService::appendProgressAndFilter($data, $request);

		return Datatables::of($data)->make(true);
	}

    public function handleDataDetail(Request $request) {
        $data = OrderDetail::where('is_active', 1)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["11-Udara Ambient"])
            ->where('status', 0)
            ->whereNotNull('tanggal_terima')
            ->where('id', $request->id)
            ->whereJsonDoesntContain('parameter', ["318;Psikologi"])
            ->first();

        return response()->json([
            'data' => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }

	public function convertHourToMinute($hour)
	{
		$minutes = $hour * 60;
		return $minutes;
	}

	public function detail(Request $request)
	{
		try {
			$parameters = json_decode(html_entity_decode($request->parameter), true);
			$parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];


			$directData = DirectLainHeader::with(['ws_udara'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', 1)
				->where('is_active', 1)
				->where('status', 0)
				->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
				->addSelect(DB::raw("'direct' as data_type"))
				->get();

			$lingkunganData = LingkunganHeader::with(['ws_udara', 'ws_value_linkungan'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approved', 1)
				->where('status', 0)
				->where('is_active', 1)
				->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
				->addSelect(DB::raw("'lingkungan' as data_type"))
				->get();
			$dustfallData = DustFallHeader::with(['ws_udara'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approved', 1)
				->where('is_active', 1)
				->where('status', 0)
				->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
				->addSelect(DB::raw("'dustfall' as data_type"))
				->get();
			$subkontrak = Subkontrak::with(['ws_udara'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', 1)
				->where('is_active', 1)
				->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
				->addSelect(DB::raw("'subKontrak' as data_type"))
				->get();
			$partikulat = PartikulatHeader::with(['ws_udara'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', 1)
				->where('is_active', 1)
				->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
				->addSelect(DB::raw("'partikulat' as data_type"))
				->get();



			$combinedData = collect()
				->merge($lingkunganData)
				->merge($dustfallData)
				->merge($subkontrak)
				->merge($directData)
				->merge($partikulat);


			$processedData = $combinedData->map(function ($item) {
				switch ($item->data_type) {
					case 'lingkungan':
						$item->source = 'Lingkungan';
						break;
					case 'subKontrak':
						$item->source = 'Subkontrak';
						break;
					case 'direct':
						$item->source = 'Direct Lain';
						break;
					case 'partikulat':
						$item->source = 'Partikulat';
						break;
					case 'dustfall':
						$item->source = 'Dust Fall';
						break;
				}
				return $item;
			});
			$id_regulasi = $request->regulasi;
			foreach ($processedData as $item) {

				$dataLapangan = DetailLingkunganHidup::where('no_sampel', $item->no_sampel)
					->select('durasi_pengambilan')
					->where('parameter', $item->parameter)
					->first();
				$bakuMutu = MasterBakumutu::where("parameter", $item->parameter)
					->where('id_regulasi', $id_regulasi)
					->where('is_active', 1)
					->select('baku_mutu', 'satuan', 'method', 'nama_header')
					->first();
				// dd($bakuMutu,  $item->id_parameter, $id_regulasi);
				$item->durasi = $dataLapangan->durasi_pengambilan ?? null;
				$item->satuan = $bakuMutu->satuan ?? null;
				$item->baku_mutu = $bakuMutu->baku_mutu ?? null;
				$item->method = $bakuMutu->method ?? null;
				$item->nama_header = $bakuMutu->nama_header ?? null;
				// dd($bakuMutu);
			}

            $getSatuan = new HelperSatuan;

            $parameters = collect(json_decode($request->parameter))->map(fn($item) => ['id' => explode(";", $item)[0], 'parameter' => explode(";", $item)[1]]);
            $mdlUdara = MdlUdara::whereIn('parameter_id', $parameters->pluck('id'))->get();

            $getHasilUji = function ($index, $parameterId, $hasilUji) use ($mdlUdara) {
                if ($hasilUji && $hasilUji !== "-" && !str_contains($hasilUji, '<')) {
                    $colToSearch = "hasil" . ($index ?: 1);
                    $mdlUdara = $mdlUdara->where('parameter_id', $parameterId)->whereNotNull($colToSearch)->first();
                    if ($mdlUdara && (float) $mdlUdara->$colToSearch > (float) $hasilUji) {
                        $hasilUji = "<" . $mdlUdara->$colToSearch;
                    }
                }

                return $hasilUji;
            };

			$parameterHasKoreksi = [
				"SO2",
				"SO2 (6 Jam)",
				"SO2 (8 Jam)",
				"SO2 (24 Jam)",
				"NO2",
				"NO2 (6 Jam)",
				"NO2 (8 Jam)",
				"NO2 (24 Jam)",
				"O3",
				"O3 (8 Jam)",
				"TSP",
				"TSP (6 Jam)",
				"TSP (24 Jam)",
				"TSP (8 Jam)",
				"PM 10",
				"PM 10 (8 Jam)",
				"PM 10 (24 Jam)",
				"PM 2.5 (8 Jam)",
				"PM 2.5 (24 Jam)",
				"PM 2.5",
				"CO",
				"C O",
				"CO (24 Jam)",
				"CO (8 Jam)",
				"CO (6 Jam)",
			];

			return Datatables::of($processedData)
				->addColumn('nilai_uji', function ($item) use ($getSatuan, $getHasilUji) {
					// ambil satuan dan index (boleh null)
					$satuan = $item->satuan ?? null;
					$index  = $getSatuan->udara($satuan);

					// pilih sumber hasil: ws_udara dulu, kalau ga ada pakai ws_value_linkungan
					$source = $item->ws_udara ?? $item->ws_value_linkungan ?? null;
					if (!$source) return 'noWs';

					// pastikan array
					$hasil = is_array($source) ? $source : $source->toArray();
					// helper kecil: cek tersedia dan tidak kosong
					$has = function ($key) use ($hasil) {
						return isset($hasil[$key]) && $hasil[$key] !== null && $hasil[$key] !== '';
					};

					// jika index tidak diketahui, coba serangkaian fallback (dari paling prioritas ke paling umum)
					if ($index === null) {
						// 1) f_koreksi_c (tanpa nomor) lalu f_koreksi_c1..f_koreksi_c16
						if ($has('f_koreksi_c')) return $getHasilUji(1, $item->id_parameter, $hasil['f_koreksi_c']);

						for ($i = config('column_ws.ws_value_lingkungan.min'); $i <= config('column_ws.ws_value_lingkungan.max'); $i++) {
							$k = "f_koreksi_c{$i}";
							if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
						}


						// 2) C (tanpa nomor) lalu C1..C16
						if ($has('C')) return $hasil['C'];
						for ($i = config('column_ws.ws_value_lingkungan.min'); $i <= config('column_ws.ws_value_lingkungan.max'); $i++) {
							$k = "C{$i}";
							if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
						}

						// 3) f_koreksi_1..f_koreksi_17
						for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
							$k = "f_koreksi_{$i}";
							if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
						}

						// 4) hasil1..hasil17
						for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
							$k = "hasil{$i}";
							if ($has($k)) return $getHasilUji(1, $item->id_parameter, $hasil[$k]);
						}

						// kalau semua gagal
						return '-';
					}

					$CIndex = $index == 1 ? '' : $index - 1;
					// bila index diketahui, cek urutan preferensi khusus index itu
					$keysToTry = [
						"f_koreksi_c{$index}", // contoh: f_koreksi_c3
						"C{$CIndex}",           // contoh: C3
						"f_koreksi_{$index}",  // contoh: f_koreksi_3
						"hasil{$index}"
					];

					if ($index == 17) {
						foreach ($keysToTry as $k) {
							if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
						foreach (['f_koreksi_c2', 'C2', 'f_koreksi_2', 'hasil2'] as $k) {
							if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
					} if ($index == 15) {
						foreach ($keysToTry as $k) {
							if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
						foreach (['f_koreksi_c3', 'C3', 'f_koreksi_3', 'hasil3'] as $k) {
							if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
					} if ($index == 16) {
						foreach ($keysToTry as $k) {
							if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
						foreach (['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'] as $k) {
							if ($has($k) && $hasil[$k]) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
					} else {
						foreach ($keysToTry as $k) {
							if ($has($k) && isset($hasil[$k])) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
						foreach (['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'] as $k) {
							if ($has($k) && isset($hasil[$k])) return $getHasilUji($index, $item->id_parameter, $hasil[$k]);
						}
					}

					return '-';
				})->addColumn("koreksi_udara", function($item) use ($parameterHasKoreksi) {
					return $parameterHasKoreksi;
				})->addColumn("index_satuan", function($item) use ($getSatuan) {
					$satuan = $item->satuan ?? null;
					return $getSatuan->udara($satuan);
				})
				->make(true);
		} catch (\Throwable $th) {
			return response()->json([
				'message' => $th->getMessage(),
			], 401);
		}
	}

	public function detailLapangan(Request $request)
	{
		$parameterNames = [];

		if (is_array($request->parameter)) {
			foreach ($request->parameter as $param) {
				$paramParts = explode(";", $param);
				if (isset($paramParts[1])) {
					$parameterNames[] = trim($paramParts[1]);
				}
			}
		}
		if ($request->kategori == 11) {
			$noOrder = explode('/', $request->no_sampel)[0] ?? null;
			$Lapangan = OrderDetail::where('no_order', $noOrder)->get();
			$lapangan2 = $Lapangan->map(function ($item) {
				return $item->no_sampel;
			})->unique()->sortBy(function ($item) {
				return (int) explode('/', $item)[1];
			})->values();

			$totLapangan = $lapangan2->count();
			try {
				$data = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->first();

				if ($data) {
					return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['data' => [], 'message' => 'Data tidak ditemukan', 'success' => false, 'status' => 404]);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if ($request->kategori == 27) {
			// $parameters = json_decode(html_entity_decode($request->parameter), true);


			try {
				$noOrder = explode('/', $request->no_sampel)[0] ?? null;
				$Lapangan = OrderDetail::where('no_order', $noOrder)->get();
				$lapangan2 = $Lapangan->map(function ($item) {
					return $item->no_sampel;
				})->unique()->sortBy(function ($item) {
					return (int) explode('/', $item)[1];
				})->values();
				$totLapangan = $lapangan2->count();
				// Cek apakah 'Ergonomi' ada dalam array
				if (in_array("Ergonomi", $parameterNames)) {

					$data = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->first();
					$urutan = $lapangan2->search($data->no_sampel);
					$urutanDisplay = $urutan + 1;
					$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
					if ($data) {
						$dataArray = $data->toArray();
						$dataArray['parameter'] = 'Ergonomi';

						return response()->json([
							'data' => $dataArray,
							'message' => 'Berhasil mendapatkan data',
							'success' => true,
							'status' => 200
						]);
					}
				} else if (in_array("Sinar UV", $parameterNames)) {
					$data = DataLapanganSinarUV::where('no_sampel', $request->no_sampel)->first();
					$urutan = $lapangan2->search($data->no_sampel);
					$urutanDisplay = $urutan + 1;
					$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
					if ($data) {
						$dataArray = $data->toArray();
						$dataArray['parameter'] = 'Sinar UV';

						return response()->json([
							'data' => $dataArray,
							'message' => 'Berhasil mendapatkan data',
							'success' => true,
							'status' => 200
						]);
					}
				} else if (in_array("Debu (P8J)", $parameterNames)) {
					$data = DataLapanganDebuPersonal::where('no_sampel', $request->no_sampel)->first();


					if ($data) {
						$dataArray = $data->toArray();
						$dataArray['parameter'] = 'Debu (P8J)';

						return response()->json([
							'data' => $dataArray,
							'message' => 'Berhasil mendapatkan data',
							'success' => true,
							'status' => 200
						]);
					}
				} else if (in_array('Medan Magnit Statis', $parameterNames) || in_array('Medan Listrik', $parameterNames) || in_array('Power Density', $parameterNames)) {

					$data = DataLapanganMedanLM::where('no_sampel', $request->no_sampel)->first();
					$urutan = $lapangan2->search($data->no_sampel);
					$urutanDisplay = $urutan + 1;
					$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
					if ($data) {
						$dataArray = $data->toArray();
						switch (true) {
							case in_array('Medan Magnit Statis', $parameterNames):
								$dataArray['parameter'] = 'Medan Magnit Statis';
								break;
							case in_array('Medan Listrik', $parameterNames):
								$dataArray['parameter'] = 'Medan Listrik';
								break;
							case in_array('Power Density', $parameterNames):
								$dataArray['parameter'] = 'Power Density';
								break;
						}


						return response()->json([
							'data' => $dataArray,
							'message' => 'Berhasil mendapatkan data',
							'success' => true,
							'status' => 200
						]);
					}
				} else {
					$data = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->first();
					if ($data) {
						return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
					} else {
						return response()->json(['message' => 'Data lapangan tidak ditemukan', 'success' => false, 'status' => 404]);
					}
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else {
			$data = [];
		}
	}

	public function rejectAnalys(Request $request)
	{
		try {
			if (in_array($request->kategori, $this->categoryLingkunganKerja)) {
				if ($request->data_type == 'lingkungan') {
					// Update data for 'lingkungan'
					$data = LingkunganHeader::where('id', $request->id)->update([
						'is_approved' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				} else if ($request->data_type == 'subKontrak') {
					$data = Subkontrak::where('id', $request->id)->update([
						'is_approve' => 0,
						'is_active' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				} else if ($request->data_type == 'direct') {
					// Update data for 'direct'
					$data = DirectLainHeader::where('id', $request->id)->update([
						'is_approve' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				} else if ($request->data_type == 'medan_lm') {
					// Update data for 'direct'
					$data = MedanLmHeader::where('id', $request->id)->update([
						'is_approve' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				} else if ($request->data_type == 'debu_personal') {
					// Update data for 'direct'
					$data = DebuPersonalHeader::where('id', $request->id)->update([
						'is_approved' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				}else if ($request->data_type == 'dustfall') {
					// Update data for 'direct'
					$data = DustFallHeader::where('id', $request->id)->update([
						'is_approved' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				}else if ($request->data_type == 'sinar_uv') {
					// Update data for 'direct'
					$data = SinarUvHeader::where('id', $request->id)->update([
						'is_approve' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				} else {
					// If neither 'lingkungan' nor 'direct', return an error message
					return response()->json(['message' => 'Invalid data_type provided.'], 400);
				}

				if ($data) {
					return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['message' => 'Gagal', 'success' => false, 'status' => 400]);
				}
			} else {
				$data = [];
			}
		} catch (\Exception $ex) {
			dd($ex);
		}
	}

	public function approveWSApi(Request $request)
	{
		$user = $request->attributes->get('user');
		$karyawan = ($user && isset($user->karyawan) && $user->karyawan)
			? $user->karyawan->nama_lengkap
			: $request->header('token');

		if ($request->id) {
			if (in_array($request->kategori, $this->categoryLingkunganKerja)) {
				if ($request->data_type == 'lingkungan') {
					$data = LingkunganHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
					// dd($data);
					if ($data) {
						$cek = LingkunganHeader::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = LingkunganHeader::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else if ($request->data_type == 'subKontrak') {

					$data = Subkontrak::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();


					if ($data) {
						$cek = Subkontrak::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = Subkontrak::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else if ($request->data_type == 'direct') {
					$data = DirectLainHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
					// dd($data);
					if ($data) {
						$cek = DirectLainHeader::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = DirectLainHeader::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else if ($request->data_type == 'medan_lm') {
					$data = MedanLmHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
					// dd($data);
					if ($data) {
						$cek = MedanLmHeader::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = MedanLmHeader::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else if ($request->data_type == 'debu_personal') {
					$data = DebuPersonalHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
					// dd($data);
					if ($data) {
						$cek = DebuPersonalHeader::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = DebuPersonalHeader::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else if ($request->data_type == 'partikulat') {
					$data = PartikulatHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
					// dd($data);
					if ($data) {
						$cek = PartikulatHeader::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = PartikulatHeader::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else if ($request->data_type == 'dustfall') {
					$data = DustFallHeader::where('parameter', $request->parameter)
						->where('lhps', 1)
						->where('no_sampel', $request->no_sampel)
						->first();

					if ($data) {
						$cek = DustFallHeader::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();

						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$dat = DustFallHeader::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();

						dispatch(new ApproveWsParameterJob($request->all(), $karyawan));

						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
				} else {
					$data = SinarUvHeader::where('parameter', $request->parameter)
						->where('lhps', 1)
						->where('no_sampel', $request->no_sampel)
						->first();
					$ws = WsValueUdara::where('no_sampel', $request->no_sampel)
						->first();
					if ($data) {

						$data->update([
							'lhps' => 0
						]);
					} else {
						$dat = SinarUvHeader::where('id', $request->id)->first()
							->update([
								'lhps' => 1
							]);
					}
					if ($ws) {
						$ws->nab = $request->nab;
						$ws->save();
					}
					return response()->json([
						'message' => 'Data has ben Updated',
						'success' => true,
						'status' => 201,
					], 201);
				}
			} else {
				return response()->json([
					'message' => 'Gagal Approve : Data tidak termasuk kategori lingkungan hidup',
				], 404);
			}
		} else {
			return response()->json([
				'message' => 'Gagal Approve',
				'status' => 401,
			], 401);
		}
	}

	public function AddSubKontrak(Request $request)
	{
		$allowedGrades = ['MANAGER', 'SENIOR MANAGER', 'DIRECTOR'];
		if (!$this->grade || !in_array(strtoupper($this->grade), $allowedGrades)) {
			return response()->json([
				'message' => 'Akses ditolak. Hanya MANAGER, SENIOR MANAGER, dan DIRECTOR yang dapat menambah data.',
				'success' => false,
				'status' => 403,
			], 403);
		}

		DB::beginTransaction();
		try {
			$orderDetail = OrderDetail::where('no_sampel', $request->no_sampel)
				->where('is_active', true)
				->first();

			if (!$orderDetail) {
				return response()->json([
					'message' => 'No Sample tidak ditemukan.',
					'success' => false,
					'status' => 404,
				], 404);
			}

			$categoryId = $request->category ?? 4;

			$dataParameter = Parameter::where('nama_lab', $request->parameter)
				->where('id_kategori', $categoryId)
				->where('is_active', true)
				->first();

			if (!$dataParameter) {
				return response()->json([
					'message' => 'Parameter tidak ditemukan.',
					'success' => false,
					'status' => 404,
				], 404);
			}

			$dataParsing = (object) [
				'hp' => $request->hp,
				'fp' => $request->fp ?? 1,
				'parameter' => $request->parameter,
				'no_sample' => $request->no_sampel,
			];

			$dataKalkulasi = AnalystFormula::where('function', 'OthersSubkontrak')
				->where('data', $dataParsing)
				->where('id_parameter', $dataParameter->id)
				->process();

			if (!is_array($dataKalkulasi) && $dataKalkulasi == 'Coming Soon') {
				return response()->json([
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter,
					'success' => false,
					'status' => 404,
				], 404);
			}

			$exist = Subkontrak::where('no_sampel', trim($request->no_sampel))
				->where('category_id', $categoryId)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->first();

			if (isset($exist->id)) {
				$data = Subkontrak::find($exist->id);
				WsValueUdara::where('id_subkontrak', $data->id)
					->where('is_active', true)
					->update(['is_active' => false]);
			} else {
				$data = new Subkontrak();
				$data->created_by = $this->karyawan;
				$data->created_at = Carbon::now()->format('Y-m-d H:i:s');
			}

			$data->no_sampel = trim($request->no_sampel);
			$data->category_id = $categoryId;
			$data->parameter = $request->parameter;
			$data->jenis_pengujian = $request->jenis_pengujian ?? 'sample';
			$data->hp = $request->hp;
			$data->fp = $request->fp ?? null;
			$data->note = $request->keterangan ?? $request->note ?? null;
			$data->is_active = true;
			$data->is_approve = 1;
			$data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
			$data->approved_by = $this->karyawan;
			$data->save();

			$dataUdara = [
				'id_subkontrak' => $data->id,
				'no_sampel' => trim($request->no_sampel),
				'is_active' => true,
			];

			for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
				$dataUdara['f_koreksi_' . $i] = $dataKalkulasi['hasil'];
			}

			WsValueUdara::create($dataUdara);

			DB::commit();
			return response()->json([
				'message' => 'Data berhasil disimpan dan di-approve oleh ' . $this->karyawan,
				'success' => true,
				'status' => 200,
			], 200);
		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'success' => false,
				'status' => 401,
			], 401);
		}
	}

	public function validasiApproveWSApi(Request $request)
	{
		$result = \App\Services\WsFinalApprovalService::validateAndApprove($request->all(), $this->karyawan);

		return response()->json([
			'message' => $result['message'],
			'status'  => $result['status'],
			'success' => $result['success'] ?? false,
		], $result['status']);
	}

	public function KalkulasiKoreksi(Request $request)
	{
		try {
			$id = $request->id;
			$no_sampel = $request->no_sampel;
			$parameter = $request->parameter;
			$faktor_koreksi = (float) $request->faktor_koreksi;
			
			// 1. Ambil index_satuan yang dikirim dari frontend (contoh: 1, 2, 3...)
			$index_satuan = $request->index_satuan;

			// 2. Tentukan nama key dinamis berdasarkan index_satuan untuk mengambil datanya.
			// Jika index_satuan = 1, maka key-nya 'hasil1'. Jika 2, maka 'hasil2'.
			$keyToFetch = 'hasil' . $index_satuan;
			$inputValue = html_entity_decode($request->$keyToFetch ?? '');

			// 3. Kalkulasi HANYA untuk satu nilai (spesifik pada index_satuan tersebut)
			$hasilKalkulasi = $this->hitungKoreksi($request, $no_sampel, $faktor_koreksi, $parameter, $inputValue, $index_satuan);

			// 4. Format hasil menjadi 4 angka di belakang koma (jika bernilai numerik / angka murni)
			if (is_numeric($hasilKalkulasi)) {
				$hasilKalkulasi = number_format((float)$hasilKalkulasi, 4, '.', '');
			}

			// 5. Kembalikan respons JSON.
			// Memasukkan datanya ke key dinamis agar frontend bisa membaca otomatis `hasil['hasil1']`
			return response()->json([
				'hasil' => [
					$keyToFetch => $hasilKalkulasi
				]
			]);
		} catch (\Exception $e) {
			\Log::error('Error dalam KalkulasiKoreksi: ' . $e->getMessage());
			return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
		}
	}

	private function hitungKoreksi($request, $no_sampel, $faktor_koreksi, $parameter, $inputValue, $index_satuan)
	{
		try {
			return $this->rumusUdara($request, $no_sampel, $faktor_koreksi, $parameter, $inputValue, $index_satuan);
		} catch (\Exception $e) {
			\Log::error('Error dalam hitungKoreksi: ' . $e->getMessage());
			throw $e;
		}
	}

	public function rumusUdara($request, $no_sampel, $faktor_koreksi, $parameter, $inputValue, $index_satuan)
	{
		$po = OrderDetail::where('no_sampel', $no_sampel)
			->where('is_active', 1)
			->where('parameter', 'like', '%' . $parameter . '%')
			->first();
			
		try {
			// Jika input kosong, null, atau strip '-', langsung hentikan dan kembalikan null
			if ($inputValue === null || $inputValue === '' || $inputValue === '-') {
				return null;
			}

			// Membersihkan karakter '<' agar bisa di-convert menjadi float untuk dikalkulasi
			$cleaned = is_string($inputValue) ? str_replace('<', '', $inputValue) : $inputValue;
			
			// Jika ternyata datanya tidak valid sebagai angka, kembalikan null
			$num = floatval($cleaned);
			if (is_nan($num)) return null;

			// Cek apakah data awal (sebelum dibersihkan) mengandung karakter '<'
			$cekSpecialChar = is_string($inputValue) && strpos($inputValue, '<') !== false;
			
			// Terapkan rumus persentase berdasarkan keberadaan karakter khusus '<'
			if ($cekSpecialChar) {
				$result = (($num / 0.3856) * ($faktor_koreksi / 100)) + ($num / 0.3856);
			} else {
				$result = ($num * ($faktor_koreksi / 100)) + $num;
			}

			/* 
			 * BATAS DETEKSI (LIMIT) SPESIFIK PARAMETER BERDASARKAN index_satuan
			 * -----------------------------------------------------------------
			 * Pemetaan index_satuan terhadap C (ws_lingkungan lama):
			 * index_satuan = 1  => (dulu: C / hasilc)
			 * index_satuan = 2  => (dulu: C1 / hasilc1)
			 * index_satuan = 3  => (dulu: C2 / hasilc2)
			 * index_satuan = 16 => (dulu: C15 / hasilc15)
			 * index_satuan = 17 => (dulu: C16 / hasilc16)
			 */

			// Kondisi spesifik parameter O3
			if (in_array($parameter, ['O3', 'O3 (8 Jam)'])) {
				if ($index_satuan == 1 && $result < 0.1419) $result = '<0.1419';
				if ($index_satuan == 2 && $result < 0.00014) $result = '<0.00014';
				if ($index_satuan == 3 && $result < 0.00007) $result = '<0.00007';
			}

			// Kondisi spesifik parameter CO
			if (in_array($parameter, ['C O', 'CO', 'CO (8 Jam)', 'CO (24 Jam)', 'CO (6 Jam)'])) {
				if ($index_satuan == 3 && $result < 0.01) $result = '<0.01';
				if ($index_satuan == 16 && $result < 11.45) $result = '<11.45';
				if ($index_satuan == 17 && $result < 0.01145) $result = '<0.01145';
			}

			// Kondisi spesifik parameter SO2
            if (in_array($parameter, ['SO2', 'SO2 (6 Jam)', 'SO2 (8 Jam)', 'SO2 (24 Jam)'])) {
				if ($index_satuan == 1 && $result < 25.91) $result = '<25.91';
				if ($index_satuan == 3 && $result < 0.00082) $result = '<0.00082';
				if ($index_satuan == 17 && $result < 0.0259) $result = '<0.0259';
			}

			// Kondisi spesifik parameter NO2
            if (in_array($parameter, ['NO2', 'NO2 (6 Jam)', 'NO2 (8 Jam)', 'NO2 (24 Jam)'])) {
				if ($index_satuan == 1 && $result < 5.83) $result = '<5.83';
				if ($index_satuan == 3 && $result < 0.00025) $result = '<0.00025';
				if ($index_satuan == 17 && $result < 0.00583) $result = '<0.00583';
			}

			return $result;
		} catch (\Exception $e) {
			\Log::error('Error in rumusUdara: ' . $e->getMessage());
			return null;
		}
	}

	// ========================
	// 🔽 SAVE DATA SECTION 🔽
	// ========================

	public function saveData(Request $request)
	{
		$kategori_koreksi = $request->kategori;
		$id = $request->id;
		$no_sampel = $request->no_sampel;
		$parameter = $request->parameter;
		$faktor_koreksi = (float)$request->faktor_koreksi;
		
		// 1. Ambil index_satuan dari payload
		$index_satuan = $request->index_satuan;

		// 2. Ambil nilai hasil koreksi sesuai key yang dinamis
		// Contoh: Jika index_satuan = 1, maka bacanya dari $request->f_koreksi_1
		$keyToFetch = 'f_koreksi_' . $index_satuan;
		$hasilKoreksi = $request->$keyToFetch ?? null;

		if ($kategori_koreksi) {
			switch ($kategori_koreksi) {
				case '11':
				case '27':
					return $this->handleLingkungan($request, $no_sampel, $parameter, $hasilKoreksi, $faktor_koreksi, $index_satuan);
				default:
					return response()->json(['message' => 'Type koreksi tidak valid.'], 400);
			}
		} else {
			return response()->json(['message' => 'Type koreksi harus diisi.'], 400);
		}
	}

	private function handleLingkungan($request, $no_sampel, $parameter, $hasilKoreksi, $faktor_koreksi, $index_satuan)
	{
		try {
			DB::beginTransaction();

			$po = OrderDetail::where('no_sampel', $no_sampel)
				->where('is_active', 1)
				->where('parameter', 'like', '%' . $parameter . '%')
				->first();

			if (!$po) {
				return response()->json(['message' => 'Data tidak ditemukan di kategori Udara.'], 404);
			}

			$lingkungan = LingkunganHeader::where('no_sampel', $no_sampel)
				->where('parameter', $parameter)
				->where('is_active', 1)
				->first();

			if (!$lingkungan) {
				return response()->json(['message' => 'Data Lingkungan tidak ditemukan.'], 404);
			}

			// Ambil WsValueUdara (target utama penyimpanan)
			$wsUdara = WsValueUdara::where('no_sampel', $no_sampel)
				->where('id_lingkungan_header', $lingkungan->id)
				->where('is_active', 1)
				->first();

			if (!$wsUdara) {
				return response()->json(['message' => 'Data pada ws final tidak ditemukan.'], 404);
			}
			
			// Ambil WsValueLingkungan (juga di-update untuk sinkronisasi)
			// $valuews = WsValueLingkungan::where('no_sampel', $no_sampel)
			// 	->where('lingkungan_header_id', $lingkungan->id)
			// 	->where('is_active', 1)
			// 	->first();

			// Update attempt flag di lingkungan header
			$nomor = $lingkungan->tipe_koreksi ? ($lingkungan->tipe_koreksi < 5 ? $lingkungan->tipe_koreksi + 1 : 5) : 1;
			if ($nomor > 5) {
				return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.'], 400);
			}
			$lingkungan->tipe_koreksi = $nomor;
			$lingkungan->input_koreksi = $faktor_koreksi;
			$lingkungan->save();

			// 3. Format nilainya
			$val = $hasilKoreksi;
			if ($val === '-' || $val === '' || $val === null) {
				$val = null;
			} elseif (!str_contains((string)$val, '<') && is_numeric($val)) {
				$val = number_format((float)$val, 4, '.', '');
			}

			// 4. Update WsValueUdara HANYA untuk kolom f_koreksi_... sesuai index_satuan
			// Contoh: jika index_satuan 1 -> kolom f_koreksi_1
			$colUdara = 'f_koreksi_' . $index_satuan; 
			$wsUdara->$colUdara = $val;
			$wsUdara->save();

			// 5. Update WsValueLingkungan untuk kolom f_koreksi_c... (menyesuaikan index lama)
			// if ($valuews) {
			// 	// index_satuan (1, 2, 3...) ke index WsValueLingkungan (0, 1, 2...)
			// 	$indexLingkungan = $index_satuan - 1;
			// 	$colLingkungan = $indexLingkungan === 0 ? 'f_koreksi_c' : 'f_koreksi_c' . $indexLingkungan;
				
			// 	$valuews->$colLingkungan = $val;
			// 	$valuews->input_koreksi = $faktor_koreksi;
			// 	$valuews->save();
			// }

			DB::commit();
			return response()->json(['message' => 'Data berhasil diupdate.', 'status' => 200, "success" => true], 200);
		} catch (\Exception $ex) {
			DB::rollBack();
			\Log::error('Error dalam handleLingkungan: ' . $ex->getMessage());
			return response()->json(['message' => 'Terjadi kesalahan: ' . $ex->getMessage()], 500);
		}
	}

	public function getKaryawan(Request $request)
	{
		return MasterKaryawan::where('is_active', true)->get();
	}

	public function updateTindakan(Request $request)
	{

		try {

			$data = WsValueUdara::where('id', $request->id)->first();
			$data->tindakan = $request->tindakan;
			$data->save();

			return response()->json([
				'message' => 'Data berhasil diupdate.',
				'status' => 200
			]);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 401
			], 401);
		}
	}
	public function updateBagianTubuh(Request $request)
	{
		try {
			$data = MedanLmHeader::where('id', $request->id)->first();
			$data->bagian_tubuh = $request->bag_tubuh;
			$data->save();

			return response()->json([
				'message' => 'Data berhasil diupdate.',
				'status' => 200
			]);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 401
			], 401);
		}
	}

	public function updateNab(Request $request)
	{
		try {
			if (
				(in_array($request->kategori, $this->categoryLingkunganKerja) && $request->parameter == 'Sinar UV') ||
				(in_array($request->kategori, $this->categoryLingkunganKerja) && $request->parameter == 'Medan Magnit Statis') ||
				(in_array($request->kategori, $this->categoryLingkunganKerja) && $request->parameter == 'Medan Listrik') ||
				(in_array($request->kategori, $this->categoryLingkunganKerja) && $request->parameter == 'Power Density')
			) {
				$data = WsValueUdara::where('id', $request->id)->first();

				$data->nab = $request->nab;
				$data->save();

				return response()->json([
					'message' => 'Data berhasil diupdate.',
					'status' => 200
				]);
			}
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 401
			], 401);
		}
	}
	public function getRegulasi(Request $request)
	{
		$data = MasterRegulasi::where('id_kategori', $request->id_kategori)
			->where('is_active', '1')->get();

		return response()->json([
			'data' => $data
		]);
	}
	public function ubahRegulasi(Request $request)
	{
		DB::beginTransaction();
		try {
			$regulasi = MasterRegulasi::where('id', $request->regulasi)->first();
			$new_regulasi = [$request->regulasi . '-' . $regulasi->peraturan];
			$data = OrderDetail::where('id', $request->id)->first();
			$data->regulasi = $new_regulasi;
			$data->save();
			DB::commit();
			return response()->json([
				'success' => true,
				'message' => 'Regulasi berhasil diubah!'
			], 200);
		} catch (\Throwable $th) {
			DB::rollback();
			throw $th;
		}
	}

	public function updateNilaiUji(Request $request)
	{
		DB::beginTransaction();
		try {
			$wsList = WsValueUdara::where('no_sampel', $request->no_sampel)->get();

			if ($wsList->isEmpty()) {
				return response()->json([
					'message' => 'Data WsValueUdara tidak ditemukan.'
				], 404);
			}

			$headerMap = [
				'id_direct_lain_header'   => DirectLainHeader::class,
				'id_lingkungan_header'    => LingkunganHeader::class,
				'id_partikulat_header'    => PartikulatHeader::class,
				'id_debu_personal_header' => DebuPersonalHeader::class,
				'id_dustfall_header'      => DustfallHeader::class,
			];

			/**
			 * =====================================================
			 * 1️⃣ LOOP SEMUA WS UDARA
			 * =====================================================
			 */
			foreach ($wsList as $wsUdara) {

				// 🔹 CEK SUBKONTRAK
				if ($wsUdara->id_subkontrak) {
					$valid = Subkontrak::where('id', $wsUdara->id_subkontrak)
						->where('parameter', $request->parameter)
						->exists();

					if ($valid) {
						for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
							$wsUdara->{"f_koreksi_$i"} = $request->nilai_uji;
						}
						$wsUdara->save();

						DB::commit();
						return response()->json([
							'success' => true,
							'message' => 'Hasil berhasil direplace'
						]);
					}
				}

				// 🔹 CEK HEADER
				foreach ($headerMap as $field => $model) {
					if ($wsUdara->$field) {
						$valid = $model::where('id', $wsUdara->$field)
							->where('parameter', $request->parameter)
							->exists();

						if ($valid) {
							for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
								$wsUdara->{"f_koreksi_$i"} = $request->nilai_uji;
							}
							$wsUdara->save();

							DB::commit();
							return response()->json([
								'success' => true,
								'message' => 'Hasil berhasil direplace'
							]);
						}
					}
				}
			}

			/**
			 * =====================================================
			 * 2️⃣ JIKA TIDAK ADA YANG MATCH → BUAT SUBKONTRAK BARU
			 * =====================================================
			 */
			$subkontrak = Subkontrak::create([
				'no_sampel'   => $request->no_sampel,
				'parameter'   => $request->parameter,
				'created_by'  => $this->karyawan,
				'category_id' => 4,
				'is_approve'  => 1,
				'approved_by' => $this->karyawan,
				'approved_at' => now(),
			]);

			// pakai WS pertama sebagai target
			$wsUdara = $wsList->first();

			foreach ($headerMap as $field => $model) {
				$wsUdara->$field = null;
			}

			$wsUdara->id_subkontrak = $subkontrak->id;

			for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
				$wsUdara->{"f_koreksi_$i"} = $request->nilai_uji;
			}

			$wsUdara->save();

			DB::commit();
			return response()->json([
				'success' => true,
				'message' => 'Hasil berhasil disimpan ke subkontrak baru'
			]);

		} catch (\Throwable $e) {
			DB::rollBack();
			\Log::error($e);

			return response()->json([
				'success' => false,
				'message' => 'Terjadi kesalahan'
			], 500);
		}
	}

}