<?php

use App\Services\PromoPengujianAvailability;
use PHPUnit\Framework\TestCase;

class PromoPengujianAvailabilityTest extends TestCase
{
    public function testLifetimeAktifIsLive()
    {
        $promo = (object) ['status' => 'aktif', 'masa_berlaku' => 'lifetime'];
        $this->assertTrue(PromoPengujianAvailability::isLive($promo, '2026-11-04'));
    }

    public function testPeriodeMustAlreadyStart()
    {
        $promo = (object) [
            'status' => 'aktif',
            'masa_berlaku' => 'periode',
            'berlaku_dari' => '2026-11-10',
            'berlaku_sampai' => '2026-12-31',
        ];
        $this->assertFalse(PromoPengujianAvailability::isLive($promo, '2026-11-04'));
        $this->assertTrue(PromoPengujianAvailability::isLive($promo, '2026-11-10'));
    }

    public function testExpiredPeriodeIsNotLive()
    {
        $promo = (object) [
            'status' => 'aktif',
            'masa_berlaku' => 'periode',
            'berlaku_dari' => '2026-01-01',
            'berlaku_sampai' => '2026-11-03',
        ];
        $this->assertFalse(PromoPengujianAvailability::isLive($promo, '2026-11-04'));
    }

    public function testGrandfatheredWhenOrderedOrDataLama()
    {
        $this->assertTrue(PromoPengujianAvailability::isGrandfathered(9, (object) [
            'promo_id' => 9,
            'flag_status' => 'ordered',
            'data_lama' => null,
        ]));
        $this->assertTrue(PromoPengujianAvailability::isGrandfathered(9, (object) [
            'promo_id' => 9,
            'flag_status' => 'emailed',
            'data_lama' => json_encode(['no_order' => 'ISL/001']),
        ]));
        $this->assertFalse(PromoPengujianAvailability::isGrandfathered(9, (object) [
            'promo_id' => 9,
            'flag_status' => 'emailed',
            'data_lama' => null,
        ]));
        $this->assertFalse(PromoPengujianAvailability::isGrandfathered(9, (object) [
            'promo_id' => 8,
            'flag_status' => 'ordered',
            'data_lama' => null,
        ]));
    }
}
