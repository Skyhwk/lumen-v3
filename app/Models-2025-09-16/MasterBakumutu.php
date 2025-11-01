<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterBakumutu extends Sector
{

    protected $table = 'master_bakumutu';

    protected $fillable = [
        'id_regulasi',
        'id_parameter',
        'parameter',
        'satuan',
        'method',
        'baku_mutu',
    ];

    public $timestamps = false;

    public function colorimetri() {
        return $this->belongsTo('App\Models\Colorimetri', 'parameter', 'parameter');
    }
    public function titrimetri() {
        return $this->belongsTo('App\Models\Titrimetri', 'parameter', 'parameter');
    }
    public function gravimetri() {
        return $this->belongsTo('App\Models\Gravimetri', 'parameter', 'parameter');
    }
    public function subkontrak() {
        return $this->belongsTo('App\Models\Subkontrak', 'parameter', 'parameter');
    }
}
