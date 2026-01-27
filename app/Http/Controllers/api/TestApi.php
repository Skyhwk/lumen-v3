<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\{
    GetAtasan,
    Notification,
    Printing,
    TemplateLhpErgonomi,
    GenerateQrDocumentLhp
};
use App\Models\{
    DataLapanganErgonomi,
    WsValueErgonomi,
    DraftErgonomiFile,
    PengesahanLhp,OrderDetail,Parameter};
use \App\Services\MpdfService as PDF;
class TestApi extends Controller
{
    
    public function index(Request $request)
    {
        try {
            Carbon::setLocale('id');
            
            $noSampel = $request->no_sampel; // Ambil no_sampel dari request frontend $request->no_sampel
            
            /* prepare data pengesahan */
            $tanggalLhp = date('Y-m-d H:i:s');
            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $tanggalLhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            /* save file */
            $saveFilePDF = new DraftErgonomiFile;
            $pdfFile = $saveFilePDF::where('no_sampel',$noSampel)->first();
            
            if ($pdfFile === null) {
                $pdfFile = new $saveFilePDF(); // bikin object baru
            }
            $nama_perilis = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $jabatan_perilis = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
            $pdfFile->no_sampel = $noSampel;
            $pdfFile->create_at = Carbon::now('Asia/Jakarta');
            $pdfFile->nama_karyawan = $nama_perilis;
            $pdfFile->jabatan_karyawan = $jabatan_perilis;
            $pdfFile->create_by =$this->karyawan;
            // $pdfFile->save();

           
            
            /* prepare Qr Document */
            $file_qr = new GenerateQrDocumentLhp();
            $dataLHP = DataLapanganErgonomi::with(['detail'])
                    ->where('no_sampel', $noSampel)->first();
            if($pdfFile->file_qr == null && $pdfFile->file_qr == ''){
                $dataQr =(object)[
                    'id' => $saveFilePDF->id,
                    'no_lhp' => $dataLHP->detail->cfr,
                    'nama_pelanggan' => $dataLHP->detail->nama_perusahaan,
                    'no_order' => substr($dataLHP->detail->no_order, 0, 6),
                    'tanggal_lhp' => $tanggalLhp,
                    'nama_karyawan' => $pengesahan->nama_karyawan,
                    'jabatan_karyawan' => $pengesahan->jabatan_karyawan
                ];
                $file_qr = new GenerateQrDocumentLhp();
                //$pathQr = $file_qr->insert('LHP_ERGONOMI', $dataQr, $this->karyawan);
                
                //$pdfFile->file_qr = $pathQr;
                // $pdfFile->save();
            }
           
            
            // Definisikan metode yang ingin digabungkan dan ID methodnya
            $methodsToCombine = [
                'nbm' => 1,  //✅
                'reba' => 2, //✅
                'rula' => 3, //✅
                'rosa' => 4, //✅
                'rwl' => 5,  
                'brief' => 6,
                'sni_gotrak' => 7,
                'sni_bahaya_ergonomi' =>8,
                'antropometri' =>9,
                'desain_stasiun_kerja' =>10
            ];
            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 5,      // ✅ Berikan ruang untuk header
                'margin_bottom' => 15,     // ✅ Cukup ruang untuk footer (15mm + buffer)
                'margin_footer' => 5,      // ✅ Berikan ruang untuk footer
                'margin_top' => 30,
                'margin_left' => 10,
                'margin_right' => 10,
                'orientation' => 'L',
            );

            

            // Siapkan folder untuk menyimpan file
            $dir = public_path("draft_ergonomi");
            $folders = ['draft', 'lhp', 'lhp_digital'];
            
            foreach ($folders as $folder) {
                if (!file_exists("$dir/$folder")) {
                    mkdir("$dir/$folder", 0755, true);
                }
            }

            // Kumpulkan semua content HTML terlebih dahulu
            $allHtmlContent = [];
            $dataMethod = null;
            
            

            

            // Fungsi helper untuk membuat PDF dengan konfigurasi tertentu
            $createPDF = function($type) use ($mpdfConfig, $methodsToCombine, $noSampel, $dir,$pdfFile,$allHtmlContent) {
                
                $pdf = new PDF($mpdfConfig);
                $render = new TemplateLhpErgonomi();

                // CSS definitions (keeping your existing CSS)
                $globalCssContent = '
                    * {
                        box-sizing: border-box;
                    }
                    body {
                        font-family: Arial, sans-serif;
                        margin: 15px;
                        font-size: 9px;
                    }
                    .page-container {
                        width: 100%;
                        text-align: center;
                    }
                    .main-header-title {
                        text-align: center;
                        font-weight: bold;
                        font-size: 1.5em;
                        margin-bottom: 15px;
                        text-decoration: underline;
                    }
                    .two-column-layout {
                        width: 900px;
                        margin: 0 auto;
                        overflow: hidden;
                        text-align: left;
                    }
                    .column {
                        float: left;
                    }
                    .column-left {
                        width: 500px;
                    }
                    .column-right {
                        width: 390px; 
                        margin-left:6px; 
                    }
                    .section {
                        border: 1px solid #000;
                        padding: 6px;
                        margin-bottom: 10px;
                    }
                    .section-title {
                        font-weight: bold;
                        padding: 3px 6px;
                        margin: -6px -6px 6px -6px;
                        border-bottom: 1px solid #000;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 5px;
                        table-layout: fixed;
                    }
                    th, td {
                        border: 1px solid #000;
                        padding: 3px;
                        text-align: left;
                        vertical-align: top;
                    }
                    th {
                        font-weight: bold;
                        text-align: center;
                    }
                    .text-input-space {
                        width: 100%;
                        padding: 2px;
                        min-height: 1.5em;
                    }
                    .multi-line-input {
                        width: 100%;
                        border: 1px solid #000;
                        padding: 4px;
                        min-height: 40px;
                    }
                    .footer-text {
                        font-size: 0.85em;
                        margin-top: 15px;
                        padding-top: 8px;
                        display: flex;
                        justify-content: space-between;
                    }
                    .signature-block {
                        margin-top: 15px;
                        text-align: right;
                    }
                    .signature-block .signature-name {
                        margin-top: 30px;
                        font-weight: bold;
                        text-decoration: underline;
                    }
                    .interpretasi-table td { text-align: center; }
                    .interpretasi-table td:last-child { text-align: left; }
                    .uraian-tugas-table td { height: 1.8em; }
                ';
                $templateSpecificCss = '
                    /* Specific untuk Body Parts Analysis */
                    .image-placeholder-container {
                        width: 100%;
                        margin-top: 8px;
                        display: table;
                        table-layout: fixed;
                        min-height: 280px;
                    }
                    
                    .image-placeholder {
                        float: left;
                        width: 150px;
                        margin-right: 15px;
                    }
                    
                    .body-map {
                        overflow: hidden;
                    }
                    
                    .body-parts-list-container {
                        display: table-cell;
                        vertical-align: top;
                    }
                    
                    .body-parts-list {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 7.5px;
                        margin-bottom: 8px;
                    }
                    
                    .body-parts-list td {
                        padding: 2px 4px;
                        border: 1px solid #000;
                        vertical-align: middle;
                    }
                    
                    .body-parts-list td:first-child {
                        width: 70%;
                        text-align: left;
                    }
                    
                    .body-parts-list td:last-child {
                        width: 30%;
                        text-align: center;
                    }
                    
                    .input-line {
                        text-align: center;
                        font-weight: bold;
                        min-height: 12px;
                    }
                    
                    .analysis-content,
                    .conclusion-content {
                        min-height: 60px;
                        padding: 4px;
                        border: 1px solid #000;
                        font-size: 7.5px;
                        line-height: 1.3;
                    }
                    
                    /* Info table khusus untuk column kanan */
                    .info-table th,
                    .info-table td {
                        border: 0 !important;
                        text-align: left;
                        padding: 1px 0;
                        font-size: 7.5px;
                    }
                    
                    .info-table .label-column {
                        width: 40%;
                        padding-right: 3px;
                    }
                    
                    .lhp-info-table th,
                    .lhp-info-table td {
                        border: 1px solid #000 !important;
                        text-align: center;
                        font-size: 7px;
                        padding: 2px;
                    }
                    .signature-section {
                        width: 100%;
                        margin-top: 8px;
                        clear: both;
                    }

                    .signature-table {
                        width: 100%;
                        border: none !important;
                        font-family: Arial, sans-serif;
                        font-size: 8px;
                        table-layout: fixed;
                    }

                    .signature-table td {
                        border: none !important;
                        padding: 2px;
                        vertical-align: top;
                    }

                    .signature-left {
                        width: 65%;
                    }

                    .signature-right {
                        width: 35%;
                        text-align: center;
                    }

                    .signature-date {
                        margin-bottom: 8px;
                        font-size: 8px;
                    }

                    .signature-qr {
                        width: 60px;
                        height: 60px;
                        margin: 5px auto;
                        display: block;
                    }

                    .signature-text {
                        margin-top: 3px;
                        font-size: 7px;
                    }
                    .header-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 10px;
                        table-layout: fixed;
                    }

                    .header-table td {
                        border: none;
                        padding: 10px;
                        vertical-align: middle;
                        height: 60px;
                    }

                    .header-table .left-cell {
                        width: 33.33%;
                        text-align: left;
                        padding-left: 20px;
                    }

                    .header-table .center-cell {
                        width: 33.33%;
                        text-align: center;
                    }

                    .header-table .right-cell {
                        width: 33.33%;
                        text-align: right;
                        padding-right: 50px;
                    }
                    .header-logo {
                        height: 50px;
                        width: auto;
                        display: block;
                    }
                    .info-table {
                        border: 0;
                        margin-bottom: 6px;
                    }

                    .info-table td {
                        border: 0;
                        padding: 0px 2px;
                        font-size: 8px;
                        vertical-align: top;
                    }
                ';
                $templateSpecificCssNbm='
                    /* CSS dengan font size yang konsisten - Layout Fixed */
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                        font-size: 10px; /* Base font size yang konsisten */
                        width: 100%;
                        min-width: 800px; /* Minimum width untuk mempertahankan layout */
                    }

                    .header {
                        text-align: center;
                        margin-bottom: 5px;
                    }

                    .header h1 {
                        font-size: 12px; /* Dikurangi untuk konsistensi */
                        font-weight: bold;
                        margin: 5px 0;
                        text-decoration: underline;
                    }

                    .company-name {
                        font-weight: bold;
                        font-size: 10px; /* Konsisten dengan base */
                        text-align: left;
                        margin-bottom: 10px;
                    }

                    .section-title {
                        font-weight: bold;
                        margin: 10px 0 5px 0;
                        font-size: 10px; /* Konsisten dengan base */
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 9px; /* Sedikit lebih kecil untuk tabel */
                        margin-bottom: 10px;
                        table-layout: fixed; /* Fixed table layout */
                    }

                    th,
                    td {
                        border: 1px solid black;
                        padding: 3px 5px;
                        text-align: center;
                        vertical-align: middle;
                        font-size: 9px; /* Konsisten untuk semua sel tabel */
                    }

                    thead {
                        display: table-header-group;
                    }

                    tbody {
                        display: table-row-group;
                    }

                    .body-map {
                        width: 80px;
                        height: auto;
                        margin: 5px auto;
                        display: block;
                    }

                    .info-section {
                        margin-bottom: 10px;
                    }

                    .info-section p {
                        margin: 3px 0;
                        font-size: 9px; /* Konsisten */
                    }

                    .info-label {
                        font-weight: normal;
                        width: 120px;
                        float: left;
                        font-size: 9px; /* Konsisten, tidak lagi 10pt */
                    }

                    .info-value {
                        display: inline-block;
                        font-size: 9px; /* Konsisten */
                    }

                    .customer-info,
                    .sampling-info,
                    .worker-info {
                        margin-left: 0;
                        margin-bottom: 10px;
                    }

                    .customer-info h4,
                    .sampling-info h4,
                    .worker-info h4 {
                        margin: 5px 0 2px 0;
                        font-size: 10px; /* Konsisten */
                        font-weight: bold;
                    }

                    .risk-table {
                        margin-top: 5px;
                    }

                    .left-section p {
                        font-weight: bold;
                        text-align: justify;
                        margin-bottom: 5px;
                        font-size: 9px; /* Konsisten */
                    }

                    .table-note {
                        font-size: 8px; /* Tetap kecil untuk catatan */
                        margin-top: 3px;
                        font-style: italic;
                    }

                    .job-description {
                        margin-top: 10px;
                    }

                    .job-description th {
                        width: 30%;
                        text-align: left;
                        vertical-align: top;
                        font-size: 9px; /* Konsisten */
                    }

                    .job-description td {
                        vertical-align: top;
                        font-size: 9px; /* Konsisten */
                    }

                    .conclusion-box {
                        border: 1px solid black;
                        padding: 5px;
                        min-height: 30px;
                        margin-top: 5px;
                        margin-bottom: 10px;
                        font-size: 9px; /* Konsisten */
                    }

                    .conclusion-box .section-title {
                        margin-top: 0;
                        margin-bottom: 5px;
                        font-size: 10px; /* Konsisten */
                    }

                    /* Fixed Layout - Tidak Responsif */
                    .left-section {
                        width: 60%;
                        float: left;
                        box-sizing: border-box;
                        min-width: 60%;
                        max-width: 60%;
                    }

                    .right-section {
                        width: 39%;
                        float: right;
                        box-sizing: border-box;
                        min-width: 39%;
                        max-width: 39%;
                    }

                    /* Pastikan layout tetap fixed untuk print */
                    @media print {
                        .left-section {
                            width: 60% !important;
                            min-width: 60% !important;
                            max-width: 60% !important;
                        }
                        
                        .right-section {
                            width: 39% !important;
                            min-width: 39% !important;
                            max-width: 39% !important;
                        }
                    }

                    .result-header {
                        text-align: center;
                        font-weight: bold;
                        margin: 5px 0;
                        font-size: 9px; /* Konsisten dengan tabel */
                    }

                    /* Styling untuk tabel nested SEBELUM/SESUDAH */
                    .nested-table-container {
                        padding: 0;
                    }

                    .nested-table {
                        width: 100%;
                        margin: 0;
                        border: none;
                    }

                    .nested-table td {
                        border: 1px solid black;
                        width: 50%;
                        text-align: center;
                        font-weight: bold;
                        padding: 3px;
                        font-size: 9px; /* Konsisten */
                    }

                    .total-score {
                        font-weight: bold;
                        text-align: center;
                        margin-top: 5px;
                        font-size: 9px; /* Konsisten */
                    }

                    .content-container {
                        width: 100%;
                        min-width: 800px; /* Pastikan layout minimum */
                    }

                    .clearfix::after {
                        content: "";
                        clear: both;
                        display: table;
                    }
                    
                    .info-header {
                        font-weight: bold;
                        margin-top: 8px;
                        margin-bottom: 3px;
                        font-size: 10px; /* Konsisten, tidak lagi 10pt */
                        clear: both;
                    }

                    /* Styling khusus untuk informasi di sisi kanan */
                    .right-section div {
                        font-size: 9px; /* Base untuk right section */
                    }

                    .right-section span {
                        font-size: 9px; /* Konsisten untuk semua span */
                    }

                    /* Styling untuk div dengan margin-bottom di right section */
                    .right-section div[style*="margin-bottom: 3px"] {
                        margin-bottom: 3px;
                        font-size: 9px; /* Konsisten, tidak lagi 10pt */
                }';
                $rebaSpecificCss = '
                    * { 
                        box-sizing: border-box; 
                        margin: 0;
                        padding: 0;
                    }

                    body {
                        font-family: Arial, sans-serif;
                        font-size: 8px;
                        margin: 0;
                        padding: 0;
                    }

                    .container {
                        width: 100%;
                        clear: both;
                    }

                    /* Header */
                    .main-header {
                        text-align: center;
                        font-weight: bold;
                        font-size: 12px;
                        text-decoration: underline;
                        margin-bottom: 6px;
                        padding: 4px 0;
                        clear: both;
                        width: 100%;
                        display: block;
                    }

                    /* Main content wrapper */
                    .main-content {
                        clear: both;
                        overflow: hidden;
                        margin-bottom: 6px;
                        width: 100%;
                    }

                    /* Column layouts */
                    .column-left {
                        float: left;
                        width: 30%;
                        padding-right: 2px;
                    }

                    .column-center {
                        float: left;
                        width: 30%;
                        padding: 0 1px;
                    }

                    .column-right {
                        float: right;
                        width: 40%;
                        padding-left: 2px;
                    }

                    /* Bottom section */
                    .bottom-section {
                        clear: both;
                        overflow: hidden;
                        margin-top: 6px;
                        width: 100%;
                    }

                    .bottom-left {
                        float: left;
                        width: 60%;
                        padding-right: 2px;
                    }

                    .bottom-right {
                        float: right;
                        width: 40%;
                        padding-left: 2px;
                    }

                    /* Table styles */
                    table {
                        border-collapse: collapse;
                        width: 100%;
                        margin-bottom: 4px;
                        font-size: 8px;
                        border-spacing: 0;
                    }

                    th, td {
                        border: 1px solid #000;
                        padding: 1px 2px;
                        vertical-align: top;
                        font-size: 8px;
                        line-height: 1.0;
                        margin: 0;
                    }

                    th {
                        background-color: #f2f2f2;
                        font-weight: bold;
                        text-align: center;
                        padding: 2px;
                    }

                    .section-header {
                        font-weight: bold;
                        font-size: 8px;
                        text-decoration: underline;
                        text-align: left;
                        margin: 2px 0 1px 0;
                        display: block;
                        clear: both;
                    }

                    .info-table {
                        border: 0;
                        margin-bottom: 3px;
                    }

                    .info-table td {
                        border: 0;
                        padding: 0px 1px;
                        font-size: 8px;
                        vertical-align: top;
                        line-height: 1.0;
                    }

                    .final-score {
                        background-color: #e0e0e0;
                        font-weight: bold;
                    }

                    /* Image optimization */
                    td img {
                        max-width: 100%;
                        max-height: 25px;
                        object-fit: contain;
                        display: block;
                        margin: 0 auto;
                        vertical-align: middle;
                    }

                    /* Specific table adjustments */
                    .score-table th:first-child { width: 8%; }
                    .score-table th:nth-child(2) { width: 67%; }
                    .score-table th:last-child { width: 25%; }

                    .header-info-table th { 
                        width: 33.33%; 
                        padding: 2px 1px;
                        font-size: 8px;
                    }

                    .header-info-table td {
                        padding: 2px 1px;
                        text-align: center;
                        font-size: 8px;
                    }

                    .reference-table th:nth-child(1) { width: 15%; }
                    .reference-table th:nth-child(2) { width: 12%; }
                    .reference-table th:nth-child(3) { width: 23%; }
                    .reference-table th:nth-child(4) { width: 50%; }

                    .reference-table td {
                        font-size: 7px;
                        padding: 1px;
                        line-height: 1.0;
                    }

                    /* Compact spacing */
                    .image-row td:first-child {
                        text-align: center;
                        vertical-align: middle;
                        font-size: 8px;
                    }

                    .image-row td:nth-child(2) {
                        height: 25px;
                        padding: 1px;
                    }

                    .image-row td:last-child {
                        text-align: center;
                        vertical-align: middle;
                        font-size: 8px;
                    }

                    .label-row td {
                        text-align: center;
                        font-size: 7px;
                        padding: 1px;
                        height: 12px;
                    }

                    /* Footer notes */
                    .footer-notes {
                        border: 0;
                        margin-top: 15px;
                    }

                    .footer-notes td:first-child {
                        width: 2%;
                        text-align: right;
                        vertical-align: top;
                        font-size: 7px;
                        border: 0;
                        padding-right: 3px;
                    }

                    .footer-notes td:last-child {
                        width: 98%;
                        text-align: left;
                        font-size: 7px;
                        border: 0;
                        line-height: 1.0;
                    }

                    /* Conclusion table specific */
                    .conclusion-table td:first-child {
                        width: 35%;
                        text-align: center;
                        font-weight: bold;
                        vertical-align: middle;
                        height: 35px;
                        font-size: 8px;
                    }

                    .conclusion-table td:last-child {
                        width: 65%;
                        text-align: justify;
                        vertical-align: top;
                        font-size: 8px;
                        line-height: 1.1;
                        padding: 3px;
                    }

                    /* Compact margin adjustments */
                    .compact-table {
                        margin-bottom: 2px;
                    }

                    /* Text alignment helpers */
                    .text-left { text-align: left !important; padding-left: 3px; }
                    .text-center { text-align: center !important; }
                    .text-justify { text-align: justify !important; }

                    /* Clear floats helper */
                    .clearfix::after {
                        content: "";
                        display: table;
                        clear: both;
                    }
                ';
                // Setup header yang sama untuk semua versi
                /* width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; */
                switch($type) {
                    case 'draft':
                        /***
                         * 
                         * style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <span
                                style="font-weight: bold; border-bottom: 1px solid #000">'.$title_lhp.'</span>
                        </td>
                    </tr>
                         */
                        $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                                <tr>
                                    <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                    </td>
                                    <td style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;"><span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span></td>
                                    <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                    </td>
                                <tr>
                                </table>';
                            break;
                    case 'lhp':
                        $header ='<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; ">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                        </td>
                                    </tr>
                                     </table>';
                            break;
                    case 'lhp_digital':
                        /* chek akreditasi */
                        $noSampelAkre = OrderDetail::where('no_sampel',$noSampel)->first();
                        $decodeParameterNya =json_decode($noSampelAkre->parameter,true);
                        $idParameterAkre =explode(';',$decodeParameterNya[0])[0];
                        $akreditasiKan = Parameter::where('id', $idParameterAkre)->where('status', "AKREDITASI")->where('is_active', true)->first();

                        if($akreditasiKan === null){
                            $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif; ">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                        </td>
                                    </tr>
                                     </table>';
                        }else{
                            $header = '<table width="100%" border="0" style="border:none; border-collapse:collapse; text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                                    <tr>
                                        <td class="left-cell" style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: left; padding-left: 20px;">
                                            <img src="'.public_path('img/isl_logo.png').'" alt="ISL"  style ="height: 50px; width: auto; display: block;">
                                        </td>
                                        <td  style="border: none; padding: 10px; vertical-align: middle; height: 60px; width: 33.33%; text-align: center;">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">LAPORAN HASIL PENGUJIAN</span>
                                        </td>
                                        <td style="border: none; padding: 10px; vertical-align: middle; height: 60px width: 33.33%; text-align: right; padding-right: 50px;">
                                            <img src="'.public_path('img/logo_kan.png').'" alt="KAN" style ="height: 50px; width: auto; display: block;">
                                        </td>
                                    </tr>
                                     </table>';
                        }
                            break;
                }
               $pdf->SetHeader($header);
                // Setup watermark dan footer berdasarkan tipe
                switch($type) {
                    case 'draft':
                        // Draft: watermark draft + footer dengan QR
                        $pageWidth = $pdf->w;
                        $watermarkPath = public_path().'/watermark-draft.png';
                        $pdf->SetWatermarkImage(
                            $watermarkPath,
                            0.1,
                            '',
                            [0, 0],
                            $pageWidth,
                            0
                        );
                        $pdf->showWatermarkImage = true;
                        
                        $footerHtml = '
                            <table width="100%" border="0" style="border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                <tr>
                                    <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                        PT Inti Surya Laboratorium<br>
                                        Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br>
                                        021-5089-8988/89 contact@intilab.com
                                    </td>
                                    <td width="45%" style="font-size: 6px; vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none">
                                        Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.<br>
                                    </td>
                                    <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none">
                                        <b>Halaman {PAGENO} dari {nbpg}</b>
                                    </td>
                                </tr>
                            </table>';
                        break;
                        
                    case 'lhp':
                        // LHP: tanpa watermark + footer tanpa QR
                        $pdf->showWatermarkImage = false;
                        $file_qr = public_path('qr_documents/' . $pdfFile->file_qr . '.svg');
                        $footerHtml = '
                            <table width="100%" border="0" style="border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                <tr>
                                    <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                        PT Inti Surya Laboratorium<br>
                                        Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br>
                                        021-5089-8988/89 contact@intilab.com
                                    </td>
                                    <td width="45%" style="font-size: 6px; vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none">
                                        Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.<br>
                                        <b>Halaman {PAGENO} dari {nbpg}</b>
                                    </td>
                                    <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none">
                                        <img src="'.$file_qr.'" width="25" height="25" alt="QR Code" />
                                    </td>
                                </tr>
                            </table>';
                        break;
                        
                    case 'lhp_digital':
                        // LHP Digital: watermark logo + footer dengan QR
                        $pdf->SetWatermarkImage(public_path('logo-watermark.png'), -1, '', [110, 35]);
                        $pdf->showWatermarkImage = true;
                        $file_qr = public_path('qr_documents/' . $pdfFile->file_qr . '.svg');
                        $footerHtml = '
                            <table width="100%" border="0" style="border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                                <tr>
                                    <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                        PT Inti Surya Laboratorium<br>
                                        Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br>
                                        021-5089-8988/89 contact@intilab.com
                                    </td>
                                    <td width="45%" style="font-size: 6px; vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none">
                                        Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.<br>
                                        <b>Halaman {PAGENO} dari {nbpg}</b>
                                    </td>
                                    <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none">
                                        <img src="'.$file_qr.'" width="25" height="25" alt="QR Code" />
                                    </td>
                                </tr>
                            </table>';
                        break;
                }
                $pdf->SetHTMLFooter($footerHtml,'0');
                $pdf->setAutoBottomMargin = 'stretch';
                // Tulis semua konten HTML
                foreach ($methodsToCombine as $methodName => $methodId) {
                    // Ambil data untuk setiap metode dan no_sampel yang diminta
                    $dataMethod = DataLapanganErgonomi::with(['detail'])
                        ->where('no_sampel', $noSampel)
                        ->where('method', $methodId)
                        ->first();
                   
                    if ($dataMethod) {
                       
                        $ttdData = null;
                        switch($type) {
                            case 'draft':
                                $ttdData = null; // Tidak ada TTD untuk draft
                                break;
                            case 'lhp':
                                $ttdData = (object)[
                                    'show_signature' => true,
                                    'show_qr_in_signature' => true,
                                    'nama_karyawan' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                                    'jabatan_karyawan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                                    'tanggal' => Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('DD MMMM YYYY'),
                                    'qr_path' => null
                                ];
                                break;
                            case 'lhp_digital':
                                $ttdData = (object)[
                                    'show_signature' => true,
                                    'show_qr_in_signature' => false, // Tidak perlu QR di TTD karena sudah ada di footer
                                    'nama_karyawan' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                                    'jabatan_karyawan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                                    'tanggal' => Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('DD MMMM YYYY'),
                                    'qr_path' => public_path('qr_documents/' . $pdfFile->file_qr . '.svg')
                                ];
                                break;
                        }
                        $htmlContent = '';
                        // Panggil metode yang sesuai di TemplateLhpsErgonomi dan dapatkan HTMLnya
                        switch ($methodName) {
                            case 'rwl':
                                $htmlContent = $render->ergonomiRwl($dataMethod,'','',$ttdData);
                                break;
                            case 'nbm':
                                $htmlContent = $render->ergonomiNbm($dataMethod,'',$templateSpecificCssNbm,$ttdData);
                                break;
                            case 'reba':
                                $htmlContent = $render->ergonomiReba($dataMethod,$globalCssContent,$rebaSpecificCss,$ttdData);
                                break;
                            case 'rula':
                                $htmlContent = $render->ergonomiRula($dataMethod,'','',$ttdData);
                                break;
                            case 'rosa':
                                $htmlContent = $render->ergonomiRosa($dataMethod,'','',$ttdData);
                                break;
                            case 'brief':
                                $htmlContent = $render->ergonomiBrief($dataMethod,'','',$ttdData);
                                break;
                            case 'sni_gotrak':
                                 
                                $htmlContent = $render->ergonomiGontrak($dataMethod,$globalCssContent,$templateSpecificCss,$ttdData);
                                break;
                            case 'sni_bahaya_ergonomi':
                                $htmlContent = $render->ergonomiPotensiBahaya($dataMethod,'','',$ttdData);
                                break;
                        }

                        
                        if ($htmlContent != '') {
                            $allHtmlContent[] = $htmlContent;
                        }

                    }
                }
                
                $firstPage = true;
                foreach ($allHtmlContent as $htmlContent) {
                    if (!$firstPage) {
                        $pdf->AddPage();
                        
                    }
                    $pdf->WriteHTML($htmlContent);
                   
                    $firstPage = false;
                  
                }
                
                
                // Simpan file
                $namaFile = 'LHP_Ergonomi_'.str_replace('/', '_', $noSampel).'.pdf';
                $pathFile = $dir.'/'.$type.'/'.$namaFile;
                $pdf->Output($pathFile, 'F');
                return [$pdf,$namaFile];
            };

            // Buat 3 versi PDF
            $pdfDraft = $createPDF('draft')[0];
            $pdfLhp = $createPDF('lhp')[0]; 
            $pdfLhpDigital = $createPDF('lhp_digital')[0];
            $pdfFile->name_file = $createPDF('draft')[1];
            //$pdfFile->save();
            return response($pdfDraft->Output('laporan.pdf', 'S'), 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan_ergonomi_gabungan.pdf"',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                "message" => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    private function applyWatermark($pdf, $mode)
    {
        $pageWidth = $pdf->w;
        $pageHeight = $pdf->h;
        $path = $mode === 'kan'
            ? public_path('logo-watermark.png')
            : public_path('watermark-draft.png');

        if ($mode === 'kan') {
            // logo ISL digital
            $pdf->SetWatermarkImage(public_path('logo-watermark.png'), -1, '', [110, 35]);
        } else {
            // draft besar transparan
            $pageWidth = $pdf->w;
            $pdf->SetWatermarkImage(public_path('watermark-draft.png'), 0.1, '', [0, 0], $pageWidth, 0);
        }
        $pdf->showWatermarkImage = true;
    }

    private function getHeader()
    {
        return '
            <table width="100%" border="0" style="border:none; border-collapse:collapse;">
                <tr>
                    <td width="33%" style="padding-left:20px;">
                        <img src="' . public_path('img/isl_logo.png') . '" alt="ISL">
                    </td>
                    <td width="33%" style="text-align:center;">
                        <b style="border-bottom:1px solid #000">LAPORAN HASIL PENGUJIAN</b>
                    </td>
                    <td width="33%" style="text-align:right; padding-right:50px;">
                        <img src="' . public_path('img/logo_kan.png') . '" alt="KAN" width="100" height="50">
                    </td>
                </tr>
            </table>';
    }

    private function getFooter($mode)
    {
        $qr = $mode === 'kan'
            ? '<img src="' . public_path('qr_documents/ISL_STPS_25-VIII_5054.svg') . '" width="25" height="25">'
            : '';
        return '<table width="100%" border="0" style="border:none; border-collapse:collapse; font-family: Arial, sans-serif; margin: 0; padding: 0;">
                        <tr>
                            <td width="30%" style="vertical-align: top; font-size: 6px; line-height: 1.1; padding: 0; text-align: left; border:none;">
                                PT Inti Surya Laboratorium<br>
                                Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br>
                                021-5089-8988/89 contact@intilab.com
                            </td>
                            <td width="45%" style="font-size: 6px; vertical-align: top; text-align: center; line-height: 1.1; padding: 0; border:none">
                                Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.<br>
                                <b>Halaman {PAGENO} dari {nbpg}</b>
                            </td>
                            <td width="25%" style="text-align: right; vertical-align: top; padding: 0; border:none">
                                '.$qr.'
                            </td>
                        </tr>
                    </table>';
    }

    private function getGlobalCss()
    {
        return '* {
                    box-sizing: border-box;
                 }

                 body {
                     font-family: Arial, sans-serif;
                     margin: 15px;
                     font-size: 9px;
                 }

                 .page-container {
                     width: 100%;
                     text-align: center; /* opsional kalau mau text di dalam rata tengah */
                 }

                 .main-header-title {
                     text-align: center;
                     font-weight: bold;
                     font-size: 1.5em;
                     margin-bottom: 15px;
                     text-decoration: underline;
                 }

                 .two-column-layout {
                     width: 900px;         /* atau fixed misalnya 800px */
                     margin: 0 auto;     /* bikin center */
                     overflow: hidden;
                     text-align: left;   /* supaya isi kolom tidak ikut rata tengah */
                 }

                 .column {
                     float: left;
                 }

                 .column-left {
                     width: 500px;
                 }

                 .column-right {
                     width: 390px; 
                     margin-left:6px; 
                 }

                 .section {
                     border: 1px solid #000;
                     padding: 6px;
                     margin-bottom: 10px;
                 }

                 .section-title {
                     font-weight: bold;
                     padding: 3px 6px;
                     margin: -6px -6px 6px -6px;
                     border-bottom: 1px solid #000;
                 }

                 table {
                     width: 100%;
                     border-collapse: collapse;
                     margin-bottom: 5px;
                     table-layout: fixed;
                 }

                 th, td {
                     border: 1px solid #000;
                     padding: 3px;
                     text-align: left;
                     vertical-align: top;
                 }

                 th {
                     font-weight: bold;
                     text-align: center;
                 }

                 .text-input-space {
                     width: 100%;
                    
                     padding: 2px;
                     min-height: 1.5em;
                 }

                 .multi-line-input {
                     width: 100%;
                     border: 1px solid #000;
                     padding: 4px;
                     min-height: 40px;
                 }

                 .footer-text {
                     font-size: 0.85em;
                     margin-top: 15px;
                    
                     padding-top: 8px;
                     display: flex;
                     justify-content: space-between;
                 }

                 .signature-block {
                     margin-top: 15px;
                     text-align: right;
                 }

                 .signature-block .signature-name {
                     margin-top: 30px;
                     font-weight: bold;
                     text-decoration: underline;
                 }

                 .interpretasi-table td { text-align: center; }
                 .interpretasi-table td:last-child { text-align: left; }

                 .uraian-tugas-table td { height: 1.8em; }';
    }

    private function getSpecificCss($methodName)
    {
        switch ($methodName) {
            case 'nbm':
                return '/* CSS dengan font size yang konsisten - Layout Fixed */
                 body {
                     font-family: Arial, sans-serif;
                     margin: 0;
                     padding: 0;
                     font-size: 10px; /* Base font size yang konsisten */
                     width: 100%;
                     min-width: 800px; /* Minimum width untuk mempertahankan layout */
                 }

                 .header {
                     text-align: center;
                     margin-bottom: 5px;
                 }

                 .header h1 {
                     font-size: 12px; /* Dikurangi untuk konsistensi */
                     font-weight: bold;
                     margin: 5px 0;
                     text-decoration: underline;
                 }

                 .company-name {
                     font-weight: bold;
                     font-size: 10px; /* Konsisten dengan base */
                     text-align: left;
                     margin-bottom: 10px;
                 }

                 .section-title {
                     font-weight: bold;
                     margin: 10px 0 5px 0;
                     font-size: 10px; /* Konsisten dengan base */
                 }

                 table {
                     width: 100%;
                     border-collapse: collapse;
                     font-size: 9px; /* Sedikit lebih kecil untuk tabel */
                     margin-bottom: 10px;
                     table-layout: fixed; /* Fixed table layout */
                 }

                 th,
                 td {
                     border: 1px solid black;
                     padding: 3px 5px;
                     text-align: center;
                     vertical-align: middle;
                     font-size: 9px; /* Konsisten untuk semua sel tabel */
                 }

                 thead {
                     display: table-header-group;
                 }

                 tbody {
                     display: table-row-group;
                 }

                 .body-map {
                     width: 80px;
                     height: auto;
                     margin: 5px auto;
                     display: block;
                 }

                 .info-section {
                     margin-bottom: 10px;
                 }

                 .info-section p {
                     margin: 3px 0;
                     font-size: 9px; /* Konsisten */
                 }

                 .info-label {
                     font-weight: normal;
                     width: 120px;
                     float: left;
                     font-size: 9px; /* Konsisten, tidak lagi 10pt */
                 }

                 .info-value {
                     display: inline-block;
                     font-size: 9px; /* Konsisten */
                 }

                 .customer-info,
                 .sampling-info,
                 .worker-info {
                     margin-left: 0;
                     margin-bottom: 10px;
                 }

                 .customer-info h4,
                 .sampling-info h4,
                 .worker-info h4 {
                     margin: 5px 0 2px 0;
                     font-size: 10px; /* Konsisten */
                     font-weight: bold;
                 }

                 .risk-table {
                     margin-top: 5px;
                 }

                 .left-section p {
                     font-weight: bold;
                     text-align: justify;
                     margin-bottom: 5px;
                     font-size: 9px; /* Konsisten */
                 }

                 .table-note {
                     font-size: 8px; /* Tetap kecil untuk catatan */
                     margin-top: 3px;
                     font-style: italic;
                 }

                 .job-description {
                     margin-top: 10px;
                 }

                 .job-description th {
                     width: 30%;
                     text-align: left;
                     vertical-align: top;
                     font-size: 9px; /* Konsisten */
                 }

                 .job-description td {
                     vertical-align: top;
                     font-size: 9px; /* Konsisten */
                 }

                 .conclusion-box {
                     border: 1px solid black;
                     padding: 5px;
                     min-height: 30px;
                     margin-top: 5px;
                     margin-bottom: 10px;
                     font-size: 9px; /* Konsisten */
                 }

                 .conclusion-box .section-title {
                     margin-top: 0;
                     margin-bottom: 5px;
                     font-size: 10px; /* Konsisten */
                 }

                 /* Fixed Layout - Tidak Responsif */
                 .left-section {
                     width: 60%;
                     float: left;
                     box-sizing: border-box;
                     min-width: 60%;
                     max-width: 60%;
                 }

                 .right-section {
                     width: 39%;
                     float: right;
                     box-sizing: border-box;
                     min-width: 39%;
                     max-width: 39%;
                 }

                 /* Pastikan layout tetap fixed untuk print */
                 @media print {
                     .left-section {
                         width: 60% !important;
                         min-width: 60% !important;
                         max-width: 60% !important;
                     }
                    
                     .right-section {
                         width: 39% !important;
                         min-width: 39% !important;
                         max-width: 39% !important;
                     }
                 }

                 .result-header {
                     text-align: center;
                     font-weight: bold;
                     margin: 5px 0;
                     font-size: 9px; /* Konsisten dengan tabel */
                 }

                 /* Styling untuk tabel nested SEBELUM/SESUDAH */
                 .nested-table-container {
                     padding: 0;
                 }

                 .nested-table {
                     width: 100%;
                     margin: 0;
                     border: none;
                 }

                 .nested-table td {
                     border: 1px solid black;
                     width: 50%;
                     text-align: center;
                     font-weight: bold;
                     padding: 3px;
                     font-size: 9px; /* Konsisten */
                 }

                 .total-score {
                     font-weight: bold;
                     text-align: center;
                     margin-top: 5px;
                     font-size: 9px; /* Konsisten */
                 }

                 .content-container {
                     width: 100%;
                     min-width: 800px; /* Pastikan layout minimum */
                 }

                 .clearfix::after {
                     content: "";
                     clear: both;
                     display: table;
                 }
                
                 .info-header {
                     font-weight: bold;
                     margin-top: 8px;
                     margin-bottom: 3px;
                     font-size: 10px; /* Konsisten, tidak lagi 10pt */
                     clear: both;
                 }

                 /* Styling khusus untuk informasi di sisi kanan */
                 .right-section div {
                     font-size: 9px; /* Base untuk right section */
                 }

                 .right-section span {
                     font-size: 9px; /* Konsisten untuk semua span */
                 }

                 /* Styling untuk div dengan margin-bottom di right section */
                 .right-section div[style*="margin-bottom: 3px"] {
                     margin-bottom: 3px;
                     font-size: 9px; /* Konsisten, tidak lagi 10pt */
             }';
            case 'reba':
                return '* { 
                     box-sizing: border-box; 
                     margin: 0;
                     padding: 0;
                 }

                 body {
                     font-family: Arial, sans-serif;
                     font-size: 8px;
                     margin: 0;
                     padding: 0;
                 }

                 .container {
                     width: 100%;
                     clear: both;
                 }

                 /* Header */
                 .main-header {
                     text-align: center;
                     font-weight: bold;
                     font-size: 12px;
                     text-decoration: underline;
                     margin-bottom: 6px;
                     padding: 4px 0;
                     clear: both;
                     width: 100%;
                     display: block;
                 }

                 /* Main content wrapper */
                 .main-content {
                     clear: both;
                     overflow: hidden;
                     margin-bottom: 6px;
                     width: 100%;
                 }

                 /* Column layouts */
                 .column-left {
                     float: left;
                     width: 30%;
                     padding-right: 2px;
                 }

                 .column-center {
                     float: left;
                     width: 30%;
                     padding: 0 1px;
                 }

                 .column-right {
                     float: right;
                     width: 40%;
                     padding-left: 2px;
                 }

                 /* Bottom section */
                 .bottom-section {
                     clear: both;
                     overflow: hidden;
                     margin-top: 6px;
                     width: 100%;
                 }

                 .bottom-left {
                     float: left;
                     width: 60%;
                     padding-right: 2px;
                 }

                 .bottom-right {
                     float: right;
                     width: 40%;
                     padding-left: 2px;
                 }

                 /* Table styles */
                 table {
                     border-collapse: collapse;
                     width: 100%;
                     margin-bottom: 4px;
                     font-size: 8px;
                     border-spacing: 0;
                 }

                 th, td {
                     border: 1px solid #000;
                     padding: 1px 2px;
                     vertical-align: top;
                     font-size: 8px;
                     line-height: 1.0;
                     margin: 0;
                 }

                 th {
                     background-color: #f2f2f2;
                     font-weight: bold;
                     text-align: center;
                     padding: 2px;
                 }

                 .section-header {
                     font-weight: bold;
                     font-size: 8px;
                     text-decoration: underline;
                     text-align: left;
                     margin: 2px 0 1px 0;
                     display: block;
                     clear: both;
                 }

                 .info-table {
                     border: 0;
                     margin-bottom: 3px;
                 }

                 .info-table td {
                     border: 0;
                     padding: 0px 1px;
                     font-size: 8px;
                     vertical-align: top;
                     line-height: 1.0;
                 }

                 .final-score {
                     background-color: #e0e0e0;
                     font-weight: bold;
                 }

                 /* Image optimization */
                 td img {
                     max-width: 100%;
                     max-height: 25px;
                     object-fit: contain;
                     display: block;
                     margin: 0 auto;
                     vertical-align: middle;
                 }

                 /* Specific table adjustments */
                 .score-table th:first-child { width: 8%; }
                 .score-table th:nth-child(2) { width: 67%; }
                 .score-table th:last-child { width: 25%; }

                 .header-info-table th { 
                     width: 33.33%; 
                     padding: 2px 1px;
                     font-size: 8px;
                 }

                 .header-info-table td {
                     padding: 2px 1px;
                     text-align: center;
                     font-size: 8px;
                 }

                 .reference-table th:nth-child(1) { width: 15%; }
                 .reference-table th:nth-child(2) { width: 12%; }
                 .reference-table th:nth-child(3) { width: 23%; }
                 .reference-table th:nth-child(4) { width: 50%; }

                 .reference-table td {
                     font-size: 7px;
                     padding: 1px;
                     line-height: 1.0;
                 }

                 /* Compact spacing */
                 .image-row td:first-child {
                     text-align: center;
                     vertical-align: middle;
                     font-size: 8px;
                 }

                 .image-row td:nth-child(2) {
                     height: 25px;
                     padding: 1px;
                 }

                 .image-row td:last-child {
                     text-align: center;
                     vertical-align: middle;
                     font-size: 8px;
                 }

                 .label-row td {
                     text-align: center;
                     font-size: 7px;
                     padding: 1px;
                     height: 12px;
                 }

                 /* Footer notes */
                 .footer-notes {
                     border: 0;
                     margin-top: 15px;
                 }

                 .footer-notes td:first-child {
                     width: 2%;
                     text-align: right;
                     vertical-align: top;
                     font-size: 7px;
                     border: 0;
                     padding-right: 3px;
                 }

                 .footer-notes td:last-child {
                     width: 98%;
                     text-align: left;
                     font-size: 7px;
                     border: 0;
                     line-height: 1.0;
                 }

                 /* Conclusion table specific */
                 .conclusion-table td:first-child {
                     width: 35%;
                     text-align: center;
                     font-weight: bold;
                     vertical-align: middle;
                     height: 35px;
                     font-size: 8px;
                 }

                 .conclusion-table td:last-child {
                     width: 65%;
                     text-align: justify;
                     vertical-align: top;
                     font-size: 8px;
                     line-height: 1.1;
                     padding: 3px;
                 }

                 /* Compact margin adjustments */
                 .compact-table {
                     margin-bottom: 2px;
                 }

                 /* Text alignment helpers */
                 .text-left { text-align: left !important; padding-left: 3px; }
                 .text-center { text-align: center !important; }
                 .text-justify { text-align: justify !important; }

                 /* Clear floats helper */
                 .clearfix::after {
                     content: "";
                     display: table;
                     clear: both;
                 }
             ';
            default:
                return '';
        }
    }
}
