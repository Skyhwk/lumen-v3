<?php

namespace App\Console\Commands;

use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kembalikan keputusan Bapak Direktur (approve/reject penawaran gaji — email salary-decision).
 * Bukan tahap Ibu (approve/reject/keep kandidat).
 */
class RollbackDirectorSalaryDecision extends Command
{
    protected $signature = 'recruitment:rollback-director-salary
        {recruitmentId? : ID new_recruitment}
        {--token= : token_approval}
        {--dry-run : Tampilkan rencana tanpa menulis DB}';

    protected $description = 'Rollback approve/reject Bapak (penawaran gaji) ke menunggu keputusan salary';

    private const REVERTIBLE = [
        'internal_sallary_offer_approved',
        'internal_sallary_offer_rejected',
    ];

    public function handle(): int
    {
        $row = $this->resolveRow();
        if (!$row) {
            return 1;
        }

        $history = RecruitmentStatusService::parseMetaHistory($row);
        if ($history === []) {
            $this->error('meta_history kosong.');

            return 1;
        }

        $last = $history[count($history) - 1];
        $lastStatus = (string) ($last['status'] ?? '');
        if (!in_array($lastStatus, self::REVERTIBLE, true)) {
            $this->error("History terakhir bukan keputusan gaji Bapak: {$lastStatus}");
            $this->line('Rollback gaji hanya untuk: ' . implode(', ', self::REVERTIBLE));
            $this->line('Untuk tahap Ibu (keep/approve kandidat): recruitment:rollback-director-management');

            return 1;
        }

        $offer = DB::table('sallary_offer')
            ->where('new_recruitment_id', $row->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$offer) {
            $offer = DB::table('sallary_offer')
                ->where('new_recruitment_id', $row->id)
                ->orderByDesc('id')
                ->first();
        }

        $this->table(
            ['', 'Nilai'],
            [
                ['id', (string) $row->id],
                ['nama', (string) ($row->nama_lengkap ?? '-')],
                ['status sekarang', (string) $row->status],
                ['status setelah rollback', 'internal_sallary_offer'],
                ['history dihapus', $lastStatus],
                ['token_approval', 'tidak diubah'],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — tidak ada perubahan DB.');

            return 0;
        }

        if (!$this->confirm('Kembalikan ke menunggu approve/reject Bapak (penawaran gaji)?', true)) {
            $this->info('Dibatalkan.');

            return 0;
        }

        $newHistory = $history;
        array_pop($newHistory);
        $now = Carbon::now();

        DB::transaction(function () use ($row, $newHistory, $lastStatus, $offer, $now) {
            DB::table('new_recruitment')->where('id', $row->id)->update([
                'status' => 'internal_sallary_offer',
                'meta_history' => json_encode(array_values($newHistory)),
                'rejected_salary' => 0,
                'rejected_salary_reason' => null,
                'updated_at' => $now,
            ]);

            if ($offer) {
                $offerUpdate = [
                    'updated_at' => $now,
                ];

                if ($lastStatus === 'internal_sallary_offer_approved') {
                    $offerUpdate['final_sallary'] = null;
                }

                if ($lastStatus === 'internal_sallary_offer_rejected' && empty($offer->email_sent_at)) {
                    $offerUpdate['email_sent_at'] = $now;
                }

                DB::table('sallary_offer')->where('id', $offer->id)->update($offerUpdate);
            }

            RecruitmentStatusService::syncRejectedSectionFlags((int) $row->id);
        });

        $this->info("Rollback gaji Bapak berhasil (#{$row->id}). Email/tombol approve-reject bisa diproses lagi (setelah halaman salary-decision tersedia).");
        $this->warn('Jika surat hiring sudah terkirim ke kandidat, email itu tidak ditarik — cek manual ke HRD.');

        return 0;
    }

    private function resolveRow()
    {
        $id = $this->argument('recruitmentId');
        $token = trim((string) $this->option('token'));

        if ($id !== null && $id !== '') {
            $row = DB::table('new_recruitment')->where('id', (int) $id)->first();
            if (!$row) {
                $this->error("ID {$id} tidak ditemukan.");

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
            $this->error('Token tidak ditemukan di new_recruitment.token_approval');

            return null;
        }

        return $row;
    }
}
