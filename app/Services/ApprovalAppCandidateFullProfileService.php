<?php

namespace App\Services;

use App\Helpers\ShioElemenHelper;
use App\Models\NewRecruitment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalAppCandidateFullProfileService
{
    public function build(NewRecruitment $candidate): array
    {
        $candidate->loadMissing([
            'candidateProfile.educations' => fn ($q) => $q->where('is_active', 1)->orderByDesc('tahun_lulus'),
            'candidateProfile.workExperiences' => fn ($q) => $q->where('is_active', 1)->orderByDesc('tanggal_selesai'),
        ]);

        $cp = $candidate->candidateProfile;
        $medical = $this->loadMedical($cp, $candidate);

        return [
            'application_context' => $this->applicationContextBlock($candidate),
            'identity' => $this->identityBlock($candidate, $cp, $medical),
            'address' => $this->addressBlock($candidate, $cp),
            'emergency' => $this->emergencyBlock($cp),
            'medical' => $this->medicalBlock($medical),
            'education' => $this->educationBlock($candidate, $cp),
            'work_experience' => $this->workExperienceBlock($candidate, $cp),
            'skills' => $this->normalizeSkillLabels($candidate),
            'languages' => $this->normalizeLanguageLabels($candidate),
            'certificates' => $this->normalizeCertificateLabels($candidate),
            'courses' => $this->normalizeCourseLabels($candidate),
            'organization' => $this->normalizeOrganizationLabels($candidate),
            'interests' => $this->normalizeLooseLabels($candidate->minat ?? null),
            'references' => HrdEmailViewData::normalizePersonReferenceList($candidate->referensi ?? null),
            'recommendations' => HrdEmailViewData::normalizePersonRekomendasiList($candidate->rekomendasi ?? null),
            'supporting_info' => $this->supportingInfoBlock($cp, $candidate),
        ];
    }

    private function applicationContextBlock(NewRecruitment $candidate): array
    {
        $sumber = trim((string) ($candidate->sumber_informasi ?? ''));
        if (strtolower($sumber) === 'null') {
            $sumber = '';
        }

        $rekomendasiLines = array_values(array_filter(array_map(
            static fn (array $item) => HrdEmailViewData::formatPersonRekomendasiLine($item),
            HrdEmailViewData::normalizePersonRekomendasiList($candidate->rekomendasi ?? null)
        )));

        return [
            'sumber_informasi' => $sumber !== '' ? $sumber : null,
            'rekomendasi_personnel_intilab' => $rekomendasiLines,
            'pernah_bekerja_perusahaan' => $this->yesNoFieldForApi($candidate->pernah_bekerja_perusahaan ?? null),
            'masalah_hukum' => $this->yesNoFieldForApi($candidate->masalah_hukum ?? null),
        ];
    }

    /**
     * @return array{pernah: bool, pernah_label: string, keterangan: string|null}
     */
    private function yesNoFieldForApi($raw): array
    {
        $parsed = HrdEmailViewData::parseYesNoKeteranganField($raw);

        return [
            'pernah' => $parsed['pernah'],
            'pernah_label' => $parsed['pernah'] ? 'Ya' : 'Tidak',
            'keterangan' => $parsed['pernah'] && $parsed['keterangan'] !== ''
                ? $parsed['keterangan']
                : null,
        ];
    }

    private function loadMedical($cp, NewRecruitment $candidate)
    {
        if (!Schema::hasTable('candidate_medical_informations')) {
            return null;
        }

        $query = DB::table('candidate_medical_informations')->where('is_active', 1);

        if ($cp && $cp->id) {
            $query->where(function ($q) use ($cp, $candidate) {
                $q->where('candidate_profile_id', $cp->id)
                    ->orWhere('new_recruitment_id', $candidate->id);
            });
        } else {
            $query->where('new_recruitment_id', $candidate->id);
        }

        return $query->orderByDesc('id')->first();
    }

    private function identityBlock(NewRecruitment $candidate, $cp, $medical): array
    {
        $birthRaw = ($cp && $cp->tanggal_lahir) ? $cp->tanggal_lahir : ($candidate->tanggal_lahir ?? null);
        $shioElemen = ShioElemenHelper::resolve($birthRaw, $candidate->shio ?? null, $candidate->elemen ?? null);

        return [
            'nama_panggilan' => $cp ? $this->pick($cp->nama_panggilan ?? null, null) : null,
            'jenis_kelamin' => $this->formatGender(
                ($cp && $cp->jenis_kelamin) ? $cp->jenis_kelamin : ($candidate->jenis_kelamin ?? null)
            ),
            'tempat_lahir' => $this->pick($cp ? ($cp->tempat_lahir ?? null) : null, $candidate->tempat_lahir ?? null),
            'tanggal_lahir' => $this->formatDate($birthRaw),
            'tempat_tanggal_lahir' => $this->formatTtl($candidate, $cp),
            'shio' => $shioElemen['shio'] ?? null,
            'elemen' => $shioElemen['elemen'] ?? null,
            'agama' => $this->pick($cp ? ($cp->agama ?? null) : null, $candidate->agama ?? null),
            'status_pernikahan' => $this->pick($cp ? ($cp->status_pernikahan ?? null) : null, $candidate->status_nikah ?? null),
            'tanggal_nikah' => $this->formatDate($candidate->tgl_nikah ?? null),
            'nik_ktp' => $this->pick($cp ? ($cp->nik_ktp ?? null) : null, $candidate->nik_ktp ?? null),
            'no_kk' => $cp ? ($cp->no_kk ?? null) : null,
            'no_npwp' => $cp ? ($cp->no_npwp ?? null) : null,
            'no_bpjs_kesehatan' => $this->pick($cp ? ($cp->no_bpjs_ks ?? null) : null, $candidate->bpjs_kesehatan ?? null),
            'no_bpjs_ketenagakerjaan' => $this->pick($cp ? ($cp->no_bpjs_tk ?? null) : null, $candidate->bpjs_ketenagakerjaan ?? null),
            'tgl_exp_identitas' => $this->formatDate($candidate->tgl_exp_identitas ?? null),
            'jumlah_tanggungan' => ($cp && isset($cp->jumlah_tanggungan)) ? (int) $cp->jumlah_tanggungan : null,
            'golongan_darah' => $medical->golongan_darah ?? null,
        ];
    }

    private function addressBlock(NewRecruitment $candidate, $cp): array
    {
        if (!$cp) {
            return [
                'alamat_ktp' => $candidate->alamat_ktp ?? null,
                'alamat_domisili' => $candidate->alamat_domisili ?? null,
                'status_tempat_tinggal' => null,
                'kota_ktp' => null,
                'provinsi_ktp' => null,
                'kode_pos_ktp' => null,
                'kota_domisili' => null,
                'provinsi_domisili' => null,
                'kode_pos_domisili' => null,
            ];
        }

        return [
            'alamat_ktp' => $this->joinAddress(
                $cp->alamat_ktp ?: ($candidate->alamat_ktp ?? null),
                $cp->kota_ktp,
                $cp->provinsi_ktp,
                $cp->kode_pos_ktp
            ),
            'alamat_domisili' => $this->joinAddress(
                $cp->alamat_domisili ?: ($candidate->alamat_domisili ?? null),
                $cp->kota_domisili,
                $cp->provinsi_domisili,
                $cp->kode_pos_domisili
            ),
            'status_tempat_tinggal' => $cp->status_tempat_tinggal ?? null,
            'kota_ktp' => $cp->kota_ktp ?? null,
            'provinsi_ktp' => $cp->provinsi_ktp ?? null,
            'kode_pos_ktp' => $cp->kode_pos_ktp ?? null,
            'kota_domisili' => $cp->kota_domisili ?? null,
            'provinsi_domisili' => $cp->provinsi_domisili ?? null,
            'kode_pos_domisili' => $cp->kode_pos_domisili ?? null,
        ];
    }

    private function emergencyBlock($cp): array
    {
        if (!$cp) {
            return [];
        }

        return array_filter([
            'kontak_1' => $this->emergencyContact(
                $cp->nama_kontak_darurat ?? null,
                $cp->hubungan_kontak_darurat ?? null,
                $cp->no_telepon_darurat ?? null
            ),
            'kontak_2' => $this->emergencyContact(
                $cp->nama_kontak_darurat_2 ?? null,
                $cp->hubungan_kontak_darurat_2 ?? null,
                $cp->no_telepon_darurat_2 ?? null
            ),
        ]);
    }

    private function emergencyContact(?string $name, ?string $relation, ?string $phone): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return [
            'nama' => $name,
            'hubungan' => trim((string) $relation) ?: null,
            'telepon' => trim((string) $phone) ?: null,
        ];
    }

    private function medicalBlock($medical): array
    {
        if (!$medical) {
            return [];
        }

        return array_filter([
            'tinggi_badan_cm' => $medical->tinggi_badan ?? null,
            'berat_badan_kg' => $medical->berat_badan ?? null,
            'mata' => $medical->mata ?? null,
            'golongan_darah' => $medical->golongan_darah ?? null,
            'penyakit_bawaan' => $medical->penyakit_bawaan_lahir ?? null,
            'penyakit_kronis' => $medical->penyakit_kronis ?? null,
            'riwayat_kecelakaan' => $medical->riwayat_kecelakaan ?? null,
            'catatan' => $medical->catatan ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function educationBlock(NewRecruitment $candidate, $cp): array
    {
        $rows = [];

        if ($cp && $cp->relationLoaded('educations') && $cp->educations->isNotEmpty()) {
            foreach ($cp->educations as $edu) {
                $rows[] = [
                    'jenjang' => $edu->jenjang_pendidikan ?? null,
                    'institusi' => $edu->nama_institusi ?? null,
                    'jurusan' => $edu->jurusan ?? null,
                    'tahun_masuk' => $edu->tahun_masuk ?? null,
                    'tahun_lulus' => $edu->tahun_lulus ?? null,
                    'ipk' => $edu->nilai_ipk ?? null,
                ];
            }

            return $rows;
        }

        $byRecruitment = Schema::hasTable('candidate_educations')
            ? DB::table('candidate_educations')
                ->where('new_recruitment_id', $candidate->id)
                ->where('is_active', 1)
                ->orderByDesc('tahun_lulus')
                ->get()
            : collect();

        foreach ($byRecruitment as $edu) {
            $rows[] = [
                'jenjang' => $edu->jenjang_pendidikan ?? null,
                'institusi' => $edu->nama_institusi ?? null,
                'jurusan' => $edu->jurusan ?? null,
                'tahun_masuk' => $edu->tahun_masuk ?? null,
                'tahun_lulus' => $edu->tahun_lulus ?? null,
                'ipk' => $edu->nilai_ipk ?? null,
            ];
        }

        if ($rows !== []) {
            return $rows;
        }

        $raw = $candidate->pendidikan;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        foreach ($raw as $edu) {
            if (!is_array($edu)) {
                continue;
            }
            $rows[] = [
                'jenjang' => $edu['jenjang'] ?? $edu['degree'] ?? null,
                'institusi' => $edu['institusi'] ?? $edu['school'] ?? null,
                'jurusan' => $edu['jurusan'] ?? $edu['major'] ?? null,
                'tahun_masuk' => $edu['tahun_masuk'] ?? null,
                'tahun_lulus' => $edu['tahun_lulus'] ?? $edu['tahun'] ?? $edu['year'] ?? null,
                'ipk' => $edu['ipk'] ?? $edu['gpa'] ?? null,
            ];
        }

        return $rows;
    }

    private function workExperienceBlock(NewRecruitment $candidate, $cp): array
    {
        $rows = [];

        if ($cp && $cp->relationLoaded('workExperiences') && $cp->workExperiences->isNotEmpty()) {
            foreach ($cp->workExperiences as $exp) {
                $rows[] = [
                    'posisi' => $exp->posisi_terakhir ?? null,
                    'perusahaan' => $exp->nama_perusahaan ?? null,
                    'mulai' => $this->formatMonthYear($exp->tanggal_mulai ?? null),
                    'selesai' => $this->formatMonthYear($exp->tanggal_selesai ?? null) ?: 'Sekarang',
                    'alasan_keluar' => $exp->alasan_resign ?? null,
                    'deskripsi' => null,
                ];
            }

            return $rows;
        }

        $raw = $candidate->pengalaman_kerja;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        foreach ($raw as $exp) {
            if (!is_array($exp)) {
                continue;
            }
            $rows[] = [
                'posisi' => $exp['posisi_kerja'] ?? $exp['posisi'] ?? $exp['position'] ?? null,
                'perusahaan' => $exp['nama_perusahaan'] ?? $exp['perusahaan'] ?? $exp['company'] ?? null,
                'lokasi' => $exp['lokasi_perusahaan'] ?? $exp['lokasi'] ?? null,
                'mulai' => $this->formatMonthYear($exp['mulai_kerja'] ?? $exp['mulai'] ?? null),
                'selesai' => $this->formatWorkEnd($exp['akhir_kerja'] ?? $exp['akhir'] ?? null),
                'periode' => $exp['periode'] ?? $exp['period'] ?? null,
                'alasan_keluar' => $exp['alasan_keluar'] ?? $exp['alasan_resign'] ?? null,
                'deskripsi' => $exp['deskripsi'] ?? $exp['description'] ?? null,
            ];
        }

        return $rows;
    }

    private function pick(?string $primary, ?string $fallback): ?string
    {
        $primary = trim((string) ($primary ?? ''));
        if ($primary !== '') {
            return $primary;
        }
        $fallback = trim((string) ($fallback ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    private function formatGender(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $g = strtolower(trim($raw));
        if (in_array($g, ['male', 'laki-laki', 'l', 'm'], true)) {
            return 'Laki-laki';
        }
        if (in_array($g, ['female', 'perempuan', 'p', 'f'], true)) {
            return 'Perempuan';
        }

        return ucfirst($raw);
    }

    private function formatDate($value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }
        try {
            return Carbon::parse($value)->locale('id')->isoFormat('D MMMM YYYY');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatWorkEnd($value): ?string
    {
        $formatted = $this->formatMonthYear($value);

        return $formatted ?: 'Sekarang';
    }

    private function formatMonthYear($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->locale('id')->isoFormat('MMM YYYY');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatTtl(NewRecruitment $candidate, $cp): ?string
    {
        $tempat = $this->pick($cp->tempat_lahir ?? null, $candidate->tempat_lahir ?? null);
        $tgl = $this->formatDate(
            ($cp && $cp->tanggal_lahir) ? $cp->tanggal_lahir : ($candidate->tanggal_lahir ?? null)
        );
        if ($tempat && $tgl) {
            return $tempat . ', ' . $tgl;
        }

        return $tempat ?: $tgl;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSkillLabels(NewRecruitment $candidate): array
    {
        $labels = [];

        foreach (HrdEmailViewData::decodeJsonField($candidate->skill ?? null) as $item) {
            if (!is_array($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $labels[] = $text;
                }
                continue;
            }
            $keahlian = trim((string) ($item['keahlian'] ?? $item['skill'] ?? $item['name'] ?? ''));
            if ($keahlian === '') {
                continue;
            }
            $rate = $item['rate'] ?? null;
            if ($rate !== null && $rate !== '') {
                $labels[] = sprintf('%s (%s/10)', $keahlian, (int) $rate);
            } else {
                $labels[] = $keahlian;
            }
        }

        if ($labels !== []) {
            return $labels;
        }

        $legacy = $candidate->keahlian ?? null;
        if (is_string($legacy)) {
            $legacy = json_decode($legacy, true);
        }
        if (!is_array($legacy)) {
            return [];
        }

        foreach ($legacy as $item) {
            if (!is_array($item)) {
                continue;
            }
            $keahlian = trim((string) ($item['keahlianSkill'] ?? $item['keahlian'] ?? ''));
            if ($keahlian !== '') {
                $labels[] = $keahlian;
            }
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeLanguageLabels(NewRecruitment $candidate): array
    {
        $labels = [];
        foreach (HrdEmailViewData::decodeJsonField($candidate->skill_bahasa ?? null) as $item) {
            if (!is_array($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $labels[] = $text;
                }
                continue;
            }
            $bahasa = trim((string) ($item['bahasa'] ?? $item['language'] ?? $item['name'] ?? ''));
            if ($bahasa === '') {
                continue;
            }
            $parts = array_filter([
                isset($item['baca']) ? 'baca ' . $item['baca'] : null,
                isset($item['tulis']) ? 'tulis ' . $item['tulis'] : null,
                isset($item['dengar']) ? 'dengar ' . $item['dengar'] : null,
                isset($item['bicara']) ? 'bicara ' . $item['bicara'] : null,
                isset($item['level']) ? (string) $item['level'] : null,
            ]);
            $labels[] = $parts !== [] ? $bahasa . ' · ' . implode(' · ', $parts) : $bahasa;
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeCertificateLabels(NewRecruitment $candidate): array
    {
        $labels = [];
        foreach (HrdEmailViewData::decodeJsonField($candidate->sertifikat ?? null) as $item) {
            if (!is_array($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $labels[] = $text;
                }
                continue;
            }
            $nama = trim((string) ($item['nama'] ?? $item['name'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $extra = array_filter([
                $item['tipe'] ?? null,
                isset($item['nomor']) ? 'No. ' . $item['nomor'] : null,
            ]);
            $period = trim((string) (($item['tanggal_sertifikasi'] ?? '') . ' – ' . ($item['tanggal_expired'] ?? '')));
            if ($period !== '–') {
                $extra[] = $period;
            }
            $labels[] = $extra !== [] ? $nama . ' · ' . implode(' · ', $extra) : $nama;
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeCourseLabels(NewRecruitment $candidate): array
    {
        $labels = [];
        foreach (HrdEmailViewData::decodeJsonField($candidate->kursus ?? null) as $item) {
            if (!is_array($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $labels[] = $text;
                }
                continue;
            }
            $nama = trim((string) ($item['nama'] ?? $item['name'] ?? ''));
            $institusi = trim((string) ($item['institusi'] ?? $item['lembaga'] ?? ''));
            $title = $nama !== '' ? $nama : $institusi;
            if ($title === '') {
                continue;
            }
            $parts = array_filter([
                $institusi !== '' && $institusi !== $nama ? $institusi : null,
                trim((string) (($item['mulai_kursus'] ?? '') . ' – ' . ($item['akhir_kursus'] ?? ''))) ?: null,
            ]);
            $labels[] = $parts !== [] ? $title . ' · ' . implode(' · ', $parts) : $title;
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeOrganizationLabels(NewRecruitment $candidate): array
    {
        $labels = [];
        foreach (HrdEmailViewData::decodeJsonField($candidate->organisasi ?? null) as $item) {
            if (!is_array($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $labels[] = $text;
                }
                continue;
            }
            $posisi = trim((string) ($item['posisi'] ?? $item['jabatan'] ?? ''));
            $nama = trim((string) ($item['nama'] ?? $item['organisasi'] ?? $item['name'] ?? ''));
            $period = trim((string) (($item['mulai_org'] ?? $item['mulai'] ?? '') . ' – ' . ($item['akhir_org'] ?? $item['akhir'] ?? '')));
            if ($posisi !== '' && $nama !== '') {
                $line = $posisi . ' · ' . $nama;
            } else {
                $line = $posisi !== '' ? $posisi : $nama;
            }
            if ($line === '') {
                continue;
            }
            if ($period !== '' && $period !== '–') {
                $line .= ' (' . $period . ')';
            }
            $labels[] = $line;
        }

        return $labels;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeLooseLabels($value): array
    {
        $labels = [];
        foreach (HrdEmailViewData::decodeJsonField($value) as $item) {
            if (is_scalar($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $labels[] = $text;
                }
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            foreach (['minat', 'name', 'nama', 'title', 'label', 'value', 'keahlian', 'deskripsi'] as $key) {
                $text = trim((string) ($item[$key] ?? ''));
                if ($text !== '') {
                    $labels[] = $text;
                    continue 2;
                }
            }
        }

        return $labels;
    }

    /**
     * Kuesioner kelengkapan profil — tabel candidate_supporting_info_answers.
     *
     * @return array{categories: array<int, array{name: string, items: array<int, array{question: string, answer: string}>}>, has_answers: bool}
     */
    private function supportingInfoBlock($cp, NewRecruitment $candidate): array
    {
        $empty = ['categories' => [], 'has_answers' => false];

        if (!Schema::hasTable('candidate_supporting_info_answers')) {
            return $empty;
        }

        $query = DB::table('candidate_supporting_info_answers')
            ->where('is_active', 1)
            ->orderBy('question_category_id')
            ->orderBy('question_id');

        if ($cp && !empty($cp->id)) {
            $query->where(function ($q) use ($cp, $candidate) {
                $q->where('candidate_profile_id', $cp->id)
                    ->orWhere('new_recruitment_id', $candidate->id);
            });
        } else {
            $query->where('new_recruitment_id', $candidate->id);
        }

        $answers = $query->get();
        if ($answers->isEmpty()) {
            return $empty;
        }

        $grouped = [];
        foreach ($answers as $answer) {
            $category = trim((string) ($answer->category_name ?? ''));
            if ($category === '' || strtolower($category) === 'null') {
                $category = 'Informasi pendukung';
            }
            $question = trim((string) ($answer->question_text ?? ''));
            if ($question === '') {
                $question = 'Pertanyaan';
            }
            $answerText = $this->resolveSupportingAnswerText($answer);
            if ($answerText === '') {
                continue;
            }

            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'question' => $question,
                'answer' => $answerText,
            ];
        }

        $categories = [];
        foreach ($grouped as $name => $items) {
            if ($items === []) {
                continue;
            }
            $categories[] = [
                'name' => $name,
                'items' => $items,
            ];
        }

        return [
            'categories' => $categories,
            'has_answers' => $categories !== [],
        ];
    }

    private function resolveSupportingAnswerText($answer): string
    {
        $text = trim((string) ($answer->answer_text ?? ''));
        if ($text !== '' && strtolower($text) !== 'null') {
            return $text;
        }

        $payload = $answer->answer_payload ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload)) {
            return '';
        }

        $raw = $payload['raw'] ?? null;
        if (is_array($raw)) {
            $parts = array_filter(array_map(static fn ($v) => trim((string) $v), $raw));

            return implode(', ', $parts);
        }

        return trim((string) $raw);
    }

    private function joinAddress(?string $main, ?string $city, ?string $province, ?string $postal): ?string
    {
        $main = trim((string) ($main ?? ''));
        $parts = array_filter([
            trim((string) ($city ?? '')),
            trim((string) ($province ?? '')),
            $postal ? 'Kode pos ' . trim((string) $postal) : '',
        ]);

        if ($main === '' && $parts === []) {
            return null;
        }
        if ($main === '') {
            return implode(', ', $parts);
        }
        if ($parts === []) {
            return $main;
        }

        return $main . ', ' . implode(', ', $parts);
    }
}
