<?php

namespace App\Jobs;

use App\Services\PrintLhp;

class JobPrintLhp extends Job
{
    protected $no_sampel;
    protected $mode;

    public function __construct($no_sampel , $mode = null)
    {
        $this->no_sampel = $no_sampel;
        $this->mode = $mode;
    }

    public function handle()
    { 
        // if($this->mode != null) {
        //     if($this->mode == "draftPsikologi") {
        //         $render = new PrintLhp();
        //         $render->printPsikologi($this->no_sampel);
        //         return true;
        //     }
        // } else  {
            $render = new PrintLhp();
            $render->print($this->no_sampel);
            return true;
        // }
    }
}
