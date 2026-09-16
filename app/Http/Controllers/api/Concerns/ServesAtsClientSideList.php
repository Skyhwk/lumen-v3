<?php

namespace App\Http\Controllers\api\Concerns;

trait ServesAtsClientSideList
{
    /**
     * Return full dataset as JSON array when client_side=1 (no server-side paging/filter/sort).
     */
    protected function serveAtsClientSideList($datatable)
    {
        if (!request()->boolean('client_side')) {
            return null;
        }

        request()->merge([
            'start' => 0,
            'length' => -1,
            'draw' => (int) request()->input('draw', 1),
            'search' => ['value' => '', 'regex' => false],
            'order' => [],
        ]);

        $payload = json_decode($datatable->make(true)->getContent(), true);

        return response()->json([
            'status' => 'success',
            'data' => $payload['data'] ?? [],
        ]);
    }
}
