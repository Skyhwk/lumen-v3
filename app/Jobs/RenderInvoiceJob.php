<?php

namespace App\Jobs;

use App\Services\RenderInvoice;

class RenderInvoiceJob extends Job
{
    protected $no_invoice;

    public function __construct($no_invoice)
    {
        $this->no_invoice = $no_invoice;
    }

    public function handle()
    {
        if (count($this->no_invoice) > 0) {
            foreach ($this->no_invoice as $item) {
                $render = new RenderInvoice();
                $render->renderInvoice($item);
            }
            return true;
        } else {
            return true;
        }
    }
}
