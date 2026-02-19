<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Log;
use Carbon\Carbon;
use Datatables;

use App\Models\MasterKaryawan;
use App\Models\DbAccessUser;

class AksesDatabaseController extends Controller
{

    public function index(Request $request)
    {
        try {
            $data = DbAccessUser::with(['karyawan' => function($query) {
                $query->select('id', 'nama_lengkap');
            }])->where('is_active', true);

            return Datatables::of($data)->make(true);

        } catch (\Throwable $e) {
            // Log the error for debugging
            return response()->json([
                'error' => "An error occurred: Trying to access array offset on value of type null"
            ], 500);
        }
    }

    public function getDatabases(Request $request)
    {
        $search = $request->input('search', '');
        $page = (int) $request->input('page', 1);
        $perPage = 10; // You can configure this as needed

        // Ambil list nama database di MySQL selain database bawaan
        $excludedDatabases = ['information_schema', 'mysql', 'performance_schema', 'sys'];
        $query = DB::table('information_schema.schemata')
            ->select('schema_name as database_name')
            ->whereNotIn('schema_name', $excludedDatabases);

        if (!empty($search)) {
            $query->where('schema_name', 'like', '%' . $search . '%');
        }

        $total = $query->count();

        $items = $query
            ->orderBy('schema_name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->get();

        $hasMore = $items->count() > $perPage;

        $results = $items->take($perPage)->map(function ($item) {
            return [
                'database_name' => $item->database_name,
            ];
        })->values();

        return response()->json([
            'data' => $results,
            'pagination' => [
                'more' => $hasMore
            ]
        ]);
    }

    public function getProgrammers(Request $request)
    {
        $search = $request->input('search', '');
        $page = (int) $request->input('page', 1);
        $perPage = 10; // or make this configurable

        $query = MasterKaryawan::where('is_active', true)->where('id_department', 7);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%');
            });
        }

        $total = $query->count();

        $items = $query
            ->orderBy('nama_lengkap')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->get();

        $hasMore = $items->count() > $perPage;

        $results = $items->take($perPage)->map(function ($item) {
            return [
                'id' => $item->id,
                'nama_lengkap' => $item->nama_lengkap,
            ];
        })->values();

        return response()->json([
            'data' => $results,
            'pagination' => [
                'more' => $hasMore
            ]
        ]);
    }

    public function deleteAkses(Request $request)
    {
        try {

            $akses = DbAccessUser::findOrFail($request->id);

            $username = $akses->username_mysql;
            $ip       = $akses->ip_address;

            // 1️⃣ Drop MySQL user
            DB::statement("
                DROP USER IF EXISTS '$username'@'$ip'
            ");

            DB::statement("FLUSH PRIVILEGES");

            // 2️⃣ Soft delete
            $akses->update([
                'is_active' => false,
                'updated_by'=> $this->karyawan ?? 'system',
                'updated_at'=> Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Akses database berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function buildPrivilegeSQL($privileges)
    {
        if (empty($privileges)) {
            return 'SELECT';
        }

        if (is_array($privileges)) {
            return implode(', ', $privileges);
        }

        return $privileges;
    }

    public function createAkses(Request $request)
    {
        try {

            $username   = $request->username;
            $password   = $request->password;
            $ip         = $request->ip_address;
            $database   = $request->database_name;
            $privileges = $this->buildPrivilegeSQL($request->akses);
            
            // 1️⃣ Simpan ke tabel master
            $akses = DbAccessUser::create([
                'id_karyawan' => $request->id_karyawan,
                'username_mysql'=> $username,
                'password_mysql'=> $password,
                'ip_address'    => $ip,
                'database_name' => $database,
                'privileges'    => $privileges,
                'created_by'    => $this->karyawan ?? 'system',
                'is_active'     => true
            ]);

            // 2️⃣ Create MySQL User
            DB::statement("
                CREATE USER '$username'@'$ip'
                IDENTIFIED BY '$password'
            ");

            // 3️⃣ Grant Privilege
            DB::statement("
                GRANT $privileges
                ON `$database`.*
                TO '$username'@'$ip'
            ");

            DB::statement("FLUSH PRIVILEGES");

            return response()->json([
                'success' => true,
                'message' => 'Akses database berhasil dibuat'
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAkses(Request $request)
    {
        try {

            $akses = DbAccessUser::findOrFail($request->id);

            $oldUsername = $akses->username_mysql;
            $oldPassword = $akses->password_mysql;
            $oldIp       = $akses->ip_address;

            $newUsername = $request->username;
            $newPassword = $request->password;
            $newIp       = $request->ip_address;
            $database    = $request->database_name;
            $privileges  = $this->buildPrivilegeSQL($request->akses);

            // 1️⃣ Drop user lama
            DB::statement("
                DROP USER IF EXISTS '$oldUsername'@'$oldIp'
            ");

            // 2️⃣ Create user baru
            DB::statement("
                CREATE USER '$newUsername'@'$newIp'
                IDENTIFIED BY '$newPassword'
            ");

            // 3️⃣ Grant privilege baru
            DB::statement("
                GRANT $privileges
                ON `$database`.*
                TO '$newUsername'@'$newIp'
            ");

            DB::statement("FLUSH PRIVILEGES");

            // 4️⃣ Update tabel master
            $akses->update([
                'id_karyawan' => $request->id_karyawan,
                'username_mysql'=> $newUsername,
                'password_mysql'=> $newPassword,
                'ip_address'    => $newIp,
                'database_name' => $database,
                'privileges'    => $privileges,
                'updated_by'    => $this->karyawan ?? 'system',
                'updated_at'    => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Akses database berhasil diupdate'
            ], 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}