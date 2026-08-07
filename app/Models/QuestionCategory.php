<?php

namespace App\Models;

class QuestionCategory extends Sector
{
    protected $table = 'question_categories';
    protected $guarded = [];

    public function questions()
    {
        return $this->hasMany(Question::class, 'question_category_id');
    }
}
