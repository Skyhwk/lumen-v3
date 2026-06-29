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

class EmailAccessTechincalSupport extends Controller
{
    public function index(Request $request)
    {
        // Mengambil data karyawan beserta relasi usernya
        $data = MasterKaryawan::with(['user'])->where('is_active', true);
        
        return Datatables::of($data)
            ->make(true);
    }

    public function update(Request $request)
    {
        try {
            $data = $request->all();
            Repository::dir('setting_mail')->key((string) $this->user_id)->save(json_encode($data));
            InternalMailService::clearAuthBlockFor((int) $this->user_id, $this->karyawan);
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function testconnection(Request $request)
    {
        try {
            $key = (string) $this->user_id;
            $data = Repository::dir('setting_mail')->key($key)->get();
            if (empty($data) && $this->karyawan) {
                $data = Repository::dir('setting_mail')->key($this->karyawan)->get();
            }

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
                    InternalMailService::clearAuthBlockFor((int) $this->user_id, $this->karyawan);
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
}