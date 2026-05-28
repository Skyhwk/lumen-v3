<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;

class SendPpiNotificationJob extends Job
{
    /**
     * Data notifikasi PPI.
     *
     * @var array
     */
    protected $data;

    /**
     * Daftar pengguna PPI yang akan menerima notifikasi.
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
        $host = env('PPI_MQTT_HOST','portal.intilab.com');
        $port = env('PPI_MQTT_PORT', '1111');
        $clientID = env('PPI_MQTT_USERNAME', 'admin');
        $username = env('PPI_MQTT_USERNAME', 'admin');
        $password = env('PPI_MQTT_PASSWORD', '');

        $mqtt = new phpMQTT($host, $port, $clientID);

        if ($mqtt->connect(true, null, $username, $password)) {
            foreach ($this->users as $user) {
                $topic = '/ppi/notification/' . $user->id;
                $payload = json_encode($this->data);

                $mqtt->publish($topic, $payload, 0);
            }

            $mqtt->close();
        } else {
            dd('gagal');
        }
    }
}
