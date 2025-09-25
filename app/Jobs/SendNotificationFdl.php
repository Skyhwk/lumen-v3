<?php

namespace App\Jobs;

use App\Services\FirebaseService;

class SendNotificationFdl extends Job
{
    protected $tokenFcm;
    protected $title;
    protected $message;
    protected $data;
    protected $userId;

    public function __construct($tokenFcm, $title, $message, $data, $userId)
    {
        $this->tokenFcm = $tokenFcm;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->userId = $userId;
    }

    public function handle()
    {
        $firebase = app(FirebaseService::class);
        $firebase->sendNotification($this->tokenFcm, $this->title, $this->message, $this->data, $this->userId);
    }
}
