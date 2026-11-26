<?php

namespace Tests\Unit;

use App\Services\LaporanHarianSummaryAiService;
use PHPUnit\Framework\TestCase;

class LaporanHarianSummaryAiServiceTest extends TestCase
{
    public function testHtmlToReadableTextPreservesListAndParagraphBreaks(): void
    {
        $html = '<p>Perbaikan modul absensi:</p><ul><li>Fix koreksi Check In</li><li>Fix koreksi Check Out</li></ul><p>Menunggu review &amp; deploy.<script>alert(1)</script></p>';

        $plain = LaporanHarianSummaryAiService::htmlToReadableText($html);

        $this->assertSame(
            "Perbaikan modul absensi:\n- Fix koreksi Check In\n- Fix koreksi Check Out\nMenunggu review & deploy.",
            $plain
        );
    }

    public function testNormalizeAndRenderSummaryHtmlProducesCompactLayoutAndExtractsMemberSummaries(): void
    {
        $normalized = LaporanHarianSummaryAiService::normalizeParsedSummary([
            'ringkasan_utama' => 'Tim menyelesaikan perbaikan <script>x</script> scan sampel & dashboard HRD.',
            'ringkasan_per_sumber' => [
                ['nama' => 'Afdhal', 'ringkasan' => 'Perbaikan bug scan sampel & validasi input.'],
                ['nama' => 'Harold', 'ringkasan' => 'Update filter dashboard HRD 2 bulan ke belakang.'],
            ],
            'catatan_penting' => [],
        ], [
            ['karyawan_id' => 10, 'nama' => 'Afdhal'],
            ['karyawan_id' => 20, 'nama' => 'Harold'],
        ]);

        $html = LaporanHarianSummaryAiService::renderSummaryHtml($normalized, 'harian');

        $this->assertStringContainsString('lh-ai-lead', $html);
        $this->assertStringContainsString('data-karyawan-id="10"', $html);
        $this->assertStringContainsString('<div class="lh-ai-member-name">Afdhal</div>', $html);
        $this->assertStringNotContainsString('lh-ai-notes', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $extracted = LaporanHarianSummaryAiService::extractSourceSummaries($html);
        $this->assertSame(
            'Perbaikan bug scan sampel & validasi input.',
            LaporanHarianSummaryAiService::resolveSourceSummary($extracted, 10, 'Afdhal')
        );
        $this->assertSame(
            'Update filter dashboard HRD 2 bulan ke belakang.',
            LaporanHarianSummaryAiService::resolveSourceSummary($extracted, null, 'harold')
        );
    }

    public function testExtractSourceSummariesSupportsLegacyBulletFormat(): void
    {
        $legacyHtml = '<div class="lh-ai-summary"><section class="lh-ai-section"><h4>Ringkasan per Bawahan</h4><ul><li><strong>Abdul Goni Murod:</strong> Menangani tiket sales &amp; revisi jadwal.</li></ul></section></div>';

        $extracted = LaporanHarianSummaryAiService::extractSourceSummaries($legacyHtml);

        $this->assertSame(
            'Menangani tiket sales & revisi jadwal.',
            LaporanHarianSummaryAiService::resolveSourceSummary($extracted, 99, 'Abdul Goni Murod')
        );
    }

    public function testRenderIncludesNotesStripWhenCatatanPentingPresent(): void
    {
        $normalized = LaporanHarianSummaryAiService::normalizeParsedSummary([
            'ringkasan_utama' => 'Fokus perbaikan bug produksi.',
            'ringkasan_per_sumber' => [
                ['nama' => 'Afdhal', 'ringkasan' => 'Fix validasi scan sampel.'],
            ],
            'catatan_penting' => ['Menunggu konfirmasi tim lab terkait format barcode.'],
        ]);

        $html = LaporanHarianSummaryAiService::renderSummaryHtml($normalized, 'harian');

        $this->assertStringContainsString('lh-ai-notes', $html);
        $this->assertStringContainsString('Perlu Diperhatikan &amp; Tindak Lanjut', $html);
    }

    public function testStripLeadingDatePhraseRemovesDateOpenersFromOverview(): void
    {
        $this->assertSame(
            'Tim berfokus pada pengembangan fitur.',
            LaporanHarianSummaryAiService::stripLeadingDatePhrase('Pada Senin, 23 November 2026, tim berfokus pada pengembangan fitur.')
        );

        $this->assertSame(
            'Pekerjaan berjalan sesuai target.',
            LaporanHarianSummaryAiService::stripLeadingDatePhrase('Pada periode 23 Nov – 29 Nov 2026, pekerjaan berjalan sesuai target.')
        );

        $this->assertSame(
            'Tim menyelesaikan seluruh modul.',
            LaporanHarianSummaryAiService::stripLeadingDatePhrase('Selama bulan November 2026, tim menyelesaikan seluruh modul.')
        );

        $this->assertSame(
            'Fokus utama adalah perbaikan bug.',
            LaporanHarianSummaryAiService::stripLeadingDatePhrase('Hari ini, fokus utama adalah perbaikan bug.')
        );
    }
}
