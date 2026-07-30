<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'void_note')) {
                $table->text('void_note')->nullable()->after('deleted_at');
            }
            if (!Schema::hasColumn('purchase_requests', 'void_source')) {
                $table->string('void_source', 20)->nullable()->after('void_note');
            }
        });

        DB::table('purchase_requests')
            ->whereNotNull('deleted_at')
            ->whereNull('void_source')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $source = ($row->deleted_by && $row->deleted_by !== $row->created_by)
                        ? 'finance'
                        : 'requester';

                    DB::table('purchase_requests')
                        ->where('id', $row->id)
                        ->update(['void_source' => $source]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'void_source')) {
                $table->dropColumn('void_source');
            }
            if (Schema::hasColumn('purchase_requests', 'void_note')) {
                $table->dropColumn('void_note');
            }
        });
    }
};
