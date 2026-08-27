<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateMessageAtsWhatsapp;
use App\Services\MpdfService;
use App\Services\RecruitmentStatusService;
use App\Services\RecruitmentPictureService;
use App\Services\AtsCvPdfSectionsBuilder;
use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
use App\Services\AtsNotificationService;
use App\Models\CandidateProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Mpdf\Output\Destination;

class DataApplicantsController extends Controller
{
    use BuildsCandidateAssessmentPreview;

    /**
     * Get Datatable list of applicants (Initial Assessment Stage)
     */
    public function index(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview'])
            ->where('status', 'screening')
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
            ->editColumn('status', function ($row) {
                return $row->status ?: 'assessment';
            })
            ->addColumn('hrd_interview', function ($row) {
                return $row->hrdInterview;
            })
            ->addColumn('user_interview', function ($row) {
                return $row->userInterview;
            })
            ->make(true);
    }

    /**
     * Get single candidate detail for applicant detail modal
     */
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
                'candidate' => $this->formatCandidatePreviewItem($candidate, $pictureService),
                'request' => $personnelRequest ? [
                    'id' => $personnelRequest->id,
                    'no_request' => $personnelRequest->no_request,
                    'posisi' => optional($personnelRequest->masterJabatan)->nama_jabatan ?: $personnelRequest->posisi,
                    'divisi' => optional($personnelRequest->masterDivisi)->nama_divisi ?: ($personnelRequest->divisi_alias ?: $personnelRequest->divisi),
                    'minimum_matching' => $personnelRequest->minimum_matching,
                ] : null,
            ],
        ], 200);
    }

    /**
     * Approve applicant, move candidate to 'interview_hrd' stage, schedule HRD interview & send Corporate Email + WhatsApp notifications
     */
    public function approve(Request $request, $id)
    {
        $applicant = NewRecruitment::with('personalRequest.masterJabatan')->find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Applicant data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        $tglInterview = $request->input('tgl_interview');
        $jenisInterview = $request->input('jenis_interview', 'Online');
        $linkGmeet = $request->input('link_gmeet');
        $ruanganInterview = $request->input('ruangan_interview');

        // 1. Update applicant status to 'interview_hrd' with RecruitmentStatusService meta_history tracking
        (new RecruitmentStatusService())->update($applicant->id, 'interview_hrd', Carbon::now());

        $applicant->update([
            'status' => 'interview_hrd',
            'approved_by' => $user,
            'approved_at' => Carbon::now(),
        ]);

        // 2. Deactivate previous HRD interview schedules & create active record in recruitment_interviews table
        RecruitmentInterview::where('new_recruitment_id', $applicant->id)
            ->where('stage', 'hrd')
            ->update(['is_active' => 0]);

        $interview = RecruitmentInterview::create([
            'new_recruitment_id' => $applicant->id,
            'stage' => 'hrd',
            'is_active' => 1,
            'tgl_interview' => $tglInterview,
            'jenis_interview' => $jenisInterview,
            'link_gmeet' => $jenisInterview === 'Online' ? $linkGmeet : null,
            'ruangan_interview' => $jenisInterview === 'Offline' ? $ruanganInterview : null,
            'status_result' => 'pending',
            'created_by' => $user,
            'updated_by' => $user,
        ]);

        // 3. Send Corporate Email & WhatsApp Notifications
        try {
            $dt = Carbon::parse($tglInterview);
            $days = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hariIndonesia = $days[$dt->format('l')] ?? $dt->format('l');
            $tglInter = $dt->format('d F Y');
            $jamInterview = $dt->format('H:i');

            $posisiName = $this->resolvePositionName($applicant);

            $dataArray = (object) [
                'nama_lengkap' => $applicant->nama_lengkap,
                'jenis_kelamin' => $applicant->jenis_kelamin,
                'posisi_di_lamar' => $posisiName,
                'nama_jabatan' => $posisiName,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview' => $jamInterview,
                'jam_interview_hrd' => $jamInterview,
                'jenis_interview_hrd' => $jenisInterview,
                'link_gmeet_hrd' => $jenisInterview === 'Online' ? $linkGmeet : null,
                'alamat_cabang' => $jenisInterview === 'Offline' ? $ruanganInterview : 'Online Meeting',
                'kode_uniq' => $applicant->id,
            ];

            if (!empty($applicant->email)) {
                $bodyEmail = GenerateMessageAtsEmail::bodyEmailApproveKandidat($dataArray);
                SendEmail::where('to', $applicant->email)
                    ->where('subject', 'Undangan Interview HRD - PT Inti Surya Laboratorium')
                    ->where('body', $bodyEmail)
                    ->where('karyawan', $user)
                    ->noReply()
                    ->replyToAtsHrd()
                    ->send();
            }

            $phone = $applicant->no_telepon ?: ($applicant->no_whatsapp ?? ($applicant->no_hp ?? null));
            if (!empty($phone)) {
                $waObj = new GenerateMessageAtsWhatsapp($dataArray);
                $waMessage = $waObj->PassedCandidateSelection();

                $sendWa = new SendWhatsapp($phone, $waMessage);
                $sendWa->send();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ATS HRD Interview Notification Error: ' . $e->getMessage());
        }

        app(AtsNotificationService::class)->applicantApprovedForHrdInterview(
            $applicant,
            $applicant->personalRequest
        );

        return response()->json([
            'status' => 200,
            'message' => 'Applicant approved successfully and moved to HRD Interview stage.',
            'data' => $applicant->load(['hrdInterview', 'userInterview']),
        ], 200);
    }

    /**
     * Reject applicant & send dignified rejection notifications
     */
    public function reject(Request $request, $id)
    {
        $applicant = NewRecruitment::with('personalRequest.masterJabatan')->find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Applicant data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';
        $reason = $request->input('alasan_reject') ?? 'Did not pass initial qualification evaluation';

        // Update applicant status to 'rejected' with RecruitmentStatusService meta_history tracking
        (new RecruitmentStatusService())->update($applicant->id, 'rejected', Carbon::now());

        $applicant->update([
            // 'status' => 'screening',
            'rejected_by' => $user,
            'rejected_at' => Carbon::now(),
            'alasan_reject' => $reason,
        ]);

        try {
            $posisiName = $this->resolvePositionName($applicant);

            $dataArray = (object) [
                'nama_lengkap' => $applicant->nama_lengkap,
                'jenis_kelamin' => $applicant->jenis_kelamin,
                'posisi_di_lamar' => $posisiName,
                'nama_jabatan' => $posisiName,
                'alasan_reject' => $reason,
                'hrd_name' => $user,
            ];

            if (!empty($applicant->email)) {
                $bodyEmail = GenerateMessageAtsEmail::bodyEmailRejectKandidat($dataArray);
                SendEmail::where('to', $applicant->email)
                    ->where('subject', 'Informasi Hasil Seleksi - PT Inti Surya Laboratorium')
                    ->where('body', $bodyEmail)
                    ->where('karyawan', $user)
                    ->noReply()
                    ->replyToAtsHrd()
                    ->send();
            }

            $phone = $applicant->no_telepon ?: ($applicant->no_hp ?? null);
            if (!empty($phone)) {
                $waObj = new GenerateMessageAtsWhatsapp($dataArray);
                $waMessage = $waObj->RejectedCandidateSelection();

                $sendWa = new SendWhatsapp($phone, $waMessage);
                $sendWa->send();
            }
        } catch (\Exception $e) {
            // Silence exception
        }

        app(AtsNotificationService::class)->notifyPersonnelRequestCreator(
            $applicant->personalRequest,
            'Kandidat Ditolak Screening',
            "Kandidat {$applicant->nama_lengkap} ditolak pada tahap screening.",
            AtsNotificationService::URL_DATA_APPLICANTS
        );

        return response()->json([
            'status' => 200,
            'message' => 'Applicant has been rejected.',
            'data' => $applicant,
        ], 200);
    }

    /**
     * Generate ATS CV PDF via MPDF — enriched with candidate_profile data
     */
    public function generateCvPdf(Request $request, $id)
    {
        $applicant = NewRecruitment::with([
            'personalRequest.masterJabatan',
            'hrdInterview',
            'userInterview',
            'candidateProfile.educations' => function ($query) {
                $query->where('is_active', 1);
            },
            'candidateProfile.workExperiences' => function ($query) {
                $query->where('is_active', 1);
            },
        ])->find($id);

        if (!$applicant) {
            return response()->json(['message' => 'Applicant data not found'], 404);
        }

        $cp = $applicant->candidateProfile ?? null;

        // ── Core identity — prefer candidate_profile, fallback to new_recruitment ──
        $namaLengkap   = ($cp && $cp->nama_lengkap)  ? $cp->nama_lengkap   : ($applicant->nama_lengkap ?? '-');
        $namaPanggilan = ($cp && $cp->nama_panggilan) ? $cp->nama_panggilan : '-';
        $email         = ($cp && $cp->email)          ? $cp->email          : ($applicant->email ?? '-');
        $noTelepon     = ($cp && $cp->no_telepon)     ? $cp->no_telepon     : ($applicant->no_telepon ?? '-');
        $noWhatsapp    = ($cp && $cp->no_whatsapp)    ? $cp->no_whatsapp    : '-';
        $nikKtp        = ($cp && $cp->nik_ktp)        ? $cp->nik_ktp        : '-';
        $noKK          = ($cp && $cp->no_kk)          ? $cp->no_kk          : '-';
        $noNpwp        = ($cp && $cp->no_npwp)        ? $cp->no_npwp        : '-';
        $noBpjsKs      = ($cp && $cp->no_bpjs_ks)     ? $cp->no_bpjs_ks    : '-';
        $noBpjsTk      = ($cp && $cp->no_bpjs_tk)     ? $cp->no_bpjs_tk    : '-';
        $rawGender     = ($cp && !empty($cp->jenis_kelamin)) ? $cp->jenis_kelamin : ($applicant->jenis_kelamin ?? null);
        if ($rawGender) {
            $gLower = strtolower(trim($rawGender));
            if ($gLower === 'male' || $gLower === 'laki-laki' || $gLower === 'l') {
                $jenisKelamin = 'Laki-laki (Male)';
            } elseif ($gLower === 'female' || $gLower === 'perempuan' || $gLower === 'p') {
                $jenisKelamin = 'Perempuan (Female)';
            } else {
                $jenisKelamin = ucfirst($rawGender);
            }
        } else {
            $jenisKelamin = '-';
        }
        $agama         = ($cp && $cp->agama)          ? $cp->agama          : ($applicant->agama ?? '-');
        $statusNikah   = ($cp && $cp->status_pernikahan) ? $cp->status_pernikahan : ($applicant->status_nikah ?? '-');
        $medicalInfo   = DB::table('candidate_medical_informations')
            ->where(function($q) use ($cp, $applicant) {
                if ($cp && $cp->id) {
                    $q->where('candidate_profile_id', $cp->id);
                }
                $q->orWhere('new_recruitment_id', $applicant->id);
            })
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->first();

        $tinggiBadan       = ($medicalInfo && $medicalInfo->tinggi_badan) ? $medicalInfo->tinggi_badan . ' cm' : '-';
        $beratBadan        = ($medicalInfo && $medicalInfo->berat_badan) ? $medicalInfo->berat_badan . ' kg' : '-';
        $kondisiMata       = ($medicalInfo && $medicalInfo->mata) ? $medicalInfo->mata : '-';
        $golDarah          = ($medicalInfo && $medicalInfo->golongan_darah) ? $medicalInfo->golongan_darah : (($cp && isset($cp->golongan_darah)) ? $cp->golongan_darah : '-');
        $penyakitBawaan    = ($medicalInfo && $medicalInfo->penyakit_bawaan_lahir) ? $medicalInfo->penyakit_bawaan_lahir : '-';
        $penyakitKronis    = ($medicalInfo && $medicalInfo->penyakit_kronis) ? $medicalInfo->penyakit_kronis : '-';
        $riwayatKecelakaan = ($medicalInfo && $medicalInfo->riwayat_kecelakaan) ? $medicalInfo->riwayat_kecelakaan : '-';

        // ── Birth date & place ──
        $tempatLahir = ($cp && $cp->tempat_lahir) ? $cp->tempat_lahir : ($applicant->tempat_lahir ?? null);
        $tglLahirRaw = ($cp && $cp->tanggal_lahir) ? $cp->tanggal_lahir : ($applicant->tanggal_lahir ?? null);

        $tglLahirStr = null;
        $birthYear   = null;

        if (!empty($tglLahirRaw)) {
            try {
                $tglLahirCarbon = Carbon::parse($tglLahirRaw);
                $tglLahirStr    = $tglLahirCarbon->format('Y-m-d');
                $birthYear      = (int) $tglLahirCarbon->year;
            } catch (\Exception $e) {
                $tglLahirStr = $tglLahirRaw;
                $birthYear   = $this->extractBirthYear($tglLahirRaw);
            }
        } elseif (!empty($applicant->tempat_tanggal_lahir)) {
            $tglLahirStr = $applicant->tempat_tanggal_lahir;
            $birthYear   = $this->extractBirthYear($tglLahirStr);
        }

        if ($tempatLahir && $tglLahirStr) {
            if (stripos($tglLahirStr, $tempatLahir) === 0) {
                $ttlDisplay = $tglLahirStr;
            } else {
                $ttlDisplay = $tempatLahir . ', ' . $tglLahirStr;
            }
        } else {
            $ttlDisplay = $tempatLahir ?: ($tglLahirStr ?: '-');
        }

        $usia = $birthYear ? (Carbon::now()->year - $birthYear) . ' Years' : '-';

        // ── Shio & Elemen ──
        $birthDateForShio = ($cp && $cp->tanggal_lahir) ? $cp->tanggal_lahir : ($applicant->tanggal_lahir ?? $this->getTtlString($applicant));
        $shioElemen = ShioElemenHelper::resolve($birthDateForShio, $applicant->shio, $applicant->elemen);
        $shio       = $shioElemen['shio']   ?? '-';
        $elemen     = $shioElemen['elemen'] ?? '-';

        // ── Address ──
        $buildAddress = function ($mainAddress, $kota, $provinsi, $kodePos) {
            $mainAddress = trim($mainAddress ?? '');
            if (empty($mainAddress)) {
                return implode(', ', array_filter([$kota, $provinsi, $kodePos ? 'Kode Pos: ' . $kodePos : null])) ?: '-';
            }

            $cleanMain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $mainAddress));
            $parts = [];

            if (!empty($kota)) {
                $cleanKota = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(['kab.', 'kabupaten', 'kota'], '', strtolower($kota))));
                if (!empty($cleanKota) && (empty($cleanMain) || strpos($cleanMain, $cleanKota) === false)) {
                    $parts[] = $kota;
                }
            }

            if (!empty($provinsi)) {
                $cleanProv = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(['provinsi', 'prov.'], '', strtolower($provinsi))));
                if (!empty($cleanProv) && (empty($cleanMain) || strpos($cleanMain, $cleanProv) === false)) {
                    $parts[] = $provinsi;
                }
            }

            if (!empty($kodePos)) {
                $cleanPos = strtolower(preg_replace('/[^0-9]/', '', (string)$kodePos));
                if (!empty($cleanPos) && (empty($cleanMain) || strpos($cleanMain, $cleanPos) === false)) {
                    $parts[] = 'Kode Pos: ' . $kodePos;
                }
            }

            if (count($parts) > 0) {
                return $mainAddress . ', ' . implode(', ', $parts);
            }

            return $mainAddress;
        };

        if ($cp) {
            $rawKtpAddress = $cp->alamat_ktp ?: ($applicant->alamat_ktp ?? null);
            $rawDomAddress = $cp->alamat_domisili ?: ($applicant->alamat_domisili ?? null);

            $alamatKtp      = $buildAddress($rawKtpAddress, $cp->kota_ktp, $cp->provinsi_ktp, $cp->kode_pos_ktp);
            $alamatDomisili = $buildAddress($rawDomAddress, $cp->kota_domisili, $cp->provinsi_domisili, $cp->kode_pos_domisili);
            $statusTinggal  = $cp->status_tempat_tinggal ?? '-';
        } else {
            $alamatKtp      = $applicant->alamat_ktp ?? '-';
            $alamatDomisili = $applicant->alamat_domisili ?? '-';
            $statusTinggal  = '-';
        }

        // ── Emergency contact ──
        $namaKontakDarurat = ($cp && $cp->nama_kontak_darurat)    ? $cp->nama_kontak_darurat    : '-';
        $hubKontakDarurat  = ($cp && $cp->hubungan_kontak_darurat) ? $cp->hubungan_kontak_darurat : '-';
        $noTelpDarurat     = ($cp && $cp->no_telepon_darurat)      ? $cp->no_telepon_darurat      : '-';

        // ── Salary (dari new_recruitment, candidate_profiles tidak punya kolom ini) ──
        $gajiTerakhirFmt  = $applicant->gaji_terakhir  ? 'Rp ' . number_format($applicant->gaji_terakhir,  0, ',', '.') : '-';
        $ekspetasiGajiFmt = $applicant->ekspetasi_gaji ? 'Rp ' . number_format($applicant->ekspetasi_gaji, 0, ',', '.') : '-';

        // ── Position & score ──
        $posisiName = $this->resolvePositionName($applicant);
        $score      = $applicant->nilai_kecocokan ?: ($applicant->matching_score ?: 85);
        $photoDataUri = app(RecruitmentPictureService::class)->toDataUri($applicant->picture ?? null);
        $nameParts = preg_split('/\s+/', trim((string) $namaLengkap));
        $initials = '';
        foreach (array_slice(array_filter($nameParts), 0, 2) as $namePart) {
            $initials .= strtoupper(substr($namePart, 0, 1));
        }
        $initials = $initials ?: 'CV';
        $photoHtml = $photoDataUri
            ? "<img class='profile-photo' src='{$photoDataUri}' alt='Candidate photo'>"
            : "<div class='profile-placeholder'>{$initials}</div>";

        // ── Earliest Join ──
        $tanggalJoin = $applicant->tanggal_join_tercepat
            ? Carbon::parse($applicant->tanggal_join_tercepat)->format('d F Y')
            : '-';

        // ── Education HTML ──
        $pendidikanList = [];
        if ($cp && $cp->educations && $cp->educations->count() > 0) {
            foreach ($cp->educations as $edu) {
                $pendidikanList[] = [
                    'jenjang'     => $edu->jenjang_pendidikan ?? '',
                    'jurusan'     => $edu->jurusan ?? '',
                    'institusi'   => $edu->nama_institusi ?? '',
                    'kota'        => '',
                    'tahun_masuk' => $edu->tahun_masuk ?? '',
                    'tahun_lulus' => $edu->tahun_lulus ?? '',
                    'ipk'         => ($edu->nilai_ipk !== null && $edu->nilai_ipk > 0) ? $edu->nilai_ipk : null,
                ];
            }
        } else {
            $rawPendidikan = $applicant->pendidikan;
            if (is_string($rawPendidikan)) {
                $rawPendidikan = json_decode($rawPendidikan, true);
            }
            if (is_array($rawPendidikan) && count($rawPendidikan) > 0) {
                foreach ($rawPendidikan as $edu) {
                    if (!is_array($edu)) continue;
                    $pendidikanList[] = [
                        'jenjang'     => $edu['jenjang'] ?? $edu['degree'] ?? '',
                        'jurusan'     => $edu['jurusan'] ?? $edu['major'] ?? '',
                        'institusi'   => $edu['institusi'] ?? $edu['school'] ?? '',
                        'kota'        => $edu['kota'] ?? '',
                        'tahun_masuk' => $edu['tahun_masuk'] ?? '',
                        'tahun_lulus' => $edu['tahun_lulus'] ?? $edu['tahun'] ?? $edu['year'] ?? '',
                        'ipk'         => $edu['gpa'] ?? $edu['ipk'] ?? null,
                    ];
                }
            }
        }

        $pendidikanHtml = '';
        if (count($pendidikanList) > 0) {
            foreach ($pendidikanList as $edu) {
                $jenjang   = $edu['jenjang'] ?: 'Education';
                $institusi = $edu['institusi'] ?: '-';
                $jurusan   = $edu['jurusan'] ? " — {$edu['jurusan']}" : '';
                $kota      = $edu['kota'] ? " ({$edu['kota']})" : '';
                $masuk     = $edu['tahun_masuk'] ?? '';
                $lulus     = $edu['tahun_lulus'] ?? '';
                $periode   = ($masuk && $lulus) ? "{$masuk} – {$lulus}" : ($lulus ?: ($masuk ?: '-'));
                $ipkStr    = !empty($edu['ipk']) ? " <span style='color:#64748b;'>(IPK: {$edu['ipk']})</span>" : '';

                $pendidikanHtml .= "
                <div style='margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0;'>
                    <strong style='font-size: 13px; color: #1e293b;'>{$jenjang}{$jurusan}</strong> - <span>{$institusi}{$kota}</span>{$ipkStr}<br>
                    <span style='font-size: 11px; color: #64748b;'>Periode: {$periode}</span>
                </div>";
            }
        } else {
            $pendidikanHtml = "<p style='color: #64748b; font-size: 11px; margin: 0;'>No education record found.</p>";
        }

        // ── Work Experience HTML ──
        $pengalamanList = [];
        if ($cp && $cp->workExperiences && $cp->workExperiences->count() > 0) {
            foreach ($cp->workExperiences as $exp) {
                $pengalamanList[] = [
                    'posisi_kerja'      => $exp->posisi_terakhir ?? '',
                    'nama_perusahaan'   => $exp->nama_perusahaan ?? '',
                    'lokasi_perusahaan' => '',
                    'mulai_kerja'       => $exp->tanggal_mulai ? Carbon::parse($exp->tanggal_mulai)->format('M Y') : '',
                    'akhir_kerja'       => $exp->tanggal_selesai ? Carbon::parse($exp->tanggal_selesai)->format('M Y') : 'Present',
                    'alasan_keluar'     => $exp->alasan_resign ?? '',
                    'deskripsi'         => '',
                ];
            }
        } else {
            $rawPengalaman = $applicant->pengalaman_kerja;
            if (is_string($rawPengalaman)) {
                $rawPengalaman = json_decode($rawPengalaman, true);
            }
            if (is_array($rawPengalaman) && count($rawPengalaman) > 0) {
                foreach ($rawPengalaman as $exp) {
                    if (!is_array($exp)) continue;

                    $mulaiRaw = $exp['mulai_kerja'] ?? $exp['mulai'] ?? null;
                    $akhirRaw = $exp['akhir_kerja'] ?? $exp['akhir'] ?? null;

                    $mulaiFmt = '';
                    if (!empty($mulaiRaw)) {
                        try {
                            $mulaiFmt = Carbon::parse($mulaiRaw)->format('M Y');
                        } catch (\Exception $e) {
                            $mulaiFmt = $mulaiRaw;
                        }
                    }

                    $akhirFmt = '';
                    if (!empty($akhirRaw)) {
                        try {
                            $akhirFmt = Carbon::parse($akhirRaw)->format('M Y');
                        } catch (\Exception $e) {
                            $akhirFmt = $akhirRaw;
                        }
                    } else {
                        $akhirFmt = 'Present';
                    }

                    $pengalamanList[] = [
                        'posisi_kerja'      => $exp['posisi_kerja'] ?? $exp['posisi'] ?? $exp['position'] ?? '',
                        'nama_perusahaan'   => $exp['nama_perusahaan'] ?? $exp['perusahaan'] ?? $exp['company'] ?? '',
                        'lokasi_perusahaan' => $exp['lokasi_perusahaan'] ?? $exp['lokasi'] ?? $exp['location'] ?? '',
                        'mulai_kerja'       => $mulaiFmt,
                        'akhir_kerja'       => $akhirFmt,
                        'periode_manual'    => $exp['periode'] ?? $exp['period'] ?? '',
                        'alasan_keluar'     => $exp['alasan_keluar'] ?? $exp['alasan_resign'] ?? $exp['alasan'] ?? '',
                        'deskripsi'         => $exp['deskripsi'] ?? $exp['description'] ?? '',
                    ];
                }
            }
        }

        $pengalamanHtml = '';
        if (count($pengalamanList) > 0) {
            foreach ($pengalamanList as $exp) {
                $pos        = $exp['posisi_kerja'] ?: 'Position';
                $perusahaan = $exp['nama_perusahaan'] ?: 'Company';
                $lokasi     = $exp['lokasi_perusahaan'] ? " ({$exp['lokasi_perusahaan']})" : '';
                
                $mulai   = $exp['mulai_kerja'] ?? '';
                $akhir   = $exp['akhir_kerja'] ?? '';
                $periode = !empty($exp['periode_manual']) 
                    ? $exp['periode_manual'] 
                    : ($mulai ? "{$mulai} – {$akhir}" : ($akhir ?: '-'));

                $alasan    = $exp['alasan_keluar'] ?? '';
                $deskripsi = $exp['deskripsi'] ?? '';

                $pengalamanHtml .= "
                <div style='margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0;'>
                    <strong style='font-size: 13px; color: #1e293b;'>{$pos}</strong> &nbsp;at&nbsp; <span style='color: #2563eb;'>{$perusahaan}{$lokasi}</span><br>
                    <span style='font-size: 11px; color: #64748b;'>Period: {$periode}</span>";
                if ($alasan)    $pengalamanHtml .= "<p style='font-size: 11px; color: #94a3b8; margin: 3px 0 0 0; font-style: italic;'>Reason leaving: {$alasan}</p>";
                if ($deskripsi) $pengalamanHtml .= "<p style='font-size: 11px; color: #334155; margin: 4px 0 0 0;'>{$deskripsi}</p>";
                $pengalamanHtml .= "</div>";
            }
        } else {
            $pengalamanHtml = "<p style='color: #64748b; font-size: 11px; margin: 0;'>Fresh Graduate / No work experience recorded.</p>";
        }

        $profileCompletionHtml = app(AtsCvPdfSectionsBuilder::class)
            ->buildProfileCompletionSections($applicant, $cp);

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>ATS CV - {$namaLengkap}</title>
            <style>
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    color: #334155;
                    font-size: 11px;
                    line-height: 1.55;
                    margin: 0;
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    background-color: #0f2747;
                    margin-bottom: 18px;
                }
                .photo-cell {
                    width: 126px;
                    padding: 20px 0 20px 20px;
                    vertical-align: middle;
                }
                .profile-photo,
                .profile-placeholder {
                    width: 104px;
                    height: 124px;
                    border: 4px solid #ffffff;
                    border-radius: 8px;
                }
                .profile-photo {
                    object-fit: cover;
                }
                .profile-placeholder {
                    background-color: #2563eb;
                    color: #ffffff;
                    font-size: 32px;
                    font-weight: bold;
                    line-height: 124px;
                    text-align: center;
                }
                .identity-cell {
                    padding: 20px 18px;
                    vertical-align: middle;
                }
                .applicant-name {
                    font-size: 25px;
                    font-weight: bold;
                    color: #ffffff;
                    margin: 0 0 6px 0;
                    text-transform: uppercase;
                    letter-spacing: 0.8px;
                }
                .applied-position {
                    font-size: 13px;
                    font-weight: bold;
                    color: #93c5fd;
                    margin: 0;
                }
                .contact-line {
                    margin-top: 10px;
                    color: #dbeafe;
                    font-size: 10px;
                }
                .score-cell {
                    width: 120px;
                    padding: 20px 20px 20px 0;
                    text-align: right;
                    vertical-align: middle;
                }
                .meta-badge {
                    background-color: #ffffff;
                    color: #0f2747;
                    padding: 10px 12px;
                    border-radius: 8px;
                    font-size: 10px;
                    font-weight: bold;
                    display: inline-block;
                    text-align: center;
                }
                .score-number {
                    display: block;
                    color: #2563eb;
                    font-size: 22px;
                    line-height: 1.1;
                }
                .section-title {
                    font-size: 12px;
                    font-weight: bold;
                    color: #0f2747;
                    text-transform: uppercase;
                    border-left: 4px solid #2563eb;
                    border-bottom: 1px solid #dbe4ef;
                    padding: 5px 0 5px 9px;
                    margin-top: 18px;
                    margin-bottom: 8px;
                    letter-spacing: 0.8px;
                    background-color: #f8fafc;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: 1px solid #e2e8f0;
                    margin-bottom: 12px;
                }
                .info-table td {
                    padding: 6px 10px;
                    vertical-align: top;
                    border-bottom: 1px solid #edf2f7;
                }
                .info-label {
                    width: 32%;
                    font-weight: bold;
                    color: #64748b;
                    background-color: #f8fafc;
                }
                .info-value {
                    width: 68%;
                    color: #0f172a;
                }
                .section-note {
                    color: #64748b;
                    font-size: 10px;
                }
                .cv-card-block {
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 10px 12px;
                    margin-bottom: 12px;
                    background: #ffffff;
                }
                .cv-list-item {
                    margin-bottom: 10px;
                    padding-bottom: 10px;
                    border-bottom: 1px dashed #e2e8f0;
                    font-size: 11px;
                    color: #0f172a;
                }
                .cv-list-item:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }
                .cv-subsection-title {
                    font-size: 11px;
                    font-weight: bold;
                    color: #2563eb;
                    text-transform: uppercase;
                    letter-spacing: 0.4px;
                    margin: 8px 0 6px 0;
                }
                .cv-subsection-title:first-child {
                    margin-top: 0;
                }
                .cv-question {
                    font-weight: bold;
                    color: #334155;
                    margin-bottom: 3px;
                }
                .cv-answer {
                    color: #0f172a;
                }
                .cv-doc-note {
                    font-size: 9px;
                    color: #64748b;
                    margin-top: 4px;
                    line-height: 1.35;
                }
                .cv-doc-grid {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 8px 6px;
                    margin-bottom: 10px;
                }
                .cv-doc-cell {
                    width: 33.33%;
                    vertical-align: top;
                    padding: 6px;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    background-color: #ffffff;
                    page-break-inside: avoid;
                }
                .cv-doc-cell-empty {
                    border: none;
                    background: transparent;
                }
                .cv-doc-type {
                    font-size: 9px;
                    font-weight: bold;
                    color: #475569;
                    text-transform: uppercase;
                    letter-spacing: 0.4px;
                    margin-bottom: 6px;
                    text-align: center;
                }
                .cv-doc-thumb {
                    display: block;
                    width: 100%;
                    max-width: 100%;
                    max-height: 170px;
                    margin: 0 auto;
                    object-fit: contain;
                    border: 1px solid #dbeafe;
                    border-radius: 4px;
                    background-color: #f8fafc;
                }
                .cv-doc-placeholder {
                    height: 120px;
                    border: 1px dashed #cbd5e1;
                    border-radius: 4px;
                    background-color: #f8fafc;
                    color: #64748b;
                    font-size: 10px;
                    font-weight: bold;
                    text-align: center;
                    line-height: 120px;
                }
                .cv-doc-placeholder-pdf {
                    color: #dc2626;
                }
            </style>
        </head>
        <body>

            <table class='header-table'>
                <tr>
                    <td class='photo-cell'>
                        {$photoHtml}
                    </td>
                    <td class='identity-cell'>
                        <div class='applicant-name'>{$namaLengkap}</div>
                        <div class='applied-position'>{$posisiName}</div>
                        <div class='contact-line'>
                            {$email}<br>
                            {$noTelepon}" . ($noWhatsapp !== '-' ? " &nbsp;&bull;&nbsp; WhatsApp {$noWhatsapp}" : '') . "
                        </div>
                    </td>
                    <td class='score-cell'>
                        <div class='meta-badge'>
                            ATS MATCH
                            <span class='score-number'>{$score}%</span>
                        </div>
                    </td>
                </tr>
            </table>

            <div class='section-title'>Professional Profile</div>
            <table class='info-table'>
                <tr>
                    <td class='info-label'>Full Name</td>
                    <td class='info-value'>{$namaLengkap}</td>
                </tr>
                <tr>
                    <td class='info-label'>Nickname</td>
                    <td class='info-value'>{$namaPanggilan}</td>
                </tr>
                <tr>
                    <td class='info-label'>Place / Date of Birth</td>
                    <td class='info-value'>{$ttlDisplay}" . ($usia !== '-' ? " ({$usia})" : '') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Gender</td>
                    <td class='info-value'>{$jenisKelamin}</td>
                </tr>
                <tr>
                    <td class='info-label'>Religion</td>
                    <td class='info-value'>{$agama}</td>
                </tr>
                <tr>
                    <td class='info-label'>Marital Status</td>
                    <td class='info-value'>{$statusNikah}</td>
                </tr>
                <tr>
                    <td class='info-label'>Blood Type</td>
                    <td class='info-value'>{$golDarah}</td>
                </tr>
                <tr>
                    <td class='info-label'>Shio / Element</td>
                    <td class='info-value'>{$shio} " . ($elemen !== '-' ? "({$elemen})" : '') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>NIK KTP</td>
                    <td class='info-value'>{$nikKtp}</td>
                </tr>
                <tr>
                    <td class='info-label'>No. KK</td>
                    <td class='info-value'>{$noKK}</td>
                </tr>
                <tr>
                    <td class='info-label'>No. NPWP</td>
                    <td class='info-value'>{$noNpwp}</td>
                </tr>
                <tr>
                    <td class='info-label'>No. BPJS Kesehatan</td>
                    <td class='info-value'>{$noBpjsKs}</td>
                </tr>
                <tr>
                    <td class='info-label'>No. BPJS Ketenagakerjaan</td>
                    <td class='info-value'>{$noBpjsTk}</td>
                </tr>
                <tr>
                    <td class='info-label'>ID Address (KTP)</td>
                    <td class='info-value'>" . ($alamatKtp ?: '-') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Domicile Address</td>
                    <td class='info-value'>" . ($alamatDomisili ?: '-') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Housing Status</td>
                    <td class='info-value'>{$statusTinggal}</td>
                </tr>
                <tr>
                    <td class='info-label'>Last Salary</td>
                    <td class='info-value'>{$gajiTerakhirFmt}</td>
                </tr>
                <tr>
                    <td class='info-label'>Expected Salary</td>
                    <td class='info-value'>{$ekspetasiGajiFmt}</td>
                </tr>
                <tr>
                    <td class='info-label'>Earliest Joining Date</td>
                    <td class='info-value'>{$tanggalJoin}</td>
                </tr>
            </table>

            <div class='section-title'>Medical &amp; Physical Profile</div>
            <table class='info-table'>
                <tr>
                    <td class='info-label'>Height / Weight</td>
                    <td class='info-value'>{$tinggiBadan} / {$beratBadan}</td>
                </tr>
                <tr>
                    <td class='info-label'>Eye Condition</td>
                    <td class='info-value'>{$kondisiMata}</td>
                </tr>
                <tr>
                    <td class='info-label'>Blood Type</td>
                    <td class='info-value'>{$golDarah}</td>
                </tr>
                <tr>
                    <td class='info-label'>Congenital Disease</td>
                    <td class='info-value'>{$penyakitBawaan}</td>
                </tr>
                <tr>
                    <td class='info-label'>Chronic Illness</td>
                    <td class='info-value'>{$penyakitKronis}</td>
                </tr>
                <tr>
                    <td class='info-label'>Accident / Surgery History</td>
                    <td class='info-value'>{$riwayatKecelakaan}</td>
                </tr>
            </table>

            <div class='section-title'>Education History</div>
            {$pendidikanHtml}

            <div class='section-title'>Work Experience</div>
            {$pengalamanHtml}

            {$profileCompletionHtml}

        </body>
        </html>
        ";

        $mpdf = new MpdfService([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 20,
        ]);

        $mpdf->SetHTMLFooter("
            <div style='border-top: 1px solid #cbd5e1; padding-top: 6px; text-align: right; color: #94a3b8; font-size: 10px; font-family: Helvetica, Arial, sans-serif;'>
                Document generated automatically by HRD Applicant Tracking System (ATS).
            </div>
        ");

        $mpdf->WriteHTML($html);
        return $mpdf->Output("CV_ATS_{$namaLengkap}.pdf", Destination::INLINE);
    }

    /**
     * Helper to resolve TTL string
     */
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

    /**
     * Helper to extract birth year
     */
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

    /**
     * Helper to resolve applicant position title safely in PHP 7.4
     */
    private function resolvePositionName($applicant)
    {
        if (!$applicant) {
            return 'Applied Position';
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

        return $pos ?: 'Applied Position';
    }
}
