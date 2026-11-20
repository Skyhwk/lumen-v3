# Claim Fee External — Progress Implementasi

> Terakhir diperbarui: 2026-11-19

## Fase 0 — Dokumen & ketentuan

| Item | Status |
|------|--------|
| Q1–Q7 terkunci | ✅ |
| ERD / API / UAT / Wireframe / Notifikasi | ✅ |
| Sign-off stakeholder | ⏳ |

## Fase 1 — Backend sales workflow

| Item | Status |
|------|--------|
| Migration workflow + approval logs | ✅ |
| Workflow service + notifikasi + status sync | ✅ |
| Controller tab slice + approve/reject + guard store/delete | ✅ |
| Backfill legacy | ⏳ |
| Registrasi slice ACL | ⏳ (+ `claimedPeriods`, `checkClaimDuplicate`) |

## Fase 2 — Frontend sales

| Item | Status |
|------|--------|
| Tab StatusTabBar + multi-table | ✅ |
| ClaimDetailDrawer + deep link | ✅ |
| Redirect approval menu | ✅ |
| ModalFeeExternal UX + Select2 periode + anti-duplikat | ✅ |
| UAT manual | ⏳ |

## Fase 3 — Finance

| Item | Status |
|------|--------|
| Tax + Expense gate APPROVED_SALES | ✅ |
| Notifikasi finance | ✅ |
| UAT Finance | ⏳ |

