<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use App\Models\MasterKendaraan;
use App\Models\MasterQr;
use App\Models\DataLapanganEmisiOrder;
use App\Models\DataLapanganEmisiKendaraan;
use App\Models\MasterBakumutu;
use App\Models\MasterRegulasi;
use Log;



class ValidatorHandler extends BaseController
{
	public function handleEmisi(Request $request)
	{
		if (isset($request->qr) && $request->qr != null) {
			try {
				$data = MasterQr::where('kode', $request->qr)->where('is_active', true)->first();
				if ($data != null) {
					if ($data->id_kendaraan != null && $data->status == 1) {
						$order = DataLapanganEmisiOrder::where('id_qr', $data->id)->where('is_active', true)->get();
						$kendaraan = MasterKendaraan::where('id', $data->id_kendaraan)->where('is_active', true)->first();
						$jumlah = count($order);
						foreach ($order as $key => $value) {
							$cek_fdl = DataLapanganEmisiKendaraan::where('no_sampel', $value->no_sampel)->where('is_active', true)->first();
							$cek_bakumutu = MasterBakumutu::where('id_regulasi', $value->id_regulasi)->where('is_active', true)->get();
							$regulasi = MasterRegulasi::where('id', $value->id_regulasi)->where('is_active', true)->first();
							$status = 'Parameter Tidak di uji';
							$status_co = 'Parameter Tidak di uji';
							$status_hc = 'Parameter Tidak di uji';
							foreach ($cek_bakumutu as $keys => $val) {
								if ($val->parameter == 'CO' || $val->parameter == 'CO (Bensin)') {
									if ($cek_fdl->co <= $val->baku_mutu) {
										$status_co = 'Memenuhi Baku Mutu';
									} else {
										$status_co = 'Tidak Memenuhi Baku Mutu';
									}
								} else if ($val->parameter == 'HC' || $val->parameter == 'HC (Bensin)') {
									if ($cek_fdl->hc <= $val->baku_mutu) {
										$status_hc = 'Memenuhi Baku Mutu';
									} else {
										$status_hc = 'Tidak Memenuhi Baku Mutu';
									}
								} else if ($val->parameter == 'Opasitas' || $val->parameter == 'Opasitas (Solar)') {
									if ($cek_fdl->opasitas <= $val->baku_mutu) {
										$status = 'Memenuhi Baku Mutu';
									} else {
										$status = 'Tidak Memenuhi Baku Mutu';
									}
								}
							}
							$datas[$key]['tgl_uji'] = DATE('Y-m-d', strtotime($cek_fdl->created_at));
							$datas[$key]['parameters'][0]['parameter'] = "CO";
							$datas[$key]['parameters'][0]['hasil'] = $cek_fdl->co . ' %';
							$datas[$key]['parameters'][0]['status'] = $status_co;
							$datas[$key]['parameters'][1]['parameter'] = "HC";
							$datas[$key]['parameters'][1]['hasil'] = $cek_fdl->hc . ' ppm';
							$datas[$key]['parameters'][1]['status'] = $status_hc;
							$datas[$key]['parameters'][2]['parameter'] = "Opasitas";
							$datas[$key]['parameters'][2]['hasil'] = $cek_fdl->opasitas . ' %';
							$datas[$key]['parameters'][2]['status'] = $status;
						}

						$data_detail['tipe_analisa'] = "Emisi Kendaraan";
						$data_detail['qr'] = $request->qr;
						$data_detail['merk_kendaraan'] = $kendaraan->merk_kendaraan;
						$data_detail['transmisi'] = $kendaraan->transmisi;
						$data_detail['tahun_pembuatan'] = $kendaraan->tahun_pembuatan;
						$data_detail['no_polisi'] = $kendaraan->plat_nomor;
						$data_detail['no_mesin'] = $kendaraan->no_mesin;
						$data_detail['bahan_bakar'] = $kendaraan->jenis_bbm;
						$data_detail['kapasitas_cc'] = $kendaraan->cc . ' CC';
						$data_detail['regulasi'] = $regulasi->peraturan;
						$data_detail['tgl_uji'] = DATE('Y-m-d', strtotime($cek_fdl->created_at));

						return response()->json([
							'detail' => $data_detail,
							'record' => $jumlah,
							'hasil' => $datas,
							'message' => 'Data has ben Show'
						], 201);
					} else {
						// $this->resultx = 'Qr Available';
						return response()->json([
							'detail' => [],
							'record' => 0,
							'hasil' => [],
							'message' => 'Qr Available'
						], 201);
					}
				} else {
					// $this->resultx = 'Qr Code tidak diterbitkan oleh INTILAB';
					return response()->json([
						'message' => 'Qr Code tidak diterbitkan oleh INTILAB'
					], 401);
				}
			} catch (\Throwable $th) {
				dd($th);
				return response()->json([
					'message' => 'Pastikan Qr Code Terbaca / Terisi'
				], 401);
			}
		} else {
			return response()->json([
				'message' => 'Pastikan Qr Code Terbaca / Terisi'
			], 401);
		}
	}


	public function handleDocument(Request $request)
	{
		if ($request->qr != null) {
			$trim = substr($request->qr, 5);
			$cek = DB::table('qr_documents')->where('kode_qr', $request->qr)->first();
			// dd($request->qr);
			if ($cek) {
				if ($cek->type_document != 'signature') {
					$data = json_decode($cek->data);
					// dd($data);
					if (isset($data->Nomor_LHP) || in_array($cek->type_document, ['berita_acara_sampling', 'surat_tugas_pengambilan_sampel', 'coding_sample', 'persiapan_sampel', 'permintaan_dokumentasi_sampling', 'invoice','e_certificate_webinar'])) {
						$array = (array) $data;
					} else {
						$array = [
							'Document_Number' => $data->no_document,
							'Document_Type' => strtoupper($data->type_document),
							'Customer_Name' => $data->nama_customer,
						];
					}
					return response()->json([
						'message' => 'Document Valid.',
						'data' => $array
					], 200);
				} else {
					$data = json_decode($cek->data);
					$array = [
						'Nama_Lengkap' => $data->nama_lengkap,
						'Nik_Karyawan' => $data->nik,
						'Jabatan' => strtoupper($data->posision),
						'Department' => strtoupper($data->department),
						'Penetapan' => strtoupper($data->cabang),
					];
					return response()->json([
						'message' => 'Signature Valid.',
						'data' => $array
					], 200);
				}
			} else {
				return response()->json([
					'data' => [],
					'message' => 'QR Code Invalid.',
				], 200);
			}
		} else {
			return response()->json([
				'message' => 'QR Code Invalid.',
			], 200);
		}
	}
}