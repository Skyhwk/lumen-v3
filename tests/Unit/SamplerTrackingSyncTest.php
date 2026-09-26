<?php

namespace Tests\Unit;

use App\Models\Jadwal;
use App\Models\PersiapanSampelHeader;
use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use App\Services\SamplerTrackingService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class SamplerTrackingSyncTest extends TestCase
{
    private $db;
    private $service;
    private $previousContainer;
    private $previousFacade;
    private $previousResolver;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Run PHP with -d extension=pdo_sqlite for isolated tracking tests.');
        }
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Jadwal::getConnectionResolver();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository(['database.default' => 'mysql']));
        $this->db = new Manager($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'mysql');
        $this->db->getDatabaseManager()->setDefaultConnection('mysql');
        $this->db->bootEloquent();
        $connection = $this->db->getConnection('mysql');
        $connection->getPdo();
        // Production models qualify source tables with the database name.
        $connection->setDatabaseName('main');
        $container->instance('db', $this->db->getDatabaseManager());
        $container->bind('db.schema', function () use ($connection) { return $connection->getSchemaBuilder(); });
        $container->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en'), $container));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        if (!class_exists('DB')) {
            class_alias(\Illuminate\Support\Facades\DB::class, 'DB');
        }
        $schema = $connection->getSchemaBuilder();
        $schema->create('jadwal', function (Blueprint $table) {
            $table->increments('id');
            foreach (['id_sampling', 'parsial', 'no_quotation', 'tanggal', 'jam_mulai', 'jam_selesai',
                'kendaraan', 'id_cabang', 'durasi', 'durasi_personal', 'driver', 'nama_perusahaan',
                'alamat', 'kategori', 'userid', 'sampler'] as $name) {
                $table->string($name)->nullable();
            }
            $table->boolean('is_active')->default(true);
        });
        $schema->create('sampling_plan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_quotation');
            $table->string('google_maps_url')->nullable();
        });
        $schema->create('order_header', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_document');
            $table->string('no_order');
            $table->string('alamat_sampling')->nullable();
            $table->boolean('is_active')->default(true);
        });
        require_once __DIR__ . '/../../database/migrations/2026_06_29_100000_create_sampler_tracking_tables.php';
        (new \CreateSamplerTrackingTables())->up();
        require_once __DIR__ . '/../../database/migrations/2026_09_15_100000_create_sampler_tracking_troubles.php';
        (new \CreateSamplerTrackingTroubles())->up();
        require_once __DIR__ . '/../../database/migrations/2026_09_18_120000_add_unblock_detail_to_sampler_tracking_troubles.php';
        (new \AddUnblockDetailToSamplerTrackingTroubles())->up();
        require_once __DIR__ . '/../../database/migrations/2026_09_22_100000_add_session_to_sampler_tracking_troubles.php';
        (new \AddSessionToSamplerTrackingTroubles())->up();
        require_once __DIR__ . '/../../database/migrations/2026_09_23_100000_add_lampiran_to_sampler_tracking_troubles.php';
        (new \AddLampiranToSamplerTrackingTroubles())->up();
        $connection->table('sampling_plan')->insert(['id' => 1, 'no_quotation' => 'Q1']);
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-17 10:00:00', 'Asia/Jakarta'));
        $this->service = new SamplerTrackingService();
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        if ($this->db) {
            $this->db->getDatabaseManager()->purge('mysql');
            if ($this->previousResolver) {
                Jadwal::setConnectionResolver($this->previousResolver);
            } else {
                Jadwal::unsetConnectionResolver();
            }
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacade);
            Container::setInstance($this->previousContainer);
        }
        parent::tearDown();
    }

    public function testCheckoutRequiresEmailedBasEvenWhenDraftExists(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $session->activeMembers()->firstOrFail();
        $connection = $this->db->getConnection('mysql');
        $connection->getSchemaBuilder()->create('persiapan_sampel_header', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_quotation');
            $table->string('tanggal_sampling');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_emailed_bas')->default(false);
            $table->text('detail_bas_documents')->nullable();
        });
        $connection->table('persiapan_sampel_header')->insert([
            'no_quotation' => 'Q1', 'tanggal_sampling' => '2026-09-17',
            'detail_bas_documents' => '[{"filename":"draft.pdf"}]', 'is_emailed_bas' => 0,
        ]);
        $this->assertNotNull($this->service->checkoutBasWarning($member->id));
        $connection->table('persiapan_sampel_header')->update(['is_emailed_bas' => 1]);
        $this->assertNull($this->service->checkoutBasWarning($member->id));
    }

    public function testCheckoutIsRejectedUntilBasEmailIsSentEvenWhenForced(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $session->activeMembers()->firstOrFail();
        $connection = $this->db->getConnection('mysql');
        $connection->getSchemaBuilder()->create('persiapan_sampel_header', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_quotation');
            $table->string('tanggal_sampling');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_emailed_bas')->default(false);
        });
        $connection->table('persiapan_sampel_header')->insert([
            'no_quotation' => 'Q1', 'tanggal_sampling' => '2026-09-17', 'is_emailed_bas' => 0,
        ]);

        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']);
        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'checkin']);
        try {
            $this->service->storeEvent([
                'member_id' => $member->id,
                'event_type' => 'checkout',
                'force_bas_checkout' => true,
            ]);
            $this->fail('Checkout must require emailed BAS.');
        } catch (ValidationException $exception) {
            $this->assertSame('Anda tidak dapat checkout dikarenakan BAS belum disubmit.', $exception->errors()['event_type'][0]);
        }
        $this->assertSame(0, $member->events()->where('event_type', 'checkout')->count());

        $connection->table('persiapan_sampel_header')->update(['is_emailed_bas' => 1]);
        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'checkout']);
        $this->assertSame(1, $member->events()->where('event_type', 'checkout')->count());
    }

    public function testRouteCannotChangeAfterDeparture(): void
    {
        $today = \Carbon\Carbon::now('Asia/Jakarta')->toDateString();
        $this->schedule(['tanggal' => $today]);
        $session = $this->prepare($today)->first();
        $member = $session->activeMembers()->firstOrFail();
        $connection = $this->db->getConnection('mysql');
        $connection->getSchemaBuilder()->create('sampler_tracking_route_overrides', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tanggal_sampling');
            $table->string('sampler_key');
            $table->integer('sampler_tracking_session_id');
            $table->integer('route_order');
            $table->boolean('is_active')->default(true);
        });
        $connection->table('sampler_tracking_events')->insert([
            'sampler_tracking_session_id' => $session->id,
            'sampler_tracking_member_id' => $member->id,
            'event_type' => 'departure', 'event_at' => $today . ' 08:00:00',
        ]);
        $this->expectException(ValidationException::class);
        $this->service->updateRouteOrder([
            'sampler_id' => 10, 'sampler_name' => 'A', 'reason' => 'Change route',
            'items' => [['session_id' => $session->id, 'route_order' => 1]],
        ], 'A');
    }

    public function testTroubleCollectionCatchesMissedDeadlinesWithinImplementationWindow(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-25 10:00:00', 'Asia/Jakarta'));
        $connection = $this->db->getConnection('mysql');
        // Previous implementation date, missed one-day deadline, due multi-day,
        // ongoing multi-day, completed activity, and another sampler.
        $cases = [
            ['2026-09-20', 3, 10, false],
            ['2026-09-21', 1, 10, false],
            ['2026-09-22', 3, 10, false],
            ['2026-09-23', 3, 10, false],
            ['2026-09-24', 1, 10, true],
            ['2026-09-21', 1, 20, false],
        ];
        foreach ($cases as $index => [$date, $duration, $samplerId, $complete]) {
            $session = SamplerTrackingSession::create([
                'team_key' => 'collect-' . $index, 'tanggal_sampling' => $date, 'is_active' => true,
            ]);
            $member = SamplerTrackingMember::create([
                'sampler_tracking_session_id' => $session->id, 'sampler_id' => $samplerId,
                'effective_duration' => $duration, 'is_active' => true,
            ]);
            if ($complete) {
                foreach (['departure', 'checkin', 'checkout', 'return'] as $type) {
                    $connection->table('sampler_tracking_events')->insert([
                        'sampler_tracking_session_id' => $session->id,
                        'sampler_tracking_member_id' => $member->id,
                        'event_type' => $type, 'event_at' => $date . ' 10:00:00',
                    ]);
                }
            }
        }
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(0, $service->collect('2026-09-20', [10]));
        $this->assertSame(2, $service->collect('2026-09-24', [10]));
        $this->assertSame(['2026-09-21', '2026-09-22'], $connection->table('sampler_tracking_troubles')
            ->orderBy('activity_date')->pluck('activity_date')->all());
        $this->assertSame(0, $service->collect('2026-09-24', [10]));
        $this->assertSame(2, $connection->table('sampler_tracking_troubles')->count());
    }

    public function testCutiDoesNotCreateTroubleAndExistingLeaveOnlyBlockIsCleared(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        $connection = $this->db->getConnection('mysql');
        foreach (['CUTI', ' cuti ', 'CuTi'] as $index => $name) {
            $session = SamplerTrackingSession::create([
                'team_key' => 'leave-' . $index, 'tanggal_sampling' => '2026-09-21',
                'nama_perusahaan' => $name, 'is_active' => true,
            ]);
            SamplerTrackingMember::create([
                'sampler_tracking_session_id' => $session->id, 'sampler_id' => 10 + $index,
                'effective_duration' => 1, 'is_active' => true,
            ]);
        }
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(0, $service->collect('2026-09-22'));
        $connection->table('sampler_tracking_troubles')->insert([
            'sampler_id' => 10, 'activity_date' => '2026-09-21', 'is_clear' => 0,
            'tracking_session_id' => SamplerTrackingSession::where('team_key', 'leave-0')->value('id'),
        ]);
        $this->assertCount(0, $service->unresolved(10));
        $this->assertEquals(1, $connection->table('sampler_tracking_troubles')->where('sampler_id', 10)->value('is_clear'));
        $service->assertAllowed(10, '2026-09-23');
    }

    public function testCutiDoesNotHideOrPostponeRealUnfinishedWork(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        foreach ([['CUTI', 10], ['Client', 1]] as $index => [$name, $duration]) {
            $session = SamplerTrackingSession::create([
                'team_key' => 'mixed-leave-' . $index, 'tanggal_sampling' => '2026-09-21',
                'nama_perusahaan' => $name, 'is_active' => true,
            ]);
            SamplerTrackingMember::create([
                'sampler_tracking_session_id' => $session->id, 'sampler_id' => 10,
                'effective_duration' => $duration, 'is_active' => true,
            ]);
        }
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(1, $service->collect('2026-09-22', [10]));
        $this->assertCount(1, $service->unresolved(10));
    }

    private function troubleAssignment($key, $date = '2026-09-21', $duration = 1)
    {
        $session = SamplerTrackingSession::create([
            'team_key' => $key, 'tanggal_sampling' => $date, 'nama_perusahaan' => 'Client ' . $key,
            'is_active' => true,
        ]);
        $member = SamplerTrackingMember::create([
            'sampler_tracking_session_id' => $session->id, 'sampler_id' => 10,
            'sampler_name' => 'Asep', 'effective_duration' => $duration, 'is_active' => true,
        ]);
        return [$session, $member];
    }

    private function finishTroubleAssignment($session, $member)
    {
        foreach (['departure', 'checkin', 'checkout', 'return'] as $type) {
            $this->db->getConnection('mysql')->table('sampler_tracking_events')->insert([
                'sampler_tracking_session_id' => $session->id,
                'sampler_tracking_member_id' => $member->id,
                'event_type' => $type, 'event_at' => '2026-09-21 10:00:00',
            ]);
        }
    }

    public function testTwoAssignmentsSameSamplerAndDateCreateIndependentTroublesIdempotently(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        [$first] = $this->troubleAssignment('first-team');
        [$second] = $this->troubleAssignment('second-team');
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(2, $service->collect('2026-09-22', [10]));
        $this->assertSame(0, $service->collect('2026-09-22', [10]));
        $rows = $service->unresolved(10);
        $this->assertCount(2, $rows);
        $this->assertEquals([$first->id, $second->id], $rows->pluck('tracking_session_id')->all());
        $this->assertSame(['2026-09-21'], $rows->pluck('activity_date')->unique()->values()->all());
    }

    public function testFinishedAssignmentCannotCompleteAnotherTeamsAssignment(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        [$first, $firstMember] = $this->troubleAssignment('finished-team');
        [$second] = $this->troubleAssignment('unfinished-team');
        $this->finishTroubleAssignment($first, $firstMember);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(1, $service->collect('2026-09-22', [10]));
        $this->assertEquals($second->id, $service->unresolved(10)->first()->tracking_session_id);
    }

    public function testReopenedAssignmentWaitsForOwnReturnAfterCheckout(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        [$first, $firstMember] = $this->troubleAssignment('returned-team');
        [$second, $secondMember] = $this->troubleAssignment('recovery-team');
        $this->finishTroubleAssignment($first, $firstMember);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(1, $service->collect('2026-09-22', [10]));
        $trouble = $service->unresolved(10)->first();
        $service->reopen($trouble->id, 601, ['note' => 'Finish second team']);
        $connection = $this->db->getConnection('mysql');
        foreach (['departure', 'checkin', 'checkout', 'return'] as $type) {
            $service->assertAllowed(10, '2026-09-21', $second->id);
            $connection->table('sampler_tracking_events')->insert([
                'sampler_tracking_session_id' => $second->id,
                'sampler_tracking_member_id' => $secondMember->id,
                'event_type' => $type, 'event_at' => '2026-09-23 10:00:00',
            ]);
            if ($type !== 'return') {
                $remaining = $service->unresolved(10);
                $this->assertCount(1, $remaining);
                $this->assertEquals($second->id, $remaining->first()->tracking_session_id);
                $this->assertTrue($service->isReopened(10, $second->id));
            }
        }
        $this->assertCount(0, $service->unresolved(10));
        $this->assertEquals(1, $connection->table('sampler_tracking_troubles')->find($trouble->id)->is_clear);
    }

    public function testPrematurelyClearedRecoveryReappearsUntilOwnReturn(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        [$first, $firstMember] = $this->troubleAssignment('returned-team');
        [$second, $member] = $this->troubleAssignment('prematurely-cleared-team');
        $this->finishTroubleAssignment($first, $firstMember);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $service->collect('2026-09-22', [10]);
        $trouble = $service->unresolved(10)->first();
        $service->reopen($trouble->id, 601, ['note' => 'Recover second team']);
        $connection = $this->db->getConnection('mysql');
        foreach (['departure', 'checkin', 'checkout'] as $type) {
            $connection->table('sampler_tracking_events')->insert([
                'sampler_tracking_session_id' => $second->id,
                'sampler_tracking_member_id' => $member->id,
                'event_type' => $type, 'event_at' => '2026-09-23 09:00:00',
            ]);
        }
        $connection->table('sampler_tracking_troubles')->where('id', $trouble->id)->update([
            'is_clear' => 1, 'cleared_at' => '2026-09-23 09:00:00',
        ]);
        $remaining = $service->unresolved(10);
        $this->assertCount(1, $remaining);
        $this->assertEquals($second->id, $remaining->first()->tracking_session_id);
        $this->assertNull($remaining->first()->cleared_at);
        $this->assertTrue($service->isReopened(10, $second->id));
        $service->assertAllowed(10, '2026-09-21', $second->id);
        $this->assertCount(1, $this->service->listByDate('2026-09-21', 10, null, [$second->id]));
        $connection->table('sampler_tracking_events')->insert([
            'sampler_tracking_session_id' => $second->id,
            'sampler_tracking_member_id' => $member->id,
            'event_type' => 'return', 'event_at' => '2026-09-23 10:00:00',
        ]);
        $this->assertCount(0, $service->unresolved(10));
        $this->assertCount(0, $service->unresolved(10));
        $this->assertEquals(1, $connection->table('sampler_tracking_troubles')->find($trouble->id)->is_clear);
    }

    public function testSameDayOvernightAssignmentDoesNotBlockSesaatUntilOvernightIsDue(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        $this->troubleAssignment('sesaat-pt', '2026-09-21', 0);
        $this->troubleAssignment('overnight-pt', '2026-09-21', 2);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(0, $service->collect('2026-09-21', [10]));
        $this->assertCount(0, $service->unresolved(10));
        $service->assertAllowed(10, '2026-09-23');
        $this->assertSame(2, $service->collect('2026-09-22', [10]));
        $this->assertCount(2, $service->unresolved(10));
    }

    public function testAllUnfinishedAssignmentsAreCollectedAfterLongestDeadline(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-25 10:00:00', 'Asia/Jakarta'));
        $this->troubleAssignment('short-team', '2026-09-21', 1);
        $this->troubleAssignment('long-team', '2026-09-21', 4);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(0, $service->collect('2026-09-22', [10]));
        $this->assertCount(0, $service->unresolved(10));
        $service->assertAllowed(10, '2026-09-25');
        $this->assertSame(2, $service->collect('2026-09-24', [10]));
        $this->assertCount(2, $service->unresolved(10));
    }

    public function testUnblockingAndFinishingOneAssignmentDoesNotUnlockOrClearTheOther(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        [$first, $firstMember] = $this->troubleAssignment('unblocked-team');
        [$second] = $this->troubleAssignment('still-blocked-team');
        $service = new \App\Services\SamplerTrackingTroubleService();
        $service->collect('2026-09-22', [10]);
        $troubles = $service->unresolved(10);
        $firstTrouble = $troubles->firstWhere('tracking_session_id', $first->id);
        $service->reopen($firstTrouble->id, 601, ['note' => 'Only first assignment', 'reopen_reason' => 'technical_issue']);
        $service->assertAllowed(10, '2026-09-21', $first->id);
        try {
            $service->assertAllowed(10, '2026-09-21', $second->id);
            $this->fail('Another assignment must remain locked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(423, $exception->getStatusCode());
        }
        $this->finishTroubleAssignment($first, $firstMember);
        $remaining = $service->unresolved(10);
        $this->assertCount(1, $remaining);
        $this->assertEquals($second->id, $remaining->first()->tracking_session_id);
        $this->assertNull($remaining->first()->reopened_at);
        try {
            $service->assertAllowed(10, '2026-09-23');
            $this->fail('Unfinished second assignment must still block new work.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(423, $exception->getStatusCode());
        }
    }

    public function testLegacyTroubleIsLeftUntouchedAndDoesNotGrantSessionAccess(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        [$session] = $this->troubleAssignment('new-session');
        $connection = $this->db->getConnection('mysql');
        $legacyId = $connection->table('sampler_tracking_troubles')->insertGetId([
            'sampler_id' => 10, 'activity_date' => '2026-09-21', 'is_clear' => 0,
            'reopened_by' => 601, 'reopened_at' => '2026-09-22 08:00:00',
        ]);
        $original = $connection->table('sampler_tracking_troubles')->find($legacyId);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(1, $service->collect('2026-09-22', [10]));
        $this->assertCount(1, $service->unresolved(10));
        $this->assertEquals($original, $connection->table('sampler_tracking_troubles')->find($legacyId));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assertAllowed(10, '2026-09-21', $session->id);
    }

    public function testRecoveryAndAdminRowsAreScopedBeforeSessionConsolidation(): void
    {
        [$first] = $this->troubleAssignment('first-team', '2026-09-17');
        [$second] = $this->troubleAssignment('second-team', '2026-09-17');
        $sessions = $this->service->listByDate('2026-09-17', 10, null, [$first->id]);
        $this->assertCount(1, $sessions);
        $this->assertEquals([$first->id], $sessions->first()->activity_session_ids);
        $rows = $this->service->listTrackingRows('2026-09-17', 10, null, null, [$second->id])['data'];
        $this->assertCount(1, $rows);
        $this->assertEquals($second->id, $rows->first()['sessions']->first()->id);
    }

    public function testRecoveryEventCannotPropagateToAnotherBlockedSourceSession(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        $connection = $this->db->getConnection('mysql');
        $connection->getSchemaBuilder()->table('order_header', function (Blueprint $table) {
            $table->integer('id_pelanggan')->nullable();
        });
        $connection->table('order_header')->insert([
            'no_document' => 'Q1', 'no_order' => 'O1', 'id_pelanggan' => 100, 'is_active' => true,
        ]);
        $this->schedule(['tanggal' => '2026-09-21']);
        $this->schedule(['tanggal' => '2026-09-21', 'jam_mulai' => '13:00:00']);
        $this->prepare('2026-09-21');
        $sessions = SamplerTrackingSession::orderBy('id')->get();
        $first = $sessions->first();
        $second = $sessions->last();
        // Normal display consolidates this customer's stops. Recovery must filter
        // the source session first, not accidentally write to both source rows.
        $this->assertCount(1, $this->service->listByDate('2026-09-21', 10));
        $troubleService = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(2, $troubleService->collect('2026-09-22', [10]));
        $trouble = $troubleService->unresolved(10)->firstWhere('tracking_session_id', $first->id);
        $troubleService->reopen($trouble->id, 601, ['note' => 'First only']);
        $member = $first->activeMembers()->firstOrFail();
        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']);
        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'checkin']);
        $this->assertSame(2, $first->events()->count());
        $this->assertSame(0, $second->events()->count());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->storeEvent(['member_id' => $second->activeMembers()->firstOrFail()->id, 'event_type' => 'departure']);
    }


    public function testCompletedSesaatSharesReturnButMissingCheckoutGetsItsOwnTrouble(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-22 10:00:00', 'Asia/Jakarta'));
        [$short, $member] = $this->troubleAssignment('sesaat', '2026-09-21', 0);
        [$long] = $this->troubleAssignment('overnight', '2026-09-21', 2);
        $this->attendance($short);
        $service = new \App\Services\SamplerTrackingTroubleService();
        $this->assertSame(0, $service->collect('2026-09-21', [10]));
        $service->assertAllowed(10, '2026-09-21', $long->id);
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 10:00:00', 'Asia/Jakarta'));
        $this->assertSame(1, $service->collect('2026-09-22', [10]));
        $this->assertEquals($long->id, $service->unresolved(10)->first()->tracking_session_id);
        $this->assertSame(0, $service->collect('2026-09-22', [10]));
        $member->events()->where('event_type', 'checkout')->delete();
        $this->assertSame(1, $service->collect('2026-09-22', [10]));
        $this->assertCount(2, $service->unresolved(10));
        $this->assertSame(0, $service->collect('2026-09-22', [10]));
    }


    public function testTrackingSummaryGroupsDailyMemberSetsAndRetainsVisits(): void
    {
        [$first] = $this->troubleAssignment('first', '2026-09-21');
        [$second] = $this->troubleAssignment('second', '2026-09-21');
        [$third] = $this->troubleAssignment('third', '2026-09-21');
        $sessions = SamplerTrackingSession::with('activeMembers.events')->get();
        $rows = $this->service->buildTrackingRows($sessions);
        $this->assertCount(1, $rows);
        $this->assertSame('Asep', $rows->first()['sampler']);
        $this->assertEquals([$first->id, $second->id, $third->id], $rows->first()['sessions']->pluck('id')->all());
        $this->assertCount(3, $rows->first()['perusahaan_list']);
        foreach ([$first, $second] as $session) {
            SamplerTrackingMember::create([
                'sampler_tracking_session_id' => $session->id, 'sampler_id' => 20,
                'sampler_name' => 'Andik', 'effective_duration' => 1, 'is_active' => true,
            ]);
        }
        $this->troubleAssignment('tomorrow', '2026-09-22');
        $sessions = SamplerTrackingSession::with('activeMembers.events')->get();
        $secondSession = $sessions->firstWhere('id', $second->id);
        $secondSession->setRelation('activeMembers', $secondSession->activeMembers->reverse()->values());
        $rows = $this->service->buildTrackingRows($sessions);
        $this->assertCount(3, $rows);
        $team = $rows->firstWhere('sampler', 'Asep, Andik');
        $this->assertNotNull($team);
        $this->assertEquals([$first->id, $second->id], $team['sessions']->pluck('id')->all());
        $this->assertCount(4, $team['members']);
        $this->assertSame(3, $rows->pluck('row_id')->unique()->count());
        $this->assertSame(4, SamplerTrackingSession::count());
    }

    public function testTrackingSummarySeparatesTeamMembersByEffectiveDuration(): void
    {
        $this->schedule(['sampler' => 'Satrio', 'userid' => 10, 'durasi' => 1]);
        $this->schedule(['sampler' => 'Hafizh', 'userid' => 20, 'durasi' => 2]);
        $this->schedule(['sampler' => 'Marcellius', 'userid' => 30, 'durasi' => 2]);
        $session = $this->prepare('2026-09-17')->first();
        $satrio = $session->activeMembers()->where('sampler_name', 'Satrio')->firstOrFail();

        $this->db->getConnection('mysql')->table('sampler_tracking_events')->insert([
            'sampler_tracking_session_id' => $session->id,
            'sampler_tracking_member_id' => $satrio->id,
            'event_type' => 'return',
            'event_at' => '2026-09-17 18:00:00',
        ]);

        $rows = $this->service->buildTrackingRows(
            SamplerTrackingSession::with('activeMembers.events')->where('id', $session->id)->get()
        );

        $this->assertCount(2, $rows);
        $short = $rows->firstWhere('sampler', 'Satrio');
        $long = $rows->firstWhere('sampler', 'Hafizh, Marcellius');
        $this->assertSame('8 Jam', $short['durasi']);
        $this->assertSame('completed', $short['tracking_status']);
        $this->assertSame('1 x 24 Jam', $long['durasi']);
        $this->assertSame('ongoing', $long['tracking_status']);
        $this->assertCount(1, $short['members']);
        $this->assertCount(2, $long['members']);
    }


    public function testJourneyDepartureReferenceRespectsSamplerReturnAndCheckinTime(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-21 12:00:00', 'Asia/Jakarta'));
        [$first, $a] = $this->troubleAssignment('first-team');
        [$second, $b] = $this->troubleAssignment('second-team');
        $other = SamplerTrackingMember::create([
            'sampler_tracking_session_id' => $second->id, 'sampler_id' => 20,
            'sampler_name' => 'Eko', 'is_active' => true,
        ]);
        $events = $this->db->getConnection('mysql')->table('sampler_tracking_events');
        $departureId = $events->insertGetId([
            'sampler_tracking_session_id' => $first->id, 'sampler_tracking_member_id' => $a->id,
            'event_type' => 'departure', 'event_at' => '2026-09-21 08:00:00',
        ]);
        $this->assertEquals($departureId, $this->service->departureForMember($b->fresh(), '2026-09-21')->id);
        $this->assertNull($this->service->departureForMember($other->fresh(), '2026-09-21'));
        $visible = $this->service->listByDate('2026-09-21', 10, null, [$second->id]);
        $reference = $visible->first()->activeMembers->firstWhere('sampler_id', 10)->events->first();
        $this->assertTrue($reference->is_journey_reference);
        $this->assertEquals($first->id, $reference->sampler_tracking_session_id);
        $this->assertSame(1, $events->count());
        $events->insert([
            'sampler_tracking_session_id' => $first->id, 'sampler_tracking_member_id' => $a->id,
            'event_type' => 'return', 'event_at' => '2026-09-21 10:00:00',
        ]);
        $this->assertNull($this->service->departureForMember($b->fresh(), '2026-09-21'));
        $events->insert([
            'sampler_tracking_session_id' => $second->id, 'sampler_tracking_member_id' => $b->id,
            'event_type' => 'checkin', 'event_at' => '2026-09-21 09:00:00',
        ]);
        $this->assertEquals($departureId, $this->service->departureForMember($b->fresh(), '2026-09-21')->id);
    }

    public function testRecoveryClearsWithReferencedDepartureAndOwnReturn(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-23 12:00:00', 'Asia/Jakarta'));
        [$first, $a] = $this->troubleAssignment('first');
        [$second, $b] = $this->troubleAssignment('second');
        $events = $this->db->getConnection('mysql')->table('sampler_tracking_events');
        $events->insert([
            'sampler_tracking_session_id' => $first->id, 'sampler_tracking_member_id' => $a->id,
            'event_type' => 'departure', 'event_at' => '2026-09-21 08:00:00',
        ]);
        foreach (['checkin', 'checkout'] as $type) {
            foreach ([[$first, $a], [$second, $b]] as [$session, $member]) {
                $events->insert([
                    'sampler_tracking_session_id' => $session->id, 'sampler_tracking_member_id' => $member->id,
                    'event_type' => $type, 'event_at' => '2026-09-21 09:00:00',
                ]);
            }
        }
        $service = new \App\Services\SamplerTrackingTroubleService();
        $service->collect('2026-09-22', [10]);
        $trouble = $service->unresolved(10)->firstWhere('tracking_session_id', $second->id);
        $service->reopen($trouble->id, 601, ['note' => 'Second team recovery']);
        $events->insert([
            'sampler_tracking_session_id' => $first->id, 'sampler_tracking_member_id' => $a->id,
            'event_type' => 'return', 'event_at' => '2026-09-23 10:00:00',
        ]);
        $this->assertTrue($service->unresolved(10)->contains('tracking_session_id', $second->id));
        $events->insert([
            'sampler_tracking_session_id' => $second->id, 'sampler_tracking_member_id' => $b->id,
            'event_type' => 'return', 'event_at' => '2026-09-23 11:00:00',
        ]);
        $this->assertCount(0, $service->unresolved(10));
        $this->assertSame(0, $b->events()->where('event_type', 'departure')->count());
    }


    public function testCrossTeamMultidayCheckoutAndReturnUseOriginalDeparture(): void
    {
        $connection = $this->db->getConnection('mysql');
        $connection->getSchemaBuilder()->create('persiapan_sampel_header', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_quotation');
            $table->date('tanggal_sampling');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_emailed_bas')->default(true);
        });
        foreach ([2, 3] as $duration) {
            $date = $duration === 2 ? '2026-09-21' : '2026-09-25';
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse($date . ' 08:00:00', 'Asia/Jakarta'));
            foreach ([[10, 'A'], [20, 'Andik']] as [$id, $name]) {
                $this->schedule(['tanggal' => $date, 'userid' => $id, 'sampler' => $name, 'durasi' => 0]);
            }
            foreach ([[10, 'A'], [30, 'Eko']] as [$id, $name]) {
                $this->schedule(['tanggal' => $date, 'no_quotation' => 'Q2', 'jam_mulai' => '11:00:00', 'userid' => $id, 'sampler' => $name, 'durasi' => $duration]);
            }
            $connection->table('persiapan_sampel_header')->insert([
                ['no_quotation' => 'Q1', 'tanggal_sampling' => $date],
                ['no_quotation' => 'Q2', 'tanggal_sampling' => $date],
            ]);
            $this->prepare($date);
            $first = SamplerTrackingSession::where('tanggal_sampling', $date)->where('no_quotation', 'Q1')->firstOrFail();
            $second = SamplerTrackingSession::where('tanggal_sampling', $date)->where('no_quotation', 'Q2')->firstOrFail();
            $a = $first->activeMembers()->where('sampler_id', 10)->firstOrFail();
            $b = $second->activeMembers()->where('sampler_id', 10)->firstOrFail();
            foreach (['departure', 'checkin', 'checkout'] as $type) {
                $this->service->storeEvent(['member_id' => $a->id, 'event_type' => $type]);
            }
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse($date . ' 11:00:00', 'Asia/Jakarta'));
            $this->service->storeEvent(['member_id' => $b->id, 'event_type' => 'checkin']);
            $due = \Carbon\Carbon::parse($date)->addDays($duration - 1);
            \Carbon\Carbon::setTestNow($due->copy()->setTime(10, 0));
            $troubles = new \App\Services\SamplerTrackingTroubleService();
            $this->assertSame(0, $troubles->collect($due->copy()->subDay()->toDateString(), [10]));
            $this->assertCount(0, $troubles->unresolved(10));
            $visible = $this->service->listByDate($due->toDateString(), 10);
            $this->assertTrue($visible->contains('id', $second->id));
            $member = $visible->firstWhere('id', $second->id)->activeMembers->firstWhere('sampler_id', 10);
            $this->assertTrue(\App\Services\SamplerTrackingActivity::hasEvent($member, 'departure'));
            $this->assertTrue(\App\Services\SamplerTrackingActivity::hasEvent($member, 'checkin'));
            $this->service->storeEvent(['member_id' => $b->id, 'event_type' => 'checkout']);
            $this->service->storeEvent(['member_id' => $b->id, 'event_type' => 'return']);
            $this->assertSame(1, $b->events()->where('event_type', 'checkout')->count());
            $this->assertSame(1, $b->events()->where('event_type', 'return')->count());
            $this->assertSame(0, $b->events()->where('event_type', 'departure')->count());
            $this->assertCount(0, $troubles->unresolved(10));
        }
    }


    public function testBlockedTabRowIgnoresTeammateReturnWhenPinningTroubleSampler(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-24 10:00:00', 'Asia/Jakarta'));
        $session = SamplerTrackingSession::create([
            'team_key' => 'enseval-team',
            'tanggal_sampling' => '2026-09-23',
            'nama_perusahaan' => 'ENSEVAL PUTERA MEGATRADING, PT',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '10:00:00',
            'is_active' => true,
        ]);
        $erik = SamplerTrackingMember::create([
            'sampler_tracking_session_id' => $session->id,
            'sampler_id' => 10,
            'sampler_name' => 'Erik Suhendar',
            'effective_duration' => 1,
            'is_active' => true,
        ]);
        $dhanu = SamplerTrackingMember::create([
            'sampler_tracking_session_id' => $session->id,
            'sampler_id' => 20,
            'sampler_name' => 'Dhanuarta Dwika Apriansyah',
            'effective_duration' => 1,
            'is_active' => true,
        ]);
        $connection = $this->db->getConnection('mysql');
        foreach (['departure', 'checkin', 'checkout'] as $type) {
            $connection->table('sampler_tracking_events')->insert([
                'sampler_tracking_session_id' => $session->id,
                'sampler_tracking_member_id' => $erik->id,
                'event_type' => $type,
                'event_at' => '2026-09-23 09:00:00',
            ]);
            $connection->table('sampler_tracking_events')->insert([
                'sampler_tracking_session_id' => $session->id,
                'sampler_tracking_member_id' => $dhanu->id,
                'event_type' => $type,
                'event_at' => '2026-09-23 09:30:00',
            ]);
        }
        $connection->table('sampler_tracking_events')->insert([
            'sampler_tracking_session_id' => $session->id,
            'sampler_tracking_member_id' => $erik->id,
            'event_type' => 'return',
            'event_at' => '2026-09-24 10:54:00',
        ]);
        $troubleId = $connection->table('sampler_tracking_troubles')->insertGetId([
            'tracking_session_id' => $session->id,
            'sampler_id' => 20,
            'activity_date' => '2026-09-23',
            'is_clear' => 0,
        ]);
        $trouble = $connection->table('sampler_tracking_troubles')->find($troubleId);
        $reflection = new \ReflectionClass(\App\Http\Controllers\api\SamplerTrackingController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($controller, $this->service);
        $attach = $reflection->getMethod('attachTroubleToTrackingRows');
        $attach->setAccessible(true);
        $sampler = (object) ['id' => 20, 'nama_lengkap' => 'Dhanuarta Dwika Apriansyah'];
        $attached = $attach->invoke($controller, $trouble, $sampler, []);
        $consolidate = $reflection->getMethod('consolidateBlockedTeamRows');
        $consolidate->setAccessible(true);
        $rows = $consolidate->invoke($controller, $attached);
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('Dhanuarta Dwika Apriansyah', $row['sampler']);
        $this->assertSame('overdue', $row['tracking_status']);
        $this->assertStringNotContainsString('return', strtolower($row['last_event']));
    }


    public function testBlockedTabMergesSameSessionWhileWholeTeamStillOut(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-24 10:00:00', 'Asia/Jakarta'));
        $session = SamplerTrackingSession::create([
            'team_key' => 'sesaat-duo',
            'tanggal_sampling' => '2026-09-23',
            'nama_perusahaan' => 'PT Contoh Tim',
            'is_active' => true,
        ]);
        $first = SamplerTrackingMember::create([
            'sampler_tracking_session_id' => $session->id,
            'sampler_id' => 10,
            'sampler_name' => 'Erik Suhendar',
            'effective_duration' => 0,
            'is_active' => true,
        ]);
        $second = SamplerTrackingMember::create([
            'sampler_tracking_session_id' => $session->id,
            'sampler_id' => 20,
            'sampler_name' => 'Dhanuarta Dwika Apriansyah',
            'effective_duration' => 0,
            'is_active' => true,
        ]);
        foreach ([$first, $second] as $member) {
            foreach (['departure', 'checkin', 'checkout'] as $type) {
                $this->db->getConnection('mysql')->table('sampler_tracking_events')->insert([
                    'sampler_tracking_session_id' => $session->id,
                    'sampler_tracking_member_id' => $member->id,
                    'event_type' => $type,
                    'event_at' => '2026-09-23 09:00:00',
                ]);
            }
        }
        $connection = $this->db->getConnection('mysql');
        $ids = [];
        foreach ([10, 20] as $samplerId) {
            $ids[] = $connection->table('sampler_tracking_troubles')->insertGetId([
                'tracking_session_id' => $session->id,
                'sampler_id' => $samplerId,
                'activity_date' => '2026-09-23',
                'is_clear' => 0,
            ]);
        }
        $reflection = new \ReflectionClass(\App\Http\Controllers\api\SamplerTrackingController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($controller, $this->service);
        $attach = $reflection->getMethod('attachTroubleToTrackingRows');
        $attach->setAccessible(true);
        $consolidate = $reflection->getMethod('consolidateBlockedTeamRows');
        $consolidate->setAccessible(true);
        $rows = collect();
        foreach ($ids as $troubleId) {
            $trouble = $connection->table('sampler_tracking_troubles')->find($troubleId);
            $samplerId = (int) $trouble->sampler_id;
            $name = $samplerId === 10 ? 'Erik Suhendar' : 'Dhanuarta Dwika Apriansyah';
            $rows = $rows->merge($attach->invoke(
                $controller,
                $trouble,
                (object) ['id' => $samplerId, 'nama_lengkap' => $name],
                []
            ));
        }
        $merged = $consolidate->invoke($controller, $rows);
        $this->assertCount(1, $merged);
        $this->assertSame('Erik Suhendar, Dhanuarta Dwika Apriansyah', $merged->first()['sampler']);
        $this->assertSame('overdue', $merged->first()['tracking_status']);
        $this->assertSame('trouble-session-' . $session->id, $merged->first()['row_id']);
    }


    public function testBlockedRowsAndUnblockAreScopedToSession(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-22 10:00:00', 'Asia/Jakarta'));
        [$first] = $this->troubleAssignment('Agung-Amru');
        [$second] = $this->troubleAssignment('other-team');
        $connection = $this->db->getConnection('mysql');
        $ids = [];
        foreach ([[$first->id, 10], [$first->id, 20], [$second->id, 10]] as [$sessionId, $samplerId]) {
            $ids[] = $connection->table('sampler_tracking_troubles')->insertGetId([
                'tracking_session_id' => $sessionId, 'sampler_id' => $samplerId,
                'activity_date' => '2026-09-21', 'is_clear' => 0,
            ]);
        }
        $reflection = new \ReflectionClass(\App\Http\Controllers\api\SamplerTrackingController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('uniqueTrackingRows');
        $method->setAccessible(true);
        $rows = collect([
            [
                'row_id' => 'trouble-' . $ids[0],
                'tracking_session_id' => $first->id,
                'trouble_id' => $ids[0],
                'tanggal_sampling' => '2026-09-21',
                'sampler' => 'Agung',
                'members' => [],
                'events' => [],
                'trouble' => ['sampler_id' => 10, 'sampler_name' => 'Agung'],
            ],
            [
                'row_id' => 'trouble-' . $ids[1],
                'tracking_session_id' => $first->id,
                'trouble_id' => $ids[1],
                'tanggal_sampling' => '2026-09-21',
                'sampler' => 'Amru',
                'members' => [],
                'events' => [],
                'trouble' => ['sampler_id' => 20, 'sampler_name' => 'Amru'],
            ],
            [
                'row_id' => 'trouble-' . $ids[2],
                'tracking_session_id' => $second->id,
                'trouble_id' => $ids[2],
                'tanggal_sampling' => '2026-09-21',
                'sampler' => 'Agung',
                'members' => [],
                'events' => [],
                'trouble' => ['sampler_id' => 10, 'sampler_name' => 'Agung'],
            ],
        ]);
        $consolidate = $reflection->getMethod('consolidateBlockedTeamRows');
        $consolidate->setAccessible(true);
        $rows = $consolidate->invoke($controller, $rows);
        $grouped = $method->invoke($controller, $rows);
        $this->assertCount(2, $grouped);
        $this->assertSame('Agung, Amru', $grouped->first()['sampler']);
        (new \App\Services\SamplerTrackingTroubleService())->reopen($grouped->first()['trouble_id'], 601, [
            'session_id' => $first->id, 'note' => 'Open team',
        ]);
        $this->assertSame(2, $connection->table('sampler_tracking_troubles')->where('tracking_session_id', $first->id)->whereNotNull('reopened_at')->count());
        $this->assertNull($connection->table('sampler_tracking_troubles')->find($ids[2])->reopened_at);
    }


    public function testQuotationRevisionPreservesSessionEvidenceAndUpdatedCategories(): void
    {
        foreach (['old-header', 'new-header', 'manual'] as $index => $mode) {
            $old = 'ISL/QT/26-IX/01854' . $index . 'R1';
            $new = 'ISL/QT/26-IX/01854' . $index . 'R2';
            $schedule = $this->schedule(['no_quotation' => $old, 'id_sampling' => 100 + $index, 'kategori' => '["Air - 001","Udara - 002"]']);
            $session = $this->prepare('2026-09-17')->firstWhere('no_quotation', $old);
            $member = $this->attendance($session);
            $eventIds = $member->events()->pluck('id')->all();
            // Simulate quotation job: the original schedule row is retained.
            Jadwal::where('id', $schedule->id)->update(['no_quotation' => $new, 'kategori' => '["Udara - 002","Kebisingan - 003"]', 'durasi' => 2]);
            if ($mode === 'manual') {
                $this->service->sync('2026-09-17');
            } else {
                $header = new PersiapanSampelHeader();
                $header->no_quotation = $mode === 'old-header' ? $old : $new;
                $header->tanggal_sampling = '2026-09-17';
                $this->service->syncByPersiapanHeader($header);
            }
            $current = $session->fresh();
            $this->assertSame($new, $current->no_quotation);
            $this->assertTrue((bool) $current->is_active);
            $this->assertEquals(['Udara - 002', 'Kebisingan - 003'], json_decode($current->kategori, true));
            $this->assertSame($eventIds, $member->fresh()->events()->pluck('id')->all());
            $this->assertEquals(2, $member->fresh()->effective_duration);
            $this->service->syncQuotation($new);
            $this->assertSame(1, SamplerTrackingSession::where('no_quotation', $new)->count());
            // A later schedule edit must still preserve the migrated identity.
            $before = $this->service->snapshotSchedules($new);
            Jadwal::where('id', $schedule->id)->update(['jam_mulai' => '09:00:00', 'kategori' => '["Udara - 002"]']);
            $this->service->syncScheduleEdit($before, $new);
            $this->assertSame('09:00:00', $session->fresh()->jam_mulai);
            $this->assertEquals(['Udara - 002'], json_decode($session->fresh()->kategori, true));
        }
    }

    public function testRevisionWithoutPreparationCannotCreateActivity(): void
    {
        $row = $this->schedule(['no_quotation' => 'ISL/QT/26-IX/123R1']);
        Jadwal::where('id', $row->id)->update(['no_quotation' => 'ISL/QT/26-IX/123R2']);
        $this->service->syncQuotation('ISL/QT/26-IX/123R2');
        $this->assertSame(0, SamplerTrackingSession::count());
    }

    public function testInactiveOlderRevisionsDoNotBlockCurrentRevisionMigration(): void
    {
        $old = 'ISL/QT/26-IX/157R10';
        $new = 'ISL/QT/26-IX/157R11';
        $row = $this->schedule(['no_quotation' => $old, 'id_sampling' => 41396, 'parsial' => 201033]);
        $session = $this->prepare('2026-09-17')->firstWhere('no_quotation', $old);
        $this->attendance($session);

        // These are old, superseded visits from the same sampling plan. They
        // have evidence/history but are inactive, so a current QT revision
        // must leave them untouched rather than attempting to migrate them.
        $archived = SamplerTrackingSession::create([
            'team_key' => 'archived-r9-session', 'no_quotation' => 'ISL/QT/26-IX/157R9',
            'id_sampling' => 41396, 'parsial' => 199165, 'tanggal_sampling' => '2026-09-17',
            'is_active' => false,
        ]);
        $archivedKey = $archived->team_key;

        Jadwal::where('id', $row->id)->update(['no_quotation' => $new]);
        $this->service->syncQuotation($new);

        $this->assertSame($new, $session->fresh()->no_quotation);
        $this->assertTrue((bool) $session->fresh()->is_active);
        $this->assertSame('ISL/QT/26-IX/157R9', $archived->fresh()->no_quotation);
        $this->assertSame($archivedKey, $archived->fresh()->team_key);
        $this->assertFalse((bool) $archived->fresh()->is_active);
    }

    public function testRescheduleSamplingPlanOnSameOrderDatePromotesSessionWithEvidence(): void
    {
        $this->db->getConnection('mysql')->table('order_header')->insert([
            'no_document' => 'Q1',
            'no_order' => 'ORD-RESCHEDULE',
            'is_active' => 1,
        ]);
        $this->db->getConnection('mysql')->table('sampling_plan')->insert([
            ['id' => 100, 'no_quotation' => 'Q1'],
            ['id' => 200, 'no_quotation' => 'Q1'],
        ]);

        $row = $this->schedule([
            'id_sampling' => 100,
            'tanggal' => '2026-09-23',
            'kendaraan' => 'B 1963 NZK',
        ]);
        $session = $this->prepare('2026-09-23')->first();
        $member = $this->attendance($session);
        $eventIds = $member->events()->pluck('id')->all();

        Jadwal::where('id', $row->id)->update(['is_active' => false]);
        $this->schedule([
            'id_sampling' => 200,
            'parsial' => 203540,
            'tanggal' => '2026-09-23',
            'kendaraan' => 'B 9030 JMV',
        ]);

        $header = new PersiapanSampelHeader();
        $header->no_quotation = 'Q1';
        $header->tanggal_sampling = '2026-09-23';
        $this->service->syncByPersiapanHeader($header);

        $active = SamplerTrackingSession::where('no_quotation', 'Q1')
            ->where('tanggal_sampling', '2026-09-23')
            ->where('is_active', true)
            ->get();

        $this->assertCount(1, $active);
        $keeper = $active->first();
        $this->assertSame((int) $session->id, (int) $keeper->id);
        $this->assertSame(200, (int) $keeper->id_sampling);
        $this->assertSame(203540, (int) $keeper->parsial);
        $this->assertSame('B 9030 JMV', $keeper->kendaraan);
        $this->assertSame($eventIds, $member->fresh()->events()->pluck('id')->all());
    }

    public function testRevisionDoesNotMoveEvidenceToDifferentVisit(): void
    {
        $old = 'ISL/QT/26-IX/456R1';
        $new = 'ISL/QT/26-IX/456R2';
        $row = $this->schedule(['no_quotation' => $old]);
        $session = $this->prepare('2026-09-17')->first();
        $member = $this->attendance($session);
        Jadwal::where('id', $row->id)->update(['is_active' => false]);
        $this->schedule(['no_quotation' => $new, 'id_sampling' => 999]);
        $this->service->syncQuotation($old);
        $this->assertSame($old, $session->fresh()->no_quotation);
        $this->assertFalse((bool) $session->fresh()->is_active);
        $this->assertSame(2, $member->events()->count());
        $this->assertSame(0, SamplerTrackingSession::where('no_quotation', $new)->count());
    }


    public function testMultipleTeamsInOneQuotationKeepTheirOwnHistoryAcrossRevisions(): void
    {
        $old = 'ISL/QT/26-IX/777R1';
        $new = 'ISL/QT/26-IX/777R2';
        foreach ([['08:00:00', 10, 'Asep'], ['13:00:00', 20, 'Andik']] as [$time, $id, $name]) {
            $this->schedule(['no_quotation' => $old, 'jam_mulai' => $time, 'userid' => $id, 'sampler' => $name]);
        }
        $this->schedule(['no_quotation' => $old, 'tanggal' => '2026-09-18', 'userid' => 30, 'sampler' => 'Eko']);
        $this->prepare('2026-09-17');
        $this->prepare('2026-09-18');
        $sessions = SamplerTrackingSession::orderBy('id')->get();
        $this->assertCount(3, $sessions);
        $evidence = [];
        foreach ($sessions as $session) {
            $member = $this->attendance($session);
            $evidence[$session->id] = [$member->id, $member->sampler_id, $member->events()->pluck('id')->all()];
        }
        Jadwal::where('no_quotation', $old)->update(['no_quotation' => $new]);
        Jadwal::where('no_quotation', $new)->where('userid', 20)->update(['kategori' => '["Udara - 002","Kebisingan - 003"]', 'durasi' => 2]);
        $this->service->syncQuotation($old);
        $this->service->syncQuotation($new);
        $this->assertSame(3, SamplerTrackingSession::count());
        foreach ($sessions as $session) {
            $current = $session->fresh();
            $member = $current->activeMembers()->firstOrFail();
            $this->assertTrue((bool) $current->is_active);
            $this->assertSame($new, $current->no_quotation);
            $this->assertEquals($evidence[$session->id], [$member->id, $member->sampler_id, $member->events()->pluck('id')->all()]);
            $this->assertEquals($member->sampler_id == 20 ? ['Udara - 002', 'Kebisingan - 003'] : ['Air - 001'], json_decode($current->kategori, true));
        }
        Jadwal::where('no_quotation', $new)->update(['no_quotation' => 'ISL/QT/26-IX/777R3']);
        $this->service->syncQuotation('ISL/QT/26-IX/777R3');
        $this->assertSame(3, SamplerTrackingSession::where('no_quotation', 'ISL/QT/26-IX/777R3')->where('is_active', true)->count());
        $this->assertSame(6, $this->db->getConnection('mysql')->table('sampler_tracking_events')->count());
    }

    public function testRevisionCollisionRollsBackAllTeamsWithoutMovingEvidence(): void
    {
        $old = 'ISL/QT/26-IX/888R1';
        $new = 'ISL/QT/26-IX/888R2';
        $this->schedule(['no_quotation' => $old]);
        $second = $this->schedule(['no_quotation' => $old, 'jam_mulai' => '13:00:00']);
        $sessions = $this->prepare('2026-09-17');
        foreach ($sessions as $session) $this->attendance($session);
        Jadwal::where('no_quotation', $old)->update(['no_quotation' => $new]);
        $method = new \ReflectionMethod($this->service, 'makeTeamKey');
        $method->setAccessible(true);
        SamplerTrackingSession::create([
            'team_key' => $method->invoke($this->service, $second->fresh()),
            'no_quotation' => $new, 'tanggal_sampling' => '2026-09-17', 'is_active' => true,
        ]);
        try {
            $this->service->syncQuotation($old);
            $this->fail('Conflicting destination session must reject revision reconciliation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('jadwal', $exception->errors());
        }
        foreach ($sessions as $session) {
            $this->assertSame($old, $session->fresh()->no_quotation);
            $this->assertSame($session->team_key, $session->fresh()->team_key);
            $this->assertTrue((bool) $session->fresh()->is_active);
            $this->assertSame(2, $session->events()->count());
        }
    }

    private function schedule(array $values = [])
    {
        $id = $this->db->getConnection('mysql')->table('jadwal')->insertGetId(array_merge([
            'id_sampling' => 1, 'no_quotation' => 'Q1', 'tanggal' => '2026-09-17',
            'jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00', 'kendaraan' => 'B 1',
            'id_cabang' => 1, 'durasi' => 1, 'userid' => 10, 'sampler' => 'A',
            'kategori' => '["Air - 001"]', 'nama_perusahaan' => 'Client',
        ], $values));
        return Jadwal::findOrFail($id);
    }

    private function prepare($date)
    {
        $sessions = collect();
        foreach (Jadwal::where('is_active', true)->where('tanggal', $date)->pluck('no_quotation')->unique() as $quotation) {
            $header = new PersiapanSampelHeader();
            $header->no_quotation = $quotation;
            $header->tanggal_sampling = $date;
            $sessions = $sessions->merge($this->service->syncByPersiapanHeader($header));
        }
        return $sessions;
    }

    private function attendance($session)
    {
        $member = $session->activeMembers()->firstOrFail();
        foreach (['checkin', 'checkout'] as $type) {
            $this->db->getConnection('mysql')->table('sampler_tracking_events')->insert([
                'sampler_tracking_session_id' => $session->id,
                'sampler_tracking_member_id' => $member->id,
                'triggered_by_member_id' => $member->id,
                'event_type' => $type, 'event_at' => '2026-09-17 08:30:00',
                'photo' => 'existing-proof.jpg',
            ]);
        }
        return $member;
    }

    public function testCancellingLastScheduleDeactivatesSessionAndPreservesProof(): void
    {
        $row = $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $this->attendance($session);
        $before = $this->service->snapshotSchedules('Q1');
        $row->is_active = false;
        $row->save();
        $this->service->syncAfterScheduleVoid('Q1', $before);
        $this->assertFalse((bool) $session->fresh()->is_active);
        $this->assertCount(2, $member->events);
        $this->assertCount(0, $this->service->listByDate('2026-09-17'));
    }

    public function testEditDateTimeAndVehicleKeepsSessionMemberAndAttendanceIds(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $this->attendance($session);
        $eventIds = $member->events()->pluck('id')->all();
        $this->db->getConnection('mysql')->transaction(function () {
            $before = $this->service->snapshotSchedules('Q1');
            Jadwal::query()->update(['is_active' => false]);
            $this->schedule(['tanggal' => '2026-09-18', 'jam_mulai' => '09:00:00', 'kendaraan' => 'B 2']);
            $this->service->syncScheduleEdit($before, 'Q1');
        });
        $this->assertSame(1, SamplerTrackingSession::count());
        $this->assertSame('2026-09-18', $session->fresh()->tanggal_sampling);
        $this->assertSame('B 2', $session->fresh()->kendaraan);
        $this->assertSame($member->id, $session->activeMembers()->first()->id);
        $this->assertSame($eventIds, $member->events()->pluck('id')->all());
        $this->assertSame('existing-proof.jpg', $member->events()->first()->photo);
        $this->assertCount(1, $this->service->listByDate('2026-09-18'));
        $this->assertCount(0, $this->service->listByDate('2026-09-17'));
    }

    public function testSavingPreparationForOneSamplerDoesNotRemoveOtherTeamMembers(): void
    {
        $this->schedule();
        $this->schedule(['userid' => 20, 'sampler' => 'B']);
        $session = $this->prepare('2026-09-17')->first();
        $memberA = $this->attendance($session);
        $originalEventIds = $memberA->events()->pluck('id')->all();
        $header = new PersiapanSampelHeader();
        $header->no_quotation = 'Q1';
        $header->tanggal_sampling = '2026-09-17';
        $header->sampler_jadwal = 'A';
        $this->service->syncByPersiapanHeader($header);
        $memberB = $session->activeMembers()->where('sampler_name', 'B')->firstOrFail();
        $this->assertSame(2, $session->activeMembers()->count());
        $this->assertSame($originalEventIds, $memberA->fresh()->events()->pluck('id')->all());
        $this->assertSame(['checkin', 'checkout'], $memberA->events()->orderBy('event_type')->pluck('event_type')->all());
        $this->assertSame(2, $memberB->events()->count());
        $this->assertEquals(1, $memberB->events()->first()->is_auto);
        $this->assertStringContainsString('sumber event #', (string) $memberB->events()->first()->note);
        $this->assertSame(4, $session->events()->count());
        $this->service->syncByPersiapanHeader($header);
        $this->assertSame(4, $session->events()->count());
    }

    public function testCorrectingOriginalSamplerCopiesTeamAttendanceWithoutDeletingOriginal(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $oldMember = $this->attendance($session);
        $before = $this->service->snapshotSchedules('Q1');
        Jadwal::query()->update(['is_active' => false]);
        $this->schedule(['userid' => 20, 'sampler' => 'B']);
        $this->service->syncScheduleEdit($before, 'Q1');
        $this->assertFalse((bool) $oldMember->fresh()->is_active);
        $this->assertSame(2, $oldMember->events()->count());
        $newMember = $session->activeMembers()->firstOrFail();
        $this->assertSame('B', $newMember->sampler_name);
        $this->assertSame(2, $newMember->events()->count());
        $this->assertEquals(1, $newMember->events()->first()->is_auto);
        $this->assertEquals($oldMember->id, $newMember->events()->first()->triggered_by_member_id);
    }

    public function testQuotationSyncDoesNotDeactivateOtherQuotations(): void
    {
        $row = $this->schedule();
        $this->schedule(['no_quotation' => 'Q2', 'userid' => 20, 'sampler' => 'B']);
        $this->prepare('2026-09-17');
        $row->is_active = false;
        $row->save();
        $this->service->syncQuotation('Q1');
        $this->assertEquals(0, SamplerTrackingSession::where('no_quotation', 'Q1')->first()->is_active);
        $this->assertEquals(1, SamplerTrackingSession::where('no_quotation', 'Q2')->first()->is_active);
    }

    public function testInactiveSessionRejectsAnEventFromAnAlreadyOpenPage(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $session->activeMembers()->first();
        $session->is_active = false;
        $session->save();
        $this->expectException(ValidationException::class);
        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']);
    }

    public function testInactiveSourceScheduleRejectsEventEvenBeforeReconciliation(): void
    {
        $row = $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $session->activeMembers()->first();
        $row->is_active = false;
        $row->save();
        $this->expectException(ValidationException::class);
        $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']);
    }

    public function testConflictingEditRollsBackWithoutLosingAttendance(): void
    {
        $first = $this->schedule();
        $this->schedule(['jam_mulai' => '13:00:00']);
        $sessions = $this->prepare('2026-09-17');
        $member = $this->attendance($sessions->first());
        try {
            $this->db->getConnection('mysql')->transaction(function () use ($first) {
                $before = $this->service->snapshotSchedules('Q1');
                $first->jam_mulai = '13:00:00';
                $first->save();
                $this->service->syncScheduleEdit($before, 'Q1');
            });
            $this->fail('A different visit must not absorb existing attendance.');
        } catch (ValidationException $exception) {
            $this->assertSame('08:00:00', $first->fresh()->jam_mulai);
            $this->assertSame(2, SamplerTrackingSession::where('is_active', true)->count());
            $this->assertSame(2, $member->events()->count());
        }
    }

    public function testParentReplacementRetainsPartialVisitAttendance(): void
    {
        $parent = $this->schedule();
        $partial = $this->schedule(['parsial' => $parent->id, 'tanggal' => '2026-09-18']);
        $this->prepare('2026-09-17');
        $this->prepare('2026-09-18');
        $partialSession = SamplerTrackingSession::where('tanggal_sampling', '2026-09-18')->firstOrFail();
        $member = $this->attendance($partialSession);
        $before = $this->service->snapshotSchedules('Q1');
        $parent->is_active = false;
        $parent->save();
        $newParent = $this->schedule(['kendaraan' => 'B 2']);
        $partial->parsial = $newParent->id;
        $partial->save();
        $this->service->syncScheduleEdit($before, 'Q1');
        $this->assertSame(2, SamplerTrackingSession::count());
        $this->assertEquals($newParent->id, $partialSession->fresh()->parsial);
        $this->assertSame(2, $member->events()->count());
    }

    public function testNewVisitAtCancelledTimeDoesNotInheritOldAttendance(): void
    {
        $row = $this->schedule();
        $oldSession = $this->prepare('2026-09-17')->first();
        $oldMember = $this->attendance($oldSession);
        $row->is_active = false;
        $row->save();
        $this->service->syncQuotation('Q1');
        $before = $this->service->snapshotSchedules('Q1');
        $this->schedule();
        $this->service->syncScheduleCreation($before, 'Q1');
        $this->assertSame(0, SamplerTrackingSession::where('is_active', true)->count());
        $this->prepare('2026-09-17');
        $newSession = SamplerTrackingSession::where('is_active', true)->firstOrFail();
        $this->assertNotEquals($oldSession->id, $newSession->id);
        $this->assertSame(0, $newSession->events()->count());
        $this->assertFalse((bool) $oldSession->fresh()->is_active);
        $this->assertSame(2, $oldMember->events()->count());
    }

    public function testAddingAPartialVisitDoesNotResetExistingVisitOrMovementGroup(): void
    {
        $parent = $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $this->attendance($session);
        $member->current_movement_group = 'MANUAL-GROUP';
        $member->save();
        $before = $this->service->snapshotSchedules('Q1');
        $this->schedule(['parsial' => $parent->id, 'tanggal' => '2026-09-18']);
        $this->service->syncScheduleCreation($before, 'Q1');
        $this->assertSame(1, SamplerTrackingSession::where('is_active', true)->count());
        $this->prepare('2026-09-18');
        $this->assertSame(2, SamplerTrackingSession::where('is_active', true)->count());
        $this->assertSame(2, $member->events()->count());
        $this->assertSame('MANUAL-GROUP', $member->fresh()->current_movement_group);
        $this->assertTrue((bool) $session->fresh()->is_active);
        $this->assertSame(0, SamplerTrackingSession::where('tanggal_sampling', '2026-09-18')->first()->events()->count());
    }

    public function testPreparationAfterScheduleChangeReconcilesPreviousDateWithoutTouchingOtherQuotation(): void
    {
        $row = $this->schedule();
        $this->schedule(['no_quotation' => 'Q2']);
        $this->prepare('2026-09-17');
        $before = $this->service->snapshotSchedules('Q1');
        $row->tanggal = '2026-09-18';
        $row->save();
        $this->service->syncScheduleEdit($before, 'Q1');
        $header = new PersiapanSampelHeader();
        $header->no_quotation = 'Q1';
        $header->tanggal_sampling = '2026-09-17';
        $header->sampler_jadwal = 'Old sampler name';
        $this->service->syncByPersiapanHeader($header);
        $this->assertSame(2, SamplerTrackingSession::where('is_active', true)->count());
        $this->assertSame('2026-09-18', SamplerTrackingSession::where('no_quotation', 'Q1')->first()->tanggal_sampling);
        $this->assertSame('2026-09-17', SamplerTrackingSession::where('no_quotation', 'Q2')->first()->tanggal_sampling);
    }

    public function testActiveScheduleStillAcceptsDepartureAfterSynchronization(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $session->activeMembers()->firstOrFail();
        $events = $this->service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']);
        $this->assertCount(1, $events);
        $this->assertSame('departure', $member->events()->first()->event_type);
        $this->assertEquals($member->id, $events->first()->triggered_by_member_id);
    }

    public function testScheduleCreationEditAndManualSyncCannotCreateTrackingBeforePreparation(): void
    {
        $before = $this->service->snapshotSchedules('Q1');
        $row = $this->schedule();
        $this->service->syncScheduleCreation($before, 'Q1');
        $this->service->sync('2026-09-17');
        $this->service->syncQuotation('Q1');
        $before = $this->service->snapshotSchedules('Q1');
        $row->jam_mulai = '09:00:00';
        $row->save();
        $this->service->syncScheduleEdit($before, 'Q1');
        $this->assertSame(0, SamplerTrackingSession::count());
        $this->assertSame(0, SamplerTrackingMember::count());
        $this->prepare('2026-09-17');
        $this->assertSame(1, SamplerTrackingSession::count());
        $this->assertSame(1, SamplerTrackingMember::count());
    }

    public function testPreparationOnlyCreatesItsTeamAndDateButIncludesAllTeamMembers(): void
    {
        $this->schedule(['sampler' => 'Asep']);
        $this->schedule(['userid' => 20, 'sampler' => 'Andik']);
        $this->schedule(['userid' => 30, 'sampler' => 'Other', 'jam_mulai' => '13:00:00']);
        $this->schedule(['tanggal' => '2026-09-18', 'sampler' => 'Asep']);
        $header = new PersiapanSampelHeader();
        $header->no_quotation = 'Q1';
        $header->tanggal_sampling = '2026-09-17';
        $header->sampler_jadwal = 'Asep';
        $this->service->syncByPersiapanHeader($header);
        $this->assertSame(1, SamplerTrackingSession::count());
        $this->assertSame(['Andik', 'Asep'], SamplerTrackingMember::orderBy('sampler_name')->pluck('sampler_name')->all());
        $this->service->sync('2026-09-18');
        $this->assertSame(1, SamplerTrackingSession::count());
    }

    public function testRepeatedSyncKeepsAttendanceAndDoesNotDuplicateSessionOrMembers(): void
    {
        $this->schedule();
        $session = $this->prepare('2026-09-17')->first();
        $member = $this->attendance($session);
        $ids = $member->events()->pluck('id')->all();
        $this->service->syncQuotation('Q1');
        $this->service->sync('2026-09-17');
        $this->assertSame(1, SamplerTrackingSession::count());
        $this->assertSame(1, SamplerTrackingMember::count());
        $this->assertSame($ids, $member->events()->pluck('id')->all());
    }

    public function testAsepAndAndikCorrectedToAsepAndEkoInheritsAsepsCheckinOnce(): void
    {
        $this->schedule(['sampler' => 'Asep']);
        $andikSchedule = $this->schedule(['userid' => 20, 'sampler' => 'Andik']);
        $session = $this->prepare('2026-09-17')->first();
        $asep = $this->attendance($session);
        $asep->events()->where('event_type', 'checkout')->delete();
        $source = $asep->events()->firstOrFail();
        $before = $this->service->snapshotSchedules('Q1');
        $andikSchedule->userid = 30;
        $andikSchedule->sampler = 'Eko';
        $andikSchedule->save();
        $this->service->syncScheduleEdit($before, 'Q1');
        $eko = $session->activeMembers()->where('sampler_name', 'Eko')->firstOrFail();
        $event = $eko->events()->firstOrFail();
        $this->assertSame('checkin', $event->event_type);
        $this->assertEquals($source->event_at, $event->event_at);
        $this->assertSame($source->photo, $event->photo);
        $this->assertEquals($asep->id, $event->triggered_by_member_id);
        $this->assertEquals(1, $event->is_auto);
        $this->assertStringContainsString('sumber event #' . $source->id, $event->note);
        $this->assertSame(0, $session->activeMembers()->where('sampler_name', 'Andik')->count());
        $before = $this->service->snapshotSchedules('Q1');
        $andikSchedule->durasi = 2;
        $andikSchedule->save();
        $this->service->syncScheduleEdit($before, 'Q1');
        $this->service->syncQuotation('Q1');
        $this->assertSame(1, $eko->events()->count());
        $this->assertSame(1, $asep->events()->count());
    }

    public function testCorrectedMemberWithLongerDurationDoesNotInheritEarlyCheckout(): void
    {
        $this->schedule(['sampler' => 'Asep']);
        $row = $this->schedule(['userid' => 20, 'sampler' => 'Andik']);
        $session = $this->prepare('2026-09-17')->first();
        $this->attendance($session);
        $before = $this->service->snapshotSchedules('Q1');
        $row->userid = 30;
        $row->sampler = 'Eko';
        $row->durasi_personal = 2;
        $row->save();
        $this->service->syncScheduleEdit($before, 'Q1');
        $eko = $session->activeMembers()->where('sampler_name', 'Eko')->firstOrFail();
        $this->assertSame(['checkin'], $eko->events()->pluck('event_type')->all());
    }
}
