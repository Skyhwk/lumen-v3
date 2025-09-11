<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\User;
use App\Models\MedicalCheckup;
use App\Models\MasterPelanggan;
use App\Services\GetAtasan;
use Validator;
use App\Http\Controllers\Controller;
use App\Models\AksesMenu;
use App\Models\HistoryPerubahanSales;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class MasterKaryawanController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with(['medical', 'user'])->where('is_active', true);
        return Datatables::of($data)->addColumn('personal', function ($row) {
            return [
                'nama_lengkap' => $row->nama_lengkap,
                'birth_place' => $row->tempat_lahir,
                'shio' => $row->shio,
                'gender' => $row->jenis_kelamin,
                'marital_status' => $row->status_pernikahan,
                'marital_date' => $row->tgl_nikah,
                'marital_place' => $row->tempat_nikah,
                'nik_ktp' => $row->nik_ktp,
                'date_birth' => $row->tanggal_lahir,
                'elemen' => $row->elemen,
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
            ->rawColumns(['personal', 'contact', 'employee', 'access'])
            ->make(true);
    }

    public function store(Request $request)
    {
        if ($request->personal['id'] != '') {
            DB::transaction(function () use ($request) {
                $timestamp = DATE('Y-m-d H:i:s');
                $karyawan = MasterKaryawan::find($request->personal['id']);
                if (!$karyawan) {
                    return response()->json(['message' => 'Karyawan tidak ditemukan'], 401);
                }

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
                    'shio' => $request->personal['shio'],
                    'elemen' => $request->personal['elemen']
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

                $dataKaryawan['updated_by'] = $this->karyawan;
                $dataKaryawan['updated_at'] = $timestamp;

                $karyawan->update($dataKaryawan);

                if ($request->has('medical') && $request->medical['tinggi_badan'] != '') {
                    $dataMedical = [
                        'tinggi_badan' => $request->medical['tinggi_badan'],
                        'berat_badan' => $request->medical['berat_badan'],
                        'rate_mata' => $request->medical['rate_mata'],
                        'golongan_darah' => $request->medical['golongan_darah'],
                        'penyakit_bawaan_lahir' => $request->medical['penyakit_lahir'],
                        'penyakit_kronis' => $request->medical['penyakit_kronis'],
                        'riwayat_kecelakaan' => $request->medical['riwayat_kecelakaan'],
                    ];

                    $checkMedical = MedicalCheckup::where('karyawan_id', $karyawan->id)->first();
                    ($request->medical['keterangan_mata'] != 'true') ? $dataMedical['keterangan_mata'] = $request->medical['keterangan_mata'] : $dataMedical['keterangan_mata'] = null;
                    // dd($dataMedical);
                    if ($checkMedical) {
                        $checkMedical->update($dataMedical);
                    } else {
                        $dataMedical['karyawan_id'] = $karyawan->id;
                        MedicalCheckup::insert($dataMedical);
                    }
                }

                if ($request->has('access') && $request->access['username'] != '') {
                    $dataUser = [
                        'username' => $request->access['username'],
                        // 'password'      => Hash::make($request->access['password']),
                        'email' => $request->employee['email']
                    ];
                    ($request->access['password'] != '' ? $dataUser['password'] = Hash::make($request->access['password']) : '');
                    $cek_user = User::where('id', $karyawan->user_id)->where('is_active', true)->first();

                    if ($cek_user) {
                        $dataUser['updated_by'] = $this->karyawan;
                        $dataUser['updated_at'] = $timestamp;
                        $cek_user->update($dataUser);
                    } else {
                        $dataUser['created_by'] = $this->karyawan;
                        $dataUser['created_at'] = $timestamp;
                        $user = User::insertGetId($dataUser);
                        $data = MasterKaryawan::where('id', $karyawan->id)->first();

                        if ($data) {
                            $data->user_id = $user;
                            $data->save();
                        }
                    }
                }
            });
            return response()->json(['message' => 'Karyawan updated successfully'], 201);
        } else {
            DB::transaction(function () use ($request) {
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
                    'shio' => $request->personal['shio'],
                    'elemen' => $request->personal['elemen']
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

                if ($request->has('medical')) {
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

                    ($request->medical['keterangan_mata'] != 'true') ? $dataMedical['keterangan_mata'] = $request->medical['keterangan_mata'] : $dataMedical['keterangan_mata'] = null;

                    $medical = MedicalCheckup::create($dataMedical);
                }

                if ($request->has('access') && $request->access['username'] != '') {
                    $dataUser = [
                        'username' => $request->access['username'],
                        'password' => Hash::make($request->access['password']),
                        'email' => $request->employee['email'],
                        'created_by' => $this->karyawan,
                        'created_at' => DATE('Y-m-d H:i:s')
                    ];
                    $user = User::create($dataUser);

                    $data = MasterKaryawan::where('id', $karyawan->id)->first();

                    if ($data) {
                        $data->user_id = $user->id;
                        $data->save();
                    }

                }
            });

            return response()->json(['message' => 'Karyawan created successfully'], 201);
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
            ->select('nama_lengkap', 'nik_karyawan')
            ->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }
    public function nonActive(Request $request)
    {
        DB::beginTransaction();
        try {
            $timestamp = DATE('Y-m-d H:i:s');

            $karyawan = MasterKaryawan::where('id', $request->id)->first();
            if (!$karyawan) {
                return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
            }
            $dataKaryawan = [
                'effective_date' => $request->effective_date,
                'reason_non_active' => $request->reason_non_active,
                'notes' => $request->notes,
                'updated_at' => $timestamp,
                'updated_by' => $this->karyawan,
                'active' => true,
                'is_active' => false,
            ];
            $karyawan->update($dataKaryawan);

            $akses = AksesMenu::where('user_id', $karyawan->user_id)->where('is_active', true)->first();
            if(!is_null($akses)){
                $akses->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $akses->is_active = false;
                $akses->save();
            }
            // // kondisi jika sales maka pindahkan semua customer ke atasan langsung

            // if($karyawan->id_jabatan == 24){ //staff sales
            //     //pindahkan customer ke manager
            //     $cekAtasan = GetAtasan::where('id', $karyawan->id)->get();

            //     if(!empty($cekAtasan)) {
            //         $atasan = $cekAtasan->where('grade', 'SUPERVISOR')->first();
            //         if(!$atasan) {
            //             $atasan = $cekAtasan->where('id', $karyawan->id)->where('grade', 'MANAGER')->first();
            //             if(!$atasan) {
            //                 $atasan = MasterKaryawan::where('id_jabatan', 15)->where('is_active', true)->first();
            //             }
            //         }
            //     } else {
            //         $atasan = MasterKaryawan::where('id_jabatan', 15)->where('is_active', true)->first();
            //     }

            //     // dd($cekAtasan, $karyawan->atasan_langsung);
            //     // $executive = GetAtasan::where('id', $atasan->id)->where('grade', 'EXECUTIVE')->first();

            //     $customer = MasterPelanggan::where('sales_id', $request->id)->update([
            //         'sales_penanggung_jawab' => $atasan->nama_lengkap,
            //         'sales_id' => $atasan->id,
            //     ]);
            // }
            
            // reassign customer ke sales lain =================
            if($karyawan->id_jabatan == 24) { // staff sales
                $activeSalesStaff = MasterKaryawan::where('id_jabatan', 24)
                    ->where('is_active', true)
                    ->where('id', '!=', 41) // Novva Novita Ayu Putri Rukmana
                    ->get()
                    ->shuffle()
                    ->values();

                if ($activeSalesStaff->isEmpty()) {
                    return response()->json(['message' => 'Tidak ada sales staff aktif lainnya untuk menerima customer. Proses dibatalkan'], 404);
                }
                
                $salesCount = $activeSalesStaff->count();
                $salesIndex = 0;

                MasterPelanggan::where('sales_id', $karyawan->id)->orWhere('sales_penanggung_jawab', $karyawan->nama_lengkap)
                    ->where('is_active', true)
                    ->chunkById(100, function ($customers) use ($activeSalesStaff, &$salesIndex, $salesCount, $karyawan) {
                        foreach ($customers as $customer) {
                            $newSales = $activeSalesStaff[$salesIndex];

                            $historySales = new HistoryPerubahanSales();
                            $historySales->id_pelanggan = $customer->id_pelanggan;
                            $historySales->id_sales_lama = $karyawan->id;
                            $historySales->id_sales_baru = $newSales->id;
                            $historySales->tanggal_rotasi = Carbon::now();
                            $historySales->save();

                            MasterPelanggan::where('id_pelanggan', $customer->id_pelanggan)->update([
                                'sales_id' => $newSales->id,
                                'sales_penanggung_jawab' => $newSales->nama_lengkap,
                            ]);

                            $salesIndex = ($salesIndex + 1) % $salesCount;
                        }
                    });
                }
                    
            DB::commit();
            return response()->json([
                'message' => 'Non Active Karyawan Success.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui karyawan',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
