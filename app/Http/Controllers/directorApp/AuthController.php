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

class AuthController extends Controller
{
    protected $users, $tokens, $menus, $permissions;

    public function __construct(UserRepository $users, TokenManager $tokens, Menu $menus, Permission $permissions)
    {
        $this->users = $users;
        $this->tokens = $tokens;
        $this->menus = $menus;
        $this->permissions = $permissions;
    }

    public function login(Request $request)
    {
        try {
            //code...
            $validator = Validator::make($request->all(), [
                'credential' => 'required',
                'password' => 'required'
            ]);
    
            if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);
    
            if (str_contains($request->credential, '@')) {
                $user = $this->users->findByEmail($request->credential);
            } else {
                $user = $this->users->findByUsername($request->credential);
            }
    
            if (!$user) return response()->json(['message' => 'User not found'], 404);
    
            if (Hash::check($request->password, $user['password'])) {
                return response()->json([
                    'message' => 'Logged in successfully',
                    'data' => [
                        'user' => collect($user)->except(['password']),
                        'token' => $this->tokens->generateToken($user),
                        'menus' => collect($this->menus->getAllGrantedMenus($user['id']))->values()->toArray(),
                        'permissions' => $this->permissions->getPermissionsByUserId($user['id']),
                    ],
                ], 200);
            }
    
            return response()->json(['message' => 'Invalid password'], 402);
        } catch (\Throwable $th) {
            //throw $th;
            dd($th);
        }
    }

    public function logout(Request $request)
    {
        $this->tokens->invalidateToken($request->bearerToken());

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
