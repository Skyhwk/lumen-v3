<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollWorkingDaysService;

class PayrollWorkingDaysServiceTest extends \TestCase
{
    public function testSaturdayShiftOffExcludedFromEligibleWorkDaysForSaturdayJabatan(): void
    {
        $this->assertShiftOffRemovesRequiredDay('2026-01-03');
    }

    public function testWeekdayShiftOffExcludedFromEligibleWorkDaysForSaturdayJabatan(): void
    {
        $this->assertShiftOffRemovesRequiredDay('2026-01-05');
    }

    private function assertShiftOffRemovesRequiredDay(string $ymd): void
    {
        $periode = substr($ymd, 0, 7);
        $jabatanMonSat = 94;

        $base = PayrollWorkingDaysService::eligibleWorkDateIndex($periode, $jabatanMonSat);
        if (!isset($base[$ymd])) {
            $this->markTestSkipped("Tanggal {$ymd} tidak ada di index hari kerja (kalender/ENV berbeda).");
        }

        $adjusted = PayrollWorkingDaysService::eligibleWorkDateIndexForKaryawan(
            999999001,
            $periode,
            $jabatanMonSat,
            [['tanggal' => $ymd, 'shift' => 'off']]
        );

        $this->assertArrayNotHasKey($ymd, $adjusted);
        $this->assertSame(count($base) - 1, count($adjusted));
    }
}
