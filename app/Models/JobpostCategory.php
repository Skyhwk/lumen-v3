<?php

namespace App\Models;

class JobpostCategory extends Sector
{
    protected $table = 'jobpost_categories';

    protected $fillable = [
        'name',
        'assigned_user_ids',
        'division_ids',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'assigned_user_ids' => 'array',
        'division_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function personnelRequests()
    {
        return $this->hasMany(PersonnelRequest::class, 'divisi_alias_id', 'id');
    }
}
