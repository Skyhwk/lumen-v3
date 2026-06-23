<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLimsDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('lims_documents', function (Blueprint $table) {
            $table->id();
            $table->string('menu_slug', 255);
            $table->string('nama_dokumen', 500);
            $table->string('terbitan', 100)->nullable();
            $table->string('revisian', 100)->nullable();
            $table->string('pengesahan', 255)->nullable();
            $table->date('disahkan_pada')->nullable();
            $table->string('content_file', 255)->nullable();
            $table->json('extra_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['menu_slug', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lims_documents');
    }
}
