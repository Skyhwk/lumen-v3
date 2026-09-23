<?php

namespace App\Http\Controllers\api;

use App\Models\MasterKaryawan;
use App\Services\SamplerTrackingTroubleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu: Sampling → Rekap Sampler Bermasalah.
 *
 * Menampilkan riwayat penugasan sampler yang pernah di-unblock (kolom reopened_at terisi
 * di tabel sampler_tracking_troubles).
 *
 * ALUR DATA (urut eksekusi):
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ 1. index() / rekapSamplerBermasalahDataTable()                             │
 * │    Entry dari frontend (DataTables POST + draw).                             │
 * └───────────────────────────────────┬─────────────────────────────────────────┘
 *                                     ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ 2. loadUnblockedTroubles()                                                   │
 * │    Sumber utama: sampler_tracking_troubles                                   │
 * │    Filter: reopened_at NOT NULL (pernah di-unblock).                          │
 * │    is_clear TIDAK difilter — activity selesai (is_clear=1) tetap tampil.     │
 * │    Tanpa filter tanggal — semua riwayat unblock ditampilkan.                 │
 * └───────────────────────────────────┬─────────────────────────────────────────┘
 *                                     ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ 3. buildRekapRowsFromTroubles()                                              │
 * │    Satu record trouble → satu atau beberapa baris tabel (lihat langkah 4).   │
 * └───────────────────────────────────┬─────────────────────────────────────────┘
 *                                     ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ 4. resolveRekapDisplayRows($trouble) — per record trouble                  │
 * │                                                                              │
 * │    A) enrichTroublePayload() — label alasan/tindakan + lampiran (rekap).     │
 * │                                                                              │
 * │    B) attachTroubleToTrackingRows() [parent] — coba baris “live” seperti     │
 * │       tab Data Blocked di Tracking Sampler (SamplerTrackingService).         │
 * │       Berhasil → nama perusahaan, jam, event, dll. dari sync tracking.       │
 * │                                                                              │
 * │    C) Jika hasil (B) kosong / hanya baris sintetis (perusahaan "-"):         │
 * │       buildRekapRowsFromSessionArchive() — baca sampler_tracking_sessions    │
 * │       + sampler_tracking_members (termasuk session non-aktif).               │
 * │       Ini mengatasi data lama yang sudah tidak muncul di listTrackingRows.     │
 * │                                                                              │
 * │    D) Jika (C) juga tidak ada: buildMinimalRekapRow() — hanya dari trouble   │
 * │       + master karyawan (sampler).                                           │
 * └───────────────────────────────────┬─────────────────────────────────────────┘
 *                                     ▼
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ 5. groupRecapTrackingRows()                                                  │
 * │    Gabung baris: tracking_session_id + durasi (group_key) sama → 1 baris.    │
 * │    Nama sampler digabung (sampler_list). Detail unblock: array troubles[].    │
 * │    Durasi beda → baris terpisah meski session sama.                          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class RekapSamplerBermasalahController extends SamplerTrackingController
{
    public function index(Request $request)
    {
        if (!$this->user_id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($request->has('draw')) {
            return response()->json($this->rekapSamplerBermasalahDataTable($request));
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildRekapRowsFromTroubles(),
        ]);
    }

    /**
     * Payload trouble untuk rekap saja (menambah lampiran; parent tidak menyentuh lampiran).
     */
    protected function troublePayload($trouble, $reopenedByMap = null)
    {
        $troubleObj = parent::troublePayload($trouble, $reopenedByMap);
        $troubleObj['lampiran'] = $this->resolveTroubleLampiran($trouble);

        return $troubleObj;
    }

    protected function resolveTroubleLampiran($trouble)
    {
        $table = SamplerTrackingTroubleService::TABLE;
        if (!Schema::hasColumn($table, 'lampiran')) {
            return [];
        }

        $raw = is_array($trouble) ? ($trouble['lampiran'] ?? null) : ($trouble->lampiran ?? null);
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values($decoded);
            }

            return [$raw];
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array>
     */
    protected function buildRekapRowsFromTroubles()
    {
        $troubles = $this->loadUnblockedTroubles();
        if ($troubles->isEmpty()) {
            return collect();
        }

        $reopenedByMap = $this->mapReopenedByNames($troubles);

        $rows = $troubles->flatMap(function ($trouble) use ($reopenedByMap) {
            return $this->resolveRekapDisplayRows($trouble, $reopenedByMap);
        })->filter(function ($row) {
            return strtolower(trim($row['nama_perusahaan'] ?? '')) !== 'cuti';
        })->values();

        return $this->groupRecapTrackingRows($rows);
    }

    /**
     * Semua riwayat unblock; tidak ada filter tanggal dari request.
     */
    protected function loadUnblockedTroubles()
    {
        return DB::table(SamplerTrackingTroubleService::TABLE)
            ->whereNotNull('reopened_at')
            ->orderBy('reopened_at', 'desc')
            ->get();
    }

    protected function mapReopenedByNames($troubles)
    {
        $reopenedByIds = collect($troubles)->pluck('reopened_by')->filter()->unique()->values();
        if ($reopenedByIds->isEmpty()) {
            return collect();
        }

        return MasterKaryawan::whereIn('id', $reopenedByIds)->pluck('nama_lengkap', 'id');
    }

    /**
     * Satu trouble → baris tampilan (1..N). Lihat docblock class untuk sumber data B/C/D.
     */
    protected function resolveRekapDisplayRows($trouble, $reopenedByMap)
    {
        $sampler = $this->resolveSampler($trouble->sampler_id);
        $troubleObj = $this->troublePayload($trouble, $reopenedByMap);
        $samplerName = $sampler ? $sampler->nama_lengkap : (string) $trouble->sampler_id;

        $fromLiveTracking = $this->attachTroubleToTrackingRows($trouble, $sampler, $troubleObj);

        if ($this->shouldUseSessionArchiveFallback($fromLiveTracking)) {
            $fromArchive = $this->buildRekapRowsFromSessionArchive($trouble, $samplerName, $troubleObj);
            if ($fromArchive->isNotEmpty()) {
                return $fromArchive->map(function ($row) use ($samplerName) {
                    return $this->pinRekapRowToSampler($row, $samplerName);
                });
            }
        }

        if ($fromLiveTracking->isNotEmpty()) {
            return $fromLiveTracking->map(function ($row) use ($samplerName) {
                return $this->pinRekapRowToSampler($row, $samplerName);
            });
        }

        return collect([$this->buildMinimalRekapRow($trouble, $samplerName, $troubleObj)]);
    }

    /**
     * Satu record trouble = satu nama sampler pada baris pra-gabung (digabung di groupRecapTrackingRows).
     */
    protected function pinRekapRowToSampler(array $row, $samplerName)
    {
        $row['sampler'] = $samplerName;
        $row['sampler_list'] = [$samplerName];
        $row['samplers'] = [$samplerName];
        if (!empty($row['trouble']) && is_array($row['trouble'])) {
            $row['trouble']['sampler_name'] = $samplerName;
        }

        return $row;
    }

    /**
     * Parent syntheticTroubleRow hanya punya "-" untuk perusahaan — indikasi tracking live tidak ketemu.
     */
    protected function shouldUseSessionArchiveFallback(Collection $rows)
    {
        if ($rows->isEmpty()) {
            return true;
        }

        return $rows->every(function ($row) {
            $company = trim((string) ($row['nama_perusahaan'] ?? ''));
            return $company === '' || $company === '-';
        });
    }

    /**
     * Fallback: session/member di DB (tidak mensyaratkan is_active=true seperti listTrackingRows).
     */
    protected function buildRekapRowsFromSessionArchive($trouble, $samplerName, $troubleObj)
    {
        $sessionId = (int) ($trouble->tracking_session_id ?? 0);
        if (!$sessionId) {
            return collect();
        }

        $session = DB::table('sampler_tracking_sessions')->where('id', $sessionId)->first();
        if (!$session || strtolower(trim($session->nama_perusahaan ?? '')) === 'cuti') {
            return collect();
        }

        $members = DB::table('sampler_tracking_members')
            ->where('sampler_tracking_session_id', $sessionId)
            ->where('sampler_id', $trouble->sampler_id)
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            return collect([
                $this->composeRekapDisplayRow($trouble, $troubleObj, $samplerName, $session, null, 'archive'),
            ]);
        }

        return $members
            ->groupBy(function ($member) {
                return (string) $this->memberEffectiveDuration($member);
            })
            ->map(function ($group) use ($trouble, $troubleObj, $samplerName, $session) {
                return $this->composeRekapDisplayRow(
                    $trouble,
                    $troubleObj,
                    $samplerName,
                    $session,
                    $group->first(),
                    'archive'
                );
            })
            ->values();
    }

    protected function buildMinimalRekapRow($trouble, $samplerName, $troubleObj)
    {
        $activityDate = Carbon::parse($trouble->activity_date)->toDateString();

        return [
            'row_id' => 'rekap-trouble-' . $trouble->id,
            'tanggal_sampling' => $activityDate,
            'nama_perusahaan' => '-',
            'perusahaan_list' => [],
            'sampler' => $samplerName,
            'sampler_list' => [$samplerName],
            'samplers' => [$samplerName],
            'durasi' => '-',
            'jam' => '- - -',
            'jam_mulai' => null,
            'jam_selesai' => null,
            'no_order' => '-',
            'movement_group' => '-',
            'last_event' => '-',
            'total_event' => 0,
            'tracking_status' => 'overdue',
            'events' => [],
            'sessions' => [],
            'members' => [],
            'trouble_id' => $trouble->id,
            'tracking_session_id' => $trouble->tracking_session_id,
            'trouble' => $troubleObj,
            'rekap_data_source' => 'trouble_only',
        ];
    }

    protected function composeRekapDisplayRow($trouble, $troubleObj, $samplerName, $session, $member, $source)
    {
        $activityDate = Carbon::parse($trouble->activity_date)->toDateString();
        $duration = $member ? $this->memberEffectiveDuration($member) : null;
        $durationLabel = $this->durationLabelForRekap($duration);
        $company = trim((string) ($session->nama_perusahaan ?? '')) ?: '-';
        $noOrder = $session->no_order ?: ($session->no_quotation ?? '-');
        $jamMulai = $session->jam_mulai ?? null;
        $jamSelesai = $session->jam_selesai ?? null;

        $row = [
            'row_id' => 'rekap-archive-' . $trouble->id . '-' . ($duration ?? 'x'),
            'tanggal_sampling' => $activityDate,
            'nama_perusahaan' => $company,
            'perusahaan_list' => $company !== '-' ? [$company] : [],
            'sampler' => $samplerName,
            'sampler_list' => [$samplerName],
            'samplers' => [$samplerName],
            'durasi' => $durationLabel,
            'group_key' => $member ? json_encode([$activityDate, 'duration:' . (string) $duration]) : null,
            'jam' => ($jamMulai ?: '-') . ' - ' . ($jamSelesai ?: '-'),
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'no_order' => $noOrder ?: '-',
            'movement_group' => $member ? ($member->current_movement_group ?? '-') : '-',
            'last_event' => '-',
            'total_event' => 0,
            'tracking_status' => 'overdue',
            'events' => [],
            'sessions' => [$session],
            'members' => $member ? [$member] : [],
            'trouble_id' => $trouble->id,
            'tracking_session_id' => $trouble->tracking_session_id,
            'session_id' => (int) $session->id,
            'trouble' => $troubleObj,
            'rekap_data_source' => $source,
        ];

        return $row;
    }

    protected function memberEffectiveDuration($member)
    {
        foreach (['effective_duration', 'durasi_personal', 'duration', 'durasi'] as $field) {
            if (isset($member->$field) && $member->$field !== null && $member->$field !== '') {
                return max(0, (int) $member->$field);
            }
        }

        return 0;
    }

    /** Label durasi selaras SamplerTrackingService (untuk tampilan rekap). */
    protected function durationLabelForRekap($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        $numberValue = (int) $value;
        if ($numberValue === 0) {
            return 'Sesaat';
        }
        if ($numberValue === 1) {
            return '8 Jam';
        }

        return $numberValue . 'x24 Jam';
    }

    protected function trackingDurationMergePart($row)
    {
        return md5((string) ($row['group_key'] ?? $row['durasi'] ?? ''));
    }

    /**
     * Kunci penggabungan baris rekap (selaras tab Data Blocked): session + durasi.
     */
    protected function trackingRecapGroupKey($row)
    {
        $sessionId = $row['tracking_session_id'] ?? null;
        if ($sessionId) {
            return 'session-' . $sessionId . '|' . $this->trackingDurationMergePart($row);
        }

        return 'trouble-' . ($row['trouble_id'] ?? md5(json_encode($row)));
    }

    protected function groupRecapTrackingRows($rows)
    {
        return collect($rows)
            ->groupBy(function ($row) {
                return $this->trackingRecapGroupKey($row);
            })
            ->map(function ($group) {
                return $this->mergeRekapRowGroup($group);
            })
            ->sortByDesc(function ($row) {
                $troubles = collect($row['troubles'] ?? []);
                if ($troubles->isNotEmpty()) {
                    return $troubles->max('reopened_at') ?? '';
                }

                return $row['trouble']['reopened_at'] ?? '';
            })
            ->values();
    }

    protected function mergeRekapRowGroup(Collection $group)
    {
        $base = $group->first();
        if ($group->count() === 1) {
            $row = $base;
            $row['troubles'] = !empty($row['trouble']) ? [$row['trouble']] : [];
            $row['trouble_ids'] = array_filter([$row['trouble_id'] ?? null]);
            $durationPart = $this->trackingDurationMergePart($row);
            $sessionId = $row['tracking_session_id'] ?? ('t-' . ($row['trouble_id'] ?? 'x'));
            $row['row_id'] = 'rekap-group-' . $sessionId . '-' . $durationPart;

            return $row;
        }

        $samplerNames = $group->flatMap(function ($row) {
            $names = array_merge(
                (array) ($row['sampler_list'] ?? []),
                (array) ($row['samplers'] ?? []),
                array_filter([$row['sampler'] ?? null, $row['trouble']['sampler_name'] ?? null])
            );

            return $names;
        })->map(function ($name) {
            return trim((string) $name);
        })->filter(function ($name) {
            return $name !== '' && $name !== '-';
        })->unique()
            ->values();

        $troubles = $group->map(function ($row) {
            return $row['trouble'] ?? null;
        })->filter()->values()->all();

        $merged = $base;
        $merged['sampler_list'] = $samplerNames->all();
        $merged['samplers'] = $samplerNames->all();
        $merged['sampler'] = $samplerNames->isNotEmpty() ? $samplerNames->implode(', ') : '-';
        $merged['troubles'] = $troubles;
        $merged['trouble_ids'] = $group->pluck('trouble_id')->filter()->unique()->values()->all();
        $merged['trouble'] = $troubles[0] ?? null;
        $merged['trouble_id'] = $merged['trouble_ids'][0] ?? ($merged['trouble_id'] ?? null);

        $durationPart = $this->trackingDurationMergePart($merged);
        $sessionId = $merged['tracking_session_id'] ?? ('t-' . ($merged['trouble_id'] ?? 'x'));
        $merged['row_id'] = 'rekap-group-' . $sessionId . '-' . $durationPart;

        return $merged;
    }

    protected function rekapSamplerBermasalahDataTable(Request $request)
    {
        $rows = $this->buildRekapRowsFromTroubles();
        $recordsTotal = $rows->count();

        $rows = $this->service->filterTrackingRows($rows, $request);
        $recordsFiltered = $rows->count();
        $rows = $this->service->sortTrackingRows($rows, $request)->values();

        $start = (int) ($request->start ?? 0);
        $length = (int) ($request->length ?? 25);
        if ($length > -1) {
            $rows = $rows->slice($start, $length)->values();
        }

        return [
            'draw' => (int) ($request->draw ?? 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ];
    }
}
