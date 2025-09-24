<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\Mdl;
use Carbon\Carbon;

class AnalystFormula
{
    /**
     * Cara pemanggilan
     * AnalystFormula::where('function', 'nama fungsi yang di tuju')
     * ->where('data', (object)$request->all())
     * ->where('id_parameter', $id_parameter)
     * ->process();
     */

    private $function;
    private $data;
    private $id_parameter;
    private $mdl;
    private static $instance;

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        switch ($field) {
            case 'function':
                self::$instance->function = $value;
                break;
            case 'data':
                self::$instance->data = $value;
                break;
            case 'id_parameter':
                if (!$value) {
                    throw new \Exception('Parameter ID wajib diisi');
                }

                self::$instance->id_parameter = $value;
                $mdl = Mdl::where('parameter_id', $value)->where('is_active', true)->where('function', self::$instance->function)->first();
                if ($mdl) {
                    self::$instance->mdl = $mdl->value;
                }
                break;
        }

        return self::$instance;
    }

    public function process()
    {
        $function = $this->function;
        $data = $this->data;
        $id_parameter = $this->id_parameter;
        $mdl = $this->mdl ?? null;  
        $result = [];
		
        $helperClass = "App\\HelpersFormula\\{$function}";

        if (class_exists($helperClass)) {
            $helper = new $helperClass();
            if (method_exists($helper, 'index')) {
                $result = $helper->index($data, $id_parameter, $mdl);
            } else {
                return 'Coming Soon';
            }
        } else {
            if (method_exists($this, $function)) {
                $result = $this->$function($data, $id_parameter, $mdl);
            } else {
                return 'Coming Soon';
            }
        }

        self::$instance = null;
        return $result;
    }

}