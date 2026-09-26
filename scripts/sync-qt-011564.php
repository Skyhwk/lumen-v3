<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

use App\Models\PersiapanSampelHeader;
use App\Services\SamplerTrackingService;
use Illuminate\Support\Facades\DB;

$qt = 'ISL/QT/26-IX/011564R1';
$service = app(SamplerTrackingService::class);

$reflection = new ReflectionClass($service);
$makeTeamKey = $reflection->getMethod('makeTeamKey');
$makeTeamKey->setAccessible(true);

echo "=== Expected team_key per jadwal group ===\n";
$jadwals = DB::table('jadwal')->where('no_quotation', $qt)->where('is_active', 1)->where('tanggal', '2026-09-26')->get();
$groups = [];
foreach ($jadwals as $row) {
    $obj = (object) (array) $row;
    $key = $makeTeamKey->invoke($service, $obj);
    $groups[$key] = ($groups[$key] ?? 0) + 1;
}
foreach ($groups as $key => $cnt) {
    echo "$key ($cnt sampler rows)\n";
}

echo "\n=== syncByPersiapanHeader for each active header on 2026-09-26 ===\n";
$headers = PersiapanSampelHeader::where('no_quotation', $qt)
    ->where('tanggal_sampling', '2026-09-26')
    ->where('is_active', 1)
    ->orderBy('id')
    ->get();

foreach ($headers as $header) {
    echo "PSH id={$header->id} samplers={$header->sampler_jadwal}\n";
    $service->syncByPersiapanHeader($header);
}

echo "\n=== syncQuotation full ===\n";
$service->syncQuotation($qt);

echo "\n=== SESSIONS after sync ===\n";
$s = DB::table('sampler_tracking_sessions')
    ->where('no_quotation', $qt)
    ->orderBy('id')
    ->get(['id', 'tanggal_sampling', 'team_key', 'id_sampling', 'parsial', 'kendaraan', 'driver', 'is_active', 'kategori', 'synced_at']);

foreach ($s as $r) {
    $row = (array) $r;
    $row['kategori'] = substr((string) ($row['kategori'] ?? ''), 0, 60);
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
