# Trello — GA Fixed Asset (Fase 3)

> **Fase 3 — Approval workflow + mutasi antar site**  
> Satu kartu = satu fitur/menu (**Backend + Frontend** dalam satu step), kecuali kartu infra/UAT.  
> Copy **Judul** ke nama kartu; copy blok **Description** ke deskripsi Trello.  
> Label: `GA-Fixed-Asset`, `F3`, `Full-stack`, `Butuh-GA` / `Butuh-HRD` jika perlu.

**Prasyarat:** **Fase 2 selesai** (mutasi same-site, log kondisi, timeline). Mutasi antar cabang **tidak** boleh bypass approval setelah F3 live.

**Dokumen terkait:** [ROADMAP-GA-FIXED-ASSET.md](./ROADMAP-GA-FIXED-ASSET.md) §9 · [Fase 2](./TRELLO-TASKS-GA-FIXED-ASSET-F2.md)

---

## Epic (kartu induk)

**Judul:** GA Fixed Asset — Fase 3 (Approval + mutasi antar site)

**Description:**

```
Tujuan: Aksi sensitif hanya jalan setelah semua manager yang ditetapkan approve (paralel AND).
Scope: Pengajuan, inbox approver, eksekusi otomatis ke asset + movement log.

request_type (roadmap):
- movement_cross_site — pindah cabang + ruang
- take_over — acquisition take over + metadata
- dispose_rusak_berat — RB / penghapusan inventaris
- report_hilang — status hilang
- transfer_out — transfer (T) sesuai kebijakan GA

Definition of Done (epic):
- [ ] Kartu 1–5 selesai
- [ ] Kartu 6 (UAT) disetujui GA + minimal 2 manager
- [ ] Kartu 7 (runbook) di docs/

Exit criteria roadmap: Cross-site mutasi tidak bisa tanpa approval completed; reject = tidak eksekusi; revisi & submit ulang sesuai kebijakan GA.
```

---

## Daftar judul (paste cepat — urutan kerja)

1. GA Asset F3 — Migration approval & master approver (DB)  
2. GA Asset F3 — Master Approver per cabang  
3. GA Asset F3 — Pengajuan Saya (form aksi sensitif)  
4. GA Asset F3 — Inbox Approval manager + eksekusi otomatis  
5. GA Asset F3 — Notifikasi inbox approver  
6. GA Asset F3 — QA / UAT approval paralel  
7. GA Asset F3 — Update roadmap & runbook Fase 3  

---

## Kartu 1 — Migration approval & master approver (DB)

**Judul:** GA Asset F3 — Migration approval & master approver (DB)

**Menu/route:** — (infra; tanpa menu user)

**Description:**

```
Tujuan: Schema workflow approval paralel + approver default per cabang.

Backend:
- Migration (sesuai roadmap §4):
  - ga_asset_approval_requests
  - ga_asset_approval_assignees
  - ga_asset_approval_actions
  - ga_asset_approval_policies (jika dipakai)
  - ga_asset_cabang_approvers (id_cabang, karyawan_id, …)
- Model + relasi; enum status pengajuan (draft/submitted/partial/approved/rejected/executed/cancelled — finalisasi di kartu 2)
- Index: request by asset, by assignee, by status

Frontend: —

Butuh GA + HRD: Struktur approver default per site (siapa manager)

Definition of Done:
- [ ] migrate up/down aman
- [ ] Seed contoh 1 cabang + 2 approver untuk UAT
- [ ] Dokumen 1 halaman ERD ringkas di docs/ (opsional)

Dependensi: Fase 2 movement/condition logs (untuk executor nanti)
Estimasi: M
Label: Full-stack (BE-heavy)
```

---

## Kartu 2 — Master Approver per cabang

**Judul:** GA Asset F3 — Master Approver per cabang

**Menu/route:** General Affair → Assets → **Master Approver**  
(URL usulan: `/general-affair/assets/master-approver`)

**Description:**

```
Tujuan: GA Admin kelola daftar manager/approver default per master_cabang (untuk pre-fill pengajuan).

Backend:
- CRUD ga_asset_cabang_approvers (id_cabang, karyawan_id, aktif)
- API lookup karyawan (reuse master karyawan / autocomplete nama)
- Validasi: approver harus karyawan aktif

Frontend:
- Pilih cabang → tabel approver → tambah/hapus/nonaktifkan
- Search karyawan by nama/NIK

Butuh GA: Daftar approver per Pusat/Karawang/Pemalang dll.

Definition of Done:
- [ ] Menu + hak akses (GA Admin)
- [ ] Approver tersimpan per cabang
- [ ] API list approver dipakai form Pengajuan Saya (Kartu 3)

Dependensi: Kartu 1
Estimasi: M
Label: Full-stack, Butuh-GA
```

---

## Kartu 3 — Pengajuan Saya (form aksi sensitif)

**Judul:** GA Asset F3 — Pengajuan Saya (form aksi sensitif)

**Menu/route:** General Affair → Assets → **Pengajuan Saya**  
(URL usulan: `/general-affair/assets/pengajuan-saya`)

**Description:**

```
Tujuan: GA Staff ajukan aksi sensitif; pilih approver (default dari master + boleh tambah manual jika kebijakan mengizinkan).

Backend:
- GaAssetApprovalController + GaAssetApprovalService (bagian submit):
  - create request + payload JSON (asset_id, request_type, to_branch, to_room, notes, …)
  - assign semua approver (parallel AND — semua wajib approve)
  - status submitted; lock edit asset terkait jika perlu (kebijakan)
- Validasi per request_type:
  - movement_cross_site: cabang/ruang tujuan wajib; tolak jika same-site (arahkan ke Mutasi F2)
  - take_over, dispose_rusak_berat, report_hilang, transfer_out — field sesuai workshop GA
- List "pengajuan saya" + detail + status per approver

Frontend:
- Halaman list pengajuan user + tombol buat baru
- Form: scan/ketik NO CS → pilih jenis pengajuan → isi field → pilih approver (multi) → submit
- Tampilkan status: menunggu X/Y approve, rejected + alasan

Butuh GA: Form field wajib per jenis pengajuan; siapa boleh submit

Definition of Done:
- [ ] 5 request_type bisa disubmit (minimal movement_cross_site + 1 lain fully tested)
- [ ] Approver list tercatat di assignees
- [ ] Same-site pindah ruang tidak lewat form ini (pesan: pakai Mutasi Ruang F2)
- [ ] Pengaju lihat status realtime setelah refresh/list

Dependensi: Kartu 1–2; F2 lookup aset by NO CS
Estimasi: L
Label: Full-stack, Butuh-GA
```

---

## Kartu 4 — Inbox Approval manager + eksekusi otomatis

**Judul:** GA Asset F3 — Inbox Approval manager + eksekusi otomatis

**Menu/route:** General Affair → Assets → **Inbox Approval**  
(URL usulan: `/general-affair/assets/inbox-approval`)

**Description:**

```
Tujuan: Manager approve/reject; jika SEMUA approver approve → eksekusi otomatis ke asset (transaction).

Backend:
- Inbox API: pengajuan where current user is pending assignee
- approve / reject + catatan wajib jika reject
- Parallel AND: hitung approved count vs total assignees
- Post-approve executor (F3-B4):
  - movement_cross_site → update branch_id, room_id, location_id + ga_asset_movements
  - take_over → acquisition_origin / metadata + log
  - dispose_rusak_berat → condition + is_active false (sesuai kebijakan)
  - report_hilang → condition hilang + log
  - transfer_out → sesuai kebijakan GA
- Tolak mutasi cross-site di F2 API jika masih ada (double guard)
- Audit: approval_actions + link ke timeline aset

Frontend:
- Inbox tabel + badge count (menu/header jika pola app mendukung)
- Detail pengajuan: data aset, payload, timeline approve/reject per orang
- Tombol Approve / Reject + modal catatan

Butuh GA + 2 manager untuk UAT paralel

Definition of Done:
- [ ] 1 approve dari 2 → status masih pending, asset belum berubah
- [ ] 2 approve → executed, asset & movement/log sesuai payload
- [ ] Reject → stopped, tidak eksekusi; pengaju lihat alasan
- [ ] Cross-site tidak bisa tanpa status executed

Dependensi: Kartu 3
Estimasi: XL
Label: Full-stack, Butuh-GA
```

---

## Kartu 5 — Notifikasi inbox approver

**Judul:** GA Asset F3 — Notifikasi inbox approver

**Menu/route:** — (integrasi; reuse modul notifikasi internal)

**Description:**

```
Tujuan: Approver dapat tahu ada pengajuan baru / perlu tindakan (reuse pola HRD/LIMS jika ada).

Backend:
- Trigger notif saat: submit pengajuan, reminder optional, approved/rejected final
- Payload: link ke inbox approval, NO CS, jenis pengajuan

Frontend:
- Badge inbox (jika belum di Kartu 4) + klik menuju detail
- Atau email/WA jika scope proyek mengizinkan (TBD GA)

Butuh GA: Channel notifikasi yang dipakai (in-app saja vs email)

Definition of Done:
- [ ] Approver menerima notif in-app saat assignee baru (minimal)
- [ ] Dokumentasi channel di runbook

Dependensi: Kartu 4 (inbox ada)
Estimasi: M
Label: Full-stack
```

---

## Kartu 6 — QA / UAT approval paralel

**Judul:** GA Asset F3 — QA / UAT approval paralel

**Menu/route:** — (QA)

**Description:**

```
Tujuan: Memenuhi F3-Q1 roadmap.

Skenario wajib:
1. Pengajuan mutasi cross-site dengan 2 approver — hanya 1 approve → pending
2. Approver ke-2 approve → asset pindah cabang/ruang + movement log
3. Reject oleh salah satu → tidak eksekusi; GA submit ulang (revisi)
4. Minimal 1 skenario take_over / hilang / RB (sesuai prioritas GA)

Definition of Done:
- [ ] Checklist UAT ditandatangani GA
- [ ] Bug kritis closed

Dependensi: Kartu 1–5
Estimasi: M
Label: Butuh-GA
```

---

## Kartu 7 — Update roadmap & runbook Fase 3

**Judul:** GA Asset F3 — Update roadmap & runbook Fase 3

**Menu/route:** — (docs)

**Description:**

```
Tujuan: GA/PM paham alur approval tanpa tanya dev.

Isi:
- Update ROADMAP status Fase 3
- Runbook: kapan pakai Mutasi F2 vs Pengajuan F3; daftar request_type; SLA approver; reject & revisi
- Matriks approver per cabang (link ke Master Approver)

Definition of Done:
- [ ] File markdown di docs/ + link dari ROADMAP

Dependensi: Kartu 6
Estimasi: S
Label: Butuh-GA
```

---

## Urutan kolom Trello

| List | Kartu |
|------|--------|
| **Backlog F3** | 1 → 7 |
| **Blocked by F2** | Epic F3 (jangan start sebelum F2 Done) |
| **Review GA / HRD** | 2, 6 |
| **Done** | Epic checklist |

---

## Workshop GA disarankan (sebelum sprint Kartu 3)

| Topik | Output |
|--------|--------|
| Field wajib per `request_type` | Spec 1 halaman |
| Satu reject = rejected total? | Ya (roadmap default) |
| Revisi setelah reject | Form baru vs reopen — putuskan |
| Approver wajib dari master saja atau boleh ad-hoc? | Keputusan |
| Eksekusi dispose/hilang | Soft delete vs is_active |

---

## Out of scope Fase 3

- Stock opname (Fase 4)  
- Laporan depresiasi / Accurate (Fase 5)  
- Mobile / PWA  

---

*Dibuat: 16 Oktober 2026 — selaras ROADMAP-GA-FIXED-ASSET.md v1.2 §9.*
