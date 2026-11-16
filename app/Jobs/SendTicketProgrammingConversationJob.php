<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;
use Illuminate\Support\Facades\Log;

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
        $host = trim((string) env('MQTT_HOST', ''));
        $port = (int) env('MQTT_PORT', 0);
        if ($host === '' || $port <= 0) {
            return;
        }

        try {
            $clientID = env('MQTT_USERNAME', env('MQTT_USER', 'Admin'));
            $username = env('MQTT_USERNAME', env('MQTT_USER', 'Admin'));
            $password = env('MQTT_PASSWORD');
            $mqtt = new phpMQTT($host, $port, $clientID);

            if (!$mqtt->connect(true, null, $username, $password)) {
                return;
            }

            foreach ($this->users as $user) {
                $mqtt->publish('/notification/' . $user->id, json_encode($this->data), 0);
            }
            $mqtt->close();
        } catch (\Throwable $e) {
            Log::warning('MQTT conversation publish gagal: ' . $e->getMessage());
        }
    }
}
