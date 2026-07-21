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
            $update = PersiapanSampelHeader::has('limsOrderHeader')->where('id', $id)->first();
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

    public function renderHeader($id)
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

            $firstSamplerName = !empty($samplers) ? trim(reset($samplers)) : ($dataHeader->sampler_jadwal ?? '-');
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
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 6px; text-align: center; font-size: 11px;">' . $no . '</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 6px; font-weight: bold; font-size: 11px;">' . htmlspecialchars($item['kategori']) . '</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 6px; text-align: center; font-size: 11px;">' . $item['jumlah_titik'] . '</td>';
                    $tableRowsHtml .= '</tr>';
                    $no++;
                }
            } else {
                for ($i = 1; $i <= 5; $i++) {
                    $tableRowsHtml .= '<tr>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 6px; text-align: center; font-size: 11px;">' . $i . '</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 6px; font-size: 11px;">&nbsp;</td>';
                    $tableRowsHtml .= '<td style="border: 1px solid #000; padding: 6px; text-align: center; font-size: 11px;">&nbsp;</td>';
                    $tableRowsHtml .= '</tr>';
                }
            }

            foreach ($orderDetails as $index => $od) {
                if ($index > 0) {
                    $pdf->AddPage();
                }

                $noSampel = $od->no_sampel ?? ($noOrder . '/001');
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

                $botolInfo = '4 - 850 mL';
                if (!empty($od->persiapan)) {
                    $persiapanArr = is_string($od->persiapan) ? json_decode($od->persiapan, true) : $od->persiapan;
                    if (is_array($persiapanArr) && count($persiapanArr) > 0) {
                        $totalBotol = count($persiapanArr);
                        $totalVol = 0;
                        foreach ($persiapanArr as $p) {
                            $totalVol += isset($p['volume']) ? floatval($p['volume']) : 0;
                        }
                        if ($totalVol > 0) {
                            $botolInfo = $totalBotol . ' - ' . $totalVol . ' mL';
                        } else {
                            $botolInfo = $totalBotol . ' Botol';
                        }
                    }
                }

                $keteranganPengujianHtml = '';
                if ($index === 0) {
                    $keteranganPengujianHtml = '
                    <table width="100%">
                        <tr>
                            <td width="60%"></td>
                            <td>
                                <table class="table table-bordered" width="100%">
                                    <tr>
                                        <td width="50%" style="text-align: center; font-size: 13px;"><b>No Order</b></td>
                                        <td style="text-align: center; font-size: 13px;"><b>' . htmlspecialchars($noOrder) . '</b></td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: center; font-size: 12px;" colspan="2"><b>Sampling</b></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    <table width="100%" style="margin-top: 5px;">
                        <tr>
                            <td class="text-left text-wrap" style="width: 55%;"></td>
                            <td style="text-align:center">
                                <p style="font-size:14px; margin:0;"><b><u>CHAIN OF CUSTODY</u></b></p>
                                <p style="font-size:11px; text-align:center; margin-top: 2px;" id="no_document">' . htmlspecialchars($noDocument) . '</p>
                            </td>
                        </tr>
                    </table>

                    <table style="font-size:13px; font-weight:700; width:100%; margin-top:15px;">
                        <tr>
                            <td colspan="2" style="font-size: 13px; font-weight: bold; padding-bottom: 5px;">' . htmlspecialchars($namaPerusahaan) . '</td>
                        </tr>
                        <tr>
                            <td width="65%" style="vertical-align: top;">
                                <p style="font-size:10px; margin: 0; line-height: 1.4;">
                                    <u>Informasi Sampling :</u><br>
                                    <span id="tgl_sampling">' . htmlspecialchars($hariTanggal) . '</span><br>
                                    <span id="alamat_sampling" style="white-space:pre-wrap; word-wrap:break-word; width:50%;">' . htmlspecialchars($alamatSampling) . '</span><br>
                                    <span id="pic_order">' . htmlspecialchars($picInfoStr) . '</span>
                                </p>
                            </td>
                            <td style="vertical-align:top; font-size:10px;">
                                <u>Petugas Sampling :</u>
                                <div id="petugas_sampling" style="line-height: 1.4;">' . $petugasListHtml . '</div>
                            </td>
                        </tr>
                    </table>
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
                }

                $html = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 10px; }
                        .table { border-collapse: collapse; width: 100%; }
                        .table-bordered { border: 1px solid #000; }
                        .table-bordered td, .table-bordered th { border: 1px solid #000; padding: 4px; }
                        .text-center { text-align: center; }
                        .text-left { text-align: left; }
                        .main-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 15px; }
                        .main-table th { border: 1px solid #000; padding: 6px; font-size: 11px; font-weight: bold; text-align: center; }
                        .main-table td { border: 1px solid #000; padding: 6px; font-size: 11px; }
                    </style>
                </head>
                <body>

                    ' . $keteranganPengujianHtml . '

                    <div style="text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 15px; margin-top: 25px;">
                        Chain Of Custody
                    </div>

                    <table width="100%" style="border-collapse: collapse; font-size: 11px; margin-bottom: 15px;">
                        <tr>
                            <td width="25%" style="padding: 3px 0;">No Sampel</td>
                            <td width="3%" style="padding: 3px 0;">:</td>
                            <td width="72%" style="padding: 3px 0; font-weight: bold;">' . htmlspecialchars($noSampel) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;">Tanggal Sampel</td>
                            <td style="padding: 3px 0;">:</td>
                            <td style="padding: 3px 0;">' . htmlspecialchars($tglFormattedOnly) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0;">Nama Petugas Pengambil Contoh</td>
                            <td style="padding: 3px 0;">:</td>
                            <td style="padding: 3px 0;">' . htmlspecialchars($firstSamplerName) . '</td>
                        </tr>
                    </table>

                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 6px;">A. Informasi Data Sampel</div>
                    <table class="main-table" style="margin-top: 0; margin-bottom: 15px;">
                        <thead>
                            <tr>
                                <th width="20%">Waktu pengambilan</th>
                                <th width="20%">Kategori Pengujian</th>
                                <th width="25%">Transportasi</th>
                                <th colspan="2">Data pengambilan Contoh Uji</th>
                            </tr>
                            <tr>
                                <th style="border-top: none;"></th>
                                <th style="border-top: none;"></th>
                                <th style="border-top: none;"></th>
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

                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 6px;">B. Informasi Kondisi Sampel</div>
                    <table class="main-table" style="margin-top: 0; margin-bottom: 20px;">
                        <thead>
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
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($botolInfo) . '</td>
                                <td style="vertical-align: top; padding: 6px; font-size: 10px; line-height: 1.4;">
                                    - Pendinginan pada suhu &le; 6&deg;C - P<br>
                                    - Penambahan H2SO4 sampai pH &lt; 2 dan Pendinginan pada suhu &lt; 6&deg;C - P<br>
                                    - Dinginkan pada suhu &le; 6&deg;C - Winkler<br>
                                    - Pendinginan pada suhu &lt; 10&deg;C - G (A)
                                </td>
                                <td style="text-align: center; vertical-align: middle;">Tersegel</td>
                                <td style="text-align: center; vertical-align: middle;">Tidak Ada</td>
                                <td style="text-align: center; vertical-align: middle;">Ada</td>
                                <td style="text-align: center; vertical-align: middle;">Kondisi Baik</td>
                            </tr>
                        </tbody>
                    </table>

                    <table width="100%" style="font-size: 11px; margin-bottom: 25px;">
                        <tr>
                            <td width="25%">Petugas Pengirim</td>
                            <td width="75%">: ' . htmlspecialchars($firstSamplerName) . '</td>
                        </tr>
                        <tr>
                            <td>Petugas Penerima Sampel</td>
                            <td>: Nisa Alkhaira</td>
                        </tr>
                    </table>

                    <div style="font-size: 9px; font-style: italic; color: #333;">
                        ISL/DP/7.3.18 Form Rekaman Pengamanan Contoh Uji
                    </div>
                </body>
                </html>
                ';

                $pdf->WriteHTML($html);
            }

            $fileName = str_replace("/", "_", $dataHeader->no_document) . '.pdf';
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
}