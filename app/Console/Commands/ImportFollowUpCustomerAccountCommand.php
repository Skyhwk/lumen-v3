<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFollowUpCustomerAccountCommand extends Command
{
    protected $signature = 'follow-up-portal:import {--take=}';

    protected $description = 'Ambil pelanggan tanpa akun portal ke antrian follow up, bertahap';

    public function handle()
    {
        $portalIds = $this->portalCustomerIds();
        $remaining = $this->remainingQuery($portalIds)->count();
        $queued = DB::table('follow_up_customer_account')->count();
        $thousands = number_format($remaining / 1000, 1, ',', '.');

        $this->info('Pelanggan aktif tanpa akun portal yang belum masuk antrian: ' . number_format($remaining, 0, ',', '.'));
        $this->info('Sekitar ' . $thousands . ' ribu data.');
        $this->info('Sudah ada di antrian: ' . number_format($queued, 0, ',', '.'));

        if ($remaining < 1) {
            $this->warn('Tidak ada data baru yang bisa diambil.');
            return 0;
        }

        $take = $this->option('take');
        if ($take === null || $take === '') {
            $answer = $this->ask('Mau ambil berapa data? Kosong berarti 1000', '1000');
            $take = $answer;
        }

        if (!is_numeric($take) || (int) $take < 1) {
            $this->error('Jumlah ambil harus angka lebih dari 0.');
            return 1;
        }

        $take = (int) $take;
        if ($take > $remaining) {
            $this->warn('Yang diminta ' . number_format($take, 0, ',', '.') . ', sisa hanya ' . number_format($remaining, 0, ',', '.') . '. Diambil sesuai sisa.');
            $take = $remaining;
        }

        $rows = $this->remainingQuery($portalIds)
            ->orderBy('mp.id')
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
        $this->info('Sisa yang belum diambil: ' . number_format(max(0, $remaining - $inserted), 0, ',', '.') . '.');

        return 0;
    }

    private function portalCustomerIds(): array
    {
        $ids = [];
        $users = DB::connection('portal_pelanggan')
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

    private function remainingQuery(array $portalIds)
    {
        $query = DB::table('master_pelanggan as mp')
            ->where('mp.is_active', 1)
            ->whereNotNull('mp.id_pelanggan')
            ->where('mp.id_pelanggan', '!=', '')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('follow_up_customer_account as f')
                    ->whereColumn('f.id_pelanggan', 'mp.id_pelanggan');
            });

        if ($portalIds !== []) {
            $query->whereNotIn('mp.id_pelanggan', $portalIds);
        }

        return $query;
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
}
