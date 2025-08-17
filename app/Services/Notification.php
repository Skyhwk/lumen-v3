<?php

namespace App\Services;

use App\Models\Notification as NotificationModel;
use App\Models\MasterKaryawan;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Laravel\Lumen\Routing\Controller;

class Notification extends Controller
{
    /*
        cara pemanggilan 
        Notification::where('id', $id_user)->title('title')->message('Pesan')->url('url')->send();
        Notification::whereIn('id', $array_id_user)->title('title')->message('Pesan')->url('url')->send();
        Notification::where('id_department', $id_divisi)->title('title')->message('Pesan')->url('url')->send();
        Notification::whereIn('id_department', $array_id_divisi)->title('title')->message('Pesan')->url('url')->send();
    */
    protected $query;
    protected $title;
    protected $message;
    protected $url;

    public static function where($field, $value)
    {
        $instance = new static();
        $instance->query = MasterKaryawan::where($field, $value)->where('is_active', 1);
        return $instance;
    }

    public static function whereIn($field, $values)
    {
        $instance = new static();
        $instance->query = MasterKaryawan::whereIn($field, $values)->where('is_active', 1);
        return $instance;
    }

    public function title($title)
    {
        $this->title = $title;
        return $this;
    }

    public function message($message)
    {
        $this->message = $message;
        return $this;
    }

    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    public function send()
    {
        $users = $this->query->get(['id']);

        $notifications = [];
        foreach ($users as $user) {
            $notifications[] = [
                'user_id' => $user->id,
                'title' => $this->title,
                'message' => $this->message,
                'url' => $this->url,
                'is_read' => false,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'is_active' => true,
            ];
        }

        NotificationModel::insert($notifications);

        $data = [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];

        $array = [
            'data' => $data,
            'users' => $users,
        ];

        $job = new SendNotificationJob($array);
        $this->dispatch($job);

        return true;
    }
}
