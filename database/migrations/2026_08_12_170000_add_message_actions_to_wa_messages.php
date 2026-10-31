<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMessageActionsToWaMessages extends Migration
{
    private const TABLE = 'wa_messages';

    public function up()
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->addColumnIfMissing('reply_to_wa_message_id', function (Blueprint $table) {
            $table->string('reply_to_wa_message_id', 100)->nullable()->after('content');
        });

        $this->addColumnIfMissing('quoted_text', function (Blueprint $table) {
            $table->text('quoted_text')->nullable()->after('reply_to_wa_message_id');
        });

        $this->addColumnIfMissing('quoted_sender_jid', function (Blueprint $table) {
            $table->string('quoted_sender_jid', 100)->nullable()->after('quoted_text');
        });

        $this->addColumnIfMissing('quoted_sender_name', function (Blueprint $table) {
            $table->string('quoted_sender_name', 255)->nullable()->after('quoted_sender_jid');
        });

        $this->addColumnIfMissing('mentions', function (Blueprint $table) {
            $table->json('mentions')->nullable()->after('quoted_sender_name');
        });

        $this->addColumnIfMissing('is_forwarded', function (Blueprint $table) {
            $table->boolean('is_forwarded')->default(false)->after('mentions');
        });

        $this->addColumnIfMissing('is_edited', function (Blueprint $table) {
            $table->boolean('is_edited')->default(false)->after('is_forwarded');
        });

        $this->addColumnIfMissing('edited_at', function (Blueprint $table) {
            $table->dateTime('edited_at')->nullable()->after('is_edited');
        });
    }

    public function down()
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = [
            'reply_to_wa_message_id',
            'quoted_text',
            'quoted_sender_jid',
            'quoted_sender_name',
            'mentions',
            'is_forwarded',
            'is_edited',
            'edited_at',
        ];

        $existing = array_values(array_filter($columns, function ($column) {
            return Schema::hasColumn(self::TABLE, $column);
        }));

        if ($existing === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if (Schema::hasColumn(self::TABLE, $column)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }
}
