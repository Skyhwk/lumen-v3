# Fase 0 — Claim Fee External (Checklist)

> Proyek: Unified menu Sales + workflow approval  
> Mulai: 2026-11-19  
> Referensi: `CLAIM_FEE_EXTERNAL_ROADMAP_DAN_KETENTUAN.md` v1.2

---

## Exit criteria Fase 0

| # | Deliverable | File | Status |
|---|-------------|------|--------|
| D0.1 | Keputusan Q1–Q7 terkunci | Roadmap §18 | ✅ Done |
| D0.2 | ERD + relasi + enum status | `CLAIM_FEE_EXTERNAL_ERD_API.md` §1–3 | ✅ Done |
| D0.3 | Draft migration (alter + tabel log) | `CLAIM_FEE_EXTERNAL_ERD_API.md` §4 + `database/migrations/2026_11_19_100000_claim_fee_external_workflow_draft.php` | ✅ Draft |
| D0.4 | Kontrak API (slice / request / response) | `CLAIM_FEE_EXTERNAL_ERD_API.md` §5–7 | ✅ Done |
| D0.5 | Matriks UAT lengkap | `CLAIM_FEE_EXTERNAL_UAT_MATRIX.md` | ✅ Done |
| D0.6 | Wireframe UX (pengganti mockup Figma fase 0) | `CLAIM_FEE_EXTERNAL_WIREFRAME.md` | ✅ Done |
| D0.6b | Spesifikasi notifikasi tiap proses | `CLAIM_FEE_EXTERNAL_NOTIFIKASI.md` | ✅ Done |
| D0.7 | Sign-off stakeholder (Product/Sales/Finance) | — | ⏳ Menunggu review manusia |
| D0.8 | Mockup Figma resmi (opsional) | — | ⏳ Opsional / parity dari wireframe |

**Gate ke Fase 1:** D0.1–D0.6 selesai + **minimal** review tim dev setuju ERD/API; D0.7 bisa paralel dengan awal Fase 1.

---

## Risiko fase 0

- Skema `claim_fee_external` di production belum di-dump — kolom legacy didokumentasikan dari pemakaian kode (lihat ERD §2). Saat Fase 1: **wajib** `DESCRIBE claim_fee_external` di staging sebelum jalankan migration.

---

## Pemilik

| Peran | Tanggung jawab Fase 0 |
|-------|------------------------|
| Product / Sales | Sign-off wireframe + tab visibility |
| Finance | Sign-off gate `APPROVED_SALES` + reject = claim baru |
| Backend | Review migration + API |
| Frontend | Review slice + tab shell |

---

## Langkah berikutnya (Fase 1)

1. `ClaimFeeExternalSalesWorkflowService` + unit test stages (Q6)  
2. Migration production-ready (rename draft)  
3. Endpoint `pengajuanIndex`, `show`, `store` dengan guard Q5/Q7  