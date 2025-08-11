<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Repository;

class SettingMailController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = Repository::dir('setting_mail')->key($this->karyawan)->get();
            return response()->json(['data' => $data, 'message' => 'Data berhasil diambil'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->all();
            Repository::dir('setting_mail')->key($this->karyawan)->save(json_encode($data));
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function testconnection(Request $request)
    {
        try {
            $data = Repository::dir('setting_mail')->key($this->karyawan)->get();

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

                $mail->setFrom($data['email'], $data['full_name']);
                $mail->addAddress($data['email'], $data['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Test Connection';
                $mail->Body = 'This is a test email to verify your email settings.';

                if ($mail->send()) {
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
