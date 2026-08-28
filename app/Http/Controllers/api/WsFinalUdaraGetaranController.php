<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use App\Models\DetailLingkunganHidup;
use App\Models\MicrobioHeader;
use App\Models\DataLapanganIklimPanas;
use App\Models\DataLapanganIklimDingin;
use App\Models\DetailLingkunganKerja;
use App\Models\DataLapanganKebisingan;
use App\Models\DataLapanganGetaran;
use App\Models\DataLapanganGetaranPersonal;
use App\Models\DataLapanganCahaya;
use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganSinarUV;
use App\Models\DataLapanganMedanLM;
use App\Models\DataLapanganKebisinganPersonal;
use App\Models\DataLapanganDebuPersonal;
use App\Models\MasterKaryawan;
use App\Models\Subkontrak;
use App\Models\IklimHeader;
use App\Models\GetaranHeader;
use App\Models\KebisinganHeader;
use App\Models\PencahayaanHeader;
use App\Models\LingkunganHeader;
use App\Models\DirectLainHeader;
use App\Models\ErgonomiHeader;
use App\Models\SinarUvHeader;
use App\Models\MedanLmHeader;
use App\Models\DebuPersonalHeader;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class WsFinalUdaraGetaranController extends Controller
{
	private $categoryGetaran = [13, 14, 15, 16, 18, 19];

	public function index(Request $request)
	{
		$data = OrderDetail::select(
			DB::raw("MAX(id) as max_id"),
			DB::raw("GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ', ') as tanggal_sampling"),
			DB::raw("GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ', ') as tanggal_terima"),
			DB::raw("GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ', ') as no_sampel"),
			'no_order',
			'nama_perusahaan',
			'cfr',
			'kategori_2',
			'kategori_3',
		)->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->where('status', 0)
			->whereIn('kategori_3', ["13-Getaran", "14-Getaran (Bangunan)", "15-Getaran (Kejut Bangunan)", "16-Getaran (Kenyamanan & Kesehatan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)"])
			->whereNotNull('tanggal_terima')
			->when($request->filled('year'), function ($q) use ($request) {
				return $q->whereYear('tanggal_sampling', $request->year);
			})
			->groupBy('cfr', 'kategori_2', 'kategori_3', 'nama_perusahaan', 'no_order')
			->orderBy('tanggal_sampling');

		$data = $data->get();
		$data = \App\Services\WsFinalApprovalService::appendProgressAndFilter($data, $request);

		return Datatables::of($data)->make(true);
	}

	public function convertHourToMinute($hour)
	{
		$minutes = $hour * 60;
		return $minutes;
	}

	private function getNabKebisingan($menit)
	{
		if ($menit >= 0.94 && $menit < 1.88) {
			return 112;
		} elseif ($menit >= 1.88 && $menit < 3.75) {
			return 109;
		} elseif ($menit >= 3.75 && $menit < 7.5) {
			return 106;
		} elseif ($menit >= 7.5 && $menit < 15) {
			return 103;
		} elseif ($menit >= 15 && $menit < 30) {
			return 100;
		} elseif ($menit >= 30 && $menit < 60) {
			return 97;
		} elseif ($menit >= 60 && $menit < 120) {
			return 94;
		} elseif ($menit >= 120 && $menit < 240) {
			return 91;
		} elseif ($menit >= 240 && $menit < 480) {
			return 88;
		} elseif ($menit >= 480) {
			return 85;
		}
		return null;
	}

	public function detail(Request $request)
	{
		try {
			$parameters = json_decode(html_entity_decode($request->parameter), true);
			$parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];

			// Ambil data utama dari WsValueUdara
			$wsData = WsValueUdara::with([
				'lapangan_getaran',  // Relasi ke DataLapanganGetaran
				'lapangan_getaran_personal', // Relasi ke DataLapanganGetaranPersonal
				'getaran', // Relasi ke GetaranHeader
				'subkontrak' // Relasi ke Subkontrak
			])
				->where('no_sampel', $request->no_sampel)
				->get();

			// Gunakan variable hasil query tadi untuk di-loop
			foreach ($wsData as $item) {
				// 1. Logic Fallback Durasi (Prioritas Reguler -> Personal)
				$rawDurasi = null;
				if (!empty($item->lapangan_getaran->durasi_paparan)) {
					$rawDurasi = $item->lapangan_getaran->durasi_paparan;
				} elseif (!empty($item->lapangan_getaran_personal->durasi_paparan)) {
					$rawDurasi = $item->lapangan_getaran_personal->durasi_paparan;
				}

				$decodedDurasi = json_decode($rawDurasi);
				$totalDurasi = is_array($decodedDurasi) ? array_sum(array_map('floatval', $decodedDurasi)) : floatval($decodedDurasi ?? 0);
				$paparan = $this->convertHourToMinute($totalDurasi);

				// 2. Logic Perhitungan NAB
				$nab = 0;
				if ($parameterArray[1] == 'Getaran (LK) TL') {
					if ($paparan < 30) $nab = 20.0;
					else if ($paparan < 120) $nab = 14.0;
					else if ($paparan < 240) $nab = 7.0;
					else if ($paparan < 360) $nab = 6.0;
					else if ($paparan < 480) $nab = 5.0;
				} else if ($parameterArray[1] == 'Getaran (LK) ST') {
					if ($paparan < 60) $nab = 3.4644;
					else if ($paparan < 120) $nab = 2.4497;
					else if ($paparan < 240) $nab = 1.7322;
					else if ($paparan < 480) $nab = 1.2249;
					else $nab = 0.8661;
				}

				$hasilUji = null;
				$parameterHeader = '';
				$analyst = '';
				$catatan = '';

				if (isset($item->subkontrak) && isset($item->subkontrak->parameter)) {
					$parameterHeader = $item->subkontrak->parameter;
					$analyst = $item->subkontrak->approved_by;
					$catatan = $item->subkontrak->note ?? '';
				} else if (isset($item->getaran) && isset($item->getaran->parameter)) {
					$parameterHeader = $item->getaran->parameter;
					$analyst = $item->getaran->created_by;
					$catatan = $item->getaran->notes_reject ?? '';
				}

				if (str_contains(strtolower($parameterHeader), 'hz')) {
					if (!empty($item->hasil1)) {
						$decoded = json_decode($item->hasil1, true);
						$hasilUji = $decoded['Kecepatan'] ?? null;
					} else {
						$hasilUji = $item->f_koreksi_1 ?? null;
					}
				}

				// Simpan NAB ke DB (karena NAB biasanya memang disimpan)
				$item->nab = $nab;
				$item->save();

				// Inject ke Object Collection untuk dibawa ke frontend via Datatables
				$item->hasil_uji = is_numeric($hasilUji) ? floatval($hasilUji) : null;
				$item->parameter = $parameterHeader;
				$item->analyst = $analyst;
				$item->catatan = $catatan;
			}

			$wsData = $wsData->unique('parameter')->values();

			// Kembalikan data ke Datatables
			return Datatables::of($wsData)
				->addColumn('hasil_uji', function ($row) {
					return $row->hasil_uji;
				})->addColumn('parameter', function ($row) {
					return $row->parameter;
				})->addColumn('analyst', function ($row) {
					return $row->analyst;
				})->addColumn('catatan', function ($row) {
					return $row->catatan;
				})
				->make(true);
		} catch (\Throwable $th) {
			return response()->json([
				'message' => $th->getMessage(),
				'line' => $th->getLine()
			], 500);
		}
	}

	public function getDetailCfr(Request $request)
	{
		$data = OrderDetail::where('cfr', $request->cfr)
			->orderByDesc('id')
			->get()
			->where('status', 0)
			->map(function ($item) {
				$item->getAnyHeaderUdara();
				return $item;
			})
			->map(function ($item) {
				$item->getAnyDataLapanganUdara();
				return $item;
			})->values();

		return response()->json([
			'data' => $data,
			'message' => 'Data retrieved successfully',
		], 200);
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

		if (in_array($request->kategori, $this->categoryGetaran)) {
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
				$model = (in_array("Getaran (LK) ST", $parameterNames) || in_array("Getaran (LK) TL", $parameterNames))
					? DataLapanganGetaranPersonal::class
					: DataLapanganGetaran::class;

				$data = $model::where('no_sampel', $request->no_sampel)->first();

				if (!$data)
					return response()->json(['message' => 'Data Lapangan Tidak Ditemukan'], 401);

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
		} else {
			$data = [];
		}
	}

	public function rejectAnalys(Request $request)
	{
		try {
			$data = GetaranHeader::where('id', $request->id)->update([
				'is_approve' => 0,
				'notes_reject' => $request->note,
				'rejected_by' => $this->karyawan,
				'rejected_at' => Carbon::now(),
				'approved_by' => null,
				'approved_at' => null
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

	public function approveWSApi(Request $request)
	{
		if ($request->id) {
			$data = GetaranHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
			// dd($data);
			if ($data) {
				$cek = GetaranHeader::where('id', $data->id)->first();
				$cek->lhps = 0;
				$cek->save();
				return response()->json([
					'message' => 'Data has ben Rejected',
					'success' => true,
					'status' => 201,
				], 201);
			} else {
				$dat = GetaranHeader::where('id', $request->id)->first();
				$dat->lhps = 1;
				$dat->save();
				return response()->json([
					'message' => 'Data has ben Approved',
					'success' => true,
					'status' => 200,
				], 200);
			}
		} else {
			$data = [];
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
			$data->note = $request->keterangan;
			$data->jenis_pengujian = $request->jenis_pengujian;
			$data->is_active = true;
			$data->is_approve = 1;
			$data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
			$data->approved_by = $this->karyawan;
			$data->created_at = Carbon::now()->format('Y-m-d H:i:s');
			$data->created_by = $this->karyawan;
			$data->save();

			$ws = new WsValueLingkungan();
			$ws->no_sampel = $request->no_sampel;
			$ws->id_subkontrak = $data->id;
			$ws->flow = $request->flow;
			$ws->durasi = $request->durasi;
			$ws->C = $request->C;
			$ws->C1 = $request->C1;
			$ws->C2 = $request->C2;
			$ws->is_active = true;
			$ws->status = 0;
			$ws->save();

			DB::commit();
			return response()->json([
				'message' => 'Data has ben Added',
				'success' => true,
				'status' => 200,
			], 200);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 401
			], 401);
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

	public function getKaryawan(Request $request)
	{
		$data = MasterKaryawan::where('is_active', true)
			->get();
		return $data;
	}

	public function updateTindakan(Request $request)
	{
		try {
			// dd($request->all());
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

	public function updateNab(Request $request)
	{
		try {
			$data = WsValueUdara::where('id', $request->id)->first();
			$data->nab = $request->nab;
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

	public function handleReject(Request $request)
	{
		DB::beginTransaction();
		try {
			$dataLapangan = DataLapanganGetaranPersonal::where('no_sampel', $request->no_sampel)->update([
				'is_approve' => 0,
			]);

			if (!$dataLapangan) {
				$dataLapangan = DataLapanganGetaran::where('no_sampel', $request->no_sampel)->update([
					'is_approve' => 0,
				]);
			}

			GetaranHeader::where('no_sampel', $request->no_sampel)
				->update([
					'is_approve' => 0
				]);

			DB::commit();
			return response()->json([
				'message' => 'Data berhasil direject.',
				'success' => true,
				'status' => 200,
			], 200);
		} catch (\Throwable $th) {
			DB::rollBack();
			return response()->json([
				'message' => 'Gagal mereject data: ' . $th->getMessage(),
				'success' => false,
				'status' => 500,
			], 500);
		}
	}

	public function handleApproveAll(Request $request)
	{
		DB::beginTransaction();
		try {
			$orderDetails = OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->get();

			OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
				->update([
					'status' => 1,
				]);

			foreach ($orderDetails as $detail) {
				HistoryAppReject::insert([
					'no_lhp' => $detail->cfr,
					'no_sampel' => $detail->no_sampel,
					'kategori_2' => $detail->kategori_2,
					'kategori_3' => $detail->kategori_3,
					'menu' => 'WS Final Udara',
					'status' => 'approve',
					'approved_at' => Carbon::now(),
					'approved_by' => $this->karyawan
				]);
			}

			GetaranHeader::whereIn('no_sampel', $request->no_sampel_list)
				->update([
					'lhps' => 1,
				]);

			SubKontrak::whereIn('no_sampel', $request->no_sampel_list)
				->update([
					'lhps' => 1,
				]);

			\App\Services\WsFinalApprovalService::finalizeSamples($orderDetails, true, $this->karyawan);

			DB::commit();
			return response()->json([
				'message' => 'Data berhasil diapprove.',
				'success' => true,
				'status' => 200,
			], 200);
		} catch (\Throwable $th) {
			DB::rollBack();
			return response()->json([
				'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
				'success' => false,
				'status' => 500,
			], 500);
		}
	}
}