<?php 
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Parameter;

class AutomaticApprove extends Model
{
    protected $table = "automatic_approve";
    public $timestamps = false;
    protected $guarded = [];


    public function sample()
    {
        return $this->belongsTo(MasterKategori::class,'id_kategori', 'id');
    }
}
