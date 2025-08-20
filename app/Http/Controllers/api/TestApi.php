<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\{
    GetAtasan,
    Notification,
    Printing,
    TemplateLhpErgonomi
};
use App\Models\{
    DataLapanganErgonomi,
    DraftErgonomiFile};
use \Mpdf\Mpdf as PDF;
class TestApi extends Controller
{
    // public function index()
    // {
    //     // $getAtasan = GetAtasan::where('id', 7)->get()->pluck('email');

    //     // return response()->json(['data' => $getAtasan]);

    //     // Notification::whereIn('id', [127,7])->title('title')->message('Pesan Baru.!')->url('/')->send();
    //     $tes = Printing::get();
    //     return response()->json(['data' => $tes]);
    // }

    public function index(Request $request)
    {
        try {
            $render = new TemplateLhpErgonomi();
            $noSampel = 'THAU012501/001'; // Ambil no_sampel dari request frontend $request->no_sampel

            // Definisikan metode yang ingin digabungkan dan ID methodnya
            $methodsToCombine = [
                //'nbm' => 1,
                //'reba' => 2,
                //'rula' => 3,
                //'rosa' => 4,
                //'brief' => 6,
                'sni_gotrak' => 7,
                'sni_bahaya_ergonomi' =>8,
                // 'antropometri' =>9,
                // 'desain_stasiun_kerja' =>10
            ];

            // Konfigurasi mPDF umum untuk semua halaman
            /* $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_header' => 8,
                'margin_bottom' => 12,
                'margin_footer' => 5,
                'margin_top' => 15,
                'margin_left' => 10,
                'margin_right' => 10,
                'orientation' => 'L',
            ]; */
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font_size' => 7,
                'default_font' => 'arial'
            ];
            $pdf = new PDF($mpdfConfig); // Inisialisasi mPDF hanya sekali
            $globalCssContent ='body {
                    font-family: Arial, sans-serif;
                    font-size: 10pt; 
                    background-color: white;
                    margin: 0;
                    padding: 0;
                }.container-wrapper {
                    width: 100%;
                    padding: 15mm;
                    box-sizing: border-box;
                    border: 1px solid #000;
                    position: relative;
                }h1 {
                    text-align: center;
                    font-size: 14pt;
                    font-weight: bold;
                    text-decoration: underline;
                    margin-bottom: 15px;
                    margin-top: 0;
                    padding-top: 5mm;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 12px;
                    table-layout: fixed;
                }table, th, td {
                    border: 1px solid #000;
                }th, td {
                    padding: 4px 6px; 
                    text-align: center;
                    font-size: 9pt; 
                    vertical-align: top; 
                    word-wrap: break-word; 
                }
                th {
                    background-color: #e9e9e9; 
                    font-weight: bold;
                }.text-left { text-align: left; }.text-right { text-align: right; }.text-justify { text-align: justify; }.centered-text { text-align: center; }.clearfix::after {
                    content: "";
                    clear: both;
                    display: table;
                }thead { display: table-header-group; }
                tbody { display: table-row-group; }
                tfoot { display: table-footer-group; }.page-break-before { page-break-before: always; }.two-column-layout { 
                    width: 100%;
                    overflow: hidden; 
                    margin-bottom: 15px;
                }.column {
                    float: left;
                    box-sizing: border-box;
                    vertical-align: top; 
                }.col-width-60 { width: 60%; padding-right: 8px; }
                .col-width-40 { width: 40%; padding-left: 8px; }
                .col-width-65 { width: 65%; padding-right: 8px; }
                .col-width-35 { width: 35%; padding-left: 8px; }
                .col-width-30 { width: 30%; padding-right: 8px; }
                .col-width-25 { width: 25%; padding-right: 5px; }.table-display-layout { 
                    display: table;
                    width: 100%;
                    table-layout: fixed;
                    margin-bottom: 15px;
                }.table-display-layout .row-container { display: table-row; }
                .table-display-layout .col-wrapper { display: table-cell; box-sizing: border-box; vertical-align: top; }.col-left-half { width: 25%; padding-right: 5px; } 
                .col-right-half { width: 25%; padding-right: 5px; } 
                .col-wide-50 { width: 50%; padding-left: 5px; } 
                .col-left-nbm { width: 65%; padding-right: 8px; } 
                .col-right-nbm { width: 35%; padding-left: 8px; } 
                .col-left-brief-small { width: 30%; padding-right: 8px; } 
                .col-middle-brief-small { width: 30%; padding-right: 8px; } 
                .col-right-brief-large { width: 40%; padding-left: 8px; } 
                .col-full-width { width: 100%; padding: 0 8px; } 
                .col-no-padding-full-width { width: 100%; }.bottom-row {
                    clear: both;
                    width: 100%;
                    overflow: hidden;
                    margin-top: 10px;
                }.bottom-column {
                    float: left;
                    box-sizing: border-box;
                    padding-top: 5px;
                }.section {
                    border: 1px solid #000;
                    padding: 6px;
                    margin-bottom: 10px;
                    background-color: #fff;
                }.section-title { 
                    font-weight: bold;
                    background-color: #e0e0e0;
                    padding: 3px 6px;
                    margin: -6px -6px 6px -6px; 
                    border-bottom: 1px solid #000;
                    font-size: 9.5pt;
                }.info-table { 
                    margin-bottom: 5px;
                }.info-table th, .info-table td { border: 0 !important; text-align: left; padding: 2px 0; font-size: 9pt; }
                .info-table .label-column { width: 30%; padding-right: 5px; } 
                .info-table .separator-column { width: 5%; text-align: center; } 
                .info-table .value-column { width: 65%; }.info-line {
                    margin-bottom: 2px;
                    font-size: 9pt;
                    min-height: 1.1em;
                    overflow: hidden; 
                }.info-line .info-label { 
                    width: 100px;
                    float: left;
                    font-weight: normal;
                }.info-line .info-separator { 
                    width: 5px;
                    float: left;
                    text-align: center;
                }.info-line .info-value { 
                    overflow: hidden;
                    text-align: left;
                }.info-line .label { 
                    width: 100px;
                    float: left;
                    font-weight: normal;
                }.info-line .separator { 
                    width: 5px;
                    float: left;
                    text-align: center;
                }.info-line .value { 
                    overflow: hidden;
                    text-align: left;
                }.info-line span:nth-of-type(2) { 
                    display: inline-block;
                    margin-left: 5px;
                    text-align: left;
                }.text-input-space {
                    width: 100%;
                    border: 1px solid #ccc;
                    padding: 2px 4px;
                    min-height: 1.5em; 
                    background-color: #fff;
                    display: inline-block;
                    box-sizing: border-box;
                    font-size: 9pt;
                    line-height: 1.3;
                    vertical-align: middle;
                }.bold-text { 
                    font-weight: bold;
                    font-size: 9.5pt;
                    margin-bottom: 5px;
                    display: block;
                }.multi-line-input {
                    width: 100%;
                    border: 1px solid #000;
                    padding: 4px;
                    min-height: 40px; 
                    background-color: #fff;
                    box-sizing: border-box;
                    font-size: 9pt;
                    line-height: 1.4;
                    text-align: justify;
                }


                .notes {
                    font-size: 8.5pt;
                    margin-top: 10px;
                    line-height: 1.3;
                    padding: 5px;
                    border: 1px solid #eee;
                    background-color: #f9f9f9;
                }
                .notes sup { font-size: 9pt; vertical-align: super; }



                .signature-block {
                    margin-top: 15px;
                    text-align: right;
                    font-size: 9pt;
                }
                .signature-block div { margin-bottom: 2px; }
                .signature-block .signature-name {
                    margin-top: 30px;
                    font-weight: bold;
                    text-decoration: underline;
                    display: block;
                }


                .page-footer-text { 
                    font-size: 8pt;
                    margin-top: 15px;
                    border-top: 1px solid #ccc;
                    padding-top: 8px;
                    display: flex;
                    justify-content: space-between;
                }





                .rwl-header-text { 
                    font-size: 9pt;
                    text-align: left;
                    margin-bottom: 8px;
                }
                .rwl-table-title { 
                    text-align: left;
                    font-weight: bold;
                    padding: 5px 6px;
                    font-size: 10pt;
                    background-color: #f0f0f0;
                    margin-top: 10px;
                    margin-bottom: 0;
                    border: 1px solid #000;
                    border-bottom: none;
                }
                .rwl-bottom-column:nth-child(1) { width: 65%; padding-right: 8px; }
                .rwl-bottom-column:nth-child(2) { width: 35%; padding-left: 8px; }



                .rula-table-title { 
                    text-align: center;
                    font-weight: bold;
                    padding: 5px;
                    font-size: 9.5pt;
                    background-color: #f0f0f0;
                    border-bottom: 1px solid #000;
                }
                .rula-table-secondary { background-color: #f0f0f0; } 
                .rula-info-container { margin-top: 10px; margin-bottom: 10px; } 
                .rula-arrow { 
                    text-align: center;
                    font-size: 14pt;
                    margin: 0 4px;
                    display: inline-block;
                    vertical-align: middle;
                }
                .rula-note-box { 
                    border: 1px solid #000;
                    padding: 8px;
                    margin-top: 10px;
                    font-size: 9pt;
                    line-height: 1.4;
                }
                .rula-box-arrow { 
                    margin-bottom: 10px;
                    width: 100%;
                    clear: both;
                    display: flex; 
                    align-items: center;
                    justify-content: flex-start;
                    padding-top: 5px;
                }
                .rula-box-arrow table { width: auto; margin-bottom: 0; } 
                .rula-box-arrow table th, .rula-box-arrow table td { font-size: 9pt; padding: 3px 6px; }
                .rula-empty-box { 
                    width: 45px;
                    height: 22px;
                    border: 1px solid #000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-left: 8px;
                    font-size: 10pt;
                    font-weight: bold;
                    line-height: 1;
                }



                .rosa-skor-table th, .rosa-skor-table td { font-size: 8.5pt; padding: 3px 5px; }
                .rosa-skor-table .table-header-cell {
                    font-size: 9.5pt;
                    font-weight: bold;
                    text-align: center;
                    background-color: #f0f0f0;
                }
                .rosa-skor-d-row td { font-size: 9pt; }
                .rosa-skor-d-row .score-label { font-weight: bold; text-align: center; }
                .rosa-skor-d-row .final-score-box {
                    border: 1px solid #000;
                    width: 50px;
                    height: 25px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 10pt;
                    vertical-align: middle;
                }
                .rosa-skor-d-row .arrow-text {
                    font-size: 16pt;
                    margin: 0 5px;
                    vertical-align: middle;
                    display: inline-block;
                }
                .rosa-conclusion-box { 
                    border: 1px solid #000;
                    padding: 8px;
                    margin-top: 15px;
                    font-size: 9pt;
                    line-height: 1.4;
                    height: 200px;
                    overflow: hidden;
                    text-align: justify;
                    vertical-align: top;
                }
                .rosa-conclusion-box strong { display: block; margin-bottom: 5px; }



                .nbm-skor-table th, .nbm-skor-table td { font-size: 8.5pt; padding: 3px 5px; font-family: Arial, sans-serif !important; }
                .nbm-skor-table .sub-header {
                    background-color: #f0f0f0;
                    font-weight: bold;
                    font-size: 9.5pt;
                }
                .nbm-skor-table .total-row td { background-color: #e0e0e0; font-weight: bold; }
                .nbm-conclusion-box, .nbm-description-box {
                    border: 1px solid #000;
                    padding: 8px;
                    margin-top: 10px;
                    font-size: 9pt;
                    line-height: 1.4;
                    height: 100px;
                    vertical-align: top;
                    text-align: justify;
                }
                .nbm-conclusion-box strong, .nbm-description-box strong { display: block; margin-bottom: 5px; }



                .reba-skor-table th, .reba-skor-table td { font-size: 8.5pt; padding: 3px 5px; font-family: Arial, sans-serif !important; }
                .reba-skor-table img { max-width: 100%; height: auto; object-fit: contain; display: block; margin: auto; }
                .reba-skor-table .image-row { height: 50px; padding: 2px; }
                .reba-skor-table .text-label-row { padding: 2px; }
                .reba-skor-table .text-label-row u { display: block; }
                .reba-korelasi-table th, .reba-korelasi-table td { font-size: 8.5pt; padding: 3px 5px; }
                .reba-korelasi-table .final-score-reba { background-color: lightgrey; font-weight: bold; }
                .reba-korelasi-table.reba-korelasi-b { height: 350px; vertical-align: bottom; }
                .reba-acuan-table th, .reba-acuan-table td { font-size: 8.5pt; padding: 3px 5px; }
                .reba-acuan-table th { height: 35px; }
                .reba-conclusion-section table { margin-top: 10px; }
                .reba-conclusion-section th, .reba-conclusion-section td { font-size: 8.5pt; padding: 5px; }
                .reba-conclusion-section .conclusion-title { height: 75px; vertical-align: middle; font-weight: bold; }
                .reba-conclusion-section .description-title { height: 60px; vertical-align: middle; font-weight: bold; }
                .reba-conclusion-section .conclusion-content, .reba-conclusion-section .description-content { text-align: justify; vertical-align: top; }
                .reba-notes-table { margin-top: 10px; margin-bottom: 0; }
                .reba-notes-table td { border: 0 !important; font-size: 8.5pt; text-align: left; vertical-align: top; line-height: 1.3; }
                .reba-notes-table sup { font-size: 9pt; vertical-align: super; }



                .brief-skor-table th, .brief-skor-table td { font-size: 8.5pt; padding: 3px 5px; font-family: Arial, sans-serif !important; }
                .brief-skor-table .section-header {
                    text-align: left;
                    font-weight: bold;
                    font-size: 9.5pt;
                    border: 0 !important;
                    padding-left: 0;
                    padding-top: 10px;
                }
                .brief-skor-table .sub-row-title { text-align: center; font-weight: normal; }
                .brief-skor-table .skor-split-cell {
                    text-align: left;
                    font-size: 8pt;
                    line-height: 1.2;
                    vertical-align: middle;
                }
                .brief-skor-table .data-cell { height: 50px; }

                .brief-image-parts-layout { display: table; width: 100%; table-layout: fixed; margin-top: 10px; margin-bottom: 10px; }
                .brief-image-parts-layout .row-content { display: table-row; }
                .brief-image-parts-layout .cell-image { width: 180px; padding-right: 15px; vertical-align: top; }
                .brief-image-parts-layout .cell-list { vertical-align: top; }
                .brief-image-placeholder {
                    width: 100%; height: 330px; border: 1px solid #000; text-align: center;
                    font-size: 9pt; line-height: 1.4; padding: 5px; background-color: #f5f5f5;
                    display: flex; align-items: center; justify-content: center; box-sizing: border-box;
                }
                .brief-body-parts-list-table { width: 100%; border-collapse: collapse; }
                .brief-body-parts-list-table th, .brief-body-parts-list-table td {
                    border: 0 !important; padding: 2px 0; font-size: 9pt; text-align: left;
                    vertical-align: top; line-height: 1.2;
                }
                .brief-body-parts-list-table .part-name-cell { width: 50%; }
                .brief-body-parts-list-table .input-cell { width: 50%; }
                .brief-body-parts-list-table .input-line {
                    display: inline-block; border-bottom: 1px solid #000; width: 80%;
                    height: 14px; vertical-align: middle; text-align: center; box-sizing: border-box;
                    font-size: 8.5pt;
                }

                .brief-acuan-table th, .brief-acuan-table td { font-size: 8.5pt; padding: 3px 5px; }
                .brief-acuan-table .table-title {
                    text-align: left; font-weight: bold; font-size: 9.5pt; border: 0 !important;
                    padding-left: 0; padding-bottom: 5px; text-decoration: underline;
                }

                .brief-result-table th, .brief-result-table td { font-size: 8.5pt; padding: 5px; }
                .brief-result-table .title-cell { width: 35%; font-weight: bold; vertical-align: middle; }
                .brief-result-table .content-cell { width: 65%; text-align: justify; vertical-align: top; }
                .brief-result-table .result-height { height: 75px; }
                .brief-result-table .description-height { height: 60px; }







                .potensi-bahaya-table-potensi-bahaya th, .potensi-bahaya-table-potensi-bahaya td { font-size: 8.5pt; padding: 3px 5px; }
                .potensi-bahaya-table-potensi-bahaya td .text-input-space { min-height: 1.8em; }
                .potensi-bahaya-table-potensi-bahaya td { height: 1.8em; vertical-align: middle; }

                .potensi-bahaya-total-score-table th, .potensi-bahaya-total-score-table td { font-size: 9pt; padding: 3px 5px; }
                .potensi-bahaya-total-score-table td:first-child { text-align: left; font-weight: bold; background-color: #f0f0f0; }
                .potensi-bahaya-total-score-table td:last-child { width: 20%; }
                .potensi-bahaya-total-score-table.final-rekap-table td:first-child { width: 80%; }
                .potensi-bahaya-total-score-table td { height: 1.8em; vertical-align: middle; }

                .potensi-bahaya-uraian-tugas-table th, .potensi-bahaya-uraian-tugas-table td { font-size: 8.5pt; padding: 3px 5px; }
                .potensi-bahaya-uraian-tugas-table td .text-input-space { min-height: 1.8em; }
                .potensi-bahaya-uraian-tugas-table td { height: 1.8em; vertical-align: middle; }

                .potensi-bahaya-interpretasi-table th, .potensi-bahaya-interpretasi-table td { font-size: 9pt; padding: 3px 5px; }
                .potensi-bahaya-interpretasi-table td { text-align: center; }
                .potensi-bahaya-interpretasi-table td:last-child { text-align: left; }

                .potensi-bahaya-multi-line-input { min-height: 80px; }';
            // Atur watermark dan footer umum untuk semua halaman
            $pdf->SetWatermarkText('DRAFT');
            $pdf->showWatermarkText = true;
            $pdf->watermarkTextAlpha = 0.1;

            $footerHtml = '<table width="100%" border="0">
                                <tr>
                                    <td width="13%"></td>
                                    <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa izin tertulis dari pihak laboratorium.</td>
                                    <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                                </tr>
                            </table>';
            $pdf->SetFooter($footerHtml);
            $pdf->setAutoBottomMargin = 'stretch';

            $firstPageAdded = false;

            foreach ($methodsToCombine as $methodName => $methodId) {
                // Ambil data untuk setiap metode dan no_sampel yang diminta
                $dataMethod = DataLapanganErgonomi::with(['detail'])
                    ->where('no_sampel', $noSampel)
                    ->where('method', $methodId)
                    ->first();

                if ($dataMethod) {
                    $htmlContent = '';
                    // Panggil metode yang sesuai di TemplateLhpsErgonomi dan dapatkan HTMLnya
                    switch ($methodName) {
                        case 'nbm':
                            $htmlContent = $render->ergonomiNbm($dataMethod); // Teruskan data
                            break;
                        case 'reba':
                            $htmlContent = $render->ergonomiReba($dataMethod);
                            break;
                        case 'rula':
                            $htmlContent = $render->ergonomiRula($dataMethod);
                            break;
                        case 'rosa':
                            $htmlContent = $render->ergonomiRosa($dataMethod);
                            break;
                        case 'brief':
                            $htmlContent = $render->ergonomiBrief($dataMethod);
                            break;
                        case 'sni_gotrak':
                            $htmlContent = $render->ergonomiGontrak($dataMethod);
                            break;
                        case 'sni_bahaya_ergonomi':
                            $htmlContent = $render->ergonomiPotensiBahaya($dataMethod);
                            break;
                        // Tambahkan case lain untuk method lain yang ingin digabungkan
                    }

                    if ($htmlContent) {
                        // Tambahkan halaman baru jika ini bukan halaman pertama
                        if ($firstPageAdded) {
                            $pdf->AddPage();
                        } else {
                            $firstPageAdded = true;
                        }
                        $pdf->WriteHTML($htmlContent);
                    }
                }
            }

            if (!$firstPageAdded) {
                // Jika tidak ada data yang ditemukan untuk metode apa pun
                throw new \Exception("Tidak ada data laporan yang ditemukan untuk sampel ini.");
            }

            // Kembalikan PDF gabungan
            $dir = public_path("draft_ergonomi");
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            $namaFile = str_replace('/', '_', $dataMethod->no_sampel);
            $pathFile = $dir.'/ERGONOMI_'.$namaFile.'.pdf';
            $pdf->Output($pathFile, 'F');

            $saveFilePDF = new DraftErgonomiFile;
            $saveFilePDF::where('no_sampel',$dataMethod->no_sampel)->first();
            if($saveFilePDF != NULL){
                
            }
            return response()->json('data berhasil di render',200);
            /* return response($pdf->Output('laporan.pdf', 'S'), 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan_ergonomi_gabungan.pdf"',
            ]); */

        } catch (\Throwable $th) {
            return response()->json(["message" => $th->getMessage(),
                                    'line' => $th->getLine(),'file' =>$th->getFile()], 500);
        }
    }
}
