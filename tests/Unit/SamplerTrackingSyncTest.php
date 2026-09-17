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
        $connection->table('sampling_plan')->insert(['id' => 1, 'no_quotation' => 'Q1']);
        $this->service = new SamplerTrackingService();
    }

    protected function tearDown(): void
    {
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
        $row->is_active = false;
        $row->save();
        $this->assertSame(1, $this->service->previewSync('2026-09-17')['akan_dinonaktifkan']);
        $this->service->sync('2026-09-17');
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
        $this->attendance($session);
        $header = new PersiapanSampelHeader();
        $header->no_quotation = 'Q1';
        $header->tanggal_sampling = '2026-09-17';
        $header->sampler_jadwal = 'A';
        $this->service->syncByPersiapanHeader($header);
        $this->assertSame(2, $session->activeMembers()->count());
        $this->assertSame(2, $session->events()->count());
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
