<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
// use App\Models\RekamMedisKaryawan;
use App\Models\User;
use App\Models\MedicalCheckup;
use Validator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsHrisEmployeeIndexDatatable;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class MedicalCheckupController extends Controller
{
    use BuildsHrisEmployeeIndexDatatable;

    public function index(Request $request)
    {
        $data = $this->hrisEmployeeBaseQuery();

        return $this->applyHrisEmployeeContextColumns(Datatables::of($data))
            ->addColumn('status_rekam_medis', function ($row) {
                return $this->medicalRecord($row) ? 'Sudah ada' : 'Belum ada';
            })
            ->addColumn('ringkasan_medis', function ($row) {
                return $this->medicalSummary($row);
            })
            ->addColumn('jumlah_rekam_medis', function ($row) {
                return $this->medicalRecord($row) ? 1 : 0;
            })
            ->filterColumn('status_rekam_medis', function ($query, $keyword) {
                $normalized = strtolower(trim($keyword));
                if (in_array($normalized, ['sudah', 'ada', 'sudah ada'], true)) {
                    $query->whereHas('medical', function ($sub) {
                        $this->applyActiveScope($sub);
                    });
                } elseif (in_array($normalized, ['belum', 'belum ada', 'kosong'], true)) {
                    $query->whereDoesntHave('medical', function ($sub) {
                        $this->applyActiveScope($sub);
                    });
                }
            })
            ->filterColumn('ringkasan_medis', function ($query, $keyword) {
                $query->whereHas('medical', function ($sub) use ($keyword) {
                    $this->applyActiveScope($sub)->where(function ($w) use ($keyword) {
                        $w->where('golongan_darah', 'like', '%' . $keyword . '%')
                            ->orWhere('tinggi_badan', 'like', '%' . $keyword . '%')
                            ->orWhere('berat_badan', 'like', '%' . $keyword . '%');
                    });
                });
            })
            ->filterColumn('jumlah_rekam_medis', function ($query, $keyword) {
                if (!is_numeric($keyword)) {
                    return;
                }
                if ((int) $keyword >= 1) {
                    $query->whereHas('medical', function ($sub) {
                        $this->applyActiveScope($sub);
                    });
                } else {
                    $query->whereDoesntHave('medical', function ($sub) {
                        $this->applyActiveScope($sub);
                    });
                }
            })
            ->make(true);
    }

    public function showById(Request $request)
    {
        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['data' => []], 200);
        }

        $rows = MedicalCheckup::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $rows->values()->all()], 200);
    }

    private function medicalRecord($row)
    {
        static $cache = [];
        $karyawanId = (int) ($row->id ?? 0);
        if ($karyawanId <= 0) {
            return null;
        }
        if (array_key_exists($karyawanId, $cache)) {
            return $cache[$karyawanId];
        }

        $record = MedicalCheckup::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderByDesc('id')
            ->first();

        $cache[$karyawanId] = $record;

        return $record;
    }

    private function medicalSummary($row): string
    {
        $medical = $this->medicalRecord($row);
        if (!$medical) {
            return '-';
        }

        $parts = [];
        $gol = trim((string) ($medical->golongan_darah ?? ''));
        if ($gol !== '') {
            $parts[] = 'Gol. ' . $gol;
        }
        $tb = trim((string) ($medical->tinggi_badan ?? ''));
        $bb = trim((string) ($medical->berat_badan ?? ''));
        if ($tb !== '' || $bb !== '') {
            $parts[] = trim($tb . ' cm / ' . $bb . ' kg', ' /');
        }

        return $parts ? implode(' · ', $parts) : 'Data tersedia';
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $allowedBloodTypes = ['A', 'B', 'AB', 'O'];
            
            if (!in_array($request->golongan_darah, $allowedBloodTypes)) {
                return response()->json(['messages' => 'Invalid blood type.'], 400);
            }  
         
            $data = [
                'karyawan_id' => $request->karyawan_id,
                'tinggi_badan' => $request->tinggi_badan,
                'berat_badan' => $request->berat_badan,
                // 'keterangan_mata' => $request->keterangan_mata ?? null, 
                // 'rate_mata' => $request->rate_mata ?? null,    
                'golongan_darah' => $request->golongan_darah,
                'penyakit_bawaan_lahir' => $request->penyakit_bawaan_lahir ?? null,
                'penyakit_kronis' => $request->penyakit_kronis ?? null,
                'riwayat_kecelakaan' => $request->riwayat_kecelakaan ?? null,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true,
            ];

            if (!empty($request->keterangan_mata)) {
                $data['keterangan_mata'] = $request->keterangan_mata;
                $data['rate_mata'] = $request->rate_mata ?? null; 
            }

            $medicalCheckup = MedicalCheckup::create($data);
            
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            
            DB::beginTransaction();
            
            $allowedBloodTypes = ['A', 'B', 'AB', 'O'];
            
            $data = MedicalCheckup::find($request->id);

            if (!in_array($request->golongan_darah, $allowedBloodTypes)) {
                return response()->json(['messages' => 'Invalid blood type.'], 400);
            }        

            $data->tinggi_badan = $request->tinggi_badan ?? $data->tinggi_badan;
            $data->berat_badan = $request->berat_badan ?? $data->berat_badan;
            // $data->keterangan_mata = $request->keterangan_mata ?? $data->keterangan_mata;
            // $data->rate_mata = $request->rate_mata ?? $data->rate_mata;
            $data->golongan_darah = $request->golongan_darah ?? $data->golongan_darah;
            $data->penyakit_bawaan_lahir = $request->penyakit_bawaan_lahir ?? $data->penyakit_bawaan_lahir;
            $data->penyakit_kronis = $request->penyakit_kronis ?? $data->penyakit_kronis;
            $data->riwayat_kecelakaan = $request->riwayat_kecelakaan ?? $data->riwayat_kecelakaan;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');

            if (!empty($request->keterangan_mata)) {
                $data['keterangan_mata'] = $request->keterangan_mata;
                $data['rate_mata'] = $request->rate_mata ?? null;
            }

            $data->save();


            DB::commit();
            return response()->json(['message' => 'Data berhasil diupdate', 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = MedicalCheckup::find($request->id);
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}