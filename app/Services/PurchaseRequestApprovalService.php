<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\PurchaseRequest;

class PurchaseRequestApprovalService
{
    private const EXCLUDED_NAMES = [
        'Siti Nur Faidhah',
        'Reiko Nishio Yana Gita Sinaga',
    ];

    private const DIRECTOR_IDS = [1];

    public static function buildApprovalPlan(MasterKaryawan $employee): array
    {
        $managerChain = self::resolveManagerApprovalChain($employee);
        if (!empty($managerChain)) {
            return ['mode' => 'chain', 'chain' => $managerChain];
        }

        // Manager puncak (tidak punya atasan manager lagi) & supervisor → auto-approve
        if (in_array($employee->grade, ['MANAGER', 'SUPERVISOR'], true)) {
            return ['mode' => 'auto', 'chain' => []];
        }

        $supervisor = self::findDirectSupervisor($employee);
        if ($supervisor) {
            return [
                'mode' => 'supervisor',
                'chain' => [[
                    'id' => (int) $supervisor->id,
                    'nama_lengkap' => $supervisor->nama_lengkap,
                    'grade' => $supervisor->grade,
                    'step' => 0,
                ]],
            ];
        }

        $atasanIds = json_decode($employee->atasan_langsung ?? '[]', true) ?? [];
        $fallbackApprovers = MasterKaryawan::whereIn('id', array_map('intval', $atasanIds))
            ->where('is_active', 1)
            ->whereNotIn('id', self::DIRECTOR_IDS)
            ->get()
            ->filter(fn($person) => !self::isExcludedPerson($person))
            ->values();

        if ($fallbackApprovers->isEmpty()) {
            return ['mode' => 'auto', 'chain' => []];
        }

        return [
            'mode' => 'supervisor',
            'chain' => $fallbackApprovers->map(fn($person, $index) => [
                'id' => (int) $person->id,
                'nama_lengkap' => $person->nama_lengkap,
                'grade' => $person->grade,
                'step' => $index,
            ])->values()->all(),
        ];
    }

    /**
     * Bangun rantai approval manager berurutan (manager terdekat → manager puncak).
     * Membaca hierarki dari GetAtasan, berjalan naik via atasan_langsung,
     * berhenti di manager puncak — tidak sampai direktur.
     */
    public static function resolveManagerApprovalChain(MasterKaryawan $employee): array
    {
        $hierarchy = GetAtasan::where('id', $employee->id)->get();
        $hierarchyLookup = $hierarchy
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->flip()
            ->all();

        $chain = [];
        $visited = [];
        $current = $employee;

        for ($depth = 0; $depth < 15; $depth++) {
            $candidates = self::getManagerCandidatesFromAtasan($current, (int) $employee->id, $hierarchyLookup);
            if (empty($candidates)) {
                break;
            }

            $nextManager = self::pickNearestManagerAmongCandidates($candidates);
            if (!$nextManager || in_array((int) $nextManager->id, $visited, true)) {
                break;
            }

            $visited[] = (int) $nextManager->id;
            $chain[] = [
                'id' => (int) $nextManager->id,
                'nama_lengkap' => $nextManager->nama_lengkap,
                'grade' => $nextManager->grade,
                'step' => count($chain),
            ];

            $current = $nextManager;
        }

        return $chain;
    }

    private static function getManagerCandidatesFromAtasan(
        MasterKaryawan $current,
        int $employeeId,
        array $hierarchyLookup
    ): array {
        $atasanIds = json_decode($current->atasan_langsung ?? '[]', true) ?? [];
        $candidates = [];

        foreach (array_map('intval', $atasanIds) as $atasanId) {
            if ($atasanId === $employeeId || self::isDirectorId($atasanId)) {
                continue;
            }

            if (!isset($hierarchyLookup[$atasanId])) {
                continue;
            }

            $person = MasterKaryawan::where('id', $atasanId)->where('is_active', 1)->first();
            if (!$person || self::isExcludedPerson($person) || !self::isManagerGrade($person) || self::isDirector($person)) {
                continue;
            }

            $candidates[] = $person;
        }

        return $candidates;
    }

    /**
     * Dari beberapa manager di level yang sama, pilih manager terdekat (tingkat bawah).
     * Manager terdekat = yang atasan_langsung-nya memuat manager lain di kandidat yang sama.
     * Contoh: staff → {843, 13} → pilih 843 karena 843 melapor ke 13.
     */
    private static function pickNearestManagerAmongCandidates(array $candidates): ?MasterKaryawan
    {
        if (empty($candidates)) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        foreach ($candidates as $candidate) {
            $atasanIds = array_map('intval', json_decode($candidate->atasan_langsung ?? '[]', true) ?? []);

            foreach ($candidates as $other) {
                if ((int) $other->id === (int) $candidate->id) {
                    continue;
                }

                if (in_array((int) $other->id, $atasanIds, true)) {
                    return $candidate;
                }
            }
        }

        return self::pickManagerWithShortestPathToDirector($candidates);
    }

    /**
     * Fallback: pilih manager yang paling jauh dari direktur (paling dekat dengan pengaju).
     */
    private static function pickManagerWithShortestPathToDirector(array $candidates): ?MasterKaryawan
    {
        $scored = [];

        foreach ($candidates as $candidate) {
            $distanceToDirector = self::distanceToDirector($candidate);
            $scored[] = ['person' => $candidate, 'distance' => $distanceToDirector];
        }

        usort($scored, function ($a, $b) {
            if ($a['distance'] === $b['distance']) {
                return (int) $a['person']->id <=> (int) $b['person']->id;
            }

            return $b['distance'] <=> $a['distance'];
        });

        return $scored[0]['person'] ?? $candidates[0];
    }

    private static function distanceToDirector(MasterKaryawan $person): int
    {
        $visited = [];
        $current = $person;

        for ($depth = 0; $depth < 15; $depth++) {
            $atasanIds = array_map('intval', json_decode($current->atasan_langsung ?? '[]', true) ?? []);

            foreach ($atasanIds as $atasanId) {
                if (in_array($atasanId, $visited, true)) {
                    continue;
                }

                if (self::isDirectorId($atasanId)) {
                    return $depth + 1;
                }

                $next = MasterKaryawan::where('id', $atasanId)->where('is_active', 1)->first();
                if (!$next) {
                    continue;
                }

                $visited[] = $atasanId;
                $current = $next;
                continue 2;
            }

            break;
        }

        return 0;
    }

    public static function findDirectSupervisor(MasterKaryawan $employee): ?MasterKaryawan
    {
        $atasanIds = json_decode($employee->atasan_langsung ?? '[]', true) ?? [];
        if (empty($atasanIds)) {
            return null;
        }

        return MasterKaryawan::whereIn('id', array_map('intval', $atasanIds))
            ->where('is_active', 1)
            ->where('grade', 'SUPERVISOR')
            ->whereNotIn('nama_lengkap', self::EXCLUDED_NAMES)
            ->first();
    }

    private static function isManagerGrade(MasterKaryawan $person): bool
    {
        return strtoupper((string) $person->grade) === 'MANAGER';
    }

    private static function isDirectorId(int $id): bool
    {
        return in_array($id, self::DIRECTOR_IDS, true);
    }

    private static function isDirector(MasterKaryawan $person): bool
    {
        if (self::isDirectorId((int) $person->id)) {
            return true;
        }

        $grade = strtoupper((string) $person->grade);

        return in_array($grade, ['DIRECTOR', 'DIREKTUR', 'DIREKTUR UTAMA'], true);
    }

    private static function isExcludedPerson(MasterKaryawan $person): bool
    {
        return in_array($person->nama_lengkap, self::EXCLUDED_NAMES, true);
    }

    public static function parseChain($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public static function parseLog($value): array
    {
        return self::parseChain($value);
    }

    public static function encodeChain(array $chain): ?string
    {
        return empty($chain) ? null : json_encode(array_values($chain));
    }

    public static function encodeLog(array $log): ?string
    {
        return empty($log) ? null : json_encode(array_values($log));
    }

    public static function initializeApprovalState(PurchaseRequest $purchaseRequest, array $plan): void
    {
        $chain = $plan['chain'] ?? [];

        foreach ($chain as $index => &$entry) {
            $entry['step'] = $index;
        }
        unset($entry);

        $purchaseRequest->approval_step = 0;
        $purchaseRequest->approval_chain = self::encodeChain($chain);
        $purchaseRequest->approval_log = null;
        $purchaseRequest->approved_by = null;
        $purchaseRequest->approved_at = null;
    }

    public static function getCurrentApprover(PurchaseRequest $purchaseRequest): ?array
    {
        $chain = self::parseChain($purchaseRequest->approval_chain);
        $step = (int) ($purchaseRequest->approval_step ?? 0);

        return $chain[$step] ?? null;
    }

    public static function canUserApprove($viewer, PurchaseRequest $purchaseRequest): bool
    {
        if (!in_array($purchaseRequest->status, ['Pending', 'Reopened', 'Partially Approved'], true)) {
            return false;
        }

        $chain = self::parseChain($purchaseRequest->approval_chain);

        if (!empty($chain)) {
            $current = self::getCurrentApprover($purchaseRequest);

            return $current && (int) $viewer->id === (int) $current['id'];
        }

        $creator = MasterKaryawan::where('nama_lengkap', $purchaseRequest->created_by)->where('is_active', 1)->first();
        if (!$creator) {
            return false;
        }

        $plan = self::buildApprovalPlan($creator);
        if ($plan['mode'] === 'auto') {
            return false;
        }

        $legacyApproverIds = array_map(
            fn($entry) => (int) $entry['id'],
            $plan['chain'] ?? []
        );

        return in_array((int) $viewer->id, $legacyApproverIds, true);
    }

    public static function formatProgress(PurchaseRequest $purchaseRequest): ?string
    {
        $chain = self::parseChain($purchaseRequest->approval_chain);
        if (empty($chain)) {
            return null;
        }

        $step = (int) ($purchaseRequest->approval_step ?? 0);
        $total = count($chain);
        $current = $chain[$step] ?? null;

        if ($purchaseRequest->status === 'Approved' || $purchaseRequest->finance_status === 'Waiting to Delegate') {
            return "{$total}/{$total}";
        }

        if ($current) {
            return ($step + 1) . "/{$total} — {$current['nama_lengkap']}";
        }

        return "{$step}/{$total}";
    }

    public static function formatDisplayStatus(PurchaseRequest $purchaseRequest): ?string
    {
        if (!in_array($purchaseRequest->status, ['Pending', 'Reopened', 'Partially Approved'], true)) {
            return null;
        }

        $chain = self::parseChain($purchaseRequest->approval_chain);
        if (empty($chain)) {
            return 'Menunggu Persetujuan Atasan';
        }

        $step = (int) ($purchaseRequest->approval_step ?? 0);
        $total = count($chain);
        $current = $chain[$step] ?? null;

        if ($total === 1) {
            return $current
                ? "Menunggu Persetujuan Atasan — {$current['nama_lengkap']}"
                : 'Menunggu Persetujuan Atasan';
        }

        return $current
            ? "Menunggu Persetujuan Atasan (Lapis " . ($step + 1) . "/{$total}) — {$current['nama_lengkap']}"
            : "Menunggu Persetujuan Atasan (Lapis {$step}/{$total})";
    }
}
