<?php

namespace App\Support\ClaimFeeExternal;

final class ClaimFeeExternalWorkflow
{
    public const SALES_SUBMITTED = 'SUBMITTED';
    public const SALES_PENDING_SPV = 'PENDING_SPV';
    public const SALES_PENDING_MANAGER = 'PENDING_MANAGER';
    public const SALES_PENDING_SENIOR_MANAGER = 'PENDING_SENIOR_MANAGER';
    public const SALES_APPROVED = 'APPROVED_SALES';
    public const SALES_REJECTED = 'REJECTED_SALES';
    public const SALES_CANCELLED = 'CANCELLED';

    public const FINANCE_WAITING_TAX = 'WAITING_TAX';
    public const FINANCE_TAX_IN_PROGRESS = 'TAX_IN_PROGRESS';
    public const FINANCE_READY_TO_PAY = 'READY_TO_PAY';
    public const FINANCE_PAID = 'PAID';
    public const FINANCE_REJECTED = 'REJECTED_FINANCE';

    public const STAGE_SPV = 'SPV';
    public const STAGE_MANAGER = 'MANAGER';
    public const STAGE_SENIOR_MANAGER = 'SENIOR_MANAGER';

    public static function pendingStatusForStage(string $stage): string
    {
        switch ($stage) {
            case self::STAGE_SPV:
                return self::SALES_PENDING_SPV;
            case self::STAGE_MANAGER:
                return self::SALES_PENDING_MANAGER;
            case self::STAGE_SENIOR_MANAGER:
                return self::SALES_PENDING_SENIOR_MANAGER;
            default:
                return self::SALES_SUBMITTED;
        }
    }

    public static function stageLabel(string $stage): string
    {
        switch ($stage) {
            case self::STAGE_SPV:
                return 'SPV';
            case self::STAGE_MANAGER:
                return 'Manager';
            case self::STAGE_SENIOR_MANAGER:
                return 'Senior Manager';
            default:
                return $stage;
        }
    }

    public static function gradeToStage(string $grade): ?string
    {
        $g = strtoupper(str_replace('_', ' ', trim($grade)));
        if ($g === 'SPV') {
            $g = 'SUPERVISOR';
        }

        switch ($g) {
            case 'SUPERVISOR':
                return self::STAGE_SPV;
            case 'MANAGER':
                return self::STAGE_MANAGER;
            case 'SENIOR MANAGER':
                return self::STAGE_SENIOR_MANAGER;
            default:
                return null;
        }
    }

    /** @return list<string> */
    public static function financeLockedStatuses(): array
    {
        return [
            self::FINANCE_WAITING_TAX,
            self::FINANCE_TAX_IN_PROGRESS,
            self::FINANCE_READY_TO_PAY,
            self::FINANCE_PAID,
            self::FINANCE_REJECTED,
        ];
    }
}