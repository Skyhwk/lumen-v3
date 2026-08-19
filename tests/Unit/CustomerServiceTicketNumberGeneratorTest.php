<?php

namespace Tests\Unit;

use App\Services\CustomerServiceTicketNumberGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CustomerServiceTicketNumberGeneratorTest extends TestCase
{
    public function testRandomCodeMatchesFormatAndLength(): void
    {
        $method = new ReflectionMethod(CustomerServiceTicketNumberGenerator::class, 'buildRandomCode');
        $method->setAccessible(true);

        for ($i = 0; $i < 1000; $i++) {
            $code = $method->invoke(null);
            $this->assertSame(8, strlen($code));
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
        }
    }

    public function testRandomCodeBatchHasNoDuplicatesInMemory(): void
    {
        $method = new ReflectionMethod(CustomerServiceTicketNumberGenerator::class, 'buildRandomCode');
        $method->setAccessible(true);

        $codes = [];
        for ($i = 0; $i < 1000; $i++) {
            $code = $method->invoke(null);
            $this->assertArrayNotHasKey($code, $codes, "Duplikat in-memory pada iterasi {$i}");
            $codes[$code] = true;
        }
    }
}
