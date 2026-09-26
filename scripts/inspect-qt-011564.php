<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\DB;

$qt = $argv[1] ?? 'ISL/QT/26-IX/011564R1';

echo "=== JADWAL active ($qt) ===\n";
$j = DB::table('jadwal')
    ->where('no_quotation', $qt)
    ->where('is_active', 1)
    ->orderBy('tanggal')
    ->orderBy('id')
    ->get(['id', 'tanggal', 'id_sampling', 'parsial', 'sampler', 'userid', 'kendaraan', 'driver', 'jam_mulai', 'jam_selesai', 'kategori']);

foreach ($j as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== TRACKING SESSIONS (011564*) ===\n";
$s = DB::table('sampler_tracking_sessions')
    ->where(function ($q) {
        $q->where('no_quotation', 'like', '%011564%')
            ->orWhere('no_order', 'ESTX012616');
    })
    ->orderBy('id')
    ->get(['id', 'tanggal_sampling', 'team_key', 'no_quotation', 'no_order', 'id_sampling', 'parsial', 'kendaraan', 'driver', 'kategori', 'is_active', 'status', 'synced_at']);

foreach ($s as $r) {
    $row = (array) $r;
    if (isset($row['kategori']) && strlen((string) $row['kategori']) > 100) {
        $row['kategori'] = substr((string) $row['kategori'], 0, 100) . '...';
    }
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== PERSIAPAN headers ===\n";
$p = DB::table('persiapan_sampel_header')
    ->where('no_quotation', $qt)
    ->orderBy('id', 'desc')
    ->limit(8)
    ->get(['id', 'no_quotation', 'tanggal_sampling', 'sampler_jadwal', 'is_active']);

foreach ($p as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== EVENT counts by session ===\n";
$ids = DB::table('sampler_tracking_sessions')
    ->where('no_quotation', 'like', '%011564%')
    ->pluck('id');
foreach ($ids as $sid) {
    $c = DB::table('sampler_tracking_events')->where('sampler_tracking_session_id', $sid)->count();
    if ($c > 0) {
        echo "session $sid events: $c\n";
    }
}
