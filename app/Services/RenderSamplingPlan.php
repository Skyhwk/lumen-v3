<?php

namespace App\Services;

use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use App\Models\Parameter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mpdf;

class RenderSamplingPlan
{
    private $data;
    private $periode;

    public static function onKontrak($id)
    {
        $data = QuotationKontrakH::with('detail', 'sampling')->where('id', $id)->first();

        // dd(json_decode($data->data_pendukung_sampling)->toArray());
        // dd($data->sampling->where('periode_kontrak','2025-02')->first()->jadwal()->where('periode' ,'2025-02')->get()->toArray());
        $self = new self();
        $self->data = $data;

        return $self;
    }

    public function onPeriode($periode)
    {
        $this->periode = $periode;

        return $this;
    }

    public static function onNonKontrak($id)
    {
        $data = QuotationNonKontrak::with('sampling')->where('id', $id)->first();

        $self = new self();
        $self->data = $data;

        return $self;
    }

    public function save()
    {
        try {
            $sampling = '';
            $data = $this->data;

            if ($data->status_sampling == 'S24') {
                $sampling = 'SAMPLING 24 JAM';
            } else if ($data->status_sampling == 'SD') {
                $sampling = 'SAMPLING DATANG';
            } else if ($data->status_sampling == 'SP') {
                $sampling = 'SAMPLE PICKUP';
            }else if ($data->status_sampling == 'RS') {
                $sampling = 'RE-SAMPLING';
            } else {
                $sampling = 'SAMPLING';
            }

            if ($data->konsultan != '') {
                $perusahaan = strtoupper($data->konsultan) . ' ( ' . $data->nama_perusahaan . ' ) ';
            } else {
                $perusahaan = $data->nama_perusahaan;
            }

            $sampling_plan = $data->sampling->first();

            $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_header' => 3, 'margin_footer' => 3, 'setAutoTopMargin' => 'stretch', 'orientation' => 'P']);

            $pdf->SetProtection(['print'], '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', [65, 60]);
            $pdf->showWatermarkImage = true;

            $pdf->setFooter([
                'odd' => [
                    'C' => ['content' => 'Hal {PAGENO} dari {nbpg}', 'font-size' => 6, 'font-style' => 'I', 'font-family' => 'serif', 'color' => '#606060'],
                    'R' => ['content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}', 'font-size' => 5, 'font-style' => 'I', 'font-family' => 'serif', 'color' => '#000000'],
                    'L' => ['font-size' => 4, 'font-style' => 'I', 'font-family' => 'serif', 'color' => '#000000'],
                    'line' => -1,
                ]
            ]);

            $periode_ = '';
            $status_kontrak = 'NON CONTRACT';

            if (explode("/", $sampling_plan->no_quotation)[1] == 'QTC') {
                $status_kontrak = 'CONTRACT';
                $periode_ = self::tanggal_indonesia($sampling_plan->periode_kontrak, 'period');
            }

            $fileName = preg_replace('/\\//', '-', $sampling_plan->no_document) . '.pdf';

            $pdf->SetHTMLHeader('
                <table class="tabel" width="100%">
                    <tr class="tr_top">
                        <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL"></td>
                        <td style="width: 33.33%; text-align: center;">
                            <h5 style="text-align:center; font-size:14px;"><b><u>SAMPLING PLAN</u></b></h5>
                            <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $periode_ . '</p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;">' . self::tanggal_indonesia(date('Y-m-d')) . ' - ' . date('G:i') . '</p> <br>
                            <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">' . $status_kontrak . '</span> <span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $sampling . '</span>
                        </td>
                    </tr>
                </table>
                <table class="table table-bordered" width="100%">
                    <tr>
                        <td colspan="2" style="font-size: 12px; padding: 5px;"><h6 style="font-size:12px; font-weight: bold;" id="nama_customer">' . preg_replace('/&AMP;+/', '&', $perusahaan) . '</h6></td>
                        <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;" id="no_document">' . $sampling_plan->no_quotation . '</span></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 12px; padding: 5px;"><span style="font-size:12px;" id="alamat_customer">' . $data->alamat_sampling . '</span></td>
                        <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;" id="no_document_sp">' . $sampling_plan->no_document . '</span></td>
                    </tr>
                </table>
            ');

            $pdf->WriteHTML('
                <table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="85%">KETERANGAN PENGUJIAN</th>
                            <th width="13%">TITIK</th>
                        </tr>
                    </thead>
                    <tbody>');

            $i = 1;
            if (explode("/", $sampling_plan->no_quotation)[1] == 'QTC') {
                foreach (json_decode($data->data_pendukung_sampling) as $key => $y) {

                    if (!in_array($sampling_plan->periode_kontrak, $y->periode)) continue;  /* update by rangga */

                    $kategori = explode("-", $y->kategori_1);
                    $kategori2 = explode("-", $y->kategori_2);
                    $regulasi = ($y->regulasi[0] != '') ? explode('-', $y->regulasi[0])[1] : '';
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                        <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . $regulasi . ' - ' . $y->total_parameter . ' Parameter</b>'
                    );

                    foreach ($y->parameter as $keys => $valuess) {
                        $dParam = explode(';', $valuess);
                        $d = Parameter::where('id', $dParam[0])->where('is_active', 1)->first();
                        if ($keys == 0) {
                            $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        } else {
                            $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        }
                    }

                    $pdf->WriteHTML(
                        '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $y->jumlah_titik . '</td></tr>'
                    );
                }

                if ($data->transportasi > 0) {
                    // $pdf->WriteHTML('
                    //     <tr>
                    //         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //         <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                    //         <td style="font-size: 13px; text-align:center;">' . $data->transportasi / $data->transportasi . '</td>
                    //     </tr>');
                    $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        </tr>');
                }

                if ($data->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;

                    $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $data->perdiem_jumlah_orang . ' Orang/hari</td>
                            <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari . '</td>
                        </tr>');
                    /* $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Perdiem : ' . ceil($data->perdiem_jumlah_orang / $data->perdiem_jumlah_hari) . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari / $data->perdiem_jumlah_hari . '</td>
                        </tr>'); */
                }

                $pdf->WriteHTML('</tbody></table>');
            } else {
                
                foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                    $kategori = explode("-", $a->kategori_1);
                    $kategori2 = explode("-", $a->kategori_2);
                    $regulasi = '';

                    if (is_array($a->regulasi) && count($a->regulasi) > 0) {
                       
                        // $cleanedRegulasi = array_map(function ($peraturan) {
                        //     return $peraturan && explode("-", $peraturan, 2)[1]; // Ambil bagian setelah "-"
                        // }, $a->regulasi);


                        $cleanedRegulasi = array_map(function ($peraturan) {
                            $parts = explode("-", $peraturan, 2);
                            return $parts[1] ?? $peraturan; // Kalau gagal explode, kembalikan string aslinya
                        }, $a->regulasi);
                         $regulasi = implode(', ', $cleanedRegulasi);
                    }
                    
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                            <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . $regulasi . ' - ' . $a->total_parameter . ' Parameter</b>'
                    );
                    // dd('ss');
                    // dd($d->nama_lab);
                    foreach ($a->parameter as $keys => $valuess) {
                        $dParam = explode(';', $valuess);
                        $d = Parameter::where('id', $dParam[0])->where('is_active', 1)->first();
                        if (!$d) continue;
                        if ($keys == 0) {
                            $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        } else {
                            $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        }
                    }

                    $pdf->WriteHTML(
                        '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $a->jumlah_titik . '</td></tr>'
                    );
                }

                if ($data->transportasi > 0) {
                    // $pdf->WriteHTML('
                    //     <tr>
                    //         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //         <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                    //         <td style="font-size: 13px; text-align:center;">' . $data->transportasi / $data->transportasi . '</td>
                    //     </tr>');
                    $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        </tr>');
                }

                if ($data->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    // $pdf->WriteHTML('
                    //     <tr>
                    //         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //         <td style="font-size: 13px;padding: 5px;">Perdiem : ' . ceil($data->perdiem_jumlah_orang / $data->perdiem_jumlah_hari) . '</td>
                    //         <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari / $data->perdiem_jumlah_hari . '</td>
                    //     </tr>');
                    $pdf->WriteHTML('
                        <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $data->perdiem_jumlah_orang . ' Orang/hari</td>
                            <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari . '</td>
                        </tr>');
                }

                $pdf->WriteHTML('</tbody></table>');
            }

            $pdf->WriteHTML('
                <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                    <thead class="text-center">
                        <tr>
                            <th colspan="5" style="text-align:center;">PERMINTAAN WAKTU SAMPLING</th>
                        </tr>
                        <tr>
                            <td style="text-align:center;">Tanggal</td>
                            <td style="text-align:center;">JAM</td>
                            <td style="text-align:center;">SABTU</td>
                            <td style="text-align:center;">MINGGU</td>
                            <td style="text-align:center;">MALAM</td>
                        </tr>
                    </thead>
                    <tbody>');
            $datopsi = [$sampling_plan->opsi_1, $sampling_plan->opsi_2];

            foreach ($datopsi as $k => $v) {
                if ($v != null) {
                    $dattgl = preg_replace('/\s+/', ' ', $v);
                    $dattgl2 = str_replace(',', ' ', $dattgl);
                    $dattgl3 = explode(" ", $dattgl2);
                    $opsi_tgl = $dattgl3[0] . ' ' . $dattgl3[1] . ' ' . $dattgl3[2] . ' ';
                    $opsi_jam = $dattgl3[3] . ' ' . $dattgl3[4] . ' ' . $dattgl3[5] . ' ';
                    $pdf->WriteHTML('
                        <tr>
                            <td style="text-align:center;">' . $opsi_tgl . '</td>
                            <td style="text-align:center;">' . $opsi_jam . '</td>
                            <td style="vertical-align: middle; text-align:center;">' . $sampling_plan->is_sabtu . '</td>
                            <td style="text-align:center;">' . $sampling_plan->is_minggu . '</td>
                            <td style="text-align:center;">' . $sampling_plan->is_malam . '</td>
                        </tr>');
                }
            }

            $pdf->WriteHTML('</tbody></table>');

            /* ======== perubahan logic */
            $isTambahanEmpty = $sampling_plan->tambahan === 'null' || $sampling_plan->tambahan === null || empty(json_decode($sampling_plan->tambahan));
            $isKeteranganLainEmpty = $sampling_plan->keterangan_lain === 'null' || $sampling_plan->keterangan_lain === null || empty(json_decode($sampling_plan->keterangan_lain));

            if (!$isTambahanEmpty || !$isKeteranganLainEmpty) {
                $pdf->WriteHTML('
                    <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                        <thead class="text-center">
                            <tr>
                                <th style="text-align:center;" colspan="2">TAMBAHAN</th>
                                <th style="text-align:center;">KETERANGAN LAIN</th>
                            </tr>
                        </thead>
                        <tbody>');
                $no = 0;
                $n = 0;
                if ($sampling_plan->tambahan != 'null' || $sampling_plan->tambahan != null) {
                    $tambahanData = json_decode($sampling_plan->tambahan);
                    $keteranganLainData = array_values((array) json_decode($sampling_plan->keterangan_lain));
                    $totalRows = max(count($tambahanData), count($keteranganLainData)); // Ambil jumlah baris terbanyak antara tambahan dan keterangan_lain

                    for ($i = 0; $i < $totalRows; $i++) {
                        $pdf->WriteHTML('<tr>');

                        if (isset($tambahanData[$i])) {
                            switch ($tambahanData[$i]) {
                                case "genset":
                                    $valTambahan = "GENSET";
                                    break;
                                case "sarungLatex":
                                    $valTambahan = "SARUNG TANGAN ( LATEX )";
                                    break;
                                case "masker":
                                    $valTambahan = "MASKER";
                                    break;
                                case "sarungKain":
                                    $valTambahan = "SARUNG TANGAN ( KAIN )";
                                    break;
                                case "faceShield":
                                    $valTambahan = "FACE SHIELD";
                                    break;
                                case "workingPermit":
                                    $valTambahan = "WORKING PERMIT";
                                    break;
                                case "apdLengkap":
                                    $valTambahan = "APD LENGKAP";
                                    break;
                                case "pickupSampel":
                                    $valTambahan = "Pick Up Sampel";
                                    break;
                                case "hazmat":
                                    $valTambahan = "Hazmat";
                                    break;
                                default:
                                    $valTambahan = $tambahanData[$i];
                                    break;
                            }

                            $pdf->WriteHTML('<td style="text-align:center;">V</td>');
                            $pdf->WriteHTML('<td style="text-align:center;">' . $valTambahan . '</td>');
                        } else {
                            $pdf->WriteHTML('<td style="text-align:center;">&nbsp;</td>'); // Sel kosong jika tidak ada data tambahan
                            $pdf->WriteHTML('<td style="text-align:center;">&nbsp;</td>');
                        }

                        // Kolom Keterangan Lain
                        if (isset($keteranganLainData[$i])) {
                            $pdf->WriteHTML('<td style="text-align:center;">' . $keteranganLainData[$i] . '</td>');
                        } else {
                            $pdf->WriteHTML('<td style="text-align:center;">&nbsp;</td>'); // Sel kosong jika tidak ada keterangan lain
                        }

                        $pdf->WriteHTML('</tr>');
                    }
                } else {
                    // Mengisi semua baris dengan kosong jika "tambahan" kosong
                    for ($i = 0; $i < 12; $i++) {
                        $pdf->WriteHTML('
                            <tr>
                                <td style="text-align:center;">&nbsp;</td>
                                <td style="text-align:center;"></td>
                                <td style="text-align:center;"></td>
                            </tr>');
                    }
                }
                $pdf->WriteHTML('</tbody></table>');
            }

            $groupedKategori = [];
            $groupedSampler = [];
            $groupedDate = [];
            $groupedJamMulai = [];
            $groupedJamSelesai = [];
            foreach ($sampling_plan->jadwal as $item) {
                array_push($groupedKategori, json_decode($item->kategori));
                array_push($groupedSampler, $item->sampler);
                array_push($groupedDate, $item->tanggal);
                array_push($groupedJamMulai, $item->jam_mulai);
                array_push($groupedJamSelesai, $item->jam_selesai);
            }

            $kategories = collect($groupedKategori)->flatten()->unique()->values()->toArray();
            $samplers = array_unique($groupedSampler);
            $dates = array_unique($groupedDate);
            $jamMulai = array_unique($groupedJamMulai);
            $jamSelesai = array_unique($groupedJamSelesai);

            $groupedData = [];
            foreach ($kategories as $item) {
                $parts = explode(" - ", $item);
                $kategori = $parts[0];

                if (array_key_exists($kategori, $groupedData)) {
                    $groupedData[$kategori]++;
                } else {
                    $groupedData[$kategori] = 1;
                }
            }

            if (is_array($groupedData) && count($groupedData) > 0) {
                $pdf->WriteHTML('
                    <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                        <thead class="text-center">
                            <tr>
                                <th width="2%" style="padding: 5px !important;">NO</th>
                                <th width="85%">PENGAMBILAN SAMPLING</th>
                                <th width="13%">TITIK</th>
                            </tr>
                        </thead>
                        <tbody>');

                $i = 1;

                foreach ($groupedData as $kategori => $jumlah) {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 11px;">' . $i++ . '</td>
                        <td style="font-size: 11px; padding: 5px;"><b style="font-size: 11px;">' . $kategori . '</b>'
                    );

                    $pdf->WriteHTML(
                        '<td style="font-size: 11px; padding: 5px;text-align:center;">' . $jumlah . '</td></tr>'
                    );
                }
                $pdf->WriteHTML('</tbody></table>');

                $pdf->WriteHTML('
                    <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                        <thead class="text-center">
                            <tr>
                                <th colspan="6" style="text-align:center;">PENJADWALAN SAMPLING</th>
                            </tr>
                            <tr>
                                <th class="text-center">Tanggal</th>
                                <th class="text-center">Jam Mulai</th>
                                <th class="text-center">Jam Selesai</th>
                                <th colspan="3" class="text-center">Sampler</th>
                            </tr>
                        </thead>
                        <tbody>');

                $pdf->WriteHTML('
                    <tr>
                        <td style="text-align:center; vertical-align: middle;">' . implode(", ", array_map([$this, 'tanggal_indonesia'], array_map(function ($date) {
                    return date("Y-m-d", strtotime($date));
                }, $dates))) . '</td>
                        <td style="text-align:center; vertical-align: middle;">' . min($jamMulai) . '</td>
                        <td style="vertical-align: middle; text-align:center;">' . max($jamSelesai) . '</td>
                        <td colspan="3" style="text-align:center; vertical-align: middle;">' . implode(", ", $samplers) . '</td>
                    </tr>');

                $pdf->WriteHTML('</tbody></table>');
            }

            $dir = public_path('sampling_plan/');

            if (!file_exists($dir)) mkdir($dir, 0777, true);

            $filePath = $dir . '/' . $fileName;

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);

            $updatedSP = SamplingPlan::where('id', $sampling_plan->id)->first();
            $updatedSP->filename = $fileName;
            $updatedSP->save();

            return $fileName;
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function renderPartialKontrak()
    {

        try {
            $sampling = '';
            $data = $this->data;
            $periode = $this->periode;
            // dd($data->sampling->where('periode_kontrak', $periode)->toArray());
            // dd($data->detail->toArray());

            if ($data->status_sampling == 'S24') {
                $sampling = 'SAMPLING 24 JAM';
            } else if ($data->status_sampling == 'SD') {
                $sampling = 'SAMPLING DATANG';
            } else if ($data->status_sampling == 'SP') {
                $sampling = 'SAMPLE PICKUP';
            } else if ($data->status_sampling == 'RS') {
                $sampling = 'RE-SAMPLING';
            } else {
                $sampling = 'SAMPLING';
            }

            if ($data->konsultan != '') {
                $perusahaan = strtoupper($data->konsultan) . ' ( ' . $data->nama_perusahaan . ' ) ';
            } else {
                $perusahaan = $data->nama_perusahaan;
            }

            $sampling_plan = $data->sampling
                ->where('periode_kontrak', $periode)
                ->where('no_quotation', $data->no_document)
                ->sortByDesc('id')
                ->first();

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3,     // 30mm not pixel
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'orientation' => 'P'
            );

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(
                65,
                60
            ));
            $pdf->showWatermarkImage = true;

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
                        // 'content' => '' . $qr_img . '',
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

            $periode_ = '';
            $status_kontrak = 'NON CONTRACT';
            if (explode("/", $sampling_plan->no_quotation)[1] == 'QTC') {
                $status_kontrak = 'CONTRACT';
                $periode_ = self::tanggal_indonesia($sampling_plan->periode_kontrak, 'period');
            }

            $fileName = preg_replace('/\\//', '-', $sampling_plan->no_document) . '.pdf';

            $pdf->SetHTMLHeader('
                    <table class="tabel" width="100%">
                        <tr class="tr_top">
                            <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                                    src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <h5 style="text-align:center; font-size:14px;"><b><u>SAMPLING PLAN</u></b></h5>
                                <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $periode_ . '</p>
                            </td>
                            <td style="text-align: right;">
                                <p style="font-size: 9px; text-align:right;">' . self::tanggal_indonesia(date('Y-m-d')) . ' - ' . date('G:i') . '</p> <br>
                                <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">' . $status_kontrak . '</span> <span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $sampling . '</span>
                            </td>
                        </tr>
                    </table>
                    <table class="table table-bordered" width="100%">
                        <tr>
                            <td colspan="2" style="font-size: 12px; padding: 5px;"><h6 style="font-size:12px; font-weight: bold;" id="nama_customer">' . preg_replace('/&AMP;+/', '&', $perusahaan) . '</h6></td>
                            <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;" id="no_document">' . $sampling_plan->no_quotation . '</span></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="font-size: 12px; padding: 5px;"><span style="font-size:12px;" id="alamat_customer">' . $data->alamat_sampling . '</span></td>
                            <td style="font-size: 12px; padding: 5px;"><span style="font-size:12px; font-weight: bold;" id="no_document_sp">' . $sampling_plan->no_document . '</span></td>
                        </tr>
                    </table>
                ');

            $pdf->WriteHTML('
                    <table class="table table-bordered" style="font-size: 8px;">
                        <thead class="text-center">
                            <tr>
                                <th width="2%" style="padding: 5px !important;">NO</th>
                                <th width="85%">KETERANGAN PENGUJIAN</th>
                                <th width="13%">TITIK</th>
                            </tr>
                        </thead>
                        <tbody>');

            $i = 1;

            if (explode("/", $sampling_plan->no_quotation)[1] == 'QTC') {
                foreach (json_decode($data->data_pendukung_sampling) as $key => $y) {
                    if (!in_array($sampling_plan->periode_kontrak, $y->periode)) continue;

                    $kategori = explode("-", $y->kategori_1);
                    $kategori2 = explode("-", $y->kategori_2);
                    $regulasi = ($y->regulasi && $y->regulasi[0] != '') ? explode('-', $y->regulasi[0])[1] : '';
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                        <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . $regulasi . ' - ' . $y->total_parameter . ' Parameter</b>'
                    );

                    foreach ($y->parameter as $keys => $valuess) {
                        $dParam = explode(';', $valuess);
                        $d = Parameter::where('id', $dParam[0])->where('is_active', 1)->first();
                        if ($keys == 0) {
                            $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        } else {
                            $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        }
                    }

                    $pdf->WriteHTML(
                        '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $y->jumlah_titik . '</td></tr>'
                    );
                }

                if ($data->transportasi > 0) {
                    // $pdf->WriteHTML('
                    //         <tr>
                    //             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //             <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                    //             <td style="font-size: 13px; text-align:center;">' . $data->transportasi / $data->transportasi . '</td>
                    //         </tr>');
                    $transportasi = $data->detail->where('periode_kontrak', $periode)->first();
                    $pdf->WriteHTML('
                            <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                                <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                                <td style="font-size: 13px; text-align:center;">' . $transportasi->transportasi . '</td>
                            </tr>');
                }

                if ($data->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    /* $pdf->WriteHTML('
                            <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                                <td style="font-size: 13px;padding: 5px;">Perdiem : ' . ceil($data->perdiem_jumlah_orang / $data->perdiem_jumlah_hari) . '</td>
                                <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari / $data->perdiem_jumlah_hari . '</td>
                            </tr>'); */
                    $perdiem = $data->detail->where('periode_kontrak', $periode)->first();
                    $pdf->WriteHTML('
                    <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $perdiem->perdiem_jumlah_orang . ' Orang/hari</td>
                        <td style="font-size: 13px; text-align:center;">' . $perdiem->perdiem_jumlah_hari . '</td>
                    </tr>');
                }

                $pdf->WriteHTML('</tbody></table>');
            } else {
                foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                    $kategori = explode("-", $a->kategori_1);
                    $kategori2 = explode("-", $a->kategori_2);
                    $regulasi = '';
                    if (is_array($a->regulasi) && count($a->regulasi) > 0) {
                        $cleanedRegulasi = array_map(function ($peraturan) {
                            return explode("-", $peraturan, 2)[1]; // Ambil bagian setelah "-"
                        }, $a->regulasi);

                        $regulasi = implode(', ', $cleanedRegulasi);
                    }

                    $pdf->WriteHTML(
                        '<tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 12px; padding: 5px;"><b style="font-size: 12px;">' . $kategori2[1] . ' - ' . $regulasi . ' - ' . $a->total_parameter . ' Parameter</b>'
                    );

                    foreach ($a->parameter as $keys => $valuess) {
                        $dParam = explode(';', $valuess);
                        $d = Parameter::where('id', $dParam[0])->where('is_active', 1)->first();
                        if ($keys == 0) {
                            $pdf->WriteHTML('<br><hr><span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        } else {
                            $pdf->WriteHTML(' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_lab . '</span> ');
                        }
                    }

                    $pdf->WriteHTML(
                        '<td style="font-size: 13px; padding: 5px;text-align:center;">' . $a->jumlah_titik . '</td></tr>'
                    );
                }

                if ($data->transportasi > 0) {
                    // $pdf->WriteHTML('
                    //         <tr>
                    //             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //             <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                    //             <td style="font-size: 13px; text-align:center;">' . $data->transportasi / $data->transportasi . '</td>
                    //         </tr>');
                    $transportasi = $data->detail->where('periode_kontrak', $periode)->first();
                    $pdf->WriteHTML('
                            <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                                <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . explode('-', $data->wilayah)[1] . '</td>
                                <td style="font-size: 13px; text-align:center;">' . $transportasi->transportasi . '</td>
                            </tr>');
                }

                if ($data->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    // $pdf->WriteHTML('
                    //         <tr>
                    //             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //             <td style="font-size: 13px;padding: 5px;">Perdiem : ' . ceil($data->perdiem_jumlah_orang / $data->perdiem_jumlah_hari) . '</td>
                    //             <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari / $data->perdiem_jumlah_hari . '</td>
                    //         </tr>');

                    // $pdf->WriteHTML('
                    //     <tr>
                    //         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                    //         <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $data->perdiem_jumlah_orang . ' Orang/hari</td>
                    //         <td style="font-size: 13px; text-align:center;">' . $data->perdiem_jumlah_hari . '</td>
                    //     </tr>');
                    $perdiem = $data->detail->where('periode_kontrak', $periode)->first();
                    $pdf->WriteHTML('
                    <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem : ' . $perdiem->perdiem_jumlah_orang . ' Orang/hari</td>
                        <td style="font-size: 13px; text-align:center;">' . $perdiem->perdiem_jumlah_hari . '</td>
                    </tr>');
                }

                $pdf->WriteHTML('</tbody></table>');
            }

            $pdf->WriteHTML('
                    <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                        <thead class="text-center">
                            <tr>
                                <th colspan="5" style="text-align:center;">PERMINTAAN WAKTU SAMPLING</th>
                            </tr>
                            <tr>
                                <td style="text-align:center;">Tanggal</td>
                                <td style="text-align:center;">JAM</td>
                                <td style="text-align:center;">SABTU</td>
                                <td style="text-align:center;">MINGGU</td>
                                <td style="text-align:center;">MALAM</td>
                            </tr>
                        </thead>
                        <tbody>');
            $datopsi = [$sampling_plan->opsi_1, $sampling_plan->opsi_2];

            foreach ($datopsi as $k => $v) {
                if ($v != null) {
                    $dattgl = preg_replace('/\s+/', ' ', $v);
                    $dattgl2 = str_replace(',', ' ', $dattgl);
                    $dattgl3 = explode(" ", $dattgl2);
                    $opsi_tgl = $dattgl3[0] . ' ' . $dattgl3[1] . ' ' . $dattgl3[2] . ' ';
                    $opsi_jam = $dattgl3[3] . ' ' . $dattgl3[4] . ' ' . $dattgl3[5] . ' ';
                    $pdf->WriteHTML('
                            <tr>
                                <td style="text-align:center;">' . $opsi_tgl . '</td>
                                <td style="text-align:center;">' . $opsi_jam . '</td>
                                <td style="vertical-align: middle; text-align:center;">' . $sampling_plan->is_sabtu . '</td>
                                <td style="text-align:center;">' . $sampling_plan->is_minggu . '</td>
                                <td style="text-align:center;">' . $sampling_plan->is_malam . '</td>
                            </tr>');
                }
            }

            $pdf->WriteHTML('</tbody></table>');

            /* ======== perubahan logic */
            $isTambahanEmpty = $sampling_plan->tambahan === 'null' || $sampling_plan->tambahan === null || empty(json_decode($sampling_plan->tambahan));
            $isKeteranganLainEmpty = $sampling_plan->keterangan_lain === 'null' || $sampling_plan->keterangan_lain === null || empty(json_decode($sampling_plan->keterangan_lain));

            if (!$isTambahanEmpty || !$isKeteranganLainEmpty) {
                $pdf->WriteHTML('
                        <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                            <thead class="text-center">
                                <tr>
                                    <th style="text-align:center;" colspan="2">TAMBAHAN</th>
                                    <th style="text-align:center;">KETERANGAN LAIN</th>
                                </tr>
                            </thead>
                            <tbody>');
                $no = 0;
                $n = 0;
                if ($sampling_plan->tambahan != 'null' || $sampling_plan->tambahan != null) {
                    $tambahanData = json_decode($sampling_plan->tambahan);
                    $keteranganLainData = array_values((array) json_decode($sampling_plan->keterangan_lain));
                    $totalRows = max(count($tambahanData), count($keteranganLainData)); // Ambil jumlah baris terbanyak antara tambahan dan keterangan_lain

                    for ($i = 0; $i < $totalRows; $i++) {
                        $pdf->WriteHTML('<tr>');

                        if (isset($tambahanData[$i])) {
                            switch ($tambahanData[$i]) {
                                case "genset":
                                    $valTambahan = "GENSET";
                                    break;
                                case "sarungLatex":
                                    $valTambahan = "SARUNG TANGAN ( LATEX )";
                                    break;
                                case "masker":
                                    $valTambahan = "MASKER";
                                    break;
                                case "sarungKain":
                                    $valTambahan = "SARUNG TANGAN ( KAIN )";
                                    break;
                                case "faceShield":
                                    $valTambahan = "FACE SHIELD";
                                    break;
                                case "workingPermit":
                                    $valTambahan = "WORKING PERMIT";
                                    break;
                                case "apdLengkap":
                                    $valTambahan = "APD LENGKAP";
                                    break;
                                case "pickupSampel":
                                    $valTambahan = "Pick Up Sampel";
                                    break;
                                case "hazmat":
                                    $valTambahan = "Hazmat";
                                    break;
                                default:
                                    $valTambahan = $tambahanData[$i];
                                    break;
                            }

                            $pdf->WriteHTML('<td style="text-align:center;">V</td>');
                            $pdf->WriteHTML('<td style="text-align:center;">' . $valTambahan . '</td>');
                        } else {
                            $pdf->WriteHTML('<td style="text-align:center;">&nbsp;</td>'); // Sel kosong jika tidak ada data tambahan
                            $pdf->WriteHTML('<td style="text-align:center;">&nbsp;</td>');
                        }

                        // Kolom Keterangan Lain
                        if (isset($keteranganLainData[$i])) {
                            $pdf->WriteHTML('<td style="text-align:center;">' . $keteranganLainData[$i] . '</td>');
                        } else {
                            $pdf->WriteHTML('<td style="text-align:center;">&nbsp;</td>'); // Sel kosong jika tidak ada keterangan lain
                        }

                        $pdf->WriteHTML('</tr>');
                    }
                } else {
                    // Mengisi semua baris dengan kosong jika "tambahan" kosong
                    for ($i = 0; $i < 12; $i++) {
                        $pdf->WriteHTML('
                                <tr>
                                    <td style="text-align:center;">&nbsp;</td>
                                    <td style="text-align:center;"></td>
                                    <td style="text-align:center;"></td>
                                </tr>');
                    }
                }
                $pdf->WriteHTML('</tbody></table>');
            }

            $groupedKategori = [];
            $groupedSampler = [];
            $groupedDate = [];
            $groupedJamMulai = [];
            $groupedJamSelesai = [];
            foreach ($sampling_plan->jadwal as $item) {
                array_push($groupedKategori, json_decode($item->kategori));
                array_push($groupedSampler, $item->sampler);
                array_push($groupedDate, $item->tanggal);
                array_push($groupedJamMulai, $item->jam_mulai);
                array_push($groupedJamSelesai, $item->jam_selesai);
            }

            $kategories = collect($groupedKategori)->flatten()->unique()->values()->toArray();
            $samplers = array_unique($groupedSampler);
            $dates = array_unique($groupedDate);
            $jamMulai = array_unique($groupedJamMulai);
            $jamSelesai = array_unique($groupedJamSelesai);

            $groupedData = [];
            foreach ($kategories as $item) {
                $parts = explode(" - ", $item);
                $kategori = $parts[0];

                if (array_key_exists($kategori, $groupedData)) {
                    $groupedData[$kategori]++;
                } else {
                    $groupedData[$kategori] = 1;
                }
            }

            if (is_array($groupedData) && count($groupedData) > 0) {
                $pdf->WriteHTML('
                        <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                            <thead class="text-center">
                                <tr>
                                    <th width="2%" style="padding: 5px !important;">NO</th>
                                    <th width="85%">PENGAMBILAN SAMPLING</th>
                                    <th width="13%">TITIK</th>
                                </tr>
                            </thead>
                            <tbody>');

                $i = 1;

                foreach ($groupedData as $kategori => $jumlah) {
                    $pdf->WriteHTML(
                        '<tr>
                                    <td style="vertical-align: middle; text-align:center;font-size: 11px;">' . $i++ . '</td>
                                    <td style="font-size: 11px; padding: 5px;"><b style="font-size: 11px;">' . $kategori . '</b>'
                    );

                    $pdf->WriteHTML(
                        '<td style="font-size: 11px; padding: 5px;text-align:center;">' . $jumlah . '</td></tr>'
                    );
                }
                $pdf->WriteHTML('</tbody></table>');

                $pdf->WriteHTML('
                        <table class="table table-bordered" style="font-size: 8px; margin-top:5px;" width="100%">
                            <thead class="text-center">
                                <tr>
                                    <th colspan="6" style="text-align:center;">PENJADWALAN SAMPLING</th>
                                </tr>
                                <tr>
                                    <th class="text-center">Tanggal</th>
                                    <th class="text-center">Jam Mulai</th>
                                    <th class="text-center">Jam Selesai</th>
                                    <th colspan="3" class="text-center">Sampler</th>
                                </tr>
                            </thead>
                            <tbody>');

                $pdf->WriteHTML('
                        <tr>
                            <td style="text-align:center; vertical-align: middle;">' . implode(", ", array_map([$this, 'tanggal_indonesia'], array_map(function ($date) {
                    return date("Y-m-d", strtotime($date));
                }, $dates))) . '</td>
                            <td style="text-align:center; vertical-align: middle;">' . min($jamMulai) . '</td>
                            <td style="vertical-align: middle; text-align:center;">' . max($jamSelesai) . '</td>
                            <td colspan="3" style="text-align:center; vertical-align: middle;">' . implode(", ", $samplers) . '</td>
                        </tr>');

                // $pdf->WriteHTML('
                //     <tr>
                //         <td style="text-align:center; vertical-align: middle;">' . implode(", ", array_map(function ($date) {
                //     $formattedDate = date("Y-m-d", strtotime($date));
                //     if ($formattedDate !== false) {
                //         return $this->tanggal_indonesia($formattedDate);
                //     }
                //     return '';
                // }, $dates)) . '</td>
                //         <td style="text-align:center; vertical-align: middle;">' . min($jamMulai) . '</td>
                //         <td style="vertical-align: middle; text-align:center;">' . max($jamSelesai) . '</td>
                //         <td colspan="3" style="text-align:center; vertical-align: middle;">' . implode(", ", $samplers) . '</td>
                //     </tr>');

                $pdf->WriteHTML('</tbody></table>');
            }

            $dir = public_path('sampling_plan/');

            if (!file_exists($dir)) mkdir($dir, 0777, true);

            $filePath = $dir . '/' . $fileName;

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);

            $updatedSP = SamplingPlan::where('id', $sampling_plan->id)->first();
            $updatedSP->filename = $fileName;
            $updatedSP->save();

            return $fileName;
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    private function tanggal_indonesia($tanggal, $mode = '')
    {
        $bulan = array(
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );

        switch (date('D', strtotime($tanggal))) {
            case "Sun":
                $hari = "Minggu";
                break;
            case "Mon":
                $hari = "Senin";
                break;
            case "Tue":
                $hari = "Selasa";
                break;
            case "Wed":
                $hari = "Rabu";
                break;
            case "Thu":
                $hari = "Kamis";
                break;
            case "Fri":
                $hari = "Jum'at";
                break;
            case "Sat":
                $hari = "Sabtu";
                break;
        }

        $var = explode('-', $tanggal);
        if ($mode == 'period') {
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        } else if ($mode == 'hari') {
            return $hari . ' / ' . $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        }
    }
}
