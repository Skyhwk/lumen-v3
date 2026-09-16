<?php

namespace Tests\Unit;

use App\Services\DashboardAbsensiKaryawanService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DashboardAbsensiKaryawanServiceTest extends TestCase
{
    private function call(string $method, ...$args)
    {
        $reflection = new ReflectionMethod(DashboardAbsensiKaryawanService::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs(new DashboardAbsensiKaryawanService(), $args);
    }

    private function employee(int $id, array $days, array $shifts = []): array
    {
        $punches = collect();
        foreach ($days as $date => $times) {
            $punches->put($id . '|' . $date, collect(array_map(fn ($time) => (object) ['jam' => $time], $times)));
        }
        $assignments = collect();
        foreach ($shifts as $date => $shift) {
            $assignments->put($id . '|' . $date, collect([(object) $shift]));
        }
        return $this->call('summarizeEmployee', (object) [
            'id' => $id, 'nama_lengkap' => 'Employee ' . $id, 'nik_karyawan' => 'N' . $id,
            'jabatan' => 'Staff', 'image' => null, 'department' => 'Kantor', 'id_department' => 1,
        ], ['start' => Carbon::parse('2026-09-14'), 'end' => Carbon::parse('2026-09-16')], $assignments, $punches);
    }

    public function testEarlyArrivalDoesNotCancelLateDays(): void
    {
        $row = $this->employee(1, ['2026-09-14' => ['07:30', '17:30'], '2026-09-15' => ['08:30', '16:30']]);
        $this->assertSame(0, $row['avg_in_delta']);
        $this->assertEquals(50, $row['on_time_rate']);
        $this->assertSame(1, $row['late_in_days']);
        $this->assertSame(1800, $row['late_in_avg_seconds']);
        $this->assertSame(1, $row['late_out_days']);
        $this->assertSame(1800, $row['late_out_avg_seconds']);
    }

    public function testOfficeAndNightShiftCompeteWithTheirOwnSchedules(): void
    {
        $office = $this->employee(1, ['2026-09-14' => ['07:50', '18:00']]);
        $night = $this->employee(2, ['2026-09-14' => ['21:50', '21:55'], '2026-09-15' => ['11:00']], [
            '2026-09-14' => ['shift' => 'SHSECURITY2', 'time_in' => '22:00', 'time_out' => '10:00'],
            '2026-09-15' => ['shift' => 'OFF', 'time_in' => null, 'time_out' => null],
            '2026-09-16' => ['shift' => 'OFF', 'time_in' => null, 'time_out' => null],
        ]);
        $this->assertSame($office['on_time_avg_seconds'], $night['on_time_avg_seconds']);
        $this->assertSame(3600, $night['late_out_avg_seconds']);
        $ranking = $this->call('buildRankings', collect([$night, $office]));
        $this->assertSame([1, 2], $ranking['on_time']->pluck('id')->all());
        $this->assertSame([1, 2], $ranking['late_out']->pluck('id')->all());
    }

    public function testDailyRotatingShiftUsesEachDaysSchedule(): void
    {
        $row = $this->employee(1, ['2026-09-14' => ['07:50', '17:00'], '2026-09-15' => ['09:50', '19:00']], [
            '2026-09-15' => ['shift' => 'SHTEKNISI', 'time_in' => '10:00', 'time_out' => '19:00'],
        ]);
        $this->assertSame(2, $row['on_time_days']);
        $this->assertSame(600, $row['on_time_avg_seconds']);
        $this->assertSame(0, $row['late_out_days']);
    }

    public function testMissingPunchesHaveSeparateDenominatorsAndNoFalseCandidates(): void
    {
        $row = $this->employee(1, ['2026-09-14' => ['08:00'], '2026-09-15' => ['16:00']]);
        $this->assertSame(2, $row['attendance_days']);
        $this->assertSame(2, $row['partial_days']);
        $this->assertSame(1, $row['in_days']);
        $this->assertSame(1, $row['out_days']);
        $this->assertEquals(100, $row['on_time_rate']);
        $empty = $this->employee(2, []);
        $this->assertNull($empty['on_time_rate']);
        $ranks = $this->call('buildRankings', collect([$row, $empty]));
        $this->assertSame([1], $ranks['on_time']->pluck('id')->all());
        $this->assertTrue($ranks['late_in']->isEmpty());
        $this->assertTrue($ranks['late_out']->isEmpty());
    }

    public function testRankingPrioritizesRateThenDaysThenDuration(): void
    {
        $oneDay = $this->employee(1, ['2026-09-14' => ['07:00']]);
        $twoDays = $this->employee(2, ['2026-09-14' => ['07:50'], '2026-09-15' => ['07:50']]);
        $twoEarlierDays = $this->employee(3, ['2026-09-14' => ['07:40'], '2026-09-15' => ['07:40']]);
        $lessConsistent = $this->employee(4, ['2026-09-14' => ['07:00'], '2026-09-15' => ['07:00'], '2026-09-16' => ['08:01']]);
        $ranks = $this->call('buildRankings', collect([$oneDay, $twoDays, $twoEarlierDays, $lessConsistent]));
        $this->assertSame([3, 2, 1, 4], $ranks['on_time']->pluck('id')->all());
        $this->assertSame([4], $ranks['late_in']->pluck('id')->all());
    }

    public function testCurrentWeekAndMonthAreAvailableOnFirstDay(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'Asia/Jakarta'));
        try {
            foreach (['weekly', 'monthly'] as $period) {
                $range = $this->call('resolvePeriod', $period, 0);
                $this->assertSame('2026-06-01', $range['start']->toDateString());
                $this->assertSame('2026-06-01', $range['end']->toDateString());
            }
            $previous = $this->call('resolvePeriod', 'monthly', 0, '2026-05');
            $this->assertSame('2026-05-31', $previous['end']->toDateString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testNightCheckoutAfterPeriodEndBelongsToLastShiftDay(): void
    {
        $row = $this->employee(1, ['2026-09-16' => ['21:55'], '2026-09-17' => ['10:30']], [
            '2026-09-16' => ['shift' => 'SHSECURITY2', 'time_in' => '22:00', 'time_out' => '10:00'],
        ]);
        $this->assertSame(1, $row['in_days']);
        $this->assertSame(1, $row['out_days']);
        $this->assertSame(1, $row['attendance_days']);
        $this->assertSame(1800, $row['late_out_avg_seconds']);
    }

    public function testLateAndAfterScheduleRankFrequencyBeforeDuration(): void
    {
        $frequent = $this->employee(1, ['2026-09-14' => ['08:01', '17:01'], '2026-09-15' => ['08:01', '17:01']]);
        $longer = $this->employee(2, ['2026-09-14' => ['09:00', '19:00'], '2026-09-15' => ['08:00', '17:00']]);
        $oneDay = $this->employee(3, ['2026-09-14' => ['09:00', '19:00']]);
        $ranks = $this->call('buildRankings', collect([$longer, $oneDay, $frequent]));
        $this->assertSame([1, 3, 2], $ranks['late_in']->pluck('id')->all());
        $this->assertSame([1, 3, 2], $ranks['late_out']->pluck('id')->all());
    }
}
