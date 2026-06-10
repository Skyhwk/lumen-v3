<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketProgrammingConversationsTables extends Migration
{
    public function up()
    {
        Schema::create('ticket_programming_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_programming_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_name', 255);
            $table->string('sender_role', 50)->default('requester');
            $table->text('message');
            $table->string('attachment', 255)->nullable();
            $table->dateTime('created_at');
            $table->index('ticket_programming_id');
        });

        Schema::create('ticket_programming_conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_programming_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->dateTime('updated_at')->nullable();
            $table->unique(['ticket_programming_id', 'user_id'], 'tp_conv_read_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_programming_conversation_reads');
        Schema::dropIfExists('ticket_programming_conversations');
    }
}
