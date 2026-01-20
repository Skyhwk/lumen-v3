<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\LhpsAirHeader;
use App\Models\LhpsAirDetail;
use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiCDetail;
use App\Models\LhpsEmisiIsokinetikHeader;
use App\Models\LhpsEmisiIsokinetikDetail;
use App\Models\LhpsPadatanDetail;
use App\Models\LhpsPadatanHeader;
use App\Models\LhpsSinarUVDetail;
use App\Models\LhpsSinarUVHeader;
use App\Models\MasterBakumutu;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class LhpTemplate
{

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
    private $lampiran = false;
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

            case 'lampiran':
                self::$instance->lampiran = $value;
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

    public static function setDataHeader($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->header = $value;
        return self::$instance;
    }
    public static function useLampiran($value)
    {
        // dd($value, 'asd');
        if (!self::$instance) {
            self::$instance = new self();
        }
        self::$instance->lampiran = $value;
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

        self::$instance->showKan = $this->cekAkreditasi($this->header->no_lhp);

        $modes = [
            'downloadWSDraft',
            'downloadLHP',
            'downloadLHPFinal',
        ];

        if (!$value) {
            $resultName = '';
            foreach ($modes as $mode) {
                $resultName = $this->execute($this->header, $this->detail, $this->prefix, $view, $this->custom, $mode, $this->lampiran);
            }

            self::$instance = null;
            return $resultName;
        }

        $resultName = $this->execute($this->header, $this->detail, $this->prefix, $view, $this->custom, $value, $this->lampiran);

        self::$instance = null;
        return $resultName;
    }

    private function execute($header, $detail, $prefix, $view, $customs, $mode, $lampiran)
    {
        $namaFile = '';
        if ($this->filename) {
            $namaFile = $this->filename;
        } else {
            $namaFile = str_replace("/", "-", $header->no_lhp);
        }

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
        $filename = $prefix . '-' . $namaFile . '.pdf';
        $filePath = $dir . '/' . $filename;

        $htmlBody = view($view . '.left', compact('header', 'detail',  'mode'))->render();
        $htmlHeader = view($this->directoryDefault . '.header', compact('header', 'detail',  'mode', 'view', 'showKan'))->render();
        $htmlFooter = view($this->directoryDefault . '.footer', ['header' => $header, 'detail' => $detail, 'mode' => $mode, 'last' => false])->render();
        $htmlLastFooter = view($this->directoryDefault . '.footer', compact('header', 'detail',  'mode', 'last'))->render();
        if ($header->getTable() === 'lhps_air_header') {
            $biota = $detail->filter(fn($d) => !empty($d->hasil_uji_json) && $d->hasil_uji_json !== '{}');
            foreach ($biota as $key => $value) {
                $is_custom = false;
                $page = null;
                $biotaBody[$key] = view($view . '.biota', compact('header', 'value', 'mode'))->render();
                $biotaHeader[$key] = view($view . '.biotaHeader', compact('header', 'value', 'mode', 'view', 'showKan', 'is_custom', 'page'))->render();
                
            }
        }
        if (!empty($customs)) {
            foreach ($customs as $page => $custom) {
                $last = ($page === array_key_last($customs)) ? true : false;
                $htmlCustomBody[$page] = view($view . '.customLeft', compact('header', 'custom', 'page'))->render();
                $htmlCustomHeader[$page] = view($this->directoryDefault . '.customHeader', compact('header', 'detail', 'mode', 'view', 'showKan', 'page'))->render();
                $htmlCustomFooter[$page] = view($this->directoryDefault . '.footer', ['header' => $header, 'detail' => $detail, 'custom' => $custom, 'mode' => $mode, 'last' => false])->render();
                $htmlCustomLastFooter[$page] = view($this->directoryDefault . '.footer', compact('header', 'detail', 'custom', 'mode', 'last'))->render();

                if ($header->getTable() === 'lhps_air_header') {
                    $biota_custom = $detail->filter(fn($d) => !empty($d->hasil_uji_json) && $d->hasil_uji_json !== '{}');
                    foreach ($biota_custom as $key => $value) {
                        $is_custom = true;
                        $biotaCustomBody[$page][$key] = view($view . '.biota', compact('header', 'value', 'mode'))->render();
                        $biotaCustomHeader[$page][$key] = view($view . '.biotaHeader', compact('header', 'value', 'mode', 'view', 'showKan', 'is_custom', 'page'))->render();
                    }
                }
            }
        }

        if ($lampiran) {
            $pdfLampiran = view($this->directoryDefault . '.lampiran', [
                'header' => $header,
                'custom' => false,
                'page'   => null,
                'sub_kategori' => $header->sub_kategori ?? ''
            ])->render();

            $lampiranHeader = view($this->directoryDefault . '.lampiranHeader', compact('header', 'showKan', 'mode'))->render();
        }

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => ($mode == 'downloadLHPFinal' ? 12 : 17),
            'margin_bottom' => 22,
            'margin_footer' => 8,
            'margin_top' => 27.5, //23.5
            'margin_left' => 10,
            'margin_right' => 10,
            'orientation' => 'L',

            'fontDir' => array_merge($fontDirs, [
                __DIR__ . '/vendor/mpdf/mpdf/ttfonts', // pastikan path sesuai!
            ]),

            // Tambahkan font Roboto
            'fontdata' => $fontData + [
                'roboto' => [
                    'R'  => 'Roboto-Regular.ttf',     // 400
                    'M'  => 'Roboto-Medium.ttf',      // 500
                    'SB' => 'Roboto-SemiBold.ttf',    // 600
                    'B'  => 'Roboto-Bold.ttf',        // 700
                    'BI'  => 'Roboto-BoldItalic.ttf',        // 700
                ]
            ],
        ]);


        $mpdf->SetProtection(
            ['print'], // hanya boleh print
            '',        // user password kosong (bisa dibuka tanpa password)
            'skyhwk12',
            128,       // level enkripsi 128-bit
            [
                'copy' => false,
                'modify' => false,
                'print' => true,
                'annot-forms' => false,
                'fill-forms' => false,
                'extract' => false,
                'assemble' => false,
                'print-highres' => true
            ]
        );

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
        if(isset($biotaBody) && isset($biotaHeader)) {
            foreach ($biotaBody as $page => $custom) {
                $mpdf->SetHTMLHeader($biotaHeader[$page]);
                $mpdf->WriteHTML($biotaBody[$page]);
                $mpdf->SetHTMLFooter($htmlFooter);
                $mpdf->SetHTMLFooter($htmlLastFooter);
            }
        }
        if (isset($htmlCustomBody) && isset($htmlCustomHeader) && isset($htmlCustomFooter) && isset($htmlCustomLastFooter)) {
            foreach ($htmlCustomBody as $page => $custom) {
                $mpdf->SetHTMLHeader($htmlCustomHeader[$page]);
                $mpdf->SetHTMLFooter($htmlCustomFooter[$page]);
                $mpdf->WriteHTML($htmlCustomBody[$page]);
                $mpdf->SetHTMLFooter($htmlCustomLastFooter[$page]);

                if(isset($biotaCustomBody[$page]) && isset($biotaCustomHeader[$page])) {
                    foreach ($biotaCustomBody[$page] as $biotaPage => $biotaCustom) {
                        $mpdf->SetHTMLHeader($biotaCustomHeader[$page][$biotaPage]);
                        $mpdf->WriteHTML($biotaCustomBody[$page][$biotaPage]);
                        $mpdf->SetHTMLFooter($htmlCustomFooter[$page]);
                        $mpdf->SetHTMLFooter($htmlCustomLastFooter[$page]);
                    }
                }
            }
        }

        if (isset($pdfLampiran) && isset($lampiranHeader)) {
            $mpdf->SetHTMLHeader($lampiranHeader);
            $mpdf->WriteHTML($pdfLampiran);
            $mpdf->SetHTMLFooter($htmlFooter);
            if (isset($pdfLampiranCustom)) {
                foreach ($pdfLampiranCustom as $page => $custom) {
                    $mpdf->WriteHTML($pdfLampiranCustom[$page]);
                }
            }
        }

        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        return $filename;
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

    private function cekAkreditasi($no_lhp)
    {

        $parameterAkreditasi = 0;
        $parameterNonAkreditasi = 0;

        $orderDetail = OrderDetail::where('cfr', $no_lhp)->where('is_active', 1)->get();
        foreach ($orderDetail as  $value) {
            $kategori = explode('-', $value->kategori_2)[0];
            $sub_kategori = explode('-', $value->kategori_3)[0];
            $dataDecode = json_decode($value->parameter);
            $dataRegulasi = json_decode($value->regulasi, true)[0] ?? '';
            $sub_kategori = intval(strval($sub_kategori));
            $kategori = intval(strval($kategori));
            if ($kategori === 1) {
                $header = LhpsAirHeader::where('no_lhp', $value->cfr)->where('is_active', true)->first();
                $detail = LhpsAirDetail::where('id_header', $header->id)->get();
                foreach ($detail as $val) {
                    if ($val->akr != 'ẍ') {
                        $parameterAkreditasi++;
                    } else {
                        $parameterNonAkreditasi++;
                    }
                }
            } else if ($kategori === 6) {
                $header = LhpsPadatanHeader::where('no_lhp', $value->cfr)->where('is_active', true)->first();
                $detail = LhpsPadatanDetail::where('id_header', $header->id)->get();
                foreach ($detail as $val) {
                    if ($val->akr != 'ẍ') {
                        $parameterAkreditasi++;
                    } else {
                        $parameterNonAkreditasi++;
                    }
                }
            } else if ($kategori === 4 && ($sub_kategori === 27 || $sub_kategori === 11 ) && !collect($dataDecode)->contains(function ($item) {
                return in_array(
                    strtolower($item),
                    ['235;fungal counts', '266;jumlah bakteri total', '619;t. bakteri (kudr - 8 jam)', '620;t. jamur (kudr - 8 jam)', '563;medan magnet','309;pencahayaan', '316;power density', '277;medan listrik','236;gelombang elektro']
                );
            })) {
                if (collect($dataDecode)->contains(fn($item) => in_array($item, ['324;Sinar UV']))) {
                    $header = LhpsSinarUVHeader::where('no_lhp', $value->cfr)->where('is_active', true)->first();
                    $detail = LhpsSinarUVDetail::where('id_header', $header->id)->get();
                } else {
                    $header = LhpsLingHeader::where('no_lhp', $value->cfr)->where('is_active', true)->first();
                    $detail = LhpsLingDetail::where('id_header', $header->id)->get();
                }

                foreach ($detail as $val) {
                    if ($val->akr != 'ẍ') {
                        $parameterAkreditasi++;
                    } else {
                        $parameterNonAkreditasi++;
                    }
                }
            } else if ($kategori === 5 && !($sub_kategori === 32 || $sub_kategori === 31 || $sub_kategori === 116)) {
                if (collect($dataDecode)->contains(function ($item) {
                    return in_array(
                        $item,
                        ['395;Iso-Debu', '396;Iso-Traverse', '397;Iso-Velo', '398;Iso-DMW', '399;Iso-Moisture', '400;Iso-Percent']
                    );
                })) {
                    $header = LhpsEmisiIsokinetikHeader::where('no_lhp', $value->cfr)->where('is_active', true)->first();
                    $detail = LhpsEmisiIsokinetikDetail::where('id_header', $header->id)->get();
                } else {
                    $header = LhpsEmisiCHeader::where('no_lhp', $value->cfr)->where('is_active', true)->first();
                    $detail = LhpsEmisiCDetail::where('id_header', $header->id)->get();
                }

                foreach ($detail as $val) {
                    if ($val->akr != 'ẍ') {
                        $parameterAkreditasi++;
                    } else {
                        $parameterNonAkreditasi++;
                    }
                }
            } else {
                foreach ($dataDecode as $val) {
                    $bakumutu = MasterBakumutu::where('id_regulasi', explode("-", $dataRegulasi)[0])->where('parameter', explode(";", $val)[1])->first();
                    if ($bakumutu && str_contains($bakumutu->akreditasi, 'AKREDITASI')) {
                        $parameterAkreditasi++;
                    } else {
                        $parameterNonAkreditasi++;
                    }
                }
            }
        }
        if ($parameterAkreditasi == 0) {
            return false;
        }
        if (($parameterAkreditasi / ($parameterAkreditasi + $parameterNonAkreditasi)) >= 0.6) {
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
                            border: 1px solid #000000;
                            font-weight: bold;
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
                        .pd-3-dot {
                            padding: 3px;
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px dotted #000000;
                            font-size: 9px;
                        }
                        .pd-3-solid {
                            padding: 3px;
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
                        .pd-3-dot-center {
                            padding: 3px;
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
                        .pd-3-dot-left {
                            padding: 3px;
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
                        .pd-3-solid-left {
                            padding: 3px;
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
                        .pd-3-solid-center {
                            padding: 3px;
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
                        .pd-3-solid-top-center {
                            padding: 3px;
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
                        .leftMiddle{
                            float: left;
                            width: 50%;
                        }
                        .rightMiddle{
                            float: right;
                            width: 49%;
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
