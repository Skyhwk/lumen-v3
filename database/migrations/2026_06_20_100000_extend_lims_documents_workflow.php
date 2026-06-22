<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendLimsDocumentsWorkflow extends Migration
{
    public function up()
    {
        Schema::table('lims_documents', function (Blueprint $table) {
            $table->string('no_dokumen', 100)->nullable()->after('menu_slug');
            $table->string('header_dokumen', 255)->nullable()->after('nama_dokumen');
            $table->string('sub_header_dokumen', 255)->nullable()->after('header_dokumen');
            $table->date('tanggal_cetak')->nullable()->after('sub_header_dokumen');
            $table->unsignedSmallInteger('cetakan')->nullable()->after('revisian');
            $table->string('disusun_oleh', 255)->nullable()->after('cetakan');
            $table->string('jabatan_penyusun', 255)->nullable()->after('disusun_oleh');
            $table->string('status', 30)->default('in_review')->after('jabatan_penyusun');
            $table->date('tanggal_pengesahan')->nullable()->after('disahkan_pada');
        });
    }

    public function down()
    {
        Schema::table('lims_documents', function (Blueprint $table) {
            $table->dropColumn([
                'no_dokumen',
                'header_dokumen',
                'sub_header_dokumen',
                'tanggal_cetak',
                'cetakan',
                'disusun_oleh',
                'jabatan_penyusun',
                'status',
                'tanggal_pengesahan',
            ]);
        });
    }
}
