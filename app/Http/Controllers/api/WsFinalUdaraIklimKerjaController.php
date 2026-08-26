<?php

namespace App\Http\Controllers\api;

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Datatables;
use Carbon\Carbon;
use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\IklimHeader;
use App\Models\WsValueUdara;
use App\Models\MasterKaryawan;
use App\Models\LingkunganHeader;
use App\Models\HistoryAppReject;
use App\Models\WsValueLingkungan;
use App\Models\DataLapanganIklimPanas;
use App\Models\DataLapanganIklimDingin;
use App\Models\Parameter;



class WsFinalUdaraIklimKerjaController extends Controller
{
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
		)
			->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->whereIn('kategori_3', ["21-Iklim Kerja"])
			->where('status', 0)
			->whereNotNull('tanggal_terima')
			->whereJsonDoesntContain('parameter', ["318;Psikologi"])
			->when($request->filled('year'), function ($q) use ($request) {
				return $q->whereYear('tanggal_sampling', $request->year);
			})
			->groupBy('cfr', 'kategori_2', 'kategori_3', 'nama_perusahaan', 'no_order')
			->orderBy('tanggal_sampling');
		$data = $data->get();
		$data = \App\Services\WsFinalApprovalService::appendProgressAndFilter($data, $request);
		return Datatables::of($data)->make(true);
	}

	public function getDetailCfr(Request $request)
	{
		$data = OrderDetail::where('cfr', $request->cfr)
			->where('status', 0)
			->orderByDesc('id')
			->get()
			->map(function ($item) {
				$item->getAnyDataLapanganUdara();
				return $item;
			});
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
			$data = IklimHeader::with(['iklim_panas', 'iklim_dingin', 'ws_udara', 'orderDetail'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', 1)
				->where('status', 0)
				->where('is_active', 1)
				->get();
			foreach ($data as $item) {
				if (isset($item->iklim_dingin)) {
					$pengukuranJson = $item->iklim_dingin->pengukuran;
					$pengukuranArray = json_decode($pengukuranJson, true);
					$item->iklim_dingin->pengukuranParsed = $pengukuranArray ?? null;
					$suhuTotal = 0;
					$anginTotal = 0;
					$count = 0;

					foreach ($item->iklim_dingin->pengukuranParsed as $data2) {
						$suhuTotal += (float) $data2['suhu_kering'];
						$anginTotal += (float) $data2['kecepatan_angin'];
						$count++;
					}

					$item->iklim_dingin->avg_suhu_kering = $count > 0 ? round($suhuTotal / $count, 2) : null;
					$item->iklim_dingin->avg_kecepatan_angin = $count > 0 ? round($anginTotal / $count, 2) : null;
				}
				// $regulasi = json_decode($item->orderDetail->regulasi);

				// $item->method = $regulasi ? explode('-', $regulasi[0])[1] : null;
				$idParameter = explode(';', $parameters[0])[0];
				$method = Parameter::where('id', $idParameter)->first()->method ?? '-';
				$item->method = $method;
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
		if (!empty($parameterNames)) {
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
		}
	}

	public function rejectAnalys(Request $request)
	{

		try {

			$data = IklimHeader::where('id', $request->id)->update([

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



			$data = IklimHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();

			// dd($data);

			if ($data) {

				$cek = IklimHeader::where('id', $data->id)->first();

				$cek->lhps = 0;

				$cek->save();

				return response()->json([

					'message' => 'Data has ben Rejected',

					'success' => true,

					'status' => 201,

				], 201);

			} else {

				$dat = IklimHeader::where('id', $request->id)->first();

				$dat->lhps = 1;

				$dat->save();

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

	public function AddSubKontrak(Request $request)
	{

		DB::beginTransaction();

		try {

			if ($request->subCategory == 11 || $request->subCategory == 27) {

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

			}



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

	public function handleEkuivalen(Request $request)
	{



		try {

			$data = WsValueUdara::where('id', $request->id)->first();

			$data->ekuivalen = $request->ekuivalen;

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

	public function updateInterpretasi(Request $request)
	{



		try {



			$data = WsValueUdara::where('id', $request->id)->first();



			$data->interpretasi = $request->interpretasi;

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

			$dataLapangan = DataLapanganIklimPanas::where('no_sampel', $request->no_sampel)

				->update(['is_approve' => 0]);



			if (!$dataLapangan) {

				$dataLapangan = DataLapanganIklimDingin::where('no_sampel', $request->no_sampel)

					->update(['is_approve' => 0]);

			}



			IklimHeader::where('no_sampel', $request->no_sampel)

				->update(['is_approved' => 0]);



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

	public function handleApproveSelected(Request $request)
	{

		DB::beginTransaction();

		try {

			$orderDetails = OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->get();



			OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 1]);



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



			IklimHeader::whereIn('no_sampel', $request->no_sampel_list)
				->update([
					'lhps' => 1,
				]);

			\App\Services\WsFinalApprovalService::finalizeSamples($orderDetails, true, $this->karyawan);
			DB::commit();
			return response()->json([

				'message' => 'Data berhasil diapprove.',

				'success' => true,

			], 200);

		} catch (\Throwable $th) {

			DB::rollBack();

			return response()->json([

				'message' => 'Gagal mengapprove data: ' . $th->getMessage(),

				'success' => false,

			], 500);

		}

	}
}

