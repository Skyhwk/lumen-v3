<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTimestampMsToWaMessages extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_messages')) {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('timestamp_ms')->nullable()->after('timestamp');
            $table->index(['chat_id', 'timestamp_ms'], 'wa_messages_chat_ts_ms_idx');
        });
        }

        DB::statement('UPDATE wa_messages SET timestamp_ms = UNIX_TIMESTAMP(timestamp) * 1000 WHERE timestamp_ms IS NULL');
    }

    public function down()
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropIndex('wa_messages_chat_ts_ms_idx');
            $table->dropColumn('timestamp_ms');
        });
    }
}
