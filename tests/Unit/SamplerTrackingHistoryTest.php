<?php

namespace Tests\Unit;

use App\Http\Controllers\api\SamplerTrackingController;
use App\Models\SamplerTrackingSession;
use App\Services\SamplerTrackingHistory;
use App\Services\SamplerTrackingHistoryBackfill;
use App\Services\SamplerTrackingJourneyBackfill;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class SamplerTrackingHistoryTest extends TestCase
{
    private $db;
    private $previousContainer;
    private $previousFacade;
    private $previousResolver;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requires pdo_sqlite; uses only an in-memory database.');
        }
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = SamplerTrackingSession::getConnectionResolver();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository(['database.default' => 'mysql']));
        $this->db = new Manager($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'mysql');
        $this->db->getDatabaseManager()->setDefaultConnection('mysql');
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        $container->bind('db.schema', function () { return $this->db->getConnection()->getSchemaBuilder(); });
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        if (!class_exists('DB')) {
            class_alias(\Illuminate\Support\Facades\DB::class, 'DB');
        }
        $this->db->getConnection()->getSchemaBuilder()->create('sampler_tracking_sessions', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->text('meta_history')->nullable();
        });
        $this->db->getConnection()->table('sampler_tracking_sessions')->insert([['id' => 5], ['id' => 6]]);
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->getDatabaseManager()->purge('mysql');
            if ($this->previousResolver) {
                SamplerTrackingSession::setConnectionResolver($this->previousResolver);
            } else {
                SamplerTrackingSession::unsetConnectionResolver();
            }
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacade);
            Container::setInstance($this->previousContainer);
        }
        parent::tearDown();
    }

    private function history($id): array
    {
        return json_decode($this->db->getConnection()->table('sampler_tracking_sessions')->where('id', $id)->value('meta_history'), true) ?: [];
    }

    public function testAppendPreservesPreviousHistoryAndZeroDuration(): void
    {
        SamplerTrackingHistory::append(5, 'schedule_changed', ['after' => ['effective_duration' => 0]]);
        SamplerTrackingHistory::append(5, 'activity_recorded', ['triggered_by' => ['name' => 'Irfan'], 'affected' => [['name' => 'Farhan']]]);
        $history = $this->history(5);
        $this->assertCount(2, $history);
        $this->assertSame(0, $history[0]['after']['effective_duration']);
        $this->assertSame('Irfan', $history[1]['triggered_by']['name']);
        $this->assertSame('Farhan', $history[1]['affected'][0]['name']);
    }

    public function testHistoryRollsBackWithTheOuterMutation(): void
    {
        $db = $this->db->getConnection();
        $db->beginTransaction();
        SamplerTrackingHistory::append(5, 'activity_recorded', []);
        $db->rollBack();
        $this->assertSame([], $this->history(5));
    }

    public function testReplacementLinksBothSessionsAndHistoryIsHidden(): void
    {
        SamplerTrackingHistory::link(5, 6, 'schedule_replacement');
        $this->assertSame(6, $this->history(5)[0]['to_session_id']);
        $this->assertSame(5, $this->history(6)[0]['from_session_id']);
        $this->assertArrayNotHasKey('meta_history', SamplerTrackingSession::find(5)->toArray());
    }

    public function testMalformedHistoryIsNotOverwritten(): void
    {
        $this->db->getConnection()->table('sampler_tracking_sessions')->where('id', 5)->update(['meta_history' => 'broken']);
        try {
            SamplerTrackingHistory::append(5, 'activity_recorded', []);
            $this->fail('Invalid existing history must be preserved.');
        } catch (\JsonException $exception) {
            $this->assertSame('broken', $this->db->getConnection()->table('sampler_tracking_sessions')->where('id', 5)->value('meta_history'));
        }
    }

    public function testAccessUsesTicketProgrammingDepartmentAndExclusions(): void
    {
        $db = $this->db->getConnection();
        $db->getSchemaBuilder()->create('master_karyawan', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('id_department');
        });
        $db->table('master_karyawan')->insert([
            ['id' => 601, 'id_department' => 7], ['id' => 10, 'id_department' => 7], ['id' => 595, 'id_department' => 8],
        ]);
        $reflection = new \ReflectionClass(SamplerTrackingController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $user = $reflection->getProperty('user_id');
        $user->setAccessible(true);
        $access = $reflection->getMethod('canViewActivityHistory');
        $access->setAccessible(true);
        foreach ([601 => true, 10 => false, 595 => false, 0 => false] as $id => $expected) {
            $user->setValue($controller, $id);
            $this->assertSame($expected, $access->invoke($controller));
        }
    }

    private function createBackfillTables(): void
    {
        $db = $this->db->getConnection();
        $schema = $db->getSchemaBuilder();
        $schema->table('sampler_tracking_sessions', function (Blueprint $table) {
            $table->string('tanggal_sampling')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('sampler_tracking_members', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('sampler_tracking_session_id');
            $table->integer('sampler_id');
            $table->string('sampler_name');
            $table->integer('duration')->nullable();
            $table->string('durasi_personal')->nullable();
            $table->string('effective_duration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('sampler_tracking_events', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('sampler_tracking_session_id');
            $table->integer('sampler_tracking_member_id');
            $table->integer('triggered_by_member_id')->nullable();
            $table->string('event_type');
            $table->string('event_at');
            $table->string('movement_group')->nullable();
            $table->boolean('is_auto')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function testBackfillHonorsRangePreservesNativeHistoryAndIsRepeatable(): void
    {
        $this->createBackfillTables();
        $db = $this->db->getConnection();
        $db->table('sampler_tracking_sessions')->where('id', 5)->update([
            'tanggal_sampling' => '2026-10-01', 'is_active' => false, 'updated_at' => '2026-10-02 12:00:00',
        ]);
        $db->table('sampler_tracking_sessions')->where('id', 6)->update(['tanggal_sampling' => '2026-10-31']);
        $db->table('sampler_tracking_sessions')->insert(['id' => 7, 'tanggal_sampling' => '2026-11-01']);
        $db->table('sampler_tracking_members')->insert([
            ['id' => 20, 'sampler_tracking_session_id' => 5, 'sampler_id' => 251, 'sampler_name' => 'Irfan', 'effective_duration' => '0'],
            ['id' => 21, 'sampler_tracking_session_id' => 5, 'sampler_id' => 595, 'sampler_name' => 'Farhan', 'effective_duration' => '2'],
        ]);
        $db->table('sampler_tracking_events')->insert([
            ['id' => 1, 'sampler_tracking_session_id' => 5, 'sampler_tracking_member_id' => 20,
                'triggered_by_member_id' => 20, 'event_type' => 'checkout', 'event_at' => '2026-10-01 18:00:00', 'is_auto' => false],
            ['id' => 2, 'sampler_tracking_session_id' => 5, 'sampler_tracking_member_id' => 21,
                'triggered_by_member_id' => 20, 'event_type' => 'checkout', 'event_at' => '2026-10-01 18:00:00', 'is_auto' => true],
        ]);
        SamplerTrackingHistory::append(5, 'activity_recorded', ['affected' => [['event_id' => 1]]]);
        $original = $this->history(5)[0];
        $service = new SamplerTrackingHistoryBackfill();
        $this->assertSame(['sessions' => 2, 'entries' => 3], $service->run('2026-10-01', '2026-10-31', true));
        $this->assertCount(1, $this->history(5));
        $this->assertSame(['sessions' => 2, 'entries' => 3], $service->run('2026-10-01', '2026-10-31'));
        $history = $this->history(5);
        $this->assertSame($original, $history[0]);
        $this->assertTrue($history[1]['backfilled']);
        $this->assertSame('activity_imported', $history[2]['type']);
        $this->assertSame('Irfan', $history[2]['triggered_by']['name']);
        $this->assertSame('Farhan', $history[2]['affected'][0]['name']);
        $this->assertTrue($history[2]['affected'][0]['automatic']);
        $this->assertArrayNotHasKey('effective_duration', $history[2]['affected'][0]);
        $this->assertSame(['sessions' => 2, 'entries' => 0], $service->run('2026-10-01', '2026-10-31'));
        $this->assertSame([], $this->history(7));
        $this->assertSame(2, $db->table('sampler_tracking_events')->count());
        $this->assertSame('2026-10-02 12:00:00', $db->table('sampler_tracking_sessions')->where('id', 5)->value('updated_at'));
    }

    public function testDetailedJourneyBackfillReconstructsDurationAndOnlyWritesTarget(): void
    {
        $this->createBackfillTables();
        $db = $this->db->getConnection();
        $db->setDatabaseName('main');
        $schema = $db->getSchemaBuilder();
        $schema->table('sampler_tracking_sessions', function (Blueprint $table) {
            $table->integer('id_sampling')->nullable();
            foreach (['no_quotation', 'nama_perusahaan', 'jam_mulai', 'jam_selesai', 'kategori'] as $field) {
                $table->string($field)->nullable();
            }
        });
        $schema->create('jadwal', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('id_sampling');
            $table->integer('userid');
            foreach (['no_quotation', 'nama_perusahaan', 'tanggal', 'jam_mulai', 'jam_selesai', 'sampler',
                'durasi', 'durasi_personal', 'kategori', 'updated_by', 'created_by'] as $field) {
                $table->string($field)->nullable();
            }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $db->table('sampler_tracking_sessions')->whereIn('id', [5, 6])->update([
            'id_sampling' => 100, 'no_quotation' => 'Q1R2', 'tanggal_sampling' => '2026-10-28',
            'nama_perusahaan' => 'SANKOSHA', 'jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00', 'kategori' => '["Air"]',
        ]);
        $db->table('sampler_tracking_members')->insert([
            ['id' => 1, 'sampler_tracking_session_id' => 5, 'sampler_id' => 251, 'sampler_name' => 'Irfan', 'effective_duration' => '0'],
            ['id' => 2, 'sampler_tracking_session_id' => 5, 'sampler_id' => 595, 'sampler_name' => 'Farhan', 'effective_duration' => '2'],
        ]);
        foreach ([[10,251,'Irfan','2',0],[11,595,'Farhan','2',0],[20,251,'Irfan','0',1],[21,595,'Farhan','2',1]] as [$id,$uid,$name,$duration,$active]) {
            $db->table('jadwal')->insert([
                'id' => $id, 'id_sampling' => 100, 'no_quotation' => 'Q1R2', 'tanggal' => '2026-10-28',
                'nama_perusahaan' => 'SANKOSHA', 'jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00',
                'kategori' => '["Air"]', 'userid' => $uid, 'sampler' => $name, 'durasi' => '2',
                'durasi_personal' => $duration, 'is_active' => $active,
                'updated_at' => '2026-10-28 18:52:55', 'updated_by' => 'Scheduler',
            ]);
        }
        // Same company, different sampling plan: never treated as predecessor evidence.
        $db->table('jadwal')->insert(['id' => 9, 'id_sampling' => 999, 'userid' => 251, 'nama_perusahaan' => 'SANKOSHA', 'tanggal' => '2026-10-28']);
        SamplerTrackingHistory::append(5, 'existing_fact', ['reason' => 'preserve']);
        $original = $this->history(5)[0];
        $service = new SamplerTrackingJourneyBackfill();
        $preview = $service->run(5, true);
        $this->assertCount(1, $preview['changes']);
        $this->assertSame(4, $preview['source_rows']);
        $this->assertSame('2', $preview['changes'][0]['before']['members'][0]['effective_duration']);
        $this->assertSame('0', $preview['changes'][0]['after']['members'][0]['effective_duration']);
        $this->assertSame('inferred', $preview['changes'][0]['confidence']);
        $this->assertCount(1, $this->history(5));
        $this->assertSame([], $this->history(6));
        $written = $service->run(5);
        $this->assertGreaterThan(0, $written['entries_added']);
        $this->assertSame($original, $this->history(5)[0]);
        $this->assertSame([], $this->history(6));
        $this->assertSame(0, $service->run(5)['entries_added']);
        $this->assertSame(5, $db->table('jadwal')->count());
        $this->assertSame('0', $db->table('sampler_tracking_members')->where('id', 1)->value('effective_duration'));
        // Concurrent active teams are observations, not an inferred replacement.
        $db->table('jadwal')->whereIn('id', [10, 11])->update(['is_active' => 1]);
        $this->assertCount(0, $service->run(5, true)['changes']);
    }
}
