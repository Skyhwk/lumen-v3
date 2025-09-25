<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;
use Illuminate\Support\Facades\Log;

class SendMqttAccess extends Job
{
    protected $data;
    protected $device;

    public function __construct($data, $device)
    {
        $this->data = $data;
        $this->device = $device;
    }

    public function handle()
    {
        $return = [
            'topic' => 'sync_access',
            'device' => $this->device,
            'data' => $this->data,
        ];

        $this->send_mqtt(json_encode($return));
    }

    private function send_mqtt($data)
    {
        $mqtt = new phpMQTT('apps.intilab.com', '1111', 'AdminIoT');
        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/iot/multidevice', $data, 0);
            $mqtt->close();

            return true;
        }

        return false;
    }
}