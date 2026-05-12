<?php

namespace App\Services;

use Carbon\Carbon;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Mpdf\HTMLParserMode;
use Mpdf\Output\Destination;
use Mpdf\Config\FontVariables;
use Mpdf\Config\ConfigVariables;

use App\Services\MpdfService;

use App\Models\{DokumenCoc, HistoryAppReject, OrderDetail, OrderHeader, Parameter, PengesahanLhp, QrDocument};

class GenerateDokumenCocService
{
    protected string $lhpNumber;

    public function __construct(string $lhpNumber)
    {
        $this->lhpNumber = $lhpNumber;
    }

    private function generateNoDokumenCoc()
    {
        $number = 1;

        $latest = DokumenCoc::whereYear('generated_at', date('y'))->latest('generated_at')->first();
        if ($latest) {
            $explode = explode('/', $latest->no_dokumen);

            if (isset($explode[1])) {
                $lastCode = $explode[1];
                $lastNumber = (int) substr($lastCode, 2);
                $number = $lastNumber + 1;
            }
        }

        $number = date('y') . str_pad($number, 6, '0', STR_PAD_LEFT);

        return "ISL-05-SKET/{$number}";
    }

    public function generate()
    {
        $no_lhp = $this->lhpNumber;

        $orderDetail = OrderDetail::with(['TrackingSatu', 'scan_tc', 'scan_analis'])
            ->withAnyDataLapangan()
            ->withAnyLapanganHeader()
            ->withAnyLhps()
            ->where('cfr', $no_lhp)->where('is_active', true)
            ->get()
            ->each->append(['any_data_lapangan', 'any_lapangan_header', 'any_lhps']);

        $orderHeader = OrderHeader::find($orderDetail->first()->id_order_header);

        $parameterIds = $orderDetail
            ->flatMap(fn($item) => json_decode($item->parameter, true))
            ->filter()
            ->unique()
            ->values()
            ->map(fn($item) => explode(':', $item)[0]);

        $parameterUji = Parameter::whereIn('id', $parameterIds)->pluck('nama_regulasi')->filter()->unique()->values()->toArray();

        $historyAppRejectWsFinal = HistoryAppReject::where('no_lhp', $no_lhp)->where('menu', 'like', 'WS Final%')->whereNotNull('approved_at')->first();

        // $tglTerimaMin = $orderDetail->min('tanggal_terima');
        $tglApproveLapanganHeaderMax = $orderDetail->flatMap->any_lapangan_header->max('approved_at');
        $tglApproveWsFinal = optional($historyAppRejectWsFinal)->approved_at;
        $tglApproveLhps = $orderDetail->flatMap->any_lhps->max('approved_at');

        $pengesah = PengesahanLhp::where('berlaku_mulai', '<=', $tglApproveLhps)->latest('berlaku_mulai')->first();

        $dokumenCoc = new DokumenCoc();
        $dokumenCoc->no_dokumen = $this->generateNoDokumenCoc();
        $dokumenCoc->no_penawaran = $orderHeader->no_document;
        $dokumenCoc->no_order = $orderHeader->no_order;
        $dokumenCoc->no_lhp = $no_lhp;
        $dokumenCoc->nama_perusahaan = $orderHeader->nama_perusahaan;
        $dokumenCoc->alamat_sampling = $orderHeader->alamat_sampling;
        $dokumenCoc->titik_sampling = json_encode($orderDetail->pluck('keterangan_1')->filter()->unique()->values()->toArray());
        $dokumenCoc->kategori_sampling = $orderDetail->pluck('kategori_1')->filter()->unique()->values()->implode(', ');
        $dokumenCoc->parameter_uji = json_encode($parameterUji);
        $dokumenCoc->regulasi = json_encode($orderDetail->flatMap(fn($item) => json_decode($item->regulasi, true))->filter()->unique()->values()->toArray());
        $dokumenCoc->tgl_penawaran = $orderHeader->tanggal_penawaran;
        $dokumenCoc->tgl_konfirmasi_order = $orderHeader->tanggal_order;
        $dokumenCoc->tgl_mulai_sampling = $orderDetail->flatMap->any_data_lapangan->min('created_at');
        $dokumenCoc->tgl_selesai_sampling = $orderDetail->flatMap->any_data_lapangan->max('created_at');
        $dokumenCoc->tgl_terima_lab = $orderDetail->flatMap->TrackingSatu->max('ftc_verifier') ?? $orderDetail->flatMap->scan_tc->max('created_at');
        $dokumenCoc->tgl_mulai_analisa = $orderDetail->flatMap->TrackingSatu->min('ftc_laboratory') ?? $orderDetail->flatMap->scan_analis->min('created_at');
        $dokumenCoc->tgl_selesai_analisa = $tglApproveLapanganHeaderMax;
        $dokumenCoc->tgl_mulai_tcc = $tglApproveLapanganHeaderMax;
        $dokumenCoc->tgl_selesai_tcc = $tglApproveWsFinal;
        $dokumenCoc->tgl_mulai_drafting = $tglApproveWsFinal;
        $dokumenCoc->tgl_selesai_drafting = $tglApproveLhps;
        $dokumenCoc->tgl_penerbitan_lhp = $tglApproveLhps;
        $dokumenCoc->generated_by = 'System';
        $dokumenCoc->generated_at = date('Y-m-d H:i:s');

        $filename = str_replace("/", "_", $dokumenCoc->no_dokumen);
        $path = public_path() . "/qr_documents/" . $filename . '.svg';
        if (!file_exists($path)) {
            $link = 'https://www.intilab.com/validation/';
            $qrCode = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $qrCode, $path);
            $dataQr = [
                'type_document' => 'coc',
                'kode_qr' => $qrCode,
                'file' => $filename,
                'data' => json_encode([
                    'no_document' => $dokumenCoc->no_dokumen,
                    'nama_customer' => $orderHeader->nama_perusahaan,
                    'type_document' => 'Dokumen Chain of Custody',
                    'Tanggal_Pengesahan' => Carbon::now()->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $pengesah->nama_karyawan,
                    'Jabatan' => $pengesah->jabatan_karyawan
                ]),
                'created_by' => 'System',
                'created_at' => Carbon::now(),
            ];

            QrDocument::insert($dataQr);
        }

        $filename = $this->renderDokumenCoc($dokumenCoc, $path);

        $dokumenCoc->filename = $filename;
        $dokumenCoc->save();

        return $filename;
    }

    private function renderDokumenCoc($data, $qr)
    {
        $dir = public_path('dokumen/coc/');
        if (!file_exists($dir)) mkdir($dir, 0777, true);

        $htmlBody = view('DokumenCoc.body', ['data' => $data, 'qr' => $qr])->render();
        $htmlHeader = view('DokumenCoc.header', ['data' => $data])->render();

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 3,
            'margin_bottom' => 3,
            'margin_footer' => 3,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'orientation' => 'P',
            'fontDir' => array_merge($fontDirs, [__DIR__ . '/vendor/mpdf/mpdf/ttfonts']),
            'fontdata' => $fontData + [
                'roboto' => [
                    'R'  => 'Roboto-Regular.ttf',
                    'M'  => 'Roboto-Medium.ttf',
                    'SB' => 'Roboto-SemiBold.ttf',
                    'B'  => 'Roboto-Bold.ttf',
                    'BI'  => 'Roboto-BoldItalic.ttf',
                ]
            ],
        ]);

        $mpdf->SetProtection(array('print'), '', 'skyhwk12');

        $mpdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
        $mpdf->showWatermarkImage = true;
        $mpdf->showWatermarkText = true;
        $mpdf->keep_table_proportions = true;

        $mpdf->SetHTMLHeader($htmlHeader);
        $mpdf->WriteHTML($this->generateStylesheet(), HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($htmlBody);

        $mpdf->setFooter([
            'odd' => [
                'L' => [
                    'content' =>  'PT Inti Surya Laboratorium<br>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk,<br>Sampora Kab. Tangerang 15341 021-5089-8988/89 contact@intilab.com',
                    'font-size' => 4,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000'
                ],
                'C' => [
                    'content' => 'Hal {PAGENO} dari {nbpg}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060'
                ],
                'R' => [
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000'
                ],
                'line' => -1,
            ],
        ]);

        $filename = str_replace('/', '_', $data->no_dokumen) . '.pdf';

        $mpdf->Output($dir . '/' . $filename, Destination::FILE);

        return $filename;
    }

    private function generateStylesheet()
    {
        return  "
            .custom {
                padding: 3px;
                text-align: center;
                border: 1px solid #000;
                font-weight: bold;
                font-size: 9px;
            }
            .custom1 {
                padding: 3px;
                text-align: left;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px dotted #5b5b5b;
                font-size: 9px;
            }
            .custom2 {
                padding: 3px;
                text-align: center;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px dotted #5b5b5b;
                font-size: 9px;
            }
            .custom3 {
                padding: 3px;
                text-align: center;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                font-size: 9px;
            }
            .custom4 {
                padding: 3px;
                border: 1px solid #000;
                font-weight: bold;
                font-size: 9px;
            }
            .custom5 {
                text-align: left;
            }
            .custom6 {
                padding: 3px;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
            }
            .custom7 {
                text-align: center;
                border: 1px solid #000;
                font-weight: bold;
                font-size: 9px;
            }
            .custom8 {
                text-align: center;
                font-style: italic;
                border: 1px solid #000;
                font-weight: bold;
                font-size: 9px;
            }
            .custom9 {
                padding: 3px;
                text-align: center;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                font-size: 9px;
            }
            .pd-5-dot-center {
                padding: 8px;
                text-align: center;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px dotted #000;
                font-size: 9px;
            }
            .pd-5-dot-left {
                padding: 8px;
                text-align: left;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px dotted #000;
                font-size: 9px;
            }
            .pd-5-solid-left {
                padding: 8px;
                text-align: left;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                font-size: 9px;
            }
            .pd-5-solid-center {
                padding: 8px;
                text-align: center;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                font-size: 9px;
            }
            .pd-5-solid-top-center {
                padding: 8px;
                text-align: center;
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                border-top: 1px solid #000;
                font-size: 9px;
                font-weight: bold;
            }
            .bordered{
                border-left: 1px solid #000;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                border-top: 1px solid #000;
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
            }
        ";
    }
}
