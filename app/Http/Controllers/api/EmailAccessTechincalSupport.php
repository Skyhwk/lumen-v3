<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Models\MasterKaryawan;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Services\InternalMailService;
use Repository;
use DB;

class EmailAccessTechincalSupport extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('master_karyawan')
            ->join('mail_folder_meta as primary_meta', 'master_karyawan.id', '=', 'primary_meta.id_karyawan')
            ->leftJoin('mail_folder_meta as inbox_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'inbox_meta.id_karyawan')
                     ->where('inbox_meta.folder', '=', 'inbox');
            })
            ->leftJoin('mail_folder_meta as outbox_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'outbox_meta.id_karyawan')
                     ->where('outbox_meta.folder', '=', 'outbox');
            })
            ->leftJoin('mail_folder_meta as spam_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'spam_meta.id_karyawan')
                     ->where('spam_meta.folder', '=', 'spam');
            })
            ->leftJoin('mail_folder_meta as trash_meta', function ($join) {
                $join->on('master_karyawan.id', '=', 'trash_meta.id_karyawan')
                     ->where('trash_meta.folder', '=', 'trash');
            })
            ->select([
                'master_karyawan.id as id_karyawan',
                'master_karyawan.nama_lengkap as nama_karyawan',
                'master_karyawan.nik_karyawan',
                'master_karyawan.email',
                'master_karyawan.jabatan',
                'master_karyawan.is_active',
                DB::raw('COALESCE(inbox_meta.total, 0) as inbox_total'),
                DB::raw('COALESCE(outbox_meta.total, 0) as outbox_total'),
                DB::raw('COALESCE(spam_meta.total, 0) as spam_total'),
                DB::raw('COALESCE(trash_meta.total, 0) as trash_total'),
            ])
            ->where(function ($q) {
                $q->where('master_karyawan.is_active', '=', 1)
                  ->orWhere(function ($sub) {
                      $sub->where('master_karyawan.is_active', '=', 0)
                          ->where(function ($sub2) {
                              $sub2->where(DB::raw('COALESCE(inbox_meta.total, 0)'), '>', 0)
                                   ->orWhere(DB::raw('COALESCE(outbox_meta.total, 0)'), '>', 0)
                                   ->orWhere(DB::raw('COALESCE(spam_meta.total, 0)'), '>', 0)
                                   ->orWhere(DB::raw('COALESCE(trash_meta.total, 0)'), '>', 0);
                          });
                  });
            })
            ->groupBy([
                'master_karyawan.id',
                'master_karyawan.nama_lengkap',
                'master_karyawan.nik_karyawan',
                'master_karyawan.email',
                'master_karyawan.jabatan',
                'master_karyawan.is_active',
                'inbox_meta.total',
                'outbox_meta.total',
                'spam_meta.total',
                'trash_meta.total'
            ]);
        return DataTables::of($query)->make(true);
    }

    public function store(Request $request)
    {
        try {
            $idKaryawan = $request->input('id_karyawan');
            if (!$idKaryawan) {
                return response()->json(['message' => 'id_karyawan wajib diisi'], 422);
            }

            $data = $request->except(['id_karyawan']);
            Repository::dir('setting_mail')->key((string) $idKaryawan)->save(json_encode($data));
            InternalMailService::clearAuthBlockFor((int) $idKaryawan, $this->karyawan);
            return response()->json(['message' => 'Setting email berhasil disimpan'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $idKaryawan = $request->input('id_karyawan');
            if (!$idKaryawan) {
                return response()->json(['message' => 'id_karyawan wajib diisi'], 422);
            }

            $data = $request->except(['id_karyawan']);

            // Jika password kosong, ambil password dari data yang sudah pernah disimpan
            if (empty($data['password'])) {
                $existing = Repository::dir('setting_mail')->key((string) $idKaryawan)->get();
                if (!empty($existing)) {
                    $existingData = json_decode($existing, true);
                    $data['password'] = $existingData['password'] ?? '';
                }
            }

            Repository::dir('setting_mail')->key((string) $idKaryawan)->save(json_encode($data));
            InternalMailService::clearAuthBlockFor((int) $idKaryawan, $this->karyawan);
            return response()->json(['message' => 'Setting email berhasil diupdate'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function testconnection(Request $request)
    {
        try {
            $idKaryawan = $request->input('id_karyawan');
            if (!$idKaryawan) {
                return response()->json(['message' => 'id_karyawan wajib diisi'], 422);
            }

            $key = (string) $idKaryawan;
            $data = Repository::dir('setting_mail')->key($key)->get();

            if ($data) {
                $data = json_decode($data, true);
                
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $data['outgoing']['hostname'];
                $mail->SMTPAuth = true;
                $mail->Username = $data['email'];
                $mail->Password = $data['password'];
                $mail->SMTPSecure = $data['outgoing']['connection_security'];
                $mail->Port = $data['outgoing']['port'];
                InternalMailService::configurePhpmailerSslForSettings($mail, $data);

                $mail->setFrom($data['email'], $data['full_name']);
                $mail->addAddress($data['email'], $data['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Test Connection';
                $mail->Body = 'This is a test email to verify your email settings.';

                if ($mail->send()) {
                    InternalMailService::clearAuthBlockFor((int) $idKaryawan, $this->karyawan);
                    return response()->json(['message' => 'Koneksi email berhasil.'], 200);
                } else {
                    return response()->json(['message' => 'Koneksi email gagal.'], 401);
                }
            } else {
                return response()->json(['message' => 'Save terlebih dahulu kemudian lakukan test'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getKaryawan(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)
            ->select('id', 'nama_lengkap', 'email')
            ->orderBy('nama_lengkap', 'asc')
            ->get();
        return response()->json(['message' => 'Data berhasil diambil', 'data' => $data], 200);
    }
}