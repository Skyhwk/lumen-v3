<?php

namespace App\Jobs;

use App\Services\GenerateDocumentJadwal;
use App\Services\GenerateDocumentSampling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDocumentJadwalJob extends Job
{

    protected string $type;
    protected int $quotationId;
    protected string $karyawan;
<<<<<<< HEAD
    protected int $spId;
=======
    protected bool $email;
>>>>>>> ec4149e67c08f924a6b918cde7324ada8054877d

    public $timeout = 300; // PDF berat
    public $tries = 3;

<<<<<<< HEAD
    public function __construct(string $type, int $quotationId, ?string $karyawan = null, ?int $spId = null)
=======
    public function __construct(string $type, int $quotationId, ?string $karyawan = null, bool $email = false)
>>>>>>> ec4149e67c08f924a6b918cde7324ada8054877d
    {
        $this->type        = $type;
        $this->quotationId = $quotationId;
        $this->karyawan    = $karyawan;
<<<<<<< HEAD
        $this->spId        = $spId;
=======
        $this->email       = $email;
>>>>>>> ec4149e67c08f924a6b918cde7324ada8054877d
    }

    public function handle()
    {
        if ($this->type === 'QT') {
<<<<<<< HEAD
            GenerateDocumentJadwal::onNonKontrak($this->quotationId, $this->spId)
            ->setKaryawan($this->karyawan)->save();
        } else {
            GenerateDocumentJadwal::onKontrak($this->quotationId, $this->spId)
            ->setKaryawan($this->karyawan)->saveKontrak();
=======
            GenerateDocumentJadwal::onNonKontrak($this->quotationId)
            ->setKaryawan($this->karyawan)->setEmail($this->email)->save();
        } else {
            GenerateDocumentJadwal::onKontrak($this->quotationId)
            ->setKaryawan($this->karyawan)->setEmail($this->email)->saveKontrak();
>>>>>>> ec4149e67c08f924a6b918cde7324ada8054877d
                
        }
    }
}
