<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\QrDocument;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateSkhpSarOnthespotService
{
    public function generate($header, $generatedBy = 'System')
    {
        $header->no_document = 'ISL-SAR-OS/SKHP/' . $header->no_order;
        $header->tanggal_selesai = Carbon::now()->format('Y-m-d H:i:s');

        $this->generateQr($header, $generatedBy);

        $render = new RenderDokumenSkhpOnthespot();
        $filename = $render->execute(
            $header,
            $header->hasilUji,
            public_path('qr_documents/' . str_replace('/', '_', $header->no_document) . '.svg')
        );

        $header->file_skhp = $filename;
        $header->save();

        $emailSent = $this->sendEmail($header, $filename, $generatedBy);

        return [
            'filename' => $filename,
            'email_sent' => $emailSent,
        ];
    }

    private function sendEmail($header, $filename, $generatedBy)
    {
        if (empty($header->email)) {
            Log::warning("SKHP email skipped: email pelanggan kosong untuk order {$header->no_order}");
            return false;
        }

        try {
            $subject = "Surat Keterangan Hasil Pengujian no order : {$header->no_order}";
            $body = view('TemplateEmail.skhpSarOnthespot', ['data' => $header])->render();

            SendEmail::where('to', $header->email)
                ->where('bcc', ['reiko@intilab.com', 'winda@intilab.com'])
                ->where('subject', $subject)
                ->where('body', $body)
                ->where('attachment', [[
                    'path' => 'dokumen/SkhpOnthespot/' . $filename,
                    'name' => "Surat Keterangan Hasil Pengujian - {$header->no_order}.pdf",
                ]])
                ->where('karyawan', $generatedBy)
                ->fromLhp()
                ->send();

            return true;
        } catch (\Exception $e) {
            Log::error("SKHP email gagal untuk order {$header->no_order}: " . $e->getMessage());
            return false;
        }
    }

    private function generateQr($header, $generatedBy)
    {
        $filename = str_replace('/', '_', $header->no_document);
        $path = public_path('qr_documents/' . $filename . '.svg');

        if (file_exists($path)) {
            return $path;
        }

        $dir = public_path('qr_documents/');
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $link = 'https://www.intilab.com/validation/';
        $unique = 'isldc' . (int) floor(microtime(true) * 1000);

        QrCode::size(200)->generate($link . $unique, $path);

        QrDocument::insert([
            'id_document' => $header->id,
            'type_document' => 'skhp_sar',
            'kode_qr' => $unique,
            'file' => $filename,
            'data' => json_encode([
                'no_document' => $header->no_document,
                'no_order' => $header->no_order,
                'nama_pelanggan' => $header->nama_pelanggan,
                'type_document' => 'Surat Keterangan Hasil Pengujian',
                'Tanggal_Pengesahan' => Carbon::now()->locale('id')->isoFormat('DD MMMM YYYY'),
                'Disahkan_Oleh' => $generatedBy,
            ]),
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'created_by' => $generatedBy,
        ]);

        return $path;
    }
}
