<?php

namespace App\Services;

use App\Models\EmployeeAdjustmentRekap;
use App\Models\EmployeeCareerEvent;
use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;
class EmployeeCareerContextService
{
    /**
     * @return array<string, mixed>
     */
    public function buildForApprovalApp(?MasterKaryawan $employee): array
    {
        if (!$employee) {
            return $this->emptyPayload();
        }

        $this->ensureHistorySynced((int) $employee->id);

        $tenure = $this->buildTenure($employee);
        $appointmentEvents = $this->loadAppointmentEvents((int) $employee->id);
        $compensationHistory = $this->buildCompensationHistory($employee);

        return [
            'tenure' => $tenure,
            'last_appointment' => $this->summarizeLastAppointment($appointmentEvents),
            'appointment_history' => $appointmentEvents,
            'compensation_history' => $compensationHistory,
        ];
    }

    public function recordFromAdjustmentApply(
        SalaryAdjustmentRequest $record,
        EmployeeAdjustmentRekap $rekap,
        Carbon $appliedAt
    ): void {
        $employeeId = (int) $record->employee_id;
        $effectiveAt = $this->resolveEffectiveDate($record, $appliedAt);
        $noDocument = $record->no_document;

        if ($this->meaningfulStringChange($rekap->jabatan_lama, $rekap->jabatan_baru)) {
            $this->upsertEvent($employeeId, EmployeeCareerEvent::TYPE_JABATAN, (int) $record->id, [
                'effective_at' => $effectiveAt,
                'title' => 'Perubahan jabatan',
                'old_value' => ['jabatan' => $rekap->jabatan_lama],
                'new_value' => ['jabatan' => $rekap->jabatan_baru],
                'source' => 'adjustment_apply',
                'no_document' => $noDocument,
            ]);
        }

        if ($this->meaningfulStringChange($rekap->status_karyawan_lama, $rekap->status_karyawan_baru)) {
            $this->upsertEvent($employeeId, EmployeeCareerEvent::TYPE_STATUS, (int) $record->id, [
                'effective_at' => $effectiveAt,
                'title' => 'Perubahan status karyawan',
                'old_value' => ['status_karyawan' => $rekap->status_karyawan_lama],
                'new_value' => ['status_karyawan' => $rekap->status_karyawan_baru],
                'source' => 'adjustment_apply',
                'no_document' => $noDocument,
            ]);
        }

        $kontrakLama = $this->formatDateValue($rekap->tgl_berakhir_kontrak_lama);
        $kontrakBaru = $this->formatDateValue($rekap->tgl_berakhir_kontrak_baru);
        if ($kontrakLama !== $kontrakBaru && ($kontrakLama || $kontrakBaru)) {
            $this->upsertEvent($employeeId, EmployeeCareerEvent::TYPE_KONTRAK, (int) $record->id, [
                'effective_at' => $effectiveAt,
                'title' => 'Perubahan berakhir kontrak',
                'old_value' => ['tgl_berakhir_kontrak' => $kontrakLama],
                'new_value' => ['tgl_berakhir_kontrak' => $kontrakBaru],
                'source' => 'adjustment_apply',
                'no_document' => $noDocument,
            ]);
        }

        if ($this->salaryChanged($rekap)) {
            $masterSalaryId = null;
            if (is_array($rekap->payload_json)) {
                $masterSalaryId = isset($rekap->payload_json['master_salary_id'])
                    ? (int) $rekap->payload_json['master_salary_id']
                    : null;
            }

            $this->upsertSalaryEvent(
                $employeeId,
                (int) $record->id,
                $masterSalaryId,
                $effectiveAt,
                (float) $rekap->gaji_lama,
                (float) $rekap->tunjangan_lama,
                (float) $rekap->gaji_baru,
                (float) $rekap->tunjangan_baru,
                $record->bulan_efektif,
                'adjustment_apply',
                $noDocument
            );
        }
    }

    public function ensureHistorySynced(int $employeeId): void
    {
        if ($employeeId <= 0) {
            return;
        }

        $hasAny = EmployeeCareerEvent::where('employee_id', $employeeId)->exists();
        if ($hasAny) {
            return;
        }

        $rekapRows = EmployeeAdjustmentRekap::query()
            ->where('employee_id', $employeeId)
            ->whereNotNull('applied_at')
            ->orderBy('applied_at')
            ->get();

        foreach ($rekapRows as $rekap) {
            $record = SalaryAdjustmentRequest::find($rekap->request_id);
            if (!$record) {
                continue;
            }
            $this->recordFromAdjustmentApply(
                $record,
                $rekap,
                Carbon::parse($rekap->applied_at)
            );
        }

        $employee = MasterKaryawan::find($employeeId);
        if (!$employee) {
            return;
        }

        $salaryRows = $this->loadSalaryRowsForEmployee($employee);
        foreach ($salaryRows as $row) {
            if (EmployeeCareerEvent::where('employee_id', $employeeId)
                ->where('event_type', EmployeeCareerEvent::TYPE_SALARY)
                ->where('master_salary_id', $row->id)
                ->exists()) {
                continue;
            }

            $gaji = (float) ($row->gaji_pokok ?? 0);
            $tunj = (float) ($row->tunjangan_kerja ?? 0);
            $effectiveAt = $this->resolveSalaryEffectiveDate($row);

            $this->upsertSalaryEvent(
                $employeeId,
                null,
                (int) $row->id,
                $effectiveAt,
                null,
                null,
                $gaji,
                $tunj,
                $row->bulan_efektif,
                'backfill_salary',
                null,
                false
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'tenure' => null,
            'last_appointment' => null,
            'appointment_history' => [],
            'compensation_history' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTenure(MasterKaryawan $employee): array
    {
        $startRaw = $employee->tgl_mulai_kerja ?? null;
        $start = $startRaw ? Carbon::parse($startRaw)->startOfDay() : null;
        $now = Carbon::now()->startOfDay();

        return [
            'tgl_mulai_kerja' => $this->formatDateValue($startRaw),
            'tgl_berakhir_kontrak' => $this->formatDateValue($employee->tgl_berakhir_kontrak ?? null),
            'tgl_pra_pensiun' => $this->formatDateValue($employee->tgl_pra_pensiun ?? null),
            'tgl_pensiun' => $this->formatDateValue($employee->tgl_pensiun ?? null),
            'status_karyawan' => $employee->status_karyawan ?: null,
            'status_pekerjaan' => $employee->status_pekerjaan ?? null,
            'grade' => $employee->grade ?? null,
            'masa_kerja_label' => $start ? $this->formatTenureLabel($start, $now) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadAppointmentEvents(int $employeeId): array
    {
        $types = [
            EmployeeCareerEvent::TYPE_JABATAN,
            EmployeeCareerEvent::TYPE_STATUS,
            EmployeeCareerEvent::TYPE_KONTRAK,
        ];

        return EmployeeCareerEvent::query()
            ->where('employee_id', $employeeId)
            ->whereIn('event_type', $types)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (EmployeeCareerEvent $event) {
                return $this->formatEventRow($event);
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $appointmentEvents
     * @return array<string, mixed>|null
     */
    private function summarizeLastAppointment(array $appointmentEvents): ?array
    {
        if ($appointmentEvents === []) {
            return null;
        }

        $latest = $appointmentEvents[0];
        $parts = [];
        if (!empty($latest['summary'])) {
            $parts[] = $latest['summary'];
        }

        return [
            'effective_at' => $latest['effective_at'] ?? null,
            'effective_at_label' => $latest['effective_at_label'] ?? null,
            'event_type' => $latest['event_type'] ?? null,
            'event_type_label' => $latest['event_type_label'] ?? null,
            'summary' => implode(' · ', array_filter($parts)) ?: null,
            'no_document' => $latest['no_document'] ?? null,
            'old_value' => $latest['old_value'] ?? null,
            'new_value' => $latest['new_value'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCompensationHistory(MasterKaryawan $employee): array
    {
        $rows = $this->loadSalaryRowsForEmployee($employee);
        if ($rows->isEmpty()) {
            return [];
        }

        $history = [];
        $prevGaji = null;
        $prevTunj = null;

        foreach ($rows as $row) {
            $gaji = (float) ($row->gaji_pokok ?? 0);
            $tunj = (float) ($row->tunjangan_kerja ?? 0);
            $total = $gaji + $tunj;
            $effectiveAt = $this->resolveSalaryEffectiveDate($row);

            $entry = [
                'effective_at' => $this->formatDateValue($effectiveAt),
                'effective_at_label' => $this->formatDateLabel($effectiveAt),
                'bulan_efektif' => $row->bulan_efektif,
                'bulan_efektif_label' => $this->formatBulanEfektif($row->bulan_efektif),
                'gaji_pokok' => $gaji,
                'tunjangan_kerja' => $tunj,
                'total_kompensasi' => $total,
                'delta_total' => $prevGaji !== null ? $total - ($prevGaji + $prevTunj) : null,
                'delta_gaji_pokok' => $prevGaji !== null ? $gaji - $prevGaji : null,
                'delta_tunjangan_kerja' => $prevTunj !== null ? $tunj - $prevTunj : null,
                'is_active' => (bool) ($row->is_active ?? false),
                'source_label' => (bool) ($row->is_active ?? false) ? 'Berlaku saat ini' : 'Riwayat',
            ];
            $history[] = $entry;

            $prevGaji = $gaji;
            $prevTunj = $tunj;
        }

        return array_reverse($history);
    }

    /**
     * @param \Illuminate\Support\Collection<int, MasterSallary> $rows
     */
    private function loadSalaryRowsForEmployee(MasterKaryawan $employee)
    {
        return MasterSallary::query()
            ->where(function ($query) use ($employee) {
                $query->where('id_karyawan', $employee->id)
                    ->orWhere('nik_karyawan', $employee->nik_karyawan)
                    ->orWhere('karyawan', $employee->nama_lengkap);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertEvent(
        int $employeeId,
        string $eventType,
        ?int $requestId,
        array $attributes
    ): void {
        if ($requestId === null) {
            EmployeeCareerEvent::create(array_merge($attributes, [
                'employee_id' => $employeeId,
                'event_type' => $eventType,
                'request_id' => null,
            ]));

            return;
        }

        EmployeeCareerEvent::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'event_type' => $eventType,
                'request_id' => $requestId,
            ],
            $attributes
        );
    }

    private function upsertSalaryEvent(
        int $employeeId,
        ?int $requestId,
        ?int $masterSalaryId,
        $effectiveAt,
        ?float $gajiLama,
        ?float $tunjLama,
        float $gajiBaru,
        float $tunjBaru,
        ?string $bulanEfektif,
        string $source,
        ?string $noDocument,
        bool $useRequestUnique = true
    ): void {
        $payload = [
            'effective_at' => $this->formatDateValue($effectiveAt),
            'title' => 'Perubahan gaji & tunjangan',
            'old_value' => [
                'gaji_pokok' => $gajiLama,
                'tunjangan_kerja' => $tunjLama,
                'total' => $gajiLama !== null ? $gajiLama + (float) $tunjLama : null,
            ],
            'new_value' => [
                'gaji_pokok' => $gajiBaru,
                'tunjangan_kerja' => $tunjBaru,
                'total' => $gajiBaru + $tunjBaru,
                'bulan_efektif' => $bulanEfektif,
            ],
            'source' => $source,
            'no_document' => $noDocument,
            'master_salary_id' => $masterSalaryId,
        ];

        if ($useRequestUnique && $requestId) {
            EmployeeCareerEvent::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'event_type' => EmployeeCareerEvent::TYPE_SALARY,
                    'request_id' => $requestId,
                ],
                $payload
            );

            return;
        }

        if ($masterSalaryId && EmployeeCareerEvent::where('employee_id', $employeeId)
            ->where('master_salary_id', $masterSalaryId)
            ->where('event_type', EmployeeCareerEvent::TYPE_SALARY)
            ->exists()) {
            return;
        }

        EmployeeCareerEvent::create(array_merge($payload, [
            'employee_id' => $employeeId,
            'event_type' => EmployeeCareerEvent::TYPE_SALARY,
            'request_id' => $requestId,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEventRow(EmployeeCareerEvent $event): array
    {
        $effective = $event->effective_at;
        $old = is_array($event->old_value) ? $event->old_value : [];
        $new = is_array($event->new_value) ? $event->new_value : [];

        return [
            'id' => (int) $event->id,
            'event_type' => $event->event_type,
            'event_type_label' => $this->eventTypeLabel($event->event_type),
            'title' => $event->title ?: $this->eventTypeLabel($event->event_type),
            'effective_at' => $this->formatDateValue($effective),
            'effective_at_label' => $this->formatDateLabel($effective),
            'no_document' => $event->no_document,
            'summary' => $this->buildEventSummary($event->event_type, $old, $new),
            'old_value' => $old,
            'new_value' => $new,
        ];
    }

    private function buildEventSummary(string $type, array $old, array $new): string
    {
        switch ($type) {
            case EmployeeCareerEvent::TYPE_JABATAN:
                return trim(($old['jabatan'] ?? '—') . ' → ' . ($new['jabatan'] ?? '—'));
            case EmployeeCareerEvent::TYPE_STATUS:
                return trim(($old['status_karyawan'] ?? '—') . ' → ' . ($new['status_karyawan'] ?? '—'));
            case EmployeeCareerEvent::TYPE_KONTRAK:
                return trim(($old['tgl_berakhir_kontrak'] ?? '—') . ' → ' . ($new['tgl_berakhir_kontrak'] ?? '—'));
            case EmployeeCareerEvent::TYPE_SALARY:
                $oldTotal = isset($old['total']) ? (float) $old['total'] : null;
                $newTotal = isset($new['total']) ? (float) $new['total'] : null;
                if ($oldTotal !== null && $newTotal !== null) {
                    return 'Total kompensasi ' . $this->formatRupiah($oldTotal) . ' → ' . $this->formatRupiah($newTotal);
                }

                return 'Pembaruan gaji & tunjangan';
            default:
                return '';
        }
    }

    private function eventTypeLabel(string $type): string
    {
        $map = [
            EmployeeCareerEvent::TYPE_JABATAN => 'Jabatan',
            EmployeeCareerEvent::TYPE_STATUS => 'Status karyawan',
            EmployeeCareerEvent::TYPE_KONTRAK => 'Kontrak',
            EmployeeCareerEvent::TYPE_SALARY => 'Kompensasi',
        ];

        return $map[$type] ?? $type;
    }

    private function resolveEffectiveDate(SalaryAdjustmentRequest $record, Carbon $appliedAt): string
    {
        if (!empty($record->tanggal_efektif)) {
            return Carbon::parse($record->tanggal_efektif)->toDateString();
        }

        if (!empty($record->bulan_efektif) && preg_match('/^(\d{4})-(\d{2})/', (string) $record->bulan_efektif, $m)) {
            return sprintf('%s-%s-01', $m[1], $m[2]);
        }

        return $appliedAt->toDateString();
    }

    private function resolveSalaryEffectiveDate($row): ?Carbon
    {
        if (!empty($row->bulan_efektif) && preg_match('/^(\d{4})-(\d{2})/', (string) $row->bulan_efektif, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->startOfDay();
        }

        if (!empty($row->created_at)) {
            return Carbon::parse($row->created_at)->startOfDay();
        }

        return null;
    }

    private function salaryChanged(EmployeeAdjustmentRekap $rekap): bool
    {
        $gajiLama = (float) ($rekap->gaji_lama ?? 0);
        $gajiBaru = (float) ($rekap->gaji_baru ?? 0);
        $tunjLama = (float) ($rekap->tunjangan_lama ?? 0);
        $tunjBaru = (float) ($rekap->tunjangan_baru ?? 0);

        return abs($gajiLama - $gajiBaru) >= 0.005 || abs($tunjLama - $tunjBaru) >= 0.005;
    }

    private function meaningfulStringChange(?string $old, ?string $new): bool
    {
        $a = trim((string) $old);
        $b = trim((string) $new);

        return $a !== '' && $b !== '' && strcasecmp($a, $b) !== 0;
    }

    private function formatTenureLabel(Carbon $start, Carbon $end): string
    {
        $months = $start->diffInMonths($end);
        if ($months < 1) {
            $days = $start->diffInDays($end);

            return $days . ' hari';
        }

        $years = intdiv($months, 12);
        $rem = $months % 12;
        $parts = [];
        if ($years > 0) {
            $parts[] = $years . ' tahun';
        }
        if ($rem > 0) {
            $parts[] = $rem . ' bulan';
        }

        return implode(' ', $parts);
    }

    private function formatDateValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return is_string($value) ? $value : null;
        }
    }

    private function formatDateLabel($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            Carbon::setLocale('id');

            return Carbon::parse($value)->translatedFormat('d F Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatBulanEfektif(?string $value): ?string
    {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})/', $value, $m)) {
            return $value;
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $month = (int) $m[2];

        return ($months[$month] ?? $m[2]) . ' ' . $m[1];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
