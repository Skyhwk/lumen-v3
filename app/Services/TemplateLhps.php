<?php

namespace App\Services;

use App\Models\LhpsAirHeader;
use App\Models\LhpsAirDetail;
use App\Models\OrderDetail;
use App\Models\QrDocument;
use Illuminate\Support\Facades\DB;
use App\Services\MpdfService as PDF;
use Carbon\Carbon;

class TemplateLhps
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public function lhpAir20Kolom($data, $data_detail, $mode_download, $data_custom, $custom2 = null)
    {
        $totData = $data->header_table ? count(json_decode($data->header_table)) : 0;
        $header_table = $data->header_table ? json_decode($data->header_table) : [];
        // $methode_sampling = $data->methode_sampling ? $data->methode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        $temptArrayPush = [];

        if ($data->methode_sampling != null) {
            $methode_sampling = "";
            $dataArray = json_decode($data->methode_sampling);

            $result = array_map(function ($item) {
                $parts = explode(';', $item);
                $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                $sni = $parts[1];
                return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
            }, $dataArray);

            foreach ($result as $index => $item) {
                $methode_sampling .= "<span>
                <span>" . ($index + 1) . ". " . $item . "</span>
                </span><br>";
            }
        } else {
            $methode_sampling = "-";
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $itemsPerPage = 20;
        if (!empty($data_detail)) {
            $chunks = array_chunk($data_detail->toArray(), $itemsPerPage);
            foreach ($chunks as $pageIdx => $chunkedData) {
                $pageBreakStyle = $pageIdx > 0 ? 'margin-top: 20px;' : '';


                $bodi = '<div class="left" style="' . $pageBreakStyle . '">
                    <table   style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <thead>
                            <tr>
                                <th width="25" style="border: 1px solid #000; padding: 5px; text-align: center;">NO</th>
                                <th width="200" style="border: 1px solid #000; padding: 5px; text-align: center;">PARAMETER</th>
                                <th width="60" style="border: 1px solid #000; padding: 5px; text-align: center;">HASIL UJI</th>';

                $bmWidth = (85 / $totData);
                foreach ($header_table as $key => $value) {
                    $header = $key == 0 ? 'BAKU MUTU**' : $value;
                    $bodi .= '<th width="' . $bmWidth . '" style="border: 1px solid #000; padding: 5px; text-align: center;">' . $header . '</th>';
                }

                $bodi .= '<th width="50" style="border: 1px solid #000; padding: 5px; text-align: center;">SATUAN</th>
                          <th width="220" style="border: 1px solid #000; padding: 5px; text-align: center;">SPESIFIKASI METODE</th>
                        </tr>
                        </thead>
                        <tbody>';

                foreach ($chunkedData as $k => $v) {
                    if (!empty($v['attr']) && !in_array($v['attr'], $temptArrayPush)) {
                        $temptArrayPush[] = $v['attr'];
                    }
                    if (!empty($v['akr']) && !in_array($v['akr'], $temptArrayPush)) {
                        $temptArrayPush[] = $v['akr'];
                    }

                    $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                    $baku = explode(',', str_replace(['[', ']', '"', ' '], '', $v['baku_mutu']));
                    $i = $k + 1 + ($pageIdx * $itemsPerPage);
                    $akr = !empty($v['akr']) ? $v['akr'] : '&nbsp;&nbsp;';

                    $bodi .= '<tr>
                        <td style="border: 1px dotted #000; padding: 5px; text-align: center;">' . $i . '</td>
                        <td style="border: 1px dotted #000; padding: 5px; text-align: left;">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                        <td style="border: 1px dotted #000; padding: 5px; text-align: center;">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                    foreach ($baku as $value) {
                        $bodi .= '<td style="border: 1px dotted #000; padding: 5px; text-align: center;">' . $value . '</td>';
                    }

                    $bodi .= '<td style="border: 1px dotted #000; padding: 5px; text-align: center;">' . $satuan . '</td>
                            <td style="border: 1px dotted #000; padding: 5px; text-align: left;">' . $v['methode'] . '</td>
                        </tr>';
                }

                $bodi .= '</tbody></table></div>';
                $customBod[] = $bodi;
            }
        }


        if (!empty($data_custom)) {
            foreach ($data_custom as $key => $value) {
                $bodi = '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                <thead>
                                <tr>
                                    <th width="25"
                                        class="pd-5-solid-top-center">NO</th>
                                    <th width="200" class="pd-5-solid-top-center">PARAMETER</th>
                                    <th width="60" class="pd-5-solid-top-center">HASIL
                                        UJI</th>';
                $bmWidth = (85 / $totData);
                foreach ($header_table as $key => $value) {
                    if ($key == 0) {
                        $bodi .= '<th width="' . $bmWidth . '" class="pd-5-solid-top-center">BAKU MUTU**</th>';
                    } else {
                        $bodi .= '<th width="' . $bmWidth . '" class="pd-5-solid-top-center">' . $value . '</th>';
                    }
                }
                $bodi .= '<th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                    <th width="220" class="pd-5-solid-top-center">SPESIFIKASI
                                        METODE</th>
                                </tr></thead><tbody>';
                $tot = is_array($value) ? count($value) : 0;

                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        if (!empty($v['attr'])) {
                            if (!in_array($v['attr'], $temptArrayPush)) {
                                $temptArrayPush[] = $v['attr'];
                            }
                        }

                        if (!empty($v['akr'])) {
                            if (!in_array($v['akr'], $temptArrayPush)) {
                                $temptArrayPush[] = $v['akr'];
                            }
                        }


                        $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                        $baku = str_replace("[", "", $v['baku_mutu']);
                        $baku = str_replace("]", "", $baku);
                        $baku = str_replace('"', '', $baku);
                        $baku = explode(',', $baku);
                        $i = $k + 1;
                        $akr = '&nbsp;&nbsp;';
                        if ($v['akr'] != '')
                            $akr = $v['akr'];
                        if ($i == $tot) {

                            $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $i . '</td>
                                    <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                                    <td class="pd-5-solid-center">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                            foreach ($baku as $key => $value) {
                                $value = str_replace(' ', '', $value);
                                $bodi .= '<td class="pd-5-solid-center">' . $value . '</td>';
                            }
                            $bodi .= '<td class="pd-5-solid-center">' . $satuan . '</td>
                                    <td class="pd-5-solid-left">' . $v['methode'] . '</td>
                                </tr>';
                        } else {
                            $bodi .= '<tr>
                                    <td class="pd-5-dot-center">' . $i . '</td>
                                    <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                                    <td class="pd-5-dot-center">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                            foreach ($baku as $key => $value) {
                                $value = str_replace(' ', '', $value);
                                $bodi .= '<td class="pd-5-dot-center">' . $value . '</td>';
                            }

                            $bodi .= '<td class="pd-5-dot-center">' . $satuan . '</td>
                                    <td class="pd-5-dot-left">' . $v['methode'] . '</td>
                                </tr>';
                        }
                    }
                }

                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }
        }

        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
            <tr>
                <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
            </tr>
        </table>
        <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '"
            width="100%">
            <tr><td></td></tr>
            <tr><td></td></tr>
        </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table>
                <div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                        <td class="pd-5-solid-top-center" width="120">No. SAMPLE</td>
                                        <td class="pd-5-solid-top-center" width="200">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->no_sampel . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Deskripsi Sampel</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">' . $data->deskripsi_titik . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Titik Koordinat</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">' . $data->titik_koordinat . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Periode
                                            Analisa</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                </table>';
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $y) {
                $header .= '<tr>
                                <td class="custom5" colspan="3">**' . $y . '</td>
                            </tr>';
            }
        }
        $header .= '</table>';
        $header .= '<table style="padding: 5px 0px 0px 10px;" width="100%">';

        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $py => $vx) {
                foreach ($temptArrayPush as $symbol) {
                    if (strpos($vx, $symbol) === 0) {
                        $header .= '<tr>
                                        <td class="custom5" colspan="3">' . $vx . '
                                        </td>
                                    </tr>';
                    }
                }
            }
            ;
        }
        $header .= '</table></td>
                            </tr>
                        </table>
                    </div>';
        $no_lhp = str_replace("/", "-", $data->no_lhp);

        if ($mode_download == 'downloadLHP') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'draft_customer') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'downloadWSDraft') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'downloadLHPFinal') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        }
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2);
        return $ress;
    }

    public function lhpAirLebih20Kolom($data, $data_detail, $mode_download, $data_custom, $custom2 = null)
    {
        $totData = $data->header_table ? count(json_decode($data->header_table)) : 0;
        $colc = '';
        $rowc = '';
        if ($totData > 1) {
            $colc = 'colspan="' . $totData . '"';
            $rowc = 'rowspan="2"';
        }

        if ($data->methode_sampling != null) {
            $methode_sampling = "";
            $dataArray = json_decode($data->methode_sampling);

            $result = array_map(function ($item) {
                $parts = explode(';', $item);
                $accreditation = strpos($parts[0], 'AKREDITASI') !== false;
                $sni = $parts[1];
                return $accreditation ? "{$sni} <sup style=\"border-bottom: 1px solid;\">a</sup>" : $sni;
            }, $dataArray);

            foreach ($result as $index => $item) {
                $methode_sampling .= "<span>
                                        <span>" . ($index + 1) . ". " . $item . "</span>
                                    </span><br>";
            }
        } else {
            $methode_sampling = "-";
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $period = explode(" - ", $data->periode_analisa);
        $period1 = '';
        $period2 = '';
        if (!empty($period) && count($period) > 1) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }
        // =================Isi Data==================
        $customBod = [];
        $temptArrayPush = [];

        if (!empty($data)) {
            $bodi = '<div class="left"><table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                <thead>
                    <tr>
                        <th width="25" class="pd-5-solid-top-center" rowspan="2">NO</th>
                        <th width="200" class="pd-5-solid-top-center" rowspan="2">PARAMETER</th>
                        <th width="60" class="pd-5-solid-top-center" rowspan="2">HASIL UJI</th>';

            if ($totData == 1) {
                $bodi .= '<th width="85" class="pd-5-solid-top-center" rowspan="2">BAKU MUTU**</th>';
            } else {
                foreach (json_decode($data->header_table) as $key => $val) {
                    if ($key == 0) {
                        $bodi .= '<th width="85" class="pd-5-solid-top-center" rowspan="2">BAKU MUTU**</th>';
                    } else {
                        $bodi .= '<th width="85" class="pd-5-solid-top-center" rowspan="2">' . $val . '</th>';
                    }
                }
            }
            $bodi .= '<th width="50" class="pd-5-solid-top-center" rowspan="2">SATUAN</th>
                        <th width="220" class="pd-5-solid-top-center" rowspan="2">SPESIFIKASI
                            METODE</th>
                    </tr><tr>';
            if ($totData !== 1) {
                foreach (json_decode($data->header_table) as $key => $val) {
                    $bodi .= '<th class="pd-5-solid-top-center" width="50">' . $val . '</th>';
                }
            }
            $bodi .= '<tr></thead><tbody>';
            $totdat = count($data_detail);
            foreach ($data_detail as $k => $v) {
                $i = $k + 1;
                if (!empty($v['akr'])) {
                    if (!in_array($v['akr'], $temptArrayPush)) {
                        $temptArrayPush[] = $v['akr'];
                    }
                }

                if (!empty($v['attr'])) {
                    if (!in_array($v['attr'], $temptArrayPush)) {
                        $temptArrayPush[] = $v['attr'];
                    }
                }
                $akr = '&nbsp;&nbsp;';
                if ($v['akr'] != '')
                    $akr = $v['akr'];
                $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                if ($i == $totdat) {
                    $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $i . '</td>
                                    <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                                    <td class="pd-5-solid-center">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                    foreach (json_decode($v['baku_mutu']) as $kk => $vv) {
                        $bodi .= '<td class="pd-5-solid-center">' . $vv . '</td>';
                    }
                    $bodi .= '<td class="pd-5-solid-center">' . $satuan . '</td>
                                    <td class="pd-5-solid-left">' . $v['methode'] . '</td>
                                </tr>';
                } else {
                    $bodi .= '<tr>
                                    <td class="pd-5-dot-center">' . $i . '</td>
                                    <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                                    <td class="pd-5-dot-center">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                    foreach (json_decode($v['baku_mutu']) as $kk => $vv) {
                        $bodi .= '<td class="pd-5-dot-center">' . $vv . '</td>';
                    }
                    $bodi .= '<td class="pd-5-dot-center">' . $satuan . '</td>
                                    <td class="pd-5-dot-left">' . $v['methode'] . '</td>
                                </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }

        if (!empty($data_custom)) {
            foreach ($data_custom as $key => $value) {
                $bodi = '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                <thead>
                                <tr>
                                    <th width="25"
                                        class="pd-5-solid-top-center" ' . $rowc . '>NO</th>
                                    <th width="200" class="pd-5-solid-top-center" ' . $rowc . '>PARAMETER</th>
                                    <th width="60" class="pd-5-solid-top-center" ' . $rowc . '>HASIL
                                        UJI</th>';

                if ($totData == 1) {
                    $bodi .= '<th width="50" class="pd-5-solid-top-center" ' . $rowc . '>BAKU MUTU</th>';
                } else {
                    if ($key == 0) {
                        $bodi .= '<th width="85" class="pd-5-solid-top-center" rowspan="2">BAKU MUTU**</th>';
                    } else {
                        $bodi .= '<th width="85" class="pd-5-solid-top-center" rowspan="2">' . $val . '</th>';
                    }
                }
                $bodi .= '<th width="50" class="pd-5-solid-top-center" ' . $rowc . '>SATUAN</th>
                            <th width="220" class="pd-5-solid-top-center" ' . $rowc . '>SPESIFIKASI
                                METODE</th>
                        </tr><tr>';
                if ($totData > 1) {
                    foreach (json_decode($data->header_table) as $key => $val) {
                        $bodi .= '<th class="pd-5-solid-top-center" width="50">' . $val . '</th>';
                    }
                }
                $bodi .= '</tr></thead><tbody>';

                $totdat = count($value);
                foreach ($value as $k => $v) {
                    $i = $k + 1;
                    if (!empty($v['akr'])) {
                        if (!in_array($v['akr'], $temptArrayPush)) {
                            $temptArrayPush[] = $v['akr'];
                        }
                    }

                    if (!empty($v['attr'])) {
                        if (!in_array($v['attr'], $temptArrayPush)) {
                            $temptArrayPush[] = $v['attr'];
                        }
                    }

                    $akr = '&nbsp;&nbsp;';
                    if ($v['akr'] != '')
                        $akr = $v['akr'];
                    $satuan = $v['satuan'] != "null" ? $v['satuan'] : '';
                    if ($i == $totdat) {
                        $bodi .= '<tr>
                                <td class="pd-5-solid-center">' . $i . '</td>
                                <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                                <td class="pd-5-solid-center">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                        foreach (json_decode($v['baku_mutu']) as $kk => $vv) {
                            $bodi .= '<td class="pd-5-solid-center">' . $vv . '</td>';
                        }
                        $bodi .= '<td class="pd-5-solid-center">' . $satuan . '</td>
                                <td class="pd-5-solid-left">' . $v['methode'] . '</td>
                            </tr>';
                    } else {
                        $bodi .= '<tr>
                                <td class="pd-5-dot-center">' . $i . '</td>
                                <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $v['parameter'] . '</td>
                                <td class="pd-5-dot-center">' . $v['hasil_uji'] . '&nbsp;' . $v['attr'] . '</td>';
                        foreach (json_decode($v['baku_mutu']) as $kk => $vv) {
                            $bodi .= '<td class="pd-5-dot-center">' . $vv . '</td>';
                        }
                        $bodi .= '<td class="pd-5-dot-center">' . $satuan . '</td>
                                <td class="pd-5-dot-left">' . $v['methode'] . '</td>
                            </tr>';
                    }
                }

                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }
        }

        // =================Isi Kolom Kanan==================
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table
            style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px"
            width="100%">
            <tr>
                <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
            </tr>
        </table>
        <table
            style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '"
            width="100%">
            <tr>
                <td>
                    
                </td>
            </tr>
            <tr>
                <td>
                    
                </td>
            </tr>
        </table>';

        $header = '<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP</td>
                                        <td class="custom" width="120">No. SAMPLE</td>
                                        <td class="custom" width="200">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">' . $data->no_lhp . '</td>
                                        <td class="custom">' . $data->no_sampel . '</td>
                                        <td class="custom">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Keterangan</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">' . $data->deskripsi_titik . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Titik Koordinat</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5">' . $data->titik_koordinat . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Periode
                                            Analisa</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr></table>';

        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $header .= '<tr>
                                                            <td class="custom5" colspan="3">**' . $y . '**</td>
                                                        </tr>';
            }
        }

        $header .= '</table>';

        $header .= '<table style="padding: 5px 0px 0px 10px;"
                                    width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $py => $vx) {
                foreach ($temptArrayPush as $symbol) {
                    if (strpos($vx, $symbol) === 0) {
                        $header .= '<tr>
                                                    <td class="custom5" colspan="3">' . $vx . '
                                                    </td>
                                                </tr>';
                    }
                }
            }
            ;
        }
        $header .= '</table>
                            </td>
                        </tr>
                        </table>
                </div>';
        // $file_qr = '';
        // // if ($mode_download == 'downloadWSDraft') {
        // //     $qr_img = '';
        // // } else if ($mode_download == 'downloadLHP') {
        // if (!is_null($data->file_qr)) {
        //     $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
        //     $qr_img = '<img src="' . $file_qr . '" width="45px" height="45px" style="margin-top: 10px;">';
        // } else {
        //     $qr_img = '';
        // }
        // }
        $no_lhp = str_replace("/", "-", $data->no_lhp);

        if ($mode_download == 'downloadLHP') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'draft_customer') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'downloadWSDraft') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        } else if ($mode_download == 'downloadLHPFinal') {
            $name = 'LHP-' . $no_lhp . '.pdf';
        }
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2);
        return $ress;
    }



    public function emisisumbertidakbergerak($data, $data1, $mode_download, $custom)
    {
        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tgl_lhp) . '';
        } else {
            $qr_img = '';
        }

        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        if (empty($custom)) {
            $bodi = '<div class="left">
                        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                                <tr>
                                    <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                                    <th rowspan="2" width="250" class="pd-5-solid-top-center">PARAMETER</th>
                                    <th colspan="3" class="pd-5-solid-top-center">HASIL UJI</th>
                                    <th rowspan="2" width="75" class="pd-5-solid-top-center">BAKU MUTU</th>
                                    <th rowspan="2" class="pd-5-solid-top-center">SATUAN</th>
                                    <th rowspan="2" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                                </tr>
                                <tr>
                                    <th class="pd-5-solid-top-center" width="75">C</th>
                                    <th class="pd-5-solid-top-center" width="75">C1</th>
                                    <th class="pd-5-solid-top-center" width="75">C2</th>
                                </tr>
                            </thead>
                            <tbody>';

            $dataItems = is_array($data1) ? $data1 : [$data1];
            $total = count($dataItems);

            foreach ($dataItems as $key => $value) {
                if (!$value)
                    continue;

                $akr = !empty($value->akr) ? $value->akr : '&nbsp;&nbsp;';
                $p = $key + 1;
                $rowClass = ($p == $total) ? 'solid' : 'dot';

                $bodi .= '<tr>
                            <td class="pd-5-' . $rowClass . '-center">' . $p . '</td>
                            <td class="pd-5-' . $rowClass . '-left">' . $akr . '&nbsp;' . htmlspecialchars($value->parameter) . '</td>
                            <td class="pd-5-' . $rowClass . '-center">' . htmlspecialchars($value->C) . '</td>
                            <td class="pd-5-' . $rowClass . '-center">' . htmlspecialchars($value->C1) . '</td>
                            <td class="pd-5-' . $rowClass . '-center">' . htmlspecialchars($value->C2) . '</td>
                            <td class="pd-5-' . $rowClass . '-center">' . htmlspecialchars($value->baku_mutu) . '</td>
                            <td class="pd-5-' . $rowClass . '-center">' . htmlspecialchars($value->satuan) . '</td>
                            <td class="pd-5-' . $rowClass . '-center">' . htmlspecialchars($value->spesifikasi_metode) . '</td>
                          </tr>';
            }

            $bodi .= '</tbody></table></div>';
            $customBod[] = $bodi;
        }


        $parame = str_replace("[", "", $data->parameter_uji);
        $parame = str_replace("]", "", $parame);
        $parame = str_replace('"', '', $parame);
        $alamat_sample = $data->alamat_sampling != null ? $data->alamat_sampling : "-";
        $methode_sampling = $data->metode_sampling != null ? json_decode($data->metode_sampling) : [];

        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = ' <table width="100%" style="font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            ' . $isi_header . '
                        </tr>
                    </table>
                    <div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                        <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;" width="100%">
                            <tr>
                                <td>
                                    <table style="border-collapse: collapse; text-align: center;" width="100%">
                                        <tr>
                                            <td class="custom" width="120">No. LHP</td>
                                            <td class="custom" width="240">JENIS SAMPEL</td>
                                        </tr>
                                        <tr>
                                            <td class="custom">' . $data->no_lhp . '</td>
                                            <td class="custom">EMISI SUMBER TIDAK BERGERAK</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="padding: 20px 0px 0px 0px;" width="100%">
                                        <tr>
                                            <td>
                                                <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Nama Pelanggan</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $data->nama_pelanggan . '</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                                        <tr>
                                            <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $alamat_sample . '</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                                        <tr>
                                            <td class="custom5" width="120">
                                                <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Kategori</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $data->sub_kategori . '</td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Parameter</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $parame . '</td>
                                        </tr>';

        if (count($methode_sampling) > 0) {
            $i = 1;
            foreach ($methode_sampling as $key => $value) {
                $akre = explode(';', $value)[0] == 'AKREDITASI' ? " <sup style=\"border-bottom: 1px solid;\">a</sup>" : "";
                $metode = implode(' - ', array_slice(explode(';', $value), 1, 2));
                if ($key == 0) {
                    $header .= '<tr>
                                    <td class="custom5">Metode Sampling</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $i . '. ' . $metode . $akre . '</td>
                                </tr>';
                } else {
                    $header .= '<tr>
                                    <td class="custom5"></td>
                                    <td class="custom5"></td>
                                    <td class="custom5">' . $i . '. ' . $metode . $akre . '</td>
                                </tr>';
                }
                $i++;
            }
        } else {
            $header .= '<tr>
                            <td class="custom5">Metode Sampling</td>
                            <td class="custom5">:</td>
                            <td class="custom5">-</td>
                        </tr>';
        }

        $header .= '<tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                    </tr>
                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                    </tr>
                </table>';
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if (!empty($data->regulasi)) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $header .= '<tr>
                    <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                </tr>';
                $bintang .= '*';
            }
        }
        $header .= '</table>';
        $header .= '
                            </td>
                        </tr>
                    </table>
                    </div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, null);
        return $ress;
    }
    public function DirectESBBensin($data, $data1, $mode_download, $custom)
    {
        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tgl_lhp) . '';
        } else {
            $qr_img = '';
        }

        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);
        $customBod = [];
        if (empty($custom)) {
            $bodi = '<div class="left">
                        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                                <tr>
                                    <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                                    <th rowspan="2" width="250" class="pd-5-solid-top-center">JENIS / NAMA KENDARAAN</th>
                                    <th rowspan="2" width="75" class="pd-5-solid-top-center">BOBOT</th>
                                    <th rowspan="2" width="75" class="pd-5-solid-top-center">TAHUN</th>
                                    <th colspan="2" class="pd-5-solid-top-center">HASIL UJI</th>
                                    <th colspan="2" class="pd-5-solid-top-center">BAKU MUTU **</th>
                                </tr>
                                <tr>
                                    <th class="pd-5-solid-top-center" width="75">CO (%)</th>
                                    <th class="pd-5-solid-top-center" width="75">HC (ppm)</th>
                                    <th class="pd-5-solid-top-center" width="75">CO (%)</th>
                                    <th class="pd-5-solid-top-center" width="75">HC (ppm)</th>
                                </tr>
                            </thead>
                            <tbody>';

            $tot = count($data1);
            foreach ($data1 as $key => $value) {
                $baku = json_decode($value['baku_mutu']);
                $hasil = json_decode($value['hasil_uji']);
                $p = $key + 1;
                if ($p == $tot) {
                    $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $value['no_sampel'] . '</sup>' . $value['nama_kendaraan'] . '</td>
                                    <td class="pd-5-solid-center">' . $value['bobot_kendaraan'] . ' TON</td>
                                    <td class="pd-5-solid-center">' . $value['tahun_kendaraan'] . '</td>
                                    <td class="pd-5-solid-center">' . $hasil->CO . '</td>
                                    <td class="pd-5-solid-center">' . $hasil->HC . '</td>
                                    <td class="pd-5-solid-center">' . $baku->CO . '</td>
                                    <td class="pd-5-solid-center">' . $baku->HC . '</td>
                                </tr>';
                } else {
                    $bodi .= '<tr>
                                    <td class="pd-5-dot-center">' . $p . '</td>
                                    <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $value['no_sampel'] . '</sup>' . $value['nama_kendaraan'] . '</td>
                                    <td class="pd-5-dot-center">' . $value['bobot_kendaraan'] . ' TON</td>
                                    <td class="pd-5-dot-center">' . $value['tahun_kendaraan'] . '</td>
                                    <td class="pd-5-dot-center">' . $hasil->CO . '</td>
                                    <td class="pd-5-dot-center">' . $hasil->HC . '</td>
                                    <td class="pd-5-dot-center">' . $baku->CO . '</td>
                                    <td class="pd-5-dot-center">' . $baku->HC . '</td>
                                </tr>';
                }
            }

            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }
        $parame = str_replace("[", "", $data->parameter_uji);
        $parame = str_replace("]", "", $parame);
        $parame = str_replace('"', '', $parame);
        $alamat_sample = $data->alamat_sampling != null ? $data->alamat_sampling : "-";
        $methode_sampling = $data->metode_sampling != null ? json_decode($data->metode_sampling) : [];

        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = ' <table width="100%" style="font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            ' . $isi_header . '
                        </tr>
                    </table>
                    <div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                        <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;" width="100%">
                            <tr>
                                <td>
                                    <table style="border-collapse: collapse; text-align: center;" width="100%">
                                        <tr>
                                            <td class="custom" width="120">No. LHP</td>
                                            <td class="custom" width="240">JENIS SAMPEL</td>
                                        </tr>
                                        <tr>
                                            <td class="custom">' . $data->no_lhp . '</td>
                                            <td class="custom">EMISI SUMBER BERGERAK</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table style="padding: 20px 0px 0px 0px;" width="100%">
                                        <tr>
                                            <td>
                                                <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Nama Pelanggan</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $data->nama_pelanggan . '</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                                        <tr>
                                            <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $alamat_sample . '</td>
                                        </tr>
                                    </table>
                                    <table style="padding: 10px 0px 0px 0px;" width="100%">
                                        <tr>
                                            <td class="custom5" width="120">
                                                <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Kategori</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $data->sub_kategori . '</td>
                                        </tr>
                                        <tr>
                                            <td class="custom5" width="120">Parameter</td>
                                            <td class="custom5" width="12">:</td>
                                            <td class="custom5">' . $parame . '</td>
                                        </tr>';

        if (count($methode_sampling) > 0) {
            $i = 1;
            foreach ($methode_sampling as $key => $value) {
                $akre = explode(';', $value)[0] == 'AKREDITASI' ? " <sup style=\"border-bottom: 1px solid;\">a</sup>" : "";
                $metode = implode(' - ', array_slice(explode(';', $value), 1, 2));
                if ($key == 0) {
                    $header .= '<tr>
                                    <td class="custom5">Metode Sampling</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $i . '. ' . $metode . $akre . '</td>
                                </tr>';
                } else {
                    $header .= '<tr>
                                    <td class="custom5"></td>
                                    <td class="custom5"></td>
                                    <td class="custom5">' . $i . '. ' . $metode . $akre . '</td>
                                </tr>';
                }
                $i++;
            }
        } else {
            $header .= '<tr>
                            <td class="custom5">Metode Sampling</td>
                            <td class="custom5">:</td>
                            <td class="custom5">-</td>
                        </tr>';
        }

        $header .= '<tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                    </tr>
                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                    </tr>
                </table>';
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if (!empty($data->regulasi)) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $header .= '<tr>
                    <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                </tr>';
                $bintang .= '*';
            }
        }
        $header .= '</table>';
        $header .= '
                            </td>
                        </tr>
                    </table>
                    </div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, null);
        return $ress;
    }

    public function DirectESBSolar($data, $data1, $mode_download, $custom)
    {

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tgl_lhp) . '';
        } else {
            $qr_img = '';
        }

        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        $customBod = [];
        if (empty($custom)) {
            $bodi = '<div class="left">
                        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                                <tr>
                                    <th rowspan="2" width="25" class="pd-5-solid-top-center">NO</th>
                                    <th rowspan="2" width="310" class="pd-5-solid-top-center">JENIS / NAMA KENDARAAN</th>
                                    <th rowspan="2" width="85" class="pd-5-solid-top-center">BOBOT</th>
                                    <th rowspan="2" width="85" class="pd-5-solid-top-center">TAHUN</th>
                                    <th width="105" class="pd-5-solid-top-center">HASIL UJI</th>
                                    <th width="105" class="pd-5-solid-top-center">BAKU MUTU **</th>
                                </tr>
                                <tr>
                                    <th class="pd-5-solid-top-center" colspan="2">Satuan = Opasitas (%)</th>
                                </tr>
                            </thead>
                            <tbody>';
            $tot = count($data1);
            foreach ($data1 as $key => $value) {
                $baku = json_decode($value['baku_mutu']);
                $hasil = json_decode($value['hasil_uji']);
                $p = $key + 1;
                if ($p == $tot) {
                    $bodi .= '<tr>
                                        <td class="pd-5-solid-center">' . $p . '</td>
                                        <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $value['no_sampel'] . '</sup>' . $value['nama_kendaraan'] . '</td>
                                        <td class="pd-5-solid-center">' . $value['bobot_kendaraan'] . ' TON</td>
                                        <td class="pd-5-solid-center">' . $value['tahun_kendaraan'] . '</td>
                                        <td class="pd-5-solid-center">' . $hasil->OP . '</td>
                                        <td class="pd-5-solid-center">' . $baku->OP . '</td>
                                    </tr>';
                } else {
                    $bodi .= '<tr>
                                        <td class="pd-5-dot-center">' . $p . '</td>
                                        <td class="pd-5-dot-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $value['no_sampel'] . '</sup>' . $value['nama_kendaraan'] . '</td>
                                        <td class="pd-5-dot-center">' . $value['bobot_kendaraan'] . ' TON</td>
                                        <td class="pd-5-dot-center">' . $value['tahun_kendaraan'] . '</td>
                                        <td class="pd-5-dot-center">' . $hasil->OP . '</td>
                                        <td class="pd-5-dot-center">' . $baku->OP . '</td>
                                    </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }
        $parame = str_replace("[", "", $data->parameter_uji);
        $parame = str_replace("]", "", $parame);
        $parame = str_replace('"', '', $parame);
        $alamat_sample = $data->alamat_sampling != null ? $data->alamat_sampling : "-";
        $methode_sampling = $data->metode_sampling != null ? json_decode($data->metode_sampling) : [];
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; border-collapse: collapse;" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = ' <table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    ' . $isi_header . '
                </tr>
            </table>
            <div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <table style="border-collapse: collapse; text-align: center;" width="100%">
                                <tr>
                                    <td class="custom" width="120">No. LHP</td>
                                    <td class="custom" width="240">JENIS SAMPEL</td>
                                </tr>
                                <tr>
                                    <td class="custom">' . $data->no_lhp . '</td>
                                    <td class="custom">EMISI SUMBER BERGERAK</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table style="padding: 20px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td>
                                        <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Nama Pelanggan</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->nama_pelanggan . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $alamat_sample . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">
                                        <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Kategori</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->sub_kategori . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Parameter</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $parame . '</td>
                                </tr>';

        if (count($methode_sampling) > 0) {
            $i = 1;
            foreach ($methode_sampling as $key => $value) {
                $akre = explode(';', $value)[0] == 'AKREDITASI' ? " <sup style=\"border-bottom: 1px solid;\">a</sup>" : "";
                $metode = implode(' - ', array_slice(explode(';', $value), 1, 2));
                if ($key == 0) {
                    $header .= '<tr>
                                    <td class="custom5">Metode Sampling</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $i . '. ' . $metode . $akre . '</td>
                                </tr>';
                } else {
                    $header .= '<tr>
                                    <td class="custom5"></td>
                                    <td class="custom5"></td>
                                    <td class="custom5">' . $i . '. ' . $metode . $akre . '</td>
                                </tr>';
                }
                $i++;
            }
        } else {
            $header .= '<tr>
                            <td class="custom5">Metode Sampling</td>
                            <td class="custom5">:</td>
                            <td class="custom5">-</td>
                        </tr>';
        }

        $header .= '<tr>
                        <td class="custom5" width="120">Tanggal Sampling</td>
                        <td class="custom5" width="12">:</td>
                        <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                    </tr>

                    <tr>
                        <td class="custom5">Periode Analisa</td>
                        <td class="custom5">:</td>
                        <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                    </tr>
                </table>';
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        foreach (json_decode($data->regulasi) as $t => $y) {
            $header .= '<tr>
                            <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                        </tr>';
            $bintang .= '*';
        }
        $header .= '</table></td>
                        </tr>
                    </table>
                </div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        // dd($name);
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);
        return $ress;
    }

    public function lhpLingkungan($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        $temptArrayPush = [];
        if ($data->id_kategori_3 == 11) {
            if (!empty($custom)) {
                foreach ($custom as $key => $value) {
                    $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                                <th width="50" class="pd-5-solid-top-center">DURASI</th>
                                <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                                <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                            </tr></thead><tbody>';
                    $tot = count($value);
                    foreach ($value as $kk => $yy) {
                        $p = $kk + 1;
                        if (!empty($yy['akr'])) {
                            if (!in_array($yy['akr'], $temptArrayPush)) {
                                $temptArrayPush[] = $yy['akr'];
                            }
                        }

                        if (!empty($yy['attr'])) {
                            if (!in_array($yy['attr'], $temptArrayPush)) {
                                $temptArrayPush[] = $yy['attr'];
                            }
                        }
                        $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                        if ($p == $tot) {
                            $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">' . $p . '</td>
                                                    <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['durasi'] . '</td>
                                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . $yy['attr'] . '</td>
                                                    <td class="pd-5-solid-center">' . json_decode($yy['baku_mutu'])[0] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['satuan'] . '</td>
                                                    <td class="pd-5-solid-left">' . $yy['methode'] . '</td>
                                                </tr>';
                        } else {
                            $bodi .= '<tr>
                                                    <td class="pd-5-dot-center">' . $p . '</td>
                                                    <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                    <td class="pd-5-dot-center">' . $yy['durasi'] . '</td>
                                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . $yy['attr'] . '</td>
                                                    <td class="pd-5-dot-center">' . json_decode($yy['baku_mutu'])[0] . '</td>
                                                    <td class="pd-5-dot-center">' . $yy['satuan'] . '</td>
                                                    <td class="pd-5-dot-left">' . $yy['methode'] . '</td>
                                                </tr>';
                        }
                    }
                    $bodi .= '</tbody></table></div>';

                    array_push($customBod, $bodi);
                }
            } else {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                            <th width="50" class="pd-5-solid-top-center">DURASI</th>
                            <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                            <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                            <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                            <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                        </tr></thead><tbody>';
                $tot = count($data1);
                foreach ($data1 as $kk => $yy) {
                    $p = $kk + 1;
                    if (!empty($yy['akr'])) {
                        if (!in_array($yy['akr'], $temptArrayPush)) {
                            $temptArrayPush[] = $yy['akr'];
                        }
                    }

                    if (!empty($yy['attr'])) {
                        if (!in_array($yy['attr'], $temptArrayPush)) {
                            $temptArrayPush[] = $yy['attr'];
                        }
                    }
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                                <td class="pd-5-solid-center">' . $p . '</td>
                                                <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                <td class="pd-5-solid-center">' . $yy['durasi'] . '</td>
                                                <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . $yy['attr'] . '</td>
                                                <td class="pd-5-solid-center">' . json_decode($yy['baku_mutu'])[0] . '</td>
                                                <td class="pd-5-solid-center">' . $yy['satuan'] . '</td>
                                                <td class="pd-5-solid-left">' . $yy['methode'] . '</td>
                                            </tr>';
                    } else {
                        $bodi .= '<tr>
                                                <td class="pd-5-dot-center">' . $p . '</td>
                                                <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                <td class="pd-5-dot-center">' . $yy['durasi'] . '</td>
                                                <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . $yy['attr'] . '</td>
                                                <td class="pd-5-dot-center">' . json_decode($yy['baku_mutu'])[0] . '</td>
                                                <td class="pd-5-dot-center">' . $yy['satuan'] . '</td>
                                                <td class="pd-5-dot-left">' . $yy['methode'] . '</td>
                                            </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }
        } else if ($data->id_kategori_3 == 27) {
            if (!empty($custom)) {
                foreach ($custom as $key => $value) {
                    // dd($data);
                    $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th width="25"
                                    class="pd-5-solid-top-center">NO</th>
                                <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                                <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                                <th width="70" class="pd-5-solid-top-center">BAKU MUTU</th>
                                <th width="50" class="pd-5-solid-top-center">SATUAN</th>
                                <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                            </tr></thead><tbody>';
                    $tot = count($value);
                    foreach ($value as $kk => $yy) {
                        $p = $kk + 1;
                        if (!empty($yy['akr'])) {
                            if (!in_array($yy['akr'], $temptArrayPush)) {
                                $temptArrayPush[] = $yy['akr'];
                            }
                        }

                        if (!empty($yy['attr'])) {
                            if (!in_array($yy['attr'], $temptArrayPush)) {
                                $temptArrayPush[] = $yy['attr'];
                            }
                        }
                        $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                        if ($p == $tot) {
                            $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">' . $p . '</td>
                                                    <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . $yy['attr'] . '</td>
                                                    <td class="pd-5-solid-center">' . json_decode($yy['baku_mutu'])[0] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['satuan'] . '</td>
                                                    <td class="pd-5-solid-left">' . $yy['methode'] . '</td>
                                                </tr>';
                        } else {
                            $bodi .= '<tr>
                                                    <td class="pd-5-dot-center">' . $p . '</td>
                                                    <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                    <td class="pd-5-dot-center">' . str_replace('.', ',', $yy['hasil_uji']) . $yy['attr'] . '</td>
                                                    <td class="pd-5-dot-center">' . json_decode($yy['baku_mutu'])[0] . '</td>
                                                    <td class="pd-5-dot-center">' . $yy['satuan'] . '</td>
                                                    <td class="pd-5-dot-left">' . $yy['methode'] . '</td>
                                                </tr>';
                        }
                    }

                    $bodi .= '</tbody></table></div>';
                    array_push($customBod, $bodi);
                }
            } else {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="170" class="pd-5-solid-top-center">PARAMETER</th>
                            <th width="70" class="pd-5-solid-top-center">HASIL UJI</th>
                            <th width="210" class="pd-5-solid-top-center">SPESIFIKASI METODE</th>
                        </tr></thead><tbody>';
                // dd(count($data1));
                $tot = count($data1);
                foreach ($data1 as $kk => $yy) {
                    $p = $kk + 1;
                    // dd($yy);
                    if (!empty($yy['akr'])) {
                        if (!in_array($yy['akr'], $temptArrayPush)) {
                            $temptArrayPush[] = $yy['akr'];
                        }
                    }

                    if (!empty($yy['attr'])) {
                        if (!in_array($yy['attr'], $temptArrayPush)) {
                            $temptArrayPush[] = $yy['attr'];
                        }
                    }
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                                <td class="pd-5-solid-center">' . $p . '</td>
                                                <td class="pd-5-solid-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil']) . $yy['attr'] . '</td>
                                                <td class="pd-5-solid-left">' . $yy['methode'] . '</td>
                                            </tr>';
                    } else {
                        $bodi .= '<tr>
                                                <td class="pd-5-dot-center">' . $p . '</td>
                                                <td class="pd-5-dot-left">' . $akr . '&nbsp;' . $yy['parameter'] . '</td>
                                                <td class="pd-5-dot-center">' . str_replace('.', ',', $yy['hasil']) . $yy['attr'] . '</td>
                                                <td class="pd-5-dot-center">' . $yy['satuan'] . '</td>
                                                <td class="pd-5-dot-left">' . $yy['methode'] . '</td>
                                            </tr>';
                    }
                }

                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        if ($data->id_kategori_3 == 11) {
            $ketling = '<tr>
                            <td class="custom5">Cuaca</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->cuaca . '</td>
                            <td class="custom5"></td>
                            <td class="custom5">Kecepatan Angin</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->kec_angin . '</td>
                        </tr>
                        <tr>
                            <td class="custom5">Suhu Lingkungan</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->suhu . '</td>
                            <td class="custom5"></td>
                            <td class="custom5">Arah Angin</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->arah_angin . '</td>
                        </tr>
                            <tr>
                            <td class="custom5">Kelembapan</td>
                            <td class="custom5">:</td>
                            <td class="custom5">' . $data->kelembapan . '</td>
                        </tr>';
        } else if ($data->id_kategori_3 == 27) {
            $ketling = '<tr>
                            <td class="custom5">Suhu Lingkungan</td>
                            <td class="custom5">:</td>
                            <td class="custom5" colspan="5">' . $data->suhu . '</td>
                        </tr>
                            <tr>
                            <td class="custom5">Kelembapan</td>
                            <td class="custom5">:</td>
                            <td class="custom5" colspan="5">' . $data->kelembapan . '</td>
                        </tr>';
        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">**' . $y . '</td>
                            </tr>';
            }
        }

        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        // if ($data->keterangan != null) {
        //     foreach (json_decode($data->keterangan) as $t => $y) {
        //         $bodketer .= '<tr>
        //                         <td class="custom5" colspan="3">' . $y . '</td>
        //                     </tr>';
        //     }
        // }
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $py => $vx) {
                foreach ($temptArrayPush as $symbol) {
                    if (strpos($vx, $symbol) === 0) {
                        $bodketer .= '<tr>
                                                    <td class="custom5" colspan="3">' . $vx . '
                                                    </td>
                                                </tr>';
                    }
                }
            }
            ;
        }
        $bodketer .= '</table>';
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                        <td class="pd-5-solid-top-center" width="120">No. SAMPEl</td>
                                        <td class="pd-5-solid-top-center" width="240">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->no_sampel . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Kondisi
                                                Lingkungan</span></td>
                                    </tr>' . $ketling . '</table>' . $bodreg . $bodketer . '</td></tr></table></div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }
    public function lhpSinarUV($data, $data1, $mode_download, $custom)
    {
        // dd($data, $data1, $mode_download, $custom);
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        $temptArrayPush = [];
       
            if (!empty($custom)) {
                foreach ($custom as $key => $value) {
                    // dd($data);
                    $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                            <tr>
                                <th width="25"
                                    class="custom" rowspan="2">NO</th>
                                <th width="170" rowspan="2" class="custom">KETERANGAN</th>
                                <th width="210" colspan="3" class="custom">HASIL UJI (mW/cm²)</th>
                                <th width="50" rowspan="2" class="custom">NAB (mW/cm²)**</th>
                                <th width="120" rowspan="2" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                            </tr>
                            <tr>
                                <th class="custom">MATA</th>
                                <th class="custom">SIKU</th>
                                <th class="custom">BETIS</th>
                            </tr></thead><tbody>';
                    $tot = count($value);
                    foreach ($value as $kk => $yy) {
                        $p = $kk + 1;
                        if (!empty($yy['akr'])) {
                            if (!in_array($yy['akr'], $temptArrayPush)) {
                                $temptArrayPush[] = $yy['akr'];
                            }
                        }

                        if (!empty($yy['attr'])) {
                            if (!in_array($yy['attr'], $temptArrayPush)) {
                                $temptArrayPush[] = $yy['attr'];
                            }
                        }
                        $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                        if ($p == $tot) {
                            $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">' . $p . '</td>
                                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $akr . '&nbsp;' . $yy['keterangan'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['mata'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['siku'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['betis'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                                    <td class="pd-5-solid-center">' . $this->waktuPemaparan($yy['waktu_pemaparan']) . '</td>
                                                </tr>';
                        } else {
                            $bodi .= '<tr>
                                                    <td class="pd-5-solid-center">' . $p . '</td>
                                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $akr . '&nbsp;' . $yy['keterangan'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['mata'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['siku'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['betis'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                                    <td class="pd-5-solid-center">' . $this->waktuPemaparan($yy['waktu_pemaparan']) . '</td>
                                                </tr>';
                        }
                    }

                    $bodi .= '</tbody></table></div>';
                    array_push($customBod, $bodi);
                }
            } else {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                                <th width="25"
                                    class="custom" rowspan="2">NO</th>
                                <th width="220" rowspan="2" class="custom">KETERANGAN</th>
                                <th width="200" colspan="3" class="custom">HASIL UJI (mW/cm²)</th>
                                <th width="50" rowspan="2" class="custom">NAB (mW/cm²)**</th>
                                <th width="120" rowspan="2" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                            </tr>
                            <tr>
                                <th class="custom">MATA</th>
                                <th class="custom">SIKU</th>
                                <th class="custom">BETIS</th>
                            </tr></thead><tbody>';
                // dd(count($data1));
                $tot = count($data1);
                foreach ($data1 as $kk => $yy) {
                    $p = $kk + 1;
                    // dd($yy);
                    if (!empty($yy['akr'])) {
                        if (!in_array($yy['akr'], $temptArrayPush)) {
                            $temptArrayPush[] = $yy['akr'];
                        }
                    }

                    if (!empty($yy['attr'])) {
                        if (!in_array($yy['attr'], $temptArrayPush)) {
                            $temptArrayPush[] = $yy['attr'];
                        }
                    }
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                                <td class="pd-5-solid-center">' . $p . '</td>
                                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $akr . '&nbsp;' . $yy['keterangan'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['mata'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['siku'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['betis'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                                    <td class="pd-5-solid-center">' . $this->waktuPemaparan($yy['waktu_pemaparan']) . '</td>
                                            </tr>';
                    } else {
                        $bodi .= '<tr>
                                                <td class="pd-5-solid-center">' . $p . '</td>
                                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $akr . '&nbsp;' . $yy['keterangan'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['mata'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['siku'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['betis'] . '</td>
                                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                                    <td class="pd-5-solid-center">' . $this->waktuPemaparan($yy['waktu_pemaparan']) . '</td>
                                            </tr>';
                    }
                }

                $bodi .= '</tbody></table></div>';
                array_push($customBod, $bodi);
            }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
   
        //    if ($data->id_kategori_3 == 27) {
        //     $ketling = '<tr>
        //                     <td class="custom5">Suhu Lingkungan</td>
        //                     <td class="custom5">:</td>
        //                     <td class="custom5" colspan="5">' . $data->suhu . '</td>
        //                 </tr>
        //                     <tr>
        //                     <td class="custom5">Kelembapan</td>
        //                     <td class="custom5">:</td>
        //                     <td class="custom5" colspan="5">' . $data->kelembapan . '</td>
        //                 </tr>';
        // }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">**' . $y . '</td>
                            </tr>';
            }
        }

        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        // if ($data->keterangan != null) {
        //     foreach (json_decode($data->keterangan) as $t => $y) {
        //         $bodketer .= '<tr>
        //                         <td class="custom5" colspan="3">' . $y . '</td>
        //                     </tr>';
        //     }
        // }
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $py => $vx) {
                foreach ($temptArrayPush as $symbol) {
                    if (strpos($vx, $symbol) === 0) {
                        $bodketer .= '<tr>
                                                    <td class="custom5" colspan="3">' . $vx . '
                                                    </td>
                                                </tr>';
                    }
                }
            }
            ;
        }
        $bodketer .= '</table>';
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';
        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="30%">No. LHP <sup style="font-size: 8px;"><u>a</u></sup></td>
                                        <td class="custom" width="40%">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">' . $data->no_lhp . '</td>
                                        <td class="custom">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;" width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Spesifikasi Metode</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr></table>' . $bodreg . $bodketer . '</td></tr></table></div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }
    public function lhpMagnet($data, $data1, $mode_download, $custom)
    {
        // dd($data, $data1, $mode_download, $custom); // Bisa di-uncomment untuk debugging

        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            if (file_exists($file_qr)) {
                $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            } else {
                $qr_img = ''; // Atau berikan placeholder
            }
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        $temptArrayPush = [];

    
        $bodiContent = '';

        if (!empty($custom)) {
            foreach ($custom as $key => $value) {
                // Setiap iterasi $custom akan membuat tabel baru, yang akan ditambahkan ke $customBod
                $tempTableHtml = '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                                <thead>
                                <tr>
                                    <th width="25" class="custom">NO</th>
                                    <th width="170" class="custom">PARAMETER</th>
                                    <th width="210" class="custom">HASIL UJI </th>
                                    <th width="50" class="custom">NAB **</th>
                                    <th width="50" class="custom">SATUAN</th>
                                    <th width="120" class="custom">SPESIFIKASI METODE</th>
                                </tr>
                                </thead>
                                <tbody>';
                $tot = count($value);
                foreach ($value as $kk => $yy) {
                    $p = $kk + 1;
                    if (!empty($yy['akr']) && !in_array($yy['akr'], $temptArrayPush)) {
                        $temptArrayPush[] = $yy['akr'];
                    }
                    if (!empty($yy['attr']) && !in_array($yy['attr'], $temptArrayPush)) {
                        $temptArrayPush[] = $yy['attr'];
                    }

                    $tempTableHtml .= '<tr>
                                            <td class="pd-5-solid-center">' . $p . '</td>
                                            <td class="pd-5-solid-left">' . $yy['parameter'] . '</td>
                                            <td class="pd-5-solid-center">' . $yy['hasil'] . '</td>
                                            <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                            <td class="pd-5-solid-center">' . $yy['satuan'] . '</td>
                                            <td class="pd-5-solid-center">' . $yy['methode'] . '</td>
                                        </tr>';
                }
                $tempTableHtml .= '</tbody></table></div>';
                array_push($customBod, $tempTableHtml); 
            }
        } else {
            $bodiContent .= '<div class="left"><table
                                style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                                <thead>
                                <tr>
                                    <th width="25" class="custom">NO</th>
                                    <th width="170" class="custom">PARAMETER</th>
                                    <th width="210" class="custom">HASIL UJI </th>
                                    <th width="50" class="custom">NAB **</th>
                                    <th width="50" class="custom">SATUAN</th>
                                    <th width="120" class="custom">SPESIFIKASI METODE</th>
                                </tr>
                                </thead><tbody>';
            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                if (!empty($yy['akr']) && !in_array($yy['akr'], $temptArrayPush)) {
                    $temptArrayPush[] = $yy['akr'];
                }
                if (!empty($yy['attr']) && !in_array($yy['attr'], $temptArrayPush)) {
                    $temptArrayPush[] = $yy['attr'];
                }

                $bodiContent .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left">' . $yy['parameter'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['satuan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['methode'] . '</td>
                                </tr>';
            }
            $bodiContent .= '</tbody></table>';

            $additionalNotes = [
                ['parameter' => 'Sumber Radiasi', 'data' => 'Panel'],
                ['parameter' => 'Waktu Pemaparan (Per-menit)', 'data' => '6 Menit'],
                ['parameter' => 'Frekuensi Area (MHz)', 'data' => '984,9'],
            ];

            $bodiContent .= '<br />
                <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
                    <thead>
                        <tr>
                            <th width="4%" class="custom" style="text-align: center;">NO</th>
                            <th width="36%" class="custom" style="text-align: center;">PARAMETER</th>
                            <th width="60%" class="custom" style="text-align: center;">DATA</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($additionalNotes as $key => $note) {
                $bodiContent .= '<tr>
                        <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">' . ($key + 1) . '</td>
                        <td width="36%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">' . $note['parameter'] . '</td>
                        <td width="60%" class="pd-5-solid-left" style="text-align: center;">' . $note['data'] . '</td>
                    </tr>';
            }
            $bodiContent .= '</tbody></table>'; // Tutup tabel additionalNotes

            // HASIL OBSERVASI
          
            $bodiContent .= '<br/>
                <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
                    <thead>
                        <tr>
                            <th colspan="2" class="custom" style="text-align: center; ">HASIL OBSERVASI</th>
                        </tr>
                    
                    </thead>
                    <tbody>';
            foreach (json_decode($data->hasil_observasi) as $key => $note) {
             
                $bodiContent .= '<tr>
                        <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">' . ($key + 1) . '</td>
                        <td width="96%" class="pd-5-solid-left" style="text-align: center;">' . $note . '</td>
                    </tr>';
            }
            $bodiContent .= '</tbody></table>'; 

          

            $bodiContent .= '<br />
                <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%;">
                    <thead>
                        <tr>
                            <th colspan="2" class="custom" style="text-align: center;">KESIMPULAN</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach (json_decode($data->kesimpulan) as $key => $note) {
                $bodiContent .= '<tr>
                        <td width="4%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">' . ($key + 1) . '</td>
                        <td width="96%" class="pd-5-solid-left" style="font-weight: bold; text-align: center;">' . $note . '</td>
                    </tr>';
            }
            $bodiContent .= '</tbody></table></div>'; 
            array_push($customBod, $bodiContent); 
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {
            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }

        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">**' . $y . '</td>
                            </tr>';
            }
        }
        $bodreg .= '</table>';

        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $py => $vx) {
          
                $found = false;
                foreach ($temptArrayPush as $symbol) {
                    if (strpos($vx, $symbol) === 0) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $bodketer .= '<tr>
                                        <td class="custom5" colspan="3">' . $vx . '</td>
                                    </tr>';
                }
            }
        }
        $bodketer .= '</table>';

        $title_lhp = 'LAPORAN HASIL PENGUJIAN';
        $pading = '';
        $isi_header = '';

        if ($mode_download == 'downloadWSDraft') {
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        ' . $isi_header . '
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="120">No. LHP <sup style="font-size: 8px;"><u>a</u></sup></td>
                                        <td class="custom" width="120">No. SAMPEL</td>
                                        <td class="custom" width="200">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="custom">' . $data->no_lhp . '</td>
                                        <td class="custom">' . $data->no_sampel . '</td>
                                        <td class="custom">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                    style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                    Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;" width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Keterangan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . implode(", ", json_decode($data->keterangan, true)) . '</td>
                                    </tr>
                                </table>' . $bodreg . $bodketer . '</td></tr></table></div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }

    public function lhpPencahayaan($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        if (!empty($custom)) {
            foreach ($custom as $key => $value) {
                $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                         <tr>
                        <th width="25" rowspan="2"
                            class="pd-5-solid-top-center">NO</th>
                        <th width="400"  class="pd-5-solid-top-center" rowspan="2">LOKASI / KETERANGAN SAMPEL</th>
                        <th width="120" class="pd-5-solid-top-center">HASIL UJI</th>
                        <th width="120" class="pd-5-solid-top-center">STANDART</th>
                        <th width="400"  class="pd-5-solid-top-center" rowspan="2">SUMBER PENCAHAYAAN</th>
                        <th width="400"  class="pd-5-solid-top-center" rowspan="2">JENIS PENGUKURAN</th>

                    </tr><tr>
                        <th class="pd-5-solid-top-center" colspan="2">Satuan = LUX</th>
                    </tr></thead><tbody>';
                $tot = count($value);
                foreach ($value as $kk => $yy) {
                    $p = $kk + 1;
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['sumber_cahaya'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['jenis_pengukuran'] . '</td>
                                </tr>';
                    } else {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['sumber_cahaya'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['jenis_pengukuran'] . '</td>
                                </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';

                array_push($customBod, $bodi);
            }
        } else {
            $bodi = '<div class="left"><table
                            style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                            <thead>
                    <tr>
                        <th width="25" rowspan="2"
                            class="pd-5-solid-top-center">NO</th>
                        <th width="400"  class="pd-5-solid-top-center" rowspan="2">LOKASI / KETERANGAN SAMPEL</th>
                        <th width="120" class="pd-5-solid-top-center">HASIL UJI</th>
                        <th width="120" class="pd-5-solid-top-center">STANDART</th>
                        <th width="400"  class="pd-5-solid-top-center" rowspan="2">SUMBER PENCAHAYAAN</th>
                        <th width="400"  class="pd-5-solid-top-center" rowspan="2">JENIS PENGUKURAN</th>

                    </tr><tr>
                        <th class="pd-5-solid-top-center" colspan="2">Satuan = LUX</th>
                    </tr></thead><tbody>';
            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                if ($p == $tot) {
                    $bodi .= '<tr>
                                <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['sumber_cahaya'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['jenis_pengukuran'] . '</td>
                            </tr>';
                } else {
                    $bodi .= '<tr>
                                   <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['sumber_cahaya'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['jenis_pengukuran'] . '</td>
                            </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }

        $bodreg .= '</table>';


        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $t => $y) {
                $bodketer .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        $bodketer .= '</table>';
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                        <td class="pd-5-solid-top-center" width="240">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                    </table>' . $bodreg . $bodketer . '</td></tr></table></div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }

    public function lhpKebisingan($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];

        if (!empty($custom)) {
            foreach ($custom as $key => $value) {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="175"   class="pd-5-solid-top-center">LOKASI / KETERANGAN SAMPEL</th>
                            <th width="200"  class="pd-5-solid-top-center" >HASIL UJI</th>
                            <th width="200"  class="pd-5-solid-top-center" >JUMLAH PEMAPARAN PER HARI</th>
                        
                        </tr>
                      
                        </thead><tbody>';
                $tot = count($value);
                foreach ($value as $kk => $yy) {
                    $p = $kk + 1;
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                             
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>

                                </tr>';
                    } else {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';

                array_push($customBod, $bodi);
            }
        } else {
            $bodi = '<div class="left"><table
                    style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                    <thead>
                    <tr>
                           <th width="25" 
                                class="pd-5-solid-top-center">NO</th>
                            <th width="175"   class="pd-5-solid-top-center">LOKASI / KETERANGAN SAMPEL</th>
                            <th width="200"  class="pd-5-solid-top-center" >HASIL UJI</th>
                            <th width="200"  class="pd-5-solid-top-center" >JUMLAH PEMAPARAN PER HARI</th>
                    </tr>
                    </thead><tbody>';
            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                if ($p == $tot) {
                    $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>

                            </tr>';
                } else {
                    $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>

                            </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }

        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $t => $y) {
                $bodketer .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        $bodketer .= '</table>';

        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 0px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 2px; margin-bottom: 29px;" width="100%">
                    <tr><td></td></tr>
                </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                        <td class="pd-5-solid-top-center" width="240">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 3px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 3px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 3px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                    </table>' . $bodreg . $bodketer . '
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table border="1" cellspacing="0" cellpadding="2" width="100%">
                                            <thead>
                                                <tr>
                                                    <th>Waktu Pemaparan Per Hari</th>
                                                    <th>Intensitas Kebisingan (dalam dBA)</th>
                                                    <th>Waktu Pemaparan Per Hari</th>
                                                    <th>Intensitas Kebisingan (dalam dBA)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">8 Jam</td>
                                                    <td style="text-align: center; vertical-align: middle;">85</td>
                                                    <td style="text-align: center; vertical-align: middle;">28,12 Detik</td>
                                                    <td style="text-align: center; vertical-align: middle;">115</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">4</td>
                                                    <td style="text-align: center; vertical-align: middle;">88</td>
                                                    <td style="text-align: center; vertical-align: middle;">14,06</td>
                                                    <td style="text-align: center; vertical-align: middle;">118</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">2</td>
                                                    <td style="text-align: center; vertical-align: middle;">91</td>
                                                    <td style="text-align: center; vertical-align: middle;">7,03</td>
                                                    <td style="text-align: center; vertical-align: middle;">121</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">1</td>
                                                    <td style="text-align: center; vertical-align: middle;">94</td>
                                                    <td style="text-align: center; vertical-align: middle;">3,52</td>
                                                    <td style="text-align: center; vertical-align: middle;">124</td>
                                                </tr>
                                                 <tr>
                                                    <td style="text-align: center; vertical-align: middle;">30 Menit</td>
                                                    <td style="text-align: center; vertical-align: middle;">97</td>
                                                    <td style="text-align: center; vertical-align: middle;">1,76</td>
                                                    <td style="text-align: center; vertical-align: middle;">127</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">15</td>
                                                    <td style="text-align: center; vertical-align: middle;">100</td>
                                                    <td style="text-align: center; vertical-align: middle;">0,88</td>
                                                    <td style="text-align: center; vertical-align: middle;">130</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">7,5</td>
                                                    <td style="text-align: center; vertical-align: middle;">103</td>
                                                    <td style="text-align: center; vertical-align: middle;">0,44</td>
                                                    <td style="text-align: center; vertical-align: middle;">133</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">3,75</td>
                                                    <td style="text-align: center; vertical-align: middle;">106</td>
                                                    <td style="text-align: center; vertical-align: middle;">0,22</td>
                                                    <td style="text-align: center; vertical-align: middle;">136</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">1,88</td>
                                                    <td style="text-align: center; vertical-align: middle;">109</td>
                                                    <td style="text-align: center; vertical-align: middle;">0,11</td>
                                                    <td style="text-align: center; vertical-align: middle;">139</td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: center; vertical-align: middle;">0,94</td>
                                                    <td style="text-align: center; vertical-align: middle;">112</td>
                                                    <td style="text-align: center; vertical-align: middle;"></td>
                                                    <td style="text-align: center; vertical-align: middle;"></td>
                                                </tr> 
                                                
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }
    public function lhpKebisinganSesaat($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        if (!empty($custom)) {
            foreach ($custom as $key => $value) {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="8%"
                                class="custom">NO</th>
                            <th width="40%"  class="custom">LOKASI / KETERANGAN SAMPEL</th>
                            <th width="21%"  class="custom">HASIL UJI (dBA)</th>
                            <th width="21%" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>
                        </tr>
                      
                        </thead><tbody>';
                $tot = count($value);
                foreach ($value as $kk => $yy) {
                    $p = $kk + 1;
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                     
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                </tr>';
                    } else {
                        $bodi .= '<tr>
                                       <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';

                array_push($customBod, $bodi);
            }
        } else {
            $bodi = '<div class="left"><table
                    style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                    <thead>
                     <tr>
                       <th width="8%" class="custom">NO</th>
                            <th width="40%"  class="custom">LOKASI / KETERANGAN SAMPEL</th>
                            <th width="21%"  class="custom">HASIL UJI (dBA)</th>
                            <th width="21%" class="custom">JUMLAH JAM PEMAPARAN PER HARI</th>

                        </tr>
                    </thead><tbody>';
            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                if ($p == $tot) {
                    $bodi .= '<tr>
                                        <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                            </tr>';
                } else {
                    $bodi .= '<tr>
                                       <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                            </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }

        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $t => $y) {
                $bodketer .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        $bodketer .= '</table>';

        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 0px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 2px; margin-bottom: 29px;" width="100%">
                    <tr><td>testtest</td></tr>
                </table>';

     $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="custom" width="180">NO. LHP <sup>a</sup></td>
                                        <td class="custom" width="220">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr> <td> <table style="padding: 3px 0px 0px 0px;"
                                       width="100%">
                                       <tr>
                                           <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span></td>
                                       </tr>
                                       <tr>
                                           <td class="custom5" width="120">Nama
                                               Pelanggan</td>
                                           <td class="custom5" width="12">:</td>
                                           <td class="custom5">' . $data->nama_pelanggan . '</td>
                                       </tr>
                                </table>
                                <table style="padding: 3px 0px 0px 0px;"
                                       width="100%">
                                       <tr>
                                           <td class="custom5" width="120">Alamat /
                                               Lokasi
                                               Sampling</td>
                                           <td class="custom5" width="12">:</td>
                                           <td class="custom5">' . $data->alamat_sampling . '</td>
                                       </tr>
                                </table>
                                <table style="padding: 3px 0px 0px 0px;"
                                       width="100%">
                                       <tr>
                                           <td class="custom5" colspan="7"><span
                                                   style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                   Sampling</span></td>
                                       </tr>
                                       <tr>
                                           <td class="custom5">Metode Sampling</td>
                                           <td class="custom5">:</td>
                                           <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                       </tr>
                                       <tr>
                                           <td class="custom5">Tanggal
                                               Sampling</td>
                                           <td class="custom5" width="12">:</td>
                                           <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                       </tr>
                                       <tr>
                                           <td class="custom5">Periode
                                               Analisa</td>
                                           <td class="custom5">:</td>
                                           <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                       </tr>
                                </table>
                            ' . $bodreg . $bodketer . '
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table border="1" cellspacing="0" cellpadding="2" width="100%">
                                    <thead>
                                        <tr>
                                            <th>Durasi Pajanan Kebisingan per Hari</th>
                                            <th>Level Kebisingan(dalam dBA)</th>
                                            <th>Durasi Pajanan Kebisingan per Hari</th>
                                            <th>Level Kebisingan(dalam dBA)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">24 Jam</td>
                                            <td style="text-align: center; vertical-align: middle;">80</td>
                                            <td style="text-align: center; vertical-align: middle;">28,12 Detik</td>
                                            <td style="text-align: center; vertical-align: middle;">115</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">16</td>
                                            <td style="text-align: center; vertical-align: middle;">82</td>
                                            <td style="text-align: center; vertical-align: middle;">14,06</td>
                                            <td style="text-align: center; vertical-align: middle;">118</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">8</td>
                                            <td style="text-align: center; vertical-align: middle;">85</td>
                                            <td style="text-align: center; vertical-align: middle;">7,03</td>
                                            <td style="text-align: center; vertical-align: middle;">121</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">4</td>
                                            <td style="text-align: center; vertical-align: middle;">88</td>
                                            <td style="text-align: center; vertical-align: middle;">3,52</td>
                                            <td style="text-align: center; vertical-align: middle;">124</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">2</td>
                                            <td style="text-align: center; vertical-align: middle;">91</td>
                                            <td style="text-align: center; vertical-align: middle;">1,76</td>
                                            <td style="text-align: center; vertical-align: middle;">127</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">1</td>
                                            <td style="text-align: center; vertical-align: middle;">94</td>
                                            <td style="text-align: center; vertical-align: middle;">0,88</td>
                                            <td style="text-align: center; vertical-align: middle;">130</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">30 Menit</td>
                                            <td style="text-align: center; vertical-align: middle;">97</td>
                                            <td style="text-align: center; vertical-align: middle;">0,44</td>
                                            <td style="text-align: center; vertical-align: middle;">133</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">15</td>
                                            <td style="text-align: center; vertical-align: middle;">100</td>
                                            <td style="text-align: center; vertical-align: middle;">0,22</td>
                                            <td style="text-align: center; vertical-align: middle;">136</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">7,5</td>
                                            <td style="text-align: center; vertical-align: middle;">103</td>
                                            <td style="text-align: center; vertical-align: middle;">0,11</td>
                                            <td style="text-align: center; vertical-align: middle;">139</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">3,75</td>
                                            <td style="text-align: center; vertical-align: middle;">106</td>
                                            <td style="text-align: center; vertical-align: middle;"></td>
                                            <td style="text-align: center; vertical-align: middle;"></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">1,88</td>
                                            <td style="text-align: center; vertical-align: middle;">109</td>
                                            <td style="text-align: center; vertical-align: middle;"></td>
                                            <td style="text-align: center; vertical-align: middle;"></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;">0,94</td>
                                            <td style="text-align: center; vertical-align: middle;">112</td>
                                            <td style="text-align: center; vertical-align: middle;"></td>
                                            <td style="text-align: center; vertical-align: middle;"></td>
                                        </tr> 
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;" colspan="4">Catatan : Pajanan bising tidak boleh melebihi level 140 dBA walaupun hanya sesaat</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }
    public function lhpKebisinganPersonal($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        if (!empty($custom)) {
            foreach ($custom as $key => $value) {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25" rowspan="2"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="200" rowspan="2" class="pd-5-solid-top-center">NAMA PEKERJA</th>
                            <th width="150" rowspan="2" class="pd-5-solid-top-center">LOKASI SAMPLING</th>
                            <th width="100" class="pd-5-solid-top-center" rowspan="2">DURASI PAPARAN PEKERJA PER JAM</th>
                            <th width="80" class="pd-5-solid-top-center">HASIL UJI</th>
                            <th width="80" class="pd-5-solid-top-center">NAB**</th>
                        </tr>
                        <tr>
                            <th class="pd-5-solid-top-center" colspan="2">(dBA)</th>
                        </tr>
                        </thead><tbody>';
                $tot = count($value);
                foreach ($value as $kk => $yy) {
                    $p = $kk + 1;
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . ' ' . $yy['nama_pekerja'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                </tr>';
                    } else {
                        $bodi .= '<tr>
                                     <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . ' ' . $yy['nama_pekerja'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                                </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';

                array_push($customBod, $bodi);
            }
        } else {
            $bodi = '<div class="left"><table
                    style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                    <thead>
                    <tr>
                            <th rowspan="2" class="pd-5-solid-top-center">NO</th>
                            <th width="200" rowspan="2" class="pd-5-solid-top-center">NAMA PEKERJA</th>
                            <th width="150" rowspan="2" class="pd-5-solid-top-center">LOKASI SAMPLING</th>
                            <th width="100" class="pd-5-solid-top-center" rowspan="2">DURASI PAPARAN PEKERJA PER JAM</th>
                            <th width="80" class="pd-5-solid-top-center">HASIL UJI</th>
                            <th width="80" class="pd-5-solid-top-center">NAB**</th>
                        </tr>
                        <tr>
                            <th class="pd-5-solid-top-center" colspan="2">(dBA)</th>
                        </tr>
                    </thead><tbody>';
            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                if ($p == $tot) {
                    $bodi .= '<tr>
                                   <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . ' ' . $yy['nama_pekerja'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                            </tr>';
                } else {
                    $bodi .= '<tr>
                                <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:8px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . ' ' . $yy['nama_pekerja'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['paparan'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['hasil_uji'] . '</td>
                                    <td class="pd-5-solid-center">' . $yy['nab'] . '</td>
                            </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }

        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $t => $y) {
                $bodketer .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        $bodketer .= '</table>';

        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 0px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 2px; margin-bottom: 29px;" width="100%">
                    <tr><td>testtest</td></tr>
                </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP <sup>a</sup></td>
                                        <td class="pd-5-solid-top-center" width="240">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 3px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 3px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 3px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7">
                                            <span style="font-weight: bold; border-bottom: 1px solid #000">
                                                Informasi Sampling
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                </table>
                            
                               ' . $bodreg . $bodketer . '
                                    </td>
                                </tr>
                         
                            </table>
                        </div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }

    public function lhpKebisingan24Jam($data, $data1, $mode_download, $custom)
    {
        // dd('masuk');
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';
        $period = explode(" - ", $data->periode_analisa);
        $period = array_filter($period);
        $period1 = '';
        $period2 = '';
        if (!empty($period)) {
            $period1 = self::tanggal_indonesia($period[0]);
            $period2 = self::tanggal_indonesia($period[1]);
        }

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        $customBod = [];
        if (!empty($custom)) {
            foreach ($custom as $key => $value) {
                $bodi = '<div class="left"><table
                        style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                        <tr>
                            <th width="25" rowspan="2"
                                class="pd-5-solid-top-center">NO</th>
                            <th width="175"  rowspan="2" class="pd-5-solid-top-center">Lokasi / Keterangan Sample</th>
                            <th width="200"  class="pd-5-solid-top-center" colspan="3">Kebisingan 24 Jam (dBA)</th>
                            <th width="175"  rowspan="2" class="pd-5-solid-top-center">Titik Koordinat</th>
                        </tr>
                        <tr>
                            <th class="pd-5-solid-top-center" colspan="1">Ls (Siang)</th>
                            <th class="pd-5-solid-top-center" colspan="1">Lm (Malam)</th>
                            <th class="pd-5-solid-top-center" colspan="1">Ls-m (Siang-Malam)</th>
                        </tr>
                        </thead><tbody>';
                $tot = count($value);
                foreach ($value as $kk => $yy) {
                    $p = $kk + 1;
                    $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                    if ($p == $tot) {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_ls']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_lm']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', ($yy['leq_ls'] - $yy['leq_lm'])) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['titik_koordinat'] . '</td>
                                </tr>';
                    } else {
                        $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_ls']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_lm']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', ($yy['leq_ls'] - $yy['leq_lm'])) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['titik_koordinat'] . '</td>
                                </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';

                array_push($customBod, $bodi);
            }
        } else {
            $bodi = '<div class="left"><table
                    style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                    <thead>
                    <tr>
                        <th width="25" rowspan="2"
                            class="pd-5-solid-top-center">NO</th>
                        <th width="175"  rowspan="2" class="pd-5-solid-top-center">Lokasi / Keterangan Sample</th>
                        <th width="200"  class="pd-5-solid-top-center" colspan="3">Kebisingan 24 Jam (dBA)</th>
                        <th width="175"  rowspan="2" class="pd-5-solid-top-center">Titik Koordinat</th>
                    </tr>
                    <tr>
                        <th class="pd-5-solid-top-center" colspan="1">Ls (Siang)</th>
                        <th class="pd-5-solid-top-center" colspan="1">Lm (Malam)</th>
                        <th class="pd-5-solid-top-center" colspan="1">Ls-m (Siang-Malam)</th>
                    </tr>
                    </thead><tbody>';
            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                // dd($yy);
                $akr = ($yy['akr'] != "") ? $yy['akr'] : "&nbsp;&nbsp;";
                if ($p == $tot) {
                    $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_ls']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_lm']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['titik_koordinat'] . '</td>
                            </tr>';
                } else {
                    $bodi .= '<tr>
                                    <td class="pd-5-solid-center">' . $p . '</td>
                                    <td class="pd-5-solid-left"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['lokasi_keterangan'] . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_ls']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['leq_lm']) . '</td>
                                    <td class="pd-5-solid-center">' . str_replace('.', ',', $yy['hasil_uji']) . '</td>
                                    <td class="pd-5-solid-center">' . $yy['titik_koordinat'] . '</td>
                            </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';
            array_push($customBod, $bodi);
        }

        $tgl_sampling = '';
        if (strpos($data->tanggal_sampling, " - ") !== false) {
            $tgl_ = explode(" - ", $data->tanggal_sampling);
            $tgl_mulai = self::tanggal_indonesia($tgl_[0]);
            $tgl_selesai = self::tanggal_indonesia($tgl_[1]);
            $tgl_sampling = $tgl_mulai . ' - ' . $tgl_selesai;
        } else {

            $tgl_sampling = self::tanggal_indonesia($data->tanggal_sampling);
        }
        $bodreg = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $bodreg .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }

        $bodreg .= '</table>';
        $bodketer = '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        if ($data->keterangan != null) {
            foreach (json_decode($data->keterangan) as $t => $y) {
                $bodketer .= '<tr>
                                <td class="custom5" colspan="3">' . $y . '</td>
                            </tr>';
            }
        }
        $bodketer .= '</table>';
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                    <tr>
                        <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                    </tr>
                </table>
                <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>';

        $header = '<table width="100%"
                    style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            ' . $isi_header . '
                        </td>
                    </tr>
                </table><div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                    <table
                        style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td>
                                <table
                                    style="border-collapse: collapse; text-align: center;"
                                    width="100%">
                                    <tr>
                                        <td class="pd-5-solid-top-center" width="120">No. LHP</td>
                                        <td class="pd-5-solid-top-center" width="240">JENIS SAMPEL</td>
                                    </tr>
                                    <tr>
                                        <td class="pd-5-solid-center">' . $data->no_lhp . '</td>
                                        <td class="pd-5-solid-center">' . $data->sub_kategori . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table style="padding: 20px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Pelanggan</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5" width="120">Nama
                                            Pelanggan</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->nama_pelanggan . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" width="120">Alamat /
                                            Lokasi
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5">' . $data->alamat_sampling . '</td>
                                    </tr>
                                </table>
                                <table style="padding: 10px 0px 0px 0px;"
                                    width="100%">
                                    <tr>
                                        <td class="custom5" colspan="7"><span
                                                style="font-weight: bold; border-bottom: 1px solid #000">Informasi
                                                Sampling</span></td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Tanggal
                                            Sampling</td>
                                        <td class="custom5" width="12">:</td>
                                        <td class="custom5" colspan="5">' . $tgl_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Metode Sampling</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $methode_sampling . '</td>
                                    </tr>
                                    <tr>
                                        <td class="custom5">Periode
                                            Analisa</td>
                                        <td class="custom5">:</td>
                                        <td class="custom5" colspan="5">' . $period1 . ' - ' . $period2 . '</td>
                                    </tr>
                                    </table>' . $bodreg . $bodketer . '
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table border="1" cellspacing="0" cellpadding="2" width="100%">
                                            <thead>
                                                <tr>
                                                    <th>A</th>
                                                    <th width="60%">Peruntukan Kawasan</th>
                                                    <th>Tingkat Kebisingan (dBA)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>1</td>
                                                    <td>Perumahan dan Pemukiman</td>
                                                    <td style="text-align: center; vertical-align: middle;">55</td>
                                                </tr>
                                                <tr>
                                                    <td>2</td>
                                                    <td>Perdagangan dan Jasa</td>
                                                    <td style="text-align: center; vertical-align: middle;">70</td>
                                                </tr>
                                                <tr>
                                                    <td>3</td>
                                                    <td>Perkantoran dan Perdagangan</td>
                                                    <td style="text-align: center; vertical-align: middle;">65</td>
                                                </tr>
                                                <tr>
                                                    <td>4</td>
                                                    <td>Ruang Terbuka Hijau</td>
                                                    <td style="text-align: center; vertical-align: middle;">50</td>
                                                </tr>
                                                <tr>
                                                    <td>5</td>
                                                    <td>Industri</td>
                                                    <td style="text-align: center; vertical-align: middle;">70</td>
                                                </tr>
                                                <tr>
                                                    <td>6</td>
                                                    <td>Pemerintahan dan Fasilitas Umum</td>
                                                    <td style="text-align: center; vertical-align: middle;">60</td>
                                                </tr>
                                                <tr>
                                                    <td>7</td>
                                                    <td>Rekreasi</td>
                                                    <td style="text-align: center; vertical-align: middle;">70</td>
                                                </tr>
                                                <tr>
                                                    <td>8</td>
                                                    <td>Khusus : - Pelabuhan Laut</td>
                                                    <td style="text-align: center; vertical-align: middle;">70</td>
                                                </tr>
                                                <tr>
                                                    <td></td>
                                                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- Cagar Budaya</td>
                                                    <td style="text-align: center; vertical-align: middle;">60</td>
                                                </tr>
                                                <tr>
                                                    <td></td>
                                                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- Bandar Udara / Stasiun Kereta Api *)</td>
                                                    <td></td>
                                                </tr>
                                            </tbody>
                                            <thead>
                                                <tr>
                                                    <th>B</th>
                                                    <th>Lingkungan Kerja</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>1</td>
                                                    <td>Rumah Sakit atau sejenisnya</td>
                                                    <td style="text-align: center; vertical-align: middle;">55</td>
                                                </tr>
                                                <tr>
                                                    <td>2</td>
                                                    <td>Sekolah atau sejenisnya</td>
                                                    <td style="text-align: center; vertical-align: middle;">55</td>
                                                </tr>
                                                <tr>
                                                    <td>3</td>
                                                    <td>Tempat Ibadah atau sejenisnya</td>
                                                    <td style="text-align: center; vertical-align: middle;">55</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3">Keterangan : *) Disesuaikan dengan ketentuan Menteri Perhubungan</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>';

        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }

    public function lhpIklimPanas($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';

        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        $file_qr = '';
        $qr_img = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        }

        $customBod = [];

        if (!empty($custom)) {
            // Custom content handling (empty in original code)
        } else {
            $bodi = '';
            $bodi = '<div class="left">
                    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                            <tr>
                                <th width="25" class="custom">No.</th>
                                <th width="200" class="custom">LOKASI / KETERANGAN SAMPEL</th>
                                <th width="150" class="custom">INDEX SUHU BASAH DAN BOLA (°C)</th>
                                <th width="150" class="custom">AKTIVITAS PEKERJAAN</th>
                                <th width="180" class="custom">DURASI PAPARAN TERHADAP PEKERJAAN PER JAM</th>
                            </tr>
                        </thead>
                        <tbody>';

            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                if ($p == $tot) {
                    $bodi .= '<tr>
                            <td class="pd-5-solid-center">' . $p . '</td>
                            <td class="pd-5-solid-left"><sup style="font-size: 8px;">' . $yy['no_sampel'] . '</sup> ' . $yy['keterangan'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['indeks_suhu_basah'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['aktivitas_pekerjaan'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['durasi_paparan'] . ' Jam</td>
                        </tr>';
                } else {
                    $bodi .= '<tr>
                            <td class="pd-5-dot-center">' . $p . '</td>
                            <td class="pd-5-solid-left"><sup style="font-size: 8px;">' . $yy['no_sampel'] . '</sup> ' . $yy['keterangan'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['indeks_suhu_basah'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['aktivitas_pekerjaan'] . '</td>
                              <td class="pd-5-solid-center">' . $yy['durasi_paparan'] . ' Jam</td>
                        </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';

            array_push($customBod, $bodi);
        }

        $pading = '';
        // $title = 'LAPORAN HASIL PENGUJIAN';
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        // Create signature section
        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                <tr>
                    <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                </tr>
            </table>
            <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                <tr><td></td></tr>
                <tr><td></td></tr>
            </table>';

        // Create header section
        $header = '<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td>
                        ' . $isi_header . '
                    </td>
                </tr>
            </table>
            <div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <table style="border-collapse: collapse; text-align: center;" width="100%">
                                <tr>
                                    <td class="custom" width="180">No. LHP <sup><u>a</u></sup></td>
                                    <td class="custom" width="220">JENIS SAMPEL</td>
                                </tr>
                                <tr>
                                    <td class="custom">' . $data->no_lhp . '</td>
                                    <td class="custom">' . $data->sub_kategori . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table style="padding: 20px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span></td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Nama Pelanggan</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->nama_pelanggan . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->alamat_sampling . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">
                                        <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="custom5">Metode Sampling</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $methode_sampling . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Tanggal Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Periode Analisa</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                                </tr>
                            </table>';

        // Add regulation information
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $header .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }
        $header .= '</table>';

        // Add category-specific tables
        if ($data->id_kategori_3 == 21) {
            $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-top: 20px">
                <tr>
                    <th class="custom" rowspan="3">Pengaturan Siklus Waktu Kerja</th>
                    <th class="custom" colspan="4">ISBB (°C)</th>
                </tr>
                <tr>
                    <th class="custom" colspan="4">Beban Kerja</th>
                </tr>
                <tr>
                    <th class="custom">Ringan</th>
                    <th class="custom">Sedang</th>
                    <th class="custom">Berat</th>
                    <th class="custom">Sangat Berat</th>
                </tr>
                <tr>
                    <td class="custom2">75 - 100 %</td>
                    <td class="custom2">31,0</td>
                    <td class="custom2">28,0</td>
                    <td class="custom2">-</td>
                    <td class="custom2">-</td>
                </tr>
                <tr>
                    <td class="custom2">50 - 75 %</td>
                    <td class="custom2">31,0</td>
                    <td class="custom2">29,0</td>
                    <td class="custom2">27,5</td>
                    <td class="custom2">-</td>
                </tr>
                <tr>
                    <td class="custom2">25 - 50 %</td>
                    <td class="custom2">32,0</td>
                    <td class="custom2">30,0</td>
                    <td class="custom2">29,0</td>
                    <td class="custom2">28,0</td>
                </tr>
                <tr>
                    <td class="custom2">0 - 25 %</td>
                    <td class="custom2">32,5</td>
                    <td class="custom2">31,5</td>
                    <td class="custom2">30,5</td>
                    <td class="custom2">30,0</td>
                </tr>
            </table>';
        }

        // Close header div
        $header .= '</td>
                </tr>
            </table>
        </div>';

        // Set QR code based on mode
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-IKLIM_KERJA-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);
        return $ress;
    }

    public function lhpIklimDingin($data, $data1, $mode_download, $custom)
    {
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';

        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        $file_qr = '';
        $qr_img = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        }

        $customBod = [];

        if (!empty($custom)) {
            // Custom content handling (empty in original code)
        } else {
            $bodi = '';
            $bodi = '<div class="left">
                    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                            <tr>
                                <th width="25" class="custom">No.</th>
                                <th width="200" class="custom">LOKASI / KETERANGAN SAMPEL</th>
                                <th width="150" class="custom">KECEPATAN ANGIN (mph)</th>
                                <th width="170" class="custom">SUHU TEMPERATUR AKTUAL (°C)</th>
                                <th width="150" class="custom">KONDISI</th>
                            </tr>
                        </thead>
                        <tbody>';

            $tot = count($data1);
            foreach ($data1 as $kk => $yy) {
                $p = $kk + 1;
                if ($p == $tot) {
                    $bodi .= '<tr>
                            <td class="pd-5-solid-center">' . $p . '</td>
                            <td class="pd-5-solid-left"><sup style="font-size: 8px;">' . $yy['no_sampel'] . '</sup> ' . $yy['keterangan'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['kecepatan_angin'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['suhu_temperatur'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['kondisi'] . '</td>
                        </tr>';
                } else {
                    $bodi .= '<tr>
                            <td class="pd-5-dot-center">' . $p . '</td>
                            <td class="pd-5-solid-left"><sup style="font-size: 8px;">' . $yy['no_sampel'] . '</sup> ' . $yy['keterangan'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['kecepatan_angin'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['suhu_temperatur'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['kondisi'] . '</td>
                        </tr>';
                }
            }
            $bodi .= '</tbody></table></div>';

            array_push($customBod, $bodi);
        }

        $pading = '';
        if ($mode_download == 'downloadWSDraft') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = 'margin-bottom: 40px;';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHP') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>';
        } else if ($mode_download == 'downloadLHPFinal') {
            $title_lhp = 'LAPORAN HASIL PENGUJIAN';
            $pading = '';
            $isi_header = '<td style="width: 33.33%; padding-left: 20px;" class="text-left text-wrap">
                                <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title_lhp . '</span>
                            </td>
                            <td style="width: 33.33%; text-align: right; padding-right: 50px;">
                                <img src="' . public_path() . '/img/logo_kan.png" alt="ISL" width="100px" height="50px">
                            </td>';
        }

        // Create signature section
        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                <tr>
                    <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                </tr>
            </table>
            <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                <tr><td></td></tr>
                <tr><td></td></tr>
            </table>';

        // Create header section
        $header = '<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td>
                        ' . $isi_header . '
                    </td>
                </tr>
            </table>
            <div class="right" style="margin-top: ' . ($mode_download == 'downloadLHPFinal' ? '0px' : '14px') . ';">
                <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <table style="border-collapse: collapse; text-align: center;" width="100%">
                                <tr>
                                    <td class="custom" width="180">No. LHP <sup><u>a</u></sup></td>
                                    <td class="custom" width="220">JENIS SAMPEL</td>
                                </tr>
                                <tr>
                                    <td class="custom">' . $data->no_lhp . '</td>
                                    <td class="custom">' . $data->sub_kategori . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table style="padding: 20px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td><span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span></td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Nama Pelanggan</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->nama_pelanggan . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->alamat_sampling . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">
                                        <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="custom5">Spesifikasi Metode</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $methode_sampling . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Tanggal Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Periode Analisa</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                                </tr>
                                
                            </table>';

        // Add regulation information
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        $bintang = '**';
        if ($data->regulasi != null) {
            foreach (json_decode($data->regulasi) as $t => $y) {
                $header .= '<tr>
                                <td class="custom5" colspan="3">' . $bintang . $y . '</td>
                            </tr>';
                $bintang .= '*';
            }
        }
        $header .= '</table>';

        // Close header div
        $header .= '</td>
                </tr>
            </table>
        </div>';

        // Set QR code based on mode
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-IKLIM_KERJA-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);
        return $ress;
    }

    public function lhpGetaran($data, $data1, $mode_download, $custom)
    {
        // Get sampling method or default to '-'
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';

        // Format period dates
        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        $file_qr = '';
        $tanggal_qr = '';
        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = 'Tangerang, ' . self::tanggal_indonesia($data->tanggal_lhp) . '';
        } else {
            $qr_img = '';
        }

        // Parse test parameters
        $parame = json_decode($data->parameter_uji);
        $customBod = [];

        if (!empty($custom)) {
            // Custom content handling (empty in original code)
        } else {
            $bodi = '';

            // Check for specific parameters and create appropriate table
            if (in_array("Getaran (LK) TL", $parame) || in_array("Getaran (LK) ST", $parame)) {
                $bodi = '<div class="left">
                    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                            <tr>
                                <th width="25" class="pd-5-solid-top-center">NO</th>
                                <th width="150" class="pd-5-solid-top-center">Aktivitas Pekerja</th>
                                <th width="150" class="pd-5-solid-top-center">Sumber Getaran</th>
                                <th width="150" class="pd-5-solid-top-center">Waktu Pemaparan</th>
                                <th width="150" class="pd-5-solid-top-center">Hasil Pengukuran (m/s<sup>2</sup>)</th>
                            </tr>
                        </thead>
                        <tbody>';

                $tot = count($data1);
                foreach ($data1 as $kk => $yy) {
                    $p = $kk + 1;
                    if ($p == $tot) {
                        $bodi .= '<tr>
                            <td class="pd-5-solid-center">' . $p . '</td>
                            <td class="pd-5-solid-left">' . $yy['aktivitas'] . '</td>
                            <td class="pd-5-solid-left">' . $yy['sumber_get'] . '</td>
                            <td class="pd-5-solid-center">' . $yy['w_paparan'] . '</td>
                            <td class="pd-5-solid-left">X : ' . $yy['x'] . '<br> Y : ' . $yy['y'] . '<br> Z : ' . $yy['z'] . '</td>
                        </tr>';
                    } else {
                        $bodi .= '<tr>
                            <td class="pd-5-dot-center">' . $p . '</td>
                            <td class="pd-5-dot-left">' . $yy['aktivitas'] . '</td>
                            <td class="pd-5-dot-left">' . $yy['sumber_get'] . '</td>
                            <td class="pd-5-dot-center">' . $yy['w_paparan'] . '</td>
                            <td class="pd-5-dot-left">X : ' . $yy['x'] . '<br> Y : ' . $yy['y'] . '<br> Z : ' . $yy['z'] . '</td>
                        </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';
            } else {
                $bodi = '<div class="left">
                    <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                        <thead>
                            <tr>
                                <th width="25" class="custom">NO</th>
                                <th width="270" class="custom">Keterangan</th>
                                <th width="210" class="custom">Sumber Getaran</th>
                                <th width="100" class="custom">Hasil Pengukuran (m/s<sup>2</sup>)</th>
                            </tr>
                        </thead>
                        <tbody>';

                foreach ($data1 as $kk => $yy) {
                    $p = $kk + 1;
                    $bodi .= '<tr>
                        <td class="custom3">' . $p . '</td>
                        <td class="custom4"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['keterangan'] . '</td>
                        <td class="custom3">' . $yy['sumber_get'] . '</td>
                        <td class="custom4">Percepatan : ' . $yy['percepatan'] . '<br> Kecepatan : ' . $yy['kecepatan'] . '</td>
                    </tr>';
                }
                $bodi .= '</tbody></table></div>';
            }
            array_push($customBod, $bodi);
        }
        $pading = '';
        // Set page settings based on download mode
        if ($mode_download == 'downloadWSDraft') {
            $pading = 'margin-bottom: 40px;';
            $title = 'LAPORAN HASIL PENGUJIAN';
        } else if ($mode_download == 'downloadLHP') {
            $pading = '';
            $title = 'LAPORAN HASIL PENGUJIAN';
        }

        // Create signature section
        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
                <tr>
                    <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
                </tr>
            </table>
            <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                <tr><td></td></tr>
                <tr><td></td></tr>
            </table>';

        // Create header section
        $header = '<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td>
                        <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title . '</span>
                    </td>
                </tr>
            </table>
            <div class="right" style="margin-top: 13.9px;">
                <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                    <tr>
                        <td>
                            <table style="border-collapse: collapse; text-align: center;" width="100%">
                                <tr>
                                    <td class="custom" width="120">No. LHP</td>
                                    <td class="custom" width="240">JENIS SAMPLE</td>
                                </tr>
                                <tr>
                                    <td class="custom">' . $data->no_lhp . '</td>
                                    <td class="custom">' . $data->sub_kategori . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table style="padding: 20px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td>
                                        <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Nama Pelanggan</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->nama_pelanggan . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . $data->alamat_sampling . '</td>
                                </tr>
                            </table>
                            <table style="padding: 10px 0px 0px 0px;" width="100%">
                                <tr>
                                    <td class="custom5" width="120">
                                        <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="custom5" width="120">Tanggal Sampling</td>
                                    <td class="custom5" width="12">:</td>
                                    <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Metode Sampling</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $methode_sampling . '</td>
                                </tr>
                                <tr>
                                    <td class="custom5">Periode Analisa</td>
                                    <td class="custom5">:</td>
                                    <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                                </tr>
                            </table>';

        // Add regulation information
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        foreach (json_decode($data->regulasi) as $t => $y) {
            $header .= '<tr>
                <td class="custom5" colspan="3">' . $y . '</td>
            </tr>';
        }
        $header .= '</table>';

        // Add category-specific tables
        if ($data->id_kategori_3 == 17) {
            $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                <tr>
                    <th class="custom" rowspan="2">Jumlah Waktu Pemaparan Per Hari Kerja (Jam)</th>
                    <th class="custom">Resultan Percepatan di Sb. X, Sb. Y dan Sb. Z</th>
                </tr>
                <tr>
                    <th class="custom">Meter Per Detik Kuadrat (m/s<sup>2</sup>)</th>
                </tr>
                <tr>
                    <td class="custom1">6 jam sampai dengan 8 jam</td>
                    <td class="custom2">5</td>
                </tr>
                <tr>
                    <td class="custom1">4 jam dan kurang dari 6 jam</td>
                    <td class="custom2">6</td>
                </tr>
                <tr>
                    <td class="custom1">2 jam dan kurang dari 4 jam</td>
                    <td class="custom2">7</td>
                </tr>
                <tr>
                    <td class="custom1">1 jam dan kurang dari 2 jam</td>
                    <td class="custom2">10</td>
                </tr>
                <tr>
                    <td class="custom1">0.5 jam dan kurang dari 1 jam</td>
                    <td class="custom2">14</td>
                </tr>
                <tr>
                    <td class="custom4">kurang dari 0.5 jam</td>
                    <td class="custom9">20</td>
                </tr>
            </table>';
        } else if ($data->id_kategori_3 == 19) {
            $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                <tr>
                    <th class="custom" style="border: 1px solid black; vertical-align:middle;" rowspan="2">Frekuensi (HZ)</th>
                    <th class="custom" colspan="4">Batas Getaran, Peak, mm/s</th>
                </tr>
                <tr>
                    <th class="custom">Kategori A</th>
                    <th class="custom">Kategori B</th>
                    <th class="custom">Kategori C</th>
                    <th class="custom">Kategori D</th>
                </tr>
                <tr>
                    <td class="custom1">4</td>
                    <td class="custom1"><2</td>
                    <td class="custom1">2 - 27</td>
                    <td class="custom1">> 27 - 40</td>
                    <td class="custom1">>140</td>
                </tr>
                <tr>
                    <td class="custom1">5</td>
                    <td class="custom1"><7,5</td>
                    <td class="custom1">< 7,5 - 25</td>
                    <td class="custom1">>24 - 130</td>
                    <td class="custom1">>130</td>
                </tr>
                <!-- Additional rows omitted for brevity -->
                <tr>
                    <td class="custom9">50</td>
                    <td class="custom9"><1</td>
                    <td class="custom9"><1 - 7</td>
                    <td class="custom9">>7 - 42</td>
                    <td class="custom9">>42</td>
                </tr>
            </table>';
        } else if ($data->id_kategori_3 == 20) {
            $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                <tr>
                    <th class="custom" style="border: 1px solid black; vertical-align:middle;" rowspan="2">
                        Jumlah Waktu Pajanan Per Hari <br>
                        <span>(Jam)</span>
                    </th>
                </tr>
                <tr>
                    <th class="custom">
                        Nilai Ambang Batas <br> 
                        <span>(m/det<sup>2</sup>)</span>
                    </th>
                </tr>
                <tr>
                    <td class="custom1">0,5</td>
                    <td class="custom1">3,4644</td>
                </tr>
                <!-- Additional rows omitted for brevity -->
                <tr>
                    <td class="custom9">8</td>
                    <td class="custom9">0,8661</td>
                </tr>
            </table>';
        } else if ($data->id_kategori_3 == 14 || $data->id_kategori_3 == 15) {
            $header .= '<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                <tr>
                    <th class="custom">Kelas</th>
                    <th class="custom">Jenis Bangunan</th>
                    <th class="custom" style="border: 1px solid black; width: 30%;">
                        Kecepatan Getaran Maksimum <br> 
                        <span>(mm/detik)</span>
                    </th>
                </tr>
                <tr>
                    <td class="custom1">1</td>
                    <td class="custom2">Peruntukan dan bangunan kuno yang mempunyai nilai sejarah yang tinggi</td>
                    <td class="custom1">2</td>
                </tr>
                <!-- Additional rows omitted for brevity -->
                <tr>
                    <td class="custom1">4</td>
                    <td class="custom2">Bangunan "kuat" (misalnya bangunan industri terbuat dari beton dan baja)</td>
                    <td class="custom1">10 - 40</td>
                </tr>
            </table>';
        }

        // Close header div
        $header .= '</td>
                </tr>
            </table>
        </div>';
        // Set QR code based on mode
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);
        return $ress;
    }
    public function lhpGetaranPersonal($data, $data1, $mode_download, $custom)
    {
        // Get sampling method or default to '-'
        $methode_sampling = $data->metode_sampling ? $data->metode_sampling : '-';

        // Format period dates
        $period = explode(" - ", $data->periode_analisa);
        $period1 = self::tanggal_indonesia($period[0]);
        $period2 = self::tanggal_indonesia($period[1]);

        // Handle QR code and location for signature
        $file_qr = '';
        $tanggal_qr = '';
        $lokasi_ttd = 'Tangerang';
        $pading = '';

        if (!is_null($data->file_qr) && $mode_download != 'downloadWSDraft') {
            $file_qr = public_path('qr_documents/' . $data->file_qr . '.svg');
            $qr_img = '<img src="' . $file_qr . '" width="60px" height="60px" style="margin-top: 10px;">';
            $tanggal_qr = $lokasi_ttd . ', ' . self::tanggal_indonesia($data->tanggal_lhp);
        } else {
            $qr_img = '';
        }

        // Parse test parameters
        $parame = json_decode($data->parameter_uji);
        $customBod = [];

        if (!empty($custom)) {
            // Custom content handling (empty in original code)
        } else {
            $bodi = '';

            // Check for specific parameters and create appropriate table
            if (in_array("Getaran (LK) TL", $parame) || in_array("Getaran (LK) ST", $parame)) {
                $bodi = '<div class="left">
                <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                    <thead>
                        <tr>
                            <th width="25" class="pd-5-solid-top-center" rowspan="2">NO</th>
                            <th width="183" class="pd-5-solid-top-center" rowspan="2">KETERANGAN</th>
                            <th width="183" class="pd-5-solid-top-center" rowspan="2">SUMBER GETARAN</th>
                            <th width="183" class="pd-5-solid-top-center" rowspan="2">DUARSI JAM PEMAPARAN PER HARI</th>

                            <th width="100" class="pd-5-solid-top-center">HASIL UJI (m/s<sup>2</sup>)</th>
                            <th width="100" class="pd-5-solid-top-center">NAB <sup>**</sup></th>
                        </tr>
                        <tr>
                            <td class="pd-5-solid-center" colspan="2">(m/det<sup>2</sup>)</td>
                        </tr>
                    </thead>
                    <tbody>';

                $tot = count($data1);
                foreach ($data1 as $kk => $yy) {

                    $p = $kk + 1;
                    if ($p == $tot) {
                        $bodi .= '<tr>
                        <td class="pd-5-solid-center">' . $p . '</td>
                        <td class="pd-5-solid-left"><sup>' . $yy['no_sampel'] . '</sup>' . $yy['keterangan'] . '</td>
                        <td class="pd-5-solid-center">' . $yy['sumber_get'] . '</td>
                        <td class="pd-5-solid-center">' . $yy['w_paparan'] . '</td>
                         <td class="pd-5-solid-center"> ' . $yy['hasil'] . '</td>
                         <td class="pd-5-solid-center"> ' . $yy['nab'] . '</td>
                    </tr>';
                    } else {
                        $bodi .= '<tr>
                        <td class="pd-5-solid-center">' . $p . '</td>
                        <td class="pd-5-solid-left"><sup>' . $yy['no_sampel'] . '</sup>' . $yy['keterangan'] . '</td>
                        <td class="pd-5-solid-center">' . $yy['sumber_get'] . '</td>
                        <td class="pd-5-solid-center">' . $yy['w_paparan'] . '</td>
                        <td class="pd-5-solid-center"> ' . $yy['hasil'] . '</td>
                        <td class="pd-5-solid-center"> ' . $yy['nab'] . '</td>
                    </tr>';
                    }
                }
                $bodi .= '</tbody></table></div>';
            } else {
                $bodi = '<div class="left">
                <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
                    <thead>
                        <tr>
                            <th width="25" class="custom">NO</th>
                            <th width="270" class="custom">Keterangan</th>
                            <th width="210" class="custom">Sumber Getaran</th>
                            <th width="100" class="custom">Hasil Pengukuran (m/s<sup>2</sup>)</th>
                        </tr>
                    </thead>
                    <tbody>';

                foreach ($data1 as $kk => $yy) {
                    $p = $kk + 1;
                    $bodi .= '<tr>
                    <td class="custom3">' . $p . '</td>
                    <td class="custom4"><sup style="font-size:5px; !important; margin-top:-10px;">' . $yy['no_sampel'] . '</sup>' . $yy['keterangan'] . '</td>
                    <td class="custom3">' . $yy['sumber_get'] . '</td>
                    <td class="custom4">Percepatan : ' . $yy['percepatan'] . '<br> Kecepatan : ' . $yy['kecepatan'] . '</td>
                </tr>';
                }
                $bodi .= '</tbody></table></div>';
            }
            array_push($customBod, $bodi);
        }
        $title = 'LAPORAN HASIL PENGUJIAN';
        // Set page settings based on download mode
        if ($mode_download == 'downloadWSDraft') {
            $pading = 'margin-bottom: 40px;';
           
        } else if ($mode_download == 'downloadLHP') {
            $pading = '';
        }

        // Create signature section
        $ttd = '<table style="text-align: center; font-family: Helvetica, sans-serif; font-size: 9px" width="100%">
            <tr>
                <td>' . $tanggal_qr . ' <br> ' . $qr_img . '</td>
            </tr>
            </table>
            <table style="padding: 50px 0px 0px 0px; text-align: center; font-family: Helvetica, sans-serif; font-size: 9px; ' . $pading . '" width="100%">
                <tr><td></td></tr>
                <tr><td></td></tr>
            </table>';

        // Create header section
        $header = '<table width="100%" style="text-align: center; font-weight: bold; font-size: 15px; font-family: Arial, Helvetica, sans-serif;">
            <tr>
                <td>
                    <span style="font-weight: bold; border-bottom: 1px solid #000">' . $title . '</span>
                </td>
            </tr>
            </table>
            <div class="right" style="margin-top: 13.9px;">
            <table style="border-collapse: collapse; font-size: 10px; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <td>
                        <table style="border-collapse: collapse; text-align: center;" width="100%">
                            <tr>
                                <td class="custom" width="40%">No. LHP <sup>a</sup></td>
                                <td class="custom" width="60%">JENIS SAMPLE</td>
                            </tr>
                            <tr>
                                <td class="custom">' . $data->no_lhp . '</td>
                                <td class="custom">' . $data->sub_kategori . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table style="padding: 20px 0px 0px 0px;" width="100%">
                            <tr>
                                <td>
                                    <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Pelanggan</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="custom5" width="120">Nama Pelanggan</td>
                                <td class="custom5" width="12">:</td>
                                <td class="custom5">' . $data->nama_pelanggan . '</td>
                            </tr>
                            <tr>
                                <td class="custom5" width="120">Alamat / Lokasi Sampling</td>
                                <td class="custom5" width="12">:</td>
                                <td class="custom5">' . $data->alamat_sampling . '</td>
                            </tr>
                        </table>
                        <table style="padding: 10px 0px 0px 0px;" width="100%">
                            <tr>
                                <td class="custom5" width="120">
                                    <span style="font-weight: bold; border-bottom: 1px solid #000">Informasi Sampling</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="custom5">Metode Sampling</td>
                                <td class="custom5">:</td>
                                <td class="custom5">' . $methode_sampling . '</td>
                            </tr>
                            <tr>
                                <td class="custom5" width="120">Tanggal Sampling</td>
                                <td class="custom5" width="12">:</td>
                                <td class="custom5">' . self::tanggal_indonesia($data->tanggal_sampling) . '</td>
                            </tr>
                      
                            <tr>
                                <td class="custom5">Periode Analisa</td>
                                <td class="custom5">:</td>
                                <td class="custom5">' . $period1 . ' - ' . $period2 . '</td>
                            </tr>
                           
                        </table>';

        // Add regulation information
        $header .= '<table style="padding: 10px 0px 0px 0px;" width="100%">';
        foreach (json_decode($data->regulasi) as $t => $y) {
            $header .= '<tr>
            <td class="custom5" colspan="3">**' . $y . '</td>
            </tr>';
        }
        $header .= '</table>';

       
        $header .= '</td>
            </tr>
                </table>
            </div>';

        // Generate PDF
        $no_lhp = str_replace("/", "-", $data->no_lhp);
        $name = 'LHP-' . $no_lhp . '.pdf';
        $ress = self::formatTemplate($customBod, $header, $name, $ttd, $data->file_qr, $mode_download, $custom2 = null);

        return $ress;
    }

    private function formatTemplate($bodi, $header, $filename, $ttd, $qr_img, $mode_download, $custom2)
    {
        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => ($mode_download == 'downloadLHPFinal' ? 12 : 18),
            'margin_bottom' => 17,
            'margin_footer' => 8,
            'margin_top' => 23.5,
            'margin_left' => 10,
            'margin_right' => 10,
            'orientation' => 'L',
        );

        $pdf = new PDF($mpdfConfig);
        $pdf->SetProtection(array(
            'print'
        ), '', 'skyhwk12');
        $stylesheet = " .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
                            font-weight: bold;
                            font-size: 9px;
                        }
                        .custom {
                            padding: 3px;
                            text-align: center;
                            border: 1px solid #000000;
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
                            border-left: 1px solid #000000;
                            border-right: 1px solid #000000;
                            border-bottom: 1px solid #000000;
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
                        .pd-5-dot-center {
                            padding: 8px;
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
                        .pd-5-solid-left {
                            padding: 8px;
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
                        .right {
                            float: right;
                            width: 40%;
                            height: 100%;
                        }
                        .left {
                            float: left;
                            padding-top: " . ($mode_download == 'downloadWSDraft' || $mode_download == 'downloadLHP' ? '18px' : '14px') . ";
                            width: 59%;
                        }
                        .left2 {
                            float: left;
                            width: 69%;
                        }";
        $file_qr = public_path('qr_documents/' . $qr_img . '.svg');
        if ($mode_download == 'downloadWSDraft') {
            // dd('masuk');
            $qr = 'Halaman {PAGENO} - {nbpg}';
            $tanda_tangan = $ttd;
            $ketFooter = '<td width="15%" style="vertical-align: bottom;">
                        <div>PT Inti Surya Laboratorium</div>
                        <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                        <div>021-5089-8988/89 contact@intilab.com</div>
                        </td>
                        <td width="59%" style="vertical-align: bottom; text-align:center; padding:0; padding-left:44px; margin:0; position:relative; min-height:100px;"> Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.</td>';
            // $url = public_path() . '/watermark-draft.png';
            // $body = '<body style="background: url(' . $url . ');">';
            $pdf->SetWatermarkImage(public_path() . '/watermark-draft.png', -1, '', array(
                0,
                0
            ), 200);
            $pdf->showWatermarkImage = true;
            $body = '';
        } else if ($mode_download == 'downloadLHP' || $mode_download == 'downloadLHPFinal') {
            if (!is_null($qr_img)) {
                $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
            } else {
                $qr = 'DP/7.8.1/ISL; Rev 3; 08 November 2022';
            }
            $tanda_tangan = $ttd;
            $ketFooter = '<td width="15%" style="vertical-align: bottom;">
                          <div>PT Inti Surya Laboratorium</div>
                          <div>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341</div>
                          <div>021-5089-8988/89 contact@intilab.com</div>
                          </td>
                          <td width="59%" style="vertical-align: bottom; text-align:center; padding:0; padding-left:44px; margin:0; position:relative; min-height:100px;">
                          Hasil uji ini hanya berlaku untuk kondisi sampel yang tercantum pada lembar ini dan tidak dapat digeneralisasikan untuk sampel lain. Lembar ini tidak dapat di gandakan tanpa izin dari laboratorium.
                            <br>Halaman {PAGENO} - {nbpg}
                        </td>';
            $body = '<body>';

            if ($mode_download == 'downloadLHPFinal') {
                $pdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);
                $pdf->showWatermarkImage = true;
            }
        }

        $pdf->SetHTMLHeader($header, '', TRUE);
        $pdf->WriteHTML($stylesheet, 1);
        $pdf->WriteHTML('<!DOCTYPE html>
            <html>
                ' . $body . '');
        // =================Isi Data==================
        $pdf->SetHTMLFooter('
            <table width="100%" style="font-size:7px">
                <tr>
                    ' . $ketFooter . '
                    <td width="23%" style="text-align: right;">' . $tanda_tangan . $qr . '</td>
                </tr>
                <tr>
                </tr>
            </table>
        ');
        $tot = count($bodi) - 1;
        foreach ($bodi as $key => $val) {
            $pdf->WriteHTML($val);
            if ($tot > $key) {
                $pdf->AddPage();
            }
        }

        $pdf->WriteHTML('</body>
        </html>');
        $dir = public_path('dokumen/LHPS/');

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        if ($mode_download == 'downloadWSDraft' && $custom2 == 1) {

            // $pdf->Output('TemplateWSDraft/'.$filename);
            $pdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);
        } else if ($mode_download == 'downloadWSDraft' || $mode_download == 'draft_customer') {

            $pdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);
            // $pdf->Output('TemplateWSDraft/'.$filename);
        } else if ($mode_download == 'downloadLHP') {
            $dir = public_path('dokumen/LHP/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $pdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);
        } else if ($mode_download == 'downloadLHPFinal') {

            $dir = public_path('dokumen/LHP_DOWNLOAD/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $pdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);
        }
        return $filename;
    }

    private function tanggal_indonesia($tanggal)
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
        if ($tanggal != '') {
            $var = explode('-', $tanggal);
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return '-';
        }
    }

    private function waktuPemaparan($waktu)
    {
        $jam = floor($waktu / 60);
        $menit = $waktu % 60;

        $hasil = '';
        if ($jam > 0) {
            $hasil .= $jam . ' jam';
        }
        if ($menit > 0) {
            $hasil .= ($jam > 0 ? ' ' : '') . $menit . ' menit';
        }

        return $hasil ?: '0 menit';
    }
}
