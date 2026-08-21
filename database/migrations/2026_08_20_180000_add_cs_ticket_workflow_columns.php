<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCsTicketWorkflowColumns extends Migration
{
    protected $connection = 'portal_customer';

    public function up()
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('cs_tickets')) {
            $schema->table('cs_tickets', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('cs_tickets', 'processed_at')) {
                    $table->dateTime('processed_at')->nullable()->after('assigned_to');
                }
                if (!Schema::connection($this->connection)->hasColumn('cs_tickets', 'processed_by')) {
                    $table->unsignedBigInteger('processed_by')->nullable()->index()->after('processed_at');
                }
                if (!Schema::connection($this->connection)->hasColumn('cs_tickets', 'auto_close_at')) {
                    $table->dateTime('auto_close_at')->nullable()->index()->after('closed_at');
                }
                if (!Schema::connection($this->connection)->hasColumn('cs_tickets', 'archived_at')) {
                    $table->dateTime('archived_at')->nullable()->index()->after('auto_close_at');
                }
                if (!Schema::connection($this->connection)->hasColumn('cs_tickets', 'closed_by')) {
                    $table->unsignedBigInteger('closed_by')->nullable()->after('archived_at');
                }
            });
        }

        if ($schema->hasTable('cs_ticket_messages')) {
            $schema->table('cs_ticket_messages', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('cs_ticket_messages', 'message_kind')) {
                    $table->string('message_kind', 20)->default('chat')->after('attachment');
                }
                if (!Schema::connection($this->connection)->hasColumn('cs_ticket_messages', 'is_auto')) {
                    $table->boolean('is_auto')->default(false)->after('message_kind');
                }
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('cs_ticket_messages')) {
            $schema->table('cs_ticket_messages', function (Blueprint $table) {
                if (Schema::connection($this->connection)->hasColumn('cs_ticket_messages', 'is_auto')) {
                    $table->dropColumn('is_auto');
                }
                if (Schema::connection($this->connection)->hasColumn('cs_ticket_messages', 'message_kind')) {
                    $table->dropColumn('message_kind');
                }
            });
        }

        if ($schema->hasTable('cs_tickets')) {
            $schema->table('cs_tickets', function (Blueprint $table) {
                foreach (['closed_by', 'archived_at', 'auto_close_at', 'processed_by', 'processed_at'] as $column) {
                    if (Schema::connection($this->connection)->hasColumn('cs_tickets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
