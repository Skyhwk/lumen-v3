<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\RecruitmentStatusService;

class CompleteProfileController extends Controller
{
    public function overview(Request $request)
    {
        $recruitment = $this->recruitment($request->token);
        if (!$recruitment) {
            return response()->json(['message' => 'Link kelengkapan profil tidak valid.'], 404);
        }
        if (!(int) $recruitment->is_approved_interview_hrd) {
            return response()->json(['message' => 'Kelengkapan profil baru dapat diisi setelah Anda disetujui pada tahap interview HRD.'], 403);
        }
        $profile = DB::table('candidate_profiles')->where('new_recruitment_id', $recruitment->id)->first();
        if ($recruitment->status !== 'profile_completion' || $profile) {
            return response()->json([
                'status' => 'completed',
                'message' => 'Kelengkapan profil Anda sudah dikirim. Tim rekrutmen akan menghubungi Anda apabila ada tahap berikutnya.',
                'recruitment' => $this->recruitmentData($recruitment),
            ]);
        }

        return response()->json([
            'status' => 'ready',
            'recruitment' => $this->recruitmentData($recruitment),
            'profile' => $profile,
            'educations' => $profile ? DB::table('candidate_educations')->where('candidate_profile_id', $profile->id)->where('is_active', 1)->get() : $this->educations($recruitment),
            'work_experiences' => $profile ? DB::table('candidate_work_experiences')->where('candidate_profile_id', $profile->id)->where('is_active', 1)->get() : $this->workExperiences($recruitment),
            'documents' => $profile ? DB::table('candidate_documents')->where('candidate_profile_id', $profile->id)->where('is_active', 1)->get() : [],
            'medical' => $profile ? DB::table('candidate_medical_informations')->where('candidate_profile_id', $profile->id)->where('is_active', 1)->first() : null,
        ]);
    }

    public function submit(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $recruitment = $this->recruitment($request->token, true);
            if (!$recruitment) {
                return response()->json(['message' => 'Link kelengkapan profil tidak valid.'], 404);
            }
            if (!(int) $recruitment->is_approved_interview_hrd) {
                return response()->json(['message' => 'Kelengkapan profil baru dapat diisi setelah Anda disetujui pada tahap interview HRD.'], 403);
            }
            if ($recruitment->status !== 'profile_completion') {
                return response()->json(['message' => 'Kelengkapan profil sudah pernah dikirim.'], 409);
            }
            if (DB::table('candidate_profiles')->where('new_recruitment_id', $recruitment->id)->exists()) {
                return response()->json(['message' => 'Data kelengkapan profil sudah pernah dikirim.'], 409);
            }

            $errors = $this->validateInput($request);
            if ($errors) {
                return response()->json(['message' => 'Mohon lengkapi data yang wajib diisi.', 'errors' => $errors], 422);
            }

            $now = Carbon::now();
            $base = $this->baseData($request, $recruitment);
            DB::table('new_recruitment')->where('id', $recruitment->id)->update(array_merge($base, [
                'updated_at' => $now,
            ]));
            $profileId = DB::table('candidate_profiles')->insertGetId([
                'new_recruitment_id' => $recruitment->id,
                'nama_panggilan' => trim((string) $request->input('nama_panggilan')) ?: null,
                'nik_ktp' => trim((string) $request->input('nik_ktp')) ?: null,
                'no_kk' => trim((string) $request->input('no_kk')) ?: null,
                'no_npwp' => trim((string) $request->input('no_npwp')) ?: null,
                'no_bpjs_ks' => trim((string) $request->input('no_bpjs_ks')) ?: null,
                'no_bpjs_tk' => trim((string) $request->input('no_bpjs_tk')) ?: null,
                'agama' => trim((string) $request->input('agama')) ?: null,
                'status_pernikahan' => trim((string) $request->input('status_pernikahan')) ?: null,
                'alamat_ktp' => $base['alamat_ktp'],
                'kota_ktp' => trim((string) $request->input('kota_ktp')) ?: null,
                'provinsi_ktp' => trim((string) $request->input('provinsi_ktp')) ?: null,
                'kode_pos_ktp' => trim((string) $request->input('kode_pos_ktp')) ?: null,
                'alamat_domisili' => $base['alamat_domisili'],
                'kota_domisili' => trim((string) $request->input('kota_domisili')) ?: null,
                'provinsi_domisili' => trim((string) $request->input('provinsi_domisili')) ?: null,
                'kode_pos_domisili' => trim((string) $request->input('kode_pos_domisili')) ?: null,
                'status_tempat_tinggal' => trim((string) $request->input('status_tempat_tinggal')) ?: null,
                'nama_kontak_darurat' => trim((string) $request->input('nama_kontak_darurat')) ?: null,
                'hubungan_kontak_darurat' => trim((string) $request->input('hubungan_kontak_darurat')) ?: null,
                'no_telepon_darurat' => trim((string) $request->input('no_telepon_darurat')) ?: null,
                'created_by' => 'Candidate Profile Portal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->storeMedicalInformation($request, $profileId, $recruitment->id, $now);
            $this->storeEducations($request->input('educations', []), $profileId, $recruitment->id, $now);
            $this->storeWorkExperiences($request->input('work_experiences', []), $profileId, $recruitment->id, $now);
            $this->storeDocuments($request->input('documents', []), $profileId, $recruitment->id, $now);
            (new RecruitmentStatusService())->update($recruitment->id, 'interview_user', $now);

            return response()->json(['status' => true, 'message' => 'Kelengkapan profil berhasil dikirim.']);
        });
    }

    private function recruitment($token, $lock = false)
    {
        $query = DB::table('new_recruitment')->where('token', $token);
        if ($lock) {
            $query->lockForUpdate();
        }
        $recruitment = $query->first();
        return $recruitment;
    }

    private function recruitmentData($recruitment)
    {
        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'tempat_lahir' => $recruitment->tempat_lahir,
            'tanggal_lahir' => $recruitment->tanggal_lahir,
            'jenis_kelamin' => $recruitment->jenis_kelamin,
            'no_telepon' => $recruitment->no_telepon,
            'email' => $recruitment->email,
            'alamat_ktp' => $recruitment->alamat_ktp,
            'alamat_domisili' => $recruitment->alamat_domisili,
            'posisi_dilamar' => $this->positionLabel($recruitment),
            'gaji_terakhir' => $recruitment->gaji_terakhir,
            'ekspetasi_gaji' => $recruitment->ekspetasi_gaji,
        ];
    }

    private function educations($recruitment)
    {
        return collect(json_decode($recruitment->pendidikan ?: '[]', true) ?: [])->map(function ($item) {
            return [
                'jenjang_pendidikan' => $item['jenjang_pendidikan'] ?? $item['jenjang'] ?? '',
                'nama_institusi' => $item['nama_institusi'] ?? $item['institusi'] ?? '',
                'jurusan' => $item['jurusan'] ?? '',
                'nilai_ipk' => $item['nilai_ipk'] ?? $item['ipk'] ?? '',
                'tahun_masuk' => $item['tahun_masuk'] ?? $item['start_year'] ?? '',
                'tahun_lulus' => $item['tahun_lulus'] ?? $item['graduated_year'] ?? '',
            ];
        })->values()->all();
    }

    private function positionLabel($recruitment)
    {
        $alias = DB::table('personnel_requests')
            ->where('id', $recruitment->personnel_request_id)
            ->value('divisi_alias');

        return $alias ?: $recruitment->posisi_dilamar;
    }

    private function workExperiences($recruitment)
    {
        return collect(json_decode($recruitment->pengalaman_kerja ?: '[]', true) ?: [])->map(function ($item) {
            return [
                'nama_perusahaan' => $item['nama_perusahaan'] ?? $item['company'] ?? '',
                'posisi_terakhir' => $item['posisi_terakhir'] ?? $item['posisi_kerja'] ?? $item['position'] ?? '',
                'tanggal_mulai' => $item['tanggal_mulai'] ?? $item['mulai_kerja'] ?? $item['start_date'] ?? '',
                'tanggal_selesai' => $item['tanggal_selesai'] ?? $item['akhir_kerja'] ?? $item['end_date'] ?? '',
                'alasan_resign' => $item['alasan_resign'] ?? $item['alasan_keluar'] ?? $item['reason_for_leaving'] ?? '',
            ];
        })->values()->all();
    }

    private function validateInput(Request $request)
    {
        $required = ['nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'no_telepon', 'email', 'alamat_ktp', 'alamat_domisili', 'nama_panggilan', 'nik_ktp', 'agama', 'status_pernikahan', 'kota_ktp', 'provinsi_ktp', 'kode_pos_ktp', 'kota_domisili', 'provinsi_domisili', 'kode_pos_domisili', 'status_tempat_tinggal', 'nama_kontak_darurat', 'hubungan_kontak_darurat', 'no_telepon_darurat', 'tinggi_badan', 'berat_badan', 'mata'];
        $errors = [];
        foreach ($required as $field) {
            if (trim((string) $request->input($field)) === '') {
                $errors[$field] = ['Field wajib diisi.'];
            }
        }
        if ($request->input('email') && !filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Format email tidak valid.'];
        }
        if ($request->input('jenis_kelamin') && !in_array($request->input('jenis_kelamin'), ['Male', 'Female'], true)) {
            $errors['jenis_kelamin'] = ['Jenis kelamin tidak valid.'];
        }
        if (empty($request->input('educations', []))) {
            $errors['educations'] = ['Minimal satu riwayat pendidikan wajib diisi.'];
        }
        return $errors;
    }

    private function baseData(Request $request, $recruitment)
    {
        return [
            'nama_lengkap' => trim((string) $request->input('nama_lengkap')),
            'tempat_lahir' => trim((string) $request->input('tempat_lahir')),
            'tanggal_lahir' => $request->input('tanggal_lahir'),
            'jenis_kelamin' => $request->input('jenis_kelamin'),
            'no_telepon' => trim((string) $request->input('no_telepon')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'alamat_ktp' => trim((string) $request->input('alamat_ktp')),
            'alamat_domisili' => trim((string) $request->input('alamat_domisili')),
        ];
    }

    private function storeEducations($items, $profileId, $recruitmentId, $now)
    {
        foreach ((array) $items as $item) {
            if (empty($item['jenjang_pendidikan']) || empty($item['nama_institusi'])) {
                continue;
            }
            DB::table('candidate_educations')->insert(['candidate_profile_id' => $profileId, 'new_recruitment_id' => $recruitmentId, 'jenjang_pendidikan' => $item['jenjang_pendidikan'], 'nama_institusi' => $item['nama_institusi'], 'jurusan' => $item['jurusan'] ?? null, 'nilai_ipk' => $item['nilai_ipk'] ?: null, 'tahun_masuk' => $item['tahun_masuk'] ?: null, 'tahun_lulus' => $item['tahun_lulus'] ?: null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function storeWorkExperiences($items, $profileId, $recruitmentId, $now)
    {
        foreach ((array) $items as $item) {
            if (empty($item['nama_perusahaan']) || empty($item['posisi_terakhir'])) {
                continue;
            }
            DB::table('candidate_work_experiences')->insert(['candidate_profile_id' => $profileId, 'new_recruitment_id' => $recruitmentId, 'nama_perusahaan' => $item['nama_perusahaan'], 'posisi_terakhir' => $item['posisi_terakhir'], 'tanggal_mulai' => $item['tanggal_mulai'] ?: null, 'tanggal_selesai' => $item['tanggal_selesai'] ?: null, 'alasan_resign' => $item['alasan_resign'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function storeDocuments($items, $profileId, $recruitmentId, $now)
    {
        foreach ((array) $items as $item) {
            if (empty($item['data']) || empty($item['jenis_dokumen'])) {
                continue;
            }
            if (!preg_match('/^data:([\w.+-]+\/[\w.+-]+);base64,(.+)$/', $item['data'], $matches)) {
                throw new \RuntimeException('Format dokumen tidak valid.');
            }
            $binary = base64_decode($matches[2], true);
            if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
                throw new \RuntimeException('Ukuran dokumen maksimal 5 MB.');
            }
            $extension = strtolower(pathinfo($item['nama_file'] ?? '', PATHINFO_EXTENSION)) ?: 'bin';
            if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                throw new \RuntimeException('Dokumen hanya menerima PDF, JPG, atau PNG.');
            }
            $directory = public_path('recruitment/candidate-documents');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $safeType = preg_replace('/[^a-z0-9]+/i', '-', strtolower($item['jenis_dokumen']));
            $fileName = 'candidate-' . $recruitmentId . '-' . $safeType . '-' . time() . '-' . substr(md5($item['nama_file']), 0, 6) . '.' . $extension;
            file_put_contents($directory . DIRECTORY_SEPARATOR . $fileName, $binary);
            DB::table('candidate_documents')->insert(['candidate_profile_id' => $profileId, 'new_recruitment_id' => $recruitmentId, 'jenis_dokumen' => $item['jenis_dokumen'], 'nama_file' => $item['nama_file'] ?? $fileName, 'path_file' => 'recruitment/candidate-documents/' . $fileName, 'mime_type' => $matches[1], 'ukuran_file' => strlen($binary), 'catatan' => $item['catatan'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function storeMedicalInformation(Request $request, $profileId, $recruitmentId, $now)
    {
        DB::table('candidate_medical_informations')->insert([
            'new_recruitment_id' => $recruitmentId,
            'candidate_profile_id' => $profileId,
            'tinggi_badan' => $request->input('tinggi_badan') !== null && $request->input('tinggi_badan') !== '' ? (float) $request->input('tinggi_badan') : null,
            'berat_badan' => $request->input('berat_badan') !== null && $request->input('berat_badan') !== '' ? (float) $request->input('berat_badan') : null,
            'mata' => trim((string) $request->input('mata')) ?: null,
            'golongan_darah' => trim((string) $request->input('golongan_darah')) ?: null,
            'penyakit_bawaan_lahir' => trim((string) $request->input('penyakit_bawaan_lahir')) ?: null,
            'penyakit_kronis' => trim((string) $request->input('penyakit_kronis')) ?: null,
            'riwayat_kecelakaan' => trim((string) $request->input('riwayat_kecelakaan')) ?: null,
            'is_active' => 1,
            'created_by' => 'Candidate Profile Portal',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
