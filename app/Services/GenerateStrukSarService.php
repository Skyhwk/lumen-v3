<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class GenerateStrukSarService
{
    public function generate($data)
    {
        $mpdf = new Mpdf([
            "format" => [80, 200],
            "margin_left" => 4,
            "margin_right" => 4,
            "margin_top" => 4,
            "margin_bottom" => 4,
            "default_font_size" => 9,
            "default_font" => "arial",
        ]);

        $html = view("struk.sar", ["data" => $data])->render();

        $mpdf->WriteHTML($html);

        $filename = public_path("struk/sar/STRUK_SAR_{$data->no_order}.pdf");

        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }

        $mpdf->Output($filename, Destination::FILE);

        return $filename;
    }
}
