<?php

// app/Services/MpdfService.php
namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;

class MpdfService extends Mpdf
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $css = __DIR__ . '/../../resources/mpdf/mpdf.css';
        if (file_exists($css)) {
            $this->WriteHTML(
                file_get_contents($css),
                HTMLParserMode::HEADER_CSS
            );
        }
    }
}
