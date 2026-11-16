<?php

namespace Tests\Unit;

use App\Services\Hr\ShiftAbsensiPunchRules;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ShiftAbsensiPunchRulesTest extends TestCase
{
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
}
