<?php

namespace Tests\Unit;

use App\Models\MasterShiftKaryawan;
use App\Services\Hr\ShiftAbsensiPunchRules;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ShiftAbsensiPunchRulesTest extends TestCase
{
    protected function tearDown(): void
    {
        MasterShiftKaryawan::clearCache();
        parent::tearDown();
    }
    public function testShsecurity2StatusFollowsMesinRule1800(): void
    {
        $this->assertSame('Keluar', ShiftAbsensiPunchRules::inferProductionStatus('SHSECURITY2', '08:06:42'));
        $this->assertSame('Masuk', ShiftAbsensiPunchRules::inferProductionStatus('SHSECURITY2', '19:53:04'));
    }

    public function testRegularShiftUses1400Threshold(): void
    {
        $this->assertSame('Masuk', ShiftAbsensiPunchRules::inferProductionStatus('SHREGULAR', '08:00:00'));
        $this->assertSame('Keluar', ShiftAbsensiPunchRules::inferProductionStatus('SHREGULAR', '17:00:00'));
    }

    public function testShsecurity2KeluarPicksGreatdayMorningPunch(): void
    {
        $rows = Collection::make([
            (object) ['id' => 1, 'jam' => '08:00:00', 'kode_mesin' => null],
            (object) ['id' => 2, 'jam' => '08:06:42', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 3, 'jam' => '19:53:04', 'kode_mesin' => 'GREATDAY'],
        ]);

        $keluar = ShiftAbsensiPunchRules::pickOvernightKeluarRow($rows);
        $this->assertNotNull($keluar);
        $this->assertSame('08:06:42', $keluar->jam);
    }

    public function testShsecurity2MasukPrefersGreatdayEveningOverLaterMesin(): void
    {
        $rows = Collection::make([
            (object) ['id' => 1, 'jam' => '08:06:42', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 2, 'jam' => '19:53:04', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 3, 'jam' => '20:00:00', 'kode_mesin' => 'ISL05'],
        ]);

        $masuk = ShiftAbsensiPunchRules::pickSecurity2MasukRow($rows);
        $this->assertNotNull($masuk);
        $this->assertSame('19:53:04', $masuk->jam);
    }

    public function testOvernightKeluarIgnoresAfternoonIdOrderingBug(): void
    {
        $rowsNext = Collection::make([
            (object) ['id' => 297733, 'jam' => '20:00:00', 'kode_mesin' => 'ISL05'],
            (object) ['id' => 308402, 'jam' => '08:00:00', 'kode_mesin' => null],
            (object) ['id' => 308817, 'jam' => '08:06:42', 'kode_mesin' => 'GREATDAY'],
        ]);

        $keluar = ShiftAbsensiPunchRules::pickOvernightKeluarRow($rowsNext);
        $this->assertSame('08:06:42', $keluar->jam);
    }

    public function testCustomDynamicNightShiftFollowsMasterBounds(): void
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

        // Masuk 21:00 -> masuk_from = 19:00:00, keluar 06:00 -> keluar_before = 12:00:00
        $this->assertSame('Keluar', ShiftAbsensiPunchRules::inferProductionStatus('SHMALAM', '06:15:00'));
        $this->assertSame('Masuk', ShiftAbsensiPunchRules::inferProductionStatus('SHMALAM', '20:45:00'));

        $rowsToday = Collection::make([
            (object) ['id' => 1, 'jam' => '06:15:00', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 2, 'jam' => '20:45:00', 'kode_mesin' => 'GREATDAY'],
            (object) ['id' => 3, 'jam' => '21:02:00', 'kode_mesin' => 'ISL05'],
        ]);
        $masuk = ShiftAbsensiPunchRules::pickNightShiftMasukRow($rowsToday, 'SHMALAM');
        $this->assertNotNull($masuk);
        $this->assertSame('20:45:00', $masuk->jam);
    }
}
