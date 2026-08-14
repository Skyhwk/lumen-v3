<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use App\Models\SallaryOffer;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateMessageAtsWhatsapp;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
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

    // ─── Index — DataTables list of interview_user candidates ─────────────────

    /**
     * List candidates with status = interview_user, grouped by today / upcoming-past
     */
    public function index(Request $request)
    {
        $mode = $request->input('mode', 'scheduled');

        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'userInterview', 'hrdInterview'])
            ->whereIn('status', ['interview_user', 'profile_completion'])
            ->where(function ($q) use ($mode) {
                if ($mode === 'scheduled') {
                    // Candidates with an active User Interview schedule set
                    $q->whereHas('userInterview', function ($ui) {
                        $ui->whereNotNull('tgl_interview')->where('is_active', 1);
                    });
                } else {
                    // mode = 'unscheduled' (pending User Interview schedule creation)
                    $q->whereDoesntHave('userInterview', function ($ui) {
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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed sending User Interview notifications: " . $e->getMessage());
        }

        return response()->json([
            'status'  => 200,
            'message' => 'User interview schedule saved successfully.',
            'data'    => $interview->fresh(),
        ], 200);
    }

    public function salaryOffer(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'userInterview', 'sallaryOffer'])
            ->where('status', 'internal_sallary_offer')
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

            SallaryOffer::updateOrCreate(
                ['new_recruitment_id' => $id],
                array_merge($offerData, [
                    'created_by' => $user,
                ])
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

        $targetEmail = 'abdulpatah@intilab.com';
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

