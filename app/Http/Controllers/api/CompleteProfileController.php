<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\RecruitmentStatusService;
use App\Services\RecruitmentPictureService;
use App\Services\AtsNotificationService;
use App\Services\ScaleScoringService;

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
            'skills' => $this->skills($recruitment),
            'documents' => $profile ? DB::table('candidate_documents')->where('candidate_profile_id', $profile->id)->where('is_active', 1)->get() : [],
            'medical' => $profile ? DB::table('candidate_medical_informations')->where('candidate_profile_id', $profile->id)->where('is_active', 1)->first() : null,
            'supporting_categories' => $this->supportingQuestionCategories(),
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
                'jumlah_tanggungan' => is_numeric($request->input('jumlah_tanggungan'))
                    ? (int) $request->input('jumlah_tanggungan')
                    : null,
                'nama_kontak_darurat' => trim((string) $request->input('nama_kontak_darurat')) ?: null,
                'hubungan_kontak_darurat' => trim((string) $request->input('hubungan_kontak_darurat')) ?: null,
                'no_telepon_darurat' => trim((string) $request->input('no_telepon_darurat')) ?: null,
                'nama_kontak_darurat_2' => trim((string) $request->input('nama_kontak_darurat_2')) ?: null,
                'hubungan_kontak_darurat_2' => trim((string) $request->input('hubungan_kontak_darurat_2')) ?: null,
                'no_telepon_darurat_2' => trim((string) $request->input('no_telepon_darurat_2')) ?: null,
                'created_by' => 'Candidate Profile Portal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->storeMedicalInformation($request, $profileId, $recruitment->id, $now);
            $this->storeEducations($request->input('educations', []), $profileId, $recruitment->id, $now);
            $this->storeWorkExperiences($request->input('work_experiences', []), $profileId, $recruitment->id, $now);
            $this->storeDocuments($request->input('documents', []), $profileId, $recruitment->id, $now);
            $this->storeSupportingAnswers($request->input('supporting_answers', []), $profileId, $recruitment->id, $now);
            (new RecruitmentStatusService())->update($recruitment->id, 'interview_user', $now);

            $personnelRequest = DB::table('personnel_requests')
                ->where('id', $recruitment->personnel_request_id)
                ->first();
            app(AtsNotificationService::class)->profileCompleted($recruitment, $personnelRequest);

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
            'picture_base64' => app(RecruitmentPictureService::class)->toDataUri($recruitment->picture ?? null),
        ];
    }

    private function skills($recruitment)
    {
        return collect(json_decode($recruitment->skill ?: '[]', true) ?: [])->map(function ($item) {
            return [
                'keahlian' => trim((string) ($item['keahlian'] ?? $item['skill'] ?? '')),
                'rate' => $item['rate'] ?? '',
            ];
        })->filter(fn ($item) => $item['keahlian'] !== '')->values()->all();
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
        $required = ['nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'no_telepon', 'email', 'alamat_ktp', 'alamat_domisili', 'nama_panggilan', 'nik_ktp', 'agama', 'status_pernikahan', 'kota_ktp', 'provinsi_ktp', 'kode_pos_ktp', 'kota_domisili', 'provinsi_domisili', 'kode_pos_domisili', 'status_tempat_tinggal', 'jumlah_tanggungan', 'nama_kontak_darurat', 'hubungan_kontak_darurat', 'no_telepon_darurat', 'nama_kontak_darurat_2', 'hubungan_kontak_darurat_2', 'no_telepon_darurat_2', 'tinggi_badan', 'berat_badan', 'mata'];
        $errors = [];
        foreach ($required as $field) {
            if ($field === 'jumlah_tanggungan') {
                if ($request->input('jumlah_tanggungan') === null || $request->input('jumlah_tanggungan') === '') {
                    $errors[$field] = ['Field wajib diisi.'];
                }
                continue;
            }
            if (trim((string) $request->input($field)) === '') {
                $errors[$field] = ['Field wajib diisi.'];
            }
        }

        if ($request->input('jumlah_tanggungan') !== null && $request->input('jumlah_tanggungan') !== '') {
            $jumlahTanggungan = (int) $request->input('jumlah_tanggungan');
            if ($jumlahTanggungan < 0 || $jumlahTanggungan > 8) {
                $errors['jumlah_tanggungan'] = ['Jumlah tanggungan harus antara 0 sampai 8.'];
            }
        }

        $duplicateEmergencyContactErrors = $this->validateEmergencyContacts($request);
        if (!empty($duplicateEmergencyContactErrors)) {
            $errors = array_merge($errors, $duplicateEmergencyContactErrors);
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

        $documentErrors = $this->validateRequiredDocuments($request->input('documents', []));
        if (!empty($documentErrors)) {
            $errors = array_merge($errors, $documentErrors);
        }

        $supportingErrors = $this->validateSupportingAnswers($request->input('supporting_answers', []));
        if (!empty($supportingErrors)) {
            $errors = array_merge($errors, $supportingErrors);
        }

        return $errors;
    }

    private function validateEmergencyContacts(Request $request)
    {
        $errors = [];
        $name1 = $this->normalizeEmergencyText($request->input('nama_kontak_darurat'));
        $name2 = $this->normalizeEmergencyText($request->input('nama_kontak_darurat_2'));
        $phone1 = $this->normalizeEmergencyPhone($request->input('no_telepon_darurat'));
        $phone2 = $this->normalizeEmergencyPhone($request->input('no_telepon_darurat_2'));

        if ($phone1 !== '' && $phone2 !== '' && $phone1 === $phone2) {
            $errors['no_telepon_darurat_2'] = ['Nomor telepon kontak darurat 1 dan 2 tidak boleh sama.'];
        }

        if ($name1 !== '' && $name2 !== '' && $name1 === $name2) {
            $errors['nama_kontak_darurat_2'] = ['Nama kontak darurat 1 dan 2 tidak boleh sama.'];
        }

        return $errors;
    }

    private function normalizeEmergencyPhone($value)
    {
        return preg_replace('/\D+/', '', trim((string) $value));
    }

    private function normalizeEmergencyText($value)
    {
        return mb_strtolower(trim((string) $value));
    }

    private function validateRequiredDocuments(array $documents): array
    {
        $requiredTypes = [
            'KTP' => 'Dokumen KTP wajib diunggah.',
            'KARTU KELUARGA' => 'Dokumen Kartu Keluarga wajib diunggah.',
            'IJAZAH / SKL' => 'Dokumen Ijazah / SKL wajib diunggah.',
        ];
        $uploadedTypes = [];

        foreach ($documents as $document) {
            if (!is_array($document) || empty($document['jenis_dokumen']) || empty($document['data'])) {
                continue;
            }

            $normalizedType = strtoupper(trim((string) $document['jenis_dokumen']));
            $uploadedTypes[$normalizedType] = true;
        }

        $errors = [];
        foreach ($requiredTypes as $type => $message) {
            if (empty($uploadedTypes[$type])) {
                $errors['documents.' . strtolower(str_replace(' ', '_', $type))] = [$message];
            }
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
            'skill' => json_encode(collect((array) $request->input('skills', []))->map(function ($item) {
                return [
                    'keahlian' => trim((string) ($item['keahlian'] ?? '')),
                    'rate' => isset($item['rate']) && $item['rate'] !== '' ? max(1, min(10, (int) $item['rate'])) : null,
                ];
            })->filter(fn ($item) => $item['keahlian'] !== '')->values()->all()),
        ];
    }

    private function storeEducations($items, $profileId, $recruitmentId, $now)
    {
        foreach ((array) $items as $item) {
            if (empty($item['jenjang_pendidikan']) || empty($item['nama_institusi'])) {
                continue;
            }
            DB::table('candidate_educations')->insert([
                'candidate_profile_id' => $profileId,
                'new_recruitment_id' => $recruitmentId,
                'jenjang_pendidikan' => $item['jenjang_pendidikan'] ?? null,
                'nama_institusi' => $item['nama_institusi'] ?? null,
                'jurusan' => $item['jurusan'] ?? null,
                'nilai_ipk' => !empty($item['nilai_ipk']) ? $item['nilai_ipk'] : null,
                'tahun_masuk' => !empty($item['tahun_masuk']) ? $item['tahun_masuk'] : null,
                'tahun_lulus' => !empty($item['tahun_lulus']) ? $item['tahun_lulus'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function storeWorkExperiences($items, $profileId, $recruitmentId, $now)
    {
        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $namaPerusahaan = trim((string) ($item['nama_perusahaan'] ?? ''));
            $posisi = trim((string) ($item['posisi_terakhir'] ?? $item['posisi_kerja'] ?? ''));
            if ($namaPerusahaan === '' || $posisi === '') {
                continue;
            }

            $mulai = $item['tanggal_mulai'] ?? $item['mulai_kerja'] ?? $item['tgl_mulai_kerja'] ?? null;
            $selesai = $item['tanggal_selesai'] ?? $item['akhir_kerja'] ?? $item['tgl_berakhir_kerja'] ?? null;

            DB::table('candidate_work_experiences')->insert([
                'candidate_profile_id' => $profileId,
                'new_recruitment_id' => $recruitmentId,
                'nama_perusahaan' => $namaPerusahaan,
                'posisi_terakhir' => $posisi,
                'tanggal_mulai' => !empty($mulai) ? $mulai : null,
                'tanggal_selesai' => !empty($selesai) ? $selesai : null,
                'alasan_resign' => $item['alasan_resign'] ?? $item['alasan_keluar'] ?? null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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

    private function supportingQuestionCategories(): array
    {
        if (!Schema::hasTable('question_categories') || !Schema::hasTable('questions')) {
            return [];
        }

        $categories = DB::table('question_categories')
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->whereRaw('UPPER(name) = ?', ['INFORMASI PENDUKUNG']);
                if (Schema::hasColumn('question_categories', 'category_scope')) {
                    $query->orWhere('category_scope', 'profile_support');
                }
            })
            ->orderBy('name')
            ->get();

        return $categories->map(function ($category) {
            $questions = DB::table('questions')
                ->where('question_category_id', $category->id)
                ->where('is_active', 1)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->where('question_scope', 'hr')->orWhereNull('question_scope');
                })
                ->whereIn('question_type', ['single_choice', 'multiple_choice', 'scale'])
                ->orderBy('id')
                ->get()
                ->map(function ($question) {
                    return $this->mapSupportingQuestion($question);
                })
                ->values()
                ->all();

            return [
                'id' => (int) $category->id,
                'name' => $category->name,
                'questions' => $questions,
            ];
        })->filter(function ($category) {
            return !empty($category['questions']);
        })->values()->all();
    }

    private function mapSupportingQuestion($question): array
    {
        $options = DB::table('question_options')
            ->where('question_id', $question->id)
            ->orderBy('option_order')
            ->get()
            ->map(function ($option) {
                return [
                    'id' => (string) $option->id,
                    'text' => $option->option_text,
                ];
            })
            ->values()
            ->all();

        if ($question->question_type === 'scale' && Schema::hasTable('scale_types')) {
            $scale = DB::table('scale_types')->where('id', $question->scale_type_id)->first();
            $options = $scale ? ScaleScoringService::buildScaleOptions($scale) : [];
            $options = collect($options)->map(function ($option) {
                return [
                    'id' => (string) ($option['id'] ?? ''),
                    'text' => (string) ($option['text'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                    'value' => $option['value'] ?? null,
                ];
            })->values()->all();
        }

        return [
            'id' => (int) $question->id,
            'question_category_id' => (int) $question->question_category_id,
            'question_type' => $question->question_type,
            'question_text' => $question->question_text,
            'question_image' => json_decode($question->question_image ?: '[]', true) ?: [],
            'options' => $options,
        ];
    }

    private function flattenSupportingQuestions(): array
    {
        $items = [];
        foreach ($this->supportingQuestionCategories() as $category) {
            foreach ($category['questions'] as $question) {
                $items[(string) $question['id']] = array_merge($question, [
                    'category_id' => $category['id'],
                    'category_name' => $category['name'],
                ]);
            }
        }

        return $items;
    }

    private function validateSupportingAnswers($answers): array
    {
        $questions = $this->flattenSupportingQuestions();
        if (empty($questions)) {
            return [];
        }

        $answers = is_array($answers) ? $answers : [];
        $errors = [];

        foreach ($questions as $questionId => $question) {
            $rawAnswer = $answers[$questionId] ?? $answers[(int) $questionId] ?? null;
            if (!$this->hasSupportingAnswerValue($rawAnswer)) {
                $errors['supporting_answers.' . $questionId] = ['Pertanyaan wajib dijawab: ' . ($question['question_text'] ?? 'Soal')];
                continue;
            }

            if (!$this->isValidSupportingAnswer($question, $rawAnswer)) {
                $errors['supporting_answers.' . $questionId] = ['Jawaban tidak valid untuk pertanyaan: ' . ($question['question_text'] ?? 'Soal')];
            }
        }

        return $errors;
    }

    private function hasSupportingAnswerValue($value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, function ($item) {
                return trim((string) $item) !== '';
            })) > 0;
        }

        return trim((string) $value) !== '';
    }

    private function isValidSupportingAnswer(array $question, $rawAnswer): bool
    {
        $type = $question['question_type'] ?? 'single_choice';
        $options = collect($question['options'] ?? []);
        $allowedIds = $options->pluck('id')->map(function ($id) {
            return (string) $id;
        })->all();

        if ($type === 'multiple_choice') {
            $selected = array_map('strval', (array) $rawAnswer);
            if (empty($selected)) {
                return false;
            }

            foreach ($selected as $item) {
                if (!in_array($item, $allowedIds, true)) {
                    return false;
                }
            }

            return true;
        }

        $selected = trim((string) $rawAnswer);
        return $selected !== '' && in_array($selected, $allowedIds, true);
    }

    private function resolveSupportingAnswerLabel(array $question, $rawAnswer): string
    {
        $options = collect($question['options'] ?? [])->keyBy(function ($option) {
            return (string) ($option['id'] ?? '');
        });

        if (($question['question_type'] ?? '') === 'multiple_choice') {
            return collect((array) $rawAnswer)
                ->map(function ($item) use ($options) {
                    $option = $options->get((string) $item);
                    return $option['text'] ?? (string) $item;
                })
                ->filter(function ($label) {
                    return trim((string) $label) !== '';
                })
                ->implode(', ');
        }

        $option = $options->get((string) $rawAnswer);
        return $option['text'] ?? trim((string) $rawAnswer);
    }

    private function storeSupportingAnswers($answers, $profileId, $recruitmentId, $now): void
    {
        if (!Schema::hasTable('candidate_supporting_info_answers')) {
            return;
        }

        $questions = $this->flattenSupportingQuestions();
        if (empty($questions)) {
            return;
        }

        $answers = is_array($answers) ? $answers : [];

        foreach ($questions as $questionId => $question) {
            $rawAnswer = $answers[$questionId] ?? $answers[(int) $questionId] ?? null;
            if (!$this->hasSupportingAnswerValue($rawAnswer)) {
                continue;
            }

            $normalizedAnswer = ($question['question_type'] ?? '') === 'multiple_choice'
                ? array_values(array_map('strval', (array) $rawAnswer))
                : trim((string) $rawAnswer);

            DB::table('candidate_supporting_info_answers')->insert([
                'candidate_profile_id' => $profileId,
                'new_recruitment_id' => $recruitmentId,
                'question_category_id' => $question['category_id'] ?? $question['question_category_id'] ?? null,
                'question_id' => (int) $questionId,
                'category_name' => $question['category_name'] ?? null,
                'question_text' => $question['question_text'] ?? null,
                'question_type' => $question['question_type'] ?? null,
                'answer_text' => $this->resolveSupportingAnswerLabel($question, $rawAnswer),
                'answer_payload' => json_encode([
                    'raw' => $normalizedAnswer,
                    'question_type' => $question['question_type'] ?? null,
                ]),
                'is_active' => 1,
                'created_by' => 'Candidate Profile Portal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
