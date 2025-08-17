<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Console\Commands\ScheduleEverySecond;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            ScheduleEverySecond::class,
        ]);
    }
}
