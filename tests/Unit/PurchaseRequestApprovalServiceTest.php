<?php

namespace Tests\Unit;

use App\Services\PurchaseRequestApprovalService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PurchaseRequestApprovalServiceTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider hierarchyProvider
     */
    public function testStaffApprovalUsesManagers(array $rows, string $mode, array $expectedIds): void
    {
        $model = Mockery::mock('alias:App\\Models\\MasterKaryawan');
        $people = [];
        foreach ($rows as $id => $row) {
            $person = new \App\Models\MasterKaryawan;
            $person->id = $id;
            $person->grade = $row[0];
            $person->atasan_langsung = json_encode($row[1]);
            $person->nama_lengkap = 'Employee ' . $id;
            $people[$id] = $person;
        }
        $model->shouldReceive('where')->withArgs(function ($field, $id) {
            return $field === 'id';
        })->andReturnUsing(function ($field, $id) use ($people) {
            $query = Mockery::mock();
            $query->shouldReceive('where')->with('is_active', 1)->andReturnSelf();
            $query->shouldReceive('first')->andReturn($people[$id] ?? null);
            return $query;
        });
        try {
            $plan = PurchaseRequestApprovalService::buildApprovalPlan($people[10]);
            $this->assertSame($mode, $plan['mode']);
            $this->assertSame($expectedIds, array_column($plan['chain'], 'id'));
            foreach ($plan['chain'] as $index => $entry) {
                $this->assertSame('MANAGER', $entry['grade']);
                $this->assertSame($index, $entry['step']);
            }
        } finally {
            Mockery::close();
        }
    }

    public static function hierarchyProvider(): array
    {
        return [
            'skip supervisor' => [[10 => ['STAFF', [20]], 20 => ['SUPERVISOR', [30]], 30 => ['MANAGER', [1]]], 'chain', [30]],
            'deep chain' => [[10 => ['STAFF', [20]], 20 => ['SUPERVISOR', [21]], 21 => ['SUPERVISOR', [30]], 30 => ['MANAGER', [40]], 40 => ['MANAGER', [1]]], 'chain', [30, 40]],
            'direct manager' => [[10 => ['STAFF', [30]], 30 => ['MANAGER', [1]]], 'chain', [30]],
            'no manager' => [[10 => ['STAFF', [20]], 20 => ['SUPERVISOR', []]], 'blocked', []],
            'no superior' => [[10 => ['STAFF', []]], 'blocked', []],
            'supervisor cycle' => [[10 => ['STAFF', [20]], 20 => ['SUPERVISOR', [10]]], 'blocked', []],
            'manager cycle' => [[10 => ['STAFF', [30]], 30 => ['MANAGER', [40]], 40 => ['MANAGER', [30]]], 'chain', [30, 40]],
            'director grade' => [[10 => ['STAFF', [30]], 30 => ['DIRECTOR', []]], 'blocked', []],
        ];
    }
}
