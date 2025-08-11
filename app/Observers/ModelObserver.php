<?php
namespace App\Observers;

use Illuminate\Support\Facades\Log;

class ModelObserver
{

    public function updating($model)
    {
        $original = $model->getOriginal();
        $changes = $model->getDirty();
        Log::channel('transaction')->info('Before Update Model '.get_class($model).':', ['original' => $original]);
        Log::channel('transaction')->info('Changes:', ['changes' => $changes]);
    }

    public function updated($model)
    {
        Log::channel('transaction')->info('After Update Model '.get_class($model).':', ['commit' => $model->toArray()]);
    }


    public function deleted($model)
    {
        Log::channel('delete')->info('Delete from Model '.get_class($model).':', ['data' => $model->toArray()]);
    }
}