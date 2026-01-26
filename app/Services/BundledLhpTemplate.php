<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\Parameter;

class BundledLhpTemplate
{
    private $mpdf;

    private $header;
    private $directory = 'TemplateLHP';
    private $directoryDefault = 'TemplateLHP.DefaultTemplate';
    private $detail;
    private $view;
    private $mode;
    private $showKan = 'false';
    private $filename;
    private $custom;
    private $prefix = 'LHP';
    private $stylesheet;
    private static $instance;

    public static function set($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        switch ($field) {
            case 'dataHeader':
                self::$instance->header = $value;
                break;
            case 'dataDetail':
                self::$instance->detail = $value;
                break;
            case 'custom':
                self::$instance->custom = $value;
                break;
            case 'prefix':
                self::$instance->prefix = $value;
                break;
            case 'filename':
                self::$instance->filename = $value;
                break;
        }

        return self::$instance;
    }

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        switch ($field) {
            case 'view':
                self::$instance->view = $value;
                break;
        }

        return self::$instance;
    }

    public static function setMpdf($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->mpdf = $value;
        return self::$instance;
    }

    public static function setDataHeader($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->header = $value;
        return self::$instance;
    }

    public static function setDataCustom($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->custom = $value;
        return self::$instance;
    }

    public static function setPrefix($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->prefix = $value;
        return self::$instance;
    }

    public static function setDataDetail($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->detail = $value;
        return self::$instance;
    }

    public static function whereView($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->view = $value;
        return self::$instance;
    }

    public static function setFilename($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->filename = $value;
        return self::$instance;
    }

    public function render($value = null)
    {
        if (!$this->view) {
            throw new \Exception("View belum diset. Gunakan setView('namaView') sebelum render().");
        }

        if (!$this->header) {
            throw new \Exception("Header belum diset. Gunakan setHeader() sebelum render().");
        }

        if (!$this->detail) {
            throw new \Exception("Detail belum diset. Gunakan setDetail() sebelum render().");
        }

        self::$instance->mode = $value;
        $view = $this->directory . '.' . $this->view;

        self::$instance->showKan = $this->cekAkreditasi($this->header->parameter_uji, $this->header->no_sampel);

        $modes = [
            'downloadWSDraft',
            'downloadLHP',
            'downloadLHPFinal',
        ];

        if (!$value) {
            $resultName = '';
            foreach ($modes as $mode) {
                $resultName = $this->execute($this->header, $this->detail, $this->prefix, $view, $this->custom, $mode);
            }

            self::$instance = null;
            return $resultName;
        }

        $resultName = $this->execute($this->header, $this->detail, $this->prefix, $view, $this->custom, $value);

        self::$instance = null;
        return $resultName;
    }

    private function execute($header, $detail, $prefix, $view, $customs, $mode)
    {
        $detail = collect($detail);
        $header = (object) $header;
        // dd($header);
        // // dd(count(collect($detail)->toArray()));
        // $namaFile = '';
        // if ($this->filename) {
        //     $namaFile = $this->filename;
        // } else {
        //     $namaFile = str_replace("/", "-", $header->no_lhp);
        // }

        $header->sub_kategori = $this->ReplaceAlias($header->sub_kategori);

        $dir = $this->folderLocation($mode);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->generateStylesheet();
        $last = true;
        if (!empty($customs)) {
            $last = false;
        }
        $showKan = $this->showKan;
        // $filename = $prefix . '-' . $namaFile . '.pdf';
        // $filePath = $dir . '/' . $filename;

        $htmlBody = view($view . '.left', compact('header', 'detail',  'mode'))->render();
        $htmlHeader = view($this->directoryDefault . '.header', compact('header', 'detail',  'mode', 'view', 'showKan'))->render();
        $htmlFooter = view($this->directoryDefault . '.footer', ['header' => $header, 'detail' => $detail, 'mode' => $mode, 'last' => false])->render();
        $htmlLastFooter = view($this->directoryDefault . '.footer', compact('header', 'detail',  'mode', 'last'))->render();

        if (!empty($customs)) {
            foreach ($customs as $page => $custom) {
                $last = ($page === array_key_last($customs)) ? true : false;
                $htmlCustomBody[$page] = view($view . '.customLeft', compact('header', 'custom', 'page'))->render();
                $htmlCustomHeader[$page] = view($this->directoryDefault . '.customHeader', compact('header', 'detail', 'mode', 'view', 'showKan', 'page'))->render();
                $htmlCustomFooter[$page] = view($this->directoryDefault . '.footer', ['header' => $header, 'detail' => $detail, 'custom' => $custom, 'mode' => $mode, 'last' => false])->render();
                $htmlCustomLastFooter[$page] = view($this->directoryDefault . '.footer', compact('header', 'detail', 'custom', 'mode', 'last'))->render();
            }
        }

        // $mpdf = new \App\Services\MpdfService as Mpdf([
        //     'mode' => 'utf-8',
        //     'format' => 'A4',
        //     'margin_header' => ($mode == 'downloadLHPFinal' ? 12 : 17),
        //     'margin_bottom' => 22,
        //     'margin_footer' => 8,
        //     'margin_top' => 27.5, //23.5
        //     'margin_left' => 10,
        //     'margin_right' => 10,
        //     'orientation' => 'L',
        // ]);

        $mpdf = $this->mpdf;

        // $mpdf->AddPage('L', 'A4', '', '', '', 10, 10, 27.5, 22);
        $mpdf->AddPage();

        // $mpdf->SetProtection(array(
        //     'print'
        // ), '', 'skyhwk12');

        if ($mode == 'downloadWSDraft') {
            $mpdf->SetWatermarkImage(public_path() . '/watermark-draft.png', 0.05, '', array(0, 0), 200);
            $mpdf->showWatermarkImage = true;
        }

        if ($mode == 'downloadLHPFinal') {
            $mpdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);
            $mpdf->showWatermarkImage = true;
        }

        $mpdf->SetHTMLHeader($htmlHeader);
        $mpdf->SetHTMLFooter($htmlFooter);
        $mpdf->WriteHTML($this->stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($htmlBody);
        $mpdf->SetHTMLFooter($htmlLastFooter);

        if (isset($htmlCustomBody) && isset($htmlCustomHeader) && isset($htmlCustomFooter) && isset($htmlCustomLastFooter)) {
            foreach ($htmlCustomBody as $page => $custom) {
                $mpdf->SetHTMLHeader($htmlCustomHeader[$page]);
                $mpdf->SetHTMLFooter($htmlCustomFooter[$page]);
                $mpdf->WriteHTML($htmlCustomBody[$page]);
                $mpdf->SetHTMLFooter($htmlCustomLastFooter[$page]);
            }
        }

        // $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        // return $filename;
    }

    private function folderLocation($mode)
    {
        $paths = [
            'downloadWSDraft' => public_path('dokumen/LHPS/'),
            'downloadLHP' => public_path('dokumen/LHP/'),
            'downloadLHPFinal' => public_path('dokumen/LHP_DOWNLOAD/'),
            'LHPP' => public_path('dokumen/LHPP/'),
        ];

        if (!array_key_exists($mode, $paths)) {
            throw new \InvalidArgumentException("Mode {$mode} tidak dikenali.");
        }

        return $paths[$mode];
    }

    private function cekAkreditasi($data, $no_sampel)
    {
        $dataDecode = json_decode($data);

        $parameterAkreditasi = 0;
        $parameterNonAkreditasi = 0;
        $total = count($dataDecode);

        $orderDetail = OrderDetail::where('no_sampel', $no_sampel)->first();

        $kategori = explode('-', $orderDetail->kategori_2)[0];
        foreach ($dataDecode as $key => $value) {
            $parameter = Parameter::where('nama_lab', $value)->where('id_kategori', $kategori)->first();
            if ($parameter->status = 'AKREDITASI') {
                $parameterAkreditasi++;
            } else {
                $parameterNonAkreditasi++;
            }
        }

        if ($parameterAkreditasi == 0) {
            return false;
        }

        if ($total / $parameterAkreditasi >= 0.6) {
            return true;
        } else {
            return false;
        }
    }

    private function generateStylesheet()
    {
        // $paddingTop = in_array($this->mode, ['downloadWSDraft', 'downloadLHP']) ? '18px' : '14px';
        $this->stylesheet = " .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom1 {
                            padding: 3px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #5b5b5b;
                            font-size: 9px;
                        }
                        .custom2 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #5b5b5b;
                            font-size: 9px;
                        }
                        .custom3 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom4 {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .custom5 {
                            text-align: left;
                        }
                        .custom6 {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                        }
                        .custom7 {
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom8 {
                            text-align: center;
                            font-style: italic;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom9 {
                            padding: 3px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-dot-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-5-dot-left {
                            padding: 8px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-left {
                            padding: 8px;
                            text-align: left;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            font-size: 9px;
                        }
                        .pd-5-solid-top-center {
                            padding: 8px;
                            text-align: center;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            border-top: 1px solid #000000;
                            font-size: 9px;
                            font-weight: bold;
                        }
                        .bordered{
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
                            border-top: 1px solid #000000;
                            font-size: 9px;
                        }
                        .right {
                            float: right;
                            width: 40%;
                            height: 100%;
                        }
                        .left {
                            float: left;
                            width: 59%;
                        }
                        .left2 {
                            float: left;
                            width: 69%;
                        }";
    }

    private function ReplaceAlias($data)
    {
        $ketentuan = [
            "Air Bersih" => "Air untuk Keperluan Higiene Sanitasi",
            "Air Limbah Domestik" => "Air Limbah",
            "Air Limbah Industri" => "Air Limbah",
            "Air Minum" => "Air Minum",
            "Air Laut" => "Air Laut",
            "Air Permukaan" => "Air Sungai",
            "Air Kolam Renang" => "Air Kolam Renang",
            "Air Limbah" => "Air Limbah",
            "Air Sungai" => "Air Sungai",
            "Air Danau" => "Air Danau",
            "Air Higiene Sanitasi" => "Air untuk Keperluan Higiene Sanitasi",
            "Air Khusus" => "Air Reverse Osmosis",
            "Air Tanah" => "Air Tanah",
            "Air Limbah Terintegrasi" => "Air Limbah",
            "Air Waduk" => "Air Waduk",
            "Air Situ" => "Air Situ",
            "Air Rawa" => "Air Rawa",
            "Air Muara" => "Air Muara",
            "Air Mata Air" => "Air Mata Air",
            "Air Lindi" => "Air Lindi",
            "Air Reverse Osmosis" => "Air Reverse Osmosis",
        ];

        return isset($ketentuan[$data]) ? $ketentuan[$data] : $data;
    }
}
