# Changelog — merge ke `main`

**Periode:** 30 Oktober 2026 – 1 November 2026  
**Branch:** `main` (Backend: Skyhwk/lumen-v3 · Frontend: Skyhwk/frontend-react-js)

Dokumen ini merangkum perubahan yang masuk ke `main` untuk keperluan release note, QA, dan deploy. Pasangan backend–frontend yang saling bergantung disebutkan di bagian **Deploy bersama**.

---

## Ringkasan eksekutif

| Area | Backend | Frontend |
|------|---------|----------|
| Sampling / tracking sampler | Refactor besar, rekap sampel, trouble fix | UI tracking, history, monitoring |
| HR / disipliner / ATS | Dokumen disipliner, migrasi hired → karyawan | Halaman disipliner, wizard migrasi ATS |
| GA / aset | Validasi QR aset + unit test | Penyesuaian halaman aset / QR |
| Penyesuaian gaji & karyawan | Approval, assessment, ATS email, mutasi | UI approval, assessment, mutasi |
| Stabilitas & utilitas | Invoice render, bank data sort, log QSD | Debounce pin column, log transaksi, login |

---

## 1 November 2026

### HR — Penyesuaian gaji & karyawan

**Backend** (commit langsung ke `main`)

- Pembaharuan alur **approval penyesuaian gaji** (`PenyesuaianGajiHrdController`, layanan approval detail).
- Menampilkan **hasil assessment** dalam konteks penyesuaian gaji.
- **ATS / karyawan:** perbaikan notifikasi & email stakeholder (`AtsStakeholderEmailService`, `AtsNotificationService`, `GenerateMessageAtsEmail`), hired candidates, helper agama, penyesuaian `MasterKaryawanController`.
- **Mutasi penyesuaian:** perluasan `PermohonanPenyesuaianGajiController`, `EmployeeAdjustmentMutasiService`, workflow & notifikasi penyesuaian gaji.

**Frontend** — PR #3293–#3297 (`dedi-dev`)

- UI **approval** penyesuaian karyawan (termasuk career summary).
- Utilitas tampilan **evaluasi / assessment** (`evaluationDisplayUtils`).
- Perbaikan **approval** dan data **ATS** di master karyawan (`PersonalInformation`).
- Penyesuaian **mutasi** permohonan penyesuaian (`AdjustmentRequestDetailContent`, dll.).

**Deploy bersama:** backend commit 1 Nov + frontend #3293–#3297.

---

## 31 Oktober 2026

### Sampling — tracking & rekap sampel

**Backend** — PR #3627 (`poc/sampel-main`)

- Refactor **tracking sampler** (service journey/history/backfill, trouble collection).
- Penyesuaian controller **rekap sampel** (LIMS / SD / sampling).
- Command backfill & collect trouble; **pengecekan version** fitur.
- Cleanup kode lama terkait sampler tracking.

**Frontend** — PR #3242 (`poc/sampel-main`)

- **Tracking sampler:** modal riwayat aktivitas, perbaikan halaman tracking.
- Tools programmer (fixing tracking history), monitoring parameter analis.
- Selaras refactor backend; cleanup file tidak terpakai.

**Deploy bersama:** backend #3627 + frontend #3242.

### Sampling — perbaikan lanjutan

**Backend**

- PR #3685 (`remote-backend`): fix **`SamplerTrackingTroubleService`**.
- PR #3686 (`harold/ts/pc`): perbaikan tracking sampler / PC + unit test sync.
- Commit: perbaikan migration; perbaikan harga modal & parameter (domain terkait tools/modal).

**Frontend** — PR #3292 (`dedi-dev`)

- **Login:** remembered login (`rememberedLogin.js`).
- Perbaikan **modal harga & parameter**.

---

## 30 Oktober 2026

### Disipliner & koreksi absensi

**Backend** — PR #3681 (`goni-oct-akhir`)

- Modul **disipliner:** surat teguran, surat peringatan, berita acara kejadian (model, controller, generate QR/PDF).
- **Koreksi absensi** (controller & kolom terkait).
- Menu disipliner, penyesuaian footer dokumen, perbaikan log transaksi QSD.

**Frontend** — PR #3288 (`goni-oct-akhir`)

- UI **disipliner** dan **koreksi absensi** selaras backend.

**Deploy bersama:** backend #3681 + frontend #3288.

### HR — migrasi karyawan dari ATS

**Backend** — PR #3683 (`alawi/hc-migrate`)

- **Migrasi hired candidates → master karyawan** (controller ATS, dokumen karyawan, penanggung jawab dokumen).
- Integrasi dengan **penyesuaian gaji** (orchestrator, approval detail).
- Reconcile duplikat fitur disipliner dari merge branch lain.

**Frontend** — PR #3290 (`alawi/hc-migrate`)

- Halaman **dokumen disipliner** (BAK, surat teguran/peringatan), preview PDF.
- **Modal migrasi karyawan** multi-step dari ATS (contact information, dll.).

**Deploy bersama:** backend #3683 + frontend #3290.

### GA — validasi QR aset

**Backend** — PR #3684 (`harold/assets/validation-qr`)

- Validasi **QR dokumen aset**; unit test `AssetQrValidationTest`.

**Frontend** — PR #3291 (`harold/assets/validation-qr`)

- Penyesuaian UI **aset** selaras validasi QR backend.

**Deploy bersama:** backend #3684 + frontend #3291.

### Tracking trouble (sampler)

**Backend** — PR #3679 (`harold/2026/okt-29`)

- Update **tracking trouble** sampler.

**Frontend** — PR #3286 (`harold/2026/okt-29`)

- Penyesuaian UI **tracking trouble**.

**Deploy bersama:** backend #3679 + frontend #3286.

### Finance / penjualan / data

**Backend**

- PR #3680 (`harold/2026/okt-30`): perbaikan **`RenderInvoice`**.
- PR #3682 (`poc/update-logic-telu`): fix sort & error SQL **`BankDataCustomerController`** (kolom object / `SQLSTATE[42S22]`).

### Frontend — stabilitas grid & operasional

- PR #3287 (`dedi-dev`): perbaikan **log transaksi** (tabel).
- PR #3289 (`poc/bugfixeddayly`):
  - **Debounce** dan **pin column** di banyak menu DataTables.
  - Fix bentrok file **koreksi absensi table** saat merge.
  - Perbaikan **Remail Jadwal** dan logic index/style.

---

## Daftar PR (referensi)

### Backend (lumen-v3)

| Tanggal | PR | Branch |
|---------|-----|--------|
| 2026-10-30 | #3680 | `harold/2026/okt-30` |
| 2026-10-30 | #3681 | `goni-oct-akhir` |
| 2026-10-30 | #3682 | `poc/update-logic-telu` |
| 2026-10-30 | #3683 | `alawi/hc-migrate` |
| 2026-10-30 | #3684 | `harold/assets/validation-qr` |
| 2026-10-30 | #3679 | `harold/2026/okt-29` |
| 2026-10-31 | #3685 | `remote-backend` |
| 2026-10-31 | #3686 | `harold/ts/pc` |
| 2026-10-31 | #3627 | `poc/sampel-main` |

### Frontend (frontend-react-js)

| Tanggal | PR | Branch |
|---------|-----|--------|
| 2026-10-30 | #3287 | `dedi-dev` |
| 2026-10-30 | #3288 | `goni-oct-akhir` |
| 2026-10-30 | #3289 | `poc/bugfixeddayly` |
| 2026-10-30 | #3290 | `alawi/hc-migrate` |
| 2026-10-30 | #3291 | `harold/assets/validation-qr` |
| 2026-10-30 | #3286 | `harold/2026/okt-29` |
| 2026-10-31 | #3242 | `poc/sampel-main` |
| 2026-10-31 | #3292 | `dedi-dev` |
| 2026-11-01 | #3293–#3297 | `dedi-dev` (5 PR) |

---

## Checklist QA singkat (post-deploy)

- [ ] **Tracking sampler:** checkout, riwayat, trouble, rekap sampel.
- [ ] **Disipliner:** buat/preview/cetak surat teguran, peringatan, BAK.
- [ ] **ATS → karyawan:** migrasi hired candidate, dokumen karyawan.
- [ ] **Penyesuaian gaji:** approval, tampilan assessment, mutasi.
- [ ] **Aset GA:** scan/validasi QR sesuai policy.
- [ ] **Grid:** scroll pin column + filter (menu yang pakai debounce).
- [ ] **Invoice / bank data customer:** render invoice & sort/filter pelanggan.
- [ ] **Login:** remembered login (frontend #3292).

---

## Catatan release

1. Beberapa merge (**hc-migrate**, **validation-qr**, **sampel-main**) memiliki diff besar dengan banyak penghapusan file — umumnya **reconcile duplikat** antar branch, bukan penghapusan fitur di production.
2. Perubahan **1 November backend** (penyesuaian gaji/ATS) sebaiknya di-deploy **bersamaan** dengan frontend **#3293–#3297**.
3. Jalankan **migration** backend setelah pull `main` (termasuk commit perbaikan migration 31 Oct) sebelum smoke test.

---

*Generated from `git log origin/main --since=2026-10-30` on backend & frontend repositories.*
