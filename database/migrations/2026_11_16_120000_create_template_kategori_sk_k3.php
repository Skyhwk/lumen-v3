<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTemplateKategoriSkK3 extends Migration
{
    private const SEED_SUB_KATEGORI = [
        'Udara Angka Kuman',
        'Getaran (Kenyamanan & Kesehatan)',
        'Getaran (Lengan & Tangan)',
        'Getaran (Seluruh Tubuh)',
        'Iklim Kerja',
        'Kebisingan',
        'Kualitas Udara Dalam Ruang',
        'Udara Lingkungan Kerja',
        'Pencahayaan',
        'Mikrobiologi Udara',
        'Ergonomi',
        'Kelembapan',
        'Higiene Sanitasi',
        'Psikologi',
    ];

    public function up()
    {
        if (!Schema::hasTable('template_kategori_sk_k3')) {
            Schema::create('template_kategori_sk_k3', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_sub_kategori');
                $table->string('nama_kategori', 150)->nullable();
                $table->string('nama_sub_kategori', 255)->nullable();
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(1);

                $table->unique('id_sub_kategori', 'template_kategori_sk_k3_sub_unique');
                $table->index('is_active');
            });
        }

        if (Schema::hasTable('ticket_sk_k3') && !Schema::hasColumn('ticket_sk_k3', 'kategori_terpilih')) {
            Schema::table('ticket_sk_k3', function (Blueprint $table) {
                $table->json('kategori_terpilih')->nullable()->after('wilayah_surat');
            });
        }

        $this->seedTemplate();
    }

    public function down()
    {
        if (Schema::hasTable('ticket_sk_k3') && Schema::hasColumn('ticket_sk_k3', 'kategori_terpilih')) {
            Schema::table('ticket_sk_k3', function (Blueprint $table) {
                $table->dropColumn('kategori_terpilih');
            });
        }

        Schema::dropIfExists('template_kategori_sk_k3');
    }

    private function seedTemplate(): void
    {
        if (!Schema::hasTable('template_kategori_sk_k3') || !Schema::hasTable('master_sub_kategori')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $subsQuery = DB::table('master_sub_kategori')
            ->whereIn('nama_sub_kategori', self::SEED_SUB_KATEGORI);
        if (Schema::hasColumn('master_sub_kategori', 'is_active')) {
            $subsQuery->where('is_active', 1);
        }
        $masterRows = $subsQuery->get(['id', 'nama_kategori', 'nama_sub_kategori'])
            ->keyBy('nama_sub_kategori');

        foreach (self::SEED_SUB_KATEGORI as $name) {
            $master = $masterRows[$name] ?? null;
            if (!$master) {
                continue;
            }

            $exists = DB::table('template_kategori_sk_k3')
                ->where('id_sub_kategori', $master->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('template_kategori_sk_k3')->insert([
                'id_sub_kategori' => $master->id,
                'nama_kategori' => $master->nama_kategori,
                'nama_sub_kategori' => $master->nama_sub_kategori,
                'created_by' => 'migration',
                'created_at' => $now,
                'is_active' => 1,
            ]);
        }
    }
}