<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\GenerateLink;
use App\Models\JobTask;
use App\Models\Jadwal;
use App\Models\QrDocument;
use App\Models\SamplingPlan;
use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Support\Facades\DB;
use \App\Services\MpdfService as PDF;
use Carbon\Carbon;
use Exception;

class EmailJadwal
{
    private $data;
    private $value;
    private $quotation_id;
    private $tanggal_penawaran;
    private $timestamp;
    private $lang;
    private static $instance;

    public function __construct(object $data = null, array $value = null, string $lang = 'id')
    {
        app()->setLocale($lang);
        Carbon::setLocale($lang);

        if (self::$instance === null) {
            self::$instance = $this;
        }
        if ($data !== null) {
            $this->data = $data;
            $this->timestamp = $this->data->timestamp;
        }
        if ($value !== null) {
            $this->value = $value;
        }
        if ($lang !== null) {
            $this->lang = $lang;
        }
    }

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'quotation_id':
                self::$instance->quotation_id = $value;
                break;
            case 'tanggal_penawaran':
                self::$instance->tanggal_penawaran = $value;
                break;
        }
        return self::$instance;
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

    private function decrypt($data = null)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
        $data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $extand = explode("|", $data);
        return $extand;
    }

    private function tanggal_indonesia($tanggal, $mode = '')
    {
        $date = Carbon::parse($tanggal);

        switch ($mode) {
            case 'period':
                return $date->translatedFormat('F Y'); // contoh: "Juni 2025"
            case 'hari':
                return $date->translatedFormat('l / d F Y'); // contoh: "Selasa / 24 Juni 2025"
            default:
                return $date->translatedFormat('d F Y'); // contoh: "24 Juni 2025"
        }
    }

    public function emailJadwalSampling()
    {
        $request = $this->data;
        $user = MasterKaryawan::where('id', $request->karyawan_id)->first()->toArray();
        $parts = explode('/', $request->no_document);
        $path = $parts[1] ?? null;
        if ($path == 'QT') {
            $data = QuotationNonKontrak::with(['sales:id,nama_lengkap,email,atasan_langsung', 'updateby:id,nama_lengkap,email,atasan_langsung'])
                ->where('no_document', $request->no_document)
                ->where('is_active', true)
                ->first();

            if ($data) {
                $data->toArray();
                $status_quot = 'non_kontrak';
                $sales = MasterKaryawan::where('id', $data['sales_id'])->first();
            } else {
                throw new Exception('Quotation not found when execute emailJadwalSampling()', 401);
            }
        } else if ($path == 'QTC') {
            $data = QuotationKontrakH::with(['sales:id,nama_lengkap,email,atasan_langsung', 'updateby:id,nama_lengkap,email,atasan_langsung'])
                ->where('no_document', $request->no_document)
                ->where('is_active', true)
                ->first();

            if ($data) {
                $data->toArray();
                $status_quot = 'kontrak';
                $sales = MasterKaryawan::where('id', $data['sales_id'])->first();
            } else {
                throw new Exception('Quotation not found when execute emailJadwalSampling()', 401);
            }
        }
        $dataEmail = ["user" => $user, "client" => (object) $data, "status_quot" => $status_quot];

        DB::beginTransaction();
        try {
            $this->tanggal_penawaran = $dataEmail['client']->tanggal_penawaran;
            $this->quotation_id = $request->quotation_id;

            $token_ = $this->servisRenderJadwal();

            if ($token_) {
                $dataEmail['file'][0] = [
                    'token' => $token_
                ];
            } else {
                SamplingPlan::where('id', $request->sampling_id)
                    ->update(['is_approved' => false]);
            }

            // if ($path == 'QT') {
            //     $tanggal = (isset($this->value['tanggal'])) ? implode(", ", $this->value['tanggal']) : "-";
            //     $jam_mulai = (isset($this->value['jam_mulai'])) ? $this->value['jam_mulai'] : "-";
            //     $jam_selesai = (isset($this->value['jam_selesai'])) ? $this->value['jam_selesai'] : "-";

            //     $body_text =
            //         ' <style>
            //             body {
            //                 font-family: Arial, sans-serif;
            //                 margin: 0;
            //                 padding: 0
            //             }
            //             .container {
            //                 max-width: 600px;
            //                 margin: 0 auto;
            //                 padding: 20px
            //             }
            //             .header {
            //                 background-color: #007bff;
            //                 color: #fff;
            //                 text-align: center;
            //                 padding: 10px
            //             }
            //             .content {
            //                 padding: 20px
            //             }
            //             .signature {
            //                 margin-top: 20px;
            //                 font-style: italic
            //             }
            //         </style>
            //     <div class="container">
            //         <div class="header">
            //             <h2>Penjadwalan Pengambilan Sampling</h2>
            //         </div>
            //         <div class="content">
            //             <p>Kepada ' . $dataEmail['client']->nama_pic_order . ',</p>
            //             <p>&nbsp;' . $dataEmail['client']->nama_perusahaan . '</p>
            //             <p>Saya harap email ini menemui Anda dalam keadaan baik. Saya ingin mengatur jadwal pengambilan sampling yang telah direncanakan dengan Anda. Berikut adalah rincian jadwal dan informasi yang relevan:</p>
            //             <ul>
            //                 <li>
            //                     <strong>Tanggal Pengambilan Sampling: </strong>' . $tanggal . '
            //                 </li>
            //                 <li>
            //                     <strong>Waktu: </strong>' . $jam_mulai . ' sd ' . $jam_selesai . ' WIB
            //                 </li>
            //             </ul>
            //             <p>Mohon konfirmasi kembali jadwal ini apakah telah sesuai dengan ketersediaan Anda. Jika ada perubahan yang perlu dilakukan atau pertanyaan lebih lanjut, silakan segera hubungi pihak sales kami (' . $sales->nama_lengkap . '-' . $sales->no_telpon . ') atau melalui telepon 021-5089-8988/89.</p>
            //             <p>Kami sangat menghargai kerjasama Anda dalam proses pengambilan sampling ini dan berharap semuanya berjalan lancar. Terima kasih atas perhatian Anda dan segera konfirmasi jadwal ini agar kami dapat mempersiapkan segala yang diperlukan.</p>
            //             <p>Terima kasih dan salam,</p>
            //             <a role="button" id="detailPdf" href="' . env('PORTAL_API') . $dataEmail['file'][0]['token'] . '" class="btn btn-primary">Lihat Detail</a>
            //             <p class="signature">' . $dataEmail['user']['nama_lengkap'] . ' <br>' . $dataEmail['user']['cost_center'] . ' <br>INTI SURYA LABORATARIUM <br>' . $dataEmail['user']['email'] . ' <br>' . $dataEmail['user']['no_telpon'] . ' <br>
            //             </p>
            //         </div>
            //     </div>';
            // } else if ($path == 'QTC') {
            //     $body_text =
            //         ' <style>
            //         body {
            //             font-family: Arial, sans-serif;
            //             margin: 0;
            //             padding: 0
            //         }

            //         .container {
            //             max-width: 600px;
            //             margin: 0 auto;
            //             padding: 20px
            //         }

            //         .header {
            //             background-color: #007bff;
            //             color: #fff;
            //             text-align: center;
            //             padding: 10px
            //         }

            //         .content {
            //             padding: 20px
            //         }

            //         .signature {
            //             margin-top: 20px;
            //             font-style: italic
            //         }
            //     </style>
            //     <div class="container">
            //         <div class="header">
            //             <h2>Penjadwalan Pengambilan Sampling</h2>
            //         </div>
            //         <div class="content">
            //             <p>Kepada ' . $dataEmail['client']->nama_pic_order . ',</p>
            //             <p>&nbsp;' . $dataEmail['client']->nama_perusahaan . '</p>
            //             <p>Saya harap email ini menemui Anda dalam keadaan baik. Saya ingin mengatur jadwal pengambilan sampling yang telah direncanakan dengan Anda.</p>';
            //     // dd($this->value);
            //     foreach ($this->value as $periode => $data) {
            //         $tanggal = !empty($data['tanggal']) ? implode(", ", $this->translateTanggalArray($data['tanggal'])) : "-";
            //         $jam_mulai = $data['jam_mulai'] ?? "-";
            //         $jam_selesai = $data['jam_selesai'] ?? "-";
            //         $sampler = !empty($data['sampler']) ? implode(", ", $data['sampler']) : "-";

            //         $body_text .= "
            //                     <li>
            //                         <strong>Periode:</strong> " . self::translatePeriode($periode) . "<br>
            //                         <strong>Tanggal Pengambilan Sampling: </strong> $tanggal<br>
            //                         <strong>Waktu:</strong> $jam_mulai sd $jam_selesai WIB<br>
            //                     </li><br>
            //                 ";
            //     }
            //     $body_text .= '<p>Mohon konfirmasi kembali jadwal ini apakah telah sesuai dengan ketersediaan Anda. Jika ada perubahan yang perlu dilakukan atau pertanyaan lebih lanjut, silakan segera hubungi pihak sales kami (' . $sales->nama_lengkap . '-' . $sales->no_telpon . ') atau melalui telepon 021-5089-8988/89.</p>
            //             <p>Kami sangat menghargai kerjasama Anda dalam proses pengambilan sampling ini dan berharap semuanya berjalan lancar. Terima kasih atas perhatian Anda dan segera konfirmasi jadwal ini agar kami dapat mempersiapkan segala yang diperlukan.</p>
            //             <p>Terima kasih dan salam,</p>
            //             <a role="button" id="detailPdf" href="' . env('PORTAL_API') . $dataEmail['file'][0]['token'] . '" class="btn btn-primary">Lihat Detail</a>
            //             <p class="signature">' . $dataEmail['user']['nama_lengkap'] . ' <br>' . $dataEmail['user']['cost_center'] . ' <br>INTI SURYA LABORATARIUM <br>' . $dataEmail['user']['email'] . ' <br>' . $dataEmail['user']['no_telpon'] . ' <br>
            //             </p>
            //         </div>
            //     </div>';
            // }

            if ($path === 'QT') {
                $view = 'TemplateEmailJadwal.sampling_qt';

                $data = [
                    'client' => $dataEmail['client'],
                    'user'   => $dataEmail['user'],
                    'sales'  => $sales,
                    'file'   => $dataEmail['file'][0],
                    'tanggal' => $this->value['tanggal'] ?? [],
                    'jam_mulai'   => $this->value['jam_mulai'] ?? '-',
                    'jam_selesai' => $this->value['jam_selesai'] ?? '-',
                ];
            } else if ($path === 'QTC') {
                $view = 'TemplateEmailJadwal.sampling_qtc';

                $data = [
                    'client' => $dataEmail['client'],
                    'user'   => $dataEmail['user'],
                    'sales'  => $sales,
                    'file'   => $dataEmail['file'][0],
                    'values' => $this->value,
                ];
            }

            $body_text = view($view, $data)->render();
            $atasan_sales = GetAtasan::where('id', $dataEmail['client']->sales_id)->get();

            // $filterEmails = [
            //     'inafitri@intilab.com',
            //     'kika@intilab.com',
            //     'trialif@intilab.com',
            //     'manda@intilab.com',
            //     'amin@intilab.com',
            //     'daud@intilab.com',
            //     'faidhah@intilab.com',
            // ];

            $emailBcc = $atasan_sales->pluck('email')->toArray();
            // if (count(array_intersect($filterEmails, $emailBcc)) > 0) {
            // }
            $emailBcc[] = 'admsales03@intilab.com';
            $emailBcc[] = 'admsales04@intilab.com';
            $emailBcc[] = 'luthfi@intilab.com';
            $emailBcc[] = 'sales@intilab.com';

            $idBcc = $atasan_sales->pluck('id')->toArray();
            $replyTo = ['admsales01@intilab.com'];
            $subject = "Penjadwalan Pengambilan Sampling (" . $dataEmail['client']->no_document . ") - " . htmlspecialchars_decode($dataEmail['client']->nama_perusahaan, ENT_QUOTES);
            $email = SendEmail::where('to', trim($dataEmail['client']->email_pic_order))
                ->where('subject', $subject)
                ->where('body', $body_text)
                ->where('bcc', $emailBcc)
                ->where('replyto', $replyTo)
                ->where('karyawan', $request->karyawan)
                ->fromAdmsales()
                ->send();

            if ($email) {
                JobTask::insert([
                    'job' => 'RenderAndEmailJadwal',
                    'status' => 'success',
                    'no_document' => $dataEmail['client']->no_document,
                    'timestamp' => $this->timestamp,
                ]);

                $idBcc = $atasan_sales->pluck('id')->toArray();
                $no_penawaran = $dataEmail['client']->no_document;
                $message = "Nomor QT $no_penawaran " . "\ntelah dijadwalkan dan sudah diapprove dan disampaikan ke client melalui email.";

                Notification::whereIn('id', $idBcc)->title('Jadwal Sampling')->message($message)->url('/sampling-plan')->send();

                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim.!'
                ], 200);
            }
        } catch (Exception $ex) {
            $templateMessage = "Error : " . $request->no_document . "\nError : " . $ex->getMessage() . "\nLine : " . $ex->getLine() . "\nFile : " . $ex->getFile() . "\n pada method email jadwal sampling";

            JobTask::insert([
                'job' => 'RenderAndEmailJadwal',
                'status' => 'failed',
                'no_document' => $request->no_document,
                'timestamp' => $this->timestamp,
            ]);

            DB::rollBack();
            throw new Exception($templateMessage, 401, $ex);
        }
    }

    private function translatePeriode($periode)
    {
        try {
            return Carbon::parse($periode . '-01')->translatedFormat('F Y');
        } catch (Exception $e) {
            return $periode;
        }
    }

    private function translateTanggal($tanggal)
    {
        Carbon::setLocale('id');

        try {
            return Carbon::parse($tanggal)->translatedFormat('d F Y');
        } catch (Exception $e) {
            return $tanggal;
        }
    }

    private function translateTanggalArray(array $tanggalList)
    {
        if (empty($tanggalList)) {
            return [];
        }

        return array_map(function ($tanggal) {
            return $this->translateTanggal($tanggal);
        }, $tanggalList);
    }

    public function servisRenderJadwal()
    {
        $request = $this->data;
        DB::beginTransaction();
        try {
            $type = explode('/', $request->no_document)[1];
            if ($type == 'QT') {
                $cek = QuotationNonKontrak::where('id', $request->quotation_id)
                    ->where('is_active', true)
                    ->first();
                if ($cek->flag_status != 'ordered')
                    $cek->flag_status = 'sp';
                if ($cek->flag_status != 'ordered')
                    $cek->is_ready_order = 1;
                $cek->save();
                $fileName = $this->renderDataJadwalSampler();
            } else {
                $cek = QuotationKontrakH::where('id', $request->quotation_id)
                    ->where('is_active', true)
                    ->first();
                if ($cek->flag_status != 'ordered')
                    $cek->flag_status = 'sp';
                if ($cek->flag_status != 'ordered')
                    $cek->is_ready_order = 1;
                $cek->save();
                $fileName = $this->renderDataJadwalSamplerH();
            }
            if ($cek != null && $fileName != null) {
                $key = $cek->created_by . DATE('YmdHis');
                $gen = MD5($key);
                $token = $this->encrypt($gen . '|' . $cek->email_pic_order);
                $data = [
                    'token' => $token,
                    'key' => $gen,
                    'expired' => Carbon::parse($cek->expired)->addMonths(3)->format('Y-m-d'),
                    //'password' => $cek->nama_pic_order[4] . DATE('dym', strtotime($cek->add_at)),
                    'created_at' => Carbon::parse($this->timestamp)->format('Y-m-d'),
                    'created_by' => $request->karyawan,
                    // 'fileName' => json_encode($data_file) ,
                    'fileName_pdf' => $fileName,
                    'is_reschedule' => 1,
                    'quotation_status' => ($type == 'QT') ? 'non_kontrak' : 'kontrak',
                    'type' => 'jadwal',
                    'id_quotation' => $request->quotation_id
                ];

                $dataLink = GenerateLink::insert($data);

                $cek->expired = Carbon::parse($cek->expired)->addMonths(1)->format('Y-m-d');
                $cek->generated_at = $this->timestamp;
                $cek->generated_by = $request->karyawan;
                $cek->filename = $fileName;
                $cek->is_generated = true;
                $cek->save();
                DB::commit();
                return $token;
            } else {
                DB::rollBack();
                throw new Exception("Data or Filename cannot be null", 401);
            }
        } catch (Exception $exception) {
            DB::rollback();
            throw new Exception($exception->getMessage() . $exception->getCode() . $exception->getLine(), 401);
        }
    }

    public function renderDataJadwalSampler()
    {
        if ($this->tanggal_penawaran == null || $this->quotation_id == null) {
            throw new Exception('Quotation id, Tanggal penawaran is required when execute renderDataJadwalSampler()', 401);
        }
        $db = null;

        $data = QuotationNonKontrak::with('cabang', 'addby', 'updateby')
            ->where('is_active', true)
            ->where('id', $this->quotation_id)
            ->first();

        $datajadwalPlan = Jadwal::with('samplingPlan')
            ->select('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi', 'id_sampling')
            ->where('is_active', true)
            ->where('no_quotation', $data->no_document)
            ->groupBy('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi', 'id_sampling')
            ->get()
            ->toArray();

        $qr = QrDocument::where('id_document', $this->quotation_id)->where('type_document', 'quotation')->first();
        if (!is_null($qr)) {
            $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
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

        if ($this->lang == 'zh') {
            $customFontPath = resource_path('fonts/Noto_Serif_SC');
            $mpdfConfig['fontDir'] = array_merge($mpdfConfig['fontDir'] ?? [], [
                $customFontPath,
            ]);

            $mpdfConfig['fontdata'] = [
                'notoserifsc' => [
                    'R' => 'NotoSerifSC-Regular.ttf',
                ],
            ];
            $mpdfConfig['default_font'] = 'notoserifsc';
        }

        switch ($data->status_sampling) {
            case 'S24':
                $sampling = strtoupper(__('Jadwal.status_sampling.S24'));
                break;
            case 'SD':
                $sampling = strtoupper(__('Jadwal.status_sampling.SD'));
                break;
            default:
                $sampling = strtoupper(__('Jadwal.status_sampling.S'));
                break;
        }

        $pdf = new PDF($mpdfConfig);

        $pdf->SetProtection(array('print'), '', 'skyhwk12');
        $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
        $pdf->showWatermarkImage = true;
        // $pdf->SetWatermarkText('CONFIDENTIAL');
        // $pdf->showWatermarkText = true;
        $footer = array(
            'odd' => array(
                'C' => array(
                    'content' => __('Jadwal.footer.center_content', ['page' => '{PAGENO}', 'total_pages' => '{nbpg}']),
                    'font-size' => 6,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060'
                ),
                'R' => array(
                    'content' => __('Jadwal.footer.right_content'),
                    // 'content' => __('Jadwal.footer.right_content') . ' <br> {DATE YmdGi}',
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
        if ($data->no_pic_order != '')
            $no_pic_order = ' -' . $data->no_pic_order;
        if ($data->no_tlp_pic_sampling != '')
            $no_pic_sampling = ' -' . $data->no_tlp_pic_sampling;

        $order = OrderHeader::where('no_document', $data->no_document)->where('is_active', true)->first();

        $ord = '';
        if (!is_null($order)) {
            $ord = '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';
        }

        $fileName = preg_replace('/\\//', '-', 'JADWAL-SAMPLING-' . $data->no_document) . '.pdf';

        $pdf->SetHTMLHeader(
            ' <table class="tabel">
                <tr class="tr_top">
                    <td class="text-left text-wrap" style="width: 33.33%;">
                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                    </td>
                    <td style="width: 33.33%; text-align: center;">
                        <h5 style="text-align:center; font-size:14px;">
                            <b>
                                <u>' . strtoupper(__('Jadwal.header.quotation')) . '</u>
                            </b>
                        </h5>
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="font-size: 9px; text-align:right;">
                            <b>PT INTI SURYA LABORATORIUM</b>
                            <br>
                            <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->cabang->alamat_cabang . '</span>
                            <br>
                            <span>T : ' . $data->cabang->tlp_cabang . ' - sales@intilab.com</span>
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
                            <u>' . __('Jadwal.header.office') . ' :</u>
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
                            <u>' . __('Jadwal.header.sampling') . ' :</u>
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
            </table> '
        );

        $pdf->writeHTML(
            ' <table class="table table-bordered" style="border:1px solid black; width:100%; padding:5px; ">
                <tr>
                    <td colspan="7" style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.title') . '</td>
                </tr>
                <tr>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.no') . '</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.category') . '</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.date.start') . '</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.date.end') . '</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.time.start') . '</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.time.end') . '</td>
                    <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.duration') . '</td>
                </tr>'
        );

        foreach ($datajadwalPlan as $key => $value) {
            $durasiText = '';
            $tanggalSelesai = null;

            switch ($value['durasi']) {
                case 0:
                    $durasiText = __('Jadwal.duration.0');
                    $tanggalSelesai = $value['tanggal'];
                    break;
                case 1:
                    $durasiText = __('Jadwal.duration.1');
                    $tanggalSelesai = $value['tanggal'];
                    break;
                case 2:
                    $durasiText = __('Jadwal.duration.2');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(1)->format('Y-m-d');
                    break;
                case 3:
                    $durasiText = __('Jadwal.duration.3');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(2)->format('Y-m-d');
                    break;
                case 4:
                    $durasiText = __('Jadwal.duration.4');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(3)->format('Y-m-d');
                    break;
                case 5:
                    $durasiText = __('Jadwal.duration.5');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(4)->format('Y-m-d');
                    break;
                case 6:
                    $durasiText = __('Jadwal.duration.6');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(5)->format('Y-m-d');
                    break;
                case 7:
                    $durasiText = __('Jadwal.duration.7');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(6)->format('Y-m-d');
                    break;
                case 8:
                    $durasiText = __('Jadwal.duration.8');
                    $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(7)->format('Y-m-d');
                    break;
                default:
                    $durasiText = __('Jadwal.duration.9');
                    $tanggalSelesai = 'Tidak Ada';
                    break;
            }

            $jadwalTable = json_decode($value['kategori']);

            $pdf->writeHtml(
                ' <tr>
                    <td style="text-align:center;padding:3px; border:1px solid black;">' . ($key + 1) . '</td>
                    <td style="text-align:left;padding:3px; border:1px solid black;">'
            );

            // Buka tabel dalam kolom
            if (count($jadwalTable) >= 200) {
                foreach ($jadwalTable as $subKey => $subValue) {
                    // Tambahkan baris untuk setiap item dalam jadwalTable
                    $pdf->writeHtml('<span style="margin-right: 10px; word-spacing: 3px; font-size: 12px;">' . $subValue . '</span>');
                }
                $pdf->writeHtml('</td>');
            } else {
                $pdf->writeHtml('<ul>');
                foreach ($jadwalTable as $subKey => $subValue) {
                    // Tambahkan baris untuk setiap item dalam jadwalTable
                    $pdf->writeHtml('<li>' . $subValue . '</li>');
                }
                $pdf->writeHtml('</ul></td>');
            }

            // Tambahkan kolom lainnya
            $pdf->writeHtml(
                ' <td style="text-align:center;padding:3px; border:1px solid black;">' . $value['tanggal'] . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $tanggalSelesai . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $value['jam_mulai'] . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $value['jam_selesai'] . '</td>
                <td style="text-align:center;padding:3px; border:1px solid black;">' . $durasiText . '</td></tr>'
            );
        }
        $pdf->writeHtml('</table>');

        $pdf->AddPage();

        $renderPdf = new RenderNonKontrak();
        return $renderPdf->renderBody($pdf, $data, $fileName, $this->lang);
    }

    public function renderDataJadwalSamplerH()
    {
        if ($this->tanggal_penawaran == null || $this->quotation_id == null) {
            throw new Exception('Quotation id, Tanggal penawaran is required when execute renderDataJadwalSamplerH()', 401);
        }
        try {
            $data = QuotationKontrakH::with('cabang', 'addby', 'updateby')
                ->where('is_active', true)
                ->where('id', $this->quotation_id)->first();

            $datajadwalPlan = Jadwal::with('samplingPlan')
                ->select('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi', 'id_sampling')
                ->where('is_active', true)
                ->where('no_quotation', $data->no_document)
                ->groupBy('no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai', 'id_sampling', 'kategori', 'durasi', 'id_sampling')
                ->get()
                ->toArray(); // Konversi ke array

            $qr = QrDocument::where('id_document', $this->quotation_id)->where('type_document', 'quotation_kontrak')->first();
            if (!is_null($qr)) {
                $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
            } else {
                $qr_img = '';
            }

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3, // 30mm not pixel
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            if ($this->lang == 'zh') {
                $customFontPath = resource_path('fonts/Noto_Serif_SC');
                $mpdfConfig['fontDir'] = array_merge($mpdfConfig['fontDir'] ?? [], [
                    $customFontPath,
                ]);

                $mpdfConfig['fontdata'] = [
                    'notoserifsc' => [
                        'R' => 'NotoSerifSC-Regular.ttf',
                    ],
                ];
                $mpdfConfig['default_font'] = 'notoserifsc';
            }

            switch ($data->status_sampling) {
                case 'S24':
                    $sampling = strtoupper(__('Jadwal.status_sampling.S24'));
                    break;
                case 'SD':
                    $sampling = strtoupper(__('Jadwal.status_sampling.SD'));
                    break;
                default:
                    $sampling = strtoupper(__('Jadwal.status_sampling.S'));
                    break;
            }

            $pdf = new PDF($mpdfConfig);
            // if(isset($request->protect) && $request->protect != null)$pdf->SetProtection(array(), $request->protect, $request->protect);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            // $pdf->SetWatermarkText('CONFIDENTIAL');
            // $pdf->showWatermarkText = true;
            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => __('Jadwal.footer.center_content', ['page' => '{PAGENO}', 'total_pages' => '{nbpg}']),
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => __('Jadwal.footer.right_content'),
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
            $order = OrderHeader::where('no_document', $data->no_document)->where('is_active', true)->first();

            $ord = '';
            if (!is_null($order)) {
                $ord = '<span style="font-size:11px; font-weight: bold; border: 1px solid gray;" id="status_sampling">' . $order->no_order . '</span>';
            }

            $fileName = preg_replace('/\\//', '-', 'JADWAL-SAMPLING-' . $data->no_document) . '.pdf';
            $detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $this->quotation_id)
                ->orderBy('periode_kontrak', 'asc')
                ->get();
            $period = [];
            foreach ($detail as $key => $val) {
                if ($key == 0) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        array_push($period, $this->tanggal_indonesia($v_->periode_kontrak, 'period'));
                        continue;
                    }
                } else if ($key == (count($detail) - 1)) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        array_push($period, $this->tanggal_indonesia($v_->periode_kontrak, 'period'));
                        continue;
                    }
                }
            }

            if (explode(" ", $period[0])[1] == explode(" ", $period[(count($period) - 1)])[1]) {
                $period = explode(" ", $period[0])[0] . ' - ' . $period[(count($period) - 1)];
            } else {
                $period = $period[0] . ' - ' . $period[(count($period) - 1)];
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
                                    <u>' . strtoupper(__('Jadwal.header.quotation')) . '</u>
                                </b>
                            </h5>
                            <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
                            <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $period . ' </p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;">
                                <b>PT INTI SURYA LABORATORIUM</b>
                                <br>
                                <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->cabang->alamat_cabang . '</span>
                                <br>
                                <span>T : ' . $data->cabang->tlp_cabang . ' - sales@intilab.com</span>
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
                            <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">' . strtoupper(__('Jadwal.header.contract')) . '</span>
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
                                <u>' . __('Jadwal.header.office') . ' :</u>
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
                                <u>' . __('Jadwal.header.sampling') . ' :</u>
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

            $pdf->writeHTML(
                ' <table class="table table-bordered" style="border:1px solid black; width:100%; padding:5px; ">
                    <tr>
                        <td colspan="7" style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.title') . '</td>
                    </tr>
                    <tr>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.no') . '</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.category') . '</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.date.start') . '</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.date.end') . '</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.time.start') . '</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.time.end') . '</td>
                        <td style="text-align:center;padding:5px; border:1px solid black;">' . __('Jadwal.table.header.duration') . '</td>
                    </tr>'
            );

            foreach ($datajadwalPlan as $key => $value) {
                $durasiText = '';
                $tanggalSelesai = null;

                switch ($value['durasi']) {
                    case 0:
                        $durasiText = __('Jadwal.duration.0');
                        $tanggalSelesai = $value['tanggal'];
                        break;
                    case 1:
                        $durasiText = __('Jadwal.duration.1');
                        $tanggalSelesai = $value['tanggal'];
                        break;
                    case 2:
                        $durasiText = __('Jadwal.duration.2');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(1)->format('Y-m-d');
                        break;
                    case 3:
                        $durasiText = __('Jadwal.duration.3');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(2)->format('Y-m-d');
                        break;
                    case 4:
                        $durasiText = __('Jadwal.duration.4');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(3)->format('Y-m-d');
                        break;
                    case 5:
                        $durasiText = __('Jadwal.duration.5');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(4)->format('Y-m-d');
                        break;
                    case 6:
                        $durasiText = __('Jadwal.duration.6');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(5)->format('Y-m-d');
                        break;
                    case 7:
                        $durasiText = __('Jadwal.duration.7');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(6)->format('Y-m-d');
                        break;
                    case 8:
                        $durasiText = __('Jadwal.duration.8');
                        $tanggalSelesai = Carbon::parse($value['tanggal'])->addDays(7)->format('Y-m-d');
                        break;
                    default:
                        $durasiText = __('Jadwal.duration.9');
                        $tanggalSelesai = 'Tidak Ada';
                        break;
                }

                $jadwalTable = json_decode($value['kategori']);
                $pdf->writeHtml('<tr><td style="text-align:center;padding:3px; border:1px solid black;">' . ($key + 1) . '</td><td style="text-align:left;padding:3px; border:1px solid black;">');

                // Buka tabel dalam kolom
                $pdf->writeHtml('<ul style="text-align:left;padding:3px;">');
                if ($jadwalTable != null) {
                    foreach ($jadwalTable as $subKey => $subValue) {
                        // Tambahkan baris untuk setiap item dalam jadwalTable
                        $pdf->writeHtml('<li style="font-size:10px;">' . $subValue . '</li>');
                    }
                }

                // Tutup tabel dalam kolom
                $pdf->writeHtml('</ul></td>');

                // Tambahkan kolom lainnya
                $pdf->writeHtml(
                    ' <td style="text-align:center;padding:3px; border:1px solid black;">' . $value['tanggal'] . '</td>
                    <td style="text-align:center;padding:3px; border:1px solid black;">' . $tanggalSelesai . '</td>
                    <td style="text-align:center;padding:3px; border:1px solid black;">' . $value['jam_mulai'] . '</td>
                    <td style="text-align:center;padding:3px; border:1px solid black;">' . $value['jam_selesai'] . '</td>
                    <td style="text-align:center;padding:3px; border:1px solid black;">' . $durasiText . '</td></tr>'
                );
            }
            $pdf->writeHtml('</table>');

            $pdf->AddPage();

            $renderPdf = new RenderKontrak();
            return $renderPdf->renderBody($pdf, $data, $fileName, $detail, $this->lang);
        } catch (Exception $e) {
            throw new Exception('Message : ' . $e->getMessage() . ' Line : ' . $e->getLine() . ' File : ' . $e->getFile(), 401);
        }
    }
}
