<?php

namespace Tests\Unit;

use App\Services\TicketSkK3KategoriService;
use PHPUnit\Framework\TestCase;

class TicketSkK3KategoriServiceTest extends TestCase
{
    public function testSamplingPointRequiresTheFullTemplateSubcategoryName(): void
    {
        $allowed = ['higiene sanitasi', 'kebisingan', 'pencahayaan'];
        $air = TicketSkK3KategoriService::parseJadwalLabel('Air Higiene Sanitasi - 016');
        $udara = TicketSkK3KategoriService::parseJadwalLabel('Higiene Sanitasi - 016');
        $kebisingan = TicketSkK3KategoriService::parseJadwalLabel('Kebisingan - 003');

        $this->assertFalse(TicketSkK3KategoriService::isAllowedKategoriName($air['nama'], $allowed));
        $this->assertTrue(TicketSkK3KategoriService::isAllowedKategoriName($udara['nama'], $allowed));
        $this->assertTrue(TicketSkK3KategoriService::isAllowedKategoriName($kebisingan['nama'], $allowed));
        $this->assertTrue(TicketSkK3KategoriService::isAllowedKategoriName($air['nama'], ['air higiene sanitasi']));
        $this->assertFalse(TicketSkK3KategoriService::isAllowedKategoriName($udara['nama'], ['air higiene sanitasi']));
    }

    public function testFullTemplateNameStillUsesExistingNormalization(): void
    {
        $label = TicketSkK3KategoriService::parseJadwalLabel('GETARAN (Lengan &amp;  Tangan) - 017');
        $allowed = [TicketSkK3KategoriService::normalizeName('Getaran (Lengan & Tangan)')];

        $this->assertTrue(TicketSkK3KategoriService::isAllowedKategoriName($label['nama'], $allowed));
    }
}
