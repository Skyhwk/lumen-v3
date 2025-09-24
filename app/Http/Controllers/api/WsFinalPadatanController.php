<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\Colorimetri;
use App\Models\MasterRegulasi;
use App\Models\WsValueAir;
use App\Models\Subkontrak;
use App\Models\HistoryWsValueAir;
use App\Models\HistoryAppReject;
use App\Models\CategorySample;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class WsFinalPadatanController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::with('wsValueAir', 'dataLapanganAir', 'sample_diantar.detail')
			->where('is_active', $request->is_active)
			->where('kategori_2', '6-Padatan')
			->where('status', 0)
			->whereNotNull('tanggal_terima');

		if ($request->has(['from', 'to'])) {
			$from = $request->from . '-01';
			$to = date("Y-m-t", strtotime($request->to . '-01'));

			$data->whereBetween('tanggal_sampling', [$from, $to]);
		}

		return Datatables::of($data)->make(true);
	}

	public function detail(Request $request)
	{
		$data = WsValueAir::with([
			'dataLapanganAir',
			'subkontrak.createdByKaryawan',
			'titrimetri.createdByKaryawan',
			'gravimetri.createdByKaryawan',
			'colorimetri.createdByKaryawan',
			'colorimetri.baku_mutu' => function ($q) use ($request) {
				$q->where('id_regulasi', $request->regulasi);
			},
			'gravimetri.baku_mutu' => function ($q) use ($request) {
				$q->where('id_regulasi', $request->regulasi);
			},
			'titrimetri.baku_mutu' => function ($q) use ($request) {
				$q->where('id_regulasi', $request->regulasi);
			},
			'subkontrak.baku_mutu' => function ($q) use ($request) {
				$q->where('id_regulasi', $request->regulasi);
			}
		])
			->where('no_sampel', $request->no_sampel)
			->where('status', 0)
			->where(function ($query) {
				$query->whereHas('colorimetri', function ($q) {
					$q->where('is_approved', 1)->where('colorimetri.is_total', 0);
				})
					->orWhereHas('gravimetri', function ($q) {
						$q->where('is_approved', 1)->where('gravimetri.is_total', 0);
					})
					->orWhereHas('titrimetri', function ($q) {
						$q->where('is_approved', 1)->where('titrimetri.is_total', 0);
					})
					->orWhereHas('subkontrak', function ($q) {
						$q->where('is_approve', 1)->where('subkontrak.is_total', 0);
					});
			});

		return Datatables::of($data)
			->make(true);
	}

	public function rejectAnalys(Request $request)
	{
		if ($request->type == 'titrimetri') {
			try {
				$data = Titrimetri::where('id', $request->id)->update([
					'is_approved' => 0,
					'notes_reject' => $request->note,
					'rejected_by' => $this->karyawan,
					'rejected_at' => date('Y-m-d H:i:s')
				]);

				if ($data) {
					return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['message' => 'Gagal']);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if ($request->type == 'gravimetri') {
			try {
				$data = Gravimetri::where('id', $request->id)->update([
					'is_approved' => 0,
					'notes_reject' => $request->note,
					'rejected_by' => $this->karyawan,
					'rejected_at' => date('Y-m-d H:i:s')
				]);

				if ($data) {
					return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['message' => 'Gagal']);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if ($request->type == 'colorimetri') {
			try {
				$data = Colorimetri::where('id', $request->id)->update([
					'is_approved' => 0,
					'notes_reject' => $request->note,
					'rejected_by' => $this->karyawan,
					'rejected_at' => date('Y-m-d H:i:s')
				]);

				if ($data) {
					return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['message' => 'Gagal']);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		} else if ($request->type == 'subkontrak') {
			// dd('subkontrak');
			try {
				$data = Subkontrak::where('id', $request->id)->update([
					'is_approve' => 0,
					'notes_reject' => $request->note,
					'is_active' => 0,
					'deleted_by' => $this->karyawan,
					'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
				]);

				if ($data) {
					return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
				} else {
					return response()->json(['message' => 'Gagal']);
				}
			} catch (\Exception $ex) {
				dd($ex);
			}
		}
	}

	public function approveWSApi(Request $request)
	{
		DB::beginTransaction();
		try {
			if ($request->template_stp == 4) {
				if ($request->id) {
					$data = Titrimetri::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->where('is_active', 1)->first();
					if ($data) {
						$cek = Titrimetri::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
	
						], 201);
					} else {
						$dat = Titrimetri::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'id' => $dat->no_sampel,
						], 200);
					}
				} else {
					return response()->json([
						'message' => 'Gagal Approve'
					], 401);
				}
			} else if ($request->template_stp == 3) {
				if ($request->id) {
					$data = Gravimetri::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->where('is_active', 1)->first();
					if ($data) {
						$cek = Gravimetri::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
	
						], 201);
					} else {
						$dat = Gravimetri::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'id' => $dat->no_sampel,
						], 200);
					}
				} else {
					return response()->json([
						'message' => 'Gagal Approve'
					], 401);
				}
			} else if ($request->template_stp == 7 || $request->template_stp == 2 || $request->template_stp == 5 || $request->template_stp == 6 || $request->template_stp == 8 || $request->template_stp == 76 || $request->template_stp == 34) {
				if ($request->id) {
					$data = Colorimetri::where('parameter', $request->parameter)->where('lhps', 1)->where('template_stp', empty($request->template_stp) ? null : $request->template_stp)->where('is_active', 1)->where('no_sampel', $request->no_sampel)->first();
					if ($data) {
						$cek = Colorimetri::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'success' => true,
							'status' => 201
						], 201);
					} else {
						$dat = Colorimetri::where('id', $request->id)->first();
						$dat->lhps = 1;
						$dat->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Approved',
							'success' => true,
							'status' => 200
						], 200);
					}
				} else {
					return response()->json([
						'message' => 'Gagal Approve',
						'success' => false,
						'status' => 401
					], 401);
				}
			} else {
				if ($request->id) {
					$data = Subkontrak::where('parameter', $request->parameter)->where('lhps', 1)->where('is_active', 1)->where('no_sampel', $request->no_sampel)->first();
					if ($data != null) {
						$cek = Subkontrak::where('id', $data->id)->first();
						$cek->lhps = 0;
						$cek->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Rejected',
							'status' => 201,
							'success' => true
						], 201);
					} else {
						$dat = Subkontrak::where('id', $request->id)->where('is_active', 1)->first();
						$dat->lhps = 1;
						$dat->save();
						DB::commit();
						return response()->json([
							'message' => 'Data has ben Approved',
							'status' => 200,
							'success' => true
						], 200);
					}
				} else {
					return response()->json([
						'message' => 'Gagal Approve',
						'success' => false
					], 401);
				}
			}
		} catch (\Throwable $th) {
			DB::rollBack();
			return response()->json([
				'message' => 'Gagal Approve because ' . $th->getMessage(),
				'success' => false,
				'status' => 401
			], 401);
		}
	}

	public function KalkulasiKoreksi(Request $request)
	{
		try {

			$type_koreksi = $request->type;
			$id = $request->id;
			$no_sample = $request->no_sample;
			$parameter = $request->parameter;

			$faktor_koreksi = (float) $request->faktor_koreksi;
			$hasilPengujian = html_entity_decode($request->hasil_pengujian);


			$hasil = $this->hitungKoreksi($request, $type_koreksi, $id, $no_sample, $faktor_koreksi, $parameter, $hasilPengujian);

			$history = new HistoryWsValueAir();
			$history->id_ws_value_air = $id;
			$history->no_sampel = $no_sample;
			$history->parameter = $parameter;
			$history->hasil = $hasil;
			$history->created_by = $this->karyawan;
			$history->created_at = Carbon::now()->format('Y-m-d H:i:s');
			$history->save();

			if (is_numeric($hasil)) {
				$hasil = number_format((float) $hasil, 4, '.', '');
			}

			return response()->json(['hasil' => $hasil]);
		} catch (\Exception $e) {
			dd($e);
			\Log::error('Error dalam KalkulasiKoreksi: ' . $e->getMessage());
			return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
		}
	}

	private function hitungKoreksi($request, $type_koreksi, $id, $no_sample, $faktor_koreksi, $parameter, $hasilPengujian)
	{
		try {
			$hasil = 0;
			$hasil = $this->rumusAir($request, $faktor_koreksi, $parameter, $hasilPengujian, null);
			return $hasil;
		} catch (\Exception $e) {
			dd($e);
			\Log::error('Error dalam hitungKoreksi: ' . $e->getMessage());
			throw $e;
		}
	}

	public function rumusAir($request, $faktor_koreksi, $parameter, $hasilPengujian, $air)
	{
		try {
			// dd($faktor_koreksi);
			switch ($parameter) {
				case 'BOD':
					if (str_contains($hasilPengujian, '<')) {
						$mdl = (float) str_replace('<', '', $hasilPengujian);
						$hasil = $mdl / 0.5;
						$hasil = ($hasil * ($faktor_koreksi / 100)) + $hasil;
						$hasil = $hasil < 1 ? '<1' : $hasil;
					} else {
						if ($faktor_koreksi >= 65 && $faktor_koreksi <= 85) {
							$hasil = (float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100);
							$hasil = $hasil < 1 ? '<1' : $hasil;
						} elseif ($faktor_koreksi >= 120 && $faktor_koreksi <= 190) {
							$hasil = ((float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100)) + (float) str_replace('<', '', $hasilPengujian);
							$hasil = $hasil < 1 ? '<1' : $hasil;
						}
					}
					break;

				case 'COD':
					if (str_contains($hasilPengujian, '<')) {
						$mdl = (float) str_replace('<', '', $hasilPengujian);
						$hasil = $mdl / 0.8;
						$hasil = ($hasil * ($faktor_koreksi / 100)) + $hasil;
						$hasil = $hasil < 1.31 ? '<1.31' : $hasil;
					} else {
						if ($faktor_koreksi >= 65 && $faktor_koreksi <= 80) {
							$hasil = (float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100);
							$hasil = $hasil < 1.31 ? '<1.31' : $hasil;
						} elseif ($faktor_koreksi >= 120 && $faktor_koreksi <= 190) {
							$hasil = ((float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100)) + (float) str_replace('<', '', $hasilPengujian);
							$hasil = $hasil < 1.31 ? '<1.31' : $hasil;
						}
					}
					break;

				case 'NH3':
					if (str_contains($hasilPengujian, '<')) {
						$mdl = (float) str_replace('<', '', $hasilPengujian);
						$hasil = $mdl / 0.08;
						$hasil = ($hasil * ($faktor_koreksi / 100)) + $hasil;
						$hasil = $hasil < 0.0038 ? '<0.0038' : $hasil;
					} else {
						if ($faktor_koreksi >= 20 && $faktor_koreksi <= 80) {
							$hasil = (float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100);
							$hasil = $hasil < 0.0038 ? '<0.0038' : $hasil;
						} elseif ($faktor_koreksi >= 120 && $faktor_koreksi <= 190) {
							$hasil = ((float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100)) + (float) str_replace('<', '', $hasilPengujian);
							$hasil = $hasil < 0.0038 ? '<0.0038' : $hasil;
						}
					}
					break;

				case 'NH3-N':
					if (str_contains($hasilPengujian, '<')) {
						$mdl = (float) str_replace('<', '', $hasilPengujian);
						$hasil = $mdl / 0.08;
						$hasil = ($hasil * ($faktor_koreksi / 100)) + $hasil;
						$hasil = $hasil < 0.0031 ? '<0.0031' : $hasil;
					} else {
						if ($faktor_koreksi >= 20 && $faktor_koreksi <= 80) {
							$hasil = (float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100);
							$hasil = $hasil < 0.0031 ? '<0.0031' : $hasil;
						} elseif ($faktor_koreksi >= 120 && $faktor_koreksi <= 190) {
							$hasil = ((float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100)) + (float) str_replace('<', '', $hasilPengujian);
							$hasil = $hasil < 0.0031 ? '<0.0031' : $hasil;
						}
					}
					break;
				case 'KMnO4':
					if (str_contains($hasilPengujian, '<')) {
						$mdl = (float) str_replace('<', '', $hasilPengujian);
						$hasil = $mdl / 0.5;
						$hasil = ($hasil * ($faktor_koreksi / 100)) + $hasil;
						$hasil = $hasil < 0.3 ? '<0.3' : $hasil;
					} else {
						if ($faktor_koreksi >= 65 && $faktor_koreksi <= 85) {
							$hasil = (float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100);
							// dd($hasil);
							$hasil = $hasil < 0.3 ? '<0.3' : $hasil;
						} elseif ($faktor_koreksi >= 120 && $faktor_koreksi <= 190) {
							$hasil = ((float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100)) + (float) str_replace('<', '', $hasilPengujian);
							$hasil = $hasil < 0.3 ? '<0.3' : $hasil;
						}
					}
					break;
				case 'Cr6+':
					if ($faktor_koreksi >= 10 && $faktor_koreksi <= 95) {
						$hasil = (float) str_replace('<', '', $hasilPengujian) * ($faktor_koreksi / 100);
						$hasil = $hasil < 0.0056 ? '<0.0056' : $hasil;
					}
					break;
				case 'NO3':
					if ($faktor_koreksi == 4.4268) {
						$hasil = (float) $hasilPengujian / 4.4268;
						$hasil = number_format($hasil, 10, '.', '');
						$hasil = substr($hasil, 0, strpos($hasil, '.') + 5);
						if ($hasil < 0.4427) {
							$hasil = '<0.4427';
						} else {
							$hasil;
						}
					}
					break;

				case 'NO2':
					// dd($faktor_koreksi);
					if ($faktor_koreksi == 3.2845) {
						$hasil = (float) $hasilPengujian / 3.2845;
						$hasil = number_format($hasil, 10, '.', '');
						$hasil = substr($hasil, 0, strpos($hasil, '.') + 5);

						if ($hasil < 0.0030) {
							$hasil = '<0.0030';
						}
					}
					break;
				default:
					$hasil = ''; // or handle unexpected parameter
					break;
			}
			return $hasil;
		} catch (\Exception $e) {
			dd($e);
			return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
		}
	}

	public function saveData(Request $request)
	{
		$type_koreksi = $request->type;

		$id = $request->id;
		$no_sampel = $request->no_sampel;
		$parameter = $request->parameter;

		$faktor_koreksi = (float) $request->faktor_koreksi;
		$hasilPengujian = $request->hasil_pengujian;

		if ($type_koreksi) {
			switch ($type_koreksi) {
				//AIR
				case 'titrimetri':
					$air = WsValueAir::where('no_sampel', $request->no_sampel)->where('id', $request->id)->where('is_active', 1)->first();
					return $this->handleTitrimetri($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air);
				case 'gravimetri':
					// Proses untuk gravimetri
					$air = WsValueAir::where('no_sampel', $request->no_sampel)->where('id', $request->id)->where('is_active', 1)->first();
					return $this->handleGravimetri($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air);
				case 'colorimetri':
					// Proses untuk colorimetri
					$air = WsValueAir::where('no_sampel', $request->no_sampel)->where('id', $request->id)->where('is_active', 1)->first();
					return $this->handleColorimetri($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air);
				case 'subkontrak':
					// Proses untuk subkontrak
					$air = WsValueAir::where('no_sampel', $request->no_sampel)->where('id_subkontrak', $request->id)->where('is_active', 1)->first();
					return $this->handleSubkontrak($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air);

				default:
					return response()->json(['message' => 'Type koreksi tidak valid.'], 400);
			}
		} else {
			return response()->json(['message' => 'Type koreksi harus diisi.'], 400);
		}
	}

	private function handleTitrimetri($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air)
	{
		try {
			DB::beginTransaction();
			$po = OrderDetail::where('no_sampel', $no_sampel)
				->where('is_active', 1)
				->where('parameter', 'like', '%' . $parameter . '%')
				->first();
			if ($po) {
				$titri = Titrimetri::where('no_sampel', $no_sampel)
					->where('parameter', $parameter)
					->where('is_active', 1)
					->first();

				if ($titri != null) {
					$valuews = WsValueAir::where('no_sampel', $no_sampel)
						->where('id_titrimetri', $titri->id)
						->where('is_active', 1)
						->first();

					if ($titri->tipe_koreksi == null) {
						$nomor = 1;
					} else {
						if ($titri->tipe_koreksi < 5) {
							$nomor = $titri->tipe_koreksi + 1;
						} else {
							return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.'], 400);
						}
					}
					$titri->tipe_koreksi = $nomor;
					$titri->save();

					$hasil = $request->hasil_koreksi;
					if (!str_contains((string) $hasil, '<')) {
						$hasil = number_format((float) $hasil, 4, '.', '');
					}
					// dd($faktor_koreksi, $air);

					if ($valuews) {
						$valuews->faktor_koreksi = $hasil;
						$valuews->input_koreksi = $faktor_koreksi;
						$valuews->save();
					} else {
						return response()->json(['message' => 'Data Valuews tidak ditemukan.'], 404);
					}

					DB::commit();
					return response()->json(['message' => 'Data berhasil diupdate.', 'status' => 200, "success" => true], 200);
				}
			} else {
				return response()->json(['message' => 'Data tidak ditemukan di kategori AIR.'], 404);
			}
		} catch (\Exception $ex) {
			DB::rollBack();
			return response()->json(['message' => 'Terjadi kesalahan: ' . $ex->getMessage()], 500);
		}
	}

	private function handleGravimetri($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air)
	{
		DB::beginTransaction();
		try {


			$po = OrderDetail::where('no_sampel', $no_sampel)
				->where('is_active', 1)
				->where('parameter', 'like', '%' . $parameter . '%')
				->first();
			if ($po) {
				$gravi = Gravimetri::where('no_sampel', $request->no_sampel)
					->where('parameter', $request->parameter)
					->where('is_active', 1)
					->first();

				if ($gravi != null) {
					$valuews = WsValueAir::where('no_sampel', $request->no_sampel)
						->where('id_gravimetri', $gravi->id)
						->where('is_active', 1)
						->first();

					if ($gravi->tipe_koreksi == null) {
						$nomor = 1;
					} else {
						if ($gravi->tipe_koreksi < 5) {
							$nomor = $gravi->tipe_koreksi + 1;
						} else {
							return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.', 'status' => 400, "success" => false], 400);
						}
					}

					$gravi->tipe_koreksi = $nomor;
					$gravi->save();
					$hasil = $request->hasil_koreksi;
					if (!str_contains((string) $hasil, '<')) {
						$hasil = number_format((float) $hasil, 4, '.', '');
					}

					if ($valuews) {
						$valuews->faktor_koreksi = $hasil;
						$valuews->input_koreksi = $faktor_koreksi;
						$valuews->save();
					} else {
						return response()->json(['message' => 'Data Valuews tidak ditemukan.'], 404);
					}

					DB::commit();
					return response()->json(['message' => 'Data berhasil diupdate.', 'status' => 200, "success" => true], 200);
				} else {
					return response()->json(['message' => 'Data Gravimetri tidak ditemukan.'], 404);
				}
			} else {
				return response()->json(['message' => 'Data tidak ditemukan di kategori AIR.'], 404);
			}
		} catch (\Exception $e) {
			DB::rollback(); // Rollback transaksi jika ada kesalahan
			return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
		}
	}

	private function handleColorimetri($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air)
	{
		DB::beginTransaction();
		try {
			// dd($faktor_koreksi, $air);


			$po = OrderDetail::where('no_sampel', $no_sampel)
				->where('is_active', 1)
				->where('parameter', 'like', '%' . $parameter . '%')
				->first();
			if ($po) {

				// Ambil seluruh data dari model Colorimetri
				$colori = Colorimetri::where('no_sampel', $request->no_sampel)
					->where('parameter', $request->parameter)
					->where('is_active', 1)
					->first();

				if ($colori != null) {
					$valuews = WsValueAir::where('no_sampel', $request->no_sampel)
						->where('id_colorimetri', $colori->id)
						->where('is_active', 1)
						->first();

					if ($colori->tipe_koreksi == null) {
						$nomor = 1;
					} else {
						if ($colori->tipe_koreksi < 5) {
							$nomor = $colori->tipe_koreksi + 1;
						} else {
							return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.'], 400);
						}
					}

					$colori->tipe_koreksi = $nomor;
					// dd($colori->tipe_koreksi)
					$colori->save();


					$hasil = $request->hasil_koreksi;
					// dd($hasil);
					if (!str_contains((string) $hasil, '<')) {
						$hasil = number_format((float) $hasil, 4, '.', '');
					}

					if ($valuews) {
						$valuews->faktor_koreksi = $hasil;
						$valuews->input_koreksi = $faktor_koreksi;
						$valuews->save();
					} else {
						return response()->json(['message' => 'Data Valuews tidak ditemukan.'], 404);
					}

					DB::commit();
					return response()->json(['message' => 'Data berhasil diupdate.', 'status' => 200, "success" => true], 200);
				} else {
					return response()->json(['message' => 'Data Colorimetri tidak ditemukan.'], 404);
				}
			} else {
				return response()->json(['message' => 'Data tidak ditemukan di kategori AIR.'], 404);
			}
		} catch (\Exception $e) {
			DB::rollback();
			return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
		}
	}

	private function handleSubkontrak($request, $faktor_koreksi, $no_sampel, $parameter, $hasilPengujian, $air)
	{
		DB::beginTransaction();
		try {
			$po = OrderDetail::where('no_sampel', $no_sampel)
				->where('is_active', 1)
				->where('param', 'like', '%' . $parameter . '%') // Menambahkan kondisi where dengan like
				->first();
			if ($po) {
				// Ambil seluruh data dari model Subkontrak
				$subkon = Subkontrak::where('no_sampel', $request->no_sampel)
					->where('param', $request->parameter)
					->where('is_active', 1)
					->first();

				if ($subkon != null) {
					$valuews = Valuews::where('no_sampel', $request->no_sampel)
						->where('id_subkontrak', $subkon->id)
						->where('is_active', 1)
						->first(); // Ganti dengan query yang sesuai untuk mendapatkan data yang diupdate

					if ($subkon->tipe_koreksi == null) {
						$nomor = 1;
					} else {
						if ($subkon->tipe_koreksi < 5) {
							$nomor = $subkon->tipe_koreksi + 1;
						} else {
							return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.'], 400);
						}
					}

					$subkon->tipe_koreksi = $nomor;
					$subkon->save();

					// $hasil = $this->rumusAir($request,$faktor_koreksi,$parameter,$hasilPengujian, $air);
					$hasil = $request->hasil_koreksi;

					if (!str_contains((string) $hasil, '<')) {
						$hasil = number_format((float) $hasil, 4, '.', ''); // Mengatur format angka menjadi 4 desimal
					}
					// Simpan hasil koreksi pada field faktor_koreksi di model Valuews
					if ($valuews) {
						$valuews->faktor_koreksi = $hasil;
						$valuews->save();
					} else {
						return response()->json(['message' => 'Data Valuews tidak ditemukan.'], 404);
					}

					DB::commit(); // Commit transaksi jika semua berhasil
					return response()->json(['message' => 'Data berhasil diupdate.'], 200);
				} else {
					return response()->json(['message' => 'Data Subkontrak tidak ditemukan.'], 404);
				}
			} else {
				return response()->json(['message' => 'Data tidak ditemukan di kategori AIR.'], 404);
			}
		} catch (\Exception $e) {
			DB::rollback(); // Rollback transaksi jika ada kesalahan
			return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
		}
	}

	public function validasiApproveWSApi(Request $request)
	{

		DB::beginTransaction();
		try {
			if ($request->id) {
				$data = OrderDetail::where('id', $request->id)->first();
				$data->status = 1;
				$data->keterangan_1 = $request->keterangan_1;
				$data->save();


				HistoryAppReject::insert([
					'no_lhp' => $data->cfr,
					'no_sampel' => $data->no_sampel,
					'kategori_2' => $data->kategori_2,
					'kategori_3' => $data->kategori_3,
					'menu' => 'WS Final Air',
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

	public function AddSubKontrak(Request $request)
	{
		DB::beginTransaction();
		try {
			$data = new Subkontrak();
			$data->no_sampel = $request->no_sampel;
			$data->category_id = $request->category;
			$data->parameter = $request->parameter;
			$data->jenis_pengujian = $request->jenis_pengujian;
			$data->note = $request->keterangan;
			$data->is_active = true;
			$data->is_approve = 1;
			$data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
			$data->approved_by = $this->karyawan;
			$data->created_at = Carbon::now()->format('Y-m-d H:i:s');
			$data->created_by = $this->karyawan;
			$data->save();
			// dd($data);
			if ($request->category == 1) {
				$ws = new WsValueAir();
				$ws->no_sampel = $request->no_sampel;
				$ws->id_subkontrak = $data->id;
				$ws->hasil = $request->hasil;
				$ws->is_active = true;
				$ws->status = 0;
				$ws->save();
			}

			DB::commit();
			return response()->json([
				'message' => 'Data Berhasil Disimpan',
				'status' => 200,
				'success' => true
			], 200);
		} catch (exception $e) {
			DB::rollback();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 500,
				'line' => $e->getLine()
			], 500);
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


}
