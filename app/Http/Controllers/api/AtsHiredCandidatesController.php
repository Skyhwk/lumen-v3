<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\KeahlianBahasaKaryawan;
use App\Models\KeahlianKaryawan;
use App\Models\MasterKaryawan;
use App\Models\MedicalCheckup;
use App\Models\NewRecruitment;
use App\Models\PendidikanKaryawan;
use App\Models\PengalamanKerjaKaryawan;
use App\Models\SertifikatKaryawan;
use App\Models\User;
use App\Services\RecruitmentPictureService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->addColumn('is_migrated', function ($row) {
                return $this->isEmployeeMigrated($row);
            })
            ->rawColumns([])
            ->make(true);
    }

    public function previewMigration(Request $request)
    {
        $recruitment = $this->findRecruitmentForMigration($request->input('id'));
        if (!$recruitment) {
            return response()->json(['status' => false, 'message' => 'Data kandidat tidak ditemukan.'], 404);
        }

        $payload = $this->buildMigrationPreview($recruitment);

        return response()->json([
            'status' => true,
            'data' => $payload,
        ]);
    }

    public function migrateToMasterKaryawan(Request $request)
    {
        if (!$request->id) {
            return response()->json(['status' => false, 'message' => 'Data kandidat wajib dipilih.'], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $recruitment = $this->findRecruitmentForMigration($request->input('id'), true);
                if (!$recruitment) {
                    return response()->json(['status' => false, 'message' => 'Data kandidat tidak ditemukan.'], 404);
                }

                if ($this->isEmployeeMigrated($recruitment)) {
                    return response()->json(['status' => false, 'message' => 'Data karyawan sudah pernah dimigrasikan ke Master Karyawan.'], 409);
                }

                $nikKaryawan = trim((string) data_get($request, 'employee.nik', ''));
                $email = trim((string) data_get($request, 'employee.email', ''));
                $username = trim((string) data_get($request, 'access.username', ''));

                if ($nikKaryawan === '') {
                    return response()->json(['status' => false, 'message' => 'NIK karyawan wajib diisi.'], 422);
                }
                if ($email === '') {
                    return response()->json(['status' => false, 'message' => 'Email karyawan wajib diisi.'], 422);
                }
                if ($username === '') {
                    return response()->json(['status' => false, 'message' => 'Username akun sistem wajib diisi.'], 422);
                }
                if (trim((string) data_get($request, 'access.password', '')) === '') {
                    return response()->json(['status' => false, 'message' => 'Password akun sistem wajib diisi.'], 422);
                }

                if (MasterKaryawan::where('nik_karyawan', $nikKaryawan)->exists()) {
                    return response()->json(['status' => false, 'message' => 'NIK karyawan sudah terdaftar di Master Karyawan.'], 400);
                }
                if (MasterKaryawan::where('email', $email)->exists()) {
                    return response()->json(['status' => false, 'message' => 'Email karyawan sudah terdaftar di Master Karyawan.'], 400);
                }
                if (User::where('email', $email)->exists()) {
                    return response()->json(['status' => false, 'message' => 'Email sudah digunakan akun sistem lain.'], 400);
                }
                if (User::where('username', $username)->exists()) {
                    return response()->json(['status' => false, 'message' => 'Username sudah digunakan akun sistem lain.'], 400);
                }

                $this->performEmployeeMigrationFromForm($recruitment, $request);

                return response()->json([
                    'status' => true,
                    'message' => 'Data kandidat berhasil dimigrasikan ke Master Karyawan, payroll, dan akun sistem.',
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terdapat kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeEmployee(Request $request)
    {
        if (!$request->id) {
            return response()->json(['status' => false, 'message' => 'Data kandidat wajib dipilih.'], 422);
        }

        return DB::transaction(function () use ($request) {
            $recruitment = NewRecruitment::lockForUpdate()->find($request->id);
            if (!$recruitment) {
                return response()->json(['status' => false, 'message' => 'Data kandidat tidak ditemukan.'], 404);
            }

            $now = Carbon::now();
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

            if (Schema::hasTable('candidate_onboarding_verification')) {
                DB::table('candidate_onboarding_verification')->updateOrInsert(
                    ['new_recruitment_id' => $recruitment->id],
                    array_merge($checklistData, ['created_at' => $now])
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Verifikasi onboarding berhasil disimpan.',
            ]);
        });
    }

    private function findRecruitmentForMigration($id, $lock = false)
    {
        $query = NewRecruitment::with([
            'personalRequest',
            'candidateProfile.workExperiences',
            'candidateDataOffer',
            'salaryOffer',
            'candidateEducations',
            'candidateWorkExperiences',
            'candidateMedicalInformation',
        ]);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->find($id);
    }

    private function isEmployeeMigrated($recruitment)
    {
        $profile = $recruitment->candidateProfile ?? null;
        $nikKaryawan = trim((string) optional($profile)->nik_ktp);
        if ($nikKaryawan === '') {
            return false;
        }

        if (!Schema::hasTable('master_karyawan')) {
            return false;
        }

        return DB::table('master_karyawan')
            ->where('nik_karyawan', $nikKaryawan)
            ->where('is_active', 1)
            ->exists();
    }

    private function buildMigrationPreview($recruitment)
    {
        $profile = $recruitment->candidateProfile;
        $offer = $recruitment->candidateDataOffer;
        $pr = $recruitment->personalRequest;
        $medical = $recruitment->candidateMedicalInformation;
        $nikKtp = trim((string) optional($profile)->nik_ktp);
        $birthDate = $this->formatDateInput($recruitment->tanggal_lahir);
        $startDate = $this->formatDateInput(optional($offer)->tanggal_mulai_kerja);
        $gajiPokok = (float) (optional($offer)->gaji_pokok ?? optional($recruitment->salaryOffer)->final_sallary ?? 0);
        $bpjsKes = (float) (optional($offer)->potongan_bpjs_kes ?? 0);
        $bpjsTk = (float) (optional($offer)->potongan_bpjs_tk ?? 0);
        $pph21 = (float) (optional($offer)->pot_pph21 ?? 0);
        $pencadangan = (float) (optional($offer)->pencadangan_upah ?? 0);
        $tunjangan = (float) (optional($offer)->tunjangan_kerja ?? 0);
        $isMigrated = $this->isEmployeeMigrated($recruitment);
        $shioElemen = ShioElemenHelper::resolve($birthDate, $recruitment->shio ?? null, $recruitment->elemen ?? null);

        $educationRows = $this->mapCandidateEducations($recruitment);
        $experienceRows = $this->mapCandidateExperiences($recruitment);
        $pictureUrl = app(RecruitmentPictureService::class)->toDataUri($recruitment->picture ?? null);

        $personal = [
            'id_kandidat' => $recruitment->id,
            'nama_lengkap' => $recruitment->nama_lengkap,
            'nik_ktp' => $nikKtp,
            'salutation' => optional($profile)->nama_panggilan,
            'birth_place' => $recruitment->tempat_lahir,
            'date_birth' => $birthDate,
            'gender' => $recruitment->jenis_kelamin,
            'religion' => optional($profile)->agama,
            'marital_status' => optional($profile)->status_pernikahan,
            'status_nikah' => optional($profile)->status_pernikahan,
            'status_pernikahan' => optional($profile)->status_pernikahan,
            'marital_date' => '',
            'marital_place' => '',
            'nationality' => 'Indonesia',
            'shio' => $shioElemen['shio'] ?? null,
            'elemen' => $shioElemen['elemen'] ?? null,
            'foto_selfie' => $recruitment->picture,
            'picture_url' => $pictureUrl,
        ];

        $contact = [
            'address' => optional($profile)->alamat_domisili ?: optional($profile)->alamat_ktp,
            'country' => 'Indonesia',
            'province' => optional($profile)->provinsi_domisili ?: optional($profile)->provinsi_ktp,
            'city' => optional($profile)->kota_domisili ?: optional($profile)->kota_ktp,
            'phone' => $recruitment->no_telepon,
            'postal_code' => optional($profile)->kode_pos_domisili ?: optional($profile)->kode_pos_ktp,
        ];

        $employee = [
            'nik' => $nikKtp,
            'email' => $recruitment->email,
            'email_pribadi' => $recruitment->email,
            'estatus' => 'Contract',
            'sdate' => $startDate,
            'ecdate' => '',
            'branch' => is_numeric(optional($pr)->lokasi_penempatan_cabang) ? $pr->lokasi_penempatan_cabang : '',
            'departement' => is_numeric(optional($pr)->divisi) ? $pr->divisi : '',
            'position' => is_numeric(optional($pr)->posisi) ? $pr->posisi : '',
            'grade' => 'STAFF',
            'gradec' => 'STAFF',
            'jstatus' => 'Full Time',
            'ccenter' => optional($offer)->gaji_pokok ?? optional($recruitment->salaryOffer)->final_sallary ?? '',
            'dsupervisor' => [],
            'ppdate' => '',
            'pdate' => '',
        ];

        $medicalData = [
            'tinggi_badan' => optional($medical)->tinggi_badan,
            'berat_badan' => optional($medical)->berat_badan,
            'keterangan_mata' => optional($medical)->mata,
            'mata' => optional($medical)->mata,
            'golongan_darah' => optional($medical)->golongan_darah,
            'penyakit_bawaan_lahir' => optional($medical)->penyakit_bawaan_lahir,
            'penyakit_kronis' => optional($medical)->penyakit_kronis,
            'riwayat_kecelakaan' => optional($medical)->riwayat_kecelakaan,
        ];

        $payroll = [
            'gaji_pokok' => $gajiPokok,
            'tunjangan_kerja' => $tunjangan,
            'potongan_bpjs_kes' => $bpjsKes,
            'potongan_bpjs_tk' => $bpjsTk,
            'pot_pph21' => $pph21,
            'pencadangan_upah' => $pencadangan,
            'no_bpjs_ks' => optional($profile)->no_bpjs_ks,
            'no_bpjs_tk' => optional($profile)->no_bpjs_tk,
        ];

        $access = [
            'username' => $this->suggestUsername($recruitment),
            'password' => '',
            'priv_branch' => is_numeric(optional($pr)->lokasi_penempatan_cabang)
                ? [(string) $pr->lokasi_penempatan_cabang]
                : [],
        ];

        return array_merge([
            'id' => $recruitment->id,
            'nama_lengkap' => $recruitment->nama_lengkap,
            'nik_ktp' => $nikKtp,
            'tempat_lahir' => $recruitment->tempat_lahir,
            'tanggal_lahir' => $birthDate,
            'gender' => $recruitment->jenis_kelamin,
            'status_nikah' => optional($profile)->status_pernikahan,
            'status_pernikahan' => optional($profile)->status_pernikahan,
            'agama' => optional($profile)->agama,
            'nama_panggilan' => optional($profile)->nama_panggilan,
            'email' => $recruitment->email,
            'picture' => $recruitment->picture,
            'picture_url' => $pictureUrl,
            'no_telepon' => $recruitment->no_telepon,
            'tgl_kerja' => $startDate,
            'posisi_di_lamar' => $this->resolvePositionName($recruitment),
            'status_karyawan' => 'Contract',
            'salary_user' => optional($offer)->gaji_pokok ?? optional($recruitment->salaryOffer)->final_sallary ?? '',
            'tinggi_badan' => optional($medical)->tinggi_badan,
            'berat_badan' => optional($medical)->berat_badan,
            'mata' => optional($medical)->mata,
            'golongan_darah' => optional($medical)->golongan_darah,
            'pendidikan' => json_encode($educationRows),
            'sertifikat' => '[]',
            'skill' => '[]',
            'skill_bahasa' => '[]',
            'personal' => $personal,
            'contact' => $contact,
            'employee' => $employee,
            'medical' => $medicalData,
            'education' => $educationRows,
            'certificate' => [],
            'experience' => $experienceRows,
            'languages' => [],
            'payroll' => $payroll,
            'access' => $access,
            'candidate' => [
                'id' => $recruitment->id,
                'nama_lengkap' => $recruitment->nama_lengkap,
                'posisi_dilamar' => $this->resolvePositionName($recruitment),
                'no_request' => optional($pr)->no_request,
            ],
            'is_migrated' => $isMigrated,
            'can_migrate' => !$isMigrated,
            'block_reason' => $isMigrated ? 'Data karyawan sudah pernah dimigrasikan.' : null,
        ]);
    }

    private function performEmployeeMigrationFromForm($recruitment, Request $request)
    {
        $now = Carbon::now();
        $timestamp = $now->format('Y-m-d H:i:s');
        $profile = $recruitment->candidateProfile;
        $imageName = $recruitment->picture;

        if ($request->hasFile('personal.image')) {
            $profilePicture = $request->file('personal.image');
            $imageName = ($request->personal['nik_ktp'] ?? 'karyawan') . '_' . str_replace(' ', '_', $request->personal['nama_lengkap'] ?? 'karyawan') . '.' . $profilePicture->getClientOriginalExtension();
            $destinationPath = public_path('/Foto_Karyawan');
            $profilePicture->move($destinationPath, $imageName);
        } elseif ($request->hasFile('image')) {
            $profilePicture = $request->file('image');
            $imageName = ($request->personal['nik_ktp'] ?? 'karyawan') . '_' . str_replace(' ', '_', $request->personal['nama_lengkap'] ?? 'karyawan') . '.' . $profilePicture->getClientOriginalExtension();
            $destinationPath = public_path('/Foto_Karyawan');
            $profilePicture->move($destinationPath, $imageName);
        }

        $shioElemen = ShioElemenHelper::resolve(
            $request->personal['date_birth'] ?? null,
            $request->personal['shio'] ?? null,
            $request->personal['elemen'] ?? null
        );

        $karyawan = new MasterKaryawan();
        $karyawan->created_by = $this->karyawan;
        $karyawan->created_at = $timestamp;
        $karyawan->nama_lengkap = trim($request->personal['nama_lengkap'] ?? $recruitment->nama_lengkap);
        $karyawan->nik_ktp = $request->personal['nik_ktp'] ?? optional($profile)->nik_ktp;
        $karyawan->nama_panggilan = $request->personal['salutation'] ?? optional($profile)->nama_panggilan;
        $karyawan->kebangsaan = $request->personal['nationality'] ?? 'Indonesia';
        $karyawan->tempat_lahir = $request->personal['birth_place'] ?? $recruitment->tempat_lahir;
        $karyawan->tanggal_lahir = $request->personal['date_birth'] ?? $recruitment->tanggal_lahir;
        $karyawan->jenis_kelamin = $request->personal['gender'] ?? $recruitment->jenis_kelamin;
        $karyawan->agama = $request->personal['religion'] ?? optional($profile)->agama;
        $karyawan->status_pernikahan = $request->personal['marital_status'] ?? optional($profile)->status_pernikahan;
        $karyawan->tempat_nikah = $request->personal['marital_place'] ?? null;
        $karyawan->tgl_nikah = $request->personal['marital_date'] ?: null;
        $karyawan->shio = $shioElemen['shio'] ?? null;
        $karyawan->elemen = $shioElemen['elemen'] ?? null;
        $karyawan->nik_karyawan = $request->employee['nik'];
        $karyawan->email = $request->employee['email'];
        $karyawan->email_pribadi = $request->employee['email_pribadi'] ?? $request->employee['email'];
        $karyawan->id_cabang = $request->employee['branch'] ?: null;
        $karyawan->status_karyawan = $request->employee['estatus'] ?? 'Contract';
        $karyawan->tgl_mulai_kerja = $request->employee['sdate'] ?: null;
        $karyawan->tgl_berakhir_kontrak = $request->employee['ecdate'] ?: null;
        $karyawan->id_jabatan = $request->employee['position'] ?: null;
        $karyawan->kategori_grade = $request->employee['gradec'] ?? null;
        $karyawan->grade = $request->employee['grade'] ?? null;
        $karyawan->status_pekerjaan = $request->employee['jstatus'] ?? null;
        $karyawan->id_department = $request->employee['departement'] ?: null;
        $karyawan->atasan_langsung = json_encode($request->employee['dsupervisor'] ?? []);
        $karyawan->cost_center = $request->employee['ccenter'] ?? null;
        $karyawan->tgl_pra_pensiun = $request->employee['ppdate'] ?: null;
        $karyawan->tgl_pensiun = $request->employee['pdate'] ?: null;
        $karyawan->alamat = $request->contact['address'] ?? null;
        $karyawan->negara = $request->contact['country'] ?? 'Indonesia';
        $karyawan->provinsi = $request->contact['province'] ?? null;
        $karyawan->kota = $request->contact['city'] ?? null;
        $karyawan->no_telpon = $request->contact['phone'] ?? $recruitment->no_telepon;
        $karyawan->kode_pos = $request->contact['postal_code'] ?? null;
        $karyawan->no_bpjs_kes = data_get($request, 'payroll.no_bpjs_ks') ?: optional($profile)->no_bpjs_ks;
        $karyawan->no_bpjs_tk = data_get($request, 'payroll.no_bpjs_tk') ?: optional($profile)->no_bpjs_tk;
        $karyawan->no_npwp = optional($profile)->no_npwp;
        $karyawan->npwp = optional($profile)->no_npwp;
        $karyawan->no_kk = optional($profile)->no_kk;
        $karyawan->privilage_cabang = json_encode($request->access['priv_branch'] ?? []);
        $karyawan->image = $imageName;
        $karyawan->is_active = 1;
        $karyawan->save();

        $medis = new MedicalCheckup();
        $medis->karyawan_id = $karyawan->id;
        $medis->tinggi_badan = data_get($request, 'medical.tinggi_badan');
        $medis->berat_badan = data_get($request, 'medical.berat_badan');
        $medis->rate_mata = data_get($request, 'medical.keterangan_mata');
        $medis->golongan_darah = data_get($request, 'medical.golongan_darah');
        $medis->penyakit_bawaan_lahir = data_get($request, 'medical.penyakit_bawaan_lahir');
        $medis->penyakit_kronis = data_get($request, 'medical.penyakit_kronis');
        $medis->riwayat_kecelakaan = data_get($request, 'medical.riwayat_kecelakaan');
        $medis->keterangan_mata = data_get($request, 'medical.keterangan_mata');
        $medis->save();

        if ($request->has('education') && is_array($request->education)) {
            foreach ($request->education as $education) {
                if (!is_array($education)) {
                    continue;
                }
                $pendidikan = new PendidikanKaryawan();
                $pendidikan->karyawan_id = $karyawan->id;
                $pendidikan->institusi = $education['institusi'] ?? null;
                $pendidikan->jenjang = $education['jenjang'] ?? null;
                $pendidikan->jurusan = $education['jurusan'] ?? null;
                $pendidikan->tahun_masuk = $education['tahun_masuk'] ?? null;
                $pendidikan->tahun_lulus = $education['tahun_lulus'] ?? null;
                $pendidikan->kota = $education['kota'] ?? null;
                $pendidikan->created_by = $this->karyawan;
                $pendidikan->created_at = $timestamp;
                $pendidikan->save();
            }
        }

        if ($request->has('certificate') && is_array($request->certificate)) {
            foreach ($request->certificate as $certificate) {
                if (!is_array($certificate)) {
                    continue;
                }
                $sertifikat = new SertifikatKaryawan();
                $sertifikat->karyawan_id = $karyawan->id;
                $sertifikat->nama_sertifikat = $certificate['nama_sertifikat'] ?? $certificate['nama'] ?? null;
                $sertifikat->tipe_sertifikat = $certificate['tipe_sertifikat'] ?? $certificate['tipe'] ?? null;
                $sertifikat->nomor_sertifikat = $certificate['nomor_sertifikat'] ?? $certificate['nomor'] ?? null;
                $sertifikat->deskripsi_sertifikat = $certificate['deskripsi_sertifikat'] ?? $certificate['deskripsi'] ?? null;
                $sertifikat->tgl_sertifikat = $certificate['tgl_sertifikat'] ?? $certificate['tanggal_sertifikasi'] ?? null;
                $sertifikat->tgl_exp_sertifikat = $certificate['tgl_exp_sertifikat'] ?? $certificate['tanggal_expired'] ?? null;
                $sertifikat->created_by = $this->karyawan;
                $sertifikat->created_at = $timestamp;
                $sertifikat->save();
            }
        }

        if ($request->has('experience') && is_array($request->experience)) {
            foreach ($request->experience as $experience) {
                if (!is_array($experience)) {
                    continue;
                }
                $pengalaman = new PengalamanKerjaKaryawan();
                $pengalaman->karyawan_id = $karyawan->id;
                $pengalaman->nama_perusahaan = $experience['nama_perusahaan'] ?? null;
                $pengalaman->lokasi_perusahaan = $experience['lokasi_perusahaan'] ?? null;
                $pengalaman->posisi_kerja = $experience['posisi_kerja'] ?? null;
                $pengalaman->tgl_mulai_kerja = $experience['tgl_mulai_kerja'] ?? null;
                $pengalaman->tgl_berakhir_kerja = $experience['tgl_berakhir_kerja'] ?? null;
                $pengalaman->alasan_keluar = $experience['alasan_keluar'] ?? null;
                $pengalaman->created_by = $this->karyawan;
                $pengalaman->created_at = $timestamp;
                $pengalaman->save();
            }
        }

        if ($request->has('skill') && is_array($request->skill)) {
            foreach ($request->skill as $skill) {
                if (!is_array($skill)) {
                    continue;
                }
                $keahlian = new KeahlianKaryawan();
                $keahlian->karyawan_id = $karyawan->id;
                $keahlian->keahlian = $skill['keahlian'] ?? null;
                $keahlian->rate = $skill['rate'] ?? null;
                $keahlian->save();
            }
        }

        if ($request->has('languages') && is_array($request->languages)) {
            foreach ($request->languages as $language) {
                if (!is_array($language)) {
                    continue;
                }
                $bahasa = new KeahlianBahasaKaryawan();
                $bahasa->karyawan_id = $karyawan->id;
                $bahasa->bahasa = $language['bahasa'] ?? null;
                $bahasa->baca = $language['baca'] ?? null;
                $bahasa->tulis = $language['tulis'] ?? null;
                $bahasa->dengar = $language['dengar'] ?? null;
                $bahasa->bicara = $language['bicara'] ?? null;
                $bahasa->save();
            }
        }

        $user = new User();
        $user->created_by = $this->karyawan;
        $user->created_at = $timestamp;
        $user->username = $request->access['username'];
        $user->email = $request->employee['email'];
        $user->password = Hash::make($request->access['password']);
        $user->save();

        $karyawan->user_id = $user->id;
        $karyawan->updated_by = $this->karyawan;
        $karyawan->updated_at = $timestamp;
        $karyawan->save();

        $nikKaryawan = $karyawan->nik_karyawan;
        $namaKaryawan = $karyawan->nama_lengkap;
        $startDate = $request->employee['sdate'] ?: $now->toDateString();
        $effectiveMonth = Carbon::parse($startDate)->startOfMonth()->toDateString();

        $gajiPokok = $this->parseNumeric(data_get($request, 'payroll.gaji_pokok'));
        $tunjangan = $this->parseNumeric(data_get($request, 'payroll.tunjangan_kerja'));
        $bpjsKes = $this->parseNumeric(data_get($request, 'payroll.potongan_bpjs_kes'));
        $bpjsTk = $this->parseNumeric(data_get($request, 'payroll.potongan_bpjs_tk'));
        $pph21 = $this->parseNumeric(data_get($request, 'payroll.pot_pph21'));
        $pencadangan = $this->parseNumeric(data_get($request, 'payroll.pencadangan_upah'));
        $noBpjsKs = data_get($request, 'payroll.no_bpjs_ks') ?: optional($profile)->no_bpjs_ks;
        $noBpjsTk = data_get($request, 'payroll.no_bpjs_tk') ?: optional($profile)->no_bpjs_tk;

        if ($gajiPokok > 0) {
            $this->upsertPayroll('master_sallary', $nikKaryawan, $namaKaryawan, [
                'gaji_pokok' => $gajiPokok,
                'tunjangan_kerja' => $tunjangan,
                'bulan_efektif' => $effectiveMonth,
            ], $now);
        }

        if ($bpjsKes > 0) {
            $this->upsertPayroll('bpjs_kesehatan', $nikKaryawan, $namaKaryawan, [
                'gaji_pokok' => $gajiPokok,
                'no_bpjs' => $noBpjsKs,
                'potongan_karyawan' => 0.005,
                'nominal_potongan_karyawan' => $bpjsKes,
                'potongan_kantor' => 0.02,
                'nominal_potongan_kantor' => $gajiPokok * 0.02,
                'bulan_efektif' => $effectiveMonth,
            ], $now);
        }

        if ($bpjsTk > 0) {
            $this->upsertPayroll('bpjs_tk', $nikKaryawan, $namaKaryawan, [
                'gaji_pokok' => $gajiPokok,
                'no_bpjs_tk' => $noBpjsTk,
                'potongan_karyawan' => 0.03,
                'nominal_potongan_karyawan' => $bpjsTk,
                'potongan_kantor' => 0.01,
                'nominal_potongan_kantor' => $gajiPokok * 0.01,
                'bulan_efektif' => $effectiveMonth,
            ], $now);
        }

        if ($pph21 > 0) {
            $this->upsertPayroll('pph_21', $nikKaryawan, $namaKaryawan, [
                'pajak_bulanan' => $pph21,
                'pajak_tahunan' => $pph21 * 12,
                'bulan_mulai_pemotongan' => $effectiveMonth,
            ], $now);
        }

        if ($pencadangan > 0) {
            $this->upsertPayroll('pencadangan_upah', $nikKaryawan, $namaKaryawan, [
                'nominal' => $pencadangan,
                'nominal_berjalan' => -$pencadangan,
                'tenor' => 1,
                'tenor_berjalan' => -1,
                'bulan_efektif' => $effectiveMonth,
                'status' => 'ONGOING',
            ], $now);
        }

        if (Schema::hasTable('candidate_onboarding_verification')) {
            DB::table('candidate_onboarding_verification')->updateOrInsert(
                ['new_recruitment_id' => $recruitment->id],
                $this->existingColumns('candidate_onboarding_verification', [
                    'employee_migrated_at' => $now,
                    'employee_migrated_by' => $this->karyawan,
                    'updated_at' => $now,
                ])
            );
        }
    }

    private function mapCandidateEducations($recruitment)
    {
        $rows = $recruitment->candidateEducations ?? collect();
        if ($rows->isEmpty()) {
            $rows = DB::table('candidate_educations')
                ->where('new_recruitment_id', $recruitment->id)
                ->get();
        }

        return collect($rows)->map(function ($item) {
            return [
                'institusi' => $item->nama_institusi ?? $item->institusi ?? '',
                'jenjang' => $item->jenjang_pendidikan ?? $item->jenjang ?? '',
                'jurusan' => $item->jurusan ?? '',
                'tahun_masuk' => $item->tahun_masuk ?? '',
                'tahun_lulus' => $item->tahun_lulus ?? '',
                'kota' => $item->kota ?? '',
            ];
        })->values()->all();
    }

    private function mapCandidateExperiences($recruitment)
    {
        if (!Schema::hasTable('candidate_work_experiences')) {
            return $this->mapLegacyPengalamanKerja($recruitment);
        }

        $rows = collect();

        if ($recruitment->relationLoaded('candidateWorkExperiences')) {
            $rows = $rows->merge($recruitment->candidateWorkExperiences);
        } else {
            $rows = $rows->merge(
                DB::table('candidate_work_experiences')
                    ->where('new_recruitment_id', $recruitment->id)
                    ->orderBy('id')
                    ->get()
            );
        }

        $profile = $recruitment->candidateProfile;
        if ($profile) {
            if ($profile->relationLoaded('workExperiences')) {
                $rows = $rows->merge($profile->workExperiences);
            } else {
                $rows = $rows->merge(
                    DB::table('candidate_work_experiences')
                        ->where('candidate_profile_id', $profile->id)
                        ->orderBy('id')
                        ->get()
                );
            }
        }

        $rows = $rows->unique(function ($item) {
            return is_object($item) ? (string) ($item->id ?? spl_object_hash($item)) : json_encode($item);
        });

        if ($rows->isNotEmpty()) {
            return $rows->map(function ($item) {
                return $this->normalizeExperienceRow($item);
            })->values()->all();
        }

        return $this->mapLegacyPengalamanKerja($recruitment);
    }

    private function mapLegacyPengalamanKerja($recruitment)
    {
        $raw = $recruitment->pengalaman_kerja ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw) || count($raw) === 0) {
            return [];
        }

        return collect($raw)
            ->filter(function ($exp) {
                return is_array($exp);
            })
            ->map(function ($exp) {
                $mulai = $exp['mulai_kerja'] ?? $exp['mulai'] ?? $exp['tanggal_mulai'] ?? $exp['tgl_mulai_kerja'] ?? null;
                $akhir = $exp['akhir_kerja'] ?? $exp['akhir'] ?? $exp['tanggal_selesai'] ?? $exp['tgl_berakhir_kerja'] ?? null;
                $startDate = $this->formatDateInput($mulai);
                $endDate = $this->formatDateInput($akhir);
                $posisi = $exp['posisi_kerja'] ?? $exp['posisi_terakhir'] ?? '';
                $alasan = $exp['alasan_keluar'] ?? $exp['alasan_resign'] ?? '';

                return [
                    'id' => null,
                    'nama_perusahaan' => $exp['nama_perusahaan'] ?? '',
                    'posisi_terakhir' => $posisi,
                    'posisi_kerja' => $posisi,
                    'lokasi_perusahaan' => $exp['lokasi_perusahaan'] ?? '',
                    'tanggal_mulai' => $startDate,
                    'tanggal_selesai' => $endDate,
                    'tgl_mulai_kerja' => $startDate,
                    'tgl_berakhir_kerja' => $endDate,
                    'alasan_resign' => $alasan,
                    'alasan_keluar' => $alasan,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeExperienceRow($item)
    {
        $startDate = $this->formatDateInput($item->tanggal_mulai ?? null);
        $endDate = $this->formatDateInput($item->tanggal_selesai ?? null);

        return [
            'id' => $item->id ?? null,
            'nama_perusahaan' => $item->nama_perusahaan ?? '',
            'posisi_terakhir' => $item->posisi_terakhir ?? '',
            'posisi_kerja' => $item->posisi_terakhir ?? '',
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => $endDate,
            'tgl_mulai_kerja' => $startDate,
            'tgl_berakhir_kerja' => $endDate,
            'alasan_resign' => $item->alasan_resign ?? '',
            'alasan_keluar' => $item->alasan_resign ?? '',
        ];
    }

    private function suggestUsername($recruitment)
    {
        $email = trim((string) $recruitment->email);
        if ($email !== '' && str_contains($email, '@')) {
            return strtolower(strtok($email, '@'));
        }

        $name = preg_replace('/\s+/', '.', strtolower(trim((string) $recruitment->nama_lengkap)));
        return substr($name, 0, 30);
    }

    private function formatDateInput($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            $value = trim((string) $value);
            if (preg_match('/^\d{4}-\d{2}$/', $value)) {
                return $value . '-01';
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return '';
        }
    }

    private function parseNumeric($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) preg_replace('/[^\d.-]/', '', (string) $value);
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
