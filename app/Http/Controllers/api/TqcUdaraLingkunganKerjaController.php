<?php

namespace App\Http\Controllers\api;

use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DirectLainHeader;
use App\Models\HistoryAppReject;
use App\Models\LhpsGetaranDetail;
use App\Models\LhpsGetaranHeader;

use App\Models\LingkunganHeader;
use App\Models\MasterBakumutu;
use App\Models\OrderDetail;


use App\Models\Subkontrak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TqcUdaraLingkunganKerjaController extends Controller

{
    public function index(Request $request)
    {
        $data = OrderDetail::select(
            'cfr',
            DB::raw('MAX(id) as max_id'),
            'nama_perusahaan',
            'no_quotation',
            'no_order',
            DB::raw('GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ", ") as no_sampel'),
            DB::raw('GROUP_CONCAT(DISTINCT kategori_3 SEPARATOR ", ") as kategori_3'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ", ") as tanggal_sampling'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ", ") as tanggal_terima'),
            'kategori_1',
            'konsultan',
            'regulasi',
        )
            ->where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["27-Udara Lingkungan Kerja"])
            ->where(function ($query) {
                $query->where('parameter', 'not like', '%Power Density%')
                    ->orWhere('parameter', 'not like', '%Medan Magnit Statis%')
                    ->orWhere('parameter', 'not like', '%Medan Listrik%');
            })
            ->where('parameter', 'not like', '%Sinar UV%')
            ->where('parameter', 'not like', '%Ergonomi%')
            ->groupBy('cfr', 'nama_perusahaan', 'no_quotation', 'no_order', 'kategori_1', 'konsultan', 'regulasi')
            ->orderBy('max_id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            if ($data) {
                $data->status = 2;
                $data->save();
                HistoryAppReject::insert([
                    'no_lhp' => $data->cfr,
                    'no_sampel' => $data->no_sampel,
                    'kategori_2' => $data->kategori_2,
                    'kategori_3' => $data->kategori_3,
                    'menu' => 'TQC Udara',
                    'status' => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data tqc no sample ' . $data->no_sampel . ' berhasil diapprove'
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }


    public function rejectData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('cfr', $request->cfr)->where('status', 1)->update([
                'status' => 0
            ]);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data tqc no lhp ' . $request->cfr . ' berhasil direject'
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }


    	public function detail(Request $request)
	{
		try {
			$directData = DirectLainHeader::with(['ws_udara'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', 1)
				->where('status', 0)
				->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
				->addSelect(DB::raw("'direct' as data_type"))
				->get();

			$lingkunganData = LingkunganHeader::with('ws_udara', 'ws_value_linkungan')
				->where('no_sampel', $request->no_sampel)
				->where('is_approved', 1)
				->where('status', 0)
				->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
				->addSelect(DB::raw("'lingkungan' as data_type"))
				->get();
			$subkontrak = Subkontrak::with(['ws_value_linkungan'])
				->where('no_sampel', $request->no_sampel)
				->where('is_approve', 1)
				->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
				->addSelect(DB::raw("'subKontrak' as data_type"))
				->get();



			$combinedData = collect()
				->merge($lingkunganData)
				->merge($subkontrak)
				->merge($directData);


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
				}
				return $item;
			});
			// $id_regulasi = explode("-", json_decode($request->regulasi)[0])[0];
			$id_regulasi = $request->regulasi;
			foreach ($processedData as $item) {

				$dataLapangan = DetailLingkunganHidup::where('no_sampel', $item->no_sampel)
					->select('durasi_pengambilan')
					->where('parameter', $item->parameter)
					->first();
				$bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
					->where('id_regulasi', $id_regulasi)
					->where('is_active', 1)
					->select('baku_mutu', 'satuan', 'method')
					->first();
				$item->durasi = $dataLapangan->durasi_pengambilan ?? null;
				$item->satuan = $bakuMutu->satuan ?? null;
				$item->baku_mutu = $bakuMutu->baku_mutu ?? null;
				$item->method = $bakuMutu->method ?? null;
				$item->nama_header = $bakuMutu->nama_header ?? null;
			
			}


			return Datatables::of($processedData)->make(true);

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
		} else {
			$data = [];
		}
	}

    public function handleApproveSelected(Request $request)
    {
        DB::beginTransaction();
        try {
            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 2,
                ]);

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

    public function handleRejectSelected(Request $request)
    {
        DB::beginTransaction();
        try {

            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 0,
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
}