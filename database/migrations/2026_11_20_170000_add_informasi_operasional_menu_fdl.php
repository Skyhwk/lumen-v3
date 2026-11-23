<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInformasiOperasionalMenuFdl extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('menu_fdl')) {
            return;
        }

        $exists = DB::table('menu_fdl')
            ->whereIn('title', ['Informasi Sampling', 'Informasi Operasional'])
            ->exists();
        if ($exists) {
            return;
        }

        $payload = [
            'title' => 'Informasi Sampling',
            'icon' => 'icons/form.png',
            'is_active' => 1,
            'is_wiseList' => 0,
            'access_restricted' => null,
        ];

        if (Schema::hasColumn('menu_fdl', 'id')) {
            DB::table('menu_fdl')->insert($payload);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('menu_fdl')) {
            return;
        }

        DB::table('menu_fdl')->where('title', 'Informasi Sampling')->delete();
    }
}