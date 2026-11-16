<?php

use App\Services\QuotationGenerate\QuotationAutoQtMonth;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class QuotationAutoQtMonthTest extends TestCase
{
    public function testDesemberUsesCurrentYearAndLooksBackSixMonths()
    {
        $now = Carbon::create(2026, 11, 6, 8, 0, 0, 'Asia/Jakarta');
        $window = QuotationAutoQtMonth::parse('desember', $now);

        $this->assertSame('12-2026', $window->token());
        $this->assertSame('2026-06-01 00:00:00', $window->orderFrom()->toDateTimeString());
        $this->assertSame('2026-12-01 00:00:00', $window->orderUntil()->toDateTimeString());
        $this->assertSame('2026-07-01 00:00:00', $window->sourceUntil()->toDateTimeString());
        $this->assertSame('2026-06-30 23:59:59', $window->sourceJuneEnd()->toDateTimeString());
    }

    public function testExplicitMonthYearFlag()
    {
        $window = QuotationAutoQtMonth::parse('12-2026');

        $this->assertSame(12, $window->month());
        $this->assertSame(2026, $window->year());
        $this->assertSame('2026-06-01 00:00:00', $window->orderFrom()->toDateTimeString());
        $this->assertSame('2026-07-01 00:00:00', $window->sourceUntil()->toDateTimeString());
    }

    public function testMayCampaignLooksBackToPreviousJune()
    {
        $window = QuotationAutoQtMonth::parse('05-2026');

        $this->assertSame('2025-07-01 00:00:00', $window->sourceUntil()->toDateTimeString());
        $this->assertSame('2025-06-30 23:59:59', $window->sourceJuneEnd()->toDateTimeString());
    }

    public function testExtractFromArgvReadsEitherForm()
    {
        $this->assertSame('desember', QuotationAutoQtMonth::extractFromArgv(['artisan', 'generateAutoQt', '--desember']));
        $this->assertSame('12-2026', QuotationAutoQtMonth::extractFromArgv(['artisan', 'generateAutoQt', '--12-2026', '--test']));
        $this->assertSame('desember', QuotationAutoQtMonth::extractFromArgv(['artisan', 'generateAutoQt', '--month=desember']));
    }

    public function testPromoCodeUsesFullMonthWhenWithinTwentyChars()
    {
        $window = QuotationAutoQtMonth::parse('12-2026');

        $this->assertSame('Auto_Desember_2026', $window->promoCode());
        $this->assertSame('Auto Desember 2026', $window->promoName());
        $this->assertLessThanOrEqual(20, strlen($window->promoCode()));
        $this->assertSame('Auto_September_2026', QuotationAutoQtMonth::parse('09-2026')->promoCode());
        $this->assertSame('Auto_November_2026', QuotationAutoQtMonth::parse('11-2026')->promoCode());
    }

    public function testPromoCodeFallsBackToShortMonthWhenOverTwentyChars()
    {
        $this->assertSame('Auto_Sep_202600', QuotationAutoQtMonth::formatPromoCode('September', 'Sep', 202600));
        $this->assertSame('Auto_Des_9999999', QuotationAutoQtMonth::formatPromoCode('Desember', 'Des', 9999999));
    }
}
