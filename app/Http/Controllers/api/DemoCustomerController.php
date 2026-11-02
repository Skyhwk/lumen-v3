<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterPelanggan;
use App\Models\ppi\Menus;
use App\Models\ppi\TeamMembers;
use App\Models\ppi\Teams;
use App\Models\ppi\UserPermissions;
use App\Models\ppi\Users;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoCustomerController extends Controller
{
    const TRIAL_PELANGGAN_ID = 'ESTX01';
    const CONNECTION = 'portal_pelanggan';

    public function index()
    {
        $query = Users::where('role_id', 2);

        if (Schema::connection(self::CONNECTION)->hasColumn('users', 'is_trial')) {
            $query->where('is_trial', 1);
        } else {
            $query->whereRaw('1 = 0');
        }

        $data = $query->orderBy('id', 'desc')->get();
        $this->mapPelanggan($data);

        return response()->json([
            'message' => 'Data demo customer berhasil ditampilkan',
            'data' => $data,
            'status' => 200,
            'success' => true
        ], 200);
    }

    public function checkEmail(Request $request)
    {
        $email = trim((string) $request->email);
        if ($email === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Email wajib diisi',
                'status' => 200,
                'success' => true
            ], 200);
        }

        $exists = Users::where('email', $email)
            ->when($request->id, fn ($q) => $q->where('id', '!=', $request->id))
            ->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Email telah terdaftar' : 'Email tersedia',
            'status' => 200,
            'success' => true
        ], 200);
    }

    public function getTrialCustomer()
    {
        $pelanggan = $this->resolveTrialPelanggan();

        if (!$pelanggan) {
            return response()->json([
                'message' => 'Pelanggan trial ESTX01 tidak ditemukan',
                'data' => [],
                'status' => 404,
                'success' => false
            ], 404);
        }

        return response()->json([
            'message' => 'Customer data displayed successfully',
            'data' => [$pelanggan],
            'status' => 200,
            'success' => true
        ], 200);
    }

    public function store(Request $request)
    {
        $portal = DB::connection(self::CONNECTION);
        $portal->beginTransaction();
        try {
            $pelanggan = $this->resolveTrialPelanggan();
            if (!$pelanggan) {
                $portal->rollBack();
                return response()->json([
                    'message' => 'Pelanggan trial ESTX01 tidak ditemukan',
                    'status' => 422,
                    'success' => false
                ], 422);
            }

            $email = trim((string) $request->email);
            $existing = Users::where('email', $email)
                ->when($request->id, fn ($q) => $q->where('id', '!=', $request->id))
                ->first();

            if ($existing) {
                $portal->rollBack();
                return response()->json([
                    'message' => 'Email telah terdaftar',
                    'status' => 400,
                    'success' => false
                ], 400);
            }

            $password = trim((string) $request->password);
            if (!$request->id && $password === '') {
                $portal->rollBack();
                return response()->json([
                    'message' => 'Password wajib diisi',
                    'status' => 422,
                    'success' => false
                ], 422);
            }

            if ($password !== '' && mb_strlen($password) < 8) {
                $portal->rollBack();
                return response()->json([
                    'message' => 'Password minimal 8 karakter',
                    'status' => 422,
                    'success' => false
                ], 422);
            }

            if (!$request->id) {
                $emailName = $this->uniqueUsername(explode('@', $email)[0]);

                $user = new Users();
                $user->nama_lengkap = $request->nama_lengkap;
                $user->username = $emailName;
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->id_pelanggan = json_encode([$pelanggan->id_pelanggan]);
                $user->created_by = $this->karyawan;
                $user->created_at = Carbon::now();
                $user->role_id = 2;
                $user->is_active = 1;
                $this->applyTrialFlags($user);
                $user->save();

                $this->seedUserPermissions($user);
                $this->seedUserTeam($user, $emailName);
            } else {
                $user = Users::where('id', $request->id)->where('is_trial', 1)->first();
                if (!$user) {
                    $portal->rollBack();
                    return response()->json([
                        'message' => 'Demo customer tidak ditemukan',
                        'status' => 404,
                        'success' => false
                    ], 404);
                }

                $user->nama_lengkap = $request->nama_lengkap;
                $user->email = $email;
                $user->id_pelanggan = json_encode([$pelanggan->id_pelanggan]);
                $user->updated_by = $this->karyawan;
                $user->updated_at = Carbon::now();
                $this->applyTrialFlags($user);

                if ($password !== '') {
                    $user->password = Hash::make($password);
                }

                $user->save();
            }

            $portal->commit();

            return response()->json([
                'message' => 'Data demo customer berhasil disimpan',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (\Exception $ex) {
            $portal->rollBack();
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'status' => 500,
                'success' => false
            ], 500);
        }
    }

    private function uniqueUsername($base)
    {
        $username = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $base) ?: 'demo';
        $candidate = $username;
        $i = 1;

        while (Users::where('username', $candidate)->where('is_active', 1)->exists()) {
            $candidate = $username . $i;
            $i++;
        }

        return $candidate;
    }

    private function mapPelanggan($data)
    {
        $allPelangganIds = $data->pluck('id_pelanggan')
            ->filter()
            ->flatMap(function ($item) {
                return json_decode($item, true) ?? [];
            })
            ->unique()
            ->values();

        $pelanggan = MasterPelanggan::whereIn('id_pelanggan', $allPelangganIds)
            ->get()
            ->keyBy('id_pelanggan');

        $data->transform(function ($item) use ($pelanggan) {
            $ids = json_decode($item->id_pelanggan, true) ?? [];
            $item->pelanggan = collect($ids)
                ->map(fn ($id) => $pelanggan[$id] ?? null)
                ->filter()
                ->values();

            return $item;
        });
    }

    private function resolveTrialPelanggan()
    {
        return MasterPelanggan::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(id_pelanggan) = ?', [self::TRIAL_PELANGGAN_ID])
            ->select('id_pelanggan', 'nama_pelanggan')
            ->first();
    }

    private function applyTrialFlags(Users $user)
    {
        $schema = Schema::connection(self::CONNECTION);
        $now = Carbon::now();

        if ($schema->hasColumn('users', 'is_trial')) {
            $user->is_trial = 1;
        }

        if ($schema->hasColumn('users', 'is_emailed')) {
            $user->is_emailed = 1;
            if ($schema->hasColumn('users', 'emailed_at') && !$user->emailed_at) {
                $user->emailed_at = $now;
            }
            if ($schema->hasColumn('users', 'emailed_by') && !$user->emailed_by) {
                $user->emailed_by = $this->karyawan ?: 'system';
            }
        }

        if ($schema->hasColumn('users', 'is_completed_boarding')) {
            $user->is_completed_boarding = 1;
        }

        if ($schema->hasColumn('users', 'is_cheklist')) {
            $user->is_cheklist = 1;
        }

        if ($schema->hasColumn('users', 'is_generated')) {
            $user->is_generated = 1;
            if ($schema->hasColumn('users', 'generated_at') && !$user->generated_at) {
                $user->generated_at = $now;
            }
            if ($schema->hasColumn('users', 'generated_by') && !$user->generated_by) {
                $user->generated_by = $this->karyawan ?: 'system';
            }
        }
    }

    private function seedUserPermissions(Users $user)
    {
        if (!Schema::connection(self::CONNECTION)->hasTable('user_permissions')) {
            return;
        }

        $permissions = [];
        if (Schema::connection(self::CONNECTION)->hasTable('menus')) {
            foreach (Menus::get() as $item) {
                $parent = explode('-', $item->name);
                $permissions[] = [
                    'name' => $item->name,
                    'access' => true,
                    'parent' => $parent[0] ?? $item->name,
                ];
            }
        }

        $permission = UserPermissions::firstOrNew(['user_id' => $user->id]);
        $permission->permissions = json_encode($permissions);
        $permission->copy_access = true;
        $permission->paste_access = true;
        $permission->upload_access = true;
        $permission->download_access = true;
        $permission->is_active = 1;
        $permission->created_at = $permission->created_at ?: Carbon::now();
        $permission->updated_at = Carbon::now();
        $permission->save();
    }

    private function seedUserTeam(Users $user, $teamName)
    {
        if (!Schema::connection(self::CONNECTION)->hasTable('teams')) {
            return;
        }

        $team = new Teams();
        if (Schema::connection(self::CONNECTION)->hasColumn('teams', 'nama_tim')) {
            $team->nama_tim = $teamName;
        } else {
            $team->team_name = $teamName;
        }
        $team->created_by = $this->karyawan;
        $team->created_at = Carbon::now();
        $team->save();

        if (!Schema::connection(self::CONNECTION)->hasTable('team_members')) {
            return;
        }

        $teamMember = new TeamMembers();
        $teamMember->team_id = $team->id;
        $teamMember->user_id = $user->id;
        $teamMember->is_owner = true;
        $teamMember->created_by = $this->karyawan;
        $teamMember->created_at = Carbon::now();
        $teamMember->save();
    }
}
