<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\DB;

foreach ([879, 883] as $sid) {
    echo "=== SESSION $sid ===\n";
    $s = DB::table('sampler_tracking_sessions')->where('id', $sid)->first();
    if (!$s) {
        echo "not found\n\n";
        continue;
    }
    echo json_encode([
        'kategori' => substr($s->kategori ?? '', 0, 80),
        'kendaraan' => $s->kendaraan,
        'parsial' => $s->parsial,
        'synced_at' => $s->synced_at,
    ], JSON_UNESCAPED_UNICODE) . "\n";

    $members = DB::table('sampler_tracking_members')
        ->where('sampler_tracking_session_id', $sid)
        ->get(['id', 'sampler_id', 'sampler_name', 'is_active']);

    foreach ($members as $m) {
        $evtCount = DB::table('sampler_tracking_events')->where('sampler_tracking_member_id', $m->id)->count();
        echo "  member {$m->id} {$m->sampler_name} (userid {$m->sampler_id}) active={$m->is_active} events=$evtCount\n";
        if ($evtCount > 0 && $evtCount <= 20) {
            $types = DB::table('sampler_tracking_events')
                ->where('sampler_tracking_member_id', $m->id)
                ->orderBy('id')
                ->pluck('event_type');
            echo '    types: ' . $types->implode(', ') . "\n";
        }
    }

    $orphan = DB::table('sampler_tracking_events')
        ->where('sampler_tracking_session_id', $sid)
        ->whereNotIn('sampler_tracking_member_id', $members->pluck('id'))
        ->count();
    echo "  events by session_id (all members): " . DB::table('sampler_tracking_events')->where('sampler_tracking_session_id', $sid)->count() . "\n";
    if ($orphan) {
        echo "  orphan session-level mismatch: $orphan\n";
    }
    echo "\n";
}

echo "=== Cross-check: events on 879 with sampler Andik/Asep names ===\n";
$andikAsep = DB::select("
    SELECT e.id, e.event_type, e.created_at, m.sampler_name, m.sampler_tracking_session_id
    FROM sampler_tracking_events e
    JOIN sampler_tracking_members m ON m.id = e.sampler_tracking_member_id
    WHERE m.sampler_tracking_session_id IN (879, 883)
    AND (m.sampler_name LIKE '%Andik%' OR m.sampler_name LIKE '%Asep Saepudin%')
    ORDER BY e.id
    LIMIT 30
");
foreach ($andikAsep as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
