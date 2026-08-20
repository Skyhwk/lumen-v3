<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SendWhatsapp
{
    private $number;
    private $message;

    public function __construct($number, $message)
    {
        $this->number = $number;
        $this->message = $message;
    }

    private function getToken()
    {
        $response = Http::post(env('TOKEN_API').'/api/get-token',
            [
            'secret' => env('SECRET')
            ]
        );

        $res = json_decode($response->getBody());
        return $res->data->access_token;
    }

    private function getSeason()
    {
        $token = $this->getToken();
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$token])->get(env('TOKEN_API').'/api/get_season',
            [
            'number' => env('NUMBER')
            ]
        );

        $res = json_decode($response->getBody());
        return $res->data->name;
    }

    private function formatNumber()
    {
        $number = preg_replace('/[^0-9]/', '', $this->number);
        if (substr($number, 0, 2) === '08') {
            return '62' . substr($number, 1);
        }
        if (substr($number, 0, 1) === '8') {
            return '62' . $number;
        }
        return $number;
    }

    public function send()
    {
        $seasonID = $this->getSeason();
        $number = $this->formatNumber();
        $response = Http::withHeaders(
            [
                'Content-Type' => 'application/json'
            ])->post(env('WHATSAPP_API').'/'.$seasonID.'/messages/send',
            [
            'jid' => $number.'@s.whatsapp.net',
            'type' => 'number',
            'message' => ['text' => $this->message]
            ]
        );

        $res = json_decode($response->getBody());
        
        if(isset($res->status)) {
            return true;
        } else {
            return false;
        }
    }
}