<?php 
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\AnalystFormula;

class TemplateAnalyst extends Model
{
    protected $table = "template_analyst";
    public $timestamps = false;

    public function formula()
    {
        return $this->hasMany(AnalystFormula::class,'id_function', 'id');
    }
}
