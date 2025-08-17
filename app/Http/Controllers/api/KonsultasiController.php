<?php

namespace App\Http\Controllers\api;

use App\Models\Lemburan;
use App\Models\{FormHeader, FormDetail};
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\Konsul;
use App\Models\KonsulRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;



class KonsultasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = Konsul::on('android_intilab')
                ->with(['konsulRoom', 'user.department'])
                ->whereIn('status', [0, 1])->get();

            return Datatables::of($data)->make(true);
        } catch (Exception $ex) {
            return response()->json([
                "message" => $ex->getMessage(),
                "line" => $ex->getLine()
            ], 402);
        }
    }

    public function approve(Request $request)
    {
        try {
            $data = Konsul::on('android_intilab')
                ->where('id', $request->consulId)
                ->first();

            if ($data !== null) {
                if ($data->status == 0) {
                    $data->update(["status" => 1]);
                    KonsulRoom::on('android_intilab')->create([
                        'consule_id' => $data->id,
                        'hrd_id' => $this->userid,
                        'user_id' => $data->user_id
                    ]);
                    /* next step notif */
                    // if ($data->type == 'online') {
                    //     // notif to mobile
                    //     $response = Http::post('https://apps.intilab.com/v4/public/api/notif', [
                    //         'user_id' => $data->user_id,
                    //         'title' => 'Konsultasi di prosess',
                    //         'body' => 'Silahkan Menunggu Kabar Selanjutnya dari HRD, Pastikan Cek Email Terimakasih',
                    //     ]);
                    //     // notif dan email
                    // } else {
                    //     // notif
                    //     // notif to mobile
                    //     $response = Http::post('https://apps.intilab.com/v4/public/api/notif', [
                    //         'user_id' => $data->user_id,
                    //         'title' => 'Konsultasi di prosess',
                    //         'body' => 'Silahkan Menunggu Kabar Selanjutnya dari HRD, Pastikan Cek Email Terimakasih',
                    //     ]);
                    // }
                    return response()->json(["message" => "Ruangan Konsul Sudah Siap"], 200);
                } else {
                    return response()->json(["message" => "Konsultasi sudah disetujui sebelumnya"], 400);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Error: " . $e->getMessage()
            ], 500);
        }
    }

    public function room(Request $request)
    {
        try {
            $modelRoom = KonsulRoom::on('android_intilab')
                ->with('konsul')
                ->where('consule_id', $request->consulId)
                ->first();

            if ($modelRoom === null) {
                return response()->json([
                    "success" => false,
                    "message" => "Room tidak ditemukan, periksa kembali data user"
                ], 404);
            }

            try {
                $modelRoom->update([
                    'keluhan' => $request->paramBody['keluhan'],
                    'solusi' => $request->paramBody['solusi'],
                    'resume' => $request->paramBody['kesimpulan'],
                    'status' => true
                ]);

                // notif to mobile
                // $response = Http::post('https://apps.intilab.com/v4/public/api/notif', [
                //     'user_id' => $modelRoom->konsul->user_id,
                //     'title' => 'Konsultasi Room',
                //     'body' => 'Sesi Konsultasi Selesai, Terimakasih Sudah Menggunakan Layanan Kami',
                // ]);
                return response()->json([
                    "success" => true,
                    "message" => "Room diskusi berhasil disimpan"
                ], 200);

            } catch (Exception $e) {
                return response()->json([
                    "success" => false,
                    "message" => "Gagal menyimpan data: " . $e->getMessage()
                ], 500);
            }
        } catch (Exception $ex) {
            return response()->json([
                "message" => $ex->getMessage(),
                "line" => $ex->getLine(),
                "file" => $ex->getFile()
            ], 500);
        }
    }

    public function void(Request $request)
    {
        try {
            $data = Konsul::on('android_intilab')
                ->where('id', $request->consulId)
                ->first();

            $data->update([
                'status' => 2,
                'ket_reject' => $request->paramBody
            ]);
            // notif to mobile
            // $response = Http::post('https://apps.intilab.com/v4/public/api/notif', [
            //     'user_id' => $data->user_id,
            //     'title' => 'Konsultasi Room',
            //     'body' => $request->paramBody,
            // ]);
            return response()->json([
                "success" => true,
                "message" => "Data berhasil direject"
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Gagal reject data: " . $e->getMessage()
            ], 500);
        }
    }
}