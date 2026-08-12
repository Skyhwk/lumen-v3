<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNewRecruitmentTable extends Migration
{
    /**
     * Run the migrations for new_recruitment table.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            Schema::create('new_recruitment', function (Blueprint $table) {
                $table->id();
                $table->string('nama_lengkap', 255);
                $table->string('tempat_lahir', 255)->nullable();
                $table->date('tanggal_lahir')->nullable();
                $table->enum('jenis_kelamin', ['Male', 'Female'])->nullable();
                $table->text('alamat_ktp')->nullable();
                $table->text('alamat_domisili')->nullable();
                $table->string('no_telepon', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->json('pendidikan')->nullable();
                $table->json('pengalaman_kerja')->nullable();
                $table->string('shio', 100)->nullable();
                $table->string('elemen', 100)->nullable();

                // Foreign key to personnel_requests
                $table->unsignedBigInteger('personnel_request_id')->nullable();
                $table->foreign('personnel_request_id')
                    ->references('id')
                    ->on('personnel_requests')
                    ->nullOnDelete();

                $table->string('posisi_dilamar', 255)->nullable();
                $table->double('nilai_kecocokan')->default(0)->nullable();
                $table->decimal('gaji_terakhir', 15, 2)->nullable();
                $table->decimal('ekspetasi_gaji', 15, 2)->default(0.00);
                $table->date('tanggal_join_tercepat')->nullable();
                $table->text('token');

                $table->enum('status', [
                    'assessment',
                    'screening',
                    'approved',
                    'interview_hrd',
                    'profile_completion',
                    'interview_user',
                    'management_decision',
                    'internal_sallary_offer',
                    'salary_offer',
                    'hired',
                    'rejected'
                ])->default('assessment');

                $table->boolean('is_approved_interview_hrd')->default(false);
                $table->string('approved_interview_hrd_by', 255)->nullable();
                $table->datetime('approved_interview_hrd_at')->nullable();
                $table->boolean('is_input_review_hrd')->default(false);

                $table->string('approved_by', 255)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('rejected_by', 255)->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('alasan_reject')->nullable();

                $table->timestamps();

                $table->json('meta_history')->nullable();
                $table->text('token_approval')->nullable();

                $table->string('approved_interview_user', 100)->nullable();
                $table->timestamp('approved_interview_user_at')->nullable();
                $table->boolean('is_approve_interview_user')->default(false)->nullable();
                $table->string('picture', 255)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('new_recruitment');
    }
}
