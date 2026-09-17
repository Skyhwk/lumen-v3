<?php

namespace Tests\Unit;

use App\Services\BasDocumentScope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BasDocumentScopeTest extends TestCase
{
    private const SAMPLING_DATE = '2026-01-15';

    public function testSamplesNormalizesShortCodes(): void
    {
        $result = BasDocumentScope::samples(['012', '013'], 'ESTX012601');

        $this->assertSame(['ESTX012601/012', 'ESTX012601/013'], $result);
    }

    public function testSamplesRejectsMismatchedOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BasDocumentScope::samples(['OTHER/012'], 'ESTX012601');
    }

    public function testValidDecisionAcceptsDilanjutkanWithoutDate(): void
    {
        $this->assertTrue(BasDocumentScope::validDecision((object) ['status' => 'Dilanjutkan'], self::SAMPLING_DATE));
        $this->assertTrue(BasDocumentScope::validDecision((object) [
            'status' => 'Dilanjutkan',
            'tanggal_dilanjutkan' => null,
        ], self::SAMPLING_DATE));
        $this->assertTrue(BasDocumentScope::validDecision((object) [
            'status' => 'Dilanjutkan',
            'tanggal_dilanjutkan' => '',
        ], self::SAMPLING_DATE));
    }

    public function testValidDecisionValidatesOptionalFutureDate(): void
    {
        $this->assertTrue(BasDocumentScope::validDecision((object) [
            'status' => 'Dilanjutkan',
            'tanggal_dilanjutkan' => '2026-02-01',
        ], self::SAMPLING_DATE));

        $this->assertFalse(BasDocumentScope::validDecision((object) [
            'status' => 'Dilanjutkan',
            'tanggal_dilanjutkan' => '2026-01-01',
        ], self::SAMPLING_DATE));
    }

    public function testValidDecisionForBelumSelesai(): void
    {
        $this->assertTrue(BasDocumentScope::validDecision((object) [
            'status' => 'Belum Selesai',
            'alasan' => 'Sample di pick up',
        ], self::SAMPLING_DATE));

        $this->assertTrue(BasDocumentScope::validDecision((object) [
            'status' => 'Belum Selesai',
            'alasan' => 'Lainnya',
            'keterangan' => 'Kendala lapangan',
        ], self::SAMPLING_DATE));

        $this->assertFalse(BasDocumentScope::validDecision((object) [
            'status' => 'Belum Selesai',
            'alasan' => 'Lainnya',
            'keterangan' => '',
        ], self::SAMPLING_DATE));

        $this->assertFalse(BasDocumentScope::validDecision((object) [
            'status' => 'Belum Selesai',
            'alasan' => 'Dibatalkan otomatis dari Submit BAS',
        ], self::SAMPLING_DATE));
    }

    public function testFilenameValidation(): void
    {
        $this->assertTrue(BasDocumentScope::filename('BAS_ESTX012601.pdf'));
        $this->assertFalse(BasDocumentScope::filename('../evil.pdf'));
        $this->assertFalse(BasDocumentScope::filename('file.txt'));
    }
}
