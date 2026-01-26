<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Datatables;
use Carbon\Carbon;

use App\Models\Parameter;
use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\WsValueUdara;
use App\Models\MasterKaryawan;
use App\Models\KebisinganHeader;
use App\Models\LingkunganHeader;
use App\Models\HistoryAppReject;
use App\Models\WsValueLingkungan;
use App\Models\DataLapanganKebisingan;
use App\Models\DataLapanganKebisinganPersonal;
use App\Models\MasterRegulasi;


class WsFinalUdaraKebisinganPersonalController extends Controller
{
	private $categoryKebisingan = [23];

	public function index(Request $request)
	{
		$data = OrderDetail::select(
			DB::raw("MAX(id) as max_id"),
			DB::raw("GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ', ') as tanggal_sampling"),
			DB::raw("GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ', ') as tanggal_terima"),
			'no_order',
			'nama_perusahaan',
			'cfr',
			'kategori_2',
			'kategori_3',
		)
			->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->where('kategori_3', "23-Kebisingan")
			->where('status', 0)
			->whereNotNull('tanggal_terima')
			->where('parameter', 'like' , '%Kebisingan (P8J)%')
			->when($request->date, fn($q) => $q->whereYear('tanggal_sampling', explode('-', $request->date)[0])->whereMonth('tanggal_sampling', explode('-', $request->date)[1]))
			->groupBy('cfr', 'kategori_2', 'kategori_3', 'nama_perusahaan', 'no_order')
			->orderBy('tanggal_sampling');

		return Datatables::of($data)->make(true);
	}

	public function getDetailCfr(Request $request)
	{
		$data = OrderDetail::where('cfr', $request->cfr)
			->where('status', 0)
			->orderByDesc('id')
			->get()
			->map(function ($item) {
                $item->getAnyHeaderUdara();
                return $item;
            })->values()
			->map(function ($item) {
				$item->getAnyDataLapanganUdara();
				return $item;
			});

		return response()->json([
			'data' => $data,
			'message' => 'Data retrieved successfully',
		], 200);
	}

	public function detail(Request $request)
	{
		DB::beginTransaction();
		try {
			$parameters = json_decode(html_entity_decode($request->parameter), true);
			$parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
			if (in_array($request->kategori, $this->categoryKebisingan)) {
				$data = KebisinganHeader::with(['ws_udara', 'data_lapangan_personal', 'orderDetail'])
					->where('no_sampel', $request->no_sampel)
					->where('is_approved', 1)
					->where('status', 0)
					->where('is_active', 1)
					->first();
				if($data) {
					$data = [$data];
				} else {
					$data = [];
				}

				return response()->json([
					'data' => $data,
					'message' => 'Berhasil mendapatkan data',
					'success' => true,
					'status' => 200,
				], 200);
			} else {
				DB::rollBack();
				return response()->json([
					'message' => 'Kategori tidak sesuai',
					'status' => 404,
				], 404);
			}
		} catch (\Throwable $th) {
			DB::rollBack();
			dd($th);
			return response()->json([
				'message' => $th->getMessage(),
			], 401);
		}
	}

	public function detailLapangan(Request $request)
	{
		$parameterNames = [];

		if(!isset($request->parameter) || $request->parameter == null || $request->parameter == '') {
			return response()->json(['message' => 'Parameter tidak ditemukan'], 401);
		}

		if (is_array($request->parameter)) {
			foreach ($request->parameter as $param) {
				$paramParts = explode(";", $param);
				if (isset($paramParts[1])) {
					$parameterNames[] = trim($paramParts[1]);
				}
			}
		}
		if (in_array($request->kategori, $this->categoryKebisingan)) {
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

				$data = DataLapanganKebisinganPersonal::where('no_sampel', $request->no_sampel)->first();

				if (!$data) return response()->json(['message' => 'Data Lapangan Tidak Ditemukan'], 401);

				$urutan = $lapangan2->search($data->no_sampel);
				$urutanDisplay = $urutan + 1;
				$data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
				$data['parameter'] = $parameterNames[0];
				
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
			if (in_array($request->kategori, $this->categoryKebisingan)) {
				$data = KebisinganHeader::where('id', $request->id)->update([
					'is_approved' => 0,
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
			} else {
				$data = [];
			}
		} catch (\Exception $ex) {
			dd($ex);
		}
	}

	public function approveWSApi(Request $request)
	{
		if ($request->id) {

			if (in_array($request->kategori, $this->categoryKebisingan)) {
				$data = KebisinganHeader::where('parameter', $request->parameter)
					->where('lhps', 1)
					->where('no_sampel', $request->no_sampel)
					->first();
				$ws = WsValueUdara::where('no_sampel', $request->no_sampel)
					->first();
				if ($data) {
					$data->update(['lhps' => 0]);
				} else {
					KebisinganHeader::where('id', $request->id)
						->update(['lhps' => 1]);
				}
				if ($ws) {
					$ws->nab = $request->nab;
					$ws->save();
				}
				return response()->json([
					'message' => 'Data has been Updated',
					'success' => true,
					'status' => 200,
				]);
			} else {
				$data = [];
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
			if (
				in_array($request->kategori, $this->categoryKebisingan)
			) {
				$data = WsValueUdara::where('id', $request->id)->first();
				$data->tindakan = $request->tindakan;
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

	public function updateNab(Request $request)
	{

		try {
			if (
				in_array($request->kategori, $this->categoryKebisingan)
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

	public function handleReject(Request $request)
	{
		DB::beginTransaction();
		try {
			$dataLapangan = DataLapanganKebisinganPersonal::where('no_sampel', $request->no_sampel)->update([
				'is_approve' => 0,
			]);

			KebisinganHeader::where('no_sampel', $request->no_sampel)
				->update([
					'is_approved' => 0
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

	public function handleApproveSelected(Request $request)
	{
		OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 1]);
		
		KebisinganHeader::whereIn('no_sampel', $request->no_sampel_list)
			->update([
				'lhps' => 1,
			]);
		return response()->json([
			'message' => 'Data berhasil diapprove.',
			'success' => true,
		], 200);
	}

	public function getRegulasi(Request $request)
	{
		$data = MasterRegulasi::where('id_kategori', 4)
			->where('is_active', true)
			->get();
		return response()->json([
			'data' => $data
		], 200);
	}

	public function getTableRegulasi(Request $request)
	{
		$data = DB::table('tabel_regulasi')
			->whereJsonContains('id_regulasi', (string)$request->id)
			->first();

		return response()->json([
			'data' => $data
		], 200);
	}
}