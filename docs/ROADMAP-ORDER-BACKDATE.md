# Roadmap — Order Backdate

> **Versi dokumen:** 1.0  
> **Tanggal:** 9 November 2026  
> **Status:** Fase 1 live (pilot AIR + EMISI)  
> **Scope:** LUMEN-V3 backend + React frontend (`frontend`)

Dokumen ini mencatat **skenario bisnis**, **yang sudah jalan**, dan **urutan kerja berikutnya** supaya tidak lupa saat melanjutkan fitur.

---

## 1. Ringkasan bisnis

**Order backdate:** alur operasional tetap “hari ini” (QT → order → sampling → LHP), setelah selesai **tanggal di backdate** ke tanggal yang diminta (mis. 26 Jun 2026) untuk penawaran, order, jadwal, lapangan, dokumen sampling, LHP, dan QR.

**Prinsip produk (disepakati):**

| Prinsip | Keterangan |
|--------|------------|
| Satu menu | **Order Backdate** — kelola per paket per `no_order` |
| Simpan per step | Bukan satu tombol apply all; tiap tab disimpan sendiri |
| Arsip asli | Snapshot per step saat pertama kali simpan |
| Mode audit | Toggle **display (backdate)** ↔ **audit (asli)** untuk step yang sudah `saved` |
| Dokumen | Setelah simpan tanggal, regenerate dokumen/QR (otomatis via queue) |
| Gate LHP | Wajib `order_berjalan` + semua LHP release (`status_selesai`) sebelum buat paket / simpan step |

---

## 2. Arsitektur singkat

```
┌─────────────────────────────────────────────────────────────┐
│  Frontend: laporan/order-backdate (OrderBackdate.js)        │
│  • List paket • Buat paket • Modal stepper 7 tab            │
└───────────────────────────┬─────────────────────────────────┘
                            │ X-Slice: OrderBackdateController
┌───────────────────────────▼─────────────────────────────────┐
│  order_backdate_packages / _steps / _audit_logs             │
│  OrderBackdateFieldResolver  → manifest field per domain    │
│  OrderBackdateStepApplier    → DB + snapshot + toggle audit   │
│  OrderBackdateDocumentRegenerator → queue regenerate        │
│  OrderBackdateRegenerateStepJob (queue)                     │
└─────────────────────────────────────────────────────────────┘
```

**7 step key:** `quotation` | `order` | `schedule` | `field_data` | `sampling_doc` | `lhp` | `qr`

**Status paket:** `draft` → `partial` → `completed` (semua step `saved`)

**Status dokumen per step:** `not_required` | `pending` | `queued` | `done` | `failed`

---

## 3. Sudah selesai (v1 — 9 Nov 2026)

### 3.1 Infrastruktur

- [x] Migration tabel: `order_backdate_packages`, `order_backdate_steps`, `order_backdate_audit_logs`
- [x] Menu **Order Backdate** (grup **Laporan**, setelah **Tracking Order**) + salin akses dari Tracking Order
- [x] Model + API: index, lookup, create, show, previewStep, saveStep, toggleViewMode, markDocumentRegenerated
- [x] Frontend: list DataTable, buat paket, modal 7 tab, toggle audit
- [x] Gate eligibility: cek `order_berjalan` + LHP release penuh (`OrderBackdateEligibilityService`)

### 3.2 Manifest tanggal (pilot)

| Domain | Cakupan v1 |
|--------|------------|
| Umum | `order_header` (penawaran, order), `request_quotation` / `request_quotation_kontrak_H`, `jadwal.tanggal`, `persiapan_sampel_header.tanggal_sampling` |
| **AIR** | `lhps_air_header`: terima, sampling, `tanggal_lhp`; QR `LHP` / file LHP |
| **EMISI** | `lhps_emisi_header`, `lhps_emisic_header`, `lhps_emisi_isokinetik_header` (sampling/terima/tugas + `tanggal_lhp`); QR `LHP_EMISI`, `LHP_EMISI_C`, `LHP_EMISI_ISOKINETIK` |
| Deteksi manifest | `OrderBackdateDomainProfile`: `status_sampling` + `sub_kategori` (air / emisi) |

### 3.3 Auto regenerate (queue) — per step simpan

| Step | Job / layanan | Catatan |
|------|----------------|---------|
| Penawaran | `RenderNonKontrak::renderHeader` / `RenderKontrak::renderHeader` | Sinkron PDF QT |
| Order | Log only | Belum ada PDF order terpusat di codebase |
| Dokumen sampling | `GenerateDocumentSamplingJob` | Butuh `sampling_plan` aktif |
| LHP | `RenderLhp` (`downloadLHP`, `downloadLHPFinal`) per header | AIR + EMISI |
| QR | `GenerateQrDocumentLhp::update` / `insert` | Ikut `tanggal_lhp` |

**Operasional wajib:** queue worker harus jalan (`php artisan queue:work` atau worker setara di server).

### 3.4 File referensi utama

| Area | Path |
|------|------|
| Roadmap | `docs/ROADMAP-ORDER-BACKDATE.md` (file ini) |
| Controller | `app/Http/Controllers/api/OrderBackdateController.php` |
| Resolver / domain | `app/Services/OrderBackdate/*` |
| Job | `app/Jobs/OrderBackdateRegenerateStepJob.php` |
| Frontend | `frontend/src/pages/laporan/order-backdate/` |
| Menu migration | `database/migrations/2026_11_09_100100_add_order_backdate_menu.php` |

---

## 4. Roadmap fase berikutnya

Prioritas disusun mengikuti diskusi: **perluas manifest dulu**, lalu **perhalus dokumen & UX**, lalu **governance & laporan**.

### Fase 2 — Manifest UDARA & PADATAN (prioritas berikutnya)

| ID | Task | Deliverable | Status |
|----|------|-------------|--------|
| 2.1 | Audit tabel LHP udara (ambient, LK, kebisingan, …) | Daftar kolom tanggal per `status_sampling` / `kategori_*` | ⬜ |
| 2.2 | `OrderBackdateLhpLocator` + resolver untuk udara | Tab field_data / lhp / qr terisi | ⬜ |
| 2.3 | Audit tabel LHP padatan / mikrobiologi / swab | Idem | ⬜ |
| 2.4 | Regenerator: `RenderLhp` / `LhpTemplate` branch padatan | Queue LHP padatan | ⬜ |
| 2.5 | Uji 1 order nyata per tipe (checklist QA) | Sheet uji di §7 | ⬜ |

### Fase 3 — Dokumen & operasional (lanjutan A)

| ID | Task | Deliverable | Status |
|----|------|-------------|--------|
| 3.1 | PDF **order** (jika ada path generate di modul sales) | Step order bukan “log only” | ⬜ |
| 3.2 | Regenerate **jadwal** (opsional: `RenderSamplingPlan` / jadwal doc) | Step schedule `document_status` | ⬜ |
| 3.3 | Idempotent regenerate (cek hash / skip jika tidak ada perubahan) | Kurangi beban queue | ⬜ |
| 3.4 | Retry policy + dead-letter / notifikasi jika `failed` | Email/Telegram ke admin | ⬜ |
| 3.5 | Polling status `doc:*` di modal (tanpa refresh manual) | UX | ⬜ |

### Fase 4 — Governance, audit, keamanan

| ID | Task | Deliverable | Status |
|----|------|-------------|--------|
| 4.1 | Role ketat: hanya grup tertentu bisa buat paket & toggle audit | Middleware / akses menu | ⬜ |
| 4.2 | Field **alasan** wajib + lampiran (opsional) | Validasi API | ⬜ |
| 4.3 | Satu paket aktif per `no_order` (hard rule + UI) | Cegah tabrakan snapshot | ⬜ |
| 4.4 | Log immutable / export audit untuk compliance | `order_backdate_audit_logs` | ⬜ |
| 4.5 | Setelah **invoice** / lock finance — blok backdate atau approval khusus | Business rule | ⬜ |

### Fase 5 — Laporan & data warehouse

| ID | Task | Deliverable | Status |
|----|------|-------------|--------|
| 5.1 | Kebijakan: laporan SMS/tracking pakai tanggal **display** atau **asli** | Config global | ⬜ |
| 5.2 | Flag di export / dashboard “termasuk backdate” | Transparansi audit | ⬜ |
| 5.3 | Customer portal & validasi QR konsisten dengan mode display | E2E test | ⬜ |

### Fase 6 — Nice to have

| ID | Task | Status |
|----|------|--------|
| 6.1 | “Isi dari tanggal acuan” (anchor LHP → offset hari ke step lain) | ⬜ |
| 6.2 | Bulk backdate (multi order) dengan approval | ⬜ |
| 6.3 | CLI: `order-backdate:restore {package_id}` untuk support | ⬜ |

---

## 5. Keputusan bisnis yang masih terbuka

Centang / isi saat rapat tim:

- [ ] Apakah **nomor dokumen** (no LHP, no order) **tidak boleh** diubah — hanya tanggal? (asumsi v1: **hanya tanggal**)
- [ ] Paket `completed` = **semua 7 step wajib** atau subset? (v1: semua 7)
- [ ] Siapa yang **boleh lihat mode audit** selain tim audit?
- [ ] Backdate **setelah invoice** — allowed atau tidak?
- [ ] Partial order (subset sampel) — satu paket per header atau per `no_sampel`?

---

## 6. Skenario uji regresi (minimum)

Gunakan order uji di staging sebelum production.

1. **AIR:** buat paket → simpan quotation + lhp + qr → cek PDF/QR → toggle audit → kembali display.
2. **EMISI bergerak / cerobong / isokinetik:** minimal satu order per jenis header.
3. Queue mati → simpan step → pastikan `document_status = queued` → nyalakan worker → menjadi `done`.
4. Simpan step di mode **audit** → harus ditolak (422).
5. Dua user: jangan buat dua paket `draft/partial` untuk `no_order` sama (409).

---

## 7. Changelog dokumen

| Versi | Tanggal | Perubahan |
|-------|---------|-----------|
| 1.0 | 2026-11-09 | Draft awal: v1 AIR+EMISI, queue regenerate, struktur fase 2–6 |
| 1.1 | 2026-11-09 | Gate order_berjalan + semua LHP harus release |

---

## 8. Cara update roadmap ini

Saat menyelesaikan task:

1. Ubah ⬜ → ✅ di tabel fase terkait.
2. Tambah baris di **§3** jika fitur baru masuk production.
3. Bump **Versi dokumen** + baris **§7 Changelog**.
