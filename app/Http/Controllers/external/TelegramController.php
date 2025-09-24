<?php
namespace App\Http\Controllers\external;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SevenEcks\Tableify\Tableify;
use App\Models\MasterKaryawan;
use Illuminate\Support\Facades\Http;
use Bluerhinos\phpMQTT;
use Carbon\Carbon;

class TelegramController extends Controller
{
    protected $tele_it;

    public function __construct()
    {
        $this->tele_it = ['680526259', '1342214372', '158724236']; //asep belum masuk , '158724236' pak eko , '1405440715' punya kojok
    }

    public function mqtt($data){
        $mqtt = new phpMQTT('apps.intilab.com', '1883', 'Admin');
        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/resource/set-manage', $data, 0);
            $mqtt->close();
            return true;
        } else {
            return false;
        }
    }

    public function setWebhook()
    {   

        $response = Telegram::setWebhook(['url'=>env('TELEGRAM_WEBHOOK_URL')]);
        return response()->json(['status' => true, 'message' => 'Webhook berhasil di set'], 200);
    }

    public function reloadWebhook()
    {
        $response = Http::post("https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/setWebhook",
            [
            'drop_pending_updates' => true,
            'url' => ''
            ]
        );
        if($response->status() == 200){
            $response = Telegram::setWebhook(['url'=>env('TELEGRAM_WEBHOOK_URL')]);
            return response()->json(['status' => true, 'message' => 'Webhook berhasil di reload'], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Webhook gagal di reload'], 200);
        }
    }

    public function commandHandlerWebHook()
    {
        Telegram::commandsHandler(true);
        $updates = Telegram::getWebhookUpdates(true);;
        if ($updates->isType('callback_query')){
            $query = $updates->getCallbackQuery();
            $id_message = $query->getMessage()->getMessageId();
            $chat_id = $query->getMessage()->getChat()->getId();
            $data  = $query->getData();
            
            DB::table('tele_chat')->insert(['text' => json_encode($query), 'pin_from' => $chat_id, 'pin_to' => '6269330028', 'message_id' => $id_message, 'message' => $data]);
            self::callback($query, $data);
        } else if ($updates->isType('message')) {
            // FROM IDENTITY 
            // $chat_id = $updates->getChat()->getId();
            // $username = $updates->getChat()->getUserName();
            // $firstName = $updates->getChat()->getFirstName();
            // $lastName = $updates->getChat()->getLastName();
            // $fullName = $firstName." ".$lastName;
            // // END FROM IDENTITY
    
            // $message = \strtolower($updates->getMessage()->getText());
            $messageObj = $updates->getMessage();
            $chat_id = $messageObj->getChat()->getId();
            $username = $messageObj->getChat()->getUsername();
            $firstName = $messageObj->getChat()->getFirstName();
            $lastName = $messageObj->getChat()->getLastName();
            $fullName = trim($firstName . ' ' . $lastName);
            $message = strtolower($messageObj->getText());
            $id_message = $messageObj->getMessageId();
    
            DB::table('tele_chat')->insert(['text' => json_encode($updates), 'pin_from' => $chat_id, 'pin_to' => '6269330028', 'message_id' => $id_message, 'message' => $message]);
    
    
            if($message == 'halo' || $message == 'hai' || $message == 'hi' || $message == 'hii' || $message == 'hiii' || $message == 'hallo' || $message == 'hello' || $message == 'hey' || $message == 'hei'){
                self::sendTo($chat_id, "Haloo.., apa kabar $fullName ?");
            } else if ($message == '/start'){
                $keyboard = Keyboard::make()
                ->inline()
                ->row(
                    Keyboard::inlineButton(['text' => 'SUDAH', 'callback_data' => '@sudah']),
                    Keyboard::inlineButton(['text' => 'BELUM', 'callback_data' => '@belum']),
                );

                self::sendTo($chat_id, 'Apakah Anda Sudah Melakukan Registrasi Secara Mandiri.?', $keyboard);
            } else if ($message == 'menu'){
                if(in_array($chat_id, ['6839033468', '680526259'])){
                    $keyboard = Keyboard::make()
                    ->inline()
                    ->row(Keyboard::inlineButton(['text' => 'ABSENSI', 'callback_data' => 'absensi']))
                    ->row(Keyboard::inlineButton(['text' => 'PENGAMBILAN BARANG', 'callback_data' => 'pengambilan_barang']))
                    ->row(Keyboard::inlineButton(['text' => 'DUKUNGAN IT', 'callback_data' => 'dukungan_it']))
                    ->row(Keyboard::inlineButton(['text' => 'ABSEN', 'callback_data' => '#absen']))
                    ->row(Keyboard::inlineButton(['text' => 'OPEN DOOR', 'callback_data' => '#open_door']))
                    ->row(Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']));
                } else {
                    $keyboard = Keyboard::make()
                    ->inline()
                    ->row(Keyboard::inlineButton(['text' => 'ABSENSI', 'callback_data' => 'absensi']))
                    ->row(Keyboard::inlineButton(['text' => 'PENGAMBILAN BARANG', 'callback_data' => 'pengambilan_barang']))
                    // ->row(Keyboard::inlineButton(['text' => 'JADWAL SAMPLING', 'callback_data' => 'jadwal_sampling']))
                    // ->row(Keyboard::inlineButton(['text' => 'PENGAJUAN CUTI', 'callback_data' => 'pengajuan_cuti']))
                    ->row(Keyboard::inlineButton(['text' => 'DUKUNGAN IT', 'callback_data' => 'dukungan_it']))
                    ->row(Keyboard::inlineButton(['text' => 'ABSEN', 'callback_data' => '#absen']))
                    ->row(Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']));
                }
                self::sendTo($chat_id, 'Silahkan Pilih Menu Di Bawah Ini : ', $keyboard);
            } else if (str_contains($message, '@')){
                if(str_contains($message, 'gmail')){
                    self::sendTo($chat_id, 'Masukan email perusahaan @intilab.com');
                } else {
                    $data = MasterKaryawan::where('email', $message)->where('is_active', 1)->first();

                    if($data == NULL){
                        self::sendTo($chat_id, 'Maaf.., Anda belum teregistrasi kedalam system PT INTI SURYA LABORATORIUM, Silahkan hubungi bagian IT Program');
                    } else {
                        if($data->pin_user!=NULL){
                            $posisi = DB::table('master_jabatan')->where('id', $data->id_jabatan)->first();
                            $txt = "anda sudah terdaftar sebagai : \n 1. Nama : $data->nama_lengkap \n 2. Jabatan : $posisi->nama_jabatan \n 3. Email : $data->email \n 4. No tlp : $data->no_tlpon";

                            self::sendTo($chat_id, $txt);
                        } else {
                            $data->pin_user = $chat_id;
                            $data->save();
                            $txt = "\n silahkan tulis <b>menu</b> dan enter";
                            self::sendTo($chat_id, 'Terimakasih sudah registrasi secara mandiri'.$txt );
                        }
                    }
                }
            } else if ($message == 'baik' || $message == 'good' || $message == 'sehat' || $message == 'luar biasa' || $message == 'mantap'){
                self::sendTo($chat_id, "Sayapun demikian, semoga anda selalu dalam lindungan TUHAN yang maha KUASA. \n Silahkan ketik Menu");
            } else {
                self::customReply($messageObj);
            }

        }
    }

    public function callback($query, $respons){
        $chat_id = $query->getMessage()->getChat()->getId();
        $message_id = $query->getMessage()->getMessageId();

        if($respons == '@sudah'){
            self::update($chat_id, $message_id, "Terimakasih, anda dapat melanjutkan dengan menulis menu untuk menampilkan list fitur telegram INTILAB");
        } else if ($respons == '@belum'){
            self::update($chat_id, $message_id, 'Silahkan Masukan Email dengan Domain @intilab.com');
        } else if ($respons == 'destroy_message'){
            self::delete($chat_id, $message_id);
        } else if ($respons == 'menu'){
            if($chat_id == '6839033468'){
                $keyboard = Keyboard::make()
                ->inline()
                ->row(Keyboard::inlineButton(['text' => 'ABSENSI', 'callback_data' => 'absensi']))
                ->row(Keyboard::inlineButton(['text' => 'PENGAMBILAN BARANG', 'callback_data' => 'pengambilan_barang']))
                ->row(Keyboard::inlineButton(['text' => 'DUKUNGAN IT', 'callback_data' => 'dukungan_it']))
                ->row(Keyboard::inlineButton(['text' => 'ABSEN', 'callback_data' => '#absen']))
                ->row(Keyboard::inlineButton(['text' => 'OPEN DOOR', 'callback_data' => '#open_door']))
                ->row(Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']));
            } else {
                $keyboard = Keyboard::make()
                ->inline()
                ->row(Keyboard::inlineButton(['text' => 'ABSENSI', 'callback_data' => 'absensi']))
                ->row(Keyboard::inlineButton(['text' => 'PENGAMBILAN BARANG', 'callback_data' => 'pengambilan_barang']))
                // ->row(Keyboard::inlineButton(['text' => 'JADWAL SAMPLING', 'callback_data' => 'jadwal_sampling']))
                // ->row(Keyboard::inlineButton(['text' => 'PENGAJUAN CUTI', 'callback_data' => 'pengajuan_cuti']))
                ->row(Keyboard::inlineButton(['text' => 'DUKUNGAN IT', 'callback_data' => 'dukungan_it']))
                ->row(Keyboard::inlineButton(['text' => 'ABSEN', 'callback_data' => '#absen']))
                ->row(Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']));
            }
            self::update($chat_id, $message_id, 'Silahkan Pilih Menu Di Bawah Ini : ', $keyboard);

        } else if ($respons == 'absensi'){
            $keyboard = Keyboard::make()
                ->inline()
                ->row(
                    Keyboard::inlineButton(['text' => self::Bulan(Carbon::now()->subMonth(2)->format('Y-m')), 'callback_data' => Carbon::now()->subMonth(2)->format('Y-m')]),
                )->row(
                    Keyboard::inlineButton(['text' => self::Bulan(Carbon::now()->subMonth(1)->format('Y-m')), 'callback_data' => Carbon::now()->subMonth(1)->format('Y-m')]),
                )->row(
                    Keyboard::inlineButton(['text' => self::Bulan(Carbon::now()->format('Y-m')), 'callback_data' => Carbon::now()->format('Y-m')]),
                );
            self::update($chat_id, $message_id, 'Silahkan Pilih Bulan diBawah ini :', $keyboard);
        } else if($respons == Carbon::now()->subMonth(2)->format('Y-m')){
            self::update($chat_id, $message_id, self::getAbsen(Carbon::now()->subMonth(2)->format('Y-m'), $chat_id));
        } else if($respons == Carbon::now()->subMonth(1)->format('Y-m')){
            self::update($chat_id, $message_id, self::getAbsen(Carbon::now()->subMonth(1)->format('Y-m'), $chat_id));
        } else if($respons == Carbon::now()->format('Y-m')){
            self::update($chat_id, $message_id, self::getAbsen(Carbon::now()->format('Y-m'), $chat_id));
        } else if ($respons == 'pengambilan_barang'){
            $data = MasterKaryawan::where('pin_user', $chat_id)->where('is_active', 1)->first();
            if($data!=null){
                $token = microtime(true);
                $body = [
                    'token' => $token,
                    'user_id' => $data->id,
                    'create_date' => DATE('Y-m-d H:i:s')
                ];
                DB::table('link_extend')->insert($body);
                
                $keyboard = Keyboard::make()
                    ->inline()
                    ->row(  
                        Keyboard::inlineButton(['text' => 'Klik Untuk Melanjutkan', 'url' => 'https://portal.intilab.com/public/pengambilan_barang/'.$token]),
                    );
                self::update($chat_id, $message_id, 'Silahkan klik button dibawah untuk melakukan pengisisan permintaan.', $keyboard);
            }
        } else if ($respons == 'jadwal_sampling'){
            self::update($chat_id, $message_id, 'Maaf,. Untuk sementara fitur belum tersedia');
        } else if ($respons == 'pengajuan_cuti'){
            self::update($chat_id, $message_id, 'Maaf,. Untuk sementara fitur belum tersedia');
        } else if (str_contains($respons, '$')){ //handle permintaan barang
            $status = explode('-', $respons)[1];
            $idReq = explode('-', $respons)[2];
            $db = explode('-', $respons)[3];
            if($status == 'sudah'){
                DB::table('record_permintaan_barang')->where('id', $idReq)->update(['submited' => 1]);
                self::update($chat_id, $message_id, 'Transaction Success.!');
            } else if($status == 'belum'){
                $newTime = date("Y-m-d H:i:s",strtotime("+15 minutes"));
                self::update($chat_id, $message_id, 'Request sedang di teruskan ke bagian terkait');
                DB::table('record_permintaan_barang')->where('id', $idReq)->update(['process_time' => $newTime, 'reminder' => 0]);
            }
        }  else if (str_contains($respons, 'dukungan_it')){
            $cek = \explode("-", $respons);
            if(count($cek) > 1){
                if(count($cek) == 2){
                    if($cek[1] == 'keluhan'){
                        $keyboard = Keyboard::make()
                        ->inline()
                        ->row(
                            Keyboard::inlineButton(['text' => 'JARINGAN', 'callback_data' => 'dukungan_it-keluhan-jaringan']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'KOMPUTER', 'callback_data' => 'dukungan_it-keluhan-komputer']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'PRINTER', 'callback_data' => 'dukungan_it-keluhan-printer']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'SERVER', 'callback_data' => 'dukungan_it-keluhan-server']),
                        )->row(
                            Keyboard::inlineButton(['text' => '<< BACK', 'callback_data' => 'dukungan_it']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']),
                        );
                        self::update($chat_id, $message_id, 'Silahkan Pilih Keluhan Di Bawah Ini : ', $keyboard);
                    } else if ($cek[1] == 'laporan'){
                        $keyboard = Keyboard::make()
                        ->inline()
                        ->row(
                            Keyboard::inlineButton(['text' => 'LISTRIK', 'callback_data' => 'dukungan_it-laporan-listrik']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'AC', 'callback_data' => 'dukungan_it-laporan-ac']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'ACCESS DOOR', 'callback_data' => 'dukungan_it-laporan-access_door']),
                        )->row(
                            Keyboard::inlineButton(['text' => '<< BACK', 'callback_data' => 'dukungan_it']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']),
                        );
                        self::update($chat_id, $message_id, 'Silahkan Pilih Laporan Di Bawah Ini : ', $keyboard);
                    } else if ($cek[1] == 'permohonan'){
                        $keyboard = Keyboard::make()
                        ->inline()
                        ->row(
                            Keyboard::inlineButton(['text' => 'PEMINJAMAN ALAT', 'callback_data' => 'dukungan_it-permohonan-peminjaman alat']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'LAIN-LAIN', 'callback_data' => 'dukungan_it-permohonan-lain lain']),
                        )->row(
                            Keyboard::inlineButton(['text' => '<< BACK', 'callback_data' => 'dukungan_it']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']),
                        );
                        self::update($chat_id, $message_id, 'Silahkan Pilih Laporan Di Bawah Ini : ', $keyboard);
                    }
                } else if(count($cek) > 2){
                    self::update($chat_id, $message_id, 'Silahkan Ketikan Kendala yang anda alami :');
                }
            } else {
                $keyboard = Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton(['text' => 'KELUHAN', 'callback_data' => 'dukungan_it-keluhan']),
                    )->row(
                        Keyboard::inlineButton(['text' => 'LAPORAN', 'callback_data' => 'dukungan_it-laporan']),
                    )->row(
                        Keyboard::inlineButton(['text' => 'PERMOHONAN', 'callback_data' => 'dukungan_it-permohonan']),
                    )->row(
                        Keyboard::inlineButton(['text' => '<< BACK', 'callback_data' => 'menu']),
                    )->row(
                        Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']),
                    );
                    self::update($chat_id, $message_id, 'Silahkan Pilih Dukungan Di Bawah Ini : ', $keyboard);
            }
        } else if (str_contains($respons, 'proses_it')){
            $seq = explode("-", $respons);
            
            if(count($seq) == 2){
                $id = $seq[1];
                $cross = DB::table('table_support')->where('id', $id)->first();
                $cekNama = MasterKaryawan::where('pin_user', $chat_id)->where('is_active', 1)->first();

                $request_id = $cross->request_id;
                $tele_tim = $this->tele_it;
                $date = DATE('Y-m-d H:i:s');

                $waktu = DATE('Y-m-d H:i:s', strtotime('+7 hours', strtotime($date)));
                $support = "Request-ID : $request_id \n";
                $support .= "Waktu Process : $waktu \n";
                $support .= "Status : Process \n \n";

                $array[0] = ['Request By', $cross->request_by];
                $array[1] = ['Divisi', $cross->divisi];
                $array[2] = ['Kategori', strtoupper($cross->kategori)];
                $array[3] = ['Topic', strtoupper($cross->sub_kategori)];

                $table = Tableify::new($array);
                $table = $table->make();
                $table_data = $table->toArray();
                foreach ($table_data as $row) {
                    $support .= $row . "\n";
                }

                $support .= "\nKeterangan :\n";
                $support .= $cross->response;

                $html = $support;
                if(strtoupper($cross->kategori) == 'PERMOHONAN'){
                    
                    $html .= "\n\nRequest and sudah diprosess oleh $cekNama->nama_lengkap.";
                    $html = "<pre>" . $html . "</pre>";
                } else if(strtoupper($cross->kategori) == 'KELUHAN'){
                    
                    $html .= "\n\nTim IT Support a/n $cekNama->nama_lengkap Sedang menuju lokasi anda.";
                    $html = "<pre>" . $html . "</pre>";
                } else if(strtoupper($cross->kategori) == 'LAPORAN'){
                    
                    $html .= "\n\nLaporan anda sudah diterima oleh $cekNama->nama_lengkap.";
                    $html = "<pre>" . $html . "</pre>";
                }
                    
                
                self::sendTo($cross->pin_user, $html);
                $html = '';
                foreach ($tele_tim as $key => $value) {
                    $mess = '';
                    $xl = '';
                    if($value == $chat_id){
                        $update = [
                            'handle_by' => $cekNama->nama_lengkap,
                            'handle_at' => $date,
                            'status' => 'proses'
                        ];
                        
                        $keyboard = Keyboard::make()
                        ->inline()
                        ->row(
                            Keyboard::inlineButton(['text' => 'SOLVE', 'callback_data' => 'proses_it-'.$id.'-solve']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'PENDING', 'callback_data' => 'proses_it-'.$id.'-pending']),
                        );
                        
                        DB::table('table_support')->where('id', $id)->update($update);
                        
                        $xl = "<pre>" . $support . "</pre>";
                        self::update($chat_id, $message_id, $xl, $keyboard);
                    } else {
                        
                        $search = DB::table('tele_chat')->where('pin_to', $value)->where('uniq', $request_id)->first();
                        $mess = $support;
                        $pukul = DATE('Y-m-d H:i:s', strtotime( '+7 hours', strtotime($date)));
                        $mess .= "\nTelah diprosess oleh $cekNama->nama_lengkap pukul $pukul";
                        $mess = "<pre>" . $mess . "</pre>";

                        self::update($value, $search->message_id, $mess);
                    }
                }

                
            } else if(count($seq) == 3){
                if($seq[2] == 'solve'){
                    $id = $seq[1];
                    $cross = DB::table('table_support')->where('id', $id)->first();
                    $cekNama = MasterKaryawan::where('pin_user', $chat_id)->where('is_active', 1)->first();

                    $request_id = $cross->request_id;
                    $tele_tim = $this->tele_it;
                    $date = DATE('Y-m-d H:i:s');

                    $waktu = DATE('Y-m-d H:i:s', strtotime('+7 hours', strtotime($date)));
                    $support = "Request-ID : $request_id \n";
                    $support .= "Waktu Solve : $waktu \n";
                    $support .= "Status : Solve \n \n";

                    $array[0] = ['Request By', $cross->request_by];
                    $array[1] = ['Divisi', $cross->divisi];
                    $array[2] = ['Kategori', strtoupper($cross->kategori)];
                    $array[3] = ['Topic', strtoupper($cross->sub_kategori)];
                    $array[4] = ['Dikerjakan', $cross->handle_by];

                    $table = Tableify::new($array);
                    $table = $table->make();
                    $table_data = $table->toArray();
                    foreach ($table_data as $row) {
                        $support .= $row . "\n";
                    }

                    $support .= "\nKeterangan :\n";
                    $support .= $cross->response;
                    $html = "<pre>" . $support . "</pre>";
                    $html .= "\n\nApakah masalah atau kendala yang anda alami sudah teratasi.?";
                    
                    $keyboard = Keyboard::make()
                        ->inline()
                        ->row(
                            Keyboard::inlineButton(['text' => 'SUDAH', 'callback_data' => 'proses_it-'.$id.'-sudah']),
                        )->row(
                            Keyboard::inlineButton(['text' => 'BELUM', 'callback_data' => 'proses_it-'.$id.'-belum']),
                        );
                        
                    self::sendTo($cross->pin_user, $html, $keyboard);
                    
                    foreach ($tele_tim as $key => $value) {
                        $mess = '';
                        $xl = '';
                        if($value == $chat_id){
                            DB::table('table_support')->where('id', $id)->update(['status' => 'solve']);
                            $xl = "<pre>" . $support . "</pre>";
                            self::update($value, $message_id, $xl);
                        } else {
                            $search = DB::table('tele_chat')->where('pin_to', $value)->where('uniq', $request_id)->first();
                            $mess = $support;
                            $pukul = DATE('Y-m-d H:i:s', strtotime( '+7 hours', strtotime($date)));
                            $mess .= "\nTelah diSelesaikan oleh $cekNama->nama_lengkap pukul $pukul";
                            $mess = "<pre>" . $mess . "</pre>";
                            self::update($value, $search->message_id, $mess);
                        }
                    }

                } else if ($seq[2] == 'pending'){
                    $id = $seq[1];
                    $cross = DB::table('table_support')->where('id', $id)->first();
                    $cekNama = MasterKaryawan::where('pin_user', $chat_id)->where('is_active', 1)->first();

                    $request_id = $cross->request_id;
                    $tele_tim = $this->tele_it;
                    $date = DATE('Y-m-d H:i:s');

                    $waktu = DATE('Y-m-d H:i:s', strtotime('+7 hours', strtotime($date)));
                    $support = "Request-ID : $request_id \n";
                    $support .= "Waktu Pending : $waktu \n";
                    $support .= "Status : Pending \n \n";

                    $array[0] = ['Request By', $cross->request_by];
                    $array[1] = ['Divisi', $cross->divisi];
                    $array[2] = ['Kategori', strtoupper($cross->kategori)];
                    $array[3] = ['Topic', strtoupper($cross->sub_kategori)];
                    $array[4] = ['Dikerjakan', $cross->handle_by];

                    $table = Tableify::new($array);
                    $table = $table->make();
                    $table_data = $table->toArray();
                    foreach ($table_data as $row) {
                        $support .= $row . "\n";
                    }

                    $support .= "\nKeterangan :\n";
                    $support .= $cross->response;
                    $html = "<pre>" . $support . "</pre>";
                    $html .= "\n\nMohon maaf untuk sementara request-ID anda di pending oleh tim IT Support";
                    
                    self::sendTo($cross->pin_user, $html);
                    $keyboard = Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton(['text' => 'PROSESS', 'callback_data' => 'proses_it-'.$id]),
                    );
                    foreach ($tele_tim as $key => $value) {
                        if($value == $chat_id){
                            $keyboard1 = Keyboard::make()
                            ->inline()
                            ->row(
                                Keyboard::inlineButton(['text' => 'PROSESS', 'callback_data' => 'proses_it-'.$id]),
                            );
                            // 'waiting','proses','pending','solve','done'
                            DB::table('table_support')->where('id', $id)->update(['status' => 'pending']);
                            $xl = "<pre>" . $support . "</pre>";
                            self::update($chat_id, $message_id, $xl, $keyboard1);
                        } else {
                            $search = DB::table('tele_chat')->where('pin_to', $value)->where('uniq', $request_id)->first();
                            $mess = $support;
                            $mess .= "\nTelah diPending oleh $cekNama->nama_lengkap pukul $waktu";
                            $mess = "<pre>" . $mess . "</pre>";
                            self::update($value, $search->message_id, $mess, $keyboard);

                        }
                    }
                } else if ($seq[2] == 'sudah'){
                    $id = $seq[1];
                    $cross = DB::table('table_support')->where('id', $id)->first();
                    $cekNama = MasterKaryawan::where('pin_user', $chat_id)->where('is_active', 1)->first();

                    $request_id = $cross->request_id;
                    $tele_tim = $this->tele_it;
                    $date = DATE('Y-m-d H:i:s');

                    $waktu = DATE('Y-m-d H:i:s', strtotime('+7 hours', strtotime($date)));
                    $support = "Request-ID : $request_id \n";
                    // $support .= "Waktu Request : $waktu \n";
                    $support .= "Status : Done \n \n";

                    $array[0] = ['Request By', $cross->request_by];
                    $array[1] = ['Divisi', $cross->divisi];
                    $array[2] = ['Kategori', strtoupper($cross->kategori)];
                    $array[3] = ['Topic', strtoupper($cross->sub_kategori)];
                    $array[4] = ['Dikerjakan', $cross->handle_by];

                    $table = Tableify::new($array);
                    $table = $table->make();
                    $table_data = $table->toArray();
                    foreach ($table_data as $row) {
                        $support .= $row . "\n";
                    }

                    $support .= "\nKeterangan :\n";
                    $support .= $cross->response;
                    $html = "<pre>" . $support . "</pre>";
                    
                    self::update($cross->pin_user, $message_id, $html);
                    $update = [
                        'status' => 'done'
                    ];
                    
                    DB::table('table_support')->where('id', $id)->update($update);

                    foreach ($tele_tim as $key => $value) {
                            $xl = $support;
                            $search = DB::table('tele_chat')->where('pin_to', $value)->where('uniq', $request_id)->first();
                            
                            $pukul = DATE('Y-m-d H:i:s', strtotime( '+7 hours', strtotime($date)));
                            $xl .= "\nTelah diSelesaikan oleh $cross->request_by pukul $pukul";
                            $xl = "<pre>" . $xl . "</pre>";
                            self::update($value, $search->message_id, $xl);
                        
                    }
                }
            }
        } else if (str_contains($respons, 'cancel_it')){
            $seq = explode("-", $respons);
            if(count($seq) == 2){
                $id = $seq[1];
                $cross = DB::table('table_support')->where('id', $id)->first();
                $request_id = $cross->request_id;
                DB::table('table_support')->where('id', $id)->delete();

                self::delete($cross->pin_user, $message_id);
                $tele_tim = $this->tele_it;
                foreach ($tele_tim as $key => $value) {
                    $search = DB::table('tele_chat')->where('pin_to', $value)->where('uniq', $request_id)->first();
                    self::delete($value, $search->message_id);
                }
            }
        } else if (str_contains($respons, '#absen')){
            // $txt = json_encode($query);
            self::update($chat_id, $message_id, 'Silahkan Kirimkan Lokasi anda.');
        } else if (str_contains($respons, '#open_door')){
            if($chat_id == '6839033468'){
                $keyboard = Keyboard::make()
                ->inline()
                ->row(Keyboard::inlineButton(['text' => 'HEAD OFFICE', 'callback_data' => '#ho']))
                ->row(Keyboard::inlineButton(['text' => 'KARAWANG', 'callback_data' => '#karawang']))
                ->row(Keyboard::inlineButton(['text' => 'PEMALANG', 'callback_data' => '#pemalang']))
                ->row(Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']));

                self::update($chat_id, $message_id, 'Silahkan Pilih Lokasi : ', $keyboard);
            }
        } else if (str_contains($respons, '#ho')){
            $cek = DB::table('devices')->where('is_active', 1)->where('status_device', 'online')->get();
            $device = [];
            foreach ($cek as $key => $value) {
                $device[] = [

                    'client_id' => $value->kode_device,
                    'name' => $value->nama_device
                ];
            }
            if(count($device) > 0){
            $keyboard = Keyboard::make()->inline();
            foreach ($device as $dev) {
                $keyboard->row(Keyboard::inlineButton(['text' => $dev['name'], 'callback_data' => '#device_' . $dev['client_id']]));
            }
            $keyboard->row(Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'destroy_message']));
            }
            
            self::update($chat_id, $message_id, 'Silahkan Pilih Pintu : ', $keyboard);

        } else if (str_contains($respons, '#karawang')){
            self::update($chat_id, $message_id, 'Silahkan Kirimkan Lokasi anda.');
        } else if (str_contains($respons, '#pemalang')){
            self::update($chat_id, $message_id, 'Silahkan Kirimkan Lokasi anda.');
        } else if (str_contains($respons, '#device_')){
            $seq = explode("_", $respons);
            $device_id = $seq[1];

            $parse = (object)[
                'topic' => 'open',
                'device' => $device_id
            ];
            $send = self::mqtt(json_encode($parse));
        }
    }

    

    public function customReply($query){
        $chat_id = $query->getChat()->getId();
        $message = strtolower($query->getText());
        $id_message = $query->getMessageId();
        $kondisi = $query;
        $response = '';
        
        $cek = DB::table('tele_chat')->where('pin_from', $chat_id)->orderBy('id', 'DESC')->take(2)->get();
        $user = MasterKaryawan::with('divisi')->where('pin_user', $chat_id)->where('is_active', 1)->first();
        $data = [];

        foreach ($cek as $key => $value) {
            array_push($data, $value);
        }

        if(count($data) > 1){
            $response = $data[1]->message;
            if(str_contains($response, 'dukungan_it')){
                $kategori = \explode("-", $response)[1]; // keluhan atau pengaduan
                $sub_kategori = \explode("-", $response)[2]; // jenis
                $catatan = $message;
                $time = DATE('Y-m-d H:i:s');
                $request_id = \str_replace(".", "/", microtime(true));
                $body = [
                    'kategori' => $kategori,
                    'sub_kategori' => $sub_kategori,
                    'request_id' => $request_id,
                    'request_by' => $user->nama_lengkap,
                    'user_id' => $user->id,
                    'pin_user' => $chat_id,
                    'divisi' => $user->divisi->nama_divisi,
                    'request_at' => $time,
                    'response' => $catatan,


                ];

                $id_req = DB::table('table_support')->insertGetId($body);
                $waktu = DATE('Y-m-d H:i:s', strtotime('+7 hours', strtotime($time)));
                $support = "Request-ID : $request_id \n";
                $support .= "Waktu Request : $waktu \n";
                $support .= "Status : Waiting \n \n";

                $array[0] = ['Request By', $user->nama_lengkap];
                $array[1] = ['Divisi', $user->divisi->nama_divisi];
                $array[2] = ['Kategori', strtoupper($kategori)];
                $array[3] = ['Topic', strtoupper($sub_kategori)];


                $table = Tableify::new($array);
                $table = $table->make();
                $table_data = $table->toArray();
                foreach ($table_data as $row) {
                    $support .= $row . "\n";
                }

                $support .= "\nKeterangan :\n";
                $support .= $catatan;
                
                if($this->tele_it!=null){
                    $keyboard = Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton(['text' => 'PROSESS', 'callback_data' => 'proses_it-'.$id_req]),
                    );
                    $support = "<pre>" . $support . "</pre>";
                    $tim = $this->tele_it;
                    foreach($tim as $key => $val){
                        self::sendTo($val, $support, $keyboard, $request_id);
                    }
                }
                $keyboard1 = Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton(['text' => 'CANCEL', 'callback_data' => 'cancel_it-'.$id_req]),
                    );

                $support .= "\n\nTim IT Support akan segera merespon request anda.";
                
                $support = "<pre>" . $support . "</pre>";
                self::sendTo($chat_id, $support, $keyboard1);

            } else {
                if($kondisi->getLocation()){
                    if($response == '#absen'){
                        date_default_timezone_set("Asia/Bangkok");
                        $lat_kantor = '-6.317557';
                        $long_kantor = '106.647732';

                        $lat = $kondisi->getLocation()->getLatitude();
                        $long = $kondisi->getLocation()->getLongitude();

                        $centerlat = str_replace('.', '', $lat_kantor);
                        $centerlong = str_replace('.', '', $long_kantor);

                        $batasBawah_lat = $centerlat + 400;
                        $batasAtas_lat = $centerlat - 400;

                        $batasBawah_long = $centerlong + 400;
                        $batasAtas_long = $centerlong - 400;

                        $latf = str_replace('.', '', $lat);
                        $longf = str_replace('.', '', $long);

                        $flagok = false;
                        $latf1 = str_replace('-', '', $latf);
                        $batasBawah_lat1 = str_replace('-', '', $batasBawah_lat);
                        $batasAtas_lat1 = str_replace('-', '', $batasAtas_lat);

                        if($latf1 >= $batasBawah_lat1 && $latf1 <= $batasAtas_lat1) $flagok = true;
                        if($longf >= $batasAtas_long && $longf <= $batasBawah_long) $flagok = true;

                        if($flagok == true) {
                            $tgl = DATE('Y-m-d');
                            $jam = DATE('H:i:s');
                            $status = ($jam > '13:00:00') ? 'Keluar' : 'Masuk';
                            $cek_user = MasterKaryawan::join('rfid_card', 'master_karyawan.id' , '=', 'rfid_card.userid')
                            ->select('master_karyawan.id', 'kode_kartu')
                            ->where('pin_user', $chat_id)
                            ->first();

                            $kode_kartu = $cek_user->kode_kartu;

                            DB::table('absensi')->insert([
                                'karyawan_id' => $cek_user->id,
                                'kode_kartu' => $cek_user->kode_kartu,
                                'kode_mesin' => 'ISL04',
                                'hari' => self::hari($tgl),
                                'tanggal' => $tgl,
                                'jam' => $jam,
                                'status' => $status,
                            ]);
                            self::delete($chat_id, $data[0]->message_id );
                            self::sendTo($chat_id, "Anda berhasil absen $status melalui telegram. Terimakasih");
                        } else {
                            self::delete($chat_id, $data[0]->message_id );
                            self::sendTo($chat_id, "Silahkan absen di lokasi kantor.!");
                        }
                        
                    } else {
                        self::delete($chat_id, $data[0]->message_id);
                        self::sendTo($chat_id, "Silahkan pilih menu yang sesuai.!");
                    }
                } else {
                    self::sendTo($chat_id, 'Maaf, Telegram belum di kembangkan secara luas..!');
                }
            }
        } else {
            self::sendTo($chat_id, 'Maaf, Telegram belum di kembangkan secara luas.!');
        }
    }


    public function sendTo($chatID = null, $message = '', $keyboard = null, $seq_id = null)
    {   
        if($keyboard!=null){
    
            $respons = Telegram::sendMessage([
                'chat_id' => $chatID, 
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);

        } else {
            $respons = Telegram::sendMessage([
                'chat_id' => $chatID, 
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

        }
        $to = $respons->chat->id;
        $from = $respons->from->id;
        $text = $respons->text;
        $id_message = $respons->message_id;

        DB::table('tele_chat')->insert(['text' => json_encode($respons), 'pin_from' => $from, 'pin_to' => $to, 'message_id' => $id_message, 'message' => $text, 'uniq' => $seq_id]);

        return $respons;
    }

    public function update($chatID = null, $message_id = null, $message = '', $keyboard = null)
    {   
        if($keyboard!=null){
    
            return Telegram::editMessageText([
                'chat_id' => $chatID, 
                'text' => $message,
                'parse_mode' => 'HTML',
                'message_id' => $message_id,
                'reply_markup' => $keyboard
            ]);

        } else {
            return Telegram::editMessageText([
                'chat_id' => $chatID, 
                'message_id' => $message_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

        }
    }

    public function delete($chatID = null, $message_id = null)
    {   
        return Telegram::deleteMessage([
            'chat_id' => $chatID, 
            'message_id' => $message_id,
        ]);
    }

    public function Bulan($data){
        $cal = explode("-", $data);
        $bulan = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        return $bulan[(int)$cal[1] - 1] .' '. $cal[0];
    }

    public function hari($tanggal){
        $hari = date ("D", strtotime($tanggal));
     
        switch($hari){
            case 'Sun':
                $hari_ini = "Minggu";
            break;
     
            case 'Mon':			
                $hari_ini = "Senin";
            break;
     
            case 'Tue':
                $hari_ini = "Selasa";
            break;
     
            case 'Wed':
                $hari_ini = "Rabu";
            break;
     
            case 'Thu':
                $hari_ini = "Kamis";
            break;
     
            case 'Fri':
                $hari_ini = "Jumat";
            break;
     
            case 'Sat':
                $hari_ini = "Sabtu";
            break;
            
            default:
                $hari_ini = "Tidak di ketahui";		
            break;
        }
     
        return $hari_ini;
     
    }

    public function getAbsen($date, $pin)
    {
        $data = [
            ['Tanggal', 'Masuk', 'Keluar'],
        ];

        $userid = MasterKaryawan::where('pin_user', $pin)->first();
        $id = $userid->id;

        $tahun = Carbon::parse($date)->format('Y');
        $bulan = Carbon::parse($date)->format('m');

        $tableMessage = '';
        $query = DB::select(
        "SELECT tanggal,
        CASE WHEN MIN(jam) <= '13:00:00' THEN MIN(jam) ELSE '' END as masuk, 
        CASE WHEN MAX(jam) > '13:00:10' THEN MAX(jam) ELSE '' END as keluar 
        FROM `absensi`
        WHERE karyawan_id = '$id' AND YEAR(tanggal) = '$tahun' AND MONTH(tanggal) = '$bulan'
        group by tanggal
        ");

        foreach($query as $key => $val){
                array_push($data, array(str_replace("-","/",$val->tanggal), $val->masuk, $val->keluar));
            }

        $table = Tableify::new($data);
        $table = $table->make();
        $table_data = $table->toArray();
        foreach ($table_data as $row) {
            $tableMessage .= $row . "\n";
        }
        $tableMessage = "<pre>" . $tableMessage . "</pre>";
        return $tableMessage;
    }

    public function askOpenAI($messageText)
    {
    }

}