<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeMailIndexToIdKaryawan extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mail_folder_meta')) {
            return;
        }

        Schema::dropIfExists('mail_list_index');
        Schema::dropIfExists('mail_folder_meta');

        Schema::create('mail_folder_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_karyawan');
            $table->string('folder', 32);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('unread_count')->default(0);
            $table->unsignedBigInteger('uidnext')->default(0);
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->unsignedInteger('min_seq')->default(0);
            $table->unsignedInteger('max_seq')->default(0);
            $table->unsignedInteger('indexed_count')->default(0);
            $table->dateTime('synced_at')->nullable();

            $table->unique(['id_karyawan', 'folder']);
            $table->index('id_karyawan');
        });

        Schema::create('mail_list_index', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_karyawan');
            $table->string('folder', 32);
            $table->unsignedBigInteger('uid');
            $table->unsignedInteger('seq_num')->default(0);
            $table->string('from_addr', 500)->default('');
            $table->string('to_addr', 500)->nullable();
            $table->string('subject', 1000)->default('');
            $table->dateTime('email_date')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->boolean('is_seen')->default(false);

            $table->unique(['id_karyawan', 'folder', 'uid']);
            $table->index(['id_karyawan', 'folder', 'email_date']);
            $table->index(['id_karyawan', 'folder', 'is_seen']);
            $table->index(['id_karyawan', 'folder', 'seq_num']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mail_list_index');
        Schema::dropIfExists('mail_folder_meta');

        Schema::create('mail_folder_meta', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan', 255);
            $table->string('folder', 32);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('unread_count')->default(0);
            $table->unsignedBigInteger('uidnext')->default(0);
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->unsignedInteger('min_seq')->default(0);
            $table->unsignedInteger('max_seq')->default(0);
            $table->unsignedInteger('indexed_count')->default(0);
            $table->dateTime('synced_at')->nullable();

            $table->unique(['karyawan', 'folder']);
        });

        Schema::create('mail_list_index', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan', 255);
            $table->string('folder', 32);
            $table->unsignedBigInteger('uid');
            $table->unsignedInteger('seq_num')->default(0);
            $table->string('from_addr', 500)->default('');
            $table->string('to_addr', 500)->nullable();
            $table->string('subject', 1000)->default('');
            $table->dateTime('email_date')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->boolean('is_seen')->default(false);

            $table->unique(['karyawan', 'folder', 'uid']);
            $table->index(['karyawan', 'folder', 'email_date']);
            $table->index(['karyawan', 'folder', 'is_seen']);
            $table->index(['karyawan', 'folder', 'seq_num']);
        });
    }
}
