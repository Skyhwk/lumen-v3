<?php

/**
 * Preview / dry-run backfill purchasing approval tanpa menyimpan perubahan.
 *
 * Usage:
 *   php database/scripts/run_purchase_approval_backfill.php --dry-run
 *   php database/scripts/run_purchase_approval_backfill.php --execute
 */

require __DIR__ . '/../../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(dirname(__DIR__, 2)))->bootstrap();

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

require_once __DIR__ . '/../migrations/2026_06_25_180000_backfill_purchase_request_purchasing_approval.php';

$execute = in_array('--execute', $argv ?? [], true);
$dryRun = !$execute;

if ($dryRun) {
    putenv('BACKFILL_DRY_RUN=true');
    echo "Mode: DRY RUN (preview only)\n";
    echo "Jalankan dengan --execute untuk menerapkan perubahan.\n\n";
} else {
    putenv('BACKFILL_DRY_RUN=false');
    echo "Mode: EXECUTE — perubahan akan disimpan ke database.\n\n";
}

$migration = new BackfillPurchaseRequestPurchasingApproval();
$migration->up();
