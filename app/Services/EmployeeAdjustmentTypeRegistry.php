<?php

namespace App\Services;

class EmployeeAdjustmentTypeRegistry
{
    public const DOCUMENT_PREFIX = 'PPK';

    public const TYPE_PENYESUAIAN_GAJI = 'penyesuaian_gaji';
    public const TYPE_PROMOSI = 'promosi';
    public const TYPE_DEMOSI = 'demosi';
    public const TYPE_PENGANGKATAN_TETAP = 'pengangkatan_tetap';
    public const TYPE_PENGANGKATAN_KONTRAK = 'pengangkatan_kontrak';
    public const TYPE_PERPANJANG_KONTRAK = 'perpanjang_kontrak';
    public const TYPE_PERPANJANG_PELATIHAN = 'perpanjang_pelatihan';
    public const TYPE_GAGAL_PELATIHAN = 'gagal_pelatihan';
    public const TYPE_PHK = 'phk';
    public const TYPE_PENSIUN = 'pensiun';
    public const TYPE_MUTASI = 'mutasi';

    public const WORKFLOW_FULL = 'full';
    public const WORKFLOW_EXIT = 'exit';
    public const WORKFLOW_MUTASI = 'mutasi';

    public const STATUS_KARYAWAN_PERMANENT = 'Permanent';
    public const STATUS_KARYAWAN_CONTRACT = 'Contract';
    public const STATUS_KARYAWAN_TRAINING = 'Training';

    /** @var array<string, array<string, mixed>> */
    private const DEFINITIONS = [
        self::TYPE_PENYESUAIAN_GAJI => [
            'label' => 'Penyesuaian Upah Kerja',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => true,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => null,
        ],
        self::TYPE_PROMOSI => [
            'label' => 'Promosi',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => null,
        ],
        self::TYPE_DEMOSI => [
            'label' => 'Demosi',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => true,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => null,
        ],
        self::TYPE_PENGANGKATAN_TETAP => [
            'label' => 'Pengangkatan Karyawan Tetap',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => self::STATUS_KARYAWAN_PERMANENT,
        ],
        self::TYPE_PENGANGKATAN_KONTRAK => [
            'label' => 'Pengangkatan Karyawan Kontrak',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => self::STATUS_KARYAWAN_CONTRACT,
        ],
        self::TYPE_PERPANJANG_KONTRAK => [
            'label' => 'Perpanjang Masa Kontrak',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => null,
        ],
        self::TYPE_PERPANJANG_PELATIHAN => [
            'label' => 'Perpanjang Masa Pelatihan',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => self::STATUS_KARYAWAN_TRAINING,
        ],
        self::TYPE_GAGAL_PELATIHAN => [
            'label' => 'Gagal Masa Pelatihan',
            'workflow_profile' => self::WORKFLOW_EXIT,
            'kpi_required' => false,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => false,
            'requires_assessment' => false,
            'requires_counseling' => false,
            'target_status_karyawan' => null,
        ],
        self::TYPE_PHK => [
            'label' => 'PHK',
            'workflow_profile' => self::WORKFLOW_EXIT,
            'kpi_required' => false,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => false,
            'requires_assessment' => false,
            'requires_counseling' => false,
            'target_status_karyawan' => null,
        ],
        self::TYPE_PENSIUN => [
            'label' => 'Penetapan Karyawan Pensiun',
            'workflow_profile' => self::WORKFLOW_FULL,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => false,
            'requires_finance_when_salary' => false,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => null,
        ],
        self::TYPE_MUTASI => [
            'label' => 'Mutasi',
            'workflow_profile' => self::WORKFLOW_MUTASI,
            'kpi_required' => true,
            'salary_required' => false,
            'allows_negative_salary' => true,
            'requires_finance_when_salary' => true,
            'requires_assessment' => true,
            'requires_counseling' => true,
            'target_status_karyawan' => null,
        ],
    ];

    public static function all(): array
    {
        return self::DEFINITIONS;
    }

    public static function allTypes(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function isValid(?string $type): bool
    {
        return isset(self::DEFINITIONS[self::normalizeType($type)]);
    }

    public static function get(?string $type): ?array
    {
        $normalized = self::normalizeType($type);

        return self::DEFINITIONS[$normalized] ?? null;
    }

    public static function label(?string $type): string
    {
        return self::get($type)['label'] ?? ucfirst(str_replace('_', ' ', (string) $type));
    }

    public static function workflowProfile(?string $type): string
    {
        return self::get($type)['workflow_profile'] ?? self::WORKFLOW_FULL;
    }

    public static function documentPrefix(?string $type = null): string
    {
        return self::DOCUMENT_PREFIX;
    }

    public static function kpiRequired(?string $type): bool
    {
        return (bool) (self::get($type)['kpi_required'] ?? true);
    }

    public static function salaryRequired(?string $type): bool
    {
        return (bool) (self::get($type)['salary_required'] ?? false);
    }

    public static function allowsNegativeSalary(?string $type): bool
    {
        return (bool) (self::get($type)['allows_negative_salary'] ?? false);
    }

    public static function requiresAssessment(?string $type): bool
    {
        return (bool) (self::get($type)['requires_assessment'] ?? true);
    }

    public static function requiresCounseling(?string $type): bool
    {
        return (bool) (self::get($type)['requires_counseling'] ?? true);
    }

    public static function targetStatusKaryawan(?string $type): ?string
    {
        return self::get($type)['target_status_karyawan'] ?? null;
    }

    public static function listForApi(): array
    {
        return collect(self::DEFINITIONS)->map(function (array $definition, string $type) {
            return [
                'value' => $type,
                'label' => $definition['label'],
                'workflow_profile' => $definition['workflow_profile'],
                'kpi_required' => $definition['kpi_required'],
                'salary_required' => $definition['salary_required'],
                'allows_negative_salary' => $definition['allows_negative_salary'],
            ];
        })->values()->all();
    }

    public static function normalizeType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return $normalized !== '' ? $normalized : self::TYPE_PENYESUAIAN_GAJI;
    }
}
