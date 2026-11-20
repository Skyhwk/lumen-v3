# HR Aset ↔ GA — Progress & Checklist

Update tanggal terakhir: **2026-11-20**

---

## Backend (lumen-v3)

| Item | Status |
|------|--------|
| Migration `2026_11_19_120000` (tabel HR) | ✅ (branch hc-aset) |
| Migration `2026_11_20_140000` (kolom GA) | ✅ |
| `HrGaAssetLinkService` | ✅ |
| `MasterAsetKaryawanController` | ✅ |
| `AsetKaryawanController` link API | ✅ |
| Default `ga_link_status` pada create/migrasi | ✅ |
| Dokumen roadmap + API | ✅ |
| Registrasi slice/menu DB prod | ⬜ |
| Bulk link script | ⬜ Fase 3 |
| Pivot qty>1 | ⬜ Fase 3 |

---

## Frontend (frontend-react-js)

| Item | Status |
|------|--------|
| `AssetInformation.js` (migrasi) | ✅ (branch hc-aset) |
| `AsetKaryawan.js` | ✅ |
| Halaman `master-aset-karyawan` | ⬜ Fase 2 |
| Link unit di `ModalAsetKaryawan` | ⬜ Fase 2 |
| Menu DB + route | ⬜ |
| Dokumen pointer | ✅ `docs/ROADMAP-HRD-ASSET-FINANCE-BRIDGE-FE.md` |

---

## Deploy

```bash
# Dari root lumen-v3
php artisan migrate --path=database/migrations/2026_11_20_140000_add_ga_link_columns_to_hr_aset_tables.php
```

Urutan: Fase 0 migration dulu, lalu Fase 1.

---

## UAT — centang saat selesai

- [ ] Migrasi kandidat + aset kosong
- [ ] Migrasi + beberapa master HR
- [ ] Map master "Laptop" ke sub kategori GA (API `linkSubKategori`)
- [ ] `searchAssignableGaAssets` mengembalikan unit
- [ ] `linkGaAsset` + cek PIC di daftar aset
- [ ] `unlinkGaAsset` + `clear_pic`
- [ ] SPPA tetap benar setelah link (nama dari master HR)