<?php

namespace App\Services\Payroll;

use App\Models\MasterKaryawan;
use App\Models\Payroll;
use App\Models\PayrollHeader;
use App\Services\Greatday\FirebaseService;
use App\Services\Greatday\RenderSlipGajiService;
use App\Services\SendEmail;
use App\Support\Greatday\NotificationCopy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollTransferCompletionService
{
    /**
     * Dipanggil dari callback Python setelah transfer bank / proses eksternal selesai.
     *
     * @param int $payrollHeaderId
     * @param int[]|null $payrollIds null = semua baris aktif di header
     */
    public function complete(int $payrollHeaderId, ?array $payrollIds = null): array
    {
        $header = PayrollHeader::query()
            ->where('id', $payrollHeaderId)
            ->where('is_active', true)
            ->first();

        if (!$header) {
            throw new \InvalidArgumentException('Payroll header tidak ditemukan.');
        }

        $query = Payroll::query()
            ->where('payroll_header_id', $header->id)
            ->where('is_active', true);

        if ($payrollIds !== null && $payrollIds !== []) {
            $query->whereIn('id', $payrollIds);
        }

        $payrolls = $query->get();
        if ($payrolls->isEmpty()) {
            return [
                'payroll_header_id' => $payrollHeaderId,
                'updated' => 0,
                'notified' => 0,
                'emailed' => 0,
            ];
        }

        $periodeLabel = self::formatPeriodeLabel($header->periode_payroll);
        $notificationPayload = NotificationCopy::payrollTransferCompleted($periodeLabel);

        $updated = 0;
        $notified = 0;
        $emailed = 0;
        $errors = [];

        $firebase = null;

        foreach ($payrolls as $payroll) {
            if (strtoupper((string) $payroll->status) === 'PAID') {
                continue;
            }

            try {
                DB::transaction(function () use ($payroll, &$updated) {
                    Payroll::where('id', $payroll->id)->update([
                        'status' => 'PAID',
                        'updated_at' => Carbon::now()->toDateTimeString(),
                    ]);
                    $updated++;
                });

                $attendanceUserId = (int) ($payroll->id_karyawan ?: 0);
                if ($attendanceUserId > 0) {
                    try {
                        if ($firebase === null) {
                            $firebase = app(FirebaseService::class);
                        }
                        $firebase->sendNotifications([$attendanceUserId], $notificationPayload);
                        $notified++;
                    } catch (\Throwable $notificationError) {
                        $errors[] = [
                            'payroll_id' => $payroll->id,
                            'step' => 'notification',
                            'message' => $notificationError->getMessage(),
                        ];
                    }
                }

                try {
                    if ($this->sendPayslipEmail($payroll, $header, $periodeLabel)) {
                        $emailed++;
                    }
                } catch (\Throwable $mailError) {
                    $errors[] = [
                        'payroll_id' => $payroll->id,
                        'step' => 'email',
                        'message' => $mailError->getMessage(),
                    ];
                }
            } catch (\Throwable $rowError) {
                $errors[] = [
                    'payroll_id' => $payroll->id,
                    'step' => 'row',
                    'message' => $rowError->getMessage(),
                ];
            }
        }

        Log::info('payroll_disbursement.completed', [
            'payroll_header_id' => $payrollHeaderId,
            'updated' => $updated,
            'notified' => $notified,
            'emailed' => $emailed,
            'errors' => $errors,
        ]);

        return [
            'payroll_header_id' => $payrollHeaderId,
            'updated' => $updated,
            'notified' => $notified,
            'emailed' => $emailed,
            'errors' => $errors,
        ];
    }

    protected function sendPayslipEmail(Payroll $payroll, PayrollHeader $header, string $periodeLabel): bool
    {
        $karyawan = MasterKaryawan::query()
            ->where('id', $payroll->id_karyawan)
            ->where('is_active', true)
            ->first();

        $email = trim((string) optional($karyawan)->email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $slipPath = $this->ensureSlipPdfPath((int) $payroll->id);
        $attachmentPublicPath = 'dokumen/payroll/slip_gaji/pdf/' . basename($slipPath);

        $nama = $payroll->karyawan ?: optional($karyawan)->nama_lengkap ?: 'Karyawan';
        $body = view('emails.payroll.paid', [
            'nama' => $nama,
            'periodeLabel' => $periodeLabel,
            'tglTransferLabel' => self::formatTransferDateLabel($header->tgl_transfer),
        ])->render();

        SendEmail::where('to', $email)
            ->where('subject', 'Payroll ' . $periodeLabel)
            ->where('body', $body)
            ->where('attachment', [
                [
                    'path' => $attachmentPublicPath,
                    'name' => basename($slipPath),
                ],
            ])
            ->noReply('Payroll ISL')
            ->send();

        return true;
    }

    protected function ensureSlipPdfPath(int $payrollId): string
    {
        $payroll = Payroll::query()->where('id', $payrollId)->where('is_active', true)->first();
        if (!$payroll) {
            throw new \RuntimeException('Payroll tidak ditemukan untuk slip gaji.');
        }

        $filenames = RenderSlipGajiService::buildSlipFilenames($payroll);
        $pdfPath = public_path('dokumen/payroll/slip_gaji/pdf/' . $filenames['pdf_filename']);

        if (is_file($pdfPath) && !RenderSlipGajiService::shouldRefreshSlipPdf($payroll, $pdfPath)) {
            return $pdfPath;
        }

        if (is_file($pdfPath)) {
            RenderSlipGajiService::deleteCachedSlipFiles($payroll);
        }

        $generated = RenderSlipGajiService::fromPayrollId($payrollId)->generate();
        $path = $generated['path'] ?? $pdfPath;

        if (!is_file($path)) {
            throw new \RuntimeException('Gagal menghasilkan PDF slip gaji.');
        }

        return $path;
    }

    public static function formatPeriodeLabel(?string $periodePayroll): string
    {
        $periodePayroll = trim((string) $periodePayroll);
        if ($periodePayroll === '') {
            return '-';
        }

        try {
            $date = Carbon::parse($periodePayroll . '-01');
            $bulan = [
                1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];

            return ($bulan[(int) $date->month] ?? $date->format('F')) . ' ' . $date->year;
        } catch (\Throwable $exception) {
            return $periodePayroll;
        }
    }

    /** Contoh: 2026-10-12 → 12 Oktober 2026 */
    public static function formatTransferDateLabel($tanggal): string
    {
        $tanggal = trim((string) $tanggal);
        if ($tanggal === '' || $tanggal === '-') {
            return '-';
        }

        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        try {
            $parsed = Carbon::parse($tanggal);
        } catch (\Throwable $exception) {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $tanggal, $m)) {
                return (int) $m[3] . ' ' . ($bulan[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
            }

            return $tanggal;
        }

        return $parsed->day . ' ' . ($bulan[(int) $parsed->month] ?? $parsed->format('F')) . ' ' . $parsed->year;
    }
}
