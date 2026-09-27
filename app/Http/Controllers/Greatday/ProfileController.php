<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Greatday\GreatdayAuthService;
use App\Services\Greatday\SlipGajiPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function changePassword(Request $request)
    {
        if (!$this->karyawan || !$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required',
            'newPassword' => [
                'required',
                'string',
                'min:6',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/',
            ],
        ], [
            'newPassword.regex' => 'The new password must contain at least one lowercase letter, one uppercase letter, and one number.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            $message = $errors['newPassword'][0] ?? $validator->errors()->first();

            return response()->json(['message' => $message], 422);
        }

        $existing = app(GreatdayAuthService::class)->findAccountByKaryawanId($this->user_id);

        if (!$existing) {
            return response()->json(['message' => 'User account not found.'], 404);
        }

        if (!Hash::check($request->currentPassword, $existing->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }

        if (Hash::check($request->newPassword, $existing->password)) {
            return response()->json(['message' => 'The new password cannot be the same as the current password.'], 422);
        }

        $existing->password = Hash::make($request->newPassword);
        $existing->save();

        return response()->json(['message' => 'Password changed successfully.'], 200);
    }

    public function slipGajiPinStatus(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'message' => 'success',
            'has_pin' => SlipGajiPinService::hasPin($this->user_id),
        ], 200);
    }

    public function setSlipGajiPin(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|size:6|regex:/^\d{6}$/',
            'confirm_pin' => 'required|same:pin',
        ], [
            'pin.regex' => 'PIN harus 6 digit angka.',
            'pin.size' => 'PIN harus 6 digit angka.',
            'confirm_pin.same' => 'Konfirmasi PIN tidak cocok.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        SlipGajiPinService::setPin($this->user_id, $request->pin);

        return response()->json(['message' => 'PIN proteksi slip gaji berhasil disimpan.'], 200);
    }

    public function removeSlipGajiPin(Request $request)
    {
        if (!$this->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|size:6|regex:/^\d{6}$/',
        ], [
            'pin.regex' => 'PIN harus 6 digit angka.',
            'pin.size' => 'PIN harus 6 digit angka.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if (!SlipGajiPinService::verifyPin($this->user_id, $request->pin)) {
            return response()->json(['message' => 'PIN proteksi tidak valid.'], 403);
        }

        SlipGajiPinService::removePin($this->user_id);

        return response()->json(['message' => 'PIN proteksi slip gaji berhasil dihapus.'], 200);
    }
}
