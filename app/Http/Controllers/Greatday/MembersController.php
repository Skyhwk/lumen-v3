<?php

namespace App\Http\Controllers\Greatday;

use App\Models\MasterKaryawan;
use App\Services\Greatday\MembersHierarchyService;
use App\Support\Greatday\KaryawanPresentation;
use App\Services\Greatday\MembersEmployeeDetailService;
use Illuminate\Http\Request;

class MembersController extends Controller
{
    public function getLeaderboard(Request $request)
    {
        $user = $this->karyawan;
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $grade = $user->grade;
        $userId = (int) $user->id;

        try {
            $hierarchy = app(MembersHierarchyService::class);
            $scopedIds = $hierarchy->stakeholderIds($user);

            if ($scopedIds === []) {
                return response()->json([
                    'data' => [],
                    'tree' => null,
                    'viewer_grade' => $grade,
                ], 200);
            }

            /** Eager load relasi (id_jabatan, id_department) — jangan akses $karyawan->jabatan / ->department langsung */
            $scoped = MasterKaryawan::with(['divisi', 'jabatan', 'salary'])
                ->whereIn('id', $scopedIds)
                ->where('is_active', 1)
                ->orderBy('nama_lengkap')
                ->get();

            $tree = $hierarchy->buildTree($user, $scoped);

            $leaderboard = $scoped->map(function ($karyawan) use ($grade, $userId) {
                $data = [
                    'id' => $karyawan->id,
                    'image' => $karyawan->image,
                    'nama_lengkap' => $karyawan->nama_lengkap,
                    'agama' => $karyawan->agama,
                    'nik_ktp' => $karyawan->nik_ktp,
                    'status_pernikahan' => $karyawan->status_pernikahan,
                    'no_telpon' => $karyawan->no_telpon,
                    'alamat' => $karyawan->alamat,
                    'grade' => $karyawan->grade,
                    'nik_karyawan' => $karyawan->nik_karyawan,
                    'jabatan' => KaryawanPresentation::jabatanLabel($karyawan),
                    'email' => $karyawan->email,
                    'status_karyawan' => $karyawan->status_karyawan,
                    'nama_divisi' => KaryawanPresentation::divisiLabel($karyawan),
                    'tgl_mulai_kerja' => $karyawan->tgl_mulai_kerja,
                ];

                if ($karyawan->atasan_langsung) {
                    $atasanIds = json_decode($karyawan->atasan_langsung);
                    if ($atasanIds) {
                        $data['nama_atasan'] = MasterKaryawan::whereIn('id', $atasanIds)
                            ->pluck('nama_lengkap')
                            ->implode(', ');
                    } else {
                        $data['nama_atasan'] = null;
                    }
                } else {
                    $data['nama_atasan'] = null;
                }

                $canSeeSalary = in_array($userId, [1], true)
                    || in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)
                    || (int) $karyawan->id === $userId;

                $data['salary'] = $canSeeSalary && $karyawan->salary
                    ? ($karyawan->salary->gaji_pokok + $karyawan->salary->tunjangan_kerja)
                    : 0;

                return $data;
            })->values();

            return response()->json([
                'data' => $leaderboard,
                'tree' => $tree,
                'viewer_grade' => $grade,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 401);
        }
    }

    public function getDetail(Request $request)
    {
        $user = $this->karyawan;
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $targetId = (int) $request->input('id');
        if ($targetId <= 0) {
            return response()->json(['message' => 'ID karyawan tidak valid'], 422);
        }

        try {
            $service = app(MembersEmployeeDetailService::class);
            if (!$service->canView($user, $targetId)) {
                return response()->json(['message' => 'Anda tidak dapat melihat profil karyawan ini'], 403);
            }

            $detail = $service->build($user, $targetId);
            if (!$detail) {
                return response()->json(['message' => 'Data karyawan tidak ditemukan'], 404);
            }

            return response()->json(['data' => $detail], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
        }
    }
}
