<?php

namespace App\Http\Controllers\external;

use App\Services\QuotationGenerate\QuotationAutoLinkOpenService;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class QuotationAutoLinkOpenHandler extends BaseController
{
    public function open(Request $request)
    {
        $expected = (string) env('PUBLIC_TOKEN', '');
        $provided = (string) $request->header('token', '');

        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $slug = trim((string) $request->input('no_quotation', $request->input('slug', '')));
        $ip = $request->input('ip_address') ?: $request->ip();
        $userAgent = $request->input('user_agent') ?: $request->userAgent();

        try {
            $result = (new QuotationAutoLinkOpenService())->recordOpenAndResolveRedirectUrl(
                $slug,
                is_string($ip) ? $ip : null,
                is_string($userAgent) ? $userAgent : null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal membuka link penawaran.'], 500);
        }

        return response()->json([
            'redirect_url' => $result['redirect_url'],
            'open_count' => $result['open_count'],
            'first_open' => $result['first_open'],
        ], 200);
    }
}
