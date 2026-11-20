<?php

namespace App\Services;

/**
 * Bangun map konflik kendaraan per tanggal (keyed by plat normalisasi).
 * Tim pemilik = jam_mulai paling awal (lalu created/updated paling awal).
 */
class KendaraanConflictHelper
{
    /**
     * @param  \Illuminate\Support\Collection|array  $rows  jadwal bentrok
     * @return array<string, object>
     */
    public static function buildDetailKonflik($rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $veh = JadwalServices::normalizePlatKey($row->kendaraan ?? '');
            if ($veh === '') {
                continue;
            }
            $grouped[$veh][] = $row;
        }

        $detailKonflik = [];

        foreach ($grouped as $veh => $list) {
            usort($list, function ($a, $b) {
                $jamA = (string) ($a->jam_mulai ?? '');
                $jamB = (string) ($b->jam_mulai ?? '');
                if ($jamA !== $jamB) {
                    return $jamA <=> $jamB;
                }
                $waktuA = !empty($a->created_at) ? strtotime($a->created_at) : strtotime((string) ($a->updated_at ?? ''));
                $waktuB = !empty($b->created_at) ? strtotime($b->created_at) : strtotime((string) ($b->updated_at ?? ''));
                $waktuA = $waktuA ?: PHP_INT_MAX;
                $waktuB = $waktuB ?: PHP_INT_MAX;

                return $waktuA <=> $waktuB;
            });

            $primary = $list[0];
            $names = [];
            foreach ($list as $item) {
                $nama = self::samplerDisplayName($item->sampler ?? '');
                if ($nama !== '' && !in_array($nama, $names, true)) {
                    $names[] = $nama;
                }
            }

            // Attach field agar FE bisa baca tanpa mengubah struktur lama
            $primary->tim_bertugas = $names[0] ?? self::samplerDisplayName($primary->sampler ?? '');
            $primary->tim_bertugas_all = implode(', ', $names);

            $detailKonflik[$veh] = $primary;
        }

        return $detailKonflik;
    }

    protected static function samplerDisplayName($sampler): string
    {
        $raw = trim((string) $sampler);
        if ($raw === '') {
            return '';
        }
        if (strpos($raw, ',') === false) {
            return $raw;
        }
        $parts = explode(',', $raw);
        $names = [];
        foreach ($parts as $idx => $part) {
            if ($idx % 2 === 1) {
                $name = trim($part);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return $names ? implode(', ', $names) : $raw;
    }
}
