<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opsi B: pekerjaan + biaya disatukan ke service_mobil; tabel service_mobil_detail dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_mobil')) {
            return;
        }

        if (!Schema::hasColumn('service_mobil', 'hasil_pekerjaan')) {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->text('hasil_pekerjaan')->nullable()->after('catatan_hasil');
            });
        }

        if (!Schema::hasColumn('service_mobil', 'biaya')) {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->json('biaya')->nullable()->after('hasil_pekerjaan');
            });
        }

        if (Schema::hasTable('service_mobil_detail')) {
            $detailRows = DB::table('service_mobil_detail')
                ->where('is_active', true)
                ->orderBy('service_mobil_id')
                ->orderBy('urutan')
                ->orderBy('id')
                ->get();

            $seen = [];
            foreach ($detailRows as $row) {
                $serviceId = (int) $row->service_mobil_id;
                if (isset($seen[$serviceId])) {
                    continue;
                }
                $seen[$serviceId] = true;

                $header = DB::table('service_mobil')->where('id', $serviceId)->first();
                if (!$header) {
                    continue;
                }

                $update = [];
                if (empty($header->hasil_pekerjaan) && !empty($row->deskripsi_pekerjaan)) {
                    $update['hasil_pekerjaan'] = $row->deskripsi_pekerjaan;
                }
                if ($header->biaya === null && $row->biaya !== null && $row->biaya !== '') {
                    $update['biaya'] = is_string($row->biaya)
                        ? $row->biaya
                        : json_encode($row->biaya, JSON_UNESCAPED_UNICODE);
                }

                if ($update !== []) {
                    DB::table('service_mobil')->where('id', $serviceId)->update($update);
                }
            }

            Schema::dropIfExists('service_mobil_detail');
        }

        if (Schema::hasTable('service_mobil_log') && Schema::hasColumn('service_mobil_log', 'catatan')) {
            Schema::table('service_mobil_log', function (Blueprint $table) {
                $table->dropColumn('catatan');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('service_mobil')) {
            return;
        }

        if (!Schema::hasTable('service_mobil_detail')) {
            Schema::create('service_mobil_detail', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_mobil_id');
                $table->text('deskripsi_pekerjaan');
                $table->json('biaya')->nullable();
                $table->unsignedSmallInteger('urutan')->default(1);
                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->index('service_mobil_id');
            });

            $services = DB::table('service_mobil')
                ->whereNotNull('hasil_pekerjaan')
                ->orWhereNotNull('biaya')
                ->get(['id', 'hasil_pekerjaan', 'biaya']);

            foreach ($services as $service) {
                DB::table('service_mobil_detail')->insert([
                    'service_mobil_id' => $service->id,
                    'deskripsi_pekerjaan' => $service->hasil_pekerjaan ?? '',
                    'biaya' => $service->biaya,
                    'urutan' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => true,
                ]);
            }
        }

        if (Schema::hasColumn('service_mobil', 'biaya')) {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->dropColumn('biaya');
            });
        }

        if (Schema::hasColumn('service_mobil', 'hasil_pekerjaan')) {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->dropColumn('hasil_pekerjaan');
            });
        }

        if (Schema::hasTable('service_mobil_log') && !Schema::hasColumn('service_mobil_log', 'catatan')) {
            Schema::table('service_mobil_log', function (Blueprint $table) {
                $table->text('catatan')->nullable()->after('alasan');
            });
        }
    }
};