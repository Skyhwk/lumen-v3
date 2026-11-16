<?php

namespace App\Console\Commands;

use App\Services\Payroll\PayrollTransferCompletionService;
use Illuminate\Console\Command;

class CompletePayrollDisbursementCommand extends Command
{
    protected $signature = 'payroll:complete-disbursement {payroll_header_id : ID payroll_header} {--email-only : Kirim ulang email slip saja (payroll sudah PAID)}';

    protected $description = 'Tandai payroll PAID + notif Greatday + email slip (recovery / tanpa Python)';

    public function handle(): int
    {
        $payrollHeaderId = (int) $this->argument('payroll_header_id');
        if ($payrollHeaderId <= 0) {
            $this->error('payroll_header_id tidak valid.');

            return 1;
        }

        try {
            $service = app(PayrollTransferCompletionService::class);
            $result = $this->option('email-only')
                ? $service->resendPayslipEmails($payrollHeaderId)
                : $service->complete($payrollHeaderId);
            $this->info(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }
    }
}
