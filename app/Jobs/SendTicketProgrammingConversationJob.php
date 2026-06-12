<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;

class SendTicketProgrammingConversationJob extends Job
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
        $host = env('MQTT_HOST');
        $port = env('MQTT_PORT');
        $clientID = env('MQTT_USERNAME');
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $mqtt = new phpMQTT($host, $port, $clientID);

        if ($mqtt->connect(true, null, $username, $password)) {
            foreach ($this->users as $user) {
                $topic = '/notification/' . $user->id;
                $mqtt->publish($topic, json_encode($this->data), 0);
            }
            $mqtt->close();
        }
    }
}
