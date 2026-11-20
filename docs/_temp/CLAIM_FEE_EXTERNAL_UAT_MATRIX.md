# Matriks UAT — Claim Fee External (Unified)

> Fase 0 deliverable · Jalankan di **staging** setelah Fase 2–3 selesai (beberapa case butuh UI).

**Legenda:** P = Pass, F = Fail, N/A, Catatan

---

## A. Tab & visibility

| ID | Skenario | Langkah | Ekspektasi | Staff | SPV | MGR | SM |
|----|----------|---------|------------|:-----:|:---:|:---:|:--:|
| A1 | Buka menu | Login → Claim Fee External | Tab sesuai matriks roadmap | P | P | P | P |
| A2 | Staff tanpa Rekap | Lihat header tab | Tab Rekap **tidak ada** | P | — | — | — |
| A3 | SPV hanya Persetujuan | Login SPV | Hanya tab Persetujuan | — | P | — | — |
| A4 | Default tab | Buka menu | Staff→Pengajuan, SPV→Persetujuan, SM→Rekap | P | P | — | P |
| A5 | Redirect menu lama | Buka `/sales/approval-fee-external` | Redirect `?tab=persetujuan` | P | P | P | P |

---

## B. Pengajuan (sales staff)

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| B1 | Tambah claim order lunas | 201, status PENDING_* / SUBMITTED, `can_edit=true` |
| B2 | Nominal > biaya_akhir | 422/toast gagal |
| B3 | Edit sebelum approve tingkat 1 | Berhasil |
| B4 | Edit setelah approve tingkat 1 | 403, tombol edit hilang |
| B5 | Hapus sebelum approve tingkat 1 | Soft delete |
| B6 | SPV coba POST store | 403 |
| B7 | Multi-kontrak pilih periode | Data order sesuai periode |
| B8 | Due date lewat | Baris merah di tabel |

---

## C. Persetujuan sales

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| C1 | SPV approve tahap SPV | Log APPROVED, status ke tahap berikutnya |
| C2 | SPV antrian hilang pasca approve | Row tidak di `persetujuanIndex` SPV |
| C3 | SPV detail pasca approve (jika ada) | Read-only, timeline Manager/SM |
| C4 | MGR approve | Lanjut SM atau APPROVED_SALES |
| C5 | SM approve | `APPROVED_SALES`, `finance_status=WAITING_TAX` |
| C6 | Tanpa SM di hirarki | Setelah MGR approve → APPROVED_SALES |
| C7 | Director tidak di timeline | Tidak muncul step Director |
| C8 | Reject sales + alasan | REJECTED_SALES, remarks tersimpan |
| C9 | Reject tanpa alasan | 422 |
| C10 | User bukan resolver klik approve | 403 |
| C11 | Turnover SPV | Approver baru di resolver; antrian benar |

---

## D. Menunggu Finance

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| D1 | Staff lihat claim sendiri approved | Tampil read-only |
| D2 | SPV akses tab | Tab tidak ada |
| D3 | SPV panggil API menungguFinanceIndex | 403 |
| D4 | MGR lihat bawahan | Scope bawahan |
| D5 | SM lihat all | Data lintas divisi |

---

## E. Rekap

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| E1 | Staff akses rekap | Tab absent + API 403 |
| E2 | SPV akses rekap | 403 |
| E3 | MGR rekap | Hanya bawahan |
| E4 | SM rekap | All data perusahaan |
| E5 | Export excel SM | File terbentuk (jika permission) |

---

## F. Finance (Tax / Expense)

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| F1 | Claim belum APPROVED_SALES | Tidak di antrian Tax |
| F2 | Tax approve | finance_status TAX_IN_PROGRESS, legacy PROCESSED |
| F3 | setPPH | READY_TO_PAY, potongan terisi |
| F4 | Expense transfer | PAID, tanggal_pembayaran |
| F5 | Reject finance | REJECTED_FINANCE |
| F6 | Edit claim setelah reject finance | 409 — harus claim baru |
| F7 | Claim baru setelah F5 | Boleh create record baru |

---

## G. Detail & pipeline

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| G1 | Drawer timeline | Urutan tahap sesuai `required_sales_stages` |
| G2 | Tahap skipped (Q6) | Tidak tampil sebagai pending |
| G3 | Expected approver nama | Nama resolve hari ini |
| G4 | Tombol approve di drawer | Sesuai `can_approve` |

---

## H. Migrasi data

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| H1 | Sample lama WAITING not approved | sales_workflow_status masuk akal |
| H2 | Sample lama TRANSFER | PAID + pipeline finance hijau |
| H3 | Log sintetis manajer | Timeline tidak kosong |

---

## I. Non-regression

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| I1 | Fee sales calculation | **Identik** sebelum deploy (Q3) |
| I2 | Tax upload PDF | Masih jalan |
| I3 | Legacy finance user workflow | Tidak breaking |

---

## J. Notifikasi (pengaju & approver)

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| N1 | Staff submit | Pengaju: terkirim; approver tingkat 1: antrian |
| N2 | SPV approve | Pengaju: tahap disetujui; MGR: antrian baru |
| N3 | Reject sales | Pengaju: ditolak + alasan di body |
| N4 | APPROVED_SALES | Pengaju: lolos sales + masuk finance |
| N5 | Tax setPPH → transfer | Pengaju: siap bayar → sudah transfer |
| N6 | Reject finance | Pengaju: ditolak finance + arahan claim baru |
| N7 | Klik notifikasi | Deep link tab + drawer `detail={id}` |
| N8 | FCM gagal / tanpa token | Inbox DB pengaju tetap ada |

Detail event: `CLAIM_FEE_EXTERNAL_NOTIFIKASI.md`.

---

## Sign-off UAT

| Role | Nama | Tanggal | Tanda |
|------|------|---------|-------|
| Sales staff | | | |
| SPV | | | |
| Manager | | | |
| Senior Manager | | | |
| Finance Tax | | | |
| Finance Expense | | | |
| QA | | | |