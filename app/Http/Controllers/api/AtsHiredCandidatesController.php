<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview', 'salaryOffer', 'candidateDataOffer', 'candidateProfile'])
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
                if (Schema::hasTable('candidate_onboarding_verification')) {
                    $verification = DB::table('candidate_onboarding_verification')
                        ->where('new_recruitment_id', $row->id)
                        ->first();

                    if ($verification) {
                        return (array) $verification;
                    }
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
            ->addColumn('onboarding_data', function ($row) {
                $profile = $row->candidateProfile;
                $offer = $row->candidateDataOffer;

                return [
                    'nik_ktp' => optional($profile)->nik_ktp,
                    'email' => $row->email ?? null,
                    'tanggal_mulai_kerja' => optional($offer)->tanggal_mulai_kerja,
                    'gaji_pokok' => optional($offer)->gaji_pokok,
                    'potongan_bpjs_kes' => optional($offer)->potongan_bpjs_kes,
                    'potongan_bpjs_tk' => optional($offer)->potongan_bpjs_tk,
                    'pot_pph21' => optional($offer)->pot_pph21,
                    'pencadangan_upah' => optional($offer)->pencadangan_upah,
                ];
            })
            ->rawColumns([])
            ->make(true);
    }

    public function storeEmployee(Request $request)
    {
        if (!$request->id) {
            return response()->json(['status' => false, 'message' => 'Candidate recruitment data is required.'], 422);
        }

        return DB::transaction(function () use ($request) {
            $recruitment = NewRecruitment::with(['personalRequest', 'candidateProfile', 'candidateDataOffer', 'salaryOffer'])
                ->lockForUpdate()
                ->find($request->id);
            if (!$recruitment) {
                return response()->json(['status' => false, 'message' => 'Candidate recruitment data not found.'], 404);
            }

            $profile = $recruitment->candidateProfile;
            if (!$profile || empty($profile->nik_ktp)) {
                return response()->json(['status' => false, 'message' => 'Candidate complete profile or NIK KTP is not available.'], 422);
            }

            $now = Carbon::now();
            $nikKaryawan = trim($profile->nik_ktp);
            $offer = $recruitment->candidateDataOffer;
            $startDate = optional($offer)->tanggal_mulai_kerja ?? $now->toDateString();
            $effectiveMonth = Carbon::parse($startDate)->startOfMonth()->toDateString();
            $pr = $recruitment->personalRequest;
            $educations = DB::table('candidate_educations')
                ->where('new_recruitment_id', $recruitment->id)
                ->get();
            $workExperiences = DB::table('candidate_work_experiences')
                ->where('new_recruitment_id', $recruitment->id)
                ->get();

            $checklistData = [
                'new_recruitment_id' => $recruitment->id,
                'has_id_card' => filter_var($request->has_id_card, FILTER_VALIDATE_BOOLEAN),
                'has_email' => filter_var($request->has_email, FILTER_VALIDATE_BOOLEAN),
                'has_server_account' => filter_var($request->has_server_account, FILTER_VALIDATE_BOOLEAN),
                'has_all_documents' => filter_var($request->has_all_documents, FILTER_VALIDATE_BOOLEAN),
                'verified_by' => $this->karyawan,
                'verified_at' => $now,
                'updated_at' => $now,
            ];
            DB::table('candidate_onboarding_verification')->updateOrInsert(
                ['new_recruitment_id' => $recruitment->id],
                array_merge($checklistData, ['created_at' => $now])
            );

            $karyawanData = $this->existingColumns('master_karyawan', [
                'nik_karyawan' => $nikKaryawan,
                'nama_lengkap' => $recruitment->nama_lengkap,
                'nama_panggilan' => $profile->nama_panggilan,
                'nik_ktp' => $profile->nik_ktp,
                'no_kk' => $profile->no_kk,
                'npwp' => $profile->no_npwp,
                'no_npwp' => $profile->no_npwp,
                'no_bpjs_kes' => $profile->no_bpjs_ks,
                'no_bpjs_tk' => $profile->no_bpjs_tk,
                'email' => $recruitment->email,
                'email_pribadi' => $recruitment->email,
                'no_telpon' => $recruitment->no_telepon,
                'tempat_lahir' => $recruitment->tempat_lahir,
                'tanggal_lahir' => $recruitment->tanggal_lahir,
                'jenis_kelamin' => $recruitment->jenis_kelamin,
                'agama' => $profile->agama,
                'status_pernikahan' => $profile->status_pernikahan,
                'alamat' => $profile->alamat_domisili ?: $profile->alamat_ktp,
                'kota' => $profile->kota_domisili ?: $profile->kota_ktp,
                'provinsi' => $profile->provinsi_domisili ?: $profile->provinsi_ktp,
                'kode_pos' => $profile->kode_pos_domisili ?: $profile->kode_pos_ktp,
                'pendidikan' => $educations->isNotEmpty() ? json_encode($educations) : null,
                'pengalaman_kerja' => $workExperiences->isNotEmpty() ? json_encode($workExperiences) : null,
                'id_jabatan' => is_numeric(optional($pr)->posisi) ? $pr->posisi : null,
                'id_department' => is_numeric(optional($pr)->divisi) ? $pr->divisi : null,
                'id_cabang' => is_numeric(optional($pr)->lokasi_penempatan_cabang) ? $pr->lokasi_penempatan_cabang : null,
                'tgl_mulai_kerja' => $startDate,
                'status_karyawan' => 'Kontrak',
                'image' => $recruitment->picture,
                'is_active' => 1,
                'updated_at' => $now,
                'updated_by' => $this->karyawan,
            ]);

            $employee = DB::table('master_karyawan')->where('nik_karyawan', $nikKaryawan)->first();
            if ($employee) {
                DB::table('master_karyawan')->where('id', $employee->id)->update($karyawanData);
            } else {
                DB::table('master_karyawan')->insert(array_merge($karyawanData, $this->existingColumns('master_karyawan', [
                    'created_at' => $now,
                    'created_by' => $this->karyawan,
                ])));
            }

            $gajiPokok = (float) (optional($offer)->gaji_pokok ?? optional($recruitment->salaryOffer)->final_sallary ?? 0);
            $this->upsertPayroll('master_sallary', $nikKaryawan, $recruitment->nama_lengkap, [
                'gaji_pokok' => $gajiPokok,
                'tunjangan_kerja' => (float) (optional($offer)->tunjangan_kerja ?? 0),
                'bulan_efektif' => $effectiveMonth,
            ], $now);
            $this->upsertPayroll('bpjs_kesehatan', $nikKaryawan, $recruitment->nama_lengkap, [
                'gaji_pokok' => $gajiPokok,
                'no_bpjs' => $profile->no_bpjs_ks,
                'nominal_potongan_karyawan' => (float) (optional($offer)->potongan_bpjs_kes ?? 0),
                'bulan_efektif' => $effectiveMonth,
            ], $now);
            $this->upsertPayroll('bpjs_tk', $nikKaryawan, $recruitment->nama_lengkap, [
                'gaji_pokok' => $gajiPokok,
                'no_bpjs_tk' => $profile->no_bpjs_tk,
                'nominal_potongan_karyawan' => (float) (optional($offer)->potongan_bpjs_tk ?? 0),
                'bulan_efektif' => $effectiveMonth,
            ], $now);
            $this->upsertPayroll('pph_21', $nikKaryawan, $recruitment->nama_lengkap, [
                'pajak_bulanan' => (float) (optional($offer)->pot_pph21 ?? 0),
                'pajak_tahunan' => (float) (optional($offer)->pot_pph21 ?? 0) * 12,
                'bulan_mulai_pemotongan' => $effectiveMonth,
            ], $now);
            $this->upsertPayroll('pencadangan_upah', $nikKaryawan, $recruitment->nama_lengkap, [
                'nominal' => (float) (optional($offer)->pencadangan_upah ?? 0),
                'nominal_berjalan' => -(float) (optional($offer)->pencadangan_upah ?? 0),
                'tenor' => 1,
                'tenor_berjalan' => -1,
                'bulan_efektif' => $effectiveMonth,
            ], $now);

            return response()->json(['status' => true, 'message' => 'Onboarding verification and employee data have been saved.']);
        });
    }

    private function existingColumns($table, array $data)
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        return array_filter($data, function ($value, $column) use ($columns) {
            return in_array($column, $columns, true) && $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function upsertPayroll($table, $nikKaryawan, $namaKaryawan, array $data, Carbon $now)
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $payload = $this->existingColumns($table, array_merge($data, [
            'nik_karyawan' => $nikKaryawan,
            'karyawan' => $namaKaryawan,
            'is_active' => 1,
            'updated_at' => $now,
            'updated_by' => $this->karyawan,
        ]));
        $existing = DB::table($table)->where('nik_karyawan', $nikKaryawan)->where('is_active', 1)->first();
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);
            return;
        }

        DB::table($table)->insert(array_merge($payload, $this->existingColumns($table, [
            'created_at' => $now,
            'created_by' => $this->karyawan,
        ])));
    }
}
