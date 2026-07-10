<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSamplerTrackingTables extends Migration
{
    public function up()
    {
        Schema::create('sampler_tracking_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('team_key', 191)->unique();
            $table->unsignedBigInteger('id_sampling')->nullable();
            $table->integer('parsial')->nullable();
            $table->string('no_quotation', 70)->nullable();
            $table->string('no_order', 70)->nullable();
            $table->date('tanggal_sampling')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('durasi', 50)->nullable();
            $table->string('kendaraan', 100)->nullable();
            $table->string('driver', 100)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->json('kategori')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['tanggal_sampling', 'is_active'], 'idx_sampler_tracking_sessions_date_active');
            $table->index(['no_quotation', 'tanggal_sampling'], 'idx_sampler_tracking_sessions_qt_date');
            $table->index('no_order', 'idx_sampler_tracking_sessions_no_order');
        });

        Schema::create('sampler_tracking_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sampler_tracking_session_id');
            $table->string('sampler_id', 70)->nullable();
            $table->string('sampler_name', 150)->nullable();
            $table->string('durasi', 50)->nullable();
            $table->string('durasi_personal', 50)->nullable();
            $table->string('effective_duration', 50)->nullable();
            $table->string('current_movement_group', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('sampler_tracking_session_id', 'sampler_tracking_member_session_fk')
                ->references('id')
                ->on('sampler_tracking_sessions')
                ->onDelete('cascade');
            $table->index(['sampler_id', 'is_active'], 'idx_sampler_tracking_members_sampler_id');
            $table->index(['sampler_name', 'is_active'], 'idx_sampler_tracking_members_sampler_name');
            $table->index('current_movement_group', 'idx_sampler_tracking_members_movement_group');
        });

        Schema::create('sampler_tracking_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sampler_tracking_session_id');
            $table->unsignedBigInteger('sampler_tracking_member_id');
            $table->unsignedBigInteger('triggered_by_member_id')->nullable();
            $table->string('event_type', 30);
            $table->string('movement_group', 191)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('photo', 255)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_auto')->default(false);
            $table->integer('sequence_no')->default(1);
            $table->timestamp('event_at')->nullable();
            $table->timestamps();

            $table->foreign('sampler_tracking_session_id', 'sampler_tracking_event_session_fk')
                ->references('id')
                ->on('sampler_tracking_sessions')
                ->onDelete('cascade');
            $table->foreign('sampler_tracking_member_id', 'sampler_tracking_event_member_fk')
                ->references('id')
                ->on('sampler_tracking_members')
                ->onDelete('cascade');
            $table->index(['event_type', 'event_at'], 'idx_sampler_tracking_events_type_time');
            $table->index(['sampler_tracking_session_id', 'movement_group'], 'idx_sampler_tracking_events_session_group');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sampler_tracking_events');
        Schema::dropIfExists('sampler_tracking_members');
        Schema::dropIfExists('sampler_tracking_sessions');
    }
}
