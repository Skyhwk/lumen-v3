<?php

namespace App\Services;

use App\Models\QrDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateQrDocumentPo
{
    public function insert($typeDoc, $data, $generatedBy)
    {
        $filename = 'PO_' . str_replace('/', '_', $data->po_number);
        $existing = QrDocument::where('file', $filename)->first();

        $qrData = [
            'Nomor_PO' => $data->po_number,
            'Nomor_Faktur' => $data->invoice_number,
            'Nama_Supplier' => $data->supplier_name,
            'Tanggal_Pengesahan' => Carbon::parse($data->approval_date)->locale('id')->isoFormat('DD MMMM YYYY'),
            'Disahkan_Oleh' => $data->approval_name,
            'Jabatan' => $data->approval_jabatan ?? '',
        ];

        if ($existing) {
            $existing->id_document = $data->id;
            $existing->data = json_encode($qrData);
            $existing->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $existing->created_by = $generatedBy;
            $existing->save();

            return $existing->file;
        }

        DB::beginTransaction();

        try {
            $dir = public_path() . '/qr_documents/';

            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $path = public_path() . '/qr_documents/' . $filename . '.svg';
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);

            QrDocument::insert([
                'id_document' => $data->id,
                'type_document' => $typeDoc,
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode($qrData),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $generatedBy,
            ]);

            DB::commit();

            return $filename;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
