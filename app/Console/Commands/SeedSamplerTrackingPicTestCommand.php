<?php

namespace App\Console\Commands;

use App\Models\MasterKaryawan;
use App\Models\PersiapanSampelHeader;
use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingSession;
use App\Services\SamplerTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedSamplerTrackingPicTestCommand extends Command
{
    protected $signature = 'sampler-tracking:seed-pic-test {--user=601} {--clear-pic}';
    protected $description = 'Seed today jadwal + tracking session for PIC flow testing';

    public function handle(SamplerTrackingService $service)
    {
        $userId = (int) $this->option('user');
        $karyawan = MasterKaryawan::where('id', $userId)->first();
        if (!$karyawan) {
            $this->error("Karyawan #{$userId} tidak ditemukan.");
            return 1;
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $now = Carbon::now('Asia/Jakarta')->toDateTimeString();

        if ($this->option('clear-pic')) {
            $sessionIds = SamplerTrackingSession::where('is_active', true)
                ->whereDate('tanggal_sampling', $today)
                ->whereHas('activeMembers', function ($query) use ($userId) {
                    $query->where('sampler_id', $userId);
                })
                ->pluck('id');

            $deleted = $sessionIds->isEmpty()
                ? 0
                : SamplerTrackingEvent::whereIn('sampler_tracking_session_id', $sessionIds)
                    ->where('event_type', 'meet_pic')
                    ->delete();

            $this->line(json_encode([
                'cleared_meet_pic' => $deleted,
                'session_ids' => $sessionIds->values()->all(),
                'user' => ['id' => $userId, 'name' => $karyawan->nama_lengkap],
                'tanggal' => $today,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return 0;
        }
        $qt = 'ISL/QT/26-XI/TEST601';
        $orderNo = 'TEST601-' . Carbon::now('Asia/Jakarta')->format('md');
        $sample = 'A' . Carbon::now('Asia/Jakarta')->format('dmy') . 'T01';
        $company = 'PT Inti Surya [TEST 601]';
        $name = $karyawan->nama_lengkap;
        $cabang = $karyawan->id_cabang ?: 1;

        $cleared = DB::table('sampler_tracking_troubles')
            ->where('sampler_id', $userId)
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->where('is_clear', 0)->orWhereNull('is_clear');
            })
            ->update([
                'is_active' => 0,
                'updated_at' => $now,
            ]);

        $templateJadwal = DB::table('jadwal')
            ->where('is_active', 1)
            ->whereNotNull('no_quotation')
            ->whereNotNull('jam_mulai')
            ->whereNotNull('kendaraan')
            ->orderByDesc('id')
            ->first();
        $templateOrder = DB::table('order_header')
            ->where('is_active', 1)
            ->whereNotNull('no_order')
            ->orderByDesc('id')
            ->first();
        $templateDetail = DB::table('order_detail')
            ->where('is_active', 1)
            ->where('no_sampel', 'like', 'A%')
            ->orderByDesc('id')
            ->first();
        $templatePlan = $templateJadwal && $templateJadwal->id_sampling
            ? DB::table('sampling_plan')->where('id', $templateJadwal->id_sampling)->first()
            : DB::table('sampling_plan')->orderByDesc('id')->first();

        $planId = $this->upsert('sampling_plan', ['no_quotation' => $qt], [
            'no_quotation' => $qt,
            'status' => $templatePlan->status ?? 1,
            'is_active' => 1,
            'google_maps_url' => $templatePlan->google_maps_url ?? 'https://maps.google.com/?q=-6.2,106.8',
            'created_by' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsert('order_header', ['no_document' => $qt], [
            'no_document' => $qt,
            'no_order' => $orderNo,
            'nama_perusahaan' => $company,
            'id_pelanggan' => $templateOrder->id_pelanggan ?? 999601,
            'id_cabang' => $cabang,
            'alamat_sampling' => $templateOrder->alamat_sampling ?? 'Jl. Test PIC Tracking No. 601',
            'is_active' => 1,
            'created_by' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $headerId = DB::table('order_header')->where('no_document', $qt)->value('id');
        $this->upsert('order_detail', ['no_sampel' => $sample], array_merge(
            $templateDetail ? (array) $templateDetail : [],
            [
                'id' => null,
                'id_order_header' => $headerId,
                'no_order' => $orderNo,
                'no_quotation' => $qt,
                'no_sampel' => $sample,
                'tanggal_sampling' => $today,
                'kategori' => $templateDetail->kategori ?? '1-Air',
                'kategori_2' => $templateDetail->kategori_2 ?? 'Air',
                'kategori_3' => $templateDetail->kategori_3 ?? 'Air Limbah',
                'status' => 0,
                'is_active' => 1,
                'created_by' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ));

        $this->upsert('jadwal', [
            'no_quotation' => $qt,
            'tanggal' => $today,
            'userid' => $userId,
        ], [
            'id_sampling' => $planId,
            'no_quotation' => $qt,
            'tanggal' => $today,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '12:00:00',
            'kendaraan' => $templateJadwal->kendaraan ?? 'B 1234 TES',
            'id_cabang' => $cabang,
            'durasi' => 0,
            'durasi_personal' => 0,
            'driver' => $templateJadwal->driver ?? $name,
            'nama_perusahaan' => $company,
            'alamat' => $templateOrder->alamat_sampling ?? 'Jl. Test PIC Tracking No. 601',
            'kategori' => '["Air - 001"]',
            'userid' => $userId,
            'sampler' => $name,
            'flag' => 0,
            'is_active' => 1,
            'created_by' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsert('persiapan_sampel_header', [
            'no_quotation' => $qt,
            'tanggal_sampling' => $today,
        ], [
            'no_order' => $orderNo,
            'no_quotation' => $qt,
            'tanggal_sampling' => $today,
            'nama_perusahaan' => $company,
            'no_sampel' => json_encode([$sample]),
            'sampler_jadwal' => json_encode([$name]),
            'is_emailed_bas' => 1,
            'is_active' => 1,
            'created_by' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $header = PersiapanSampelHeader::where('no_quotation', $qt)
            ->whereDate('tanggal_sampling', $today)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->firstOrFail();

        $sessions = $service->syncByPersiapanHeader($header);
        $listed = $service->listByDate($today, $userId, $name);

        $this->line(json_encode([
            'cleared_troubles' => $cleared,
            'user' => ['id' => $userId, 'name' => $name],
            'tanggal' => $today,
            'no_quotation' => $qt,
            'no_order' => $orderNo,
            'no_sampel' => $sample,
            'synced_sessions' => $sessions->pluck('id')->values()->all(),
            'visible_today' => $listed->map(function ($session) {
                $member = $session->activeMembers->first();
                return [
                    'session_id' => $session->id,
                    'company' => $session->nama_perusahaan,
                    'member_id' => $member->id ?? null,
                    'sampler' => $member->sampler_name ?? null,
                    'has_meet_pic' => (bool) $session->has_meet_pic,
                    'events' => $member ? $member->events->pluck('event_type')->all() : [],
                ];
            })->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    protected function upsert($table, array $match, array $values)
    {
        $filtered = $this->existing($table, $values);
        $query = DB::table($table);
        foreach ($match as $column => $value) {
            $query->where($column, $value);
        }
        $existing = $query->orderByDesc('id')->first();
        if ($existing) {
            unset($filtered['id'], $filtered['created_at'], $filtered['created_by']);
            DB::table($table)->where('id', $existing->id)->update($filtered);
            return $existing->id;
        }

        unset($filtered['id']);
        return DB::table($table)->insertGetId($filtered);
    }

    protected function existing($table, array $values)
    {
        $columns = Schema::getColumnListing($table);
        $filtered = [];
        foreach ($values as $key => $value) {
            if ($key === 'id' || $value === null) {
                continue;
            }
            if (in_array($key, $columns, true)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
