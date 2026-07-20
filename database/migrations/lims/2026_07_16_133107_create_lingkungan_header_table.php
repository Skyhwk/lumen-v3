<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLingkunganHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lingkungan_header', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 50)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->bigInteger('id_parameter')->nullable();
            $table->string('parameter', 50)->nullable();
            $table->integer('template_stp');
            $table->date('tanggal_terima')->nullable();
            $table->integer('lhps')->default(0)->comment('app param di wsfinal');
            $table->integer('tipe_koreksi')->nullable();
            
            // Data JSON & Konfigurasi
            $table->json('data_shift')->nullable()->comment('data konsentrasi per shift');
            $table->json('data_pershift')->nullable()->comment('data hasil pershift');
            $table->boolean('use_absorbansi')->default(0);
            $table->string('input_koreksi', 100)->nullable()->comment('Nilai faktor koreksi');
            
            // Catatan
            $table->text('note')->nullable();
            $table->text('notes_reject')->nullable();
            
            // Workflow Status & Audit Trails
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 100)->nullable();
            $table->integer('is_approved')->default(0);
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('deleted_by', 100)->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->integer('status')->default(0);
            $table->integer('is_active')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lingkungan_header');
    }
}
