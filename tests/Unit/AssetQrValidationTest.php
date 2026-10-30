<?php

namespace Tests\Unit;

use App\Models\GaAsset;
use App\Services\AssetQrValidationService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class AssetQrValidationTest extends TestCase
{
    private $db;
    private $oldContainer;
    private $oldFacade;
    private $oldResolver;
    private $service;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) $this->markTestSkipped('Requires isolated SQLite.');
        $this->oldContainer = Container::getInstance(); $this->oldFacade = Facade::getFacadeApplication();
        $this->oldResolver = GaAsset::getConnectionResolver();
        $container = new Container(); Container::setInstance($container);
        $container->instance('config', new Repository(['database.default' => 'mysql']));
        $this->db = new Manager($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'mysql');
        $this->db->getDatabaseManager()->setDefaultConnection('mysql'); $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($container);
        if (!class_exists('DB')) class_alias(\Illuminate\Support\Facades\DB::class, 'DB');
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('ga_assets', function (Blueprint $table) {
            $table->increments('id');
            foreach (['cs_code', 'name', 'brand', 'condition', 'lifecycle_state', 'asset_code', 'record_kind', 'model_spec',
                'condition_note', 'condition_text', 'usage_state', 'publication_state', 'verification_state',
                'acquisition_origin', 'purchase_date', 'damage_received_date'] as $field) $table->string($field)->nullable();
            $table->integer('room_id')->nullable(); $table->integer('location_id')->nullable();
            $table->integer('branch_id')->nullable(); $table->integer('kategori_aset_id')->nullable();
            $table->integer('sub_kategori_aset_id')->nullable(); $table->integer('useful_life_years')->nullable();
            $table->decimal('acquisition_cost', 20, 2)->nullable(); $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        foreach (['ga_asset_rooms', 'ga_asset_locations'] as $table) $schema->create($table, function (Blueprint $table) {
            $table->increments('id'); $table->string('name');
            $table->integer('location_id')->nullable(); $table->integer('branch_id')->nullable();
        });
        $schema->create('master_cabang', function (Blueprint $table) { $table->increments('id'); $table->string('nama_cabang'); });
        $schema->create('master_kategori_aset', function (Blueprint $table) { $table->increments('id'); $table->string('nama_kategori'); });
        $schema->create('master_sub_kategori_aset', function (Blueprint $table) { $table->increments('id'); $table->string('nama_sub_kategori'); });
        $schema->create('qr_documents', function (Blueprint $table) {
            $table->increments('id'); $table->string('type_document'); $table->string('kode_qr')->unique();
            $table->string('file'); $table->text('data'); $table->timestamp('created_at')->nullable(); $table->string('created_by')->nullable();
        });
        $schema->create('data_aset', function (Blueprint $table) {
            $table->increments('id');
            foreach (['no_cs', 'merk', 'tanggal_pembelian', 'kondisi', 'status_alat', 'ruang', 'lokasi'] as $field) $table->string($field)->nullable();
            $table->boolean('is_active')->default(true);
        });
        $this->service = new AssetQrValidationService();
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->getDatabaseManager()->purge('mysql');
            if ($this->oldResolver) GaAsset::setConnectionResolver($this->oldResolver); else GaAsset::unsetConnectionResolver();
            Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->oldFacade); Container::setInstance($this->oldContainer);
        }
        parent::tearDown();
    }

    private function asset(): GaAsset
    {
        return GaAsset::create(['cs_code' => 'ISL-LAP-001', 'name' => 'Laptop', 'brand' => 'Brand',
            'condition' => 'normal', 'lifecycle_state' => 'active', 'acquisition_cost' => '15000000', 'is_active' => true]);
    }

    public function testExportRegistersStableValidationUrlAndRefreshesJsonWithoutChangingAsset(): void
    {
        $asset = $this->asset();
        $before = $asset->fresh()->getAttributes();
        $first = $this->service->registerGaAsset($asset->id);
        $second = $this->service->registerGaAsset($asset->id);
        $this->assertSame($first->kode_qr, $second->kode_qr);
        $this->assertSame($first->id, $second->id);
        $this->assertMatchesRegularExpression('~^https://www\.intilab\.com/validation/isldc[a-f0-9]{32}$~', AssetQrValidationService::url($first->kode_qr));
        $this->assertSame(1, $this->db->getConnection()->table('qr_documents')->count());
        $this->assertEquals($before, $asset->fresh()->getAttributes());
        $data = json_decode($first->data, true);
        $this->assertSame('ISL-LAP-001', $data['no_cs']);
        $this->assertSame('ga_assets', $data['_asset_source']);
        $this->assertSame('Rp.15.000.000', $data['acquisition_cost']);
        $asset->name = 'Laptop Updated'; $asset->save();
        $updated = $this->service->registerGaAsset($asset->id);
        $this->assertSame($first->kode_qr, $updated->kode_qr);
        $this->assertSame('Laptop Updated', json_decode($updated->data, true)['nama_aset']);
    }

    public function testPublicValidationUsesCurrentAssetAndDoesNotExposeInternalFields(): void
    {
        $asset = $this->asset(); $record = $this->service->registerGaAsset($asset->id);
        $asset->condition = 'rusak'; $asset->save();
        $data = $this->service->publicData($record);
        $this->assertSame('rusak', $data['Kondisi']);
        $this->assertArrayNotHasKey('_asset_id', $data);
        $this->assertArrayNotHasKey('_asset_source', $data);
        $this->assertSame('Rp.15.000.000', $data['Harga Perolehan']);
        $asset->is_active = false; $asset->save();
        $this->assertNull($this->service->publicData($record));
    }

    public function testValidationUsesRoomLocationBranchNamesAndIncludesPurchaseDetails(): void
    {
        $db = $this->db->getConnection();
        $db->table('master_cabang')->insert([['id' => 1, 'nama_cabang' => 'BSD'], ['id' => 2, 'nama_cabang' => 'Karawang']]);
        $db->table('ga_asset_locations')->insert([['id' => 1, 'name' => 'Gedung BSD', 'branch_id' => 1], ['id' => 2, 'name' => 'Gedung Karawang', 'branch_id' => 2]]);
        $db->table('ga_asset_rooms')->insert(['id' => 1, 'name' => 'Ruang IT', 'location_id' => 1]);
        $db->table('master_kategori_aset')->insert(['id' => 1, 'nama_kategori' => 'Elektronik']);
        $db->table('master_sub_kategori_aset')->insert(['id' => 1, 'nama_sub_kategori' => 'Laptop']);
        $asset = $this->asset();
        // Deliberately stale direct location/branch: only room -> location -> cabang is authoritative.
        $asset->fill(['room_id' => 1, 'location_id' => 2, 'branch_id' => 2, 'kategori_aset_id' => 1,
            'sub_kategori_aset_id' => 1, 'asset_code' => 'GA-0001', 'record_kind' => 'unit',
            'condition_note' => '<p>Normal &amp; lengkap</p><p>Adaptor tersedia.</p>',
            'purchase_date' => '2025-01-15', 'useful_life_years' => 4, 'model_spec' => 'RAM 16 GB',
            'acquisition_origin' => 'new']);
        $asset->save();
        $record = $this->service->registerGaAsset($asset->id);
        $data = $this->service->publicData($record);
        $this->assertSame('Ruang IT', $data['Ruangan']);
        $this->assertSame('Gedung BSD', $data['Lokasi']);
        $this->assertSame('BSD', $data['Cabang']);
        $this->assertSame('Elektronik', $data['Kategori']);
        $this->assertSame('Laptop', $data['Subkategori']);
        $this->assertSame('15 Januari 2025', $data['Tanggal Pembelian']);
        $this->assertSame(4, $data['Masa Manfaat (Tahun)']);
        $this->assertSame('Rp.15.000.000', $data['Harga Perolehan']);
        $this->assertSame("Normal & lengkap\nAdaptor tersedia.", $data['Catatan Kondisi']);
        foreach (['room_id', 'location_id', 'branch_id', 'kategori_aset_id', 'sub_kategori_aset_id', '_asset_id'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
        // The same printed QR follows room relocations and purchase-data corrections immediately.
        $db->table('ga_asset_rooms')->where('id', 1)->update(['name' => 'Ruang Baru', 'location_id' => 2]);
        $asset->condition_note = 'Sudah diperbaiki'; $asset->acquisition_cost = '14000000'; $asset->save();
        $updated = $this->service->publicData($record);
        $this->assertSame('Ruang Baru', $updated['Ruangan']);
        $this->assertSame('Gedung Karawang', $updated['Lokasi']);
        $this->assertSame('Karawang', $updated['Cabang']);
        $this->assertSame('Rp.14.000.000', $updated['Harga Perolehan']);
        $this->assertSame('Sudah diperbaiki', $updated['Catatan Kondisi']);
    }

    public function testMissingRoomDoesNotFallBackToStaleDirectLocation(): void
    {
        $db = $this->db->getConnection();
        $db->table('ga_asset_locations')->insert(['id' => 2, 'name' => 'Stale location']);
        $asset = $this->asset(); $asset->location_id = 2; $asset->save();
        $data = $this->service->publicData($this->service->registerGaAsset($asset->id));
        $this->assertNull($data['Ruangan']);
        $this->assertNull($data['Lokasi']);
        $this->assertNull($data['Cabang']);
        $this->assertNull($data['Tanggal Pembelian']);
        $this->assertNull($data['Masa Manfaat (Tahun)']);
    }

    public function testOpnameAcceptsOldNoCsNewUrlAndTokenAndResolvesCurrentCode(): void
    {
        $asset = $this->asset(); $record = $this->service->registerGaAsset($asset->id);
        $this->assertSame('ISL-LAP-001', $this->service->opnameCode(' isl-lap-001 '));
        $this->assertSame('ISL-LAP-001', $this->service->opnameCode(AssetQrValidationService::url($record->kode_qr)));
        $this->assertSame('ISL-LAP-001', $this->service->opnameCode($record->kode_qr));
        $asset->cs_code = 'ISL-LAP-002'; $asset->save();
        $this->assertSame('ISL-LAP-002', $this->service->opnameCode(AssetQrValidationService::url($record->kode_qr)));
        $asset->is_active = false; $asset->save();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->opnameCode(AssetQrValidationService::url($record->kode_qr));
    }

    public function testLegacyAssetJsonRemainsSupportedButCannotBeConfusedWithGaAsset(): void
    {
        $this->db->getConnection()->table('data_aset')->insert(['no_cs' => 'OLD-001', 'merk' => 'Legacy Brand', 'is_active' => 1]);
        $record = (object) ['type_document' => 'asset', 'data' => json_encode(['no_cs' => 'OLD-001', 'merk' => 'Stale Brand'])];
        $this->assertSame('Legacy Brand', $this->service->publicData($record)['Merek']);
        $record->type_document = 'invoice';
        $this->assertNull($this->service->publicData($record));
    }

    /** @dataProvider invalidUrls */
    public function testOpnameRejectsForeignOrInvalidValidationUrls(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->opnameCode($url);
    }

    public function invalidUrls(): array
    {
        return [['https://evil.example/validation/token'], ['http://www.intilab.com/validation/token'],
            ['https://www.intilab.com/other/token'], ['https://user@www.intilab.com/validation/token'],
            ['https://www.intilab.com:443/validation/token'], ['https://www.intilab.com/validation/unregistered']];
    }

    public function testInvoiceTokenCannotBeUsedAsAssetQr(): void
    {
        $this->db->getConnection()->table('qr_documents')->insert(['type_document' => 'invoice', 'kode_qr' => 'isldcinvoice', 'file' => 'Invoice', 'data' => '{}']);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->opnameCode(AssetQrValidationService::url('isldcinvoice'));
    }

    public function testPublicValidatorReturnsAssetJsonAndKeepsInvoiceJsonUnchanged(): void
    {
        $asset = $this->asset();
        $record = $this->service->registerGaAsset($asset->id, 'Harold');
        $this->assertSame('Harold', $record->created_by);
        $handler = new \App\Http\Controllers\external\ValidatorHandler();
        $response = $handler->handleDocument(new \Illuminate\Http\Request(['qr' => $record->kode_qr]));
        $result = $response->getData(true);
        $this->assertSame('Document Valid.', $result['message']);
        $this->assertSame('ISL-LAP-001', $result['data']['Nomor CS']);
        $this->assertArrayNotHasKey('_asset_id', $result['data']);
        $asset->is_active = false; $asset->save();
        $invalid = $handler->handleDocument(new \Illuminate\Http\Request(['qr' => $record->kode_qr]))->getData(true);
        $this->assertSame('QR Code Invalid.', $invalid['message']);
        $data = ['no_document' => 'ISL/INV/TEST', 'nama_customer' => 'Client', 'type_document' => 'invoice', 'Disahkan_Oleh' => 'Signer'];
        $this->db->getConnection()->table('qr_documents')->insert(['type_document' => 'invoice', 'kode_qr' => 'isldctestinvoice', 'file' => 'Invoice', 'data' => json_encode($data)]);
        $invoice = $handler->handleDocument(new \Illuminate\Http\Request(['qr' => 'isldctestinvoice']))->getData(true);
        $this->assertSame('Document Valid.', $invoice['message']);
        $this->assertSame($data, $invoice['data']);
    }
}
