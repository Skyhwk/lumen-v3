<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class GenerateStrukSarService
{
    public function generate($data)
    {
        $html = view("struk.sar", ["data" => $data])->render();

        $estimatedHeight = max(200, substr_count($html, '<tr') * 8);

        $mpdf = new Mpdf([
            "format" => [80, $estimatedHeight],
            "margin_left" => 4,
            "margin_right" => 4,
            "margin_top" => 4,
            "margin_bottom" => 4,
            "default_font_size" => 9,
            "default_font" => "arial",
        ]);

        $mpdf->WriteHTML($html);

        $filename = "STRUK_SAR_{$data->no_order}.pdf";

        $filepath = public_path("struk/sar/$filename");

        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $mpdf->Output($filepath, Destination::FILE);

        $data->file_struk = $filename;
        $data->save();

        return $filename;
    }
}
