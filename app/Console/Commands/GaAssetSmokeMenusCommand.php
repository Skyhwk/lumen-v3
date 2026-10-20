<?php

namespace App\Console\Commands;

use App\Services\GaAsset\GaAssetDepreciationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GaAssetSmokeMenusCommand extends Command
{
    protected $signature = 'ga-asset:smoke-menus';

    protected $description = 'Smoke test backend endpoints untuk menu Finance → Aset';

    public function handle(GaAssetDepreciationService $depreciation): int
    {
        $checks = [];

        $controllers = [
            'GaAssetImportController' => ['index'],
            'GaAssetRegistryController' => ['summary'],
            'GaAssetLocationController' => ['branches'],
            'GaAssetRoomController' => ['index'],
            'GaAssetMovementController' => ['index'],
            'GaAssetReportController' => ['index', 'quantity', 'totalAkhir', 'rebuild'],
            'GaAssetOpnameController' => ['index'],
            'GaAssetUsefulLifeRuleController' => ['index'],
        ];

        foreach ($controllers as $controller => $methods) {
            $class = 'App\\Http\\Controllers\\api\\' . $controller;
            $exists = class_exists($class);
            foreach ($methods as $method) {
                $ok = $exists && method_exists($class, $method);
                $checks[] = [$controller . '@' . $method, $ok ? 'OK' : 'MISSING'];
            }
        }

        try {
            $report = $depreciation->report((int) date('Y'));
            $checks[] = ['report()', count($report['rows'] ?? []) . ' rows'];
        } catch (\Throwable $e) {
            $checks[] = ['report()', 'FAIL: ' . $e->getMessage()];
        }

        try {
            $byBranch = $depreciation->quantityMatrixByBranch();
            $checks[] = ['quantityMatrixByBranch()', count($byBranch['branches'] ?? []) . ' branches'];
        } catch (\Throwable $e) {
            $checks[] = ['quantityMatrixByBranch()', 'FAIL: ' . $e->getMessage()];
        }

        try {
            $akhir = $depreciation->totalAssetAkhirReport((int) date('Y'));
            $checks[] = ['totalAssetAkhirReport()', $akhir['summary']['total_akhir'] ?? '-'];
        } catch (\Throwable $e) {
            $checks[] = ['totalAssetAkhirReport()', 'FAIL: ' . $e->getMessage()];
        }

        $assetCount = DB::table('ga_assets')->where('is_active', 1)->count();
        $checks[] = ['ga_assets active count', (string) $assetCount];

        $this->table(['Check', 'Result'], $checks);

        $failed = collect($checks)->contains(fn ($row) => str_contains((string) $row[1], 'MISSING') || str_contains((string) $row[1], 'FAIL'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
