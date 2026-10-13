<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportingInfoAnswer extends Model
{
    protected $table = 'supporting_info_answers';

    protected $guarded = [];

    protected $casts = [
        'answer_payload' => 'array',
        'is_active' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
