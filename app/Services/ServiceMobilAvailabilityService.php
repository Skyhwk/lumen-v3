<?php

namespace App\Services;

use App\Models\PenggunaanMobilDetail;
use App\Models\ServiceMobil;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Satu sumber logika: mobil tidak tersedia karena service aktif atau sudah dipakai (penggunaan mobil).
 */
class ServiceMobilAvailabilityService
{
    public const ACTIVE_SERVICE_STATUSES = [
        ServiceMobil::STATUS_DIJADWALKAN,
        ServiceMobil::STATUS_DALAM_PERBAIKAN,
    ];

    public function blockStart(ServiceMobil $service): ?string
    {
        $raw = $service->tanggal_mulai_aktual ?: $service->tanggal_mulai_rencana;

        return $raw ? Carbon::parse($raw)->toDateString() : null;
    }

    public function blockEnd(ServiceMobil $service): ?string
    {
        $raw = $service->tanggal_selesai_aktual ?: $service->tanggal_selesai_estimasi;

        return $raw ? Carbon::parse($raw)->toDateString() : null;
    }

    /**
     * Overlap periode [rangeStart, rangeEnd] dengan service aktif (dijadwalkan / dalam perbaikan).
     */
    public function findOverlappingActiveServices(
        int $mobilId,
        string $rangeStart,
        string $rangeEnd,
        ?int $excludeServiceId = null
    ): Collection {
        return $this->overlappingActiveServices($rangeStart, $rangeEnd)
            ->where('daftar_mobil_id', $mobilId)
            ->when($excludeServiceId, function ($q) use ($excludeServiceId) {
                $q->where('id', '!=', $excludeServiceId);
            })
            ->orderByDesc('id')
            ->get();
    }

    public function busyMobilIdsOnDate(string $tanggal): array
    {
        return $this->overlappingActiveServices($tanggal, $tanggal)
            ->distinct()
            ->pluck('daftar_mobil_id')
            ->all();
    }

    private function overlappingActiveServices(string $rangeStart, string $rangeEnd): Builder
    {
        $start = Carbon::parse($rangeStart)->toDateString();
        $end = Carbon::parse($rangeEnd)->toDateString();

        return ServiceMobil::where('is_active', true)
            ->whereIn('status', self::ACTIVE_SERVICE_STATUSES)
            ->whereRaw('COALESCE(tanggal_mulai_aktual, tanggal_mulai_rencana) <= ?', [$end])
            ->whereRaw('COALESCE(tanggal_selesai_aktual, tanggal_selesai_estimasi) >= ?', [$start]);
    }

    public function findFirstOverlappingActiveService(
        int $mobilId,
        string $rangeStart,
        string $rangeEnd,
        ?int $excludeServiceId = null
    ): ?ServiceMobil {
        return $this->findOverlappingActiveServices($mobilId, $rangeStart, $rangeEnd, $excludeServiceId)->first();
    }

    /**
     * Mobil yang punya service aktif (tanpa cek tanggal) — untuk dropdown jadwal baru.
     */
    public function busyMobilIdsForActiveService(?int $excludeServiceId = null): array
    {
        return ServiceMobil::where('is_active', true)
            ->whereIn('status', self::ACTIVE_SERVICE_STATUSES)
            ->when($excludeServiceId, function ($q) use ($excludeServiceId) {
                $q->where('id', '!=', $excludeServiceId);
            })
            ->pluck('daftar_mobil_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Konflik jadwal service untuk rentang tanggal pemakaian (satu baris per service).
     */
    public function serviceConflictsForUsageDates(int $mobilId, array $dates): Collection
    {
        if ($dates === []) {
            return collect();
        }

        $sorted = $dates;
        sort($sorted);
        $rangeStart = $sorted[0];
        $rangeEnd = $sorted[count($sorted) - 1];

        return $this->findOverlappingActiveServices($mobilId, $rangeStart, $rangeEnd)
            ->map(function (ServiceMobil $service) {
                return [
                    'jenis' => 'service',
                    'service_id' => $service->id,
                    'status' => $service->status,
                    'plat_mobil' => $service->plat_mobil,
                    'periode_mulai' => $this->blockStart($service),
                    'periode_selesai' => $this->blockEnd($service),
                    'tanggal_label' => $this->formatDateRangeLabel(
                        $this->blockStart($service),
                        $this->blockEnd($service)
                    ),
                ];
            });
    }

    /**
     * Konflik penggunaan mobil untuk periode jadwal service baru.
     */
    public function penggunaanConflictsForServiceRange(int $mobilId, string $rangeStart, string $rangeEnd): Collection
    {
        $start = Carbon::parse($rangeStart)->toDateString();
        $end = Carbon::parse($rangeEnd)->toDateString();

        return PenggunaanMobilDetail::with(['header.requester'])
            ->where('mobil_id', $mobilId)
            ->where('is_active', true)
            ->whereBetween('tanggal_penggunaan', [$start, $end])
            ->whereHas('header', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('tanggal_penggunaan')
            ->get()
            ->map(function ($item) {
                return [
                    'jenis' => 'penggunaan',
                    'tanggal_penggunaan' => $item->tanggal_penggunaan,
                    'tanggal_label' => $this->formatDateLabel($item->tanggal_penggunaan),
                    'requester' => optional(optional($item->header)->requester)->nama_lengkap,
                    'keterangan' => optional($item->header)->keterangan,
                ];
            });
    }

    public function buildServiceConflictMessage(string $mobilLabel, Collection $serviceConflicts): string
    {
        if ($serviceConflicts->isEmpty()) {
            return '';
        }

        $parts = $serviceConflicts->map(function ($row) {
            $status = $row['status'] ?? 'service';
            $id = $row['service_id'] ?? '?';

            return "service #{$id} ({$status}, {$row['tanggal_label']})";
        })->values()->all();

        $list = $this->joinIndoList($parts);

        return "Mobil {$mobilLabel} sedang atau akan diservice ({$list}). Tidak bisa dipakai pada tanggal tersebut. Ubah tanggal atau pilih mobil lain.";
    }

    public function buildPenggunaanConflictMessage(string $mobilLabel, Collection $penggunaanConflicts): string
    {
        if ($penggunaanConflicts->isEmpty()) {
            return '';
        }

        $dates = $penggunaanConflicts->pluck('tanggal_penggunaan')->unique()->values()->all();
        $dateText = $this->joinIndoList(array_map(fn ($d) => $this->formatDateLabel($d), $dates));
        $requesters = $penggunaanConflicts->pluck('requester')->filter()->unique()->values();
        $who = $requesters->count() === 1 ? ' oleh ' . $requesters->first() : '';

        return "Mobil {$mobilLabel} sudah dipakai{$who} pada {$dateText}. Tidak bisa dijadwalkan service pada periode yang bertumpuk.";
    }

    public function formatDateLabel(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $parsed = Carbon::parse($date);

        return $parsed->format('j') . ' ' . $months[(int) $parsed->format('n')] . ' ' . $parsed->format('Y');
    }

    public function formatDateRangeLabel(?string $start, ?string $end): string
    {
        if (!$start && !$end) {
            return '-';
        }

        if ($start === $end || !$end) {
            return $this->formatDateLabel($start);
        }

        return $this->formatDateLabel($start) . ' s/d ' . $this->formatDateLabel($end);
    }

    protected function joinIndoList(array $items): string
    {
        $items = array_values(array_filter($items, fn ($item) => $item !== null && $item !== ''));

        if (count($items) === 0) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        if (count($items) === 2) {
            return $items[0] . ' dan ' . $items[1];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ', dan ' . $last;
    }
}
