<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use App\Models\NewRecruitment;
use App\Services\RecruitmentPictureService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AtsArchivedCandidatesController extends Controller
{
    use BuildsCandidateAssessmentPreview;

    private function getTtlString($row)
    {
        if (!empty($row->tempat_tanggal_lahir)) {
            return $row->tempat_tanggal_lahir;
        }
        $parts = [];
        if (!empty($row->tempat_lahir)) $parts[] = $row->tempat_lahir;
        if (!empty($row->tanggal_lahir)) $parts[] = $row->tanggal_lahir;
        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    private function extractBirthYear($row)
    {
        $ttl = is_string($row) ? $row : $this->getTtlString($row);

        if (is_object($row) && !empty($row->tanggal_lahir)) {
            try {
                $dt = Carbon::parse($row->tanggal_lahir);
                $year = (int) $dt->year;
                if ($year >= 1930 && $year <= Carbon::now()->year) {
                    return $year;
                }
                if ($year > 0) {
                    $last2 = $year % 100;
                    $currentYY = Carbon::now()->year % 100;
                    return $last2 <= $currentYY ? (2000 + $last2) : (1900 + $last2);
                }
            } catch (\Exception $e) {}
        }

        if (!$ttl) return null;

        if (preg_match('/\b(19\d\d|20\d\d)\b/', $ttl, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(\d{4})\b/', $ttl, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1930 && $year <= Carbon::now()->year) {
                return $year;
            }
            if ($year > 0) {
                $last2 = $year % 100;
                $currentYY = Carbon::now()->year % 100;
                return $last2 <= $currentYY ? (2000 + $last2) : (1900 + $last2);
            }
        }

        return null;
    }

    private function resolvePositionName($applicant)
    {
        if (!$applicant) {
            return '-';
        }

        $pos = null;
        $pr = $applicant->personalRequest ?? null;

        if ($pr) {
            $masterJabatan = $pr->masterJabatan ?? null;
            if ($masterJabatan && !empty($masterJabatan->nama_jabatan)) {
                $pos = $masterJabatan->nama_jabatan;
            } elseif (!empty($pr->posisi_name)) {
                $pos = $pr->posisi_name;
            } elseif (!empty($pr->posisi) && !is_numeric($pr->posisi)) {
                $pos = $pr->posisi;
            }
        }

        if (!$pos && !empty($applicant->posisi_dilamar) && !is_numeric($applicant->posisi_dilamar)) {
            $pos = $applicant->posisi_dilamar;
        }

        return $pos ?: '-';
    }

    public function index(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview'])
            ->where('status', 'training')
            ->orderBy('id', 'desc');

        return DataTables::of($query)
            ->addColumn('no_request', function ($row) {
                return optional($row->personalRequest)->no_request ?? '-';
            })
            ->addColumn('request_by', function ($row) {
                return optional($row->personalRequest)->created_by ?: '-';
            })
            ->filterColumn('no_request', function ($q, $keyword) {
                $q->whereHas('personalRequest', function ($sub) use ($keyword) {
                    $sub->where('no_request', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('request_by', function ($q, $keyword) {
                $q->whereHas('personalRequest', function ($sub) use ($keyword) {
                    $sub->where('created_by', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_lengkap', function ($q, $keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%");
            })
            ->editColumn('posisi_dilamar', function ($row) {
                return $this->resolvePositionName($row);
            })
            ->filterColumn('posisi_dilamar', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('posisi_dilamar', 'like', "%{$keyword}%")
                        ->orWhereHas('personalRequest.masterJabatan', function ($j) use ($keyword) {
                            $j->where('nama_jabatan', 'like', "%{$keyword}%");
                        });
                });
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'training';
            })
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('status', 'like', "%{$keyword}%");
            })
            ->addColumn('usia', function ($row) {
                $birthYear = $this->extractBirthYear($row);
                if ($birthYear) {
                    $age = Carbon::now()->year - $birthYear;
                    return $age . ' Yrs';
                }
                return '-';
            })
            ->filterColumn('usia', function ($q, $keyword) {
                $cleanDigits = preg_replace('/[^0-9]/', '', $keyword);
                if (!empty($cleanDigits)) {
                    $targetYear = Carbon::now()->year - (int) $cleanDigits;
                    $q->where(function ($sub) use ($targetYear, $cleanDigits) {
                        $sub->whereYear('tanggal_lahir', $targetYear)
                            ->orWhere('tempat_tanggal_lahir', 'like', "%{$cleanDigits}%");
                    });
                } else {
                    $q->where('tempat_tanggal_lahir', 'like', "%{$keyword}%");
                }
            })
            ->editColumn('shio', function ($row) {
                $birthDate = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                $shio = $shioElemen['shio'] ?? null;
                $elemen = $shioElemen['elemen'] ?? null;
                if ($shio && $elemen) {
                    return "{$shio} ({$elemen})";
                }
                return $shio ?: ($elemen ?: '-');
            })
            ->filterColumn('shio', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('shio', 'like', "%{$keyword}%")
                        ->orWhere('elemen', 'like', "%{$keyword}%")
                        ->orWhere('tempat_tanggal_lahir', 'like', "%{$keyword}%")
                        ->orWhere('tanggal_lahir', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                if ($row->nilai_kecocokan !== null && $row->nilai_kecocokan !== '') {
                    return (float) $row->nilai_kecocokan;
                }
                if ($row->matching_score !== null && $row->matching_score !== '') {
                    return (float) $row->matching_score;
                }
                return null;
            })
            ->addColumn('minimum_matching', function ($row) {
                $minimum = optional($row->personalRequest)->minimum_matching;
                return $minimum !== null && $minimum !== '' ? (float) $minimum : null;
            })
            ->filterColumn('nilai_kecocokan', function ($q, $keyword) {
                $cleanVal = preg_replace('/[^0-9.]/', '', $keyword);
                if (!empty($cleanVal)) {
                    $q->where(function ($sub) use ($cleanVal) {
                        $sub->where('nilai_kecocokan', 'like', "%{$cleanVal}%")
                            ->orWhere('matching_score', 'like', "%{$cleanVal}%");
                    });
                }
            })
            ->toJson();
    }

    public function candidateDetail(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID kandidat tidak ditemukan'], 400);
        }

        $candidate = NewRecruitment::with(['personalRequest.masterJabatan', 'personalRequest.masterDivisi', 'hrdInterview', 'userInterview'])
            ->find($id);

        if (!$candidate) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
        }

        $pictureService = app(RecruitmentPictureService::class);
        $personnelRequest = $candidate->personalRequest;

        return response()->json([
            'status' => 'success',
            'data' => [
                'candidate' => array_merge(
                    $candidate->toArray(),
                    $this->formatCandidatePreviewItem($candidate, $pictureService)
                ),
                'request' => $personnelRequest ? [
                    'id' => $personnelRequest->id,
                    'no_request' => $personnelRequest->no_request,
                    'posisi' => optional($personnelRequest->masterJabatan)->nama_jabatan ?: $personnelRequest->posisi,
                    'divisi' => optional($personnelRequest->masterDivisi)->nama_divisi ?: ($personnelRequest->divisi_alias ?: $personnelRequest->divisi),
                    'minimum_matching' => $personnelRequest->minimum_matching,
                ] : null,
            ],
        ]);
    }
}
