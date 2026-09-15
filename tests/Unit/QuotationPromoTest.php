<?php

use App\Services\QuotationPromo;
use PHPUnit\Framework\TestCase;

class QuotationPromoTest extends TestCase
{
    public function testRequestQrPriceIsCalculatedSeparatelyForEveryPeriod()
    {
        $promo = (object) ['metode' => 'free_parameter', 'kode_promo' => 'TEST', 'konfigurasi' => ['kategori_id' => 1, 'jumlah_titik' => 3, 'parameter_ids' => [1]]];
        $a = $this->row(5); $a->periode = ['2026-09', '2026-10'];
        $b = clone $a;
        [$rows, $total] = QuotationPromo::requestRows($promo, [$a, $b], '2026-09-14', true, fn() => [100000, 50000]);
        $this->assertCount(2, $rows);
        $this->assertEquals([450000, 750000], array_column($rows, 'harga_total'));
        $this->assertEquals(['2026-09', '2026-10'], $rows[0]->periode);
        $this->assertEquals(2400000, $total);
    }

    public function testPackageUsesIdsInsteadOfDisplayLabels()
    {
        $template = (array) $this->row(3);
        $template['harga_paket'] = 400000;
        $promo = (object) ['metode' => 'paket_pengujian', 'konfigurasi' => ['data_pendukung_sampling' => [$template]]];
        $row = $this->row(6, '1-AIR');
        $row->parameter = ['1;COD new label', '2;PH'];
        $row->is_promo = true;
        QuotationPromo::validateRows($promo, [$row]);
        $used = false;
        $this->assertEquals(800000, QuotationPromo::rowPrice($promo, $row, 999999, [], $used)[1]);
    }

    public function testRequestPackagePriceAndFreeTestPersistAcrossPeriods()
    {
        $a = (array) $this->row(3); $a['harga_paket'] = 400000;
        $b = (array) $this->row(2, '4-Udara'); $b['harga_paket'] = 0;
        $promo = (object) ['metode' => 'pengujian_free_pengujian', 'kode_promo' => 'TEST', 'konfigurasi' => ['data_pendukung_sampling' => [$a, $b]]];
        $rows = [$this->row(6), $this->row(4, '4-Udara')];
        foreach ($rows as $row) { $row->is_promo = true; $row->periode = ['2026-09', '2026-10']; }
        [$priced, $total] = QuotationPromo::requestRows($promo, $rows, '2026-09-14', true, fn() => [100000, 50000]);
        $this->assertCount(2, $priced);
        $this->assertEquals([800000, 0], array_column($priced, 'harga_total'));
        $this->assertEquals(1600000, $total);
    }

    private function row($points = 3, $category = '1-Air')
    {
        return (object) ['kategori_1' => $category, 'kategori_2' => '1-Air Bersih', 'regulasi' => [], 'parameter' => ['1;COD', '2;pH'], 'jumlah_titik' => $points];
    }

    public function testPackageRatioAndZeroPrice()
    {
        $a = (array) $this->row(3);
        $b = (array) $this->row(2, '4-Udara');
        $a['harga_paket'] = 400000;
        $b['harga_paket'] = 0;
        $promo = (object) ['metode' => 'pengujian_free_pengujian', 'konfigurasi' => ['data_pendukung_sampling' => [$a, $b]]];
        $rows = [$this->row(6), $this->row(4, '4-Udara')];
        foreach ($rows as $row) $row->is_promo = true;
        QuotationPromo::validateRows($promo, $rows);
        $used = false;
        $this->assertEquals(800000, QuotationPromo::rowPrice($promo, $rows[0], 200000, [], $used)[1]);
        $this->assertEquals(0, QuotationPromo::rowPrice($promo, $rows[1], 200000, [], $used)[1]);
        $rows[1]->jumlah_titik = 2;
        $this->expectException(InvalidArgumentException::class);
        QuotationPromo::validateRows($promo, $rows);
    }

    public function testFreeParameterIsLimitedAndNotCombinedOrRepeated()
    {
        $promo = (object) ['metode' => 'free_parameter', 'konfigurasi' => ['kategori_id' => 1, 'jumlah_titik' => 3, 'parameter_ids' => [1]]];
        $used = false;
        foreach ([2, 2] as $count) $this->assertEquals(300000, QuotationPromo::rowPrice($promo, $this->row($count), 150000, [100000, 50000], $used)[1]);
        $this->assertFalse($used);
        $this->assertEquals(450000, QuotationPromo::rowPrice($promo, $this->row(5), 150000, [100000, 50000], $used)[1]);
        $this->assertEquals(750000, QuotationPromo::rowPrice($promo, $this->row(5), 150000, [100000, 50000], $used)[1]);
        $used = false; // A new contract period gets its own allowance.
        $this->assertEquals(450000, QuotationPromo::rowPrice($promo, $this->row(5), 150000, [100000, 50000], $used)[1]);
    }

    public function testManualDiscountPrecedesPromoForEachBasis()
    {
        // Analysis 1m - manual 10% = 900k; transport 200k; perdiem 100k; preparation 50k.
        foreach (['analisa' => 180000, 'transport' => 40000, 'perdiem' => 20000, 'global' => 240000] as $basis => $expected) {
            $promo = (object) ['metode' => 'persentase', 'kode_promo' => 'TEST', 'nama_diskon' => 'Test', 'konfigurasi' => ['dasar_diskon' => $basis, 'persentase' => 20]];
            $model = new stdClass();
            $dpp = 1250000; $outside = 0; $discount = 100000;
            QuotationPromo::percentage($promo, $model, $dpp, $outside, $discount, ['transportasi' => 200000, 'perdiem' => 100000, 'preparasi' => 50000], null);
            $this->assertEquals(1250000 - $expected, $dpp);
            $this->assertEquals(100000 + $expected, $discount);
        }
    }

    public function testOutsideTaxTransportDoesNotReduceTaxableAnalysis()
    {
        $promo = (object) ['metode' => 'persentase', 'kode_promo' => 'TEST', 'nama_diskon' => 'Test', 'konfigurasi' => ['dasar_diskon' => 'transport', 'persentase' => 20]];
        $model = new stdClass(); $dpp = 900000; $outside = 200000; $discount = 100000;
        QuotationPromo::percentage($promo, $model, $dpp, $outside, $discount, ['transportasi' => 200000], (object) ['transportasi' => 'true']);
        $this->assertEquals(900000, $dpp);
        $this->assertEquals(160000, $outside);
    }

    public function testFourPercentageBasesOnlyDiscountTheirSelectedCosts()
    {
        // Values after manual discounts. Preparation and other costs are not eligible.
        $costs = ['transportasi' => 180000, 'perdiem' => 90000, 'perdiem24jam' => 270000,
            'preparasi' => 50000, 'biayalain' => 70000];
        $selected = ['analisa' => ['analisa'], 'transport' => ['transportasi'],
            'perdiem' => ['perdiem', 'perdiem24jam'], 'global' => ['analisa', 'transportasi', 'perdiem', 'perdiem24jam']];
        foreach (range(0, 7) as $mask) {
            $flags = ['transportasi' => (bool) ($mask & 1), 'perdiem' => (bool) ($mask & 2), 'perdiem24jam' => (bool) ($mask & 4)];
            foreach ($selected as $basis => $keys) {
                $dpp = 900000 + 50000 + 70000; $outside = 0;
                foreach ($flags as $key => $isOutside) {
                    if ($isOutside) $outside += $costs[$key]; else $dpp += $costs[$key];
                }
                $expectedDpp = $dpp; $expectedOutside = $outside; $expectedDiscount = 0;
                foreach ($keys as $key) {
                    $amount = ($key === 'analisa' ? 900000 : $costs[$key]) * 0.1;
                    if ($flags[$key] ?? false) $expectedOutside -= $amount; else $expectedDpp -= $amount;
                    $expectedDiscount += $amount;
                }
                $promo = (object) ['metode' => 'persentase', 'kode_promo' => 'TEST', 'nama_diskon' => 'Test',
                    'konfigurasi' => ['persentase' => 10, 'dasar_diskon' => $basis]];
                $model = new stdClass(); $discount = 100000; $originalCosts = $costs;
                QuotationPromo::percentage($promo, $model, $dpp, $outside, $discount, $costs, $flags);
                $this->assertEquals($expectedDpp, $dpp, $basis . ' taxable mask ' . $mask);
                $this->assertEquals($expectedOutside, $outside, $basis . ' outside mask ' . $mask);
                $this->assertEquals($expectedDiscount, $model->total_discount_promo);
                $this->assertEquals(100000 + $expectedDiscount, $discount);
                $this->assertSame($originalCosts, $costs);
            }
        }
    }

    public function testPercentageDoesNotRepriceExistingAnalysisPackage()
    {
        $used = false;
        $promo = (object) ['metode' => 'persentase'];
        $this->assertEquals(400000, QuotationPromo::rowPrice($promo, $this->row(3), 400000, [], $used, 400000)[1]);
    }

    public function testMissingPackageMemberIsRejected()
    {
        $template = (array) $this->row(); $template['harga_paket'] = 400000;
        $promo = (object) ['metode' => 'pengujian_free_pengujian', 'konfigurasi' => ['data_pendukung_sampling' => [$template, array_merge($template, ['harga_paket' => 0])]]];
        $row = $this->row(); $row->is_promo = true;
        $this->expectException(InvalidArgumentException::class);
        QuotationPromo::validateRows($promo, [$row]);
    }

    public function testIdenticalPackageTestsRetainTheirOwnPrices()
    {
        $template = (array) $this->row(); $template['harga_paket'] = 400000;
        $promo = (object) ['metode' => 'pengujian_free_pengujian', 'konfigurasi' => ['data_pendukung_sampling' => [$template, array_merge($template, ['harga_paket' => 0])]]];
        $row = $this->row(); $row->is_promo = true; $used = false;
        $this->assertEquals(400000, QuotationPromo::rowPrice($promo, $row, 200000, [], $used)[1]);
        $this->assertEquals(0, QuotationPromo::rowPrice($promo, $row, 200000, [], $used)[1]);
    }
}
