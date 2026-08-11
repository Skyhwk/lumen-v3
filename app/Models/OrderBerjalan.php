<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderBerjalan extends Model
{
    protected $table = 'order_berjalan';
    
    protected $guarded = ['id'];
    
    // Opsional: otomatis mengkonversi JSON ke array
    protected $casts = [
        'dataOrderDetail' => 'array',
        'is_revisi' => 'boolean',
        'status_selesai' => 'boolean',
    ];
}
