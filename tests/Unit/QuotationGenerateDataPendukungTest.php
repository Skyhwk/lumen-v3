<?php

use App\Services\QuotationGenerate\QuotationGenerateDataPendukung;
use PHPUnit\Framework\TestCase;

class QuotationGenerateDataPendukungTest extends TestCase
{
    public function testEmptyValuesAreNotUsable()
    {
        $this->assertFalse(QuotationGenerateDataPendukung::isUsable(null));
        $this->assertFalse(QuotationGenerateDataPendukung::isUsable(''));
        $this->assertFalse(QuotationGenerateDataPendukung::isUsable('{}'));
        $this->assertFalse(QuotationGenerateDataPendukung::isUsable('[]'));
        $this->assertFalse(QuotationGenerateDataPendukung::isUsable('null'));
        $this->assertFalse(QuotationGenerateDataPendukung::isUsable('  { }  '));
    }

    public function testFilledArrayIsUsable()
    {
        $this->assertTrue(QuotationGenerateDataPendukung::isUsable('[{"kategori":"Air"}]'));
        $this->assertTrue(QuotationGenerateDataPendukung::isUsable('{"kategori":"Air"}'));
    }
}
