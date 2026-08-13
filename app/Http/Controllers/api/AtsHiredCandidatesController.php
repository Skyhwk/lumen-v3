<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AtsHiredCandidatesController extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

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

    // ─── Index — DataTables list of hired candidates ─────────────────────────

    /**
     * List candidates with status = hired
     */
    public function index(Request $request)
    {
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview', 'salaryOffer', 'candidateDataOffer'])
            ->where(function ($q) {
                $q->where('status', 'hired')
                  ->orWhere('status', 'HIRED');
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
            ->addColumn('usia', function ($row) {
                $birthYear = $this->extractBirthYear($row);
                if ($birthYear) {
                    return (Carbon::now()->year - $birthYear) . ' Yrs';
                }
                return '-';
            })
            ->editColumn('shio', function ($row) {
                $birthDate  = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                $shio       = $shioElemen['shio']   ?? null;
                $elemen     = $shioElemen['elemen'] ?? null;
                if ($shio && $elemen) {
                    return "{$shio} ({$elemen})";
                }
                return $shio ?: ($elemen ?: '-');
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== ''
                    ? $row->nilai_kecocokan
                    : ($row->matching_score ?? rand(85, 99));
                return $score . '%';
            })
            ->editColumn('status', function ($row) {
                return 'hired';
            })
            ->addColumn('onboarding_checklist', function ($row) {
                if (!empty($row->onboarding_checklist)) {
                    return is_string($row->onboarding_checklist)
                        ? json_decode($row->onboarding_checklist, true)
                        : $row->onboarding_checklist;
                }
                return null;
            })
            ->addColumn('candidate_data_offer', function ($row) {
                if (!empty($row->candidateDataOffer)) {
                    $tglMulai = $row->candidateDataOffer->tanggal_mulai_kerja;
                    if ($tglMulai) {
                        // If it's already a string, use it; if it's a Carbon instance, format it
                        $tglMulai = is_string($tglMulai)
                            ? substr($tglMulai, 0, 10)
                            : $tglMulai->format('Y-m-d');
                    }
                    
                    return [
                        'id' => $row->candidateDataOffer->id ?? null,
                        'gaji_pokok' => $row->candidateDataOffer->gaji_pokok ?? null,
                        'tunjangan_kerja' => $row->candidateDataOffer->tunjangan_kerja ?? null,
                        'tanggal_mulai_kerja' => $tglMulai,
                        'potongan_bpjs_kes' => $row->candidateDataOffer->potongan_bpjs_kes ?? null,
                        'potongan_bpjs_tk' => $row->candidateDataOffer->potongan_bpjs_tk ?? null,
                        'pot_pph21' => $row->candidateDataOffer->pot_pph21 ?? null,
                        'pencadangan_upah' => $row->candidateDataOffer->pencadangan_upah ?? null,
                    ];
                }
                return null;
            })
            ->rawColumns([])
            ->make(true);
    }

    /**
     * Store Onboarding Checklist & Auto Create Employee + Salary
     */
    public function storeEmployee(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'nik' => 'required',
        ]);

        $recruitment = NewRecruitment::find($request->id);
        if (!$recruitment) {
            return response()->json([
                'status' => false,
                'message' => 'Candidate recruitment data not found'
            ], 404);
        }

        // Save Onboarding Checklist status on NewRecruitment
        $checklistData = [
            'has_id_card' => filter_var($request->has_id_card, FILTER_VALIDATE_BOOLEAN),
            'has_email' => filter_var($request->has_email, FILTER_VALIDATE_BOOLEAN),
            'has_server_account' => filter_var($request->has_server_account, FILTER_VALIDATE_BOOLEAN),
            'has_all_documents' => filter_var($request->has_all_documents, FILTER_VALIDATE_BOOLEAN),
            'nik' => $request->nik,
            'tanggal_masuk' => $request->tanggal_masuk ?? date('Y-m-d'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $recruitment->onboarding_checklist = json_encode($checklistData);
        $recruitment->save();

        // 1. Create or Update master_karyawan
        $pr = $recruitment->personalRequest;
        $idJabatan = $request->id_jabatan ?: ($pr->posisi ?? $recruitment->bagian_di_lamar);
        $idDept = $request->id_department ?: ($pr->divisi ?? null);
        $idCabang = $request->id_cabang ?: ($pr->lokasi_penempatan_cabang ?? null);

        $karyawanData = [
            'nik' => trim($request->nik),
            'nama_lengkap' => $recruitment->nama_lengkap,
            'email' => $request->email ?: ($recruitment->email ?? null),
            'nomor_hp' => $recruitment->no_hp ?? $recruitment->telepon ?? null,
            'alamat' => $recruitment->alamat_domisili ?? $recruitment->alamat_ktp ?? null,
            'id_jabatan' => is_numeric($idJabatan) ? $idJabatan : null,
            'id_department' => is_numeric($idDept) ? $idDept : null,
            'id_cabang' => is_numeric($idCabang) ? $idCabang : null,
            'tanggal_masuk' => $request->tanggal_masuk ?: date('Y-m-d'),
            'status_karyawan' => $request->status_karyawan ?: 'Kontrak',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Match existing employee by NIK or name
        $karyawan = \App\Models\MasterKaryawan::where('nik', trim($request->nik))
            ->orWhere('nama_lengkap', $recruitment->nama_lengkap)
            ->first();

        if ($karyawan) {
            $karyawan->update(array_filter($karyawanData, function ($val) {
                return !is_null($val);
            }));
        } else {
            $karyawan = \App\Models\MasterKaryawan::create($karyawanData);
        }

        // 2. Create or Update master_sallary
        $salaryOffer = $recruitment->salaryOffer;
        $gajiPokok = $request->gaji_pokok !== null && $request->gaji_pokok !== '' 
            ? (float) $request->gaji_pokok 
            : ($salaryOffer ? (float) ($salaryOffer->final_sallary ?? $salaryOffer->sallary_offer_direktur ?? $salaryOffer->sallary_offer_hrd ?? 0) : 0);

        $tunjangan = $request->tunjangan_kerja !== null && $request->tunjangan_kerja !== '' 
            ? (float) $request->tunjangan_kerja 
            : 0;

        $salaryData = [
            'karyawan' => $recruitment->nama_lengkap,
            'nik_karyawan' => trim($request->nik),
            'gaji_pokok' => $gajiPokok,
            'tunjangan_kerja' => $tunjangan,
            'bulan_efektif' => $request->bulan_efektif ?: date('Y-m-01'),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'HRD ATS System',
        ];

        $masterSalary = \App\Models\MasterSallary::where('nik_karyawan', trim($request->nik))
            ->orWhere('karyawan', $recruitment->nama_lengkap)
            ->first();

        if ($masterSalary) {
            $masterSalary->update($salaryData);
        } else {
            \App\Models\MasterSallary::create($salaryData);
        }

        return response()->json([
            'status' => true,
            'message' => 'HRD Onboarding checklist berhasil disimpan & Data Karyawan serta Master Gaji berhasil dibuat!',
            'data' => [
                'checklist' => $checklistData,
                'karyawan' => $karyawan,
            ]
        ]);
    }
}
