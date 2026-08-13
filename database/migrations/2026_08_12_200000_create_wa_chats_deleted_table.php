<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaChatsDeletedTable extends Migration
{
    public function up()
    {
        Schema::create('wa_chats_deleted', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_chat_id')->nullable();
            $table->unsignedBigInteger('user_id_erp');
            $table->string('jid', 100);
            $table->string('name', 255)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->boolean('is_group')->default(false);
            $table->text('last_message')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->dateTime('pinned_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->string('delete_reason', 50)->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('deleted_at')->useCurrent();

            $table->index(['user_id_erp', 'jid'], 'wa_chats_deleted_user_jid_idx');
            $table->index('deleted_at', 'wa_chats_deleted_at_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wa_chats_deleted');
    }
}
