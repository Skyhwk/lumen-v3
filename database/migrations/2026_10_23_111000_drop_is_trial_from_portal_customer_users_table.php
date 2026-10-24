<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropIsTrialFromPortalCustomerUsersTable extends Migration
{
    protected $connection = 'portal_customer';

    public function up()
    {
        $schema = Schema::connection($this->connection);
        if (!$schema->hasTable('users') || !$schema->hasColumn('users', 'is_trial')) {
            return;
        }

        DB::connection($this->connection)
            ->table('users')
            ->where('is_trial', 1)
            ->update(['is_active' => 0]);

        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('is_trial');
        });
    }

    public function down()
    {
        $schema = Schema::connection($this->connection);
        if (!$schema->hasTable('users') || $schema->hasColumn('users', 'is_trial')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->tinyInteger('is_trial')->default(0);
        });
    }
}
