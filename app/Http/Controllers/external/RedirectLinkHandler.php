<?php

namespace App\Http\Controllers\external;

use App\Models\RedirectLink;
use Laravel\Lumen\Routing\Controller as BaseController;

class RedirectLinkHandler extends BaseController
{
    public function redirect($key)
    {
        $redirectLink = RedirectLink::where('batch_key', $key)
            ->where('is_active', true)
            ->first();

        if (!$redirectLink) {
            return response('Link tidak ditemukan atau sudah tidak aktif.', 404);
        }

        RedirectLink::where('id', $redirectLink->id)->increment('visit_count');

        return response('', 302, ['Location' => $redirectLink->target_url]);
    }
}
