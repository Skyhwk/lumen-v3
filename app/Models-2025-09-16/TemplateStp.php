<?php 
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterKategori;

class TemplateStp extends Model
{
    protected $table = "template_stp";
    public $timestamps = false;

    public function sample()
    {
        return $this->belongsTo(MasterKategori::class,'category_id', 'id');
    }
}
