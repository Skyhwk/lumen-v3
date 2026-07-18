<?php

namespace Tests\Unit;

use App\Services\FormulaService;
use PHPUnit\Framework\TestCase;

class FormulaServiceTest extends TestCase
{
    private FormulaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormulaService();
    }

    public function testSimpleMultiplicationAndDivision(): void
    {
        $result = $this->service->calculate(
            '(({hasil_uji}*{faktor_pengencer})/0.0044)',
            [
                'hasil_uji' => 120,
                'faktor_pengencer' => 2,
            ]
        );

        $this->assertTrue($result['valid']);
        $this->assertEqualsWithDelta(54545.4545454545, $result['result'], 0.0001);
    }

    public function testUnknownVariableIsRejected(): void
    {
        $result = $this->service->validate('({abc}*2)', ['hasil_uji']);

        $this->assertFalse($result['valid']);
        $this->assertSame('UNKNOWN_VARIABLE', $result['errors'][0]['code']);
    }

    public function testFunctionWithManualClosingParen(): void
    {
        $result = $this->service->validate(
            'ABS({hasil_uji})',
            ['hasil_uji']
        );

        $this->assertTrue($result['valid']);
    }

    public function testAbsFunctionCalculation(): void
    {
        $result = $this->service->calculate(
            'ABS({hasil_uji})',
            ['hasil_uji' => -120]
        );

        $this->assertTrue($result['valid']);
        $this->assertEquals(120, $result['result']);
    }

    public function testRoundFunctionWithPrecision(): void
    {
        $result = $this->service->calculate(
            'ROUND({nilai},4)',
            ['nilai' => 3.14159265]
        );

        $this->assertTrue($result['valid']);
        $this->assertEqualsWithDelta(3.1416, $result['result'], 0.00001);
    }

    public function testTruncFunctionWithPrecision(): void
    {
        $result = $this->service->calculate(
            'TRUNC({nilai},4)',
            ['nilai' => 3.14159265]
        );

        $this->assertTrue($result['valid']);
        $this->assertEqualsWithDelta(3.1415, $result['result'], 0.00001);
    }
}
