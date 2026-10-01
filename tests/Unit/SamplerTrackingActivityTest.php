<?php

namespace Tests\Unit;

use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use App\Services\SamplerTrackingActivity;
use PHPUnit\Framework\TestCase;

class SamplerTrackingActivityTest extends TestCase
{
    private function session($id, $order, $date = '2026-10-01', $sampler = 10)
    {
        $member = new SamplerTrackingMember();
        $member->forceFill(['id' => $id, 'sampler_id' => $sampler, 'durasi' => 0]);
        $member->setRelation('events', collect());
        $session = new SamplerTrackingSession();
        $session->forceFill(['id' => $id, 'no_order' => $order, 'tanggal_sampling' => $date, 'id_pelanggan' => 100]);
        $session->setRelation('activeMembers', collect([$member]));
        return $session;
    }

    public function testDifferentOrdersOfSameCustomerStaySeparate(): void
    {
        $first = $this->session(1, 'O1');
        $second = $this->session(2, 'O2');
        $activities = SamplerTrackingActivity::consolidate(collect([$first, $second]));
        $this->assertCount(2, $activities);
        $this->assertSame([1], $activities[0]->activity_session_ids);
        $this->assertSame([2], $activities[1]->activity_session_ids);
        $this->assertSame([1], $activities[0]->activeMembers->first()->activity_member_ids);
        $this->assertSame([2], $activities[1]->activeMembers->first()->activity_member_ids);
    }

    public function testSameOrderDateAndTeamConsolidateWithoutChangingSources(): void
    {
        $first = $this->session(1, 'O1');
        $second = $this->session(2, 'O1');
        $activities = SamplerTrackingActivity::consolidate(collect([$first, $second]));
        $this->assertCount(1, $activities);
        $this->assertSame([1, 2], $activities->first()->activity_session_ids);
        $this->assertSame([1, 2], $activities->first()->activeMembers->first()->activity_member_ids);
        $this->assertSame('O1', $activities->first()->no_order);
        $this->assertNull($first->activity_session_ids);
        $this->assertNull($first->activeMembers->first()->activity_member_ids);
    }

    public function testDifferentDatesTeamsAndMissingOrdersStaySeparate(): void
    {
        $activities = SamplerTrackingActivity::consolidate(collect([
            $this->session(1, 'O1'),
            $this->session(2, 'O1', '2026-10-02'),
            $this->session(3, 'O1', '2026-10-01', 20),
            $this->session(4, null),
            $this->session(5, ''),
        ]));
        $this->assertCount(5, $activities);
    }
}
