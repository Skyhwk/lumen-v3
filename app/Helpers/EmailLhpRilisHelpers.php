<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use App\Models\EmailHistory;

use App\Services\SendEmail;

class EmailLhpRilisHelpers
{
    public static function run($data)
    {

        try {
            $cfr        = $data['cfr'];
            $no_order   = $data['no_order'];
            $name       = preg_replace('/\b(Ibu|Bapak)\b\s*/i', '', $data['nama_pic_order']);
            $company    = $data['nama_perusahaan'];
            $periode    = $data['periode'];
            if ($periode !== null) {
                $periode = Carbon::createFromFormat('Y-m', $periode)->locale('id')->isoFormat('MMMM Y');
            }
            $karyawan   = $data['karyawan'];

            $prefix = "e-LHP_{$no_order}_";

            $cekHistory = EmailHistory::where('email_subject', 'LIKE', "{$prefix}%")->first();

            if (!$cekHistory) {
                return false;
            }

            $body    = self::body($name, $company, $cfr);
            $footer  = self::footer();
            $fullBody = $body . $footer;

            if($periode != null) $periode = $periode . '_';
            
            $subject = "Update Hasil Uji_{$no_order}_{$periode}{$company}";

            $cc  = $cekHistory->email_cc != null  ? json_decode($cekHistory->email_cc, true)  : [];
            $bcc = $cekHistory->email_bcc != null ? json_decode($cekHistory->email_bcc, true) : [];
            
            $bcc = is_array($bcc) ? $bcc : [];

            $email = SendEmail::where('to', $cekHistory->email_to)
                ->where('subject', $subject)
                ->where('body', $fullBody)
                ->where('cc', $cc)
                ->where('bcc', $bcc)
                ->where('replyto', ['adminlhp@intilab.com'])
                ->where('karyawan', $karyawan)
                ->fromLhp()
                ->send();

            return $email;
        } catch (\Exception $e) {
            Log::error("EmailLhpRilisHelpers ERROR: " . $e->getMessage());
            return false;
        }
    }

    private static function footer()
    {
        return view('TemplateEmail.footerLhp')->render();
    }

    private static function body($name, $company, $cfr)
    {
        return view('TemplateEmail.bodyLhp', [
            'name' => $name,
            'company' => $company,
            'cfr' => $cfr,
        ])->render();
    }

}