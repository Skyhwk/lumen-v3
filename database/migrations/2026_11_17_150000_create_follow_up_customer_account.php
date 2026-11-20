<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU = 'Follow Up Portal';

    public function up(): void
    {
        if (!Schema::hasTable('follow_up_customer_account')) {
            Schema::create('follow_up_customer_account', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('master_pelanggan_id')->nullable();
                $table->string('id_pelanggan', 50);
                $table->string('nama_pelanggan', 255)->nullable();
                $table->string('wilayah', 255)->nullable();
                $table->string('npwp', 100)->nullable();
                $table->string('no_tlp', 50)->nullable();
                $table->string('email', 255)->nullable();
                $table->text('alamat')->nullable();
                $table->string('pic_nama', 255)->nullable();
                $table->string('pic_jabatan', 255)->nullable();
                $table->string('pic_telepon', 50)->nullable();
                $table->string('pic_email', 255)->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->string('slot', 20)->nullable();
                $table->dateTime('assigned_at')->nullable();
                $table->string('keputusan', 30)->nullable();
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('followed_by_id')->nullable();
                $table->string('followed_by', 255)->nullable();
                $table->dateTime('followed_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->unique('id_pelanggan', 'uq_follow_up_customer_id_pelanggan');
                $table->index(['followed_at', 'id'], 'idx_follow_up_customer_queue');
                $table->index(['assigned_to', 'slot'], 'idx_follow_up_customer_assignee');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_customer_account');
    }
};
