<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPinToWaChats extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_chats')) {
        Schema::table('wa_chats', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('unread_count');
            $table->dateTime('pinned_at')->nullable()->after('is_pinned');
            $table->index(['user_id_erp', 'is_pinned', 'pinned_at'], 'wa_chats_user_pin_idx');
        });
        }
    }

    public function down()
    {
        Schema::table('wa_chats', function (Blueprint $table) {
            $table->dropIndex('wa_chats_user_pin_idx');
            $table->dropColumn(['is_pinned', 'pinned_at']);
        });
    }
}
