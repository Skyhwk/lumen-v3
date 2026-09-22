<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use App\Services\SallaryOfferService;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateMessageAtsWhatsapp;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
use App\Services\AtsNotificationService;
use App\Services\GenerateToken;
use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Controllers\api\Concerns\OrdersAtsDataTableColumns;
use App\Http\Controllers\api\Concerns\ServesAtsClientSideList;

class AtsInterviewUserController extends Controller
{
    use OrdersAtsDataTableColumns;
    use ServesAtsClientSideList;
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

    private function applyPreparedUserInterviewScope($ui)
    {
        $ui->whereNotNull('tgl_interview')
            ->where('is_active', 1)
            ->where(function ($sub) {
                $sub->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(jenis_interview, ''))) = 'online'")
                        ->whereNotNull('link_gmeet')
                        ->where('link_gmeet', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(jenis_interview, ''))) = 'offline'")
                        ->whereNotNull('ruangan_interview')
                        ->where('ruangan_interview', '!=', '')
                        ->where('ruangan_interview', '!=', '<p></p>')
                        ->whereRaw("TRIM(REPLACE(REPLACE(REPLACE(ruangan_interview, '<p>', ''), '</p>', ''), '&nbsp;', '')) != ''");
                })->orWhere(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(jenis_interview, ''))) NOT IN ('online', 'offline')")
                        ->where(function ($either) {
                            $either->where(function ($l) {
                                $l->whereNotNull('link_gmeet')->where('link_gmeet', '!=', '');
                            })->orWhere(function ($r) {
                                $r->whereNotNull('ruangan_interview')
                                    ->where('ruangan_interview', '!=', '')
                                    ->where('ruangan_interview', '!=', '<p></p>')
                                    ->whereRaw("TRIM(REPLACE(REPLACE(REPLACE(ruangan_interview, '<p>', ''), '</p>', ''), '&nbsp;', '')) != ''");
                            });
                        });
                });
            });
    }

    private function applyUnpreparedUserInterviewScope($ui)
    {
        $ui->whereNotNull('tgl_interview')
            ->where('is_active', 1)
            ->where(function ($sub) {
                $sub->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(jenis_interview, ''))) = 'online'")
                        ->where(function ($link) {
                            $link->whereNull('link_gmeet')->orWhere('link_gmeet', '=', '');
                        });
                })->orWhere(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(jenis_interview, ''))) = 'offline'")
                        ->where(function ($room) {
                            $room->whereNull('ruangan_interview')
                                ->orWhere('ruangan_interview', '=', '')
                                ->orWhere('ruangan_interview', '=', '<p></p>')
                                ->orWhereRaw("TRIM(REPLACE(REPLACE(REPLACE(ruangan_interview, '<p>', ''), '</p>', ''), '&nbsp;', '')) = ''");
                        });
                })->orWhere(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(jenis_interview, ''))) NOT IN ('online', 'offline')")
                        ->where(function ($missing) {
                            $missing->where(function ($l) {
                                $l->whereNull('link_gmeet')->orWhere('link_gmeet', '=', '');
                            })->where(function ($r) {
                                $r->whereNull('ruangan_interview')
                                    ->orWhere('ruangan_interview', '=', '')
                                    ->orWhere('ruangan_interview', '=', '<p></p>')
                                    ->orWhereRaw("TRIM(REPLACE(REPLACE(REPLACE(ruangan_interview, '<p>', ''), '</p>', ''), '&nbsp;', '')) = ''");
                            });
                        });
                });
            });
    }

    private function buildUserInterviewQuery($mode, $year = null, $todayStr = null)
    {
        $todayStr = $todayStr ?: Carbon::today()->toDateString();

        return NewRecruitment::query()
            ->where('is_active', 1)
            ->whereIn('status', ['interview_user'])
            ->whereNotNull('personnel_request_id')
            ->where('personnel_request_id', '!=', '')
            ->where(function ($q) use ($mode, $todayStr) {
                if ($mode === 'waiting_scheduling') {
                    $q->whereDoesntHave('userInterview', function ($ui) {
                        $ui->whereNotNull('tgl_interview')->where('is_active', 1);
                    });
                    return;
                }

                if ($mode === 'waiting_input') {
                    $q->whereHas('userInterview', function ($ui) {
                        $this->applyUnpreparedUserInterviewScope($ui);
                    });
                    return;
                }

                if ($mode === 'today') {
                    $q->whereHas('userInterview', function ($ui) use ($todayStr) {
                        $this->applyPreparedUserInterviewScope($ui);
                        $ui->whereDate('tgl_interview', '=', $todayStr);
                    });
                    return;
                }

                if ($mode === 'upcoming') {
                    $q->whereHas('userInterview', function ($ui) use ($todayStr) {
                        $this->applyPreparedUserInterviewScope($ui);
                        $ui->whereDate('tgl_interview', '>', $todayStr);
                    });
                    return;
                }

                if ($mode === 'past') {
                    $q->whereHas('userInterview', function ($ui) use ($todayStr) {
                        $this->applyPreparedUserInterviewScope($ui);
                        $ui->whereDate('tgl_interview', '<', $todayStr);
                    });
                    return;
                }

                if ($mode === 'scheduled') {
                    $q->whereHas('userInterview', function ($ui) {
                        $ui->whereNotNull('tgl_interview')->where('is_active', 1);
                    });
                    return;
                }

                // Legacy mode: unscheduled
                $q->whereDoesntHave('userInterview', function ($ui) {
                    $ui->whereNotNull('tgl_interview')->where('is_active', 1);
                });
            })
            ->when($year, function ($q) use ($year) {
                return $q->where(function ($sub) use ($year) {
                    $sub->whereYear('created_at', $year)
                        ->orWhereNull('created_at');
                });
            });
    }

    private function newRecruitmentHasColumn($column)
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('new_recruitment')
                ? array_flip(Schema::getColumnListing('new_recruitment'))
                : [];
        }

        return isset($columns[$column]);
    }

    private function whereAnyExistingLike($query, array $columns, $keyword)
    {
        $query->where(function ($sub) use ($columns, $keyword) {
            $applied = false;

            foreach ($columns as $column) {
                if (!$this->newRecruitmentHasColumn($column)) {
                    continue;
                }

                if (!$applied) {
                    $sub->where($column, 'like', "%{$keyword}%");
                    $applied = true;
                    continue;
                }

                $sub->orWhere($column, 'like', "%{$keyword}%");
            }

            if (!$applied) {
                $sub->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Get tab counts for User Interview schedule tabs
     */
    public function counts(Request $request)
    {
        $year = $request->input('year');
        $todayStr = Carbon::today()->toDateString();

        return response()->json([
            'data' => [
                'counts' => [
                    'waiting_input' => $this->buildUserInterviewQuery('waiting_input', $year, $todayStr)->count(),
                    'waiting_scheduling' => $this->buildUserInterviewQuery('waiting_scheduling', $year, $todayStr)->count(),
                    'today' => $this->buildUserInterviewQuery('today', $year, $todayStr)->count(),
                    'upcoming' => $this->buildUserInterviewQuery('upcoming', $year, $todayStr)->count(),
                    'past' => $this->buildUserInterviewQuery('past', $year, $todayStr)->count(),
                ],
            ],
            'message' => 'User Interview tab counts retrieved successfully',
        ], 200);
    }

    // ─── Index — DataTables list of interview_user candidates ─────────────────

    /**
     * List candidates with status = interview_user
     * mode = waiting_input | waiting_scheduling | today | upcoming | past
     * Legacy: scheduled | unscheduled
     */
    public function index(Request $request)
    {
        $mode = $request->input('mode', 'waiting_input');
        $todayStr = Carbon::today()->toDateString();
        $year = $request->filled('year') ? $request->year : null;

        $query = $this->buildUserInterviewQuery($mode, $year, $todayStr)
            ->with(['personalRequest.masterJabatan', 'userInterview', 'hrdInterview'])
            ->orderBy('id', 'desc');

        $datatable = DataTables::of($query)
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
            ->addColumn('waktu_melamar', function ($row) {
                return $row->created_at ?: '-';
            })
            ->filterColumn('waktu_melamar', function ($q, $keyword) {
                if ($this->newRecruitmentHasColumn('created_at')) {
                    $q->where('created_at', 'like', "%{$keyword}%");
                }
            })
            ->addColumn('decision_by', function ($row) {
                $ui = $row->userInterview;
                if (!$ui) {
                    $uiRaw = DB::table('recruitment_interviews')
                        ->where('new_recruitment_id', $row->id)
                        ->where('stage', 'user')
                        ->where('is_active', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!$uiRaw) {
                        $uiRaw = DB::table('recruitment_interviews')
                            ->where('new_recruitment_id', $row->id)
                            ->where('stage', 'user')
                            ->orderBy('id', 'desc')
                            ->first();
                    }

                    return ($uiRaw && !empty($uiRaw->created_by)) ? $uiRaw->created_by : '-';
                }

                return $ui->created_by ?: '-';
            })
            ->filterColumn('decision_by', function ($q, $keyword) {
                $q->whereHas('userInterview', function ($sub) use ($keyword) {
                    $sub->where('created_by', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('jadwal_interview', function ($row) {
                $ui = $row->userInterview;
                if ($ui && $ui->tgl_interview) {
                    $dt = Carbon::parse($ui->tgl_interview);
                    $jenis = strtolower(strip_tags($ui->jenis_interview ?? ''));
                    if ($jenis === 'online') {
                        $detail = 'Online (GMeet)';
                    } else {
                        $room = trim(strip_tags($ui->ruangan_interview ?: 'Office Room'));
                        $detail = 'Offline (' . ($room ?: 'Office Room') . ')';
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
                $ui = $row->userInterview;
                if (!$ui) {
                    $uiRaw = DB::table('recruitment_interviews')
                        ->where('new_recruitment_id', $row->id)
                        ->where('stage', 'user')
                        ->where('is_active', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!$uiRaw) {
                        $uiRaw = DB::table('recruitment_interviews')
                            ->where('new_recruitment_id', $row->id)
                            ->where('stage', 'user')
                            ->orderBy('id', 'desc')
                            ->first();
                    }
                    return $uiRaw ? (array) $uiRaw : null;
                }
                return $ui ? $ui->toArray() : null;
            })
            ->addColumn('hrd_interview', function ($row) {
                $hrd = $row->hrdInterview;
                if (!$hrd) {
                    $hrdRaw = DB::table('recruitment_interviews')
                        ->where('new_recruitment_id', $row->id)
                        ->where('stage', 'hrd')
                        ->where('is_active', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!$hrdRaw) {
                        $hrdRaw = DB::table('recruitment_interviews')
                            ->where('new_recruitment_id', $row->id)
                            ->where('stage', 'hrd')
                            ->orderBy('id', 'desc')
                            ->first();
                    }
                    return $hrdRaw ? (array) $hrdRaw : null;
                }
                return $hrd ? $hrd->toArray() : null;
            })
            ->addColumn('usia', function ($row) {
                $birthYear = $this->extractBirthYear($row);
                if ($birthYear) {
                    return (Carbon::now()->year - $birthYear) . ' Yrs';
                }
                return '-';
            })
            ->filterColumn('usia', function ($q, $keyword) {
                $cleanDigits = preg_replace('/[^0-9]/', '', $keyword);
                $q->where(function ($sub) use ($keyword, $cleanDigits) {
                    if ($cleanDigits !== '') {
                        $targetYear = Carbon::now()->year - (int) $cleanDigits;
                        if ($this->newRecruitmentHasColumn('tanggal_lahir')) {
                            $sub->whereYear('tanggal_lahir', $targetYear);
                        }
                        foreach (['tempat_tanggal_lahir', 'tempat_lahir'] as $column) {
                            if ($this->newRecruitmentHasColumn($column)) {
                                $sub->orWhere($column, 'like', "%{$cleanDigits}%");
                            }
                        }
                        return;
                    }

                    $this->whereAnyExistingLike($sub, ['tempat_tanggal_lahir', 'tempat_lahir'], $keyword);
                });
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
            ->filterColumn('shio', function ($q, $keyword) {
                $this->whereAnyExistingLike($q, [
                    'shio',
                    'elemen',
                    'tempat_tanggal_lahir',
                    'tempat_lahir',
                    'tanggal_lahir',
                ], $keyword);
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== ''
                    ? $row->nilai_kecocokan
                    : ($this->newRecruitmentHasColumn('matching_score') ? ($row->matching_score ?? null) : null);

                if ($score === null || $score === '') {
                    return '-';
                }

                return $score . '%';
            })
            ->filterColumn('nilai_kecocokan', function ($q, $keyword) {
                $cleanVal = preg_replace('/[^0-9.]/', '', $keyword);
                if ($cleanVal === '' || $cleanVal === null) {
                    return;
                }

                $this->whereAnyExistingLike($q, ['nilai_kecocokan', 'matching_score'], $cleanVal);
            })
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('new_recruitment.status', 'like', "%{$keyword}%");
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'interview_user';
            })
            ->addColumn('is_approved_interview_hrd', function ($row) {
                return $row->is_approved_interview_hrd ?? 0;
            })
            ->rawColumns([]);

        $clientSide = $this->serveAtsClientSideList($datatable);
        if ($clientSide) {
            return $clientSide;
        }

        return $this->applyUserInterviewDataTableOrdering($datatable)->make(true);
    }

    // ─── Update user interview schedule detail ────────────────────────────────

    /**
     * Update or create user-stage RecruitmentInterview schedule details
     * Body: { jenis_interview?, link_gmeet?, ruangan_interview?, tgl_interview? }
     */
    public function updateSchedule(Request $request, $id = null)
    {
        $candidateId = $id ?: $request->input('id') ?: $request->header('id');
        $applicant = NewRecruitment::find($candidateId);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        $interview = RecruitmentInterview::where('new_recruitment_id', $candidateId)
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
            $updateData['link_gmeet'] = trim(strip_tags($request->input('link_gmeet')));
        }

        if ($request->filled('ruangan_interview')) {
            $updateData['ruangan_interview'] = trim(strip_tags($request->input('ruangan_interview')));
        }

        if ($request->filled('tgl_interview')) {
            $updateData['tgl_interview'] = $request->input('tgl_interview');
        }

        if ($request->filled('catatan')) {
            $updateData['catatan'] = trim(strip_tags($request->input('catatan')));
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

        // Send Email & WhatsApp Notifications to Candidate and Requesting User
        try {
            $pr = $applicant->personalRequest;
            $posisiName = $this->resolvePositionName($applicant);
            $noRequest  = optional($pr)->no_request ?? '-';

            $tglFormatted = '-';
            if (!empty($interview->tgl_interview)) {
                $tglFormatted = Carbon::parse($interview->tgl_interview)->format('d M Y, H:i') . ' WIB';
            }

            $jenisInterview = strtolower(strip_tags($interview->jenis_interview ?? 'online'));

            // Extract & clean catatan strictly from recruitment_interviews.catatan column
            $rawCatatan = $interview->catatan ?? null;
            $catatanClean = !empty($rawCatatan) ? trim(strip_tags(html_entity_decode($rawCatatan))) : null;

            // 1. Email & WhatsApp to Candidate
            $candidateDataObj = (object) [
                'nama_kandidat'     => $applicant->nama_lengkap,
                'nama_lengkap'      => $applicant->nama_lengkap,
                'jenis_kelamin'     => $applicant->jenis_kelamin,
                'posisi'            => $posisiName,
                'tgl_interview'     => $tglFormatted,
                'jenis_interview'   => $jenisInterview,
                'link_gmeet'        => $interview->link_gmeet,
                'ruangan_interview' => $interview->ruangan_interview,
                'catatan'           => $catatanClean,
            ];

            if (!empty($applicant->email)) {
                $candidateEmailBody = GenerateMessageAtsEmail::bodyEmailUserInterviewCandidate($candidateDataObj);
                SendEmail::where('to', trim($applicant->email))
                    ->where('subject', "Jadwal User Interview — PT Inti Surya Laboratorium")
                    ->where('body', $candidateEmailBody)
                    ->where('karyawan', $user)
                    ->noReply()
                    ->replyToAtsHrd()
                    ->send();
            }

            $candidatePhone = $applicant->no_telepon ?: ($applicant->no_hp ?: ($applicant->no_whatsapp ?? null));
            if (!empty($candidatePhone)) {
                $waCandidateGen = new GenerateMessageAtsWhatsapp($candidateDataObj);
                $waCandidateMsg = $waCandidateGen->UserInterviewScheduleCandidate();
                $sendWaCandidate = new SendWhatsapp(trim($candidatePhone), $waCandidateMsg);
                $sendWaCandidate->send();
            }

            // 2. Email only to Requesting User (PR Creator)
            $prCreatedBy = $pr->created_by ?? null;
            $prUser = null;
            if ($prCreatedBy) {
                $prUser = DB::table('master_karyawan')->where('nama_lengkap', $prCreatedBy)->first();
                if (!$prUser) {
                    $prUser = DB::table('master_karyawan')->where('id_karyawan', $prCreatedBy)->first();
                }
            }
            if (!$prUser && $pr && !empty($pr->email)) {
                $prUser = DB::table('master_karyawan')->where('email', $pr->email)->first();
            }

            $userEmail = $prUser->email ?? ($pr->email ?? null);
            $userName  = $prUser->nama_lengkap ?? ($prCreatedBy ?: 'User');

            $userDataObj = (object) [
                'nama_user'         => $userName,
                'nama_kandidat'     => $applicant->nama_lengkap,
                'posisi'            => $posisiName,
                'no_request'        => $noRequest,
                'tgl_interview'     => $tglFormatted,
                'jenis_interview'   => $jenisInterview,
                'link_gmeet'        => $interview->link_gmeet,
                'ruangan_interview' => $interview->ruangan_interview,
                'catatan'           => $catatanClean,
            ];

            if (!empty($userEmail)) {
                $userEmailBody = GenerateMessageAtsEmail::bodyEmailUserInterviewUserNotif($userDataObj);
                SendEmail::where('to', trim($userEmail))
                    ->where('subject', "Pemberitahuan Sesi User Interview — {$applicant->nama_lengkap} ({$posisiName})")
                    ->where('body', $userEmailBody)
                    ->where('karyawan', $user)
                    ->noReply()
                    ->send();
            }

            app(AtsNotificationService::class)->userInterviewSchedulePrepared(
                $applicant,
                $pr
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed sending User Interview notifications: " . $e->getMessage());
        }

        return response()->json([
            'status'  => 200,
            'message' => 'User interview schedule saved successfully.',
            'data'    => $interview->fresh(),
        ], 200);
    }

    /**
     * Lewati proses User Interview dan pindahkan kandidat ke Final Decision.
     * Email persetujuan Direktur tidak dikirim otomatis; HRD mengirimkannya
     * dari modul Final Decision setelah bypass selesai.
     */
    public function bypassToFinalDecision(Request $request, $id = null)
    {
        $allowedGrades = ['MANAGER', 'DIREKSI', 'DIREKTUR'];
        if (!in_array(strtoupper(trim((string) $this->grade)), $allowedGrades, true)) {
            return response()->json([
                'status' => 403,
                'message' => 'Bypass User Interview hanya dapat dilakukan oleh Manager atau Direksi.',
            ], 403);
        }

        $candidateId = $id ?: $request->input('id') ?: $request->header('id');
        $applicant = NewRecruitment::find($candidateId);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        if (strtolower(trim((string) $applicant->status)) !== 'interview_user') {
            return response()->json([
                'status' => 422,
                'message' => 'Bypass hanya tersedia untuk kandidat pada tahap User Interview.',
            ], 422);
        }

        $pr = $applicant->personalRequest;
        if (!$pr) {
            return response()->json([
                'status' => 422,
                'message' => 'Data personnel request tidak ditemukan.',
            ], 422);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';
        $description = trim((string) $request->input('description'));
        $plainDescription = preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainDescription = trim($plainDescription);
        if ($plainDescription === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Keterangan bypass wajib diisi.',
            ], 422);
        }
        $now = Carbon::now();

        DB::beginTransaction();
        try {
            $interview = RecruitmentInterview::where('new_recruitment_id', $applicant->id)
                ->where('stage', 'user')
                ->where('is_active', 1)
                ->latest('id')
                ->first();

            if ($interview) {
                $interview->update([
                    'status_result' => 'bypassed',
                    'updated_by' => $user,
                ]);
            }

            $tokenService = new GenerateToken();
            $tokenKey = $pr->id . $applicant->nama_lengkap . 'approval' . str_replace('.', '', microtime(true));
            $token = $tokenService->encrypt(md5($tokenKey) . '|' . $tokenService->encrypt(date('Y-m-d')));

            $history = RecruitmentStatusService::parseMetaHistory($applicant);
            $history[] = [
                'status' => 'user_interview_bypassed',
                'at' => $now->toDateTimeString(),
                'by' => $user,
                'description' => $description,
                'bypassed_processes' => ['user_interview'],
            ];
            // Tetap letakkan status tujuan sebagai entry terakhir agar pembacaan
            // meta_history berbasis status terakhir tetap melihat Final Decision.
            $history[] = [
                'status' => 'management_decision',
                'at' => $now->toDateTimeString(),
                'by' => $user,
                'source' => 'user_interview_bypass',
            ];

            $applicant->update([
                'approved_interview_user' => $user,
                'approved_interview_user_at' => $now,
                'is_approve_interview_user' => 1,
                'token_approval' => $token,
                'status' => 'management_decision',
                'meta_history' => json_encode(array_values($history)),
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'User Interview berhasil dibypass. Kandidat siap dikirim ke Final Decision.',
                'data' => $applicant->fresh(),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Gagal melakukan bypass User Interview: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function salaryOffer(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'userInterview', 'sallaryOffer'])
            ->where('status', 'internal_sallary_offer')
            ->whereNotNull('personnel_request_id')
            ->where('personnel_request_id', '!=', '')
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
            ->addColumn('sallary_offer', function ($row) {
                return $row->sallaryOffer;
            })
            ->addColumn('usia', function ($row) {
                $ttl = $this->getTtlString($row);
                $birthYear = $this->extractBirthYear($ttl);
                if ($birthYear) {
                    return (Carbon::now()->year - $birthYear) . ' Yrs';
                }
                return '-';
            })
            ->filterColumn('usia', function ($q, $keyword) {
                $cleanDigits = preg_replace('/[^0-9]/', '', $keyword);
                $q->where(function ($sub) use ($keyword, $cleanDigits) {
                    if ($cleanDigits !== '') {
                        $targetYear = Carbon::now()->year - (int) $cleanDigits;
                        if ($this->newRecruitmentHasColumn('tanggal_lahir')) {
                            $sub->whereYear('tanggal_lahir', $targetYear);
                        }
                        foreach (['tempat_tanggal_lahir', 'tempat_lahir'] as $column) {
                            if ($this->newRecruitmentHasColumn($column)) {
                                $sub->orWhere($column, 'like', "%{$cleanDigits}%");
                            }
                        }
                        return;
                    }

                    $this->whereAnyExistingLike($sub, ['tempat_tanggal_lahir', 'tempat_lahir'], $keyword);
                });
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
            ->filterColumn('shio', function ($q, $keyword) {
                $this->whereAnyExistingLike($q, [
                    'shio',
                    'elemen',
                    'tempat_tanggal_lahir',
                    'tempat_lahir',
                    'tanggal_lahir',
                ], $keyword);
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== ''
                    ? $row->nilai_kecocokan
                    : ($this->newRecruitmentHasColumn('matching_score') ? ($row->matching_score ?? null) : null);

                if ($score === null || $score === '') {
                    return '-';
                }

                return $score . '%';
            })
            ->filterColumn('nilai_kecocokan', function ($q, $keyword) {
                $cleanVal = preg_replace('/[^0-9.]/', '', $keyword);
                if ($cleanVal === '' || $cleanVal === null) {
                    return;
                }

                $this->whereAnyExistingLike($q, ['nilai_kecocokan', 'matching_score'], $cleanVal);
            })
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('new_recruitment.status', 'like', "%{$keyword}%");
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'interview_user';
            })
            ->addColumn('is_approved_interview_hrd', function ($row) {
                return $row->is_approved_interview_hrd ?? 0;
            })
            ->addColumn('expected_salary', function ($row) {
                $offer = $row->sallaryOffer;
                if ($offer && $offer->sallary_offer_hrd !== null && $offer->sallary_offer_hrd !== '') {
                    return $offer->sallary_offer_hrd;
                }
                return $row->ekspetasi_gaji;
            })
            ->filterColumn('expected_salary', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('ekspetasi_gaji', 'like', "%{$keyword}%")
                        ->orWhereHas('sallaryOffer', function ($so) use ($keyword) {
                            $so->where('sallary_offer_hrd', 'like', "%{$keyword}%");
                        });
                });
            })
            ->rawColumns([])
            ->make(true);
    }

    public function updateExpectedSalary(Request $request, $id = null)
    {
        $id = $id ?? $request->header('id') ?? $request->input('id');

        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        $expectedSalary = $request->input('expected_salary') ?? $request->input('ekspetasi_gaji');

        if ($expectedSalary !== null) {
            $cleanSalary = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', $expectedSalary)));
            $valueToSave = $cleanSalary !== '' ? $cleanSalary : $expectedSalary;

            $applicant->ekspetasi_gaji = $valueToSave;
            $applicant->save();

            $user = $this->karyawan;

            $offerData = [
                'sallary_offer_hrd' => $valueToSave,
                'updated_by'        => $user,
            ];

            if ($request->has('sallary_offer_direktur')) {
                $offerData['sallary_offer_direktur'] = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', $request->input('sallary_offer_direktur'))));
            }

            if ($request->has('final_sallary')) {
                $offerData['final_sallary'] = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', $request->input('final_sallary'))));
            }

            SallaryOfferService::upsertActive(
                (int) $id,
                $offerData,
                $user
            );
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Expected salary updated successfully.',
            'data'    => $applicant->fresh(),
        ], 200);
    }

    public function sendOfferingSalaryEmail(Request $request, $id = null)
    {
        $id = $id ?? $request->header('id') ?? $request->input('id');

        $applicant = NewRecruitment::with([
            'personalRequest.masterJabatan', 
            'masterJabatan', 
            'sallaryOffer', 
            'hrdInterview', 
            'candidateProfile', 
            'candidateEducations', 
            'candidateWorkExperiences'
        ])->find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        $targetEmail = env('EMAIL_DIREKTUR_BAPAK');
        // $targetEmail = 'abdulpatah@intilab.com';

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        try {
            $bodyEmail = GenerateMessageAtsEmail::bodyEmailSallaryOffer($applicant);

            SendEmail::where('to', $targetEmail)
                ->where('subject', 'Permohonan Persetujuan Offering Salary - ' . ($applicant->nama_lengkap ?? 'Kandidat'))
                ->where('body', $bodyEmail)
                ->where('karyawan', $user)
                ->noReply()
                ->send();

            return response()->json([
                'status'  => 200,
                'message' => 'Offering salary approval email sent successfully to ' . $targetEmail,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }
}

