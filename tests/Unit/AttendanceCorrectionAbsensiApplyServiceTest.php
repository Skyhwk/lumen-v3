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

    public function testCustomDynamicNightShiftCheckOutStoresOnNextDay(): void
    {
        MasterShiftKaryawan::seedCacheForTesting([
            'SHMALAM' => [
                'id' => 99,
                'kode' => 'SHMALAM',
                'label' => 'Shift Malam Custom',
                'time_in' => '21:00',
                'time_out' => '06:00',
                'payroll_workday_mode' => MasterShiftKaryawan::PAYROLL_MODE_PER_SHIFT,
                'is_active' => 1,
            ],
        ]);

        $date = AttendanceCorrectionAbsensiApplyService::resolveStorageDate(
            '2026-11-24',
            '06:10:00',
            false,
            'SHMALAM',
            'SHREGULAR'
        );

        $this->assertSame('2026-11-25', $date);
    }

    public function testCheckInCorrectionUpdatesLateCheckInWithoutTouchingCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 101, 'tanggal' => '2026-11-25', 'jam' => '09:21:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 102, 'tanggal' => '2026-11-25', 'jam' => '17:05:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '07:55:00',
            true,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertSame(101, $slot['primary']->id);
        $this->assertSame([], $slot['duplicateIds']);
    }

    public function testCheckOutCorrectionUpdatesLaterCheckOutWithoutTouchingCheckIn(): void
    {
        $rows = collect([
            (object) ['id' => 201, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 202, 'tanggal' => '2026-11-25', 'jam' => '18:30:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertSame(202, $slot['primary']->id);
        $this->assertSame([], $slot['duplicateIds']);
    }

    public function testCheckOutCorrectionDeduplicatesMultipleCheckOutPunchesSoMaxJamMatchesCorrection(): void
    {
        $rows = collect([
            (object) ['id' => 301, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 302, 'tanggal' => '2026-11-25', 'jam' => '17:45:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 303, 'tanggal' => '2026-11-25', 'jam' => '18:30:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertSame(303, $slot['primary']->id);
        $this->assertSame([302], $slot['duplicateIds']);
    }

    public function testCheckInCorrectionDeduplicatesMultipleCheckInPunchesSoMinJamMatchesCorrection(): void
    {
        $rows = collect([
            (object) ['id' => 311, 'tanggal' => '2026-11-25', 'jam' => '07:30:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 312, 'tanggal' => '2026-11-25', 'jam' => '08:05:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 313, 'tanggal' => '2026-11-25', 'jam' => '17:00:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '08:15:00',
            true,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertSame(311, $slot['primary']->id);
        $this->assertSame([312], $slot['duplicateIds']);
    }

    public function testForgotCheckOutInsertsNewRowInsteadOfOverwritingCheckIn(): void
    {
        $rows = collect([
            (object) ['id' => 401, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertNull($slot['primary']);
        $this->assertSame([], $slot['duplicateIds']);
    }

    public function testForgotCheckInInsertsNewRowInsteadOfOverwritingCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 411, 'tanggal' => '2026-11-25', 'jam' => '17:15:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '07:55:00',
            true,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertNull($slot['primary']);
        $this->assertSame([], $slot['duplicateIds']);
    }

    public function testMiddayShiftUsesShiftThresholdAndDoesNotOverwriteOppositeSlot(): void
    {
        $shiftRow = (object) ['shift' => 'SHOB2', 'time_in' => '11:00', 'time_out' => '20:00'];

        // Sudah Check In 12:15, lupa Check Out -> koreksi Check Out 20:00 harus INSERT
        $slotOut = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            collect([(object) ['id' => 501, 'tanggal' => '2026-11-25', 'jam' => '12:15:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05']]),
            '2026-11-25',
            '2026-11-25',
            '20:00:00',
            false,
            'SHOB2',
            'SHOB2',
            $shiftRow
        );
        $this->assertNull($slotOut['primary']);

        // Lupa Check In, sudah Check Out 20:05 -> koreksi Check In 11:00 harus INSERT
        $slotIn = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            collect([(object) ['id' => 502, 'tanggal' => '2026-11-25', 'jam' => '20:05:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05']]),
            '2026-11-25',
            '2026-11-25',
            '11:00:00',
            true,
            'SHOB2',
            'SHOB2',
            $shiftRow
        );
        $this->assertNull($slotIn['primary']);

        // Sudah Check In 12:15 & Check Out 21:15 -> koreksi Check Out 20:00 update 21:15 saja
        $slotOutUpdate = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            collect([
                (object) ['id' => 503, 'tanggal' => '2026-11-25', 'jam' => '12:15:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
                (object) ['id' => 504, 'tanggal' => '2026-11-25', 'jam' => '21:15:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
            ]),
            '2026-11-25',
            '2026-11-25',
            '20:00:00',
            false,
            'SHOB2',
            'SHOB2',
            $shiftRow
        );
        $this->assertSame(504, $slotOutUpdate['primary']->id);
        $this->assertSame([], $slotOutUpdate['duplicateIds']);
    }

    public function testDayShiftCheckOutAfterOvernightPrevShiftDoesNotOverwriteMorningOvernightCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 601, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Keluar', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 602, 'tanggal' => '2026-11-25', 'jam' => '18:30:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        // Koreksi pulang shift siang 17:15 -> harus target 18:30 (602), bukan pulang shift malam 08:00 (601)
        $slotDayOut = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHSECURITY2',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00'],
            (object) ['shift' => 'SHSECURITY2', 'time_in' => '20:00', 'time_out' => '08:00']
        );
        $this->assertSame(602, $slotDayOut['primary']->id);
        $this->assertSame([], $slotDayOut['duplicateIds']);

        // Koreksi masuk shift siang 08:15 -> tidak boleh timpa pulang shift malam 08:00 (status Keluar)
        $slotDayIn = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '08:15:00',
            true,
            'SHREGULAR',
            'SHSECURITY2',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00'],
            (object) ['shift' => 'SHSECURITY2', 'time_in' => '20:00', 'time_out' => '08:00']
        );
        $this->assertNull($slotDayIn['primary']);
    }

    public function testNightShiftCheckInCorrectionDoesNotTouchPreviousMorningCheckOutAndCleansEveningDuplicates(): void
    {
        $rows = collect([
            (object) ['id' => 701, 'tanggal' => '2026-11-25', 'jam' => '08:05:00', 'status' => 'Keluar', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 702, 'tanggal' => '2026-11-25', 'jam' => '20:15:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 703, 'tanggal' => '2026-11-25', 'jam' => '20:30:00', 'status' => 'Masuk', 'kode_mesin' => 'GREATDAY'],
        ]);

        $slot = AttendanceCorrectionAbsensiApplyService::resolveSlotSelection(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '19:55:00',
            true,
            'SHSECURITY2',
            'SHSECURITY2',
            (object) ['shift' => 'SHSECURITY2', 'time_in' => '20:00', 'time_out' => '08:00'],
            (object) ['shift' => 'SHSECURITY2', 'time_in' => '20:00', 'time_out' => '08:00']
        );

        $this->assertSame(703, $slot['primary']->id);
        $this->assertSame([702], $slot['duplicateIds']);
    }
}
