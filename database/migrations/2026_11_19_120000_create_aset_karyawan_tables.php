<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_aset_karyawan')) {
            Schema::create('master_aset_karyawan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama', 150);

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('is_active', 'master_aset_karyawan_active_idx');
            });
        }

        if (!Schema::hasTable('aset_karyawan')) {
            Schema::create('aset_karyawan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('master_karyawan_id');
                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->unsignedBigInteger('master_aset_karyawan_id');
                $table->unsignedBigInteger('rfid_card_id')->nullable();
                $table->unsignedInteger('qty')->default(1);
                // Teks satuan saja (tanpa jumlah), contoh: "Pcs", "Pasang"
                $table->string('satuan', 100)->nullable();

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('master_karyawan_id', 'aset_karyawan_karyawan_idx');
                $table->index('new_recruitment_id', 'aset_karyawan_recruitment_idx');
                $table->index('master_aset_karyawan_id', 'aset_karyawan_master_aset_idx');
                $table->index('rfid_card_id', 'aset_karyawan_rfid_idx');
                $table->index(
                    ['master_karyawan_id', 'is_active'],
                    'aset_karyawan_karyawan_active_idx'
                );
            });
        }

        $this->seedMasterAset();
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_karyawan');
        Schema::dropIfExists('master_aset_karyawan');
    }

    private function seedMasterAset(): void
    {
        if (!Schema::hasTable('master_aset_karyawan')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $names = [
            'Laptop',
            'Mouse',
            'Keyboard',
            'Seragam',
            'Sepatu Safety',
            'Tas Kerja',
            'Handphone Dinas',
        ];

        foreach ($names as $nama) {
            $exists = DB::table('master_aset_karyawan')
                ->where('nama', $nama)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('master_aset_karyawan')->insert([
                'nama' => $nama,
                'created_at' => $now,
                'created_by' => 'system',
                'is_active' => 1,
            ]);
        }
    }
};
