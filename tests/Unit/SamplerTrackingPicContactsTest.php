<?php

use App\Services\SamplerTrackingPicContacts;
use PHPUnit\Framework\TestCase;

class SamplerTrackingPicContactsTest extends TestCase
{
    public function testNormalizeKeepsCompleteRows()
    {
        $contacts = SamplerTrackingPicContacts::normalize([
            ['nama' => ' Budi ', 'jabatan' => 'HSSE', 'telepon' => '0812 3456'],
            ['nama' => '', 'jabatan' => '', 'telepon' => ''],
        ]);

        $this->assertSame([
            ['nama' => 'Budi', 'jabatan' => 'HSSE', 'telepon' => '08123456'],
        ], $contacts);
        $this->assertTrue(SamplerTrackingPicContacts::isFilled($contacts));
        $this->assertSame([
            'pic' => $contacts,
            'dokumentasi' => ['sampler_tracking/pic.jpg'],
        ], SamplerTrackingPicContacts::payload([
            'pic' => [
                ['nama' => ' Budi ', 'jabatan' => 'HSSE', 'telepon' => '0812 3456'],
            ],
            'dokumentasi' => ['sampler_tracking/pic.jpg', ''],
        ]));
        $this->assertTrue(SamplerTrackingPicContacts::isFilled([
            'pic' => $contacts,
            'dokumentasi' => [],
        ]));
    }

    public function testIncompleteRowIsRejectedWhenJabatanMissing()
    {
        $this->expectException(\InvalidArgumentException::class);
        SamplerTrackingPicContacts::normalize([
            ['nama' => 'Budi', 'jabatan' => '', 'telepon' => '0812'],
        ]);
    }

    public function testTeleponIsOptional()
    {
        $contacts = SamplerTrackingPicContacts::normalize([
            ['nama' => 'Budi', 'jabatan' => 'HSSE', 'telepon' => ''],
        ]);

        $this->assertSame([
            ['nama' => 'Budi', 'jabatan' => 'HSSE', 'telepon' => ''],
        ], $contacts);
        $this->assertTrue(SamplerTrackingPicContacts::isFilled($contacts));
    }
}
