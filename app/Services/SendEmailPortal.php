<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;

use App\Models\CustomerAccount;
use Carbon\Carbon;
use Repository;

class SendEmailPortal
{
    private $timestamp;
    private $email;
    private $body;
    private $subject;
    private $data;
    private static $instance;


    public function __construct()
    {
        $this->timestamp = Carbon::now()->format('Y-m-d H:i:s');
    }

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'email':
                self::$instance->email = $value;
                break;
            case 'body':
                self::$instance->body = $value;
                break;
            case 'subject':
                self::$instance->subject = $value;
                break;
            case 'data':
                self::$instance->data = $value;
                break;
        }
    }

    public static function registerAccount()
    {
        $data = CustomerAccount::where('email', self::$instance->email)->first();
        $key = 'skyhwk12';
        $btn_data = $data->id . "|" . $data->email . "|Email Verification|" . env('PUBLIC_TOKEN');
        $btn_encrypted = self::encrypt($btn_data, $key);
        $btn_link = env('CUSTOMER_V3') . "/emailVerification/" . $btn_encrypted;

        $body = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background-color: #f4f4f4;
                            margin: 0;
                            padding: 0;
                        }
                        .container {
                            width: 100%;
                            max-width: 600px;
                            margin: 20px auto;
                            background: #ffffff;
                            padding: 20px;
                            text-align: center;
                            border-radius: 8px;
                            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                        }
                        .button {
                            display: inline-block;
                            padding: 12px 20px;
                            margin-top: 20px;
                            background-color: #007BFF;
                            color: #ffffff;
                            text-decoration: none;
                            font-size: 16px;
                            border-radius: 5px;
                        }
                        .footer {
                            margin-top: 20px;
                            font-size: 12px;
                            color: #666;
                        }
                        .divider {
                            margin: 30px 0;
                            border-top: 1px solid #ddd;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2>Verify Your Email</h2>
                        <p>Thank you for signing up! Click the button below to verify your email.</p>
                        <a href="' . $btn_link . '" class="button">Verify Email</a>
                        <p class="footer">If you did not sign up, please ignore this email.</p>
                        
                        <div class="divider"></div>
                        
                        <h2>Verifikasi Email Anda</h2>
                        <p>Terima kasih telah mendaftar! Klik tombol di bawah ini untuk memverifikasi email Anda.</p>
                        <a href="' . $btn_link . '" class="button">Verifikasi Email</a>
                        <p class="footer">Jika Anda tidak mendaftar, abaikan email ini.</p>
                    </div>
                </body>
                </html>';

        self::$instance->body = $body;
        self::$instance->subject = 'Verify Your Email Address';
    }

    public static function forgotPassword()
    {
        $data = CustomerAccount::where('email', self::$instance->email)->first();
        $key = 'skyhwk12';
        $btn_data = $data->id . "|" . $data->email . "|Forgot Password|" . env('PUBLIC_TOKEN');
        $btn_encrypted = self::encrypt($btn_data, $key);
        $btn_link = env('CUSTOMER_V3') . "/forgotPassword/" . $btn_encrypted;

        $body = '<!DOCTYPE html>
        <html>`
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reset Password | Atur Ulang Kata Sandi</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    width: 100%;
                    max-width: 600px;
                    margin: 20px auto;
                    background: #ffffff;
                    padding: 20px;
                    text-align: center;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                }
                .button {
                    display: inline-block;
                    padding: 12px 20px;
                    margin-top: 20px;
                    background-color: #FF5733;
                    color: #ffffff;
                    text-decoration: none;
                    font-size: 16px;
                    border-radius: 5px;
                }
                .footer {
                    margin-top: 20px;
                    font-size: 12px;
                    color: #666;
                }
                .divider {
                    margin: 30px 0;
                    border-top: 1px solid #ddd;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Reset Your Password</h2>
                <p>We received a request to reset your password. Click the button below to set a new password.</p>
                <a href="' . $btn_link . '" class="button">Reset Password</a>
                <p class="footer">If you did not request a password reset, please ignore this email.</p>
                
                <div class="divider"></div>
                
                <h2>Atur Ulang Kata Sandi Anda</h2>
                <p>Kami menerima permintaan untuk mengatur ulang kata sandi Anda. Klik tombol di bawah ini untuk mengatur kata sandi baru.</p>
                <a href="' . $btn_link . '" class="button">Atur Ulang Kata Sandi</a>
                <p class="footer">Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.</p>
            </div>
        </body>
        </html>';


        self::$instance->body = $body;
        self::$instance->subject = 'Forgot Password';
    }

    private static function resetInstance()
    {
        self::$instance = null;
    }

    public static function send()
    {
        $email = SendEmail::where('to', 'afryan@intilab.com')
            ->where('subject', self::$instance->subject)
            ->where('body', self::$instance->body)
            ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
            ->noReply()
            ->send();

        self::resetInstance();
    }

    private function encrypt($string, $key)
    {
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($string, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext, $key, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }
}