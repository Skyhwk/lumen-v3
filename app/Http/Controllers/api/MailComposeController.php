<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\InternalMailService;
use Illuminate\Http\Request;

class MailComposeController extends Controller
{
    public function send(Request $request)
    {
        try {
            $mail = new InternalMailService((int) $this->user_id, $this->karyawan);
            $payload = $request->all();

            if ($request->hasFile('attachments')) {
                $payload['attachments'] = $request->file('attachments');
            }

            $mail->sendEmail($payload);

            return response()->json(['message' => 'Email berhasil dikirim'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
