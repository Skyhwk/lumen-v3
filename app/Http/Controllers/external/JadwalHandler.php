<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Jadwal;
use App\Models\MasterKaryawan;
use App\Models\PerbantuanSampler;


class JadwalHandler extends BaseController
{
    public function getJadwal(Request $request)
    {
        if ($request->mode == 'gps') {
            if ($request->tanggal != null) {
                if ($request->kendaraan != null) {
                    $now = DATE('Y-m-d');
                    $prev = DATE('Y-m-d', \strtotime('-2 months'));
                    $next = DATE('Y-m-d', \strtotime('+2 months'));

                    $data = DB::select("SELECT GROUP_CONCAT(sampler) as sampler, tanggal, kendaraan FROM `jadwal` where kendaraan = ? AND tanggal = ? GROUP BY kendaraan, tanggal", [$request->kendaraan, $request->tanggal]);
                    return response()->json([
                        'data' => $data
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'data not Found'
                    ], 401);
                }
            } else {
                if ($request->kendaraan != null) {
                    $now = DATE('Y-m-d');
                    $prev = DATE('Y-m-d', \strtotime('-2 months'));
                    $next = DATE('Y-m-d', \strtotime('+2 months'));

                    $data = DB::select("SELECT GROUP_CONCAT(sampler) as sampler, tanggal, kendaraan FROM `jadwal` where kendaraan = ? AND tanggal >= ? AND tanggal <= ? GROUP BY kendaraan, tanggal", [$request->kendaraan, $prev, $next]);
                    return response()->json([
                        'data' => $data
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'data not Found'
                    ], 401);
                }
            }
        } else {
           
            $endDate = $request->endDate;
            $startDate = $request->startDate;
            $db1 = explode('-', $request->endDate)[0];
            $db2 = explode('-', $request->startDate)[0];

            $libur = DB::table('jadwal_libur')
                ->where('is_active', 1)
                ->where(function($query) use ($startDate, $endDate) {
                    $query->where(function($q) use ($startDate, $endDate) {
                        $q->whereDate('start_date', '<=', $endDate)
                          ->whereDate('end_date', '>=', $startDate);
                    });
                })
                ->get()

                ->map(function($item) use ($startDate, $endDate) {
                    $dates = [];
                    $current = max(strtotime($item->start_date), strtotime($startDate));
                    $end = min(strtotime($item->end_date), strtotime($endDate));

                    while($current <= $end) {
                        $dates[] = (object)['tanggal_libur' => date('Y-m-d', $current)];
                        $current = strtotime('+1 day', $current);
                    }
                    return $dates;
                })
                ->flatten();

            $warna = array("merah", "biru_tua", "biru_muda", "orange", "peach", "hijau_tua", "hijau_muda", "NULL", "");
            $users = MasterKaryawan::select('id','id_cabang', 'pin_user', 'nama_lengkap', 'warna')->whereIn('id_jabatan', [94,146])->where('is_active', 1)->get()->map(function ($user) {
                $user->is_perbantuan = 0;
                return $user;
            });
            // Query untuk user spesial
            $userMerge = PerbantuanSampler::with(['users' => function($query) {
                    // Sebaiknya select kolom yang dibutuhkan saja di relasi untuk efisiensi
                    $query->select('user_id', 'id_jabatan', 'id_cabang', 'pin_user', 'warna');
                }, 'users.jabatan'])
                ->where('is_active', 1)
                ->get();
                //select('id','id_cabang', 'pin_user', 'nama_lengkap', 'warna')
            $userMerge->transform(function ($karyawan) {
                //$karyawan->nama_display = $karyawan->nama_lengkap . ' (perbantuan)';
                $digitCount = strlen((string)$karyawan->user_id);
                if ($digitCount > 4) {
                    $karyawan->nama_display = $karyawan->nama_lengkap . ' (freelance)';
                } else {
                    $karyawan->nama_display = $karyawan->nama_lengkap . ' (perbantuan)';
                }
                $karyawan->is_perbantuan = 1;
                // $karyawan->nama_lengkap = $karyawan->nama_lengkap . ' (perbantuan)';
                if ($karyawan->users) {
                    // 2. Tarik kolom fisik dari relasi users ke root item
                    $karyawan->id_cabang = $karyawan->users->id_cabang;
                    $karyawan->pin_user  = $karyawan->users->pin_user;
                    $karyawan->warna     = $karyawan->users->warna;

                    // 3. Tarik Objek Jabatan (Mengatasi bentrok nama kolom/relasi)
                    // Gunakan getRelation agar pasti mengambil Objek, bukan string kolom
                    // $jabatanObj = $karyawan->users->getRelation('jabatan');
                    // $karyawan->setRelation('jabatan', $jabatanObj);
                }else{
                    $karyawan->id_cabang = null;
                    $karyawan->pin_user  = null;
                    $karyawan->warna     = null;
                }
                $karyawan->unsetRelation('users');
                return $karyawan;
            });
            // $users->transform(function ($karyawan) {
            //     $karyawan->nama_display = $karyawan->nama_lengkap;
            //     return $karyawan;
            // });
            // Only merge if $userMerge contains data
            if (!$userMerge->isEmpty()) {
                $users = $users->merge($userMerge)->unique('id')->values();
            }

            if ($db1 != $db2) {
                $jadwal = [];
            } else {
                $jadwal = Jadwal::select(
                    'nama_perusahaan', 'tanggal', 'jam', 'kategori', 'sampler',
                    'warna', 'note', 'durasi', 'urutan', 'wilayah', 'status', 'id_cabang'
                )
                ->where('is_active', 1)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->groupBy(
                    'nama_perusahaan', 'tanggal', 'jam', 'kategori', 'sampler',
                    'warna', 'note', 'durasi', 'urutan', 'wilayah', 'status', 'id_cabang'
                )
                ->orderBy('jam', 'ASC');

                if ($request->has('id_cabang') && $request->id_cabang !== null && $request->id_cabang !== 'null') {
                    $jadwal->where('id_cabang', $request->id_cabang);
                }

                $jadwal = $jadwal->get();

                // costume:
                if($request->id_cabang == 4){
                    // $users = $users->where('id_cabang', 4)->values();
                    $usersCabang4 = $users->where('id_cabang', 4);

                // Daftar user tambahan berdasarkan ID meskipun bukan dari cabang 4
                $userTambahanIds = [77]; // ID yang harus ikut meskipun bukan cabang 4

                $userTambahan = $users->whereIn('id', $userTambahanIds);

                // Gabungkan hasil dan hilangkan duplikat berdasarkan 'id'
                $users = $usersCabang4->merge($userTambahan)->unique('id')->values();
                }elseif($request->id_cabang == 5){
                    $users = $users->where('id_cabang', 5)->values();
                }elseif($request->id_cabang == 1){
                    $users = $users->where('id_cabang', 1)->values();
                }
            }
            return response()->json([
                'users' => $users,
                'data' => $jadwal,
                'libur' => $libur,
                'status' => '200'
            ], 200);
        }
    }

    public function getDetailJadwalApi(Request $request)
    {
        $data = DB::table('jadwal as a')
            ->distinct()
            ->select("a.nama_perusahaan", "a.no_quotation", "a.wilayah", "a.alamat", "a.tanggal", "a.jam", "a.jam_mulai", "a.jam_selesai", "a.kategori", "a.sampler", "a.warna", "a.note", "a.durasi", "a.status", "a.is_active", "a.urutan", DB::raw("(GROUP_CONCAT(b.sampler SEPARATOR ',')) as `sampler`"))
            ->join("jadwal as b", function ($join) {

                $join->on('b.nama_perusahaan', '=', 'a.nama_perusahaan');
                $join->on('b.tanggal', '=', 'a.tanggal');
                $join->on('b.no_quotation', '=', 'a.no_quotation');
                $join->on('b.kategori', '=', 'a.kategori');
                $join->on('b.is_active', '=', 'a.is_active');
            })
            ->whereDay('a.tanggal', $request->day)
            ->whereMonth('a.tanggal', $request->month)
            ->whereYear('a.tanggal', $request->tahun)
            ->where('a.sampler', $request->sampler)
            ->where('a.is_active', 1)
            ->groupBy("a.nama_perusahaan", "a.no_quotation", "a.wilayah", "a.alamat", "a.tanggal", "a.jam", "a.jam_mulai", "a.jam_selesai", "a.kategori", "a.sampler", "a.warna", "a.note", "a.durasi", "a.status", "a.is_active", "a.urutan")->get();
        return response()->json([

            'data' => $data,
        ], 200);
    }
}
