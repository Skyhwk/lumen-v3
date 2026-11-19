<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_bank_transfer')) {
            Schema::create('master_bank_transfer', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama_bank', 150);
                $table->string('kode_bank', 32)->nullable();
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->unique('nama_bank', 'uq_master_bank_transfer_nama');
                $table->index(['is_active', 'urutan'], 'idx_master_bank_transfer_active_urutan');
            });
        }

        if (!Schema::hasTable('master_bank_transfer')) {
            return;
        }

        $exists = DB::table('master_bank_transfer')->exists();
        if ($exists) {
            return;
        }

        $path = database_path('data/master_bank_transfer_seed.php');
        if (!is_file($path)) {
            return;
        }

        $banks = require $path;
        if (!is_array($banks)) {
            return;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $rows = [];
        $urutan = 1;
        foreach ($banks as $nama) {
            $nama = trim((string) $nama);
            if ($nama === '') {
                continue;
            }
            $rows[] = [
                'nama_bank' => $nama,
                'kode_bank' => null,
                'urutan' => $urutan++,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('master_bank_transfer')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_bank_transfer');
    }
};