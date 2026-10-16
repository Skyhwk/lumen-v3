# 16PF — Assessment Penyesuaian Karyawan

Modul **16PF** hanya untuk penyesuaian karyawan (`is_show = 0`), tidak muncul di assessment rekrutmen.

## Setup data

1. Pastikan file sumber ada di `Documents` (Excel tes + skoring).
2. Generate bundle JSON:

   ```bash
   python scripts/extract_16pf_for_seed.py
   ```

   Output: `storage/app/psychometric/16pf_bundle.json`

3. Migrate + seed norma STEN (database) dan soal:

   ```bash
   php artisan migrate
   php artisan db:seed --class=SixteenPfNormSeeder
   php artisan db:seed --class=SixteenPfProfileSeeder
   php artisan db:seed --class=SixteenPfQuestionSeeder
   ```

   Runtime: norma dari **`psychometric_16pf_norms`**, uraian kutub dari **`psychometric_16pf_profiles`**. File `16pf_bundle.json` hanya untuk import/seed dari Excel (bukan dibaca saat tes).

## Operasional HRD

1. Buka permohonan penyesuaian → generate link assessment.
2. Aktifkan modul **16PF** (105 soal, ±60 menit).
3. Karyawan mengerjakan via `/private/assessment/{token}`.

## Teknis

| Lapisan | Lokasi |
|---------|--------|
| Kategori | `question_categories` (`16PF`, `is_show=0`) |
| Soal + kunci item | `soal_psikotes` (`kategori_soal=16PF`) |
| Norma STEN | Tabel `psychometric_16pf_norms` (seed dari bundle/Excel) |
| Uraian profil (kutub) | Tabel `psychometric_16pf_profiles` → API `pf16_detail` |
| Engine | `SalaryAdjustmentAssessmentEngine::pf16Questions()` |
| Skoring | `SixteenPfScoringService` / `PsychometricScoringService` |
| Jawaban attempt | `salary_adjustment_assessment_sessions.answers_json` |

Kunci skoring per item (0/1/2 untuk a/b/c) diekstrak dari template Excel; validasi silakan bandingkan STEN dengan `Aplikasi Skoring 16PF.xls`.
