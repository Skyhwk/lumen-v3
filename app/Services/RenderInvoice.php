<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\QuotationNonKontrak;
use App\Models\Invoice;
use App\Models\QuotationKontrakD;
use App\Models\QrDocument;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use Illuminate\Support\Facades\DB;
use Mpdf;

class RenderInvoice
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public function renderInvoice($noInvoice)
    {
        DB::beginTransaction();
        try {
            $invoice = Invoice::where('is_active', true)
                ->where('no_invoice', $noInvoice)
                ->first();
            $filename = $this->renderCustom($noInvoice);
            // if ($invoice->is_custom == true) {
                // } else {
                    // $filename = $this->renderHeader($noInvoice);
            // }
            if (!$filename) {
                throw new \Exception("Gagal membuat file header untuk invoice: $noInvoice");
            }

            // Update invoice dengan filename
            $update = Invoice::where('no_invoice', $noInvoice)->update(['filename' => $filename]);

            // if (!$update) {
            //     throw new \Exception("Invoice dengan nomor $noInvoice tidak ditemukan.");
            // }

            DB::commit();
            return true; // Proses berhasil
        } catch (\Exception $e) {
            DB::rollBack();

            // Lempar ulang exception agar bisa ditangani di tempat lain
            throw $e;
        }
    }

    static function renderCustom($noInvoice)
    {
        try {

            $dataHead = Invoice::where('is_active', true)
                ->where('no_invoice', $noInvoice)
                ->first();

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3, // 30mm not pixel
                'margin_bottom' => 3, // 30mm not pixel
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            $pdf->showWatermarkText = true;
            $area = '';

            // $customInvoice = json_decode($dataHead->custom_invoice);
            $customInvoice = InvoiceService::getKeterangan($noInvoice);
            // dd($customInvoice);
            if ($customInvoice instanceof \Illuminate\Http\JsonResponse) {
                $customInvoice = $customInvoice->getData(); // object stdClass
            }
            if (data_get($customInvoice->data[0], 'id_cabang') == 1) {
                $area = 'Tangerang';
            } elseif (data_get($customInvoice->data[0], 'id_cabang') == 4) {
                $area = 'Karawang';
            } elseif (data_get($customInvoice->data[0], 'id_cabang') == 5) {
                $area = 'Pemalang';
            }
            
            $pr = '';
            if ($dataHead->periode != null && $dataHead->periode != 'all') {
                $pr = self::tanggal_indonesia($dataHead->periode, 'period');
            } else {
                if($dataHead->periode == 'all'){
                    $pr = "Semua Periode";
                }{
                    $pr = "";
                }
            }
            
            $konsultant = $dataHead->nama_perusahaan;
            $jab_pic = '';
            $fileName = 'INVOICE' . '_' . preg_replace('/\\//', '_', $dataHead->no_invoice) . '.pdf';
            $jab_pic = data_get($customInvoice->data[0], 'jabatan_pic');

            $qr_img = '';
            if ($dataHead->is_generate == 1) {
                $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
                $qr = DB::table('qr_documents')->where('file', $qr_name)->where('type_document', 'invoice')->first();
                if ($qr) $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
            }
            
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

            $trAlamat = '<tr>
                <td style="width:35%;"><p style="font-size: 10px;"><u>Alamat Kantor :</u><br><span
                id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $dataHead->alamat_penagihan . '</span><br><span id="no_tlp_perusahaan">' . data_get($customInvoice->data[0], 'no_tlp_perusahaan') . '</span><br><span
                id="nama_pic_order">' . $dataHead->nama_pic . $jab_pic . ' - ' . $dataHead->no_pic . '</span><br><span id="email_pic_order">' . $dataHead->email_pic . '</span></p></td>
                <td style="width: 30%; text-align: center;"></td>
                </tr>';

            $pdf->SetHTMLHeader('
                <table class="tabel">
                <tr class="tr_top">
                <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                </td>
                <td style="width: 33.33%; text-align: center;">
                <h5 style="text-align:center; font-size:10px;"><b><u>INVOICE</u></b></h5>
                <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $dataHead->no_invoice . '
                </p>
                </td>
                <td style="text-align: right;">
                <p style="font-size: 8px; text-align:right;"><b>PT INTI SURYA
                LABORATORIUM</b><br><span
                style="white-space: pre-wrap; word-wrap: break-word;">Ruko Icon Business Park blok O no 5 - 6, BSD City
                Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang</span><br><span>T : 021-50898988/89 - sales@intilab.com</span><br>www.intilab.com
                </p>
                </td>
                </tr>
                </table>
                <table class="head2" width="100%">
                <tr>
                <td colspan="3"><h6 style="font-size:10px; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $dataHead->nama_perusahaan . '</h6></td>
                </tr>
                ' . $trAlamat . '
                </table>
                ');

            $pdf->writeHTML('
                <table style="width:100%;">
                <tr>
                <th></th>
                ');

            if ($dataHead->faktur_pajak != null && $dataHead->faktur_pajak != "") {
                $pdf->writeHTML('
                            <th style="text-align:left;padding:5px;font-size:10px;"><b>Faktur Pajak: ' . $dataHead->faktur_pajak . '</b></th> 
                            ');
            }

            if ($dataHead->no_po != null && $dataHead->no_po != "") {
                $pdf->writeHTML('
                            <th style="text-align:right;padding:5px;font-size:10px;"><b>No. PO: ' . $dataHead->no_po . '</b></th>  
                            ');
            }

            $pdf->writeHTML('
                        </tr>
                        </table>
                        ');

            $pdf->writeHTML('
                        <table style="border-collapse: collapse;">
                        <thead>
                        <tr>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">NO</th>
                        <th style="font-size:10px; padding:14px; padding:5px;border:1px solid #000">NO QT</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;" class="text-center" colspan="3">KETERANGAN PENGUJIAN</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">TITIK</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">HARGA SATUAN</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">TOTAL HARGA</th>
                        </tr>
                        </thead>
                        <tbody>
                        ');

            $no = 1;
            
            foreach ($customInvoice->data as $k => $invoice) {
                // Debugging the invoice details

                $pdf->writeHTML(
                    '<tr style="border: 1px solid; font-size: 9px;">
                                    <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . (count(data_get($invoice, 'invoiceDetails')) + 1) . '">' . ($k + 1) . '</td>
                                    <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . (count(data_get($invoice, 'invoiceDetails')) + 1) . '">
                                    <span><b>' . data_get($invoice, 'no_order') . '</b></span><br>
                                    <span><b>' . data_get($invoice, 'no_document') . '</b></span><br>
                                    <span><b>' . $pr . '</b></span>
                                    </td>
                                    </tr>'
                );

                foreach (data_get($invoice, 'invoiceDetails') as $k => $itemInvoice) {
                    // Handle empty values
                    $titk = !empty(data_get($itemInvoice, 'titk')) ? data_get($itemInvoice, 'titk') : ' ';  // Default to 'N/A' if empty
                    $keterangan = !empty(data_get($itemInvoice, 'keterangan')) ? data_get($itemInvoice, 'keterangan') : 'No Description'; // Default text if empty
                    $hargaSatuan = !empty(data_get($itemInvoice, 'harga_satuan')) ? self::rupiah(data_get($itemInvoice, 'harga_satuan')) : ''; // Default to '0' if empty
                    $totalHarga = !empty(data_get($itemInvoice, 'total_harga')) ? self::rupiah(data_get($itemInvoice, 'total_harga')) : '0'; // Default to '0' if empty

                    $pdf->writeHTML('
                                <tr>
                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $keterangan . '</span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $titk . '</td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $hargaSatuan . '</td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $totalHarga . '</td>
                                </tr>
                                ');
                }
            }

            $spk = '';

            if ($dataHead->no_spk != null && $dataHead->no_faktur != null) {
                $spk = '<span style="font-size:10px">- No. SPK: ' . $dataHead->no_spk . ' - No. Faktur: ' . $dataHead->no_faktur . '</span>';
            } else if ($dataHead->no_faktur != null && $dataHead->no_spk == null) {
                $spk = '<span style="font-size:10px">- No. Faktur: ' . $dataHead->no_faktur . '</span>';
            } else if ($dataHead->no_spk != null && $dataHead->no_faktur == null) {
                $spk = '<span style="font-size:10px">- No. SPK: ' . $dataHead->no_spk . '</span>';
            } else {
                $spk = '';
            }

            $space = '<p style="font-size:4px;">&nbsp;</p>';
            $spaceSection = '<p style="font-size:8px;">&nbsp;</p>';
            $pdf->writeHTML('
                <tr>
                    <td style="height: 7px;" colspan="5"></td>
                </tr>
                <tr class="line_">
                <td rowspan="11" colspan="5">
                    <div>   
                    </div>
                        <p style="line-height:normal; font-size:9px;">Terbilang: </p>
                        ' . $space . '
                        <p>
                            <b style="font-size:9px; text-align:center; white-space:normal; line-height:normal;">' . self::terbilang($customInvoice->harga->nilai_tagihan) . ' Rupiah</b>
                        </p>
                        ' . $spaceSection . '
                        <p style="font-size:10px; border-bottom:1px solid #000; width: 120px; padding-bottom:5px;">Keterangan Pembayaran:</p>
                        ' . $space . '
                        <p style="font-size:10px;">- Pembayaran dilakukan secara <b style="font-style: italic;">"Full Amount"</b> (tanpa pemotongan biaya apapun)</p>
                        <p style="font-size:10px;">- <b>Cash / Transfer : ' . $dataHead->rekening . ' atas nama PT Inti Surya Laboratorium Bank Central Asia (BCA) - Kota Tangerang - Cabang BSD Serpong</b></p>
                        <p style="font-size:10px">- Pembayaran baru dianggap sah apabila cek / giro telah dapat dicairkan</p>
                        <p style="font-size:10px">- Bukti Pembayaran agar dapat di e-mail ke : billing@intilab.com</p>
                        <p style="font-size:10px">- Invoice asli ini berlaku juga sebagai kwitansi asli yang sah</p>
                        ' . $spk . '
                ');

            if ($dataHead->keterangan_tambahan != null && count($dataHead->keterangan_tambahan) > 0) {

                $pdf->writeHTML(
                    $spaceSection . '
                    <p style="font-size:10px; border-bottom:1px solid #000; width: 120px;">Keterangan Tambahan:</p>
                    ' . $space . '
                '
                );

                foreach ($dataHead->keterangan_tambahan as $el) {
                    $pdf->writeHTML('
                        <p style="font-size:10px;">- ' . $el . '</p>
                    ');
                }
            }

            $pdf->writeHTML('
                    </div>
                </td>
                <td colspan="3" style="border:none; padding:0; margin:0; vertical-align:top;">
                    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr >
                            <td style="width:60%; border:1px solid #000; font-size:10px; padding:3px;" colspan="7">
                                <b>SUB TOTAL</b>
                            </td>

                            <td style="width:33%; border:1px solid #000; font-size:9px; text-align:center;" colspan="2">
                                ' . self::rupiah($customInvoice->harga->sub_total) . '
                            </td>
                        </tr>
            ');

            if ($customInvoice->harga->diskon != 0 && $customInvoice->harga->diskon != null) {

                $pdf->writeHTML('
                                <tr>
                                    <td style="width:60%; border: 1px solid; padding: 3px;" colspan="7"><span style="font-size: 10px;"><b>DISKON</b></span><br><span style="font-size: 7px;">(*Total Diskon)</span></td>
                                    <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->diskon) . '</td>
                                </tr>
                                ');

                $pdf->writeHTML('
                                <tr>
                                    <td style="width:60%; border: 1px solid; padding: 3px;" colspan="7"><span style="font-size: 10px;"><b>TOTAL SETELAH DISKON</b></span></td>
                                    <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->sub_total - $customInvoice->harga->diskon) . '</td>
                                </tr>
                                ');
            }
            if ($customInvoice->harga->ppn != 0 && $customInvoice->harga->ppn != null) {
                $pdf->writeHTML('
                <tr>
                <td style="width:60%; border: 1px solid; padding: 3px;" colspan="7"><span style="font-size: 10px;"><b>PPN</b></span><br><span style="font-size: 7px;">(*Total PPN)</span></td>
                <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->ppn) . '</td></tr>
                                ');
            }
            
            if (!empty($customInvoice->harga->pph) && $customInvoice->harga->pph != 0 && $customInvoice->harga->pph != null) {
                $pdf->writeHTML('
                <tr>
                <td style="width:60%; border: 1px solid; padding:3px;" colspan="7"><span style="font-size: 10px;"><b>PPH</b></span><br><span style="font-size: 7px;">(*Total PPH)</span></td>
                <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->pph) . '</td></tr>
                ');
            }
            // dd($customInvoice->harga);
            
            $pdf->writeHTML('
                        <tr>
                        <td style="width:60%; border: 1px solid; font-size: 10px; padding:3px;" colspan="7"><span><b>TOTAL</b></span></td>
                        <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_tagihan) . '</td></tr>
                        ');
            $pdf->writeHTML('
                        <tr><td colspan="5" style="height: 10px;"></td></tr>
                        <tr>
                        <td style="width:60%; border: 1px solid; font-size: 10px; padding: 3px;" colspan="7"><b style="text-transform: uppercase;">' . $dataHead->keterangan . '</b></td>
                        <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->nilai_tagihan) . '</td></tr>
                        ');
                        
            // dd($customInvoice->harga);
            if (abs($customInvoice->harga->total_tagihan - $customInvoice->harga->nilai_tagihan) > 10) {
                $pdf->writeHTML('
                            <tr>
                            <td style="width:60%; border: 1px solid; font-size: 10px; padding:3px;" colspan="7"><b style="text-transform: uppercase;">SISA PEMBAYARAN</b></td>
                            <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_tagihan - $customInvoice->harga->nilai_tagihan) . '</td></tr>
                            ');
            }
            $pdf->writeHTML('
                    </table>
                </td> 
            ');
            $pdf->writeHTML('
                    </tbody>
                </table>
                        ');
            $pdf->writeHTML('
                        <table style="margin-top: 30px;" width="100%">
                            <tr>
                            <td style="padding-right:50px;">
                            </td>
                            <td width="25%" style="text-align:center;">
                            <div style="float: right; text-align: center;">
                            <span style="font-size: 10px;">' . $area . ', ' . self::tanggal_indonesia($dataHead->tgl_invoice) . '</span><br><br><br><br><br><br><br>
                            <span style="border-bottom: solid 1px #000; font-size:10px;"><b>' . $dataHead->nama_pj . '</b></span><br>
                            <span style="font-size:10px;">' . $dataHead->jabatan_pj . '</span>
                            </div>
                            </td>
                            </tr>
                            </table>
                            ');

            $filePath = public_path('invoice/' . $fileName);
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            // dd($filePath);
            return $fileName;
        } catch (\Exception $e) {
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

    protected static function terbilang($angka)
    {
        $satuan = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
        $hasil = "";

        if ($angka < 12) {
            $hasil = $satuan[$angka];
        } elseif ($angka < 20) {
            $hasil = $satuan[$angka - 10] . " Belas";
        } elseif ($angka < 100) {
            $hasil = $satuan[floor($angka / 10)] . " Puluh " . $satuan[$angka % 10];
        } elseif ($angka < 200) {
            $hasil = "Seratus " . self::terbilang($angka - 100); // Ganti $this dengan self
        } elseif ($angka < 1000) {
            $hasil = $satuan[floor($angka / 100)] . " Ratus " . self::terbilang($angka % 100); // Ganti $this dengan self
        } elseif ($angka < 2000) {
            $hasil = "Seribu " . self::terbilang($angka - 1000); // Ganti $this dengan self
        } elseif ($angka < 1000000) {
            $hasil = self::terbilang(floor($angka / 1000)) . " Ribu " . self::terbilang($angka % 1000); // Ganti $this dengan self
        } elseif ($angka < 1000000000) {
            $hasil = self::terbilang(floor($angka / 1000000)) . " Juta " . self::terbilang($angka % 1000000); // Ganti $this dengan self
        }

        return trim($hasil);
    }


    protected static function tanggal_indonesia($tanggal, $mode = null)
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
        if ($mode == "period") {
            return $bulan[(int) $var[1]] . " " . $var[0];
        } else {
            return $var[2] . " " . $bulan[(int) $var[1]] . " " . $var[0];
        }
    }

    protected static function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ".", ",");
        return $hasil_rupiah;
    }

    private static function chunkByContentHeight($data, $extra_row = null)
    {
        $maxHeight = 1000;
        $chunks = [];
        $currentChunk = [];
        $currentHeight = 0;

        foreach ($data as $key => $row) {
            // Estimasi tinggi berdasarkan panjang teks
            $textLength = strlen($row->keterangan_pengujian ?? '') +
                strlen(json_encode($row->regulasi ?? ''));
            $rowHeight = 40 + ceil($textLength / 120) * 20;

            if ($key == count($data) - 1 && $extra_row != null) {
                $extraHeight = (40 + 20) * 3;
                if ($currentHeight + $rowHeight + $extraHeight > $maxHeight) {
                    $chunks[] = $currentChunk;
                    $currentChunk = [];
                    $currentHeight = 0;
                }
            } else {
                if ($currentHeight + $rowHeight > $maxHeight) {
                    $chunks[] = $currentChunk;
                    $currentChunk = [];
                    $currentHeight = 0;
                }
            }

            $currentChunk[] = $row;
            $currentHeight += $rowHeight;
        }

        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }
}
