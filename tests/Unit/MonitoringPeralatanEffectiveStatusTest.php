<?php

namespace Tests\Unit;

use App\Models\MonitoringPeralatan;
use App\Services\MonitoringPeralatanResolutionContext;
use App\Services\MonitoringPeralatanService;
use PHPUnit\Framework\TestCase;

class MonitoringPeralatanEffectiveStatusTest extends TestCase
{
    public function testEventCoversDateForActivePeriod(): void
    {
        $event = (object) [
            'status_alat' => 'Sedang Digunakan',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-17',
        ];

        $this->assertTrue(MonitoringPeralatanService::eventCoversDate($event, '2026-11-16'));
        $this->assertFalse(MonitoringPeralatanService::eventCoversDate($event, '2026-11-18'));
    }

    public function testResolvesTersediaAfterPeriodEnds(): void
    {
        $ctx = new MonitoringPeralatanResolutionContext('2026-11-18');
        $ctx->eventsByPeralatan[5] = [
            (object) [
                'id' => 10,
                'status_alat' => 'Sedang Digunakan',
                'keterangan' => 'tim A',
                'start_date' => '2026-11-15',
                'end_date' => '2026-11-17',
                'created_at' => '2026-11-14 10:00:00',
            ],
        ];

        $resolved = MonitoringPeralatanService::resolveEffectiveStatus(5, 'AST-001', $ctx);

        $this->assertSame(MonitoringPeralatanService::STATUS_TERSEDIA, $resolved['status_alat']);
        $this->assertSame('default', $resolved['source']);
    }

    public function testResolvesRusakPointInTime(): void
    {
        $ctx = new MonitoringPeralatanResolutionContext('2026-11-11');
        $ctx->eventsByPeralatan[1] = [
            (object) [
                'id' => 3,
                'status_alat' => 'Rusak',
                'keterangan' => '16/11/2026',
                'start_date' => null,
                'end_date' => null,
                'created_at' => '2026-11-10 08:00:00',
            ],
        ];

        $resolved = MonitoringPeralatanService::resolveEffectiveStatus(1, '022', $ctx);

        $this->assertSame('Rusak', $resolved['status_alat']);
        $this->assertSame('monitoring_point', $resolved['source']);
    }

    public function testRequiresPeriodIncludesDigunakan(): void
    {
        $this->assertTrue(MonitoringPeralatan::requiresPeriod('Sedang Digunakan'));
    }
}