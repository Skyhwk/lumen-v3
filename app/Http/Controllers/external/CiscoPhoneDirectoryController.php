<?php

namespace App\Http\Controllers\external;

use App\Services\CiscoPhoneDirectoryXmlService;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class CiscoPhoneDirectoryController extends BaseController
{
    public function xml(Request $request)
    {
        try {
            $expectedKey = trim((string) (optional(CiscoPhoneDirectoryXmlService::settings())->access_key ?? env('CISCO_PHONE_DIRECTORY_KEY', '')));
            if ($expectedKey !== '' && (string) $request->query('key', '') !== $expectedKey) {
                return $this->ciscoXmlResponse($this->textXml('Error', 'Unauthorized'), 401);
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
        } catch (\Throwable $e) {
            return $this->ciscoXmlResponse($this->textXml('Phonebook', 'Error: ' . $e->getMessage()), 200);
        }
    }

    private function textXml(string $title, string $text): string
    {
        $title = htmlspecialchars(preg_replace('/[^\x20-\x7E]/', '?', $title) ?: 'Phonebook', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars(preg_replace('/[^\x20-\x7E]/', '?', $text) ?: 'Error', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<CiscoIPPhoneText><Title>' . $title . '</Title><Text>' . $text . '</Text></CiscoIPPhoneText>';
    }

    private function ciscoXmlResponse(string $xml, int $status)
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', (string) $xml);
        $xml = ltrim((string) $xml);
        if ($xml === '' || strpos($xml, '<') !== 0) {
            $xml = $this->textXml('Phonebook', 'XML kosong');
        }

        return response($xml, $status)
            ->header('Content-Type', 'text/xml; charset=utf-8')
            ->header('Content-Encoding', 'identity')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Connection', 'close')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
