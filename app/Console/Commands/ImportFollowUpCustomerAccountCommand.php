<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFollowUpCustomerAccountCommand extends Command
{
    private const PELANGGAN_ID_COLLATE = 'utf8mb4_general_ci';

    protected $signature = 'follow-up-portal:import {--take=} {--skip-count : Lewati hitung sisa (lebih cepat, disarankan jika pakai --take)}';

    protected $description = 'Ambil pelanggan aktif (punya penawaran, belum order) tanpa akun portal ke antrian follow up, bertahap';

    /** @var bool */
    private $portalExclusionTempTable = false;

    public function handle()
    {
        $takeOption = $this->option('take');
        $skipCount = (bool) $this->option('skip-count')
            || ($takeOption !== null && $takeOption !== '');

        $portalIds = $this->portalCustomerIds();
        $this->ensurePortalExclusionTable($portalIds);

        $queued = DB::table('follow_up_customer_account')->count();
        $this->info('Sudah ada di antrian: ' . number_format($queued, 0, ',', '.'));

        $remaining = null;
        if ($skipCount) {
            $this->info('Mode cepat: hitungan sisa dilewati (pakai --skip-count atau --take).');
        } else {
            $this->line('Menghitung sisa yang bisa diimport (bisa beberapa menit)...');
            $remaining = $this->remainingQuery($portalIds)->count();
            $thousands = number_format($remaining / 1000, 1, ',', '.');
            $this->info('Pelanggan aktif (punya penawaran, belum order aktif) tanpa akun portal yang belum masuk antrian: ' . number_format($remaining, 0, ',', '.'));
            $this->info('Sekitar ' . $thousands . ' ribu data.');

            if ($remaining < 1) {
                $this->warn('Tidak ada data baru yang bisa diambil.');
                $this->dropPortalExclusionTable();

                return 0;
            }
        }

        $take = $takeOption;
        if ($take === null || $take === '') {
            $answer = $this->ask('Mau ambil berapa data? Kosong berarti 1000', '1000');
            $take = $answer;
        }

        if (!is_numeric($take) || (int) $take < 1) {
            $this->error('Jumlah ambil harus angka lebih dari 0.');
            $this->dropPortalExclusionTable();

            return 1;
        }

        $take = (int) $take;
        if ($remaining !== null && $take > $remaining) {
            $this->warn('Yang diminta ' . number_format($take, 0, ',', '.') . ', sisa hanya ' . number_format($remaining, 0, ',', '.') . '. Diambil sesuai sisa.');
            $take = $remaining;
        }

        $this->line('Mengambil ' . number_format($take, 0, ',', '.') . ' data...');
        $rows = $this->rowsQuery($portalIds)
            ->limit($take)
            ->get([
                'mp.id',
                'mp.id_pelanggan',
                'mp.nama_pelanggan',
                'mp.wilayah',
                'mp.npwp',
            ]);

        $ids = $rows->pluck('id')->all();
        $contacts = $this->firstByPelanggan('kontak_pelanggan', $ids, ['no_tlp_perusahaan', 'email_perusahaan']);
        $pics = $this->firstByPelanggan('pic_pelanggan', $ids, ['nama_pic', 'jabatan_pic', 'no_tlp_pic', 'email_pic']);
        $addresses = $this->firstByPelanggan('alamat_pelanggan', $ids, ['alamat']);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $inserted = 0;

        foreach ($rows->chunk(200) as $chunk) {
            $payload = [];
            foreach ($chunk as $row) {
                $contact = $contacts[$row->id] ?? null;
                $pic = $pics[$row->id] ?? null;
                $address = $addresses[$row->id] ?? null;
                $payload[] = [
                    'master_pelanggan_id' => $row->id,
                    'id_pelanggan' => $row->id_pelanggan,
                    'nama_pelanggan' => $row->nama_pelanggan,
                    'wilayah' => $row->wilayah,
                    'npwp' => $row->npwp,
                    'no_tlp' => $contact->no_tlp_perusahaan ?? null,
                    'email' => $contact->email_perusahaan ?? null,
                    'alamat' => $address->alamat ?? null,
                    'pic_nama' => $pic->nama_pic ?? null,
                    'pic_jabatan' => $pic->jabatan_pic ?? null,
                    'pic_telepon' => $pic->no_tlp_pic ?? null,
                    'pic_email' => $pic->email_pic ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $inserted += DB::table('follow_up_customer_account')->insertOrIgnore($payload);
        }

        $this->info('Masuk antrian: ' . number_format($inserted, 0, ',', '.') . ' data.');
        if ($remaining !== null) {
            $this->info('Sisa yang belum diambil: ' . number_format(max(0, $remaining - $inserted), 0, ',', '.') . '.');
        } elseif ($inserted < 1) {
            $this->warn('Tidak ada baris baru yang masuk (antrian kosong atau semua sudah ter-import).');
        }

        $this->dropPortalExclusionTable();

        return 0;
    }

    private function portalCustomerIds(): array
    {
        $ids = [];
        $users = DB::connection('portal_customer')
            ->table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('id_pelanggan')
            ->pluck('id_pelanggan');

        foreach ($users as $raw) {
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $idPelanggan) {
                $idPelanggan = trim((string) $idPelanggan);
                if ($idPelanggan !== '') {
                    $ids[$idPelanggan] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private function eligibleQuotationPelangganSubquery()
    {
        $pelangganSelect = $this->pelangganIdSelectSql('pelanggan_ID', 'pelanggan_ID');

        $nonKontrak = DB::table('request_quotation')
            ->selectRaw($pelangganSelect)
            ->where('is_active', 1)
            ->whereNotNull('pelanggan_ID')
            ->where('pelanggan_ID', '!=', '')
            ->distinct();

        $kontrak = DB::table('request_quotation_kontrak_H')
            ->selectRaw($pelangganSelect)
            ->where('is_active', 1)
            ->whereNotNull('pelanggan_ID')
            ->where('pelanggan_ID', '!=', '')
            ->distinct();

        return $nonKontrak->union($kontrak);
    }

    private function quotationCountSubquery()
    {
        $pelangganSelect = $this->pelangganIdSelectSql('pelanggan_ID', 'pelanggan_ID');

        $docs = DB::table('request_quotation')
            ->selectRaw($pelangganSelect . ', no_document')
            ->where('is_active', 1)
            ->whereNotNull('pelanggan_ID')
            ->where('pelanggan_ID', '!=', '');

        $docsKontrak = DB::table('request_quotation_kontrak_H')
            ->selectRaw($pelangganSelect . ', no_document')
            ->where('is_active', 1)
            ->whereNotNull('pelanggan_ID')
            ->where('pelanggan_ID', '!=', '');

        $union = $docs->unionAll($docsKontrak);

        return DB::query()
            ->fromSub($union, 'qt_docs')
            ->select('pelanggan_ID', DB::raw('COUNT(DISTINCT no_document) AS qt_count'))
            ->groupBy('pelanggan_ID');
    }

    private function remainingQuery(array $portalIds)
    {
        return $this->applyEligibleCustomerFilters(
            DB::table('master_pelanggan as mp')
                ->joinSub($this->eligibleQuotationPelangganSubquery(), 'qt_eligible', function ($join) {
                    $this->joinOnPelangganId($join, 'qt_eligible.pelanggan_ID');
                }),
            $portalIds
        );
    }

    private function rowsQuery(array $portalIds)
    {
        return $this->applyEligibleCustomerFilters(
            DB::table('master_pelanggan as mp')
                ->joinSub($this->eligibleQuotationPelangganSubquery(), 'qt_eligible', function ($join) {
                    $this->joinOnPelangganId($join, 'qt_eligible.pelanggan_ID');
                })
                ->leftJoinSub($this->quotationCountSubquery(), 'qt_count', function ($join) {
                    $this->joinOnPelangganId($join, 'qt_count.pelanggan_ID');
                })
                ->orderByDesc('qt_count.qt_count')
                ->orderBy('mp.id'),
            $portalIds
        );
    }

    private function applyEligibleCustomerFilters($query, array $portalIds)
    {
        $query = $query
            ->where('mp.is_active', 1)
            ->whereNotNull('mp.id_pelanggan')
            ->where('mp.id_pelanggan', '!=', '')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('follow_up_customer_account as f')
                    ->whereRaw($this->pelangganIdEqualsSql('f.id_pelanggan', 'mp.id_pelanggan'));
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('order_header as oh')
                    ->whereRaw($this->pelangganIdEqualsSql('oh.id_pelanggan', 'mp.id_pelanggan'))
                    ->where('oh.is_active', 1);
            });

        return $this->applyPortalExclusion($query, $portalIds);
    }

    private function applyPortalExclusion($query, array $portalIds)
    {
        if ($portalIds === []) {
            return $query;
        }

        if ($this->portalExclusionUsesTempTable()) {
            return $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('tmp_follow_up_portal_ids as p')
                    ->whereRaw($this->pelangganIdEqualsSql('p.id_pelanggan', 'mp.id_pelanggan'));
            });
        }

        return $query->whereNotIn('mp.id_pelanggan', $portalIds);
    }

    private function portalExclusionUsesTempTable(): bool
    {
        return $this->portalExclusionTempTable;
    }

    private function ensurePortalExclusionTable(array $portalIds): void
    {
        if (count($portalIds) < 800) {
            return;
        }

        $collate = self::PELANGGAN_ID_COLLATE;
        DB::statement(
            "CREATE TEMPORARY TABLE tmp_follow_up_portal_ids (
                id_pelanggan VARCHAR(50) CHARACTER SET utf8mb4 COLLATE {$collate} NOT NULL PRIMARY KEY
            )"
        );
        $this->portalExclusionTempTable = true;

        foreach (array_chunk($portalIds, 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $id) {
                $rows[] = ['id_pelanggan' => $id];
            }
            DB::table('tmp_follow_up_portal_ids')->insert($rows);
        }
    }

    private function dropPortalExclusionTable(): void
    {
        if (!$this->portalExclusionTempTable) {
            return;
        }

        DB::statement('DROP TEMPORARY TABLE IF EXISTS tmp_follow_up_portal_ids');
        $this->portalExclusionTempTable = false;
    }

    private function firstByPelanggan(string $table, array $pelangganIds, array $columns): array
    {
        if ($pelangganIds === []) {
            return [];
        }

        $select = array_merge(['pelanggan_id'], $columns);
        $grouped = [];
        foreach (array_chunk($pelangganIds, 500) as $chunk) {
            $rows = DB::table($table)
                ->where('is_active', 1)
                ->whereIn('pelanggan_id', $chunk)
                ->orderBy('id')
                ->get($select);
            foreach ($rows as $row) {
                if (!isset($grouped[$row->pelanggan_id])) {
                    $grouped[$row->pelanggan_id] = $row;
                }
            }
        }

        return $grouped;
    }

    private function pelangganIdSelectSql(string $column, string $alias): string
    {
        $collate = self::PELANGGAN_ID_COLLATE;

        return "{$column} COLLATE {$collate} AS {$alias}";
    }

    private function pelangganIdEqualsSql(string $leftQualified, string $rightQualified): string
    {
        $collate = self::PELANGGAN_ID_COLLATE;

        return "{$leftQualified} COLLATE {$collate} = {$rightQualified}";
    }

    private function joinOnPelangganId($join, string $leftQualified): void
    {
        $join->whereRaw($this->pelangganIdEqualsSql($leftQualified, 'mp.id_pelanggan'));
    }
}
