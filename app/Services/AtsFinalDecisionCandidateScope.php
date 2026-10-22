<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Scope kandidat Final Decision ATS — dipakai HRD (AtsFinalDecisionController) dan Apps Approval.
 */
class AtsFinalDecisionCandidateScope
{
    public static function applyActive(Builder $query): Builder
    {
        self::applyBase($query);
        self::applyActiveSectionFilter($query);

        return $query;
    }

    public static function applyRejected(Builder $query): Builder
    {
        self::applyBase($query);
        self::applyRejectedSectionFilter($query);

        return $query;
    }

    public static function applyBase(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('status', [
                'management_decision',
                'internal_sallary_offer',
                'salary_offer',
                'finance_review',
                'rejected',
            ])
                ->orWhere(function ($sub) {
                    $sub->where('status', 'rejected')
                        ->where(function ($meta) {
                            $meta->where('meta_history', 'like', '%management_decision%')
                                ->orWhere('meta_history', 'like', '%internal_sallary_offer%')
                                ->orWhere('meta_history', 'like', '%interview_user%')
                                ->orWhere('meta_history', 'like', '%finance_review%')
                                ->orWhere('meta_history', 'like', '%finance_rejected%')
                                ->orWhere('meta_history', 'like', '%candidate_rejected%')
                                ->orWhere('is_approve_interview_user', 1);
                        });
                });
        })
            ->where(function ($q) {
                $q->whereNull('meta_history')
                    ->orWhere('meta_history', 'not like', '%hrd_final_decision_rejected%');
            })
            ->where(function ($q) {
                $q->where('is_rejected_kandidat', false)
                    ->orWhereNull('is_rejected_kandidat');
            })
            ->where(function ($q) {
                $q->whereNull('rejected_by')
                    ->orWhere('is_approved_interview_hrd', 1)
                    ->orWhereNotNull('approved_interview_hrd_by');
            })
            ->where('is_active', 1);
    }

    private static function applyActiveSectionFilter(Builder $query): void
    {
        if (Schema::hasColumn('new_recruitment', 'rejected_decision')) {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('rejected_decision', false)->orWhereNull('rejected_decision');
                })->where(function ($sub) {
                    $sub->where('rejected_salary', false)->orWhereNull('rejected_salary');
                });
            });
        }

        if (Schema::hasColumn('new_recruitment', 'is_reject_finance')) {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('is_reject_finance', false)->orWhereNull('is_reject_finance');
                })->orWhere('status', '!=', 'management_decision');
            });
        }
    }

    private static function applyRejectedSectionFilter(Builder $query): void
    {
        $hasFilter = false;

        $query->where(function ($q) use (&$hasFilter) {
            if (Schema::hasColumn('new_recruitment', 'rejected_decision')) {
                $q->where(function ($sub) {
                    $sub->where('rejected_decision', true)->orWhere('rejected_salary', true);
                });
                $hasFilter = true;
            }

            if (Schema::hasColumn('new_recruitment', 'is_reject_finance')) {
                $financeRejected = function ($sub) {
                    $sub->where('status', 'management_decision')->where('is_reject_finance', true);
                };

                if ($hasFilter) {
                    $q->orWhere($financeRejected);
                } else {
                    $q->where($financeRejected);
                    $hasFilter = true;
                }
            }

            if (!$hasFilter) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
