<?php

namespace App\Services;

use App\Models\SalaryAdjustmentRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmployeeAdjustmentMutasiService
{
    public const META_UNDER_SAME_MANAGER = 'mutasi_under_same_manager';
    public const META_DATE_NEGOTIATION = 'date_negotiation';

    public const TURN_RECEIVER = 'receiver';
    public const TURN_GIVER = 'giver';

    public const DECISION_ACCEPT = 'accept';
    public const DECISION_REJECT = 'reject';

    public const GIVER_DATE_ACTION_AGREE = 'agree';
    public const GIVER_DATE_ACTION_PROPOSE = 'propose';

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

    public static function isUnderSameManagerScope(SalaryAdjustmentRequest $record): bool
    {
        if ($record->request_type !== EmployeeAdjustmentTypeRegistry::TYPE_MUTASI) {
            return false;
        }

        $meta = self::decodeMetadata($record);

        return !empty($meta[self::META_UNDER_SAME_MANAGER]);
    }

    public static function decodeMetadata(SalaryAdjustmentRequest $record): array
    {
        $meta = $record->type_metadata;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        return is_array($meta) ? $meta : [];
    }

    public static function encodeMetadata(array $meta): array
    {
        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialDateNegotiation(string $giverDate): array
    {
        return [
            'giver_date' => $giverDate,
            'receiver_date' => null,
            'turn' => self::TURN_RECEIVER,
            'agreed' => false,
            'agreed_by' => null,
            'locked' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDateNegotiation(SalaryAdjustmentRequest $record): array
    {
        $meta = self::decodeMetadata($record);
        $nego = $meta[self::META_DATE_NEGOTIATION] ?? [];

        return is_array($nego) ? $nego : [];
    }

    public static function requiresDateNegotiation(SalaryAdjustmentRequest $record): bool
    {
        if ($record->request_type !== EmployeeAdjustmentTypeRegistry::TYPE_MUTASI) {
            return false;
        }

        return !self::isUnderSameManagerScope($record);
    }

    public function isGiver(SalaryAdjustmentRequest $record, int $userId): bool
    {
        return (int) $record->requested_by_id === $userId
            && $record->request_type === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI;
    }

    public function isReceiver(SalaryAdjustmentRequest $record, int $userId): bool
    {
        if (self::isUnderSameManagerScope($record)) {
            return false;
        }

        return (int) $record->receiver_manager_id === $userId
            && $record->request_type === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI;
    }

    public function canRespond(SalaryAdjustmentRequest $record, int $userId): bool
    {
        return $this->isReceiver($record, $userId)
            && $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER
            && $record->is_active;
    }

    public function canRespondMutasiDate(SalaryAdjustmentRequest $record, int $userId): bool
    {
        return $this->isGiver($record, $userId)
            && $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_GIVER
            && $record->is_active
            && self::requiresDateNegotiation($record);
    }

    public function canProposeMutasiDate(SalaryAdjustmentRequest $record, int $userId): bool
    {
        return $this->canRespond($record, $userId)
            && self::requiresDateNegotiation($record);
    }

    /**
     * Tanggal yang bisa disetujui penerima saat Terima (accept).
     */
    public function resolveActiveEffectiveDate(SalaryAdjustmentRequest $record): ?string
    {
        if (!self::requiresDateNegotiation($record)) {
            return $this->normalizeDateString($record->tanggal_efektif);
        }

        $nego = self::getDateNegotiation($record);

        if (!empty($nego['locked'])) {
            return $this->normalizeDateString($record->tanggal_efektif)
                ?: $this->normalizeDateString($nego['receiver_date'] ?? null);
        }

        return $this->normalizeDateString($nego['giver_date'] ?? null);
    }

    public function canAcceptWithEffectiveDate(SalaryAdjustmentRequest $record): bool
    {
        if (!self::requiresDateNegotiation($record)) {
            return true;
        }

        return $this->resolveActiveEffectiveDate($record) !== null;
    }

    /**
     * @return array{error: ?string, data: array<string, mixed>}
     */
    public function validateProposeMutasiDate(Request $request, SalaryAdjustmentRequest $record): array
    {
        $proposed = $this->normalizeDateString($request->input('proposed_date'));
        if (!$proposed) {
            return ['error' => 'Tanggal efektif usulan wajib diisi', 'data' => []];
        }

        $notes = trim((string) ($request->input('notes') ?? ''));

        return [
            'error' => null,
            'data' => [
                'proposed_date' => $proposed,
                'notes' => $notes !== '' ? $notes : null,
            ],
        ];
    }

    public function applyReceiverProposedDate(SalaryAdjustmentRequest $record, array $payload): void
    {
        $meta = self::decodeMetadata($record);
        $nego = self::getDateNegotiation($record);
        $nego['receiver_date'] = $payload['proposed_date'];
        $nego['turn'] = self::TURN_GIVER;
        $nego['locked'] = false;
        $nego['agreed'] = false;
        $nego['agreed_by'] = null;
        $meta[self::META_DATE_NEGOTIATION] = $nego;
        $record->type_metadata = $meta;
        $record->tanggal_efektif = null;
        $record->status = SalaryAdjustmentWorkflowService::STATUS_WAITING_GIVER;
    }

    /**
     * @return array{error: ?string, data: array<string, mixed>}
     */
    public function validateRespondMutasiDate(Request $request, SalaryAdjustmentRequest $record): array
    {
        $action = strtolower(trim((string) $request->input('action', '')));
        if (!in_array($action, [self::GIVER_DATE_ACTION_AGREE, self::GIVER_DATE_ACTION_PROPOSE], true)) {
            return ['error' => 'Aksi respons tanggal mutasi tidak valid', 'data' => []];
        }

        $nego = self::getDateNegotiation($record);
        $receiverDate = $this->normalizeDateString($nego['receiver_date'] ?? null);

        if ($action === self::GIVER_DATE_ACTION_AGREE) {
            if (!$receiverDate) {
                return ['error' => 'Tidak ada usulan tanggal dari manager penerima', 'data' => []];
            }

            return [
                'error' => null,
                'data' => [
                    'action' => self::GIVER_DATE_ACTION_AGREE,
                    'effective_date' => $receiverDate,
                    'notes' => trim((string) ($request->input('notes') ?? '')) ?: null,
                ],
            ];
        }

        $proposed = $this->normalizeDateString($request->input('proposed_date'));
        if (!$proposed) {
            return ['error' => 'Tanggal efektif usulan wajib diisi', 'data' => []];
        }

        return [
            'error' => null,
            'data' => [
                'action' => self::GIVER_DATE_ACTION_PROPOSE,
                'proposed_date' => $proposed,
                'notes' => trim((string) ($request->input('notes') ?? '')) ?: null,
            ],
        ];
    }

    public function applyGiverAgreedDate(SalaryAdjustmentRequest $record, array $payload): void
    {
        $meta = self::decodeMetadata($record);
        $nego = self::getDateNegotiation($record);
        $nego['turn'] = self::TURN_RECEIVER;
        $nego['locked'] = true;
        $nego['agreed'] = false;
        $nego['agreed_by'] = null;
        $meta[self::META_DATE_NEGOTIATION] = $nego;
        $record->type_metadata = $meta;
        $record->tanggal_efektif = $payload['effective_date'];
        $record->status = SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER;
    }

    public function applyGiverProposedDate(SalaryAdjustmentRequest $record, array $payload): void
    {
        $meta = self::decodeMetadata($record);
        $nego = self::getDateNegotiation($record);
        $nego['giver_date'] = $payload['proposed_date'];
        $nego['turn'] = self::TURN_RECEIVER;
        $nego['locked'] = false;
        $nego['agreed'] = false;
        $nego['agreed_by'] = null;
        $meta[self::META_DATE_NEGOTIATION] = $nego;
        $record->type_metadata = $meta;
        $record->tanggal_efektif = null;
        $record->status = SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER;
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

        if (!$this->canAcceptWithEffectiveDate($record)) {
            return ['error' => 'Tanggal efektif mutasi belum disepakati', 'data' => []];
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
        $effectiveDate = $this->resolveActiveEffectiveDate($record);

        return [
            'error' => null,
            'data' => [
                'decision' => self::DECISION_ACCEPT,
                'receiver_salary_decision' => $salaryDecision,
                'receiver_adjustment_gaji' => $hasSalaryAdjustment ? $adjustmentGaji : null,
                'receiver_adjustment_tunjangan' => $hasSalaryAdjustment ? $adjustmentTunj : null,
                'has_salary_adjustment' => $hasSalaryAdjustment,
                'bulan_efektif' => $bulanEfektif,
                'tanggal_efektif' => $effectiveDate,
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

        if (!empty($payload['tanggal_efektif'])) {
            $record->tanggal_efektif = $payload['tanggal_efektif'];
        }

        if (self::requiresDateNegotiation($record)) {
            $meta = self::decodeMetadata($record);
            $nego = self::getDateNegotiation($record);
            $nego['agreed'] = true;
            $nego['agreed_by'] = self::TURN_RECEIVER;
            $nego['locked'] = true;
            $meta[self::META_DATE_NEGOTIATION] = $nego;
            $record->type_metadata = $meta;

            $resolver = new EmployeeAdjustmentWorkflowResolver();
            $scheduled = $resolver->resolveScheduledApplyDate([
                'tanggal_efektif' => $record->tanggal_efektif,
                'bulan_efektif' => $record->bulan_efektif,
            ], EmployeeAdjustmentTypeRegistry::TYPE_MUTASI);
            if ($scheduled) {
                $record->scheduled_apply_at = $scheduled->toDateString();
            }
        }

        $record->status = SalaryAdjustmentWorkflowService::STATUS_RECEIVER_RESPONDED;
        $record->receiver_responded_at = Carbon::now();
    }

    public function formatReceiverFields(SalaryAdjustmentRequest $record): array
    {
        $nego = self::getDateNegotiation($record);
        $activeDate = $this->resolveActiveEffectiveDate($record);

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
            'can_propose_mutasi_date' => $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER
                && self::requiresDateNegotiation($record),
            'can_respond_mutasi_date' => $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_GIVER
                && self::requiresDateNegotiation($record),
            'mutasi_active_effective_date' => $activeDate,
            'mutasi_date_negotiation' => [
                'giver_date' => $nego['giver_date'] ?? null,
                'receiver_date' => $nego['receiver_date'] ?? null,
                'turn' => $nego['turn'] ?? null,
                'agreed' => !empty($nego['agreed']),
                'agreed_by' => $nego['agreed_by'] ?? null,
                'locked' => !empty($nego['locked']),
            ],
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

    private function normalizeDateString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
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
