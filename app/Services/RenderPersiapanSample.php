<?php

namespace App\Services;

use App\Models\PersiapanSampelHeader;
use App\Models\JobTask;
use App\Models\QrDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Mpdf;

class RenderPersiapanSample
{
    private $data;
    private $periode;

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
                    'job' => 'RenderPdfPersiapanSample',
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
                'job' => 'RenderPdfPersiapanSample',
                'status' => 'failed',
                'no_document' => $update->no_document,
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
                                'orderHeader'
                            ])
                            ->where('id', $id)
                            ->first();

            if (!$dataHeader)
                return response()->json(['message' => "Persiapan Sampel tidak ditemukan, silahkan isi terlebih dahulu"], 401);

            if ($dataHeader->psDetail) {
                $dataDetail = $dataHeader->psDetail;
            } else {
                $dataDetail = [];
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

            // if ($dataHeader->orderHeader->is_revisi) return response()->json(['message' => "Order dengan No. Quotation $request->no_quotation sedang dalam proses revisi"], 401);
            
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3, // 30mm not pixel
                'margin_bottom' => 3, // 30mm not pixel
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ]);

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            $pdf->setFooter($footer);

            $pdf->WriteHTML('
            <!DOCTYPE html>
                <html>
                    <head>
                        <style>
                            .custom1 { font-size: 11px; font-weight: bold; }
                            .custom2 { font-size: 18px; font-weight: bold; text-align: center; padding: 5px; }
                            .custom3 { font-size: 12px; font-weight: bold; text-align: center; padding: 5px; }
                            .custom4 { font-size: 12px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px; }
                            .custom5 { border: 1px solid #000000; }
                            .custom6 { font-size: 11px; border: 1px solid #000000; padding: 5px; }
                            .custom7 { font-size: 12px; border: 1px solid #000000; padding: 5px; }
                        </style>
                    </head>
                    <body>
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr>
        ');

            $pdf->WriteHTML('<td colspan="8" class="custom1" style="text-align: right; padding-right: 10px; font-size: 12px;">' . Carbon::parse($dataHeader->tanggal_sampling)->locale('id')->translatedFormat('d F Y') . '</td>');

            $pdf->WriteHTML('       </td>
                            </tr>
                            <tr>
                                <td width="30%" colspan="2" class="custom1">PT INTI SURYA LABORATORIUM</td>
                                <td width="40%" colspan="4" class="custom2">Persiapan Sampling <br> ' . $dataHeader->no_document . '</td>
                                <td width="10%" class="custom3 custom5">ORDER</td>
                                <td width="20%" style="font-size: 14px; font-weight: bold; border: 1px solid #000000; text-align: center;">' . $dataHeader->no_order . '</td>
                            </tr>
                            <tr><td colspan="8" style="padding: 2px;"></td></tr>
                            <tr>
                                <td colspan="2" class="custom5">
                                    <table width="100%">
                                        <tr><td style="font-size: 10px;">NO QUOTATION :</td></tr>
                                        <tr><td style="font-size: 14px; text-align: center;">' . $dataHeader->no_quotation . '</td></tr>
                                    </table>
                                </td>
                                <td colspan="6" class="custom5" style="text-align: center;"></td>
                            </tr>
                        </table>
                        <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                            <tr><td colspan="8" style="padding: 2px;"></td></tr>
                            <tr>
                                <th colspan="3" class="custom4">PERLENGKAPAN</th>
                                <th rowspan="2" class="custom4">DISIAPKAN</th>
                                <th rowspan="2" class="custom4">TAMBAHAN</th>
                                <th rowspan="2" class="custom4">TERPAKAI</th>
                                <th rowspan="2" class="custom4">SISA</th>
                                <th rowspan="2" class="custom4">KETERANGAN</th>
                            </tr>
                            <tr>
                                <th class="custom4">KATEGORI</th>
                                <th class="custom4">NO. SAMPEL</th>
                                <th class="custom4">PARAMETER</th>
                            </tr>
        ');

            $pdf->WriteHTML('<tr><td colspan="8" style="padding: 2px;"></td></tr>');

            $mergedData = [];
            // Log::info($dataDetail->toArray());            
            foreach ($dataDetail as $entry) {
                $parameters = json_decode($entry["parameters"], true);
                foreach ($parameters as $kategori => $params) {
                    if (!isset($mergedData[$kategori])) {
                        $mergedData[$kategori] = [];
                    }
                    $mergedData[$kategori][] = [
                        "no_sampel" => $entry["no_sampel"],
                        "params" => $params
                    ];
                }
            }

            // Loop pertama: Menampilkan daftar sampel dan parameternya
            foreach ($mergedData as $kategori => $entries) {
                $firstKategori = true;

                foreach ($entries as $entry) {
                    $firstRow = true;

                    foreach ($entry["params"] as $param => $values) {
                        $pdf->WriteHTML('<tr>');

                        if ($firstKategori) {
                            $rowspanKategori = array_sum(array_map('count', array_column($entries, 'params')));
                            $kategoriLabel = ($kategori == "air") ? "BOTOL AIR" : (($kategori == "udara") ? "PENJERAP<br />+<br />KERTAS SARING<br />(UDARA)" : "PENJERAP<br />+<br />KERTAS SARING<br />(EMISI)");
                            $pdf->WriteHTML('<td class="custom6" style="text-align: center; font-size: 6px !important;" rowspan="' . $rowspanKategori . '">' . $kategoriLabel . '</td>');
                            $firstKategori = false;
                        }

                        if ($firstRow) {
                            $pdf->WriteHTML('<td class="custom6" rowspan="' . count($entry["params"]) . '">' . $entry["no_sampel"] . '</td>');
                            $firstRow = false;
                        }

                        $pdf->WriteHTML('<td class="custom6">' . $param . '</td>');
                        $pdf->WriteHTML('<td class="custom6" style="text-align: center;">' . $values["disiapkan"] . '</td>');
                        $pdf->WriteHTML('<td class="custom6"></td><td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>');
                        $pdf->WriteHTML('</tr>');
                    }
                }
            }

            $pdf->WriteHTML('<tr><td colspan="8" style="padding: 2px;"></td></tr>');

            // Loop kedua: Menampilkan total tambahan berdasarkan parameter unik
            foreach ($mergedData as $kategori => $entries) {
                $uniqueParams = [];

                foreach ($entries as $entry) {
                    foreach ($entry["params"] as $param => $values) {
                        $uniqueParams[$param] = true;
                    }
                }

                if (!empty($uniqueParams)) {
                    $pdf->WriteHTML('<tr><td colspan="2" rowspan="' . count($uniqueParams) . '" class="custom6" style="text-align: center; font-size: 6px !important;">Total Tambahan<br />' . ($kategori == "air" ? "BOTOL AIR" : ($kategori == "udara" ? "PENJERAP<br />+<br />KERTAS SARING<br />(UDARA)" : "PENJERAP<br />+<br />KERTAS SARING<br />(EMISI)")) . '</td>');

                    $firstTotalRow = true;
                    foreach ($uniqueParams as $param => $_) {
                        if (!$firstTotalRow)
                            $pdf->WriteHTML('<tr>');
                        $pdf->WriteHTML('<td class="custom6">' . $param . '</td>');
                        $pdf->WriteHTML('<td class="custom6" style="text-align: center;">2</td>');
                        $pdf->WriteHTML('<td class="custom6"></td><td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>');
                        $pdf->WriteHTML('</tr>');

                        $firstTotalRow = false;
                    }
                }
            }

            $pdf->WriteHTML('
                <tr><td colspan="8" style="padding: 2px;"></td></tr>
                <tr>
                    <td colspan="3" class="custom6">PLASTIK BENTHOS</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->plastik_benthos)->disiapkan . '</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->plastik_benthos)->tambahan . '</td>
                    <td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>
                </tr>
                <tr>
                    <td colspan="3" class="custom6">MEDIA - PETRI DISH</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->media_petri_dish)->disiapkan . '</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->media_petri_dish)->tambahan . '</td>
                    <td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>
                </tr>
                <tr>
                    <td colspan="3" class="custom6">MEDIA - TABUNG</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->media_tabung)->disiapkan . '</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->media_tabung)->tambahan . '</td>
                    <td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>
                </tr>
                <tr><td colspan="8" style="padding: 2px;"></td></tr>
                <tr>
                    <td colspan="3" class="custom6">MASKER</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->masker)->disiapkan . '</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->masker)->tambahan . '</td>
                    <td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>
                </tr>
                <tr>
                    <td colspan="3" class="custom6">SARUNG TANGAN - KARET</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->sarung_tangan_karet)->disiapkan . '</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->sarung_tangan_karet)->tambahan . '</td>
                    <td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>
                </tr>
                <tr>
                    <td colspan="3" class="custom6">SARUNG TANGAN - BINTIK</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->sarung_tangan_bintik)->disiapkan . '</td>
                    <td class="custom6" style="text-align: center;">' . json_decode($dataHeader->sarung_tangan_bintik)->tambahan . '</td>
                    <td class="custom6"></td><td class="custom6"></td><td class="custom6"></td>
                </tr>
            </table>

            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; table-layout: fixed;">
                <tr><td colspan="8" style="padding: 2px;"></td></tr>
                <tr>
                    <td class="custom4" colspan="4">PARAF - AWAL (BERANGKAT)</td>
                    <td class="custom4" colspan="4">PARAF - AKHIR (PULANG)</td>
                </tr>
                <tr>
                    <td class="custom4" colspan="2">ANALIS</td>
                    <td class="custom4" colspan="2">SAMPLER</td>
                    <td class="custom4" colspan="2">ANALIS</td>
                    <td class="custom4" colspan="2">SAMPLER</td>
                </tr>
                <tr>
                    <td class="custom4" colspan="2" style="height: 60px;"></td>
                    <td class="custom4" colspan="2"></td>
                    <td class="custom4" colspan="2"></td>
                    <td class="custom4" colspan="2"></td>
                </tr>
                <tr>
                    <td class="custom7" colspan="2" style="text-align: center;">' . $dataHeader->analisis_berangkat . '</td>
                    <td class="custom7" colspan="2" style="text-align: center;">' . $dataHeader->sampler_berangkat . '</td>
                    <td class="custom7" colspan="2" style="text-align: center;">' . $dataHeader->analisis_pulang . '</td>
                    <td class="custom7" colspan="2" style="text-align: center;">' . $dataHeader->sampler_pulang . '</td>
                </tr>
            </table>
        ');

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