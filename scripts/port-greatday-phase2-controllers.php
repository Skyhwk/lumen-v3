<?php

$srcDir = dirname(__DIR__) . '/../Intilab-Internal/app/Http/Controllers/Api';
$srcDir = 'D:/PHP/Intilab-Internal/app/Http/Controllers/Api';
$dstDir = 'D:/PHP/LUMEN-V3/app/Http/Controllers/Greatday';

$files = [
    'LeaveRequestsController.php',
    'PermissionRequestsController.php',
    'OvertimeRequestsController.php',
    'OvertimeReimbursementsController.php',
    'AttendanceCorrectionsController.php',
    'ConsultationRequestsController.php',
    'EventReportsController.php',
    'SupplyRequestsController.php',
];

$masterModels = ['MasterKaryawan', 'MasterDivisi'];

$appModels = [
    'LeaveRequest', 'PermissionRequest', 'SpecialLeaveType', 'AttendanceCorrection',
    'ConsultationRequest', 'EventReport', 'OvertimeRequest', 'OvertimeRequestMember',
    'OvertimeReimbursement', 'Barang', 'RecordPermintaanBarang',
];

foreach ($files as $file) {
    $path = $srcDir . '/' . $file;
    if (!is_readable($path)) {
        fwrite(STDERR, "Missing: $path\n");
        exit(1);
    }

    $c = file_get_contents($path);

    $c = preg_replace('/namespace App\\\\Http\\\\Controllers\\\\Api;/', 'namespace App\\Http\\Controllers\\Greatday;', $c);
    $c = preg_replace('/use App\\\\Http\\\\Controllers\\\\Controller;\s*\n\s*/', '', $c);
    $c = str_replace('App\\Support\\HrdPayroll', 'App\\Support\\Greatday\\HrdPayroll', $c);
    $c = str_replace('App\\Services\\FirebaseService', 'App\\Services\\Greatday\\FirebaseService', $c);
    $c = str_replace('App\\Services\\GetAtasan', 'App\\Services\\Greatday\\GetAtasan', $c);
    $c = str_replace('App\\Services\\GetBawahan', 'App\\Services\\Greatday\\GetBawahan', $c);

    $c = preg_replace_callback(
        '/use App\\\\Models\\\\\{([^}]+)\};/',
        function ($m) use ($masterModels, $appModels) {
            $parts = array_map('trim', explode(',', $m[1]));
            $master = [];
            $app = [];
            foreach ($parts as $p) {
                if (in_array($p, $masterModels, true)) {
                    $master[] = $p;
                } else {
                    $app[] = $p;
                }
            }
            $lines = [];
            if ($app) {
                $lines[] = 'use App\\Models\\Greatday\\{' . implode(', ', $app) . '};';
            }
            if ($master) {
                $lines[] = 'use App\\Models\\{' . implode(', ', $master) . '};';
            }

            return implode("\n", $lines);
        },
        $c
    );

    $c = str_replace('auth()->user()->nama_lengkap', '$this->nama_lengkap', $c);
    $c = str_replace('auth()->user()->id', '$this->user_id', $c);
    $c = str_replace('auth()->user()', '$this->karyawan', $c);

    file_put_contents($dstDir . '/' . $file, $c);
    echo "Wrote $file\n";
}
