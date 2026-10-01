<?php

namespace Tests\Unit;

use App\Models\NewRecruitment;
use App\Models\SallaryOffer;
use App\Models\DecisionSalary;
use App\Services\RequesterSalaryApprovalService as Service;
use App\Services\AtsNotificationService;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class RequesterSalaryApprovalServiceTest extends TestCase
{
    private $db;
    private $previousContainer;
    private $previousFacade;
    private $previousResolver;
    private $notifications = [];
    private $hrdNotifications = [];

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite required for isolated salary approval tests.');
        }
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = NewRecruitment::getConnectionResolver();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('db.transactions', new DatabaseTransactionsManager());
        $notifier = $this->getMockBuilder(AtsNotificationService::class)
            ->onlyMethods(['requesterSalaryApprovalRequested', 'requesterSalaryDecisionMade'])->getMock();
        $notifier->method('requesterSalaryApprovalRequested')->willReturnCallback(function ($applicant, $user, $hrd, $round) {
            $this->notifications[] = [$applicant->id, $user, $hrd, $round];
        });
        $container->instance(AtsNotificationService::class, $notifier);
        $notifier->method('requesterSalaryDecisionMade')->willReturnCallback(function ($applicant, $decision, $reason) {
            $this->hrdNotifications[] = [$applicant->id, $decision, $reason];
        });
        $this->db = new Manager($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        $container->bind('db.schema', function () { return $this->db->getConnection()->getSchemaBuilder(); });
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('new_recruitment', function (Blueprint $t) {
            $t->increments('id'); $t->integer('personnel_request_id')->nullable();
            $t->string('status'); $t->text('meta_history')->nullable(); $t->timestamps();
        });
        $schema->create('sallary_offer', function (Blueprint $t) {
            $t->increments('id'); $t->integer('new_recruitment_id'); $t->boolean('is_active');
            $t->float('sallary_offer_user'); $t->float('sallary_offer_hrd');
            $t->string('requester_salary_status')->nullable();
            $t->timestamp('requester_salary_decided_at')->nullable(); $t->timestamps();
        });
        $schema->create('candidate_data_offers', function (Blueprint $t) {
            $t->increments('id'); $t->integer('new_recruitment_id');
            $t->float('gaji_pokok')->nullable(); $t->float('pencadangan_upah')->nullable();
        });
        $migration = require __DIR__ . '/../../database/migrations/2026_09_29_110000_create_decision_salary_table.php';
        $migration->up();
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            if ($this->previousResolver) {
                NewRecruitment::setConnectionResolver($this->previousResolver);
            } else {
                NewRecruitment::unsetConnectionResolver();
            }
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacade);
            Container::setInstance($this->previousContainer);
        }
        parent::tearDown();
    }

    private function applicant(): array
    {
        $applicant = NewRecruitment::create(['status' => 'internal_sallary_offer']);
        $offer = SallaryOffer::create(['new_recruitment_id' => $applicant->id, 'is_active' => true,
            'sallary_offer_user' => 5000000, 'sallary_offer_hrd' => 6000000]);
        return [$applicant, $offer];
    }

    public function testPendingBlocksEditingAndSending(): void
    {
        [$applicant, $offer] = $this->applicant();
        $this->assertSame('pending', Service::syncAfterHrdSalarySave($applicant, $offer, 6000000, 'HRD'));
        $this->assertFalse(Service::canSendCandidateOffering($applicant, $offer->fresh())['allowed']);
        $this->expectException(\RuntimeException::class);
        Service::syncAfterHrdSalarySave($applicant, $offer->fresh(), 7000000, 'HRD');
    }

    public function testMatchingSalaryNeedsNoApprovalRound(): void
    {
        [$applicant, $offer] = $this->applicant();
        $offer->update(['sallary_offer_hrd' => 5000000]);
        $this->assertSame('not_required', Service::syncAfterHrdSalarySave($applicant, $offer, 5000000));
        $this->assertSame(0, DecisionSalary::count());
        $this->assertSame([], $this->notifications);
        $this->assertTrue(Service::canSendCandidateOffering($applicant, $offer->fresh())['allowed']);
    }

    /** @dataProvider zeroReferenceSalaryProvider */
    public function testZeroUserSalaryDoesNotRequireApproval(int $hrdAmount): void
    {
        [$applicant, $offer] = $this->applicant();
        $offer->update(['sallary_offer_user' => 0, 'sallary_offer_hrd' => $hrdAmount]);
        $this->assertSame(0, Service::resolveUserAmount($applicant));
        $this->assertSame('not_required', Service::syncAfterHrdSalarySave($applicant, $offer, $hrdAmount));
        $this->assertTrue(Service::canSendCandidateOffering($applicant, $offer->fresh())['allowed']);
        $this->assertSame(0, DecisionSalary::count());
        $this->assertSame([], $this->notifications);
    }

    public static function zeroReferenceSalaryProvider(): array
    {
        return [[1], [5000000], [7000000]];
    }

    public function testZeroReferenceIsDistinctFromMissingSalary(): void
    {
        $this->assertSame(0, Service::normalizeAmount('0', true));
        $this->assertNull(Service::normalizeAmount('', true));
        $this->assertNull(Service::normalizeAmount(null, true));
        $this->assertNull(Service::normalizeAmount(0));
    }

    public function testNewApprovalNotifiesOnlyAfterCommit(): void
    {
        [$applicant, $offer] = $this->applicant();
        DB::beginTransaction();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $this->assertSame([], $this->notifications);
        DB::commit();
        $this->assertSame([[$applicant->id, 5000000, 6000000, 1]], $this->notifications);
    }

    public function testRolledBackApprovalDoesNotNotify(): void
    {
        [$applicant, $offer] = $this->applicant();
        DB::beginTransaction();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        DB::rollBack();
        $this->assertSame([], $this->notifications);
        $this->assertSame(0, DecisionSalary::count());
    }

    /** @dataProvider decisionNotificationProvider */
    public function testDecisionNotifiesHrdAfterCommit(string $decision, ?string $reason): void
    {
        [$applicant, $offer] = $this->applicant();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $round = Service::getPendingForRecruitment($applicant->id);
        DB::beginTransaction();
        Service::recordDecision($round, $decision, 'User', $reason);
        $this->assertSame([], $this->hrdNotifications);
        DB::commit();
        $this->assertSame([[$applicant->id, $decision, $reason]], $this->hrdNotifications);
    }

    public static function decisionNotificationProvider(): array
    {
        return [['approved', null], ['rejected', 'Di luar anggaran']];
    }

    public function testRolledBackDecisionDoesNotNotifyHrd(): void
    {
        [$applicant, $offer] = $this->applicant();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $round = Service::getPendingForRecruitment($applicant->id);
        DB::beginTransaction();
        Service::recordDecision($round, 'approved', 'User');
        DB::rollBack();
        $this->assertSame([], $this->hrdNotifications);
        $this->assertSame('pending', $round->fresh()->decision);
    }

    public function testMatchingSalaryPreservesPreviousApproval(): void
    {
        [$applicant, $offer] = $this->applicant();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $round = Service::getPendingForRecruitment($applicant->id);
        Service::recordDecision($round, 'approved', 'User');
        $offer->refresh()->update(['sallary_offer_hrd' => 5000000]);
        Service::syncAfterHrdSalarySave($applicant, $offer, 5000000);
        $this->assertSame('approved', $round->fresh()->decision);
        $this->assertSame('User', $round->fresh()->decided_by);
        $this->assertSame('not_required', $offer->fresh()->requester_salary_status);
    }

    public function testRejectThenMatchingSalaryPreservesDecision(): void
    {
        [$applicant, $offer] = $this->applicant();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $round = Service::getPendingForRecruitment($applicant->id);
        Service::recordDecision($round, 'rejected', 'User', 'Terlalu tinggi');
        $offer->refresh()->update(['sallary_offer_hrd' => 5000000]);
        $this->assertSame('not_required', Service::syncAfterHrdSalarySave($applicant, $offer, 5000000, 'HRD'));
        $this->assertSame('rejected', $round->fresh()->decision);
        $this->assertSame('Terlalu tinggi', $round->fresh()->reason);
        $this->assertTrue(Service::canSendCandidateOffering($applicant, $offer->fresh())['allowed']);
    }

    public function testStaleRoundCannotApproveNewRound(): void
    {
        [$applicant, $offer] = $this->applicant();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $old = Service::getPendingForRecruitment($applicant->id);
        Service::recordDecision($old, 'rejected', 'User', 'Tolak');
        $offer->refresh()->update(['sallary_offer_hrd' => 5500000]);
        Service::syncAfterHrdSalarySave($applicant, $offer, 5500000);
        try {
            Service::recordDecision($old, 'approved', 'User');
            $this->fail('Stale approval must fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('rejected', $old->fresh()->decision);
            $this->assertSame(2, Service::getPendingForRecruitment($applicant->id)->round);
            $this->assertSame('pending', $offer->fresh()->requester_salary_status);
        }
    }

    public function testSecondDecisionCannotOverwriteFirst(): void
    {
        [$applicant, $offer] = $this->applicant();
        Service::syncAfterHrdSalarySave($applicant, $offer, 6000000);
        $round = Service::getPendingForRecruitment($applicant->id);
        $staleCopy = DecisionSalary::find($round->id);
        Service::recordDecision($round, 'approved', 'User');
        $this->assertTrue(Service::canSendCandidateOffering($applicant, $offer->fresh())['allowed']);
        try {
            Service::recordDecision($staleCopy, 'rejected', 'Other user', 'Tolak');
            $this->fail('Second decision must fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('approved', $round->fresh()->decision);
            $this->assertSame('approved', $offer->fresh()->requester_salary_status);
            $this->assertCount(1, $this->hrdNotifications);
        }
    }
}
