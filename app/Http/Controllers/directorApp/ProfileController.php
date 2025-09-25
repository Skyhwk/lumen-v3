<?php

namespace App\Http\Controllers\directorApp;

use Laravel\Lumen\Routing\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

use App\Repositories\directorApp\UserRepository;
use App\Services\directorApp\TokenManager;
use App\Services\directorApp\Menu;
use App\Services\directorApp\Permission;

use Carbon\Carbon;

Carbon::setLocale('id');

class ProfileController extends Controller
{
    protected $users, $tokens, $menus, $permissions;

    public function __construct(UserRepository $users, TokenManager $tokens, Menu $menus, Permission $permissions)
    {
        $this->users = $users;
        $this->tokens = $tokens;
        $this->menus = $menus;
        $this->permissions = $permissions;
    }

    public function myProfile(Request $request)
    {
        $token = $this->tokens->getUserByToken($request->bearerToken());
        $user = $this->users->findByEmail($token['email']);

        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => [
                'user' => collect($user)->except(['password']),
                // 'token' => $this->tokens->generateToken($user),
                'menus' => collect($this->menus->getAllGrantedMenus($user['id']))->values()->toArray(),
                'permissions' => $this->permissions->getPermissionsByUserId($user['id']),
            ]
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'name' => 'required',
            'username' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $data = $request->only(['name', 'username', 'email', 'phone']);

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $avatarName = time() . '.' . $avatar->getClientOriginalExtension();
            $avatar->move(public_path('directorApp/avatars'), $avatarName);
            $data['avatar'] = $avatarName;
        }

        $updatedUsers = $this->users->update($request->id, $data);
        if ($updatedUsers) return response()->json(['message' => 'Profile updated successfully'], 200);

        return response()->json(['message' => 'Failed to update profile'], 500);
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required',
            'newPassword' => 'required|min:6',
            'confirmPassword' => 'required|min:6|same:newPassword',
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $token = $this->tokens->getUserByToken($request->bearerToken());
        $user = $this->users->findByEmail($token['email']);

        if (Hash::check($request->currentPassword, $user['password'])) {
            $password = Hash::make($request->newPassword);
            $updatedPassword = $this->users->updatePassword($user['id'], $password);
            if ($updatedPassword) {
                return response()->json(['message' => 'Password updated successfully'], 200);
            } else {
                return response()->json(['message' => 'Failed to update password'], 500);
            };
        } else {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }
    }
}
