<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\WsValueEmisiCerobong;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\EmisiCerobongHeader;

use App\Models\Subkontrak;
use App\Models\IsokinetikHeader;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Yajra\Datatables\Datatables;
use Carbon\Carbon;


class WsFinalEmisiEmisiSumberBergerakController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::with(['dataLapanganEmisiKendaraan', 'dataLapanganEmisiCerobong'])->where('is_active', $request->is_active)
			->where('kategori_2', '5-Emisi')
			->where('kategori_3', '!=', '34-Emisi Sumber Tidak Bergerak')
			->where('status', 0)
			->whereNotNull('tanggal_terima')
			->whereMonth('tanggal_terima', explode('-', $request->date)[1])
			->whereYear('tanggal_terima', explode('-', $request->date)[0]);

		return Datatables::of($data)->make(true);
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
}
