<?php

namespace Tests\Unit;

use App\Services\Hr\MonthlyAbsensiDataBuilder;
use App\Services\Hr\MonthlyAbsensiGenerateOrchestrator;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\TestCase;

class MonthlyAbsensiGenerateOrchestratorTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider forceProvider
     */
    public function testAutomaticGenerateOnlyPersistsRekap(bool $skipExisting, bool $rekapAppeared): void
    {
        // Any access to Absensi by the persistence path must fail this test.
        $absensi = Mockery::mock('alias:App\\Models\\Absensi');
        $absensi->shouldNotReceive('where');
        $absensi->shouldNotReceive('whereIn');
        $absensi->shouldNotReceive('insert');

        $employees = Mockery::mock('alias:App\\Models\\MasterKaryawan');
        $employeeQuery = Mockery::mock();
        $employees->shouldReceive('query')->once()->andReturn($employeeQuery);
        $employeeQuery->shouldReceive('select')->with('id', 'nik_karyawan', 'nama_lengkap', 'grade')->andReturnSelf();
        $employeeQuery->shouldReceive('where')->with('is_active', true)->andReturnSelf();
        $employeeQuery->shouldReceive('whereIn')->with('grade', ['STAFF', 'SUPERVISOR'])->andReturnSelf();
        $employeeQuery->shouldReceive('orderBy')->with('id')->andReturnSelf();
        $employeeQuery->shouldReceive('get')->andReturn([(object) [
            'id' => 7, 'nik_karyawan' => 'EMP7', 'nama_lengkap' => 'Employee',
        ]]);
        $lockQuery = Mockery::mock();
        $employees->shouldReceive('where')->with('id', 7)->once()->andReturn($lockQuery);
        $lockQuery->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $lockQuery->shouldReceive('first')->once()->andReturn((object) ['id' => 7]);

        $rekap = Mockery::mock('alias:App\\Models\\RekapMasukKerja');
        $rekapQuery = Mockery::mock();
        $rekap->shouldReceive('where')->with('karyawan_id', 7)->andReturn($rekapQuery);
        $rekapQuery->shouldReceive('where')->with('tahun', '2026')->andReturnSelf();
        $rekapQuery->shouldReceive('where')->with('bulan', '2026-09')->andReturnSelf();
        $rekapQuery->shouldReceive('where')->with('is_active', true)->andReturnSelf();
        if ($skipExisting) {
            $rekapQuery->shouldReceive('exists')->twice()->andReturn(false, $rekapAppeared);
        }
        $rekapQuery->shouldReceive('first')->andReturn(null);
        $insert = $rekap->shouldReceive('insert');
        $rekapAppeared ? $insert->never() : $insert->once();
        $insert->withArgs(function ($row) {
            return $row['karyawan_id'] === 7
                && $row['bulan'] === '2026-09'
                && json_decode($row['tanggal'], true) === ['2026-09-01']
                && $row['added_by'] === null;
        })->andReturn(true);

        $calendar = Mockery::mock('alias:App\\Models\\RekapLiburKalender');
        $calendarQuery = Mockery::mock();
        $calendar->shouldReceive('where')->with('tahun', '2026')->andReturn($calendarQuery);
        $calendarQuery->shouldReceive('where')->with('is_active', true)->andReturnSelf();
        $calendarQuery->shouldReceive('first')->andReturn((object) ['tahun' => '2026']);

        $builder = Mockery::mock(MonthlyAbsensiDataBuilder::class);
        $builder->shouldReceive('buildForKaryawan')->with(7, '2026-09')->once()->andReturn([
            [
                'karyawan_id' => 7, 'tanggal' => '2026-09-01', 'shift' => 'SHREGULAR',
                'masuk' => '08:01:45', 'keluar' => '17:00:32',
                'tgl_masuk' => '2026-09-01', 'tgl_keluar' => '2026-09-01',
                'id_masuk' => 10, 'id_keluar' => 11,
            ],
            ['tanggal' => '2026-09-02', 'shift' => 'SHREGULAR', 'masuk' => '08:00', 'keluar' => ''],
            ['tanggal' => '2026-09-03', 'shift' => 'OFF', 'masuk' => '', 'keluar' => ''],
        ]);

        $database = Mockery::mock();
        $database->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });
        DB::swap($database);

        try {
            $summary = (new MonthlyAbsensiGenerateOrchestrator($builder))
                ->generateForStaffSupervisor('2026-09', $skipExisting);
            $this->assertSame($rekapAppeared ? 0 : 1, $summary['generated']);
            $this->assertSame($rekapAppeared ? 1 : 0, $summary['skipped']);
            $this->assertSame(0, $summary['failed']);
        } finally {
            Mockery::close();
            DB::clearResolvedInstances();
        }
    }

    public static function forceProvider(): array
    {
        return [
            'normal' => [true, false],
            'force' => [false, false],
            'another process generated before lock acquired' => [true, true],
        ];
    }

    /** @dataProvider previousMonthProvider */
    public function testPreviousMonthUsesCalendarMonth(string $asOf, string $expected): void
    {
        $this->assertSame($expected, (new MonthlyAbsensiGenerateOrchestrator())->resolveTargetBulanYm($asOf));
    }

    public static function previousMonthProvider(): array
    {
        return [
            ['2026-03-31', '2026-02'],
            ['2024-03-31', '2024-02'],
            ['2026-05-31', '2026-04'],
            ['2026-01-01', '2025-12'],
            ['2026-11-01', '2026-10'],
        ];
    }
}
