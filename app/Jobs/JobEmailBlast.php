<?php

namespace App\Jobs;

use App\Services\{SendEmail};
use App\Models\{JobTask};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class JobEmailBlast extends Job
{
    protected $emailto;
    protected $subject;
    protected $content;
    protected $replyto;
    public function __construct($emailto = null, $subject = null, $replyto = null, $content = null)
    {
        $this->emailto = $emailto;
        $this->subject = $subject;
        $this->content = $content;
        $this->replyto = $replyto;
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    public function handle()
    {
        try {
            //code...
            Log::channel('custom_email_log')->info("Running JobEmailBlast to send email to " . $this->emailto . " with subject " . $this->subject . " and replyto " . json_encode($this->replyto));
            SendEmail::where('to', $this->emailto)
            ->where('subject', $this->subject)
            ->where('body',$this->content)
            ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
            //->where('replyto',['m.promo@intilab.com'])
            ->where('replyto',$this->replyto)
            ->fromPromoSales()
            ->send();
            Log::channel('custom_email_log')->info("JobEmailBlast job completed successfully");
        } catch (\Exception $e) {
            Log::channel('custom_email_log')->error(
                "JobEmailBlast job failed: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}");
        }
    }
}
