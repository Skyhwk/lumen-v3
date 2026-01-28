<?php

namespace App\Services;

use App\Models\{SampelDiantar,OrderDetail,SampelDiantarDetail};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\MpdfService as Mpdf;

class RenderSD
{
    public function renderHeader($id,$periode,$mode)
    {

        DB::beginTransaction();
        try {
            $filename = self::generate($id,$periode,$mode);
            // dd($filename);
            $update = SampelDiantar::where('id', $id)->first();
            if ($update) {
                $update->filename = $filename;
                $update->save();
            }
            DB::commit();
            return true;
        } catch (\Exception $th) {
            DB::rollBack();
            // return false;
            throw $th;

            // dd($th->getMessage(),$th->getLine());
        }
    }

    private function generate($id,$periode,$mode)
    {
        try {
            // dd($periode);
            //code...
            if($periode){
                $data = SampelDiantar::with(['detail'])->where('id', $id)->first();
            } else {
                $data = SampelDiantar::with('detail')->where('id', $id)->first();
            }
            
            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3,
                'margin_bottom' => 3,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            $pdf = new Mpdf($mpdfConfig);

            $pdf->charset_in = 'utf-8';
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            // $pdf->SetWatermarkText('CONFIDENTIAL');
            // $pdf->showWatermarkText = true;

            $qr_img = '';
            $qr = DB::table('qr_documents')->where(['id_document' => $id, 'type_document' => 'quotation_non_kontrak'])
                // ->whereJsonContains('data->no_document', $data->no_document)
                ->first();

            $qr_data = $qr && $qr->data ? json_decode($qr->data, true) : null;

            if ($qr && ($qr_data['no_document'] == $data->no_document))
                $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';

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
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );
            $pdf->setFooter($footer);
            $fileName = preg_replace('/\\//', '-', $data->no_document) . '.pdf';

            $getBody = self::renderBody($pdf, $data, $fileName,$periode,$mode);

            return $getBody;
        } catch (\Exception $ex) {
            throw $ex;

        }
    }

    /* protected function renderBody($pdf, $data, $fileName)
    {
        try {

            $datas = OrderDetail::where('kategori_1', 'SD')
                ->where('no_order', $data->no_order)
                ->where('is_active', true)
                ->get(['no_sampel','kategori_3']);
            $detailSampelDatang = SampelDiantarDetail::where('id_header',$data->id)
            ->where('periode',null)
            ->first();


            // logic
            $no_samples = array_filter($datas->pluck('no_sampel')->toArray(), fn($item) => !is_null($item));
            $jenis_sampels = array_filter($datas->pluck('kategori_3')->toArray(), fn($item) => !is_null($item));
            $namaJenisSampel = array_map(function ($item) {
                [, $name] = explode('-', $item, 2);
                return trim($name);
            }, $jenis_sampels);

            $internal = json_decode($detailSampelDatang->internal_data, true);
            $eksternal = json_decode($detailSampelDatang->eksternal_data, true);

            $dataGabungan = [];
            foreach ($internal as $itemInt) {
                $noSampel = $itemInt['no_sampel'];
                $itemGabungan = $itemInt;
                if($itemInt['is_active']){
                    $itemGabungan['eksternal'] = collect($eksternal)->firstWhere('no_sampel', $noSampel);
                    $dataGabungan[] = $itemGabungan;
                }
            }


            //header lembar tanda terima
            $headerLemabaran='<table class="table" width="100%" border="0" style="margin-bottom: 20px">
                                <tr class="tr_top">
                                    <td class="text-left text-wrap" style="border: none;width: 40%">
                                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL" />
                                    </td>
                                    <td style="border: none;width: 60%; text-align: center">
                                        <h3 style="text-align: center;">
                                            LEMBAR TANDA TERIMA DAN INFORMASI SAMPEL<br />DATANG
                                        </h3>
                                        <table style="width: 100%" border="0">
                                            <tr>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: right">' . $data->no_document . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: center">' . Carbon::now()->locale('id')->dayName . ' / ' . self::tanggal_indonesia(date('Y-m-d')) . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: left">' . date('H:i') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';
                $pdf->SetHTMLHeader($headerLemabaran);
                $informasiWadahSampel=null;
            if ($data->created_at !== null) {
                // $tanggalSampling = Carbon::parse($data->created_at);
                $tanggalDiterima = Carbon::parse($data->created_at);
                $now = Carbon::now();
                if ($data->suhu === 'ya' || $data->suhu === 'tidak') {
                    $informasiWadahSampel = $data->suhu === 'ya'
                        ? 'Terdapat kontrol suhu selama transportasi sampel'
                        : 'Tidak Terdapat kontrol suhu selama transportasi sampel';

                    if (!is_null($data->tercatat)) {
                        $informasiWadahSampel .= ' dengan suhu penyimpanan sebesar ' . $data->tercatat;
                    }
                } else {
                    $informasiWadahSampel = null;
                }


                $diff = $tanggalDiterima->diff($now);
                $durasi = $diff->format('%d hari, %h jam, %i menit');
            } else {
                $durasi = '';
                $informasiWadahSampel = null;
            }


            $pdf->WriteHTML('<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed;">
                                <tr>
                                    <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI PELANGGAN</p></th>
                                    <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI SAMPEL</p></th>
                                </tr>
                                <tr>
                                    <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Perusahaan / Instansi Pelanggan</strong></p></td>
                                    <td style="width: 25%; word-wrap: break-word;">' . $data->nama_perusahaan . '</td>
                                    <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Informasi Wadah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">'.$informasiWadahSampel.'</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Pengantar Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . $data->nama_pengantar_sampel . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kondisi Keamanan Wadah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;"></td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Tujuang Pengujian</strong></p></td>
                                    <td style="word-wrap: break-word;"></td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Jenis Sampel - Air</strong></p></td>
                                    <td style="word-wrap: break-word;">' . implode(', <br>', $namaJenisSampel) . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Alamat Pelanggan</strong></p></td>
                                    <td style="word-wrap: break-word;">' . $data->alamat_perusahaan . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kode Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . $data->no_quotation . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Tangal Terima</strong></p></td>
                                    <td style="word-wrap: break-word;">' . $tanggalDiterima. '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>No Order</strong></p></td>
                                    <td style="word-wrap: break-word;">' . $data->no_order . '</td>
                                </tr>
                            </table>');

            $pdf->WriteHTML('<table class="table" width="100%" style="border: none;margin-top: 20px">
                    <tr>
                        <td style="border: none;width: 30%; text-align: center;"><p><strong>Diserahkan Oleh,</strong></p></td>
                        <td style="border: none;width: 20%; text-align: center;"></td>
                        <td style="border: none;width: 20%; text-align: center;"></td>
                        <td style="border: none;width: 30%; text-align: center;"><p><strong>Diterima Oleh,</strong></p></td>
                    </tr>
                    <tr>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                    </tr>
                    <tr>
                        <td style="border: none;width: 30%;text-align: center;"><p><strong>(.......................................)</strong></p></td>
                        <td style="border: none;width: 20%;text-align: center;"></td>
                        <td style="border: none;width: 20%;text-align: center;"></td>
                        <td style="border: none;width: 30%;text-align: center;"><p><strong>PT INTI SURYA LABORATORIUM</strong></p></td>
                    </tr>
                </table>');

            $pdf->WriteHTML('<pagebreak suppress="true"/>');
            //hearder LAMPIRAN TANDA TERIMA DAN INFORMASI SAMPEL
            $headerLampirann='<table class="table" width="100%" border="0" style="margin-bottom: 20px">
                                <tr class="tr_top">
                                    <td class="text-left text-wrap" style="border: none;width: 40%">
                                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL" />
                                    </td>
                                    <td style="border: none;width: 60%; text-align: center">
                                        <h3 style="text-align: center;">
                                            LAMPIRAN TANDA TERIMA DAN INFORMASI SAMPEL
                                        </h3>
                                        <table style="width: 100%" border="0">
                                            <tr>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: right">' . $data->no_document . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: center">' . Carbon::now()->locale('id')->dayName . ' / ' . self::tanggal_indonesia(date('Y-m-d')) . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: left">' . date('H:i') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';
                    $pdf->WriteHTML('<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed;">
                    <tr>
                        <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI KEGIATAN SAMPLING</p></th>
                        <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;"></p></th>
                    </tr>
                    <tr>
                        <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Petugas Sampling Pihak Pelanggan</strong></p></td>
                        <td style="width: 25%; word-wrap: break-word;">' . $data->nama_pengantar_sampel . '</td>
                        <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Suhu Transportasi sampel</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Hari / Tanggal Sampling</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kondisi Abnormal
                        </strong></p></td>
                        <td style="word-wrap: break-word;">' . implode(", <br>", $data->kondisi_ubnormal ? json_decode($data->kondisi_ubnormal, true) : []) . '</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Acuan Metode Pengambilan Sampel</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Waktu Sampling</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Sertifikasi Tenaga Pengambil Sampel</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Teknik Sampling</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kalibrasi Alat Ukur</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong></strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                    </tr>
                </table>');

                $html = '';
                $html = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed; word-wrap: break-word;">
                <tr>
                    <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">No. Sampel</th>
                    <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 15%;">Deskripsi Sampel</th>
                    <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 8%;">Jenis Sampel</th>
                    <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">pH</th>
                    <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">DHL (uS/cm)</th>
                    <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">Suhu (°C)</th>
                    <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 8%;">Berwarna</th>
                    <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 7%;">Berbau</th>
                    <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 6%;">Keruh</th>
                </tr>
                <tr>
                    <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                    <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                    <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                    <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                    <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                    <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                </tr>';
                if (empty($dataGabungan)) {
                    $html .= '<tr><td colspan="12" class="text-center">Tidak ada data</td></tr>';
                } else {
                    // Body of the table
                    foreach ($dataGabungan as $row) {
                        $warna = isset($row['warna']) && is_array($row['warna']) ? $row['warna'] : [];
                        $eksternal = isset($row['eksternal']) && is_array($row['eksternal']) ? $row['eksternal'] : [];

                        $html .= '<tr>
                            <td class="text-center">' . ($row['no_sampel'] ?? '-') . '</td>
                            <td class="text-center">' . ($row['deskripsi'] ?? '-') . '</td>
                            <td class="text-center">' . ($row['jenis_sampel'] ?? '-') . '</td>

                            <td class="text-center">' . ($row['ph'] ?? '-') . '</td>
                            <td class="text-center">' . ($eksternal['ph'] ?? '-') . '</td>

                            <td class="text-center">' . ($row['dhl'] ?? '-') . '</td>
                            <td class="text-center">' . ($eksternal['dhl'] ?? '-') . '</td>

                            <td class="text-center">' . (
                                array_key_exists('suhu', $row) && $row['suhu'] !== null && $row['suhu'] !== ''
                                    ? $row['suhu']
                                    : '-'
                            ) . '</td>
                            <td class="text-center">' . (isset($eksternal['suhu']) && $eksternal['suhu'] !== null ? $eksternal['suhu'] : '-') . '</td>';

                        $warnaStatus = isset($warna['warna_status']) ? strtolower($warna['warna_status']) : '';
                        if ($warnaStatus === 'ya') {
                            $html .= '<td class="text-center">' . ($warna['warna'] ?? '-') . '</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>';
                        } else {
                            $html .= '<td class="text-center">-</td>
                                <td class="text-center">' . ($warna['bau'] ?? '-') . '</td>
                                <td class="text-center">' . ($warna['keruh'] ?? '-') . '</td>';
                        }

                        $html .= '</tr>';
                    }
                }

                // Close the table and write to PDF
                $html .= '</table>';
                $pdf->WriteHTML($html);
                // ttd
                $pdf->WriteHTML('</table><table class="table" width="100%" style="border: none;margin-top: 20px">
                <tr>
                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Diserahkan Oleh,</strong></p></td>
                    <td style="border: none;width: 20%; text-align: center;"></td>
                    <td style="border: none;width: 20%; text-align: center;"></td>
                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Diterima Oleh,</strong></p></td>
                </tr>
                <tr>
                    <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                </tr>
                <tr>
                    <td style="border: none;width: 30%;text-align: center;"><p><strong>(.......................................)</strong></p></td>
                    <td style="border: none;width: 20%;text-align: center;"></td>
                    <td style="border: none;width: 20%;text-align: center;"></td>
                    <td style="border: none;width: 30%;text-align: center;"><p><strong>PT INTI SURYA LABORATORIUM</strong></p></td>
                </tr>
            </table>');

            $pdf->SetHTMLHeader($headerLampirann);
            $dir = public_path('dokumen/SampelDiantar/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $filePath = public_path('dokumen/SampelDiantar/' . $fileName);
            return $pdf->Output("","I");
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
            return response()->json(
                [
                    "message" => $e->getMessage(),
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                ],
                400
            );
        }
    } */

    protected function renderBody($pdf, $data, $fileName,$periode,$mode)
    {
        
        try {
            $datas = OrderDetail::where('kategori_1', 'SD')
                ->where('no_order', $data->no_order)
                ->where('is_active', true)
                ->get(['no_sampel','kategori_3']);
                $periode = ($periode === 'null' || is_null($periode)) ? null : $periode;
            $detailSampelDatang = SampelDiantarDetail::where('id_header',$data->id)
                ->first();
            
            // logic
            $no_samples = array_filter($datas->pluck('no_sampel')->toArray(), fn($item) => !is_null($item));
            // $jenis_sampels = array_filter($datas->pluck('kategori_3')->toArray(), fn($item) => !is_null($item));
            // $namaJenisSampel = array_map(function ($item) {
            //     $parts = explode('-', $item, 2);
            //     return isset($parts[1]) ? trim($parts[1]) : '';
            // }, $jenis_sampels);
            $namaJenisSampel = $datas->pluck('kategori_3')
            ->filter()
            ->map(fn($item) => isset(explode('-', $item, 2)[1]) ? trim(explode('-', $item, 2)[1]) : '')
            ->unique()
            ->values()
            ->toArray();

            if($detailSampelDatang != null){
                $internal = json_decode($detailSampelDatang->internal_data ?? '[]', true);
                $eksternal = json_decode($detailSampelDatang->eksternal_data ?? '[]', true);
            }else{
                $internal =false;
                $eksternal =false;

            }


            //kategori

            $dataGabungan = [];
            if (is_array($internal)) {
                foreach ($internal as $itemInt) {
                    if (isset($itemInt['is_active']) && $itemInt['is_active']) {
                        $noSampel = $itemInt['no_sampel'] ?? '';
                        $itemGabungan = $itemInt;
                        $itemGabungan['eksternal'] = is_array($eksternal) ?
                            collect($eksternal)->firstWhere('no_sampel', $noSampel) : [];
                        $dataGabungan[] = $itemGabungan;
                    }
                }
            }
            if($eksternal != '[]'){
                $eksternCollec =collect($eksternal);
                $tanggal = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('tanggal_diambil_oleh_pihak_pelanggan')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $waktu = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('waktu_diambil_pelanggan')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $sertfikasi = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('nama_sertifikat')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

            }else{
                $tanggal =null;
                $waktu =null;
                $sertfikasi =null;
            }
           
            if($internal){
                $internalCollec =collect($internal);
                $jenisWadahSampel = collect($internalCollec)
                ->filter(fn($item) => !empty($item['is_active']) && isset($item['jenis_wadah']))
                ->pluck('jenis_wadah')
                ->flatten()
                ->filter()
                ->unique()
                ->values()
                ->implode(', ') ?: null;
                $sistemLockSampel = collect($internalCollec)
                    ->filter(fn($item) => !empty($item['is_active']) && isset($item['sistem_lock']))
                    ->pluck('sistem_lock')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ') ?: null;     // gabung jadi string
            }else{
                $jenisWadahSampel =null;
                 $sistemLockSampel =null;
            }

            //header lembar tanda terima
            // dd($data);
            $createdAt = optional($data->detail->first())->created_at;
            $dayName = $createdAt ? Carbon::parse($createdAt)->locale('id')->dayName : '-';
            $tanggal = $createdAt ? self::tanggal_indonesia(Carbon::parse($createdAt)->toDateString()) : '-';
            if($detailSampelDatang != null){
                $tanggalSampling = $createdAt ? self::tanggal_indonesia(Carbon::parse($detailSampelDatang->tanggal_diambil_oleh_pihak_pelanggan)->toDateString()) : '-';
                $waktuSamplingSampling = $createdAt ? self::tanggal_indonesia(Carbon::parse($detailSampelDatang->waktu_diambil_pelanggan)->toDateString()) : '-';
            }else{
                $tanggalSampling = null;
                $waktuSamplingSampling = null;

            }
            $jam = $createdAt ? Carbon::parse($createdAt)->format('H:i') : '-';
            $headerLemabaran='<table class="table" width="100%" border="0" style="margin-bottom: 20px">
                                <tr class="tr_top">
                                    <td class="text-left text-wrap" style="border: none;width: 40%">
                                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL" />
                                    </td>
                                    <td style="border: none;width: 60%; text-align: center">
                                        <h3 style="text-align: center;">
                                            LEMBAR TANDA TERIMA DAN INFORMASI SAMPEL<br />DATANG
                                        </h3>
                                        <table style="width: 100%" border="0">
                                            <tr>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: right">' . $data->no_document . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: center">' . $dayName . ' / ' . $tanggal . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: left">' . $jam . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';
            $pdf->SetHTMLHeader($headerLemabaran);
            $informasiWadahSampel=null;

            if ($data->created_at !== null) {
                $tanggalDiterima = Carbon::parse($data->created_at);
                $now = Carbon::now();
                if ($data->suhu === 'ya' || $data->suhu === 'tidak') {
                    $informasiWadahSampel = $data->suhu === 'ya'
                        ? 'Terdapat kontrol suhu selama transportasi sampel'
                        : 'Tidak Terdapat kontrol suhu selama transportasi sampel';

                    if (!is_null($data->tercatat)) {
                        $informasiWadahSampel .= ' dengan suhu penyimpanan sebesar ' . $data->tercatat;
                    }
                } else {
                    $informasiWadahSampel = null;
                }

                $diff = $tanggalDiterima->diff($now);
                $durasi = $diff->format('%d hari, %h jam, %i menit');
            } else {
                $durasi = '';
                $informasiWadahSampel = null;
            }

            // First page content
            $html1 = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed;">
                                <tr>
                                    <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI PELANGGAN</p></th>
                                    <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI SAMPEL</p></th>
                                </tr>
                                <tr>
                                    <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Perusahaan / Instansi Pelanggan</strong></p></td>
                                    <td style="width: 25%; word-wrap: break-word;">' . ($data->nama_perusahaan ?? '') . '</td>
                                    <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Jenis Wadah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">'.$jenisWadahSampel.'</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Pengantar Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->nama_pengantar_sampel ?? '') . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kondisi Keamanan Wadah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">'.$sistemLockSampel.'</td>
                                </tr>
                                 <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Alamat Pelanggan</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->alamat_perusahaan ?? '') . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>No Penewaran</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->no_quotation ?? '') . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Tangal Terima</strong></p></td>
                                    <td style="word-wrap: break-word;">' . (isset($tanggalDiterima) ? $tanggalDiterima : '') . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>No Order</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->no_order ?? '') . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Jumlah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . count($namaJenisSampel) . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong></strong></p></td>
                                    <td style="word-wrap: break-word;"></td>
                                </tr>
                               
                                
                            </table>';
                if($data['ttd_pengirim'] !== null){
                    $ttd_pengirim = $this->decodeImageToBase64($data['ttd_pengirim']);
                }else{
                    $ttd_pengirim =null;
                }
                if($data['ttd_penerima'] !== null){
                    $ttd_penerima = $this->decodeImageToBase64($data['ttd_penerima']);
                }else{
                    $ttd_penerima =null;
                }

            $html1 .= '<table class="table" width="100%" style="border: none;margin-top: 20px">
                    <tr>
                        <td style="border: none;width: 30%; text-align: center;"><p><strong>Diserahkan Oleh,</strong></p></td>
                        <td style="border: none;width: 20%; text-align: center;"></td>
                        <td style="border: none;width: 20%; text-align: center;"></td>
                        <td style="border: none;width: 30%; text-align: center;"><p><strong>Diterima Oleh,<br>PT INTI SURYA LABORATORIUM</strong></p></td>
                    </tr>
                    <tr>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;">'. (
                            ($ttd_pengirim != null)
                            ? '<img src="' . $ttd_pengirim->base64 . '" alt="" style="max-width: 100px; max-height: 50px;" />'
                            : ''
                            )
                        .'</td>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;">'. (
                            ($ttd_penerima != null)
                            ? '<img src="' . $ttd_penerima->base64 . '" alt="" style="max-width: 100px; max-height: 50px;" />'
                            : ''
                            )
                        .'</td>
                    </tr>
                    <tr>
                        <td style="border: none;width: 30%;text-align: center;"><p><strong>('.$data->nama_pengantar_sampel.')</strong></p></td>
                        <td style="border: none;width: 20%;text-align: center;"></td>
                        <td style="border: none;width: 20%;text-align: center;"></td>
                        <td style="border: none;width: 30%;text-align: center;"><p><strong></strong>('.$data->nama_penerima.')</p></td>
                    </tr>
                </table>';

            // Write the first page
            $pdf->WriteHTML($html1);

            if($mode == 'terima'){
                $dir = public_path('dokumen/sampelSD/');
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $filePath = public_path('dokumen/sampelSD/' . $fileName);
                // dd($filePath);

                // The following code is unreachable due to the return statement above
                try {
                    $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
                } catch (\App\Services\MpdfService as MpdfException $e) {
                    dd("Gagal simpan PDF: " . $e->getMessage());
                }
                // $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
                return $fileName;
            }
            // Add a page break
            $pdf->WriteHTML('<pagebreak />');

            // Set the header for the second page
            $headerLampirann='<table class="table" width="100%" border="0" style="margin-bottom: 20px">
                                <tr class="tr_top">
                                    <td class="text-left text-wrap" style="border: none;width: 40%">
                                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL" />
                                    </td>
                                    <td style="border: none;width: 60%; text-align: center">
                                        <h3 style="text-align: center;">
                                            LAMPIRAN TANDA TERIMA DAN INFORMASI SAMPEL
                                        </h3>
                                        <table style="width: 100%" border="0">
                                            <tr>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: right">' . ($data->no_document ?? '') . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: center">' . Carbon::now()->locale('id')->dayName . ' / ' . self::tanggal_indonesia(date('Y-m-d')) . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: left">' . date('H:i') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';

            $pdf->SetHTMLHeader($headerLampirann, 'O');
            // dd($data->kondisi_ubnormal);
            // Second page content
            $html2 = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed;">
                    <tr>
                        <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI KEGIATAN SAMPLING</p></th>
                        <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;"></p></th>
                    </tr>
                    <tr>
                        <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Petugas Sampling Pihak Pelanggan</strong></p></td>
                        <td style="width: 25%; word-wrap: break-word;">' . (($detailSampelDatang != null ) ? $detailSampelDatang->petugas_pengambilan_sampel : '') . '</td>
                        <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Suhu Transportasi sampel</strong></p></td>
                        <td style="word-wrap: break-word;">'.$data->tercatat.'</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Hari / Tanggal Sampling</strong></p></td>
                        <td style="word-wrap: break-word;">'.$tanggalSampling.'</td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kondisi Abnormal
                        </strong></p></td>
                        <td style="word-wrap: break-word;">' . implode(", <br>", isset($data->kondisi_ubnormal) && $data->kondisi_ubnormal !== "null" ? json_decode($data->kondisi_ubnormal, true) : []) . '</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Acuan Metode Pengambilan Sampel</strong></p></td>
                        <td style="word-wrap: break-word;">'.(($detailSampelDatang != null ) ? $detailSampelDatang->metode_standar : '').'</td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Waktu Sampling</strong></p></td>
                        <td style="word-wrap: break-word;">'.$waktuSamplingSampling.'</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Sertifikasi Tenaga Pengambil Sampel</strong></p></td>
                        <td style="word-wrap: break-word;">'.(($detailSampelDatang != null ) ? $detailSampelDatang->nama_sertifikat : '').'</td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Teknik Sampling</strong></p></td>
                        <td style="word-wrap: break-word;">'.(($detailSampelDatang != null ) ? $detailSampelDatang->cara_pengambilan_sample : '').'</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kalibrasi Alat Ukur</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                        <td style="word-wrap: break-word;" colspan=2 ><p style="font-size: 10px"><strong></strong></p></td>
                    </tr>
                </table>';

            // Write the information table
            $pdf->WriteHTML($html2);
            // Now build and write the data table
            $html3 = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed; word-wrap: break-word;">
                    <thead>
                    <tr>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">No. Sampel</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 15%;">Deskripsi Sampel</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 8%;">Jenis Sampel</th>
                        <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">pH</th>
                        <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">DHL (uS/cm)</th>
                        <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">Suhu (°C)</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 8%;">Berwarna</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 7%;">Berbau</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 6%;">Keruh</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 6%;">Jenis Pengawet</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 6%;">Blanko Pencucian</th>
                    </tr>
                    <tr>
                        <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                        <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                        <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                        <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                        <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                        <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                    </tr>
                    </thead>
                    <tbody>';
            if (empty($dataGabungan)) {
                $html3 .= '<tr><td colspan="12" class="text-center">Tidak ada data</td></tr>';
            } else {
                // Body of the table
                usort($dataGabungan, function($a, $b) {
                    return strcmp($a['no_sampel'] ?? '', $b['no_sampel'] ?? '');
                });
                foreach ($dataGabungan as $row) {
                    $warna = isset($row['warna']) && is_array($row['warna']) ? $row['warna'] : [];
                    $eksternal = isset($row['eksternal']) && is_array($row['eksternal']) ? $row['eksternal'] : [];

                    $html3 .= '<tr>
                        <td class="text-center">' . ($row['no_sampel'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['deskripsi_titik'] ?? '-') . '</td>
                        <td class="text-center">' . ($row['jenis_sampel'] ?? '-') . '</td>

                        <td class="text-center">' . ($row['ph'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['ph'] ?? '-') . '</td>

                        <td class="text-center">' . ($row['dhl'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['dhl'] ?? '-') . '</td>

                        <td class="text-center">' . (
                            array_key_exists('suhu', $row) && $row['suhu'] !== null && $row['suhu'] !== ''
                                ? $row['suhu']
                                : '-'
                        ) . '</td>
                        <td class="text-center">' . (isset($eksternal['suhu']) && $eksternal['suhu'] !== null ? $eksternal['suhu'] : '-') . '</td>';
                    $html3 .= '<td class="text-center">' . ($row['warna'] ?? '-') . '</td>';
                    $html3 .= '<td class="text-center">' . ($row['bau'] ?? '-') . '</td>
                        <td class="text-center">' . ($row['keruh'] ?? '-') . '</td>';
                    // pengawetan dan blanco
                    $html3 .='<td class="text-center">' . ($eksternal['pengawetan'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['deskripsi_blanko_pencucian'] ?? '-') . '</td>';
                    $html3 .= '</tr>';
                }
            }

            // Close the table
            $html3 .= '</tbody></table>';

            // Write the data table
            $pdf->WriteHTML($html3);

            // Finally add the signature table
            $html4 = '<table class="table" width="100%" style="border: none;margin-top: 20px">
                <tr>
                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Diserahkan Oleh,</strong></p></td>
                    <td style="border: none;width: 20%; text-align: center;"></td>
                    <td style="border: none;width: 20%; text-align: center;"></td>
                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Diterima Oleh,<br>PT INTI SURYA LABORATORIUM</strong></p></td>
                </tr>
                <tr>
                    <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                </tr>
                <tr>
                    <td style="border: none;width: 30%;text-align: center;"><p><strong>(.......................................)</strong></p></td>
                    <td style="border: none;width: 20%;text-align: center;"></td>
                    <td style="border: none;width: 20%;text-align: center;"></td>
                    <td style="border: none;width: 30%;text-align: center;"><p><strong>(.......................................)</strong></p></td>
                </tr>
            </table>';
            $pdf->WriteHTML($html4);
            // return $pdf->Output("", "I");
            // Output the PDF
            $dir = public_path('dokumen/sampelSD/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $filePath = public_path('dokumen/sampelSD/' . $fileName);
            // dd($filePath);

            // The following code is unreachable due to the return statement above
            try {
                $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            } catch (\App\Services\MpdfService as MpdfException $e) {
                dd("Gagal simpan PDF: " . $e->getMessage());
            }
            // $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
            return response()->json(
                [
                    "message" => $e->getMessage(),
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                ],
                400
            );
        }
    }
    /* backup sebelum tiba fix protected function renderBody($pdf, $data, $fileName,$periode,$mode)
    {
        // dd($periode);
        try {
            $datas = OrderDetail::where('kategori_1', 'SD')
                ->where('no_order', $data->no_order)
                ->where('is_active', true)
                ->get(['no_sampel','kategori_3']);
                $periode = ($periode === 'null' || is_null($periode)) ? null : $periode;
            $detailSampelDatang = SampelDiantarDetail::where('id_header',$data->id)
                ->first();

            // logic
            $no_samples = array_filter($datas->pluck('no_sampel')->toArray(), fn($item) => !is_null($item));
            // $jenis_sampels = array_filter($datas->pluck('kategori_3')->toArray(), fn($item) => !is_null($item));
            // $namaJenisSampel = array_map(function ($item) {
            //     $parts = explode('-', $item, 2);
            //     return isset($parts[1]) ? trim($parts[1]) : '';
            // }, $jenis_sampels);
            $namaJenisSampel = $datas->pluck('kategori_3')
            ->filter()
            ->map(fn($item) => isset(explode('-', $item, 2)[1]) ? trim(explode('-', $item, 2)[1]) : '')
            ->unique()
            ->values()
            ->toArray();


            $internal = json_decode($detailSampelDatang->internal_data ?? '[]', true);
            $eksternal = json_decode($detailSampelDatang->eksternal_data ?? '[]', true);


            //kategori

            $dataGabungan = [];
            if (is_array($internal)) {
                foreach ($internal as $itemInt) {
                    if (isset($itemInt['is_active']) && $itemInt['is_active']) {
                        $noSampel = $itemInt['no_sampel'] ?? '';
                        $itemGabungan = $itemInt;
                        $itemGabungan['eksternal'] = is_array($eksternal) ?
                            collect($eksternal)->firstWhere('no_sampel', $noSampel) : [];
                        $dataGabungan[] = $itemGabungan;
                    }
                }
            }
            if($eksternal != '[]'){
                $eksternCollec =collect($eksternal);
                $tanggal = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('tanggal_diambil_oleh_pihak_pelanggan')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $waktu = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('waktu_diambil_pelanggan')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $sertfikasi = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('nama_sertifikat')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $sertfikasiMetode = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('metode_standar')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $teknikSampling = $eksternCollec
                    ->filter(function ($item) {
                        return !empty($item['is_active']);
                    })
                    ->pluck('cara_pengambilan_sampel')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

            }

            //header lembar tanda terima
            // dd($data);
            $createdAt = optional($data->detail->first())->created_at;
            $dayName = $createdAt ? Carbon::parse($createdAt)->locale('id')->dayName : '-';
            $tanggal = $createdAt ? self::tanggal_indonesia(Carbon::parse($createdAt)->toDateString()) : '-';
            $jam = $createdAt ? Carbon::parse($createdAt)->format('H:i') : '-';
            $headerLemabaran='<table class="table" width="100%" border="0" style="margin-bottom: 20px">
                                <tr class="tr_top">
                                    <td class="text-left text-wrap" style="border: none;width: 40%">
                                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL" />
                                    </td>
                                    <td style="border: none;width: 60%; text-align: center">
                                        <h3 style="text-align: center;">
                                            LEMBAR TANDA TERIMA DAN INFORMASI SAMPEL<br />DATANG
                                        </h3>
                                        <table style="width: 100%" border="0">
                                            <tr>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: right">' . $data->no_document . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: center">' . $dayName . ' / ' . $tanggal . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: left">' . $jam . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';
            $pdf->SetHTMLHeader($headerLemabaran);
            $informasiWadahSampel=null;

            if ($data->created_at !== null) {
                $tanggalDiterima = Carbon::parse($data->created_at);
                $now = Carbon::now();
                if ($data->suhu === 'ya' || $data->suhu === 'tidak') {
                    $informasiWadahSampel = $data->suhu === 'ya'
                        ? 'Terdapat kontrol suhu selama transportasi sampel'
                        : 'Tidak Terdapat kontrol suhu selama transportasi sampel';

                    if (!is_null($data->tercatat)) {
                        $informasiWadahSampel .= ' dengan suhu penyimpanan sebesar ' . $data->tercatat;
                    }
                } else {
                    $informasiWadahSampel = null;
                }

                $diff = $tanggalDiterima->diff($now);
                $durasi = $diff->format('%d hari, %h jam, %i menit');
            } else {
                $durasi = '';
                $informasiWadahSampel = null;
            }

            // First page content
            $html1 = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed;">
                                <tr>
                                    <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI PELANGGAN</p></th>
                                    <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI SAMPEL</p></th>
                                </tr>
                                <tr>
                                    <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Perusahaan / Instansi Pelanggan</strong></p></td>
                                    <td style="width: 25%; word-wrap: break-word;">' . ($data->nama_perusahaan ?? '') . '</td>
                                    <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Informasi Wadah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($informasiWadahSampel ?? '') . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Pengantar Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->nama_pengantar_sampel ?? '') . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kondisi Keamanan Wadah Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;"></td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Tujuan Pengujian</strong></p></td>
                                    <td style="word-wrap: break-word;"></td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Jenis Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . implode(', <br>', $namaJenisSampel) . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Alamat Pelanggan</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->alamat_perusahaan ?? '') . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kode Sampel</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->no_quotation ?? '') . '</td>
                                </tr>
                                <tr>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Tangal Terima</strong></p></td>
                                    <td style="word-wrap: break-word;">' . (isset($tanggalDiterima) ? $tanggalDiterima : '') . '</td>
                                    <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>No Order</strong></p></td>
                                    <td style="word-wrap: break-word;">' . ($data->no_order ?? '') . '</td>
                                </tr>
                            </table>';

            $html1 .= '<table class="table" width="100%" style="border: none;margin-top: 20px">
                    <tr>
                        <td style="border: none;width: 30%; text-align: center;"><p><strong>Diserahkan Oleh,</strong></p></td>
                        <td style="border: none;width: 20%; text-align: center;"></td>
                        <td style="border: none;width: 20%; text-align: center;"></td>
                        <td style="border: none;width: 30%; text-align: center;"><p><strong>Diterima Oleh,</strong></p></td>
                    </tr>
                    <tr>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                        <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                    </tr>
                    <tr>
                        <td style="border: none;width: 30%;text-align: center;"><p><strong>(.......................................)</strong></p></td>
                        <td style="border: none;width: 20%;text-align: center;"></td>
                        <td style="border: none;width: 20%;text-align: center;"></td>
                        <td style="border: none;width: 30%;text-align: center;"><p><strong>PT INTI SURYA LABORATORIUM</strong></p></td>
                    </tr>
                </table>';

            // Write the first page
            $pdf->WriteHTML($html1);

            if($mode == 'terima'){
                $dir = public_path('dokumen/sampelSD/');
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $filePath = public_path('dokumen/sampelSD/' . $fileName);
                // dd($filePath);

                // The following code is unreachable due to the return statement above
                try {
                    $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
                } catch (\App\Services\MpdfService as MpdfException $e) {
                    dd("Gagal simpan PDF: " . $e->getMessage());
                }
                // $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
                return $fileName;
            }
            // Add a page break
            $pdf->WriteHTML('<pagebreak />');

            // Set the header for the second page
            $headerLampirann='<table class="table" width="100%" border="0" style="margin-bottom: 20px">
                                <tr class="tr_top">
                                    <td class="text-left text-wrap" style="border: none;width: 40%">
                                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL" />
                                    </td>
                                    <td style="border: none;width: 60%; text-align: center">
                                        <h3 style="text-align: center;">
                                            LAMPIRAN TANDA TERIMA DAN INFORMASI SAMPEL
                                        </h3>
                                        <table style="width: 100%" border="0">
                                            <tr>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: right">' . ($data->no_document ?? '') . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: center">' . Carbon::now()->locale('id')->dayName . ' / ' . self::tanggal_indonesia(date('Y-m-d')) . '</td>
                                                <td style="border: none;width: 33%; font-size: 10px; text-align: left">' . date('H:i') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';

            $pdf->SetHTMLHeader($headerLampirann, 'O');
            // dd($data->kondisi_ubnormal);
            // Second page content
            $html2 = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed;">
                    <tr>
                        <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;">INFORMASI KEGIATAN SAMPLING</p></th>
                        <th colspan="2" class="text-center"><p style="font-size: 9px; word-wrap: break-word;"></p></th>
                    </tr>
                    <tr>
                        <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Nama Petugas Sampling Pihak Pelanggan</strong></p></td>
                        <td style="width: 25%; word-wrap: break-word;">' . ($detailSampelDatang->petugas_pengambilan_sampel ?? '') . '</td>
                        <td style="width: 25%; word-wrap: break-word;"><p style="font-size: 10px"><strong>Suhu Transportasi sampel</strong></p></td>
                        <td style="word-wrap: break-word;">'.$data->tercatat.'</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Hari / Tanggal Sampling</strong></p></td>
                        <td style="word-wrap: break-word;">'.$tanggal.'</td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kondisi Abnormal
                        </strong></p></td>
                        <td style="word-wrap: break-word;">' . implode(", <br>", isset($data->kondisi_ubnormal) && $data->kondisi_ubnormal !== "null" ? json_decode($data->kondisi_ubnormal, true) : []) . '</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Acuan Metode Pengambilan Sampel</strong></p></td>
                        <td style="word-wrap: break-word;">'.$sertfikasiMetode.'</td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Waktu Sampling</strong></p></td>
                        <td style="word-wrap: break-word;">'.$waktu.'</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Sertifikasi Tenaga Pengambil Sampel</strong></p></td>
                        <td style="word-wrap: break-word;">'.$sertfikasi.'</td>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Teknik Sampling</strong></p></td>
                        <td style="word-wrap: break-word;">'.$teknikSampling.'</td>
                    </tr>
                    <tr>
                        <td style="word-wrap: break-word;"><p style="font-size: 10px"><strong>Kalibrasi Alat Ukur</strong></p></td>
                        <td style="word-wrap: break-word;"></td>
                        <td style="word-wrap: break-word;" colspan=2 ><p style="font-size: 10px"><strong></strong></p></td>
                    </tr>
                </table>';

            // Write the information table
            $pdf->WriteHTML($html2);
            // Now build and write the data table
            $html3 = '<table class="table table-bordered" style="margin-bottom: 20px; width: 100%; table-layout: fixed; word-wrap: break-word;">
                    <thead>
                    <tr>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">No. Sampel</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 15%;">Deskripsi Sampel</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 8%;">Jenis Sampel</th>
                        <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">pH</th>
                        <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">DHL (uS/cm)</th>
                        <th colspan="2" class="text-center" style="word-wrap: break-word; width: 14%;">Suhu (°C)</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 8%;">Berwarna</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 7%;">Berbau</th>
                        <th rowspan="2" class="text-center" style="word-wrap: break-word; width: 6%;">Keruh</th>
                    </tr>
                    <tr>
                        <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                        <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                        <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                        <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                        <th class="text-center" style="word-wrap: break-word; width: 6%;">Lab</th>
                        <th class="text-center" style="word-wrap: break-word; width: 8%;">Lapangan</th>
                    </tr>
                    </thead>
                    <tbody>';
            if (empty($dataGabungan)) {
                $html3 .= '<tr><td colspan="12" class="text-center">Tidak ada data</td></tr>';
            } else {
                // Body of the table
                foreach ($dataGabungan as $row) {
                    $warna = isset($row['warna']) && is_array($row['warna']) ? $row['warna'] : [];
                    $eksternal = isset($row['eksternal']) && is_array($row['eksternal']) ? $row['eksternal'] : [];

                    $html3 .= '<tr>
                        <td class="text-center">' . ($row['no_sampel'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['deskripsi_titik'] ?? '-') . '</td>
                        <td class="text-center">' . ($row['jenis_sampel'] ?? '-') . '</td>

                        <td class="text-center">' . ($row['ph'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['ph'] ?? '-') . '</td>

                        <td class="text-center">' . ($row['dhl'] ?? '-') . '</td>
                        <td class="text-center">' . ($eksternal['dhl'] ?? '-') . '</td>

                        <td class="text-center">' . (
                            array_key_exists('suhu', $row) && $row['suhu'] !== null && $row['suhu'] !== ''
                                ? $row['suhu']
                                : '-'
                        ) . '</td>
                        <td class="text-center">' . (isset($eksternal['suhu']) && $eksternal['suhu'] !== null ? $eksternal['suhu'] : '-') . '</td>';

                    $warnaStatus = isset($warna['warna_status']) ? strtolower($warna['warna_status']) : '';
                    if ($warnaStatus === 'ya') {
                        $html3 .= '<td class="text-center">' . ($warna['warna'] ?? '-') . '</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>';
                    } else {
                        $html3 .= '<td class="text-center">-</td>
                            <td class="text-center">' . ($warna['bau'] ?? '-') . '</td>
                            <td class="text-center">' . ($warna['keruh'] ?? '-') . '</td>';
                    }

                    $html3 .= '</tr>';
                }
            }

            // Close the table
            $html3 .= '</tbody></table>';

            // Write the data table
            $pdf->WriteHTML($html3);

            // Finally add the signature table
            $html4 = '<table class="table" width="100%" style="border: none;margin-top: 20px">
                <tr>
                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Diserahkan Oleh,</strong></p></td>
                    <td style="border: none;width: 20%; text-align: center;"></td>
                    <td style="border: none;width: 20%; text-align: center;"></td>
                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Diterima Oleh,</strong></p></td>
                </tr>
                <tr>
                    <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                    <td style="border: none;width: 30%; text-align: center;height: 80px;"></td>
                </tr>
                <tr>
                    <td style="border: none;width: 30%;text-align: center;"><p><strong>(.......................................)</strong></p></td>
                    <td style="border: none;width: 20%;text-align: center;"></td>
                    <td style="border: none;width: 20%;text-align: center;"></td>
                    <td style="border: none;width: 30%;text-align: center;"><p><strong>PT INTI SURYA LABORATORIUM</strong></p></td>
                </tr>
            </table>';
            $pdf->WriteHTML($html4);
            // return $pdf->Output("", "I");
            // Output the PDF
            $dir = public_path('dokumen/sampelSD/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $filePath = public_path('dokumen/sampelSD/' . $fileName);
            // dd($filePath);

            // The following code is unreachable due to the return statement above
            try {
                $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            } catch (\App\Services\MpdfService as MpdfException $e) {
                dd("Gagal simpan PDF: " . $e->getMessage());
            }
            // $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
            return response()->json(
                [
                    "message" => $e->getMessage(),
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                ],
                400
            );
        }
    } */

    protected function tanggal_indonesia($tanggal)
    {
        $bulan = [
            1 => "Januari",
            "Februari",
            "Maret",
            "April",
            "Mei",
            "Juni",
            "Juli",
            "Agustus",
            "September",
            "Oktober",
            "November",
            "Desember",
        ];
        $var = explode("-", $tanggal);
        return $var[2] . " " . $bulan[(int) $var[1]] . " " . $var[0];
    }

    public function decodeImageToBase64($filename)
    {
        // Path penyimpanan
        $path = public_path('dokumen/sd');

        // Path file lengkap
        $filePath = $path . '/' . $filename;

        // Periksa apakah file ada
        if (!file_exists($filePath)) {
            return (object) [
                'status' => 'error',
                'message' => 'File tidak ditemukan'
            ];
        }

        // Baca konten file
        $imageContent = file_get_contents($filePath);

        // Konversi ke base64
        $base64Image = base64_encode($imageContent);

        // Deteksi tipe file
        $fileType = $this->detectFileType($imageContent);

        // Tambahkan data URI header sesuai tipe file
        $base64WithHeader = 'data:image/' . $fileType . ';base64,' . $base64Image;

        // Kembalikan respons
        return (object) [
            'status' => 'success',
            'base64' => $base64WithHeader,
            'file_type' => $fileType
        ];
    }
    private function detectFileType($fileContent)
    {
        // Signature file untuk berbagai format
        $signatures = [
            'png' => "\x89PNG\x0D\x0A\x1A\x0A",
            'jpg' => "\xFF\xD8\xFF",
            'gif' => "GIF87a",
            'webp' => "RIFF",
            'svg' => '<?xml'
        ];

        foreach ($signatures as $type => $signature) {
            if (strpos($fileContent, $signature) === 0) {
                return $type;
            }
        }

        return 'bin';
    }
}
