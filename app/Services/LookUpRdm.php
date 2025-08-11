<?php

namespace App\Services;

class LookUpRdm
{
    static function table(){
        $random = [
            3.000248,
            3.150260,
            3.300273,
            3.450285,
            3.600298,
            3.750310,
            3.900322,
            4.050335,
            4.200347,
            4.350360,
        ];

        return $random;
    }


    public function getRdm(){
        $random = self::table();
        $randomKey = array_rand($random);
        return $random[$randomKey];
    }
}