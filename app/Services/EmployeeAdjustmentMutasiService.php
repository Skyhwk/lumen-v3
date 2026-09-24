<?php

namespace App\Services;

use App\Models\SalaryAdjustmentRequest;
use Illuminate\Http\Request;

class EmployeeAdjustmentMutasiService
{
    public const DECISION_ACCEPT = 'accept';
    public const DECISION_REJECT = 'reject';

    public const SALARY_SAMA = 'sama';
    public const SALARY_KENAIKAN = 'kenaikan';
    public const SALARY_PENURUNAN = 'penurunan';

    public static function salaryDecisionOptions(): array
    {
        return [
            ['value' => self::SALARY_SAMA, 'label' => 'Gaji & tunjangan tetap (sama)'],
            ['value' => self::SALARY_KENAIKAN, 'label' => 'Penyesuaian kenaikan'],
            ['value' => self::SALARY_PENURUNAN, 'label' => 'Penyesuaian penurunan'],
        ];
    }

    public function isReceiver(SalaryAdjustmentRequest $record, int $userId): bool
    {
        return (int) $record->receiver_manager_id === $userId
            && $record->request_type === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI;
    }

    public function canRespond(SalaryAdjustmentRequest $record, int $userId): bool
    {
        return $this->isReceiver($record, $userId)
            && $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER
            && $record->is_active;
    }

    /**
     * @return array{error: ?string, data: array<string, mixed>}
     */
    public function validateRespondRequest(Request $request, SalaryAdjustmentRequest $record): array
    {
        $decision = strtolower(trim((string) $request->input('decision', '')));

        if (!in_array($decision, [self::DECISION_ACCEPT, self::DECISION_REJECT], true)) {
            return ['error' => 'Keputusan mutasi tidak valid', 'data' => []];
        }

        if ($decision === self::DECISION_REJECT) {
            $reason = trim((string) ($request->input('reject_reason') ?? $request->input('notes') ?? ''));
            if ($reason === '') {
                return ['error' => 'Alasan penolakan mutasi wajib diisi', 'data' => []];
            }

            return [
                'error' => null,
                'data' => [
                    'decision' => self::DECISION_REJECT,
                    'reject_reason' => $reason,
                ],
            ];
        }

        $salaryDecision = strtolower(trim((string) $request->input('receiver_salary_decision', self::SALARY_SAMA)));
        $allowedSalary = [self::SALARY_SAMA, self::SALARY_KENAIKAN, self::SALARY_PENURUNAN];
        if (!in_array($salaryDecision, $allowedSalary, true)) {
            return ['error' => 'Keputusan gaji/tunjangan mutasi tidak valid', 'data' => []];
        }

        $adjustmentGaji = 0.0;
        $adjustmentTunj = 0.0;
        $bulanEfektif = null;
        $hasSalaryAdjustment = false;
        $allowsNegative = $salaryDecision === self::SALARY_PENURUNAN;

        if (in_array($salaryDecision, [self::SALARY_KENAIKAN, self::SALARY_PENURUNAN], true)) {
            $adjustmentGaji = $this->parseSignedAmount($request->input('receiver_adjustment_gaji'), $allowsNegative);
            $adjustmentTunj = $this->parseSignedAmount($request->input('receiver_adjustment_tunjangan'), $allowsNegative);

            if ($salaryDecision === self::SALARY_KENAIKAN && ($adjustmentGaji < 0 || $adjustmentTunj < 0)) {
                return ['error' => 'Penyesuaian kenaikan tidak boleh bernilai negatif', 'data' => []];
            }

            if ($salaryDecision === self::SALARY_PENURUNAN) {
                if ($adjustmentGaji > 0) {
                    $adjustmentGaji = -$adjustmentGaji;
                }
                if ($adjustmentTunj > 0) {
                    $adjustmentTunj = -$adjustmentTunj;
                }
            }

            if ($adjustmentGaji === 0.0 && $adjustmentTunj === 0.0) {
                return ['error' => 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi', 'data' => []];
            }

            $bulanEfektif = trim((string) ($request->input('bulan_efektif') ?? ''));
            $bulanError = EmployeeAdjustmentValidationService::validateBulanEfektif($bulanEfektif, true);
            if ($bulanError) {
                return ['error' => $bulanError, 'data' => []];
            }

            $hasSalaryAdjustment = true;
        }

        $notes = trim((string) ($request->input('notes') ?? ''));

        return [
            'error' => null,
            'data' => [
                'decision' => self::DECISION_ACCEPT,
                'receiver_salary_decision' => $salaryDecision,
                'receiver_adjustment_gaji' => $hasSalaryAdjustment ? $adjustmentGaji : null,
                'receiver_adjustment_tunjangan' => $hasSalaryAdjustment ? $adjustmentTunj : null,
                'has_salary_adjustment' => $hasSalaryAdjustment,
                'bulan_efektif' => $bulanEfektif,
                'notes' => $notes !== '' ? $notes : null,
            ],
        ];
    }

    public function applyAcceptance(SalaryAdjustmentRequest $record, array $payload): void
    {
        $currentGaji = (float) $record->current_gaji_pokok;
        $currentTunj = (float) $record->current_tunjangan_kerja;

        $record->receiver_salary_decision = $payload['receiver_salary_decision'];
        $record->receiver_adjustment_gaji = $payload['receiver_adjustment_gaji'];
        $record->receiver_adjustment_tunjangan = $payload['receiver_adjustment_tunjangan'];
        $record->has_salary_adjustment = (bool) $payload['has_salary_adjustment'];

        if ($payload['has_salary_adjustment']) {
            $gaji = (float) $payload['receiver_adjustment_gaji'];
            $tunj = (float) $payload['receiver_adjustment_tunjangan'];

            $record->adjustment_gaji_pokok = $gaji !== 0.0 ? $gaji : null;
            $record->adjustment_tunjangan = $tunj !== 0.0 ? $tunj : null;
            $record->requested_gaji_pokok = $currentGaji + $gaji;
            $record->requested_tunjangan_kerja = $currentTunj + $tunj;
            $record->submitted_adjustment_gaji_pokok = $record->adjustment_gaji_pokok;
            $record->submitted_adjustment_tunjangan = $record->adjustment_tunjangan;
            $record->submitted_requested_gaji_pokok = $record->requested_gaji_pokok;
            $record->submitted_requested_tunjangan_kerja = $record->requested_tunjangan_kerja;
            $record->bulan_efektif = $payload['bulan_efektif'];
        } else {
            $record->adjustment_gaji_pokok = null;
            $record->adjustment_tunjangan = null;
            $record->requested_gaji_pokok = $currentGaji;
            $record->requested_tunjangan_kerja = $currentTunj;
        }

        $record->status = SalaryAdjustmentWorkflowService::STATUS_RECEIVER_RESPONDED;
        $record->receiver_responded_at = now();
    }

    public function formatReceiverFields(SalaryAdjustmentRequest $record): array
    {
        return [
            'receiver_salary_decision' => $record->receiver_salary_decision,
            'receiver_salary_decision_label' => $this->salaryDecisionLabel($record->receiver_salary_decision),
            'receiver_adjustment_gaji' => $record->receiver_adjustment_gaji !== null
                ? (float) $record->receiver_adjustment_gaji
                : null,
            'receiver_adjustment_tunjangan' => $record->receiver_adjustment_tunjangan !== null
                ? (float) $record->receiver_adjustment_tunjangan
                : null,
            'receiver_responded_at' => $record->receiver_responded_at,
            'can_respond_mutasi' => $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER,
        ];
    }

    public function salaryDecisionLabel(?string $decision): ?string
    {
        switch ($decision) {
            case self::SALARY_SAMA:
                return 'Tetap (sama)';
            case self::SALARY_KENAIKAN:
                return 'Kenaikan';
            case self::SALARY_PENURUNAN:
                return 'Penurunan';
            default:
                return $decision ? ucfirst($decision) : null;
        }
    }

    private function parseSignedAmount($value, bool $allowNegative): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            $amount = (float) $value;
            return $allowNegative ? $amount : max(0.0, $amount);
        }

        $raw = trim((string) $value);
        $isNegative = isset($raw[0]) && $raw[0] === '-';
        $digits = preg_replace('/[^\d]/', '', $raw);
        if ($digits === '') {
            return 0.0;
        }

        $amount = (float) $digits;
        if ($isNegative && $allowNegative) {
            $amount = -$amount;
        }

        return $allowNegative ? $amount : max(0.0, $amount);
    }
}
