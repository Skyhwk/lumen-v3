<?php

namespace App\Jobs;

use App\Services\SendEmail ;

class JobMailling extends Job
{
    protected $to;
    protected $subject;
    protected $content;
    protected $karyawan;
    protected $attachments;
    public function __construct(array $to, string $subject, string $content, string $karyawan, array $attachments = [])
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->content = $content;
        $this->karyawan = $karyawan;
        $this->attachments = $attachments;
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    public function handle()
    {
        $to = $this->to;
        $subject = $this->subject;
        $content = $this->content;
        $karyawan = $this->karyawan;
        $attachments = $this->attachments;

        foreach ($to as $email) {
            if (strpos($email, '@') === false) {
                continue;
            }
            $email = SendEmail::where('to', $email)
            ->where('subject', $subject)
            ->where('body', $content)
            ->where('attachment', $attachments)
            ->where('karyawan', $karyawan)
            ->fromPromoSales()
            // ->noReply()
            ->send();
        }
    }
}
