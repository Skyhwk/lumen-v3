<?php

namespace App\Services;

use App\Models\FdlActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InsertActivityFdl
{
    private $user_id;
    private $action;  // e.g. 'input', 'delete', 'approve', 'start', 'stop'
    private $target;  // e.g. what user is doing
    private $activity;
    private $no_sampel;
    private static $instance;

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'user':
                self::$instance->user_id = $value;
                break;
            case 'action':
                self::$instance->action = $value;
                break;
            case 'target':
                self::$instance->target = $value;
                break;
        }

        return self::$instance;
    }

    public static function by($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->user_id = $value;
        return self::$instance;
    }

    public static function action($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->action = $value;
        return self::$instance;
    }

    public static function activity($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->activity = $value;
        return self::$instance;
    }

    public static function target($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->target = $value;
        return self::$instance;
    }

    public static function noSampel($value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->no_sampel = $value;
        return self::$instance;
    }

    // Optional: simpan ke database
    public function save()
    {
        if ($this->activity == null || $this->activity == '') {
            $this->activity = $this->generateAction($this->action) . ' ' . $this->target;
        }
        $activity = FdlActivity::create([
            'user_id' => $this->user_id,
            'activity'  => $this->activity,
            'created_at' => Carbon::now(),
        ]);

        if ($this->action === 'input') {
            $noSampel = $this->no_sampel;
            if (!$noSampel && is_string($this->target) && preg_match('/nomor\s+sampel\s+(\S+)/iu', $this->target, $m)) {
                $noSampel = $m[1];
            }
            if ($noSampel) {
                try {
                    FdlOrderDetailService::afterSamplerSubmit($noSampel);
                } catch (\Throwable $e) {
                    Log::channel('sampling')->error('afterSamplerSubmit dari InsertActivityFdl', [
                        'no_sampel' => $noSampel,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        self::$instance = null;

        return $activity;
    }

    private function generateAction($action)
    {
        switch ($action) {
            case 'input':
                return 'Menginput data pada';
            case 'delete':
                return 'Menghapus data pada';
            case 'approve':
                return 'Menyetujui data pada';
            case 'start':
                return 'Mengaktifkan alat';
            case 'stop':
                return 'Menghentikan alat';
            default:
                return 'Melakukan sesuatu pada';
        }
    }
}
