<?php

namespace App\Models;

class GaAssetCommandReceipt extends Sector
{
    protected $table = 'ga_asset_command_receipts';

    const UPDATED_AT = null;

    protected $fillable = [
        'scope',
        'idempotency_key',
        'payload_hash',
        'status',
        'result_json',
    ];

    protected $casts = [
        'result_json' => 'array',
    ];
}
