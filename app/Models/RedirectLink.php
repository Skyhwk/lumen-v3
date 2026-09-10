<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class RedirectLink extends Sector
{
    use SoftDeletes;

    protected $table = 'redirect_links';
    protected $guarded = ['id'];
}
