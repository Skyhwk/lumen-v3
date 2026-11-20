# Roadmap & Ketentuan Baru — Claim Fee External (Unified Sales + Finance)

> **Dokumen sementara (spesifikasi perencanaan)**  
> **Salinan repo:** `D:\PHP\LUMEN-V3\docs\_temp\CLAIM_FEE_EXTERNAL_ROADMAP_DAN_KETENTUAN.md`  
> **Mirror frontend:** `D:\JAVASCRIPT\REACT-JS\frontend\docs\_temp\CLAIM_FEE_EXTERNAL_ROADMAP_DAN_KETENTUAN.md`  
> Tanggal: 2026-11-19 · **Rev. 1.3** — Q1–Q7 + **notifikasi tiap proses**  
> Status: **Fase 0 in progress** — deliverable teknis + notifikasi; menunggu sign-off (D0.7)

---

## Daftar isi

1. [Ringkasan eksekutif](#1-ringkasan-eksekutif)  
2. [Prinsip desain](#2-prinsip-desain)  
3. [As-Is](#3-kondisi-saat-ini-as-is)  
4. [To-Be — satu menu Sales](#4-target-to-be--satu-menu-sales)  
5. [Sudut pandang & hak akses](#5-sudut-pandang--hak-akses)  
6. [Empat tab](#6-struktur-tab-di-menu-sales)  
7. [Status & pipeline](#7-model-status--pipeline-baru)  
8. [Approval chain dinamis](#8-approval-chain-dinamis)  
9. [Detail timeline](#9-tampilan-detail-timeline-pipeline)  
10. [Finance](#10-finance--penyesuaian-minimal)  
11. [Fee sales (out of scope)](#11-perubahan-data--fee-sales-out-of-scope)  
12. [Arsitektur teknis](#12-arsitektur-teknis)  
13. [Migrasi](#13-migrasi--kompatibilitas)  
14. [Roadmap fase](#14-roadmap-implementasi)  
15. [Ketentuan bisnis](#15-ketentuan-bisnis-baru)  
16. [UAT](#16-uat)  
17. [Risiko](#17-risiko)  
18. [Keputusan Q1–Q7 (lengkap)](#18-keputusan-q1q7-lengkap)  
19. [Notifikasi ke pengaju & approver](#19-notifikasi-portal)

---

## 1. Ringkasan eksekutif

Redesign menu **Claim Fee External** menjadi satu halaman ber-tab dengan approval berjenjang (SPV → Manager → Senior Manager **jika ada**), resolver hirarki dinamis, rekap SM all data, SPV approval-only, **tanpa status DRAFT**, edit sampai **approve atasan tingkat 1**, reject finance = **claim baru**, dan **tanpa perubahan fee sales**.

**Estimasi implementasi:** 7–11 minggu (5 fase + spesifikasi).

---

## 2. Prinsip desain

1. Satu menu Sales — tab = f(grade, AksesMenu).  
2. Pisah `sales_workflow_status` / `finance_status`; sync legacy `status_pembayaran`.  
3. Tahap approval disimpan sebagai **stage**, approver di-**resolve** saat giliran.  
4. SPV tidak mengajukan; hanya Persetujuan.  
5. Staff **tanpa** tab Rekap (Q2).  
6. SM rekap **all data** (Q1).  
7. **Tidak ada DRAFT**; pengajuan langsung `SUBMITTED` / `PENDING_*` — edit selama belum approve tingkat 1 (Q5).  
8. Rantai sales **maksimal SM**; **Director tidak**; jika tidak ada SM di hirarki, berakhir di **Manager** (Q6).  
9. **Fee sales tidak disentuh** (Q3).  
10. Reject finance → user buat **claim baru** (Q4).  
11. **Notifikasi portal** pada setiap lompatan status — **pengaju (`sales_id`) wajib dapat notice** (§19).

---

## 3. Kondisi saat ini (As-Is)

| Path | Controller |
|------|------------|
| `/sales/claim-fee-external` | `ClaimFeeExternalController` |
| `/sales/approval-fee-external` | `ApprovalFeeExternalController` |
| `/finance/tax/claim-fee-external` | `ClaimFeeExternalTaxController` |
| `/finance/expense/claim-fee-external` | `ClaimFeeExternalExpenseController` |

Legacy: satu flag `is_approved_manajer`, status campur sales/finance.

---

## 4. Target (To-Be) — satu menu Sales

| Item | Nilai |
|------|--------|
| Path | `/sales/claim-fee-external` |
| Deprecated | `/sales/approval-fee-external` → `?tab=persetujuan` |

### Sudut pandang

| Peran | Tab | Rekap |
|-------|-----|-------|
| Staff/Sales | Pengajuan, Menunggu Finance | **Tidak** (Q2) |
| SPV | Hanya Persetujuan | Tidak |
| Manager | Pengajuan*, Persetujuan, Menunggu Finance, Rekap | Bawahan |
| Senior Manager | Tab relevan | **All data** (Q1) |

---

## 5. Sudut pandang & hak akses

| Aksi | Staff | SPV | MGR | SM |
|------|:-----:|:---:|:---:|:--:|
| Create/edit/delete claim | ✓† | ✗ | △ | △ |
| Approve/reject sales | — | ✓* | ✓* | ✓* |
| Tab Rekap | **✗** | ✗ | ✓ | ✓ |

**† Edit/delete (Q5):** selama **belum ada approve** pada **atasan tingkat 1** (tahap pertama dalam `required_sales_stages` setelah submit). Setelah approve tingkat 1 → edit claim **ditutup** (kecuali alur `REJECTED_SALES` + submit ulang sebagai revisi baru atau reset workflow — lihat §15).

**\* SPV (Q7):** setelah approve, hilang dari antrian; detail read-only ke Manager/SM.

**Staff (Q2):** tab Rekap tidak dirender; `rekapIndex` → 403.

---

## 6. Empat tab

| Tab | Staff | SPV | MGR | SM |
|-----|:-----:|:---:|:---:|:--:|
| Pengajuan | ✓ | ✗ | ✓* | ✓* |
| Persetujuan | ✗ | ✓ | ✓ | ✓ |
| Menunggu Finance | ✓ | ✗ | ✓ | ✓ |
| Rekap | **✗** | ✗ | ✓ | ✓ (all) |

---

## 7. Model status & pipeline baru

### Tidak ada status `DRAFT` (Q5)

- Simpan pengajuan → langsung masuk workflow (`SUBMITTED` atau `PENDING_<tahap_pertama>`).  
- **Edit diperbolehkan** selama **log approval belum berisi `APPROVED` untuk tahap indeks 0** (atasan tingkat 1).  
- UI: tombol Edit pada tab Pengajuan mengikuti flag `can_edit` dari API.

### `sales_workflow_status` (tanpa DRAFT)

| Kode | Label UI |
|------|----------|
| SUBMITTED | Diajukan |
| PENDING_SPV | Menunggu SPV |
| PENDING_MANAGER | Menunggu Manager |
| PENDING_SENIOR_MANAGER | Menunggu Senior Manager |
| APPROVED_SALES | Disetujui Sales |
| REJECTED_SALES | Ditolak Sales |
| CANCELLED | Dibatalkan (soft cancel submitter, sebelum finance) |

### `finance_status`

WAITING_TAX → TAX_IN_PROGRESS → READY_TO_PAY → PAID | REJECTED_FINANCE

### Reject finance (Q4)

- `REJECTED_FINANCE` → record **terminal** untuk claim tersebut.  
- Sales **wajib membuat claim baru** (no reuse edit pada record yang sudah masuk finance).  
- Record lama tetap di DB (audit) dengan `is_active` / status terminal.

---

## 8. Approval chain dinamis

### Tahap sales (Q6)

1. Grade approver yang diizinkan: **SUPERVISOR → MANAGER → SENIOR MANAGER** saja.  
2. **Director / level di atas SM:** **tidak** masuk rantai (selaras `BLOCKED_GRADES` di `DynamicApprovalChainService`).  
3. `required_sales_stages` dihitung dari hirarki **penjual** (`sales_id`):
   - Kumpulkan tahap yang ada di organisasi (bisa 1–3 tahap).  
   - **Jika tidak ada SM** di path hirarki → rantai **berakhir di Manager** (`APPROVED_SALES` setelah Manager approve).  
   - **Jika ada SM** → wajib tahap SM sebelum `APPROVED_SALES`.  
4. Tidak menyimpan daftar orang di awal; resolver per tahap + log.

### Atasan tingkat 1 (Q5)

- = elemen **pertama** dalam `required_sales_stages` (contoh: `SPV`, atau `MANAGER` jika SPV di-skip untuk submitter tertentu).  
- **Sebelum** log `APPROVED` untuk stage tersebut → `can_edit = true` untuk submitter (staff penjual).

### Service

- `ClaimFeeExternalSalesWorkflowService`  
- Wrapper tahap: map `orderedApproverIds` → stages {SPV,MGR,SM}, **truncate** setelah SM atau di MANAGER jika tidak ada SM.

---

## 9. Detail timeline

Timeline wajib; tahap yang tidak ada di org (mis. tidak ada SM) **tidak ditampilkan** sebagai pending — pipeline hanya tahap yang berlaku untuk claim tersebut.

---

## 10. Finance

Gate Tax: `APPROVED_SALES`.  
Reject Tax/Expense → `REJECTED_FINANCE` → **tidak** boleh `store` update; arahkan **claim baru** (Q4).

---

## 11. Perubahan data & fee sales (out of scope)

**Q3:** Tidak mengubah `FeeSalesDaily`, `CalculateFeeSales`, atau penggunaan `ClaimFeeExternal` di hitungan fee.

---

## 12. Arsitektur teknis

### Backend — aturan implementasi kunci

| Rule | Implementasi |
|------|----------------|
| Q5 `can_edit` | `!exists approval_logs where stage = required_sales_stages[0] and action = APPROVED` |
| Q4 | `store` with `id` ditolak jika `finance_status` ∈ {WAITING_TAX..PAID} atau `REJECTED_FINANCE` |
| Q6 | `buildRequiredStages()` max SM, drop director, no SM → stages end at MANAGER |
| Q2 | `rekapIndex` grade staff → 403 |
| Q7 | `store` grade SPV → 403 |

### Frontend

- `getVisibleTabs()`: staff tanpa `rekap`.  
- Modal edit disabled when `!can_edit`.

---

## 13. Migrasi

Data lama tanpa konsep multi-stage: log sintetis + `required_sales_stages` default `["MANAGER"]` atau rekonstruksi dari status.

---

## 14. Roadmap implementasi

**Fase 0 (aktif):** lihat `CLAIM_FEE_EXTERNAL_FASE0_CHECKLIST.md` + `CLAIM_FEE_EXTERNAL_NOTIFIKASI.md`.

| Fase | Notifikasi |
|------|------------|
| 1 | `ClaimFeeExternalNotificationService` + event sales (submit/approve/reject/approved_sales) |
| 2 | Deep link `?tab=&detail=` dari push |
| 3 | Event finance di Tax/Expense wrapper |
| 3b | Reminder due date ke pengaju (opsional) |

Fee sales tetap out of scope (Q3).

---

## 15. Ketentuan bisnis baru

### Pengajuan & edit (Q5)

1. Tidak ada status DRAFT.  
2. Submit = masuk antrian atasan tingkat 1.  
3. **Edit/hapus** oleh penjual pemilik claim **hanya** sebelum atasan tingkat 1 **approve**.  
4. Setelah approve tingkat 1: tidak ada edit; hanya reject downstream atau tunggu finance.

### Staff (Q2)

5. **Tidak** ada tab Rekap dan tidak export rekap.

### Rantai approval (Q6)

6. Maksimal **Senior Manager** jika ada di hirarki.  
7. **Tidak ada Director** dalam rantai.  
8. Tanpa SM di hirarki → setelah **Manager** approve = `APPROVED_SALES`.

### Finance reject (Q4)

9. Tolak/edit claim yang sudah `REJECTED_FINANCE` atau sudah proses finance.  
10. Solusi bisnis: **buat claim fee external baru**.

### SPV, SM rekap, fee sales

11–13. (Q7, Q1, Q3 — tidak berubah dari rev. 1.1.)

### Reject sales

14. `REJECTED_SALES` → penjual boleh perbaiki via **edit + submit ulang** hanya jika belum pernah lolos tingkat 1, ATAU kebijakan reset workflow: setelah reject, izinkan edit penuh dan reset `required_sales_stages` / hapus log tahap yang ditolak (detail di ERD — default: **reset workflow**, log reject tetap).

---

## 16. UAT

| ID | Skenario |
|----|----------|
| U2a | Staff **tidak melihat** tab Rekap |
| U4a | Hirarki tanpa SM: approve Manager → langsung APPROVED_SALES |
| U5 | Q7 SPV (set antrian) |
| U12 | Edit claim **sebelum** approve SPV (tingkat 1) — sukses |
| U13 | Edit claim **setelah** approve SPV — ditolak |
| U14 | Finance reject → tidak bisa edit; harus claim baru |
| U15 | Director tidak muncul di timeline |

(+ U1–U11 dari rev. 1.1)

---

## 17. Risiko

| Risiko | Mitigasi |
|--------|----------|
| Beda definisi “tingkat 1” jika skip SPV | Dokumentasi: tingkat 1 = `required_sales_stages[0]` per claim |
| User ingin edit setelah MGR approve | Locked by policy Q5 — komunikasi training |

---

## 18. Keputusan Q1–Q7 (lengkap)

| ID | Keputusan final |
|----|-----------------|
| **Q1** | Rekap SM = **ALL DATA** perusahaan (termasuk SM di luar divisi sales). |
| **Q2** | Staff **tidak** boleh tab Rekap. |
| **Q3** | **Jangan sentuh** perhitungan fee sales dalam proyek ini. |
| **Q4** | Reject finance → **claim baru** (record lama terminal, tidak diedit). |
| **Q5** | **Tidak ada DRAFT**; selama **belum di-approve atasan tingkat 1** claim masih **boleh diedit** (oleh penjual pemilik). |
| **Q6** | **Tidak** Director di rantai; **maksimal SM** bila ada di hirarki; **jika tidak ada SM** → rantai berakhir di **Manager**. |
| **Q7** | SPV tidak mengajukan; hanya approve; setelah approve hilang dari antrian; tanpa Menunggu Finance/Rekap. |

**Tidak ada keputusan terbuka** untuk redesign CFE v1.

---

## 19. Notifikasi (portal)

### Kebijakan

- Setiap perubahan **`sales_workflow_status`** atau **`finance_status`** yang memengaruhi pengaju → **push + inbox** ke `sales_id` (penjual pengaju).  
- Approver menerima notifikasi **`CFE_APPROVAL_PENDING`** hanya saat claim **masuk giliran** mereka.  
- Detail event, judul/body, deep link: **`CLAIM_FEE_EXTERNAL_NOTIFIKASI.md`**.  
- Teknis: reuse **`FirebaseService`** (sama seperti lembur); gagal kirim FCM **tidak** membatalkan transaksi.  
- Implementasi terpusat di **`ClaimFeeExternalNotificationService`**, dipanggil dari workflow + finance.

### Ringkas event ke **pengaju**

| Tahap | Notifikasi |
|-------|------------|
| Submit | Terkirim + menunggu atasan 1 |
| Tiap approve sales | Disetujui tahap X → lanjut Y |
| Rantai sales selesai | Disetujui sales → finance |
| Reject sales | Ditolak + alasan |
| Tax / PPh / transfer / reject finance | Satu notifikasi per lompatan (§3.3 dokumen notifikasi) |

UAT: **§J** di `CLAIM_FEE_EXTERNAL_UAT_MATRIX.md`.

---

## Lampiran A — Deprecation

`/sales/approval-fee-external` → redirect tab Persetujuan.

## Lampiran B — Referensi kode

- `DynamicApprovalChainService` (max SM, block Director)  
- `GetBawahan`, `AtasanApprovalScope`  
- Implementasi tahap: `ClaimFeeExternalSalesWorkflowService` (baru)
- Notifikasi: `ClaimFeeExternalNotificationService` + `FirebaseService`

## Lampiran C — Notifikasi

`CLAIM_FEE_EXTERNAL_NOTIFIKASI.md`

---

**Versi:** 1.3  
**Changelog 1.3:** §19 notifikasi tiap proses; pengaju wajib notice; service + fase 1–3