<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_backdate_packages')) {
            return;
        }

        Schema::create('order_backdate_packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_order_header')->nullable();
            $table->string('no_order', 64)->index();
            $table->string('no_document', 64)->nullable()->index();
            $table->string('status_sampling', 64)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('package_status', 32)->default('draft');
            $table->boolean('is_active')->default(true);
            $table->string('view_mode', 32)->default('display');
            $table->text('reason')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['no_order', 'is_active'], 'obd_pkg_no_order_active_idx');
        });

        Schema::create('order_backdate_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('package_id')->index();
            $table->string('step_key', 32);
            $table->string('step_status', 32)->default('pending');
            $table->string('document_status', 32)->default('not_required');
            $table->json('snapshot_original')->nullable();
            $table->json('applied_values')->nullable();
            $table->json('last_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('saved_by')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['package_id', 'step_key'], 'obd_step_pkg_key_uq');
        });

        Schema::create('order_backdate_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('package_id')->index();
            $table->string('step_key', 32)->nullable();
            $table->string('action', 64);
            $table->json('meta')->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_backdate_audit_logs');
        Schema::dropIfExists('order_backdate_steps');
        Schema::dropIfExists('order_backdate_packages');
    }
};
