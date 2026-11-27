<?php

namespace App\Http\Controllers\external;

use App\Http\Controllers\Controller;
use App\Services\CiscoPhoneDirectoryXmlService;
use Illuminate\Http\Request;

class CiscoPhoneDirectoryController extends Controller
{
    public function xml(Request $request)
    {
        $expectedKey = trim((string) env('CISCO_PHONE_DIRECTORY_KEY', ''));
        if ($expectedKey !== '' && (string) $request->query('key', '') !== $expectedKey) {
            return response('Unauthorized', 401);
        }

        $service = new CiscoPhoneDirectoryXmlService();
        $xml = $service->generateXml($service->activeEntries());

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }
}