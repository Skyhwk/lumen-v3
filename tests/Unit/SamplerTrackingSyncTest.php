<?php

use App\Models\PersiapanSampelHeader;
use App\Models\SamplerTrackingSession;
use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingEvent;
use App\Services\SamplerTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class SamplerTrackingSyncTest extends TestCase
{
    private static $application;
    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) $this->markTestSkipped('SQLite required; never use application DB.');
        $app = self::$application ?: require __DIR__ . '/../../bootstrap/app.php';
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections', ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        if (!self::$application) $app->boot();
        self::$application = $app;
        Illuminate\Database\Eloquent\Model::setConnectionResolver($app->make('db'));
        DB::purge('sqlite');
        $app['config']->set('database.connections.mysql', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('mysql');
        DB::extend('mysql', function () { return DB::connection('sqlite'); });
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        DB::connection()->getPdo();
        DB::connection()->setDatabaseName('main');
        require_once __DIR__ . '/../../database/migrations/2026_06_29_100000_create_sampler_tracking_tables.php';
        (new CreateSamplerTrackingTables())->up();
        Schema::create('jadwal', function (Blueprint $t) {
            $t->increments('id');
            foreach (['id_sampling','id_cabang','no_quotation','parsial','nama_perusahaan','alamat','tanggal','jam_mulai','jam_selesai','kategori','sampler','userid','driver','durasi','durasi_personal','kendaraan'] as $c) $t->string($c)->nullable();
            $t->boolean('is_active')->default(1);
        });
        Schema::create('sampling_plan', function (Blueprint $t) { $t->increments('id'); $t->string('google_maps_url')->nullable(); });
        Schema::create('order_header', function (Blueprint $t) { $t->increments('id'); $t->string('no_document'); $t->string('no_order'); $t->string('alamat_sampling')->nullable(); $t->boolean('is_active')->default(1); });
    }

    private function schedule($team, $sampler)
    {
        return DB::table('jadwal')->insertGetId(['id_sampling' => $team, 'id_cabang' => 4, 'no_quotation' => 'QT'.$team,
            'nama_perusahaan' => 'PT '.$team, 'tanggal' => '2026-09-16', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
            'sampler' => 'Sampler '.$sampler, 'userid' => $sampler, 'durasi' => 0, 'durasi_personal' => 0, 'kategori' => '[]']);
    }

    public function testStpsSyncUpdatesBothTeamsAndPreservesEventsAndMovementGroup()
    {
        $source = $this->schedule(1, 316); $this->schedule(1, 244);
        $this->schedule(2, 242); $this->schedule(3, 242); $this->schedule(4, 242);
        $s = new SamplerTrackingService(); $s->sync('2026-09-16');
        $this->assertEquals(0, $s->previewSync('2026-09-16')['perlu_diperbarui']);
        $old = SamplerTrackingMember::where('sampler_id',316)->first();
        $old->current_movement_group = 'KEEP'; $old->save();
        SamplerTrackingEvent::create(['sampler_tracking_session_id'=>$old->sampler_tracking_session_id,'sampler_tracking_member_id'=>$old->id,'event_type'=>'departure']);
        DB::table('jadwal')->where('id',$source)->update(['is_active'=>0]);
        foreach ([2,3,4] as $team) $this->schedule($team,316);
        $preview=$s->previewSync('2026-09-16');
        $this->assertEquals(4,$preview['perlu_diperbarui']);
        $this->assertEquals(0,$preview['belum_kebentuk']);
        $s->syncByPersiapanHeader(new PersiapanSampelHeader(['no_quotation'=>'QT2','tanggal_sampling'=>'2026-09-16','sampler_jadwal'=>'Sampler 316']));
        $this->assertEquals(0,$s->previewSync('2026-09-16')['perlu_diperbarui']);
        $this->assertEquals(3,SamplerTrackingMember::where('sampler_id',316)->where('is_active',1)->count());
        $this->assertEquals(3,SamplerTrackingMember::where('sampler_id',242)->where('is_active',1)->count());
        $this->assertFalse((bool)$old->fresh()->is_active);
        $this->assertSame('KEEP',$old->fresh()->current_movement_group);
        $s->sync('2026-09-16');
        $this->assertEquals(4,SamplerTrackingSession::count());
        $this->assertEquals(1,SamplerTrackingEvent::count());
    }

    public function testDurationChangeAndNewTeamKeyAreVisibleAndReconciled()
    {
        $id=$this->schedule(1,316); $s=new SamplerTrackingService(); $s->sync('2026-09-16');
        DB::table('jadwal')->where('id',$id)->update(['durasi_personal'=>2]);
        $this->assertEquals(1,$s->previewSync('2026-09-16')['perlu_diperbarui']);
        $s->sync('2026-09-16');
        $this->assertEquals(2,SamplerTrackingMember::first()->effective_duration);
        $this->assertEquals(0,$s->previewSync('2026-09-16')['perlu_diperbarui']);
        DB::table('jadwal')->where('id',$id)->update(['kendaraan'=>'NEW VEHICLE']);
        $preview=$s->previewSync('2026-09-16');
        $this->assertEquals(1,$preview['belum_kebentuk']);
        $this->assertEquals(1,$preview['akan_dinonaktifkan']);
        $s->sync('2026-09-16');
        $this->assertEquals(1,SamplerTrackingMember::where('is_active',1)->count());
        $this->assertEquals(2,SamplerTrackingSession::count());
    }

    public function testEmptyScheduleDeactivatesSessionsAndMembersWithoutDeletingHistory()
    {
        $id=$this->schedule(1,316); $s=new SamplerTrackingService(); $s->sync('2026-09-16');
        DB::table('jadwal')->where('id',$id)->update(['is_active'=>0]);
        $this->assertEquals(1,$s->previewSync('2026-09-16')['akan_dinonaktifkan']);
        $s->sync('2026-09-16');
        $this->assertEquals(0,SamplerTrackingSession::where('is_active',1)->count());
        $this->assertEquals(0,SamplerTrackingMember::where('is_active',1)->count());
        $this->assertEquals(1,SamplerTrackingSession::count());
    }
}
