<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaMessagesDeletedTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_messages_deleted')) {
        Schema::create('wa_messages_deleted', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_message_id')->nullable();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('user_id_erp');
            $table->string('jid', 100);
            $table->string('wa_message_id', 100);
            $table->boolean('from_me')->default(false);
            $table->string('sender_jid', 100)->nullable();
            $table->string('type', 32)->default('text');
            $table->text('content')->nullable();
            $table->string('media_path', 500)->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_filename', 255)->nullable();
            $table->longText('raw_message')->nullable();
            $table->dateTime('message_timestamp')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('delete_reason', 50)->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('deleted_at')->useCurrent();

            $table->index(['user_id_erp', 'jid'], 'wa_msg_deleted_user_jid_idx');
            $table->index(['chat_id', 'wa_message_id'], 'wa_msg_deleted_chat_msg_idx');
            $table->index('deleted_at', 'wa_msg_deleted_at_idx');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('wa_messages_deleted');
    }
}
