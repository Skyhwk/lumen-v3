<?php

namespace Tests\Unit;

use App\Http\Controllers\api\PerhitunganModalParameterController;
use Illuminate\Database\MySqlConnection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PerhitunganModalParameterDateSearchTest extends TestCase
{
    /** @dataProvider dateSearchProvider */
    public function test_display_date_search_builds_expected_conditions($keyword, $expectedBindings, $expectedSql)
    {
        $connection = new MySqlConnection(null);
        $query = $connection->table('master_perhitungan_modal_parameter as mpmp');
        $reflection = new ReflectionClass(PerhitunganModalParameterController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('filterDisplayDate');
        $method->setAccessible(true);
        $method->invoke($controller, $query, 'mpmp.tanggal_berlaku', $keyword);

        $this->assertSame($expectedBindings, $query->getBindings());
        foreach ($expectedSql as $fragment) {
            $this->assertStringContainsString($fragment, $query->toSql());
        }
    }

    public static function dateSearchProvider(): array
    {
        return [
            'short november' => ['nov', ['11'], ['month(`mpmp`.`tanggal_berlaku`)']],
            'short june' => ['jun', ['06'], ['month(`mpmp`.`tanggal_berlaku`)']],
            'month and year' => ['November 2026', ['11', '%2026%'], ['month(', 'like ?']],
            'display date' => ['1 Juni 2026', ['01', '06', '%2026%'], ['day(', 'month(', 'like ?']],
            'english month' => ['October 2026', ['10', '%2026%'], ['month(', 'like ?']],
            'indonesian month' => ['okt', ['10'], ['month(']],
            'iso month' => ['2026-10', ['%2026-10%'], ['like ?']],
            'iso date' => ['2026-06-01', ['%2026-06-01%'], ['like ?']],
            'unknown text' => ['invalid', ['%invalid%'], ['like ?']],
        ];
    }
}
