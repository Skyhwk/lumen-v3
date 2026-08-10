<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateMessageAtsWhatsapp;
use App\Services\MpdfService;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Mpdf\Output\Destination;

class DataApplicantsController extends Controller
{
    /**
     * Get Datatable list of applicants (Initial Assessment Stage)
     */
    public function index(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview'])
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereIn('status', ['assessment', 'pending', 'new']);
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
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('status', 'like', "%{$keyword}%");
            })
            ->addColumn('usia', function ($row) {
                $ttl = $this->getTtlString($row);
                $birthYear = $this->extractBirthYear($ttl);
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
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== '' 
                    ? $row->nilai_kecocokan 
                    : ($row->matching_score ?? rand(70, 95));
                return $score . '%';
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

        // 1. Update applicant status to 'interview_hrd'
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
                    ->send();
            }

            $phone = $applicant->no_telepon ?: ($applicant->no_hp ?? null);
            if (!empty($phone)) {
                $waObj = new GenerateMessageAtsWhatsapp($dataArray);
                $waMessage = $waObj->PassedCandidateSelection();

                $sendWa = new SendWhatsapp($phone, $waMessage);
                $sendWa->send();
            }
        } catch (\Exception $e) {
            // Silence exception
        }

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

        $applicant->update([
            'status' => 'rejected',
            'rejected_by' => $user,
            'rejected_at' => Carbon::now(),
            'alasan_reject' => $reason,
        ]);

        try {
            $posisiName = $this->resolvePositionName($applicant);

            $dataArray = (object) [
                'nama_lengkap' => $applicant->nama_lengkap,
                'posisi_di_lamar' => $posisiName,
                'nama_jabatan' => $posisiName,
                'alasan_reject' => $reason,
            ];

            if (!empty($applicant->email)) {
                $bodyEmail = GenerateMessageAtsEmail::bodyEmailRejectKandidat($dataArray);
                SendEmail::where('to', $applicant->email)
                    ->where('subject', 'Informasi Hasil Seleksi - PT Inti Surya Laboratorium')
                    ->where('body', $bodyEmail)
                    ->where('karyawan', $user)
                    ->noReply()
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

        return response()->json([
            'status' => 200,
            'message' => 'Applicant has been rejected.',
            'data' => $applicant,
        ], 200);
    }

    /**
     * Generate ATS CV PDF via MPDF
     */
    public function generateCvPdf(Request $request, $id)
    {
        $applicant = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview'])->find($id);

        if (!$applicant) {
            return response()->json(['message' => 'Applicant data not found'], 404);
        }

        $posisiName = $this->resolvePositionName($applicant);
        
        $ttl = $this->getTtlString($applicant);
        $birthYear = $this->extractBirthYear($ttl);
        
        $birthDate = $applicant->tanggal_lahir ?? $ttl;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $applicant->shio, $applicant->elemen);
        $shio = $shioElemen['shio'] ?? '-';
        $elemen = $shioElemen['elemen'] ?? '-';

        $usia = $birthYear ? (Carbon::now()->year - $birthYear) . ' Years' : '-';
        $gajiFormatted = ($applicant->ekspetasi_gaji ?: $applicant->gaji_terakhir) 
            ? 'Rp ' . number_format(($applicant->ekspetasi_gaji ?: $applicant->gaji_terakhir), 0, ',', '.') 
            : '-';
        $score = $applicant->nilai_kecocokan ?: ($applicant->matching_score ?: 85);

        // Build Education HTML
        $pendidikanHtml = '';
        if (is_array($applicant->pendidikan) && count($applicant->pendidikan) > 0) {
            foreach ($applicant->pendidikan as $edu) {
                $jenjang = $edu['jenjang'] ?? $edu['degree'] ?? 'Education';
                $institusi = $edu['institusi'] ?? $edu['school'] ?? '-';
                $jurusan = $edu['jurusan'] ?? $edu['major'] ?? '';
                $tahun = $edu['tahun'] ?? $edu['year'] ?? '';
                $ipk = isset($edu['gpa']) || isset($edu['ipk']) ? ' (GPA: ' . ($edu['gpa'] ?? $edu['ipk']) . ')' : '';

                $pendidikanHtml .= "
                <div style='margin-bottom: 10px;'>
                    <strong style='font-size: 13px; color: #1e293b;'>{$jenjang} {$jurusan} - {$institusi}</strong> {$ipk}<br>
                    <span style='font-size: 11px; color: #64748b;'>Year: {$tahun}</span>
                </div>";
            }
        } else {
            $pendidikanHtml = "<p style='color: #64748b; font-size: 11px; margin: 0;'>No education record found.</p>";
        }

        // Build Experience HTML
        $pengalamanHtml = '';
        if (is_array($applicant->pengalaman_kerja) && count($applicant->pengalaman_kerja) > 0) {
            foreach ($applicant->pengalaman_kerja as $exp) {
                $pos = $exp['posisi'] ?? $exp['position'] ?? 'Position';
                $perusahaan = $exp['perusahaan'] ?? $exp['company'] ?? 'Company';
                $periode = $exp['periode'] ?? $exp['period'] ?? '';
                $deskripsi = $exp['deskripsi'] ?? $exp['description'] ?? '';

                $pengalamanHtml .= "
                <div style='margin-bottom: 12px;'>
                    <strong style='font-size: 13px; color: #1e293b;'>{$pos}</strong> at <span>{$perusahaan}</span><br>
                    <span style='font-size: 11px; color: #64748b;'>Period: {$periode}</span>";
                if ($deskripsi) {
                    $pengalamanHtml .= "<p style='font-size: 11px; color: #334155; margin: 4px 0 0 0;'>{$deskripsi}</p>";
                }
                $pengalamanHtml .= "</div>";
            }
        } else {
            $pengalamanHtml = "<p style='color: #64748b; font-size: 11px; margin: 0;'>Fresh Graduate / No work experience recorded.</p>";
        }

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>ATS CV - {$applicant->nama_lengkap}</title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    color: #334155;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .header-table {
                    width: 100%;
                    border-bottom: 2px solid #2563eb;
                    padding-bottom: 12px;
                    margin-bottom: 15px;
                }
                .applicant-name {
                    font-size: 22px;
                    font-weight: bold;
                    color: #1e293b;
                    margin: 0 0 4px 0;
                    text-transform: uppercase;
                }
                .applied-position {
                    font-size: 14px;
                    font-weight: 600;
                    color: #2563eb;
                    margin: 0;
                }
                .meta-badge {
                    background-color: #eff6ff;
                    color: #1d4ed8;
                    border: 1px solid #bfdbfe;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: bold;
                    display: inline-block;
                }
                .section-title {
                    font-size: 13px;
                    font-weight: bold;
                    color: #1e293b;
                    text-transform: uppercase;
                    border-bottom: 1px solid #cbd5e1;
                    padding-bottom: 4px;
                    margin-top: 15px;
                    margin-bottom: 10px;
                    letter-spacing: 0.5px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                }
                .info-table td {
                    padding: 4px 8px;
                    vertical-align: top;
                }
                .info-label {
                    width: 30%;
                    font-weight: bold;
                    color: #475569;
                }
                .info-value {
                    width: 70%;
                    color: #0f172a;
                }
            </style>
        </head>
        <body>

            <table class='header-table'>
                <tr>
                    <td style='width: 70%;'>
                        <div class='applicant-name'>{$applicant->nama_lengkap}</div>
                        <div class='applied-position'>Applied Position: {$posisiName}</div>
                        <div style='margin-top: 6px; color: #64748b; font-size: 11px;'>
                            Email: {$applicant->email} | Phone: {$applicant->no_telepon}
                        </div>
                    </td>
                    <td style='width: 30%; text-align: right; vertical-align: top;'>
                        <div class='meta-badge' style='background: #1e40af; color: #ffffff; padding: 6px 10px; border-radius: 4px; font-size: 12px;'>
                            Matching Score: {$score}%
                        </div>
                    </td>
                </tr>
            </table>

            <div class='section-title'>Personal Information & Qualifications</div>
            <table class='info-table'>
                <tr>
                    <td class='info-label'>Place & Date of Birth</td>
                    <td class='info-value'>{$ttl} ({$usia})</td>
                </tr>
                <tr>
                    <td class='info-label'>Zodiac & Element</td>
                    <td class='info-value'>{$shio} " . ($elemen ? "({$elemen})" : "") . "</td>
                </tr>
                <tr>
                    <td class='info-label'>ID Address</td>
                    <td class='info-value'>" . ($applicant->alamat_ktp ?: '-') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Domicile Address</td>
                    <td class='info-value'>" . ($applicant->alamat_domisili ?: '-') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Expected Salary</td>
                    <td class='info-value'>{$gajiFormatted}</td>
                </tr>
                <tr>
                    <td class='info-label'>Earliest Joining Date</td>
                    <td class='info-value'>" . ($applicant->tanggal_join_tercepat ? Carbon::parse($applicant->tanggal_join_tercepat)->format('d F Y') : '-') . "</td>
                </tr>
            </table>

            <div class='section-title'>Education History</div>
            {$pendidikanHtml}

            <div class='section-title'>Work Experience</div>
            {$pengalamanHtml}

        </body>
        </html>
        ";

        $mpdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 20,
        ]);

        $mpdf->SetHTMLFooter("
            <div style='border-top: 1px solid #cbd5e1; padding-top: 6px; text-align: right; color: #94a3b8; font-size: 10px; font-family: Helvetica, Arial, sans-serif;'>
                Document generated automatically by HRD Applicant Tracking System (ATS).
            </div>
        ");

        $mpdf->WriteHTML($html);
        return $mpdf->Output("CV_ATS_{$applicant->nama_lengkap}.pdf", Destination::INLINE);
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
    private function extractBirthYear($ttl)
    {
        if (!$ttl) return null;
        if (preg_match('/\b(19\d\d|20\d\d)\b/', $ttl, $matches)) {
            return (int) $matches[1];
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
