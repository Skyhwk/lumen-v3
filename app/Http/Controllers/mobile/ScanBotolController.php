<?php

namespace App\Http\Controllers\mobile;

use App\Models\OrderDetail;
use App\Models\PersiapanSampelDetail;
use App\Models\ScanBotol;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class ScanBotolController extends Controller
{

    public function index(Request $request)
    {
        $data = ScanBotol::where('created_by', $this->karyawan)
            ->get();
        foreach ($data as $key => $value) {
            $value->data = json_decode($value->data);
            $value->filename = json_decode($value->filename);
        }
        return response()->json(['data' => $data], 200);
    }

    public function getScanData(Request $request)
    {
        try {
            $datachek = explode('/', $request->no_sampel);
            $data = null;
            $scan = null;
            $persiapan = null;
            $dataDisplay = null;
            $parameters = null;
            if (isset($datachek[1])) {
                $persiapan = PersiapanSampelDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $data = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $dataDisplay = json_decode($data->persiapan);
                $parameters = $persiapan ? json_decode($persiapan->parameters) : null;
                foreach ($dataDisplay as $item) {
                    if ($data->kategori_2 == '1-Air') {
                        $type = $item->type_botol;
                    } else {
                        $type = $item->parameter;
                    }

                    if($persiapan){
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
                        } else if (isset($parameters->padatan->$type)) {
                            $item->disiapkan = $parameters->padatan->$type->disiapkan;
                            if ($item->koding == $request->no_sampel) {
                                $item->jumlah = 1;
                            }
                        } else {
                            $item->disiapkan = null;
                        }
                    }
                }
            } else {
                // dd('www');
                $data = OrderDetail::whereNotNull('persiapan')
                    ->whereJsonContains('persiapan', ['koding' => $request->no_sampel])
                    ->where('is_active', true)
                    ->first();
                // dd($data);
                $persiapan = PersiapanSampelDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
                $dataDisplay = json_decode($data->persiapan);
                $parameters = $persiapan ? json_decode($persiapan->parameters) : null;

                $formattedData = [];

                foreach ($dataDisplay as $key => $item) {
                    if ($data->kategori_2 == '1-Air') {
                        $type = $item->type_botol;
                    } else {
                        $type = $item->parameter;
                    }

                    $paramExplane = ['SO2', 'NO2', 'Velocity', 'NOX'];

                    if (isset($parameters->emisi) || $data->kategori_2 == '5-Emisi') {
                        if (in_array($type, $paramExplane)) {
                            unset($dataDisplay[$key]);
                        }
                    }
                    if($persiapan){
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
                        } else if (isset($parameters->emisi->$type)) {
                            $item->disiapkan = $parameters->emisi->$type->disiapkan;
                            if ($item->koding == $request->no_sampel) {
                                $item->jumlah = 1;
                            }
                        } else if (isset($parameters->padatan->$type)) {
                            $item->disiapkan = $parameters->padatan->$type->disiapkan;
                            if ($item->koding == $request->no_sampel) {
                                $item->jumlah = 1;
                            }
                        } else {
                            $item->disiapkan = null;
                        }
                    }else{
                        if ($item->koding == $request->no_sampel) {
                            $item->jumlah = 1;
                        }
                    }
                }

                foreach ($dataDisplay as $item) {
                    if ($data->kategori_2 == '4-Udara' || $data->kategori_2 == '5-Emisi') {
                        $item->disiapkan = '1';
                    }
                }
            }

            $dataDisplay = array_values(
                array_reduce($dataDisplay, function ($acc, $item) {
                    if (!isset($acc[$item->koding])) {
                        $acc[$item->koding] = $item;
                    }
                    return $acc;
                }, [])
            );
            // dd($dataDisplay);
            return response()->json([
                'data' => $dataDisplay,
                'no_sampel' => $data->no_sampel,
                'not_air' => $data->kategori_2 != '1-Air'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }


    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // $persiapan = PersiapanSampelDetail::where('no_sampel', $request->no_sampel)->first();
            // $parameters = json_decode($persiapan->parameters);

            $fileNames = [];
            $path = 'scan_botol_/';
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

            $data = ScanBotol::where('no_sampel', $request->no_sampel)->first();

            if ($data) {
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
            } else {
                $data = new ScanBotol();
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->created_by = $this->karyawan;
            }

            $data->no_sampel = $request->no_sampel;
            $data->data = json_encode($request->data);
            $data->filename = json_encode($fileNames);

            // Konversi request->data ke array
            $inputData = is_array($request->data)
                ? $request->data
                : json_decode(json_encode($request->data), true);

            // Set status
            $data->status = 'selesai';

            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan', 'status' => '200'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }
    private function allDisiapkanMatch(object $parameters, array $inputData): bool
    {
        if (!isset($parameters->air) || !is_object($parameters->air)) {
            return false;
        }

        foreach ($parameters->air as $typeBotol => $info) {
            $expected = (string) ($info->disiapkan ?? '');

            if (!isset($inputData[$typeBotol]['disiapkan'])) {
                return false;
            }

            $actual = (string) $inputData[$typeBotol]['disiapkan'];

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }



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

    public function generateFileName($prefix = null, $extension)
    {
        $dateMonth = str_pad(date('m'), 2, '0', STR_PAD_LEFT) . str_pad(date('d'), 2, '0', STR_PAD_LEFT);
        $prefixToUse = $prefix ?? "scan_botol_";
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
}
