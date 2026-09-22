<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_kpi_criteria')) {
            Schema::create('salary_adjustment_kpi_criteria', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('criteria_no');
                $table->string('criteria_name', 255);
                $table->decimal('weight_pct', 5, 2);
                $table->text('indicator_text');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_adjustment_kpi')) {
            Schema::create('salary_adjustment_kpi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->unique();
                $table->decimal('total_score_avg', 4, 2)->default(0);
                $table->decimal('total_final_score', 6, 2)->default(0);
                $table->string('interpretation', 50)->nullable();
                $table->text('summary');
                $table->text('strengths');
                $table->text('improvements');
                $table->string('created_by', 255);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_adjustment_kpi_items')) {
            Schema::create('salary_adjustment_kpi_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kpi_id');
                $table->unsignedTinyInteger('criteria_no');
                $table->string('criteria_name', 255);
                $table->decimal('weight_pct', 5, 2);
                $table->text('indicator_text')->nullable();
                $table->decimal('score', 3, 1);
                $table->decimal('final_score', 6, 2)->default(0);
                $table->timestamps();

                $table->index('kpi_id');
            });
        }

        if (!Schema::hasTable('salary_adjustment_status_logs')) {
            Schema::create('salary_adjustment_status_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->string('action', 50);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 255)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('request_id');
            });
        }

        $this->seedKpiCriteria();
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustment_kpi_items');
        Schema::dropIfExists('salary_adjustment_kpi');
        Schema::dropIfExists('salary_adjustment_kpi_criteria');
        Schema::dropIfExists('salary_adjustment_status_logs');
    }

    private function seedKpiCriteria(): void
    {
        if (DB::connection('mysql')->table('salary_adjustment_kpi_criteria')->count() > 0) {
            return;
        }

        $criteria = [
            [1, 'Kualitas Pekerjaan', 20, 'Akurasi, ketelitian, dan kelengkapan hasil kerja administratif'],
            [2, 'Ketepatan Waktu', 20, 'Kemampuan menyelesaikan tugas dan dokumen sesuai deadline'],
            [3, 'Disiplin & Kehadiran', 20, 'Kedisiplinan, kehadiran, dan kepatuhan jam kerja'],
            [4, 'Inisiatif & Proaktif', 20, 'Kemampuan mengambil inisiatif tanpa menunggu diperintah'],
            [5, 'Kolaborasi & Komunikasi', 20, 'Kerjasama tim dan komunikasi dengan rekan kerja serta atasan'],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($criteria as [$no, $name, $weight, $indicator]) {
            DB::connection('mysql')->table('salary_adjustment_kpi_criteria')->insert([
                'criteria_no' => $no,
                'criteria_name' => $name,
                'weight_pct' => $weight,
                'indicator_text' => $indicator,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
