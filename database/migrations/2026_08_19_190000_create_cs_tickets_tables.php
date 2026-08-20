<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCsTicketsTables extends Migration
{
    protected $connection = 'portal_customer';

    public function up()
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable('cs_tickets')) {
            $schema->create('cs_tickets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('ticket_no', 8)->unique();
                $table->string('customer_id', 30)->index();
                $table->string('customer_name', 255);
                $table->unsignedBigInteger('created_by_user_id')->index();
                $table->string('created_by_name', 255);
                $table->string('subject', 255);
                $table->string('category', 255)->nullable();
                $table->enum('status', ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])->default('open');
                $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->dateTime('last_message_at')->nullable()->index();
                $table->string('last_message_preview', 255)->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index(['customer_id', 'status']);
                $table->index(['created_by_user_id', 'customer_id']);
            });
        }

        if (!$schema->hasTable('cs_ticket_messages')) {
            $schema->create('cs_ticket_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ticket_id')->index();
                $table->enum('sender_type', ['customer', 'staff', 'bot']);
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->string('sender_name', 255);
                $table->text('message')->nullable();
                $table->string('attachment', 255)->nullable();
                $table->dateTime('created_at');

                $table->foreign('ticket_id')
                    ->references('id')
                    ->on('cs_tickets')
                    ->onDelete('cascade');
            });
        }

        if (!$schema->hasTable('cs_ticket_reads')) {
            $schema->create('cs_ticket_reads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ticket_id');
                $table->enum('reader_type', ['customer', 'staff']);
                $table->unsignedBigInteger('reader_id');
                $table->unsignedBigInteger('last_read_message_id')->default(0);
                $table->dateTime('updated_at');

                $table->unique(['ticket_id', 'reader_type', 'reader_id'], 'cs_ticket_reads_unique');
                $table->foreign('ticket_id')
                    ->references('id')
                    ->on('cs_tickets')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection($this->connection);

        $schema->dropIfExists('cs_ticket_reads');
        $schema->dropIfExists('cs_ticket_messages');
        $schema->dropIfExists('cs_tickets');
    }
}
