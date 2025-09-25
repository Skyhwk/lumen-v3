<?php

namespace App\Http\Controllers\api;


use App\Models\Rfid;
use App\Models\customer\Users;
use App\Models\customer\Team;
use App\Models\customer\TeamMember;
use App\Models\MasterPelanggan;
use App\Models\GenerateLink;
use App\Models\{MasterKaryawan};

use App\Services\SendEmail;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegistrasiCustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = Users::get();
        foreach($data as &$item) {
            // dump(json_decode($item->id_pelanggan));
            // if($item->id_pelanggan && $item->id_pelanggan != 'null') {

                $item->pelanggan = MasterPelanggan::whereIn('id_pelanggan', json_decode($item->id_pelanggan))->get();
            // }
        };
        // dd($data);
        return datatables()->of($data)->make(true);
    }

    // Refactored -
    public function getCustomer(Request $request)
    {
        $data = MasterPelanggan::where('is_active', true)->select('id_pelanggan', 'nama_pelanggan')->get();
// dd($data);
        return response()->json([
            'message' => 'Customer data displayed successfully',
            'data' => $data,
            'status' => 200,
            'success' => true
        ], 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Users::where('id', '!=', $request->id)->where('email', $request->email)->first();

            if ($data) {
                return response()->json([
                    'message' => 'Email sudah terdaftar',
                    'status' => 400,
                    'success' => false
                ], 400);
            }

            $dataUser = Users::where('id', $request->id)->first();

            if ($dataUser) {
                $dataUser->update([
                    'nama_lengkap' => $request->nama_lengkap,
                    'email' => $request->email,
                    'id_pelanggan' => json_encode($request->id_pelanggan),
                    'password' => $request->password ? Hash::make($request->password) : Hash::make('password'),
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);
            } else {
                $user = new Users();
                $user->nama_lengkap = $request->nama_lengkap;
                $user->email = $request->email;
                $user->password = Hash::make('password');
                $user->id_pelanggan = json_encode($request->id_pelanggan);
                $user->created_by = $this->karyawan;
                $user->created_at = Carbon::now();
                $user->save();

                $portalCustomer = DB::connection('portal_customer');

                $role = $portalCustomer->table('roles')->where('name', 'admin')->first();

                // assign role
                $portalCustomer->table('model_has_roles')->insert([
                    'role_id' => $role->id,
                    'model_type' => 'App\Models\Customer\User',
                    'model_id' => $user->id,
                ]);

                $team = new Team();
                $team->nama_tim = explode("@", $request->email)[0];
                $team->created_by = $this->karyawan;
                $team->created_at = Carbon::now();
                $team->save();

                $teamMember = new TeamMember();
                $teamMember->setConnection('portal_customer');
                $teamMember->team_id = $team->id;
                $teamMember->user_id = $user->id;
                $teamMember->created_by = $this->karyawan;
                $teamMember->created_at = Carbon::now();
                $teamMember->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Data customer berhasil disimpan',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'status' => 500,
                'success' => false
            ], 500);
        }
    }

    public function handleGenerateLink(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = Users::where('id', $request->id)->first();

            if ($header != null) {
                $key = $header->id . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $insertData = [
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $header->id,
                    'quotation_status' => "registrasi",
                    'type' => 'resgistrasi_customer',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    // 'fileName_pdf' => $header->file_lhp,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ];

                $insert = GenerateLink::insertGetId($insertData);

                $header->is_generated = true;
                $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->generated_by = $this->karyawan;
                $header->id_token = $insert;
                $header->expired = Carbon::now()->addYear()->format('Y-m-d');
                $header->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Generate link success!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function handleDeleteUser(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = Users::where('id', $request->id)->first();

            if ($user != null) {
                $user->is_active = false;
                $user->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data customer berhasil dihapus',
                'status' => 200,
                'success' => true
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500,
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '' || isset($request->id)) {
                $data = Users::where('id', $request->id)->update([
                    'is_emailed' => true,
                    'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'emailed_by' => $this->karyawan
                ]);
            }

            $email = SendEmail::where('to',  $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'registrasi', 'type' => 'resgistrasi_customer'])->first();

            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            // return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
            // ganti ke portal.intilab.com
            return response()->json(['link' => 'https://portal.intilab.com/customer/form-online/' . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    public function decrypt($data = null)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
        $data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $extand = explode("|", $data);
        return $extand;
    }

    public function cheklink(Request $request)
    {

        try {
            $generateLink = GenerateLink::where('token', $request->token)
                ->where('key', $request->key)->first();

            // cek apakah link ditemukan
            if (!$generateLink) {
                return response()->json([
                    'message' => 'Link not found',
                    'status' => 404,
                    'success' => false
                ], 404);
            }

            // cek apakah link sudah expired
            if (DATE('Y-m-d') > $generateLink->expired) {
                $link_lama = GenerateLink::where('token', $request->token)
                    ->first();

                DB::table('expired_link_quotation')
                    ->insert([
                        "token" => $link_lama->token,
                        "key" => $link_lama->key,
                        "id_quotation" => $link_lama->id_quotation,
                        "quotation_status" => $link_lama->quotation_status,
                        "expired" => $link_lama->expired,
                        "password" => $link_lama->password,
                        "fileName" => $link_lama->fileName,
                        "fileName_pdf" => $link_lama->fileName_pdf,
                        "type" => $link_lama->type,
                        "created_at" => $link_lama->created_at,
                        "created_by" => $link_lama->created_by,
                        "status" => $link_lama->status,
                        "is_reschedule" => $link_lama->is_reschedule
                    ]);

                $link_lama->delete();
                return response()
                    ->json(['message' => 'link has expired', 'status' => '300'], 300);
            }

            $header = Users::where('id', $generateLink->id_quotation)->first();
            $pelangganId = json_decode($header->id_pelanggan);
            // ambil perusahaan
            $tempPerushaan = [];
            // get pelanggan
            $namaPelanggan =MasterPelanggan::select('id_pelanggan','nama_pelanggan')->whereIn('id_pelanggan', $pelangganId)->get();
            //karywan
            $pic = MasterKaryawan::select('nama_lengkap','email','no_telpon')->where('nama_lengkap', $header->created_by)->first();
            foreach ($namaPelanggan as $key => $value) {
                $tempPerushaan[] = $value->nama_pelanggan .' ['.$value->id_pelanggan.']';
            }
            $tempResult = array_unique($tempPerushaan);
            $header->perusahaan = $tempResult;

            return response()
                ->json(
                    [
                        'email' => $header->email,
                        'message' => 'data hasbenn show',
                        'perusahaan' => $header->perusahaan,
                        'is_cheklist' => $header->is_cheklist,
                        'data_user'=>$header,
                        'dataaja'=>$header->created_by,
                        'pic'=>$pic
                    ],
                    200
                );
        } catch (\Exception $ex) {
            //throw $th;
        }
    }
}
