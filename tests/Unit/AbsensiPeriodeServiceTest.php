<?php

namespace Tests\Unit;

use App\Services\Hr\AbsensiPeriodeService;
use PHPUnit\Framework\TestCase;

class AbsensiPeriodeServiceTest extends TestCase
{
    public function testResolveRangeStaffUsesCalendarMonth(): void
    {
        $range = AbsensiPeriodeService::resolveRange('2025-10', AbsensiPeriodeService::GRADE_STAFF);

        $this->assertSame('2025-10-01', $range['start_ymd']);
        $this->assertSame('2025-10-31', $range['end_ymd']);
    }

    public function testResolveRangeSupervisorUsesSixteenToFifteen(): void
    {
        $range = AbsensiPeriodeService::resolveRange('2025-10', AbsensiPeriodeService::GRADE_SUPERVISOR);

        $this->assertSame('2025-10-16', $range['start_ymd']);
        $this->assertSame('2025-11-15', $range['end_ymd']);
    }

    /** @dataProvider supervisorTargetProvider */
    public function testResolveTargetBulanYmSupervisor(string $asOf, string $expected): void
    {
        $this->assertSame(
            $expected,
            AbsensiPeriodeService::resolveTargetBulanYmSupervisor($asOf)
        );
    }

    public static function supervisorTargetProvider(): array
    {
        return [
            'on 16th — Oct payroll just closed' => ['2025-11-16', '2025-10'],
            'after 16th' => ['2025-11-20', '2025-10'],
            'before 16th — Sep payroll last closed' => ['2025-11-10', '2025-09'],
            'first day of month' => ['2025-12-01', '2025-10'],
        ];
    }

    /** @dataProvider staffTargetProvider */
    public function testResolveTargetBulanYmStaff(string $asOf, string $expected): void
    {
        $this->assertSame(
            $expected,
            AbsensiPeriodeService::resolveTargetBulanYmStaff($asOf)
        );
    }

    public static function staffTargetProvider(): array
    {
        return [
            ['2026-03-31', '2026-02'],
            ['2026-01-01', '2025-12'],
            ['2026-11-01', '2026-10'],
        ];
    }

    public function testNormalizeGradeAcceptsSpvAlias(): void
    {
        $this->assertSame(
            AbsensiPeriodeService::GRADE_SUPERVISOR,
            AbsensiPeriodeService::normalizeGrade('spv')
        );
    }
}