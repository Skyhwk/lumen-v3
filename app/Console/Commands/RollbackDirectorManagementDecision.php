<?php

namespace App\Console\Commands;

use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kembalikan keputusan Ibu Direktur (approve/reject/keep tahap management_decision) yang terlanjur diklik.
 * Token_approval tidak diubah — link email / Apps-Approval bisa dipakai lagi setelah status kembali menunggu.
 */
class RollbackDirectorManagementDecision extends Command
{
    protected $signature = 'recruitment:rollback-director-management
        {recruitmentId? : ID new_recruitment}
        {--token= : token_approval (alternatif jika ID tidak diketahui)}
        {--dry-run : Tampilkan rencana tanpa menulis DB}';

    protected $description = 'Rollback approve/reject/keep Ibu Direktur (tahap kandidat) ke management_decision';

    private const REVERTIBLE = [
        'management_decision_approved',
        'management_decision_rejected',
        'management_decision_kept',
    ];

    public function handle(): int
    {
        $row = $this->resolveRow();
        if (!$row) {
            return 1;
        }

        $history = RecruitmentStatusService::parseMetaHistory($row);
        if ($history === []) {
            $this->error('meta_history kosong — tidak ada yang di-rollback.');

            return 1;
        }

        $last = $history[count($history) - 1];
        $lastStatus = (string) ($last['status'] ?? '');
        if (!in_array($lastStatus, self::REVERTIBLE, true)) {
            $this->error("Entri history terakhir bukan keputusan direktur kandidat: {$lastStatus}");
            $this->line('Yang bisa di-rollback: ' . implode(', ', self::REVERTIBLE));
            $this->line('Untuk Bapak (approve/reject penawaran gaji): recruitment:rollback-director-salary');

            return 1;
        }

        $newHistory = $history;
        array_pop($newHistory);

        $payload = [
            'status' => 'management_decision',
            'meta_history' => json_encode(array_values($newHistory)),
            'is_keep' => 0,
            'updated_at' => Carbon::now(),
        ];

        if ($lastStatus === 'management_decision_approved') {
            $payload['approved_by'] = null;
            $payload['approved_at'] = null;
        }

        if ($lastStatus === 'management_decision_rejected') {
            $payload['rejected_by'] = null;
            $payload['rejected_at'] = null;
            $payload['rejected_decision'] = 0;
            $payload['rejected_decision_reason'] = null;
        }

        $this->table(
            ['Field', 'Sebelum', 'Sesudah'],
            [
                ['id', (string) $row->id, (string) $row->id],
                ['nama_lengkap', (string) ($row->nama_lengkap ?? '-'), (string) ($row->nama_lengkap ?? '-')],
                ['status', (string) $row->status, 'management_decision'],
                ['history pop', $lastStatus, '(dihapus)'],
                ['token_approval', '(tetap)', substr((string) ($row->token_approval ?? ''), 0, 24) . '…'],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — tidak ada perubahan DB.');

            return 0;
        }

        if (!$this->confirm('Lanjut rollback ke management_decision (menunggu Ibu Direktur)?', true)) {
            $this->info('Dibatalkan.');

            return 0;
        }

        DB::transaction(function () use ($row, $payload) {
            DB::table('new_recruitment')->where('id', $row->id)->update($payload);
            RecruitmentStatusService::syncRejectedSectionFlags((int) $row->id);
        });

        $this->info("Rollback berhasil untuk new_recruitment #{$row->id}. Bapak/Ibu bisa approve lagi via email atau Apps-Approval.");

        return 0;
    }

    private function resolveRow()
    {
        $id = $this->argument('recruitmentId');
        $token = trim((string) $this->option('token'));

        if ($id !== null && $id !== '') {
            $row = DB::table('new_recruitment')->where('id', (int) $id)->first();
            if (!$row) {
                $this->error("Kandidat id {$id} tidak ditemukan.");

                return null;
            }

            return $row;
        }

        if ($token === '') {
            $this->error('Isi recruitmentId atau --token=token_approval');

            return null;
        }

        $row = DB::table('new_recruitment')->where('token_approval', $token)->first();
        if (!$row) {
            $decoded = rawurldecode($token);
            if ($decoded !== $token) {
                $row = DB::table('new_recruitment')->where('token_approval', $decoded)->first();
            }
        }

        if (!$row) {
            $this->error('Kandidat dengan token_approval tersebut tidak ditemukan.');

            return null;
        }

        return $row;
    }
}
