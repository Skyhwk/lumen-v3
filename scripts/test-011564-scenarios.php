<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

use App\Models\PersiapanSampelHeader;
use App\Services\SamplerTrackingService;
use Illuminate\Support\Facades\DB;

$qt = 'ISL/QT/26-IX/011564R1';
$service = app(SamplerTrackingService::class);

function eventSummary(int $sessionId): array
{
    $rows = DB::select("
        SELECT m.sampler_name, COUNT(e.id) AS cnt
        FROM sampler_tracking_members m
        LEFT JOIN sampler_tracking_events e ON e.sampler_tracking_member_id = m.id
        WHERE m.sampler_tracking_session_id = ?
        GROUP BY m.id, m.sampler_name, m.is_active
        ORDER BY m.id
    ", [$sessionId]);

    return array_map(fn ($r) => "{$r->sampler_name} (events={$r->cnt})", $rows);
}

function assertTrue(bool $cond, string $msg): void
{
    echo ($cond ? 'OK  ' : 'FAIL') . " $msg\n";
    if (!$cond) {
        exit(1);
    }
}

echo "=== 1) Reattach misplaced evidence (sync quotation) ===\n";
$service->syncQuotation($qt);

$andikOn883 = (int) DB::table('sampler_tracking_events as e')
    ->join('sampler_tracking_members as m', 'm.id', '=', 'e.sampler_tracking_member_id')
    ->where('m.sampler_tracking_session_id', 883)
    ->where('m.sampler_name', 'like', '%Andik%')
    ->count();
$asepOn883 = (int) DB::table('sampler_tracking_events as e')
    ->join('sampler_tracking_members as m', 'm.id', '=', 'e.sampler_tracking_member_id')
    ->where('m.sampler_tracking_session_id', 883)
    ->where('m.sampler_name', 'like', '%Asep Saepudin%')
    ->count();
$andikOn879 = (int) DB::table('sampler_tracking_events as e')
    ->join('sampler_tracking_members as m', 'm.id', '=', 'e.sampler_tracking_member_id')
    ->where('m.sampler_tracking_session_id', 879)
    ->where('m.sampler_name', 'like', '%Andik%')
    ->count();

assertTrue($andikOn883 === 4, "Andik should have 4 events on session 883 (got $andikOn883)");
assertTrue($asepOn883 === 4, "Asep should have 4 events on session 883 (got $asepOn883)");
assertTrue($andikOn879 === 0, "Andik should have 0 events left on session 879 (got $andikOn879)");

echo "883: " . implode(', ', eventSummary(883)) . "\n";
echo "879: " . implode(', ', eventSummary(879)) . "\n";

echo "\n=== 2) Update jadwal Air Limbah (mobil) — hanya session 883 ===\n";
$key883before = DB::table('sampler_tracking_sessions')->where('id', 883)->value('team_key');
$key879before = DB::table('sampler_tracking_sessions')->where('id', 879)->value('team_key');

DB::table('jadwal')->whereIn('id', [176177, 176178])->update(['kendaraan' => 'B 6063 JCT-SCEN2']);
$service->syncByPersiapanHeader(PersiapanSampelHeader::find(100208));

$key883after = DB::table('sampler_tracking_sessions')->where('id', 883)->value('team_key');
$key879after = DB::table('sampler_tracking_sessions')->where('id', 879)->value('team_key');
$veh883 = DB::table('sampler_tracking_sessions')->where('id', 883)->value('kendaraan');

assertTrue($key883after !== $key883before, '883 team_key should change after kendaraan update');
assertTrue($key879after === $key879before, '879 team_key must stay unchanged');
assertTrue($veh883 === 'B 6063 JCT-SCEN2', "883 kendaraan updated (got $veh883)");

DB::table('jadwal')->whereIn('id', [176177, 176178])->update(['kendaraan' => 'B 6063 JCT']);
$service->syncByPersiapanHeader(PersiapanSampelHeader::find(100208));

echo "\n=== 3) Sync persiapan Kebisingan — 879 tetap aktif, kategori kebisingan ===\n";
$service->syncByPersiapanHeader(PersiapanSampelHeader::find(100209));

$s879 = DB::table('sampler_tracking_sessions')->where('id', 879)->first();
assertTrue((int) $s879->is_active === 1, '879 active');
assertTrue(str_contains($s879->kategori, 'Kebisingan'), '879 still kebisingan categories');
assertTrue((int) $s879->parsial === 176178, '879 parsial unchanged');

$events879 = (int) DB::table('sampler_tracking_events')->where('sampler_tracking_session_id', 879)->count();
$auto879 = (int) DB::table('sampler_tracking_events')->where('sampler_tracking_session_id', 879)->where('is_auto', 1)->count();
assertTrue($events879 === 0, "879 should have no events until Kebisingan team records FDL (got $events879)");
assertTrue($auto879 === 0, "879 should have no cross-team auto copies (got $auto879)");

echo "\n=== 4) Dua session aktif 26/09 ===\n";
$active = DB::table('sampler_tracking_sessions')
    ->where('no_quotation', $qt)
    ->where('tanggal_sampling', '2026-09-26')
    ->where('is_active', 1)
    ->pluck('id')
    ->all();
sort($active);
assertTrue($active === [879, 883], 'active sessions 879+883: ' . implode(',', $active));

echo "\nAll scenario checks passed.\n";
