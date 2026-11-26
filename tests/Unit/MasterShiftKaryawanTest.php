<?php

namespace Tests\Unit;

use App\Models\MasterShiftKaryawan;
use App\Services\Payroll\PayrollWorkingDaysService;
use PHPUnit\Framework\TestCase;

class MasterShiftKaryawanTest extends TestCase
{
    protected function tearDown(): void
    {
        MasterShiftKaryawan::clearCache();
        parent::tearDown();
    }

    public function testFallbackCatalogPreservesShsecurity2BoundsAndOvernightDetection(): void
    {
        MasterShiftKaryawan::clearCache();

        $this->assertTrue(MasterShiftKaryawan::isOvernight('SHSECURITY2'));
        $this->assertTrue(MasterShiftKaryawan::isNightShift('SHSECURITY2'));
        $this->assertTrue(MasterShiftKaryawan::isOvernight('24JAM'));
        $this->assertFalse(MasterShiftKaryawan::isNightShift('24JAM'));
        $this->assertFalse(MasterShiftKaryawan::isOvernight('SHREGULAR'));

        $bounds = MasterShiftKaryawan::resolveNightShiftBounds('SHSECURITY2');
        $this->assertSame('18:00:00', $bounds['masuk_from']);
        $this->assertSame('14:00:00', $bounds['keluar_before']);
    }

    public function testCustomDynamicShiftOvernightBoundsAndPayrollModes(): void
    {
        MasterShiftKaryawan::seedCacheForTesting([
            'SHREGULAR' => [
                'id' => 1,
                'kode' => 'SHREGULAR',
                'label' => 'Regular',
                'time_in' => '08:00',
                'time_out' => '17:00',
                'payroll_workday_mode' => MasterShiftKaryawan::PAYROLL_MODE_CALENDAR,
                'is_active' => 1,
            ],
            'SHOB3' => [
                'id' => 2,
                'kode' => 'SHOB3',
                'label' => 'OB Shift 3',
                'time_in' => '11:00',
                'time_out' => '20:00',
                'payroll_workday_mode' => MasterShiftKaryawan::PAYROLL_MODE_MASUK_PER_SHIFT,
                'is_active' => 1,
            ],
            'SHMALAM' => [
                'id' => 3,
                'kode' => 'SHMALAM',
                'label' => 'Shift Malam',
                'time_in' => '21:00',
                'time_out' => '06:00',
                'payroll_workday_mode' => MasterShiftKaryawan::PAYROLL_MODE_PER_SHIFT,
                'is_active' => 1,
            ],
        ]);

        $this->assertTrue(MasterShiftKaryawan::isOvernight('SHMALAM'));
        $this->assertTrue(MasterShiftKaryawan::isNightShift('SHMALAM'));
        $this->assertFalse(MasterShiftKaryawan::isOvernight('SHOB3'));

        $bounds = MasterShiftKaryawan::resolveNightShiftBounds('SHMALAM');
        $this->assertSame('19:00:00', $bounds['masuk_from']);
        $this->assertSame('12:00:00', $bounds['keluar_before']);

        $perShift = PayrollWorkingDaysService::perShiftCodes();
        $masukPerShift = PayrollWorkingDaysService::masukPerShiftCodes();

        $this->assertContains('SHMALAM', $perShift);
        $this->assertContains('24JAM', $perShift);
        $this->assertNotContains('SHOB3', $perShift);

        $this->assertContains('SHMALAM', $masukPerShift);
        $this->assertContains('SHOB3', $masukPerShift);
        $this->assertNotContains('SHREGULAR', $masukPerShift);

        $this->assertTrue(PayrollWorkingDaysService::isMasukPerShift('SHOB3'));
        $this->assertTrue(PayrollWorkingDaysService::isMasukPerShift('SHMALAM'));
        $this->assertFalse(PayrollWorkingDaysService::isMasukPerShift('SHREGULAR'));
    }

    public function testStringNullTimeValuesAreNormalizedSafely(): void
    {
        $this->assertNull(MasterShiftKaryawan::normalizeHm('null'));
        $this->assertNull(MasterShiftKaryawan::normalizeHm(''));
        $this->assertSame('08:00', MasterShiftKaryawan::normalizeHm('8:00'));
        $this->assertSame('20:30', MasterShiftKaryawan::normalizeHm('20:30:00'));
    }
}
