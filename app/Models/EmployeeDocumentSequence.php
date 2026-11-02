<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocumentSequence extends Model
{
    protected $table = 'employee_document_sequences';

    protected $guarded = ['id'];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
        'padding' => 'integer',
    ];
}
