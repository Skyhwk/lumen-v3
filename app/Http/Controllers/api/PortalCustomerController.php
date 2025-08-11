<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use SevenEcks\Tableify\Tableify;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Jobs\JobEmailBlast;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Repository;
use App\Models\{
    CustomersAccount,
    QuotationNonKontrak,
    QuotationKontrakH,
    Barang,
    RecordPermintaanBarang,
    MasterKaryawan,
    Invoice,
    LhpsAirHeader,
    MasterPelanggan,JobTask,MailSchedule,MailList};
use App\Models\customer\Users;
use App\Services\{
    SendEmail   ,
    GetBawahan};



class PortalCustomerController extends Controller
{
    public function authRegister(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        DB::beginTransaction();
        try {
            $masterPelanggan = MasterPelanggan::where('kontak_pelanggan.email_perusahaan', $request->email)->where('is_active', true)->first();
            $quotationKontrak = QuotationKontrakH::where('email_pic_order', $request->email)->where('is_active', true)->first();
            $quotationNonKontrak = QuotationNonKontrak::where('email_pic_order', $request->email)->where('is_active', true)->first();

            if ($masterPelanggan) {
                // Email ditemukan di tabel MasterPelanggan
                $nama_perusahaan = $masterPelanggan->nama_pelanggan;
            } else if ($quotationKontrak) {
                // Email ditemukan di tabel QuotationKontrakH
                $nama_perusahaan = $quotationKontrak->nama_perusahaan;
            } else if ($quotationNonKontrak) {
                // Email ditemukan di tabel QuotationNonKontrak
                $nama_perusahaan = $quotationNonKontrak->nama_perusahaan;
            } else {
                // Email tidak ditemukan di semua tabel
                DB::rollBack();
                return response()->json([
                    'message' => 'Silahkan menghubungi pihak Sales',
                    'status' => '404',
                ], 200);
            }

            $account = CustomersAccount::where('email', $request->email)->first();
            if ($account) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email sudah terdaftar',
                    'status' => '200'
                ], 200);
            }

            $validatedData = $request->validate([
                'email' => 'required|email|unique:customer_account',
                'password' => 'required|min:8',
            ]);

            $account = CustomersAccount::create([
                'nama_perusahaan' => $nama_perusahaan,
                'email' => $validatedData['email'],
                'created_by' => $validatedData['email'],
                'created_at' => $timestamp,
                'password' => Hash::make($validatedData['password']),
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Register berhasil',
                'status' => '200',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terdapat kesalahan dalam proses registrasi',
                'status' => '500',
            ], 500);
        }
    }

    public function authLogin(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        $account = CustomersAccount::where('email', $email)->first();
        if (!$account) {
            return response()->json([
                'message' => 'Email tidak terdaftar',
                'status' => '404',
            ], 404);
        }

        if (!Hash::check($password, $account->password)) {
            return response()->json([
                'message' => 'Password salah',
                'status' => '401',
            ], 401);
        }

        return response()->json([
            'message' => 'Login berhasil',
            'status' => '200',
            'data' => $account
        ], 200);
    }

    public function sendEmailKesapakatan (Request $request)
    {
        // user dan pasword generate
        Carbon::setLocale('id');
        $length = 8;
        $microtime = microtime(true);
        $hash = preg_replace('/[^a-zA-Z0-9]/', '', base_convert($microtime, 10, 36));
        $user = $request->email;
        $password = substr($hash, 0,$length); // Generate different password value

        //tanggal
        $hari = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];

        $bulan = [
            'January' => 'Januari',
            'February' => 'Februari',
            'March' => 'Maret',
            'April' => 'April',
            'May' => 'Mei',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'Agustus',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Desember'
        ];

        $tgl = date('d');
        $bln = date('F');
        $thn = date('Y');
        $hariIni = date('l');

        $hariIndo = $hari[$hariIni];
        $bulanIndo = $bulan[$bln];
        $hasilTanggal = $hariIndo.', '.$tgl.' '.$bulanIndo.' '.$thn;

        // Prepare the professional email body with a blue button
        $buttonStyle = 'background-color: #0073e6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;';
        $loginUrl =  env('PORTALV4').'/customer/login';
        Users::where('email', $user)->update(['password'=> Hash::make($password), 'template'=>$request->template,'is_cheklist' => true]);
        $userApp = Users::where('email', $user)->where('is_cheklist',true)->first();
        $body = '<body style="font-family: Arial, sans-serif; color: #000;">
            <p>Halo <b>'.$userApp->nama_lengkap.'</b></p>

            <p>
                Kami telah menerima permintaan verifikasi akun Anda pada aplikasi <b>Portal Customer</b>
                melalui email <b>'. $userApp->email .'</b> pada hari <strong>'.  $hasilTanggal .'</strong>.
            </p>

            <p>Silahkan Masukan Email : '. $user .' dan Password : <strong style="font-size: 1.2em;">'. $password .'</strong> untuk login ke aplikasi <b> <a href='.$loginUrl.'>Portal Customer<a/></b> </p>.



            <p><b>Tips untuk menjaga keamanan data Anda:</b></p>
            <ol>
                <li>Jaga kerahasiaan informasi data email dan password.</li>
                <li>Jangan membagikan email dan password ini kepada siapapun.</li>
            </ol>

            <p style="color: red; font-weight: bold;">
                Perhatian! Harap mengabaikan pesan ini apabila Anda tidak melakukan permintaan ini!
            </p>

            <p>Hormat Kami,</p>
            <p><b>PT Inti Surya Laboratorium</b></p>';
        $email = SendEmail::where('to', $user)
            ->where('subject', 'Form Registrasi')
            ->where('body',$body )
            ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
            ->noReply()
            ->send();

        return response()->json([
            'message' => 'Email berhasil dikirim',
            'status' => '200',
        ], 200);
    }

    public function testEmailSubcribe (Request $request)
    {

        try {
            Log::channel('custom_email_log')->info('testEmailSubcribe ' . \date('Y-m-d H:i:s'));
            // $currentDate = date('Y-m-d', strtotime('-2 days')); //debuging
            $currentDate = date('Y-m-d');
            $currentDay = date('l'); // Get current day in full format (e.g., Monday)
            $currentTime = date('H:i');

            $dayMapping = [
                'Senin' => 'Monday',
                'Selasa' => 'Tuesday',
                'Rabu' => 'Wednesday',
                'Kamis' => 'Thursday',
                'Jumat' => 'Friday',
                'Sabtu' => 'Saturday',
                'Minggu' => 'Sunday',
            ];
            $schedules = MailSchedule::get();
            Log::channel('custom_email_log')->info('Mulai ' . \date('Y-m-d H:i:s'));
            foreach ($schedules as $schedule) {
                Log::channel('custom_email_log')->info('Mulai1 ' . \date('Y-m-d H:i:s'));
                    $startDate = $schedule->start_date;
                    $endDate = $schedule->end_date;
                    $days = ($schedule->days != null) ? json_decode($schedule->days, true) : [];
                    $time = date('H:i', strtotime($schedule->time));
                    // target email
                    $mailist=MailList::where('id',$schedule->mail_id)->first();
                    $content = Repository::dir('blast_mail_template')->key($mailist->name)->get();
                    if ($currentDate >= $startDate && $currentDate <= $endDate) { //chek current date
                        Log::channel('custom_email_log')->info('Mulai2 ' . \date('Y-m-d H:i:s'));
                        $filteredDays = array_filter($days, fn($day) => $dayMapping[$day] === $currentDay);
                        $filteredDays = array_values($filteredDays);
                        if (!empty($filteredDays)) { //chek current day
                            if ($currentTime === $time) { //chek current time
                                JobTask::insert([
                                    'job' => 'JobEmailBlast',
                                    'status' => 'processing',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                                $job = new JobEmailBlast('luthfi@intilab.com', $mailist->subject, $content);
                                $this->dispatch($job);
                                JobTask::insert([
                                    'job' => 'JobEmailBlast',
                                    'status' => 'processed',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                            }
                    }
                }
            }
            return response()->json([
                'message' => 'Email berhasil dikirim',
                'status' => '200',
            ], 200);
        } catch (\Exception $e) {
            //throw $th;
            Log::channel('custom_email_log')->error(
                "RenderEmailBlast job failed: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}");
            return response()->json([
                'message' => 'Email gagal dikirim',
                'status' => '500',
            ], 500);
        }

    }


}
