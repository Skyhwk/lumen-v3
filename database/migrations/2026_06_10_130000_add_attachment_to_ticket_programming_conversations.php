<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttachmentToTicketProgrammingConversations extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ticket_programming_conversations') && !Schema::hasColumn('ticket_programming_conversations', 'attachment')) {
            Schema::table('ticket_programming_conversations', function (Blueprint $table) {
                $table->string('attachment', 255)->nullable()->after('message');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('ticket_programming_conversations', 'attachment')) {
            Schema::table('ticket_programming_conversations', function (Blueprint $table) {
                $table->dropColumn('attachment');
            });
        }
    }
}
