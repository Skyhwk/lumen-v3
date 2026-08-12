<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMessageActionsToWaMessages extends Migration
{
    public function up()
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('reply_to_wa_message_id', 100)->nullable()->after('content');
            $table->text('quoted_text')->nullable()->after('reply_to_wa_message_id');
            $table->string('quoted_sender_jid', 100)->nullable()->after('quoted_text');
            $table->string('quoted_sender_name', 255)->nullable()->after('quoted_sender_jid');
            $table->json('mentions')->nullable()->after('quoted_sender_name');
            $table->boolean('is_forwarded')->default(false)->after('mentions');
            $table->boolean('is_edited')->default(false)->after('is_forwarded');
            $table->dateTime('edited_at')->nullable()->after('is_edited');
        });
    }

    public function down()
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn([
                'reply_to_wa_message_id',
                'quoted_text',
                'quoted_sender_jid',
                'quoted_sender_name',
                'mentions',
                'is_forwarded',
                'is_edited',
                'edited_at',
            ]);
        });
    }
}
