<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{
    MasterKaryawan,
    MasterCabang,
    MasterDivisi,
    MasterJabatan,
    User,
    MedicalCheckup,
};

use App\Jobs\NonaktifKaryawanJob;
use App\Services\MasterSallaryNikSyncService;
use App\Services\PayrollRecordSyncService;
use App\Helpers\ShioElemenHelper;

class MasterKaryawanController extends Controller
{
    /** Kolom filter select2: cocokkan persis, bukan LIKE (hindari Male ikut match Female). */
    private const KARYAWAN_EXACT_SEARCH_COLUMNS = [
        'department',
        'jenis_kelamin',
        'status_pernikahan',
        'status_karyawan',
        'grade',
    ];

    /** Tidak ditampilkan di menu Master Karyawan (frontend). */
    private const FRONTEND_HIDDEN_KARYAWAN_IDS = [1, 2];

    public function index(Request $request)
    {
        $data = MasterKaryawan::with([
            'medical',
            'user',
            'pengalaman_kerja',
            'pendidikan_karyawan' => function ($query) {
                $query->where('is_active', true);
            },
            'sertifikat_karyawan',
            'kontak_darurat' => function ($query) {
                $query->where('is_active', true);
            },
        ])->where('is_active', true);

        $this->excludeHiddenKaryawanFromFrontend($data);

        if ($request->filled('id_cabang') && $request->id_cabang !== 'all') {
            $data->where('id_cabang', (int) $request->id_cabang);
        }

        return Datatables::of($data)
        ->addColumn('count', function ($row) {
            return $row->count();
        })
        ->addColumn('personal', function ($row) {
            $shioElemen = ShioElemenHelper::resolve($row->tanggal_lahir, $row->shio, $row->elemen);
            return [
                'nama_lengkap' => $row->nama_lengkap,
                'birth_place' => $row->tempat_lahir,
                'shio' => $shioElemen['shio'],
                'gender' => $row->jenis_kelamin,
                'marital_status' => $row->status_pernikahan,
                'marital_date' => $row->tgl_nikah,
                'marital_place' => $row->tempat_nikah,
                'nik_ktp' => $row->nik_ktp,
                'date_birth' => $row->tanggal_lahir,
                'elemen' => $shioElemen['elemen'],
                'nationality' => $row->kebangsaan,
                'religion' => $row->agama,
                'salutation' => $row->nama_panggilan,
                'image' => $row->image,
                'id' => $row->id
            ];
        })->addColumn('contact', function ($row) {
            return [
                'address' => $row->alamat,
                'country' => $row->negara,
                'city' => $row->kota,
                'phone' => $row->no_telpon,
                'province' => $row->provinsi,
                'postal_code' => $row->kode_pos
            ];
        })->addColumn('employee', function ($row) {
            return [
                'nik' => $row->nik_karyawan,
                'estatus' => $row->status_karyawan,
                'sdate' => $row->tgl_mulai_kerja,
                'ecdate' => $row->tgl_berakhir_kontrak,
                'departement' => $row->id_department,
                'grade' => $row->grade,
                'position' => $row->id_jabatan,
                'ppdate' => $row->tgl_pra_pensiun,
                'ccenter' => $row->cost_center,
                'email' => $row->email,
                'email_pribadi' => $row->email_pribadi,
                'branch' => $row->id_cabang,
                'gradec' => $row->kategori_grade,
                'jstatus' => $row->status_pekerjaan,
                'dsupervisor' => json_decode($row->atasan_langsung),
                'pdate' => $row->tgl_pensiun
            ];
        })->addColumn('access', function ($row) {
            return [
                'username' => ($row->user != null ? $row->user->username : ''),
                'priv_branch' => json_decode($row->privilage_cabang)
            ];
        })
            ->addColumn('pendidikan', function ($row) {
                $rows = $row->pendidikan_karyawan
                    ->filter(function ($item) {
                        return (int) ($item->is_active ?? 1) !== 0
                            && (trim((string) $item->jenjang) !== '' || trim((string) $item->institusi) !== '');
                    })
                    ->sortByDesc(function ($item) {
                        return sprintf('%04d-%06d', (int) $item->tahun_lulus, (int) $item->id);
                    })
                    ->values()
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'jenjang' => $item->jenjang,
                            'institusi' => $item->institusi,
                            'jurusan' => $item->jurusan,
                            'tahun_masuk' => $item->tahun_masuk,
                            'tahun_lulus' => $item->tahun_lulus,
                            'kota' => $item->kota,
                        ];
                    })
                    ->all();

                if ($rows) {
                    return $rows;
                }

                $decoded = json_decode($row->getOriginal('pendidikan') ?: '[]', true);
                return is_array($decoded) ? $decoded : [];
            })
            ->addColumn('kontak_darurat', function ($row) {
                $seen = [];
                return $row->kontak_darurat
                    ->filter(function ($item) use (&$seen) {
                        if ((int) ($item->is_active ?? 1) === 0) {
                            return false;
                        }
                        $nama = trim((string) $item->nama_kontak);
                        $hubungan = trim((string) $item->hubungan);
                        $nomor = trim((string) $item->nomor_kontak);
                        if ($nama === '' && $nomor === '') {
                            return false;
                        }
                        $fingerprint = strtolower($nama . '|' . $hubungan . '|' . $nomor);
                        if (isset($seen[$fingerprint])) {
                            return false;
                        }
                        $seen[$fingerprint] = true;
                        return true;
                    })
                    ->values()
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'nama_kontak' => $item->nama_kontak,
                            'hubungan' => $item->hubungan,
                            'nomor_kontak' => $item->nomor_kontak,
                        ];
                    })
                    ->all();
            })
            ->filterColumn('department', $this->exactColumnFilter('department'))
            ->filterColumn('jenis_kelamin', $this->exactColumnFilter('jenis_kelamin'))
            ->filterColumn('status_pernikahan', $this->exactColumnFilter('status_pernikahan'))
            ->filterColumn('status_karyawan', $this->exactColumnFilter('status_karyawan'))
            ->filterColumn('grade', $this->exactColumnFilter('grade'))
            ->rawColumns(['personal', 'contact', 'employee', 'access'])
            ->make(true);
    }

    // public function store(Request $request)
    // {
    //     if ($request->personal['id'] != '') {
    //         return DB::transaction(function () use ($request) {
    //             $timestamp = DATE('Y-m-d H:i:s');
    //             $karyawan = MasterKaryawan::find($request->personal['id']);
    //             if (!$karyawan) {
    //                 return response()->json(['message' => 'Karyawan tidak ditemukan'], 401);
    //             }

    //             $dataKaryawan = [
    //                 'nama_lengkap' => $request->personal['nama_lengkap'],
    //                 'nama_panggilan' => $request->personal['salutation'],
    //                 'nik_ktp' => $request->personal['nik_ktp'],
    //                 'kebangsaan' => $request->personal['nationality'],
    //                 'tempat_lahir' => $request->personal['birth_place'],
    //                 'tanggal_lahir' => $request->personal['date_birth'],
    //                 'jenis_kelamin' => $request->personal['gender'],
    //                 'agama' => $request->personal['religion'],
    //                 'status_pernikahan' => $request->personal['marital_status'],
    //                 'tempat_nikah' => $request->personal['marital_place'],
    //                 'tgl_nikah' => ($request->personal['marital_date'] != '' ? $request->personal['marital_date'] : null),
    //                 'shio' => $request->personal['shio'],
    //                 'elemen' => $request->personal['elemen']
    //             ];

    //             if ($request->has('contact')) {
    //                 $dataKaryawan['alamat'] = $request->contact['address'];
    //                 $dataKaryawan['negara'] = $request->contact['country'];
    //                 $dataKaryawan['provinsi'] = $request->contact['province'];
    //                 $dataKaryawan['kota'] = $request->contact['city'];
    //                 $dataKaryawan['no_telpon'] = $request->contact['phone'];
    //                 $dataKaryawan['kode_pos'] = $request->contact['postal_code'];
    //             }

    //             if ($request->has('employee')) {
    //                 $checkNikKaryawan = MasterKaryawan::where('nik_karyawan',  $request->employee['nik'])->first();
    //                 if ($checkNikKaryawan) {
    //                     return response()->json([
    //                         'message' => 'Nik karyawan sudah ada'
    //                     ], 500);
    //                 }
    //                 $dataKaryawan['nik_karyawan'] = $request->employee['nik'];
    //                 $dataKaryawan['email'] = $request->employee['email'];
    //                 $dataKaryawan['email_pribadi'] = $request->employee['email_pribadi'];
    //                 $dataKaryawan['id_cabang'] = $request->employee['branch'];
    //                 $dataKaryawan['status_karyawan'] = $request->employee['estatus'];
    //                 $dataKaryawan['tgl_mulai_kerja'] = $request->employee['sdate'];
    //                 $dataKaryawan['tgl_berakhir_kontrak'] = $request->employee['ecdate'];
    //                 $dataKaryawan['id_jabatan'] = $request->employee['position'];
    //                 $dataKaryawan['kategori_grade'] = $request->employee['gradec'];
    //                 $dataKaryawan['grade'] = $request->employee['grade'];
    //                 $dataKaryawan['status_pekerjaan'] = $request->employee['jstatus'];
    //                 $dataKaryawan['id_department'] = $request->employee['departement'];
    //                 $dataKaryawan['atasan_langsung'] = json_encode($request->employee['dsupervisor']);
    //                 $dataKaryawan['cost_center'] = $request->employee['ccenter'];
    //                 $dataKaryawan['tgl_pra_pensiun'] = ($request->employee['ppdate'] != '' ? $request->employee['ppdate'] : null);
    //                 $dataKaryawan['tgl_pensiun'] = ($request->employee['pdate'] != '' ? $request->employee['pdate'] : null);
    //             }

    //             if ($request->has('access')) {
    //                 $dataKaryawan['privilage_cabang'] = json_encode($request->access['priv_branch']);
    //             }

    //             if ($request->hasFile('personal.image')) {
    //                 $profilePicture = $request->file('personal.image');
    //                 $imageName = $request->personal['nik_ktp'] . '_' . str_replace(' ', '_', $request->personal['nama_lengkap']) . '.' . $profilePicture->getClientOriginalExtension();
    //                 $destinationPath = public_path('/Foto_Karyawan');

    //                 $profilePicture->move($destinationPath, $imageName);

    //                 $dataKaryawan['image'] = $imageName;
    //             }

    //             $dataKaryawan['updated_by'] = $this->karyawan;
    //             $dataKaryawan['updated_at'] = $timestamp;

    //             $karyawan->update($dataKaryawan);

    //             if ($request->has('medical') && $request->medical['tinggi_badan'] != '') {
    //                 $dataMedical = [
    //                     'tinggi_badan' => $request->medical['tinggi_badan'],
    //                     'berat_badan' => $request->medical['berat_badan'],
    //                     'rate_mata' => $request->medical['rate_mata'],
    //                     'golongan_darah' => $request->medical['golongan_darah'],
    //                     'penyakit_bawaan_lahir' => $request->medical['penyakit_lahir'],
    //                     'penyakit_kronis' => $request->medical['penyakit_kronis'],
    //                     'riwayat_kecelakaan' => $request->medical['riwayat_kecelakaan'],
    //                 ];

    //                 $checkMedical = MedicalCheckup::where('karyawan_id', $karyawan->id)->first();
    //                 ($request->medical['keterangan_mata'] != 'true') ? $dataMedical['keterangan_mata'] = $request->medical['keterangan_mata'] : $dataMedical['keterangan_mata'] = null;
    //                 // dd($dataMedical);
    //                 if ($checkMedical) {
    //                     $checkMedical->update($dataMedical);
    //                 } else {
    //                     $dataMedical['karyawan_id'] = $karyawan->id;
    //                     MedicalCheckup::insert($dataMedical);
    //                 }
    //             }

    //             if ($request->has('access') && $request->access['username'] != '') {
    //                 $dataUser = [
    //                     'username' => $request->access['username'],
    //                     // 'password'      => Hash::make($request->access['password']),
    //                     'email' => $request->employee['email']
    //                 ];
    //                 ($request->access['password'] != '' ? $dataUser['password'] = Hash::make($request->access['password']) : '');
    //                 $cek_user = User::where('id', $karyawan->user_id)->where('is_active', true)->first();

    //                 if ($cek_user) {
    //                     $dataUser['updated_by'] = $this->karyawan;
    //                     $dataUser['updated_at'] = $timestamp;
    //                     $cek_user->update($dataUser);
    //                 } else {
    //                     $dataUser['created_by'] = $this->karyawan;
    //                     $dataUser['created_at'] = $timestamp;
    //                     $user = User::insertGetId($dataUser);
    //                     $data = MasterKaryawan::where('id', $karyawan->id)->first();

    //                     if ($data) {
    //                         $data->user_id = $user;
    //                         $data->save();
    //                     }
    //                 }
    //             }

    //             return response()->json(['message' => 'Karyawan updated successfully'], 201);
    //         });
    //     } else {
    //         return DB::transaction(function () use ($request) {
    //             $dataKaryawan = [
    //                 'nama_lengkap' => $request->personal['nama_lengkap'],
    //                 'nama_panggilan' => $request->personal['salutation'],
    //                 'nik_ktp' => $request->personal['nik_ktp'],
    //                 'kebangsaan' => $request->personal['nationality'],
    //                 'tempat_lahir' => $request->personal['birth_place'],
    //                 'tanggal_lahir' => $request->personal['date_birth'],
    //                 'jenis_kelamin' => $request->personal['gender'],
    //                 'agama' => $request->personal['religion'],
    //                 'status_pernikahan' => $request->personal['marital_status'],
    //                 'tempat_nikah' => $request->personal['marital_place'],
    //                 'tgl_nikah' => ($request->personal['marital_date'] != '' ? $request->personal['marital_date'] : null),
    //                 'shio' => $request->personal['shio'],
    //                 'elemen' => $request->personal['elemen']
    //             ];

    //             if ($request->has('contact')) {
    //                 $dataKaryawan['alamat'] = $request->contact['address'];
    //                 $dataKaryawan['negara'] = $request->contact['country'];
    //                 $dataKaryawan['provinsi'] = $request->contact['province'];
    //                 $dataKaryawan['kota'] = $request->contact['city'];
    //                 $dataKaryawan['no_telpon'] = $request->contact['phone'];
    //                 $dataKaryawan['kode_pos'] = $request->contact['postal_code'];
    //             }

    //             if ($request->has('employee')) {
    //                 $checkNikKaryawan = MasterKaryawan::where('nik_karyawan',  $request->employee['nik'])->first();
    //                 if ($checkNikKaryawan) {
    //                     return response()->json([
    //                         'message' => 'Nik karyawan sudah ada'
    //                     ], 500);
    //                 }
    //                 $dataKaryawan['nik_karyawan'] = $request->employee['nik'];
    //                 $dataKaryawan['email'] = $request->employee['email'];
    //                 $dataKaryawan['email_pribadi'] = $request->employee['email_pribadi'];
    //                 $dataKaryawan['id_cabang'] = $request->employee['branch'];
    //                 $dataKaryawan['status_karyawan'] = $request->employee['estatus'];
    //                 $dataKaryawan['tgl_mulai_kerja'] = $request->employee['sdate'];
    //                 $dataKaryawan['tgl_berakhir_kontrak'] = $request->employee['ecdate'];
    //                 $dataKaryawan['id_jabatan'] = $request->employee['position'];
    //                 $dataKaryawan['kategori_grade'] = $request->employee['gradec'];
    //                 $dataKaryawan['grade'] = $request->employee['grade'];
    //                 $dataKaryawan['status_pekerjaan'] = $request->employee['jstatus'];
    //                 $dataKaryawan['id_department'] = $request->employee['departement'];
    //                 $dataKaryawan['atasan_langsung'] = json_encode($request->employee['dsupervisor']);
    //                 $dataKaryawan['cost_center'] = $request->employee['ccenter'];
    //                 $dataKaryawan['tgl_pra_pensiun'] = ($request->employee['ppdate'] != '' ? $request->employee['ppdate'] : null);
    //                 $dataKaryawan['tgl_pensiun'] = ($request->employee['pdate'] != '' ? $request->employee['pdate'] : null);
    //             }

    //             if ($request->has('access')) {
    //                 $dataKaryawan['privilage_cabang'] = json_encode($request->access['priv_branch']);
    //             }

    //             if ($request->hasFile('personal.image')) {
    //                 $profilePicture = $request->file('personal.image');
    //                 $imageName = $request->personal['nik_ktp'] . '_' . str_replace(' ', '_', $request->personal['nama_lengkap']) . '.' . $profilePicture->getClientOriginalExtension();
    //                 $destinationPath = public_path('/Foto_Karyawan');

    //                 $profilePicture->move($destinationPath, $imageName);

    //                 $dataKaryawan['image'] = $imageName;
    //             }

    //             $dataKaryawan['created_by'] = $this->karyawan;
    //             $dataKaryawan['created_at'] = DATE('Y-m-d H:i:s');

    //             $karyawan = MasterKaryawan::create($dataKaryawan);

    //             if ($request->has('medical')) {
    //                 $dataMedical = [
    //                     'karyawan_id' => $karyawan->id,
    //                     'tinggi_badan' => $request->medical['tinggi_badan'],
    //                     'berat_badan' => $request->medical['berat_badan'],
    //                     'golongan_darah' => $request->medical['golongan_darah'],
    //                     'rate_mata' => $request->medical['rate_mata'],
    //                     'penyakit_bawaan_lahir' => $request->medical['penyakit_lahir'],
    //                     'penyakit_kronis' => $request->medical['penyakit_kronis'],
    //                     'riwayat_kecelakaan' => $request->medical['riwayat_kecelakaan'],
    //                 ];

    //                 ($request->medical['keterangan_mata'] != 'true') ? $dataMedical['keterangan_mata'] = $request->medical['keterangan_mata'] : $dataMedical['keterangan_mata'] = null;

    //                 $medical = MedicalCheckup::create($dataMedical);
    //             }

    //             if ($request->has('access') && $request->access['username'] != '') {
    //                 $dataUser = [
    //                     'username' => $request->access['username'],
    //                     'password' => Hash::make($request->access['password']),
    //                     'email' => $request->employee['email'],
    //                     'created_by' => $this->karyawan,
    //                     'created_at' => DATE('Y-m-d H:i:s')
    //                 ];
    //                 $user = User::create($dataUser);

    //                 $data = MasterKaryawan::where('id', $karyawan->id)->first();

    //                 if ($data) {
    //                     $data->user_id = $user->id;
    //                     $data->save();
    //                 }
    //             }
    //             return response()->json(['message' => 'Karyawan created successfully'], 201);
    //         });
    //     }
    // }


    public function store(Request $request)
    {
        if (isset($request->personal['id']) && $request->personal['id'] != '') {
            // UPDATE EXISTING KARYAWAN
            return DB::transaction(function () use ($request) {
                $timestamp = DATE('Y-m-d H:i:s');
                $karyawan = MasterKaryawan::find($request->personal['id']);

                if (!$karyawan) {
                    return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
                }

                $shioElemen = ShioElemenHelper::resolve(
                    $request->personal['date_birth'] ?? null,
                    $request->personal['shio'] ?? null,
                    $request->personal['elemen'] ?? null
                );

                $dataKaryawan = [
                    'nama_lengkap' => $request->personal['nama_lengkap'],
                    'nama_panggilan' => $request->personal['salutation'],
                    'nik_ktp' => $request->personal['nik_ktp'],
                    'kebangsaan' => $request->personal['nationality'],
                    'tempat_lahir' => $request->personal['birth_place'],
                    'tanggal_lahir' => $request->personal['date_birth'],
                    'jenis_kelamin' => $request->personal['gender'],
                    'agama' => $request->personal['religion'],
                    'status_pernikahan' => $request->personal['marital_status'],
                    'tempat_nikah' => $request->personal['marital_place'],
                    'tgl_nikah' => ($request->personal['marital_date'] != '' ? $request->personal['marital_date'] : null),
                    'shio' => $shioElemen['shio'],
                    'elemen' => $shioElemen['elemen']
                ];

                if ($request->has('contact')) {
                    $dataKaryawan['alamat'] = $request->contact['address'];
                    $dataKaryawan['negara'] = $request->contact['country'];
                    $dataKaryawan['provinsi'] = $request->contact['province'];
                    $dataKaryawan['kota'] = $request->contact['city'];
                    $dataKaryawan['no_telpon'] = $request->contact['phone'];
                    $dataKaryawan['kode_pos'] = $request->contact['postal_code'];
                }

                if ($request->has('employee')) {
                    // CEK NIK KARYAWAN - KECUALIKAN DATA YANG SEDANG DIUPDATE
                    $checkNikKaryawan = MasterKaryawan::where('nik_karyawan', $request->employee['nik'])
                        ->where('id', '!=', $karyawan->id)
                        ->first();

                    if ($checkNikKaryawan) {
                        return response()->json([
                            'message' => 'Nik karyawan sudah ada'
                        ], 500);
                    }

                    $oldNik = $karyawan->nik_karyawan;
                    $newNik = $request->employee['nik'];

                    if ($oldNik !== $newNik) {
                        MasterSallaryNikSyncService::syncOnNikChange(
                            $karyawan->id,
                            $request->personal['nama_lengkap'] ?? $karyawan->nama_lengkap,
                            $oldNik,
                            $newNik,
                            $this->karyawan
                        );
                    }

                    $dataKaryawan['nik_karyawan'] = $newNik;
                    $dataKaryawan['email'] = $request->employee['email'];
                    $dataKaryawan['email_pribadi'] = $request->employee['email_pribadi'];
                    $dataKaryawan['id_cabang'] = $request->employee['branch'];
                    $dataKaryawan['status_karyawan'] = $request->employee['estatus'];
                    $dataKaryawan['tgl_mulai_kerja'] = $request->employee['sdate'];
                    $dataKaryawan['tgl_berakhir_kontrak'] = $request->employee['ecdate'];
                    $dataKaryawan['id_jabatan'] = $request->employee['position'];
                    $dataKaryawan['kategori_grade'] = $request->employee['gradec'];
                    $dataKaryawan['grade'] = $request->employee['grade'];
                    $dataKaryawan['status_pekerjaan'] = $request->employee['jstatus'];
                    $dataKaryawan['id_department'] = $request->employee['departement'];
                    $dataKaryawan['atasan_langsung'] = json_encode($request->employee['dsupervisor']);
                    $dataKaryawan['cost_center'] = $request->employee['ccenter'];
                    $dataKaryawan['tgl_pra_pensiun'] = ($request->employee['ppdate'] != '' ? $request->employee['ppdate'] : null);
                    $dataKaryawan['tgl_pensiun'] = ($request->employee['pdate'] != '' ? $request->employee['pdate'] : null);
                }

                if ($request->has('access')) {
                    $dataKaryawan['privilage_cabang'] = json_encode($request->access['priv_branch']);
                }

                if ($request->hasFile('personal.image')) {
                    $profilePicture = $request->file('personal.image');
                    $imageName = $request->personal['nik_ktp'] . '_' . str_replace(' ', '_', $request->personal['nama_lengkap']) . '.' . $profilePicture->getClientOriginalExtension();
                    $destinationPath = public_path('/Foto_Karyawan');

                    $profilePicture->move($destinationPath, $imageName);

                    $dataKaryawan['image'] = $imageName;
                }

                $dataKaryawan['updated_by'] = $this->karyawan;
                $dataKaryawan['updated_at'] = $timestamp;

                $karyawan->update($dataKaryawan);

                if ($request->has('medical') && isset($request->medical['tinggi_badan']) && $request->medical['tinggi_badan'] != '') {
                    $dataMedical = [
                        'tinggi_badan' => $request->medical['tinggi_badan'],
                        'berat_badan' => $request->medical['berat_badan'],
                        'rate_mata' => $request->medical['rate_mata'],
                        'golongan_darah' => $request->medical['golongan_darah'],
                        'penyakit_bawaan_lahir' => $request->medical['penyakit_lahir'],
                        'penyakit_kronis' => $request->medical['penyakit_kronis'],
                        'riwayat_kecelakaan' => $request->medical['riwayat_kecelakaan'],
                    ];

                    if (isset($request->medical['keterangan_mata']) && $request->medical['keterangan_mata'] != 'true') {
                        $dataMedical['keterangan_mata'] = $request->medical['keterangan_mata'];
                    } else {
                        $dataMedical['keterangan_mata'] = null;
                    }

                    $checkMedical = MedicalCheckup::where('karyawan_id', $karyawan->id)->first();

                    if ($checkMedical) {
                        $checkMedical->update($dataMedical);
                    } else {
                        $dataMedical['karyawan_id'] = $karyawan->id;
                        MedicalCheckup::create($dataMedical);
                    }
                }

                if ($request->has('access') && isset($request->access['username']) && $request->access['username'] != '') {
                    $dataUser = [
                        'username' => $request->access['username'],
                        'email' => $request->employee['email']
                    ];

                    if (isset($request->access['password']) && $request->access['password'] != '') {
                        $dataUser['password'] = Hash::make($request->access['password']);
                    }

                    $cek_user = User::where('id', $karyawan->user_id)->where('is_active', true)->first();

                    if ($cek_user) {
                        $dataUser['updated_by'] = $this->karyawan;
                        $dataUser['updated_at'] = $timestamp;
                        $cek_user->update($dataUser);
                    } else {
                        $dataUser['created_by'] = $this->karyawan;
                        $dataUser['created_at'] = $timestamp;
                        $user = User::create($dataUser);

                        $karyawan->user_id = $user->id;
                        $karyawan->save();
                    }
                }

                return response()->json(['message' => 'Karyawan updated successfully'], 200);
            });
        } else {
            // CREATE NEW KARYAWAN
            return DB::transaction(function () use ($request) {
                $shioElemen = ShioElemenHelper::resolve(
                    $request->personal['date_birth'] ?? null,
                    $request->personal['shio'] ?? null,
                    $request->personal['elemen'] ?? null
                );

                $dataKaryawan = [
                    'nama_lengkap' => $request->personal['nama_lengkap'],
                    'nama_panggilan' => $request->personal['salutation'],
                    'nik_ktp' => $request->personal['nik_ktp'],
                    'kebangsaan' => $request->personal['nationality'],
                    'tempat_lahir' => $request->personal['birth_place'],
                    'tanggal_lahir' => $request->personal['date_birth'],
                    'jenis_kelamin' => $request->personal['gender'],
                    'agama' => $request->personal['religion'],
                    'status_pernikahan' => $request->personal['marital_status'],
                    'tempat_nikah' => $request->personal['marital_place'],
                    'tgl_nikah' => ($request->personal['marital_date'] != '' ? $request->personal['marital_date'] : null),
                    'shio' => $shioElemen['shio'],
                    'elemen' => $shioElemen['elemen']
                ];

                if ($request->has('contact')) {
                    $dataKaryawan['alamat'] = $request->contact['address'];
                    $dataKaryawan['negara'] = $request->contact['country'];
                    $dataKaryawan['provinsi'] = $request->contact['province'];
                    $dataKaryawan['kota'] = $request->contact['city'];
                    $dataKaryawan['no_telpon'] = $request->contact['phone'];
                    $dataKaryawan['kode_pos'] = $request->contact['postal_code'];
                }

                if ($request->has('employee')) {
                    // CEK NIK KARYAWAN
                    $checkNikKaryawan = MasterKaryawan::where('nik_karyawan', $request->employee['nik'])->first();

                    if ($checkNikKaryawan) {
                        return response()->json([
                            'message' => 'Nik karyawan sudah ada'
                        ], 500);
                    }

                    $dataKaryawan['nik_karyawan'] = $request->employee['nik'];
                    $dataKaryawan['email'] = $request->employee['email'];
                    $dataKaryawan['email_pribadi'] = $request->employee['email_pribadi'];
                    $dataKaryawan['id_cabang'] = $request->employee['branch'];
                    $dataKaryawan['status_karyawan'] = $request->employee['estatus'];
                    $dataKaryawan['tgl_mulai_kerja'] = $request->employee['sdate'];
                    $dataKaryawan['tgl_berakhir_kontrak'] = $request->employee['ecdate'];
                    $dataKaryawan['id_jabatan'] = $request->employee['position'];
                    $dataKaryawan['kategori_grade'] = $request->employee['gradec'];
                    $dataKaryawan['grade'] = $request->employee['grade'];
                    $dataKaryawan['status_pekerjaan'] = $request->employee['jstatus'];
                    $dataKaryawan['id_department'] = $request->employee['departement'];
                    $dataKaryawan['atasan_langsung'] = json_encode($request->employee['dsupervisor']);
                    $dataKaryawan['cost_center'] = $request->employee['ccenter'];
                    $dataKaryawan['tgl_pra_pensiun'] = ($request->employee['ppdate'] != '' ? $request->employee['ppdate'] : null);
                    $dataKaryawan['tgl_pensiun'] = ($request->employee['pdate'] != '' ? $request->employee['pdate'] : null);
                }

                if ($request->has('access')) {
                    $dataKaryawan['privilage_cabang'] = json_encode($request->access['priv_branch']);
                }

                if ($request->hasFile('personal.image')) {
                    $profilePicture = $request->file('personal.image');
                    $imageName = $request->personal['nik_ktp'] . '_' . str_replace(' ', '_', $request->personal['nama_lengkap']) . '.' . $profilePicture->getClientOriginalExtension();
                    $destinationPath = public_path('/Foto_Karyawan');

                    $profilePicture->move($destinationPath, $imageName);

                    $dataKaryawan['image'] = $imageName;
                }

                $dataKaryawan['created_by'] = $this->karyawan;
                $dataKaryawan['created_at'] = DATE('Y-m-d H:i:s');

                $karyawan = MasterKaryawan::create($dataKaryawan);

                if ($request->has('medical') && isset($request->medical['tinggi_badan']) && $request->medical['tinggi_badan'] != '') {
                    $dataMedical = [
                        'karyawan_id' => $karyawan->id,
                        'tinggi_badan' => $request->medical['tinggi_badan'],
                        'berat_badan' => $request->medical['berat_badan'],
                        'golongan_darah' => $request->medical['golongan_darah'],
                        'rate_mata' => $request->medical['rate_mata'],
                        'penyakit_bawaan_lahir' => $request->medical['penyakit_lahir'],
                        'penyakit_kronis' => $request->medical['penyakit_kronis'],
                        'riwayat_kecelakaan' => $request->medical['riwayat_kecelakaan'],
                    ];

                    if (isset($request->medical['keterangan_mata']) && $request->medical['keterangan_mata'] != 'true') {
                        $dataMedical['keterangan_mata'] = $request->medical['keterangan_mata'];
                    } else {
                        $dataMedical['keterangan_mata'] = null;
                    }

                    MedicalCheckup::create($dataMedical);
                }

                if ($request->has('access') && isset($request->access['username']) && $request->access['username'] != '') {
                    $dataUser = [
                        'username' => $request->access['username'],
                        'password' => Hash::make($request->access['password']),
                        'email' => $request->employee['email'],
                        'created_by' => $this->karyawan,
                        'created_at' => DATE('Y-m-d H:i:s')
                    ];

                    $user = User::create($dataUser);

                    $karyawan->user_id = $user->id;
                    $karyawan->save();
                }

                return response()->json(['message' => 'Karyawan created successfully'], 201);
            });
        }
    }
    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = MasterKaryawan::where('id', $request->id)->first();

            if ($data) {
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->active = false;
                $data->is_active = false;
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => $request->active == 0 ? 'Karyawan Delete successfully' : 'Restore Karyawan Berhasil!'
                ], 200);
            }

            DB::rollBack();
            return response()->json(['message' => 'Data Not Found.!'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error : ' . $e->getMessage()], 500);
        }
    }

    public function getBranch(Request $request)
    {
        $data = MasterCabang::where('is_active', true)->select('id', 'nama_cabang')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function getDepartment(Request $request)
    {
        $data = MasterDivisi::where('is_active', true)->select('id', 'nama_divisi')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function getPosition(Request $request)
    {
        $data = MasterJabatan::where('is_active', true)->select('id', 'nama_jabatan')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function getSpv(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)->select('id', 'nama_lengkap', 'nik_karyawan')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }
    public function getKaryawan(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)->select('id', 'nama_lengkap', 'nik_karyawan')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function getAllKaryawan(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)
            ->select('id', 'nama_lengkap', 'nik_karyawan')
            ->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function nonActive(Request $request)
    {
        DB::beginTransaction();
        try {
            $karyawan = MasterKaryawan::find($request->id);

            $karyawan->effective_date = $request->effective_date;
            $karyawan->reason_non_active = $request->reason_non_active;
            $karyawan->notes = $request->notes;
            $karyawan->updated_by = $this->karyawan;
            $karyawan->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $karyawan->active = false;
            $karyawan->is_active = false;

            $karyawan->save();

            PayrollRecordSyncService::deactivateForKaryawan($karyawan, $this->karyawan);
            
            $user = User::where('id', $karyawan->user_id)->first();
            if ($user) {
                $user->updated_by = $this->karyawan;
                $user->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $user->is_active = false;
                
                $user->save();
            }

            // DB::connection('mysql')
            //     ->table('users')
            //     ->where('user_id', $karyawan->id)
            //     ->update([
            //         'updated_by' => $this->karyawan,
            //         'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            //         'is_active' => false
            //     ]);

            $job = new NonaktifKaryawanJob($karyawan);
            $this->dispatch($job);

            DB::commit();
            return response()->json(['message' => 'Berhasil menonaktifkan karyawan, silahkan tunggu beberapa saat'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
            return response()->json(['message' => 'Gagal menonaktifkan karyawan: ' . $th->getMessage()], 500);
            //throw $th;
        }
    }

    public function retry(Request $request)
    {
        $karyawan = MasterKaryawan::find($request->id);

        $job = new NonaktifKaryawanJob($karyawan);
        $this->dispatch($job);
    }

    public function getCabang(Request $request)
    {
        $base = MasterKaryawan::query()->where('is_active', true);
        $this->excludeHiddenKaryawanFromFrontend($base);
        $this->applyKaryawanColumnSearch($base, $request);

        $counts = (clone $base)
            ->select('id_cabang', DB::raw('COUNT(*) as total'))
            ->groupBy('id_cabang')
            ->pluck('total', 'id_cabang');

        $data = MasterCabang::where('is_active', true)
            ->select('id', 'nama_cabang')
            ->get()
            ->map(function ($cabang) use ($counts) {
                $cabang->total = (int) ($counts[$cabang->id] ?? 0);
                return $cabang;
            });

        return response()->json([
            'message' => 'Data hasbeen show',
            'data' => $data,
            'total' => (clone $base)->count(),
        ], 200);
    }

    private function applyKaryawanColumnSearch($query, Request $request): void
    {
        $columns = [
            '2' => 'nik_karyawan',
            '3' => 'nama_lengkap',
            '4' => 'department',
            '5' => 'jabatan',
            '6' => 'jenis_kelamin',
            '7' => 'status_pernikahan',
            '8' => 'status_karyawan',
            '9' => 'grade',
            '10' => 'tgl_mulai_kerja',
            '11' => 'tgl_berakhir_kontrak',
        ];

        $search = $request->input('column_search', []);
        if (!is_array($search)) {
            return;
        }

        foreach ($columns as $index => $column) {
            $value = trim((string) ($search[$index] ?? ''));
            if ($value === '') {
                continue;
            }

            $this->applyKaryawanColumnFilter($query, $column, $value);
        }
    }

    private function applyKaryawanColumnFilter($query, string $column, string $value): void
    {
        if (in_array($column, self::KARYAWAN_EXACT_SEARCH_COLUMNS, true)) {
            $query->where($column, $value);
            return;
        }

        $query->where($column, 'like', '%' . addcslashes($value, '%_\\') . '%');
    }

    private function exactColumnFilter(string $column): \Closure
    {
        return function ($query, $keyword) use ($column) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                return;
            }
            $query->where($column, $keyword);
        };
    }

    private function excludeHiddenKaryawanFromFrontend($query): void
    {
        
        $query->whereNotIn('id', self::FRONTEND_HIDDEN_KARYAWAN_IDS);
    }
}
