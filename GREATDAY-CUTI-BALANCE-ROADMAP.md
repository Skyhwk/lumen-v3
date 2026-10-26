# Roadmap — Saldo Cuti & Detail Penggunaan (Greatday + Super Apps V3)

**Sumber kebutuhan:** `DRAFT-CUTI (Untuk Sistem).xlsx` (3 sheet)  
**Tanggal analisis:** 2026-09-27  
**Update:** 2026-09-27 — keputusan HR + struktur menu `hrd/pengajuan/`  
**Status:** Fase A — inti backend + Greatday + rekap HRD (implementasi awal)

---

## 1. Ringkasan isi Excel

### Sheet `Ketentuan` — aturan bisnis

| Topik | Isi penting untuk sistem |
|-------|---------------------------|
| Jenis permohonan | Cuti Tahunan, Cuti Khusus, Unpaid Leave, Pengganti Hari Libur |
| Cuti tahunan | **12 hari kerja/tahun**; **reset** per ulang tahun kerja (bulan+tanggal join); eligible: Khusus/Tetap/Kontrak/Pelatihan (**min. 1 tahun** di ketentuan tertulis); tidak boleh adjacency tanggal merah; **max 3 hari kerja / 30 hari kalender**; **H-14** pengajuan; bisa digabung cuti khusus (max 3 hari tahunan); **alpa mengurangi** cuti tahunan |
| Cuti khusus | 15 jenis + batas masing-masing (hari kerja / kalender / surat); bisa digabung tahunan dengan aturan |
| Pengganti hari libur | Tidak mengurangi cuti tahunan; max 1 hari kerja / 30 hari kalender; gabung dengan tahunan max 3+1 |

**Mapping ke kode saat ini**

| Aturan Excel | Implementasi LUMEN-V3 / Greatday hari ini |
|--------------|-------------------------------------------|
| Periode reset (join date) | Sudah: `FormsHubService::leaveYearBounds()` dari `master_karyawan.tgl_mulai_kerja` |
| Kuota 12 hari | Hardcode `ANNUAL_LEAVE_QUOTA = 12` — **belum** pro-rata & variabel periode |
| Terpakai = approved annual | Sudah: hitung hari kerja dari `hr_requests` + `hr_leave_detail` (`leave_kind = annual`) status `APPROVED_*` |
| Sisa | `max(0, 12 - terpakai)` — **tidak** memakai saldo awal Excel |
| Cuti khusus 15 jenis | `hr_special_leave_type` + legacy `special_leave_types` — perlu **parity** teks/batas dengan sheet Ketentuan |
| Validasi H-14, max 3/30 hari, tanggal merah, alpa, unpaid, PHL | **Belum** (hanya alur approval HR) |
| Eligibility status | Sebagian: `Training`/`Probation` → sisa 0 — **perlu** selaras keputusan HR (§11) |

### Sheet `Alur` — workflow

```
Karyawan → Pengajuan → SPV → Manager → HRD (verifikasi?) → Disetujui & tercatat
```

**Open point HRD:** verifikasi saja vs approval tambahan (sudah ada step HRD di `ApprovalService` — konfirmasi bisnis).

**Rekapitulasi saldo:** cuti approved harus mengurangi sisa; historis manual sudah masuk kolom **Cuti Diambil** (lihat cutover §11).

### Sheet `REKAP SISA CUTI 2026` — data awal produksi

- **156 karyawan** (baris data, NIK terisi)
- Header kolom → field sistem:

| Kolom Excel | Field logis | Catatan |
|-------------|-------------|---------|
| NIK | `master_karyawan.nik_karyawan` | Key import |
| Employee Name | validasi / display | |
| Organization Unit | display (divisi) | |
| Join Date | `tgl_mulai_kerja` | harus selaras DB |
| Length of Working Service | display computed | |
| Permanent Date | metadata | opsional di UI |
| Employment Status | `status_karyawan` | Permanent / Contract / Pelatihan / Khusus |
| Jatah Cuti | `quota_days` | **152×12**; **4× pro-rata** (5,6,9,10) — harus dihitung engine, bukan hardcode NIK |
| Start Periode | `period_start` | harus match `leaveYearBounds` |
| End Periode | `period_end` | |
| Cuti Diambil | `opening_used_days` | usage **termasuk pengajuan lama** sebelum cutover |
| Sisa Cuti | validasi import | = jatah − diambil |
| Keterangan | `notes` | sering teks reset ulang tahun |

**Referensi pro-rata (validasi rumus, bukan daftar tetap):**

| NIK | Jatah | Status | Catatan |
|-----|-------|--------|---------|
| ISC076 | 5 | Contract | contoh pro-rata periode pertama |
| ISC082 | 6 | Contract | |
| ISC073 | 9 | Contract | |
| ISC083 | 10 | Contract | |

---

## 2. Gap utama vs kebutuhan user

1. **Menu dedicated** “Sisa cuti + detail penggunaan” — belum ada di Greatday; hanya **2 angka** di Formulir (`Forms.jsx` ← `FormsHubController::stats`).
2. **Saldo awal (`Cuti Diambil`)** belum masuk perhitungan; tanpa ini + aturan cutover, sisa setelah go-live **salah**.
3. **Detail penggunaan awal:** tidak ada rincian tanggal — UI menampilkan **1 entri agregat saldo awal** + entri per pengajuan approved pasca cutover.
4. **Kuota pro-rata** belum ada di backend (`LeaveBalanceService`).
5. **Super Apps V3:** modul HRD perlu **regroup** under `hrd/pengajuan/` (cuti, izin, lembur, koreksi absen, penyesuaian karyawan, rekap saldo cuti).

---

## 3. Model data (usulan)

### 3.1 Tabel `hr_leave_balance_period`

Satu baris = satu periode cuti tahunan aktif per karyawan.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | PK | |
| `karyawan_id` | FK → `master_karyawan.id` | |
| `period_start` | date | |
| `period_end` | date | |
| `quota_days` | unsigned smallint | **hasil rumus** §3.4 (bukan hanya 12) |
| `opening_used_days` | unsigned smallint | dari import Excel |
| `opening_imported_at` | datetime nullable | |
| `opening_source` | string nullable | e.g. `excel_rekap_2026` |
| `notes` | text nullable | |
| `is_active` | bool | periode untuk UI |
| timestamps | | |

**Rumus sisa (runtime):**

```
system_used = sum(hari_kerja cuti tahunan approved, leave_date >= cutover_at, dalam periode)
remaining   = quota_days - opening_used_days - system_used
```

`remaining` tidak disimpan persisten (kecuali cache).

### 3.2 Config cutover

| Key | Contoh | Keterangan |
|-----|--------|------------|
| `greatday.leave_balance_cutover_at` | `2026-04-01 00:00:00` | Pengajuan approved **sebelum** cutover **tidak** dijumlah ke `system_used` (sudah ada di `opening_used_days`) |

### 3.3 Ledger detail (view)

| Sumber | Jenis baris UI | Field |
|--------|----------------|-------|
| Import | `opening_balance` | agregat `opening_used_days`, tanpa tanggal per hari |
| Post cutover | `leave_request` | `hr_requests` + presenter (tanggal, hari kerja, status) |

Opsional fase 2: `hr_leave_balance_ledger` untuk koreksi HRD (alpa, penyesuaian manual).

### 3.4 Rumus kuota otomatis (`LeaveBalanceService::computeQuotaDays`)

**Keputusan HR:** jatah 5/6/9/10 **bukan** input manual per NIK — dihitung sistem.

**Eligible status (hak cuti tahunan & menu saldo):** karyawan dengan `status_karyawan` ∈ **Contract, Permanent, Khusus** (mapping DB — selaraskan label master karyawan; Excel “Pelatihan” / Training / Probation **di luar** kelompok ini kecuali HR ubah keputusan).

**Algoritma (usulan — validasi saat implement dengan sample Excel):**

1. Tentukan `period_start`, `period_end` = `leaveYearBounds(karyawan)`.
2. Jika status **tidak** eligible → `quota_days = 0` (menu boleh tampil dengan penjelasan “tidak berhak”).
3. Jika pada **periode aktif** karyawan sudah **≥ 12 bulan** sejak `tgl_mulai_kerja` (anniversary ke-N dalam periode, bukan bulan pertama join) → `quota_days = 12`.
4. Jika masih **periode cuti pertama** (belum genap 12 bulan sejak join) **dan** status Contract/Khusus/Permanent → **pro-rata** 12 hari kerja terhadap sisa bulan dalam periode anniversary pertama, dibulatkan sesuai kebijakan HR (disarankan **floor/ceil konsisten** — uji agar ISC076–ISC083 match Excel).
5. Saat **reset** ulang tahun kerja → buat periode baru, `quota_days = 12`, `opening_used_days = 0` (kecuali HR import koreksi).

Import Excel: `quota_days` dari file dipakai **hanya validasi** (log warning jika ≠ hasil rumus > toleransi 0).

### 3.5 Relasi existing

```
master_karyawan (nik_karyawan, tgl_mulai_kerja, status_karyawan)
    └── hr_leave_balance_period
    └── hr_requests (TYPE_LEAVE) → hr_leave_detail
```

---

## 4. Import Excel (`Cuti Diambil`)

- Sheet `REKAP SISA CUTI 2026`, baris 5+
- Match `nik_karyawan`
- Upsert `(karyawan_id, period_start)` — idempotent
- Isi **`opening_used_days`** + `notes`; **quota** dari rumus §3.4
- Command: `greatday:import-leave-opening-balance {path}`

---

## 5. API

### 5.1 Greatday (karyawan)

| Slice | Response |
|-------|----------|
| `LeaveBalanceController@summary` | periode, quota, opening_used, system_used, remaining, eligible, reset_label |
| `LeaveBalanceController@usage` | ledger (opening + requests pasca cutover) |

Refactor: `FormsHubService::stats` → `LeaveBalanceService` (satu rumus `sisa_cuti` / `jumlah_cuti`).

**Visibility menu Greatday (`gd_menu`):** karyawan eligible (Contract/Permanent/Khusus); quota bisa 0 (pro-rata / belum genap) — **tetap tampil** halaman saldo dengan penjelasan.

### 5.2 Super Apps V3 (HRD)

| Slice (rencana) | Controller | Kegunaan |
|-----------------|------------|----------|
| `index` / `show` | `HrLeaveBalanceController` (baru) atau perluasan `PermohonanCutiController` | Rekap saldo semua karyawan, drill-down per NIK |
| `export` | sama | Export mirror Excel |
| Trigger import | artisan via endpoint internal / job | HR re-upload tahunan |

Middleware & permission: modul HRD existing (`MenuController` / permission per menu).

---

## 6. UI Greatday (karyawan)

| Item | Nilai |
|------|-------|
| Path menu | `leave-balance` (grup forms/misc — `gd_menu`) |
| Halaman | `greatday/src/pages/Leave/LeaveBalancePage.jsx` |
| UX | Kartu sisa/jatah/terpakai; detail saldo awal + list pengajuan; CTA ajukan cuti |

Stat di **Formulir** harus identik dengan halaman Saldo Cuti.

---

## 7. Super Apps V3 — struktur menu `hrd/pengajuan/`

Routing Super Apps: path menu DB → folder `@pages/{path}/{ComponentName}` (`generateRoutes.js` + `resolvePageComponent.js`).  
**Menu DB dibuat oleh tim HR/ops**; development **menyiapkan folder + komponen** agar path cocok.

### 7.1 Pohon menu target

| Path menu (URL) | Label menu (teks UI) | Komponen / asal kode saat ini | Controller API (existing) |
|-----------------|------------------------|----------------------------------|---------------------------|
| `/hrd/pengajuan/pengajuan-lembur` | Pengajuan Lembur | Pindah/adaptasi `hrd/form-lembur/FormLembur.js` | `LemburController` |
| `/hrd/pengajuan/pengajuan-cuti` | Pengajuan Cuti | Baru atau reuse pola `request/permohonan/permohonan-cuti` untuk antrian HRD | `PermohonanCutiController` / `hr_*` portal |
| `/hrd/pengajuan/pengajuan-izin` | Pengajuan Izin | Pindah/adaptasi `hrd/attandance/pengajuan-izin/PengajuanIzin.js` | `IzinController` / `PermohonanIzinController` |
| `/hrd/pengajuan/pengajuan-perbaikan-absen` | Pengajuan Perbaikan Absen | Pindah/adaptasi `hrd/attandance/koreksi-absen/KoreksiAbsen.js` | portal koreksi absen |
| `/hrd/pengajuan/pengajuan-penyesuaian-karyawan` | **Pengajuan Penyesuaian Karyawan** | Migrasi dari `hrd/permohonan/permohonan-penyesuaian-karyawan/` | adjustment API existing |
| `/hrd/pengajuan/rekap-saldo-cuti` | Rekap Saldo Cuti *(opsional nama)* | Halaman baru datatable + detail | §5.2 |

**Migrasi penyesuaian karyawan (wajib saat fase portal):**

| Dari | Ke |
|------|-----|
| Path lama `hrd/permohonan/permohonan-penyesuaian-karyawan` | `hrd/pengajuan/pengajuan-penyesuaian-karyawan` |
| Teks menu “Permohonan Penyesuaian Karyawan” | **“Pengajuan Penyesuaian Karyawan”** |
| `PermohonanPenyesuaianKaryawan.js` | `PengajuanPenyesuaianKaryawan.js` (rename class + file) |

**Import yang harus di-update** (frontend):

- `src/pages/finance/pengajuan-penyesuaian-gaji/*` → path `hrdTableUtils` / `ModalEvaluationBundle`
- `src/pages/hrd/hris/konseling-karyawan/konselingTableUtils.js`

Redirect route lama (opsional): alias menu lama → path baru agar bookmark tidak putus.

### 7.2 Halaman Rekap Saldo Cuti (HRD)

- Datatable: NIK, nama, divisi, periode, jatah, saldo awal terpakai, terpakai sistem, sisa, status karyawan
- Detail drawer: sama seperti Greatday (saldo awal + line items)
- Aksi: export Excel, (opsional) jalankan ulang validasi rumus vs master join date

### 7.3 Pembagian tanggung jawab

| Pihak | Tugas |
|-------|--------|
| HR/Admin menu DB | Buat parent **HRD → Pengajuan** + child path §7.1 |
| Dev frontend | Folder `src/pages/hrd/pengajuan/*`, rename/migrate penyesuaian karyawan |
| Dev backend | Saldo cuti + API HRD + cutover config |

---

## 8. Fase implementasi

| Fase | Scope |
|------|-------|
| **A — Saldo inti** | Migration `hr_leave_balance_period`, `LeaveBalanceService` (rumus §3.4 + cutover §3.2), import Excel, API Greatday, halaman Saldo Cuti, selaraskan `FormsHubService::stats` |
| **A2 — Portal HRD** | Halaman `rekap-saldo-cuti`, API HRD, migrasi folder `hrd/pengajuan/*` + penyesuaian karyawan (§7) — menu DB oleh user |
| **B — Validasi pengajuan** | [x] H-14, max 3 hari/30 hari, libur perusahaan, sisa + pending; sync 15 jenis cuti khusus |
| **C — Jenis lanjutan** | [x] Unpaid, PHL, kuota cuti khusus per jenis |
| **D — Alpa & ledger HRD** | [x] Potong saldo absensi; koreksi manual |

Sync **15 jenis cuti khusus** ↔ `hr_special_leave_type` — paralel fase A/B.

---

## 9. Checklist teknis

### Backend (LUMEN-V3)

- [ ] Migration `hr_leave_balance_period`
- [ ] Config `leave_balance_cutover_at`
- [ ] `LeaveBalanceService` (quota §3.4, summary, usage, eligibility)
- [ ] Refactor `FormsHubService` stats
- [ ] `LeaveBalanceController` (Greatday) + `HrLeaveBalanceController` (portal)
- [ ] Command `greatday:import-leave-opening-balance`
- [ ] Validasi rumus: 4 NIK pro-rata + 10 random vs Excel
- [ ] `gd_menu` Greatday: Saldo Cuti

### Frontend Greatday

- [ ] `LeaveBalancePage.jsx`, route, i18n

### Frontend Super Apps (`frontend`)

- [ ] `src/pages/hrd/pengajuan/pengajuan-penyesuaian-karyawan/` (+ rename komponen & teks UI)
- [ ] `pengajuan-lembur`, `pengajuan-izin`, `pengajuan-perbaikan-absen`, `pengajuan-cuti` (wrap/move dari path lama)
- [ ] `rekap-saldo-cuti` (HRD datatable)
- [ ] Perbaiki import finance/konseling ke path baru
- [ ] *(User)* Insert/update menu DB path §7.1

### QA

- [ ] Import Excel resmi
- [ ] Sisa 10 NIK vs Excel
- [ ] Cuti approved setelah cutover mengurangi sisa; sebelum cutover tidak double
- [ ] Karyawan Contract/Permanent/Khusus lihat menu; status lain sesuai kebijakan
- [ ] Bookmark `hrd/permohonan/...` (redirect jika ada)

### Dokumen

- [ ] Link dari `GREATDAY-HR-DEVELOPMENT.md`
- [ ] Prosedur reset periode tahun depan

---

## 10. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| `tgl_mulai_kerja` ≠ Excel | Laporan mismatch import; perbaiki master data |
| Double count | Cutover §3.2 + opening hanya dari Excel |
| Rumus pro-rata ≠ Excel | Unit test 4 NIK referensi; tuning bulat hari dengan HR |
| Path menu baru vs folder lama | Migrasi §7.1 sebelum go-live menu; redirect sementara |
| Menu DB belum dibuat user | Halaman siap; route 404 sampai menu di-insert |

---

## 11. Keputusan HR (disetujui)

| # | Pertanyaan | Keputusan |
|---|------------|-----------|
| 1 | **Cutover / Cuti Diambil** — pengajuan lama sudah termasuk? | **Ya.** Semua pemakaian sebelum go-live sudah masuk kolom **Cuti Diambil** Excel → `opening_used_days`. `system_used` hanya pengajuan **setelah** `leave_balance_cutover_at`. |
| 2 | **Karyawan &lt; 1 tahun / eligibility menu** | Yang **berhak cuti tahunan**: status **Contract, Permanent, Khusus**. Menu saldo **ditampilkan** untuk status tersebut; kuota mengikuti **rumus pro-rata** (§3.4) bila belum genap periode penuh — bukan disembunyikan. |
| 3 | **Jatah 5/6/9/10** | **Rumus otomatis** (pro-rata), bukan override manual per import. Excel dipakai validasi. |
| 4 | **Struktur Super Apps** | Modul under **`hrd/pengajuan/`**: lembur, cuti, izin, perbaikan absen (+ rekap saldo cuti). **`hrd/permohonan/permohonan-penyesuaian-karyawan`** → **`hrd/pengajuan/pengajuan-penyesuaian-karyawan`**; teks menu **“Pengajuan Penyesuaian Karyawan”**. Entri menu child **dibuat user** di DB. |

**Masih open (opsional):** peran HRD verifikasi vs approval formal di alur (sheet Alur).

---

**Next step:** implement **Fase A** (backend saldo + Greatday) paralel persiapan folder **Fase A2** (`hrd/pengajuan/`) — setelah menu DB dari user, aktifkan route portal.
