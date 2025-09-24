<?php

namespace App\Http\Controllers\directorApp;

use Laravel\Lumen\Routing\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Services\{GenerateMessageHRD, GenerateMessageWhatsapp, SendEmail, SendWhatsapp};

use App\Models\{DataKandidat, MasterJabatan};

class RecruitmentsController extends Controller
{
    public function getCandidates(Request $request)
    {
        $candidates = DataKandidat::with(['cabang', 'jabatan', 'review_user', 'review_recruitment', 'approve_hrd', 'approve_user'])
            ->where('is_active', true)
            ->where('status', 'APPROVE INTERVIEW HRD');

        if ($request->position) {
            $candidates = $candidates->where('bagian_di_lamar', $request->position);
        }

        if ($request->searchTerm) {
            $candidates = $candidates->where(function ($query) use ($request) {
                $query->where('nama_lengkap', 'like', '%' . $request->searchTerm . '%')
                    ->orWhereHas('jabatan', function ($jabatan) use ($request) {
                        $jabatan->where('nama_jabatan', 'like', '%' . $request->searchTerm . '%');
                    });
            });
        }

        $candidates = $candidates->paginate(10);

        $modifiedCandidates = $candidates->getCollection()->map(function ($candidate) {
            $totalMonths = 0;
            if (!empty($candidate->pengalaman_kerja)) {
                $pengalaman = json_decode($candidate->pengalaman_kerja, true) ?? [];
                $totalMonths = collect($pengalaman)->sum(function ($item) {
                    $mulaiKerja = Carbon::parse($item['mulai_kerja']);
                    $akhirKerja = Carbon::parse($item['akhir_kerja']);
                    return $mulaiKerja->diffInMonths($akhirKerja);
                });
            }

            $candidate->months_of_experience = $totalMonths;
            return $candidate;
        });

        $candidates->setCollection($modifiedCandidates);

        return response()->json([
            'message' => 'Candidates data retrieved successfully',
            'data' => $candidates,
        ], 200);
    }

    public function getCandidateFilterParams()
    {
        return response()->json([
            'message' => 'Candidate filter params retrieved successfully',
            'data' => [
                'positions' => MasterJabatan::select('id', 'nama_jabatan')
                    ->orderBy('nama_jabatan')
                    ->where('is_active', true)
                    ->get(),
            ],
        ], 200);
    }

    public function approveCandidate(Request $request)
    {
        DB::beginTransaction();
        try {
            $candidate = DataKandidat::with([
                'cabang:id,nama_cabang',
                'jabatan:id,nama_jabatan',
            ])->find($request->id);

            $dataArray = (object) [
                'nama_lengkap' => $candidate->nama_lengkap,
                'posisi_di_lamar' => $candidate->posisi_di_lamar,
                'nama_jabatan' => $candidate->jabatan->nama_jabatan,
            ];

            $bodi = GenerateMessageHRD::bodyEmailApproveIbuBoss($dataArray);

            // $email = SendEmail::where('to', 'ranggamanggala@intilab.com')
                $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                ->where('subject', 'Approve Kandidat Interview HRD')
                ->where('bcc', ['afryan@intilab.com'])
                // ->where('bcc', ['dedi@intilab.com'])
                ->where('body', $bodi)
                ->where('karyawan', 'System')
                ->noReply()
                ->send();

            if ($email) {
                $candidate->update(['status' => 'APPROVE IBU BOS',]);

                DB::commit();
                return response()->json([
                    'message' => 'Candidate approved successfully',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cannot send email notification',
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function holdCandidate(Request $request)
    {
        $candidate = DataKandidat::with([
            'cabang:id,nama_cabang',
            'jabatan:id,nama_jabatan',
        ])->find($request->id);

        // $date = Carbon::now();
        $date = Carbon::parse($candidate->tgl_interview);
        $dayName = $date->format('l');
        $hariIndonesia = self::konversiHari($dayName);
        $tglInter = $date->format('d-m-Y');
        $hplus7 = $date->addDays(7);
        DB::beginTransaction();
        try {
            $dataArray = (object) [
                'nama_lengkap' => $candidate->nama_lengkap,
                'posisi_di_lamar' => $candidate->posisi_di_lamar,
                'nama_jabatan' => $candidate->jabatan->nama_jabatan,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview_user' => $candidate->jam_interview_user,
                'alamat' => $candidate->cabang->alamat_cabang
            ];

            $bodi = GenerateMessageHRD::bodyEmailKeepIbuBoss($dataArray);

            // $email = SendEmail::where('to', 'ranggamanggala@intilab.com')
                $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                ->where('subject', 'Hold +7 Hari Kandidat')
                ->where('body', $bodi)
                ->where('karyawan', 'System')
                ->noReply()
                ->send();

            if ($email) {
                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'keep_interview_user' => $hplus7
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Candidate hold successfully.!',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cannot send email notification.!'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan server!',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function rejectCandidate(Request $request)
    {
        $candidate = DataKandidat::with([
            'cabang:id,nama_cabang,alamat_cabang',
            'jabatan:id,nama_jabatan',
        ])->find($request->id);

        if ($candidate == null || in_array($candidate->status, ['REJECT IBU BOS', 'REJECT HRD'])) {
            return response()->json([
                'message' => 'Candidate already rejected.'
            ], 401);
        } else if (in_array($candidate->status, ['APPROVE IBU BOS'])) {
            return response()->json([
                'message' => 'Candidate already approved.'
            ], 401);
        } else {
            // $date = Carbon::now();
            $date = Carbon::parse($request->tgl_interview);
            $hariIndonesia = $date->translatedFormat('l');
            $tglInter = $date->format('d-m-Y');
            DB::beginTransaction();
            try {
                // ============================== BEGIN EMAIL BU BOSS ===================
                $dataArray = (object) [
                    'nama_lengkap' => $candidate->nama_lengkap,
                    'posisi_di_lamar' => $candidate->posisi_di_lamar,
                    'nama_jabatan' => $candidate->jabatan->nama_jabatan,
                    'hariIndonesia' => $hariIndonesia,
                    'tglInter' => $tglInter,
                    'jam_interview_user' => $candidate->jam_interview_user,
                    'alamat' => $candidate->cabang->alamat_cabang
                ];

                $bodi1 = GenerateMessageHRD::bodyEmailRejectIbuBoss($dataArray);

                // $email1 = SendEmail::where('to', 'ranggamanggala@intilab.com')
                    $email1 = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                    ->where('subject', 'Reject Kandidat Interview USER')
                    ->where('body', $bodi1)
                    ->where('karyawan', 'System')
                    ->noReply()
                    ->send();
                // ============================== END EMAIL BU BOSS ===================
                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi = GenerateMessageHRD::bodyEmailRejectHRD($dataArray);

                // $email = SendEmail::where('to', 'ranggamanggala@intilab.com')
                    $email = SendEmail::where('to', $candidate->email)
                    ->where('subject', 'Lamaran Ditolak')
                    ->where('body', $bodi)
                    ->where('karyawan', 'System')
                    ->noReply()
                    ->send();
                // ============================== END EMAIL KANDIDAT ===================
                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->RejectedHRD();

                // $Send = new SendWhatsapp('082118214793', $message);
                $Send = new SendWhatsapp($candidate->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                if ($email && $email1) {
                    $candidate->update([
                        'status' => 'REJECT IBU BOS',
                        'is_active' => false,
                    ]);
                    DB::commit();
                    if ($SendWhatsapp) {
                        // DB::commit();
                        return response()->json([
                            'message' => 'Candidate rejected successfully',
                        ], 200);
                    } else {
                        // DB::commit();
                        return response()->json([
                            'message' => 'Candidate rejected successfully but failed to send whatsapp message!',
                        ], 200);
                    }
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Cannot send email notification!'
                    ], 401);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'An error occurred!',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], 500);
            }
        }
    }

    public function getOfferingSalaries(Request $request)
    {
        $candidates = DataKandidat::with([
            'cabang:id,nama_cabang',
            'jabatan:id,nama_jabatan',
            'approve_hrd:id,nama_lengkap',
            'approve_user:id,nama_lengkap',
            'review_user',
            'review_recruitment',
            'offering_salary:id,gaji_pokok,tunjangan',
        ])
            ->where('is_active', true)
            ->where('status', 'APPROVE OFFERING SALARY HRD');

        if ($request->position) {
            $candidates = $candidates->where('bagian_di_lamar', $request->position);
        }

        if ($request->searchTerm) {
            $candidates = $candidates->where('nama_lengkap', 'like', '%' . $request->searchTerm . '%')
                // ->orWhere('department', 'like', '%' . $request->searchTerm . '%')
                ->orWhereHas('jabatan', function ($jabatan) use ($request) {
                    $jabatan->where('nama_jabatan', 'like', '%' . $request->searchTerm . '%');
                });
        }

        $candidates = $candidates->paginate(10);

        $modifiedCandidates = $candidates->getCollection()->map(function ($candidate) {
            $totalMonths = 0;
            if (!empty($candidate->pengalaman_kerja)) {
                $pengalaman = json_decode($candidate->pengalaman_kerja, true) ?? [];
                $totalMonths = collect($pengalaman)->sum(function ($item) {
                    $mulaiKerja = Carbon::parse($item['mulai_kerja']);
                    $akhirKerja = Carbon::parse($item['akhir_kerja']);
                    return $mulaiKerja->diffInMonths($akhirKerja);
                });
            }

            $candidate->months_of_experience = $totalMonths;
            return $candidate;
        });

        $candidates->setCollection($modifiedCandidates);

        return response()->json([
            'message' => 'Approvals data retrieved successfully',
            'data' => $candidates,
        ], 200);
    }

    public function approveOfferingSalary(Request $request)
    {
        $candidate = DataKandidat::with([
            'cabang:id,nama_cabang,alamat_cabang',
            'jabatan:id,nama_jabatan',
        ])->find($request->id);

        if ($candidate->status == 'REJECT BAPAK BOS') {
            return response()->json([
                'message' => 'Candidate already rejected.'
            ], 401);
        } else if ($candidate->status == 'PROBATION') {
            return response()->json([
                'message' => 'Candidate already approved.'
            ], 401);
        } else {
            $date = Carbon::parse($candidate->tgl_kerja);
            $hariIndonesia = $date->translatedFormat('l');
            $tglInter = $date->format('d-m-Y');
            DB::beginTransaction();
            try {
                $dataArray = (object) [
                    'nama_lengkap' => $candidate->nama_lengkap,
                    'posisi_di_lamar' => $candidate->posisi_di_lamar,
                    'nama_jabatan' => $candidate->jabatan->nama_jabatan,
                    'hariIndonesia' => $hariIndonesia,
                    'tglInter' => $tglInter,
                    'alamat' => $candidate->cabang->alamat_cabang,
                ];
                // ============================== BEGIN EMAIL PAK BOSS ===================
                $bodi = GenerateMessageHRD::bodyEmailApproveBapakBoss($dataArray);
                // $email = SendEmail::where('to', 'ranggamanggala@intilab.com')
                    $email = SendEmail::where('to', env('EMAIL_DIREKTUR_BAPAK'))
                    ->where('subject', 'Approve Kandidat Offering Salary')
                    ->where('body', $bodi)
                    ->where('karyawan', 'System')
                    ->noReply()
                    ->send();
                // ============================== END EMAIL PAK BOSS ===================

                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi1 = GenerateMessageHRD::bodyEmailApproveOSCalon($dataArray);
                // $email1 = SendEmail::where('to', 'ranggamanggala@intilab.com')
                    $email1 = SendEmail::where('to', $candidate->email)
                    ->where('subject', 'Pemberitahuan masuk kerja')
                    ->where('body', $bodi1)
                    ->where('karyawan', 'System')
                    ->noReply()
                    ->send();
                // ============================== END EMAIL KANDIDAT ===================

                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->PassedOS();
                // $Send = new SendWhatsapp('085888244181', $message);
                $Send = new SendWhatsapp($candidate->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                if ($email && $email1) {
                    $update = DataKandidat::where('id', $request->id)->update([
                        'status' => 'PROBATION'
                    ]);

                    DB::commit();

                    if ($SendWhatsapp) {
                        return response()->json([
                            'message' => 'Candidate approved successfully',
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => 'Candidate rejected successfully but failed to send whatsapp message!',
                        ], 200);
                    }
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Cannot send email notification!',
                    ], 401);
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ], 500);
            }
        }
    }

    public function rejectOfferingSalary(Request $request)
    {
        $candidate = DataKandidat::with([
            'cabang:id,nama_cabang,alamat_cabang',
            'jabatan:id,nama_jabatan',
        ])->find($request->id);

        if ($candidate->status == 'REJECT BAPAK BOS') {
            return response()->json([
                'message' => 'Candidate already rejected.'
            ], 401);
        } else if ($candidate->status == 'PROBATION') {
            return response()->json([
                'message' => 'Candidate already approved.'
            ], 401);
        } else {
            DB::beginTransaction();
            try {
                $dataArray = (object) [
                    'nama_lengkap' => $candidate->nama_lengkap,
                    'posisi_di_lamar' => $candidate->posisi_di_lamar,
                    'nama_jabatan' => $candidate->jabatan->nama_jabatan,
                ];
                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi = GenerateMessageHRD::bodyEmailRejectBapakBoss($dataArray);
                // $email = SendEmail::where('to', 'ranggamanggala@intilab.com')
                    $email = SendEmail::where('to', env('EMAIL_DIREKTUR_BAPAK'))
                    ->where('subject', 'Reject Kandidat Offering Salary')
                    ->where('body', $bodi)
                    ->where('karyawan', 'System')
                    ->noReply()
                    ->send();
                // ============================== END EMAIL PAK BOSS ===================

                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi1 = GenerateMessageHRD::bodyEmailRejectKandidat($dataArray);
                // $email1 = SendEmail::where('to', 'ranggamanggala@intilab.com')
                    $email1 = SendEmail::where('to', $candidate->email)
                    ->where('subject', 'Lamaran Ditolak')
                    ->where('body', $bodi1)
                    ->where('karyawan', 'System')
                    ->noReply()
                    ->send();
                // ============================== END EMAIL KANDIDAT ===================

                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->RejectedHRD(); // menggunakan method rejected hrd karna bodi messages sama

                // $Send = new SendWhatsapp('082118214793', $message);
                $Send = new SendWhatsapp($candidate->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                if ($email && $email1) {
                    $candidate->update([
                        'status' => 'REJECT BAPAK BOS',
                        'is_active' => false,
                    ]);
                    DB::commit();
                    if ($SendWhatsapp) {
                        return response()->json([
                            'message' => 'Candidate rejected successfully.!',
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => 'Candidate rejected successfully but failed to send whatsapp message.!',
                        ], 200);
                    }
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Cannot send email notification.'
                    ], 401);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ], 500);
            }
        }
    }
}
