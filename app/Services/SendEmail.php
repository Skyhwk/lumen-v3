<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\EmailHistory;
use Carbon\Carbon;
use Repository;

class SendEmail
{
    private $to;
    private $cc = [];
    private $bcc = [];
    private $replyto = [];
    private $subject;
    private $body;
    private $attachments = [];
    private $fromType = 'noreply';
    private $alias = null;
    private $karyawan;
    private static $instance;

    /*
     * Cara pemanggilan SendEmail
     * $email = SendEmail::where('to', 'email@example.com')
     * ->where('subject', 'Subject')
     * ->where('body', 'Body')
     * ->where('attachments', ['attachment1.pdf', 'attachment2.pdf'])
     * ->noReply()
     * ->send();
     */

    private $emailConfig = [
        'noreply' => [
            'email' => null,
            'password' => null,
            'name' => 'INTILAB - No Reply'
        ],
        'sales' => [
            'email' => null,
            'password' => null,
            'name' => 'Sales Team'
        ],
        'admsales' => [
            'email' => null,
            'password' => null,
            'name' => 'Admin Sales'
        ],
        'finance' => [
            'email' => null,
            'password' => null,
            'name' => 'Finance Team'
        ],
        'tc' => [
            'email' => null,
            'password' => null,
            'name' => 'Technical Team'
        ],
        'promo' => [
            'email' => null,
            'password' => null,
            'name' => 'Promo Intilab'
        ],
        'lhp' => [
            'email' => null,
            'password' => null,
            'name' => 'E-LHP'
        ]
    ];

    private function __construct()
    {
        $this->emailConfig['noreply']['email'] = env('MAIL_NOREPLY_USERNAME');
        $this->emailConfig['noreply']['password'] = env('MAIL_NOREPLY_PASSWORD');
        $this->emailConfig['admsales']['email'] = env('MAIL_ADMSALES_USERNAME');
        $this->emailConfig['admsales']['password'] = env('MAIL_ADMSALES_PASSWORD');
        $this->emailConfig['sales']['email'] = env('MAIL_SALES_USERNAME');
        $this->emailConfig['sales']['password'] = env('MAIL_SALES_PASSWORD');
        $this->emailConfig['finance']['email'] = env('MAIL_FINANCE_USERNAME');
        $this->emailConfig['finance']['password'] = env('MAIL_FINANCE_PASSWORD');
        $this->emailConfig['tc']['email'] = env('MAIL_TC_USERNAME');
        $this->emailConfig['tc']['password'] = env('MAIL_TC_PASSWORD');
        $this->emailConfig['promo']['email'] = env('MAIL_PROMO_USERNAME');
        $this->emailConfig['promo']['password'] = env('MAIL_PROMO_PASSWORD');
        $this->emailConfig['lhp']['email'] = env('MAIL_LHP_USERNAME');
        $this->emailConfig['lhp']['password'] = env('MAIL_LHP_PASSWORD');
    }

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'to':
                if (empty($value)) {
                    throw new \Exception('To email is required');
                }
                self::$instance->to = $value;
                break;
            case 'cc':
                self::$instance->cc = $value;
                break;
            case 'bcc':
                self::$instance->bcc = $value;
                break;
            case 'replyto':
                self::$instance->replyto = $value;
                break;
            case 'subject':
                if (empty($value)) {
                    throw new \Exception('Subject is required');
                }
                self::$instance->subject = $value;
                break;
            case 'body':
                if (empty($value)) {
                    throw new \Exception('Body is required');
                }
                self::$instance->body = $value;
                break;
            case 'attachment':
                self::$instance->attachments = $value;
                break;
            case 'karyawan':
                if (empty($value)) {
                    throw new \Exception('Karyawan is required');
                }
                self::$instance->karyawan = $value;
                break;
        }

        return self::$instance;
    }

    public function noReply($alias = null)
    {
        $this->fromType = 'noreply';
        $this->alias = $alias ?? $this->emailConfig['noreply']['name'];
        return $this;
    }

    public function fromSales($alias = null)
    {
        $this->fromType = 'sales';
        $this->alias = $alias ?? $this->emailConfig['sales']['name'];
        return $this;
    }

    public function fromFinance($alias = null)
    {
        $this->fromType = 'finance';
        $this->alias = $alias ?? $this->emailConfig['finance']['name'];
        return $this;
    }

    public function fromTc($alias = null)
    {
        $this->fromType = 'tc';
        $this->alias = $alias ?? $this->emailConfig['tc']['name'];
        return $this;
    }

    public function fromAdmsales($alias = null)
    {
        $this->fromType = 'admsales';
        $this->alias = $alias ?? $this->emailConfig['admsales']['name'];
        return $this;
    }
    public function fromPromoSales($alias = null)
    {
        $this->fromType = 'promo';
        $this->alias = $alias ?? $this->emailConfig['promo']['name'];
        return $this;
    }
    public function fromLhp($alias = null)
    {
        $this->fromType = 'lhp';
        $this->alias = $alias ?? $this->emailConfig['lhp']['name'];
        return $this;
    }

    public function send()
    {
        try {
            $ArrayBcc= [];
            $cekValidasi = env('REVERSE_BCC', false);

            if($cekValidasi) {
                $ArrayBcc       = $this->bcc;
                // Reset value sebelum di-overwrite
                $this->bcc      = [];
                $this->cc       = [];
                $this->replyto  = [];
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Host = env('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = $this->emailConfig[$this->fromType]['email'];
            $mail->Password = $this->emailConfig[$this->fromType]['password'];
            $mail->SMTPSecure = env('MAIL_ENCRYPTION');
            $mail->Port = env('MAIL_PORT');

            $mail->setFrom($this->emailConfig[$this->fromType]['email'], $this->alias ?? $this->emailConfig[$this->fromType]['name']);
            $emailto = trim(preg_replace('/\s+/u', '', $this->to));
            
            $mail->addAddress($emailto);

            if (!empty($this->cc)) {
                foreach ($this->cc as $cc) {
                    $trimCc = preg_replace('/\s+/', '', $cc);
                    $mail->addCC($trimCc);
                }
            }

            if (!empty($this->bcc)) {
                foreach ($this->bcc as $bcc) {
                    $trimBcc = preg_replace('/\s+/', '', $bcc);
                    $mail->addBCC($trimBcc);
                }
            }

            if (!empty($this->replyto)) {
                foreach ($this->replyto as $email) {
                    if ($email == 'admsales01@intilab.com') {
                        $mail->addReplyTo($email, 'Admin Sales');
                    }elseif ($email == 'adminlhp@intilab.com') {
                        $mail->addReplyTo($email, 'Admin LHP');
                    } else {
                        $trimTo = preg_replace('/\s+/', '', $email);
                        $mail->addReplyTo($trimTo);
                    }
                }
            }

            if (!empty($this->attachments)) {
                foreach ($this->attachments as $attachment) {
                    if(isset($attachment['path'])) {
                        $fullPath = public_path($attachment['path']);
                        if (!file_exists($fullPath)) {
                            \Log::error("Attachment not found: " . $fullPath);
                            continue;
                        }

                        $mail->addAttachment($fullPath, $attachment['name']);
                    } else {
                        $mail->addAttachment($attachment);
                    }
                }
            }

            $mail->isHTML(true);
            $mail->Subject = mb_encode_mimeheader($this->subject, 'UTF-8', 'B');
            if ($this->fromType == 'promo') {
                $body = self::replaceBase64WithUrl($this->body);
                $mail->Body = $body;
                $mail->Body .= self::footer($this->to);
            } else {
                $body = $this->body;
                $mail->Body = $body;
            }
            
            $mail->send();

            $uuid = (int) str_replace('.', '', microtime(true));

            Repository::dir('email_history')->key($uuid)->save($body);

            EmailHistory::insert([
                'email_to' => $this->to,
                'email_from' => $this->emailConfig[$this->fromType]['email'],
                'email_subject' => $this->subject,
                'email_body' => $uuid . '.txt',
                'email_cc' => !empty($this->cc) ? json_encode($this->cc) : null,
                'email_bcc' => !empty($this->bcc) ? json_encode($this->bcc) : null,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            if (!empty($ArrayBcc) && $cekValidasi) {

                foreach ($ArrayBcc as $bccEmail) {
                    $clone = clone $this;
            
                    $clone->to = $bccEmail;
                    $clone->bcc = [];
                    $clone->cc = [];
                    $clone->replyto = [];
                    
                    // kirim ulang
                    $clone->send();
                }
            }

            // Reset instance setelah pengiriman email berhasil
            self::resetInstance();

            return true;
        } catch (Exception $e) {
            // Reset instance juga ketika terjadi error
            self::resetInstance();
            throw new \Exception($mail->ErrorInfo);
        }
    }

    /**
     * Reset instance untuk menghindari data terbawa ke penggunaan berikutnya
     */
    private static function resetInstance()
    {
        self::$instance = null;
    }

    private static function replaceBase64WithUrl($htmlContent, $basePath = 'https://apps.intilab.com/v3/public/mailling/attachment/')
    {
        $pattern = '/<img[^>]*src=["\']data:image\/[^;]+;base64,([^"\']+)["\'][^>]*(?:data-filename|data-file-name)=["\']([^"\']+)["\'][^>]*>/';

        $result = preg_replace_callback($pattern, function ($matches) use ($basePath) {
            $base64 = $matches[1]; // Data base64
            $filename = str_replace(' ', '_', $matches[2]); // Nama file yang akan digunakan

            // Tentukan path file di server
            $filePath = '/var/www/html/v3/public/mailling/attachment/' . $filename;

            // Pastikan direktori tujuan ada
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true); // Membuat direktori jika belum ada
            }

            // Simpan file gambar dari data base64
            file_put_contents($filePath, base64_decode($base64));

            $imgTag = '<img src="' . $basePath . $filename . '"';

            // Ambil semua atribut dari tag img asli
            preg_match_all('/\s+([a-zA-Z0-9-]+)="([^"]+)"/', $matches[0], $attributes);
            if (isset($attributes[1]) && isset($attributes[2])) {
                foreach ($attributes[1] as $index => $attr) {
                    // Jangan duplikasi atribut src dan data-filename/data-file-name
                    if ($attr != 'src' && $attr != 'data-filename' && $attr != 'data-file-name') {
                        $imgTag .= ' ' . $attr . '="' . $attributes[2][$index] . '"';
                    }
                }
            }

            // Pastikan atribut penting seperti alt, data-rotate, dll tetap ada
            if (strpos($imgTag, 'alt=') === false) {
                $imgTag .= ' alt=""';
            }

            // Tambahkan atribut lain yang mungkin diperlukan
            foreach (['data-rotate', 'data-proportion', 'data-align', 'data-size', 'data-percentage', 'data-origin', 'origin-size'] as $attr) {
                if (strpos($imgTag, $attr . '=') === false && preg_match('/' . $attr . '="([^"]+)"/', $matches[0], $attrMatch)) {
                    $imgTag .= ' ' . $attr . '="' . $attrMatch[1] . '"';
                }
            }

            $imgTag .= ' />';
            return $imgTag;
        }, $htmlContent);

        return $result;
    }

    private static function footer($to)
    {
        $style = '<style>
            p {
                margin: 0;
                padding: 0;
            }
        </style>';

        $footerBody = '<div style="font-size: 12px; color: #6c757d; text-align: center; margin-top: 20px; line-height: 1.5;">
                            <p>
                                Anda menerima email ini karena Anda terdaftar dalam daftar mailing kami.
                                Jika Anda tidak ingin menerima email lagi dari kami, Anda dapat 
                                <a href="https://portal.intilab.com/public/email/unsubscribe?milis=' . urlencode($to) . '" style="color: #007bff; text-decoration: none;">Unsubscribe</a>.
                            </p>
                            <p>
                                INTI SURYA LABORATORIUM | Icon Business Park, Jl. Raya Cisauk Lapan Blok O No. 5 - 6, Sampora, Kec. Cisauk, Kabupaten Tangerang, Banten 15345 © 2024 INTI SURYA LABORATORIUM. Semua Hak Dilindungi Undang-Undang.
                            </p>
                        </div>
                    </body>
                </html>';

        return $style . $footerBody;
    }
}