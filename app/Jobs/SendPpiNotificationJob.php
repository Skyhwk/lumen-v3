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
        $host = env('PPI_MQTT_HOST');
        $port = env('PPI_MQTT_PORT');
        $isSsl = filter_var(env('PPI_MQTT_SSL', false), FILTER_VALIDATE_BOOLEAN);
        $transport = strtolower(env('PPI_MQTT_TRANSPORT', $isSsl ? 'wss' : 'tcp'));
        $username = env('PPI_MQTT_USER', env('PPI_MQTT_USERNAME'));
        $password = env('PPI_MQTT_PASSWORD');

        if (!$host || !$port) {
            return;
        }

        try {
            $username = $username !== '' ? $username : null;
            $password = $password !== '' ? $password : null;

            if ($transport === 'wss') {
                $this->publishViaWebSocket($host, $port, $username, $password);
                return;
            }

            $host = $isSsl && strpos($host, '://') === false ? "ssl://{$host}" : $host;
            $mqtt = new phpMQTT($host, $port, 'ppi-notification-' . uniqid());

            if (!$mqtt->connect(true, null, $username, $password)) {
                return;
            }

            foreach ($this->users as $user) {
                $mqtt->publish("/ppi/notification/{$user->id}", json_encode($this->data), 0);
            }

            $mqtt->close();
        } catch (\Throwable $exception) {
            return;
        }
    }

    protected function publishViaWebSocket($host, $port, $username = null, $password = null)
    {
        $socket = $this->openWebSocket($host, $port);
        $clientId = 'ppi-notification-' . uniqid();

        $connectPacket = $this->buildMqttConnectPacket($clientId, $username, $password);
        $this->writeWebSocketFrame($socket, $connectPacket);
        $this->readWebSocketFrame($socket);

        foreach ($this->users as $user) {
            $topic = "/ppi/notification/{$user->id}";
            $payload = json_encode($this->data);

            $this->writeWebSocketFrame($socket, $this->buildMqttPublishPacket($topic, $payload));
        }

        fclose($socket);
    }

    protected function openWebSocket($host, $port)
    {
        $socket = stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ])
        );

        if (!$socket) {
            throw new \RuntimeException("Unable to connect to portal MQTT broker: {$errstr}");
        }

        stream_set_timeout($socket, 10);

        $key = base64_encode(random_bytes(16));
        $headers = [
            "GET / HTTP/1.1",
            "Host: {$host}:{$port}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$key}",
            "Sec-WebSocket-Version: 13",
            "Sec-WebSocket-Protocol: mqtt",
            "\r\n",
        ];

        fwrite($socket, implode("\r\n", $headers));

        $response = '';
        while (strpos($response, "\r\n\r\n") === false) {
            $chunk = fgets($socket, 1024);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
        }

        if (!preg_match('/^HTTP\/1\.1 101 /', $response)) {
            throw new \RuntimeException('Portal MQTT websocket handshake failed');
        }

        return $socket;
    }

    protected function buildMqttConnectPacket($clientId, $username = null, $password = null)
    {
        $flags = 2;
        $payload = $this->encodeMqttString($clientId);

        if ($username !== null) {
            $flags |= 128;
            $payload .= $this->encodeMqttString($username);
        }

        if ($password !== null) {
            $flags |= 64;
            $payload .= $this->encodeMqttString($password);
        }

        $variableHeader = $this->encodeMqttString('MQTT') . chr(4) . chr($flags) . pack('n', 60);

        return chr(0x10) . $this->encodeRemainingLength(strlen($variableHeader . $payload)) . $variableHeader . $payload;
    }

    protected function buildMqttPublishPacket($topic, $payload)
    {
        $body = $this->encodeMqttString($topic) . $payload;

        return chr(0x30) . $this->encodeRemainingLength(strlen($body)) . $body;
    }

    protected function encodeMqttString($value)
    {
        return pack('n', strlen($value)) . $value;
    }

    protected function encodeRemainingLength($length)
    {
        $encoded = '';

        do {
            $digit = $length % 128;
            $length = (int) floor($length / 128);

            if ($length > 0) {
                $digit |= 128;
            }

            $encoded .= chr($digit);
        } while ($length > 0);

        return $encoded;
    }

    protected function writeWebSocketFrame($socket, $payload)
    {
        $length = strlen($payload);
        $frame = chr(0x82);

        if ($length <= 125) {
            $frame .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127) . pack('NN', 0, $length);
        }

        $mask = random_bytes(4);
        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        fwrite($socket, $frame);
    }

    protected function readWebSocketFrame($socket)
    {
        $header = fread($socket, 2);

        if (strlen($header) < 2) {
            return '';
        }

        $length = ord($header[1]) & 127;

        if ($length === 126) {
            $length = unpack('n', fread($socket, 2))[1];
        } elseif ($length === 127) {
            $parts = unpack('N2', fread($socket, 8));
            $length = ($parts[1] << 32) + $parts[2];
        }

        return $length > 0 ? fread($socket, $length) : '';
    }
}
