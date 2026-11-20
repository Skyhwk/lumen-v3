# Wireframe — Claim Fee External (Fase 0)

> Pengganti mockup Figma untuk alignment tim. Implementasi: React Bootstrap + DataTables + drawer kanan.

---

## Layout halaman

```
┌─────────────────────────────────────────────────────────────────┐
│ ContentHeader: Claim Fee External                               │
├─────────────────────────────────────────────────────────────────┤
│ [ Pengajuan ] [ Persetujuan ] [ Menunggu Finance ] [ Rekap ]    │  ← tab disembunyikan per grade
├─────────────────────────────────────────────────────────────────┤
│ (konten tab aktif)                                              │
└─────────────────────────────────────────────────────────────────┘
```

### Visibility tab (ringkas)

| Tab | Staff | SPV | MGR | SM |
|-----|:-----:|:---:|:---:|:--:|
| Pengajuan | ✓ | ✗ | ✓ | ✓ |
| Persetujuan | ✗ | ✓ | ✓ | ✓ |
| Menunggu Finance | ✓ | ✗ | ✓ | ✓ |
| Rekap | ✗ | ✗ | ✓ | ✓ |

---

## Tab: Pengajuan

```
┌─ Toolbar ──────────────────────────────────────────────────────┐
│ [+ Tambah Claim]  [Refresh]     Filter: Tahun [2026 ▼]         │
└────────────────────────────────────────────────────────────────┘
┌─ DataTable ────────────────────────────────────────────────────┐
│ No | Aksi | Status | No Order | ... | Due Date                  │
│    Edit Hapus Detail  (hanya jika can_edit / policy)            │
│    Baris merah jika due_date < today                            │
└────────────────────────────────────────────────────────────────┘
```

**Modal Tambah/Edit:** reuse `ModalFeeExternal` + disable field jika `!isDataComplete` / `!can_edit`.

---

## Tab: Persetujuan (SPV / MGR / SM)

```
┌─ Info banner (SPV) ────────────────────────────────────────────┐
│ Antrian persetujuan Anda. Setelah disetujui, klaim hilang       │
│ dari daftar ini.                                                │
└────────────────────────────────────────────────────────────────┘
┌─ DataTable ────────────────────────────────────────────────────┐
│ No | Aksi | Status | Sales | No Order | Nominal | Due | ...     │
│ Aksi: [✓ Setujui] [✗ Tolak]  (hanya can_approve)                 │
│ Detail → drawer (read-only setelah SPV approve jika masih tampil)│
└────────────────────────────────────────────────────────────────┘
```

**Reject modal:** textarea alasan (min 10 karakter).

---

## Tab: Menunggu Proses Finance

Read-only table. Kolom tambahan: `finance_status` (label ID), potongan/nominal bayar jika ada.

Tombol: **Detail** saja.

---

## Tab: Rekap (MGR / SM)

```
Filter: Periode | Status sales | Status finance | Sales [SM: optional]
┌─ Kartu ringkas ────────────────────────────────────────────────┐
│ Total pengajuan | Menunggu approval | Di finance | Terbayar  │
└────────────────────────────────────────────────────────────────┘
┌─ Tabel drill-down (server-side) ──────────────────────────────┐
│ ...                                                            │
└────────────────────────────────────────────────────────────────┘
[Export Excel] jika permission export
```

SM: tanpa filter wajib sales_id (all data).

---

## Drawer: Detail Claim + Pipeline

```
┌─ Detail Claim ──────────────────────────────── [X] ─┐
│ No Order • Perusahaan • Nominal • Penerima            │
├──────────────────────────────────────────────────────┤
│ TIMELINE                                               │
│  ● Diajukan — oleh Budi — 19 Nov 2026                 │
│  ● Menunggu SPV — (nama resolve hari ini) — PENDING    │
│  ○ Manager — — menunggu                                │
│  ○ Senior Manager — (disembunyikan jika tidak ada Q6) │
│  ─── Finance ───                                       │
│  ○ Pajak → ○ Siap bayar → ○ Transfer                  │
├──────────────────────────────────────────────────────┤
│ [Setujui] [Tolak]     ← hanya jika can_approve         │
└──────────────────────────────────────────────────────┘
```

---

## Redirect menu lama

`/sales/approval-fee-external` → `/sales/claim-fee-external?tab=persetujuan`