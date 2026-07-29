<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiayaOperasionalItem extends Model
{
    protected $table = 'biaya_operasional_items';
    public $timestamps = false;

    protected $guarded = [];

    public function bo()
    {
        return $this->belongsTo(BiayaOperasional::class, 'bo_id');
    }
}
