<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\HandlesGaAssetMaster;
use App\Models\AssetGroup;

class AssetGroupController extends Controller
{
    use HandlesGaAssetMaster;

    protected function gaAssetMasterModel(): string
    {
        return AssetGroup::class;
    }

    protected function gaAssetMasterCodeMaxLength(): int
    {
        return 24;
    }

    protected function gaAssetMasterLabel(): string
    {
        return 'Kelompok aset';
    }
}
