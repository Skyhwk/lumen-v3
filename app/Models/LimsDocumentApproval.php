<?php

namespace App\Models;

class LimsDocumentApproval extends Sector
{
    protected $table = 'lims_document_approvals';
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(LimsDocument::class, 'lims_document_id');
    }
}
