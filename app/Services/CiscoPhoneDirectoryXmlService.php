<?php

namespace App\Services;

use App\Models\CiscoPhoneDirectoryEntry;
use App\Models\CiscoPhoneDirectorySetting;
use DOMDocument;
use Illuminate\Support\Collection;

class CiscoPhoneDirectoryXmlService
{
    /** Cisco IP Phone XML: max 32 DirectoryEntry per halaman. */
    public const PAGE_SIZE = 32;

    /** @var CiscoPhoneDirectorySetting|null|false */
    private static $settingsCache = false;

    public static function settings(): ?CiscoPhoneDirectorySetting
    {
        if (self::$settingsCache !== false) {
            return self::$settingsCache;
        }

        try {
            self::$settingsCache = CiscoPhoneDirectorySetting::query()->first();
        } catch (\Throwable $e) {
            self::$settingsCache = null;
        }

        return self::$settingsCache;
    }

    public static function forgetSettingsCache(): void
    {
        self::$settingsCache = false;
    }

    public static function settingsPayload(): array
    {
        $row = self::settings();

        return [
            'title' => $row ? (string) $row->title : 'Phonebook Kantor',
            'base_url' => $row ? (string) ($row->base_url ?? '') : '',
            'cnf_base_url' => $row ? (string) ($row->cnf_base_url ?? '') : '',
            'access_key' => $row ? (string) ($row->access_key ?? '') : '',
            'embed_in_cnf' => $row ? (bool) $row->embed_in_cnf : false,
            'use_directory_url_tag' => $row ? (bool) $row->use_directory_url_tag : false,
        ];
    }

    /**
     * Base URL untuk phonebook (browser/admin). Tidak mempengaruhi APP_URL global.
     * Prioritas: setting DB → CISCO_PHONE_DIRECTORY_BASE_URL → APP_URL_PATH → APP_URL
     */
    public static function directoryBaseForDisplay(): string
    {
        $fromDb = trim((string) (optional(self::settings())->base_url ?? ''));
        if ($fromDb !== '') {
            return rtrim($fromDb, '/');
        }

        $dedicated = trim((string) env('CISCO_PHONE_DIRECTORY_BASE_URL', ''));
        if ($dedicated !== '') {
            return rtrim($dedicated, '/');
        }

        return rtrim((string) (env('APP_URL_PATH') ?: env('APP_URL', '')), '/');
    }

    /**
     * Base URL yang ditulis ke cnf.xml (telepon). Bisa HTTP/IP LAN terpisah dari APP_URL.
     */
    public static function directoryBaseForCnf(): string
    {
        $fromDb = trim((string) (optional(self::settings())->cnf_base_url ?? ''));
        if ($fromDb !== '') {
            return rtrim($fromDb, '/');
        }

        $cnf = trim((string) env('CISCO_PHONE_DIRECTORY_CNF_BASE_URL', ''));
        if ($cnf !== '') {
            return rtrim($cnf, '/');
        }

        return self::directoryBaseForDisplay();
    }

    public static function buildDirectoryUrlFromBase(string $base, array $query = []): string
    {
        $base = rtrim($base, '/');
        if ($base === '') {
            return '';
        }

        $url = $base . '/api/cisco-phone-directory.xml';
        $key = trim((string) (optional(self::settings())->access_key ?? env('CISCO_PHONE_DIRECTORY_KEY', '')));
        if ($key !== '') {
            $query = array_merge(['key' => $key], $query);
        }
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
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

    public function resolveDirectoryUriForDisplay(?string $override = null): string
    {
        return $this->resolveDirectoryUri($override, false);
    }

    /**
     * Default: phonebook sebagai phoneService (on-demand), BUKAN directoryURL.
     * directoryURL di-load saat boot/tombol Directories → 78xx sering stuck "Requesting".
     */
    public static function shouldEmbedInCnf(): bool
    {
        $row = self::settings();
        if ($row !== null) {
            if (!$row->embed_in_cnf) {
                return false;
            }
        } else {
            $raw = env('CISCO_PHONE_EMBED_DIRECTORY_URL');
            if ($raw === null || $raw === '' || !filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }
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

    /** Tulis ke tag directoryURL (umumnya JANGAN — CP-78xx request terus). */
    public static function shouldWriteDirectoryUrlTag(): bool
    {
        $fromDb = optional(self::settings())->use_directory_url_tag;
        $useTag = $fromDb !== null
            ? (bool) $fromDb
            : filter_var(env('CISCO_PHONE_DIRECTORY_USE_DIRECTORY_URL', false), FILTER_VALIDATE_BOOLEAN);

        return self::shouldEmbedInCnf() && $useTag;
    }

    public function resolveDirectoryUriForCnf(?string $override): string
    {
        if (!self::shouldWriteDirectoryUrlTag()) {
            return '';
        }

        $uri = trim((string) $override);
        if ($uri !== '') {
            return $uri;
        }

        return self::defaultDirectoryUrl(true);
    }

    /** URL phoneService type=1 (Directories) — di-fetch hanya saat user buka menu. */
    public function resolvePhoneServiceUrl(): string
    {
        if (!self::shouldEmbedInCnf()) {
            return '';
        }

        return self::defaultDirectoryUrl(true);
    }

    public function activeEntries(?string $search = null): Collection
    {
        $query = CiscoPhoneDirectoryEntry::query()->where('is_active', true);

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('display_name', 'like', $like)
                    ->orWhere('telephone', 'like', $like)
                    ->orWhere('department', 'like', $like);
            });
        }

        return $query->orderBy('sort_order')->orderBy('display_name')->get();
    }

    public function generateForPhone(array $params): string
    {
        $base = self::directoryBaseForCnf() ?: self::directoryBaseForDisplay();
        $mode = (string) ($params['mode'] ?? 'menu');
        $search = trim((string) ($params['name'] ?? ''));
        $start = max(0, (int) ($params['start'] ?? 0));

        if ($mode === 'input') {
            return $this->generateInputXml($base);
        }

        if ($mode === 'list' || $search !== '') {
            return $this->generateDirectoryXml($this->activeEntries($search), $base, $start, $search);
        }

        return $this->generateMenuXml($base);
    }

    public function generateMenuXml(string $base): string
    {
        $doc = $this->newDoc();
        $root = $doc->createElement('CiscoIPPhoneMenu');
        $doc->appendChild($root);

        $this->appendText($doc, $root, 'Title', $this->title());
        $this->appendText($doc, $root, 'Prompt', 'Pilih menu');

        $this->appendMenuItem($doc, $root, 'Semua Kontak', self::buildDirectoryUrlFromBase($base, ['list' => '1']));
        $this->appendMenuItem($doc, $root, 'Cari Nama', self::buildDirectoryUrlFromBase($base, ['input' => '1']));

        return $doc->saveXML() ?: '';
    }

    public function generateInputXml(string $base): string
    {
        $doc = $this->newDoc();
        $root = $doc->createElement('CiscoIPPhoneInput');
        $doc->appendChild($root);

        $this->appendText($doc, $root, 'Title', $this->title());
        $this->appendText($doc, $root, 'Prompt', 'Ketik nama / nomor');
        $this->appendText($doc, $root, 'URL', self::buildDirectoryUrlFromBase($base, ['list' => '1']));

        $item = $doc->createElement('InputItem');
        $this->appendText($doc, $item, 'DisplayName', 'Nama');
        $this->appendText($doc, $item, 'QueryStringParam', 'name');
        $this->appendText($doc, $item, 'DefaultValue', '');
        $this->appendText($doc, $item, 'InputFlags', 'A');
        $root->appendChild($item);

        return $doc->saveXML() ?: '';
    }

    /** @param Collection<int, CiscoPhoneDirectoryEntry>|iterable $entries */
    public function generateDirectoryXml($entries, string $base, int $start = 0, string $search = ''): string
    {
        $all = [];
        foreach ($entries as $entry) {
            if (empty($entry->is_active) && isset($entry->is_active)) {
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
            $all[] = ['name' => $name, 'telephone' => $tel];
        }

        $slice = array_slice($all, $start, self::PAGE_SIZE);
        $hasMore = ($start + self::PAGE_SIZE) < count($all);

        $doc = $this->newDoc();
        $root = $doc->createElement('CiscoIPPhoneDirectory');
        $doc->appendChild($root);

        $this->appendText($doc, $root, 'Title', $this->title());
        $prompt = $search !== '' ? 'Hasil pencarian' : 'Pilih kontak';
        if (count($all) === 0) {
            $prompt = 'Tidak ada kontak';
        }
        $this->appendText($doc, $root, 'Prompt', $prompt);

        foreach ($slice as $row) {
            $dirEntry = $doc->createElement('DirectoryEntry');
            $this->appendText($doc, $dirEntry, 'Name', $this->clip($row['name'], 32));
            $this->appendText($doc, $dirEntry, 'Telephone', $this->clip($row['telephone'], 32));
            $root->appendChild($dirEntry);
        }

        if ($hasMore) {
            $query = ['list' => '1', 'start' => $start + self::PAGE_SIZE];
            if ($search !== '') {
                $query['name'] = $search;
            }
            $soft = $doc->createElement('SoftKeyItem');
            $this->appendText($doc, $soft, 'Name', 'Next');
            $this->appendText($doc, $soft, 'URL', self::buildDirectoryUrlFromBase($base, $query));
            $this->appendText($doc, $soft, 'Position', '4');
            $root->appendChild($soft);
        }

        return $doc->saveXML() ?: '';
    }

    /** @deprecated gunakan generateDirectoryXml */
    public function generateXml($entries): string
    {
        $base = self::directoryBaseForCnf() ?: self::directoryBaseForDisplay();

        return $this->generateDirectoryXml($entries, $base);
    }

    private function title(): string
    {
        $title = trim((string) (optional(self::settings())->title ?: env('CISCO_PHONE_DIRECTORY_TITLE', 'Phonebook Kantor')));

        return $this->clip($title !== '' ? $title : 'Phonebook Kantor', 32);
    }

    private function newDoc(): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'ISO-8859-1');
        $doc->formatOutput = false;

        return $doc;
    }

    private function appendMenuItem(DOMDocument $doc, \DOMElement $parent, string $name, string $url): void
    {
        $item = $doc->createElement('MenuItem');
        $this->appendText($doc, $item, 'Name', $this->clip($name, 64));
        $this->appendText($doc, $item, 'URL', $url);
        $parent->appendChild($item);
    }

    private function appendText(DOMDocument $doc, \DOMElement $parent, string $tag, string $value): void
    {
        $node = $doc->createElement($tag);
        $node->appendChild($doc->createTextNode($this->toIso($value)));
        $parent->appendChild($node);
    }

    private function clip(string $value, int $max): string
    {
        $iso = $this->toIso($value);
        if (strlen($iso) <= $max) {
            return $value;
        }

        return rtrim(substr($iso, 0, $max - 1)) . '.';
    }

    private function toIso(string $value): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
        if ($converted === false) {
            return preg_replace('/[^\x20-\x7E]/', '?', $value) ?: '';
        }

        return $converted;
    }
}