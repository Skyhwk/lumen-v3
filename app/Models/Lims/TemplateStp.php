<?php 
namespace App\Models\Lims;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterKategori;

class TemplateStp extends Model
{
    protected $connection = 'lims';

    protected $table = "template_stp";
    public $timestamps = false;

    public function sample()
    {
        return $this->belongsTo(MasterKategori::class,'category_id', 'id');
    }
}
