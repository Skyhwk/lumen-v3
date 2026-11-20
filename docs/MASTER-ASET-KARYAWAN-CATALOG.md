# Katalog master_aset_karyawan (sementara)

> Sumber: teks SPPA **Penerimaan Aset Perusahaan** (PDF contoh karyawan TC, Analyst, Sampling — Nov 2026).

**ID Card** tidak ada di katalog ini — dibuat otomatis oleh sistem (opsi migrasi + RFID).

## Daftar master (dropdown HR)

| No | Nama aset (master) | Muncul di PDF |
|----|-------------------|---------------|
| 1 | Jas Laboratorium | TC, Analyst |
| 2 | Kacamata Googles Lab | TC, Analyst, Sampling |
| 3 | Kacamata Medicon | Sampling |
| 4 | Respirator | Semua |
| 5 | Catridge Respirator | TC, Analyst |
| 6 | Filter Catridge Respirator | TC, Analyst |
| 7 | Earplugg | Sampling |
| 8 | Earmuff | Sampling |
| 9 | Sarung Tangan Bintik Kuning | Sampling |
| 10 | Helm Safety | Sampling |
| 11 | Sepatu Safety | Semua |
| 12 | Baju Sampling | Sampling |
| 13 | Tas Sampling | Sampling |
| 14 | Handphone | TC |
| 15 | Handphone Sampling | Sampling |

Catatan ejaan mengikuti PDF (mis. *Googles*, *Catridge*, *Earplugg*).

## Sinkronisasi database

- Seed awal: `2026_11_19_120000_create_aset_karyawan_tables.php`
- Sync existing DB: `2026_11_20_150000_sync_master_aset_karyawan_sppa_catalog.php`

Master lama (Laptop, Mouse, dll.) dinonaktifkan jika **belum** dipakai di `aset_karyawan` aktif.

## Preset migrasi ATS (hired-candidates)

File FE: `hired-candidates/shared/hiredCandidateAssetPresets.js`

| Jabatan (match nama) | Template |
|----------------------|----------|
| Technical Control Staff | SPPA TC (8 baris + ID Card) |
| Analyst Staff | SPPA Analyst (7 baris + ID Card) |
| Sampler | SPPA Sampling (12 baris + ID Card) |

Preset diisi otomatis saat step **Assets** jika `position_name` / `posisi_di_lamar` cocok dan belum ada data aset; user tetap bisa edit/hapus/tambah.