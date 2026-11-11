<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketSkK3Tables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ticket_sk_k3')) {
            Schema::create('ticket_sk_k3', function (Blueprint $table) {
                $table->id();
                $table->string('nomor_ticket', 50)->unique();
                $table->string('no_quotation', 100);
                $table->string('tipe_quotation', 30)->nullable();
                $table->unsignedBigInteger('quotation_id')->nullable();
                $table->string('nama_perusahaan', 255)->nullable();
                $table->string('periode', 50)->nullable();
                $table->unsignedBigInteger('sampling_plan_id')->nullable();
                $table->string('no_document_sp', 150)->nullable();
                $table->string('wilayah_surat', 150)->nullable();
                $table->longText('keterangan')->nullable();
                $table->string('dokumentasi', 255)->nullable();
                $table->string('no_document_surat', 80)->nullable();
                $table->string('filename_surat', 255)->nullable();
                $table->string('status', 50)->default('WAITING APPROVE');
                $table->string('request_by', 255)->nullable();
                $table->dateTime('request_time')->nullable();
                $table->string('approved_by', 255)->nullable();
                $table->dateTime('approved_time')->nullable();
                $table->string('process_by', 255)->nullable();
                $table->dateTime('process_time')->nullable();
                $table->string('scheduled_by', 255)->nullable();
                $table->dateTime('scheduled_time')->nullable();
                $table->string('released_by', 255)->nullable();
                $table->dateTime('released_time')->nullable();
                $table->string('rejected_by', 255)->nullable();
                $table->dateTime('rejected_time')->nullable();
                $table->text('rejected_notes')->nullable();
                $table->string('void_by', 255)->nullable();
                $table->dateTime('void_time')->nullable();
                $table->text('void_notes')->nullable();
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->boolean('is_active')->default(1);

                $table->index('status');
                $table->index('no_quotation');
                $table->index('request_by');
                $table->index('sampling_plan_id');
            });
        }

        if (!Schema::hasTable('ticket_sk_k3_conversations')) {
            Schema::create('ticket_sk_k3_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_sk_k3_id');
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->string('sender_name', 255);
                $table->string('sender_role', 50)->default('requester');
                $table->text('message');
                $table->string('attachment', 255)->nullable();
                $table->dateTime('created_at');
                $table->index('ticket_sk_k3_id');
            });
        }

        if (!Schema::hasTable('ticket_sk_k3_conversation_reads')) {
            Schema::create('ticket_sk_k3_conversation_reads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_sk_k3_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('last_read_message_id')->default(0);
                $table->dateTime('updated_at')->nullable();
                $table->unique(['ticket_sk_k3_id', 'user_id'], 'tsk3_conv_read_unique');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('ticket_sk_k3_conversation_reads');
        Schema::dropIfExists('ticket_sk_k3_conversations');
        Schema::dropIfExists('ticket_sk_k3');
    }
}
