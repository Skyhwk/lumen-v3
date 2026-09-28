# Greatday → LUMEN-V3 — Roadmap (salinan backend)

**Dokumen utama:** `D:\JAVASCRIPT\REACT-JS\greatday\ROADMAP.md`  
**Panduan dev:** `GREATDAY-HR-DEVELOPMENT.md` (repo ini)

**Super Apps (V3)** = `frontend` (Intisurya Super Apps) + LUMEN-V3 `/api/...` · **Greatday** = repo `greatday` + `/api/greatday/...`

## Progress singkat

| Fase | Status |
|------|--------|
| Phase 0 | [x] Done |
| Phase 1 | [x] Auth + read-only |
| Phase 2 | [x] Controller form bridge |
| Phase 2B | [x] Inti + M6–M8 (flag freeze, auth/app data produksi, artisan migrate/verify) — cutover env opsional |
| Phase 2C | [~] Schema + command `migrate-hr-extended-from-apps`; koreksi absen HR service |
| Phase 3–4 | [x] Absensi + payroll/slip |
| Phase 5–6 | **Skip** (ops/cutover) — lihat ROADMAP `[-]` |
| Sisa kecil | 2B M6/M8, 2C extended, QA device — lihat ROADMAP |

## Scope Greatday vs Super Apps (V3)

| | Greatday | Super Apps (V3) |
|---|----------|-----------------|
| Cuti/izin/lembur | Submit + approve **Pending → atasan** | HRD, finance, datatable |
| Konsultasi HR | Submit saja | Approve |

## Deliverables 2B (LUMEN-V3)

| Item | Lokasi |
|------|--------|
| Migration `hr_*` | `database/migrations/2026_03_27_120000_*` |
| Migration `gd_*` + detail 2C | `database/migrations/2026_03_27_140000_*` |
| Model Hr / Gd | `app/Models/Hr/*`, `app/Models/Gd/*` |
| Greatday HR services | `app/Services/Hr/Greatday/*` |
| Sync | `PortalHrSync` (Super Apps), `LegacyHrMirror` (Greatday) |
| Approval | `ApprovalService`, `AtasanStepService` |
| Config | `config/greatday.php` |
| Commands | `greatday:migrate-hr-from-apps`, `migrate-hr-extended-from-apps`, `migrate-gd-auth-from-apps`, `smoke-hr`, `verify-hr-*` |

## Super Apps — route HR (tetap)

- `PermohonanCutiController`, `PermohonanIzinController`, `LemburController`, `IzinController`
- Tulis legacy + **`PortalHrSync`** → `hr_*`
- List HR Super Apps dari `hr_*`: `HR_PORTAL_READ_HR_TABLES=true` (cuti, izin, lembur, antrian HRD)

## Path greatday API

- `routes/greatday.php`
- `app/Http/Controllers/Greatday/`
- Middleware `GreatdayCheckToken` (+ `GD_USE_PRODUKSI_AUTH` → `gd_user_token`)
