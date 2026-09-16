<?php

namespace App\Http\Controllers\api\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait OrdersAtsDataTableColumns
{
    protected function normalizeSortDirection($order): string
    {
        return strtolower((string) $order) === 'desc' ? 'DESC' : 'ASC';
    }

    protected function personnelRequestNoRequestSubquery(): string
    {
        return '(SELECT no_request FROM personnel_requests WHERE personnel_requests.id = new_recruitment.personnel_request_id LIMIT 1)';
    }

    protected function personnelRequestCreatedBySubquery(): string
    {
        return '(SELECT created_by FROM personnel_requests WHERE personnel_requests.id = new_recruitment.personnel_request_id LIMIT 1)';
    }

    protected function personnelRequestPositionSubquery(): string
    {
        return "COALESCE(
            (SELECT mj.nama_jabatan FROM master_jabatan mj
             INNER JOIN personnel_requests pr ON pr.posisi = mj.id
             WHERE pr.id = new_recruitment.personnel_request_id LIMIT 1),
            new_recruitment.posisi_dilamar
        )";
    }

    protected function applyPersonnelRequestDataTableOrdering($datatable)
    {
        $table = 'personnel_requests';

        return $datatable
            ->orderColumn('no_request', "{$table}.no_request $1")
            ->orderColumn('request_type', "{$table}.request_type $1")
            ->orderColumn('jumlah_personal', "{$table}.jumlah_personal $1")
            ->orderColumn('prioritas', "{$table}.prioritas $1")
            ->orderColumn('tanggal_dibutuhkan', "{$table}.tanggal_dibutuhkan $1")
            ->orderColumn('created_at', "{$table}.created_at $1")
            ->orderColumn('request_by', "{$table}.created_by $1")
            ->orderColumn('total_pelamar', function ($query, $order) {
                $query->orderBy('total_pelamar', $this->normalizeSortDirection($order));
            })
            ->orderColumn('posisi', function ($query, $order) use ($table) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("COALESCE((SELECT nama_jabatan FROM master_jabatan WHERE id = {$table}.posisi LIMIT 1), {$table}.posisi) {$direction}");
            })
            ->orderColumn('divisi', function ($query, $order) use ($table) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("COALESCE((SELECT nama_divisi FROM master_divisi WHERE id = {$table}.divisi LIMIT 1), {$table}.divisi_alias, {$table}.divisi) {$direction}");
            })
            ->orderColumn('status_label', function ($query, $order) use ($table) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("CASE
                    WHEN {$table}.is_completed = 1 THEN 5
                    WHEN {$table}.is_publish = 1 THEN 4
                    WHEN {$table}.is_approve = 1 THEN 3
                    WHEN {$table}.is_rejected = 1 OR {$table}.is_reject = 1 THEN 1
                    ELSE 2
                END {$direction}");
            });
    }

    protected function applyRecruitmentCoreDataTableOrdering($datatable)
    {
        $table = 'new_recruitment';

        return $datatable
            ->orderColumn('nama_lengkap', "{$table}.nama_lengkap $1")
            ->orderColumn('status', "{$table}.status $1")
            ->orderColumn('created_at', "{$table}.created_at $1")
            ->orderColumn('waktu_melamar', "{$table}.created_at $1")
            ->orderColumn('no_request', function ($query, $order) {
                $query->orderBy(DB::raw($this->personnelRequestNoRequestSubquery()), $order);
            })
            ->orderColumn('request_by', function ($query, $order) {
                $query->orderBy(DB::raw($this->personnelRequestCreatedBySubquery()), $order);
            })
            ->orderColumn('posisi_dilamar', function ($query, $order) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw($this->personnelRequestPositionSubquery() . ' ' . $direction);
            })
            ->orderColumn('nilai_kecocokan', function ($query, $order) use ($table) {
                $direction = $this->normalizeSortDirection($order);
                if (Schema::hasColumn('new_recruitment', 'matching_score')) {
                    $query->orderByRaw("COALESCE({$table}.nilai_kecocokan, {$table}.matching_score, 0) {$direction}");
                    return;
                }

                $query->orderBy("{$table}.nilai_kecocokan", $order);
            })
            ->orderColumn('usia', function ($query, $order) {
                $birthOrder = strtolower((string) $order) === 'asc' ? 'DESC' : 'ASC';
                if (Schema::hasColumn('new_recruitment', 'tanggal_lahir')) {
                    $query->orderBy('new_recruitment.tanggal_lahir', $birthOrder);
                }
            })
            ->orderColumn('shio', function ($query, $order) {
                if (Schema::hasColumn('new_recruitment', 'shio')) {
                    $query->orderBy('new_recruitment.shio', $order);
                    return;
                }

                if (Schema::hasColumn('new_recruitment', 'tanggal_lahir')) {
                    $query->orderBy('new_recruitment.tanggal_lahir', $order);
                }
            });
    }

    protected function applyInterviewScheduleDataTableOrdering($datatable, string $stage = 'hrd')
    {
        return $datatable->orderColumn('jadwal_interview', function ($query, $order) use ($stage) {
            $query->orderBy(
                DB::raw("(SELECT tgl_interview FROM recruitment_interviews
                    WHERE new_recruitment_id = new_recruitment.id
                    AND stage = '{$stage}'
                    ORDER BY is_active DESC, id DESC LIMIT 1)"),
                $order
            );
        });
    }

    protected function applyHrdInterviewDataTableOrdering($datatable)
    {
        return $this->applyInterviewScheduleDataTableOrdering(
            $this->applyRecruitmentCoreDataTableOrdering($datatable),
            'hrd'
        )->orderColumn('reject_reason', function ($query, $order) {
            $direction = $this->normalizeSortDirection($order);
            $query->orderByRaw("COALESCE(new_recruitment.is_rejected_kandidat_reason, new_recruitment.alasan_reject, new_recruitment.rejected_decision_reason, '') {$direction}");
        });
    }

    protected function applyUserInterviewDataTableOrdering($datatable)
    {
        return $this->applyInterviewScheduleDataTableOrdering(
            $this->applyRecruitmentCoreDataTableOrdering($datatable),
            'user'
        );
    }

    protected function applyFinalDecisionDataTableOrdering($datatable)
    {
        return $this->applyRecruitmentCoreDataTableOrdering($datatable)
            ->orderColumn('expected_salary', function ($query, $order) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("COALESCE(
                    (SELECT gaji_pokok FROM candidate_data_offers WHERE new_recruitment_id = new_recruitment.id LIMIT 1),
                    new_recruitment.ekspetasi_gaji,
                    0
                ) {$direction}");
            })
            ->orderColumn('sallary_offer_direktur', function ($query, $order) {
                $direction = $this->normalizeSortDirection($order);
                if (Schema::hasTable('sallary_offers')) {
                    $query->orderByRaw("COALESCE(
                        (SELECT sallary_offer_direktur FROM sallary_offers WHERE new_recruitment_id = new_recruitment.id AND is_active = 1 ORDER BY id DESC LIMIT 1),
                        0
                    ) {$direction}");
                    return;
                }

                $query->orderBy('new_recruitment.ekspetasi_gaji', $order);
            })
            ->orderColumn('offering_status', function ($query, $order) {
                $query->orderBy('new_recruitment.status', $order);
            });
    }

    protected function applyRejectedCandidateDataTableOrdering($datatable)
    {
        return $this->applyRecruitmentCoreDataTableOrdering($datatable)
            ->orderColumn('is_rejected_kandidat_by', function ($query, $order) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("COALESCE(new_recruitment.is_rejected_kandidat_by, new_recruitment.rejected_by, '') {$direction}");
            })
            ->orderColumn('is_rejected_kandidat_at', function ($query, $order) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("COALESCE(new_recruitment.is_rejected_kandidat_at, new_recruitment.rejected_at, new_recruitment.updated_at) {$direction}");
            })
            ->orderColumn('is_rejected_kandidat_reason', function ($query, $order) {
                $direction = $this->normalizeSortDirection($order);
                $query->orderByRaw("COALESCE(new_recruitment.is_rejected_kandidat_reason, new_recruitment.alasan_reject, new_recruitment.rejected_decision_reason, '') {$direction}");
            });
    }
}
