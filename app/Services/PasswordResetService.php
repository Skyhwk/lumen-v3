<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Services\SendEmail;

class PasswordResetService
{
    /** @var AuthTokenService */
    private $authTokenService;

    public function __construct(AuthTokenService $authTokenService)
    {
        $this->authTokenService = $authTokenService;
    }

    /**
     * Kirim OTP reset password ke email perusahaan (master_karyawan.email).
     *
     * @return array{success: bool, message: string}
     */
    public function sendOtp($email, $ipAddress = null)
    {
        $email = strtolower(trim((string) $email));
        $genericMessage = 'Jika email terdaftar, kode OTP telah dikirim ke email perusahaan Anda.';

        $karyawan = $this->findKaryawanByCompanyEmail($email);
        if (!$karyawan || !$karyawan->user) {
            return [
                'success' => true,
                'message' => $genericMessage,
            ];
        }

        if ($this->isRateLimited($email)) {
            return [
                'success' => false,
                'message' => 'Terlalu banyak permintaan OTP. Silakan coba lagi nanti.',
                'status' => 429,
            ];
        }

        $otp = $this->generateOtp();
        $now = Carbon::now();

        PasswordResetOtp::where('user_id', $karyawan->user_id)
            ->where('is_used', false)
            ->delete();

        PasswordResetOtp::create([
            'user_id' => $karyawan->user_id,
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'is_used' => false,
            'expires_at' => $now->copy()->addMinutes($this->getOtpTtlMinutes())->toDateTimeString(),
            'created_at' => $now->toDateTimeString(),
            'ip_address' => $ipAddress,
        ]);

        $this->sendOtpEmail($email, $otp, $karyawan->nama_lengkap);

        return [
            'success' => true,
            'message' => $genericMessage,
        ];
    }

    /**
     * Verifikasi OTP tanpa mengubah password (step forgot password modern).
     *
     * @return array{success: bool, message: string, status?: int}
     */
    public function verifyOtp($email, $otp)
    {
        $result = $this->validateOtpAttempt($email, $otp);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'OTP valid. Silakan buat password baru.',
        ];
    }

    /**
     * Reset password dengan OTP, lalu logout semua sesi user.
     *
     * @return array{success: bool, message: string, status?: int}
     */
    public function resetPassword($email, $otp, $newPassword, $confirmPassword)
    {
        $email = strtolower(trim((string) $email));
        $otp = trim((string) $otp);

        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'message' => 'Konfirmasi password tidak cocok.',
                'status' => 400,
            ];
        }

        if (strlen($newPassword) < 6) {
            return [
                'success' => false,
                'message' => 'Password minimal 6 karakter.',
                'status' => 400,
            ];
        }

        $otpResult = $this->validateOtpAttempt($email, $otp);
        if (!$otpResult['success']) {
            return $otpResult;
        }

        $karyawan = $otpResult['karyawan'];
        $otpRecord = $otpResult['otp_record'];

        /** @var User $user */
        $user = $karyawan->user;
        $user->password = Hash::make($newPassword);
        $user->save();

        $otpRecord->is_used = true;
        $otpRecord->used_at = Carbon::now()->toDateTimeString();
        $otpRecord->save();

        PasswordResetOtp::where('user_id', $user->id)
            ->where('id', '!=', $otpRecord->id)
            ->delete();

        $this->authTokenService->expireAllUserTokens($user->id);

        return [
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan login kembali.',
        ];
    }

    /**
     * @return array{success: bool, message: string, status?: int, otp_record?: PasswordResetOtp, karyawan?: MasterKaryawan}
     */
    private function validateOtpAttempt($email, $otp)
    {
        $email = strtolower(trim((string) $email));
        $otp = trim((string) $otp);

        if (!preg_match('/^\d{6}$/', $otp)) {
            return [
                'success' => false,
                'message' => 'OTP harus 6 digit angka.',
                'status' => 400,
            ];
        }

        $karyawan = $this->findKaryawanByCompanyEmail($email);
        if (!$karyawan || !$karyawan->user) {
            return [
                'success' => false,
                'message' => 'OTP tidak valid atau sudah kedaluwarsa.',
                'status' => 400,
            ];
        }

        $otpRecord = PasswordResetOtp::where('email', $email)
            ->where('user_id', $karyawan->user_id)
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            return [
                'success' => false,
                'message' => 'OTP tidak valid atau sudah kedaluwarsa.',
                'status' => 400,
            ];
        }

        if (Carbon::parse($otpRecord->expires_at)->isPast()) {
            $otpRecord->is_used = true;
            $otpRecord->save();

            return [
                'success' => false,
                'message' => 'OTP sudah kedaluwarsa. Silakan minta OTP baru.',
                'status' => 400,
            ];
        }

        if ((int) $otpRecord->attempts >= $this->getOtpMaxAttempts()) {
            return [
                'success' => false,
                'message' => 'OTP terkunci karena terlalu banyak percobaan salah. Silakan minta OTP baru.',
                'status' => 400,
            ];
        }

        if (!Hash::check($otp, $otpRecord->otp_hash)) {
            $otpRecord->attempts = (int) $otpRecord->attempts + 1;
            $otpRecord->save();

            $remaining = max(0, $this->getOtpMaxAttempts() - (int) $otpRecord->attempts);

            return [
                'success' => false,
                'message' => $remaining > 0
                    ? "OTP salah. Sisa percobaan: {$remaining}."
                    : 'OTP terkunci karena terlalu banyak percobaan salah. Silakan minta OTP baru.',
                'status' => 400,
            ];
        }

        return [
            'success' => true,
            'otp_record' => $otpRecord,
            'karyawan' => $karyawan,
        ];
    }

    private function findKaryawanByCompanyEmail($email)
    {
        return MasterKaryawan::with(['user' => function ($query) {
            $query->where('is_active', true);
        }])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function isRateLimited($email)
    {
        $since = Carbon::now()->subMinutes($this->getOtpRateLimitMinutes());

        $count = PasswordResetOtp::where('email', $email)
            ->where('created_at', '>=', $since->toDateTimeString())
            ->count();

        return $count >= $this->getOtpRateLimit();
    }

    private function generateOtp()
    {
        return (string) random_int(100000, 999999);
    }

    private function sendOtpEmail($email, $otp, $recipientName = null)
    {
        $ttlMinutes = $this->getOtpTtlMinutes();
        $greetingName = $recipientName ? htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') : 'Karyawan';

        $body = '
            <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <h2 style="margin-bottom: 8px;">Reset Password INTILAB SUPER APPS</h2>
                <p>Halo ' . $greetingName . ',</p>
                <p>Kami menerima permintaan reset password untuk akun SUPER APPS Anda.</p>
                <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px; margin: 24px 0;">' . $otp . '</p>
                <p>Kode OTP di atas berlaku selama <strong>' . $ttlMinutes . ' menit</strong>.</p>
                <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
                <p style="margin-top: 24px;">Salam,<br>Tim INTILAB</p>
            </div>';

        SendEmail::where('to', $email)
            ->where('subject', 'Kode OTP Reset Password - INTILAB SUPER APPS')
            ->where('body', $body)
            ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
            ->noReply()
            ->send();
    }

    private function getOtpTtlMinutes()
    {
        return (int) config('auth.otp_ttl_minutes', 10);
    }

    private function getOtpMaxAttempts()
    {
        return (int) config('auth.otp_max_attempts', 5);
    }

    private function getOtpRateLimit()
    {
        return (int) config('auth.otp_rate_limit', 3);
    }

    private function getOtpRateLimitMinutes()
    {
        return (int) config('auth.otp_rate_limit_minutes', 15);
    }
}
