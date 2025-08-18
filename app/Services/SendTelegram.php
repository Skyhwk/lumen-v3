<?php
namespace App\Services;

use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\DB;
use SevenEcks\Tableify\Tableify;

class SendTelegram
{
    /**
     * Cara pemanggilan SendTelegram
     * $telegram = new SendTelegram();
     * $telegram->text('Hello, world!')->button([['text' => 'Button 1', 'callback_data' => 'button1']])->to('1234567890')->send();
     * 
     * atau
     * $telegram = SendTelegram->text('Hello, world!')
     * ->button([['text' => 'Button 1', 'callback_data' => 'button1']])
     * ->to('1234567890')
     * ->send();
     */
    
    protected $text;
    protected $button;
    protected $to;
    private static $instance = null;

    public function __construct()
    {
        $this->text = '';
        $this->button = [];
        $this->to = '';
    }

    public static function text($text)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->text = $text;
        return self::$instance;
    }

    public static function button($button)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->button = $button;
        return self::$instance;
    }

    public static function to($to)
    {
        if (empty($to)) {
            throw new \Exception('To is required');
        }

        if (!self::$instance) {
            self::$instance = new self();
        }

        // self::$instance->to = $to;soundbeats
        self::$instance->to = $to;
        return self::$instance;
    }

    public function send()
    {
        $params = [
            'text' => $this->text,
            'parse_mode' => 'HTML'
        ];

        if ($this->button) {
            $keyboard = Keyboard::make([
                'inline_keyboard' => $this->button
            ]);
            $params['reply_markup'] = $keyboard;
        }

        if (is_array($this->to)) {
            $responses = [];
            foreach ($this->to as $chatId) {
                $params['chat_id'] = $chatId;
                $responses[] = Telegram::sendMessage($params);
            }
            return $responses;
        } else {
            $params['chat_id'] = $this->to;
            return Telegram::sendMessage($params);
        }
    }
}