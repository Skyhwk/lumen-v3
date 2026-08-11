<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AtsInterviewUserController extends Controller
{
    // ─── Helpers (same pattern as AtsInterviewHrdController) ─────────────────

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

    private function extractBirthYear($ttl)
    {
        if (!$ttl) return null;
        if (preg_match('/\b(19\d\d|20\d\d)\b/', $ttl, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function resolvePositionName($applicant)
    {
        if (!$applicant) {
            return '-';
        }

        $pos = null;
        $pr  = $applicant->personalRequest ?? null;

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

    // ─── Index — DataTables list of interview_user candidates ─────────────────

    /**
     * List candidates with status = interview_user, grouped by today / upcoming-past
     */
    public function index(Request $request)
    {
        $mode = $request->input('mode', 'scheduled');

        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'userInterview'])
            ->where(function ($q) use ($mode) {
                if ($mode === 'scheduled') {
                    $q->where('status', 'interview_user')
                      ->orWhereHas('userInterview', function ($ui) {
                          $ui->whereNotNull('tgl_interview')->where('is_active', 1);
                      });
                } else {
                    // mode = 'unscheduled' (Approved by HRD, pending User Interview schedule)
                    $q->where('is_approved_interview_hrd', 1)
                      ->where('status', '!=', 'interview_user')
                      ->whereDoesntHave('userInterview', function ($ui) {
                          $ui->whereNotNull('tgl_interview')->where('is_active', 1);
                      });
                }
            })
            ->when($request->filled('year'), function ($q) use ($request) {
                return $q->where(function ($sub) use ($request) {
                    $sub->whereYear('created_at', $request->year)
                        ->orWhereNull('created_at');
                });
            })
            ->orderBy('id', 'desc');

        return DataTables::of($query)
            ->addColumn('no_request', function ($row) {
                return optional($row->personalRequest)->no_request ?? '-';
            })
            ->filterColumn('no_request', function ($q, $keyword) {
                $q->whereHas('personalRequest', function ($sub) use ($keyword) {
                    $sub->where('no_request', 'like', "%{$keyword}%");
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
            ->addColumn('jadwal_interview', function ($row) {
                $ui = $row->userInterview;
                if ($ui && $ui->tgl_interview) {
                    $dt = Carbon::parse($ui->tgl_interview);
                    $jenis = strtolower($ui->jenis_interview ?? '');
                    if ($jenis === 'online') {
                        $detail = 'Online (GMeet)';
                    } else {
                        $detail = 'Offline (' . ($ui->ruangan_interview ?: 'Office Room') . ')';
                    }
                    return $dt->format('d M Y, H:i') . ' WIB - ' . $detail;
                }
                return '-';
            })
            ->filterColumn('jadwal_interview', function ($q, $keyword) {
                $q->whereHas('userInterview', function ($sub) use ($keyword) {
                    $sub->where('tgl_interview', 'like', "%{$keyword}%")
                        ->orWhere('jenis_interview', 'like', "%{$keyword}%")
                        ->orWhere('ruangan_interview', 'like', "%{$keyword}%")
                        ->orWhere('link_gmeet', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('user_interview', function ($row) {
                return $row->userInterview;
            })
            ->addColumn('usia', function ($row) {
                $ttl = $this->getTtlString($row);
                $birthYear = $this->extractBirthYear($ttl);
                if ($birthYear) {
                    return (Carbon::now()->year - $birthYear) . ' Yrs';
                }
                return '-';
            })
            ->editColumn('shio', function ($row) {
                $birthDate   = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen  = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                $shio  = $shioElemen['shio']   ?? null;
                $elemen = $shioElemen['elemen'] ?? null;
                if ($shio && $elemen) {
                    return "{$shio} ({$elemen})";
                }
                return $shio ?: ($elemen ?: '-');
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== ''
                    ? $row->nilai_kecocokan
                    : ($row->matching_score ?? rand(70, 95));
                return $score . '%';
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'interview_user';
            })
            ->addColumn('is_approved_interview_hrd', function ($row) {
                return $row->is_approved_interview_hrd ?? 0;
            })
            ->rawColumns([])
            ->make(true);
    }

    // ─── Update user interview schedule detail ────────────────────────────────

    /**
     * Update or create user-stage RecruitmentInterview schedule details
     * Body: { jenis_interview?, link_gmeet?, ruangan_interview?, tgl_interview? }
     */
    public function updateSchedule(Request $request, $id)
    {
        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        $interview = RecruitmentInterview::where('new_recruitment_id', $id)
            ->where('stage', 'user')
            ->where('is_active', 1)
            ->latest()
            ->first();

        $updateData = [
            'updated_by' => $user,
        ];

        if ($request->filled('jenis_interview')) {
            $updateData['jenis_interview'] = $request->input('jenis_interview');
        }

        if ($request->filled('link_gmeet')) {
            $updateData['link_gmeet'] = $request->input('link_gmeet');
        } elseif ($request->has('link_gmeet')) {
            $updateData['link_gmeet'] = null;
        }

        if ($request->filled('ruangan_interview')) {
            $updateData['ruangan_interview'] = $request->input('ruangan_interview');
        } elseif ($request->has('ruangan_interview')) {
            $updateData['ruangan_interview'] = null;
        }

        if ($request->filled('tgl_interview')) {
            $updateData['tgl_interview'] = $request->input('tgl_interview');
        }

        if ($interview) {
            $interview->update($updateData);
        } else {
            // Create user interview record for the first time
            $interview = RecruitmentInterview::create(array_merge([
                'new_recruitment_id' => $id,
                'stage'              => 'user',
                'jenis_interview'    => $request->input('jenis_interview', 'online'),
                'link_gmeet'         => $request->input('link_gmeet'),
                'ruangan_interview'  => $request->input('ruangan_interview'),
                'tgl_interview'      => $request->input('tgl_interview', Carbon::now()),
                'status_result'      => 'pending',
                'is_active'          => 1,
                'created_by'         => $user,
            ], $updateData));
        }

        // Update candidate status to interview_user if not set yet
        if ($applicant->status !== 'interview_user') {
            $applicant->update(['status' => 'interview_user']);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'User interview schedule saved successfully.',
            'data'    => $interview->fresh(),
        ], 200);
    }
}
