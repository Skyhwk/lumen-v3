<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiayaOperasional extends Model
{
    protected $table = 'biaya_operasional';
    public $timestamps = false;

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(BiayaOperasionalItem::class, 'bo_id');
    }

    public function receipts()
    {
        return $this->hasMany(BiayaOperasionalReceipt::class, 'bo_id');
    }

    public function employee()
    {
        return $this->belongsTo(MasterKaryawan::class, 'created_by', 'nama_lengkap');
    }
}
