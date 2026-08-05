<?php

namespace App\Services;

class PdfStoreService
{
    public static function store(
        $pdf,
        $filename,
        $destination,
        $visibility = 'public'
    ) {
        $pdfBinary = $pdf->Output(
            '',
            \Mpdf\Output\Destination::STRING_RETURN
        );

        return StoreFileService::store(
            $destination,
            $filename,
            $pdfBinary,
            $visibility
        );
    }
}