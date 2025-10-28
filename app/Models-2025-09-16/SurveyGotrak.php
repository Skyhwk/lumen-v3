<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SurveyGotrak extends Sector
{
    protected $table = 'survey_gotrak';
     protected $fillable = [
        'no_order',
        'no_sampel',
        'informasi_perusahaan',
        'informasi_awal',
        'gotrak',
        'cedera',
        'created_at'
    ];

    public $timestamps = false;
}