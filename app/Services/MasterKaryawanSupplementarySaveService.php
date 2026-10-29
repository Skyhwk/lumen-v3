<?php

namespace App\Services;

use App\Models\KeahlianBahasaKaryawan;
use App\Models\KeahlianKaryawan;
use App\Models\MedicalCheckup;
use App\Models\PendidikanKaryawan;
use App\Models\PengalamanKerjaKaryawan;
use App\Models\DataSertifikatKaryawan;
use Illuminate\Http\Request;

class MasterKaryawanSupplementarySaveService
{
    public function sync(int $karyawanId, Request $request, $actorId): void
    {
        $timestamp = date('Y-m-d H:i:s');

        if ($request->has('medical')) {
            $this->syncMedical($karyawanId, (array) $request->input('medical'), $actorId, $timestamp);
        }

        if ($request->has('education')) {
            $this->replaceEducation($karyawanId, $this->normalizeList($request->input('education')), $actorId, $timestamp);
        }

        if ($request->has('certificate')) {
            $this->replaceCertificates($karyawanId, $this->normalizeList($request->input('certificate')), $actorId, $timestamp);
        }

        if ($request->has('experience')) {
            $this->replaceExperience($karyawanId, $this->normalizeList($request->input('experience')), $actorId, $timestamp);
        }

        if ($request->has('skill')) {
            $this->replaceSkills($karyawanId, $this->normalizeList($request->input('skill')));
        }

        if ($request->has('languages')) {
            $this->replaceLanguages($karyawanId, $this->normalizeList($request->input('languages')));
        }
    }

    private function normalizeList($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    /** @param array<string, mixed> $medical */
    public static function normalizeMedicalInput(array $medical): array
    {
        $defaults = [
            'tinggi_badan' => null,
            'berat_badan' => null,
            'rate_mata' => null,
            'golongan_darah' => null,
            'penyakit_lahir' => null,
            'penyakit_bawaan_lahir' => null,
            'penyakit_kronis' => null,
            'riwayat_kecelakaan' => null,
            'obat_rutin' => null,
            'gangguan_mental' => null,
            'alergi' => null,
            'keterangan_mata' => null,
        ];

        $merged = array_merge($defaults, $medical);

        $penyakitLahir = trim((string) ($merged['penyakit_lahir'] ?? ''));
        $penyakitBawaan = trim((string) ($merged['penyakit_bawaan_lahir'] ?? ''));
        if ($penyakitBawaan === '' && $penyakitLahir !== '') {
            $merged['penyakit_bawaan_lahir'] = $penyakitLahir;
        } elseif ($penyakitLahir === '' && $penyakitBawaan !== '') {
            $merged['penyakit_lahir'] = $penyakitBawaan;
        }

        return $merged;
    }

    private function syncMedical(int $karyawanId, array $medical, $actorId, string $timestamp): void
    {
        $medical = self::normalizeMedicalInput($medical);

        $payload = [
            'tinggi_badan' => $this->nullableString($medical['tinggi_badan'] ?? null),
            'berat_badan' => $this->nullableString($medical['berat_badan'] ?? null),
            'rate_mata' => $this->nullableString($medical['rate_mata'] ?? null),
            'golongan_darah' => $this->nullableString($medical['golongan_darah'] ?? null),
            'penyakit_bawaan_lahir' => $this->nullableString($medical['penyakit_bawaan_lahir'] ?? $medical['penyakit_lahir'] ?? null),
            'penyakit_kronis' => $this->nullableString($medical['penyakit_kronis'] ?? null),
            'riwayat_kecelakaan' => $this->nullableString($medical['riwayat_kecelakaan'] ?? null),
            'obat_rutin' => $this->nullableString($medical['obat_rutin'] ?? null),
            'gangguan_mental' => $this->nullableString($medical['gangguan_mental'] ?? null),
            'alergi' => $this->nullableString($medical['alergi'] ?? null),
            'keterangan_mata' => $this->normalizeKeteranganMata($medical['keterangan_mata'] ?? null),
        ];

        if (collect($payload)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
            return;
        }

        $record = MedicalCheckup::where('karyawan_id', $karyawanId)->first();
        if (!$record) {
            $record = new MedicalCheckup();
            $record->karyawan_id = $karyawanId;
            $record->created_by = $actorId;
            $record->created_at = $timestamp;
            $record->is_active = 1;
        } else {
            $record->updated_by = $actorId;
            $record->updated_at = $timestamp;
        }

        foreach ($payload as $key => $value) {
            $record->{$key} = $value;
        }

        $record->save();
    }

    private function replaceEducation(int $karyawanId, array $rows, $actorId, string $timestamp): void
    {
        PendidikanKaryawan::where('karyawan_id', $karyawanId)->delete();

        foreach ($rows as $education) {
            if (!is_array($education)) {
                continue;
            }
            if (trim((string) ($education['institusi'] ?? '')) === '' && trim((string) ($education['jenjang'] ?? '')) === '') {
                continue;
            }

            PendidikanKaryawan::create([
                'karyawan_id' => $karyawanId,
                'institusi' => $education['institusi'] ?? null,
                'jenjang' => $education['jenjang'] ?? null,
                'jurusan' => $education['jurusan'] ?? null,
                'tahun_masuk' => $education['tahun_masuk'] ?? null,
                'tahun_lulus' => $education['tahun_lulus'] ?? null,
                'kota' => $education['kota'] ?? null,
                'created_by' => $actorId,
                'created_at' => $timestamp,
                'is_active' => 1,
            ]);
        }
    }

    private function replaceCertificates(int $karyawanId, array $rows, $actorId, string $timestamp): void
    {
        DataSertifikatKaryawan::where('karyawan_id', $karyawanId)->update(['is_active' => 0]);

        foreach ($rows as $certificate) {
            if (!is_array($certificate)) {
                continue;
            }
            $name = trim((string) ($certificate['nama_sertifikat'] ?? $certificate['nama'] ?? ''));
            if ($name === '') {
                continue;
            }

            DataSertifikatKaryawan::create([
                'karyawan_id' => $karyawanId,
                'nama_sertifikat' => $name,
                'tipe_sertifikat' => $certificate['tipe_sertifikat'] ?? $certificate['tipe'] ?? null,
                'nomor_sertifikat' => $certificate['nomor_sertifikat'] ?? $certificate['nomor'] ?? null,
                'deskripsi_sertifikat' => $certificate['deskripsi_sertifikat'] ?? $certificate['deskripsi'] ?? null,
                'tgl_sertifikat' => $this->nullableDate($certificate['tgl_sertifikat'] ?? $certificate['tanggal_sertifikasi'] ?? null),
                'tgl_exp_sertifikat' => $this->nullableDate($certificate['tgl_exp_sertifikat'] ?? $certificate['tanggal_expired'] ?? null),
                'created_by' => $actorId,
                'created_at' => $timestamp,
                'is_active' => 1,
            ]);
        }
    }

    private function replaceExperience(int $karyawanId, array $rows, $actorId, string $timestamp): void
    {
        PengalamanKerjaKaryawan::where('karyawan_id', $karyawanId)->update(['is_active' => 0]);

        foreach ($rows as $experience) {
            if (!is_array($experience)) {
                continue;
            }
            if (trim((string) ($experience['nama_perusahaan'] ?? '')) === '') {
                continue;
            }

            PengalamanKerjaKaryawan::create([
                'karyawan_id' => $karyawanId,
                'nama_perusahaan' => $experience['nama_perusahaan'] ?? null,
                'lokasi_perusahaan' => $experience['lokasi_perusahaan'] ?? null,
                'posisi_kerja' => $experience['posisi_kerja'] ?? null,
                'tgl_mulai_kerja' => $this->nullableDate($experience['tgl_mulai_kerja'] ?? null),
                'tgl_berakhir_kerja' => $this->nullableDate($experience['tgl_berakhir_kerja'] ?? null),
                'alasan_keluar' => $experience['alasan_keluar'] ?? null,
                'created_by' => $actorId,
                'created_at' => $timestamp,
                'is_active' => 1,
            ]);
        }
    }

    private function replaceSkills(int $karyawanId, array $rows): void
    {
        KeahlianKaryawan::where('karyawan_id', $karyawanId)->delete();

        foreach ($rows as $skill) {
            if (!is_array($skill)) {
                continue;
            }
            if (trim((string) ($skill['keahlian'] ?? '')) === '') {
                continue;
            }

            KeahlianKaryawan::create([
                'karyawan_id' => $karyawanId,
                'keahlian' => $skill['keahlian'],
                'rate' => $skill['rate'] ?? null,
            ]);
        }
    }

    private function replaceLanguages(int $karyawanId, array $rows): void
    {
        KeahlianBahasaKaryawan::where('karyawan_id', $karyawanId)->delete();

        foreach ($rows as $language) {
            if (!is_array($language)) {
                continue;
            }
            if (trim((string) ($language['bahasa'] ?? '')) === '') {
                continue;
            }

            KeahlianBahasaKaryawan::create([
                'karyawan_id' => $karyawanId,
                'bahasa' => $language['bahasa'],
                'baca' => $language['baca'] ?? null,
                'tulis' => $language['tulis'] ?? null,
                'dengar' => $language['dengar'] ?? null,
                'bicara' => $language['bicara'] ?? null,
            ]);
        }
    }

    private function nullableString($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function nullableDate($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : substr($text, 0, 10);
    }

    private function normalizeKeteranganMata($value): ?string
    {
        if ($value === null || $value === '' || $value === true || $value === 'true') {
            return null;
        }

        return trim((string) $value) ?: null;
    }
}
