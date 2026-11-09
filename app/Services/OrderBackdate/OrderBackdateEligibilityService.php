<?php

namespace App\Services\OrderBackdate;

use Illuminate\Support\Facades\DB;

/**
 * Gate backdate: order harus ada di order_berjalan dan semua LHP sudah rilis (snapshot tracking).
 */
class OrderBackdateEligibilityService
{
    /**
     * @return array{
     *   eligible: bool,
     *   code: string,
     *   message: string,
     *   jumlah_lhp: int,
     *   jumlah_lhp_selesai: int,
     *   progress: string,
     *   persentase: float,
     *   status_selesai: bool,
     *   pending_lhp: array<int, array<string, mixed>>
     * }
     */
    public function assess(string $noOrder): array
    {
        $noOrder = trim($noOrder);
        $empty = [
            'eligible' => false,
            'code' => 'INVALID',
            'message' => 'No order tidak valid',
            'jumlah_lhp' => 0,
            'jumlah_lhp_selesai' => 0,
            'progress' => '0/0',
            'persentase' => 0.0,
            'status_selesai' => false,
            'pending_lhp' => [],
        ];

        if ($noOrder === '') {
            return $empty;
        }

        $row = DB::table('order_berjalan')->where('no_order', $noOrder)->first();
        if (!$row) {
            return array_merge($empty, [
                'code' => 'NOT_IN_ORDER_BERJALAN',
                'message' => 'Order tidak ditemukan di order_berjalan. Pastikan order aktif dan data tracking sudah disinkronkan.',
            ]);
        }

        $details = json_decode($row->dataOrderDetail ?? '[]', true);
        if (!is_array($details) || $details === []) {
            return array_merge($empty, [
                'code' => 'NO_LHP_DATA',
                'message' => 'Data progres LHP pada order_berjalan kosong.',
            ]);
        }

        $totalLhp = 0;
        $selesaiLhp = 0;
        $pending = [];

        foreach ($details as $periodeBlock) {
            if (!is_array($periodeBlock)) {
                continue;
            }

            $totalLhp += (int) ($periodeBlock['jumlah_lhp'] ?? 0);
            $selesaiLhp += (int) ($periodeBlock['jumlah_lhp_selesai'] ?? 0);

            $cfrRows = $periodeBlock['detail'] ?? [];
            if (!is_array($cfrRows)) {
                continue;
            }

            foreach ($cfrRows as $cfr) {
                if (!is_array($cfr)) {
                    continue;
                }
                if (!empty($cfr['lhp_rilis'])) {
                    continue;
                }

                $sampel = $cfr['sampelNumbers'] ?? [];
                $pending[] = [
                    'periode' => $periodeBlock['periode'] ?? null,
                    'cfr' => $cfr['cfr'] ?? null,
                    'kategori_3' => $cfr['kategori_3'] ?? null,
                    'no_sampel' => is_array($sampel) ? implode(', ', $sampel) : $sampel,
                ];
            }
        }

        $statusSelesai = (int) ($row->status_selesai ?? 0) === 1
            || filter_var($row->status_selesai, FILTER_VALIDATE_BOOLEAN);

        $persentase = $totalLhp > 0 ? round(($selesaiLhp / $totalLhp) * 100, 2) : 0.0;
        $progress = $totalLhp > 0 ? ($selesaiLhp . '/' . $totalLhp) : '0/0';

        $countsComplete = $totalLhp > 0 && $selesaiLhp >= $totalLhp && $pending === [];
        $eligible = $countsComplete && $statusSelesai;

        if (!$eligible) {
            $message = $totalLhp === 0
                ? 'Belum ada data LHP untuk order ini di order_berjalan.'
                : 'LHP belum direlease semua (progres ' . $progress . '). Selesaikan release semua LHP terlebih dahulu.';

            if ($pending !== []) {
                $message .= ' Pending: ' . count($pending) . ' CFR.';
            }

            return [
                'eligible' => false,
                'code' => $totalLhp === 0 ? 'NO_LHP' : 'LHP_NOT_FULLY_RELEASED',
                'message' => $message,
                'jumlah_lhp' => $totalLhp,
                'jumlah_lhp_selesai' => $selesaiLhp,
                'progress' => $progress,
                'persentase' => $persentase,
                'status_selesai' => $statusSelesai,
                'pending_lhp' => array_slice($pending, 0, 20),
            ];
        }

        return [
            'eligible' => true,
            'code' => 'OK',
            'message' => 'Order memenuhi syarat backdate (semua LHP sudah release).',
            'jumlah_lhp' => $totalLhp,
            'jumlah_lhp_selesai' => $selesaiLhp,
            'progress' => $progress,
            'persentase' => $persentase,
            'status_selesai' => true,
            'pending_lhp' => [],
        ];
    }

    public function assertEligible(string $noOrder): array
    {
        $result = $this->assess($noOrder);
        if (!$result['eligible']) {
            throw new OrderBackdateNotEligibleException($result['message'], $result);
        }

        return $result;
    }
}
