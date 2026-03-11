<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\{DataKandidat, EmailHistory};
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Services\GenerateMessageHRD;
use App\Services\GenerateMessageWhatsapp;
use App\Services\SendWhatsapp;
use App\Services\SendEmail;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;

class DataKandidatController extends Controller
{
    function konversiHari($hariInggris)
    {
        $hari = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu'
        ];

        return $hari[$hariInggris];
    }

    public function index(Request $request)
    {
        // dd($request->all());
        // $searchYear = isset($request->search) ? date('Y', strtotime($request->search)) : date('Y');

        // $data = DataKandidat::select(
        //     'cabang.*',
        //     'posision.*',
        //     'recruitment.*'
        // )
        //     ->leftJoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
        //     ->leftJoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
        //     ->whereIn('recruitment.id_cabang', $this->privilageCabang)
        //     ->where('recruitment.is_active', true)
        //     ->where('recruitment.flag', 0)
        //     ->where('recruitment.status', 'KANDIDAT')
        //     // ->whereYear('recruitment.created_at', $searchYear)
        //     ->distinct()
        //     ->get();

        $data = DataKandidat::with([
            'cabang:id,nama_cabang',
            'jabatan:id,nama_jabatan'
        ])
            ->whereIn('id_cabang', $this->privilageCabang)
            ->where('is_active', true)
            ->where('flag', 0)
            ->where('status', 'KANDIDAT')
            ->whereYear('created_at', $request->year)
            ->distinct();

        return Datatables::of($data)
            ->filterColumn('cabang.nama_cabang', function ($query, $keyword) {
                $query->whereHas('cabang', function ($q) use ($keyword) {
                    $q->where('nama_cabang', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('jabatan.nama_jabatan', function ($query, $keyword) {
                $query->whereHas('jabatan', function ($q) use ($keyword) {
                    $q->where('nama_jabatan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('nama_lengkap', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->whereDate('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('posisi_di_lamar', function ($query, $keyword) {
                $query->where('posisi_di_lamar', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tempat_lahir', function ($query, $keyword) {
                $query->where('tempat_lahir', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('umur', function ($query, $keyword) {
                $query->where('umur', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function approveKandidatApi(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataKandidat::with([
                'cabang:id,nama_cabang,alamat_cabang',
                'jabatan:id,nama_jabatan'
            ])
                ->whereIn('id_cabang', $this->privilageCabang)
                ->where('id', $request->id)
                ->where('is_active', true)
                ->where('flag', 0)
                ->where('status', 'KANDIDAT')
                ->first();

            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan!.'], 404);
            }
            // dd($data->kode_uniq);
            if($request->status_interview == 'Offline')
            {
                if($data->kode_uniq == null || $data->kode_uniq == ''){
                    dd('masuk');
                    $code = Helper::generateUniqueCode('recruitment','kode_uniq',5);
                    // dd($data);
                    $data->kode_uniq = $code;
                    $data->save();
                }
            }
            

            $date = date('Y-m-d', strtotime($request->tgl_interview));
            $dayName = date('l', strtotime($date));
            $hariIndonesia = self::konversiHari($dayName);
            $tglInter = date('d-m-Y', strtotime($date));

            $dataArray = (object) [
                'nama_lengkap' => $data->nama_lengkap,
                'posisi_di_lamar' => $data->posisi_di_lamar,
                'nama_jabatan' => $data->jabatan->nama_jabatan,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview' => $request->jam_interview,
                'jenis_interview_hrd' => $request->status_interview,
                'link_gmeet_hrd' => $request->link_gmeet_hrd,
                'alamat_cabang' => $data->cabang->alamat_cabang,
                'kode_uniq' => $data->kode_uniq
            ];

            $bodi = GenerateMessageHRD::bodyEmailApproveKandidat($dataArray);

            $email = SendEmail::where('to', $data->email)
                ->where('subject', 'Undangan Interview')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->PassedCandidateSelection();

                $Send = new SendWhatsapp($data->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                $data->status = 'INTERVIEW HRD';
                $data->approve_kandidat_by = $this->karyawan;
                $data->approve_kandidat_at = date('Y-m-d H:i:s');
                $data->tgl_interview = $request->tgl_interview;
                $data->jam_interview = $request->jam_interview;
                $data->jenis_interview_hrd = $request->status_interview;
                $data->link_gmeet_hrd = ($request->link_gmeet_hrd != '') ? $request->link_gmeet_hrd : NULL;
                $data->save();

                DB::commit();
                if ($SendWhatsapp) {
                    return response()->json([
                        'message' => 'Berhasil melakukan approve data recruitment.!',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Berhasil melakukan approve data recruitment akan tetapi whatsapp tidak dapat dikirimkan.!',
                    ], 200);
                }
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Gagal melakukan approve data recruitment. Email tidak dapat dikirimkan!',
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json([
                'message' => 'Terjadi Kesalahan :' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function rejectKandidatApi(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataKandidat::with([
                'cabang:id,nama_cabang,alamat_cabang',
                'jabatan:id,nama_jabatan'
            ])
                ->whereIn('id_cabang', $this->privilageCabang)
                ->where('id', $request->id)
                ->where('is_active', true)
                ->where('flag', 0)
                ->where('status', 'KANDIDAT')
                ->first();

            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan!.'], 404);
            }

            $bodi = GenerateMessageHRD::bodyEmailRejectKandidat($data);

            $email = SendEmail::where('to', $data->email)
                ->where('subject', 'Lamaran Ditolak')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                $data->status = 'REJECT KANDIDAT';
                $data->reject_kandidat_by = $this->karyawan;
                $data->reject_kandidat_at = date('Y-m-d H:i:s');
                $data->is_active = false;
                $data->save();

                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($data);
                $message = $message->RejectedCandidateSelection();

                $Send = new SendWhatsapp($data->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                DB::commit();
                if ($SendWhatsapp) {
                    return response()->json([
                        'message' => 'Berhasil melakukan reject data recruitment.!',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Berhasil melakukan reject data recruitment akan tetapi whatsapp tidak dapat dikirimkan.!',
                    ], 200);
                }
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Gagal melakukan reject data recruitment. Email tidak dapat dikirimkan!',
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Terjadi Kesalahan :' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
