<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

use App\Models\IntilabInternal\AttendanceCorrections;

class AttendanceCorrectionsController extends Controller
{
    public function index(Request $request) 
    {
        $data = AttendanceCorrections::with('karyawan');

        if ($request->has('status')) {
            $data->where('status', $request->status);
        }
        
        return datatables()->of($data)->make(true);
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            $correction = AttendanceCorrections::find($request->id);
            if (!$correction) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            // Get HRD user name from BaseController
            $hrdName = $this->karyawan ?? 'HRD';
            
            $statusAbsensi = ($correction->type == 'Check In') ? 'masuk' : 'keluar';

            // Check if absensi exists
            $absensi = DB::table('absensi')
                ->where('karyawan_id', $correction->employee_id)
                ->where('tanggal', $correction->date)
                ->where('status', $statusAbsensi)
                ->first();

            if ($absensi) {
                // Update existing absensi
                DB::table('absensi')
                    ->where('id', $absensi->id)
                    ->update(['jam' => $correction->time]);
            } else {
                // Insert new absensi
                $hari_array = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'];
                $hari = $hari_array[date('D', strtotime($correction->date))] ?? 'Tidak di ketahui';

                DB::table('absensi')->insert([
                    'karyawan_id' => $correction->employee_id,
                    'hari' => $hari,
                    'tanggal' => $correction->date,
                    'jam' => $correction->time,
                    'status' => $statusAbsensi,
                    'kode_kartu' => null,
                    'kode_mesin' => null
                ]);
            }

            // Update correction status
            $correction->approved_hrd_by = $hrdName;
            $correction->approved_hrd_at = date('Y-m-d H:i:s');
            $correction->status = 'Approved HRD';
            $correction->save();

            DB::commit();
            return response()->json(['message' => 'Absensi berhasil di-approve & diupdate'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request)
    {
        try {
            $correction = AttendanceCorrections::find($request->id);
            if (!$correction) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            // Get HRD user name from BaseController
            $hrdName = $this->karyawan ?? 'HRD';

            $correction->rejected_hrd_by = $hrdName;
            $correction->rejected_hrd_at = date('Y-m-d H:i:s');
            $correction->reject_hrd_reason = $request->reason;
            $correction->status = 'Rejected HRD';
            $correction->save();

            return response()->json(['message' => 'Koreksi absensi berhasil di-reject'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }
}