<?php

namespace Tests\Unit;

use App\Services\Hr\Greatday\AttendanceCorrectionAbsensiApplyService;
use PHPUnit\Framework\TestCase;

class AttendanceCorrectionAbsensiApplyServiceTest extends TestCase
{
    public function testShsecurity2CheckOutOnShiftDateStoresNextMorningOnPlusOneDay(): void
    {
        $date = AttendanceCorrectionAbsensiApplyService::resolveStorageDate(
            '2026-10-04',
            '08:06:42',
            false,
            'SHSECURITY2',
            'SHSECURITY'
        );

        $this->assertSame('2026-10-05', $date);
    }

    public function testShsecurity2CheckOutOnCalendarMorningUsesSameDateWhenPrevShiftOvernight(): void
    {
        $date = AttendanceCorrectionAbsensiApplyService::resolveStorageDate(
            '2026-10-05',
            '08:06:42',
            false,
            'SHSECURITY2',
            'SHSECURITY2'
        );

        $this->assertSame('2026-10-05', $date);
    }

    public function testCheckInAlwaysUsesCorrectionDate(): void
    {
        $date = AttendanceCorrectionAbsensiApplyService::resolveStorageDate(
            '2026-10-04',
            '19:53:04',
            true,
            'SHSECURITY2',
            null
        );

        $this->assertSame('2026-10-04', $date);
    }

    public function testRegularCheckOutAfternoonUsesSameDate(): void
    {
        $date = AttendanceCorrectionAbsensiApplyService::resolveStorageDate(
            '2026-10-03',
            '20:06:00',
            false,
            'SHSECURITY',
            'OFF'
        );

        $this->assertSame('2026-10-03', $date);
    }
}
