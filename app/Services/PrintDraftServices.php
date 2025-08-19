<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Printing;
use App\Models\{
    LhpsAirHeader,
    QrDocument,
    OrderDetail,
    HistoryAppReject,
    Printers,
    Parameter
};

class PrintDraftServices
{
    public static function run()
    {
        try {
            $now = Carbon::now();
            $fiveDaysAgo = $now->copy()->subDays(5)->toDateString();
            $threeDaysAgo = $now->copy()->subDays(3)->toDateString();

            $dataList = LhpsAirHeader::where('is_active', true)
                ->where('is_approve', false)
                ->where('is_generated', true)
                ->where('is_revisi', false)
                ->get();

            $filteredData = $dataList->filter(function ($item) use ($fiveDaysAgo, $threeDaysAgo) {
                $generatedDate = Carbon::parse($item->generated_at)->toDateString();
                return (is_null($item->count_revisi) && $generatedDate <= $fiveDaysAgo) ||
                    ($item->count_revisi === 1 && $generatedDate <= $threeDaysAgo) ||
                    ($item->count_revisi === 2);
            });

            $processedCount = 0;
            $errorCount = 0;

            foreach ($filteredData as $dataH) {
                DB::beginTransaction();
                try {
                    Log::channel('print_lhp')->info("[WorkerPrintDraft] Running for sample number " . $dataH->no_sampel);

                    $qr = QrDocument::where('id_document', $dataH->id)
                        ->where('type_document', 'LHP_AIR')
                        ->where('is_active', 1)
                        ->where('file', $dataH->file_qr)
                        ->orderByDesc('id')
                        ->first();

                    $data_order = OrderDetail::where('no_sampel', $dataH->no_sampel)
                        ->where('is_active', true)
                        ->first();

                    if (!$data_order) {
                        DB::rollBack();
                        Log::channel('print_lhp')->info("[WorkerPrintDraft] Order detail not found for sample number " . [$dataH->no_sampel]);
                        $errorCount++;
                        continue;
                    }

                    // Update order detail
                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = $now;
                    $data_order->approved_by = 'Abidah Walfathiyyah';
                    $data_order->save();

                    // Update header
                    $dataH->is_approve = 1;
                    $dataH->approved_at = $now;
                    $dataH->approved_by = 'Abidah Walfathiyyah';
                    $dataH->nama_karyawan = 'Abidah Walfathiyyah';
                    $dataH->jabatan_karyawan = 'Technical Control Supervisor';
                    $dataH->save();

                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Air',
                        'status' => 'approve',
                        'approved_at' => $now,
                        'approved_by' => 'Abidah Walfathiyyah'
                    ]);

                    // Update QR Document
                    if ($qr) {
                        $dataQr = json_decode($qr->data);
                        $dataQr->Tanggal_Pengesahan = $now->locale('id')->isoFormat('YYYY MMMM DD');
                        $dataQr->Disahkan_Oleh = 'Abidah Walfathiyyah';
                        $dataQr->Jabatan = 'Technical Control Supervisor';
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }

                    // Printing
                    $kan = self::cekAkreditasi($dataH->parameter_uji, $dataH->no_sampel);
                    $cek_printer = Printers::where('id', 47)->first();

                    $basePath = env('APP_URL') . '/public/dokumen/';
                    $filePath = ($kan ? 'LHP_DOWNLOAD/' : 'LHP/') . $dataH->file_lhp;

                    $print = Printing::where('pdf', $basePath . $filePath)
                        ->where('printer', $cek_printer->full_path)
                        ->where('karyawan', 'System')
                        ->where('filename', $filePath)
                        ->where('printer_name', '\\itcom2\EPSON L360 Series')
                        ->where('destination', '\\itcom2\EPSON L360 Series')
                        ->print();

                    if (!$print) {
                        DB::rollBack();
                        Log::channel('print_lhp')->info('[WorkerPrintDraft] Failed to print ' . $dataH->no_sampel);
                        $errorCount++;
                        continue;
                    }

                    $dataH->is_printed = 1;
                    $dataH->count_print = $dataH->count_print + 1;
                    $dataH->save();

                    $processedCount++;
                    Log::channel('print_lhp')->info('[WorkerPrintDraft] Successfully approved and printed ' . $dataH->no_sampel);

                    // DB::commit();
                } catch (Throwable $th) {
                    DB::rollBack();
                    Log::channel('print_lhp')->info('[WorkerPrintDraft] Failed to print ' . $dataH->no_sampel . ' caused by ' . $th->getMessage());
                    $errorCount++;
                }
            }

            Log::channel('print_lhp')->info('[WorkerPrintDraft] Process completed from ' . count($filteredData) . ' total with ' . $processedCount . ' processed and ' . $errorCount . ' errors');

            return response()->json([
                'message' => 'Process completed',
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => count($filteredData)
            ], 200);

        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('System error in WorkerPrintDraft', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $th->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    private static function cekAkreditasi($data, $no_sampel)
    {
        $dataDecode = json_decode($data);
        $total = count($dataDecode);

        $orderDetail = OrderDetail::where('no_sampel', $no_sampel)->first();
        $kategori = explode('-', $orderDetail->kategori_2)[0];

        $parameterAkreditasi = 0;

        foreach ($dataDecode as $value) {
            $parameter = Parameter::where('nama_lab', $value)
                ->where('id_kategori', $kategori)
                ->first();

            if ($parameter && $parameter->status == 'AKREDITASI') {
                $parameterAkreditasi++;
            }
        }

        return $parameterAkreditasi > 0 && ($parameterAkreditasi / $total) >= 0.6;
    }
}
