<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\KelengkapanKonfirmasiQs;
use App\Models\OrderHeader;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use App\Models\Jadwal;
use App\Models\SamplingPlan;

use App\Models\MasterKaryawan;
use App\Services\SamplingPlanServices;

use App\Jobs\RenderSamplingPlan;
use App\Models\AlasanVoidQt;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class KonfirmasiOrderController extends Controller
{
	public function index(Request $request)
	{
		if ($request->status == 'non_kontrak') {
			$data = QuotationNonKontrak::with([
				'sales',
				'sampling' => function ($q) {
					$q->orderBy('periode_kontrak', 'asc');
				},
				'konfirmasi'
			])
				// ->where('id_cabang', $request->cabang)
				->where('flag_status', 'sp')
				->where('is_active', true)
				->where('is_approved', true)
				->where('is_emailed', true)
				->where('is_ready_order', true)
				->where('konfirmasi_order', false)
				->orderBy('tanggal_penawaran', 'desc');
		} else if ($request->status == 'kontrak') {
			$data = QuotationKontrakH::with([
				'sales',
				'detail',
				'sampling' => function ($q) {
					$q->orderBy('periode_kontrak', 'asc');
				},
				'konfirmasi'
			])
				// ->where('id_cabang', $request->cabang)
				->where('flag_status', 'sp')
				->where('is_active', true)
				->where('is_approved', true)
				->where('is_emailed', true)
				->where('is_ready_order', true)
				->where('konfirmasi_order', false)
				->orderBy('tanggal_penawaran', 'desc');
		}

		$jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
		switch ($jabatan) {
			case 24: // Sales Staff
				$data->where('sales_id', $this->user_id);
				break;
			case 21: // Sales Supervisor
				$bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
					->pluck('id')
					->toArray();
				array_push($bawahan, $this->user_id);
				$data->whereIn('sales_id', $bawahan);
				break;
		}

		return DataTables::of($data)
			->filterColumn('data_lama', function ($query, $keyword) {
				if (Str::contains($keyword, 'QS U')) {
					$query->whereNotNull('data_lama')
						->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data_lama, '$.no_order')) IS NOT NULL")
						->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data_lama, '$.no_order')) != 'null'");
				}
			})->make(true);
	}

	public function getOrderDetail(Request $request)
	{
		$data = OrderHeader::with('orderDetail')
			->where('is_active', true)
			->where('id_pelanggan', $request->id_pelanggan)
			->get()
			->flatMap(function ($item) {
				return
					collect($item->orderDetail)
						->pluck('keterangan_1')
						->filter(fn($text) => !empty(trim($text)));

			})
			->unique()
			->values();

		return response()->json([
			'data' => $data,
			'success' => true,
			'message' => 'Data berhasil diambil',
		]);
	}

	public function getDataByPeriode(Request $request)
	{
		$data = KelengkapanKonfirmasiQs::where('periode', $request->periode_kontrak)
			->where('id_quotation', $request->id_quotation)
			->where('no_quotation', $request->no_quotation)
			->where('type', $request->type)
			->first();

		return response()->json([
			'data' => $data,
			'success' => true,
			'message' => 'Data berhasil diambil',
		]);
	}

	public function reject(Request $request)
	{
		DB::beginTransaction();
		try {
			if (isset($request->id) || $request->id != '') {
				if ($request->mode == 'non_kontrak') {
					$data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
					$type_doc = 'quotation';
					if (count(json_decode($data->data_pendukung_sampling)) == 0) {
						$data->is_ready_order = 1;
					}
				} else if ($request->mode == 'kontrak') {
					$data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
					$type_doc = 'quotation_kontrak';
				}

				$data_lama = null;
				if ($data->data_lama != null)
					$data_lama = json_decode($data->data_lama);

				if ($data_lama != null && $data_lama->no_order != null) {
					$json = json_encode([
						'id_qt' => $data_lama->id_qt,
						'no_qt' => $data_lama->no_qt,
						'no_order' => $data_lama->no_order,
						'id_order' => $data_lama->id_order,
						'status_sp' => (string) $request->perubahan_sp
					]);
					$data->data_lama = $json;
				} else {
					if ($data->flag_status == 'sp') {
						$json = json_encode([
							'id_qt' => $data->id,
							'no_qt' => $data->no_document,
							'no_order' => null,
							'id_order' => null,
							'status_sp' => (string) $request->perubahan_sp
						]);
						$data->data_lama = $json;
					}
				}

				$data->is_approved = false;
				$data->approved_by = null;
				$data->approved_at = null;
				$data->flag_status = 'rejected';
				$data->is_rejected = true;
				$data->rejected_by = $this->karyawan;
				$data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
				$data->keterangan_reject = $request->keterangan_reject;
				$data->save();

				DB::commit();
				return response()->json([
					'message' => 'Request Quotation number ' . $data->no_document . ' success rejected.!',
					'status' => '200'
				], 200);
			} else {
				DB::rollback();
				return response()->json([
					'message' => 'Cannot rejected data.!',
					'status' => '401'
				], 401);
			}
		} catch (Exception $e) {
			DB::rollback();
			return response()->json([
				'message' => 'Error: ' . $e->getMessage(),
				'line' => $e->getLine(),
				'status' => '500'
			], 500);
		}
	}

	public function voidQuotation(Request $request)
	{
		DB::beginTransaction();
		try {
			if (isset($request->id) || $request->id != '') {
				if ($request->mode == 'non_kontrak') {
					$data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
					$type_doc = 'quotation';
					if (count(json_decode($data->data_pendukung_sampling)) == 0) {
						$data->is_ready_order = 1;
					}
				} else if ($request->mode == 'kontrak') {
					$data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
					$type_doc = 'quotation_kontrak';
				}
				$sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', true)->update(['is_active' => false]);
				$jadwal = Jadwal::where('no_quotation', $data->no_document)->where('is_active', true)->update(['is_active' => false]);

				$data->flag_status = 'void';
				$data->is_active = false;
				$data->document_status = 'Non Aktif';
				$data->deleted_by = $this->karyawan;
				$data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
				$data->save();

                $alasanVoidQt = new AlasanVoidQt();
                $alasanVoidQt->no_quotation = $data->no_document;
                $alasanVoidQt->alasan = $request->reason;
                $alasanVoidQt->voided_by = $this->karyawan;
                $alasanVoidQt->voided_at = Carbon::now()->format('Y-m-d H:i:s');
                $alasanVoidQt->save();

				DB::commit();
				return response()->json([
					'message' => 'Success void request Quotation number ' . $data->no_document . '.!',
					'status' => '200'
				], 200);
			} else {
				DB::rollback();
				return response()->json([
					'message' => 'Cannot void data.!',
					'status' => '401'
				], 401);
			}
		} catch (Exception $e) {
			DB::rollback();
			return response()->json([
				'message' => 'Error: ' . $e->getMessage(),
				'line' => $e->getLine(),
				'status' => '500'
			], 500);
		}
	}
	public function requestSamplingPlan(Request $request)
	{
		try {
			$dataArray = (object) [
				'no_quotation' => $request->no_quotation,
				'quotation_id' => $request->quotation_id,
				'tanggal_penawaran' => $request->tanggal_penawaran,
				'sampel_id' => $request->sampel_id,
				'tanggal_sampling' => $request->tanggal_sampling,
				'jam_sampling' => $request->jam_sampling,
				'is_sabtu' => $request->is_sabtu,
				'is_minggu' => $request->is_minggu,
				'is_malam' => $request->is_malam,
				'tambahan' => $request->tambahan,
				'keterangan_lain' => $request->keterangan_lain,
				'karyawan' => $this->karyawan
			];
			if ($request->status_quotation == 'kontrak') {
				$dataArray->periode = $request->periode;
				$spServices = SamplingPlanServices::on('insertKontrak', $dataArray)->insertSPKontrak();
			} else {
				$spServices = SamplingPlanServices::on('insertNon', $dataArray)->insertSP();
			}

			if ($spServices) {
				$job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
				$this->dispatch($job);

				return response()->json(['message' => 'Add Request Sampling Plan Success', 'status' => 200], 200);
			}
		} catch (Exception $th) {
			return response()->json(['message' => 'Add Request Sampling Plan Failed: ' . $th->getMessage() . ' Line: ' . $th->getLine() . ' File: ' . $th->getFile() . '', 'status' => 401], 401);
		}
	}

	public function rescheduleSamplingPlan(Request $request)
	{
		try {
			$dataArray = (object) [
				"no_document" => $request->no_document,
				"no_quotation" => $request->no_quotation,
				"quotation_id" => $request->quotation_id,
				"karyawan" => $this->karyawan,
				"tanggal_sampling" => $request->tanggal_sampling,
				"jam_sampling" => $request->jam_sampling,
				'is_sabtu' => $request->is_sabtu,
				'is_minggu' => $request->is_minggu,
				'is_malam' => $request->is_malam,
				"tambahan" => $request->tambahan,
				"keterangan_lain" => $request->keterangan_lain,
				"tanggal_penawaran" => $request->tanggal_penawaran,
			];

			if ($request->sample_id && $request->periode) {
				$dataArray->sample_id = $request->sample_id;
				$dataArray->periode = $request->periode;
				$spServices = SamplingPlanServices::on('insertSingleKontrak', $dataArray)->insertSPSingleKontrak();
			} else {
				$spServices = SamplingPlanServices::on('insertSingleNon', $dataArray)->insertSPSingle();
			}

			if ($spServices) {
				$job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
				$this->dispatch($job);

				return response()->json(['message' => 'Reschedule Request Sampling Plan Success', 'status' => 200], 200);
			}
		} catch (Exception $e) {
			return response()->json(['message' => 'Reschedule Request Sampling Plan Failed: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'status' => 401], 401);
		}
	}

	// public function store(Request $request)
	// {
	// 	DB::beginTransaction();
	// 	try {
	// 		$model = $request->type === 'kontrak' ? QuotationKontrakH::class : QuotationNonKontrak::class;
	// 		$data = $model::where('id', $request->id)->where('no_document', $request->no_document)->first();
	// 		$konfirmasi = $request->id_konfirmasi
	// 			? $request->type == 'kontrak'
	// 			? KelengkapanKonfirmasiQs::where([
	// 				['id_quotation', $request->id],
	// 				['no_quotation', $request->no_document],
	// 				['periode', $request->periode_kontrak],
	// 				['type', $request->type],
	// 			])->first()
	// 			: KelengkapanKonfirmasiQs::where([
	// 				['id_quotation', $request->id],
	// 				['no_quotation', $request->no_document],
	// 				['type', $request->type],
	// 			])->first()
	// 			: new KelengkapanKonfirmasiQs();

	// 		// Handle base64 files
	// 		$fileNames = [];
	// 		$lampiranTitikNames = [];
	// 		$path = 'konfirmasi_order/';
	// 		$savePath = public_path($path);

	// 		// Ensure directory exists and is writable
	// 		if (!file_exists($savePath)) {
	// 			if (!mkdir($savePath, 0755, true)) {
	// 				throw new \Exception('Tidak dapat membuat direktori penyimpanan file');
	// 			}
	// 		}

	// 		// Check if directory is writable
	// 		if (!is_writable($savePath)) {
	// 			throw new \Exception('Direktori penyimpanan tidak dapat ditulis');
	// 		}

	// 		// Process filename files
	// 		if (!empty($request->filename)) {
	// 			$base64Files = is_array($request->filename) ? $request->filename : [$request->filename];

	// 			foreach ($base64Files as $base64File) {
	// 				$result = $this->processAndSaveFile($base64File, $path, null);
	// 				if (!$result['success']) {
	// 					throw new \Exception($result['message']);
	// 				}
	// 				$fileNames[] = $result['filename'];
	// 			}
	// 		}

	// 		// Process lampiran_titik files
	// 		if (!empty($request->lampiran_titik)) {
	// 			$base64Files = is_array($request->lampiran_titik) ? $request->lampiran_titik : [$request->lampiran_titik];

	// 			foreach ($base64Files as $base64File) {
	// 				$result = $this->processAndSaveFile($base64File, $path, 'lampiran_titik_');
	// 				if (!$result['success']) {
	// 					throw new \Exception($result['message']);
	// 				}
	// 				$lampiranTitikNames[] = $result['filename'];
	// 			}
	// 		}

	// 		$this->fillKonfirmasiData($konfirmasi, $request, $data, $fileNames, $lampiranTitikNames);
	// 		$konfirmasi->save();

	// 		DB::commit();
	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'Data berhasil disimpan',
	// 			'data' => $konfirmasi,
	// 		], 200);

	// 	} catch (\Throwable $th) {
	// 		DB::rollBack();
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => $th->getMessage(),
	// 			'line' => $th->getLine()
	// 		], 500);
	// 	}
	// }
	public function store(Request $request)
	{
		// dd($request->all());
		DB::beginTransaction();
		try {
			// Tentukan model berdasarkan type

			$model = $request->type === 'kontrak' ? QuotationKontrakH::class : QuotationNonKontrak::class;

			// Ambil data quotation
			$data = $model::where('id', $request->id)
				->where('no_document', $request->no_document)
				->first();

			if (!$data) {
				throw new \Exception('Data quotation tidak ditemukan.');
			}
			// Cari atau buat objek konfirmasi
			$konfirmasi = null;

			if ($request->id_konfirmasi) {
				$query = KelengkapanKonfirmasiQs::where([
					['id_quotation', $request->id],
					['no_quotation', $request->no_document],
					['type', $request->type],
				]);

				if ($request->type === 'kontrak') {
					$query->where('periode', $request->periode_kontrak);
				}

				$konfirmasi = $query->first();
			}

			if (!$konfirmasi) {
				$konfirmasi = new KelengkapanKonfirmasiQs();
			}

			$fileNames = [];
			$lampiranTitikNames = [];
			$path = 'konfirmasi_order/';
			$savePath = public_path($path);

			if (!file_exists($savePath)) {
				if (!mkdir($savePath, 0755, true)) {
					throw new \Exception('Tidak dapat membuat direktori penyimpanan file');
				}
			}

			if (!is_writable($savePath)) {
				throw new \Exception('Direktori penyimpanan tidak dapat ditulis');
			}

			if (!empty($request->filename)) {

				$base64Files = is_array($request->filename) ? $request->filename : [$request->filename];
				foreach ($base64Files as $base64File) {
					$result = $this->processAndSaveFile($base64File, $path, null);
					if (!$result['success']) {
						throw new \Exception($result['message']);
					}
					$fileNames[] = $result['filename'];
				}
			}

			if (!empty($request->lampiran_titik)) {

				$base64Files = is_array($request->lampiran_titik) ? $request->lampiran_titik : [$request->lampiran_titik];
				foreach ($base64Files as $base64File) {
					$result = $this->processAndSaveFile($base64File, $path, 'lampiran_titik_');
					if (!$result['success']) {
						throw new \Exception($result['message']);
					}
					$lampiranTitikNames[] = $result['filename'];
				}
			}

			$this->fillKonfirmasiData($konfirmasi, $request, $data, $fileNames, $lampiranTitikNames);
				// dd($konfirmasi);
		

			DB::commit();
			return response()->json([
				'success' => true,
				'message' => 'Data berhasil disimpan',
				'data' => $konfirmasi,
			], 200);

		} catch (\Throwable $th) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => $th->getMessage(),
				'line' => $th->getLine(),
				'file' => $th->getFile()
			], 500);
		}
	}

	/**
	 * Process and save base64 file
	 */
	protected function processAndSaveFile($base64File, $path, $prefix = null)
	{
		try {
			// Extract file information from base64 string

			$fileData = $this->extractBase64FileData($base64File);


			if (!$fileData) {
				return response()->json([
					'success' => false,
					'message' => 'Format base64 tidak valid.'
				], 401);
			}

			$fileExtension = $fileData['extension'];
			$fileContent = $fileData['content'];

			$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
			if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
				return response()->json([
					'success' => false,
					'message' => 'Format file tidak didukung. Hanya PDF, JPG, JPEG, dan PNG yang diizinkan.'
				], 401);
			}

			// Generate filename
			$fileName = $this->generateFileName($prefix, $fileExtension);
			$fullPath = public_path($path . $fileName);

			// Decode base64 content
			$decodedContent = base64_decode($fileContent);

			if ($decodedContent === false) {
				return [
					'success' => false,
					'message' => 'Gagal decode base64 content'
				];
			}

			// Validate file content
			if (empty($decodedContent)) {
				return [
					'success' => false,
					'message' => 'File content kosong'
				];
			}

			// Save file
			$bytesWritten = file_put_contents($fullPath, $decodedContent);

			if ($bytesWritten === false) {
				return [
					'success' => false,
					'message' => 'Gagal menyimpan file'
				];
			}

			if (!file_exists($fullPath)) {
				return [
					'success' => false,
					'message' => 'File tidak tersimpan dengan benar'
				];
			}

			if (filesize($fullPath) === 0) {
				unlink($fullPath);
				return [
					'success' => false,
					'message' => 'File tersimpan dengan ukuran 0 bytes'
				];
			}

			return [
				'success' => true,
				'filename' => $fileName,
				'bytes_written' => $bytesWritten
			];

		} catch (\Exception $e) {
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Extract data from base64 file string
	 */
	protected function extractBase64FileData($base64String)
	{
		if (strpos($base64String, ';base64,') === false) {
			return false;
		}




		// Get file data
		list($fileInfo, $fileContent) = explode(';base64,', $base64String);

		// Get file type
		list(, $fileType) = explode(':', $fileInfo);

		// Get file extension
		$fileExtension = $this->getExtensionFromMimeType($fileType);



		if (!$fileExtension) {
			return response()->json([
				'success' => false,
				'message' => 'Format base64 tidak valid.'
			], 500);
		}

		return [
			'type' => $fileType,
			'extension' => $fileExtension,
			'content' => $fileContent
		];
	}

	/**
	 * Get file extension from mime type
	 */
	protected function getExtensionFromMimeType($mimeType)
	{
		$mimeExtensionMap = [
			'application/pdf' => 'pdf',
			'image/jpeg' => 'jpg',
			'image/jpg' => 'jpg',
			'image/png' => 'png',
		];

		return $mimeExtensionMap[$mimeType] ?? null;
	}

	public function generateFileName($prefix = null, $extension)
	{
		$dateMonth = str_pad(date('m'), 2, '0', STR_PAD_LEFT) . str_pad(date('d'), 2, '0', STR_PAD_LEFT);
		$prefixToUse = $prefix ?? "konfirmasi_";
		$fileName = $prefixToUse . $dateMonth . "_" . microtime(true) . "." . $extension;
		$filename = str_replace(' ', '_', $fileName);

		return $filename;
	}

	/**
	 * Convert base64 image to PDF
	 */
	public function base64ImageToPdf($base64Content, $imageExtension, $path, $fileName)
	{

		$outputPath = public_path($path . $fileName);
		file_put_contents($outputPath, base64_decode($base64Content));

		return true;
	}

	private function fillKonfirmasiData($konfirmasi, $request, $data, $fileNames, $lampiranTitikNames)
	{
		$konfirmasi->periode = $request->periode_kontrak ?? null;
		$konfirmasi->approval_order = $request->approval_order;
		$konfirmasi->no_purchaseorder = $request->no_purchaseorder ?? null;
		$konfirmasi->filename = json_encode($fileNames) ?? [];
		$konfirmasi->lampiran_titik = json_encode($lampiranTitikNames) ?? [];
		$konfirmasi->no_co_qsd = $request->no_co_qsd;
		$konfirmasi->keterangan_approval_order = $request->keterangan_approval_order;
		$konfirmasi->status_bap = $request->status_bap == true ? 1 : 0;
		$konfirmasi->nama_pic_bap = $request->nama_pic_bap ?? null;
		$konfirmasi->jabatan_pic_bap = $request->jabatan_pic_bap ?? null;
		$konfirmasi->penggabungan_lhp = $request->penggabungan_lhp == true ? 1 : 0;
		$konfirmasi->keterangan_penggabungan_lhp = $request->keterangan_penggabungan_lhp ?? null;
		$konfirmasi->penamaan_titik_sampling = json_encode($request->penamaan_titik_sampling);
		$konfirmasi->no_quotation = $data->no_document;
		$konfirmasi->id_quotation = $data->id;
		$konfirmasi->type = $request->type;
		$konfirmasi->save();
	}


	public function approve(Request $request)
	{
		DB::beginTransaction();
		try {
			$model = $request->type === 'kontrak' ? QuotationKontrakH::class : QuotationNonKontrak::class;
			$data = $model::findOrFail($request->id);

			$data->konfirmasi_order = true;
			$data->save();


			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Data berhasil disimpan',
			], 200);

		} catch (\Throwable $th) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'message' => $th->getMessage(),
			], 500);
		}
	}
	public function handleDownload(Request $request)
	{
		$fileName = $request->fileName;
		$path = public_path('konfirmasi_order/' . $fileName);

		if (!File::exists($path)) {
			return response()->json(['message' => 'File not found.'], 404);
		}
		$publicUrl = \URL::asset('konfirmasi_order/' . $fileName);


		return response()->json([
			'file_name' => $fileName,
			'file_path' => $publicUrl,
		]);
	}
}