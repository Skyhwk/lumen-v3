<?php

namespace App\Http\Controllers\api;

use App\Models\DataPsikologi;
use App\Models\OrderDetail;
use App\Models\PsikologiHeader;
use App\Models\LhppUdaraPsikologiHeader;
use App\Models\LhppUdaraPsikologiDetail;
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
use App\Services\LhpTemplate;

class LhppUdaraPsikologiController extends Controller
{
	public function index(Request $request)
	{
		$data = OrderDetail::with('dataPsikologi', 'lhpp_psikologi')->select('no_order')
			->where('is_active', $request->is_active)
			->where('kategori_2', '4-Udara')
			->where('status', 2)
			->whereJsonContains('parameter', [
				"318;Psikologi"
			])
			->whereNotNull('tanggal_terima')
			->select('no_order', 'cfr', "tanggal_sampling", "nama_perusahaan", DB::raw('COUNT(*) as total'))
			->groupBy('no_order', 'cfr', "tanggal_sampling", "nama_perusahaan")
			->get();

		return Datatables::of($data)->make(true);
	}

	public function getOrder(Request $request)
	{
		$data = OrderHeader::with(['quotationNonKontrak', 'quotationKontrakH', 'lhpp_psikologi'])->where('no_order', $request->no_order)->first();
		return $data;
	}

	public function dataByOrder(Request $request)
	{
		$lhpp = LhppUdaraPsikologiHeader::where('no_order', $request->no_order)->where('no_cfr', $request->cfr)->first();
		$data = OrderDetail::with([
			'dataPsikologi',
			'data_lapangan_psikologi' => function ($query) {
				$query->orderBy('divisi');
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
				return strcmp($a->divisi ?? '', $b->divisi ?? '');
			});
		}
		unset($group);
		$dataPsiko = DataPsikologi::where('no_order', $request->no_order)->where('no_cfr', $request->cfr)->first();
		return response()->json([
			'data' => array_values($grouped),
			'lhpp' => $lhpp ?? ($dataPsiko ?? null),
			'status' => 200,
			'message' => 'success get data'
		], 200);
	}

	public function store(Request $request)
	{
		DB::beginTransaction();

		try {
			$data = LhppUdaraPsikologiHeader::where('no_order', $request->no_order)->where('no_cfr', $request->cfr)->first();
			$ahli = explode("-", $request->no_skp_ahli_k3);
			$waktu_pemeriksaan = $request->waktu_pemeriksaan_awal . ' - ' . $request->waktu_pemeriksaan_akhir;
			if ($data) {
				$data->nama_perusahaan = $request->nama_perusahaan;
				$data->alamat_perusahaan = $request->alamat_perusahaan;
				$data->penanggung_jawab = $request->penanggung_jawab;
				$data->lokasi_pemeriksaan = $request->lokasi_pemeriksaan;
				// $data->no_dokumen = $request->no_dokumen;
				$data->no_skp_pjk3 = $request->no_skp_pjk3;
				$data->no_skp_ahli_k3 = $ahli[1];
				$data->nama_skp_ahli_k3 = $ahli[0];
				$data->tanggal_pemeriksaan = $request->tanggal_pemeriksaan;
				$data->waktu_pemeriksaan = $waktu_pemeriksaan;
				$data->updated_at = Carbon::now();
				$data->updated_by = $this->karyawan;
				$data->save();
			} else {
				// Insert jika tidak ada
				$data = new LhppUdaraPsikologiHeader();
				$data->no_order = $request->no_order;
				$data->no_cfr = $request->no_cfr;
				$data->nama_perusahaan = $request->nama_perusahaan;
				$data->alamat_perusahaan = $request->alamat_perusahaan;
				$data->penanggung_jawab = $request->penanggung_jawab;
				$data->lokasi_pemeriksaan = $request->lokasi_pemeriksaan;
				// $data->no_dokumen = $request->no_dokumen;
				$data->no_skp_pjk3 = $request->no_skp_pjk3;
				$data->no_skp_ahli_k3 = $ahli[1];
				$data->nama_skp_ahli_k3 = $ahli[0];
				$data->tanggal_pemeriksaan = $request->tanggal_pemeriksaan;
				$data->waktu_pemeriksaan = $waktu_pemeriksaan;
				$data->created_at = Carbon::now();
				$data->created_by = $this->karyawan;
				$data->save();
			}

			// Cek apakah detail untuk header ini sudah ada (asumsi hanya 1 detail per header)
			$detail = LhppUdaraPsikologiDetail::where('id_header', $data->id)->first();

			if ($detail) {
				LhppUdaraPsikologiDetail::where('id_header', $data->id)->delete();

				foreach ($request->hasil as $key => $value) {
					$detail = new LhppUdaraPsikologiDetail();
					$detail->id_header = $data->id;
					$detail->no_sampel = $value[0]['no_sampel'];
					$detail->divisi = $value[0]['divisi'];
					$detail->tindakan = $value[0]['tindakan'];
					$detail->hasil = json_encode($value);
					$detail->created_at = Carbon::now();
					$detail->created_by = $this->karyawan;
					$detail->save();
				}
			} else {
				// Insert detail baru
				foreach ($request->hasil as $key => $value) {
					$detail = new LhppUdaraPsikologiDetail();
					$detail->id_header = $data->id;
					$detail->no_sampel = $value[0]['no_sampel'];
					$detail->divisi = $value[0]['divisi'];
					$detail->tindakan = $value[0]['tindakan'];
					$detail->hasil = json_encode($value);
					$detail->created_at = Carbon::now();
					$detail->created_by = $this->karyawan;
					$detail->save();
				}
			}

			$header = LhppUdaraPsikologiHeader::where('id', $data->id)->where('is_active', true)->first();
			$detail = LhppUdaraPsikologiDetail::where('id_header', $header->id)->get();
			if ($header != null) {
				$file_qr = new GenerateQrDocumentLhpp();
				$file_qr_k3 = $file_qr->insert('LHPP_PSIKOLOGI_K3', $header, $data->nama_skp_ahli_k3, "k3");
				$file_qr = $file_qr->insert('LHPP_PSIKOLOGI', $header, 'Kharina Waty', '');

				// dd($file_qr_hafizh, $file_qr_waty);
				if ($file_qr_k3 && $file_qr) {
					// $filename = explode('-', $file_qr_hafizh)[0] . '-' . explode('-', $file_qr_hafizh)[1];
					$header->file_qr = $file_qr . '.svg';
					$header->file_qr_k3 = $file_qr_k3 . '.svg';
					$header->save();
				}


				$job = new RenderLhpp($header, $detail, 'downloadLHPP', $request->cfr);
				$this->dispatch($job);
				$fileName = 'LHPP-' . str_replace("/", "-", $request->cfr) . '.pdf';


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

	public function handleGenerateLink(Request $request)
	{
		DB::beginTransaction();
		try {
			$header = LhppUdaraPsikologiHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
			$quot = OrderHeader::with(['quotationNonKontrak', 'quotationKontrakH'])->where('no_order', $header->no_order)->first();
			// dd($quot);
			// $quot = Self::getOrder($header->no_order);
			if ($quot->quotationNonKontrak != null) {
				$id_quot = $quot->quotationNonKontrak->id;
			} else {
				$id_quot = $quot->quotationKontrakH->id;
			}
			if ($header != null) {
				$key = $header->no_order . str_replace('.', '', microtime(true));
				$gen = MD5($key);
				$gen_tahun = self::encrypt(DATE('Y-m-d'));
				$token = self::encrypt($gen . '|' . $gen_tahun);

				$insertData = [
					'token' => $token,
					'key' => $gen,
					'id_quotation' => $id_quot,
					'quotation_status' => "lhpp_psikologi",
					'type' => 'lhpp',
					'expired' => Carbon::now()->addYear()->format('Y-m-d'),
					'fileName_pdf' => $header->file_lhp,
					'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
					'created_by' => $this->karyawan
				];

				// dd($insertData);

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
			$link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'lhpp_psikologi', 'type' => 'lhpp'])->first();

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
			if ($request->id_lhpp != '' || isset($request->id_lhpp)) {
				$data = LhppUdaraPsikologiHeader::where('id', $request->id_lhpp)->update([
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
}