<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Models\Canvasing;
use App\Models\MasterWilayahSampling;

class FdlCanvasingController extends Controller
{
    public function index(Request $request){
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = Canvasing::where('created_by', $this->karyawan)
            ->where('is_active', 1)
            ->where('is_processed', 0);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%$search%")
                ->orWhere('nama_pic', 'like', "%$search%")
                ->orWhere('wilayah', 'like', "%$search%");
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }

    public function store(Request $request){
        try {
            DB::beginTransaction();
            $data = new Canvasing();
            $data->nama_perusahaan = $request->nama_perusahaan;
            $data->no_telpon = $request->no_telpon;
            $data->nama_pic = $request->nama_pic;
            $data->nama_petugas = $this->karyawan;
            $data->no_hp_pic = $request->no_hp_pic;
            $data->wilayah = $request->wilayah;
            $data->penerima_flyer = $request->penerima_flyer;
            $data->latitude = $request->latitude;
            $data->longitude = $request->longitude;
            $data->titik_koordinat = $request->titik_koordinat;
            $data->jumlah_flyer = $request->jumlah_flyer;
            $data->foto_1                 = $request->foto_lokasi_1 ? self::convertImg($request->foto_lokasi_1, 1, $this->user_id) : null;
            $data->foto_2                 = $request->foto_lokasi_2 ? self::convertImg($request->foto_lokasi_2, 2, $this->user_id) : null;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json([
                'message' => 'Berhasil Simpan Data Canvasing', 
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(), 
                'success' => false
            ], 500);
        }
    }

    public function delete(Request $request){
        try {
            DB::beginTransaction();
            $data = Canvasing::where('id', $request->id)->first();
            $data->is_active = 0;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json([
                'message' => 'Berhasil Hapus Data Canvasing', 
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(), 
                'success' => false
            ], 500);
        }
    }

    public function getWilayah(){
        $wilayah = MasterWilayahSampling::where('is_active', true)
            ->select('id', 'wilayah')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $wilayah,
            'message' => 'Available wilayah data retrieved successfully',
        ], 201);
    }

    public function getJabatan()
    {
        $jabatan = [
            ['value' => 'direktur', 'label' => 'Direktur'],
            ['value' => 'general_manager', 'label' => 'General Manager'],
            ['value' => 'manager', 'label' => 'Manager'],
            ['value' => 'assistant_manager', 'label' => 'Assistant Manager'],
            ['value' => 'supervisor', 'label' => 'Supervisor'],
            ['value' => 'koordinator', 'label' => 'Koordinator'],
            ['value' => 'kepala_bagian', 'label' => 'Kepala Bagian'],
            ['value' => 'kepala_produksi', 'label' => 'Kepala Produksi'],
            ['value' => 'kepala_gudang', 'label' => 'Kepala Gudang'],
            ['value' => 'hrd', 'label' => 'HRD / Personalia'],
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'purchasing', 'label' => 'Purchasing'],
            ['value' => 'finance', 'label' => 'Finance'],
            ['value' => 'accounting', 'label' => 'Accounting'],
            ['value' => 'marketing', 'label' => 'Marketing'],
            ['value' => 'sales', 'label' => 'Sales'],
            ['value' => 'operator', 'label' => 'Operator'],
            ['value' => 'teknisi', 'label' => 'Teknisi'],
            ['value' => 'qc', 'label' => 'Quality Control (QC)'],
            ['value' => 'hse', 'label' => 'HSE / K3'],
            ['value' => 'engineering', 'label' => 'Engineering'],
            ['value' => 'it_support', 'label' => 'IT Support'],
            ['value' => 'resepsionis', 'label' => 'Resepsionis'],
            ['value' => 'security', 'label' => 'Security'],

            // Produksi & Operasional
            ['value' => 'foreman', 'label' => 'Foreman / Mandor'],
            ['value' => 'leader_produksi', 'label' => 'Group Leader / Team Leader'],
            ['value' => 'ppic', 'label' => 'PPIC (Production Planning & Inventory Control)'],
            ['value' => 'maintenance', 'label' => 'Maintenance'],
            ['value' => 'toolmaker', 'label' => 'Toolmaker'],
            ['value' => 'welder', 'label' => 'Welder / Juru Las'],
            ['value' => 'fitter', 'label' => 'Fitter'],
            
            // Gudang & Logistik
            ['value' => 'logistic_specialist', 'label' => 'Logistic Specialist'],
            ['value' => 'checker', 'label' => 'Checker'],
            ['value' => 'picker', 'label' => 'Picker / Packer'],
            ['value' => 'forklift_driver', 'label' => 'Operator Forklift'],
            ['value' => 'driver', 'label' => 'Driver / Sopir Logistik'],
            ['value' => 'inventory_admin', 'label' => 'Admin Gudang / Inventory'],

            // Kualitas & Teknis
            ['value' => 'qa', 'label' => 'Quality Assurance (QA)'],
            ['value' => 'lab_analyst', 'label' => 'Laboratorium Analyst'],
            ['value' => 'r_and_d', 'label' => 'Research & Development (R&D)'],
            ['value' => 'draftsman', 'label' => 'Draftsman / CAD Operator'],
            
            // HSE & GA
            ['value' => 'ga', 'label' => 'General Affair (GA)'],
            ['value' => 'environment_officer', 'label' => 'Environment Officer'],
            ['value' => 'paramedik', 'label' => 'Perawat / Paramedik Perusahaan'],
            
            // Komersial & Legal
            ['value' => 'legal_officer', 'label' => 'Legal Officer'],
            ['value' => 'public_relations', 'label' => 'Public Relations / Humas'],
            ['value' => 'procurement', 'label' => 'Procurement Specialist'],
            ['value' => 'tax_officer', 'label' => 'Tax Officer (Perpajakan)'],
            ['value' => 'internal_auditor', 'label' => 'Internal Auditor'],

            // Pendukung
            ['value' => 'office_boy', 'label' => 'Office Boy / Cleaning Service'],
            ['value' => 'driver_operasional', 'label' => 'Driver Operasional'],
            ['value' => 'messenger', 'label' => 'Kurir / Messenger'],
        ];

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'message' => 'Available Jabatan data retrieved successfully',
        ], 200); // ← 200 lebih tepat
    }


    public function convertImg($foto = '', $type = '', $user = '')
    {
        
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/canvasing/';

        // Jika folder belum ada, buat folder
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $success = file_put_contents($destinationPath . $safeName, $file);

        return $safeName;
    }

}