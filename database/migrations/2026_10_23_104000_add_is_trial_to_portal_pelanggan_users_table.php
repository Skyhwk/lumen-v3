<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsTrialToPortalPelangganUsersTable extends Migration
{
    protected $connection = 'portal_pelanggan';

    public function up()
    {
        $schema = Schema::connection($this->connection);
        if (!$schema->hasTable('users') || $schema->hasColumn('users', 'is_trial')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->tinyInteger('is_trial')->default(0);
        });
    }

    public function down()
    {
        $schema = Schema::connection($this->connection);
        if (!$schema->hasTable('users') || !$schema->hasColumn('users', 'is_trial')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('is_trial');
        });
    }
}
