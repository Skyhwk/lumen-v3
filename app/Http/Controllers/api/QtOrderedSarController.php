<?php

namespace App\Http\Controllers\api;

class QtOrderedSarController extends QtOrderedController
{
    protected function getStatusSamplingFilter()
    {
        return 'SAR';
    }
}
