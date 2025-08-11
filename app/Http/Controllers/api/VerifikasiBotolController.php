<?php

namespace App\Http\Controllers\api;

date_default_timezone_set('Asia/Jakarta');

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Models\{QcLapangan, OrderDetail, Ftc, ScanSampelTc, ScanBotol, PersiapanSampelDetail, DataLapanganAir};
use DB;

class VerifikasiBotolController extends Controller
{
    // V3 Verifikasi Sampel
    public function index(Request $request)
    {
        $date = Carbon::parse($request->date);

        $data = QcLapangan::where('is_active', $request->is_active)
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('status_jenis', function ($query, $keyword) {
                if (strtolower($keyword) === 'jenis sample sesuai') {
                    $query->where('status_jenis', 1);
                } elseif ($keyword === '-') {
                    $query->where('status_jenis', 0);
                }
            })
            ->make(true);
    }
    
    // Mobile Scan Sampel TC
    public function index_scan(Request $request)
    {
        $date = Carbon::parse($request->date);

        $data = ScanSampelTc::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->orderBy('id', 'desc');
        return Datatables::of($data)
            ->filterColumn('status_jenis', function ($query, $keyword) {
                if (strtolower($keyword) === 'jenis sample sesuai') {
                    $query->where('status_jenis', 1);
                } elseif ($keyword === '-') {
                    $query->where('status_jenis', 0);
                }
            })
            ->make(true);
    }

    public function getSamplesByUser(Request $request)
    {
        $query = ScanSampelTc::where('created_by', $this->karyawan)
            ->orderBy('id', 'desc')
            ->limit(1000)
            ->get();

        return response()->json($query, 200);
    }

    public function dashboard(Request $request)
    {
        $today = Carbon::today();
        $month = Carbon::now()->month;

        $data = ScanSampelTc::selectRaw("
                SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as total_hari_ini,
                SUM(CASE WHEN MONTH(created_at) = ? THEN 1 ELSE 0 END) as total_bulan_ini
            ", [$today, $month])
            ->where('created_by', $this->karyawan)
            ->first();

        return response()->json([
            'today' => $data->total_hari_ini ?? 0,
            'thisMonth' => $data->total_bulan_ini ?? 0
        ], 200);
    }


    
        public function scan(Request $request)
    {
        try {
            $datachek = explode('/', $request->no_sampel);
            $data = null;
            $scan = null;

            $persiapan = null;
            $dataDisplay = null;
            $parameters = null;
            $categoris = null;
            if (isset($datachek[1])) {

                $persiapan = PersiapanSampelDetail::where('no_sampel', $request->no_sampel)->first();
                $data = OrderDetail::where('no_sampel', $request->no_sampel)->first();
                $scan = ScanBotol::where('no_sampel', $request->no_sampel)->first();
                $dataDisplay = json_decode($data->persiapan);
                $parameters = json_decode($persiapan->parameters);
                foreach ($dataDisplay as $item) {
                    if ($data->kategori_2 == '4-Udara' || $data->kategori_2 == '5-Emisi') {
                        $type = $item->parameter;
                    } else {
                        $type = $item->type_botol;
                    }


                    if (isset($parameters->air->$type)) {
                        $item->disiapkan = $parameters->air->$type->disiapkan;
                        if ($item->koding == $request->no_sampel) {
                            $item->jumlah = 1;
                        }
                    } else if (isset($parameters->udara->$type)) {
                        $item->disiapkan = $parameters->udara->$type->disiapkan;
                        if ($item->koding == $request->no_sampel) {
                            $item->jumlah = 1;
                        }
                    } else {
                        $item->disiapkan = null;
                    }
                }
            } else {

                $data = OrderDetail::whereNotNull('persiapan')
                    ->whereJsonContains('persiapan', ['koding' => $request->no_sampel])
                    ->first();

                if (!$data) {
                    return response()->json(["message" => "Data Lapangan tidak ditemukan", "code" => 404], 404);
                }
                // dd($data);
                $lapangan = DataLapanganAir::where('no_sampel', $data->no_sampel)->first();
                $scan = ScanSampelTc::where('no_sampel', $data->no_sampel)->first();
                $persiapan = PersiapanSampelDetail::where('no_sampel', $data->no_sampel)->where('is_active',1)->first();
                $dataDisplay = json_decode($data->persiapan);
                $parameters = json_decode($persiapan->parameters) ?? null;

                foreach ($dataDisplay as $item) {
                    if ($data->kategori_2 == '4-Udara' || $data->kategori_2 == '5-Emisi') {
                        $type = $item->parameter;
                    } else {
                        $type = $item->type_botol;
                    }


                    if (isset($parameters->air->$type)) {
                        $item->disiapkan = $parameters->air->$type->disiapkan;
                        if ($item->koding == $request->no_sampel) {
                            $item->scanned = 1;
                        }
                    } else if (isset($parameters->udara->$type)) {
                        $item->disiapkan = $parameters->udara->$type->disiapkan;
                        if ($item->koding == $request->no_sampel) {
                            $item->scanned = 1;
                        }
                    } else if (isset($parameters->emisi->$type)) {
                        $item->disiapkan = $parameters->emisi->$type->disiapkan;
                        if ($item->koding == $request->no_sampel) {
                            $item->scanned = 1;
                        }
                    } else {
                        $item->disiapkan = null;
                    }
                }
            }
            // dd($lapangan, $data, $dataDisplay);

            if ($scan) {
                $scanData = json_decode($scan->data_detail, true);
                $scan->data = array_values($scanData);
                $scan->filename = json_decode($scan->filename);
            }


            if ($data->kategori_2 == '4-Udara') {
                $categoris = "udara";
            } else if ($data->kategori_2 == '1-Air') {
                $categoris = "air";
            } else {
                $categoris = "emisi";
            }
            return response()->json([
                'message' => 'Data berhasil didapatkan',
                'data' => $dataDisplay,
                'data_order' => $data,
                'data_lapangan' => $lapangan,
                'data_scan' => $scan,
                'no_sampel' => $request->no_sampel,
                'kategori' => $categoris
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                "message" => $th->getMessage(),
                "line" => $th->getLine(),
                "file" => $th->getFile(),
                "code" => 404
            ], 404);
        }
    }

        public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->kategori == "1-Air") {
                $kondisi_sampel = '';
                $dokumentasi_lainnya = '';
                $path = 'scan_sampel_tc/';
                $savePath = public_path($path);
                if ($request->tipe == 'botol') {
                    $koding_map = array_map(function ($item) {
                        return $item['koding'];
                    }, $request->data_detail);

                      $no_sampel = OrderDetail::whereNotNull('persiapan')
                    ->whereJsonContains('persiapan', ['koding' => $koding_map[0] ?? $koding_map[1] ?? $koding_map[2]])
                    ->first()->no_sampel;

                if (!$no_sampel) {
                    return response()->json(["message" => "No Sampel tidak ditemukan", "code" => 404], 404);
                }
                }
                // dd($request->data_detail);

                if (!empty($request->kondisi_sampel)) {
                    $base64Files = is_array($request->kondisi_sampel) ? $request->kondisi_sampel : [$request->kondisi_sampel];
                    foreach ($base64Files as $base64File) {
                        $result = $this->processAndSaveFile($base64File, $path, "KONDISI", $request->tipe == 'botol' ? $no_sampel : $request->no_sampel);
                        if (!$result['success']) {
                            throw new \Exception($result['message']);
                        }
                        $kondisi_sampel = $result['filename'];
                    }
                }
                if (!empty($request->dokumentasi_lainnya)) {
                    $base64Files = is_array($request->dokumentasi_lainnya) ? $request->dokumentasi_lainnya : [$request->dokumentasi_lainnya];
                    foreach ($base64Files as $base64File) {
                        $result = $this->processAndSaveFile($base64File, $path, "DOKLAIN", $request->tipe == 'botol' ? $no_sampel : $request->no_sampel);
                        if (!$result['success']) {
                            throw new \Exception($result['message']);
                        }
                        $dokumentasi_lainnya = $result['filename'];
                    }
                }
                $isAllReady = true;
                foreach ($request->data_detail as $detail) {
                    if ($detail['disiapkan'] > $detail['jumlah']) {
                        $isAllReady = false;
                        break;
                    }
                }

                if ($isAllReady) {
                    $ftc = Ftc::where('no_sample', $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel)->first();
                    $ftc->ftc_verifier = Carbon::now()->format('Y-m-d H:i:s');
                    $ftc->user_verifier = $this->user_id;
                    $ftc->save();
                }

                $scanSampelTc = ScanSampelTc::where('no_sampel', $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel)->first();
                if ($scanSampelTc) {

                    $scanSampelTc->no_sampel = $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel;
                    $scanSampelTc->kategori = $request->kategori;
                    $scanSampelTc->data_detail = json_encode($request->data_detail);
                    $scanSampelTc->status = $isAllReady ? 'lengkap' : 'belum_lengkap';
                    $scanSampelTc->keterangan = $request->keterangan ?? null;
                    $scanSampelTc->kondisi_sampel = $kondisi_sampel ?? null;
                    $scanSampelTc->dokumentasi_lainya = $dokumentasi_lainya ?? null;
                    $scanSampelTc->filename = json_encode([$kondisi_sampel, $dokumentasi_lainnya]);
                    $scanSampelTc->updated_at = Carbon::now();
                    $scanSampelTc->updated_by = $this->karyawan;
                    $scanSampelTc->save();
                } else {
                    $scanSampelTc = new ScanSampelTc();
                    $scanSampelTc->no_sampel = $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel;
                    $scanSampelTc->kategori = $request->kategori;
                    $scanSampelTc->data_detail = json_encode($request->data_detail);
                    $scanSampelTc->status = $isAllReady ? 'lengkap' : 'belum_lengkap';
                    $scanSampelTc->keterangan = $request->keterangan ?? null;
                    $scanSampelTc->kondisi_sampel = $kondisi_sampel ?? null;
                    $scanSampelTc->dokumentasi_lainya = $dokumentasi_lainya ?? null;
                    $scanSampelTc->filename = json_encode([$kondisi_sampel, $dokumentasi_lainnya]);
                    $scanSampelTc->created_at = Carbon::now();
                    $scanSampelTc->created_by = $this->karyawan;
                    $scanSampelTc->save();
                }
                DB::commit();
            } else {
                $kondisi_sampel = '';
                $dokumentasi_lainnya = '';
                $lampiranTitikNames = [];
                $path = 'scan_sampel_tc/';
                $savePath = public_path($path);
                if ($request->tipe == 'botol') {

                    $no_sampel = OrderDetail::whereNotNull('persiapan')
                        ->whereJsonContains('persiapan', ['koding' =>  $request->no_koding[0]])
                        ->first()->no_sampel;
                }

                if (!empty($request->kondisi_sampel)) {
                    $base64Files = is_array($request->kondisi_sampel) ? $request->kondisi_sampel : [$request->kondisi_sampel];
                    foreach ($base64Files as $base64File) {
                        $result = $this->processAndSaveFile($base64File, $path, "KONDISI", $request->tipe == 'botol' ? $no_sampel : $request->no_sampel);
                        if (!$result['success']) {
                            throw new \Exception($result['message']);
                        }
                        $kondisi_sampel = $result['filename'];
                    }
                }
                if (!empty($request->dokumentasi_lainnya)) {
                    $base64Files = is_array($request->dokumentasi_lainnya) ? $request->dokumentasi_lainnya : [$request->dokumentasi_lainnya];
                    foreach ($base64Files as $base64File) {
                        $result = $this->processAndSaveFile($base64File, $path, "DOKLAIN", $request->tipe == 'botol' ? $no_sampel : $request->no_sampel);
                        if (!$result['success']) {
                            throw new \Exception($result['message']);
                        }
                        $dokumentasi_lainnya = $result['filename'];
                    }
                }

                $isAllReady = true;
                foreach ($request->data_detail as $detail) {
                    if ($detail['disiapkan'] > $detail['jumlah']) {
                        $isAllReady = false;
                        break;
                    }
                }
                if ($isAllReady) {
                    $ftc = Ftc::where('no_sample', $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel)->first();
                    if (!$ftc) {
                        $ftc = new Ftc();
                        $ftc->no_sample = $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel;
                        $ftc->ftc_verifier = Carbon::now()->format('Y-m-d H:i:s');
                        $ftc->user_verifier = $this->user_id;
                    } else {
                        $ftc->ftc_verifier = Carbon::now()->format('Y-m-d H:i:s');
                        $ftc->user_verifier = $this->user_id;
                    }

                    $ftc->save();
                }

                $scanSampelTc = ScanSampelTc::where('no_sampel', $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel)->first();
                if ($scanSampelTc) {
                    $scanSampelTc->no_sampel = $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel;
                    $scanSampelTc->kategori = $request->kategori;
                    $scanSampelTc->data_detail = json_encode($request->data_detail);
                    $scanSampelTc->status = $isAllReady ? 'lengkap' : 'belum_lengkap';
                    $scanSampelTc->keterangan = $request->keterangan ?? null;
                    $scanSampelTc->kondisi_sampel = $kondisi_sampel ?? null;
                    $scanSampelTc->dokumentasi_lainya = $dokumentasi_lainya ?? null;
                    $scanSampelTc->filename = json_encode([$kondisi_sampel, $dokumentasi_lainnya]);
                    $scanSampelTc->updated_at = Carbon::now();
                    $scanSampelTc->updated_by = $this->karyawan;
                    $scanSampelTc->save();
                } else {
                    $scanSampelTc = new ScanSampelTc();
                    $scanSampelTc->no_sampel = $request->tipe == 'sampel' ? $request->no_sampel : $no_sampel;
                    $scanSampelTc->kategori = $request->kategori;
                    $scanSampelTc->data_detail = json_encode($request->data_detail);
                    $scanSampelTc->status = $isAllReady ? 'lengkap' : 'belum_lengkap';
                    $scanSampelTc->keterangan = $request->keterangan ?? null;
                    $scanSampelTc->kondisi_sampel = $kondisi_sampel ?? null;
                    $scanSampelTc->dokumentasi_lainya = $dokumentasi_lainya ?? null;
                    $scanSampelTc->filename = json_encode([$kondisi_sampel, $dokumentasi_lainnya]);
                    $scanSampelTc->created_at = Carbon::now();
                    $scanSampelTc->created_by = $this->karyawan;
                    $scanSampelTc->save();
                }
                DB::commit();
            }

            return response()->json(["message" => "Berhasil disimpan", "code" => 200], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(["message" => $th->getMessage(), 'getLine' => $th->getLine(), 'getFile' => $th->getFile(), "code" => 500], 500);
        }
    }

     protected function processAndSaveFile($base64File, $path, $prefix = null, $no_sampel)
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

            $fileType = $fileData['type'];
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
            $no_sampel = str_replace('/', '_', $no_sampel);
            $fileName = $prefix . '-' . $no_sampel . '_' . Carbon::now()->format('Ymdhis') . '_' . time() . '.' . $fileExtension;
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
            return false;
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



   

    public function convertFile($foto, $name)
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = $name . '_' . date("YmdHis") . '.jpeg';
        // $destinationPath = public_path() . '/sampel/dokumentasi/';
        $destinationPath = public_path() . '/qc_lapangan/';
        if (!file_exists($destinationPath)) mkdir($destinationPath, 0777, true);

        file_put_contents($destinationPath . $safeName, $file);

        return $safeName;
    }
}