<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceTaxReturn;
use App\Services\InvoiceTaxReturnService;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class InvoiceTaxReturnTest extends TestCase
{
    private $db;
    private $previousContainer;
    private $previousFacade;
    private $previousResolver;
    private $service;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) $this->markTestSkipped('Requires isolated SQLite.');
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Invoice::getConnectionResolver();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository(['database.default' => 'mysql']));
        $this->db = new Manager($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'mysql');
        $this->db->getDatabaseManager()->setDefaultConnection('mysql');
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        $container->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en'), $container
        ));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('invoice', function (Blueprint $table) {
            $table->increments('id');
            foreach (['no_invoice', 'no_order', 'faktur_pajak', 'nama_perusahaan'] as $field) $table->string($field)->nullable();
            $table->decimal('nilai_pelunasan', 20, 2)->nullable();
            $table->decimal('nilai_tagihan', 20, 2);
            $table->boolean('is_active')->default(true);
        });
        // Fixtures only; no application migration is executed.
        $schema->create('invoice_tax_returns', function (Blueprint $table) {
            $table->increments('id');
            foreach (['no_invoice', 'faktur_pajak', 'nama_perusahaan', 'return_date', 'amount_basis', 'reason', 'status', 'created_by_name'] as $field) $table->text($field)->nullable();
            $table->string('request_key')->unique();
            $table->decimal('amount', 20, 2);
            $table->decimal('dpp', 20, 2);
            $table->decimal('ppn', 20, 2);
            $table->text('invoice_snapshot');
            $table->text('meta_history');
            $table->integer('created_by');
            $table->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'Asia/Jakarta'));
        $this->service = new InvoiceTaxReturnService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        if ($this->db) {
            $this->db->getDatabaseManager()->purge('mysql');
            if ($this->previousResolver) Invoice::setConnectionResolver($this->previousResolver);
            else Invoice::unsetConnectionResolver();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacade);
            Container::setInstance($this->previousContainer);
        }
        parent::tearDown();
    }

    private function invoice(array $values = [])
    {
        return Invoice::create(array_merge(['no_invoice' => 'ISL/INV/TEST', 'no_order' => 'ORDER1', 'faktur_pajak' => 'FP-001',
            'nama_perusahaan' => 'Client', 'nilai_pelunasan' => 500, 'nilai_tagihan' => 1000, 'is_active' => 1], $values));
    }

    private function input(array $values = []): array
    {
        return array_merge(['no_invoice' => 'ISL/INV/TEST', 'dpp' => '100', 'ppn' => '0.25', 'return_date' => '2026-10-30', 'reason' => 'Pembatalan sebagian pengujian', 'request_key' => 'test-request'], $values);
    }

    public function testEligibilityUsesFakturAndNonzeroPaymentAndGroupsInvoiceRows(): void
    {
        $this->invoice();
        $this->invoice(['no_order' => 'ORDER2', 'nilai_tagihan' => 500]);
        $this->invoice(['no_invoice' => 'ISL/INV/ZERO', 'nilai_pelunasan' => 0]);
        $this->invoice(['no_invoice' => 'ISL/INV/NULL', 'nilai_pelunasan' => null]);
        $this->invoice(['no_invoice' => 'ISL/INV/NOFP', 'faktur_pajak' => '  ']);
        $this->invoice(['no_invoice' => 'ISL/INV/INACTIVE', 'is_active' => 0]);
        $eligible = $this->service->eligibleInvoices('ISL/INV');
        $this->assertCount(1, $eligible);
        $this->assertSame('1500.00', $eligible[0]['nilai_tagihan']);
        $this->assertEquals(500, $eligible[0]['nilai_pelunasan']);
        $this->assertSame(['ORDER1', 'ORDER2'], $eligible[0]['orders']);
    }

    public function testDraftSavesAmountSnapshotAndActorWithoutChangingInvoice(): void
    {
        $invoice = $this->invoice();
        $before = $invoice->getAttributes();
        $draft = $this->service->createDraft($this->input(['faktur_pajak' => 'FAKE', 'nilai_pelunasan' => 9999, 'amount' => '0.01']), 601, 'Harold');
        $this->assertSame('100.25', $draft->amount);
        $this->assertSame('FP-001', $draft->faktur_pajak);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('dpp_plus_ppn', $draft->amount_basis);
        $this->assertSame('100.00', $draft->dpp);
        $this->assertSame('0.25', $draft->ppn);
        $this->assertEquals(500, $draft->invoice_snapshot['nilai_pelunasan']);
        $this->assertSame(601, $draft->meta_history[0]['actor_id']);
        $this->assertEquals($before, $invoice->fresh()->getAttributes());
    }

    public function testSavingRechecksEligibilityAfterPaymentChanges(): void
    {
        $invoice = $this->invoice();
        $this->assertCount(1, $this->service->eligibleInvoices('TEST'));
        $invoice->nilai_pelunasan = 0; $invoice->save();
        $this->expectException(ValidationException::class);
        $this->service->createDraft($this->input(), 601, 'Harold');
    }

    public function testAccumulatedDraftsCannotExceedInvoiceValue(): void
    {
        $this->invoice();
        $this->service->createDraft($this->input(['dpp' => '900', 'ppn' => '0']), 601, 'Harold');
        $this->assertSame('100.00', $this->service->eligibleInvoices('TEST')[0]['remaining_amount']);
        try {
            $this->service->createDraft($this->input(['dpp' => '100', 'ppn' => '0.01', 'request_key' => 'second']), 601, 'Harold');
            $this->fail('Overlapping drafts must not exceed invoice amount.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(1, InvoiceTaxReturn::count());
    }

    public function testRetriesDoNotCreateDuplicateDrafts(): void
    {
        $this->invoice();
        $first = $this->service->createDraft($this->input(), 601, 'Harold');
        $second = $this->service->createDraft($this->input(), 601, 'Harold');
        $this->assertEquals($first->id, $second->id);
        $this->assertSame(1, InvoiceTaxReturn::count());
        $this->expectException(ValidationException::class);
        $this->service->createDraft($this->input(['dpp' => '101']), 601, 'Harold');
    }

    /** @dataProvider invalidDrafts */
    public function testInvalidDraftsAreRejected(array $input): void
    {
        $this->invoice();
        $this->expectException(ValidationException::class);
        $this->service->createDraft($this->input($input), 601, 'Harold');
    }

    public function invalidDrafts(): array
    {
        return [['zero' => ['dpp' => '0']], ['negative' => ['dpp' => '-1']], ['precision' => ['dpp' => '1.001']],
            ['negative_ppn' => ['ppn' => '-0.01']], ['future' => ['return_date' => '2026-10-31']], ['blank' => ['reason' => '   ']]];
    }

    public function testInconsistentFakturNumbersAreRejected(): void
    {
        $this->invoice(); $this->invoice(['faktur_pajak' => 'FP-OTHER']);
        $this->assertCount(0, $this->service->eligibleInvoices('TEST'));
        $this->expectException(ValidationException::class);
        $this->service->createDraft($this->input(), 601, 'Harold');
    }

    public function testPaymentConditionRemainsNonzeroRatherThanStrictlyPositive(): void
    {
        $this->invoice(['nilai_pelunasan' => -100]);
        $this->assertCount(1, $this->service->eligibleInvoices('TEST'));
    }

    public function testBackendUsesTaxMenuPermissionsAndSeparatesViewFromCreate(): void
    {
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('master_karyawan', function (Blueprint $table) {
            $table->integer('id')->primary(); $table->integer('user_id');
        });
        $schema->create('akses_menu', function (Blueprint $table) {
            $table->integer('id')->primary(); $table->integer('user_id'); $table->text('akses');
        });
        $db = $this->db->getConnection();
        $db->table('master_karyawan')->insert(['id' => 601, 'user_id' => 900]);
        $db->table('akses_menu')->insert(['id' => 1, 'user_id' => 900, 'akses' => json_encode([
            ['name' => 'Upload Faktur Pajak', 'parent' => 'Finance/Tax', 'access' => ['view']],
            ['name' => 'Upload Faktur Pajak', 'parent' => 'Another/Module', 'access' => ['create']],
        ])]);
        $reflection = new \ReflectionClass(\App\Http\Controllers\api\InvoiceTaxReturnController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $user = new \ReflectionProperty(\App\Http\Controllers\Controller::class, 'user_id');
        $user->setAccessible(true); $user->setValue($controller, 601);
        $permission = $reflection->getMethod('permitted'); $permission->setAccessible(true);
        $this->assertTrue($permission->invoke($controller, 'view'));
        $this->assertFalse($permission->invoke($controller, 'create'));
        $db->table('akses_menu')->where('id', 1)->update(['akses' => json_encode([
            ['name' => 'Upload Faktur Pajak', 'parent' => 'Finance/Tax', 'access' => ['view', 'create']],
        ])]);
        $this->assertTrue($permission->invoke($controller, 'create'));
        $user->setValue($controller, null);
        $this->assertFalse($permission->invoke($controller, 'view'));
    }
}
