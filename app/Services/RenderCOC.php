<?php

namespace App\Services;

use App\Models\PersiapanSampelHeader;
use App\Models\JobTask;
use App\Models\QrDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Mpdf;

class RenderCOC
{
    public function renameNoDocument($no_document){
        $parts = explode('/', $no_document);

        if (count($parts) >= 2) {
            $parts[1] = 'COC';
        }

        $result = implode('/', $parts);

        return $result;
    }
    
    private function getWaktuPengambilan($od)
    {
        if (!$od) {
            return '-';
        }

        if (is_iterable($od) && !($od instanceof \Illuminate\Database\Eloquent\Model)) {
            foreach ($od as $item) {
                $waktu = $this->getWaktuPengambilan($item);
                if ($waktu !== '-') {
                    return $waktu;
                }
            }
            return '-';
        }

        if ($od->dataLapanganAir && !empty($od->dataLapanganAir->jam_pengambilan)) {
            return $od->dataLapanganAir->jam_pengambilan;
        }
        if ($od->dataLapanganEmisiCerobong && !empty($od->dataLapanganEmisiCerobong->waktu_pengambilan)) {
            return $od->dataLapanganEmisiCerobong->waktu_pengambilan;
        }
        if ($od->dataLapanganCahaya && !empty($od->dataLapanganCahaya->waktu_pengambilan)) {
            return $od->dataLapanganCahaya->waktu_pengambilan;
        }
        if ($od->dataLapanganDebuPersonal && !empty($od->dataLapanganDebuPersonal->jam_pengambilan)) {
            return $od->dataLapanganDebuPersonal->jam_pengambilan;
        }
        if ($od->dataLapanganKebisingan && !empty($od->dataLapanganKebisingan->waktu)) {
            return $od->dataLapanganKebisingan->waktu;
        }
        if ($od->dataLapanganDirectLain && !empty($od->dataLapanganDirectLain->waktu)) {
            return $od->dataLapanganDirectLain->waktu;
        }
        if ($od->dataLapanganSwab && !empty($od->dataLapanganSwab->waktu_pengukuran)) {
            return $od->dataLapanganSwab->waktu_pengukuran;
        }
        if ($od->dataLapanganIklimPanas && !empty($od->dataLapanganIklimPanas->jam_awal_pengukuran)) {
            return $od->dataLapanganIklimPanas->jam_awal_pengukuran;
        }
        if ($od->dataLapanganLingkunganHidup && !empty($od->dataLapanganLingkunganHidup->waktu)) {
            return $od->dataLapanganLingkunganHidup->waktu;
        }
        if ($od->dataLapanganLingkunganKerja && !empty($od->dataLapanganLingkunganKerja->waktu)) {
            return $od->dataLapanganLingkunganKerja->waktu;
        }

        return '-';
    }
    
    public function renderPdf($id)
    {
        DB::beginTransaction();
        try {
            $update = PersiapanSampelHeader::where('id', $id)->first();
            $filename = self::renderHeader($id);
            if ($update && $filename) {
                $update->filename = $filename;
                $update->save();

                JobTask::insert([
                    'job' => 'RenderPdfCOC',
                    'status' => 'success',
                    'no_document' => $update->no_document,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            JobTask::insert([
                'job' => 'RenderPdfCOC',
                'status' => 'failed',
                'no_document' => $update->no_document ?? null,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    public function renderHeader($id, $outputMode = 'F')
    {
        try {
            $dataHeader = PersiapanSampelHeader::with([
                'psDetail',
                'limsOrderHeader'
            ])
            ->where('id', $id)
            ->first();

            if (!$dataHeader) {
                return response()->json(['message' => "Persiapan Sampel tidak ditemukan"], 401);
            }

            $qr_img = '';
            $qr = QrDocument::where('id_document', $dataHeader->id)
                ->where('type_document', 'persiapan_sampel')
                ->whereJsonContains('data->no_document', $dataHeader->no_document)
                ->first();

            if ($qr) {
                $qr_data = json_decode($qr->data, true);
                if (isset($qr_data['no_document']) && $qr_data['no_document'] == $dataHeader->no_document) {
                    $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                }
            }

            // Data Field Extraction
            $noOrder = $dataHeader->no_order ?? '';
            $noDocument = self::renameNoDocument($dataHeader->no_document) ?? '';

            $namaPerusahaan = $dataHeader->limsOrderHeader->nama_perusahaan ?? $dataHeader->nama_perusahaan ?? '';
            $alamatSampling = $dataHeader->limsOrderHeader->alamat_sampling ?? $dataHeader->alamat_sampling ?? '';

            $tglSampling = Carbon::parse($dataHeader->tanggal_sampling)->locale('id');
            $hariTanggal = $tglSampling->translatedFormat('l / d F Y');

            $picName = $dataHeader->limsOrderHeader->nama_pic_sampling ?? '';
            $picJabatan = $dataHeader->limsOrderHeader->jabatan_pic_sampling ?? '';
            $picPhone = $dataHeader->limsOrderHeader->no_tlp_pic_sampling ?? '';

            $picInfoStr = '';
            if ($picName) {
                $picInfoStr = 'PIC : ' . $picName;
                if ($picJabatan) {
                    $picInfoStr .= ' (' . $picJabatan . ')';
                }
                if ($picPhone) {
                    $picInfoStr .= ' - ' . $picPhone;
                }
            }

            // nama sampler
            $samplers = array_filter(explode(',', $dataHeader->sampler_jadwal ?? ''));
            $petugasListHtml = '';
            if (!empty($samplers)) {
                $i = 1;
                foreach ($samplers as $samplerName) {
                    $petugasListHtml .= $i . '. ' . trim($samplerName) . '<br>';
                    $i++;
                }
            } else {
                $petugasListHtml = '-';
            }

            // Fetch OrderDetails for Table Rows
            $orderDetails = \App\Models\Lims\OrderDetail::where('no_order', $dataHeader->no_order)
                ->where('tanggal_sampling', $dataHeader->tanggal_sampling)
                ->where('is_active', true)
                ->get();

            if ($orderDetails->isEmpty()) {
                $orderDetails = \App\Models\Lims\OrderDetail::where('no_order', $dataHeader->no_order)
                    ->where('is_active', true)
                    ->get();
            }

            if ($orderDetails->isEmpty()) {
                $dummyOd = new \App\Models\Lims\OrderDetail();
                $dummyOd->no_sampel = $noOrder . '/001';
                $dummyOd->kategori_2 = 'Air';
                $orderDetails = collect([$dummyOd]);
            }

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 10,
                'margin_header' => 5,
                'margin_bottom' => 10,
                'margin_footer' => 5,
                'margin_left' => 10,
                'margin_right' => 10,
                'orientation' => 'P'
            ]);                   

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'sans-serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'sans-serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'sans-serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            $pdf->setFooter($footer);

            $samplers = array_filter(explode(',', $dataHeader->sampler_jadwal ?? ''));
            $allSamplersHtml = '-';
            $allSamplersInline = '-';
            if (!empty($samplers)) {
                $cleanSamplers = array_map(function ($name) {
                    return htmlspecialchars(trim($name));
                }, $samplers);
                $allSamplersHtml = implode('<br>', $cleanSamplers);
                $allSamplersInline = implode(', ', $cleanSamplers);
            } else if (!empty($dataHeader->sampler_jadwal)) {
                $allSamplersHtml = htmlspecialchars(trim($dataHeader->sampler_jadwal));
                $allSamplersInline = htmlspecialchars(trim($dataHeader->sampler_jadwal));
            }

            $tglFormattedOnly = $tglSampling->translatedFormat('d F Y');

            // Build summary table rows for "Tabel Keterangan Pengujian" across all order details
            $tableRowsHtml = '';
            $no = 1;
            if ($orderDetails->isNotEmpty()) {
                $groupedCategories = [];
                foreach ($orderDetails as $odItem) {
                    $katRaw = $odItem->kategori_3 ?: $odItem->kategori_2 ?: $odItem->kategori_1 ?: '';
                    $katClean = preg_replace('/^\d+-/', '', trim($katRaw));

                    if (!isset($groupedCategories[$katClean])) {
                        $groupedCategories[$katClean] = [
                            'kategori' => $katClean,
                            'jumlah_titik' => 0
                        ];
                    }
                    $groupedCategories[$katClean]['jumlah_titik']++;
                }

                foreach ($groupedCategories as $item) {
                    $tableRowsHtml .= '<tr>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 9px;">' . $no . '</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 4px; font-weight: bold; font-size: 9px;">' . htmlspecialchars($item['kategori']) . '</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 9px;">' . $item['jumlah_titik'] . '</td>';
                    $tableRowsHtml .= '</tr>';
                    $no++;
                }
            } else {
                for ($i = 1; $i <= 5; $i++) {
                    $tableRowsHtml .= '<tr>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 9px;">' . $i . '</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 4px; font-size: 9px;">&nbsp;</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 9px;">&nbsp;</td>';
                    $tableRowsHtml .= '</tr>';
                }
            }

            // Top Common Header HTML (No Order, Document, Company, Samplers)
            $commonHeaderHtml = '
            <table width="100%">
                <tr>
                    <td width="60%"></td>
                    <td>
                        <table class="table table-bordered" width="100%">
                            <tr>
                                <td width="50%" style="text-align: center; font-size: 11px;"><b>No Order</b></td>
                                <td style="text-align: center; font-size: 11px;"><b>' . htmlspecialchars($noOrder) . '</b></td>
                            </tr>
                            <tr>
                                <td style="text-align: center; font-size: 10px;" colspan="2"><b>Sampling</b></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table width="100%" style="margin-top: 3px;">
                <tr>
                    <td class="text-left text-wrap" style="width: 55%;"></td>
                    <td style="text-align:center">
                        <p style="font-size:12px; margin:0;"><b><u>CHAIN OF CUSTODY</u></b></p>
                        <p style="font-size:9px; text-align:center; margin-top: 1px;" id="no_document">' . htmlspecialchars($noDocument) . '</p>
                    </td>
                </tr>
            </table>

            <table style="font-size:10px; font-weight:700; width:100%; margin-top:6px;">
                <tr>
                    <td colspan="2" style="font-size: 10px; font-weight: bold; padding-bottom: 3px;">' . htmlspecialchars($namaPerusahaan) . '</td>
                </tr>
                <tr>
                    <td width="65%" style="vertical-align: top;">
                        <p style="font-size:9px; margin: 0; line-height: 1.3;">
                            <u>Informasi Sampling :</u><br>
                            <span id="tgl_sampling">' . htmlspecialchars($hariTanggal) . '</span><br>
                            <span id="alamat_sampling" style="white-space:pre-wrap; word-wrap:break-word; width:50%;">' . htmlspecialchars($alamatSampling) . '</span><br>
                            <span id="pic_order">' . htmlspecialchars($picInfoStr) . '</span>
                        </p>
                    </td>
                    <td style="vertical-align:top; font-size:9px;">
                        <u>Petugas Sampling :</u>
                        <div id="petugas_sampling" style="line-height: 1.3;">' . $petugasListHtml . '</div>
                    </td>
                </tr>
            </table>';

            // PAGE 1: Sample 1 + Tabel Keterangan Pengujian
            $sample1 = $orderDetails->first();
            $blockPage1 = $this->renderSampleBlock($sample1, $tglFormattedOnly, $allSamplersHtml, $allSamplersInline);

            $summaryTableHtml = '
            <table class="main-table">
                <thead>
                    <tr>
                        <th width="6%">NO</th>
                        <th width="74%">KETERANGAN PENGUJIAN</th>
                        <th width="20%">TITIK</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $tableRowsHtml . '
                </tbody>
            </table>';

            $docFooterHtml = '
            <div style="font-size: 8.5px; font-style: italic; color: #333; margin-top: 15px; margin-bottom: 20px;">
                ISL/DP/7.3.18 Form Rekaman Pengamanan Contoh Uji
            </div>';

            $remaining = $orderDetails->slice(1)->values();

            $htmlPage1 = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 10.5px; }
                    .table { border-collapse: collapse; width: 100%; }
                    .table-bordered { border: 1px solid #000; }
                    .table-bordered td, .table-bordered th { border: 1px solid #000; padding: 3px 5px; }
                    .text-center { text-align: center; }
                    .text-left { text-align: left; }
                    .main-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 8px; }
                    .main-table th { border: 1px solid #000; padding: 4px 6px; font-size: 10.5px; font-weight: bold; text-align: center; }
                    .main-table td { border: 1px solid #000; padding: 4px 6px; font-size: 10.5px; }
                </style>
            </head>
            <body>
                ' . $commonHeaderHtml . '
                ' . $summaryTableHtml . '
                <div style="text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 8px; margin-top: 15px;">
                    Chain Of Custody
                </div>
                ' . $blockPage1 . '
                ' . ($remaining->isEmpty() ? $docFooterHtml : '') . '
            </body>
            </html>';

            $pdf->WriteHTML($htmlPage1);

            // PAGE 2+: 2 Samples Per Page
            if ($remaining->isNotEmpty()) {
                $chunks = $remaining->chunk(2);
                $totalChunks = count($chunks);
                $chunkIndex = 0;
                foreach ($chunks as $chunk) {
                    $pdf->AddPage();
                    $blocksHtml = '';
                    foreach ($chunk as $cItem) {
                        $blocksHtml .= $this->renderSampleBlock($cItem, $tglFormattedOnly, $allSamplersHtml, $allSamplersInline);
                    }

                    $isLastChunk = ($chunkIndex === $totalChunks - 1);
                    $chunkFooter = $isLastChunk ? $docFooterHtml : '';

                    $htmlChunkPage = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 10.5px; }
                            .table { border-collapse: collapse; width: 100%; }
                            .table-bordered { border: 1px solid #000; }
                            .table-bordered td, .table-bordered th { border: 1px solid #000; padding: 3px 5px; }
                            .text-center { text-align: center; }
                            .text-left { text-align: left; }
                            .main-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 8px; }
                            .main-table th { border: 1px solid #000; padding: 4px 6px; font-size: 10.5px; font-weight: bold; text-align: center; }
                            .main-table td { border: 1px solid #000; padding: 4px 6px; font-size: 10.5px; }
                        </style>
                    </head>
                    <body>
                        ' . $blocksHtml . '
                        ' . $chunkFooter . '
                    </body>
                    </html>';

                    $pdf->WriteHTML($htmlChunkPage);
                    $chunkIndex++;
                }
            }

            $fileName = str_replace("/", "_", $dataHeader->no_document) . '.pdf';
            if ($outputMode === 'S') {
                return $pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
            }
            $filePath = public_path('persiapan_sampel/' . $fileName);
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);

            return $fileName;
        } catch (\Exception $ex) {
            Log::info(["Error message" => $ex->getMessage(), "Error line" => $ex->getLine(), "Error file" => $ex->getFile()]);
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    private function renderSampleBlock($od, $tglFormattedOnly, $allSamplersHtml, $allSamplersInline)
    {
        $noSampel = $od->no_sampel ?? '-';
        $katRaw = $od->kategori_3 ?: $od->kategori_2 ?: $od->kategori_1 ?: 'Air';
        $kategoriClean = preg_replace('/^\d+-/', '', trim($katRaw));

        $waktuPengambilan = $this->getWaktuPengambilan($od);
        $jamDiterima = '-';
        if ($waktuPengambilan !== '-' && !empty($waktuPengambilan)) {
            try {
                $jamDiterima = \Carbon\Carbon::parse($waktuPengambilan)->addHours(8)->format('H:i');
            } catch (\Exception $e) {
                $jamDiterima = '-';
            }
        }

        $thKondisi = '';
        $tdKondisi = '';

        
        $isAir = (stripos($katRaw, 'Air') !== false);
        $isUdara = (stripos($katRaw, 'Udara') !== false);
        $isEmisi = (stripos($katRaw, 'Emisi') !== false);

        $titleInformasiKondisiSampel = '<div style="font-size: 10.5px; font-weight: bold; margin-bottom: 3px;">B. Informasi Kondisi Sampel</div>';
        
        if ($isAir) {
            $botolInfo = '-';
            if (!empty($od->persiapan)) {
                $persiapanArr = is_string($od->persiapan) ? json_decode($od->persiapan, true) : $od->persiapan;
                if (is_array($persiapanArr) && count($persiapanArr) > 0) {
                    $totalBotol = 0;
                    $totalVol = 0;
                    foreach ($persiapanArr as $p) {
                        if (is_array($p)) {
                            $totalBotol += isset($p['disiapkan']) ? intval($p['disiapkan']) : 0;
                            $totalVol += isset($p['volume']) ? floatval($p['volume']) : 0;
                        }
                    }
                    if ($totalBotol > 0) {
                        if ($totalVol > 0) {
                            $botolInfo = $totalBotol . ' - ' . $totalVol . ' mL';
                        } else {
                            $botolInfo = $totalBotol . ' Botol';
                        }
                    }
                }
            }

            $perlakuanBotolHtml = $this->getPerlakuanBotol($od);

            $thKondisi = '
                <tr>
                    <th width="25%" rowspan="2" style="vertical-align: middle;">Jumlah botol - Volume</th>
                    <th width="35%" rowspan="2" style="vertical-align: middle;">Perlakuan tiap Botol</th>
                    <th colspan="4">Kondisi Wadah Sampel</th>
                </tr>
                <tr>
                    <th width="10%">Tersegel</th>
                    <th width="10%">Tidak Ada Kebocoran</th>
                    <th width="10%">Label Coding</th>
                    <th width="10%">Fisik Botol</th>
                </tr>';

            $tdKondisi = '
                <tr>
                    <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfo) . '</td>
                    <td style="vertical-align: top; padding: 4px; font-size: 9px; line-height: 1.35;">
                        ' . $perlakuanBotolHtml . '
                    </td>
                    <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                    <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                </tr>';
        } else if ($isUdara){
            $persiapanArr = is_string($od->persiapan) ? json_decode($od->persiapan, true) : ($od->persiapan ?? []);
            if (!is_array($persiapanArr)) {
                $persiapanArr = [];
            }

            $parameters = [];
            foreach ($persiapanArr as $p) {
                if (!is_array($p)) continue;
                $pName = $p['parameter'] ?? $p['param'] ?? $p['type_botol'] ?? '';
                if (str_contains($pName, ';')) {
                    $pName = explode(';', $pName, 2)[1] ?? $pName;
                }
                $pName = trim($pName);
                if ($pName !== '' && !in_array($pName, $parameters, true)) {
                    $parameters[] = $pName;
                }
            }

            if (empty($parameters)) {
                $rawParams = is_string($od->parameter) ? json_decode($od->parameter, true) : (is_array($od->parameter) ? $od->parameter : []);
                $parameters = array_map(function ($item) {
                    if (is_array($item)) {
                        $item = $item['parameter'] ?? $item['name'] ?? '';
                    }
                    return str_contains($item, ';') ? (explode(';', $item, 2)[1] ?? $item) : $item;
                }, is_array($rawParams) ? $rawParams : []);
            }

            $penjeratTargets = ["NO2", "NH3", "H2S", "SO2", "O3"];
            $plastikTargets = ["TSP", "PM10", "PM 10", "PM 2.5", "PM2.5"];

            $penjeratParameters = array_values(array_filter($parameters, function ($item) use ($penjeratTargets) {
                $base = trim(preg_replace('/\s*\(.*?\)/', '', $item));
                return in_array($base, $penjeratTargets, true);
            }));

            $plastikParameters = array_values(array_filter($parameters, function ($item) use ($plastikTargets) {
                $base = trim(preg_replace('/\s*\(.*?\)/', '', $item));
                return in_array($base, $plastikTargets, true);
            }));

            $getBotolInfoByParams = function(array $targetParams) use ($persiapanArr) {
                $totalDisiapkan = 0;
                $totalVol = 0;
                $targetBases = array_map(function($t) {
                    return trim(preg_replace('/\s*\(.*?\)/', '', $t));
                }, $targetParams);

                foreach ($persiapanArr as $p) {
                    if (!is_array($p)) continue;
                    $pName = $p['parameter'] ?? $p['param'] ?? $p['type_botol'] ?? '';
                    if (str_contains($pName, ';')) {
                        $pName = explode(';', $pName, 2)[1] ?? $pName;
                    }
                    $pName = trim($pName);
                    $pBase = trim(preg_replace('/\s*\(.*?\)/', '', $pName));

                    if (in_array($pName, $targetParams, true) || in_array($pBase, $targetBases, true)) {
                        $totalDisiapkan += isset($p['disiapkan']) ? intval($p['disiapkan']) : 0;
                        $totalVol += isset($p['volume']) ? floatval($p['volume']) : 0;
                    }
                }

                if ($totalDisiapkan > 0) {
                    return $totalVol > 0 ? ($totalDisiapkan . ' - ' . $totalVol . ' mL') : ($totalDisiapkan . ' Botol');
                }

                if (count($targetParams) > 0) {
                    return count($targetParams) . ' Botol';
                }

                return '-';
            };

            $botolInfoPenjerat = $getBotolInfoByParams($penjeratParameters);
            $botolInfoPlastik = $getBotolInfoByParams($plastikParameters);

            $thKondisi = '
                <tr>
                    <th width="30%" rowspan="2" style="vertical-align: middle;">Parameter</th>
                    <th width="30%" rowspan="2" style="vertical-align: middle;">Jumlah botol - Volume</th>
                    <th width="30%" rowspan="2" style="vertical-align: middle;">Tipe Botol</th>
                    <th colspan="4">Kondisi Wadah Sampel</th>
                </tr>
                <tr>
                    <th width="10%">Tersegel</th>
                    <th width="10%">Tidak Ada Kebocoran</th>
                    <th width="10%">Label Coding</th>
                    <th width="10%">Fisik Botol</th>
                </tr>';

            $penjeratHtml = '';
            $plastikHtml = '';

            if (count($penjeratParameters) > 0) {
                $penjeratHtml .= '<tr>
                    <td style="text-align: center; vertical-align: middle;">'. implode(', ', $penjeratParameters)  .'</td>
                    <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfoPenjerat) . '</td>
                    <td style="text-align: center; vertical-align: middle;">Penjerat</td>
                    <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                    <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                </tr>';
            }

            if (count($plastikParameters) > 0) {
                $plastikHtml .= '<tr>
                    <td style="text-align: center; vertical-align: middle;">'. implode(', ', $plastikParameters)  .'</td>
                    <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfoPlastik) . '</td>
                    <td style="text-align: center; vertical-align: middle;">Plastik dan Gliserin</td>
                    <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                    <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                </tr>';
            }

            $tdKondisi = $penjeratHtml . $plastikHtml;

            if (empty($tdKondisi) && count($parameters) > 0) {
                $thKondisi = '';
                $tdKondisi = '';
                $titleInformasiKondisiSampel = '';
            }
        } else if ($isEmisi){
            $persiapanArr = is_string($od->persiapan) ? json_decode($od->persiapan, true) : ($od->persiapan ?? []);
            if (!is_array($persiapanArr)) {
                $persiapanArr = [];
            }

            $parameters = [];
            foreach ($persiapanArr as $p) {
                if (!is_array($p)) continue;
                $pName = $p['parameter'] ?? $p['param'] ?? $p['type_botol'] ?? '';
                if (str_contains($pName, ';')) {
                    $pName = explode(';', $pName, 2)[1] ?? $pName;
                }
                $pName = trim($pName);
                if ($pName !== '' && !in_array($pName, $parameters, true)) {
                    $parameters[] = $pName;
                }
            }

            if (empty($parameters)) {
                $rawParams = is_string($od->parameter) ? json_decode($od->parameter, true) : (is_array($od->parameter) ? $od->parameter : []);
                $parameters = array_map(function ($item) {
                    if (is_array($item)) {
                        $item = $item['parameter'] ?? $item['name'] ?? '';
                    }
                    return str_contains($item, ';') ? (explode(';', $item, 2)[1] ?? $item) : $item;
                }, is_array($rawParams) ? $rawParams : []);
            }

            $penjeratTargets = ["NH3", "H2S", "CL2", "HF"];
            $cawanPetriTargets = ["As", "Cd", "Co", "Cr", "Cu", "Hg", "Mn", "Pb", "Sb", "Se", "Tl", "Zn", "Sn", "Nikel (Iso)"];

            $penjeratParameters = array_values(array_filter($parameters, function ($item) use ($penjeratTargets) {
                $base = trim(preg_replace('/\s*\(.*?\)/', '', $item));
                return in_array($base, $penjeratTargets, true);
            }));

            $cawanPetriParameters = array_values(array_filter($parameters, function ($item) use ($cawanPetriTargets) {
                $base = trim(preg_replace('/\s*\(.*?\)/', '', $item));
                return in_array($base, $cawanPetriTargets, true) || in_array($item, $cawanPetriTargets, true);
            }));

            $asetonParameters = array_values(array_filter($parameters, function ($item) {
                return stripos($item, 'Iso-') !== false;
            }));

            $getBotolInfoByParams = function(array $targetParams) use ($persiapanArr) {
                $totalDisiapkan = 0;
                $totalVol = 0;
                $targetBases = array_map(function($t) {
                    return trim(preg_replace('/\s*\(.*?\)/', '', $t));
                }, $targetParams);

                foreach ($persiapanArr as $p) {
                    if (!is_array($p)) continue;
                    $pName = $p['parameter'] ?? $p['param'] ?? $p['type_botol'] ?? '';
                    if (str_contains($pName, ';')) {
                        $pName = explode(';', $pName, 2)[1] ?? $pName;
                    }
                    $pName = trim($pName);
                    $pBase = trim(preg_replace('/\s*\(.*?\)/', '', $pName));

                    if (in_array($pName, $targetParams, true) || in_array($pBase, $targetBases, true)) {
                        $totalDisiapkan += isset($p['disiapkan']) ? intval($p['disiapkan']) : 0;
                        $totalVol += isset($p['volume']) ? floatval($p['volume']) : 0;
                    }
                }

                if ($totalDisiapkan > 0) {
                    return $totalVol > 0 ? ($totalDisiapkan . ' - ' . $totalVol . ' mL') : ($totalDisiapkan . ' Botol');
                }

                if (count($targetParams) > 0) {
                    return count($targetParams) . ' Botol';
                }

                return '-';
            };

            $botolInfoPenjerat = $getBotolInfoByParams($penjeratParameters);
            $botolInfoCawanPetri = $getBotolInfoByParams($cawanPetriParameters);
            $botolInfoAseton = $getBotolInfoByParams($asetonParameters);

            $thKondisi = '
                <tr>
                    <th width="30%" rowspan="2" style="vertical-align: middle;">Parameter</th>
                    <th width="30%" rowspan="2" style="vertical-align: middle;">Jumlah botol - Volume</th>
                    <th width="30%" rowspan="2" style="vertical-align: middle;">Tipe Botol</th>
                    <th colspan="4">Kondisi Wadah Sampel</th>
                </tr>
                <tr>
                    <th width="10%">Tersegel</th>
                    <th width="10%">Tidak Ada Kebocoran</th>
                    <th width="10%">Label Coding</th>
                    <th width="10%">Fisik Botol</th>
                </tr>';

            $penjeratHtml = '';
            $cawanPetriHtml = '';
            $asetonHtml = '';

            if (count($penjeratParameters) > 0) {
                $penjeratHtml .= '<tr>
                    <td style="text-align: center; vertical-align: middle;">'. implode(', ', $penjeratParameters)  .'</td>
                    <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfoPenjerat) . '</td>
                    <td style="text-align: center; vertical-align: middle;">Penjerat</td>
                    <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                    <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                </tr>';
            }

            if (count($cawanPetriParameters) > 0) {
                $cawanPetriHtml .= '<tr>
                    <td style="text-align: center; vertical-align: middle;">'. implode(', ', $cawanPetriParameters)  .'</td>
                    <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfoCawanPetri) . '</td>
                    <td style="text-align: center; vertical-align: middle;">Cawan Petri</td>
                    <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                    <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                </tr>';
            }

            if (count($asetonParameters) > 0) {
                $asetonHtml .= '<tr>
                    <td style="text-align: center; vertical-align: middle;">'. implode(', ', $asetonParameters)  .'</td>
                    <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfoAseton) . '</td>
                    <td style="text-align: center; vertical-align: middle;">Botol Aseton</td>
                    <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                    <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Ada</td>
                    <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                </tr>';
            }

            $tdKondisi = $penjeratHtml . $cawanPetriHtml . $asetonHtml;

            if (empty($tdKondisi) && count($parameters) > 0) {
                $thKondisi = '';
                $tdKondisi = '';
                $titleInformasiKondisiSampel = '';
            }
        } else {
            $titleInformasiKondisiSampel = '';
        }


        return '
        <table width="100%" style="border-collapse: collapse; font-size: 10.5px; margin-bottom: 6px;">
            <tr>
                <td width="25%" style="padding: 2px 0;">No Sampel</td>
                <td width="3%" style="padding: 2px 0;">:</td>
                <td width="72%" style="padding: 2px 0; font-weight: bold;">' . htmlspecialchars($noSampel) . '</td>
            </tr>
            <tr>
                <td style="padding: 2px 0;">Tanggal Sampel</td>
                <td style="padding: 2px 0;">:</td>
                <td style="padding: 2px 0;">' . htmlspecialchars($tglFormattedOnly) . '</td>
            </tr>
            <tr>
                <td style="padding: 2px 0; vertical-align: top;">Nama Petugas Pengambil Contoh</td>
                <td style="padding: 2px 0; vertical-align: top;">:</td>
                <td style="padding: 2px 0;">' . $allSamplersHtml . '</td>
            </tr>
        </table>

        <div style="font-size: 10.5px; font-weight: bold; margin-bottom: 3px;">A. Informasi Data Sampel</div>
        <table class="main-table" style="margin-top: 0; margin-bottom: 6px;">
            <thead>
                <tr>
                    <th width="20%" rowspan="2" style="vertical-align: middle;">Waktu pengambilan</th>
                    <th width="20%" rowspan="2" style="vertical-align: middle;">Kategori Pengujian</th>
                    <th width="25%" rowspan="2" style="vertical-align: middle;">Transportasi</th>
                    <th colspan="2">Data pengambilan Contoh Uji</th>
                </tr>
                <tr>
                    <th width="17.5%">Jam Diterima Sampel</th>
                    <th width="17.5%">Durasi Waktu Transportasi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">' . htmlspecialchars($waktuPengambilan) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($kategoriClean) . '</td>
                    <td style="text-align: center;">Mobil Operasional</td>
                    <td style="text-align: center;">' . htmlspecialchars($jamDiterima) . '</td>
                    <td style="text-align: center;">480 Menit</td>
                </tr>
            </tbody>
        </table>

        '. $titleInformasiKondisiSampel .'
        <table class="main-table" style="margin-top: 0; margin-bottom: 6px;">
            <thead>
                ' . $thKondisi . '
            </thead>
            <tbody>
                ' . $tdKondisi . '
            </tbody>
        </table>

        <table width="100%" style="font-size: 10.5px; margin-bottom: 25px;">
            <tr>
                <td width="25%" style="vertical-align: top;">Petugas Pengirim</td>
                <td width="75%">: ' . $allSamplersInline . '</td>
            </tr>
            <tr>
                <td>Petugas Penerima Sampel</td>
                <td>: Nisa Alkhaira</td>
            </tr>
        </table>';
    }

    private function checkHasParameter($od, array $targetParams)
    {
        $paramRaw = $od->parameter ?? $od->parameters ?? '';
        $paramString = is_array($paramRaw) ? json_encode($paramRaw) : (string)$paramRaw;
        if (empty($paramString)) {
            return false;
        }

        foreach ($targetParams as $target) {
            $targetUpper = strtoupper($target);
            if (in_array($targetUpper, ['PH', 'DO', 'OG', 'BOD'], true)) {
                if (preg_match('/\b' . preg_quote($targetUpper, '/') . '\b/i', $paramString)) {
                    return true;
                }
            } else {
                if (stripos($paramString, $target) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getPerlakuanBotol($od)
    {
        if (!$od) {
            return '-';
        }

        $persiapanData = $od->persiapan ?? null;
        if (is_string($persiapanData)) {
            $persiapanData = json_decode($persiapanData, true);
        }
        // dd($persiapanData);

        $botolTypes = [];
        if (is_array($persiapanData)) {
            foreach ($persiapanData as $item) {
                $type = null;
                if (is_array($item)) {
                    $type = $item['type_botol'] ?? $item['tipe_botol'] ?? $item['type'] ?? $item['tipe'] ?? null;
                } elseif (is_string($item)) {
                    $type = $item;
                }
                if ($type !== null && trim($type) !== '') {
                    $typeClean = trim($type);
                    if (!in_array($typeClean, $botolTypes, true)) {
                        $botolTypes[] = $typeClean;
                    }
                }
            }
        }

        if (empty($botolTypes)) {
            return '-';
        }

        $hasImmediateParam = $this->checkHasParameter($od, ['pH', 'DO', 'Suhu', 'Salinitas', 'CO2', 'Residual Klorin', 'Bau']);
        $hasOgParam        = $this->checkHasParameter($od, ['OG', 'O&G', 'O & G', 'Oil & Grease', 'Minyak & Lemak', 'Minyak dan Lemak']);
        $hasBodParam       = $this->checkHasParameter($od, ['BOD', 'BOD5', 'B.O.D.']);

        $results = [];
        foreach ($botolTypes as $type) {
            $typeUpper = strtoupper($type);

            // 1. Data Keterangan
            if (str_contains($typeUpper, 'H2SO4')) {
                $keterangan = 'Tambahkan H2SO4 sampai pH < 2 ; Dinginkan pada suhu ≤ 6°C';
            } elseif (str_contains($typeUpper, 'HNO3')) {
                $keterangan = 'Tambahkan HNO3 sampai pH < 2 ; Dinginkan pada suhu ≤ 6°C';
            } elseif (str_contains($typeUpper, 'M100')) {
                $keterangan = 'Dinginkan pada suhu < 10°C';
            } elseif (str_contains($typeUpper, 'ORI')) {
                if ($hasImmediateParam) {
                    $keterangan = 'Analisis Segera';
                } else {
                    $keterangan = 'Dinginkan pada suhu ≤ 6°C';
                }
            } else {
                if ($hasImmediateParam) {
                    $keterangan = 'Analisis Segera';
                } else {
                    $keterangan = 'Dinginkan pada suhu ≤ 6°C';
                }
            }

            // 2. Data Alias
            if (str_contains($typeUpper, 'HNO3')) {
                $alias = 'P';
            } elseif (str_contains($typeUpper, 'H2SO4')) {
                if ($hasOgParam) {
                    $alias = 'G, Mulut Lebar';
                } else {
                    $alias = 'P';
                }
            } elseif (str_contains($typeUpper, 'M100')) {
                $alias = 'G';
            } elseif (str_contains($typeUpper, 'ORI')) {
                if ($hasBodParam) {
                    $alias = 'Winkler';
                } else {
                    $alias = 'P';
                }
            } else {
                $alias = 'P';
            }

            $results[] = $keterangan . ' - ' . $alias;
        }

        $formatted = array_map(function ($res) {
            return '- ' . htmlspecialchars($res, ENT_QUOTES, 'UTF-8');
        }, $results);

        return implode('<br>', $formatted);
    }
}