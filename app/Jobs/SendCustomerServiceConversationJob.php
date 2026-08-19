<?php

namespace App\Jobs;

use Bluerhinos\phpMQTT;

class SendCustomerServiceConversationJob extends Job
{
    protected $topic;
    protected $payload;

    public function __construct(string $topic, array $payload)
    {
        $this->topic = $topic;
        $this->payload = $payload;
    }

    public function handle()
    {
        $host = env('MQTT_HOST', env('PPI_MQTT_HOST', 'portal.intilab.com'));
        $port = env('MQTT_PORT', env('PPI_MQTT_PORT', 1111));
        $clientID = env('MQTT_USERNAME', env('PPI_MQTT_USERNAME', 'admin'));
        $username = env('MQTT_USERNAME', env('PPI_MQTT_USERNAME', 'admin'));
        $password = env('MQTT_PASSWORD', env('PPI_MQTT_PASSWORD', ''));

        $mqtt = new phpMQTT($host, $port, $clientID . '_cs_' . uniqid());

        if (!$mqtt->connect(true, null, $username, $password)) {
            return;
        }

        $mqtt->publish($this->topic, json_encode($this->payload), 0);
        $mqtt->close();
    }
}
