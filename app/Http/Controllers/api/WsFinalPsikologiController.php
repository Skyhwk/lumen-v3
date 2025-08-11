<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use App\Models\PsikologiHeader;
use App\Models\DataPsikologi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DataLapanganPsikologi;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class WsFinalPsikologiController extends Controller
{

	public function index(Request $request)
	{
		$baseFilter = [
			['kategori_2', '4-Udara'],
			['status', 0]
		];

		$query = OrderDetail::with('data_lapangan_psikologi')
			->where($baseFilter)
			->whereJsonContains('parameter', ['318;Psikologi'])
			->whereNotNull('tanggal_terima');

		if ($request->filled('is_active')) {
			$query->where('is_active', $request->is_active);
		}

		$data = $query->select('no_order', 'nama_perusahaan', 'tanggal_sampling', 'cfr', DB::raw('COUNT(*) as total'))
			->groupBy('no_order', 'nama_perusahaan', 'tanggal_sampling', 'cfr')
			->get();

		$orderKeys = $data->map(fn($item) => [$item->no_order, $item->cfr]);

		$data2 = OrderDetail::with('data_lapangan_psikologi')
			->where($baseFilter)
			->whereJsonContains('parameter', ['318;Psikologi'])
			->where(function ($q) use ($orderKeys) {
				foreach ($orderKeys as [$no_order, $cfr]) {
					$q->orWhere(fn($sub) => $sub->where('no_order', $no_order)->where('cfr', $cfr));
				}
			})->get();

		$groupedData2 = $data2->groupBy(fn($item) => $item->no_order . '|' . $item->cfr);

		foreach ($data as $mainItem) {
			$key = $mainItem->no_order . '|' . $mainItem->cfr;
			$details = $groupedData2->get($key, collect());

			$data_lapangan = $details->map(function ($detail) {
				$hasil = optional($detail->data_lapangan_psikologi)->hasil;
				return $hasil ? 'ada' : null;
			})->toArray();

			$mainItem->data_lapangan = $data_lapangan;
			$mainItem->jumlah_data_lapangan = count($data_lapangan);
			$mainItem->jumlah_data_lapangan_berhasil = collect($data_lapangan)->filter(fn($item) => !empty((array) $item))->count();
			$mainItem->status_lapangan = $mainItem->jumlah_data_lapangan === $mainItem->jumlah_data_lapangan_berhasil
				? 'SELESAI'
				: 'BELUM SELESAI';
		}

		return Datatables::of($data)->make(true);
	}


	public function dataByOrder(Request $request)
	{
		$data = OrderDetail::with([
			'dataPsikologi',
			'data_lapangan_psikologi' => function ($query) {
				$query->orderBy('divisi');
			}
		])
			->where('no_order', $request->no_order)
			->where('cfr', $request->cfr)
			->where('kategori_2', '4-Udara')
			->where('status', 0)
			->where('is_active', true)
			->whereJsonContains('parameter', ["318;Psikologi"])
			// ->whereNotNull('tanggal_terima')
			->get();

		$grouped = [];

		foreach ($data as $item) {
			$perusahaan = $item->nama_perusahaan ?? 'UNKNOWN';
			$alamat = $item->alamat_perusahaan ?? 'UNKNOWN';
			$key = $item->no_order . '|' . $perusahaan;

			if (!isset($grouped[$key])) {
				$grouped[$key] = [
					'cfr' => $item->cfr,
					'no_order' => $item->no_order,
					'nama_perusahaan' => $perusahaan,
					'alamat_perusahaan' => $alamat,
					'data_lapangan' => []
				];
			}

			if ($item->data_lapangan_psikologi) {
				$lapangan = $item->data_lapangan_psikologi;
				$lapangan->hasil = json_decode($lapangan->hasil);
				$lapangan->divisi = $lapangan->divisi ?? ($item->divisi ?? '');
				$lapangan->tindakan = $lapangan->tindakan ?? ($item->tindakan ?? '');
				$grouped[$key]['data_lapangan'][] = $lapangan;
			} else {
				$pekerja = explode('.', $item->keterangan_1);
				$item->nama_pekerja = $pekerja[0] ?? 'UNKNOWN';
				$item->divisi = $pekerja[1] ?? 'UNKNOWN';
				$grouped[$key]['data_lapangan'][] = $item;
			}
		}
		foreach ($grouped as &$group) {
			usort($group['data_lapangan'], function ($a, $b) {
				return strcmp($a->divisi ?? '', $b->divisi ?? '');
			});
		}
		unset($group);

		$dataPsiko = DataPsikologi::where('no_order', $request->no_order)->where('no_cfr', $request->cfr)->first();

		return response()->json([
			'data' => array_values($grouped),
			'data_psikologi' => $dataPsiko ?? null,
			'status' => 200,
			'message' => 'success get data'
		], 200);
	}

	public function updateTindakan(Request $request)
	{
		$lapangan = DataLapanganPsikologi::where('no_sampel', $request->no_sampel)->first();
		if (!$lapangan) {
			return response()->json([
				'message' => 'data lapangan not found',
				'status' => 404,
				'success' => false
			], 404);
		}
		$lapangan->tindakan = $request->tindakan;
		$lapangan->save();

		return response()->json([
			'message' => 'success',
			'status' => 200,
			'success' => true
		], 200);
	}

	public function submitDataPsikologi(Request $request)
	{
		// dd($request->all());
		DB::beginTransaction();
		try {
			$exists = DataPsikologi::where('no_order', $request->no_order)
				->where('no_cfr', $request->no_cfr)
				->where('is_active', true)
				->first();

			$waktu_pemeriksaan = $request->waktu_pemeriksaan_awal . ' - ' . $request->waktu_pemeriksaan_akhir;
			// dd($waktu_pemeriksaan);
			$ahli = explode("-", $request->no_skp_ahli_k3);
			if ($exists) {
				$exists->update([
					'penanggung_jawab' => $request->penanggung_jawab,
					'no_dokumen' => $request->no_dokumen,
					'no_skp_pjk3' => $request->no_skp_pjk3,
					'no_skp_ahli_k3' => $ahli[1],
					'nama_skp_ahli_k3' => $ahli[0],
					'tanggal_pemeriksaan' => $request->tanggal_pemeriksaan,
					'waktu_pemeriksaan' => $waktu_pemeriksaan,
					'updated_by' => $this->karyawan,
					'updated_at' => Carbon::now(),
				]);
			} else {
				DataPsikologi::create(
					[
						'no_order' => $request->no_order,
						'no_cfr' => $request->no_cfr,
						'penanggung_jawab' => $request->penanggung_jawab,
						'no_dokumen' => $request->no_dokumen,
						'no_skp_pjk3' => $request->no_skp_pjk3,
						'no_skp_ahli_k3' => $ahli[1],
						'nama_skp_ahli_k3' => $ahli[0],
						'tanggal_pemeriksaan' => $request->tanggal_pemeriksaan,
						'waktu_pemeriksaan' => $waktu_pemeriksaan,
						'created_by' => $this->karyawan,
						'created_at' => Carbon::now(),
					]
				);
			}
			DB::commit();
			return response()->json([
				'message' => 'success create data',
				'status' => 200,
				'success' => true
			], 200);
		} catch (\Exception $th) {
			DB::rollBack();
			return response()->json([
				'message' => $th->getMessage(),
				'status' => 401,
				'line' => $th->getLine()
			], 500);
		}
	}


	public function detail(Request $request)
	{
		try {
			$data = PsikologiHeader::with('data_lapangan')
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', true)
				->where('is_active', true)
				->first();
			if ($data->data_lapangan) {
				$data->data_lapangan->hasil = json_decode($data->data_lapangan->hasil);
			} else {
				$data->data_lapangan = null;
			}

			return response()->json($data, 200);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 401
			], 401);
		}
	}

	public function handleApprove(Request $request)
	{
		DB::beginTransaction();
		try {
			$data = OrderDetail::where('no_sampel', $request->no_sampel)
				->where('kategori_2', '4-Udara')
				->where('status', 0)
				->where('is_active', true)
				->first();
			$data->status = 2;
			$data->save();
			DB::commit();
			return response()->json([
				'message' => 'success',
				'status' => 200,
				'success' => true
			], 200);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'line' => $e->getLine(),
				'menu' => $e->getFile(),
				'status' => 401
			], 401);
		}
	}

}