<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use App\Models\Parameter;
use App\Models\OrderHeader;
use Illuminate\Support\Facades\DB;
use Exception;
use Mpdf;

class RenderData
{
    protected $id;
    protected $data;
    protected $pdf;
    protected $tgl_order;
    protected $db;
    protected $fileName;
    protected $detail;

    public function __construct($datas)
    {
        $this->id = $datas->id;
        $this->data = $datas->data;
        $this->pdf = $datas->pdf;
        $this->tgl_order = $datas->tgl_order;
        $this->db = $datas->db;
        $this->fileName = $datas->fileName;
        $this->detail = $datas->detail;
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

    private function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ".", ",");
        return $hasil_rupiah;
    }

    public function pdfNonKontrak()
    {
        $id = $this->id;
        $data = $this->data;
        $pdf = $this->pdf;
        $tgl_order = $this->tgl_order;
        $db = $this->db;
        $fileName = $this->fileName;
        try {
            //code...
            //return response()->json($data->wilayah,200);
            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="62%">KETERANGAN PENGUJIAN</th>
                            <th width="12%">Qty</th>
                            <th width="12%">HARGA SATUAN</th>
                            <th width="12 %">TOTAL HARGA</th>
                        </tr>
                    </thead>
                    <tbody>'
            );
            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {

                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);
                $kategori2Value = isset($kategori2[1]) ? $kategori2[1] : '';
                $penamaan_titik = "";
                if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                    if (is_array($a->penamaan_titik)) {
                        $filtered_array = array_filter($a->penamaan_titik, function ($value) {
                            return $value != "" && $value != " " && $value != "-";
                        });

                        if (!empty($filtered_array)) {
                            $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    } else {
                        // Handle string case
                        if ($a->penamaan_titik != " " && $a->penamaan_titik != "-") {
                            $penamaan_titik = "(" . $a->penamaan_titik . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    }
                } else {
                    $penamaan_titik = "";
                }

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px; padding:5px">
                            <b style="font-size: 13px;">' . $kategori2Value . " " . $penamaan_titik . "</b>
                            <hr>"
                );
                $reg__ = '';
                if ($a->regulasi != null && $a->regulasi != "" && $a->regulasi != 'null') {
                    foreach ($a->regulasi as $k => $v) {
                        if ($v != '') {
                            $regulasi = array_slice(explode("-", $v), 1);
                            $reg__ = implode("-", $regulasi);
                        }
                        if ($k == 0) {
                            $pdf->WriteHTML(
                                ' <u style="font-size: 13px;">' . $reg__ . "</u>"
                            );
                        } else {
                            $pdf->WriteHTML(
                                ' <br>
                                <u style="font-size: 13px;">' . $reg__ . "</u>"
                            );
                        }
                    }
                }

                foreach ($a->parameter as $keys => $values) {
                    $conn_param = new Parameter();
                    $d = $conn_param
                        ->where("id", explode(";", $values)[0])
                        ->where("is_active", true)
                        ->first();
                    if ($keys == 0) {
                        $pdf->WriteHTML(
                            ' <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    } else {
                        $pdf->WriteHTML(
                            ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    }
                }
                $volume = "";
                if ($a->kategori_1 == "1-Air") {
                    $volume =
                        " - Volume : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }
                $pdf->WriteHTML(
                    " <br>
                    <hr>" . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . $a->jumlah_titik . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_total) . '</td>
                    </tr>'
                );
                $i++;
            }
            $wilayah = explode("-", $data->wilayah, 2);
            if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi * $data->transportasi) . '</td>
                    </tr>'
                );
            }
            $perdiem_24 = "";
            $total_perdiem = 0;

            if ($data->jumlah_orang_24jam > 0 && $data->jumlah_orang_24jam != "" && $data->harga_24jam_personil_total != null) {
                $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                $total_perdiem =
                    $total_perdiem + $data->{'harga_24jam_personil_total'};
                // $i = $i + 1;
                // $pdf->WriteHTML('
                //         <tr>
                //             <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                //             <td style="font-size: 13px;padding: 5px;">Perdiem (24 Jam) : ' . $data->jumlah_orang_24jam . ' Personil / Hari</td>
                //             <td style="font-size: 13px; text-align:center;">' . $data->{'24jam_jumlah_hari'} . '</td>
                //             <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->{'harga_24jam_personil'}) . '</td>
                //             <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->{'harga_24jam_personil_total'}) . '</td>
                //         </tr>');
            }
            if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                    $i = $i + 1;
                }
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    </tr>'
                );
            }

            $biaya_lain = json_decode($data->biaya_lain);
            if ($biaya_lain != null) {
                $u = '';
                foreach ($biaya_lain as $k => $v) {
                    if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                        $i = $i + 1;
                    } else {
                        if ($u == $i) {
                            $i = $i + 1;
                        } else {
                            if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                                $i = $i + 1;
                            }
                        }
                    }
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Biaya : ' . $v->deskripsi . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->harga) . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->total_biaya) . '</td>
                        </tr>'
                    );
                    $u = $i;
                }
            }

            $biaya_preparasi = json_decode($data->biaya_preparasi_padatan);
            if ($biaya_preparasi != null) {
                // $i = $i + 1;
                foreach ($biaya_preparasi as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                            <td style="font-size: 13px;padding: 5px;">Biaya : ' . $v->deskripsi . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->harga) . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->harga) . '</td>
                        </tr>'
                    );
                }
            }
            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table width="100%" style="line-height: 2;">
                    <tr>
                        <td style="font-size: 10px;vertical-align: top;" width="64%">
                            <u>
                                <b>Syarat dan Ketentuan Pembayaran</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);
            //dedi 05-02-2025
            if ($syarat_ketentuan != null) {
                // $pdf->WriteHTML('<u><b>Syarat dan Ketentuan Pembayaran</b></u>');
                if (isset($syarat_ketentuan->pembayaran) && $syarat_ketentuan->pembayaran != null) {
                    if ($data->cash_discount_persen != null) {
                        $pdf->WriteHTML(
                            ' <br>
                            <span style="font-size: 10px !important;">- Cash Discount berlaku apabila pelunasan keseluruhan sebelum sampling.</span>'
                        );
                    }
                    foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                        $pdf->WriteHTML(
                            ' <br>
                            <span style="font-size: 10px !important;">- ' . $v . "</span>"
                        );
                    }
                }
            }

            $pdf->WriteHTML(
                '</td><td width="36%">
                    <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                        <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>SUB TOTAL</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($data->grand_total) . '</td>
                        </tr> '
            );
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Air ' . $data->discount_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_non_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Non Air ' . $data->discount_non_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_non_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Udara ' . $data->discount_udara . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_udara) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Emisi ' . $data->discount_emisi . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_emisi) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_transport > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Transport ' . $data->discount_transport . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_transport) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_perdiem > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Perdiem ' . $data->discount_perdiem . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_perdiem_24jam > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Perdiem 24 Jam ' . $data->discount_perdiem_24jam . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem_24jam) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Analisa + Operasional ' . $data->discount_gabungan . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Consultant ' . $data->discount_consultant . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Group ' . $data->discount_group . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }
            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Cash Disc. ' . $data->cash_discount_persen . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }
            if ($data->cash_discount != 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Cash Disc.</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->cash_discount) . '</td>
                    </tr> '
                );
            }
            $disc_custom = json_decode($data->custom_discount);
            if ($disc_custom != null) {
                foreach ($disc_custom as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . $v->deskripsi . '</td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->discount) . '</td>
                        </tr> '
                    );
                }
            }
            if ($data->total_dpp != $data->grand_total && $data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL SETELAH DISKON</b>
                        </td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }

            if ($data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPN 11%</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_ppn) . '</td>
                    </tr> '
                );
            }

            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPH ' . $data->pph . '%</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_pph) . '</td>
                    </tr> '
                );
            }

            $v = json_decode($data->biaya_di_luar_pajak);
            if ($v != null) {
                if ($v->body != null || $v->select) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>TOTAL SETELAH PAJAK</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($data->piutang) . '</td>
                        </tr> '
                    );
                }
                if ($v->select != null) {
                    foreach ($v->select as $k => $c) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                <td style="text-align:right;padding:5px;">' . $this->rupiah($c->harga) . '</td>
                            </tr> '
                        );
                    }
                }
                if ($v->body != null) {
                    foreach ($v->body as $k => $c) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                <td style="text-align:right;padding:5px;">' . $this->rupiah($c->harga) . '</td>
                            </tr> '
                        );
                    }
                }
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>TOTAL HARGA</b>
                    </td>
                    <td style="text-align:right;padding:5px;">' . $this->rupiah($data->biaya_akhir) . '</td>
                </tr> '
            );
            $pdf->WriteHTML("</table></td></tr></table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($data->keterangan_tambahan != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="font-size: 10px;">
                            <u>
                                <b>Keterangan Lain / Tambahan</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach (json_decode($data->keterangan_tambahan) as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="font-size: 10px;">- ' . $v . "</td>
                        </tr>"
                    );
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($syarat_ketentuan != null) {
                if ($syarat_ketentuan->umum != null) {
                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>Syarat dan Ketentuan Umum</b>
                                </u>
                            </td>
                        </tr>'
                    );
                    foreach ($syarat_ketentuan->umum as $k => $v) {
                        if ($v != "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan") {
                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="font-size: 10px;">- ' . $v . "</td>
                                </tr>"
                            );
                        }
                    }
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;font-size: 10px;" colspan="2">
                        <b>
                            <u>Sebagai tanda persetujuan, agar dapat menandatangani serta mengirimkan kembali kepada pihak kami melalui email : sales@intilab.com</u>
                        </b>
                    </td>
                </tr>'
            );
            $upd = "-";
            $user = MasterKaryawan::where('id', $data->sales_id)->first();
            $add = $user->nama_lengkap;
            $no_telp = " (" . $user->no_telpon . ")";
            if ($data->updateby != null) {
                $upd = $data->updateby->nama_lengkap;
            }
            // if ($data->approve_by != null) {
            //     $app = $data->approveby->nama_lengkap;
            // }
            if ($data->approved_by == null) {
                $st = "NOT APPROVED";
            } else {
                $st = "APPROVED";
            }
            // <span style="font-size:11px;">Approve By : ' . $app . '</span>
            // <span style="font-size:11px;">' . $this->tanggal_indonesia(date('Y-m-d')) . ' - ' . date('G:i') . '</span><br>
            $sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', true)->where('status', 1)->first();
            if (!is_null($sp)) {
                /* case akhir tahun
                   kontrak di bulan akhir pada current tahun  tapi ingin di jadwal di awal tahun
                */
                $nextCont = date('Y');
                $jadwal = Jadwal::where('is_active', true)->where('id_sampling', $sp->id)->select('tanggal')->first();
                if ($jadwal != null) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td>
                                <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                                <br>
                                <span style="font-size:11px;">Status : ' . $st . '</span>
                                <br>
                                <span style="font-size:11px;">Tanggal Sampling : ' . $this->tanggal_indonesia($jadwal->tanggal) . '</span>
                                <br>
                                <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                                <br>
                            </td>
                            <td style="font-size: 10px;text-align:center;">
                                <span>Menyetujui,</span>
                                <br>
                                <br>
                                <br>
                                <span>Nama (..............................................)</span>
                                <br>
                                <span>Jabatan (..............................................)</span>
                            </td> '
                    );
                } else {
                    $jadwal = Jadwal::id_samplingid_samplingwhere('is_active', true)->where('id_sampling', $sp->id)->select('tanggal')->first();
                    $pdf->WriteHTML(
                        ' <tr>
                            <td>
                                <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                                <br>
                                <span style="font-size:11px;">Status : ' . $st . '</span>
                                <br>
                                <span style="font-size:11px;">Tanggal Sampling : ' . $this->tanggal_indonesia($jadwal->tanggal) . '</span>
                                <br>
                                <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                                <br>
                            </td>
                            <td style="font-size: 10px;text-align:center;">
                                <span>Menyetujui,</span>
                                <br>
                                <br>
                                <br>
                                <span>Nama (..............................................)</span>
                                <br>
                                <span>Jabatan (..............................................)</span>
                            </td> '
                    );
                }
            } else {
                $pdf->WriteHTML(
                    ' <tr>
                        <td>
                            <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                            <br>
                            <span style="font-size:11px;">Status : ' . $st . '</span>
                            <br>
                            <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                            <br>
                        </td>
                        <td style="font-size: 10px;text-align:center;">
                            <span>Menyetujui,</span>
                            <br>
                            <br>
                            <br>
                            <span>Nama (..............................................)</span>
                            <br>
                            <span>Jabatan (..............................................)</span>
                        </td> '
                );
            }

            $pdf->WriteHTML("</tr></table>");

            $filePath = public_path('quotation/' . $fileName);

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (Exception $e) {
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

    public function pdfKontrak()
    {
        
        $id = $this->id;
        $data = $this->data;
        $pdf = $this->pdf;
        $tgl_order = $this->tgl_order;
        $db = $this->db;
        $fileName = $this->fileName;
        $detail = $this->detail;
        try {
            $order = OrderHeader::where('no_document', $data->no_document)->where('is_active', true)->first();

            $ord = '';
            if (!is_null($order)) {
                $ord = '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';
            }

            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="62%">KETERANGAN PENGUJIAN</th>
                            <th width="12%">Qty</th>
                            <th width="12%">HARGA SATUAN</th>
                            <th width="12 %">TOTAL HARGA</th>
                        </tr>
                    </thead>
                    <tbody>'
            );

            switch ($data->status_sampling) {
                case "S24":
                    $sampling = "SAMPLING 24 JAM";
                    break;
                case "SD":
                    $sampling = "SAMPLE DIANTAR";
                    break;
                default:
                    $sampling = "SAMPLING";
                    break;
            }
            $konsultant = "";
            $jab_pic_or = "";
            $jab_pic_samp = "";

            if ($data->konsultan != '') {
                $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
                $perusahaan = ' (' . strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan))) . ') ';
            } else {
                $perusahaan = strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan)));
            }

            $period = [];
            foreach ($detail as $key => $val) {
                if ($key == 0) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        array_push($period, $this->tanggal_indonesia($v_->periode_kontrak, "period"));
                        continue;
                    }
                } elseif ($key == count($detail) - 1) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        array_push($period, $this->tanggal_indonesia($v_->periode_kontrak, "period"));
                        continue;
                    }
                }
            }
            if (explode(" ", $period[0])[1] == explode(" ", $period[count($period) - 1])[1]) {
                $period = explode(" ", $period[0])[0] . " - " . $period[count($period) - 1];
            } else {
                $period = $period[0] . " - " . $period[count($period) - 1];
            }

            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);

                $penamaan_titik = "";
                if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                    if (is_array($a->penamaan_titik)) {
                        $filtered_array = array_filter($a->penamaan_titik, function ($value) {
                            return $value != "" && $value != " " && $value != "-";
                        });

                        if (!empty($filtered_array)) {
                            $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    } else {
                        // Handle string case
                        if ($a->penamaan_titik != " " && $a->penamaan_titik != "-") {
                            $penamaan_titik = "(" . $a->penamaan_titik . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    }
                } else {
                    $penamaan_titik = "";
                }

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                        <td style="font-size: 13px; padding: 5px;">
                            <b style="font-size: 13px;">' . $kategori2[1] . " " . $penamaan_titik . "</b>
                            <hr>"
                );

                if ($a->regulasi != null && $a->regulasi != "" && $a->regulasi != 'null') {
                    foreach ($a->regulasi as $k => $v) {
                        $reg__ = '';

                        if ($v != '') {
                            $regulasi = array_slice(explode("-", $v), 1);
                            $reg__ = implode("-", $regulasi);
                        }

                        if ($k == 0) {
                            $pdf->WriteHTML('<u style="font-size: 13px;">' . $reg__ . "</u>");
                        } else {
                            $pdf->WriteHTML('<br><u style="font-size: 13px;">' . $reg__ . "</u>");
                        }
                    }
                }

                foreach ($a->parameter as $keys => $values) {
                    $conn_param = new Parameter();
                    $d = $conn_param
                        ->where("id", explode(";", $values)[0])
                        ->where("is_active", true)
                        ->first();
                    if ($keys == 0) {
                        $pdf->WriteHTML(
                            ' <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    } else {
                        $pdf->WriteHTML(
                            ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    }
                }


                $volume = "";
                if ($a->kategori_1 == "1-Air") {
                    $volume =
                        " - Volume : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }
                $pdf->WriteHTML(
                    " <br>
                    <hr>" . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . $a->jumlah_titik * count($a->periode) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_satuan * ($a->jumlah_titik * count($a->periode))) . '</td>
                    </tr>'
                );
                //    $pdf->WriteHTML(
                //        '<tr>
                //            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                //            <td style="font-size: 13px; padding: 5px;"><b style="font-size: 13px;">' . $kategori2[1] . '</b></td>
                //            <td style="font-size: 13px; padding: 5px;text-align:center;">' . ($a->jumlah_titik * count($a->periode)) . '</td>
                //            <td style="font-size: 13px; padding: 5px;text-align:right;">' . $this->rupiah($a->harga_satuan) . '</td>
                //            <td style="font-size: 13px; padding: 5px;text-align:right;">' . $this->rupiah($a->harga_satuan * ($a->jumlah_titik * count($a->periode))) . '</td></tr>'
                //    );
            }
            $wilayah = explode("-", $data->wilayah, 2);
            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi_total / $data->transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi_total) . '</td>
                    </tr>'
                );
            }
            $perdiem_24 = "";
            $total_perdiem = 0;
            if (
                $data->jumlah_orang_24jam > 0 ||
                $data->jumlah_orang_24jam != ""
            ) {
                $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                $total_perdiem += $data->{'harga_24jam_personil_total'};
            }
            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    </tr>'
                );
            }

            if ($data->total_biaya_lain > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Biaya Lain - Lain</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->total_biaya_lain) . '</td>
                    </tr>'
                );
            }

            if ($data->total_biaya_preparasi > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Biaya preparasi</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->total_biaya_preparasi) . '</td>
                    </tr>'
                );
            }

            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table width="100%" style="line-height: 2;">
                    <tr>
                        <td style="font-size: 10px;vertical-align: top;" width="64%">
                            <u>
                                <b>Syarat dan Ketentuan Pembayaran</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);
            if ($syarat_ketentuan->pembayaran != null) {
                if ($data->cash_discount_persen != null) {
                    $pdf->WriteHTML(
                        ' <br>
                        <span style="font-size: 10px !important;">- Cash Discount berlaku apabila pelunasan keseluruhan sebelum sampling.</span>'
                    );
                }
                foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                    $pdf->WriteHTML(
                        ' <br>
                        <span style="font-size: 10px !important;">- ' . $v . "</span>"
                    );
                }
            }
            $pdf->WriteHTML(
                '</td><td width="36%">
                    <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px; margin-bottom: 10px;">
                        <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>SUB TOTAL</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($data->grand_total) . '</td>
                        </tr> '
            );
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Air ' . $data->discount_air . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_air) . '</td>
                    </tr>               '
                );
            }
            if ($data->total_discount_non_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Non Air ' . $data->discount_non_air . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_non_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Udara ' . $data->discount_udara . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_udara) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Emisi ' . $data->discount_emisi . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_emisi) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_transport > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Transport ' . $data->discount_transport . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_transport) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_perdiem > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Perdiem ' . $data->discount_perdiem . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_perdiem_24jam > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Perdiem 24 Jam ' . $data->discount_perdiem_24jam . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem_24jam) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Analisa + Operasional (%)</test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Consultant (%)</test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Group (%)</test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }

            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Persen (%)</test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }

            if ($data->total_cash_discount > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Cash</test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_cash_discount) . '</td>
                    </tr> '
                );
            }

            if ($data->total_custom_discount > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Custom Disc.</test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_custom_discount) . '</td>
                    </tr> '
                );
            }
            if ($data->total_dpp != $data->grand_total) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL SETELAH DISKON</b>
                            </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">PPN 11% </test>
                    <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_ppn) . '</td>
                </tr> '
            );

            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPH ' . $data->pph . '%</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_pph) . '</td>
                    </tr> '
                );
            }
            if ($data->piutang !== $data->biaya_akhir) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL HARGA SETELAH PAJAK
                        </td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->piutang) . '</td>
                    </tr> '
                );
            }

            if ($data->total_biaya_di_luar_pajak > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">BIAYA DILUAR PAJAK</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_biaya_di_luar_pajak) . '</td>
                    </tr>'
                );
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>TOTAL HARGA</b>
                        </test>
                    <td style="text-align:right;padding:5px;">' . $this->rupiah($data->biaya_akhir) . '</td>
                </tr> '
            );
            $pdf->WriteHTML("</table></td></tr></table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($data->keterangan_tambahan != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="font-size: 10px;">
                            <u>
                                <b>Keterangan Lain / Tambahan</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach (json_decode($data->keterangan_tambahan) as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="font-size: 10px;">- ' . $v . "</td>
                        </tr>"
                    );
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($syarat_ketentuan->umum != null) {
                $pdf->WriteHTML(
                    ' <tr style="font-size: 10px !important;">
                        <td style="font-size: 10px; !important">
                            <u>
                                <b>Syarat dan Ketentuan Umum</b>
                            </u>
                        </td>
                    <tr>'
                );
                foreach ($syarat_ketentuan->umum as $k => $v) {
                    // $pdf->WriteHTML('<tr><td style="font-size: 10px;">- '.$v.'</td></tr>');
                    if ($v != "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan") {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="font-size: 10px;">- ' . $v . "</td>
                            </tr>"
                        );
                    }
                }
            }

            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;font-size: 10px;" colspan="2">
                        <b>
                            <u>Sebagai tanda persetujuan, agar dapat menandatangani serta mengirimkan kembali kepada pihak kami melalui email : sales@intilab.com</u>
                        </b>
                    </td>
                </tr>'
            );
            $add = "-";
            $updd = "-";
            $user = MasterKaryawan::where('id', $data->sales_id)->first();
            $add = $user->nama_lengkap;
            $no_telp = " (" . $user->no_telpon . ")";
            if ($data->updateby != null) {
                $updd = $data->updateby->nama_lengkap;
            }

            // if ($data->approve_by != null) {
            //     $app = $data->approveby->nama_lengkap;
            // }

            if ($data->approved_by == null) {
                $st = "NOT APPROVED";
            } else {
                $st = "APPROVED";
            }
            // <span style="font-size:11px;">Approve By : ' . $app . '</span>
            // <span style="font-size:11px;">' . $this->tanggal_indonesia($data->tanggal_penawaran) . ' - ' . date('G:i') . '</span><br>
            $pdf->WriteHTML(
                ' <tr>
                    <td>
                        <span style="font-size:11px;">Administrasi : ' . $updd . '</span>
                        <br>
                        <span style="font-size:11px;">Status : ' . $st . '</span>
                        <br>
                        <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                        <br>
                    </td>
                    <td style="font-size: 10px;text-align:center;">
                        <span>Menyetujui,</span>
                        <br>
                        <br>
                        <br>
                        <span>Nama (..............................................)</span>
                        <br>
                        <span>Jabatan (..............................................)</span>
                    </td> '
            );
            $pdf->WriteHTML("</tr></table>");

            $per = [];
            // DISINI BERMASALAH
            $diluar_pajak = json_decode($data->diluar_pajak);
            foreach ($detail as $k => $v) {
                foreach (json_decode($v->data_pendukung_sampling) as $key => $value) {
                    // array_push($period, $this->tanggal_indonesia($value->periode_kontrak, 'period'));
                    array_push($per, $value->periode_kontrak);
                    if ($v->status_sampling == "S24") {
                        $sampling = "SAMPLING 24 JAM";
                    } elseif ($v->status_sampling == "SD") {
                        $sampling = "SAMPLE DIANTAR";
                    } else {
                        $sampling = "SAMPLING";
                    }

                    $pdf->SetHTMLHeader(
                        ' <table class="tabel">
                            <tr class="tr_top">
                                <td class="text-left text-wrap" style="width: 33.33%;">
                                    <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                                </td>
                                <td style="width: 33.33%; text-align: center;">
                                    <h5 style="text-align:center; font-size:14px;">
                                        <b>
                                            <u>QUOTATION</u>
                                        </b>
                                    </h5>
                                    <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
                                    <p style="font-size: 10px;text-align:center;">' . $this->tanggal_indonesia($value->periode_kontrak, "period") . '</p>
                                </td>
                                <td style="text-align: right;">
                                    <p style="font-size: 9px; text-align:right;">
                                        <b>PT INTI SURYA LABORATORIUM</b>
                                        <br>
                                        <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->cabang->alamat_cabang . "</span>
                                        <br>
                                        <span>T : " . $data->cabang->tlp_cabang . ' - sales@intilab.com</span>
                                        <br>www.intilab.com
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <table class="head2" width="100%">
                            <tr>
                                <td colspan="2">
                                    <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . $this->tanggal_indonesia($data->tanggal_penawaran) . '</p>
                                </td>
                                <td style="vertical-align: top; text-align:right;">
                                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">CONTRACT</span>
                                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-top:5px;" id="status_sampling">' . $sampling . '</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" width="80%">
                                    <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . preg_replace('/&AMP;+/', '&', $perusahaan) . '</h6>
                                </td>
                                <td style="vertical-align: top; text-align:right;">' . $ord . '</td>
                            </tr>
                            <tr>
                                <td style="width:35%;vertical-align:top;">
                                    <p style="font-size: 10px;">
                                        <u>Alamat Kantor :</u>
                                        <br>
                                        <span id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span>
                                        <br>
                                        <span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span>
                                        <br>
                                        <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . " - " . $data->no_pic_order . '</span>
                                        <br>
                                        <span id="email_pic_order">' . $data->email_pic_order . '</span>
                                    </p>
                                </td>
                                <td style="width: 30%; text-align: center;"></td>
                                <td style="text-align: left;vertical-align:top;">
                                    <p style="font-size: 10px;">
                                        <u>Alamat Sampling :</u>
                                        <br>
                                        <span id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span>
                                        <br>
                                        <span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span>
                                        <br>
                                        <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . '</span>
                                        <br>
                                        <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                                    </p>
                                </td>
                            </tr>
                        </table> '
                    );

                    $pdf->AddPage();

                    $pdf->WriteHTML(
                        ' <table class="table table-bordered" style="font-size:8px;border:1px solid;border-color:#000">
                            <thead class="text-center">
                                <tr style="page-break-inside:avoid">
                                    <th width="2%">NO</th>
                                    <th width="60%" class="text-center">KETERANGAN PENGUJIAN</th>
                                    <th>Qty</th>
                                    <th>HARGA SATUAN</th>
                                    <th>TOTAL HARGA</th>
                                </tr>
                            </thead>
                            <tbody>'
                    );

                    $i = 1;
                    foreach ($value->data_sampling as $b => $a) {
                        $kategori = explode("-", $a->kategori_1);
                        $kategori2 = explode("-", $a->kategori_2);
                        $penamaan_titik = "";
                        if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                            if (is_array($a->penamaan_titik)) {
                                $filtered_array = array_filter($a->penamaan_titik, function ($value) {
                                    return $value != "" && $value != " " && $value != "-";
                                });

                                if (!empty($filtered_array)) {
                                    $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                                } else {
                                    $penamaan_titik = "";
                                }
                            } else {
                                // Handle string case
                                if ($a->penamaan_titik != " " && $a->penamaan_titik != "-") {
                                    $penamaan_titik = "(" . $a->penamaan_titik . ")";
                                } else {
                                    $penamaan_titik = "";
                                }
                            }
                        } else {
                            $penamaan_titik = "";
                        }
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 13px; padding: 5px;">
                                    <b style="font-size: 13px;">' . $kategori2[1] . " " . $penamaan_titik . "</b>
                                    <hr>"
                        );

                        if ($a->regulasi != null && $a->regulasi != "" && $a->regulasi != 'null') {
                            foreach ($a->regulasi as $k => $z) {
                                $reg__ = '';
                                if ($z != '') {
                                    $regulasi = array_slice(explode("-", $z), 1);
                                    $reg__ = implode("-", $regulasi);
                                }
                                if ($k == 0) {
                                    $pdf->WriteHTML(
                                        ' <u style="font-size: 13px;">' . $reg__ . "</u>"
                                    );
                                } else {
                                    $pdf->WriteHTML(
                                        ' <br>
                                        <u style="font-size: 13px;">' . $reg__ . "</u>"
                                    );
                                }
                            }
                        }

                        $akreditasi_detail = [];
                        $non_akre_detail = [];
                        foreach ($a->parameter as $keys => $valuess) {
                            $conn_param = new Parameter();
                            $dParam = explode(";", $valuess);
                            $d = $conn_param
                                ->where("id", $dParam[0])
                                ->where("is_active", true)
                                ->first();
                            if ($d->status == 'AKREDITASI') {
                                array_push($akreditasi_detail, $d->nama_lab);
                            } else {
                                array_push($non_akre_detail, $d->nama_lab);
                            }
                            if ($keys == 0) {
                                $pdf->WriteHTML(
                                    ' <br>
                                    <hr>
                                    <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . ($d->status == 'AKREDITASI' ? '<b>' . $d->nama_regulasi . '</b>' : $d->nama_regulasi . '<sup style="font-size: 14px;"><u>x</u></sup>') . '</span>'
                                );
                            } else {
                                $pdf->WriteHTML(
                                    ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . ($d->status == 'AKREDITASI' ? '<b>' . $d->nama_regulasi . '</b>' : $d->nama_regulasi . '<sup style="font-size: 14px;"><u>x</u></sup>') . '</span>'
                                );
                            }
                        }
                        $volume = "";
                        if (explode("-", $a->kategori_1)[1] == "Air") {
                            $volume = " - Volume : " . number_format($a->volume / 1000, 1) . " L";
                        }
                        $pdf->WriteHTML(
                            " <br>
                            <hr>" . ' <b>
                                <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . ' - KAN (P) : ' . count($akreditasi_detail) . ' (' . count($non_akre_detail) . ')' . '</span>
                            </b>
                            </td>
                            <td style="vertical-align: middle;text-align:center;font-size: 13px; padding: 5px;">' . $a->jumlah_titik . '</td>
                            <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . $this->rupiah($a->harga_satuan) . '</td>
                            <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . $this->rupiah($a->harga_total) . '</td>
                            </tr>'
                        );
                    }
                }

                if ($v->transportasi > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $v->transportasi . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_transportasi) . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_transportasi * $v->transportasi) . '</td>
                        </tr>'
                    );
                }

                $perdiem_24 = "";
                $total_perdiem = 0;
                if (
                    $v->jumlah_orang_24jam > 0 &&
                    $v->jumlah_orang_24jam != ""
                ) {
                    $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                    $total_perdiem =
                        $total_perdiem + $v->{'harga_24jam_personil_total'};
                }
                if ($v->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
                            <td style="font-size: 13px; text-align:center;"></td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_perdiem_personil_total + $total_perdiem) . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        </tr>'
                    );
                }

                $biaya_lain = json_decode($v->biaya_lain);
                if ($biaya_lain != null) {
                    $i = $i + 1;
                    foreach ($biaya_lain as $s => $h) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 13px;padding: 5px;">Biaya : ' . $h->deskripsi . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($h->harga) . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($h->harga) . '</td>
                            </tr>'
                        );
                    }
                }

                $biaya_preparasi = $v->biaya_preparasi != null ? json_decode($v->biaya_preparasi) : [];
                if (count($biaya_preparasi) > 0) {
                    $i = $i + 1;
                    foreach ($biaya_preparasi as $s => $h) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 13px;padding: 5px;">Biaya : ' . $h->Deskripsi . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($h->Harga) . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($h->Harga) . '</td>
                            </tr>'
                        );
                    }
                }

                $pdf->WriteHTML("</tbody></table>");

                $pdf->WriteHTML(
                    ' <table width="100%" style="line-height: 2;">
                        <tr>
                            <td style="font-size: 10px;vertical-align: top;" width="62%">'
                );
                $pdf->WriteHTML(
                    '</td><td width="38%">
                        <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                            <tr>
                                <td style="text-align:center;padding:5px;">
                                    <b>SUB TOTAL</b>
                                </td>
                                <td style="text-align:right;padding:5px;" width="39%">' . $this->rupiah($v->grand_total) . '</td>
                            </tr> '
                );

                if (
                    $v->discount_air != null &&
                    $v->total_discount_air != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Air ' . $v->discount_air . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_air) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_non_air != null &&
                    $v->total_discount_non_air != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Non Air ' . $v->discount_non_air . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_non_air) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_udara != null &&
                    $v->total_discount_udara != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Udara ' . $v->discount_udara . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_udara) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_emisi != null &&
                    $v->total_discount_emisi != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Emisi ' . $v->discount_emisi . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_emisi) . '</td>
                        </tr> '
                    );
                }
                if (!is_null($diluar_pajak)) {
                    if ($v->discount_transport != null && $v->total_discount_transport > 0 && $diluar_pajak->transportasi != 'true') {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Transport ' . $v->discount_transport . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_transport) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->discount_perdiem != null && $v->discount_perdiem > 0 && $diluar_pajak->perdiem != 'true') {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Perdiem ' . $v->discount_perdiem . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->total_discount_perdiem_24jam != null && $v->total_discount_perdiem_24jam > 0 && $diluar_pajak->perdiem24jam != 'true') {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Perdiem 24 Jam ' . $v->discount_perdiem_24jam . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem_24jam) . '</td>
                            </tr> '
                        );
                    }
                } else {
                    if ($v->discount_transport != null && $v->total_discount_transport > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Transport ' . $v->discount_transport . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_transport) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->discount_perdiem != null && $v->discount_perdiem > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Perdiem ' . $v->discount_perdiem . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->total_discount_perdiem_24jam != null && $v->total_discount_perdiem_24jam > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Perdiem 24 Jam ' . $v->discount_perdiem_24jam . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem_24jam) . '</td>
                            </tr> '
                        );
                    }
                }

                if ($v->discount_gabungan != null && $v->discount_gabungan > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Analisa + Operasional ' . trim(str_replace('%', '', $v->discount_gabungan)) . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_gabungan) . '</td>
                        </tr> '
                    );
                }
                if ($v->discount_consultant != null && $v->discount_consultant > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Consultant ' . $v->discount_consultant . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_consultant) . '</td>
                        </tr> '
                    );
                }
                if ($v->discount_group != null && $v->discount_group > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Group ' . $v->discount_group . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_group) . '</td>
                        </tr> '
                    );
                }

                if ($v->total_cash_discount_persen != null && $v->total_cash_discount_persen > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. ' . $v->cash_discount_persen . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_cash_discount_persen) . '</td>
                        </tr> '
                    );
                }
                if ($v->cash_discount != null && $v->cash_discount != 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->cash_discount) . '</td>
                        </tr> '
                    );
                }

                if ($v->custom_discount != null && $v->total_custom_discount > 0) {
                    $custom_disc = json_decode($v->custom_discount);
                    foreach ($custom_disc as $key => $value) {
                        if ($value->discount != null && $value->discount != 0) {
                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $value->deskripsi . '</test>
                                    <td style="text-align:right;padding:5px;">' . self::rupiah($value->discount) . '</td>
                                </tr> '
                            );
                        }
                    }
                }

                if ($v->total_dpp != $v->grand_total) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>TOTAL SETELAH DISKON</b>
                                </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_dpp) . '</td>
                        </tr>'
                    );
                }

                if ($v->total_ppn > 0 && $v->total_ppn != "") {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">PPN ' . $v->ppn . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_ppn) . '</td>
                        </tr> '
                    );
                }

                if ($v->total_pph > 0 && $v->total_pph != "") {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">PPH ' . $v->pph . '%</td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_pph) . '</td>
                        </tr> '
                    );
                }

                if (!is_null($diluar_pajak)) {
                    $y = json_decode($v->biaya_di_luar_pajak);
                    // if ($y->body != null || $y->select) {
                    // }

                    if ($y->body != null || $y->select) {
                        if ($y->select != null) {
                            foreach ($y->select as $k => $c) {
                                if ($c->harga != null || $c->harga != 0) {
                                    $pdf->WriteHTML(
                                        ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $c->deskripsi . ' </test>
                                    <td style="text-align:right;padding:5px;">' . self::rupiah($c->harga) . '</td>
                                </tr> '
                                    );
                                }
                            }
                        }

                        if ($y->body != null) {
                            foreach ($y->body as $k => $c) {
                                if ($c->harga != null || $c->harga != 0) {
                                    $pdf->WriteHTML(
                                        ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $c->deskripsi . ' </test>
                                    <td style="text-align:right;padding:5px;">' . self::rupiah($c->harga) . '</td>
                                </tr> '
                                    );
                                }
                            }
                        }
                    }
                    if ($diluar_pajak->transportasi == 'true' && $v->total_discount_transport > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Transport ' . $v->discount_transport . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_transport) . '</td>
                            </tr> '
                        );
                    }

                    if ($diluar_pajak->perdiem == 'true' && $v->total_discount_perdiem > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Perdiem ' . $v->discount_perdiem . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem) . '</td>
                            </tr> '
                        );
                    }

                    if ($diluar_pajak->perdiem24jam == 'true' && $v->total_discount_perdiem_24jam > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">Contract Disc. Perdiem 24 Jam ' . $v->discount_perdiem_24jam . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem_24jam) . '</td>
                            </tr> '
                        );
                    }
                }

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL HARGA</b>
                            </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($v->biaya_akhir) . '</td>
                    </tr> '
                );

                $pdf->WriteHTML("</table></td></tr></table>");
            }

            $pdf->SetHTMLHeader(
                ' <table width="100%">
                    <tr>
                        <td class="text-left text-wrap">
                            <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                        </td>
                        <td style="width: 60%; text-align: center;">
                            <h5 style="text-align:center; font-size:11px;">
                                <b>
                                    <u>Rincian Kontrak Pengujian - Periode : ' . $period . '</u>
                                </b>
                            </h5>
                            <p style="font-size: 10px;text-align:center;">
                                <b>' . $konsultant . preg_replace('/&AMP;+/', '&', $perusahaan) . '</b>
                            </p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;"> No Contract : ' . $data->no_document . ' </p>
                            <p style="font-size: 9px; text-align:right;">
                                <b>PIC SALES <b> : ' . $add . '
                            </p>
                        </td>
                    </tr>
                </table> '
            );

            $pdf->AddPage("L");
            $pdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);

            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;" width="100%">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;" rowspan="2">NO</th>
                            <th width="25%" rowspan="2">KETERANGAN PENGUJIAN</th>'
            );
            $a = 1;
            foreach ($per as $c) {
                $pdf->WriteHTML("<th>" . $a++ . "</th>");
            }
            $pdf->WriteHTML(
                ' <th rowspan="2">TOTAL</th>
                <th rowspan="2">HARGA SATUAN</th>
                <th rowspan="2">TOTAL HARGA</th></tr><tr>'
            );
            foreach ($per as $c) {
                $pdf->WriteHTML("<th>" . date("M-y", strtotime($c)) . "</th>");
            }
            $pdf->WriteHTML("</tr></thead><tbody>");
            $i = 1;
            $t = count(json_decode($data->data_pendukung_sampling, true));
            $katgor = [];
            $x_ = 0;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                array_push($katgor, $a->kategori_2);

                // if (is_array($a->penamaan_titik)) {
                //     $penamaan_titik = "(" . implode(", ", $a->penamaan_titik) . ")";
                // } else {
                //     $penamaan_titik = "(" . $a->penamaan_titik . ")";
                // }
                $x_ = $key;

                if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                    if (is_array($a->penamaan_titik)) {
                        $filtered_array = array_filter($a->penamaan_titik, function ($value) {
                            return $value != "" && $value != " " && $value != "-";
                        });

                        if (!empty($filtered_array)) {
                            $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    } else {
                        $penamaan_titik = "(" . $a->penamaan_titik . ")";
                    }
                } else {
                    $penamaan_titik = "";
                }

                $th_left = implode(' ', [
                    strtoupper(explode("-", $a->kategori_1)[1]),
                    strtoupper(explode("-", htmlspecialchars_decode($a->kategori_2))[1]),
                    $a->jumlah_titik,
                    $x_,
                    $penamaan_titik,
                    $a->total_parameter,
                    implode(" ", $a->parameter)
                ]);

                $th_left1 = strtoupper(explode("-", $a->kategori_2)[1]);
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i++ . '</td>
                        <td style="font-size: 8px; padding: 5px;">
                            <b>' . $th_left1 . "</b>
                        </td>"
                );
                foreach ($detail as $key => $value) {
                    foreach (json_decode($value->data_pendukung_sampling) as $keys => $values) {
                        if (is_array($values->data_sampling)) {
                            $num_ = $values->data_sampling;
                        } else {
                            $num_ = json_decode($values->data_sampling);
                        }
                        $bollean = false;
                        $periode_found = [];
                        foreach ($num_ as $key_ => $val_) {
                            if ($val_->penamaan_titik && $val_->penamaan_titik != "") {
                                if (is_array($val_->penamaan_titik)) {
                                    $filtered_array = array_filter($val_->penamaan_titik, function ($value) {
                                        return $value != "" && $value != " " && $value != "-";
                                    });

                                    if (!empty($filtered_array)) {
                                        $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                                    } else {
                                        $penamaan_titik = "";
                                    }
                                } else {
                                    $penamaan_titik = "(" . $val_->penamaan_titik . ")";
                                }
                            } else {
                                $penamaan_titik = "";
                            }

                            $td_kat = implode(" ", [
                                strtoupper(explode("-", $val_->kategori_1)[1]),
                                strtoupper(explode("-", htmlspecialchars_decode($val_->kategori_2))[1]),
                                $val_->jumlah_titik,
                                $x_,
                                $penamaan_titik,
                                $val_->total_parameter,
                                implode(" ", $val_->parameter)
                            ]);
                            $td_kat1 = strtoupper(
                                explode("-", $a->kategori_2)[1]
                            );

                            if (in_array($values->periode_kontrak, $a->periode) && !in_array($values->periode_kontrak, $periode_found) && $th_left == $td_kat) {
                                $periode_found[] = $values->periode_kontrak;
                                $pdf->WriteHTML(
                                    '<td style="font-size: 8px; text-align:center;">' . $val_->jumlah_titik . "</td>"
                                );
                                $bollean = true;
                            }
                        }
                        if ($bollean == false) {
                            $pdf->WriteHTML("<td></td>");
                        }
                    }
                }

                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:center;">' . (int) $a->jumlah_titik * count($a->periode) . "</td>"
                );
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($a->harga_satuan) . "</td>"
                );
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($a->harga_satuan * ((int) $a->jumlah_titik * count($a->periode))) . "</td>"
                );
                $pdf->WriteHTML("</tr>");
                $x_++;
            }

            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;">' . $v->transportasi . "</td>"
                    );
                    $enum_ = $v->harga_transportasi;
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;">' . $data->transportasi . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi_total / $data->transportasi) . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi_total) . '</td></tr>'
                );
            }

            $total_perdiem = 0;
            $perdiem_24 = $data->harga_24jam_personil_total > 0
                ? "Termasuk Perdiem (24 Jam)"
                : "";
            $total_perdiem += $data->harga_24jam_personil_total > 0
                ? $data->{'harga_24jam_personil_total'}
                : 0;

            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Perdiem ' . $perdiem_24 . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td></tr>'
                );
            }

            if ($data->total_biaya_lain > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Biaya lain - Lain ' . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->total_biaya_lain) . '</td></tr>'
                );
            }

            if ($data->total_biaya_preparasi > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Biaya Preparasi ' . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->total_biaya_preparasi) . '</td></tr>'
                );
            }

            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;margin-top:10px;">
                    <thead class="text-center">
                        <tr>
                            <th style="font-size: 8px;">KETERANGAN HARGA PENGUJIAN</th>'
            );
            foreach ($per as $c) {
                $pdf->WriteHTML(
                    '<th style="font-size: 8px;">' . date("M-y", strtotime($c)) . "</th>"
                );
            }

            $pdf->WriteHTML(
                ' <th style="font-size: 8px;">TOTAL HARGA</th></tr></thead><tbody>'
            );

            // TOTAL ANALISA
            $pdf->WriteHTML(
                '<tr>
                <td style="text-align:center;font-size: 8px;">TOTAL ANALISA</td>'
            );
            $total_harga_analisa = 0;
            foreach ($detail as $key => $value) {
                foreach (json_decode($value->data_pendukung_sampling) as $keys => $values) {
                    $tot_harga = 0;
                    if (is_array($values->data_sampling)) {
                        foreach ($values->data_sampling as $b => $a) {
                            $tot_harga = $tot_harga + $a->harga_total;
                        }
                    } else {
                        foreach (json_decode($values->data_sampling) as $b => $a) {
                            $tot_harga = $tot_harga + $a->harga_total;
                        }
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($tot_harga) . "</td>"
                    );
                    $total_harga_analisa = $total_harga_analisa + $tot_harga;
                }
            }
            $pdf->WriteHTML(
                ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_analisa) . "</td></tr>"
            );

            // TOTAL TRANSPORT
            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">TOTAL TRANSPORT</td>'
                );
                $total_harga_transport = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->harga_transportasi_total) . "</td>"
                    );
                    $total_harga_transport =
                        $total_harga_transport + $value->harga_transportasi_total;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_transport) . "</td></tr>"
                );
            }

            // TOTAL PERDIEM 24 JAM
            $perdiem_24 = "";
            $total_perdiem = 0;
            if ($data->harga_24jam_personil_total != 0) {
                $perdiem_24 = "TERMASUK PERDIEM (24 JAM)";
                // $pdf->WriteHTML('<tr><td style="text-align:center;font-size: 8px;">TOTAL PERDIEM 24 JAM</td>');
                $total_harga_24jam_perdiem = 0;
                foreach ($detail as $key => $value) {
                    // $pdf->WriteHTML('<td style="font-size: 8px; text-align:right; padding: 5px;">' . Self::rupiah($value->harga_24jam_personil_total) . '</td>');
                    $total_harga_24jam_perdiem =
                        $total_harga_24jam_perdiem +
                        $value->harga_24jam_personil_total;
                }
                $total_perdiem = $total_perdiem + $total_harga_24jam_perdiem;
                // $pdf->WriteHTML('<td style="font-size: 8px; text-align:right; padding: 5px;">' . Self::rupiah($total_harga_24jam_perdiem) . '</td></tr>');
            }

            if ($data->perdiem_jumlah_orang > 0 || $data->jumlah_orang_24jam > 0) {
                // TOTAL PERDIEM
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">TOTAL PERDIEM ' . $perdiem_24 . "</td>"
                );
                $total_harga_perdiem = 0;
                foreach ($detail as $key => $value) {
                    $harga_perdiem = 0;
                    if ($value->jumlah_orang_24jam > 0) {
                        $harga_perdiem =
                            $harga_perdiem + $value->harga_24jam_personil_total;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->harga_perdiem_personil_total + $harga_perdiem) . "</td>"
                    );
                    $total_harga_perdiem =
                        $total_harga_perdiem + $value->harga_perdiem_personil_total;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_perdiem + $total_perdiem) . "</td></tr>"
                );
            }

            // BIAYA LAIN-LAIN
            if ($data->total_biaya_lain > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">BIAYA LAIN - LAIN</td>'
                );
                $total_biaya_lain = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_biaya_lain) . "</td>"
                    );
                    $total_biaya_lain += $value->total_biaya_lain;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_biaya_lain) . "</td></tr>"
                );
            }

            // BIAYA PREPARASI
            if ($data->total_biaya_preparasi > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">BIAYA PREPARASI</td>'
                );
                $total_biaya_preparasi = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_biaya_preparasi) . "</td>"
                    );
                    $total_biaya_preparasi += $value->total_biaya_preparasi;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_biaya_preparasi) . "</td></tr>"
                );
            }

            // TOTAL HARGA PENGUJIAN
            $pdf->WriteHTML(
                '<tr>
                <td style="text-align:center;font-size: 8px;"><b>TOTAL HARGA PENGUJIAN</b></td>'
            );
            $total_harga_pengujian = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($value->grand_total) . "</b></td>"
                );
                $total_harga_pengujian =
                    $total_harga_pengujian + $value->grand_total;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($total_harga_pengujian) . "</b></td></tr>"
            );

            // DISCOUNT AIR
            if ($value->discount_air != null && $value->total_discount_air > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - AIR (%)</td>'
                );
                $total_harga_discount_air = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_air) . "</td>"
                    );
                    $total_harga_discount_air =
                        $total_harga_discount_air + $value->total_discount_air;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_air) . "</td></tr>"
                );
            }
            // DISCOUNT NON AIR
            if ($value->total_discount_non_air != null && $value->total_discount_non_air > 0) {

                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - NON-AIR (%)</td>'
                );
                $total_harga_discount_non_air = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_non_air) . "</td>"
                    );
                    $total_harga_discount_non_air += $value->total_discount_non_air;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_non_air) . "</td></tr>"
                );
            }

            // DISCOUNT UDARA
            if ($value->discount_udara != null && $value->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - UDARA (%)</td>'
                );
                $total_harga_discount_udara = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_udara) . "</td>"
                    );
                    $total_harga_discount_udara += $value->total_discount_udara;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_udara) . "</td></tr>"
                );
            }

            // DISCOUNT EMISI
            if ($value->discount_emisi != null && $value->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - EMISI (%)</td>'
                );
                $total_harga_discount_emisi = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_emisi) . "</td>"
                    );
                    $total_harga_discount_emisi =
                        $total_harga_discount_emisi +
                        $value->total_discount_emisi;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_emisi) . "</td></tr>"
                );
            }
            $diluar_pajak = json_decode($data->diluar_pajak);

            if (!is_null($diluar_pajak)) {
                //Diskon TRANSPORT
                if ($data->total_discount_transport > 0 && $diluar_pajak->transportasi != 'true') {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - TRANSPORT (%)</td>'
                    );
                    $total_harga_discount_transport = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_transport) . "</td>"
                        );
                        $total_harga_discount_transport =
                            $total_harga_discount_transport +
                            $value->total_discount_transport;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_transport) . "</td></tr>"
                    );
                }
                //Diskon PERDIEM
                if ($value->discount_perdiem != null && $value->total_discount_perdiem > 0 && $diluar_pajak->perdiem != 'true') {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM (%)</td>'
                    );
                    $total_harga_discount_perdiem = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem) . "</td>"
                        );
                        $total_harga_discount_perdiem =
                            $total_harga_discount_perdiem +
                            $value->total_discount_perdiem;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_perdiem) . "</td></tr>"
                    );
                }
                //Diskon Perdiem 24 Jam
                if ($data->total_discount_perdiem_24jam > 0 && $diluar_pajak->perdiem24jam != 'true') {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM 24 JAM (%)</td>'
                    );
                    $total_harga_discount__perdiem24 = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem_24jam) . "</td>"
                        );
                        $total_harga_discount__perdiem24 =
                            $total_harga_discount__perdiem24 +
                            $value->total_discount_perdiem_24jam;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount__perdiem24) . "</td></tr>"
                    );
                }
            } else {
                if ($data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - TRANSPORT (%)</td>'
                    );
                    $total_harga_discount_transport = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_transport) . "</td>"
                        );
                        $total_harga_discount_transport =
                            $total_harga_discount_transport +
                            $value->total_discount_transport;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_transport) . "</td></tr>"
                    );
                }
                //Diskon PERDIEM
                if ($value->discount_perdiem != null && $value->total_discount_perdiem > 0) {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM (%)</td>'
                    );
                    $total_harga_discount_perdiem = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem) . "</td>"
                        );
                        $total_harga_discount_perdiem =
                            $total_harga_discount_perdiem +
                            $value->total_discount_perdiem;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_perdiem) . "</td></tr>"
                    );
                }
                //Diskon Perdiem 24 Jam
                if ($data->discount_perdiem_24jam != null && $data->total_discount_perdiem_24jam > 0) {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM 24 JAM (%)</td>'
                    );
                    $total_harga_discount__perdiem24 = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem_24jam) . "</td>"
                        );
                        $total_harga_discount__perdiem24 =
                            $total_harga_discount__perdiem24 +
                            $value->total_discount_perdiem_24jam;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount__perdiem24) . "</td></tr>"
                    );
                }
            }

            //Diskon GABUNGAN
            if ($value->discount_gabungan != null && $value->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - ANALISA + OPERASIONAL (%)</td>'
                );
                $total_harga_discount_gabungan = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_gabungan) . "</td>"
                    );
                    $total_harga_discount_gabungan =
                        $total_harga_discount_gabungan +
                        $value->total_discount_gabungan;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_gabungan) . "</td></tr>"
                );
            }

            // //Diskon CONSULTANT
            if ($value->discount_consultant != null && $value->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - CONSULTANT (%)</td>'
                );
                $total_harga_discount_consultant = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_consultant) . "</td>"
                    );
                    $total_harga_discount_consultant =
                        $total_harga_discount_consultant +
                        $value->total_discount_consultant;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_consultant) . "</td></tr>"
                );
            }

            //Diskon GROUP
            if ($value->discount_group != null && $value->total_discount_group > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - GROUP (%)</td>'
                );
                $total_harga_discount_group = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_group) . "</td>"
                    );
                    $total_harga_discount_group =
                        $total_harga_discount_group +
                        $value->total_discount_group;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_group) . "</td></tr>"
                );
            }

            // DISCOUNT CASH DISCOUNT PERSEN
            if ($value->cash_discount_persen != null && $value->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - CASH DISCOUNT PERSEN (%)</td>'
                );
                $total_harga_discount_cash_per = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_cash_discount_persen) . "</td>"
                    );
                    $total_harga_discount_cash_per =
                        $total_harga_discount_cash_per +
                        $value->total_cash_discount_persen;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_cash_per) . "</td></tr>"
                );
            }

            // DISCOUNT CASH DISCOUNT
            if ($data->total_cash_discount > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - CASH DISCOUNT</td>'
                );
                $total_harga_discount_cash = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->cash_discount) . "</td>"
                    );
                    $total_harga_discount_cash =
                        $total_harga_discount_cash + $value->cash_discount;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_cash) . "</td></tr>"
                );
            }

            // CUSTOM DISCOUNT
            if ($data->total_custom_discount > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CUSTOM DISCOUNT</td>'
                );
                $total_custom_discount = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_custom_discount) . "</td>"
                    );
                    $total_custom_discount += $value->total_custom_discount;

                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_custom_discount) . "</td></tr>"
                );
            }

            // TOTAL HARGA SETELAH DISCOUNT
            if ($data->total_dpp != $data->grand_total) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;"><b>TOTAL HARGA SETELAH DISCOUNT</b></td>'
                );
                $total_harga_setelah_discount = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($value->total_dpp) . "</b></td>"
                    );
                    $total_harga_setelah_discount += $value->total_dpp;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($total_harga_setelah_discount) . "</b></td></tr>"
                );
            }

            // PPN
            if ($data->total_ppn > 0 && $data->total_ppn != null) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">PPN ' . $detail[0]->ppn . '"%</td>'
                );
                $total_harga_ppn = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_ppn) . "</td>"
                    );
                    $total_harga_ppn = $total_harga_ppn + $value->total_ppn;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_ppn) . "</td></tr>"
                );
            }

            // PPH
            if ($value->total_pph != "" && $value->total_pph != "0.00") {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">PPH (' . $detail[0]->pph . "%)</td>"
                );
                $total_harga_pph = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_pph) . "</td>"
                    );
                    $total_harga_pph = $total_harga_pph + $value->total_pph;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_pph) . "</td></tr>"
                );
            }

            // BIAYA DI LUAR PAJAK
            if ($data->total_biaya_di_luar_pajak > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">BIAYA DI LUAR PAJAK</td>'
                );
                $total_biaya_di_luar_pajak = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_biaya_di_luar_pajak) . "</td>"
                    );
                    $total_biaya_di_luar_pajak += $value->total_biaya_di_luar_pajak;

                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_biaya_di_luar_pajak) . "</td></tr>"
                );
            }

            // START DATA DISKON DI LUAR PAJAK
            if (!is_null($diluar_pajak)) {
                // DISKON TRANSPORTASI
                if ($diluar_pajak->transportasi == 'true' && $data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - TRANSPORT (%)</td>'
                    );
                    $total_diskon_transpotasi = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_transport) . "</td>"
                        );
                        $total_diskon_transpotasi += $value->total_discount_transport;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_diskon_transpotasi) . "</td></tr>"
                    );
                }

                // DISKON PERDIEM
                if ($diluar_pajak->perdiem == 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM (%)</td>'
                    );
                    $total_diskon_perdiem = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem) . "</td>"
                        );
                        $total_diskon_perdiem += $value->total_discount_perdiem;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_diskon_perdiem) . "</td></tr>"
                    );
                }

                // DISKON PERDIEM 24 JAM
                if ($diluar_pajak->perdiem24jam == 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM 24 JAM (%)</td>'
                    );
                    $total_diskon_perdiem_24 = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem_24jam) . "</td>"
                        );
                        $total_diskon_perdiem_24 += $value->total_discount_perdiem_24jam;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_diskon_perdiem_24) . "</td></tr>"
                    );
                }
            }

            // END DATA DISKON

            // TOTAL BIAYA AKHIR
            $pdf->WriteHTML(
                '<tr><td style="text-align:center;font-size: 8px;"><b>TOTAL BIAYA AKHIR</b></td>'
            );
            $total_biaya_akhir = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($value->biaya_akhir) . "</b></td>"
                );
                $total_biaya_akhir =
                    $total_biaya_akhir + $value->biaya_akhir;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($total_biaya_akhir) . "</b></td></tr>"
            );

            $pdf->WriteHTML(
                '</tbody></table><table width="100%" style="margin-top:10px;"><tr>
                        <td width="40%"></td>'
            );
            $pdf->WriteHTML(
                ' <td style="font-size: 10px;text-align:center;">
                    <span>Tangerang, ' . self::tanggal_indonesia($data->tanggal_penawaran) . '</span>
                    <br>
                    <span>
                        <b>PT INTI SURYA LABORATORIUM</b>
                    </span>
                    <br>
                    <br>
                    <br>
                </td>'
            );
            $pdf->WriteHTML(
                ' <td style="font-size: 10px;text-align:center;">
                    <span>Menyetujui,</span>
                    <br>
                    <span>
                        <b>' . $data->nama_perusahaan . '</b>
                    </span>
                    <br>
                    <br>
                    <br>
                </td>
                </tr>'
            );
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(" <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>");
            $pdf->WriteHTML(' <tr>
                <td></td>
                <td style="font-size: 10px;text-align:center;">Nama&nbsp;&nbsp;&nbsp;(..............................................)</td>
                <td style="font-size: 10px;text-align:center;">Nama&nbsp;&nbsp;&nbsp;(..............................................)</td>
            </tr>');
            $pdf->WriteHTML(' <tr>
                <td></td>
                <td style="font-size: 10px;text-align:center;">Jabatan (..............................................)</td>
                <td style="font-size: 10px;text-align:center;">Jabatan (..............................................)</td>
            </tr>');
            $pdf->WriteHTML("</table>");
            $filePath = public_path('quotation/' . $fileName);

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (Exception $e) {
            throw new Exception("Message : " . $e->getMessage() . ", Line : " . $e->getLine() . ", File : " . $e->getFile(), 401);
        }
    }

    // ====== UNUSED ======
    public function newpdfNonKontrak()
    {
        $id = $this->id;
        $data = $this->data;
        $pdf = $this->pdf;
        $tgl_order = $this->tgl_order;
        $db = $this->db;
        $fileName = $this->fileName;

        try {
            $pdf->WriteHTML('<table class="table table-bordered" style="font-size: 8px;">
                 <thead class="text-center">
                     <tr>
                         <th width="2%" style="padding: 5px !important;">NO</th>
                         <th width="62%">KETERANGAN PENGUJIAN</th>
                         <th width="12%">Qty</th>
                         <th width="12%">HARGA SATUAN</th>
                         <th width="12 %">TOTAL HARGA</th>
                     </tr>
                 </thead>
                 <tbody>');
            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {

                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);
                $penamaan_titik = "";
                $kategori2Value = isset($kategori2[1]) ? $kategori2[1] : '';
                if ($a->penamaan_titik != null || $a->penamaan_titik != "") {
                    $penamaan_titik = "(" . htmlspecialchars_decode($a->penamaan_titik) . ")";
                }

                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td><td style="font-size: 13px; padding:5px"><b style="font-size: 13px;">' . $kategori2Value . " " . $penamaan_titik . "</b><hr>"
                );

                if ($a->regulasi != null && $a->regulasi != "" && $a->regulasi != 'null') {
                    foreach ($a->regulasi as $k => $v) {
                        $reg__ = '';

                        if ($v != '') {
                            $regulasi = array_slice(explode("-", $v), 1);
                            $reg__ = implode("-", $regulasi);
                        }
                        if ($k == 0) {
                            $pdf->WriteHTML(
                                '<u style="font-size: 13px;">' .
                                $reg__ .
                                "</u>"
                            );
                        } else {
                            $pdf->WriteHTML(
                                '<br><u style="font-size: 13px;">' .
                                $reg__ .
                                "</u>"
                            );
                        }
                    }
                }

                foreach ($a->parameter as $keys => $values) {
                    $d = Parameter::where("id", explode(";", $values)[0])
                        ->where("is_active", true)
                        ->first();
                    if ($keys == 0) {
                        $pdf->WriteHTML('
                            <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span>"
                        );
                    } else {
                        $pdf->WriteHTML(
                            ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    }
                }
                $volume = "";
                if ($a->kategori_1 == "1-Air") {
                    $volume =
                        " - Volume : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }
                $pdf->WriteHTML(
                    '<br>
                    <hr>' . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . $a->jumlah_titik . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_total) . '</td>
                    </tr>'
                );
                $i++;
            }
            $wilayah = explode("-", $data->wilayah, 2);
            if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi * $data->transportasi) . '</td>
                    </tr>'
                );
            }
            $perdiem_24 = "";
            $total_perdiem = 0;

            if (
                $data->jumlah_orang_24jam > 0 ||
                $data->jumlah_orang_24jam != "" || $data->harga_24jam_personil_total != null
            ) {
                $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                $total_perdiem =
                    $total_perdiem + $data->{'harga_24jam_personil_total'};
            }
            if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                    $i = $i + 1;
                }
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    </tr>'
                );
            }

            $biaya_lain = json_decode($data->biaya_lain);
            if ($biaya_lain != null) {
                $u = '';
                foreach ($biaya_lain as $k => $v) {
                    if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                        $i = $i + 1;
                    } else {
                        if ($u == $i) {
                            $i = $i + 1;
                        }
                    }
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Biaya : ' . $v->deskripsi . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->harga) . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->total_biaya) . '</td>
                        </tr>'
                    );
                    $u = $i;
                }
            }
            $biaya_preparasi = json_decode($data->biaya_preparasi_padatan);
            if ($biaya_preparasi != null) {
                // $i = $i + 1;
                foreach ($biaya_preparasi as $k => $v) {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                            <td style="font-size: 13px;padding: 5px;">Biaya : ' . $v->deskripsi . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->harga) . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($v->harga) . '</td>
                        </tr>'
                    );
                }
            }
            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                '<table width="100%" style="line-height: 2;">
                    <tr>
                        <td style="font-size: 10px;vertical-align: top;" width="64%">
                            <u>
                                <b>Syarat dan Ketentuan Pembayaran</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);
            if ($syarat_ketentuan != null) {
                // $pdf->WriteHTML('<u><b>Syarat dan Ketentuan Pembayaran</b></u>');
                if ($syarat_ketentuan->pembayaran != null) {
                    if ($data->cash_discount_persen != null) {
                        $pdf->WriteHTML(
                            '<br><span style="font-size: 10px !important;">- Cash Discount berlaku apabila pelunasan keseluruhan sebelum sampling.</span>'
                        );
                    }
                    foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                        $pdf->WriteHTML(
                            '<br>
                            <span style="font-size: 10px !important;">- ' . $v . "</span>"
                        );
                    }
                }
            }

            $pdf->WriteHTML(
                '</td>
                <td width="36%">
                    <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                        <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>SUB TOTAL</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($data->grand_total) . '</td>
                        </tr>'
            );
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Air ' . $data->discount_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_air) . '</td>
                    </tr>'
                );
            }
            if (
                $data->total_discount_non_air > 0
            ) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Non Air ' . $data->discount_non_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_non_air) . '</td>
                    </tr>'
                );
            }
            if (
                $data->total_discount_udara > 0
            ) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Udara ' . $data->discount_udara . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_udara) . '</td>
                    </tr>'
                );
            }
            if (
                $data->total_discount_emisi > 0
            ) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Emisi ' . $data->discount_emisi . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_emisi) . '</td>
                    </tr>'
                );
            }
            if ($data->total_discount_transport > 0) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Transport ' . $data->discount_transport . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_transport) . '</td>
                    </tr>'
                );
            }
            if ($data->total_discount_perdiem > 0) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Perdiem ' . $data->discount_perdiem . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem) . '</td>
                    </tr>'
                );
            }
            if ($data->total_discount_perdiem_24jam > 0) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Perdiem 24 Jam ' . $data->discount_perdiem_24jam . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem_24jam) . '</td>
                    </tr>'
                );
            }
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Gabungan ' . $data->discount_gabungan . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Consultant ' . $data->discount_consultant . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Group ' . $data->discount_group . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }

            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Cash Disc. ' . $data->cash_discount_persen . ' %</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }
            if ($data->cash_discount != 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Cash Disc.</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->cash_discount) . '</td>
                    </tr> '
                );
            }
            $disc_custom = json_decode($data->custom_discount);
            if ($disc_custom != null) {
                foreach ($disc_custom as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . $v->deskripsi . '</td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->discount) . '</td>
                        </tr> '
                    );
                }
            }
            if ($data->total_dpp != $data->grand_total && $data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL SETELAH DISKON</b>
                        </td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }

            if ($data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPN 11%</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_ppn) . '</td>
                    </tr> '
                );
            }


            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPH ' . $data->pph . '%</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_pph) . '</td>
                    </tr> '
                );
            }

            $v = json_decode($data->biaya_di_luar_pajak);
            if ($v != null) {
                if ($v->body != null || $v->select) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>TOTAL SETELAH PAJAK</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($data->piutang) . '</td>
                        </tr> '
                    );
                }
                if ($v->select != null) {
                    foreach ($v->select as $k => $c) {
                        $pdf->WriteHTML(
                            '
                                <tr>
                                    <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                    <td style="text-align:right;padding:5px;">' . $this->rupiah($c->harga) . '</td>
                                </tr>
                            '
                        );
                    }
                }
                if ($v->body != null) {
                    foreach ($v->body as $k => $c) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                <td style="text-align:right;padding:5px;">' . $this->rupiah($c->harga) . '</td>
                            </tr> '
                        );
                    }
                }
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>TOTAL HARGA</b>
                    </td>
                    <td style="text-align:right;padding:5px;">' . $this->rupiah($data->biaya_akhir) . '</td>
                </tr> '
            );
            $pdf->WriteHTML("</table></td></tr></table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($data->keterangan_tambahan != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="font-size: 10px;">
                            <u>
                                <b>Keterangan Lain / Tambahan</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach (json_decode($data->keterangan_tambahan) as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="font-size: 10px;">- ' . $v . "</td>
                        </tr>"
                    );
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($syarat_ketentuan != null) {
                if ($syarat_ketentuan->umum != null) {
                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>Syarat dan Ketentuan Umum</b>
                                </u>
                            </td>
                        </tr>'
                    );
                    foreach ($syarat_ketentuan->umum as $k => $v) {
                        if (
                            $v !=
                            "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan"
                        ) {
                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="font-size: 10px;">- ' . $v . "</td>
                                </tr>"
                            );
                        }
                    }
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;font-size: 10px;" colspan="2">
                        <b>
                            <u>Sebagai tanda persetujuan, agar dapat menandatangani serta mengirimkan kembali kepada pihak kami melalui email : sales@intilab.com</u>
                        </b>
                    </td>
                </tr>'
            );
            $add = "-";
            $upd = "-";
            $no_telp = "";
            $app = "-";
            $user = MasterKaryawan::where('id', $data->sales_id)->first();
            $add = $user->nama_lengkap;
            $no_telp = " (" . $user->no_telpon . ")";
            // if ($data->updateby != null) {
            $upd = $data->nama_update;
            // }
            // if ($data->approve_by != null) {
            //     $app = $data->approveby->nama_lengkap;
            // }
            if ($data->approve == 0) {
                $st = "NOT APPROVED";
            } else {
                $st = "APPROVED";
            }

            $sp = DB::table('sampling_plan')
                ->where('no_quotation', $data->no_document)
                ->where('is_active', true)
                ->where('status', 1)
                ->first();
            if (!is_null($sp)) {
                $jadwal = DB::table('jadwal')
                    ->where('is_is_active', true)
                    ->where('id_sampling', $sp->id)
                    ->select('tanggal')
                    ->first();
                $pdf->WriteHTML(
                    ' <tr>
                        <td>
                            <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                            <br>
                            <span style="font-size:11px;">Status : ' . $st . '</span>
                            <br>
                            <span style="font-size:11px;">Tanggal Sampling : ' . $this->tanggal_indonesia($jadwal->tanggal) . '</span>
                            <br>
                            <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                            <br>
                        </td>
                        <td style="font-size: 10px;text-align:center;">
                            <span>Menyetujui,</span>
                            <br>
                            <br>
                            <br>
                            <span>Nama (..............................................)</span>
                            <br>
                            <span>Jabatan (..............................................)</span>
                        </td> '
                );
            } else {
                $pdf->WriteHTML(
                    ' <tr>
                        <td>
                            <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                            <br>
                            <span style="font-size:11px;">Status : ' . $st . '</span>
                            <br>
                            <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                            <br>
                        </td>
                        <td style="font-size: 10px;text-align:center;">
                            <span>Menyetujui,</span>
                            <br>
                            <br>
                            <br>
                            <span>Nama (..............................................)</span>
                            <br>
                            <span>Jabatan (..............................................)</span>
                        </td> '
                );
            }
            $pdf->WriteHTML("</tr></table>");

            // Write some HTML code:
            // Output a PDF file directly to the browser

            $filePath = public_path('quotation/' . $fileName);

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (Exception $e) {
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

    public function newpdfKontrak()
    {
        $id = $this->id;
        $data = $this->data;
        $pdf = $this->pdf;
        $tgl_order = $this->tgl_order;
        $db = $this->db;
        $fileName = $this->fileName;
        $detail = $this->detail;

        try {
            $order = DB::table('order_header')
                ->where('no_document', $data->no_document)
                ->where('is_active', true)
                ->first();

            $ord = '';
            if (!is_null($order)) {
                $ord = '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';
            }

            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">NO</th>
                            <th width="62%">KETERANGAN PENGUJIAN</th>
                            <th width="12%">Qty</th>
                            <th width="12%">HARGA SATUAN</th>
                            <th width="12 %">TOTAL HARGA</th>
                        </tr>
                    </thead>
                    <tbody>'
            );

            //code...
            switch ($data->status_sampling) {
                case "S24":
                    $sampling = "SAMPLING 24 JAM";
                    break;
                case "SD":
                    $sampling = "SAMPLE DIANTAR";
                    break;
                default:
                    $sampling = "SAMPLING";
                    break;
            }

            $konsultant = "";
            $jab_pic_or = "";
            $jab_pic_samp = "";

            if ($data->konsultan != '') {
                $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
                $perusahaan = ' (' . strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan))) . ') ';
            } else {
                $perusahaan = strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan)));
            }

            $period = [];

            foreach ($detail as $key => $val) {
                if ($key == 0 || $key == count($detail) - 1) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        $period[] = $this->tanggal_indonesia($v_->periode_kontrak, "period");
                    }
                }
            }


            if (explode(" ", $period[0])[1] === explode(" ", end($period))[1]) {
                $period = explode(" ", $period[0])[0] . " - " . end($period);
            } else {
                $period = $period[0] . " - " . end($period);
            }

            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);

                $penamaan_titik = "";
                if ($a->penamaan_titik != null || $a->penamaan_titik != "") {
                    $penamaan_titik = "(" . htmlspecialchars_decode($a->penamaan_titik) . ")";
                }

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                        <td style="font-size: 13px; padding: 5px;">
                            <b style="font-size: 13px;">' . $kategori2[1] . " " . $penamaan_titik . "</b>
                            <hr>"
                );

                foreach ($a->regulasi as $k => $v) {
                    $reg__ = '';

                    if ($v != '') {
                        $regulasi = array_slice(explode("-", $v), 1);
                        $reg__ = implode("-", $regulasi);
                    }

                    if ($k == 0) {
                        $pdf->WriteHTML('<u style="font-size: 13px;">' . $reg__ . "</u>");
                    } else {
                        $pdf->WriteHTML('<br><u style="font-size: 13px;">' . $reg__ . "</u>");
                    }
                }

                foreach ($a->parameter as $keys => $values) {
                    $d = Parameter::where("id", explode(";", $values)[0])
                        ->where("is_active", true)
                        ->first();
                    if ($keys == 0) {
                        $pdf->WriteHTML(
                            ' <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    } else {
                        $pdf->WriteHTML(
                            ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    }
                }
                $volume = "";
                if ($a->kategori_1 == "1-Air") {
                    $volume =
                        " - Volume : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }
                $pdf->WriteHTML(
                    " <br>
                    <hr>" . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . $a->jumlah_titik * count($a->periode) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . $this->rupiah($a->harga_satuan * ($a->jumlah_titik * count($a->periode))) . '</td>
                    </tr>'
                );
            }

            $wilayah = explode("-", $data->wilayah, 2);

            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi_total / $data->transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi_total) . '</td>
                    </tr>'
                );
            }

            $perdiem_24 = "";
            $total_perdiem = 0;

            if (
                $data->jumlah_orang_24jam > 0 ||
                $data->jumlah_orang_24jam != ""
            ) {
                $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                $total_perdiem += $data->{'harga_24jam_personil_total'};
            }
            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    </tr>'
                );
            }

            if ($data->total_biaya_lain > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Biaya Lain - Lain</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->total_biaya_lain) . '</td>
                    </tr>'
                );
            }

            if ($data->total_biaya_preparasi > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Biaya preparasi</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($data->total_biaya_preparasi) . '</td>
                    </tr>'
                );
            }

            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table width="100%" style="line-height: 2;">
                    <tr>
                        <td style="font-size: 10px;vertical-align: top;" width="64%">
                            <u>
                                <b>Syarat dan Ketentuan Pembayaran</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);

            if ($syarat_ketentuan->pembayaran != null) {
                if ($data->cash_discount_persen != null) {
                    $pdf->WriteHTML(
                        ' <br>
                        <span style="font-size: 10px !important;">- Cash Discount berlaku apabila pelunasan keseluruhan sebelum sampling.</span>'
                    );
                }
                foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                    $pdf->WriteHTML(
                        '<br><span style="font-size: 10px !important;">- ' . $v . "</span>"
                    );
                }
            }
            $pdf->WriteHTML(
                '</td>
                <td width="36%">
                    <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                        <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>SUB TOTAL</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($data->grand_total) . '</td>
                        </tr> '
            );
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Air ' . $data->discount_air . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_non_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Non Air ' . $data->discount_non_air . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_non_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Udara ' . $data->discount_udara . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_udara) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Emisi ' . $data->discount_emisi . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_emisi) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_transport > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Transport ' . $data->discount_transport . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_transport) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_perdiem > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Perdiem ' . $data->discount_perdiem . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_perdiem_24jam > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Perdiem 24 Jam ' . $data->discount_perdiem_24jam . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_perdiem_24jam) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Gabungan ' . $data->discount_gabungan . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Consultant ' . $data->discount_consultant . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. Group ' . $data->discount_group . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }

            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. ' . $data->cash_discount_persen . '% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }
            if ($data->cash_discount != 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Contract Disc. </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_cash_discount) . '</td>
                    </tr> '
                );
            }
            if ($data->total_dpp != $data->grand_total) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL SETELAH DISKON</b>
                            </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">PPN 11% </test>
                    <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_ppn) . '</td>
                </tr> '
            );

            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPH ' . $data->pph . '%</td>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($data->total_pph) . '</td>
                    </tr> '
                );
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>TOTAL HARGA</b>
                        </test>
                    <td style="text-align:right;padding:5px;">' . $this->rupiah($data->biaya_akhir) . '</td>
                </tr> '
            );
            $pdf->WriteHTML("</table></td></tr></table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($data->keterangan_tambahan != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="font-size: 10px;">
                            <u>
                                <b>Keterangan Lain / Tambahan</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach (json_decode($data->keterangan_tambahan) as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="font-size: 10px;">- ' . $v . "</td>
                        </tr>"
                    );
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($syarat_ketentuan->umum != null) {
                $pdf->WriteHTML(
                    ' <tr style="font-size: 10px !important;">
                        <td style="font-size: 10px; !important">
                            <u>
                                <b>Syarat dan Ketentuan Umum</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach ($syarat_ketentuan->umum as $k => $v) {
                    // $pdf->WriteHTML('<tr><td style="font-size: 10px;">- '.$v.'</td></tr>');
                    if (
                        $v !=
                        "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan"
                    ) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="font-size: 10px;">- ' . $v . "</td>
                            </tr>"
                        );
                    }
                }
            }

            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;font-size: 10px;" colspan="2">
                        <b>
                            <u>Sebagai tanda persetujuan, agar dapat menandatangani serta mengirimkan kembali kepada pihak kami melalui email : sales@intilab.com</u>
                        </b>
                    </td>
                </tr>'
            );
            $add = "-";
            $updd = "-";
            $app = "-";
            $no_telp = "";

            $add = ($data->nama_add != null) ? $data->nama_add : "-";
            $no_telp = ($data->no_telpon != null) ? " (" . $data->no_telpon . ")" : "";
            $updd = ($data->nama_update != null) ? $data->nama_update : "-";
            $st = ($data->approve == 0) ? "NOT APPROVED" : "APPROVED";

            // <span style="font-size:11px;">Approve By : ' . $app . '</span>
            // <span style="font-size:11px;">' . $this->tanggal_indonesia($data->tanggal_penawaran) . ' - ' . date('G:i') . '</span><br>
            $pdf->WriteHTML(
                ' <tr>
                    <td>
                        <span style="font-size:11px;">Administrasi : ' . $updd . '</span>
                        <br>
                        <span style="font-size:11px;">Status : ' . $st . '</span>
                        <br>
                        <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                        <br>
                    </td>
                    <td style="font-size: 10px;text-align:center;">
                        <span>Menyetujui,</span>
                        <br>
                        <br>
                        <br>
                        <span>Nama (..............................................)</span>
                        <br>
                        <span>Jabatan (..............................................)</span>
                    </td> '
            );
            $pdf->WriteHTML("</tr></table>");

            $per = [];
            
            foreach ($detail as $k => $v) {
                foreach (json_decode($v->data_pendukung_sampling) as $key => $value) {

                    array_push($per, $value->periode_kontrak);
                    switch ($v->status_sampling) {
                        case "S24":
                            $sampling = "SAMPLING 24 JAM";
                            break;
                        case "SD":
                            $sampling = "SAMPLE DIANTAR";
                            break;
                        default:
                            $sampling = "SAMPLING";
                            break;
                    }
                    $pdf->SetHTMLHeader(
                        ' <table class="tabel">
                            <tr class="tr_top">
                                <td class="text-left text-wrap" style="width: 33.33%;">
                                    <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                                </td>
                                <td style="width: 33.33%; text-align: center;">
                                    <h5 style="text-align:center; font-size:14px;">
                                        <b>
                                            <u>QUOTATION</u>
                                        </b>
                                    </h5>
                                    <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
                                    <p style="font-size: 10px;text-align:center;">' . $this->tanggal_indonesia($value->periode_kontrak, "period") . '</p>
                                </td>
                                <td style="text-align: right;">
                                    <p style="font-size: 9px; text-align:right;">
                                        <b>PT INTI SURYA LABORATORIUM</b>
                                        <br>
                                        <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_cabang . "</span>
                                        <br>
                                        <span>T : " . $data->tlp_cabang . ' - sales@intilab.com</span>
                                        <br>www.intilab.com
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <table class="head2" width="100%">
                            <tr>
                                <td colspan="2">
                                    <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . $this->tanggal_indonesia($data->tanggal_penawaran) . '</p>
                                </td>
                                <td style="vertical-align: top; text-align:right;">
                                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">CONTRACT</span>
                                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-top:5px;" id="status_sampling">' . $sampling . '</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" width="80%">
                                    <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . preg_replace('/&AMP;+/', '&', $perusahaan) . '</h6>
                                </td>
                                <td style="vertical-align: top; text-align:right;">' . $ord . '</td>
                            </tr>
                            <tr>
                                <td style="width:35%;vertical-align:top;">
                                    <p style="font-size: 10px;">
                                        <u>Alamat Kantor :</u>
                                        <br>
                                        <span id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span>
                                        <br>
                                        <span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span>
                                        <br>
                                        <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . " - " . $data->no_pic_order . '</span>
                                        <br>
                                        <span id="email_pic_order">' . $data->email_pic_order . '</span>
                                    </p>
                                </td>
                                <td style="width: 30%; text-align: center;"></td>
                                <td style="text-align: left;vertical-align:top;">
                                    <p style="font-size: 10px;">
                                        <u>Alamat Sampling :</u>
                                        <br>
                                        <span id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span>
                                        <br>
                                        <span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span>
                                        <br>
                                        <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . '</span>
                                        <br>
                                        <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                                    </p>
                                </td>
                            </tr>
                        </table> '
                    );

                    $pdf->AddPage();

                    $pdf->WriteHTML(
                        ' <table class="table table-bordered" style="font-size:8px;border:1px solid;border-color:#000">
                            <thead class="text-center">
                                <tr style="page-break-inside:avoid">
                                    <th width="2%">NO</th>
                                    <th width="60%" class="text-center">KETERANGAN PENGUJIAN</th>
                                    <th>Qty</th>
                                    <th>HARGA SATUAN</th>
                                    <th>TOTAL HARGA</th>
                                </tr>
                            </thead>
                            <tbody>'
                    );
                    $i = 1;
                    foreach ($value->data_sampling as $b => $a) {
                        $kategori = explode("-", $a->kategori_1);
                        $kategori2 = explode("-", $a->kategori_2);
                        $penamaan_titik = $a->penamaan_titik ? "(" . htmlspecialchars_decode($a->penamaan_titik) . ")" : "";
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 13px; padding: 5px;">
                                    <b style="font-size: 13px;">' . $kategori2[1] . " " . $penamaan_titik . "</b>
                                    <hr>"
                        );
                        foreach ($a->regulasi as $k => $z) {
                            $reg__ = '';
                            if ($z != '') {
                                $regulasi = array_slice(explode("-", $z), 1);
                                $reg__ = implode("-", $regulasi);
                            }
                            if ($k == 0) {
                                $pdf->WriteHTML(
                                    '<u style="font-size: 13px;">' . $reg__ . "</u>"
                                );
                            } else {
                                $pdf->WriteHTML(
                                    '<br>
                                    <u style="font-size: 13px;">' . $reg__ . "</u>"
                                );
                            }
                        }
                        foreach ($a->parameter as $keys => $valuess) {
                            $dParam = explode(";", $valuess);
                            $d = Parameter::where("id", $dParam[0])
                                ->where("is_active", true)
                                ->first();
                            if ($keys == 0) {
                                $pdf->WriteHTML(
                                    ' <br>
                                    <hr>
                                    <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                                );
                            } else {
                                $pdf->WriteHTML(
                                    ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                                );
                            }
                        }
                        $volume = (explode("-", $a->kategori_1)[1] == "Air")
                            ? " - Volume : " . number_format($a->volume / 1000, 1) . " L"
                            : "";
                        $pdf->WriteHTML(
                            " <br>
                            <hr>" . ' <b>
                                <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . '</span>
                            </b>
                            </td>
                            <td style="vertical-align: middle;text-align:center;font-size: 13px; padding: 5px;">' . $a->jumlah_titik . '</td>
                            <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . $this->rupiah($a->harga_satuan) . '</td>
                            <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . $this->rupiah($a->harga_total) . '</td>
                            </tr>'
                        );
                    }
                }

                if ($v->transportasi > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $v->transportasi . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_transportasi) . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_transportasi * $v->transportasi) . '</td>
                        </tr>'
                    );
                }
                $perdiem_24 = "";
                $total_perdiem = 0;
                if (
                    $v->jumlah_orang_24jam > 0 &&
                    $v->jumlah_orang_24jam != ""
                ) {
                    $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                    $total_perdiem =
                        $total_perdiem + $v->{'harga_24jam_personil_total'};
                }
                if ($v->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
                            <td style="font-size: 13px; text-align:center;"></td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_perdiem_personil_total + $total_perdiem) . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . $this->rupiah($v->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        </tr>'
                    );
                }

                $biaya_lain = json_decode($v->biaya_lain);
                if ($biaya_lain != null) {
                    $i = $i + 1;
                    foreach ($biaya_lain as $s => $h) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 13px;padding: 5px;">Biaya : ' . $h->deskripsi . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($h->harga) . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . $this->rupiah($h->harga) . '</td>
                            </tr>'
                        );
                    }
                }
                $pdf->WriteHTML("</tbody></table>");

                $pdf->WriteHTML(
                    ' <table width="100%" style="line-height: 2;">
                        <tr>
                            <td style="font-size: 10px;vertical-align: top;" width="62%">'
                );
                $pdf->WriteHTML(
                    '</td>
                    <td width="38%">
                        <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                            <tr>
                                <td style="text-align:center;padding:5px;">
                                    <b>SUB TOTAL</b>
                                </td>
                                <td style="text-align:right;padding:5px;" width="39%">' . $this->rupiah($v->grand_total) . '</td>
                            </tr> '
                );
                if (
                    $v->discount_air != null &&
                    $v->total_discount_air != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Air ' . $v->discount_air . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_air) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_non_air != null &&
                    $v->total_discount_non_air != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Non Air ' . $v->discount_non_air . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_non_air) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_udara != null &&
                    $v->total_discount_udara != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Udara ' . $v->discount_udara . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_udara) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_emisi != null &&
                    $v->total_discount_emisi != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Emisi ' . $v->discount_emisi . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_emisi) . '</td>
                        </tr> '
                    );
                }

                if (
                    $v->discount_transport != null &&
                    $v->total_discount_transport > "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Transport ' . $v->discount_transport . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_transport) . '</td>
                        </tr> '
                    );
                }
                if ($v->discount_perdiem != null && $v->discount_perdiem > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Perdiem ' . $v->discount_perdiem . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_perdiem) . '</td>
                        </tr> '
                    );
                }
                if ($v->discount_perdiem_24jam != null && $v->discount_perdiem_24jam > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Perdiem 24 Jam ' . $v->discount_perdiem_24jam . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_perdiem_24jam) . '</td>
                        </tr> '
                    );
                }

                if ($v->discount_gabungan != null && $v->discount_gabungan > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Gabungan ' . $v->discount_gabungan . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_gabungan) . '</td>
                        </tr> '
                    );
                }
                if ($v->discount_consultant != null && $v->discount_consultant > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Consultant ' . $v->discount_consultant . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_consultant) . '</td>
                        </tr> '
                    );
                }
                if ($v->discount_group != null && $v->discount_group > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. Group ' . $v->discount_group . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_discount_group) . '</td>
                        </tr> '
                    );
                }

                if ($v->cash_discount_persen != null && $v->cash_discount_persen > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. ' . $v->cash_discount_persen . '% </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_cash_discount_persen) . '</td>
                        </tr> '
                    );
                }
                if ($v->cash_discount != 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">Contract Disc. </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->cash_discount) . '</td>
                        </tr> '
                    );
                }

                if ($v->total_dpp != $v->grand_total) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>TOTAL SETELAH DISKON</b>
                                </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_dpp) . '</td>
                        </tr>'
                    );
                }

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPN 11% </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_ppn) . '</td>
                    </tr> '
                );

                if ($v->total_pph > 0 && $v->total_pph != "") {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">PPH ' . $v->pph . '%</td>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->total_pph) . '</td>
                        </tr> '
                    );
                }

                $y = json_decode($v->biaya_di_luar_pajak);
                if ($y->body != null || $y->select) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>TOTAL SETELAH PAJAK</b>
                                </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($v->piutang) . '</td>
                        </tr> '
                    );
                }
                if ($y->select != null) {
                    foreach ($y->select as $k => $c) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . $c->deskripsi . ' </test>
                                <td style="text-align:right;padding:5px;">' . $this->rupiah($c->harga) . '</td>
                            </tr> '
                        );
                    }
                }

                if ($y->body != null) {
                    foreach ($y->body as $k => $c) {
                        $pdf->WriteHTML(' <tr>
                            <td style="text-align:center;padding:5px;">' . $c->deskripsi . ' </test>
                            <td style="text-align:right;padding:5px;">' . $this->rupiah($c->harga) . '</td>
                        </tr> '
                        );
                    }
                }
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL HARGA</b>
                            </test>
                        <td style="text-align:right;padding:5px;">' . $this->rupiah($v->biaya_akhir) . '</td>
                    </tr> '
                );

                $pdf->WriteHTML("</table></td></tr></table>");
            }

            $pdf->SetHTMLHeader(
                ' <table width="100%">
                    <tr>
                        <td class="text-left text-wrap">
                            <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                        </td>
                        <td style="width: 60%; text-align: center;">
                            <h5 style="text-align:center; font-size:11px;">
                                <b>
                                    <u>Rincian Kontrak Pengujian - Periode : ' . $period . '</u>
                                </b>
                            </h5>
                            <p style="font-size: 10px;text-align:center;">
                                <b>' . $konsultant . preg_replace('/&AMP;+/', '&', $perusahaan) . '</b>
                            </p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;"> No Contract : ' . $data->no_document . ' </p>
                            <p style="font-size: 9px; text-align:right;">
                                <b>PIC SALES <b> : ' . $add . '
                            </p>
                        </td>
                    </tr>
                </table> '
            );
            $pdf->AddPage("L");
            $pdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);

            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;" width="100%">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;" rowspan="2">NO</th>
                            <th width="25%" rowspan="2">KETERANGAN PENGUJIAN</th>'
            );

            $a = 1;
            foreach ($per as $c) {

                $pdf->WriteHTML("<th>" . $a++ . "</th>");
            }
            $pdf->WriteHTML(
                ' <th rowspan="2">TOTAL</th>
                <th rowspan="2">HARGA SATUAN</th>
                <th rowspan="2">TOTAL HARGA</th></tr><tr>'
            );
            foreach ($per as $c) {
                $pdf->WriteHTML("<th>" . date("M-y", strtotime($c)) . "</th>");
            }
            $pdf->WriteHTML("</tr></thead><tbody>");
            $i = 1;
            $t = count(json_decode($data->data_pendukung_sampling, true));
            $katgor = [];
            $x_ = 0;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                array_push($katgor, $a->kategori_2);
                $th_left = implode(' ', [
                    strtoupper(explode("-", $a->kategori_1)[1]),
                    strtoupper(explode("-", htmlspecialchars_decode($a->kategori_2))[1]),
                    $a->jumlah_titik,
                    $x_,
                    $a->penamaan_titik,
                    $a->total_parameter,
                    implode($a->parameter)
                ]);
                $th_left1 = strtoupper(explode("-", $a->kategori_2)[1]);
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i++ . '</td>
                        <td style="font-size: 8px; padding: 5px;">
                            <b>' . $th_left1 . "</b>
                        </td>"
                );
                foreach ($detail as $key => $value) {
                    $aa = 0;
                    foreach (json_decode($value->data_pendukung_sampling) as $keys => $values) {
                        $num_ = $values->data_sampling;
                        $bollean = false;
                        foreach ($num_ as $key_ => $val_) {
                            $td_kat = implode(" ", [
                                strtoupper(explode("-", $val_->kategori_1)[1]),
                                strtoupper(explode("-", htmlspecialchars_decode($val_->kategori_2))[1]),
                                $val_->jumlah_titik,
                                $x_,
                                $val_->penamaan_titik,
                                $val_->total_parameter,
                                implode(" ", $val_->parameter)
                            ]);
                            $td_kat1 = strtoupper(
                                explode("-", $a->kategori_2)[1]
                            );

                            // var_dump($td_kat);
                            if ($th_left == $td_kat) {
                                // var_dump($th_left, ' = ', $td_kat);
                                $pdf->WriteHTML(
                                    '<td style="font-size: 8px; text-align:center;">' . $val_->jumlah_titik . "</td>"
                                );
                                $bollean = true;
                            }
                        }
                        if ($bollean == false) {
                            $pdf->WriteHTML("<td></td>");
                        }
                    }
                }

                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:center;">' . $a->jumlah_titik * count($a->periode) . "</td>"
                );
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($a->harga_satuan) . "</td>"
                );
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($a->harga_satuan * ($a->jumlah_titik * count($a->periode))) . "</td>"
                );
                $pdf->WriteHTML("</tr>");
                $x_++;
            }

            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;">' . $v->transportasi . "</td>"
                    );
                    $enum_ = $v->harga_transportasi;
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;">' . $data->transportasi . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi_total / $data->transportasi) . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_transportasi_total) . '</td></tr>'
                );
            }

            $total_perdiem = 0;
            $perdiem_24 = $data->jumlah_orang_24jam > 0 || $data->jumlah_orang_24jam != ""
                ? "Termasuk Perdiem (24 Jam)"
                : "";
            $total_perdiem += $data->jumlah_orang_24jam > 0 || $data->jumlah_orang_24jam != ""
                ? $data->{'harga_24jam_personil_total'}
                : 0;

            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Perdiem ' . $perdiem_24 . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                    // $enum_ = $v->harga_personil;
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td></tr>'
                );
            }

            if ($data->total_biaya_lain > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Biaya lain - Lain ' . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                    // $enum_ = $v->harga_personil;
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($data->total_biaya_lain) . '</td></tr>'
                );
            }

            if ($data->total_biaya_preparasi > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">Biaya Preparasi ' . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                    // $enum_ = $v->harga_personil;
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($data->total_biaya_preparasi) . '</td></tr>'
                );
            }

            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;margin-top:10px;">
                    <thead class="text-center">
                        <tr>
                            <th style="font-size: 8px;">KETERANGAN HARGA PENGUJIAN</th>'
            );
            foreach ($per as $c) {
                $pdf->WriteHTML(
                    '<th style="font-size: 8px;">' . date("M-y", strtotime($c)) . "</th>"
                );
            }

            $pdf->WriteHTML(
                ' <th style="font-size: 8px;">TOTAL HARGA</th></tr></thead><tbody>'
            );
            // TOTAL ANALISA
            $pdf->WriteHTML(
                '<tr>
                <td style="text-align:center;font-size: 8px;">TOTAL ANALISA</td>'
            );
            $total_harga_analisa = 0;
            foreach ($detail as $key => $value) {
                foreach (json_decode($value->data_pendukung_sampling) as $keys => $values) {
                    $tot_harga = 0;
                    foreach ($values->data_sampling as $b => $a) {
                        $tot_harga = $tot_harga + $a->harga_total;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($tot_harga) . "</td>"
                    );
                    $total_harga_analisa = $total_harga_analisa + $tot_harga;
                }
            }
            $pdf->WriteHTML(
                ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_analisa) . "</td></tr>"
            );

            // TOTAL TRANSPORT
            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">TOTAL TRANSPORT</td>'
                );
                $total_harga_transport = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->harga_transportasi_total) . "</td>"
                    );
                    $total_harga_transport =
                        $total_harga_transport + $value->harga_transportasi_total;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_transport) . "</td></tr>"
                );
            }

            // TOTAL PERDIEM 24 JAM
            $perdiem_24 = "";
            $total_perdiem = 0;
            if ($data->harga_24jam_personil_total != 0) {
                $perdiem_24 = "TERMASUK PERDIEM (24 JAM)";
                // $pdf->WriteHTML('<tr><td style="text-align:center;font-size: 8px;">TOTAL PERDIEM 24 JAM</td>');
                $total_harga_24jam_perdiem = 0;
                foreach ($detail as $key => $value) {
                    // $pdf->WriteHTML('<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->harga_24jam_personil_total) . '</td>');
                    $total_harga_24jam_perdiem =
                        $total_harga_24jam_perdiem +
                        $value->harga_24jam_personil_total;
                }
                $total_perdiem = $total_perdiem + $total_harga_24jam_perdiem;
                // $pdf->WriteHTML('<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_24jam_perdiem) . '</td></tr>');
            }

            if ($data->perdiem_jumlah_orang > 0 || $data->jumlah_orang_24jam > 0) {
                // TOTAL PERDIEM
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">TOTAL PERDIEM ' . $perdiem_24 . "</td>"
                );
                $total_harga_perdiem = 0;
                foreach ($detail as $key => $value) {
                    $harga_perdiem = 0;
                    if ($value->jumlah_orang_24jam > 0) {
                        $harga_perdiem =
                            $harga_perdiem + $value->harga_24jam_personil_total;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->harga_perdiem_personil_total + $harga_perdiem) . "</td>"
                    );
                    $total_harga_perdiem =
                        $total_harga_perdiem + $value->harga_perdiem_personil_total;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_perdiem + $total_perdiem) . "</td></tr>"
                );
            }

            // TOTAL HARGA PENGUJIAN
            $pdf->WriteHTML(
                '<tr>
                <td style="text-align:center;font-size: 8px;"><b>TOTAL HARGA PENGUJIAN</b></td>'
            );
            $total_harga_pengujian = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . $this->rupiah($value->grand_total) . "</b></td>"
                );
                $total_harga_pengujian =
                    $total_harga_pengujian + $value->grand_total;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . $this->rupiah($total_harga_pengujian) . "</b></td></tr>"
            );

            // DISCOUNT AIR
            if ($value->discount_air != null && $value->total_discount_air > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - AIR (' . $detail[0]->discount_air . "%)</td>"
                );
                $total_harga_discount_air = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_air) . "</td>"
                    );
                    $total_harga_discount_air =
                        $total_harga_discount_air + $value->total_discount_air;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_air) . "</td></tr>"
                );
            }
            // DISCOUNT NON AIR
            if ($value->discount_non_air != null && $value->total_discount_non_air > 0) {

                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - NON-AIR (' . $detail[0]->discount_non_air . "%)</td>"
                );
                $total_harga_discount_non_air = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_non_air) . "</td>"
                    );
                    $total_harga_discount_non_air =
                        $total_harga_discount_non_air +
                        $value->total_discount_non_air;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_non_air) . "</td></tr>"
                );
            }

            // DISCOUNT UDARA
            if ($value->discount_udara != null && $value->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - UDARA (' . $detail[0]->discount_udara . "%)</td>"
                );
                $total_harga_discount_udara = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_udara) . "</td>"
                    );
                    $total_harga_discount_udara =
                        $total_harga_discount_udara +
                        $value->total_discount_udara;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_udara) . "</td></tr>"
                );
            }

            // DISCOUNT EMISI
            if ($value->discount_emisi != null && $value->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - EMISI (' . $detail[0]->discount_emisi . "%)</td>"
                );
                $total_harga_discount_emisi = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_emisi) . "</td>"
                    );
                    $total_harga_discount_emisi =
                        $total_harga_discount_emisi +
                        $value->total_discount_emisi;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_emisi) . "</td></tr>"
                );
            }

            //Diskon TRANSPORT
            if ($data->discount_transport != "0") {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - TRANSPORT (' . $detail[0]->discount_transport . "%)</td>"
                );
                $total_harga_discount_transport = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_transport) . "</td>"
                    );
                    $total_harga_discount_transport =
                        $total_harga_discount_transport +
                        $value->total_discount_transport;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_transport) . "</td></tr>"
                );
            }
            //Diskon PERDIEM
            if ($value->discount_perdiem != null && $value->total_discount_perdiem > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM (' . $detail[0]->discount_perdiem . "%)</td>"
                );
                $total_harga_discount_perdiem = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_perdiem) . "</td>"
                    );
                    $total_harga_discount_perdiem =
                        $total_harga_discount_perdiem +
                        $value->total_discount_perdiem;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_perdiem) . "</td></tr>"
                );
            }
            //Diskon Perdiem 24 Jam
            if ($data->discount_perdiem_24jam != null && $data->total_discount_perdiem_24jam > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - PERDIEM 24 JAM (' . $detail[0]->discount_perdiem_24jam . "%)</td>"
                );
                $total_harga_discount__perdiem24 = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_perdiem_24jam) . "</td>"
                    );
                    $total_harga_discount__perdiem24 =
                        $total_harga_discount__perdiem24 +
                        $value->total_discount_perdiem_24jam;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount__perdiem24) . "</td></tr>"
                );
            }

            //Diskon GABUNGAN
            if (
                $value->discount_gabungan != null &&
                $value->total_discount_gabungan > 0
            ) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - GABUNGAN (' . $detail[0]->discount_gabungan . "%)</td>"
                );
                $total_harga_discount_gabungan = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_gabungan) . "</td>"
                    );
                    $total_harga_discount_gabungan =
                        $total_harga_discount_gabungan +
                        $value->total_discount_gabungan;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_gabungan) . "</td></tr>"
                );
            }

            // //Diskon CONSULTANT
            if ($value->discount_consultant != null && $value->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - CONSULTANT (' . $detail[0]->discount_consultant . "%)</td>"
                );
                $total_harga_discount_consultant = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_consultant) . "</td>"
                    );
                    $total_harga_discount_consultant =
                        $total_harga_discount_consultant +
                        $value->total_discount_consultant;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_consultant) . "</td></tr>"
                );
            }

            //Diskon GROUP
            if ($value->discount_group != null && $value->total_discount_group > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - GROUP (' . $detail[0]->discount_group . "%)</td>"
                );
                $total_harga_discount_group = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_discount_group) . "</td>"
                    );
                    $total_harga_discount_group =
                        $total_harga_discount_group +
                        $value->total_discount_group;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_group) . "</td></tr>"
                );
            }

            // DISCOUNT CASH DISCOUNT PERSEN
            if ($value->cash_discount_persen != null && $value->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - CASH DISCOUNT PERSEN (' . $detail[0]->cash_discount_persen . "%)</td>"
                );
                $total_harga_discount_cash_per = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_cash_discount_persen) . "</td>"
                    );
                    $total_harga_discount_cash_per =
                        $total_harga_discount_cash_per +
                        $value->total_cash_discount_persen;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_cash_per) . "</td></tr>"
                );
            }

            // DISCOUNT CASH DISCOUNT
            if ($data->cash_discount != "0.00") {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">CONTRACT DISCOUNT - CASH DISCOUNT</td>'
                );
                $total_harga_discount_cash = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->cash_discount) . "</td>"
                    );
                    $total_harga_discount_cash =
                        $total_harga_discount_cash + $value->cash_discount;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_discount_cash) . "</td></tr>"
                );
            }

            // TOTAL HARGA SETELAH DISCOUNT
            if ($data->total_dpp != $data->grand_total) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;"><b>TOTAL HARGA SETELAH DISCOUNT</b></td>'
                );
                $total_harga_setelah_discount = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . $this->rupiah($value->total_dpp) . "</b></td>"
                    );
                    $total_harga_setelah_discount =
                        $total_harga_setelah_discount + $value->total_dpp;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . $this->rupiah($total_harga_setelah_discount) . "</b></td></tr>"
                );
            }

            // PPN
            $pdf->WriteHTML(
                '<tr><td style="text-align:center;font-size: 8px;">PPN 11%</td>'
            );
            $total_harga_ppn = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_ppn) . "</td>"
                );
                $total_harga_ppn = $total_harga_ppn + $value->total_ppn;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_ppn) . "</td></tr>"
            );

            // PPH
            if ($value->total_pph != "" && $value->total_pph != "0.00") {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">PPH (' . $detail[0]->pph . "%)</td>"
                );
                $total_harga_pph = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($value->total_pph) . "</td>"
                    );
                    $total_harga_pph = $total_harga_pph + $value->total_pph;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . $this->rupiah($total_harga_pph) . "</td></tr>"
                );
            }

            // TOTAL HARGA SETELAH PAJAK
            $pdf->WriteHTML(
                '<tr><td style="text-align:center;font-size: 8px;"><b>TOTAL HARGA SETELAH PAJAK</b></td>'
            );
            $total_harga_setelah_pajak = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . $this->rupiah($value->piutang) . "</b></td>"
                );
                $total_harga_setelah_pajak =
                    $total_harga_setelah_pajak + $value->piutang;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . $this->rupiah($total_harga_setelah_pajak) . "</b></td></tr>"
            );

            $pdf->WriteHTML(
                '</tbody></table><table width="100%" style="margin-top:10px;"><tr>
                        <td width="40%"></td>'
            );
            $pdf->WriteHTML(
                ' <td style="font-size: 10px;text-align:center;">
                    <span>Tangerang, ' . $this->tanggal_indonesia($data->tanggal_penawaran) . '</span>
                    <br>
                    <span>
                        <b>PT INTI SURYA LABORATORIUM</b>
                    </span>
                    <br>
                    <br>
                    <br>
                </td>'
            );
            $pdf->WriteHTML(
                ' <td style="font-size: 10px;text-align:center;">
                    <span>Menyetujui,</span>
                    <br>
                    <span>
                        <b>' . $data->nama_perusahaan . '</b>
                    </span>
                    <br>
                    <br>
                    <br>
                </td>
                </tr>'
            );

            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                ' <tr>
                    <td></td>
                    <td style="font-size: 10px;text-align:center;">Nama&nbsp;&nbsp;&nbsp;(..............................................)</td>
                    <td style="font-size: 10px;text-align:center;">Nama&nbsp;&nbsp;&nbsp;(..............................................)</td>
                </tr>'
            );
            $pdf->WriteHTML(
                ' <tr>
                    <td></td>
                    <td style="font-size: 10px;text-align:center;">Jabatan (..............................................)</td>
                    <td style="font-size: 10px;text-align:center;">Jabatan (..............................................)</td>
                </tr>'
            );
            
            $pdf->WriteHTML("</table>");
            // Output a PDF file directly to the browser
            $filePath = public_path('quotation/' . $fileName);

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (Exception $e) {
            return response()->json(
                [
                    "message" => $e->getMessage(),
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                ],
                401
            );
        }
    }
}