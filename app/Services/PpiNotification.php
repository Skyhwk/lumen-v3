<?php

namespace App\Services;

use Laravel\Lumen\Routing\Controller;

use Carbon\Carbon;
use App\Jobs\SendPpiNotificationJob;

use App\Models\customer\Users;
use App\Models\customer\Notifications as NotificationPpiModel;

class PpiNotification extends Controller
{
    protected $query;
    protected $title;
    protected $message;
    protected $url;

    public static function where($field, $value)
    {
        $instance = new static();
        $instance->query = Users::where($field, $value)->where('is_active', 1);
        return $instance;
    }

    public static function whereIn($field, $values)
    {
        $instance = new static();
        $instance->query = Users::whereIn($field, $values)->where('is_active', 1);
        return $instance;
    }

    public static function whereJsonContains($field, $value)
    {
        $instance = new static();
        $instance->query = Users::whereJsonContains($field, $value)->where('is_active', 1);
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

        NotificationPpiModel::insert($notifications);

        $data = [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];

        $array = [
            'data' => $data,
            'users' => $users,
        ];

        $job = new SendPpiNotificationJob($array);
        $this->dispatch($job);

        return true;
    }
}
