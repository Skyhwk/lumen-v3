<?php

namespace App\Services;

use App\Models\MasterDriver;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KelengkapanKaryawanSaveService
{
    public function save(MasterKaryawan $karyawan, Request $request, $createdBy = null)
    {
        $createdBy = $createdBy ?: $karyawan->nama_lengkap;
        $now = Carbon::now();
        $married = $this->isMarried($request->input('status_pernikahan'));
        $kontakDarurat = $this->uniqueMaps($this->list($request->input('kontak_darurat')), ['nama_kontak', 'hubungan', 'nomor_kontak']);
        $pendidikan = $this->list($request->input('pendidikan'));
        $sertifikat = array_values(array_filter($this->list($request->input('sertifikat')), function ($row) {
            return trim((string) ($row['nama_sertifikat'] ?? '')) !== '';
        }));
        $sim = $this->list($request->input('sim'));

        DB::transaction(function () use ($karyawan, $request, $createdBy, $now, $married, $kontakDarurat, $pendidikan, $sertifikat, $sim) {
            $this->updateKaryawan($karyawan, $request, $married, $now, $createdBy);
            $this->replaceRows('kontak_darurat_karyawan', $karyawan->id, $createdBy, $now, $kontakDarurat, ['nama_kontak', 'hubungan', 'nomor_kontak']);
            $this->replaceRows('pendidikan_karyawan', $karyawan->id, $createdBy, $now, array_map(function ($row) {
                return [
                    'jenjang' => trim((string) ($row['jenjang'] ?? '')),
                    'institusi' => trim((string) ($row['institusi'] ?? '')),
                    'jurusan' => trim((string) ($row['jurusan'] ?? '')),
                    'tahun_masuk' => trim((string) ($row['tahun_masuk'] ?? '')) ?: null,
                    'tahun_lulus' => trim((string) ($row['tahun_lulus'] ?? '')) ?: null,
                    'kota' => trim((string) ($row['kota'] ?? '')),
                ];
            }, $pendidikan), ['jenjang', 'institusi', 'jurusan', 'tahun_masuk', 'tahun_lulus']);
            $this->syncPendidikanJson($karyawan, $pendidikan);
            $this->replaceRows('sertifikasi_karyawan', $karyawan->id, $createdBy, $now, array_map(function ($row) {
                return [
                    'nama_sertifikat' => trim((string) ($row['nama_sertifikat'] ?? '')),
                    'nomor_sertifikat' => trim((string) ($row['nomor_sertifikat'] ?? '')),
                    'tgl_sertifikat' => trim((string) ($row['tgl_sertifikat'] ?? '')) ?: null,
                    'tgl_exp_sertifikat' => trim((string) ($row['tgl_exp_sertifikat'] ?? '')) ?: null,
                ];
            }, $sertifikat), ['nama_sertifikat', 'nomor_sertifikat', 'tgl_sertifikat']);
            $this->upsertDrivers($karyawan, $sim, $now, $createdBy);
            $this->upsertMedical($karyawan, $request, $now, $createdBy);
            $this->archiveUploads($karyawan, $request, $sim, $pendidikan, $createdBy);
        });

        $fresh = app(KelengkapanKaryawanProfileService::class)->findByEmail($karyawan->email ?: $karyawan->email_pribadi);

        return app(KelengkapanKaryawanProfileService::class)->build($fresh ?: $karyawan->fresh());
    }

    private function updateKaryawan(MasterKaryawan $karyawan, Request $request, $married, Carbon $now, $createdBy)
    {
        $data = [
            'nama_panggilan' => trim((string) $request->input('nama_panggilan')),
            'agama' => trim((string) $request->input('agama')),
            'status_pernikahan' => trim((string) $request->input('status_pernikahan')),
            'tgl_nikah' => $married ? (trim((string) $request->input('tanggal_nikah')) ?: null) : null,
            'tempat_nikah' => $married ? (trim((string) $request->input('tempat_nikah')) ?: null) : null,
            'alamat' => trim((string) $request->input('alamat_domisili')),
            'nik_ktp' => trim((string) $request->input('no_ktp')),
            'updated_by' => $createdBy,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('master_karyawan', 'alamat_ktp')) {
            $data['alamat_ktp'] = trim((string) $request->input('alamat_ktp'));
        }
        if (Schema::hasColumn('master_karyawan', 'nik_kk')) {
            $data['nik_kk'] = trim((string) $request->input('no_kk')) ?: null;
        }
        if (Schema::hasColumn('master_karyawan', 'no_npwp')) {
            $data['no_npwp'] = trim((string) $request->input('no_npwp')) ?: null;
        }

        $karyawan->update($data);
        $this->upsertBpjs($karyawan, 'bpjs_kesehatan', 'no_bpjs', $request->input('no_bpjs'), $now, $createdBy);
        $this->upsertBpjs($karyawan, 'bpjs_tk', 'no_bpjs_tk', $request->input('no_jamsostek'), $now, $createdBy);
    }

    private function upsertBpjs(MasterKaryawan $karyawan, $table, $column, $number, Carbon $now, $createdBy)
    {
        $number = trim((string) $number);
        if ($number === '' || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $existing = DB::table($table)
            ->where('id_karyawan', $karyawan->id)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update([
                $column => $number,
                'updated_by' => $createdBy,
                'updated_at' => $now,
            ]);
            return;
        }

        $row = [
            'id_karyawan' => $karyawan->id,
            'nik_karyawan' => $karyawan->nik_karyawan,
            $column => $number,
            'is_active' => 1,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn($table, 'karyawan')) {
            $row['karyawan'] = $karyawan->nama_lengkap;
        }
        DB::table($table)->insert(array_intersect_key($row, array_flip(Schema::getColumnListing($table))));
    }

    private function syncPendidikanJson(MasterKaryawan $karyawan, array $pendidikan)
    {
        if (!Schema::hasColumn('master_karyawan', 'pendidikan')) {
            return;
        }

        $rows = array_values(array_filter(array_map(function ($row) {
            return [
                'jenjang' => trim((string) ($row['jenjang'] ?? '')),
                'institusi' => trim((string) ($row['institusi'] ?? '')),
                'jurusan' => trim((string) ($row['jurusan'] ?? '')),
                'tahun_masuk' => trim((string) ($row['tahun_masuk'] ?? '')),
                'tahun_lulus' => trim((string) ($row['tahun_lulus'] ?? '')),
                'kota' => trim((string) ($row['kota'] ?? '')),
            ];
        }, $pendidikan), function ($row) {
            return $row['jenjang'] !== '' || $row['institusi'] !== '';
        }));

        $karyawan->update([
            'pendidikan' => json_encode($rows, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function replaceRows($table, $karyawanId, $createdBy, Carbon $now, array $rows, array $uniqueKeys = [])
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $columns = Schema::getColumnListing($table);
        $existing = DB::table($table)
            ->where('karyawan_id', $karyawanId)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();
        $used = [];

        foreach ($rows as $row) {
            $match = $this->matchExistingRow($existing, $row, $uniqueKeys, $used);
            $payload = array_intersect_key(array_merge($row, [
                'karyawan_id' => $karyawanId,
                'is_active' => 1,
                'updated_by' => $createdBy,
                'updated_at' => $now,
                'deleted_by' => null,
                'deleted_at' => null,
            ]), array_flip($columns));

            if ($match) {
                DB::table($table)->where('id', $match->id)->update($payload);
                $used[$match->id] = true;
                continue;
            }

            $insert = array_merge($payload, [
                'created_by' => $createdBy,
                'created_at' => $now,
            ]);
            DB::table($table)->insert(array_intersect_key($insert, array_flip($columns)));
        }

        $leftoverIds = $existing->pluck('id')->filter(function ($id) use ($used) {
            return empty($used[$id]);
        })->all();
        if ($leftoverIds && in_array('is_active', $columns, true)) {
            $deactivate = ['is_active' => 0, 'updated_at' => $now];
            if (in_array('updated_by', $columns, true)) {
                $deactivate['updated_by'] = $createdBy;
            }
            DB::table($table)->whereIn('id', $leftoverIds)->update($deactivate);
        }
    }

    private function matchExistingRow($existing, array $row, array $uniqueKeys, array $used)
    {
        if (!$uniqueKeys) {
            return $existing->first(function ($item) use ($used) {
                return empty($used[$item->id]);
            });
        }

        $wanted = [];
        foreach ($uniqueKeys as $key) {
            $wanted[$key] = strtolower(trim((string) ($row[$key] ?? '')));
        }

        return $existing->first(function ($item) use ($wanted, $used) {
            if (!empty($used[$item->id])) {
                return false;
            }
            foreach ($wanted as $key => $value) {
                if (strtolower(trim((string) ($item->{$key} ?? ''))) !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    private function upsertDrivers(MasterKaryawan $karyawan, array $sim, Carbon $now, $createdBy)
    {
        if (!Schema::hasTable('master_driver')) {
            return;
        }

        $rows = array_values(array_filter($sim, function ($row) {
            return trim((string) ($row['tipe_sim'] ?? '')) !== ''
                || trim((string) ($row['no_sim'] ?? '')) !== ''
                || trim((string) ($row['tgl_expired'] ?? '')) !== '';
        }));
        if (!$rows) {
            return;
        }

        $columns = Schema::getColumnListing('master_driver');
        $hasKaryawanId = in_array('karyawan_id', $columns, true);
        $userId = $karyawan->user_id ?: null;
        if (!$userId && !$hasKaryawanId) {
            return;
        }

        $existingQuery = MasterDriver::query();
        if ($userId && $hasKaryawanId) {
            $existingQuery->where(function ($inner) use ($userId, $karyawan) {
                $inner->where('user_id', $userId)->orWhere('karyawan_id', $karyawan->id);
            });
        } elseif ($userId) {
            $existingQuery->where('user_id', $userId);
        } else {
            $existingQuery->where('karyawan_id', $karyawan->id);
        }
        $existing = $existingQuery->orderByDesc('is_active')->orderBy('id')->get();
        $used = [];

        foreach ($rows as $index => $row) {
            $nomor = trim((string) ($row['no_sim'] ?? ''));
            $tipe = trim((string) ($row['tipe_sim'] ?? ''));
            $match = $existing->first(function ($driver) use ($nomor, $used) {
                return $nomor !== '' && empty($used[$driver->id]) && trim((string) $driver->nomor_sim) === $nomor;
            });
            if (!$match && $tipe !== '') {
                $match = $existing->first(function ($driver) use ($tipe, $used) {
                    return empty($used[$driver->id]) && strcasecmp(trim((string) $driver->tipe_sim), $tipe) === 0;
                });
            }
            if (!$match && count($rows) === 1) {
                $match = $existing->first(function ($driver) use ($used) {
                    return empty($used[$driver->id]);
                });
            }
            if (!$match) {
                $indexed = $existing->values()->get($index);
                $match = $indexed && empty($used[$indexed->id]) ? $indexed : null;
            }

            $payload = [
                'user_id' => $userId,
                'karyawan_id' => $karyawan->id,
                'nama_driver' => $karyawan->nama_lengkap,
                'tipe_sim' => $tipe,
                'nomor_sim' => $nomor,
                'expired' => trim((string) ($row['tgl_expired'] ?? '')) ?: null,
                'is_active' => 1,
                'updated_by' => $createdBy,
                'updated_at' => $now,
                'deleted_at' => null,
                'deleted_by' => null,
            ];
            $payload = array_intersect_key($payload, array_flip($columns));
            if (!$userId) {
                unset($payload['user_id']);
            }

            if ($match) {
                MasterDriver::where('id', $match->id)->update($payload);
                $used[$match->id] = true;
                continue;
            }

            $insert = array_merge($payload, [
                'created_by' => $createdBy,
                'created_at' => $now,
            ]);
            MasterDriver::create(array_intersect_key($insert, array_flip($columns)));
        }
    }

    private function archiveUploads(MasterKaryawan $karyawan, Request $request, array $sim, array $pendidikan, $createdBy)
    {
        $arsip = app(KaryawanArsipDokumenService::class);
        $single = [
            'foto_selfie' => ['jenis' => 'FOTO SELFIE', 'catatan' => 'Selfie terbaru kelengkapan karyawan'],
            'file_ktp' => ['jenis' => 'KTP', 'catatan' => $this->numberNote('No KTP', $request->input('no_ktp'))],
            'file_kk' => ['jenis' => 'KARTU KELUARGA', 'catatan' => $this->numberNote('No KK', $request->input('no_kk'))],
            'file_npwp' => ['jenis' => 'NPWP', 'catatan' => $this->numberNote('No NPWP', $request->input('no_npwp'))],
            'file_bpjs' => ['jenis' => 'BPJS KESEHATAN', 'catatan' => $this->numberNote('No BPJS', $request->input('no_bpjs'))],
            'file_jamsostek' => ['jenis' => 'BPJS KETENAGAKERJAAN', 'catatan' => $this->numberNote('No Jamsostek', $request->input('no_jamsostek'))],
            'file_sertifikasi' => ['jenis' => 'SERTIFIKAT', 'catatan' => trim((string) $request->input('nama_sertifikasi'))],
        ];
        foreach ($single as $input => $meta) {
            $file = $this->uploaded($request, $input);
            $canRemove = in_array($input, ['file_npwp', 'file_bpjs', 'file_jamsostek', 'file_sertifikasi'], true);
            if (!$file && $canRemove && $request->boolean('hapus_' . $input)) {
                $arsip->deactivateLatest($karyawan->id, $meta['jenis'], $createdBy);
                continue;
            }
            $this->archiveFileOrCatatan(
                $arsip,
                $karyawan->id,
                $file,
                $meta['jenis'],
                $createdBy,
                $meta['catatan']
            );
        }

        foreach ($sim as $index => $row) {
            $file = $this->uploaded($request, 'file_sim.' . $index) ?: $this->uploaded($request, 'file_sim.' . $index . '.file');
            if (!$file && is_array($request->file('file_sim'))) {
                $file = $request->file('file_sim')[$index] ?? null;
            }
            $this->archiveFileOrCatatan(
                $arsip,
                $karyawan->id,
                $file instanceof UploadedFile ? $file : null,
                'SIM',
                $createdBy,
                $this->simNote($row),
                $index
            );
        }

        foreach ($pendidikan as $index => $row) {
            $file = $this->uploaded($request, 'file_ijazah.' . $index);
            if (!$file && is_array($request->file('file_ijazah'))) {
                $file = $request->file('file_ijazah')[$index] ?? null;
            }
            if (!($file instanceof UploadedFile)) {
                continue;
            }
            $catatan = trim(implode(' | ', array_filter([
                trim((string) ($row['jenjang'] ?? '')) !== '' ? 'Jenjang: ' . trim((string) $row['jenjang']) : null,
                trim((string) ($row['institusi'] ?? '')) !== '' ? 'Institusi: ' . trim((string) $row['institusi']) : null,
            ]))) ?: 'Ijazah';
            $this->archiveFileOrCatatan(
                $arsip,
                $karyawan->id,
                $file,
                'IJAZAH',
                $createdBy,
                $catatan,
                $index
            );
        }
    }

    private function archiveFileOrCatatan($arsip, $karyawanId, $file, $jenis, $createdBy, $catatan, $index = null)
    {
        $catatan = trim((string) $catatan);

        if ($file instanceof UploadedFile) {
            $arsip->storeUploadedFile(
                $karyawanId,
                $file,
                $jenis,
                $createdBy,
                $catatan !== '' ? $catatan : ($jenis === 'SERTIFIKAT' ? 'Surat sertifikasi' : $catatan),
                'kelengkapan_karyawan'
            );
            return;
        }

        if ($catatan === '') {
            return;
        }

        if ($index === null) {
            $arsip->upsertLatestCatatan($karyawanId, $jenis, $catatan, $createdBy);
            return;
        }

        $arsip->updateCatatanByIndex($karyawanId, $jenis, $index, $catatan);
    }

    private function simNote(array $row)
    {
        return trim(implode(' | ', array_filter([
            trim((string) ($row['tipe_sim'] ?? '')) !== '' ? 'Tipe SIM: ' . trim((string) $row['tipe_sim']) : null,
            trim((string) ($row['no_sim'] ?? '')) !== '' ? 'Nomor: ' . trim((string) $row['no_sim']) : null,
            trim((string) ($row['tgl_expired'] ?? '')) !== '' ? 'Expired: ' . trim((string) $row['tgl_expired']) : null,
        ])));
    }

    private function numberNote($label, $value)
    {
        $value = trim((string) $value);
        return $value !== '' ? $label . ': ' . $value : $label;
    }

    private function uploaded(Request $request, $key)
    {
        $file = $request->file($key);
        return $file instanceof UploadedFile ? $file : null;
    }

    private function upsertMedical(MasterKaryawan $karyawan, Request $request, Carbon $now, $createdBy)
    {
        if (!Schema::hasTable('rekam_medis_karyawan')) {
            return;
        }

        $columns = Schema::getColumnListing('rekam_medis_karyawan');
        $data = [
            'tinggi_badan' => $this->medicalNumber($request->input('tinggi_badan')),
            'berat_badan' => $this->medicalNumber($request->input('berat_badan')),
            'keterangan_mata' => $this->medicalText($request->input('keterangan_mata'), 'Tidak ada'),
            'rate_mata' => $this->medicalText($request->input('rate_mata'), 'Tidak ada'),
            'golongan_darah' => $this->medicalText($request->input('golongan_darah'), 'Tidak tahu'),
            'penyakit_bawaan_lahir' => $this->medicalText($request->input('penyakit_bawaan_lahir'), 'Tidak ada'),
            'penyakit_kronis' => $this->medicalText($request->input('penyakit_kronis'), 'Tidak ada'),
            'riwayat_kecelakaan' => $this->medicalText($request->input('riwayat_kecelakaan'), 'Tidak ada'),
            'updated_at' => $now,
            'updated_by' => $createdBy,
        ];
        $data = array_intersect_key($data, array_flip($columns));

        $existing = DB::table('rekam_medis_karyawan')
            ->where('karyawan_id', $karyawan->id)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            DB::table('rekam_medis_karyawan')->where('id', $existing->id)->update($data);
            return;
        }

        $insert = array_merge($data, [
            'karyawan_id' => $karyawan->id,
            'is_active' => 1,
            'created_at' => $now,
            'created_by' => $createdBy,
        ]);
        DB::table('rekam_medis_karyawan')->insert(array_intersect_key($insert, array_flip($columns)));
    }

    private function medicalNumber($value)
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return $value;
    }

    private function medicalText($value, $fallback)
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }

    private function uniqueMaps(array $rows, array $keys)
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $normalized = [];
            $empty = true;
            foreach ($keys as $key) {
                $normalized[$key] = trim((string) ($row[$key] ?? ''));
                if ($normalized[$key] !== '') {
                    $empty = false;
                }
            }
            if ($empty) {
                continue;
            }
            $fingerprint = strtolower(implode('|', $normalized));
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $out[] = $normalized;
        }

        return $out;
    }

    private function list($value)
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function isMarried($value)
    {
        return (bool) preg_match('/^(menikah|married|kawin)$/i', trim((string) $value));
    }
}
