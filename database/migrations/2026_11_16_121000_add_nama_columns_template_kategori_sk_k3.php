<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddNamaColumnsTemplateKategoriSkK3 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('template_kategori_sk_k3')) {
            return;
        }

        
        if (!Schema::hasColumn('template_kategori_sk_k3', 'nama_sub_kategori')) {
            Schema::table('template_kategori_sk_k3', function (Blueprint $table) {
                $table->string('nama_sub_kategori', 255)->nullable()->after('id_sub_kategori');
            });
        }
        
        if (!Schema::hasColumn('template_kategori_sk_k3', 'nama_kategori')) {
            Schema::table('template_kategori_sk_k3', function (Blueprint $table) {
                $table->string('nama_kategori', 150)->nullable()->after('nama_sub_kategori');
            });
        }
        $this->backfillFromMaster();
    }

    public function down()
    {
        if (!Schema::hasTable('template_kategori_sk_k3')) {
            return;
        }

        Schema::table('template_kategori_sk_k3', function (Blueprint $table) {
            if (Schema::hasColumn('template_kategori_sk_k3', 'nama_sub_kategori')) {
                $table->dropColumn('nama_sub_kategori');
            }
            if (Schema::hasColumn('template_kategori_sk_k3', 'nama_kategori')) {
                $table->dropColumn('nama_kategori');
            }
        });
    }

    private function backfillFromMaster(): void
    {
        if (!Schema::hasTable('master_sub_kategori')) {
            return;
        }

        $rows = DB::table('template_kategori_sk_k3')->get(['id', 'id_sub_kategori']);

        foreach ($rows as $row) {
            $master = DB::table('master_sub_kategori')
                ->where('id', $row->id_sub_kategori)
                ->first(['nama_kategori', 'nama_sub_kategori']);

            if (!$master) {
                continue;
            }

            DB::table('template_kategori_sk_k3')->where('id', $row->id)->update([
                'nama_kategori' => $master->nama_kategori,
                'nama_sub_kategori' => $master->nama_sub_kategori,
            ]);
        }
    }
}