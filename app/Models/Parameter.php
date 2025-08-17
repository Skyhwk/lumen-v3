<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Parameter extends Sector
{

    protected $table = 'parameter';

    protected $fillable = [
        'nama_lab',
        'nama_regulasi',
        'nama_lhp',
        'status',
        'id_kategori',
        'nama_kategori',
        'satuan',
        'method',
        'nilai_minimum',
        'nilai_ketidak_pastian',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public $timestamps = false;

    public function hargaParameter()
    {
        return $this->belongsTo(HargaParameter::class, 'id', 'id_parameter')
            ->where('is_active', true);
    }
}
