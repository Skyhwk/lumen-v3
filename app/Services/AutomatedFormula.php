<?php

namespace App\Services;

use App\Models\Parameter;

class AutomatedFormula {
    
    public $required_parameter;
    public $parameter;
    public $no_sampel;
    public $tanggal_terima;
    public $class_calculate;
    private static $instance;

    public static function where($field, $value){
        if (!self::$instance) {
            self::$instance = new self();
        }

        self::$instance->$field = $value;

        return self::$instance;
    }

    public function calculate(){
        $required_parameter = $this->required_parameter;
        $parameter = $this->parameter;
        $no_sampel = $this->no_sampel;
        $tanggal_terima = $this->tanggal_terima;
        $class_calculate = $this->class_calculate;

        $helperClass = "App\\AutomatedFormula\\{$class_calculate}";

        if (class_exists($helperClass)) {
            $helper = new $helperClass();
            if (method_exists($helper, 'index')) {
                $result = $helper->index($required_parameter, $parameter, $no_sampel, $tanggal_terima);
            } else {
                return 'Coming Soon';
            }
        } else {
            if (method_exists($this, $class_calculate)) {
                $result = $this->$class_calculate($required_parameter, $parameter, $no_sampel, $tanggal_terima);
            } else {
                return 'Coming Soon';
            }
        }

        self::$instance = null;
        return $result;
    }
}
