<?php

namespace App\Support;

use App\Helpers\RecruitmentDisplay;
use Illuminate\Support\Collection;

class PublicRecruitmentJobPresenter
{
    public static function header(Collection $jobs): array
    {
        $placements = $jobs
            ->pluck('placement')
            ->filter(fn ($place) => is_string($place) && trim($place) !== '')
            ->map(fn ($place) => trim($place))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $portalBase = config('recruitment.portal_public_base');

        return [
            'company' => 'PT INTI SURYA LABORATORIUM',
            'portal_title' => 'Careers at Intilab',
            'portal_description' => 'Discover opportunities that match your experience and aspirations.',
            'total' => $jobs->count(),
            'placements' => $placements,
            'list_url' => $portalBase,
        ];
    }

    public static function listItem(object $job): array
    {
        $noRequest = (string) ($job->no_request ?? '');

        return [
            'no_request' => $noRequest,
            'position' => $job->divisi_alias ?? $job->divisi_name ?? 'Posisi tersedia',
            'division_name' => $job->divisi_name ?? null,
            'grade' => self::nullableString($job->grade_master_karyawan ?? null),
            'placement' => $job->placement ?? 'Menunggu konfirmasi',
            'experience' => RecruitmentDisplay::translateExperience($job->pengalaman_kerja ?? null),
            'experience_raw' => self::nullableString($job->pengalaman_kerja ?? null),
            'gender' => RecruitmentDisplay::translateGender($job->gender ?? null),
            'gender_raw' => self::nullableString($job->gender ?? null),
            'priority' => self::nullableString($job->prioritas ?? null),
            'priority_label' => RecruitmentDisplay::translatePriority($job->prioritas ?? 'normal'),
            'headcount' => isset($job->jumlah_personal) ? (int) $job->jumlah_personal : null,
            'image_url' => self::nullableString($job->image_url ?? null),
            'links' => self::links($noRequest),
        ];
    }

    public static function detailOnly(object $job): array
    {
        $requirementHtml = RecruitmentDisplay::richText($job->requirement ?? null);

        return [
            'education' => RecruitmentDisplay::translateEducation($job->pendidikan ?? null),
            'education_raw' => self::nullableString($job->pendidikan ?? null),
            'requirement_html' => $requirementHtml,
            'requirement_plain' => $requirementHtml !== null
                ? trim(strip_tags(html_entity_decode($requirementHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
                : null,
            'use_user_assessment' => (int) ($job->use_user_assessment ?? 0) === 1,
            'user_assessment_summary' => RecruitmentDisplay::formatUserAssessmentSummary($job),
        ];
    }

    public static function bundle(object $job): array
    {
        $summary = self::listItem($job);

        return [
            'no_request' => $summary['no_request'],
            'summary' => $summary,
            'detail' => self::detailOnly($job),
        ];
    }

    private static function links(string $noRequest): array
    {
        $portalBase = config('recruitment.portal_public_base');
        $links = [
            'list' => $portalBase,
        ];

        if ($noRequest !== '') {
            $links['apply'] = $portalBase . '/' . rawurlencode($noRequest);
        }

        return $links;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
