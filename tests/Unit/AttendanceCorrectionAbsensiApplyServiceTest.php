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

    public function testCheckInCorrectionUpdatesLateCheckInWithoutTouchingCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 101, 'tanggal' => '2026-11-25', 'jam' => '09:21:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 102, 'tanggal' => '2026-11-25', 'jam' => '17:05:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $target = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '07:55:00',
            true,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertNotNull($target);
        $this->assertSame(101, $target->id);
    }

    public function testCheckOutCorrectionUpdatesLaterCheckOutWithoutTouchingCheckIn(): void
    {
        $rows = collect([
            (object) ['id' => 201, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 202, 'tanggal' => '2026-11-25', 'jam' => '18:30:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $target = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertNotNull($target);
        $this->assertSame(202, $target->id);
    }

    public function testForgotCheckOutInsertsNewRowInsteadOfOverwritingCheckIn(): void
    {
        $rows = collect([
            (object) ['id' => 401, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
        ]);

        $target = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertNull($target);
    }

    public function testForgotCheckInInsertsNewRowInsteadOfOverwritingCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 411, 'tanggal' => '2026-11-25', 'jam' => '17:15:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        $target = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '07:55:00',
            true,
            'SHREGULAR',
            'SHREGULAR',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );

        $this->assertNull($target);
    }

    public function testMiddayShiftUsesShiftThresholdAndDoesNotOverwriteOppositeSlot(): void
    {
        $shiftRow = (object) ['shift' => 'SHOB2', 'time_in' => '11:00', 'time_out' => '20:00'];

        // Sudah Check In 12:15, lupa Check Out -> koreksi Check Out 20:00 harus INSERT
        $targetOut = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            collect([(object) ['id' => 501, 'tanggal' => '2026-11-25', 'jam' => '12:15:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05']]),
            '2026-11-25',
            '2026-11-25',
            '20:00:00',
            false,
            'SHOB2',
            'SHOB2',
            $shiftRow
        );
        $this->assertNull($targetOut);

        // Lupa Check In, sudah Check Out 20:05 -> koreksi Check In 11:00 harus INSERT
        $targetIn = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            collect([(object) ['id' => 502, 'tanggal' => '2026-11-25', 'jam' => '20:05:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05']]),
            '2026-11-25',
            '2026-11-25',
            '11:00:00',
            true,
            'SHOB2',
            'SHOB2',
            $shiftRow
        );
        $this->assertNull($targetIn);

        // Sudah Check In 12:15 & Check Out 21:15 -> koreksi Check Out 20:00 update 21:15 saja
        $targetOutUpdate = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
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
        $this->assertNotNull($targetOutUpdate);
        $this->assertSame(504, $targetOutUpdate->id);
    }

    public function testDayShiftCheckOutAfterOvernightPrevShiftDoesNotOverwriteMorningOvernightCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 601, 'tanggal' => '2026-11-25', 'jam' => '08:00:00', 'status' => 'Keluar', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 602, 'tanggal' => '2026-11-25', 'jam' => '18:30:00', 'status' => 'Keluar', 'kode_mesin' => 'ISL05'],
        ]);

        // Koreksi pulang shift siang 17:15 -> harus target 18:30 (602), bukan pulang shift malam 08:00 (601)
        $targetDayOut = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '17:15:00',
            false,
            'SHREGULAR',
            'SHSECURITY2',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );
        $this->assertNotNull($targetDayOut);
        $this->assertSame(602, $targetDayOut->id);

        // Koreksi masuk shift siang 08:15 -> tidak boleh timpa pulang shift malam 08:00 (status Keluar)
        $targetDayIn = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '08:15:00',
            true,
            'SHREGULAR',
            'SHSECURITY2',
            (object) ['shift' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00']
        );
        $this->assertNull($targetDayIn);
    }

    public function testNightShiftCheckInCorrectionTargetsEveningPunchWithoutTouchingPreviousMorningCheckOut(): void
    {
        $rows = collect([
            (object) ['id' => 701, 'tanggal' => '2026-11-25', 'jam' => '08:05:00', 'status' => 'Keluar', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 702, 'tanggal' => '2026-11-25', 'jam' => '20:15:00', 'status' => 'Masuk', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 703, 'tanggal' => '2026-11-25', 'jam' => '20:30:00', 'status' => 'Masuk', 'kode_mesin' => 'GREATDAY'],
        ]);

        $target = AttendanceCorrectionAbsensiApplyService::resolveTargetRow(
            $rows,
            '2026-11-25',
            '2026-11-25',
            '19:55:00',
            true,
            'SHSECURITY2',
            'SHSECURITY2',
            (object) ['shift' => 'SHSECURITY2', 'time_in' => '20:00', 'time_out' => '08:00']
        );

        $this->assertNotNull($target);
        $this->assertSame(703, $target->id);
    }
}
