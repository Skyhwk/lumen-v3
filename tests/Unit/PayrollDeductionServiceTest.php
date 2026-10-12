<?php

namespace Tests\Unit;

use App\Models\{Payroll, PayrollHeader, MasterKaryawan, Kasbon, DendaKaryawan, PencadanganUpah};
use App\Services\{PayrollDeductionService, PayrollDebtService, PencadanganUpahPayrollService};
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class PayrollDeductionServiceTest extends TestCase
{
    private $db;
    private $containerBefore;
    private $facadeBefore;
    private $resolverBefore;
    private $service;

    protected function setUp(): void
    {
        $this->assertContains('sqlite', \PDO::getAvailableDrivers(), 'Run PHP with -d extension=pdo_sqlite');
        $this->containerBefore = Container::getInstance();
        $this->facadeBefore = Facade::getFacadeApplication();
        $this->resolverBefore = Payroll::getConnectionResolver();
        $container = new Container();
        Container::setInstance($container);
        $this->db = new Manager($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'mysql');
        $container['config']['database.default'] = 'mysql';
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        $container->bind('db.schema', function () { return $this->db->getConnection()->getSchemaBuilder(); });
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('master_karyawan', function (Blueprint $t) {
            $t->increments('id'); $t->string('status_karyawan'); $t->timestamps();
        });
        $schema->create('payroll_header', function (Blueprint $t) {
            $t->increments('id'); $t->string('periode_payroll');
            $t->boolean('is_active')->default(true); $t->boolean('is_approve')->default(false);
        });
        $schema->create('payroll', function (Blueprint $t) {
            $t->increments('id'); $t->integer('id_karyawan'); $t->integer('payroll_header_id');
            $t->string('periode_payroll'); $t->boolean('is_active')->default(true);
            foreach (['gaji_pokok', 'tunjangan', 'bonus', 'incentive', 'pencadangan_upah',
                'jamsostek', 'bpjs_kesehatan', 'loan', 'sanksi', 'potongan_absen', 'potongan_lainnya',
                'pajak_pph', 'take_home_pay'] as $field) {
                $t->decimal($field, 15, 2)->default(0);
            }
            $t->string('created_by')->nullable(); $t->string('deleted_by')->nullable();
            $t->timestamp('created_at')->nullable(); $t->timestamp('deleted_at')->nullable();
        });
        foreach (['kasbon' => 'sisa_kasbon', 'denda_karyawan' => 'sisa_denda'] as $table => $balance) {
            $schema->create($table, function (Blueprint $t) use ($balance) {
                $t->increments('id'); $t->integer('id_karyawan');
                $t->decimal($balance, 15, 2); $t->decimal('nominal_potongan', 15, 2);
                $t->integer('sisa_tenor'); $t->string('status')->nullable()->default('ONGOING');
                $t->string('bulan_mulai_pemotongan'); $t->boolean('is_active')->default(true);
                $t->string('updated_by')->nullable(); $t->timestamp('updated_at')->nullable();
            });
        }
        $schema->create('pencadangan_upah', function (Blueprint $t) {
            $t->increments('id'); $t->integer('id_karyawan'); $t->integer('tenor');
            $t->integer('tenor_berjalan'); $t->decimal('nominal', 15, 2); $t->decimal('nominal_berjalan', 15, 2);
            $t->string('status')->nullable()->default('ONGOING'); $t->string('bulan_efektif');
            $t->boolean('is_active')->default(true); $t->string('updated_by')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        foreach (['2026_10_08_120000_add_tipe_pengembalian_to_pencadangan_upah_table.php',
            '2026_10_08_130000_add_deduction_snapshot_to_payroll.php'] as $file) {
            $migration = require __DIR__ . '/../../database/migrations/' . $file;
            $migration->up();
            $migration->up(); // Existing columns are safe to encounter.
        }
        MasterKaryawan::forceCreate(['id' => 1, 'status_karyawan' => 'Contract']);
        $this->service = new PayrollDeductionService();
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->getConnection()->disconnect();
            if ($this->resolverBefore) Payroll::setConnectionResolver($this->resolverBefore);
            else Payroll::unsetConnectionResolver();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->facadeBefore);
            Container::setInstance($this->containerBefore);
        }
        parent::tearDown();
    }

    private function payroll(array $amounts = [], string $period = '2026-01'): Payroll
    {
        $header = PayrollHeader::firstOrCreate(['periode_payroll' => $period]);
        return (new Payroll())->forceFill(array_merge(['id_karyawan' => 1, 'payroll_header_id' => $header->id,
            'periode_payroll' => $period, 'gaji_pokok' => 5000000, 'loan' => 0, 'sanksi' => 0,
            'pencadangan_upah' => 0], $amounts));
    }

    private function debt(string $class = Kasbon::class, array $override = [])
    {
        $balance = $class === Kasbon::class ? 'sisa_kasbon' : 'sisa_denda';
        return $class::forceCreate(array_merge(['id_karyawan' => 1, $balance => 250000,
            'nominal_potongan' => 100000, 'sisa_tenor' => 3, 'status' => 'ONGOING',
            'is_active' => true, 'bulan_mulai_pemotongan' => '2025-01'], $override));
    }

    private function deposit(string $type, int $tenor = 3, array $override = []): PencadanganUpah
    {
        return PencadanganUpah::forceCreate(array_merge(['id_karyawan' => 1, 'tenor' => $tenor,
            'tenor_berjalan' => -$tenor, 'nominal' => 100000, 'nominal_berjalan' => -100000,
            'status' => 'ONGOING', 'is_active' => true, 'bulan_efektif' => '2025-01',
            'tipe_pengembalian' => $type], $override));
    }

    private function rejected(callable $action, string $message): void
    {
        try { $action(); $this->fail('Expected rejection'); }
        catch (\DomainException $e) { $this->assertStringContainsString($message, $e->getMessage()); }
    }

    public static function phases(): array
    {
        return [['setelah_potongan', 1], ['setelah_potongan', 3], ['setelah_potongan', 6],
            ['setelah_kontrak', 1], ['setelah_kontrak', 3], ['setelah_kontrak', 6]];
    }

    /** @dataProvider phases */
    public function testFullCycleAndCancelResubmitAtEveryPhase(string $type, int $tenor): void
    {
        $deposit = $this->deposit($type, $tenor);
        $net = 0;
        for ($i = 0; $i < $tenor * 2; $i++) {
            $before = $deposit->fresh();
            $amount = PencadanganUpahPayrollService::previewAmount($before, 'Contract');
            $this->assertEquals($i < $tenor ? -100000 : 100000, $amount);
            $period = date('Y-m', strtotime('2025-01-01 +' . $i . ' months'));
            $row = $this->service->submit($this->payroll(['pencadangan_upah' => $amount], $period), 'test');
            $this->service->cancel($row->id, 'test');
            $this->assertEquals($before->tenor_berjalan, $deposit->fresh()->tenor_berjalan);
            $this->assertSame($before->status, $deposit->fresh()->status);
            $this->assertEquals($before->nominal_berjalan, $deposit->fresh()->nominal_berjalan);
            $this->service->submit($this->payroll(['pencadangan_upah' => $amount], $period), 'test');
            $net += $amount;
        }
        $this->assertSame(0.0, $net);
        $this->assertSame('END', $deposit->fresh()->status);
        $this->assertSame(0.0, PencadanganUpahPayrollService::previewAmount($deposit->fresh(), 'Contract'));
    }

    public function testWaitingUsesActualEmployeeStatusAndContinuesAfterFirstRefund(): void
    {
        $deposit = $this->deposit('setelah_kontrak', 3, ['tenor_berjalan' => 0, 'nominal_berjalan' => 100000]);
        MasterKaryawan::where('id', 1)->update(['status_karyawan' => 'Probation']);
        $this->rejected(function () {
            $this->service->submit($this->payroll(['pencadangan_upah' => 100000]), 'test');
        }, 'Nominal/fase');
        $this->service->submit($this->payroll(), 'test');
        $this->assertSame(0, $deposit->fresh()->tenor_berjalan);
        MasterKaryawan::where('id', 1)->update(['status_karyawan' => 'Contract']);
        $this->service->submit($this->payroll(['pencadangan_upah' => 100000], '2026-02'), 'test');
        MasterKaryawan::where('id', 1)->update(['status_karyawan' => 'Permanent']);
        $this->service->submit($this->payroll(['pencadangan_upah' => 100000], '2026-03'), 'test');
        $this->assertSame(3, $deposit->fresh()->tenor_berjalan);
    }

    public static function debtTypes(): array { return [[Kasbon::class, 'loan', 'sisa_kasbon'], [DendaKaryawan::class, 'sanksi', 'sisa_denda']]; }

    /** @dataProvider debtTypes */
    public function testActualPartialDeductionAndIdempotentCancellation(string $class, string $field, string $balance): void
    {
        $debt = $this->debt($class);
        $row = $this->service->submit($this->payroll([$field => 75000, 'take_home_pay' => 1]), 'test');
        $this->assertEquals(175000, $debt->fresh()->$balance);
        $this->assertSame(2, $debt->fresh()->sisa_tenor);
        $this->assertEquals(1, $row->fresh()->take_home_pay, 'Submit must preserve the supplied THP, not recompute it.');
        $this->service->cancel($row->id, 'test');
        $this->service->cancel($row->id, 'test');
        $this->assertEquals(250000, $debt->fresh()->$balance);
        $this->assertSame(3, $debt->fresh()->sisa_tenor);
    }

    /** @dataProvider debtTypes */
    public function testLastInstallmentAndRejectOverdraw(string $class, string $field, string $balance): void
    {
        $debt = $this->debt($class, [$balance => 50000, 'sisa_tenor' => 1]);
        $this->rejected(function () use ($field) {
            $this->service->submit($this->payroll([$field => 100000]), 'test');
        }, 'melebihi');
        $row = $this->service->submit($this->payroll([$field => 50000]), 'test');
        $this->assertEquals(0, $debt->fresh()->$balance);
        $this->assertSame('END', $debt->fresh()->status);
        $this->assertSame(0, $debt->fresh()->sisa_tenor);
        $this->service->cancel($row->id, 'test');
        $this->assertEquals(50000, $debt->fresh()->$balance);
        $this->assertSame('ONGOING', $debt->fresh()->status);
    }

    public function testZeroDoesNotAdvanceAnySourceAndDuplicateSubmitCannotDeductTwice(): void
    {
        $debt = $this->debt(); $fine = $this->debt(DendaKaryawan::class); $deposit = $this->deposit('setelah_potongan');
        $row = $this->service->submit($this->payroll(), 'test');
        $this->service->cancel($row->id, 'test');
        $this->assertEquals(250000, $debt->fresh()->sisa_kasbon);
        $this->assertEquals(250000, $fine->fresh()->sisa_denda);
        $this->assertSame(-3, $deposit->fresh()->tenor_berjalan);
        $this->service->submit($this->payroll(['loan' => 100000]), 'test');
        $this->rejected(function () { $this->service->submit($this->payroll(['loan' => 100000]), 'test'); }, 'sudah ada');
        $this->assertEquals(150000, $debt->fresh()->sisa_kasbon);
    }

    public function testLaterFailureRollsBackEarlierDebtUpdate(): void
    {
        $debt = $this->debt(); $this->debt(DendaKaryawan::class);
        $this->rejected(function () {
            $this->service->submit($this->payroll(['loan' => 100000, 'sanksi' => 999999]), 'test');
        }, 'melebihi');
        $this->assertEquals(250000, $debt->fresh()->sisa_kasbon);
        $this->assertSame(0, Payroll::count());
    }

    public function testCannotCancelOlderDeductionOrSubmitBackdatedDeduction(): void
    {
        $this->debt();
        $row = $this->service->submit($this->payroll(['loan' => 50000]), 'test');
        $later = $this->service->submit($this->payroll(['loan' => 50000], '2026-02'), 'test');
        $this->rejected(function () use ($row) { $this->service->cancel($row->id, 'test'); }, 'lebih baru');
        $this->rejected(function () { $this->service->submit($this->payroll(['loan' => 50000], '2025-12'), 'test'); }, 'berikutnya');
        $this->service->cancel($later->id, 'test');
        $this->service->cancel($row->id, 'test');
        $this->assertEquals(250000, Kasbon::first()->sisa_kasbon);
    }

    public function testLegacyCancellationRequiresEvidenceButZeroLegacyIsAllowed(): void
    {
        $debt = $this->debt();
        $row = $this->payroll(['loan' => 75000]); $row->save();
        $this->rejected(function () use ($row) { $this->service->cancel($row->id, 'test'); }, 'Payroll lama');
        $this->assertEquals(250000, $debt->fresh()->sisa_kasbon);
        $this->assertTrue((bool) $row->fresh()->is_active);
        $zero = $this->payroll([], '2026-02'); $zero->save();
        $this->service->cancel($zero->id, 'test');
        $this->assertFalse((bool) $zero->fresh()->is_active);
    }

    public function testReplacedSourceIsNotConfusedWithNewDebt(): void
    {
        $debt = $this->debt();
        $row = $this->service->submit($this->payroll(['loan' => 100000]), 'test');
        $debt->is_active = false; $debt->save();
        $replacement = $this->debt();
        $this->rejected(function () use ($row) { $this->service->cancel($row->id, 'test'); }, 'sudah berubah');
        $this->assertEquals(250000, $replacement->fresh()->sisa_kasbon);
    }

    public function testInactiveHistoryIgnoredButMultipleActiveSourcesRejected(): void
    {
        $this->debt(Kasbon::class, ['is_active' => false]); $debt = $this->debt();
        $this->service->submit($this->payroll(['loan' => 100000]), 'test');
        $this->assertEquals(150000, $debt->fresh()->sisa_kasbon);
        $this->debt();
        $this->rejected(function () { $this->service->submit($this->payroll(['loan' => 100000], '2026-02'), 'test'); }, 'ambigu');
    }

    public function testDebtPreviewCapsBalanceIgnoresEndedAndDoesNotMultiplyJoinedRows(): void
    {
        $debt = $this->debt(Kasbon::class, ['sisa_kasbon' => 50000]);
        $this->db->getConnection()->getPdo()->sqliteCreateFunction('LEAST', function ($a, $b) { return min($a, $b); }, 2);
        $sql = PayrollDebtService::sqlPreviewAmount('kasbon');
        $query = 'SELECT ' . $sql . ' as amount FROM kasbon CROSS JOIN (SELECT 1 UNION ALL SELECT 2) AS duplicated';
        $this->assertEquals(50000, $this->db->getConnection()->selectOne($query)->amount);
        $debt->status = 'END'; $debt->save();
        $this->assertEquals(0, $this->db->getConnection()->selectOne($query)->amount);
    }

    public static function moneyFormats(): array
    {
        return [[100000, 100000.0], ['100000.00', 100000.0], ['100,000', 100000.0],
            ['100.000', 100000.0], ['-100,000', -100000.0], [null, 0.0], ['', 0.0]];
    }

    /** @dataProvider moneyFormats */
    public function testRawAndFormattedMoneyAreNotMultiplied($input, float $expected): void
    {
        $this->assertSame($expected, PayrollDebtService::amount($input));
    }

    public function testNegativeDebtAndMissingOrFutureSourceCannotMutatePayroll(): void
    {
        $debt = $this->debt();
        $this->rejected(function () { $this->service->submit($this->payroll(['loan' => -100000]), 'test'); }, 'negatif');
        $debt->bulan_mulai_pemotongan = '2026-02'; $debt->save();
        $this->rejected(function () { $this->service->submit($this->payroll(['loan' => 100000]), 'test'); }, 'tidak ditemukan');
        $this->rejected(function () { $this->service->submit($this->payroll(['sanksi' => 100000]), 'test'); }, 'tidak ditemukan');
        $this->assertSame(0, Payroll::count());
        $this->assertEquals(250000, $debt->fresh()->sisa_kasbon);
    }

    public function testCancelRollsBackEarlierRestorationIfAnotherSourceWasChanged(): void
    {
        $debt = $this->debt(); $fine = $this->debt(DendaKaryawan::class);
        $row = $this->service->submit($this->payroll(['loan' => 100000, 'sanksi' => 100000]), 'test');
        $fine->refresh(); $fine->nominal_potongan = 50000; $fine->save();
        $this->rejected(function () use ($row) { $this->service->cancel($row->id, 'test'); }, 'sudah berubah');
        $this->assertEquals(150000, $debt->fresh()->sisa_kasbon);
        $this->assertEquals(150000, $fine->fresh()->sisa_denda);
        $this->assertTrue((bool) $row->fresh()->is_active);
    }

    public function testStaleDepositAmountCannotAdvancePhase(): void
    {
        $deposit = $this->deposit('setelah_potongan');
        foreach ([100000, -50000] as $amount) {
            $this->rejected(function () use ($amount) {
                $this->service->submit($this->payroll(['pencadangan_upah' => $amount]), 'test');
            }, 'Nominal/fase');
        }
        $this->assertSame(-3, $deposit->fresh()->tenor_berjalan);
        $this->assertSame(0, Payroll::count());
    }

    public function testHeaderPeriodMismatchRejectedBeforeAnyDeduction(): void
    {
        $debt = $this->debt(); $row = $this->payroll(['loan' => 100000]);
        $row->periode_payroll = '2026-02';
        $this->rejected(function () use ($row) { $this->service->submit($row, 'test'); }, 'periodenya');
        $this->assertEquals(250000, $debt->fresh()->sisa_kasbon);
    }

    public function testMigrationRerunPreservesTypeAndNewRecordsUseLegacyDefault(): void
    {
        $deposit = $this->deposit('setelah_kontrak');
        $migration = require __DIR__ . '/../../database/migrations/2026_10_08_120000_add_tipe_pengembalian_to_pencadangan_upah_table.php';
        $migration->up();
        $this->assertSame('setelah_kontrak', $deposit->fresh()->tipe_pengembalian);
        $this->assertEquals(100000, $deposit->fresh()->nominal);
        $attributes = $deposit->getAttributes();
        unset($attributes['id'], $attributes['tipe_pengembalian']);
        $legacy = PencadanganUpah::forceCreate($attributes);
        $this->assertSame('setelah_potongan', $legacy->fresh()->tipe_pengembalian);
        $this->assertTrue($this->db->getConnection()->getSchemaBuilder()->hasColumn('payroll', 'deduction_snapshot'));
    }

    public function testPencadanganSqlPreviewMatchesSubmitAcrossAllPhases(): void
    {
        foreach (['setelah_potongan', 'setelah_kontrak'] as $type) {
            $deposit = $this->deposit($type);
            foreach ([-3, -1, 0, 1, 2, 3] as $phase) {
                foreach (['Probation', 'Contract', 'Permanent'] as $status) {
                    $deposit->tenor_berjalan = $phase; $deposit->save();
                    MasterKaryawan::where('id', 1)->update(['status_karyawan' => $status]);
                    $sql = 'SELECT ' . PencadanganUpahPayrollService::sqlPreviewAmount()
                        . ' as amount FROM pencadangan_upah JOIN master_karyawan ON master_karyawan.id = pencadangan_upah.id_karyawan'
                        . ' WHERE pencadangan_upah.id = ?';
                    $actual = $this->db->getConnection()->selectOne($sql, [$deposit->id])->amount;
                    $this->assertEquals(PencadanganUpahPayrollService::previewAmount($deposit, $status), $actual);
                }
            }
        }
    }

    public function testControllerPersistsSnapshotAndCancelsActualAmountsOnlyOnce(): void
    {
        $container = Container::getInstance();
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
        $container->instance('validator', new \Illuminate\Validation\Factory($translator, $container));
        $this->db->getConnection()->getSchemaBuilder()->table('payroll', function (Blueprint $t) {
            foreach (['nik_karyawan', 'status_karyawan', 'id_jabatan', 'nama_jabatan', 'hari_kerja',
                'tidak_hadir', 'no_rekening', 'nama_bank', 'keterangan', 'karyawan'] as $field) {
                $t->string($field)->nullable();
            }
        });
        $debt = $this->debt();
        $fine = $this->debt(DendaKaryawan::class);
        $deposit = $this->deposit('setelah_kontrak');
        $base = $this->payroll()->getAttributes();
        $request = new \Illuminate\Http\Request(array_merge($base, [
            'gaji_pokok' => '5000000.00', 'loan' => '50,000', 'sanksi' => '75000.00',
            'pencadangan_upah' => '-100,000', 'take_home_pay' => '4,800,000',
        ]));
        $controller = new \App\Http\Controllers\api\KalkulasiPayrollController($request);
        $response = $controller->writeData($request);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $row = Payroll::firstOrFail();
        $this->assertEquals(4800000, $row->take_home_pay, 'Persist the frontend total even when component arithmetic differs.');
        $this->assertEquals($debt->id, $row->deduction_snapshot['entries']['loan']['id']);
        $this->assertEquals($fine->id, $row->deduction_snapshot['entries']['sanksi']['id']);
        $this->assertEquals(200000, $debt->fresh()->sisa_kasbon);
        $this->assertEquals(175000, $fine->fresh()->sisa_denda);
        $this->assertSame(-2, $deposit->fresh()->tenor_berjalan);
        $this->assertSame(422, $controller->writeData($request)->getStatusCode());
        for ($retry = 0; $retry < 2; $retry++) {
            // Cancellation must use the stored transaction, not a client-supplied period.
            $cancel = $controller->canclePayroll(new \Illuminate\Http\Request([
                'id' => $row->id, 'periode_payroll' => '1900-01',
            ]));
            $this->assertSame(200, $cancel->getStatusCode(), $cancel->getContent());
        }
        $this->assertEquals(250000, $debt->fresh()->sisa_kasbon);
        $this->assertEquals(250000, $fine->fresh()->sisa_denda);
        $this->assertSame(-3, $deposit->fresh()->tenor_berjalan);
    }

    /** @dataProvider debtTypes */
    public function testNullStatusDebtIsIncludedInPreviewSubmitAndCancel(string $class, string $field, string $balance): void
    {
        $debt = $this->debt($class, ['status' => null, $balance => 1000000,
            'nominal_potongan' => 500000, 'sisa_tenor' => 2, 'bulan_mulai_pemotongan' => '2026-05']);
        $table = $debt->getTable();
        $this->db->getConnection()->getPdo()->sqliteCreateFunction('LEAST', function ($a, $b) { return min($a, $b); }, 2);
        $sql = 'SELECT ' . PayrollDebtService::sqlPreviewAmount($table) . ' AS amount FROM ' . $table
            . ' WHERE id_karyawan = ? AND is_active = 1 AND bulan_mulai_pemotongan <= ?';
        $this->assertEquals(0, $this->db->getConnection()->selectOne($sql, [1, '2026-04'])->amount);
        $this->assertEquals(500000, $this->db->getConnection()->selectOne($sql, [1, '2026-05'])->amount);
        $row = $this->service->submit($this->payroll([$field => 500000], '2026-05'), 'test');
        $this->assertEquals(500000, $debt->fresh()->$balance);
        $this->assertSame('ONGOING', $debt->fresh()->status);
        $this->service->cancel($row->id, 'test');
        $this->assertEquals(1000000, $debt->fresh()->$balance);
        $debt->refresh(); $debt->status = 'END'; $debt->save();
        $this->assertEquals(0, $this->db->getConnection()->selectOne($sql, [1, '2026-05'])->amount);
        $this->rejected(function () use ($field) {
            $this->service->submit($this->payroll([$field => 500000], '2026-05'), 'test');
        }, 'tidak ditemukan');
    }

    public function testNullStatusDepositCanBePreviewedSubmittedAndCancelled(): void
    {
        $deposit = $this->deposit('setelah_potongan', 4, ['status' => null,
            'nominal' => 200000, 'nominal_berjalan' => -200000, 'bulan_efektif' => '2026-05']);
        $this->assertEquals(-200000, PencadanganUpahPayrollService::previewAmount($deposit, 'Permanent'));
        $this->rejected(function () {
            $this->service->submit($this->payroll(['pencadangan_upah' => -200000], '2026-04'), 'test');
        }, 'tidak ditemukan');
        $row = $this->service->submit($this->payroll(['pencadangan_upah' => -200000], '2026-05'), 'test');
        $this->assertEquals(-3, $deposit->fresh()->tenor_berjalan);
        $this->service->cancel($row->id, 'test');
        $this->assertEquals(-4, $deposit->fresh()->tenor_berjalan);
        $this->assertNull($deposit->fresh()->status);
        $deposit->refresh(); $deposit->status = 'END'; $deposit->save();
        $this->assertEquals(0, PencadanganUpahPayrollService::previewAmount($deposit, 'Permanent'));
        $this->rejected(function () {
            $this->service->submit($this->payroll(['pencadangan_upah' => -200000], '2026-05'), 'test');
        }, 'tidak ditemukan');
    }

    public static function newKasbonStatuses(): array
    {
        return [[[], 'ONGOING'], [['status' => null], 'ONGOING'], [['status' => ''], 'ONGOING'], [['status' => 'END'], 'END']];
    }

    /** @dataProvider newKasbonStatuses */
    public function testKasbonStoreDefaultsMissingStatus(array $statusInput, string $expected): void
    {
        $this->db->getConnection()->getSchemaBuilder()->table('kasbon', function (Blueprint $t) {
            foreach (['total_kasbon', 'tenor', 'tanggal_permintaan', 'tanggal_pencairan', 'keterangan',
                'created_by', 'created_at', 'nik_karyawan', 'karyawan', 'kode_kasbon'] as $field) {
                $t->string($field)->nullable();
            }
        });
        $request = new \Illuminate\Http\Request(array_merge([
            'id_karyawan' => 1, 'total_kasbon' => '1.000.000', 'nominal_potongan' => '500.000',
            'tenor' => 2, 'bulan_mulai_pemotongan' => '2026-05',
        ], $statusInput));
        $controller = new \App\Http\Controllers\api\KasbonController($request);
        $response = $controller->store($request);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent());
        $this->assertSame($expected, Kasbon::firstOrFail()->status);
    }
}
