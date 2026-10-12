<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\HandlesGaAssetMaster;
use App\Models\AssetType;

class AssetTypeController extends Controller
{
    use HandlesGaAssetMaster;

    protected function gaAssetMasterModel(): string
    {
        return AssetType::class;
    }

    protected function gaAssetMasterCodeMaxLength(): int
    {
        return 30;
    }

    protected function gaAssetMasterLabel(): string
    {
        return 'Jenis aset';
    }
}
