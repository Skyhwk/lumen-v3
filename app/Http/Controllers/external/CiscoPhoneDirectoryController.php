<?php

namespace App\Http\Controllers\external;

use App\Http\Controllers\Controller;
use App\Services\CiscoPhoneDirectoryXmlService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CiscoPhoneDirectoryController extends Controller
{
    public function xml(Request $request)
    {
        $expectedKey = trim((string) (optional(CiscoPhoneDirectoryXmlService::settings())->access_key ?? env('CISCO_PHONE_DIRECTORY_KEY', '')));
        if ($expectedKey !== '' && (string) $request->query('key', '') !== $expectedKey) {
            return $this->ciscoXmlResponse(
                '<?xml version="1.0" encoding="UTF-8"?><CiscoIPPhoneText><Title>Error</Title><Text>Unauthorized</Text></CiscoIPPhoneText>',
                401
            );
        }

        $service = new CiscoPhoneDirectoryXmlService();

        $name = trim((string) ($request->query('name', $request->query('n', ''))));
        $mode = 'menu';
        if ($request->query('input') !== null) {
            $mode = 'input';
        } elseif ($request->query('list') !== null || $name !== '') {
            $mode = 'list';
        }

        $xml = $service->generateForPhone([
            'mode' => $mode,
            'name' => $name,
            'start' => (int) $request->query('start', 0),
            'request_base' => CiscoPhoneDirectoryXmlService::baseFromRequestUrl($request->url()),
        ]);

        return $this->ciscoXmlResponse($xml, 200);
    }

    private function ciscoXmlResponse(string $xml, int $status): Response
    {
        if (trim($xml) === '' || strpos($xml, '<') === false) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?><CiscoIPPhoneText><Title>Phonebook</Title><Text>XML kosong</Text></CiscoIPPhoneText>';
        }

        return response($xml, $status)
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('Content-Encoding', 'identity')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Connection', 'close')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}