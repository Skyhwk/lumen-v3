# HR Aset ↔ GA — ERD & Kontrak API

> Pelengkap: [ROADMAP-HRD-ASSET-FINANCE-BRIDGE.md](./ROADMAP-HRD-ASSET-FINANCE-BRIDGE.md)

---

## 1. Status enum

### `master_aset_karyawan.ga_link_status`

| Nilai | Arti |
|-------|------|
| `unmapped` | Belum punya `sub_kategori_aset_id` |
| `mapped_sub_kategori` | Siap untuk cari unit GA by sub kategori |

### `aset_karyawan.ga_link_status`

| Nilai | Arti |
|-------|------|
| `unlinked` | Belum punya `ga_asset_id` |
| `linked` | Terhubung ke unit GA |
| `not_applicable` | ID Card / tidak apply ke GA |

---

## 2. Endpoint (slice pattern)

Semua via `POST` + header `X-Slice: makeSlice(Controller, action)` seperti modul lain.

### 2.1 `MasterAsetKaryawanController`

#### `index`
- **Response:** DataTables JSON (kolom: `nama`, `nama_sub_kategori`, `nama_kategori`, `ga_link_status`, `ga_linked_at`).

#### `store`
```json
{ "nama": "Headset" }
```

#### `update`
```json
{ "id": 3, "nama": "Headset Dinas" }
```

#### `delete`
```json
{ "id": 3 }
```
Soft: `is_active = false`.

#### `linkSubKategori`
```json
{ "id": 1, "sub_kategori_aset_id": 42 }
```
Hapus mapping: `sub_kategori_aset_id: null`.

#### `getSubKategoriOptions`
```json
{}
```
```json
{
  "status": true,
  "data": [
    { "id": 42, "text": "Elektronik — Laptop", "nama_sub_kategori": "Laptop", "nama_kategori": "Elektronik" }
  ]
}
```

---

### 2.2 `AsetKaryawanController` (tambahan Fase 1)

#### `searchAssignableGaAssets`
```json
{
  "aset_karyawan_id": 101,
  "keyword": "CS-LAP"
}
```
- Unit: `ga_assets` aktif, `record_kind = unit`, `sub_kategori_aset_id` = mapping master, `pic_employee_id` null atau = karyawan pemegang.

#### `linkGaAsset`
```json
{
  "aset_karyawan_id": 101,
  "ga_asset_id": 9001
}
```
- Side effect: `ga_assets.pic_employee_id = master_karyawan_id`.

#### `unlinkGaAsset`
```json
{
  "aset_karyawan_id": 101,
  "clear_pic": true
}
```

#### `show` — field tambahan per baris aset
```json
{
  "ga_asset_id": 9001,
  "ga_link_status": "linked",
  "ga_asset_code": "CS-LAPTOP-001",
  "ga_asset_name": "Laptop Dell"
}
```

---

### 2.3 `AtsHiredCandidatesController`

#### `getMasterAsetKaryawanOptions`
Item tambahan:
```json
{
  "id": 1,
  "nama": "Laptop",
  "text": "Laptop [GA mapped]",
  "sub_kategori_aset_id": 42,
  "ga_link_status": "mapped_sub_kategori"
}
```

---

## 3. Service internal

**`App\Services\HrAset\HrGaAssetLinkService`**

| Method | Deskripsi |
|--------|-----------|
| `linkMasterToSubKategori` | Validasi sub kategori aktif |
| `searchAssignableGaAssets` | Query unit untuk Select2 |
| `linkAsetKaryawanRowToGaUnit` | Transaksi + lock row + set PIC |
| `unlinkAsetKaryawanRowFromGaUnit` | Lepas FK + optional clear PIC |

---

## 4. Desain fase lanjut: qty > 1

**Tabel usulan** `aset_karyawan_ga_asset` (belum di-migrate):

| Kolom | Tipe |
|-------|------|
| `id` | bigint PK |
| `aset_karyawan_id` | FK |
| `ga_asset_id` | FK unique (per active) |
| `is_active` | bool |
| audit | created_* / deleted_* |

Aturan: satu baris HR qty=N bisa punya N child link; SPPA tetap dari parent qty; GA PIC per unit.

---

## 5. Registrasi slice (TODO operasional)

Tambahkan ke tabel slice/menu (sesuai environment):

| Controller | Action |
|------------|--------|
| `MasterAsetKaryawanController` | index, store, update, delete, linkSubKategori, getSubKategoriOptions |
| `AsetKaryawanController` | searchAssignableGaAssets, linkGaAsset, unlinkGaAsset |

Menu slug usulan: `hrd-master-aset-karyawan`, `hrd-aset-karyawan`.