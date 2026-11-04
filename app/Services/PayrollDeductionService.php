<?php

namespace App\Services;

use App\Models\{Payroll, PayrollHeader, MasterKaryawan, Kasbon, DendaKaryawan, PencadanganUpah};
use App\Services\PayrollRecordSyncService;
use Illuminate\Support\Facades\DB;

class PayrollDeductionService
{
    private const SOURCES = [
        'loan' => [Kasbon::class, 'sisa_kasbon'],
        'sanksi' => [DendaKaryawan::class, 'sisa_denda'],
        'pencadangan_upah' => [PencadanganUpah::class, null],
    ];

    public function submit(Payroll $payroll, ?string $actor): Payroll
    {
        return DB::transaction(function () use ($payroll, $actor) {
            // Both submit and cancel serialize on the employee, including first payroll creation.
            $employee = MasterKaryawan::where('id', $payroll->id_karyawan)->lockForUpdate()->firstOrFail();
            $header = PayrollHeader::where('id', $payroll->payroll_header_id)->lockForUpdate()->firstOrFail();
            if (!$header->is_active || $header->periode_payroll !== $payroll->periode_payroll) {
                throw new \DomainException('Header payroll sudah tidak aktif atau periodenya tidak sesuai.');
            }
            $existing = Payroll::where('id_karyawan', $employee->id)->where('is_active', true)
                ->where('periode_payroll', $payroll->periode_payroll)->lockForUpdate()->first();
            if ($existing) {
                throw new \DomainException('Payroll aktif untuk karyawan dan periode ini sudah ada.');
            }
            if ($this->hasAmounts($payroll) && $this->affectedPayrolls($employee->id)
                ->where('periode_payroll', '>', $payroll->periode_payroll)->exists()) {
                throw new \DomainException('Batalkan payroll periode berikutnya terlebih dahulu sebelum memproses potongan periode ini.');
            }

            $entries = [];
            foreach (self::SOURCES as $field => [$class, $balanceField]) {
                $amount = (float) $payroll->$field;
                if ($amount == 0) {
                    continue;
                }
                $periodField = $balanceField ? 'bulan_mulai_pemotongan' : 'bulan_efektif';
                $source = PayrollRecordSyncService::resolvePayrollDeductionSource(
                    $class::where('id_karyawan', $employee->id)->lockForUpdate(),
                    $balanceField,
                    $periodField,
                    $payroll->periode_payroll,
                    (bool) $employee->is_active
                );
                if ($source === null) {
                    throw new \DomainException($this->deductionSourceErrorMessage(
                        $field,
                        $payroll->periode_payroll
                    ));
                }
                $before = $this->state($source, $balanceField);
                if ($balanceField) {
                    PayrollDebtService::deduct($source, $balanceField, $amount);
                } else {
                    $employeeIsActive = (bool) $employee->is_active;
                    $expected = PencadanganUpahPayrollService::previewAmount(
                        $source,
                        $employee->status_karyawan,
                        $employeeIsActive
                    );
                    if ($amount != $expected) {
                        throw new \DomainException('Nominal/fase pencadangan berubah. Muat ulang payroll sebelum submit.');
                    }
                    if (!PencadanganUpahPayrollService::advanceOnPayrollSubmitForInactiveExit(
                        $source,
                        $amount,
                        $employeeIsActive,
                        $employee->status_karyawan
                    )) {
                        PencadanganUpahPayrollService::advanceOnPayrollSubmit(
                            $source,
                            $employee->status_karyawan,
                            $employeeIsActive
                        );
                    }
                }
                $entries[$field] = ['id' => $source->id, 'amount' => $amount,
                    'before' => $before, 'after' => $this->state($source, $balanceField)];
                $this->saveSource($source, $actor);
            }
            $payroll->deduction_snapshot = ['version' => 1, 'entries' => $entries];
            $payroll->is_active = true;
            $payroll->created_by = $actor;
            $payroll->created_at = date('Y-m-d H:i:s');
            $payroll->save();
            return $payroll;
        });
    }

    public function cancel(int $payrollId, ?string $actor): void
    {
        DB::transaction(function () use ($payrollId, $actor) {
            $identity = Payroll::findOrFail($payrollId);
            MasterKaryawan::where('id', $identity->id_karyawan)->lockForUpdate()->firstOrFail();
            $payroll = Payroll::where('id', $payrollId)->lockForUpdate()->firstOrFail();
            if (!$payroll->is_active) {
                return; // Retry must not restore a deduction twice.
            }
            $snapshot = $payroll->deduction_snapshot;
            if ($this->hasAmounts($payroll)) {
                if (!is_array($snapshot) || ($snapshot['version'] ?? null) !== 1) {
                    throw new \DomainException('Payroll lama belum memiliki snapshot sumber potongan. Rekonsiliasi riwayat sebelum cancel.');
                }
                if ($this->affectedPayrolls($payroll->id_karyawan)->where('id', '>', $payroll->id)->exists()) {
                    throw new \DomainException('Batalkan payroll dengan potongan yang lebih baru terlebih dahulu.');
                }
            }
            foreach (self::SOURCES as $field => [$class, $balanceField]) {
                $amount = (float) $payroll->$field;
                $entry = $snapshot['entries'][$field] ?? null;
                if ($amount == 0 && !$entry) {
                    continue;
                }
                if (!$entry || $amount != $entry['amount'] || $amount == 0) {
                    throw new \DomainException("Snapshot $field tidak sesuai dengan nominal payroll. Rekonsiliasi terlebih dahulu.");
                }
                // Never pick the latest master record: restore only the recorded source.
                $source = $class::where('id', $entry['id'])->where('id_karyawan', $payroll->id_karyawan)
                    ->lockForUpdate()->first();
                if (!$source || $this->state($source, $balanceField) != $entry['after']) {
                    throw new \DomainException("Sumber $field sudah berubah sejak submit. Rekonsiliasi terlebih dahulu.");
                }
                if ($balanceField) {
                    PayrollDebtService::restore($source, $balanceField, $amount);
                } else {
                    foreach ($entry['before'] as $key => $value) {
                        $source->$key = $value;
                    }
                }
                $this->saveSource($source, $actor);
            }
            $payroll->is_active = false;
            $payroll->deleted_by = $actor;
            $payroll->deleted_at = date('Y-m-d H:i:s');
            $payroll->save();
        });
    }

    private function state($source, ?string $balanceField): array
    {
        $fields = $balanceField
            ? [$balanceField, 'sisa_tenor', 'status', 'nominal_potongan']
            : ['tenor', 'tenor_berjalan', 'nominal', 'nominal_berjalan', 'status', 'tipe_pengembalian'];
        if ($balanceField && $source instanceof Kasbon) {
            $fields[] = 'interval_pemotongan_bulan';
        }
        $state = [];
        foreach ($fields as $field) {
            $state[$field] = $source->$field;
        }
        return $state;
    }

    private function saveSource($source, ?string $actor): void
    {
        $source->updated_by = $actor;
        $source->updated_at = date('Y-m-d H:i:s');
        $source->save();
    }

    private function hasAmounts(Payroll $payroll): bool
    {
        return (float) $payroll->loan != 0 || (float) $payroll->sanksi != 0 || (float) $payroll->pencadangan_upah != 0;
    }

    private function affectedPayrolls($employeeId)
    {
        return Payroll::where('id_karyawan', $employeeId)->where('is_active', true)
            ->where(function ($query) {
                $query->where('loan', '<>', 0)->orWhere('sanksi', '<>', 0)->orWhere('pencadangan_upah', '<>', 0);
            });
    }

    private function deductionSourceErrorMessage(string $field, string $periode): string
    {
        $labels = [
            'loan' => 'kasbon',
            'sanksi' => 'denda karyawan',
            'pencadangan_upah' => 'pencadangan upah',
        ];
        $label = $labels[$field] ?? $field;

        return "Data master {$label} tidak ditemukan untuk periode {$periode}. "
            . 'Muat ulang payroll, periksa master HR, atau set nominal potongan = 0 sebelum submit.';
    }

}
