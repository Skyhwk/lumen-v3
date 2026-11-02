<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\SamplerTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SamplerTrackingJourneyBackfill
{
    public function run(int $sessionId, bool $dryRun = false): array
    {
        if (!SamplerTrackingHistory::available()) {
            throw new \RuntimeException('Kolom meta_history belum tersedia.');
        }
        return DB::transaction(function () use ($sessionId, $dryRun) {
            $query = SamplerTrackingSession::where('id', $sessionId);
            if (!$dryRun) $query->lockForUpdate();
            $session = $query->first();
            if (!$session) throw new \RuntimeException('Session tidak ditemukan: ' . $sessionId);

            $history = json_decode($session->meta_history ?: '[]', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($history) || array_values($history) !== $history) {
                throw new \RuntimeException('meta_history session bukan daftar yang valid.');
            }
            $known = [];
            foreach ($history as $entry) {
                if (isset($entry['backfill_key'])) $known[$entry['backfill_key']] = true;
            }
            $source = $this->sourceSchedules($session);
            $members = $session->members()->orderBy('id')->get();
            $related = $session->id_sampling
                ? SamplerTrackingSession::where('id_sampling', $session->id_sampling)
                    ->whereDate('tanggal_sampling', $session->tanggal_sampling)->orderBy('id')->get()
                : collect([$session]);
            $eventRows = DB::table('sampler_tracking_events as e')
                ->join('sampler_tracking_members as m', 'm.id', '=', 'e.sampler_tracking_member_id')
                ->whereIn('e.sampler_tracking_session_id', $related->pluck('id')->all())
                ->whereIn('m.sampler_id', $members->pluck('sampler_id')->filter()->all())
                ->orderBy('e.event_at')->orderBy('e.id')->get([
                    'e.id', 'e.sampler_tracking_session_id', 'e.sampler_tracking_member_id',
                    'e.triggered_by_member_id', 'e.event_type', 'e.event_at', 'e.is_auto', 'e.note',
                    'e.created_at', 'e.updated_at', 'm.sampler_id', 'm.sampler_name',
                ])->map(function ($row) { return (array) $row; })->all();
            $evidence = [
                'source_schedules' => $source,
                'tracking_members' => $members->map(function ($member) {
                    return $member->only(['id', 'sampler_id', 'sampler_name', 'duration', 'durasi_personal',
                        'effective_duration', 'is_active', 'created_at', 'updated_at']);
                })->all(),
                'related_sessions' => $related->map(function ($item) {
                    return $item->only(['id', 'id_sampling', 'no_order', 'no_quotation', 'tanggal_sampling',
                        'jam_mulai', 'jam_selesai', 'durasi', 'is_active', 'created_at', 'updated_at']);
                })->all(),
                'events' => $eventRows,
            ];
            $base = [
                'backfilled' => true, 'reconstruction' => true,
                'recorded_at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
            ];
            $changes = $this->scheduleChanges($session, $source);
            $entries = [];
            foreach ($changes as $change) {
                $key = 'journey:v1:change:' . $sessionId . ':' . $this->digest($change);
                if (!isset($known[$key])) $entries[] = array_merge($base, $change, ['backfill_key' => $key]);
            }
            $contextKey = 'journey:v1:evidence:' . $sessionId . ':' . $this->digest($evidence);
            if (!isset($known[$contextKey])) {
                $entries[] = array_merge($base, [
                    'type' => 'journey_evidence_imported', 'backfill_key' => $contextKey,
                    'evidence' => $evidence,
                    'summary' => count($source) . ' baris jadwal, ' . $members->count() . ' anggota tracking, '
                        . count($eventRows) . ' event tersimpan, ' . count($changes) . ' indikasi perubahan.',
                    'limitation' => 'Konteks session lain hanya bukti terkait, bukan otomatis session pengganti. '
                        . 'Record yang dihapus atau nilai yang ditimpa tanpa audit tidak dapat dipulihkan. '
                        . 'Jadwal pada tanggal lain dan urutan perjalanan lama tidak ditebak.',
                ]);
            }
            $added = (new SamplerTrackingHistoryBackfill())->runSession($sessionId, $dryRun);
            if (!$dryRun) {
                foreach ($entries as $entry) {
                    $type = $entry['type'];
                    unset($entry['type']);
                    SamplerTrackingHistory::append($sessionId, $type, $entry);
                }
            }
            return [
                'session_id' => $sessionId, 'company' => $session->nama_perusahaan,
                'date' => $session->tanggal_sampling, 'entries_added' => $added + count($entries),
                'source_rows' => count($source), 'related_sessions' => $related->pluck('id')->all(),
                'events' => count($eventRows), 'changes' => $changes, 'evidence' => $evidence,
            ];
        });
    }

    private function sourceSchedules(SamplerTrackingSession $session): array
    {
        // A company-name match alone is not evidence of the same assignment.
        if (!$session->id_sampling || !$session->tanggal_sampling) return [];
        $root = preg_replace('/R\d+$/', '', (string) $session->no_quotation);
        return Jadwal::where('id_sampling', $session->id_sampling)
            ->whereDate('tanggal', $session->tanggal_sampling)->orderBy('id')->get()
            ->filter(function ($row) use ($root) {
                return $root !== '' && preg_replace('/R\d+$/', '', (string) $row->no_quotation) === $root;
            })->map(function ($row) {
                return $row->only(['id', 'id_sampling', 'no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai',
                    'nama_perusahaan', 'sampler', 'userid', 'durasi', 'durasi_personal', 'kategori',
                    'is_active', 'created_at', 'updated_at', 'created_by', 'updated_by']);
            })->values()->all();
    }

    private function scheduleChanges(SamplerTrackingSession $session, array $source): array
    {
        $rows = array_values(array_filter($source, function ($row) use ($session) {
            return (string) $row['jam_mulai'] === (string) $session->jam_mulai
                && (string) $row['jam_selesai'] === (string) $session->jam_selesai
                && $this->categories($row['kategori']) === $this->categories($session->kategori);
        }));
        // Consecutive IDs and matching update metadata are candidate row batches,
        // not a replacement for an audit trail. Every generated change is marked inferred.
        $batches = [];
        foreach ($rows as $row) {
            $last = count($batches) - 1;
            $previous = $last >= 0 ? end($batches[$last]) : null;
            if (!$previous || (int) $row['id'] !== (int) $previous['id'] + 1
                || $row['updated_at'] !== $previous['updated_at']
                || $row['updated_by'] !== $previous['updated_by']
                || $row['no_quotation'] !== $previous['no_quotation']
                || (bool) $row['is_active'] !== (bool) $previous['is_active']) {
                $batches[] = [$row];
            } else {
                $batches[$last][] = $row;
            }
        }
        $changes = [];
        for ($i = 1; $i < count($batches); $i++) {
            $old = $batches[$i - 1]; $new = $batches[$i];
            $oldIds = array_column($old, 'userid'); $newIds = array_column($new, 'userid');
            // Never invent a transition between unrelated teams or duplicate samplers.
            if (array_filter($old, function ($row) { return (bool) $row['is_active']; })
                || count(array_filter($oldIds)) !== count($oldIds) || count(array_filter($newIds)) !== count($newIds)
                || !array_intersect($oldIds, $newIds) || count(array_unique($oldIds)) !== count($oldIds)
                || count(array_unique($newIds)) !== count($newIds)) continue;
            $before = $this->state($old); $after = $this->state($new);
            if ($before === $after) continue;
            $changes[] = [
                'type' => 'schedule_change_reconstructed', 'confidence' => 'inferred',
                'before' => $before, 'after' => $after,
                'source_before_ids' => array_column($old, 'id'), 'source_after_ids' => array_column($new, 'id'),
                'indicated_at' => $old[0]['updated_at'], 'actor' => $old[0]['updated_by'],
                'added_members' => array_values(array_diff($newIds, $oldIds)),
                'removed_members' => array_values(array_diff($oldIds, $newIds)),
                'limitation' => 'Indikasi dari kelompok baris jadwal lama dan pengganti yang memiliki konteks sama. '
                    . 'Waktu dan pelaku mengikuti metadata update baris lama; bukan audit pasti waktu perubahan. '
                    . 'Nilai durasi yang tersimpan tidak membuktikan durasi persis saat setiap event terjadi.',
            ];
        }
        return $changes;
    }

    private function state(array $rows): array
    {
        $first = $rows[0];
        $members = array_map(function ($row) {
            return ['sampler_id' => $row['userid'], 'name' => $row['sampler'],
                'effective_duration' => (string) ($row['durasi_personal'] ?? $row['durasi'])];
        }, $rows);
        usort($members, function ($a, $b) { return strcmp((string) $a['sampler_id'], (string) $b['sampler_id']); });
        return ['company' => $first['nama_perusahaan'], 'quotation' => $first['no_quotation'],
            'date' => $first['tanggal'], 'start' => $first['jam_mulai'], 'end' => $first['jam_selesai'],
            'duration' => (string) $first['durasi'], 'members' => $members];
    }

    private function categories($value): array
    {
        $items = is_array($value) ? $value : json_decode($value ?: '[]', true);
        if (!is_array($items)) return [(string) $value];
        sort($items);
        return $items;
    }

    private function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
