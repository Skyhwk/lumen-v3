<?php

namespace App\Models;

class LimsDocument extends Sector
{
    protected $table = 'lims_documents';
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'extra_data' => 'array',
        'is_active' => 'boolean',
        'disahkan_pada' => 'date',
        'tanggal_cetak' => 'date',
        'tanggal_pengesahan' => 'date',
    ];

    public function approvals()
    {
        return $this->hasMany(LimsDocumentApproval::class, 'lims_document_id')
            ->where('is_active', true)
            ->orderBy('step')
            ->orderBy('approved_at');
    }
}
