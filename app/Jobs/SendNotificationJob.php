<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;

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
        $host = env('MQTT_HOST');
        $port = env('MQTT_PORT'); 
        $clientID = env('MQTT_USERNAME');
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $mqtt = new phpMQTT($host, $port, $clientID);
        if ($mqtt->connect(true, null, $username, $password)) {
            foreach ($this->users as $user) {
                $topic = '/notification/' . $user->id;
                $payload = json_encode($this->data);

                $mqtt->publish($topic, $payload, 0);
            }

            $mqtt->close(); // Tutup koneksi setelah selesai
        } else {
            dd('gagal');
        }
    }
}
