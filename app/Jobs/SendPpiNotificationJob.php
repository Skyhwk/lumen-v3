<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;

class SendPpiNotificationJob extends Job
{
    protected $data;

    protected $users;

    public function __construct(array $data)
    {
        $this->data = $data['data'];
        $this->users = $data['users'];
    }

    public function handle()
    {
        $mqtt = new phpMQTT(env('MQTT_HOST'), env('MQTT_PORT'), env('MQTT_USERNAME'));

        if ($mqtt->connect(true, null, env('MQTT_USERNAME'), env('MQTT_PASSWORD'))) {
            foreach ($this->users as $user) {
                $mqtt->publish("/ppi/notification/{$user->id}", json_encode($this->data), 0);
            }

            $mqtt->close();
        } else {
            dd('gagal');
        }
    }
}
