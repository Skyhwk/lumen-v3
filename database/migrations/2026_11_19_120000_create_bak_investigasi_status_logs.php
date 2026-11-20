<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBakInvestigasiStatusLogs extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bak_investigasi_status_logs')) {
            return;
        }

        Schema::create('bak_investigasi_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bak_investigasi_id');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->string('action', 50);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('bak_investigasi_id');
            $table->index(['bak_investigasi_id', 'created_at'], 'bak_inv_status_logs_inv_created_idx');
        });

        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        $rows = DB::table('bak_investigasi')->orderBy('id')->get();
        foreach ($rows as $row) {
            $investigasiId = (int) $row->id;
            $logs = [];

            $createdAt = $row->created_at ?? now();
            $creatorName = trim((string) ($row->created_by ?? ''));
            if ($creatorName === '' && (int) ($row->creator_karyawan_id ?? 0) > 0 && Schema::hasTable('master_karyawan')) {
                $creatorName = trim((string) DB::table('master_karyawan')
                    ->where('id', (int) $row->creator_karyawan_id)
                    ->value('nama_lengkap'));
            }

            $logs[] = [
                'bak_investigasi_id' => $investigasiId,
                'from_status' => null,
                'to_status' => 'draft',
                'action' => 'create',
                'actor_id' => (int) ($row->creator_karyawan_id ?? 0) ?: null,
                'actor_name' => $creatorName !== '' ? $creatorName : null,
                'notes' => 'Investigasi awal dibuat',
                'metadata' => null,
                'created_at' => $createdAt,
            ];

            if (!empty($row->submitted_at)) {
                $logs[] = [
                    'bak_investigasi_id' => $investigasiId,
                    'from_status' => 'draft',
                    'to_status' => 'menunggu_hasil',
                    'action' => 'submit',
                    'actor_id' => (int) ($row->submitted_by_karyawan_id ?? 0) ?: null,
                    'actor_name' => $row->submitted_by ?? $creatorName,
                    'notes' => 'Bagian A–F diajukan; menunggu hasil Legal (G.1)',
                    'metadata' => null,
                    'created_at' => $row->submitted_at,
                ];
            }

            if (!empty($row->legal_approved_at)) {
                $logs[] = [
                    'bak_investigasi_id' => $investigasiId,
                    'from_status' => 'menunggu_hasil',
                    'to_status' => 'issued',
                    'action' => 'approve_legal',
                    'actor_id' => null,
                    'actor_name' => $row->legal_approved_by,
                    'notes' => 'Dokumen F1 diterbitkan setelah persetujuan Legal',
                    'metadata' => null,
                    'created_at' => $row->legal_approved_at,
                ];
            }

            if (Schema::hasTable('bak_investigasi_pengesahan')) {
                $diketahui = DB::table('bak_investigasi_pengesahan')
                    ->where('bak_investigasi_id', $investigasiId)
                    ->where('keterangan', 'diketahui_oleh')
                    ->where('state', 'approved')
                    ->whereNotNull('acted_at')
                    ->orderBy('urutan')
                    ->get();

                foreach ($diketahui as $slot) {
                    $logs[] = [
                        'bak_investigasi_id' => $investigasiId,
                        'from_status' => 'issued',
                        'to_status' => 'issued',
                        'action' => 'approve_diketahui',
                        'actor_id' => (int) ($slot->karyawan_id ?? 0) ?: null,
                        'actor_name' => $slot->actor_name ?? $slot->nama,
                        'notes' => 'Pengesahan Diketahui',
                        'metadata' => null,
                        'created_at' => $slot->acted_at,
                    ];
                }
            }

            if ($logs !== []) {
                DB::table('bak_investigasi_status_logs')->insert($logs);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bak_investigasi_status_logs');
    }
}