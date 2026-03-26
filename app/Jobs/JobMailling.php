<?php

namespace App\Jobs;

use App\Services\SendEmail ;

class JobMailling extends Job
{
    protected $arrayForm;
    protected $to;
    protected $subject;
    protected $content;
    protected $karyawan;
    protected $attachments;
    public function __construct(array $arrayForm, array $to, string $subject, string $content, string $karyawan, array $attachments = [])
    {
        $this->arrayForm = $arrayForm;
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
        $from = $this->arrayForm['from'];
        $alias = $this->arrayForm['alias'];
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
            ->where('karyawan', $karyawan);
            if($from == 'promo'){
                $email->fromPromoSales($alias);
            }elseif($from == 'noreply'){
                $email->noReply($alias);
            }elseif($from == 'sales'){
                $email->fromSales($alias);
            }elseif($from == 'finance'){
                $email->fromFinance($alias);
            }elseif($from == 'tc'){
                $email->fromTc($alias);
            }elseif($from == 'admsales'){
                $email->fromAdmsales($alias);
            }elseif($from == 'lhp'){
                $email->fromLhp($alias);
            }
            $email->send();
        }
    }
}
