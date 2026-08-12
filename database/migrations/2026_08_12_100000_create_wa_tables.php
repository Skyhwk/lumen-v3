<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaTables extends Migration
{
    public function up()
    {
        Schema::create('wa_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id_erp')->unique();
            $table->string('phone_number', 20)->nullable();
            $table->enum('status', ['disconnected', 'qr', 'connecting', 'connected'])->default('disconnected');
            $table->dateTime('last_connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id_erp');
            $table->string('jid', 100);
            $table->string('name', 255)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->boolean('is_group')->default(false);
            $table->text('last_message')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id_erp', 'jid'], 'wa_chats_user_jid_unique');
            $table->index(['user_id_erp', 'last_message_at'], 'wa_chats_user_last_msg_idx');
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->string('wa_message_id', 100);
            $table->boolean('from_me')->default(false);
            $table->string('sender_jid', 100)->nullable();
            $table->enum('type', [
                'text',
                'image',
                'video',
                'document',
                'audio',
                'sticker',
                'location',
                'contact',
            ])->default('text');
            $table->text('content')->nullable();
            $table->string('media_path', 500)->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_filename', 255)->nullable();
            $table->dateTime('timestamp');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['chat_id', 'wa_message_id'], 'wa_messages_chat_msg_unique');
            $table->index(['chat_id', 'timestamp'], 'wa_messages_chat_ts_idx');
            $table->foreign('chat_id')->references('id')->on('wa_chats')->onDelete('cascade');
        });

        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id_erp');
            $table->string('jid', 100);
            $table->string('name', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id_erp', 'jid'], 'wa_contacts_user_jid_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_contacts');
        Schema::dropIfExists('wa_chats');
        Schema::dropIfExists('wa_sessions');
    }
}
