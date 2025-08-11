<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use App\Observers\ModelObserver;

class ModelObserverServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerObservers();
    }

    protected function registerObservers()
    {
        $modelFiles = File::allFiles(base_path('app/Models'));

        foreach ($modelFiles as $file) {
            $modelName = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($modelName)) {
                $modelName::observe(ModelObserver::class);
            }
        }
    }
}