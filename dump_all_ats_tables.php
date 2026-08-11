<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$atsTables = [
    'personnel_requests',
    'new_recruitment',
    'recruitment_interviews',
    'salary_offers',
    'candidate_profiles',
    'candidate_educations',
    'candidate_work_experiences',
    'candidate_documents',
    'candidate_medical_informations',
];

foreach ($atsTables as $table) {
    if (!Schema::hasTable($table)) {
        echo "=== TABLE NOT FOUND IN DB: {$table} ===\n\n";
        continue;
    }

    echo "=== TABLE: {$table} ===\n";
    $columns = DB::select("SHOW FULL COLUMNS FROM `{$table}`");
    foreach ($columns as $col) {
        $null    = $col->Null === 'YES' ? 'nullable' : 'NOT NULL';
        $default = $col->Default !== null ? "DEFAULT={$col->Default}" : '';
        $extra   = $col->Extra ? "EXTRA={$col->Extra}" : '';
        echo sprintf("  %-35s %-25s %-10s %-20s %s\n", $col->Field, $col->Type, $null, $default, $extra);
    }
    echo "\n";
}
