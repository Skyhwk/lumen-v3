# Roadmap — Fitur Permohonan Penyesuaian Gaji

> **Versi dokumen:** 1.0  
> **Tanggal:** 22 September 2026  
> **Status:** Planning — belum implementasi  
> **Scope:** Full-stack (LUMEN-V3 backend + React frontend)

---

## 1. Ringkasan Eksekutif

Fitur ini memungkinkan **Manager** mengajukan kenaikan gaji untuk bawahan (Staff/Supervisor), kemudian HRD memproses melalui workflow multi-tahap (assessment, konseling, evaluasi final), Finance melakukan review, dan approval bertingkat (**Ibu Boss → Bapak Boss**) sebelum sistem otomatis memperbarui master_sallary.

**Tidak ada kode existing** untuk fitur ini. Pola terdekat yang akan direuse:

| Pola | Referensi Codebase |
|------|-------------------|
| Workflow multi-tab + audit trail | PermohonanCutiController, RequestKebijakanWorkflowService |
| Approval Ibu/Bapak Boss | OfferingSalaryController, AtsFinalDecisionController |
| Assessment + bank soal | AssessmentController, BankSoalController, AssessmentInternalController |
| Master salary versioning | MasterSallaryController@store |
| Filter bawahan manager | GetBawahan service |
| Tab UI permohonan | PermohonanCuti.js, StatusTabs.js |
| HRD multi-stage tabs | FinalDecision.js (ATS) |

**Perbedaan kritis vs ATS Assessment:**
- Semua berjalan **di V3 (React app)** — **tidak redirect ke Portal V4**
- Assessment terikat ke **permohonan penyesuaian gaji** (bukan kandidat rekrutmen)
- Link assessment di-generate HRD per permohonan, bukan batch assessment umum

---

## 2. Aktor & Hak Akses

| Aktor | Grade / Role | Aksi |
|-------|-------------|------|
| **Manager** | MANAGER, SENIOR MANAGER | Create permohonan untuk bawahan, view status di menu Request |
| **HRD Payroll** | Akses menu HRD Payroll | Process, reject, generate assessment, schedule konseling, evaluasi final |
| **Karyawan (bawahan)** | Target permohonan | Mengerjakan assessment via link V3 |
| **Finance** | Akses menu Finance | Review pengajuan setelah evaluasi final HRD approve |
| **Ibu Boss** | Director tier (female) | Approve/reject (view-only tab di HRD) |
| **Bapak Boss** | Director tier (male) | Final approve → trigger update master salary |

### Validasi Create (Manager Only)

- Hanya grade MANAGER / SENIOR MANAGER
- employee_id harus ada di GetBawahan::where('id', user_id)->get()
- Tidak boleh mengajukan untuk diri sendiri (default)

---

## 3. Pemetaan Menu & Tab

### 3.1 Request — 
equest/permohonan/penyesuaian-gaji

| Tab | Key | Kriteria Data |
|-----|-----|---------------|
| Menunggu Di Proses | waiting | Status awal setelah submit, belum diproses HRD |
| Sedang Di Proses | in_progress | HRD sudah process — assessment/konseling/evaluasi/finance/approval berjalan |
| Selesai Diproses | completed | Master salary sudah di-update |
| Pengajuan Ditolak | 
ejected | Ditolak di level manapun |

**Actions Manager:** Create, View (detail + timeline)

### 3.2 HRD — hrd/payroll/permohonan-penyesuaian-gaji

| Tab | Key | Actions |
|-----|-----|---------|
| Menunggu Di Proses | waiting_process | View, **Process**, **Reject** |
| Menunggu Assessment | waiting_assessment | View, **Generate Link Assessment** (pilih jenis soal + durasi) |
| Detail Proses Assessment | ssessment_detail | View detail + hasil jawaban |
| Schedule Konseling | counseling_schedule | View, **Schedule** → data pindah ke hrd/hris/konseling-karyawan |
| Evaluasi Final | inal_evaluation | View (KPI + assessment + absensi 3 bln + konseling), **Approve** → Finance, **Reject** |
| Waiting Approval | waiting_approval_ibu | View only (menunggu Ibu Boss) |
| Waiting Approval Final | waiting_approval_bapak | View only (menunggu Bapak Boss) |
| Rekap Data Complete | 
ekap_complete | View only |
| Rekap Data Rejected | 
ekap_rejected | View only |

> Tab "Reject" di requirement = **action** dari tab Menunggu Di Proses, bukan tab terpisah.

### 3.3 Finance — inance/pengajuan-penyesuaian-gaji

| Tab | Key | Actions |
|-----|-----|---------|
| Menunggu Review | waiting_review | View, Approve → Waiting Approval Ibu, Reject |
| Selesai / Rejected | done / 
ejected | View |

### 3.4 HRIS — hrd/hris/konseling-karyawan

Menu baru atau extend dari konsultasi existing. Data konseling dari permohonan penyesuaian gaji muncul di sini dengan source_type = salary_adjustment.

---

## 4. Form Input Manager (Create)

| # | Field | Tipe | Validasi |
|---|-------|------|----------|
| 1 | Nama Bawahan | Select (API GetBawahan) | Required |
| 2 | Jabatan | Text readonly | Auto-fill dari master_karyawan |
| 3 | Penyesuaian Gaji Pokok | Currency | Optional, min 1 dari #3 atau #4 wajib |
| 4 | Penyesuaian Tunjangan | Currency | Optional, min 1 dari #3 atau #4 wajib |
| 5 | Mulai Berlaku | Month-Year | Required (YYYY-MM) |
| 6 | KPI | Complex form | Required (lihat §5) |
| 7 | Catatan Tambahan | Textarea | Required |

Snapshot gaji current dari master_sallary (active) saat create. Simpan sebagai **delta penyesuaian + snapshot current**.

---

## 5. Modul KPI (Format Excel)

### 5.1 Kriteria Default (Seed)

| No | Kriteria | Bobot |
|----|----------|-------|
| 1 | Kualitas Kode & Dokumentasi | 20% |
| 2 | Ketepatan Waktu Penyelesaian | 20% |
| 3 | Pemecahan Masalah & Debugging | 15% |
| 4 | Inovasi & Optimalisasi Sistem | 10% |
| 5 | Kolaborasi Tim | 10% |
| 6 | Kepatuhan Proses & SOP | 10% |
| 7 | Kepuasan Stakeholder | 15% |

### 5.2 Kalkulasi

- Skor per baris: 1.0 – 5.0 (step 0.1)
- Nilai akhir baris: (bobot / 100) × (skor / 5) × 100 = obot × skor × 0.2
- Total skor rata-rata: rata-rata 7 skor
- Total nilai akhir: sum nilai akhir baris (max 100)

### 5.3 Interpretasi

| Range | Label |
|-------|-------|
| 4.50 – 5.00 | Sangat Baik |
| 3.50 – 4.49 | Baik |
| 2.50 – 3.49 | Cukup |
| < 2.50 | Kurang |

### 5.4 Field Narrative

Ringkasan Penilaian, Kekuatan, Area Perbaikan (required)

### 5.5 Tabel DB

- salary_adjustment_kpi_criteria — master (seed, scalable)
- salary_adjustment_kpi — header per request
- salary_adjustment_kpi_items — 7 baris skor per request

---

## 6. State Machine

`
Manager CREATE → submitted
  → HRD Process → hrd_processing
  → Generate Assessment → waiting_assessment → assessment_in_progress → assessment_completed
  → Schedule Konseling → counseling_scheduled → counseling_completed
  → Evaluasi Final → final_evaluation
  → HRD Approve → finance_review
  → Finance Approve → waiting_approval_ibu
  → Ibu Approve → waiting_approval_bapak
  → Bapak Approve → completed + auto-update master_sallary

Reject bisa di setiap gate → rejected (simpan rejected_stage + reason)
`

### Enum Status

submitted, hrd_processing, waiting_assessment, ssessment_in_progress, ssessment_completed, counseling_scheduled, counseling_completed, inal_evaluation, inance_review, waiting_approval_ibu, waiting_approval_bapak, completed, 
ejected

---

## 7. Desain Database (intilab_apps)

### salary_adjustment_requests

Kolom utama: no_document, employee_id, requested_by_id, jabatan, current/requested gaji & tunjangan, adjustment delta, bulan_efektif, catatan, status, rejected_stage, reject_reason.

Audit fields (semua nullable kecuali created):
- processed_by/at, rejected_by/at
- final_eval_approved_by/at, final_eval_rejected_by/at
- finance_approved_by/at, finance_rejected_by/at
- ibu_approved_by/at, ibu_rejected_by/at
- bapak_approved_by/at, bapak_rejected_by/at
- applied_at, applied_by, master_salary_id

### salary_adjustment_status_logs

request_id, from_status, to_status, action, actor_id, actor_name, notes, metadata (JSON), created_at

### salary_adjustment_assessments + sessions

Mirror assessment_internal tapi bound ke request. Token link V3: /salary-adjustment/assessment/{token}. Reuse bank soal existing.

### salary_adjustment_counselings

Jadwal konseling + integrasi menu konseling-karyawan.

---

## 8. API Controllers (X-Slice dispatch)

| Controller | Scope |
|------------|-------|
| PermohonanPenyesuaianGajiController | Manager CRUD + tabs |
| PenyesuaianGajiHrdController | HRD workflow |
| PenyesuaianGajiAssessmentController | Generate link, session V3 |
| PenyesuaianGajiFinanceController | Finance review |
| PenyesuaianGajiApprovalController | Ibu/Bapak approve |
| PenyesuaianGajiKonselingController | Schedule konseling |

### Services

- SalaryAdjustmentWorkflowService — status transitions, tab mapping
- SalaryAdjustmentKpiService — hitung skor
- SalaryAdjustmentAssessmentService — token, pull soal, grade
- SalaryAdjustmentApplyService — apply ke MasterSallary versioning

---

## 9. Frontend Structure

`
request/permohonan/penyesuaian-gaji/          — Manager (4 tab)
hrd/payroll/permohonan-penyesuaian-gaji/      — HRD (9 tab)
hrd/hris/konseling-karyawan/                  — Konseling
finance/pengajuan-penyesuaian-gaji/           — Finance
public/salary-adjustment-assessment/          — Assessment V3-only
`

Reuse: PermohonanCuti.js, FinalDecision.js, StatusTabs, MasterSallary currency input, PublishAssessmentModal (adapt).

---

## 10. Evaluasi Final Bundle

HRD view menggabungkan:
1. KPI (form manager + kalkulasi)
2. Hasil assessment (sessions.result_json)
3. Absensi 3 bulan (tabel absensi — reuse MonitorAbsenController pattern)
4. Hasil konseling (notes, status)

---

## 11. Fase Implementasi

### Phase 1 — Foundation (MVP)
Migration requests + KPI + status_logs. Manager create/view. HRD process/reject.

### Phase 2 — Assessment V3
Assessment tables + public route V3 + HRD generate link + view result.

### Phase 3 — Konseling
Counseling table + schedule modal + menu konseling-karyawan.

### Phase 4 — Evaluasi Final & Finance
Evaluation bundle + Finance approve/reject.

### Phase 5 — Approval & Apply Salary
Ibu/Bapak approval + auto-update master_sallary + rekap tabs.

### Phase 6 — Polish
Timeline UI, export, tests, KPI admin config.

---

## 12. Keputusan Terbuka

| # | Pertanyaan | Rekomendasi |
|---|-----------|-------------|
| 1 | Delta vs nilai absolut? | Delta + snapshot |
| 2 | Manager ajukan diri sendiri? | Tidak |
| 3 | Assessment login vs token-only? | Login required |
| 4 | Konseling = modul baru? | Ya, unified view |
| 5 | Ibu/Bapak via email atau in-app? | Keduanya |
| 6 | Finance sebelum Ibu Boss? | Ya |

---

## 13. Diagram Arsitektur

`
Manager → salary_adjustment_requests ← HRD ← Finance
              ↓ KPI / Assessment / Konseling
         Ibu Boss → Bapak Boss → master_sallary (versioning)
`

Assessment reuse bank_soal. Semua di V3, tidak ke Portal V4.

---

## 14. Langkah Selanjutnya

1. Review roadmap + konfirmasi keputusan §12
2. Approve Phase 1 → mulai migration + controller
3. Implement Phase 1–6 berurutan

### Changelog

| Versi | Tanggal | Perubahan |
|-------|---------|-----------|
| 1.0 | 2026-09-22 | Initial roadmap |

---

## 14. Keputusan - DIKONFIRMASI (22 Sep 2026)

| # | Keputusan | Status |
|---|-----------|--------|
| 1 | Penyesuaian gaji = delta + snapshot gaji lama | Dikonfirmasi |
| 2 | Manager tidak boleh ajukan untuk diri sendiri | Dikonfirmasi |
| 3 | Assessment token-only; sekali pakai jika selesai; resume jika belum | Dikonfirmasi |
| 4 | Konseling-karyawan modul terpisah dari konsultasi | Dikonfirmasi |
| 5 | Ibu/Bapak approve hanya via email + attachment + button approve/reject | Dikonfirmasi |
| 6 | UI assessment referensi portal, standalone di luar AdminLTE V3 | Dikonfirmasi |

## 15. Assessment Token-Only V3 Public

Route: /public/salary-adjustment/assessment/:token (App.js, outside PrivateRoute/Main)

UI referensi: Portal-Intilab/resources/views/assessment/show.blade.php (3-column layout)

Backend: PenyesuaianGajiAssessmentController - mirror AssessmentController (overview/start/state/answer)

Token lifecycle: aktif sampai selesai; resume jika incomplete; deactivate + 403 jika sudah complete

## 16. Email Approval Ibu/Bapak

Hanya via email. Tab HRD waiting approval = view only.

Buttons: /public/salary-adjustment/decision/{token}?decision=approve|reject

Attachments: ringkasan permohonan, KPI, hasil assessment, absensi 3 bln, konseling (PDF via mPDF)

Service: SalaryAdjustmentEmailService (mirror GenerateMessageAtsEmail buildSalaryDecisionButtons)

Token one-time per approver (ibu then bapak chain)

## 17. Konseling-Karyawan Terpisah

Tabel salary_adjustment_counselings, menu hrd/hris/konseling-karyawan, tidak share dengan consultation_requests

## 18. Phase Update

Phase 2: public assessment page + token resume
Phase 5: email service + public decision page

Changelog v1.1: 2026-09-22 confirmed decisions
