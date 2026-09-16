<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRevisionMetaToRequestKebijakan extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        Schema::table('request_kebijakan', function (Blueprint $table) {
            if (!Schema::hasColumn('request_kebijakan', 'revision_meta')) {
                $table->json('revision_meta')->nullable()->after('parent_kebijakan_dokumen_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        Schema::table('request_kebijakan', function (Blueprint $table) {
            if (Schema::hasColumn('request_kebijakan', 'revision_meta')) {
                $table->dropColumn('revision_meta');
            }
        });
    }
}
