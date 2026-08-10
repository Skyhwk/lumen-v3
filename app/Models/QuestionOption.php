<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class QuestionOption extends Sector
{
    protected $table = 'question_options';
    protected $fillable = ['question_id', 'option_text', 'option_image', 'is_correct', 'option_order', 'created_at', 'updated_at'];
    public $timestamps = false;

    public function question(){
        return $this->belongsTo(Question::class, 'question_id', 'id');
    }

    public function getOptionImageAttribute($value)
    {
        return $value;
    }
}
