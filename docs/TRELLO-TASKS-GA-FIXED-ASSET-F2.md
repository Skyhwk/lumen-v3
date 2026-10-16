# Trello — GA Fixed Asset (Fase 2)

> **Fase 2 — Mutasi internal + audit trail**  
> Satu kartu = satu fitur/menu (**Backend + Frontend** dalam satu step).  
> Copy **Judul** ke nama kartu; copy blok **Description** ke deskripsi Trello.  
> Label: `GA-Fixed-Asset`, `F2`, `Full-stack`.

**Prasyarat Fase 1 (minimal):** Import Excel + Daftar Aset jalan; `ga_assets` terisi; kondisi pakai kode ID (`normal`, `kendala`, `rusak`, …); `purchase_date` ter-import.

**Dokumen terkait:** [ROADMAP-GA-FIXED-ASSET.md](./ROADMAP-GA-FIXED-ASSET.md) §8 · Fase 1: [`TRELLO-TASKS-GA-FIXED-ASSET-F1.md`](./TRELLO-TASKS-GA-FIXED-ASSET-F1.md) · Lanjut Fase 3: [`TRELLO-TASKS-GA-FIXED-ASSET-F3.md`](./TRELLO-TASKS-GA-FIXED-ASSET-F3.md)

---

## Epic (kartu induk)

**Judul:** GA Fixed Asset — Fase 2 (Mutasi internal + audit)

**Description:**

```
Tujuan: Posisi aset (ruang) selalu current; setiap mutasi ruang & ubah kondisi tercatat user + waktu.
Scope: Same-site only — mutasi antar cabang masuk Fase 3 (approval).

Definition of Done (epic):
- [ ] Kartu 1–4 selesai
- [ ] Kartu 5 (UAT) disetujui GA
- [ ] Kartu 6 (dokumentasi) di docs/

Exit criteria roadmap: Mutasi same-site tanpa approval; history room_id & kondisi lengkap.
```

---

## Daftar judul (paste cepat — urutan kerja)

1. GA Asset F2 — Migration mutasi & log kondisi  
2. GA Asset F2 — Mutasi ruang (same cabang)  
3. GA Asset F2 — Ubah kondisi aset + catatan  
4. GA Asset F2 — Riwayat aset (timeline)  
5. GA Asset F2 — QA / UAT mutasi & kondisi  
6. GA Asset F2 — Update roadmap & runbook Fase 2  

---

## Kartu 1 — Migration mutasi & log kondisi

**Judul:** GA Asset F2 — Migration mutasi & log kondisi

**Menu/route:** — (infra DB, tanpa menu)

**Description:**

```
Tujuan: Tabel audit operasional untuk mutasi ruang dan perubahan kondisi.

Backend:
- Migration `ga_asset_movements` (asset_id, from/to branch, location, room, note, moved_at, created_by, movement_kind)
- Migration `ga_asset_condition_logs` (asset_id, from_condition, to_condition, note, created_by)
- Model Eloquent + relasi ke `ga_assets`
- Index: asset_id + tanggal

Frontend: —

Butuh GA: Tidak

Definition of Done:
- [ ] Migration up/down aman di intilab_produksi
- [ ] Model bisa insert/read sample via tinker/script
- [ ] FK/index selaras pola `ga_assets` (branch_id, room_id)

Dependensi: Fase 1 — tabel `ga_assets` produksi
Estimasi: S
Label: Full-stack (BE-heavy)
```

---

## Kartu 2 — Mutasi ruang (same cabang)

**Judul:** GA Asset F2 — Mutasi ruang (same cabang)

**Menu/route:** General Affair → Assets → **Mutasi Ruang**  
(URL usulan: `/general-affair/assets/mutasi-ruang`)

**Description:**

```
Tujuan: GA pindahkan aset antar ruang dalam cabang yang sama via scan NO CS — tanpa approval.

Backend:
- `GaAssetMovementController` (atau extend registry):
  - lookup aset by `cs_code` / scan
  - list ruang aktif per `branch_id` aset
  - `transferRoom`: validasi ruang tujuan masih cabang sama; update `room_id`, `location_id`; insert `ga_asset_movements`
  - Tolak mutasi antar cabang dengan pesan jelas (Fase 3)
- Transaksi DB + lock row aset

Frontend:
- Halaman scan-friendly: input NO CS (Enter / USB scanner) → tampil aset → dropdown ruang tujuan → catatan → simpan
- Toast sukses/gagal; fokus kembali ke input scan
- Migration menu + hak akses (clone dari Daftar Aset)

Butuh GA: Uji 2–3 mutasi nyata

Definition of Done:
- [ ] Menu Mutasi Ruang muncul & bisa diakses role GA
- [ ] Mutasi same-site update `room_id` & baris movement
- [ ] Mutasi beda cabang ditolak (422 + pesan)
- [ ] Daftar Aset menampilkan ruang baru setelah mutasi

Dependensi: Kartu 1; Master Ruang (ideal) — jika belum ada, ruang dari import tetap bisa dipilih
Estimasi: L
Label: Full-stack
```

---

## Kartu 3 — Ubah kondisi aset + catatan

**Judul:** GA Asset F2 — Ubah kondisi aset + catatan

**Menu/route:** Daftar Aset → Detail Aset → tab/form **Ubah kondisi**

**Description:**

```
Tujuan: Update kondisi (N/K/R/RB/H/T → kode DB: normal, kendala, rusak, rusak_berat, hilang, transfer) dengan catatan & audit.

Backend:
- API update kondisi: validasi enum; update `ga_assets.condition` + `condition_note`
- Insert `ga_asset_condition_logs` (from → to, note, created_by)
- Tidak mengubah NO CS

Frontend:
- Tab atau modal di detail aset: dropdown kondisi (label Indonesia + kode Excel)
- Field catatan; simpan → refresh badge kondisi di tabel

Butuh GA: Daftar siapa boleh ubah kondisi (GA Staff vs Admin)

Definition of Done:
- [ ] Perubahan kondisi tersimpan & tampil di Daftar Aset
- [ ] Setiap ubah kondisi ada 1 baris di `ga_asset_condition_logs`
- [ ] Enum selaras import Excel (bukan label EN legacy)

Dependensi: Kartu 1
Estimasi: M
Label: Full-stack
```

---

## Kartu 4 — Riwayat aset (timeline)

**Judul:** GA Asset F2 — Riwayat aset (timeline)

**Menu/route:** Daftar Aset → Detail Aset → tab **Riwayat**

**Description:**

```
Tujuan: Satu timeline: mutasi ruang + ubah kondisi (urut waktu terbaru).

Backend:
- API `timeline` by `asset_id`: gabung movements + condition_logs (max ~100)
- Response: type, at, by, title, summary, note

Frontend:
- Tab Riwayat di modal/halaman detail
- List kronologis; empty state jika belum ada data

Butuh GA: Review format teks riwayat

Definition of Done:
- [ ] Setelah mutasi (Kartu 2) riwayat tampil
- [ ] Setelah ubah kondisi (Kartu 3) riwayat tampil
- [ ] Sort descending by waktu

Dependensi: Kartu 2 & 3 (minimal salah satu untuk uji; ideal keduanya)
Estimasi: M
Label: Full-stack
```

---

## Kartu 5 — QA / UAT mutasi & kondisi

**Judul:** GA Asset F2 — QA / UAT mutasi & kondisi

**Menu/route:** — (aktivitas QA)

**Description:**

```
Tujuan: Memenuhi F2-Q1 roadmap — 10 mutasi + verifikasi room_id & history.

GA + Dev:
- Skenario: 10 mutasi ruang (minimal 2 cabang berbeda, same-site)
- 5 ubah kondisi dengan catatan
- Cek di DB: room_id, movement rows, condition_logs
- Cek UI: Daftar Aset, Riwayat

Definition of Done:
- [ ] Checklist UAT 10 mutasi + 5 ubah kondisi
- [ ] Bug kritis closed
- [ ] Sign-off GA (email/notulen singkat)

Dependensi: Kartu 2–4
Estimasi: M
Label: Butuh-GA
```

---

## Kartu 6 — Update roadmap & runbook Fase 2

**Judul:** GA Asset F2 — Update roadmap & runbook Fase 2

**Menu/route:** — (docs)

**Description:**

```
Tujuan: Tim GA operasional tanpa tanya dev untuk mutasi & kondisi.

Isi:
- Update ROADMAP § status Fase 2
- Runbook: langkah mutasi ruang, ubah kondisi, baca riwayat
- Known issues & batasan (no cross-site)

Definition of Done:
- [ ] Markdown di docs/ disetujui PM/GA

Dependensi: Kartu 5
Estimasi: S
Label: Butuh-GA
```

---

## Urutan kolom Trello

| List | Kartu |
|------|--------|
| **Backlog F2** | 1 → 6 |
| **In Progress** | — |
| **Review GA** | 5 |
| **Done** | Epic checklist |

---

## Out of scope Fase 2 (masuk Fase 3+)

- Mutasi **antar cabang** + approval manager  
- Stock opname (Fase 4)  
- Audit log generik F1-B8 (bisa overlap Kartu 3/4; tidak wajib duplikat)  
- Master Ruang CRUD (tetap kartu Fase 1 jika belum selesai — disarankan sebelum go-live mutasi)

---

*Dibuat: 16 Oktober 2026 — selaras ROADMAP-GA-FIXED-ASSET.md v1.1 §8.*
