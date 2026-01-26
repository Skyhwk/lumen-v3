<?php

namespace App\Jobs;

use App\Services\RenderData;
use Illuminate\Support\Facades\DB;
use App\Services\MpdfService as Mpdf;
use Imagick;
use App\Models\GenerateLink;

class GeneratePdfDocument extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $data;
    protected $public;

    public function __construct($data)
    {
        $this->data = $data;
        $this->public = public_path('/quotation');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        if ($data['status_quot'] == 'non_kontrak') {
            $cek = DB::table('request_quotation')
                ->leftJoin('master_karyawan as a', 'request_quotation.nama_sales', '=', 'a.id')
                ->leftJoin('master_karyawan as b', 'request_quotation.updated_by', '=', 'b.nama_lengkap')
                ->leftJoin('master_cabang as c', 'request_quotation.id_cabang', '=', 'c.id')
                ->select(DB::raw('a.nama_lengkap nama_add, a.no_telpon, b.nama_lengkap nama_update, request_quotation.*, c.*'))
                ->where('request_quotation.id', $data['id'])
                ->where('request_quotation.is_active', true)
                ->first();

            $data['data'] = $cek;
            if ($data['jadwal'] == 'jadwal') {
                $fileName = $this->renderDataJadwalSampler($data);
            } else {
                $fileName = $this->renderDataQuotation($data);
            }
        } else {
            $cek = DB::table('request_quotation_kontrak_H')
                ->leftJoin('master_karyawan as a', 'request_quotation_kontrak_H.nama_sales', '=', 'a.id')
                ->leftJoin('master_karyawan as b', 'request_quotation_kontrak_H.updated_by', '=', 'b.nama_lengkap')
                ->leftJoin('master_cabang as c', 'request_quotation_kontrak_H.id_cabang', '=', 'c.id')
                ->select(DB::raw('a.nama_lengkap nama_add, a.no_telpon, b.nama_lengkap nama_update, request_quotation_kontrak_H.*, c.*'))
                ->where('request_quotation_kontrak_H.id', $data['id'])
                ->where('request_quotation_kontrak_H.is_active', true)
                ->first();

            $data['data'] = $cek;
            if ($data['jadwal'] == 'jadwal') {
                $fileName = $this->renderDataJadwalSamplerH($data);
            } else {
                $fileName = $this->renderDataQuotationKontrak($data);
            }
        }

        if ($fileName != null) {
            $data = GenerateLink::where('token', $data['token'])->first();
            $data->fileName_pdf = $fileName;
            $data->save();

            DB::table('job_task')->insert([
                'job' => 'GeneratePdfDocument',
                'status' => 'success',
                'no_document' => $cek->no_document,
                'timestamp' => DATE('Y-m-d H:i:s'),
            ]);
        }
    }

    private function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    protected function renderDataQuotation($dataArray = [])
    {
        $db = $dataArray['db'];
        $id = $dataArray['id'];
        $data = $dataArray['data'];

        $filename = \str_replace("/", "_", $data->no_document);
        $qr = DB::table('qr_documents')
            ->where('id_document', $id)
            ->where('type_document', 'quotation')
            ->first();

        if (!is_null($qr)) {
            $qr_img = '<img src="' . $this->public . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
        } else {
            $qr_img = '';
        }

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

        switch ($data->status_sampling) {
            case 'S24':
                $sampling = 'SAMPLING 24 JAM';
                break;
            case 'SD':
                $sampling = 'SAMPLE DIANTAR';
                break;
            case 'RS':
                $sampling = 'RE-SAMPLE';
                break;
            default:
                $sampling = 'SAMPLING';
        }

        $pdf = new Mpdf($mpdfConfig);
        // if(isset($request->protect) && $request->protect != null)$pdf->SetProtection(array(), $request->protect, $request->protect);
        $pdf->charset_in = 'utf-8';
        $pdf->SetProtection(array('print'), '', 'skyhwk12');
        $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
        $pdf->showWatermarkImage = true;
        // $pdf->SetWatermarkText('CONFIDENTIAL');
        // $pdf->showWatermarkText = true;
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
        $konsultant = '';
        $jab_pic_or = '';
        $jab_pic_samp = '';
        if ($data->konsultan != '') {
            $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
            $perusahaan = ' (' . strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan))) . ') ';
        } else {
            $perusahaan = strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan)));
        }
        // dd($perusahaan);
        if ($data->jabatan_pic_order != '')
            $jab_pic_or = ' (' . $data->jabatan_pic_order . ')';
        if ($data->jabatan_pic_sampling != '')
            $jab_pic_samp = ' (' . $data->jabatan_pic_sampling . ')';
        if ($data->no_pic_order != '')
            $no_pic_order = ' -' . $data->no_pic_order;
        if ($data->no_tlp_pic_sampling != '')
            $no_pic_sampling = ' -' . $data->no_tlp_pic_sampling;

        $order = DB::table('order_header')
            ->where('no_document', $data->no_document)
            ->where('is_active', true)
            ->first();

        $ord = '';
        if (!is_null($order)) {
            $ord = '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';
        }

        $fileName = preg_replace('/\\//', '-', $data->no_document) . '.pdf';
        $pdf->SetHTMLHeader('
                <table class="tabel">
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
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;">
                                <b>PT INTI SURYA LABORATORIUM</b>
                                <br>
                                <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_cabang . '</span>
                                <br>
                                <span>T : ' . $data->tlp_cabang . ' - sales@intilab.com</span>
                                <br>www.intilab.com
                            </p>
                        </td>
                    </tr>
                </table>
                <table class="head2" width="100%">
                    <tr>
                        <td colspan="2">
                            <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</p>
                        </td>
                        <td style="vertical-align: top; text-align:right;">
                            <span style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-bottom:20px;" id="status_sampling">' . $sampling . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" width="80%">
                            <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . htmlspecialchars_decode($perusahaan) . '</h6>
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
                                <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . $no_pic_order . '</span>
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
                                <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . $no_pic_sampling . '</span>
                                <br>
                                <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                            </p>
                        </td>
                    </tr>
                </table>
            ');

        $dataArray = (object) [
            'db' => $db,
            'id' => $id,
            'data' => $data,
            'pdf' => $pdf,
            'fileName' => $fileName,
        ];

        $renderPdf = new RenderData($dataArray);
        $renderPdf->newpdfNonKontrak();

        return $fileName;
    }

    protected function renderDataQuotationKontrak($dataArray = [])
    {
        $db = $dataArray['db'];
        $id = $dataArray['id'];

        $data = $dataArray['data'];

        $filename = \str_replace("/", "_", $data->no_document);
        // dd($filename);
        $qr = DB::table('qr_documents')
            ->where('id_document', $id)
            ->where('type_document', 'quotation_kontrak')
            ->first();
        $qr_img = (is_null($qr)) ? '' : '<img src="' . $this->public . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
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
        switch ($data->status_sampling) {
            case 'S24':
                $sampling = 'SAMPLING 24 JAM';
                break;
            case 'SD':
                $sampling = 'SAMPLE DIANTAR';
                break;
            default:
                $sampling = 'SAMPLING';
                break;
        }
        $pdf = new Mpdf($mpdfConfig);
        // if(isset($request->protect) && $request->protect != null)$pdf->SetProtection(array(), $request->protect, $request->protect);
        $pdf->SetProtection(array('print'), '', 'skyhwk12');
        $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
        $pdf->showWatermarkImage = true;
        // $pdf->SetWatermarkText('CONFIDENTIAL');
        // $pdf->showWatermarkText = true;
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
        $konsultant = '';
        $jab_pic_or = '';
        $jab_pic_samp = '';
        if ($data->konsultan != '') {
            $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
            $perusahaan = ' (' . strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan))) . ') ';
        } else {
            $perusahaan = strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan)));
        }
        // if ($data->konsultan != '')
        // {
        //     $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
        //     $perusahaan = ' (' . htmlspecialchars_decode($data->nama_perusahaan) . ') ';
        // }
        // else
        // {
        //     $perusahaan = htmlspecialchars_decode($data->nama_perusahaan);
        // }
        if ($data->jabatan_pic_order != '')
            $jab_pic_or = ' (' . $data->jabatan_pic_order . ')';
        if ($data->jabatan_pic_sampling != '')
            $jab_pic_samp = ' (' . $data->jabatan_pic_sampling . ')';
        $order = DB::table('order_header')
            ->where('no_document', $data->no_document)
            ->where('is_active', true)
            ->first();

        $ord = is_null($order) ? '' : "<span style=\"font-size:11px; font-weight: bold; border: 1px solid gray;\" id=\"status_sampling\">{$order->no_order}</span>";

        $fileName = preg_replace('/\\//', '-', $data->no_document) . '.pdf';

        $detail = DB::table('request_quotation_kontrak_D')
            ->where('id_request_quotation_kontrak_h', $id)
            ->orderBy('periode_kontrak', 'asc')
            ->get();

        // dd($detail);

        $period = array_map(function ($item) {
            return self::tanggal_indonesia((array) json_decode($item->data_pendukung_sampling)[0]->periode_kontrak, 'period');
        }, [$detail[0], end($detail)]);
        $period = (explode(" ", $period[0])[1] == explode(" ", $period[(count($period) - 1)])[1])
            ? explode(" ", $period[0])[0] . ' - ' . $period[(count($period) - 1)]
            : $period[0] . ' - ' . $period[(count($period) - 1)];

        // dd('test');

        $pdf->SetHTMLHeader(' 
            <table class="tabel">
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
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $period . ' </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="font-size: 9px; text-align:right;">
                            <b>PT INTI SURYA LABORATORIUM</b>
                            <br>
                            <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_cabang . '</span>
                            <br>
                            <span>T : ' . $data->tlp_cabang . ' - sales@intilab.com</span>
                            <br>www.intilab.com
                        </p>
                    </td>
                </tr>
            </table>
            <table class="head2" width="100%">
                <tr>
                    <td colspan="2">
                        <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia($data->tgl_order) . '</p>
                    </td>
                    <td style="vertical-align: top; text-align:right;">
                        <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">CONTRACT</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" width="80%">
                        <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . $perusahaan . '</h6>
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
                            <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . ' - ' . $data->no_pic_order . '</span>
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
            </table>
        ');
        // dd('masuk');
        $dataArray = (object) [
            'db' => $db,
            'id' => $id,
            'data' => $data,
            'pdf' => $pdf,
            'fileName' => $fileName,
            'detail' => $detail
        ];

        $renderPdf = new RenderData($dataArray);
        $renderPdf->newpdfKontrak();
        return $fileName;
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

        $var = explode('-', $tanggal);
        if ($mode == 'period') {
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        }

    }

    protected function renderDataJadwalSampler($dataArray = [])
    {

        $db = $dataArray['db'];
        $id = $dataArray['id'];
        $data = $dataArray['data'];
        // dd($data);
        //create by staf : ist690, sampling plan

        $datajadwalPlan = DB::table('jadwal')
            ->leftJoin('sampling_plan', 'jadwal.id_sampling', '=', 'sampling_plan.id')
            ->select('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi')
            ->where('jadwal.is_active', true)
            ->where('no_quotation', $data->no_document)
            ->groupBy('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi')
            ->get();

        $dataSamplingPlan = DB::table('sampling_plan')
            ->where('qoutation_id', $id)
            ->where('is_active', true)
            ->first();

        // dd($dataSamplingPlan);

        //create by staf : ist690,step 3 buat name file
        $filename = \str_replace("/", "_", $data->no_document);

        //create by staf : ist690, step 4 buat qr imagenya
        $qr = DB::table('qr_documents')
            ->where('id_document', $id)
            ->where('type_document', 'quotation')
            ->first();
        if (!is_null($qr)) {
            $qr_img = '<img src="' . $this->public . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
        } else {
            $qr_img = '';
        }

        //create by staf : ist690,step 4 setting config
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

        //create by staf : ist690,step 5 chek sampling
        switch ($data->status_sampling) {
            case 'S24':
                $sampling = 'SAMPLING 24 JAM';
                break;
            case 'SD':
                $sampling = 'SAMPLE DIANTAR';
                break;
            default:
                $sampling = 'SAMPLING';
                break;
        }

        //create by staf : ist690,step 6 pdf
        $pdf = new Mpdf($mpdfConfig);

        $pdf->SetProtection(array('print'), '', 'skyhwk12');
        $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
        $pdf->showWatermarkImage = true;
        // $pdf->SetWatermarkText('CONFIDENTIAL');
        // $pdf->showWatermarkText = true;
        //create by staf : ist690, step 7 bagan footer
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
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem',
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
        $konsultant = $data->konsultan != '' ? strtoupper(htmlspecialchars_decode($data->konsultan)) : '';
        $jab_pic_or = $data->jabatan_pic_order != '' ? ' (' . $data->jabatan_pic_order . ')' : '';
        $jab_pic_samp = $data->jabatan_pic_sampling != '' ? ' (' . $data->jabatan_pic_sampling . ')' : '';
        $perusahaan = $data->konsultan != '' ? ' (' . htmlspecialchars_decode($data->nama_perusahaan) . ') ' : htmlspecialchars_decode($data->nama_perusahaan);
        $no_pic_order = $data->no_pic_order != '' ? ' -' . $data->no_pic_order : '';
        $no_pic_sampling = $data->no_tlp_pic_sampling != '' ? ' -' . $data->no_tlp_pic_sampling : '';

        $order = DB::table('order_header')
            ->where('no_document', $data->no_document)
            ->where('is_active', true)
            ->first();

        $ord = is_null($order) ? '' : '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';

        //create by staf : ist690,step 3 -> step 8 create namefile
        $fileName = preg_replace('/\\//', '-', 'JADWAL-SAMPLING-' . $data->no_document) . '.pdf';

        //create by staf : ist690, step 9 create header
        $pdf->SetHTMLHeader('
                <table class="tabel">
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
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;">
                                <b>PT INTI SURYA LABORATORIUM</b>
                                <br>
                                <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_cabang . '</span>
                                <br>
                                <span>T : ' . $data->tlp_cabang . ' - sales@intilab.com</span>
                                <br>www.intilab.com
                            </p>
                        </td>
                    </tr>
                </table>
                <table class="head2" width="100%">
                    <tr>
                        <td colspan="2">
                            <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</p>
                        </td>
                        <td style="vertical-align: top; text-align:right;">
                            <span style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-bottom:20px;" id="status_sampling">' . $sampling . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" width="80%">
                            <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . htmlspecialchars_decode($perusahaan) . '</h6>
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
                                <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . $no_pic_order . '</span>
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
                                <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . $no_pic_sampling . '</span>
                                <br>
                                <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                            </p>
                        </td>
                    </tr>
                </table>
                ');

        $pdf->writeHTML('
                <table class="table table-bordered" style="border:1px solid black; width:100%; padding:5px; ">
                    <tr>
                        <td colspan="7" style="text-align:center;padding:5px; border:1px solid black;">Penjadwalan</td>
                    </tr>
                    <tr>
                        <td style="text-align:center;padding:5px; border:1px solid black;">No</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">Kategori Pengambilan</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">Tanggal Mulai</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">Tanggal Selesai</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">Jam Mulai</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">Jam Selesai</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">Durasi</td>
                    </tr>');


        foreach ($datajadwalPlan as $key => $value) {
            $durasiText = '';
            $tanggalSelesai = null;
            if ($value->durasi == 0) {
                $durasiText = 'Sesaat';
                $tanggalSelesai = $value->tanggal;
            } else if ($value->durasi == 1) {
                $durasiText = '8 Jam';
                $tanggalSelesai = $value->tanggal;
            } else if ($value->durasi == 2) {
                $durasiText = '24 Jam';
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . ' +1 days'));
            } else if ($value->durasi == 3) {
                $durasiText = '2x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 4) {
                $durasiText = '3x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 5) {
                $durasiText = '4x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 6) {
                $durasiText = '5x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 7) {
                $durasiText = '6x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 8) {
                $durasiText = '7x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else {
                $durasiText = 'Durasi Tidak Ada';
                $tanggalSelesai = 'Tidak Ada';
            }
            $jadwalTable = json_decode($value->kategori);
            $pdf->writeHtml('<tr><td style="text-align:center;padding:3px; border:1px solid black;">' . ($key + 1) . '</td><td style="text-align:center;padding:3px; border:1px solid black;">');

            // Buka tabel dalam kolom
            $pdf->writeHtml('<ul>');

            foreach ($jadwalTable as $subKey => $subValue) {
                // Tambahkan baris untuk setiap item dalam jadwalTable
                $pdf->writeHtml('<li style="font-size:10px;">' . $subValue . '</li>');
            }
            // Tutup tabel dalam kolom
            $pdf->writeHtml('</ul></td>');

            // Tambahkan kolom lainnya
            $pdf->writeHtml('<td style="text-align:center;padding:3px; border:1px solid black;">' . $value->tanggal . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $tanggalSelesai . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $value->jam_mulai . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $value->jam_selesai . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $durasiText . '</td>
            </tr>');
        }
        $pdf->writeHtml('</table>');

        $pdf->WriteHTML('
            <table class="table table-bordered" style="width:50%; padding:5px;border:1px solid black; margin-top:12px;">
                <thead>
                    <tr>
                        <th style="text-align:center;padding:5px; border:1px solid black;">Tambahan</th>
                        <th style="text-align:center;padding:5px; border:1px solid black;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>');

        $samplingTambahan = ($dataSamplingPlan != null && $dataSamplingPlan->tambahan && $dataSamplingPlan->tambahan != '') ? json_decode($dataSamplingPlan->tambahan) : [];
        $samplingKeterangan = $dataSamplingPlan != null ? json_decode($dataSamplingPlan->keterangan_lain) : [];

        if (is_array($samplingTambahan) && is_array($samplingKeterangan)) {
            $count = max(count($samplingTambahan), count($samplingKeterangan));

            for ($i = 0; $i < $count; $i++) {
                $tambahan = isset($samplingTambahan[$i]) ? $samplingTambahan[$i] : '';
                $keterangan = isset($samplingKeterangan[$i]) ? $samplingKeterangan[$i] : '';

                $pdf->writeHtml('<tr>
                            <td style="border:1px solid black;padding:5px;">' . $tambahan . '</td>
                            <td style="border:1px solid black;padding:5px;">' . $keterangan . '</td>
                        </tr>');
            }
        }
        // dd($pdf);
        $pdf->WriteHTML('</tbody></table>');
        $pdf->AddPage();
        //create by staf : ist690, bagan qoutation non kkontrak
        $dataArray = (object) [
            'db' => $db,
            'id' => $id,
            'data' => $data,
            'pdf' => $pdf,
            'fileName' => $fileName,
        ];

        $renderPdf = new RenderData($dataArray);
        $renderPdf->newpdfNonKontrak();

        return $fileName;
    }

    protected function renderDataJadwalSamplerH($dataArray = [])
    {
        $db = $dataArray['db'];
        $id = $dataArray['id'];

        $data = $dataArray['data'];

        //create by staf : ist690, sampling plan
        $datajadwalPlan = DB::table('jadwal')
            ->leftJoin('sampling_plan', 'jadwal.id_sampling', '=', 'sampling_plan.id')
            ->select('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi')
            ->where('jadwal.is_active', true)
            ->where('no_quotation', $data->no_document)
            ->groupBy('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi')
            ->get();

        $dataSamplingPlan = DB::table('sampling_plan')
            ->where('qoutation_id', $id)
            ->where('is_active', true)
            ->first();

        $filename = \str_replace("/", "_", $data->no_document);
        $qr = DB::table('qr_documents')
            ->where('id_document', $id)
            ->where('type_document', 'quotation_kontrak')
            ->first();
        if (!is_null($qr)) {
            $qr_img = '<img src="' . $this->public . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
        } else {
            $qr_img = '';
        }
        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 3, // 30mm not pixel
            'margin_footer' => 3,
            'setAutoTopMargin' => 'stretch',
            'orientation' => 'P'
        );

        if ($data->status_sampling == 'S24') {
            $sampling = 'SAMPLING 24 JAM';
        } else if ($data->status_sampling == 'SD') {
            $sampling = 'SAMPLE DIANTAR';
        } else {
            $sampling = 'SAMPLING';
        }

        $pdf = new Mpdf($mpdfConfig);
        // if(isset($request->protect) && $request->protect != null)$pdf->SetProtection(array(), $request->protect, $request->protect);
        $pdf->SetProtection(array(
            'print'
        ), '', 'skyhwk12');
        $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(
            65,
            60
        ));
        $pdf->showWatermarkImage = true;
        // $pdf->SetWatermarkText('CONFIDENTIAL');
        // $pdf->showWatermarkText = true;
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
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem',
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
        $konsultant = '';
        $jab_pic_or = '';
        $jab_pic_samp = '';
        $perusahaan = '';
        if ($data->konsultan != '') {
            $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
            $perusahaan = ' (' . htmlspecialchars_decode($data->nama_perusahaan) . ') ';
        } else {
            $perusahaan = htmlspecialchars_decode($data->nama_perusahaan);
        }

        if ($data->jabatan_pic_order != '')
            $jab_pic_or = ' (' . $data->jabatan_pic_order . ')';

        if ($data->jabatan_pic_sampling != '')
            $jab_pic_samp = ' (' . $data->jabatan_pic_sampling . ')';
        $order = DB::table('order_header')
            ->where('no_document', $data->no_document)
            ->where('is_active', true)
            ->first();

        $ord = '';
        if (!is_null($order)) {
            $ord = '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';
        }

        $fileName = preg_replace('/\\//', '-', 'JADWAL-SAMPLING-' . $data->no_document) . '.pdf';
        $detail = DB::table('request_quotation_kontrak_D')
            ->where('id_request_quotation_kontrak_h', $id)
            ->orderBy('periode_kontrak', 'asc')
            ->get();
        $period = [];
        foreach ($detail as $key => $val) {
            if ($key == 0) {
                foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                    array_push($period, self::tanggal_indonesia($v_->periode_kontrak, 'period'));
                    continue;
                }
            } else if ($key == (count($detail) - 1)) {
                foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                    array_push($period, self::tanggal_indonesia($v_->periode_kontrak, 'period'));
                    continue;
                }
            }
        }

        if (explode(" ", $period[0])[1] == explode(" ", $period[(count($period) - 1)])[1]) {

            $period = explode(" ", $period[0])[0] . ' - ' . $period[(count($period) - 1)];
        } else {
            $period = $period[0] . ' - ' . $period[(count($period) - 1)];
        }

        $pdf->SetHTMLHeader('
            <table class="tabel">
                <tr class="tr_top">
                    <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                            src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                    </td>
                    <td style="width: 33.33%; text-align: center;">
                        <h5 style="text-align:center; font-size:14px;"><b><u>QUOTATION</u></b></h5>
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . '
                        </p>
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $period . '
                        </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="font-size: 9px; text-align:right;"><b>PT INTI SURYA
                                LABORATORIUM</b><br><span
                                style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_cabang . '</span><br><span>T : ' . $data->tlp_cabang . ' - sales@intilab.com</span><br>www.intilab.com
                        </p>
                    </td>
                </tr>
            </table>
            <table class="head2" width="100%">
                <tr>
                    <td colspan="2">
                    <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia($data->tgl_order) . '</p>
                    </td>
                    <td style="vertical-align: top; text-align:right;"><span
                    style="font-size:11px; font-weight: bold; border: 1px solid gray;">CONTRACT</span></td>
                </tr>
                <tr>
                    <td colspan="2" width="80%"><h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . $perusahaan . '</h6></td>
                    <td style="vertical-align: top; text-align:right;">' . $ord . '</td>
                </tr>
                <tr>
                    <td style="width:35%;vertical-align:top;"><p style="font-size: 10px;"><u>Alamat Kantor :</u><br><span
                    id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span><br><span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span><br><span
                    id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . ' - ' . $data->no_pic_order . '</span><br><span id="email_pic_order">' . $data->email_pic_order . '</span></p></td>
                    <td style="width: 30%; text-align: center;"></td>
                    <td style="text-align: left;vertical-align:top;"><p style="font-size: 10px;"><u>Alamat Sampling :</u><br><span
                    id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span><br><span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span><br><span
                    id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . '</span><br><span id="email_pic_sampling">' . $data->email_pic_sampling . '</span></p></td>
                </tr>
            </table>
            ');

        $pdf->writeHTML('
            <table class="table table-bordered" style="border:1px solid black; width:100%; padding:5px; ">
                <tr>
                    <td colspan="7" style="text-align:center;padding:5px; border:1px solid black;">Penjadwalan</td>
                </tr>
                <tr>
                    <td style="text-align:center;padding:5px; border:1px solid black;">No</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">Kategori Pengambilan</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">Tanggal Mulai</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">Tanggal Selesai</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">Jam Mulai</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">Jam Selesai</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">Durasi</td>
                </tr>');


        foreach ($datajadwalPlan as $key => $value) {
            $durasiText = '';
            $tanggalSelesai = null;

            if ($value->durasi == 0) {
                $durasiText = 'Sesaat';
                $tanggalSelesai = $value->tanggal;
            } else if ($value->durasi == 1) {
                $durasiText = '8 Jam';
                $tanggalSelesai = $value->tanggal;
            } else if ($value->durasi == 2) {
                $durasiText = '24 Jam';
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . ' +1 days'));
            } else if ($value->durasi == 3) {
                $durasiText = '2x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 4) {
                $durasiText = '3x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 5) {
                $durasiText = '4x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 6) {
                $durasiText = '5x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 7) {
                $durasiText = '6x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else if ($value->durasi == 8) {
                $durasiText = '7x24 Jam';
                $jumlah_hari = intval(substr($durasiText, 0, 1));
                $tanggalSelesai = date('Y-m-d', strtotime($value->tanggal . " +$jumlah_hari days"));
            } else {
                $durasiText = 'Durasi Tidak Ada';
                $tanggalSelesai = 'Tidak Ada';
            }
            $jadwalTable = json_decode($value->kategori);
            $pdf->writeHtml('<tr><td style="text-align:center;padding:3px; border:1px solid black;">' . ($key + 1) . '</td><td style="text-align:center;padding:3px; border:1px solid black;">');

            // Buka tabel dalam kolom
            $pdf->writeHtml('<ul>');

            foreach ($jadwalTable as $subKey => $subValue) {
                // Tambahkan baris untuk setiap item dalam jadwalTable
                $pdf->writeHtml('<li style="font-size:10px;">' . $subValue . '</li>');
            }
            // Tutup tabel dalam kolom
            $pdf->writeHtml('</ul></td>');

            // Tambahkan kolom lainnya
            $pdf->writeHtml('<td style="text-align:center;padding:3px; border:1px solid black;">' . $value->tanggal . '</td>
            <td style="text-align:center;padding:3px; border:1px solid black;">' . $tanggalSelesai . '</td>
            <td style="text-align:center;padding:3px; border:1px solid black;">' . $value->jam_mulai . '</td>
            <td style="text-align:center;padding:3px; border:1px solid black;">' . $value->jam_selesai . '</td>
            <td style="text-align:center;padding:3px; border:1px solid black;">' . $durasiText . '</td>
        </tr>');
        }
        $pdf->writeHtml('</table>');

        $pdf->WriteHTML('<table class="table table-bordered" style="width:50%; padding:5px;border:1px solid black; margin-top:12px;">
                            <thead>
                                <tr>
                                    <th style="text-align:center;padding:5px; border:1px solid black;">Tambahan</th>
                                    <th style="text-align:center;padding:5px; border:1px solid black;">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>');

        $samplingTambahan = json_decode($dataSamplingPlan->tambahan);
        $samplingKeterangan = json_decode($dataSamplingPlan->keterangan_lain);

        if (is_array($samplingTambahan) && is_array($samplingKeterangan)) {
            $count = max(count($samplingTambahan), count($samplingKeterangan));

            for ($i = 0; $i < $count; $i++) {
                $tambahan = isset($samplingTambahan[$i]) ? $samplingTambahan[$i] : '';
                $keterangan = isset($samplingKeterangan[$i]) ? $samplingKeterangan[$i] : '';

                $pdf->writeHtml('<tr>
                    <td style="border:1px solid black;padding:5px;">' . $tambahan . '</td>
                    <td style="border:1px solid black;padding:5px;">' . $keterangan . '</td>
                </tr>');
            }
        }

        $pdf->WriteHTML('</tbody></table>');

        $pdf->AddPage();
        $dataArray = (object) [
            'db' => $db,
            'id' => $id,
            'data' => $data,
            'pdf' => $pdf,
            'fileName' => $fileName,
            'detail' => $detail
        ];

        $renderPdf = new RenderData($dataArray);
        $renderPdf->newpdfKontrak();

        return $fileName;
    }
}