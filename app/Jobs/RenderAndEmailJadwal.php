<?php

namespace App\Jobs;

use App\Services\EmailJadwal;
use Illuminate\Http\Request;

class RenderAndEmailJadwal extends Job
{
    protected $data;
    protected $value;

    public function __construct(object $data, array $value)
    {
        $this->data = $data;
        $this->value = $value;
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    public function handle()
    {
        $email = new EmailJadwal($this->data, $this->value, 'id');
        $email->emailJadwalSampling();
    }
}
