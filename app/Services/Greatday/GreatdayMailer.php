<?php

namespace App\Services\Greatday;

use App\Services\SendEmail;

class GreatdayMailer
{
    public static function sendForgotPassword(string $to, array $viewData): void
    {
        $body = view('emails.greatday.forgotPassword', $viewData)->render();

        SendEmail::where('to', $to)
            ->where('subject', 'Forgot Password - Attendance')
            ->where('body', $body)
            ->noReply()
            ->send();
    }

    public static function sendRegistration(string $to, array $viewData): void
    {
        $body = view('emails.greatday.registration', $viewData)->render();

        SendEmail::where('to', $to)
            ->where('subject', 'Verifikasi Akun - Attendance')
            ->where('body', $body)
            ->noReply()
            ->send();
    }
}
