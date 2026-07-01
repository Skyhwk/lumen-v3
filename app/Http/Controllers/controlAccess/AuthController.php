<?php

namespace App\Http\Controllers\controlAccess;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Repositories\controlAccess\UserRepository;
use App\Repositories\controlAccess\ResetTokenRepository;
use App\Services\controlAccess\TokenManager;

class AuthController extends Controller
{
    protected $users;
    protected $resets;
    protected $tokens;

    public function __construct(
        UserRepository $users,
        ResetTokenRepository $resets,
        TokenManager $tokens
    ) {
        $this->users = $users;
        $this->resets = $resets;
        $this->tokens = $tokens;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Username dan password wajib diisi'], 400);
        }

        $user = $this->users->findByCredential($request->username);
        if (!$user || !password_verify($request->password, $user['passwordHash'] ?? '')) {
            return response()->json(['message' => 'Username atau password salah'], 401);
        }

        $token = $this->tokens->generateToken($user);

        return response()->json([
            'token' => $token,
            'user' => $this->publicUser($user),
        ]);
    }

    public function me(Request $request)
    {
        $session = $request->attributes->get('controlAccessUser');
        $user = $this->users->find($session['user_id'] ?? '');

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        return response()->json($this->publicUser($user));
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Email wajib diisi'], 400);
        }

        $user = $this->users->findByEmail($request->email);
        if (!$user) {
            return response()->json(['message' => 'Jika email terdaftar, instruksi reset akan dikirim.']);
        }

        $resetToken = $this->resets->create($user['id'], $user['email']);

        return response()->json([
            'message' => 'Token reset dibuat. Gunakan token ini untuk reset password.',
            'resetToken' => $resetToken,
            'expiresIn' => '1 jam',
            '_devNote' => 'Di production, kirim token via email. Sementara token dikembalikan di response.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'newPassword' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Token dan password baru (min 6 karakter) wajib'], 400);
        }

        $resetEntry = $this->resets->findValid($request->token);
        if (!$resetEntry) {
            return response()->json(['message' => 'Token tidak valid atau sudah expired'], 400);
        }

        $user = $this->users->find($resetEntry['userId'] ?? '');
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $this->users->updatePassword($user['id'], password_hash($request->newPassword, PASSWORD_BCRYPT));
        $this->resets->delete($request->token);

        return response()->json(['message' => 'Password berhasil diubah. Silakan login.']);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required',
            'newPassword' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Password lama dan password baru wajib diisi'], 400);
        }

        $session = $request->attributes->get('controlAccessUser');
        $user = $this->users->find($session['user_id'] ?? '');

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if (!password_verify($request->currentPassword, $user['passwordHash'] ?? '')) {
            return response()->json(['message' => 'Password lama salah'], 401);
        }

        $this->users->updatePassword($user['id'], password_hash($request->newPassword, PASSWORD_BCRYPT));

        return response()->json(['message' => 'Password berhasil diubah']);
    }

    protected function publicUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }
}
