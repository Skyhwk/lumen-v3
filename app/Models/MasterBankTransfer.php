<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterBankTransfer extends Model
{
    protected $table = 'master_bank_transfer';

    protected $fillable = [
        'nama_bank',
        'kode_bank',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];
}