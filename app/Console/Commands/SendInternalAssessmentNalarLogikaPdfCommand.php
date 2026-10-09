<?php

namespace App\Console\Commands;

use App\Services\InternalAssessmentParticipantPdfService;
use Illuminate\Console\Command;

class SendInternalAssessmentNalarLogikaPdfCommand extends Command
{
    protected $signature = 'internal-assessment:send-nalar-logika-pdf
                            {assessment_id : ID assessment_internal}
                            {--email= : Hanya peserta dengan email ini (mode uji)}
                            {--send-email : Kirim PDF sebagai lampiran email ke peserta}
                            {--dry-run : Tampilkan target tanpa generate PDF / email}';

    protected $description = 'Generate PDF jawaban Nalar & Logika per peserta (opsional kirim email)';

    public function handle(): int
    {
        $assessmentId = (int) $this->argument('assessment_id');
        if ($assessmentId < 1) {
            $this->error('assessment_id tidak valid.');

            return 1;
        }

        $emailFilter = $this->option('email');
        $emailFilter = is_string($emailFilter) ? trim($emailFilter) : null;
        if ($emailFilter === '') {
            $emailFilter = null;
        }

        $sendEmail = (bool) $this->option('send-email');
        $dryRun = (bool) $this->option('dry-run');

        $service = app(InternalAssessmentParticipantPdfService::class);

        try {
            $result = $service->processAssessment($assessmentId, $emailFilter, $sendEmail, $dryRun);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info('Assessment ID: ' . $assessmentId);
        if ($emailFilter) {
            $this->line('Filter email: ' . $emailFilter);
        }
        if ($dryRun) {
            $this->warn('Mode dry-run — tidak ada file PDF atau email yang dikirim.');
        }

        if (!empty($result['generated'])) {
            $this->info('Berhasil / target (' . count($result['generated']) . '):');
            foreach ($result['generated'] as $row) {
                if (!empty($row['dry_run'])) {
                    $this->line('- #' . $row['attempt_id'] . ' ' . ($row['participant'] ?? '-') . ' [dry-run]');
                    continue;
                }
                $this->line('- #' . $row['attempt_id'] . ' ' . ($row['participant'] ?? '-'));
                if (!empty($row['links']) && is_array($row['links'])) {
                    foreach ($row['links'] as $file) {
                        $cat = $file['category'] ?? '-';
                        $link = $file['link'] ?? '-';
                        $this->line('    · ' . $cat . ' → ' . $link);
                    }
                } elseif (!empty($row['link'])) {
                    $this->line('    → ' . $row['link']);
                }
            }
        }

        if ($sendEmail && !empty($result['emailed'])) {
            $this->info('Email terkirim (' . count($result['emailed']) . ').');
        }

        if (!empty($result['skipped_email'])) {
            $this->warn('Email dilewati (' . count($result['skipped_email']) . '):');
            foreach ($result['skipped_email'] as $row) {
                $this->line('- #' . $row['attempt_id'] . ' ' . ($row['reason'] ?? ''));
            }
        }

        if (!empty($result['failed'])) {
            $this->error('Gagal (' . count($result['failed']) . '):');
            foreach ($result['failed'] as $row) {
                $this->line('- #' . $row['attempt_id'] . ' ' . ($row['message'] ?? ''));
            }

            return 1;
        }

        return 0;
    }
}
