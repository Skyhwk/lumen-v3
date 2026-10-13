<?php

namespace App\Services;

use App\Models\MasterDriver;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KelengkapanKaryawanProfileService
{
    public function findByEmail($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        return MasterKaryawan::with([
            'medical' => function ($query) {
                $query->where('is_active', true);
            },
            'user',
            'pengalaman_kerja',
            'pendidikan_karyawan' => function ($query) {
                $query->where('is_active', true);
            },
            'sertifikat_karyawan',
            'kontak_darurat' => function ($query) {
                $query->where('is_active', true);
            },
        ])
            ->where('is_active', true)
            ->where(function ($query) use ($email) {
                $query->whereRaw('LOWER(email) = ?', [$email])
                    ->orWhereRaw('LOWER(email_pribadi) = ?', [$email]);
            })
            ->first();
    }

    public function build(MasterKaryawan $karyawan)
    {
        $candidate = $this->latestCandidateProfile($karyawan);
        $documents = $this->documents($karyawan->id, $candidate);
        $alamatDomisili = $this->firstFilled([
            $karyawan->alamat,
            $candidate->alamat_domisili ?? null,
        ]);
        $alamatKtp = $this->firstFilled([
            Schema::hasColumn('master_karyawan', 'alamat_ktp') ? $karyawan->alamat_ktp : null,
            $candidate->alamat_ktp ?? null,
            $alamatDomisili,
        ]);

        $kontakDarurat = $this->kontakDarurat($karyawan, $candidate);
        $pendidikan = $this->pendidikan($karyawan, $candidate);
        $sertifikat = $this->sertifikat($karyawan, $documents['sertifikat']);
        $sim = $this->sim($karyawan, $documents['sim']);
        $pendidikan = $this->attachEducationFiles($pendidikan, $documents['ijazah']);

        $ktpDoc = $this->latestDoc($documents['ktp']);
        $kkDoc = $this->latestDoc($documents['kk']);
        $npwpDoc = $this->latestDoc($documents['npwp']);
        $bpjsDoc = $this->latestDoc($documents['bpjs']);
        $jamsostekDoc = $this->latestDoc($documents['jamsostek']);
        $sertifikasiDoc = $this->latestDoc($documents['sertifikat']);

        $selfie = $this->selfie($documents['selfie']);

        return [
            'karyawan_id' => $karyawan->id,
            'nama_lengkap' => $karyawan->nama_lengkap,
            'email' => $karyawan->email,
            'nik_karyawan' => $karyawan->nik_karyawan,
            'nama_panggilan' => $this->firstFilled([
                $karyawan->nama_panggilan,
                $candidate->nama_panggilan ?? null,
            ]),
            'agama' => $this->agama($this->firstFilled([
                $karyawan->agama,
                $candidate->agama ?? null,
            ])),
            'status_pernikahan' => $this->maritalStatus($this->firstFilled([
                $karyawan->status_pernikahan,
                $candidate->status_pernikahan ?? null,
            ])),
            'tanggal_nikah' => $this->dateOnly($karyawan->tgl_nikah),
            'tempat_nikah' => $karyawan->tempat_nikah,
            'alamat_domisili' => $alamatDomisili,
            'alamat_ktp' => $alamatKtp,
            'alamat_ktp_sama' => $alamatDomisili !== '' && $alamatDomisili === $alamatKtp,
            'no_ktp' => $this->firstFilled([
                $karyawan->nik_ktp,
                $candidate->nik_ktp ?? null,
            ]),
            'no_kk' => $this->firstFilled([
                Schema::hasColumn('master_karyawan', 'nik_kk') ? $karyawan->nik_kk : null,
                $candidate->no_kk ?? null,
            ]),
            'no_npwp' => $this->firstFilled([
                Schema::hasColumn('master_karyawan', 'no_npwp') ? $karyawan->no_npwp : null,
                $candidate->no_npwp ?? null,
                $this->numberFromCatatan($npwpDoc['catatan'] ?? '', 'No NPWP'),
            ]),
            'no_bpjs' => $this->firstFilled([
                $this->bpjsNumber($karyawan->id, 'bpjs_kesehatan', 'no_bpjs'),
                $candidate->no_bpjs_ks ?? null,
                $this->numberFromCatatan($bpjsDoc['catatan'] ?? '', 'No BPJS'),
            ]),
            'no_jamsostek' => $this->firstFilled([
                $this->bpjsNumber($karyawan->id, 'bpjs_tk', 'no_bpjs_tk'),
                $candidate->no_bpjs_tk ?? null,
                $this->numberFromCatatan($jamsostekDoc['catatan'] ?? '', 'No Jamsostek'),
            ]),
            'nama_sertifikasi' => $this->firstFilled([
                $sertifikat[0]['nama_sertifikat'] ?? null,
                $sertifikasiDoc['nama_file'] ?? null,
            ]),
            'medical' => $this->medical($karyawan),
            'foto_selfie_url' => $selfie['url'],
            'kontak_darurat' => $kontakDarurat,
            'pendidikan' => $pendidikan,
            'sertifikat' => $sertifikat,
            'sim' => $sim,
            'files' => [
                'foto_selfie' => $selfie['name'],
                'foto_selfie_url' => $selfie['url'],
                'file_ktp' => $ktpDoc['nama_file'] ?? null,
                'file_ktp_url' => $ktpDoc['url'] ?? null,
                'file_kk' => $kkDoc['nama_file'] ?? null,
                'file_kk_url' => $kkDoc['url'] ?? null,
                'file_npwp' => $npwpDoc['nama_file'] ?? null,
                'file_npwp_url' => $npwpDoc['url'] ?? null,
                'file_bpjs' => $bpjsDoc['nama_file'] ?? null,
                'file_bpjs_url' => $bpjsDoc['url'] ?? null,
                'file_jamsostek' => $jamsostekDoc['nama_file'] ?? null,
                'file_jamsostek_url' => $jamsostekDoc['url'] ?? null,
                'file_sertifikasi' => $sertifikasiDoc['nama_file'] ?? null,
                'file_sertifikasi_url' => $sertifikasiDoc['url'] ?? null,
            ],
        ];
    }

    private function selfie(array $files)
    {
        $latest = $this->latestDoc($files);
        if (!$latest) {
            return ['name' => null, 'url' => null];
        }

        return [
            'name' => $latest['nama_file'] ?? null,
            'url' => $latest['url'] ?? null,
        ];
    }

    private function latestDoc(array $items)
    {
        if (!$items) {
            return null;
        }

        return $items[count($items) - 1];
    }

    private function kontakDarurat(MasterKaryawan $karyawan, $candidate)
    {
        $seen = [];
        $rows = $karyawan->kontak_darurat
            ->filter(function ($row) {
                return (int) ($row->is_active ?? 1) !== 0;
            })
            ->sortBy('id')
            ->values()
            ->map(function ($row) {
                return [
                    'nama_kontak' => trim((string) $row->nama_kontak),
                    'hubungan' => trim((string) $row->hubungan),
                    'nomor_kontak' => trim((string) $row->nomor_kontak),
                ];
            })
            ->filter(function ($row) use (&$seen) {
                if ($row['nama_kontak'] === '' && $row['nomor_kontak'] === '') {
                    return false;
                }
                $fingerprint = strtolower($row['nama_kontak'] . '|' . $row['hubungan'] . '|' . $row['nomor_kontak']);
                if (isset($seen[$fingerprint])) {
                    return false;
                }
                $seen[$fingerprint] = true;
                return true;
            })
            ->values()
            ->all();

        if (!$rows && $candidate) {
            $rows = array_values(array_filter([
                $this->candidateContact($candidate, ''),
                $this->candidateContact($candidate, '_2'),
            ]));
        }

        while (count($rows) < 2) {
            $rows[] = ['nama_kontak' => '', 'hubungan' => '', 'nomor_kontak' => ''];
        }

        return $rows;
    }

    private function candidateContact($candidate, $suffix)
    {
        $nama = trim((string) ($candidate->{'nama_kontak_darurat' . $suffix} ?? ''));
        $hubungan = trim((string) ($candidate->{'hubungan_kontak_darurat' . $suffix} ?? ''));
        $nomor = trim((string) ($candidate->{'no_telepon_darurat' . $suffix} ?? ''));
        if ($nama === '' && $hubungan === '' && $nomor === '') {
            return null;
        }

        return [
            'nama_kontak' => $nama,
            'hubungan' => $hubungan,
            'nomor_kontak' => $nomor,
        ];
    }

    private function pendidikan(MasterKaryawan $karyawan, $candidate)
    {
        $rows = $karyawan->pendidikan_karyawan
            ->filter(function ($row) {
                return (int) ($row->is_active ?? 1) !== 0;
            })
            ->sortBy('id')
            ->values()
            ->map(function ($row) {
                return $this->mapPendidikan($row);
            })
            ->filter(function ($row) {
                return $row['jenjang'] !== '' || $row['institusi'] !== '';
            })
            ->values()
            ->all();

        if ($rows) {
            return $rows;
        }

        $decoded = json_decode($karyawan->pendidikan ?: '[]', true);
        if (is_array($decoded) && $decoded) {
            $rows = array_values(array_filter(array_map(function ($row) {
                return $this->mapPendidikan($row);
            }, $decoded), function ($row) {
                return $row['jenjang'] !== '' || $row['institusi'] !== '';
            }));
            if ($rows) {
                return $rows;
            }
        }

        return $this->candidateEducations($candidate);
    }

    private function candidateEducations($candidate)
    {
        if (!$candidate || !Schema::hasTable('candidate_educations') || (empty($candidate->id) && empty($candidate->new_recruitment_id))) {
            return [];
        }

        $query = DB::table('candidate_educations')->where('is_active', 1);
        $query->where(function ($inner) use ($candidate) {
            if (!empty($candidate->id)) {
                $inner->orWhere('candidate_profile_id', $candidate->id);
            }
            if (!empty($candidate->new_recruitment_id)) {
                $inner->orWhere('new_recruitment_id', $candidate->new_recruitment_id);
            }
        });

        return $query->orderBy('id')->get()->map(function ($row) {
            return $this->mapPendidikan($row);
        })->filter(function ($row) {
            return $row['jenjang'] !== '' || $row['institusi'] !== '';
        })->values()->all();
    }

    private function medical(MasterKaryawan $karyawan)
    {
        $row = $karyawan->medical;
        if (!$row && Schema::hasTable('rekam_medis_karyawan')) {
            $row = DB::table('rekam_medis_karyawan')
                ->where('karyawan_id', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first();
        }

        return [
            'tinggi_badan' => $this->attr($row, ['tinggi_badan']),
            'berat_badan' => $this->attr($row, ['berat_badan']),
            'keterangan_mata' => $this->attr($row, ['keterangan_mata']),
            'rate_mata' => $this->attr($row, ['rate_mata']),
            'golongan_darah' => $this->attr($row, ['golongan_darah']),
            'penyakit_bawaan_lahir' => $this->attr($row, ['penyakit_bawaan_lahir', 'penyakit_lahir']),
            'penyakit_kronis' => $this->attr($row, ['penyakit_kronis']),
            'riwayat_kecelakaan' => $this->attr($row, ['riwayat_kecelakaan']),
        ];
    }

    private function mapPendidikan($row)
    {
        return [
            'jenjang' => $this->attr($row, ['jenjang', 'jenjang_pendidikan']),
            'institusi' => $this->attr($row, ['institusi', 'nama_institusi']),
            'jurusan' => $this->attr($row, ['jurusan']),
            'tahun_masuk' => $this->attr($row, ['tahun_masuk']),
            'tahun_lulus' => $this->attr($row, ['tahun_lulus']),
            'kota' => $this->attr($row, ['kota']),
        ];
    }

    private function attr($row, array $keys)
    {
        foreach ($keys as $key) {
            $value = '';
            if (is_object($row) && isset($row->{$key})) {
                $value = trim((string) $row->{$key});
            } elseif (is_array($row) && array_key_exists($key, $row)) {
                $value = trim((string) $row[$key]);
            }
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function sertifikat(MasterKaryawan $karyawan, array $files)
    {
        $rows = $karyawan->sertifikat_karyawan
            ->filter(function ($row) {
                return (int) ($row->is_active ?? 1) !== 0;
            })
            ->sortBy('id')
            ->values()
            ->map(function ($row) {
                return [
                    'nama_sertifikat' => $row->nama_sertifikat,
                    'nomor_sertifikat' => $row->nomor_sertifikat,
                    'tgl_sertifikat' => $this->dateOnly($row->tgl_sertifikat),
                    'tgl_exp_sertifikat' => $this->dateOnly($row->tgl_exp_sertifikat),
                ];
            })
            ->filter(function ($row) {
                return trim((string) $row['nama_sertifikat']) !== '';
            })
            ->values()
            ->all();

        return $rows;
    }

    private function attachEducationFiles(array $rows, array $files)
    {
        return array_map(function ($row, $index) use ($files) {
            $file = $this->matchDocument($files, $row['institusi'] ?? '', $index) ?: ($files[$index] ?? null);
            $row['file'] = $file['nama_file'] ?? null;
            $row['file_url'] = $file['url'] ?? null;
            return $row;
        }, $rows, array_keys($rows));
    }

    private function sim(MasterKaryawan $karyawan, array $files)
    {
        $drivers = collect();
        $hasDriverId = Schema::hasTable('master_driver') && Schema::hasColumn('master_driver', 'karyawan_id');
        if (Schema::hasTable('master_driver') && ($karyawan->user_id || $hasDriverId)) {
            $drivers = MasterDriver::where('is_active', true)
                ->where(function ($inner) use ($karyawan, $hasDriverId) {
                    if ($karyawan->user_id) {
                        $inner->orWhere('user_id', $karyawan->user_id);
                    }
                    if ($hasDriverId) {
                        $inner->orWhere('karyawan_id', $karyawan->id);
                    }
                })
                ->orderBy('id')
                ->get();
        }

        $driverList = $drivers->values();
        $count = max(count($files), $driverList->count());
        $rows = [];
        for ($index = 0; $index < $count; $index++) {
            $file = $files[$index] ?? null;
            $driver = $driverList->get($index);
            $meta = $this->simMetaFromCatatan($file['catatan'] ?? '');
            $row = [
                'tipe_sim' => $this->firstFilled([$driver->tipe_sim ?? null, $meta['tipe_sim']]),
                'no_sim' => $this->firstFilled([$driver->nomor_sim ?? null, $meta['no_sim']]),
                'tgl_expired' => $this->firstFilled([
                    $driver ? $this->dateOnly($driver->expired) : '',
                    $meta['tgl_expired'],
                ]),
                'file' => $file['nama_file'] ?? null,
                'file_url' => $file['url'] ?? null,
            ];
            if (trim((string) $row['no_sim']) === '' && trim((string) $row['file']) === '' && trim((string) $row['tipe_sim']) === '') {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function matchDocument(array $files, $name, $index)
    {
        $name = strtolower(trim((string) $name));
        if ($name !== '') {
            foreach ($files as $file) {
                $hay = strtolower((string) ($file['nama_file'] ?? ''));
                if ($hay !== '' && (strpos($hay, $name) !== false || strpos($name, $hay) !== false)) {
                    return $file;
                }
            }
        }

        return $files[$index] ?? null;
    }

    private function documents($karyawanId, $candidate)
    {
        $grouped = [
            'selfie' => [],
            'ktp' => [],
            'kk' => [],
            'npwp' => [],
            'bpjs' => [],
            'jamsostek' => [],
            'sim' => [],
            'sertifikat' => [],
            'ijazah' => [],
        ];

        if (Schema::hasTable('karyawan_dokumen_arsip')) {
            $rows = DB::table('karyawan_dokumen_arsip')
                ->where('karyawan_id', $karyawanId)
                ->where('is_active', 1)
                ->orderBy('id')
                ->get();
            $this->appendDocuments($grouped, $rows);
        }

        if ($candidate && Schema::hasTable('candidate_documents') && (!empty($candidate->id) || !empty($candidate->new_recruitment_id))) {
            $filled = [];
            foreach ($grouped as $key => $items) {
                if ($items) {
                    $filled[] = $key;
                }
            }
            $query = DB::table('candidate_documents')->where('is_active', 1)->where(function ($inner) use ($candidate) {
                if (!empty($candidate->id)) {
                    $inner->orWhere('candidate_profile_id', $candidate->id);
                }
                if (!empty($candidate->new_recruitment_id)) {
                    $inner->orWhere('new_recruitment_id', $candidate->new_recruitment_id);
                }
            });
            $this->appendDocuments($grouped, $query->orderBy('id')->get(), $filled);
        }

        return $grouped;
    }

    private function appendDocuments(array &$grouped, $rows, array $skipFilled = [])
    {
        foreach ($rows as $row) {
            $key = $this->documentGroup($row->jenis_dokumen);
            if (!$key) {
                continue;
            }
            if ($skipFilled && in_array($key, $skipFilled, true)) {
                continue;
            }
            $grouped[$key][] = [
                'id' => $row->id,
                'jenis_dokumen' => $row->jenis_dokumen,
                'nama_file' => $row->nama_file,
                'path_file' => $row->path_file,
                'catatan' => $row->catatan ?? null,
                'url' => $this->publicUrl($row->path_file),
            ];
        }
    }

    private function documentGroup($jenis)
    {
        $hay = strtoupper(preg_replace('/[^A-Z0-9]+/', ' ', (string) $jenis));
        if ($this->contains($hay, ['SELFIE', 'FOTO DIRI'])) {
            return 'selfie';
        }
        if ($this->contains($hay, ['KETENAGAKERJAAN', 'JAMSOSTEK', 'BPJS TK'])) {
            return 'jamsostek';
        }
        if ($this->contains($hay, ['BPJS'])) {
            return 'bpjs';
        }
        if ($this->contains($hay, ['KARTU KELUARGA']) || preg_match('/\bKK\b/', $hay)) {
            return 'kk';
        }
        if ($this->contains($hay, ['KTP'])) {
            return 'ktp';
        }
        if ($this->contains($hay, ['NPWP'])) {
            return 'npwp';
        }
        if (preg_match('/\bSIM\b/', $hay)) {
            return 'sim';
        }
        if ($this->contains($hay, ['IJAZAH'])) {
            return 'ijazah';
        }
        if ($this->contains($hay, ['SERTIFIKAT', 'SERTIFIKASI'])) {
            return 'sertifikat';
        }

        return null;
    }

    private function simTypeFromCatatan($catatan)
    {
        return $this->simMetaFromCatatan($catatan)['tipe_sim'];
    }

    private function simMetaFromCatatan($catatan)
    {
        $catatan = (string) $catatan;
        $tipe = '';
        $nomor = '';
        $expired = '';
        if (preg_match('/Tipe SIM:\s*([^|]+)/i', $catatan, $matches)) {
            $tipe = trim($matches[1]);
        }
        if (preg_match('/Nomor:\s*([^|]+)/i', $catatan, $matches)) {
            $nomor = trim($matches[1]);
        }
        if (preg_match('/Expired:\s*([^|]+)/i', $catatan, $matches)) {
            $expired = $this->dateOnly(trim($matches[1]));
        }

        return [
            'tipe_sim' => $tipe,
            'no_sim' => $nomor,
            'tgl_expired' => $expired,
        ];
    }

    private function numberFromCatatan($catatan, $label)
    {
        $catatan = trim((string) $catatan);
        if ($catatan === '') {
            return '';
        }
        if (preg_match('/' . preg_quote($label, '/') . ':\s*([^|]+)/i', $catatan, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/Nomor:\s*([^|]+)/i', $catatan, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function contains($hay, array $needles)
    {
        foreach ($needles as $needle) {
            if (strpos($hay, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function bpjsNumber($karyawanId, $table, $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return '';
        }

        $row = DB::table($table)
            ->where('id_karyawan', $karyawanId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first([$column]);

        return $row->{$column} ?? '';
    }

    private function latestCandidateProfile(MasterKaryawan $karyawan)
    {
        if (!Schema::hasTable('candidate_profiles') || !Schema::hasTable('new_recruitment')) {
            return null;
        }

        $emails = array_values(array_filter([
            strtolower((string) $karyawan->email),
            strtolower((string) $karyawan->email_pribadi),
        ]));
        if (!$emails) {
            return null;
        }

        $recruitmentIds = DB::table('new_recruitment')
            ->where(function ($query) use ($emails) {
                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->orderByDesc('id')
            ->pluck('id');

        if ($recruitmentIds->isEmpty()) {
            return null;
        }

        $profile = DB::table('candidate_profiles')
            ->whereIn('new_recruitment_id', $recruitmentIds)
            ->orderByDesc('id')
            ->first();
        if ($profile) {
            return $profile;
        }

        return (object) ['id' => null, 'new_recruitment_id' => $recruitmentIds->first()];
    }

    private function publicUrl($path)
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = rtrim((string) env('APP_URL_PATH', env('APP_URL', '')), '/');
        if ($base === '') {
            return null;
        }

        if (substr($base, -7) === '/public' && strpos($path, 'public/') === 0) {
            $path = substr($path, 7);
        }

        return $base . '/' . $path;
    }

    private function maritalStatus($value)
    {
        $raw = trim((string) $value);
        $key = strtolower($raw);
        $map = [
            'menikah' => 'Menikah',
            'married' => 'Menikah',
            'kawin' => 'Menikah',
            'belum menikah' => 'Belum Menikah',
            'single' => 'Belum Menikah',
            'cerai hidup' => 'Cerai Hidup',
            'cerai' => 'Cerai Hidup',
            'divorced' => 'Cerai Hidup',
            'cerai mati' => 'Cerai Mati',
            'death divorce' => 'Cerai Mati',
            'death divorced' => 'Cerai Mati',
        ];

        return $map[$key] ?? $raw;
    }

    private function agama($value)
    {
        $raw = trim((string) $value);
        $key = strtolower($raw);
        $map = [
            'islam' => 'Islam',
            'protestan' => 'Kristen Protestan',
            'kristen' => 'Kristen Protestan',
            'kristen protestan' => 'Kristen Protestan',
            'katolik' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha' => 'Buddha',
            'budha' => 'Buddha',
            'konghucu' => 'Konghucu',
            'khonghucu' => 'Konghucu',
        ];

        return $map[$key] ?? $raw;
    }

    private function dateOnly($value)
    {
        $value = trim((string) $value);
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return substr($value, 0, 10);
        }
    }

    private function firstFilled(array $values)
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
