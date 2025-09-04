<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan ROSA</title>
    <style>
        @page {
            margin-top: 10mm;
            margin-bottom: 10mm;
            margin-left: 10mm;
            margin-right: 10mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        /* Layout Container */
        .main-container {
            width: 100%;
            display: flex;
            gap: 10px;
            min-height: 100vh;
        }

        .left-section {
            flex: 0 0 60%;
            width: 60%;
        }

        .right-section {
            flex: 0 0 35%;
            width: 35%;
        }

        /* Header */
        .main-header {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            text-decoration: underline;
            margin-bottom: 8px;
            padding: 5px 0;
            clear: both;
        }

        /* Table Styling */
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 8pt;
        }
        
        .skor-table {
            width: 100%;
            margin-bottom: 3px;
            font-size: 6pt;
        }
        
        .skor-table td, .skor-table th {
            font-size: 8pt;
            padding: 2px 3px;
            border: 1px solid #000;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 5px;
            font-size: 6pt;
        }
        
        .info-table td, .info-table th {
            font-size: 8pt;
            padding: 2px 3px;
            border: 1px solid #000;
        }
        
        /* Utility Classes */
        .text-center {
            text-align: center;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-right {
            text-align: right;
        }
        
        .vertical-top {
            vertical-align: top;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .kesimpulan-cell {
            height: 120px;
            vertical-align: top;
            text-align: justify;
            padding: 3px;
        }
        
        .no-border {
            border: 0;
        }
        
        .spacer {
            height: 2px;
            margin: 2px 0;
        }

        /* Styling untuk signature section yang disesuaikan dengan landscape A4 */
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

        /* Responsive fallback for older PDF generators */
        @media print {
            .main-container {
                display: block;
            }
            
            .left-section {
                float: left;
                width: 60%;
            }
            
            .right-section {
                float: right;
                width: 40%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="main-header">
        LAPORAN HASIL PENGUJIAN XX
    </div>
    
    <div class="spacer"></div>
    
    <!-- Main Content Container -->
    <div class="main-container">
        <!-- Left Section -->
        <div class="left-section">
            <!-- Section A -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" width="5%">No</th>
                    <th rowspan="2" width="25%">Jenis Skoring</th>
                    <th colspan="4" width="70%">Skor Section A</th>
                </tr>
                <tr>
                    <td colspan="2" class="text-center">Section A</td>
                    <td class="text-center">Durasi</td>
                    <td class="text-center">Total (Section A + Durasi)</td>
                </tr>
                <tr>
                    <td class="text-center">1.</td>
                    <td>Tinggi Kursi & Lebar Kursi</td>
                    <td class="text-center">3</td>
                    <td rowspan="2" class="text-center">2</td>
                    <td rowspan="2" class="text-center">&nbsp;</td>
                    <td rowspan="2" class="text-center">5</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Sandaran Lengan & Punggung</td>
                    <td class="text-center">4</td>
                </tr>
            </table>

            <!-- Section B -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" width="5%">No</th>
                    <th rowspan="2" width="25%">Jenis Skoring</th>
                    <th colspan="4" width="70%">Skor Section B</th>
                </tr>
                <tr>
                    <td class="text-center">Section B</td>
                    <td class="text-center">Durasi</td>
                    <td class="text-center">Total (Section B + Durasi)</td>
                    <td class="text-center">Skor Section B</td>
                </tr>
                <tr>
                    <td class="text-center">1.</td>
                    <td>Monitor</td>
                    <td class="text-center">2</td>
                    <td class="text-center">1</td>
                    <td class="text-center">3</td>
                    <td rowspan="2" class="text-center">4</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Telepon</td>
                    <td class="text-center">1</td>
                    <td class="text-center">2</td>
                    <td class="text-center">3</td>
                </tr>
            </table>

            <!-- Section C -->
            <table class="skor-table">
                <tr>
                    <th rowspan="2" width="5%">No</th>
                    <th rowspan="2" width="25%">Jenis Skoring</th>
                    <th colspan="4" width="70%">Skor Section C</th>
                </tr>
                <tr>
                    <td class="text-center">Section C</td>
                    <td class="text-center">Durasi</td>
                    <td class="text-center">Total (Section C + Durasi)</td>
                    <td class="text-center">Skor Section C</td>
                </tr>
                <tr>
                    <td class="text-center">1.</td>
                    <td>Mouse</td>
                    <td class="text-center">2</td>
                    <td class="text-center">1</td>
                    <td class="text-center">3</td>
                    <td rowspan="2" class="text-center">3</td>
                </tr>
                <tr>
                    <td class="text-center">2.</td>
                    <td>Keyboard</td>
                    <td class="text-center">1</td>
                    <td class="text-center">1</td>
                    <td class="text-center">2</td>
                </tr>
            </table>

            <!-- Final Score -->
            <table class="skor-table">
                <tr>
                    <td width="33%" class="text-center font-bold">Skor Section A (Section B & Section C)</td>
                    <td width="5%" rowspan="2" class="no-border">&nbsp;</td>
                    <td width="27%" class="text-center font-bold">Skor ROSA</td>
                    <td width="5%" rowspan="2" class="no-border text-center" style="font-size: 14pt;">&rarr;</td>
                    <td width="30%" rowspan="2" class="text-center">4</td>
                </tr>
                <tr>
                    <td class="text-center">5</td>
                    <td class="text-center font-bold">Skoring Section A & Section D</td>
                </tr>
            </table>

            <!-- Risk Categories -->
            <table class="skor-table">
                <tr>
                    <td width="27%" class="text-center">Skor Akhir</td>
                    <td class="text-center">Kategori Risiko</td>
                    <td width="51%" class="text-center">Tindakan</td>
                </tr>
                <tr>
                    <td class="text-center">1 - 2</td>
                    <td class="text-center">Rendah</td>
                    <td class="text-left">Mungkin perlu dilakukan tindakan</td>
                </tr>
                <tr>
                    <td class="text-center">3 - 5</td>
                    <td class="text-center">Sedang</td>
                    <td class="text-left">Diperlukan tindakan karena rawan terkena cedera</td>
                </tr>
                <tr>
                    <td class="text-center">>5</td>
                    <td class="text-center">Tinggi</td>
                    <td class="text-left">Diperlukan tindakan secara ergonomis sesegera mungkin</td>
                </tr>
            </table>

            <!-- Kesimpulan -->
            <table class="skor-table">
                <tr>
                    <td class="kesimpulan-cell">
                        <strong>Kesimpulan:</strong><br><br>
                        Berdasarkan penilaian ROSA, skor akhir adalah 4 yang termasuk dalam kategori risiko sedang. Diperlukan tindakan perbaikan ergonomis untuk mengurangi risiko cedera muskuloskeletal pada pekerja.
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Right Section -->
        <div class="right-section">
            <!-- LHP Info -->
            <table class="info-table">
                <tr>
                    <th width="30%" class="text-center">No. LHP</th>
                    <th width="30%" class="text-center">No. Sampel</th>
                    <th width="40%" class="text-center">Jenis Sampel</th>
                </tr>
                <tr>
                    <td class="text-center">LHP/001/2025</td>
                    <td class="text-center">S001</td>
                    <td class="text-center">Ergonomi</td>
                </tr>
            </table>

            <div class="spacer"></div>

            <!-- Customer Info -->
            <table class="info-table">
                <tr>
                    <th colspan="3" class="text-left font-bold"><u>Informasi Pelanggan</u></th>
                </tr>
                <tr>
                    <td width="35%" class="text-left">Nama Pelanggan</td>
                    <td width="5%" class="text-center">:</td>
                    <td width="60%" class="text-left">PT. Contoh Perusahaan</td>
                </tr>
                <tr>
                    <td class="text-left vertical-top">Alamat / Lokasi Sampling</td>
                    <td class="text-center vertical-top">:</td>
                    <td class="text-left vertical-top">Jakarta Selatan, DKI Jakarta</td>
                </tr>
            </table>

            <div class="spacer"></div>

            <!-- Sampling Info -->
            <table class="info-table">
                <tr>
                    <th colspan="3" class="text-left font-bold"><u>Informasi Sampling</u></th>
                </tr>
                <tr>
                    <td width="35%" class="text-left">Tanggal Sampling</td>
                    <td width="5%" class="text-center">:</td>
                    <td width="60%" class="text-left">01 September 2025</td>
                </tr>
                <tr>
                    <td class="text-left">Periode Analisis</td>
                    <td class="text-center">:</td>
                    <td class="text-left">01-03 September 2025</td>
                </tr>
                <tr>
                    <td class="text-left">Jenis Analisis</td>
                    <td class="text-center">:</td>
                    <td class="text-left">Pengumpulan Data (Pengukuran & Skoring)</td>
                </tr>
                <tr>
                    <td class="text-left vertical-top">Metode Analisis<sup style="font-size: 5pt;">*</sup></td>
                    <td class="text-center vertical-top">:</td>
                    <td class="text-left vertical-top">Pengamatan Langsung - ROSA (Rapid Office Restrain Assessment)</td>
                </tr>
            </table>

            <div class="spacer"></div>

            <!-- Worker Info -->
            <table class="info-table">
                <tr>
                    <th colspan="3" class="text-left font-bold"><u>Data Individu/Pekerja yang Diukur</u></th>
                </tr>
                <tr>
                    <td width="35%" class="text-left">Nama Pekerja</td>
                    <td width="5%" class="text-center">:</td>
                    <td class="text-left">John Doe</td>
                </tr>
                <tr>
                    <td class="text-left vertical-top">Jenis Pekerja</td>
                    <td class="text-center vertical-top">:</td>
                    <td class="text-left vertical-top">Operator Komputer</td>
                </tr>
            </table>

            <div class="spacer"></div>

            <!-- Notes -->
            <table class="info-table">
                <tr>
                    <td width="5%" class="text-right vertical-top"><sup style="font-size: 5pt;">*</sup></td>
                    <td width="95%" class="text-left" style="font-size: 5pt;">
                        Metode Analisis Mengacu kepada Development and Evaluation of an Office
                        Ergonomic Risk Checklist: The Rapid Office Strain Assessment (ROSA)
                        by Michael Sonne, Dino L. Villalta, and M. Andrews, 2012.
                    </td>
                </tr>
            </table>

            <!-- Signature Section -->
            <div class="signature-section">
                <table class="signature-table">
                    <tr>
                        <td class="signature-left"></td>
                        <td class="signature-right">
                            <div class="signature-date">
                                Jakarta, 04 September 2025
                            </div><br>
                            <img src="{{public_path('qr_documents/ISL_STPS_25-VIII_5054.svg')}}" width="30px" height="30px" class="signature-qr" alt="QR Code" />
                            <div class="signature-text">(Tanda Tangan Digital)</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>