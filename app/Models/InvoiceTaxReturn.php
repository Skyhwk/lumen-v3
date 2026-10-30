<?php

namespace App\Models;

class InvoiceTaxReturn extends Sector
{
    protected $table = 'invoice_tax_returns';
    protected $guarded = ['id'];
    protected $casts = ['invoice_snapshot' => 'array', 'meta_history' => 'array', 'amount' => 'decimal:2', 'dpp' => 'decimal:2', 'ppn' => 'decimal:2'];
}
