<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\RenderLhpp;
use App\Jobs\JobPrintLhp;
use App\Jobs\CombineLHPJob;
use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\PsikologiHeader;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\LhpUdaraPsikologiDetail;
use App\Models\LhpUdaraPsikologiDetailHistory;
use App\Models\LhpUdaraPsikologiHeaderHistory;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;
use App\Models\LinkLhp;
use App\Models\PengesahanLhp;
use App\Models\GenerateLink;
use App\Services\GenerateQrDocumentLhpp;
use App\Services\PrintLhp;
use App\Services\SendEmail;
use App\Services\TemplateLhpp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DraftLhpUdaraPsikologiController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::with('dataPsikologi', 'lhp_psikologi')
			->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->where('status', 2)
			->whereJsonContains('parameter', ["318;Psikologi"])
			->whereNotNull('tanggal_terima')
			->select(
				'no_order',
				'no_quotation',
				'cfr',
				'nama_perusahaan',
				DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling ORDER BY tanggal_sampling SEPARATOR ", ") as tanggal_sampling'),
				DB::raw('GROUP_CONCAT(DISTINCT no_sampel ORDER BY no_sampel SEPARATOR ", ") as no_sampel'),
				DB::raw('COUNT(*) as total')
			)
			->groupBy('no_order', 'no_quotation', 'cfr', 'nama_perusahaan')
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
				$noA = preg_match('/\/(\d+)$/', $a->no_sampel ?? $a->nosample ?? '', $matchA) ? (int) $matchA[1] : 0;
				$noB = preg_match('/\/(\d+)$/', $b->no_sampel ?? $b->nosample ?? '', $matchB) ? (int) $matchB[1] : 0;

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
			$order = OrderHeader::where('no_order', $request->no_order)->first();
			$alamat = '';

			if (trim($request->alamat_perusahaan) == trim($order->alamat_kantor)) {
				$alamat = $order->alamat_sampling;
			} else {
				$alamat = $request->alamat_perusahaan;
			}
			$pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_rilis_lhp)
			->orderByDesc('berlaku_mulai')
			->first();
			
			$waktu_pemeriksaan = $request->waktu_pemeriksaan_awal . ' - ' . $request->waktu_pemeriksaan_akhir;

			$header = LhpUdaraPsikologiHeader::where('no_order', $request->no_order)->where('no_cfr', $request->cfr)->first();
			
			if ($header) {
				$header->nama_perusahaan = $request->nama_perusahaan;
				$header->alamat_perusahaan = $alamat;
				$header->penanggung_jawab = $request->penanggung_jawab;
				$header->lokasi_pemeriksaan = $request->lokasi_pemeriksaan;
				$header->no_quotation = $request->no_quotation;
				$header->no_cfr = $request->cfr;
				$header->tanggal_rilis_lhp = $request->tanggal_rilis_lhp;
				// $header->no_dokumen = $request->no_dokumen;
				$header->no_skp_pjk3 = $request->no_skp_pjk3;
				$header->no_skp_ahli_k3 = $request->no_skp_ahli_k3;
				$header->tanggal_pemeriksaan = $request->tanggal_pemeriksaan;
				$header->waktu_pemeriksaan = $waktu_pemeriksaan;
				$header->nama_karyawan = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
				$header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
				$header->updated_at = Carbon::now();
				$header->updated_by = $this->karyawan;
				$header->save();
			} else {
				// Insert jika tidak ada
				$header = new LhpUdaraPsikologiHeader();
				$header->no_order = $request->no_order;
				$header->no_cfr = $request->cfr;
				$header->nama_perusahaan = $request->nama_perusahaan;
				$header->alamat_perusahaan = $alamat;
				$header->penanggung_jawab = $request->penanggung_jawab;
				$header->lokasi_pemeriksaan = $request->lokasi_pemeriksaan;
				$header->tanggal_rilis_lhp = $request->tanggal_rilis_lhp;
				// $header->no_dokumen = $request->no_dokumen;
				$header->no_quotation = $request->no_quotation;
				$header->no_skp_pjk3 = $request->no_skp_pjk3;
				$header->no_skp_ahli_k3 = $request->no_skp_ahli_k3;
				$header->tanggal_pemeriksaan = $request->tanggal_pemeriksaan;
				$header->waktu_pemeriksaan = $waktu_pemeriksaan;
				$header->nama_karyawan = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
				$header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
				$header->created_at = Carbon::now();
				$header->created_by = $this->karyawan;
				$header->save();
			}
			
			// Cek apakah detail untuk header ini sudah ada (asumsi hanya 1 detail per header)
			$detail = LhpUdaraPsikologiDetail::where('id_header', $header->id)->first();

			if ($detail) {
				LhpUdaraPsikologiDetail::where('id_header', $header->id)->delete();

				foreach (json_decode($request->hasil) as $key => $value) {
					$detail = new LhpUdaraPsikologiDetail();
					$detail->id_header = $header->id;
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
					$detail->id_header = $header->id;
					$detail->no_sampel = $value[0]->no_sampel;
					$detail->divisi = $value[0]->divisi;
					$detail->tindakan = $value[0]->tindakan;
					$detail->hasil = json_encode($value);
					$detail->created_at = Carbon::now();
					$detail->created_by = $this->karyawan;
					$detail->save();
				}
			}

			$detail = LhpUdaraPsikologiDetail::where('id_header', $header->id)->get();

			if ($header != null) {
				$qr = new GenerateQrDocumentLhpp();
				$file_qr = $qr->insert('LHP_PSIKOLOGI', $header, $this->karyawan, '');
				if ($header->file_qr == null && $file_qr) {
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
				if($header->no_dokumen == null && $fileName){
					$header->no_dokumen = $fileName;
					$header->save();
				}
			}

			DB::commit();
			return response()->json([
				'message' => 'success',
				'status' => 200,
				'success' => true
			], 200);
		} catch (Exception $e) {
			DB::rollBack();
			dd($e);
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


	public function handleApprove(Request $request)
	{
		try {

			$data = LhpUdaraPsikologiHeader::where('no_cfr', $request->cfr)
				->where('is_active', true)
				->first();

			$no_lhp = $data->no_cfr;

			$detail = LhpUdaraPsikologiDetail::where('id_header', $data->id)->get();

			$qr = QrDocument::where('id_document', $data->id)
				->where('type_document', 'LHP_PSIKOLOGI')
				->where('is_active', 1)
				->where('file', $data->file_qr)
				->orderBy('id', 'desc')
				->first();
			// dd($data, $noSampel, $detail);
			if ($data != null) {
				OrderDetail::where('cfr', $request->cfr)
					// ->whereIn('no_sampel', $noSampel)
					->where('is_active', true)
					->where('status', 2)
					->update([
						'is_approve' => 1,
						'status' => 3,
						'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
						'approved_by' => $this->karyawan
					]);


				$data->is_approve = 1;
				$data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
				$data->approved_by = $this->karyawan;
				// if ($data->count_print < 1) {
				// 	$data->is_printed = 1;
				// 	$data->count_print = $data->count_print + 1;
				// }
				// dd($data->id_kategori_2);

				HistoryAppReject::insert([
					'no_lhp' => $data->no_cfr,
					'no_sampel' => $request->noSampel,
					'kategori_2' => $data->id_kategori_2,
					'kategori_3' => $data->id_kategori_3,
					'menu' => 'Draft Udara',
					'status' => 'approved',
					'approved_at' => Carbon::now(),
					'approved_by' => $this->karyawan
				]);

				if ($qr != null) {
					$dataQr = json_decode($qr->data);
					$dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
					$dataQr->Disahkan_Oleh = $data->nama_karyawan;
					$dataQr->Jabatan = $data->jabatan_karyawan;
					$qr->data = json_encode($dataQr);
					$qr->save();
				}

				// $servicePrint = new PrintLhp();
				// $servicePrint->printByFilename($data->file_lhp, $detail);
				$periode = OrderDetail::where('cfr', $data->no_cfr)->where('is_active', true)->first()->periode ?? null;
				// dd($data, $periode);
				$cekLink = LinkLhp::where('no_order', $data->no_order)->where('periode', $periode)->first();

                if($cekLink) {
					$job = new CombineLHPJob($data->no_cfr, $data->no_dokumen, $data->no_order, $this->karyawan, $periode);
					$this->dispatch($job);
                }

				// if (!$servicePrint) {
				// 	DB::rollBack();
				// 	return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
				// }
			} else {
				DB::rollBack();
				return response()->json(['message' => 'Data draft Psikologi no LHP ' . $no_lhp . ' berhasil diapprove', 'status' => '401'], 401);
			}

			DB::commit();
			return response()->json([
				'data' => $data,
				'status' => true,
				'message' => 'Data draft Psikologi no LHP ' . $no_lhp . ' berhasil diapprove'
			], 201);
		} catch (\Exception $th) {
			DB::rollBack();
			dd($th);
			return response()->json([
				'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
				'status' => false
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
				// $lhpsHistory->id = $data->id;
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
						// $detailHistory->id = $detail->id;
						$detailHistory->created_by = $this->karyawan;
						$detailHistory->created_at = Carbon::now();
						$detailHistory->save();
					}
					LhpUdaraPsikologiDetail::where('id_header', $data->id)->delete();
				}

				OrderDetail::where('no_order', $data->no_order)
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
