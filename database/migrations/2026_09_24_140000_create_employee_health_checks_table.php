<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            Schema::create('employee_health_checks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('check_date');
                $table->time('check_time');

                $table->unsignedSmallInteger('tensi_sistolik');
                $table->unsignedSmallInteger('tensi_diastolik');
                $table->enum('tensi_classification', [
                    'Rendah',
                    'Rata-Rata',
                    'Tinggi',
                ]);

                $table->decimal('saturasi', 5, 2);
                $table->unsignedSmallInteger('nadi');
                $table->decimal('suhu', 4, 1);
                $table->enum('stetoskop', ['normal', 'ada_kelainan']);

                $table->decimal('gula_darah', 8, 2)->nullable();
                $table->decimal('asam_urat', 8, 2)->nullable();
                $table->decimal('kolesterol', 8, 2)->nullable();

                $table->enum('keterangan', [
                    'sehat',
                    'perlu_observasi',
                    'perlu_rujukan',
                    'izin',
                    'sakit',
                    'lainnya',
                ]);
                $table->text('keluhan')->nullable();

                $table->string('skk_number', 50)->nullable();

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('employee_id');
                $table->index('check_date');
                $table->index(['employee_id', 'check_date']);
                $table->index('keterangan');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_health_checks');
    }
};
