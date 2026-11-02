<?php

namespace App\Services;

use App\Helpers\ShioElemenHelper;
use App\Models\MasterKaryawan;

class ProfileEmployeeDetailService
{
    public function build(int $userId): ?array
    {
        $row = MasterKaryawan::with([
            'medical',
            'user',
            'jabatan',
            'divisi',
            'cabang',
            'pengalaman_kerja',
            'pendidikan_karyawan' => function ($query) {
                $query->where('is_active', true);
            },
            'sertifikat_karyawan',
            'kontak_darurat' => function ($query) {
                $query->where('is_active', true);
            },
        ])
            ->where('id', $userId)
            ->where('is_active', 1)
            ->first();

        if (!$row) {
            return null;
        }

        $shioElemen = ShioElemenHelper::resolve($row->tanggal_lahir, $row->shio, $row->elemen);
        $medical    = $row->medical;

        return [
            'id'                => $row->id,
            'nama_lengkap'      => $row->nama_lengkap,
            'nik_karyawan'      => $row->nik_karyawan,
            'department'        => optional($row->divisi)->nama_divisi ?? $row->department,
            'jabatan'           => optional($row->jabatan)->nama_jabatan ?? $row->jabatan,
            'cabang'            => optional($row->cabang)->nama_cabang,
            'image'             => $row->image,
            'jenis_kelamin'     => $row->jenis_kelamin,
            'email'             => $row->email,
            'email_pribadi'     => $row->email_pribadi,
            'tempat_lahir'      => $row->tempat_lahir,
            'tanggal_lahir'     => $row->tanggal_lahir,
            'no_telpon'             => $row->no_telpon,
            'no_telpon_perusahaan'  => $row->no_telpon_perusahaan ?? null,
            'alamat'            => $row->alamat,
            'status_karyawan'   => $row->status_karyawan,
            'status_pernikahan' => $row->status_pernikahan,
            'shio'              => $shioElemen['shio'],
            'elemen'            => $shioElemen['elemen'],
            'tgl_mulai_kerja'   => $row->tgl_mulai_kerja,
            'pendidikan'        => $this->resolvePendidikan($row),
            'skill'             => $row->skill,
            'skill_bahasa'      => $row->skill_bahasa,
            'organisasi'        => $row->organisasi,
            'sertifikat'        => $row->sertifikat,
            'pengalaman_kerja'  => $row->pengalaman_kerja,
            'sertifikat_karyawan' => $row->sertifikat_karyawan,
            'kontak_darurat'    => $row->kontak_darurat
                ->unique(function ($item) {
                    return strtolower(trim((string) $item->nama_kontak) . '|' . trim((string) $item->hubungan) . '|' . trim((string) $item->nomor_kontak));
                })
                ->values(),
            'personal'          => [
                'id'             => $row->id,
                'nama_lengkap'   => $row->nama_lengkap,
                'birth_place'    => $row->tempat_lahir,
                'shio'           => $shioElemen['shio'],
                'gender'         => $row->jenis_kelamin,
                'marital_status' => $row->status_pernikahan,
                'marital_date'   => $row->tgl_nikah,
                'marital_place'  => $row->tempat_nikah,
                'nik_ktp'        => $row->nik_ktp,
                'date_birth'     => $row->tanggal_lahir,
                'elemen'         => $shioElemen['elemen'],
                'nationality'    => $row->kebangsaan,
                'religion'       => $row->agama,
                'salutation'     => $row->nama_panggilan,
                'image'          => $row->image,
            ],
            'contact' => [
                'address'     => $row->alamat,
                'country'     => $row->negara,
                'city'        => $row->kota,
                'phone'         => $row->no_telpon,
                'company_phone' => $row->no_telpon_perusahaan ?? null,
                'province'      => $row->provinsi,
                'postal_code' => $row->kode_pos,
            ],
            'employee' => [
                'nik'           => $row->nik_karyawan,
                'estatus'       => $row->status_karyawan,
                'sdate'         => $row->tgl_mulai_kerja,
                'ecdate'        => $row->tgl_berakhir_kontrak,
                'departement'   => $row->id_department,
                'grade'         => $row->grade,
                'position'      => $row->id_jabatan,
                'ppdate'        => $row->tgl_pra_pensiun,
                'ccenter'       => $row->cost_center,
                'email'         => $row->email,
                'email_pribadi' => $row->email_pribadi,
                'branch'        => $row->id_cabang,
                'gradec'        => $row->kategori_grade,
                'jstatus'       => $row->status_pekerjaan,
                'dsupervisor'   => json_decode($row->atasan_langsung, true) ?: [],
                'pdate'         => $row->tgl_pensiun,
            ],
            'access' => [
                'username'    => optional($row->user)->username,
                'priv_branch' => json_decode($row->privilage_cabang, true),
            ],
            'medical' => $medical ? [
                'tinggi_badan'          => $medical->tinggi_badan,
                'berat_badan'           => $medical->berat_badan,
                'rate_mata'             => $medical->rate_mata,
                'keterangan_mata'       => $medical->keterangan_mata,
                'golongan_darah'        => $medical->golongan_darah,
                'penyakit_bawaan_lahir' => $medical->penyakit_bawaan_lahir,
                'penyakit_lahir'        => $medical->penyakit_bawaan_lahir,
                'penyakit_kronis'       => $medical->penyakit_kronis,
                'riwayat_kecelakaan'    => $medical->riwayat_kecelakaan,
            ] : [],
        ];
    }

    private function resolvePendidikan(MasterKaryawan $row)
    {
        $rows = $row->pendidikan_karyawan
            ->filter(function ($item) {
                return (int) ($item->is_active ?? 1) !== 0
                    && (trim((string) $item->jenjang) !== '' || trim((string) $item->institusi) !== '');
            })
            ->sortByDesc(function ($item) {
                return sprintf('%04d-%06d', (int) $item->tahun_lulus, (int) $item->id);
            })
            ->values()
            ->map(function ($item) {
                return [
                    'id'          => $item->id,
                    'jenjang'     => $item->jenjang,
                    'institusi'   => $item->institusi,
                    'jurusan'     => $item->jurusan,
                    'tahun_masuk' => $item->tahun_masuk,
                    'tahun_lulus' => $item->tahun_lulus,
                    'kota'        => $item->kota,
                ];
            })
            ->all();

        if ($rows) {
            return $rows;
        }

        $decoded = json_decode($row->pendidikan ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }
}
