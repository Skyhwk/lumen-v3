<?php

namespace Tests\Unit;

use App\Http\Controllers\external\LHPHandleController;
use App\Services\CfrDetails;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CfrDetailsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testTrackLhpUsesRequestedPeriodEvenWhenItIsNotTheFirstSnapshotGroup(): void
    {
        $previousFacade = Facade::getFacadeApplication();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(new Container());

        try {
            $order = (object) ['no_order' => 'ORDER-CONTRACT'];
            $snapshot = [
                $this->snapshotPeriod('2026-02', 'CFR-FEB', 'SAMPLE-FEB', '2026-02-10'),
                $this->snapshotPeriod('2026-11', 'CFR-NOV', 'SAMPLE-NOV', '2026-11-10'),
            ];

            $query = Mockery::mock();
            $query->shouldReceive('where')->with('no_order', $order->no_order)->once()->andReturnSelf();
            $query->shouldReceive('first')->once()->andReturn((object) ['dataOrderDetail' => json_encode($snapshot)]);
            $schema = Mockery::mock();
            $schema->shouldReceive('getColumnListing')->andReturn([]);
            $database = Mockery::mock();
            $database->shouldReceive('table')->with('order_berjalan')->once()->andReturn($query);
            $database->shouldReceive('connection->getSchemaBuilder')->andReturn($schema);
            DB::swap($database);

            $rekapBuilder = $this->createMock(LHPHandleController::class);
            $rekapBuilder->method('getRekapPengujianFromOrderBerjalan')->willReturn([]);

            $result = (new CfrDetails($order, '2026-11', $rekapBuilder))->get();

            $this->assertCount(1, $result);
            $this->assertSame('CFR-NOV', $result[0]['cfr']);
            $this->assertSame('2026-11', $result[0]['periode']);
            $this->assertSame(['SAMPLE-NOV'], $result[0]['no_sampel']);
            $this->assertSame('SAMPLE-NOV', $result[0]['order_details'][0]['no_sampel']);
            $this->assertSame('2026-11', $result[0]['order_details'][0]['periode']);
            $this->assertSame('2026-11-10', $result[0]['steps']['sampling']['date']);
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($previousFacade);
        }
    }

    private function snapshotPeriod(string $period, string $cfr, string $sample, string $samplingDate): array
    {
        return [
            'periode' => $period,
            'detail' => [[
                'cfr' => $cfr,
                'sampelNumbers' => [$sample],
                'categories' => ['Air'],
                'points' => ['Titik 1'],
                'jumlah_sampel' => 1,
                'steps' => ['sampling' => ['label' => 'Sampling', 'date' => $samplingDate]],
            ]],
        ];
    }
}
