<?php
namespace App\Services;

use Google\Auth\OAuth2;
use App\Models\DeviceIntilabRunning;
use App\Models\FcmTokenFdl;
use App\Models\MasterKaryawan;
use App\Jobs\SendNotificationFdl;
use Carbon\Carbon;


class NotificationFdlService
{
    public function deviceIntilab($idAlat, $statusBefore, $statusAfter)
    {
        $deviceRunning = DeviceIntilabRunning::where('device_id', $idAlat)->where('is_active', true)->first();
        
        if($deviceRunning){
            // dd('masukkk');
            $title = "Device {$statusAfter}";
            $messageOnline = "Device {$deviceRunning->nama} telah kembali online";
            $messageOffline = "Device {$deviceRunning->nama} telah offline, silahkan lakukan pengecekan!";
            $token = FcmTokenFdl::where('user_id', $deviceRunning->start_by_id)->first();
            $tokenFcm = $token ? $token->fcm_token : null;
            $message = $statusAfter == 'offline' ? $messageOffline : $messageOnline;
            
            dispatch(new SendNotificationFdl($tokenFcm, $title, $message, ['title' => $title, 'body' => $message], $deviceRunning->start_by_id));
        }
    
    }

    public function panduanFdl($title)
    {
        FcmTokenFdl::chunk(100, function ($users) use ($title) {
            foreach ($users as $user) {
                dispatch(new SendNotificationFdl(
                    $user->fcm_token,
                    $title,
                    'Silahkan cek di aplikasi serta membaca panduan terbaru!',
                    [
                        'title' => "$title Telah Rilis",
                        'body' => 'Silahkan cek di aplikasi serta membaca panduan terbaru!',
                    ],
                    $user->user_id
                ));
            }
        });
    }

    public function sendApproveNotification($menu, $no_sampel, $approved_by, $created_by)
    {
        $user = MasterKaryawan::where('nama_lengkap', $created_by)->where('is_active', true)->first();
        $token = FcmTokenFdl::where('user_id', $user->id)->first();
        $title = "No Sampel $no_sampel Telah di Setujui";
        $message = "Data Lapangan dengan no sampel $no_sampel pada FDL $menu telah di approve oleh $approved_by";
        
        dispatch(new SendNotificationFdl($token->fcm_token ?? null, $title, $message, ['title' => $title, 'body' => $message], $user->id));
    }

    public function sendRejectNotification($menu, $no_sampel, $reason, $rejected_by, $created_by)
    {
        $user = MasterKaryawan::where('nama_lengkap', $created_by)->where('is_active', true)->first();
        $token = FcmTokenFdl::where('user_id', $user->id)->first();
        $title = "No Sampel $no_sampel Telah di Reject";
        $message = "Data Lapangan dengan no sampel $no_sampel pada FDL $menu di reject oleh $rejected_by dengan alasan $reason";
        
        dispatch(new SendNotificationFdl($token->fcm_token ?? null, $title, $message, ['title' => $title, 'body' => $message], $user->id));
    }
}
