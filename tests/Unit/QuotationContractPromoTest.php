<?php

use App\Services\QuotationPromo;
use App\Services\QuotationPromoPresentation;
use PHPUnit\Framework\TestCase;

class QuotationContractPromoTest extends TestCase
{
    public function testPromoHeaderUsesOriginalPointNameFormatAndPreservesDetail()
    {
        foreach ([[['001' => ''], ['002' => '']], [['001' => 'Inlet'], ['002' => 'Outlet']]] as $names) {
            $details = [$this->detail('2026-09', [['jumlah_titik' => 2, 'harga_satuan' => 100, 'harga_total' => 200, 'penamaan_titik' => $names]], 200)];
            $header = $this->header($details);
            [$view, $periods] = QuotationPromoPresentation::forContractPdf($header, collect($details), (object) ['metode' => 'free_parameter', 'nama_diskon' => 'TEST']);
            $expected = array_merge(...array_map('array_values', $names));
            $headerRows = json_decode($view->data_pendukung_sampling);
            $this->assertSame($expected, $headerRows[0]->penamaan_titik);
            $this->assertSame(implode(', ', $expected), implode(', ', $headerRows[0]->penamaan_titik));
            $this->assertSame($names, json_decode($periods[0]->data_pendukung_sampling, true)[0]['data_sampling'][0]['penamaan_titik']);
        }
        $this->assertSame(['Inlet', 'Outlet'], QuotationPromo::headerPointNames(['Inlet', 'Outlet'], 2));
        $this->assertSame(['', ''], QuotationPromo::headerPointNames([], 2));
    }

    public function testSyncHeaderMapsCategoryColumnsWithoutAddingDetailOnlyColumns()
    {
        $details = collect([]);
        foreach (['2026-09', '2026-10', '2026-11'] as $period) {
            $detail = $this->detail($period, [['harga_total' => 2640000]], 2640700, 100);
            foreach (['air' => 2640000, 'udara' => 10, 'emisi' => 20, 'padatan' => 30, 'swab_test' => 40, 'tanah' => 100, 'pangan' => 500] as $category => $amount) {
                $detail->{'harga_' . $category} = $amount;
            }
            $detail->total_discount = 100;
            $detail->total_pph = 0;
            $detail->piutang = $detail->biaya_akhir;
            $detail->kode_promo = 'TEST';
            $details->push($detail);
        }
        $header = $this->getMockBuilder(\App\Models\QuotationKontrakH::class)
            ->onlyMethods(['detail', 'save'])->getMock();
        $header->promo_id = 1;
        $relation = $this->getMockBuilder(\stdClass::class)->addMethods(['get'])->getMock();
        $relation->method('get')->willReturn($details);
        $header->expects($this->once())->method('detail')->willReturn($relation);
        $header->expects($this->once())->method('save')->willReturnCallback(function () use ($header) {
            foreach (array_keys($header->getAttributes()) as $column) {
                $this->assertFalse(strpos($column, 'harga_') === 0, 'Detail-only column: ' . $column);
            }
            $this->assertArrayNotHasKey('total_harga_pangan', $header->getAttributes());
            return true;
        });
        QuotationPromo::syncHeader($header);
        foreach (['air' => 2640000, 'udara' => 10, 'emisi' => 20, 'padatan' => 30, 'swab_test' => 40, 'tanah' => 100] as $category => $amount) {
            $this->assertEquals($amount * 3, $header->{'total_harga_' . $category});
        }
        $this->assertEquals(2640700 * 3, $header->grand_total);
        $this->assertEquals(2640600 * 3, $header->total_dpp);
        $this->assertEquals(300, $header->total_discount_promo);
        $this->assertCount(3, json_decode($header->data_pendukung_sampling));
    }

    private function detail($period, $rows, $grand, $discount = 0)
    {
        return (object) ['periode_kontrak' => $period, 'grand_total' => $grand, 'total_discount_promo' => $discount,
            'discount_promo' => null, 'total_dpp' => $grand - $discount, 'total_ppn' => ($grand - $discount) * .11,
            'biaya_akhir' => ($grand - $discount) * 1.11,
            'data_pendukung_sampling' => json_encode([['periode_kontrak' => $period, 'data_sampling' => $rows]])];
    }

    private function header($details)
    {
        return (object) ['promo_id' => 1, 'grand_total' => array_sum(array_column($details, 'grand_total')),
            'total_dpp' => array_sum(array_column($details, 'total_dpp')), 'total_ppn' => array_sum(array_column($details, 'total_ppn')),
            'biaya_akhir' => array_sum(array_column($details, 'biaya_akhir')), 'data_pendukung_sampling' => '[]'];
    }

    public function testPackagesUseWholePackageUnitAndStoredTotalsEveryPeriod()
    {
        foreach (['paket_pengujian', 'pengujian_free_pengujian'] as $method) {
            $details = [];
            foreach ([1, 2] as $factor) {
                $details[] = $this->detail(sprintf('2026-%02d', 8 + $factor), [
                    ['is_promo' => true, 'jumlah_titik' => 3 * $factor, 'harga_satuan' => 400000 / 3, 'harga_total' => 400000 * $factor],
                    ['is_promo' => true, 'jumlah_titik' => 2 * $factor, 'harga_satuan' => 0, 'harga_total' => 0],
                ], 400000 * $factor);
            }
            $promo = (object) ['metode' => $method, 'nama_diskon' => 'Hemat', 'konfigurasi' => ['data_pendukung_sampling' => [
                ['jumlah_titik' => 3, 'harga_paket' => 400000], ['jumlah_titik' => 2, 'harga_paket' => 0],
            ]]];
            $header = $this->header($details); $before = serialize([$header, $details]);
            [$view, $periods] = QuotationPromoPresentation::forContractPdf($header, collect($details), $promo);
            $rows = json_decode($view->data_pendukung_sampling);
            $this->assertEquals([400000, 0, 400000, 0], array_column($rows, 'harga_satuan'));
            $this->assertEquals([400000, 0, 800000, 0], array_column($rows, '_promo_display_total'));
            $this->assertEquals(0, $view->total_discount_promo);
            $this->assertNull($view->discount_promo);
            $this->assertEquals($header->biaya_akhir, $view->biaya_akhir);
            $this->assertEquals($before, serialize([$header, $details]));
        }
    }

    public function testFreeParameterRestoresGrossPerPeriodAndOnlySumsDiscountAtHeader()
    {
        $details = [
            $this->detail('2026-09', [['jumlah_titik' => 3, 'harga_satuan' => 100000, 'harga_total' => 240000]], 240000),
            $this->detail('2026-10', [['jumlah_titik' => 5, 'harga_satuan' => 100000, 'harga_total' => 440000]], 440000),
        ];
        $header = $this->header($details); $before = serialize([$header, $details]);
        [$view, $periods] = QuotationPromoPresentation::forContractPdf($header, collect($details), (object) ['metode' => 'free_parameter', 'nama_diskon' => 'Free COD']);
        $this->assertEquals(800000, $view->grand_total);
        $this->assertEquals([60000, 60000], $periods->pluck('total_discount_promo')->all());
        $this->assertEquals(120000, $view->total_discount_promo);
        $this->assertSame('disc. Free COD', QuotationPromoPresentation::discountLabel($view));
        $this->assertEquals([300000, 500000], array_column(json_decode($view->data_pendukung_sampling), '_promo_display_total'));
        foreach (['total_dpp', 'total_ppn', 'biaya_akhir'] as $field) $this->assertEquals($header->$field, $view->$field);
        $this->assertSame($before, serialize([$header, $details]));
    }

    public function testEveryPercentageBasisIsCalculatedIndependentlyBeforeHeaderSummation()
    {
        foreach (['analisa' => 100000, 'transport' => 20000, 'perdiem' => 30000, 'global' => 150000] as $basis => $amount) {
            $promo = (object) ['metode' => 'persentase', 'nama_diskon' => 'Hemat', 'kode_promo' => 'TEST',
                'konfigurasi' => ['persentase' => 10, 'dasar_diskon' => $basis]];
            $details = [];
            foreach ([1, 2] as $factor) {
                $detail = $this->detail(sprintf('2026-%02d', 8 + $factor), [], 1500000 * $factor);
                $net = $detail->grand_total; $outside = 0; $discount = 0;
                QuotationPromo::percentage($promo, $detail, $net, $outside, $discount,
                    ['transportasi' => 200000 * $factor, 'perdiem' => 100000 * $factor, 'perdiem24jam' => 200000 * $factor], []);
                $detail->total_dpp = $net;
                $this->assertEquals($amount * $factor, $discount);
                $details[] = $detail;
            }
            $header = $this->header($details);
            [$view, $periods] = QuotationPromoPresentation::forContractPdf($header, collect($details), $promo);
            $this->assertEquals($amount * 3, $view->total_discount_promo);
            $this->assertEquals(4500000 - $amount * 3, $view->total_dpp);
            $this->assertSame('disc. Hemat', QuotationPromoPresentation::discountLabel($view));
        }
    }

    public function testLabelingAndNoPromoLeaveContractUnchanged()
    {
        $details = collect([]); $header = (object) ['promo_id' => 1];
        $this->assertSame([$header, $details], QuotationPromoPresentation::forContractPdf($header, $details, (object) ['metode' => 'labeling']));
        $header->promo_id = null;
        $this->assertSame([$header, $details], QuotationPromoPresentation::forContractPdf($header, $details));
    }

    public function testFreeParameterAllowanceResetsBetweenPeriods()
    {
        $promo = (object) ['metode' => 'free_parameter', 'kode_promo' => 'TEST', 'konfigurasi' => ['kategori_id' => 1, 'jumlah_titik' => 3, 'parameter_ids' => [46]]];
        $row = (object) ['kategori_1' => '1-Air', 'parameter' => ['46;COD', '128;pH'], 'jumlah_titik' => 5, 'periode' => ['2026-09', '2026-10']];
        [$rows, $total] = QuotationPromo::requestRows($promo, [$row], '2026-09-15', true, function () { return [20000, 80000]; });
        $this->assertEquals(880000, $total);
        $this->assertEquals(440000, $rows[0]->harga_total);
        $this->assertEquals(['2026-09', '2026-10'], $rows[0]->periode);
    }
}
