<?php

namespace App\Http\Controllers\Greatday;

use App\Models\MasterKaryawan;
use Illuminate\Http\Request;

class MembersController extends Controller
{
    public function getLeaderboard(Request $request)
    {
        $user = $this->karyawan;
        $grade = $user->grade;
        $userId = $user->id;
        $atasanLangsung = $user->atasan_langsung;

        $leaderboard = collect();

        try {
            $baseQuery = MasterKaryawan::with([
                'department:id,nama_divisi',
                'jabatan:id,nama_jabatan',
                'salary:nik_karyawan,gaji_pokok,tunjangan_kerja',
            ])
                ->where('is_active', 1)
                ->select([
                    'id',
                    'image',
                    'nama_lengkap',
                    'agama',
                    'nik_ktp',
                    'status_pernikahan',
                    'no_telpon',
                    'alamat',
                    'grade',
                    'nik_karyawan',
                    'email',
                    'status_karyawan',
                    'tgl_mulai_kerja',
                    'id_department',
                    'id_jabatan',
                    'atasan_langsung',
                ]);

            if (in_array($userId, [1], true)) {
                $leaderboard = $baseQuery->get();
            } elseif ($grade == 'MANAGER') {
                $dirisendiri = clone $baseQuery;
                $leaderboard = $leaderboard->merge($dirisendiri->where('id', $userId)->get());

                $directReports = clone $baseQuery;
                $directReports = $directReports->whereJsonContains('atasan_langsung', (string) $userId)->get();
                $leaderboard = $leaderboard->merge($directReports);

                $supervisorIds = $directReports->where('grade', 'SUPERVISOR')->pluck('id');
                if ($supervisorIds->isNotEmpty()) {
                    foreach ($supervisorIds as $supervisorId) {
                        $indirectReports = clone $baseQuery;
                        $staffData = $indirectReports
                            ->whereJsonContains('atasan_langsung', (string) $supervisorId)
                            ->where('grade', 'STAFF')
                            ->get();
                        $leaderboard = $leaderboard->merge($staffData);
                    }
                }
            } elseif ($grade == 'SUPERVISOR') {
                $manager = clone $baseQuery;
                $atasanLangsungArr = json_decode($atasanLangsung) ?: [];
                $leaderboard = $leaderboard->merge($manager->whereIn('id', $atasanLangsungArr)->get());

                $dirisendiri = clone $baseQuery;
                $leaderboard = $leaderboard->merge($dirisendiri->where('id', $userId)->get());

                $bawahan = clone $baseQuery;
                $leaderboard = $leaderboard->merge(
                    $bawahan
                        ->whereJsonContains('atasan_langsung', (string) $userId)
                        ->where('grade', 'STAFF')
                        ->get()
                );
            } elseif ($grade == 'STAFF') {
                foreach (json_decode($atasanLangsung) ?: [] as $atasanId) {
                    $rekanKerja = clone $baseQuery;
                    $users = $rekanKerja->whereJsonContains('atasan_langsung', (string) $atasanId)->get();
                    $leaderboard = $leaderboard->merge($users);
                }
            }

            $leaderboard = $leaderboard->unique('id')->values()->map(function ($karyawan) use ($grade, $userId) {
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
                    'jabatan' => $karyawan->jabatan ? $karyawan->jabatan->nama_jabatan : null,
                    'email' => $karyawan->email,
                    'status_karyawan' => $karyawan->status_karyawan,
                    'nama_divisi' => $karyawan->department ? $karyawan->department->nama_divisi : null,
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

                if (in_array($userId, [1], true) || $grade == 'MANAGER' || $karyawan->id == $userId) {
                    $data['salary'] = $karyawan->salary
                        ? ($karyawan->salary->gaji_pokok + $karyawan->salary->tunjangan_kerja)
                        : 0;
                } else {
                    $data['salary'] = 0;
                }

                return $data;
            });

            return response()->json(['data' => $leaderboard], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 401);
        }
    }
}
