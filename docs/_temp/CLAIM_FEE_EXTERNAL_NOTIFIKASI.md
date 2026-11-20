# Notifikasi — Claim Fee External (Unified)

> **Rev. 1.0** · Fase 0 extension  
> Prinsip: **pengaju (sales_id / penjual) selalu dapat update** pada setiap lompatan status; approver dapat notifikasi **antrian** saat giliran.

---

## 1. Channel & infrastruktur

| Channel | Implementasi (reuse) | Penerima utama |
|---------|----------------------|----------------|
| Inbox portal + FCM | `App\Services\Greatday\FirebaseService::sendNotifications()` | Karyawan (portal user id) |
| MQTT refresh bell | Otomatis dari `FirebaseService` | Frontend notifikasi real-time |
| Deep link web | `url` di payload push | Tab + detail claim |

**Referensi pola:** `LemburController::sendNotificationLembur`.

**Tidak wajib Fase 1:** Email / WhatsApp (bisa backlog); fokus portal push + inbox.

### Mapping penerima

| Peran | `user_id` push |
|-------|----------------|
| Pengaju | `claim.sales_id` (= karyawan id penjual, selaras controller saat ini) |
| Approver tahap aktif | `MasterKaryawan.id` hasil **resolver** tahap (SPV/MGR/SM) |
| Finance Tax/Expense | **Tidak** otomatis ke pengaju kecuali event finance ke pengaju (di bawah) — role finance punya menu sendiri |

Jika `sales_id` / approver tidak punya token FCM: notifikasi tetap **tersimpan di inbox DB** (perilaku `FirebaseService`).

---

## 2. Deep link standar

Base path menu: `/sales/claim-fee-external`

| Target | URL contoh |
|--------|------------|
| Pengaju — pantau pengajuan | `?tab=pengajuan&detail={claimId}` |
| Pengaju — proses finance | `?tab=menunggu-finance&detail={claimId}` |
| Approver — antrian | `?tab=persetujuan&detail={claimId}` |

Payload `extra_data` minimal:

```json
{
  "type": "claim_fee_external",
  "claim_id": 123,
  "no_order": "ORD-xxx",
  "sales_workflow_status": "PENDING_SPV",
  "finance_status": null,
  "tab": "pengajuan"
}
```

---

## 3. Matriks event (wajib)

Setiap baris = **satu pemanggilan** notifikasi setelah transaksi DB **commit** sukses.

### 3.1 Ke approver (bukan pengaju)

| Kode event | Trigger | Penerima | Judul (ID) | Isi singkat |
|------------|---------|----------|------------|-------------|
| `CFE_APPROVAL_PENDING` | Submit baru / setelah approve tahap sebelumnya / reset workflow | Resolver **tahap aktif** | Persetujuan Claim Fee External | Ada pengajuan fee external No Order {no_order} menunggu persetujuan Anda ({stage_label}). |
| `CFE_APPROVAL_REMINDER` | Opsional Fase 3b: cron due antrian > N hari | Approver pending | *(backlog)* | |

### 3.2 Ke pengaju — rantai sales

| Kode event | Trigger | Judul | Isi singkat |
|------------|---------|-------|-------------|
| `CFE_SUBMITTED` | `store` insert sukses | Claim Fee External Terkirim | Pengajuan No Order {no_order} telah dikirim. Menunggu {stage_label}. |
| `CFE_STAGE_APPROVED` | Setiap `approveSales` (SPV/MGR/SM) | Claim Disetujui ({stage}) | Tahap {stage_label} disetujui. {next_text} |
| `CFE_SALES_APPROVED` | Status → `APPROVED_SALES` | Disetujui Sales | Pengajuan No Order {no_order} telah disetujui seluruh rantai sales. Menunggu proses Finance. |
| `CFE_SALES_REJECTED` | `rejectSales` | Claim Ditolak (Sales) | Pengajuan ditolak pada tahap {stage_label}. Alasan: {remarks} |
| `CFE_CANCELLED` | Soft cancel pengaju | Claim Dibatalkan | Pengajuan No Order {no_order} dibatalkan. |

**`next_text` contoh:**  
- Masih ada tahap: `Menunggu persetujuan Manager.`  
- Lolos ke finance: `Menunggu proses pajak.`

### 3.3 Ke pengaju — finance

| Kode event | Trigger (controller finance) | Judul | Isi singkat |
|------------|------------------------------|-------|-------------|
| `CFE_FINANCE_WAITING_TAX` | Masuk antrian tax (`APPROVED_SALES` / `WAITING_TAX`) | Masuk Proses Pajak | Claim No Order {no_order} menunggu verifikasi pajak. |
| `CFE_FINANCE_TAX_PROGRESS` | Tax `approve` → PROCESSED | Sedang Diproses Pajak | Claim sedang diproses tim pajak. |
| `CFE_FINANCE_READY_PAY` | Tax `setPPH` | Siap Pembayaran | Potongan PPh dihitung. Menunggu transfer. Nominal bayar: {nominal_bayar}. |
| `CFE_FINANCE_PAID` | Expense `transfer` | Pembayaran Selesai | Claim No Order {no_order} telah ditransfer ({tanggal_pembayaran}). |
| `CFE_FINANCE_REJECTED` | Tax/Expense reject | Ditolak Finance | Claim ditolak tim finance. Buat pengajuan baru jika perlu. Alasan: {remarks} |

### 3.4 Ke pengaju — edit oleh sistem

| Kode event | Trigger | Judul |
|------------|---------|-------|
| `CFE_UPDATED` | `store` update (masih `can_edit`) | Data Claim Diperbarui — opsional, default **off** agar tidak spam |

Rekomendasi: **hanya** `CFE_SUBMITTED` + perubahan status, bukan setiap field edit.

---

## 4. Service baru

`App\Services\ClaimFeeExternalNotificationService`

```text
notifyApprovalPending(ClaimFeeExternal $claim): void
notifySubmitter(string $eventCode, ClaimFeeExternal $claim, array $context = []): void
resolveUserIdsForApprovers(ClaimFeeExternal $claim): int[]
resolveSubmitterUserId(ClaimFeeExternal $claim): ?int
```

- Dipanggil dari `ClaimFeeExternalSalesWorkflowService` (approve/reject/submit).  
- Dipanggil dari wrapper finance (Fase 3) setelah update status — **satu tempat** agar tidak lupa.  
- Errors notifikasi: **log warning only**, tidak rollback transaksi bisnis (selaras lembur).

---

## 5. Ketentuan produk

1. **Pengaju tidak boleh ketinggalan** — setiap perubahan `sales_workflow_status` atau `finance_status` yang terlihat di tab Menunggu Finance **wajib** notifikasi ke `sales_id`.  
2. Approver hanya notifikasi **saat giliran** (`CFE_APPROVAL_PENDING`).  
3. Setelah SPV approve, **tidak** kirim notifikasi antrian lagi ke SPV untuk claim yang sama (kecuali reset workflow).  
4. Teks notifikasi **Bahasa Indonesia**, menyertakan **No Order** selalu.  
5. Reject **wajib** sertakan alasan di body (truncate 200 char di push).  
6. Pengaju yang `REJECTED_SALES` dapat notifikasi + deep link ke tab **Pengajuan** untuk perbaiki.  
7. Pengaju yang `REJECTED_FINANCE` dapat notifikasi + arahkan **claim baru** (tanpa edit id lama).

---

## 6. Roadmap fase (penempatan kerja)

| Fase | Deliverable notifikasi |
|------|-------------------------|
| 0 | Dokumen ini + masuk UAT §J |
| 1 | Service + event sales (submit, approve, reject, approved_sales) |
| 2 | Frontend buka `detail` dari query param notifikasi |
| 3 | Event finance (5 event) di Tax/Expense controller |
| 3b | Reminder overdue due_date ke **pengaju** (opsional) |

---

## 7. UAT notifikasi (ringkas)

| ID | Skenario | Ekspektasi |
|----|----------|------------|
| N1 | Staff submit | Pengaju inbox + approver SPV inbox |
| N2 | SPV approve | Pengaju terima `CFE_STAGE_APPROVED`; MGR terima `CFE_APPROVAL_PENDING` |
| N3 | Reject sales | Pengaju `CFE_SALES_REJECTED` + alasan |
| N4 | Lolos SM → finance | Pengaju `CFE_SALES_APPROVED` + `CFE_FINANCE_WAITING_TAX` |
| N5 | Transfer | Pengaju `CFE_FINANCE_PAID` |
| N6 | Reject finance | Pengaju `CFE_FINANCE_REJECTED` |
| N7 | Tap notifikasi | Buka halaman claim + drawer detail |

---

**Versi:** 1.0