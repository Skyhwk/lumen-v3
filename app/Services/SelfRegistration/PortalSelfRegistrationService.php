<?php

namespace App\Services\SelfRegistration;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PortalSelfRegistrationService
{
    const GENERIC_REJECT = 'Email Anda tidak dapat didaftarkan secara mandiri. Hubungi tim Intilab.';

    /** @var StatementPdfService */
    private $pdfService;

    public function __construct(StatementPdfService $pdfService = null)
    {
        $this->pdfService = $pdfService ?: new StatementPdfService();
    }

    public function inspectToken($raw)
    {
        $row = $this->findByRawToken($raw);
        if (!$row) {
            return ['state' => 'invalid', 'message' => 'Tautan tidak valid'];
        }

        if ($row->status === 'used') {
            return ['state' => 'used', 'message' => 'Tautan sudah pernah digunakan'];
        }

        if ($row->status === 'revoked' || $row->status === 'failed') {
            return ['state' => 'invalid', 'message' => 'Tautan tidak valid'];
        }

        if ($this->isExpired($row)) {
            DB::table('self_registration_customer')
                ->where('id', $row->id)
                ->update([
                    'status' => 'expired',
                    'updated_at' => Carbon::now(),
                ]);

            return ['state' => 'expired', 'message' => 'Tautan sudah kadaluarsa'];
        }

        $eval = $this->evaluateEmail($row->email);
        if ($row->status === 'signed') {
            if (!$eval['ok']) {
                $user = $row->user_id
                    ? DB::table($this->portalDb() . '.users')->where('id', $row->user_id)->first()
                    : null;
                $eval = [
                    'ok' => true,
                    'available' => [],
                    'pic' => [
                        'nama_pic' => $user ? (string) ($user->nama_lengkap ?? '') : '',
                        'no_kontak' => $user ? (string) ($user->no_telepon ?? '') : '',
                        'jabatan_pic' => $user ? (string) ($user->jabatan ?? '') : '',
                    ],
                ];
            }

            return [
                'state' => 'signed',
                'message' => null,
                'data' => $this->statementPayload($row, $eval, true),
            ];
        }

        if (!$eval['ok']) {
            return ['state' => 'invalid', 'message' => $eval['message']];
        }

        return [
            'state' => 'pending',
            'message' => null,
            'data' => $this->statementPayload($row, $eval, false),
        ];
    }

    public function completeSignature($raw, $signatureDataUrl, $ip, $userAgent)
    {
        DB::beginTransaction();

        try {
            $row = $this->findByRawToken($raw, true);
            if (!$row) {
                DB::rollBack();
                return $this->error('Tautan tidak valid', 404);
            }

            if ($row->status === 'used') {
                DB::rollBack();
                return $this->error('Tautan sudah pernah digunakan', 410);
            }

            if ($row->status === 'revoked' || $row->status === 'failed') {
                DB::rollBack();
                return $this->error('Tautan tidak valid', 404);
            }

            if ($this->isExpired($row)) {
                DB::table('self_registration_customer')
                    ->where('id', $row->id)
                    ->update([
                        'status' => 'expired',
                        'updated_at' => Carbon::now(),
                    ]);
                DB::commit();
                return $this->error('Tautan sudah kadaluarsa', 410);
            }

            if ($row->status === 'signed' && $row->user_id && $row->pdf_path) {
                $eval = $this->evaluateEmail($row->email);
                if (!$eval['ok']) {
                    $user = DB::table($this->portalDb() . '.users')->where('id', $row->user_id)->first();
                    $eval = [
                        'ok' => true,
                        'available' => [],
                        'pic' => [
                            'nama_pic' => $user ? (string) ($user->nama_lengkap ?? '') : '',
                            'no_kontak' => $user ? (string) ($user->no_telepon ?? '') : '',
                            'jabatan_pic' => $user ? (string) ($user->jabatan ?? '') : '',
                        ],
                    ];
                }
                $payload = $this->statementPayload($row, $eval, true);
                DB::commit();
                return ['ok' => true, 'message' => 'Registrasi sudah diproses', 'data' => $payload];
            }

            $eval = $this->evaluateEmail($row->email);
            if (!$eval['ok']) {
                DB::rollBack();
                return $this->error($eval['message'], 422);
            }

            $binary = $this->decodeSignature($signatureDataUrl);
            if ($binary === null) {
                DB::rollBack();
                return $this->error('Tanda tangan tidak valid. Ulangi tanda tangan Anda.', 422);
            }

            $codes = array_map(function ($c) {
                return $c['id_pelanggan'];
            }, $eval['available']);

            foreach ($eval['available'] as $company) {
                DB::table('master_pelanggan')
                    ->where('id', $company['id'])
                    ->lockForUpdate()
                    ->first();
            }

            $still = $this->filterUnboundCustomers($eval['available'], $row->email);
            if (count($still) === 0) {
                DB::rollBack();
                return $this->error('Perusahaan yang terkait sudah terikat ke akun lain. Hubungi tim Intilab.', 409);
            }
            if (count($still) !== count($eval['available'])) {
                DB::rollBack();
                return $this->error('Sebagian perusahaan sudah terikat ke akun lain. Ajukan ulang pendaftaran.', 409);
            }

            $nomor = $this->isIssuedDocumentNumber($row->nomor_dokumen)
                ? $row->nomor_dokumen
                : $this->nextDocumentNumber();
            $now = Carbon::now();
            $timestamp = $now->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') . ' WIB';

            $companiesWithAlamat = $this->enrichCompaniesAlamat($eval['available']);
            $payloadPdf = [
                'nomor_dokumen' => $nomor,
                'nama_pic' => $eval['pic']['nama_pic'] ?: $row->email,
                'jabatan_pic' => $eval['pic']['jabatan_pic'] ?: '',
                'no_kontak' => $eval['pic']['no_kontak'],
                'email' => $row->email,
                'companies' => $companiesWithAlamat,
                'tanggal_lengkap' => $this->tanggalIndonesia($now->copy()->timezone('Asia/Jakarta')),
                'nama_pelanggan_utama' => $this->utamaLabel($companiesWithAlamat),
                'timestamp_wib' => $timestamp,
                'generated_at' => $now->copy()->timezone('Asia/Jakarta')->format('YmdGi'),
                'ip_address' => (string) $ip,
                'dokumen_hash' => 'pending',
            ];

            $stored = $this->pdfService->storeFromPayload($row->id, $binary, $payloadPdf);
            $userId = $this->upsertPendingUser($row, $eval, $codes);

            DB::table('self_registration_customer')
                ->where('id', $row->id)
                ->update([
                    'user_id' => $userId,
                    'id_pelanggan' => json_encode($codes),
                    'nomor_dokumen' => $nomor,
                    'dokumen_hash' => $stored['dokumen_hash'],
                    'signature_path' => $stored['signature_path'],
                    'pdf_path' => $stored['pdf_path'],
                    'status' => 'signed',
                    'signed_at' => $now,
                    'ip_address' => (string) $ip,
                    'user_agent' => substr((string) $userAgent, 0, 500),
                    'updated_at' => $now,
                ]);

            $updatedRow = DB::table('self_registration_customer')->where('id', $row->id)->first();
            DB::commit();

            return [
                'ok' => true,
                'message' => 'Surat pernyataan tersimpan',
                'data' => $this->statementPayload($updatedRow, $eval, true),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('portal_self_registration_sign_failed', ['message' => $e->getMessage()]);
            return $this->error('Proses gagal. Silakan coba lagi.', 500);
        }
    }

    public function activate($raw, $password, $passwordConfirmation)
    {
        if ($password !== $passwordConfirmation) {
            return $this->error('Konfirmasi password tidak sama', 422);
        }

        DB::beginTransaction();

        try {
            $row = $this->findByRawToken($raw, true);
            if (!$row) {
                DB::rollBack();
                return $this->error('Tautan tidak valid', 404);
            }
            if ($row->status === 'used') {
                DB::rollBack();
                return $this->error('Tautan sudah pernah digunakan', 410);
            }
            if ($row->status !== 'signed' || !$row->user_id) {
                DB::rollBack();
                return $this->error('Selesaikan tanda tangan terlebih dahulu', 422);
            }
            if ($this->isExpired($row)) {
                DB::rollBack();
                return $this->error('Tautan sudah kadaluarsa', 410);
            }

            $portalDb = $this->portalDb();
            $user = DB::table($portalDb . '.users')
                ->where('id', $row->user_id)
                ->lockForUpdate()
                ->first();
            if (!$user) {
                DB::rollBack();
                return $this->error('Akun pending tidak ditemukan', 404);
            }

            $errors = PasswordRules::validate($password, $row->email, $user->nama_lengkap ?? '');
            if (count($errors) > 0) {
                DB::rollBack();
                return $this->error(implode('. ', $errors), 422);
            }

            $now = Carbon::now();
            DB::table($portalDb . '.users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make($password),
                    'is_active' => 1,
                    'is_completed_boarding' => 0,
                    'updated_at' => $now,
                ]);

            DB::table('self_registration_customer')
                ->where('id', $row->id)
                ->update([
                    'status' => 'used',
                    'used_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::commit();

            return [
                'ok' => true,
                'message' => 'Akun berhasil diaktifkan. Silakan masuk.',
                'email' => $row->email,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('portal_self_registration_activate_failed', ['message' => $e->getMessage()]);
            return $this->error('Aktivasi gagal. Silakan coba lagi.', 500);
        }
    }

    public function pdfBlobForToken($raw)
    {
        $row = $this->findByRawToken($raw);
        if (!$row || !$row->pdf_path) {
            return null;
        }

        $binary = $this->pdfService->readPdf($row->pdf_path);
        if ($binary === null) {
            return null;
        }

        return [
            'filename' => 'surat-pernyataan-portal-intilab.pdf',
            'mime' => 'application/pdf',
            'binary' => $binary,
        ];
    }

    private function evaluateEmail($email)
    {
        $email = $this->normalizeEmail($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Format email tidak valid.', 'available' => null, 'pic' => null];
        }

        $active = DB::table($this->portalDb() . '.users')
            ->where('email', $email)
            ->where('is_active', 1)
            ->first(['id']);
        if ($active) {
            return ['ok' => false, 'message' => self::GENERIC_REJECT, 'available' => null, 'pic' => null];
        }

        $matched = $this->matchActivePicCustomers($email);
        if (count($matched['customers']) === 0) {
            return ['ok' => false, 'message' => self::GENERIC_REJECT, 'available' => null, 'pic' => null];
        }

        $available = $this->filterUnboundCustomers($matched['customers'], $email);
        if (count($available) === 0) {
            return ['ok' => false, 'message' => self::GENERIC_REJECT, 'available' => null, 'pic' => null];
        }

        return [
            'ok' => true,
            'message' => null,
            'available' => $available,
            'pic' => $matched['pic'],
        ];
    }

    private function matchActivePicCustomers($email)
    {
        $email = $this->normalizeEmail($email);
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $email) . '%';

        $pics = DB::table('pic_pelanggan')
            ->where('is_active', 1)
            ->where(function ($q) use ($email, $like) {
                $q->whereRaw('LOWER(TRIM(email_pic)) = ?', [$email])
                    ->orWhereRaw('LOWER(email_pic) LIKE ?', [$like]);
            })
            ->get(['pelanggan_id', 'email_pic', 'nama_pic', 'no_tlp_pic', 'wa_pic', 'jabatan_pic']);

        $matchedPelangganIds = [];
        $matchedPics = [];
        foreach ($pics as $pic) {
            if (!$this->emailFieldMatches($pic->email_pic ?? '', $email)) {
                continue;
            }
            $matchedPics[] = $pic;
            $matchedPelangganIds[] = (int) $pic->pelanggan_id;
        }

        if (empty($matchedPelangganIds)) {
            return [
                'customers' => [],
                'pic' => ['nama_pic' => '', 'no_kontak' => '', 'jabatan_pic' => ''],
            ];
        }

        $masters = DB::table('master_pelanggan')
            ->whereIn('id', array_values(array_unique($matchedPelangganIds)))
            ->where('is_active', 1)
            ->get(['id', 'id_pelanggan', 'nama_pelanggan'])
            ->keyBy('id');

        $customers = [];
        $picIdentity = null;
        foreach ($matchedPics as $pic) {
            $master = $masters->get((int) $pic->pelanggan_id);
            if (!$master) {
                continue;
            }

            $code = trim((string) $master->id_pelanggan);
            if ($code === '') {
                continue;
            }

            $customers[$code] = [
                'id' => (int) $master->id,
                'id_pelanggan' => $code,
                'nama_pelanggan' => (string) $master->nama_pelanggan,
            ];

            if ($picIdentity === null) {
                $picIdentity = [
                    'nama_pic' => trim((string) ($pic->nama_pic ?? '')),
                    'no_kontak' => trim((string) (($pic->no_tlp_pic ?: $pic->wa_pic) ?? '')),
                    'jabatan_pic' => trim((string) ($pic->jabatan_pic ?? '')),
                ];
            }
        }

        return [
            'customers' => array_values($customers),
            'pic' => $picIdentity ?: ['nama_pic' => '', 'no_kontak' => '', 'jabatan_pic' => ''],
        ];
    }

    private function filterUnboundCustomers(array $customers, $email)
    {
        $occupied = $this->occupiedCustomerCodes($email);
        $available = [];
        foreach ($customers as $row) {
            $code = $row['id_pelanggan'];
            if (!isset($occupied[$code])) {
                $available[] = $row;
            }
        }

        return $available;
    }

    private function occupiedCustomerCodes($exceptEmail = null)
    {
        $occupied = [];
        $exceptEmail = $exceptEmail ? $this->normalizeEmail($exceptEmail) : null;

        $users = DB::table($this->portalDb() . '.users')
            ->where('is_active', 1)
            ->get(['email', 'id_pelanggan']);

        foreach ($users as $user) {
            if ($exceptEmail && $this->normalizeEmail($user->email ?? '') === $exceptEmail) {
                continue;
            }
            foreach ($this->decodeIds($user->id_pelanggan) as $code) {
                $occupied[$code] = true;
            }
        }

        $busy = DB::table('self_registration_customer')
            ->where('status', 'signed')
            ->when($exceptEmail, function ($q) use ($exceptEmail) {
                $q->where('email', '!=', $exceptEmail);
            })
            ->get(['id_pelanggan']);

        foreach ($busy as $row) {
            foreach ($this->decodeIds($row->id_pelanggan) as $code) {
                $occupied[$code] = true;
            }
        }

        return $occupied;
    }

    private function upsertPendingUser($row, array $eval, array $codes)
    {
        $portalDb = $this->portalDb();
        $now = Carbon::now();
        $user = null;

        if (!empty($row->user_id)) {
            $user = DB::table($portalDb . '.users')
                ->where('id', $row->user_id)
                ->lockForUpdate()
                ->first();
        }
        if (!$user) {
            $user = DB::table($portalDb . '.users')
                ->where('email', $row->email)
                ->where('is_active', 0)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();
        }

        if ($user) {
            $username = $user->username ?: $row->email;
            $taken = DB::table($portalDb . '.users')
                ->where('username', $username)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($taken) {
                $username = $row->email;
            }

            DB::table($portalDb . '.users')
                ->where('id', $user->id)
                ->update([
                    'username' => $username,
                    'nama_lengkap' => $eval['pic']['nama_pic'] ?: ($user->nama_lengkap ?? ''),
                    'no_telepon' => $eval['pic']['no_kontak'] ?: ($user->no_telepon ?? ''),
                    'jabatan' => $eval['pic']['jabatan_pic'] ?: ($user->jabatan ?? ''),
                    'id_pelanggan' => json_encode($codes),
                    'updated_at' => $now,
                ]);
            $userId = (int) $user->id;
        } else {
            $userId = (int) DB::table($portalDb . '.users')->insertGetId([
                'email' => $row->email,
                'username' => $row->email,
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'role_id' => 2,
                'is_active' => 0,
                'is_completed_boarding' => 0,
                'nama_lengkap' => $eval['pic']['nama_pic'] ?: '',
                'no_telepon' => $eval['pic']['no_kontak'] ?: '',
                'jabatan' => $eval['pic']['jabatan_pic'] ?: '',
                'id_pelanggan' => json_encode($codes),
                'created_by' => 'self-registration',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->seedPermissionAndTeam($userId, $row->email);

        return $userId;
    }

    private function seedPermissionAndTeam($userId, $email)
    {
        $portalDb = $this->portalDb();
        $now = Carbon::now();

        $hasPerm = DB::table($portalDb . '.user_permissions')
            ->where('user_id', $userId)
            ->exists();

        if (!$hasPerm) {
            $menus = DB::table($portalDb . '.menus')->get(['name']);
            $permissions = [];
            foreach ($menus as $item) {
                $parent = explode('-', (string) $item->name);
                $permissions[] = [
                    'name' => $item->name,
                    'access' => true,
                    'parent' => $parent[0] ?? $item->name,
                ];
            }

            DB::table($portalDb . '.user_permissions')->insert([
                'user_id' => $userId,
                'permissions' => json_encode($permissions),
                'copy_access' => 1,
                'paste_access' => 1,
                'upload_access' => 1,
                'download_access' => 1,
                'is_active' => 1,
                'created_at' => $now,
            ]);
        }

        $hasTeam = DB::table($portalDb . '.team_members')
            ->where('user_id', $userId)
            ->exists();

        if ($hasTeam) {
            return;
        }

        $teamId = DB::table($portalDb . '.teams')->insertGetId([
            'team_name' => $email,
            'created_by' => 'self-registration',
            'created_at' => $now,
        ]);

        DB::table($portalDb . '.team_members')->insert([
            'team_id' => $teamId,
            'user_id' => $userId,
            'is_owner' => 1,
            'created_by' => 'self-registration',
            'created_at' => $now,
        ]);
    }

    private function statementPayload($row, array $eval, $withFiles)
    {
        $companies = $eval['available'] ?: [];
        if ($row->status === 'signed' || $row->status === 'used') {
            $stored = $this->decodeIds($row->id_pelanggan);
            if (count($stored) > 0) {
                $masters = DB::table('master_pelanggan')
                    ->whereIn('id_pelanggan', $stored)
                    ->get(['id', 'id_pelanggan', 'nama_pelanggan'])
                    ->keyBy('id_pelanggan');

                $companies = [];
                foreach ($stored as $code) {
                    $master = $masters->get($code);
                    $companies[] = [
                        'id' => $master ? (int) $master->id : 0,
                        'id_pelanggan' => $code,
                        'nama_pelanggan' => $master ? (string) $master->nama_pelanggan : $code,
                    ];
                }
            }
        }

        $enriched = $this->enrichCompaniesAlamat($companies);
        $generated = !empty($row->signed_at)
            ? Carbon::parse($row->signed_at)->timezone('Asia/Jakarta')
            : Carbon::now()->timezone('Asia/Jakarta');

        return [
            'email' => $row->email,
            'nama_pic' => $eval['pic']['nama_pic'] ?: '',
            'no_kontak' => $eval['pic']['no_kontak'] ?: '',
            'jabatan_pic' => $eval['pic']['jabatan_pic'] ?: '',
            'companies' => $enriched,
            'nomor_dokumen' => $this->isIssuedDocumentNumber($row->nomor_dokumen ?? '')
                ? $row->nomor_dokumen
                : $this->previewDocumentNumber(),
            'nama_pelanggan_utama' => $this->utamaLabel($enriched),
            'tanggal_lengkap' => $this->tanggalIndonesia($generated),
            'generated_at' => $generated->format('YmdGi'),
            'has_pdf' => $withFiles && !empty($row->pdf_path),
            'status' => $row->status,
        ];
    }

    private function enrichCompaniesAlamat(array $companies)
    {
        $ids = array_values(array_unique(array_filter(array_map(function ($c) {
            return (int) ($c['id'] ?? 0);
        }, $companies))));

        $alamatMap = [];
        if (!empty($ids)) {
            $rows = DB::table('alamat_pelanggan')
                ->whereIn('pelanggan_id', $ids)
                ->where('is_active', 1)
                ->orderBy('id', 'asc')
                ->get(['pelanggan_id', 'alamat']);

            foreach ($rows as $r) {
                $pid = (int) $r->pelanggan_id;
                if (!isset($alamatMap[$pid])) {
                    $alamatMap[$pid] = (string) ($r->alamat ?? '');
                }
            }
        }

        $enriched = [];
        foreach ($companies as $company) {
            $pid = (int) ($company['id'] ?? 0);
            $company['alamat'] = $alamatMap[$pid] ?? '';
            $enriched[] = $company;
        }

        return $enriched;
    }

    private function documentYearPrefix()
    {
        return 'ISL/SPNY/' . Carbon::now()->timezone('Asia/Jakarta')->format('y') . '-';
    }

    private function romanMonth($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        return $map[(int) $month] ?? 'I';
    }

    private function documentCurrentPrefix()
    {
        $now = Carbon::now()->timezone('Asia/Jakarta');

        return 'ISL/SPNY/' . $now->format('y') . '-' . $this->romanMonth((int) $now->format('n')) . '/';
    }

    private function isIssuedDocumentNumber($nomor)
    {
        return (bool) preg_match('#^ISL/SPNY/\d{2}-(I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII)/\d{4}$#', (string) $nomor);
    }

    private function formatDocumentNumber($seq)
    {
        return $this->documentCurrentPrefix() . str_pad((string) max(1, (int) $seq), 4, '0', STR_PAD_LEFT);
    }

    private function lastDocumentSequence($lock = false)
    {
        $prefix = $this->documentYearPrefix();
        $q = DB::table('self_registration_customer')
            ->where('nomor_dokumen', 'like', $prefix . '%')
            ->orderByRaw("CAST(SUBSTRING_INDEX(nomor_dokumen, '/', -1) AS UNSIGNED) DESC");
        if ($lock) {
            $q->lockForUpdate();
        }
        $last = $q->value('nomor_dokumen');
        if ($last && preg_match('#/(\d{4})$#', $last, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function nextDocumentNumber()
    {
        return $this->formatDocumentNumber($this->lastDocumentSequence(true) + 1);
    }

    private function previewDocumentNumber()
    {
        return $this->formatDocumentNumber($this->lastDocumentSequence(false) + 1);
    }

    private function utamaLabel(array $companies)
    {
        if (count($companies) === 1) {
            return $companies[0]['nama_pelanggan'];
        }
        return 'Perusahaan Terlampir';
    }

    private function tanggalIndonesia(Carbon $date)
    {
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        return $date->format('j') . ' ' . $bulan[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    private function findByRawToken($raw, $lock = false)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || strlen($raw) < 32) {
            return null;
        }
        $hash = hash('sha256', $raw);
        $q = DB::table('self_registration_customer')->where('token', $hash);
        if ($lock) {
            $q->lockForUpdate();
        }
        return $q->first();
    }

    private function isExpired($row)
    {
        if ($row->status === 'expired') {
            return true;
        }
        if (empty($row->expired_at)) {
            return false;
        }
        return Carbon::parse($row->expired_at)->lt(Carbon::now());
    }

    private function decodeSignature($dataUrl)
    {
        if (!is_string($dataUrl) || $dataUrl === '') {
            return null;
        }
        if (!preg_match('#^data:image/(png|jpeg);base64,(.+)$#i', $dataUrl, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) < 256) {
            return null;
        }
        return $bin;
    }

    private function emailFieldMatches($field, $email)
    {
        $field = strtolower(trim((string) $field));
        if ($field === '') {
            return false;
        }
        if ($field === $email) {
            return true;
        }
        foreach (preg_split('/[;,]+/', $field) as $part) {
            if (strtolower(trim($part)) === $email) {
                return true;
            }
        }
        return false;
    }

    private function normalizeEmail($email)
    {
        return strtolower(trim((string) $email));
    }

    private function decodeIds($raw)
    {
        if (is_array($raw)) {
            $ids = $raw;
        } else {
            $ids = json_decode((string) $raw, true);
        }
        if (!is_array($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return $out;
    }

    private function portalDb()
    {
        return env('DB_PORTAL_PELANGGAN', 'portal_pelanggan_intilab');
    }

    private function error($message, $status)
    {
        return [
            'ok' => false,
            'message' => $message,
            'status' => $status,
        ];
    }
}
