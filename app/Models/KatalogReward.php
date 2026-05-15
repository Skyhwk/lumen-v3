<?php
namespace App\Models;

use App\Models\Sector;

class KatalogReward extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'katalog_reward';
    protected $guarded = [];

    protected $casts = [
        'purchase_price' => 'integer',
        'price' => 'integer',
        'sold' => 'integer',
        'variants' => 'array',
        'notes' => 'array',
        'gallery' => 'array',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;
}
