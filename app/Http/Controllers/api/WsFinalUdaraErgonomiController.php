<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Datatables;
use Carbon\Carbon;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\DataLapanganErgonomi;
use App\Models\MasterKaryawan;
use App\Models\WsValueErgonomi;
use App\Models\ErgonomiHeader;

class WsFinalUdaraErgonomiController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->whereIn('kategori_3', ['53-Ergonomi', '27-Udara Lingkungan Kerja'])
			->whereJsonContains('parameter', "230;Ergonomi")
			->where('status', 0)
			->whereNotNull('tanggal_terima')
			->whereJsonDoesntContain('parameter', ["318;Psikologi"])
			->whereMonth('tanggal_sampling', explode('-', $request->date)[1])
			->whereYear('tanggal_sampling', explode('-', $request->date)[0])
			->orderBy('id', "desc");

		return Datatables::of($data)->make(true);
	}

	public function convertHourToMinute($hour)
	{
		$minutes = $hour * 60;
		return $minutes;
	}

	public function detail(Request $request)
	{
		try {
			$data = ErgonomiHeader::with('datalapangan', 'ws_value_ergonomi')
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', true)
				->where('is_active', true)
				->select('*')
				->addSelect(DB::raw("'ergonomi' as data_type"))
				->get();
			foreach ($data as $key => $value) {
				if ($value->ws_value_ergonomi) {
					$value->datalapangan->pengukuran = json_decode($value->ws_value_ergonomi->pengukuran);
				} else {
					$value->datalapangan->pengukuran = json_decode($value->datalapangan->pengukuran);
				}
			}
			return Datatables::of($data)->make(true);
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

				// $urutan = $lapangan2->search($data->no_sampel);
				// $urutanDisplay = $urutan + 1;
				// $data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
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
		} else if (in_array($request->kategori, $this->categoryKebisingan)) {
			$noOrder = explode('/', $request->no_sampel)[0] ?? null;

			$Lapangan = OrderDetail::where('no_order', $noOrder)->get();

			$lapangan2 = $Lapangan->map(function ($item) {
				return $item->no_sampel;
			})->unique()->sortBy(function ($item) {
				return (int) explode('/', $item)[1];
			})->values();

			$totLapangan = $lapangan2->count();

			try {
				$data = [];
				$model = in_array("Kebisingan (P8J)", $parameterNames)
					? DataLapanganKebisinganPersonal::class
					: DataLapanganKebisingan::class;

				$data = $model::where('no_sampel', $request->no_sampel)->first();

				$urutan = $lapangan2->search($data->no_sampel);
				$urutanDisplay = $urutan + 1;
				$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
				$data['parameter'] = $parameterNames[0];
				// dd([
				// 	'no_order' => $noOrder,
				// 	'total_lapangan' => $totLapangan,
				// 	'lapangan' => $lapangan2,
				// 	'data' => $data,
				// ]);

				if ($data) {
					return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if (in_array($request->kategori, $this->categoryPencahayaan)) {
			$noOrder = explode('/', $request->no_sampel)[0] ?? null;
			$Lapangan = OrderDetail::where('no_order', $noOrder)->get();
			$lapangan2 = $Lapangan->map(function ($item) {
				return $item->no_sampel;
			})->unique()->sortBy(function ($item) {
				return (int) explode('/', $item)[1];
			})->values();
			$totLapangan = $lapangan2->count();
			try {
				$data = DataLapanganCahaya::where('no_sampel', $request->no_sampel)->first();
				$urutan = $lapangan2->search($data->no_sampel);
				$urutanDisplay = $urutan + 1;
				$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
				if ($data) {
					return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if (in_array($request->kategori, $this->categoryIklim)) {
			try {
				$data = IklimHeader::where('no_sampel', $request->no_sampel)->where('parameter', 'like', '%ISBB%')
					->first();

				if ($data) {
					$data2 = DataLapanganIklimPanas::where('no_sampel', $request->no_sampel)->first();

					$data2['parameter'] = $parameterNames[0];
					if ($data2) {

						return response()->json(['data' => $data2, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
					}
				} else {
					$data2 = DataLapanganIklimDingin::where('no_sampel', $request->no_sampel)->first();
					$data2['parameter'] = $parameterNames[0];
					if ($data2) {

						return response()->json(['data' => $data2, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
					}
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if (in_array($request->kategori, $this->categoryGetaran)) {
			$noOrder = explode('/', $request->no_sampel)[0] ?? null;
			$Lapangan = OrderDetail::where('no_order', $noOrder)->get();
			$lapangan2 = $Lapangan->map(function ($item) {
				return $item->no_sampel;
			})->unique()->sortBy(function ($item) {
				return (int) explode('/', $item)[1];
			})->values();
			$totLapangan = $lapangan2->count();

			$detailOrder = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', 1)->first();
			if (str_contains($detailOrder->parameter, 'Getaran (LK) TL') || (str_contains($detailOrder->parameter, "Getaran (LK) ST"))) {
				try {
					$data = DataLapanganGetaranPersonal::where('no_sampel', $request->no_sampel)->first();


					$urutan = $lapangan2->search($data->no_sampel);
					$urutanDisplay = $urutan + 1;
					$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
					if ($data) {
						return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
					}
				} catch (\Exception $ex) {
					dd($ex);
				}
			} else {
				try {

					$data = DataLapanganGetaran::where('no_sampel', $request->no_sampel)->first();
					$urutan = $lapangan2->search($data->no_sampel); // cari posisi index di lapangan2
					$urutanDisplay = $urutan + 1;
					$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
					if ($data) {
						return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
					}
				} catch (\Exception $ex) {
					dd($ex);
				}
			}
		} else {
			$data = [];
		}
	}

	public function rejectAnalys(Request $request)
	{
		try {
		
				
					$data = ErgonomiHeader::where('id', $request->id)->update([
						'is_approved' => 0,
						'notes_reject' => $request->note,
						'rejected_by' => $this->karyawan,
						'rejected_at' => Carbon::now(),

					]);
				

				if ($data) {
					return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['message' => 'Gagal', 'success' => false, 'status' => 400]);
				}
		} catch (\Exception $ex) {
			dd($ex);
		}
	}

	public function approveWSApi(Request $request)
	{
		if ($request->id) {
					$data = ErgonomiHeader::where('parameter', $request->parameter)
					->where('lhps', 1)
					->where('no_sampel', $request->no_sampel)
					->where("id", $request->id)->first();
					if ($data) {
						$cek = ErgonomiHeader::where('no_sampel', $request->no_sampel)->where('id', $request->id)->first();
						$cek->lhps = 0;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201,
						], 201);
					} else {
						$cek = ErgonomiHeader::where('no_sampel', $request->no_sampel)->where('id', $request->id)->first();
						$cek->lhps = 1;
						$cek->save();
						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200,
						], 200);
					}
		} else {
			return response()->json([
				'message' => 'Gagal Approve',
				'status' => 401,
			], 401);
		}
	}

	public function validasiApproveWSApi(Request $request)
	{
		DB::beginTransaction();
		try {

			if ($request->id) {
				$data = OrderDetail::where('id', $request->id)->first();
				$data->status = 2;
				$data->keterangan_1 = $request->keterangan_1;
				$data->save();

				HistoryAppReject::insert([
					'no_lhp' => $data->cfr,
					'no_sampel' => $data->no_sampel,
					'kategori_2' => $data->kategori_2,
					'kategori_3' => $data->kategori_3,
					'menu' => 'WS Final Udara',
					'status' => 'approve',
					'approved_at' => Carbon::now(),
					'approved_by' => $this->karyawan
				]);

				DB::commit();
				$this->resultx = 'Data hasbeen Approved.!';
				return response()->json([
					'message' => $this->resultx,
					'status' => 200,
					'success' => true,
				], 200);
			} else {
				return response()->json([
					'message' => 'Data Not Found.!',
					'status' => 401,
					'success' => false,
				], 401);
			}
		} catch (Exception $e) {
			DB::rollback();
			return response()->json([
				'message' => $e->getMessage()
			], 401);
		}
	}

	public function KoreksiMethod1(Request $request)
	{
		DB::beginTransaction();
		try {
			if($request->id_datalapangan != null){
				$cek_ws = WsValueErgonomi::where('id_data_lapangan', $request->id_datalapangan)->first();
				if($cek_ws) {
					$cek_ws->sebelum_kerja = json_encode(json_decode($request->sebelum_kerja));
					$cek_ws->setelah_kerja = json_encode(json_decode($request->setelah_kerja));
					$cek_ws->pengukuran = json_encode(json_decode($request->pengukuran));
					$cek_ws->updated_at = Carbon::now();
					$cek_ws->updated_by = $this->karyawan;
					$cek_ws->save();
				} else {
					$new = new WsValueErgonomi();
					$new->id_data_lapangan = $request->id_datalapangan;
					$new->no_sampel = $request->no_sampel;
					$new->nmethod = 1;
					$new->sebelum_kerja = json_encode(json_decode($request->sebelum_kerja));
					$new->setelah_kerja = json_encode(json_decode($request->setelah_kerja));
					$new->pengukuran = json_encode(json_decode($request->pengukuran));
					$new->created_at = Carbon::now();
					$new->created_by = $this->karyawan;
					$new->save();
				}

				DB::commit();
				return response()->json([
					'message' => 'Berhasil mengupdate data',
					'success' => true,
					'status' => 200,
				], 200);
			} else {
				DB::rollback();
				return response()->json([
					'message' => 'Data tidak ditemukan',
					'success' => false,
					'status' => 404,
				], 404);
			}
		} catch (\Exception $ex) {
			DB::rollback();
			return response()->json([
				'message' => $ex->getMessage(),
				'success' => false,
				'status' => 500,
			], 500);
		}
	}
}
