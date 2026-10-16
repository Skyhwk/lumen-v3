<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Sector
{
    use SoftDeletes;

    protected $table = 'announcement';

    protected $guarded = ['id'];

    protected $casts = [
        'receiver_ids' => 'array',
        'attachments' => 'array',
    ];

    public $timestamps = false;
}
