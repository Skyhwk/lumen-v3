<?php

use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use App\Services\SamplerTrackingService;
use App\Services\SamplerTrackingTroubleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SamplerTrackingTroubleTest extends TestCase
{
    private static $application;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) $this->markTestSkipped('Requires pdo_sqlite, never use the application database.');
        $app = self::$application ?: require __DIR__ . '/../../bootstrap/app.php';
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections', ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        if (!self::$application) $app->boot();
        self::$application = $app;
        Illuminate\Database\Eloquent\Model::setConnectionResolver($app->make('db'));
        DB::purge('sqlite');
        // Some legacy models explicitly name mysql; bind it to the SAME in-memory SQLite connection.
        $app['config']->set('database.connections.mysql', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('mysql');
        DB::extend('mysql', function () { return DB::connection('sqlite'); });
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        DB::connection()->getPdo();
        DB::connection()->setDatabaseName('main'); // SQLite schema name for legacy qualified table references.
        Carbon::setTestNow(Carbon::parse('2026-09-15 08:00:00', 'Asia/Jakarta'));
        require_once __DIR__ . '/../../database/migrations/2026_06_29_100000_create_sampler_tracking_tables.php';
        require_once __DIR__ . '/../../database/migrations/2026_09_15_100000_create_sampler_tracking_troubles.php';
        (new CreateSamplerTrackingTables())->up();
        (new CreateSamplerTrackingTroubles())->up();
        (new CreateSamplerTrackingTroubles())->up(); // existing table guard
        Schema::create('order_header', function (Blueprint $table) {
            $table->increments('id'); $table->string('no_order'); $table->string('no_document')->nullable(); $table->string('id_pelanggan')->nullable(); $table->boolean('is_active')->default(1);
        });
        Schema::create('master_karyawan', function (Blueprint $table) {
            $table->increments('id'); $table->text('atasan_langsung')->nullable(); $table->boolean('is_active')->default(1);
        });
        Schema::create('persiapan_sampel_header', function (Blueprint $table) {
            $table->increments('id'); $table->string('no_order'); $table->string('no_quotation')->nullable();
            $table->date('tanggal_sampling'); $table->text('detail_bas_documents')->nullable(); $table->boolean('is_active')->default(1);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect();
    }

    private function stop($id, $date = '2026-09-14', $customer = 'C1', $team = [1, 2], $duration = 1)
    {
        DB::table('order_header')->insert(['no_order' => 'ORDER' . $id, 'id_pelanggan' => $customer]);
        $session = SamplerTrackingSession::create(['id' => $id, 'team_key' => 'key' . $id, 'tanggal_sampling' => $date,
            'no_order' => 'ORDER' . $id, 'jam_mulai' => '08:00', 'is_active' => 1]);
        foreach ($team as $sampler) {
            SamplerTrackingMember::create(['sampler_tracking_session_id' => $id, 'sampler_id' => $sampler,
                'sampler_name' => 'Sampler ' . $sampler, 'effective_duration' => $duration, 'is_active' => 1]);
        }
        return $session;
    }

    private function events($session, array $types, $sampler = 1)
    {
        $member = $session->activeMembers()->where('sampler_id', $sampler)->first();
        foreach ($types as $type) SamplerTrackingEvent::create(['sampler_tracking_session_id' => $session->id,
            'sampler_tracking_member_id' => $member->id, 'event_type' => $type, 'event_at' => Carbon::now(), 'sequence_no' => 1]);
        return $member;
    }

    private function assertLocked($callback, $status = 423)
    {
        try { $callback(); $this->fail('Expected blocked request'); }
        catch (HttpException $e) { $this->assertSame($status, $e->getStatusCode()); }
    }

    public function testSameCustomerAndTeamBecomeOneStopWithoutLosingHistory()
    {
        $a = $this->stop(1); $this->stop(2, '2026-09-14', 'C1', [2, 1], 2);
        $this->events($a, ['departure', 'checkin', 'checkout']);
        $items = (new SamplerTrackingService())->listByDate('2026-09-14', 1);
        $this->assertCount(1, $items);
        $this->assertSame(['ORDER1', 'ORDER2'], $items[0]->activity_orders);
        $this->assertCount(3, $items[0]->activeMembers[0]->events);
        $this->assertEquals(2, $items[0]->activeMembers[0]->effective_duration);
        $this->assertSame(2, SamplerTrackingSession::count());
        $this->assertSame(3, SamplerTrackingEvent::count());
    }

    public function testDifferentCustomerTeamDateAndMissingCustomerStaySeparate()
    {
        $this->stop(1); $this->stop(2, '2026-09-14', 'C2'); $this->stop(3, '2026-09-14', 'C1', [1, 3]);
        $this->stop(4, '2026-09-14', null); $this->stop(5, '2026-09-14', null);
        $this->stop(6, '2026-09-15');
        $this->assertCount(5, (new SamplerTrackingService())->listByDate('2026-09-14', 1));
    }

    public function testCollectorIsIdempotentAndIgnoresCompleteAndNotYetDueWork()
    {
        $a = $this->stop(1, '2026-09-14', 'C1', [1]);
        $b = $this->stop(2, '2026-09-14', 'C2', [2]);
        $this->events($b, ['departure', 'checkin', 'checkout', 'return'], 2);
        $this->stop(3, '2026-09-14', 'C3', [3], 2);
        $service = new SamplerTrackingTroubleService();
        $this->assertSame(1, $service->collect());
        $this->assertSame(0, $service->collect());
        $this->assertSame(1, DB::table($service::TABLE)->count());
        $this->assertLocked(function () use ($service) { $service->assertAllowed(1, '2026-09-15'); });
        $this->assertLocked(function () use ($service) { $service->assertAllowed(1, '2026-09-14'); });
        $service->assertAllowed(3, '2026-09-14');
    }

    public function testOnlySuperiorCanReopenAndClearRequiresAllEvents()
    {
        $a = $this->stop(1, '2026-09-14', 'C1', [1]);
        DB::table('master_karyawan')->insert(['id' => 1, 'atasan_langsung' => '[9]']);
        $service = new SamplerTrackingTroubleService(); $service->collect();
        $id = DB::table($service::TABLE)->value('id');
        $this->assertLocked(function () use ($service, $id) { $service->reopen($id, 1, 'self'); }, 403);
        $this->assertLocked(function () use ($service, $id) { $service->reopen($id, 8, 'other'); }, 403);
        $service->reopen($id, 9, 'Sudah melapor');
        $service->assertAllowed(1, '2026-09-14');
        $this->assertLocked(function () use ($service) { $service->assertAllowed(1, '2026-09-15'); });
        $this->events($a, ['departure', 'checkin', 'checkout']);
        $this->assertCount(1, $service->unresolved(1));
        $this->events($a, ['return']);
        $service->assertAllowed(1, '2026-09-15');
        $this->assertEquals(1, DB::table($service::TABLE)->value('is_clear'));
        DB::enableQueryLog(); $service->unresolved(1);
        $this->assertCount(0, array_filter(DB::getQueryLog(), function ($q) { return strpos($q['query'], 'sampler_tracking_sessions') !== false; }));
        DB::disableQueryLog();
    }

    public function testAnotherUnresolvedDayStillBlocks()
    {
        $a = $this->stop(1, '2026-09-13', 'C1', [1]); $this->stop(2, '2026-09-14', 'C2', [1]);
        $service = new SamplerTrackingTroubleService(); $service->collect('2026-09-13'); $service->collect('2026-09-14');
        $this->events($a, ['departure', 'checkin', 'checkout', 'return']);
        $this->assertLocked(function () use ($service) { $service->assertAllowed(1, '2026-09-15'); });
        $this->assertCount(1, $service->unresolved(1));
    }

    public function testSharedStopWritesToEachOrderAndRetriesDoNotDuplicateEvents()
    {
        $a = $this->stop(1, '2026-09-15', 'C1', [1]); $this->stop(2, '2026-09-15', 'C1', [1]);
        $member = $a->activeMembers()->first(); $service = new SamplerTrackingService();
        $this->assertCount(2, $service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']));
        $this->assertCount(0, $service->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']));
        $this->assertCount(2, $service->storeEvent(['member_id' => $member->id, 'event_type' => 'checkin']));
        $this->assertSame(4, SamplerTrackingEvent::count());
    }

    public function testBlockedTeammateDoesNotInheritTodayEvents()
    {
        $this->stop(1, '2026-09-14', 'C1', [2]);
        (new SamplerTrackingTroubleService())->collect();
        $today = $this->stop(2, '2026-09-15');
        $member = $today->activeMembers()->where('sampler_id', 1)->first();
        $events = (new SamplerTrackingService())->storeEvent(['member_id' => $member->id, 'event_type' => 'departure']);
        $this->assertCount(1, $events);
        $this->assertEquals($member->id, $events[0]->sampler_tracking_member_id);
    }

    public function testRecoveryThroughStoreEventAutomaticallyUnlocksOnlyAfterReturn()
    {
        $old = $this->stop(1, '2026-09-14', 'C1', [1]);
        DB::table('master_karyawan')->insert(['id' => 1, 'atasan_langsung' => '[9]']);
        $trouble = new SamplerTrackingTroubleService(); $trouble->collect();
        $trouble->reopen(DB::table($trouble::TABLE)->value('id'), 9, 'Laporan diterima');
        $member = $old->activeMembers()->first(); $service = new SamplerTrackingService();
        foreach (['departure', 'checkin', 'checkout'] as $type) $service->storeEvent(['member_id' => $member->id, 'event_type' => $type]);
        $this->assertLocked(function () use ($trouble) { $trouble->assertAllowed(1, '2026-09-15'); });
        $service->storeEvent(['member_id' => $member->id, 'event_type' => 'return']);
        $trouble->assertAllowed(1, '2026-09-15');
        $this->assertEquals(1, DB::table($trouble::TABLE)->value('is_clear'));
    }

    public function testMultidayDeadlineAndZeroDuration()
    {
        $this->stop(1, '2026-09-13', 'C1', [1], 2);
        $this->stop(2, '2026-09-14', 'C2', [2], 0);
        $this->assertSame(2, (new SamplerTrackingTroubleService())->collect());
    }

    public function testScopedCollectorSkipsOtherSamplersAndOngoingTwoDayWork()
    {
        $this->stop(1, '2026-09-12', 'C1', [601], 3);
        $this->stop(2, '2026-09-14', 'C2', [508], 3);
        $this->stop(3, '2026-09-12', 'C3', [999], 3);
        $service = new SamplerTrackingTroubleService();
        $this->assertSame(1, $service->collect(null, [601, 508]));
        $this->assertEquals(['601'], DB::table($service::TABLE)->pluck('sampler_id')->all());
        $this->assertSame(0, $service->collect(null, [601, 508]));
    }

    public function testMobileOnlyOffersAuthorizedRecoveryAndCannotImpersonateSampler()
    {
        $this->stop(1, '2026-09-14', 'C1', [1]); $today = $this->stop(2, '2026-09-15', 'C2', [1, 2]);
        DB::table('master_karyawan')->insert(['id' => 1, 'atasan_langsung' => '[9]']);
        $service = new SamplerTrackingTroubleService(); $service->collect();
        $request = new Illuminate\Http\Request(['tanggal' => '2026-09-15']);
        $request->attributes->set('user', (object) ['karyawan' => (object) ['id' => 1, 'nama_lengkap' => 'Sampler 1', 'id_cabang' => 1,
            'id_department' => 1, 'grade' => 'Staff', 'privilage_cabang' => '[]']]);
        $controller = new App\Http\Controllers\mobile\SamplerTrackingController($request, new SamplerTrackingService());
        $response = $controller->index($request)->getData(true);
        $this->assertTrue($response['blocked']);
        $this->assertCount(1, $response['troubles']);
        $request->merge(['tanggal' => '2026-09-14']);
        $this->assertSame('2026-09-15', $controller->index($request)->getData(true)['activity_date']);
        $service->reopen($response['troubles'][0]['id'], 9, 'Buka');
        $response = $controller->index($request)->getData(true);
        $this->assertFalse($response['blocked']);
        $this->assertSame('2026-09-14', $response['activity_date']);
        $request->merge(['member_id' => $today->activeMembers()->where('sampler_id', 2)->value('id'), 'event_type' => 'departure']);
        $this->assertLocked(function () use ($controller, $request) { $controller->storeEvent($request); }, 403);
    }

    public function testRouteUsesCompletedMergedStopInsteadOfRequiringDuplicateCheckout()
    {
        $a = $this->stop(1, '2026-09-15', 'C1', [1]); $this->stop(2, '2026-09-15', 'C1', [1]);
        $b = $this->stop(3, '2026-09-15', 'C2', [1]);
        $this->events($a, ['departure', 'checkin', 'checkout']);
        $member = $b->activeMembers()->first();
        $events = (new SamplerTrackingService())->storeEvent(['member_id' => $member->id, 'event_type' => 'checkin']);
        $this->assertCount(1, $events);
    }

    private function mobileRequest()
    {
        $request = new Illuminate\Http\Request(['tanggal' => '1999-01-01']);
        $request->attributes->set('user', (object) ['karyawan' => (object) ['id' => 1, 'nama_lengkap' => 'Sampler 1',
            'id_cabang' => 1, 'id_department' => 1, 'grade' => 'Staff', 'privilage_cabang' => '[]']]);
        return $request;
    }

    public function testServerDateUsesWibEvenWhenDefaultTimezoneAndClientDateAreBehind()
    {
        $timezone = date_default_timezone_get();
        try {
            date_default_timezone_set('UTC');
            $request = $this->mobileRequest();
            $controller = new App\Http\Controllers\mobile\SamplerTrackingController($request, new SamplerTrackingService());
            foreach (['2026-09-14 17:00:00', '2026-09-14 23:00:00', '2026-09-15 00:00:00'] as $utc) {
                Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
                $response = $controller->index($request)->getData(true);
                $this->assertSame('2026-09-15', $response['server_today']);
                $this->assertSame('2026-09-15', $response['activity_date']);
                $this->assertFalse($response['is_recovery']);
            }
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function testRefreshSelectsOldestApprovedDayThenNextThenToday()
    {
        $a = $this->stop(1, '2026-09-13', 'C1', [1]);
        $b = $this->stop(2, '2026-09-14', 'C2', [1]);
        DB::table('master_karyawan')->insert(['id' => 1, 'atasan_langsung' => '[9]']);
        $service = new SamplerTrackingTroubleService();
        $service->collect('2026-09-13'); $service->collect('2026-09-14');
        foreach (DB::table($service::TABLE)->pluck('id') as $id) $service->reopen($id, 9, 'Buka');
        $request = $this->mobileRequest();
        $controller = new App\Http\Controllers\mobile\SamplerTrackingController($request, new SamplerTrackingService());
        $this->assertSame('2026-09-13', $controller->index($request)->getData(true)['activity_date']);
        $this->events($a, ['departure', 'checkin', 'checkout', 'return']);
        $this->assertSame('2026-09-14', $controller->index($request)->getData(true)['activity_date']);
        $this->events($b, ['departure', 'checkin', 'checkout', 'return']);
        $response = $controller->index($request)->getData(true);
        $this->assertSame('2026-09-15', $response['activity_date']);
        $this->assertFalse($response['blocked']);
        $this->assertFalse($response['is_recovery']);
        $this->assertEquals(2, DB::table($service::TABLE)->where('is_clear', 1)->count());
    }
}
