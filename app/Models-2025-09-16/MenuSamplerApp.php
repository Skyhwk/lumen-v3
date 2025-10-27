<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MenuSamplerApp extends Sector
{
    protected $table = 'menu_sampler_app';

    protected $guarded = [];

    public $timestamps = false;  // Set to true if you have created_at and updated_at columns

    public function parent()
    {
        return $this->belongsTo(MenuSamplerApp::class, 'parent_id');
    }

    // Relasi orangtua ke anak
    public function children()
    {
        return $this->hasMany(MenuSamplerApp::class, 'parent_id');
    }
}
