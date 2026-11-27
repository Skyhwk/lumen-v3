<?php

namespace App\Services;

use App\Models\CiscoPhoneDirectoryEntry;
use DOMDocument;
use Illuminate\Support\Collection;

class CiscoPhoneDirectoryXmlService
{
    public static function defaultDirectoryUrl(): string
    {
        $base = rtrim((string) (env('APP_URL_PATH') ?: env('APP_URL', '')), '/');
        if ($base === '') {
            return '';
        }

        $url = $base . '/api/cisco-phone-directory.xml';
        $key = trim((string) env('CISCO_PHONE_DIRECTORY_KEY', ''));
        if ($key !== '') {
            $url .= '?key=' . rawurlencode($key);
        }

        return $url;
    }

    public function resolveDirectoryUri(?string $override): string
    {
        $uri = trim((string) $override);

        return $uri !== '' ? $uri : self::defaultDirectoryUrl();
    }

    public function activeEntries(): Collection
    {
        return CiscoPhoneDirectoryEntry::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();
    }

    /** @param Collection<int, CiscoPhoneDirectoryEntry>|iterable $entries */
    public function generateXml($entries): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('CiscoIPPhoneDirectory');
        $doc->appendChild($root);

        $title = trim((string) env('CISCO_PHONE_DIRECTORY_TITLE', 'Phonebook Kantor'));
        $this->appendText($doc, $root, 'Title', $title !== '' ? $title : 'Phonebook Kantor');
        $this->appendText($doc, $root, 'Prompt', 'Cari nama');

        foreach ($entries as $entry) {
            if (!$entry->is_active) {
                continue;
            }
            $name = trim((string) $entry->display_name);
            $tel = preg_replace('/\s+/', '', trim((string) $entry->telephone));
            if ($name === '' || $tel === '') {
                continue;
            }

            $dept = trim((string) ($entry->department ?? ''));
            if ($dept !== '') {
                $name .= ' (' . $dept . ')';
            }

            $dirEntry = $doc->createElement('DirectoryEntry');
            $this->appendText($doc, $dirEntry, 'Name', $name);
            $this->appendText($doc, $dirEntry, 'Telephone', $tel);
            $root->appendChild($dirEntry);
        }

        return $doc->saveXML() ?: '<?xml version="1.0" encoding="UTF-8"?><CiscoIPPhoneDirectory/>';
    }

    private function appendText(DOMDocument $doc, \DOMElement $parent, string $tag, string $value): void
    {
        $node = $doc->createElement($tag);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);
    }
}