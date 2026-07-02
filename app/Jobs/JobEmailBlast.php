<?php

namespace App\Jobs;

use App\Services\SendEmail;
use Illuminate\Support\Facades\Log;

class JobEmailBlast extends Job
{
    protected $emailto;
    protected $subject;
    protected $content;
    protected $replyto;
    protected $emailFrom;

    public function __construct($emailto = null, $subject = null, $replyto = null, $content = null, $emailFrom = 'fromPromoSales')
    {
        $this->emailto = $emailto;
        $this->subject = $subject;
        $this->content = $content;
        $this->replyto = $replyto;
        $this->emailFrom = in_array($emailFrom, SendEmail::allowedFromKeys(), true)
            ? $emailFrom
            : 'fromPromoSales';
    }

    public function handle()
    {
        try {
            Log::channel('custom_email_log')->info(
                'Running JobEmailBlast to send email to ' . $this->emailto
                . ' with subject ' . $this->subject
                . ', replyto ' . json_encode($this->replyto)
                . ', from ' . $this->emailFrom
            );

            SendEmail::where('to', $this->emailto)
                ->where('subject', $this->subject)
                ->where('body', $this->content)
                ->where('karyawan', SendEmail::resolveKaryawanFromKey($this->emailFrom))
                ->where('replyto', $this->replyto)
                ->applyFromKey($this->emailFrom)
                ->send();

            Log::channel('custom_email_log')->info('JobEmailBlast job completed successfully');
        } catch (\Exception $e) {
            Log::channel('custom_email_log')->error(
                "JobEmailBlast job failed: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}"
            );
        }
    }
}
