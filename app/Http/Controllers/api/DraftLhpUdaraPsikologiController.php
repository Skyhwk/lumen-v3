<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use App\Models\PsikologiHeader;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\LhpUdaraPsikologiDetail;
use App\Models\LhpUdaraPsikologiDetailHistory;
use App\Models\LhpUdaraPsikologiHeaderHistory;
use App\Services\GenerateQrDocumentLhpp;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use App\Jobs\RenderLhpp;
use App\Models\GenerateLink;
use App\Models\MasterKaryawan;
use App\Models\OrderHeader;
use App\Services\SendEmail;
use App\Services\TemplateLhpp;
use App\Jobs\JobPrintLhp;


class DraftLhpUdaraPsikologiController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::with('dataPsikologi', 'lhp_psikologi')
			->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->where('status', 2)
			->whereJsonContains('parameter', [
				"318;Psikologi"
			])
			->whereNotNull('tanggal_terima')
			->select('no_order', 'no_quotation', 'cfr', "tanggal_sampling", "nama_perusahaan", DB::raw('COUNT(*) as total'))
			->groupBy('no_order', 'no_quotation', 'cfr', "tanggal_sampling", "nama_perusahaan")
			->get();

		return Datatables::of($data)->make(true);
	}

	public function getOrder(Request $request)
	{
		$data = OrderHeader::with(['quotationNonKontrak', 'quotationKontrakH', 'lhp_psikologi'])->where('no_order', $request->no_order)->first();
		return $data;
	}

	public function dataByOrder(Request $request)
	{
		$lhpp = LhpUdaraPsikologiHeader::where('no_order', $request->no_order)->first();
		$data = OrderDetail::with([
			'dataPsikologi',
			'data_lapangan_psikologi' => function ($query) {
				$query->orderByRaw("CAST(SUBSTRING_INDEX(no_sampel, '/', -1) AS UNSIGNED)")->orderBy('divisi');
			}
		])
			->where('no_order', $request->no_order)
			->where('cfr', $request->cfr)
			->where('kategori_2', '4-Udara')
			->where('status', 2)
			->whereJsonContains('parameter', ["318;Psikologi"])
			->whereNotNull('tanggal_terima')
			->get();

		// dd($data);
		$grouped = [];

		foreach ($data as $item) {
			$perusahaan = $item->nama_perusahaan ?? 'UNKNOWN';
			$alamat = $item->alamat_perusahaan ?? 'UNKNOWN';
			$key = $item->no_order . '|' . $perusahaan;

			if (!isset($grouped[$key])) {
				$grouped[$key] = [
					'no_order' => $item->no_order,
					'tanggal_sampling' => $item->tanggal_sampling,
					'nama_perusahaan' => $perusahaan,
					'alamat_perusahaan' => $alamat,
					'data_lapangan' => [],
					'cfr' => $item->cfr,
					'no_quotation' => $item->no_quotation
				];
			}

			if ($item->data_lapangan_psikologi) {
				$lapangan = $item->data_lapangan_psikologi;
				$lapangan->hasil = json_decode($lapangan->hasil);
				$lapangan->divisi = $lapangan->divisi ?? ($item->divisi ?? '');
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
				$divA = $a->divisi ?? '';
				$divB = $b->divisi ?? '';

				if ($divA !== $divB) {
					return strcmp($divA, $divB);
				}

				// Jika divisinya sama, urutkan berdasarkan angka setelah '/'
				$noA = preg_match('/\/(\d+)$/', $a->no_sampel ?? $a->nosample ?? '', $matchA) ? (int)$matchA[1] : 0;
				$noB = preg_match('/\/(\d+)$/', $b->no_sampel ?? $b->nosample ?? '', $matchB) ? (int)$matchB[1] : 0;

				return $noA <=> $noB;
			});
		}
		unset($group);

		return response()->json([
			'data' => array_values($grouped),
			'lhpp' => $lhpp ?? ($data[0]->dataPsikologi ?? null),
			'status' => 200,
			'message' => 'success get data'
		], 200);
	}

	public function store(Request $request)
	{
		// dd('This endpoint is deprecated. Please use the new endpoint f or storing LHP data.');
		DB::beginTransaction();
		try {
			$data = LhpUdaraPsikologiHeader::where('no_order', $request->no_order)->first();
			$order = OrderHeader::where('no_order', $request->no_order)->first();

			$alamat = '';

			if (trim($request->alamat_perusahaan) == trim($order->alamat_kantor)) {
				$alamat = $order->alamat_sampling;
			} else {
				$alamat = $request->alamat_perusahaan;
			}

			$waktu_pemeriksaan = $request->waktu_pemeriksaan_awal . ' - ' . $request->waktu_pemeriksaan_akhir;
			if ($data) {
				$data->nama_perusahaan = $request->nama_perusahaan;
				$data->alamat_perusahaan = $alamat;
				$data->penanggung_jawab = $request->penanggung_jawab;
				$data->lokasi_pemeriksaan = $request->lokasi_pemeriksaan;
				$data->no_quotation = $request->no_quotation;
				$data->no_cfr = $request->cfr;
				$data->tanggal_rilis_lhp = $request->tanggal_rilis_lhp;
				// $data->no_dokumen = $request->no_dokumen;
				$data->no_skp_pjk3 = $request->no_skp_pjk3;
				$data->no_skp_ahli_k3 = $request->no_skp_ahli_k3;
				$data->tanggal_pemeriksaan = $request->tanggal_pemeriksaan;
				$data->waktu_pemeriksaan = $waktu_pemeriksaan;
				$data->updated_at = Carbon::now();
				$data->updated_by = $this->karyawan;
				$data->save();
			} else {
				// Insert jika tidak ada
				$data = new LhpUdaraPsikologiHeader();
				$data->no_order = $request->no_order;
				$data->no_cfr = $request->cfr;
				$data->nama_perusahaan = $request->nama_perusahaan;
				$data->alamat_perusahaan = $alamat;
				$data->penanggung_jawab = $request->penanggung_jawab;
				$data->lokasi_pemeriksaan = $request->lokasi_pemeriksaan;
				$data->tanggal_rilis_lhp = $request->tanggal_rilis_lhp;
				// $data->no_dokumen = $request->no_dokumen;
				$data->no_quotation = $request->no_quotation;
				$data->no_skp_pjk3 = $request->no_skp_pjk3;
				$data->no_skp_ahli_k3 = $request->no_skp_ahli_k3;
				$data->tanggal_pemeriksaan = $request->tanggal_pemeriksaan;
				$data->waktu_pemeriksaan = $waktu_pemeriksaan;
				$data->created_at = Carbon::now();
				$data->created_by = $this->karyawan;
				$data->save();
			}

			// Cek apakah detail untuk header ini sudah ada (asumsi hanya 1 detail per header)
			$detail = LhpUdaraPsikologiDetail::where('id_header', $data->id)->first();

			if ($detail) {
				LhpUdaraPsikologiDetail::where('id_header', $data->id)->delete();

				foreach (json_decode($request->hasil) as $key => $value) {
					$detail = new LhpUdaraPsikologiDetail();
					$detail->id_header = $data->id;
					$detail->no_sampel = $value[0]->no_sampel;
					$detail->divisi = $value[0]->divisi;
					$detail->tindakan = $value[0]->tindakan;
					$detail->hasil = json_encode($value);
					$detail->created_at = Carbon::now();
					$detail->created_by = $this->karyawan;
					$detail->save();
				}
			} else {
				// Insert detail baru
				foreach (json_decode($request->hasil) as $key => $value) {
					$detail = new LhpUdaraPsikologiDetail();
					$detail->id_header = $data->id;
					$detail->no_sampel = $value[0]->no_sampel;
					$detail->divisi = $value[0]->divisi;
					$detail->tindakan = $value[0]->tindakan;
					$detail->hasil = json_encode($value);
					$detail->created_at = Carbon::now();
					$detail->created_by = $this->karyawan;
					$detail->save();
				}
			}


			$header = LhpUdaraPsikologiHeader::where('id', $data->id)->where('is_active', true)->first();
			$detail = LhpUdaraPsikologiDetail::where('id_header', $header->id)->get();

			if ($header != null) {
				$qr = new GenerateQrDocumentLhpp();
				$file_qr = $qr->insert('LHP_PSIKOLOGI', $header, 'Kharina Waty', '');


				if ($file_qr) {
					$header->file_qr = $file_qr . '.svg';
					$header->save();
				}
				// dd('This endpoint is deprecated. Please use the new endpoint for generating LHP PDF.');
				$render = app(TemplateLhpp::class);
				$render->lhpp_psikologi($header, $detail, 'downloadLHP', $request->cfr);
				// $job = new RenderLhpp($header, $detail, 'downloadLHP', $request->cfr);
				// $this->dispatch($job);
				$fileName = 'LHP-' . str_replace("/", "-", $request->cfr) . '.pdf';

				// $fileName = 'LHPP-' . str_replace("/", "-", $request->cfr) . '.pdf';

				$header->no_dokumen = $fileName;
				$header->save();
			}

			DB::commit();
			return response()->json([
				'message' => 'success',
				'status' => 200,
				'success' => true
			], 200);
		} catch (Exception $e) {
			DB::rollBack();
			// dd($e);
			return response()->json([
				'message' => $e->getMessage(),
				'line' => $e->getLine(),
				'menu' => $e->getFile(),
				'status' => 401
			], 401);
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

	// public function handleApprove(Request $request)
	// {
	// 	DB::beginTransaction();
	// 	try {
	// 		$data = OrderDetail::where('no_sampel', $request->no_sampel)
	// 			->where('kategori_2', '4-Udara')
	// 			->where('status', 0)
	// 			->where('is_active', true)
	// 			->first();
	// 		$data->status = 2;
	// 		$data->save();
	// 		DB::commit();
	// 		return response()->json([
	// 			'message' => 'success',
	// 			'status' => 200,
	// 			'success' => true
	// 		], 200);
	// 	} catch (Exception $e) {
	// 		DB::rollBack();
	// 		return response()->json([
	// 			'message' => $e->getMessage(),
	// 			'line' => $e->getLine(),
	// 			'menu' => $e->getFile(),
	// 			'status' => 401
	// 		], 401);
	// 	}
	// }
	public function handleApprove(Request $request)
	{
		DB::beginTransaction();
		try {
			$data = OrderDetail::where('no_order', $request->no_order)
				->where('cfr', $request->cfr)
				->where('kategori_2', '4-Udara')
				->where('status', 2)
				->whereJsonContains('parameter', ["318;Psikologi"])
				->whereNotNull('tanggal_terima')
				->get();
			if ($data->isEmpty()) {
				return response()->json([
					'message' => 'Data tidak ditemukan',
					'status' => 404
				], 200);
			}

			$header = LhpUdaraPsikologiHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
			$header->approve_at = Carbon::now();
			$header->approve_by = $this->karyawan;
			$header->save();
			
			try {
				$this->dispatch(new JobPrintLhp($request->cfr, 'draftPsikologi'));
			} catch (\Exception $e) {
				DB::rollBack();
				return response()->json([
					'message' => 'Gagal mengirim job cetak: ' . $e->getMessage(),
					'status' => 500
				], 200);
			}
			foreach ($data as $item) {
				// Update status dulu sebelum dispatch
				$item->status = 3;
				$item->save();
			}

			DB::commit();
			return response()->json([
				'message' => 'Semua job print berhasil dikirim.',
				'status' => 201,
				'success' => true
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile(),
				'status' => 500
			], 500);
		}
	}


	public function handleGenerateLink(Request $request)
	{
		DB::beginTransaction();
		try {
			$header = LhpUdaraPsikologiHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
			if ($header != null) {
				$key = $header->no_order . str_replace('.', '', microtime(true));
				$gen = MD5($key);
				$gen_tahun = self::encrypt(DATE('Y-m-d'));
				$token = self::encrypt($gen . '|' . $gen_tahun);

				$insertData = [
					'token' => $token,
					'key' => $gen,
					'id_quotation' => $header->id,
					'quotation_status' => "lhp_psikologi",
					'type' => 'lhpp',
					'expired' => Carbon::now()->addYear()->format('Y-m-d'),
					'fileName_pdf' => $header->no_dokumen,
					'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
					'created_by' => $this->karyawan
				];
				$insert = GenerateLink::insertGetId($insertData);

				$header->is_generated = true;
				$header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
				$header->generated_by = $this->karyawan;
				$header->id_token = $insert;
				$header->expired = Carbon::now()->addYear()->format('Y-m-d');
				$header->save();
			}

			DB::commit();
			return response()->json([
				'message' => 'Generate link success!',
			]);
		} catch (Exception $e) {
			DB::rollBack();
			\Log::info("message: " . $e->getMessage() . " line: " . $e->getLine() . " menu: " . $e->getFile());
			return response()->json([
				'message' => $e->getMessage(),
			], 401);
		}
	}

	public function encrypt($data)
	{
		$ENCRYPTION_KEY = 'intilab_jaya';
		$ENCRYPTION_ALGORITHM = 'AES-256-CBC';
		$EncryptionKey = base64_decode($ENCRYPTION_KEY);
		$InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
		$EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
		$return = base64_encode($EncryptedText . '::' . $InitializationVector);
		return $return;
	}

	public function decrypt($data = null)
	{
		$ENCRYPTION_KEY = 'intilab_jaya';
		$ENCRYPTION_ALGORITHM = 'AES-256-CBC';
		$EncryptionKey = base64_decode($ENCRYPTION_KEY);
		list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
		$data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
		$extand = explode("|", $data);
		return $extand;
	}

	public function getUser(Request $request)
	{
		$users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

		return response()->json($users);
	}

	// public function getLink(Request $request)
	// {
	// 	try {
	// 		$link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'lhp_psikologi', 'type' => 'lhpp'])->first();

	// 		if (!$link) {
	// 			return response()->json(['message' => 'Link not found'], 404);
	// 		}
	// 		return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
	// 	} catch (Exception $e) {
	// 		return response()->json(['message' => $e->getMessage()], 400);
	// 	}
	// }
	public function getLink(Request $request)
	{
		try {
			$link = GenerateLink::where(['id' => $request->id])->first();
			if (!$link) {
				return response()->json(['message' => 'Link not found'], 404);
			}
			return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
		} catch (Exception $e) {
			return response()->json(['message' => $e->getMessage()], 400);
		}
	}


	public function sendEmail(Request $request)
	{
		DB::beginTransaction();
		try {
			if ($request->id_lhp != '' || isset($request->id_lhp)) {
				$data = LhpUdaraPsikologiHeader::where('id', $request->id_lhp)->update([
					'is_emailed' => true,
					'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
					'emailed_by' => $this->karyawan
				]);
			}

			$email = SendEmail::where('to', $request->to)
				->where('subject', $request->subject)
				->where('body', $request->content)
				->where('cc', $request->cc)
				->where('bcc', $request->bcc)
				->where('attachments', $request->attachments)
				->where('karyawan', $this->karyawan)
				->noReply()
				->send();

			if ($email) {
				DB::commit();
				return response()->json([
					'message' => 'Email berhasil dikirim'
				], 200);
			} else {
				DB::rollBack();
				return response()->json([
					'message' => 'Email gagal dikirim'
				], 400);
			}
		} catch (\Exception $th) {
			DB::rollBack();
			return response()->json([
				'message' => $th->getMessage()
			], 500);
		}
	}

	public function handleReject(Request $request)
	{
		DB::beginTransaction();
		try {
			$data = LhpUdaraPsikologiHeader::where('no_cfr', $request->cfr)->first();
			// dd($data);
			if ($data) {

				$lhpsHistory = $data->replicate();
                $lhpsHistory->setTable((new LhpUdaraPsikologiHeaderHistory())->getTable());
                $lhpsHistory->id = $data->id;
                $lhpsHistory->created_at = $data->created_at;
                $lhpsHistory->updated_at = $data->updated_at;
                $lhpsHistory->deleted_at = Carbon::now();
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

				$oldDetails = LhpUdaraPsikologiDetail::where('id_header', $data->id)->get();
                if ($oldDetails->isNotEmpty()) {
                    foreach ($oldDetails as $detail) {
                        $detailHistory = $detail->replicate();
                        $detailHistory->setTable((new LhpUdaraPsikologiDetailHistory())->getTable());
                        $detailHistory->id = $detail->id;
                        $detailHistory->created_by = $this->karyawan;
                        $detailHistory->created_at = Carbon::now();
                        $detailHistory->save();
                    }
                    LhpUdaraPsikologiDetail::where('id_header', $data->id)->delete();
                }

				$order = OrderDetail::where('no_order', $data->no_order)
					->where('cfr', $data->no_cfr)
					->where('kategori_2', '4-Udara')
					->where('status', 2)
					->whereJsonContains('parameter', ["318;Psikologi"])
					->whereNotNull('tanggal_terima')
					->update([
						'status' => 0,
					]);

				$data->delete();
			}

			DB::commit();
			return response()->json([
				'message' => 'Data berhasil direject',
				'status' => 200
			], 200);
		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => $e->getMessage(),
				'status' => 500
			], 500);
		}
	}
}