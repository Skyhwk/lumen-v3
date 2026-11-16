<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\QuotationGenerate\QuotationAutoLinkOpenService;
use Illuminate\Http\Request;

class QuotationAutoLinkOpenController extends Controller
{
    public function openBySlug(Request $request)
    {
        $slug = trim((string) ($request->input('no_quotation', $request->input('slug', ''))));

        try {
            $ip = $request->input('ip_address') ?: $request->ip();
            $userAgent = $request->input('user_agent') ?: $request->userAgent();

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
