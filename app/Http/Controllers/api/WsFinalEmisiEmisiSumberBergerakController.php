<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganEmisiKendaraan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Yajra\Datatables\Datatables;
use Carbon\Carbon;


class WsFinalEmisiEmisiSumberBergerakController extends Controller
{
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
			->where('kategori_2', '5-Emisi')

			->whereNotIn('kategori_3', ['34-Emisi Sumber Tidak Bergerak', '119-Emisi Isokinetik'])
			->where('status', 0)
			->whereNotNull('tanggal_terima')
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
				$item->getAnyDataLapanganEmisi();
				return $item;
			});

		return response()->json([
			'data' => $data,
			'message' => 'Data retrieved successfully',
		], 200);
	}

	public function validasiApproveWSApi(Request $request)
	{
		// dd($request->all());
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
					'menu' => 'WS Final Emisi',
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
	
	public function handleReject(Request $request)
	{
		DB::beginTransaction();
		try {
			DataLapanganEmisiKendaraan::where('no_sampel', $request->no_sampel)->update([
				'is_approve' => 0,
			]);

			// KebisinganHeader::where('no_sampel', $request->no_sampel)
			// 	->update([
			// 		'is_approved' => 0
			// 	]);

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

		return response()->json([
			'message' => 'Data berhasil diapprove.',
			'success' => true,
		], 200);
	}
}
