<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Question extends Sector
{
    protected $table = 'questions';
    public $timestamps = false;
    
    protected $fillable = [
        'question_category_id', 'question_type', 'scale_type_id', 'scoring_type',
        'question_text', 'question_image', 'explanation', 'difficulty', 'status', 'is_active', 'created_by', 'created_at', 'updated_at',
    ];

    public function options(){
        return $this->hasMany(QuestionOption::class, 'question_id', 'id');
    }

    public function scaleType()
    {
        return $this->belongsTo(ScaleType::class, 'scale_type_id', 'id');
    }

    public function categoryMaster()
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }

    public function getQuestionImageAttribute($value)
    {
        return is_array($value) ? $value : (json_decode($value ?: '[]', true) ?: []);
    }

    public function setQuestionImageAttribute($value)
    {
        $this->attributes['question_image'] = json_encode(array_values((array) $value));
    }
}
