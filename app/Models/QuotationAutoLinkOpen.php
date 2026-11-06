<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationAutoLinkOpen extends Model
{
    protected $table = 'quotation_auto_link_opens';

    protected $fillable = [
        'quotation_id',
        'no_document',
        'promo_id',
        'kode_promo',
        'nama_perusahaan',
        'nama_pic_order',
        'email_pic_order',
        'no_telpon_pic_order',
        'sales_id',
        'first_opened_at',
        'last_opened_at',
        'open_count',
        'last_ip_address',
        'last_user_agent',
    ];

    protected $casts = [
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'open_count' => 'integer',
    ];
}
