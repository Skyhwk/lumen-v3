<?php

namespace App\Services;

class OfferingSalaryEmail
{
    public static function contactLine($data): string
    {
        $phone = $data->no_hp ?? null;
        $email = $data->email ?? null;

        if (!$phone) {
            return $email ?: '-';
        }

        if (!$email) {
            return $phone;
        }

        return $phone . ' / ' . $email;
    }

    public static function photoUrl($data): string
    {
        $foto = $data->foto_selfie ?? $data->picture ?? null;
        if (empty($foto)) {
            return '';
        }

        if (env('APP_ENV') === 'production') {
            return 'https://apps.intilab.com/v3/public/recruitment/foto/' . $foto;
        }

        return url('recruitment/foto/' . $foto);
    }

    public static function decodeJsonField($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_filter($value);
        }

        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_filter($decoded);
    }

    public static function formatRupiah($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    public static function getNamaJabatan($data): string
    {
        if (empty($data)) {
            return '-';
        }

        if (!empty($data->personalRequest->masterJabatan->nama_jabatan)) {
            return $data->personalRequest->masterJabatan->nama_jabatan;
        }

        if (!empty($data->personnelRequest->masterJabatan->nama_jabatan)) {
            return $data->personnelRequest->masterJabatan->nama_jabatan;
        }

        if (!empty($data->masterJabatan->nama_jabatan)) {
            return $data->masterJabatan->nama_jabatan;
        }

        $candidatePos = $data->nama_jabatan ?? $data->posisi_di_lamar ?? $data->bagian_di_lamar ?? $data->posisi_dilamar ?? null;

        if (!empty($candidatePos) && !is_numeric($candidatePos)) {
            return $candidatePos;
        }

        if (!empty($candidatePos) && is_numeric($candidatePos)) {
            $jabatan = \App\Models\MasterJabatan::find($candidatePos);
            if ($jabatan && !empty($jabatan->nama_jabatan)) {
                return $jabatan->nama_jabatan;
            }
        }

        return '-';
    }

    public static function getUsia($data): string
    {
        if (empty($data)) {
            return '-';
        }

        $tglLahir = $data->tanggal_lahir ?? $data->tgl_lahir ?? $data->date_of_birth ?? null;

        if (!empty($tglLahir)) {
            try {
                $birthDate = \Carbon\Carbon::parse($tglLahir);
                $age = $birthDate->age;
                if ($age > 0) {
                    return $age . ' Tahun';
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        $umur = $data->umur ?? $data->usia ?? null;
        if (!empty($umur)) {
            return is_numeric($umur) ? $umur . ' Tahun' : $umur;
        }

        return '-';
    }

    public static function formatTanggalLahir($data): string
    {
        if (empty($data)) {
            return '-';
        }

        $tglLahir = $data->tanggal_lahir ?? $data->tgl_lahir ?? $data->date_of_birth ?? null;

        if (empty($tglLahir)) {
            return '-';
        }

        try {
            $date = \Carbon\Carbon::parse($tglLahir)->locale('id');
            return $date->translatedFormat('d F Y');
        } catch (\Exception $e) {
            return (string) $tglLahir;
        }
    }

    public static function prepareCvData($data): array
    {
        $profile = $data->candidateProfile 
            ?? \App\Models\CandidateProfile::where('new_recruitment_id', $data->id ?? 0)->first();

        $candidateEducations = $data->candidateEducations 
            ?? \App\Models\CandidateEducation::where('new_recruitment_id', $data->id ?? 0)->get();

        $pendidikanList = [];
        if (!empty($candidateEducations) && count($candidateEducations) > 0) {
            foreach ($candidateEducations as $edu) {
                $pendidikanList[] = [
                    'jenjang'     => $edu->jenjang_pendidikan ?? $edu->jenjang ?? '-',
                    'institusi'   => $edu->nama_institusi ?? $edu->institusi ?? '-',
                    'jurusan'     => $edu->jurusan ?? '-',
                    'tahun_masuk' => $edu->tahun_masuk ?? '-',
                    'tahun_lulus' => $edu->tahun_lulus ?? '-',
                    'ipk'         => $edu->nilai_ipk ?? '-',
                ];
            }
        } else {
            $pendidikanList = self::decodeJsonField($data->pendidikan ?? null);
        }

        $candidateWorkExp = $data->candidateWorkExperiences 
            ?? \App\Models\CandidateWorkExperience::where('new_recruitment_id', $data->id ?? 0)->get();

        $pengalamanList = [];
        if (!empty($candidateWorkExp) && count($candidateWorkExp) > 0) {
            foreach ($candidateWorkExp as $exp) {
                $pengalamanList[] = [
                    'nama_perusahaan' => $exp->nama_perusahaan ?? '-',
                    'posisi_kerja'    => $exp->posisi_terakhir ?? $exp->posisi_kerja ?? '-',
                    'mulai_kerja'     => $exp->tanggal_mulai ?? '-',
                    'akhir_kerja'     => $exp->tanggal_selesai ?? '-',
                    'alasan_keluar'   => $exp->alasan_resign ?? $exp->alasan_keluar ?? '-',
                ];
            }
        } else {
            $pengalamanList = self::decodeJsonField($data->pengalaman_kerja ?? null);
        }

        $medical = $data->candidateMedicalInformation 
            ?? \App\Models\CandidateMedicalInformation::where('new_recruitment_id', $data->id ?? 0)->first();

        return [
            'data'                 => $data,
            'profile'              => $profile,
            'medical'              => $medical,
            'photoUrl'             => self::photoUrl($data),
            'pendidikan'           => $pendidikanList,
            'pengalamanKerja'      => $pengalamanList,
            'skills'               => self::decodeJsonField($data->skill ?? null),
            'skillBahasa'          => self::decodeJsonField($data->skill_bahasa ?? null),
            'minat'                => self::decodeJsonField($data->minat ?? null),
            'organisasi'           => self::decodeJsonField($data->organisasi ?? null),
            'referensi'            => self::decodeJsonField($data->referensi ?? null),
            'sertifikat'           => self::decodeJsonField($data->sertifikat ?? null),
            'kursus'               => self::decodeJsonField($data->kursus ?? null),
            'salaryFormatted'      => self::formatRupiah($data->salary_user ?? null),
            'namaJabatanFormatted' => self::getNamaJabatan($data),
            'usiaFormatted'        => self::getUsia($data),
            'tanggalLahirFormatted' => self::formatTanggalLahir($data),
            'noTelepon'            => $data->no_telepon ?? $data->no_hp ?? '-',
        ];
    }

    public static function getMasterKaryawan($data)
    {
        if (empty($data)) {
            return null;
        }

        if (!empty($data->nik_ktp)) {
            $karyawan = \App\Models\MasterKaryawan::where('nik_ktp', $data->nik_ktp)->first();
            if ($karyawan) return $karyawan;
        }

        if (!empty($data->email)) {
            $email = $data->email;
            $karyawan = \App\Models\MasterKaryawan::where(function($q) use ($email) {
                $q->where('email', $email)->orWhere('email_pribadi', $email);
            })->first();
            if ($karyawan) return $karyawan;
        }

        if (!empty($data->nama_lengkap)) {
            $karyawan = \App\Models\MasterKaryawan::where('nama_lengkap', $data->nama_lengkap)->first();
            if ($karyawan) return $karyawan;
        }

        return null;
    }
}
