<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;
use Illuminate\Support\Facades\Log;

class SendNotificationJob extends Job
{
    /**
     * Data notifikasi.
     *
     * @var array
     */
    protected $data;

    /**
     * Daftar pengguna yang akan menerima notifikasi.
     *
     * @var array
     */
    protected $users;

    /**
     * Create a new job instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data['data'];
        $this->users = $data['users'];
    }

    /**
     * Handle the job.
     *
     * @return void
     */
    public function handle()
    {
        $host = trim((string) env('MQTT_HOST', ''));
        $port = (int) env('MQTT_PORT', 0);
        if ($host === '' || $port <= 0) {
            Log::warning('MQTT skip: MQTT_HOST/MQTT_PORT kosong');
            return;
        }

        try {
            $clientID = env('MQTT_USERNAME', env('MQTT_USER', 'Admin'));
            $username = env('MQTT_USERNAME', env('MQTT_USER', 'Admin'));
            $password = env('MQTT_PASSWORD');
            $mqtt = new phpMQTT($host, $port, $clientID);
            if (!$mqtt->connect(true, null, $username, $password)) {
                Log::warning('MQTT connect gagal', ['host' => $host, 'port' => $port]);
                return;
            }

            foreach ($this->users as $user) {
                $mqtt->publish('/notification/' . $user->id, json_encode($this->data), 0);
            }

            $mqtt->close();
        } catch (\Throwable $e) {
            Log::warning('MQTT publish gagal: ' . $e->getMessage());
        }
    }
}
