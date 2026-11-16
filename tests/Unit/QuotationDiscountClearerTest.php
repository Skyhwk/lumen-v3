<?php

use App\Models\QuotationNonKontrak;
use App\Services\QuotationGenerate\QuotationSamplingPriceRecalculator;
use PHPUnit\Framework\TestCase;

class QuotationDiscountClearerTest extends TestCase
{
    public function testClearDiscountsRemovesEverySourceDiscount()
    {
        $quotation = new QuotationNonKontrak();
        $quotation->discount_air = '10';
        $quotation->discount_non_air = '5';
        $quotation->discount_udara = '5';
        $quotation->discount_emisi = '5';
        $quotation->discount_transport = '10';
        $quotation->discount_perdiem = '10';
        $quotation->discount_perdiem_24jam = '10';
        $quotation->discount_gabungan = '3';
        $quotation->discount_group = '2';
        $quotation->discount_consultant = '1';
        $quotation->cash_discount_persen = '5';
        $quotation->cash_discount = 100000;
        $quotation->custom_discount = json_encode([['discount' => 50000]]);
        $quotation->discount_promo = json_encode(['jumlah_promo_discount' => 10]);
        $quotation->total_discount_air = 1000;
        $quotation->total_discount = 999999;
        $quotation->total_discount_promo = 25000;

        (new QuotationSamplingPriceRecalculator())->clearDiscounts($quotation);

        $this->assertNull($quotation->discount_air);
        $this->assertNull($quotation->discount_non_air);
        $this->assertNull($quotation->discount_udara);
        $this->assertNull($quotation->discount_emisi);
        $this->assertNull($quotation->discount_transport);
        $this->assertNull($quotation->discount_perdiem);
        $this->assertNull($quotation->cash_discount_persen);
        $this->assertNull($quotation->custom_discount);
        $this->assertNull($quotation->discount_promo);
        $this->assertSame(0, $quotation->cash_discount);
        $this->assertSame(0, $quotation->total_discount_air);
        $this->assertSame(0, $quotation->total_discount);
        $this->assertSame(0, $quotation->total_discount_promo);
    }
}
