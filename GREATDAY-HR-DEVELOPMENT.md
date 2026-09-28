# Greatday / HR workflow — panduan pengembangan (LUMEN-V3)

Melengkapi `greatday/ROADMAP.md`. **Super Apps (V3)** = repo `frontend` + API LUMEN-V3 `/api/...` (bukan `/greatday/`).

## Istilah

| Nama | Backend |
|------|---------|
| **Super Apps (V3)** | `App\Http\Controllers\api\*`, header `token` |
| **Greatday** | `App\Http\Controllers\Greatday\*`, Bearer greatday |

Prefix kode **`Portal*`** / env **`HR_PORTAL_*`** = sisi **Super Apps** (belum di-rename).

## Env & feature flag

| Env | Default | Arti |
|-----|---------|------|
| `HR_USE_LEGACY_TABLES` | `true` | Greatday baca/tulis `intilab_apps.*` |
| `HR_USE_LEGACY_TABLES` | `false` | Greatday pakai `hr_*` |
| `HR_DUAL_WRITE_LEGACY` | `true` | Tulis `hr_*` + mirror legacy (datatable Super Apps lama) |
| `HR_FREEZE_LEGACY_WRITES` | `false` | `true` = stop mirror `hr_*` → legacy (M6) |
| `HR_PORTAL_READ_HR_TABLES` | `false` | List cuti/izin/lembur + antrian HRD (`Portal*DatatableQuery`) dari `hr_*` |
| `GD_USE_PRODUKSI_AUTH` | `false` | Auth Greatday dari `gd_user_token` produksi |
| `GD_DUAL_WRITE_AUTH` | `true` | Login tulis `user_token` + `gd_user_token` (transisi M7) |
| `GD_USE_PRODUKSI_APP_DATA` | `false` | Notif/FCM/menu/permission dari `gd_*` produksi (M8) |

`php artisan config:clear` setelah ubah env.

## Layer

- `App\Http\Controllers\Greatday\*` — Greatday, atasan only
- `App\Http\Controllers\api\Permohonan*` — Super Apps HRD/finance
- `App\Services\Hr\Greatday\*` — CRUD Greatday mode `hr_*`
- `PortalHrSync` — legacy → `hr_*` saat **Super Apps** mengubah data
- `LegacyHrMirror` — `hr_*` → legacy saat Greatday + dual-write
- `ApprovalService` — `channel=greatday|portal` (`portal` = Super Apps)

## Artisan

```bash
php artisan migrate
php artisan greatday:migrate-hr-from-apps
php artisan greatday:migrate-hr-extended-from-apps
php artisan greatday:migrate-gd-auth-from-apps
php artisan greatday:verify-gd-auth-parity
php artisan greatday:migrate-gd-app-data-from-apps
php artisan greatday:smoke-hr
php artisan greatday:verify-hr-status-parity --fix
```

## Menambah tipe pengajuan

1. Migration detail + `HrRequest::TYPE_*`
2. Backfill command + sync services
3. Greatday: submit + atasan; Super Apps untuk step berikutnya
4. Verifikasi count + parity

## Scope produk

Greatday tidak approve HRD/finance/konsultasi. Reimbursement lembur = backlog.
