<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateKebijakanDokumenAndExtendStatus extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_kebijakan')) {
            Schema::table('request_kebijakan', function (Blueprint $table) {
                if (!Schema::hasColumn('request_kebijakan', 'parent_kebijakan_dokumen_id')) {
                    $table->unsignedBigInteger('parent_kebijakan_dokumen_id')->nullable()->after('catatan');
                }
            });

            DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
                'waiting_approval',
                'approved',
                'on_process',
                'rejected',
                'completed',
                'pending_user_review',
                'pending_user_reject_review',
                'pending_legal_final',
                'pending_director_approval'
            ) NULL");
        }

        if (Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::create('kebijakan_dokumen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_kebijakan_id');
            $table->unsignedBigInteger('drafting_kebijakan_id');
            $table->unsignedBigInteger('parent_dokumen_id')->nullable();
            $table->string('no_dokumen', 100);
            $table->string('judul', 255);
            $table->enum('kategori', ['new', 'revision', 'termination'])->default('new');
            $table->unsignedSmallInteger('revisian')->default(0);
            $table->unsignedSmallInteger('cetakan')->default(1);
            $table->date('tanggal_pengesahan')->nullable();
            $table->enum('status', [
                'pending_director',
                'active',
                'archived',
                'returned_to_legal',
            ])->default('pending_director');
            $table->enum('archive_reason', [
                'terminated',
                'deactivated',
                'superseded_revision',
            ])->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('legal_verified_by', 255)->nullable();
            $table->timestamp('legal_verified_at')->nullable();
            $table->string('director_approved_by', 255)->nullable();
            $table->timestamp('director_approved_at')->nullable();
            $table->string('director_rejected_by', 255)->nullable();
            $table->timestamp('director_rejected_at')->nullable();
            $table->text('director_rejected_note')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('no_dokumen', 'uq_kebijakan_dokumen_no');
            $table->index(['status', 'is_active'], 'idx_kd_status_active');
            $table->index('request_kebijakan_id', 'idx_kd_request');
            $table->foreign('request_kebijakan_id')
                ->references('id')
                ->on('request_kebijakan')
                ->onDelete('cascade');
            $table->foreign('drafting_kebijakan_id')
                ->references('id')
                ->on('drafting_kebijakan')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebijakan_dokumen');

        if (Schema::hasTable('request_kebijakan')) {
            DB::statement("UPDATE request_kebijakan SET status = 'completed' WHERE status IN ('pending_legal_final', 'pending_director_approval')");

            DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
                'waiting_approval',
                'approved',
                'on_process',
                'rejected',
                'completed',
                'pending_user_review',
                'pending_user_reject_review'
            ) NULL");
        }
    }
}
