<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InspectSkK3RefsCommand extends Command
{
    protected $signature = 'ticket:inspect-sk-k3-refs {--install : Create SK K3 tables and menu}';
    protected $description = 'Inspect department, wilayah surat, and ticket_programming schema for SK K3 ticket.';

    public function handle()
    {
        if ($this->option('install')) {
            require_once base_path('database/migrations/2026_11_11_140000_create_ticket_sk_k3_tables.php');
            require_once base_path('database/migrations/2026_11_11_140100_add_ticket_sk_k3_menu.php');
            require_once base_path('database/migrations/2026_11_11_150000_add_sampler_k3_to_jadwal.php');
            (new \CreateTicketSkK3Tables())->up();
            (new \AddTicketSkK3Menu())->up();
            (new \AddSamplerK3ToJadwal())->up();
            $this->grantItProgrammingAccess();
            $this->removeDuplicateSkK3Menu();
            $this->info('Ticket SK K3 tables and menu installed.');
        }

        $this->info('=== master_divisi (sales/k3) ===');
        $divisi = DB::table('master_divisi')
            ->select('id', 'nama_divisi', 'is_active')
            ->where(function ($q) {
                $q->where('nama_divisi', 'like', '%K3%')
                    ->orWhere('nama_divisi', 'like', '%SALES%')
                    ->orWhere('nama_divisi', 'like', '%SURAT%')
                    ->orWhere('nama_divisi', 'like', '%HSE%');
            })
            ->get();
        $this->line($divisi->toJson(JSON_PRETTY_PRINT));

        $this->info('=== tables like wilayah/surat ===');
        $tables = DB::select("SHOW TABLES");
        $key = 'Tables_in_' . DB::getDatabaseName();
        $matches = [];
        foreach ($tables as $row) {
            $name = $row->$key ?? reset((array) $row);
            if (preg_match('/wilayah|surat|region|k3/i', $name)) {
                $matches[] = $name;
            }
        }
        $this->line(json_encode($matches, JSON_PRETTY_PRINT));

        if (Schema::hasTable('ticket_programming')) {
            $this->info('=== ticket_programming columns ===');
            $this->line(json_encode(Schema::getColumnListing('ticket_programming')));
        }

        if (Schema::hasTable('sampling_plan')) {
            $this->info('=== sampling_plan columns ===');
            $this->line(json_encode(Schema::getColumnListing('sampling_plan')));
        }

        if (Schema::hasTable('master_region')) {
            $this->info('=== master_region columns ===');
            $this->line(json_encode(Schema::getColumnListing('master_region')));
            $this->info('=== master_region sample ===');
            $this->line(DB::table('master_region')->where('is_active', 1)->limit(5)->get()->toJson(JSON_PRETTY_PRINT));
        }

        $this->info('=== Request submenu names ===');
        $requestMenu = DB::table('menu')->where('id', 105)->first();
        $subs = json_decode($requestMenu->submenu, true) ?: [];
        foreach ($subs as $sub) {
            $this->line(($sub['nama_inden_menu'] ?? '') . ' | ' . json_encode($sub['sub_menu'] ?? null));
        }

        $this->info('=== karyawan dept sample K3 ===');
        $this->line(DB::table('master_karyawan')->where('id_department', 19)->where('is_active', 1)->limit(3)->get(['id', 'nama_lengkap', 'id_department', 'jabatan'])->toJson());

        $this->info('=== master_wilayah_sampling sample ===');
        $this->line(DB::table('master_wilayah_sampling')->where('is_active', 1)->limit(5)->get()->toJson(JSON_PRETTY_PRINT));

        if (Schema::hasTable('master_wilayah_surat')) {
            $this->info('=== master_wilayah_surat columns ===');
            $this->line(json_encode(Schema::getColumnListing('master_wilayah_surat')));
            $this->info('=== master_wilayah_surat sample ===');
            $this->line(DB::table('master_wilayah_surat')->limit(3)->get()->toJson(JSON_PRETTY_PRINT));
        }
        if (Schema::hasTable('master_wilayah_surat_sampling')) {
            $this->info('=== master_wilayah_surat_sampling columns ===');
            $this->line(json_encode(Schema::getColumnListing('master_wilayah_surat_sampling')));
            $this->info('=== master_wilayah_surat_sampling sample ===');
            $this->line(DB::table('master_wilayah_surat_sampling')->limit(3)->get()->toJson(JSON_PRETTY_PRINT));
        }

        $this->info('=== quotation wilayah sample ===');
        $this->line(DB::table('request_quotation')->where('is_active', 1)->where('flag_status', 'ordered')->whereNotNull('wilayah')->limit(5)->get(['id', 'no_document', 'wilayah'])->toJson());
        $this->info('=== kontrak H wilayah sample ===');
        $this->line(DB::table('request_quotation_kontrak_H')->where('is_active', 1)->where('flag_status', 'ordered')->whereNotNull('wilayah')->limit(5)->get(['id', 'no_document', 'wilayah'])->toJson());
        if (Schema::hasTable('jadwal')) {
            $this->info('=== jadwal columns ===');
            $this->line(json_encode(Schema::getColumnListing('jadwal')));
        }
        if (Schema::hasTable('master_karyawan')) {
            $cols = Schema::getColumnListing('master_karyawan');
            $k3Cols = array_values(array_filter($cols, fn ($c) => preg_match('/k3|certif|sampler/i', $c)));
            $this->info('=== master_karyawan k3/cert/sampler columns ===');
            $this->line(json_encode($k3Cols));
        }

        $this->info('=== mapping sampling-surat ===');
        $this->line(DB::table('master_wilayah_surat_sampling as p')
            ->join('master_wilayah_sampling as s', 's.id', '=', 'p.id_master_wilayah_sampling')
            ->join('master_wilayah_surat as u', 'u.id', '=', 'p.master_wilayah_surat_id')
            ->limit(8)
            ->get(['s.id as sampling_id', 's.wilayah as sampling_wilayah', 'u.id as surat_id', 'u.wilayah_pelanggan'])
            ->toJson(JSON_PRETTY_PRINT));
        if (Schema::hasTable('ticket_sk_k3')) {
            $this->info('=== ticket_sk_k3 columns ===');
            $this->line(json_encode(Schema::getColumnListing('ticket_sk_k3')));
        }
        $aksesCount = DB::table('akses_menu')->where('akses', 'like', '%Ticket SK K3%')->count();
        $this->info('=== akses_menu rows with Ticket SK K3 ===');
        $this->line((string) $aksesCount);

        $this->info('=== IT SUPPORT & PROGRAMMING ===');
        $itDivisi = DB::table('master_divisi')->where('nama_divisi', 'like', '%PROGRAMMING%')->orWhere('nama_divisi', 'like', '%IT SUPPORT%')->get(['id', 'nama_divisi', 'is_active']);
        $this->line($itDivisi->toJson());
        $itUsers = DB::table('master_karyawan')->where('id_department', 7)->where('is_active', 1)->get(['id', 'user_id', 'nama_lengkap']);
        $this->line('IT karyawan: ' . $itUsers->count());
        $itUserIds = $itUsers->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $itAkses = 0;
        if (!empty($itUserIds)) {
            $itAkses = DB::table('akses_menu')
                ->whereIn('user_id', $itUserIds)
                ->where('akses', 'like', '%Ticket SK K3%')
                ->count();
        }
        $this->line('IT akses Ticket SK K3: ' . $itAkses);
        $withAkses = [];
        $withoutAkses = [];
        foreach ($itUsers as $kar) {
            $has = DB::table('akses_menu')->where('user_id', $kar->user_id)->exists();
            if ($has) {
                $withAkses[] = $kar->id . '|' . $kar->nama_lengkap . '|uid:' . $kar->user_id;
            } else {
                $withoutAkses[] = $kar->id . '|' . $kar->nama_lengkap . '|uid:' . $kar->user_id;
            }
        }
        $this->info('=== IT yang punya akses_menu ===');
        $this->line(implode("\n", $withAkses));
        $this->info('=== IT tanpa akses_menu (sample 15) ===');
        $this->line(implode("\n", array_slice($withoutAkses, 0, 15)));

        return 0;
    }

    private function grantItProgrammingAccess()
    {
        $menu = [
            'name' => 'Ticket SK K3',
            'parent' => 'Request',
            'path' => '/request/ticket-sk-k3',
            'access' => ['view', 'create', 'update', 'delete', 'approve', 'reject'],
        ];

        $itUsers = DB::table('master_karyawan')
            ->where('id_department', 7)
            ->where('is_active', 1)
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'nama_lengkap']);

        $granted = 0;
        $already = 0;
        $skipped = 0;

        foreach ($itUsers as $kar) {
            $row = DB::table('akses_menu')
                ->where('user_id', $kar->user_id)
                ->orWhere('user_id', (int) $kar->user_id)
                ->orWhere('user_id', (string) $kar->user_id)
                ->first();

            if (!$row) {
                $skipped++;
                continue;
            }

            $access = json_decode($row->akses ?? '[]', true);
            if (!is_array($access)) {
                $skipped++;
                continue;
            }

            $exists = collect($access)->contains(function ($item) {
                return is_array($item) && ($item['name'] ?? null) === 'Ticket SK K3';
            });
            if ($exists) {
                $already++;
                continue;
            }

            $reference = collect($access)->first(function ($item) {
                return is_array($item) && ($item['name'] ?? null) === 'Ticket Programming';
            });
            $newItem = $reference ?: $menu;
            $newItem['name'] = $menu['name'];
            $newItem['parent'] = $menu['parent'];
            $newItem['path'] = $menu['path'];
            if (!isset($newItem['access']) || !is_array($newItem['access'])) {
                $newItem['access'] = $menu['access'];
            } else {
                foreach (['view', 'create', 'update', 'delete', 'approve', 'reject'] as $flag) {
                    if (!in_array($flag, $newItem['access'], true)) {
                        $newItem['access'][] = $flag;
                    }
                }
            }

            $access[] = $newItem;
            DB::table('akses_menu')->where('id', $row->id)->update([
                'akses' => json_encode(array_values($access), JSON_UNESCAPED_UNICODE),
            ]);
            $granted++;
        }

        $this->info("IT grant: +{$granted} baru, {$already} sudah ada, {$skipped} tanpa akses_menu");
    }

    private function removeDuplicateSkK3Menu()
    {
        DB::table('menu')->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) {
                return;
            }

            $filtered = array_values(array_filter($submenu, function ($item) {
                $name = $item['nama_inden_menu'] ?? $item['name'] ?? '';
                return trim(preg_replace('/\s+/', ' ', $name)) !== 'Ticket SK K3'
                    || $name === 'Ticket SK K3';
            }));

            if (count($filtered) !== count($submenu)) {
                DB::table('menu')->where('id', $row->id)->update([
                    'submenu' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }
}
