<?php

namespace Tests\Unit;

use App\Services\Hr\AttendanceCorrectionAtasanQueueService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AttendanceCorrectionWaitingAtasanTest extends TestCase
{
    /** @dataProvider approvalProgressProvider */
    public function testNextAtasanBecomesActive(array $states, array $expected): void
    {
        $service = new AttendanceCorrectionAtasanQueueService();
        $method = new ReflectionMethod($service, 'activateNextLegacyAtasanStep');
        $method->setAccessible(true);
        $steps = array_map(function ($state) {
            return ['state' => $state];
        }, $states);

        $result = $method->invoke($service, $steps);

        $this->assertSame($expected, array_column($result, 'state'));
    }

    public function approvalProgressProvider(): array
    {
        return [
            'first approval' => [['active', 'waiting', 'waiting'], ['active', 'waiting', 'waiting']],
            'second approval' => [['done', 'waiting', 'waiting'], ['done', 'active', 'waiting']],
            'third approval' => [['waiting', 'done', 'waiting'], ['waiting', 'done', 'active']],
            'complete' => [['done', 'done', 'done'], ['done', 'done', 'done']],
        ];
    }
}
