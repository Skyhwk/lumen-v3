<?php 
namespace App\Models\Lims;
use Illuminate\Database\Eloquent\Model;
use App\Models\Lims\Parameter;

class AnalystFormula extends Model
{
    
    protected $connection = 'lims';
protected $table = "analyst_formula";
    public $timestamps = false;

    public function templete_analyst()
    {
        return $this->belongsTo(TemplateAnalyst::class,'id_function', 'id');
    }

    public function param()
    {
        return $this->belongsTo(Parameter::class,'id_parameter', 'id');
    }
}
