<?php

namespace App\Services;

use App\Models\CiscoPhoneDirectoryEntry;
use DOMDocument;
use Illuminate\Support\Collection;

class CiscoPhoneDirectoryXmlService
{
    /**
     * Base URL untuk phonebook (browser/admin). Tidak mempengaruhi APP_URL global.
     * Prioritas: CISCO_PHONE_DIRECTORY_BASE_URL → APP_URL_PATH → APP_URL
     */
    public static function directoryBaseForDisplay(): string
    {
        $dedicated = trim((string) env('CISCO_PHONE_DIRECTORY_BASE_URL', ''));
        if ($dedicated !== '') {
            return rtrim($dedicated, '/');
        }

        return rtrim((string) (env('APP_URL_PATH') ?: env('APP_URL', '')), '/');
    }

    /**
     * Base URL yang ditulis ke cnf.xml (telepon). Bisa HTTP/IP LAN terpisah dari APP_URL.
     * Prioritas: CISCO_PHONE_DIRECTORY_CNF_BASE_URL → CISCO_PHONE_DIRECTORY_BASE_URL → APP_URL_PATH → APP_URL
     */
    public static function directoryBaseForCnf(): string
    {
        $cnf = trim((string) env('CISCO_PHONE_DIRECTORY_CNF_BASE_URL', ''));
        if ($cnf !== '') {
            return rtrim($cnf, '/');
        }

        return self::directoryBaseForDisplay();
    }

    public static function buildDirectoryUrlFromBase(string $base): string
    {
        $base = rtrim($base, '/');
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

    public static function defaultDirectoryUrl(bool $forCnf = false): string
    {
        $base = $forCnf ? self::directoryBaseForCnf() : self::directoryBaseForDisplay();

        return self::buildDirectoryUrlFromBase($base);
    }

    public function resolveDirectoryUri(?string $override, bool $forCnf = false): string
    {
        $uri = trim((string) $override);

        return $uri !== '' ? $uri : self::defaultDirectoryUrl($forCnf);
    }

    /**
     * Directory URL untuk admin/UI (browser).
     */
    public function resolveDirectoryUriForDisplay(?string $override = null): string
    {
        return $this->resolveDirectoryUri($override, false);
    }

    /**
     * Apakah directoryURL disisipkan ke cnf.xml telepon.
     * Default off: URL yang tidak bisa dijangkau telepon saat boot/provision
     * bisa membuat status register macet ("Registration in progress").
     */
    public static function shouldEmbedInCnf(): bool
    {
        $raw = env('CISCO_PHONE_EMBED_DIRECTORY_URL');
        if ($raw === null || $raw === '') {
            return false;
        }

        if (!filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $url = self::defaultDirectoryUrl(true);
        if ($url === '') {
            return false;
        }

        if (preg_match('#(?:localhost|127\.0\.0\.1|host\.docker\.internal)#i', $url)) {
            return false;
        }

        return true;
    }

    /** URL yang benar-benar ditulis ke cnf.xml (kosong jika embed dimatikan). */
    public function resolveDirectoryUriForCnf(?string $override): string
    {
        if (!self::shouldEmbedInCnf()) {
            return '';
        }

        $uri = trim((string) $override);
        if ($uri !== '') {
            return $uri;
        }

        return self::defaultDirectoryUrl(true);
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