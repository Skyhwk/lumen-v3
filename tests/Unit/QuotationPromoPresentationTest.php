<?php

use App\Services\QuotationPromoPresentation;
use PHPUnit\Framework\TestCase;

class QuotationPromoPresentationTest extends TestCase
{
    public function testBothPackageTypesKeepPackagePricesWithoutSeparateDiscount()
    {
        foreach (['paket_pengujian', 'pengujian_free_pengujian'] as $method) {
            $source = (object) ['promo_id' => 1, 'tanggal_penawaran' => '2026-09-14',
                'grand_total' => 900000, 'total_dpp' => 810000, 'total_ppn' => 89100, 'biaya_akhir' => 899100,
                'data_pendukung_sampling' => json_encode([
                    ['is_promo' => true, 'harga_satuan' => 133333.33, 'harga_total' => 800000, 'jumlah_titik' => 6],
                    ['is_promo' => true, 'harga_satuan' => 0, 'harga_total' => 0, 'jumlah_titik' => 4],
                    ['harga_satuan' => 100000, 'harga_total' => 100000, 'jumlah_titik' => 1],
                ])];
            $view = QuotationPromoPresentation::forPdf($source, (object) ['metode' => $method, 'nama_diskon' => 'Paket Hemat',
                'konfigurasi' => json_encode(['data_pendukung_sampling' => [
                    ['jumlah_titik' => 3, 'harga_paket' => 400000],
                    ['jumlah_titik' => 2, 'harga_paket' => 0],
                ]])]);
            $this->assertEquals([400000, 0, 100000], array_column(json_decode($view->data_pendukung_sampling), 'harga_satuan'));
            $this->assertEquals(133333.33, json_decode($source->data_pendukung_sampling)[0]->harga_satuan);
            $this->assertEquals([800000, 0, 100000], array_column(json_decode($view->data_pendukung_sampling), 'harga_total'));
            $this->assertEquals(0, $view->total_discount_promo);
            $this->assertEquals(900000, $view->grand_total);
            $this->assertNull($view->discount_promo);
            $this->assertEquals(0, json_decode($view->data_pendukung_sampling)[1]->harga_satuan);
            $this->assertEquals(899100, $view->biaya_akhir);
            $this->assertEquals(89100, $view->total_ppn);
            $this->assertEquals(900000, $source->grand_total);
        }
    }

    public function testLabelingDoesNotChangePdfData()
    {
        $source = (object) ['promo_id' => 3, 'grand_total' => 1000000];
        $this->assertSame($source, QuotationPromoPresentation::forPdf($source, (object) ['metode' => 'labeling', 'nama_diskon' => 'Label saja']));
    }

    public function testPercentageOnlyLabelsStoredDiscountWithoutReapplyingIt()
    {
        $source = (object) ['promo_id' => 1, 'total_discount_promo' => 180000, 'total_dpp' => 720000, 'grand_total' => 1000000];
        $view = QuotationPromoPresentation::forPdf($source, (object) ['metode' => 'persentase', 'nama_diskon' => 'Hemat 20%']);
        $this->assertEquals('Diskon Hemat 20%', json_decode($view->discount_promo)->deskripsi_promo_discount);
        $this->assertEquals(180000, $view->total_discount_promo);
        $this->assertEquals(720000, $view->total_dpp);
        $this->assertEquals(1000000, $view->grand_total);
    }

    public function testOriginalPricesAndSeparateDiscountDoNotChangePayableOrSource()
    {
        $source = (object) ['promo_id' => 24, 'grand_total' => 2160000,
            'total_dpp' => 2160000, 'total_ppn' => 237600, 'biaya_akhir' => 2397600,
            'data_pendukung_sampling' => json_encode([(object) ['harga_satuan' => 660000, 'jumlah_titik' => 3, 'harga_total' => 1760000]])];
        $promo = (object) ['metode' => 'free_parameter', 'nama_diskon' => 'Free COD'];
        $view = QuotationPromoPresentation::forPdf($source, $promo);
        $this->assertEquals(1980000, json_decode($view->data_pendukung_sampling)[0]->harga_total);
        $this->assertEquals(2380000, $view->grand_total);
        $this->assertEquals(220000, $view->total_discount_promo);
        $this->assertEquals('Diskon Free COD', json_decode($view->discount_promo)->deskripsi_promo_discount);
        foreach (['total_dpp', 'total_ppn', 'biaya_akhir'] as $field) $this->assertEquals($source->$field, $view->$field);
        $this->assertEquals(1760000, json_decode($source->data_pendukung_sampling)[0]->harga_total);
        $this->assertEquals(2160000, $source->grand_total);
    }
}
