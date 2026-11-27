<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\CiscoPhoneConfig;
use App\Models\CiscoPhoneDirectoryEntry;
use App\Models\CiscoPhoneDirectorySetting;
use App\Services\CiscoPhoneCnfXmlService;
use App\Services\CiscoPhoneDirectoryXmlService;
use App\Services\Crypto;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PesawatTelephoneController extends Controller
{
    private function cnfService(): CiscoPhoneCnfXmlService
    {
        return new CiscoPhoneCnfXmlService();
    }

    public function index()
    {
        $data = CiscoPhoneConfig::where('is_active', true)->latest('id');

        return Datatables::of($data)->make(true);
    }

    public function getDefaults()
    {
        return response()->json([
            'defaults' => CiscoPhoneCnfXmlService::defaultConfig(),
            'phone_vendors' => $this->phoneVendors(),
            'phone_models' => $this->allPhoneModels(),
            'phone_models_by_vendor' => $this->phoneModelsByVendor(),
            'time_zones' => $this->timeZones(),
            'transport_protocols' => ['AUTO', 'UDP', 'TCP', 'TLS'],
            'call_pickup_policies' => [
                'Cisco Call Pickup',
                'Cisco Group Call Pickup',
                'Cisco Other Call Pickup',
            ],
            'group_call_notes' => [
                'call_pickup' => 'Default aktif. Pickup grup membutuhkan konfigurasi pickup group di server SIP/PBX juga.',
                'cnf_join' => 'Default aktif. Ad-hoc 3-way: tekan Confrn (bukan Transfer), panggil peserta ketiga, tekan Confrn lagi untuk gabung. CP-7821 maks. 3 orang di telepon; peserta ke-4+ pakai ruang conference PBX (Meet Me). XML menyertakan maxNumCalls=4 & busyTrigger=2.',
                'meet_me' => 'Default nonaktif (URI kosong). Isi Meet Me URI dari PBX jika ingin conference dial-in.',
                'shared_line' => 'Aktifkan per line untuk shared extension / group appearance.',
            ],
            'preferred_codecs' => [
                ['value' => 'g711ulaw', 'label' => 'G.711 μ-law (PCMU) — disarankan PSTN'],
                ['value' => 'g711alaw', 'label' => 'G.711 A-law (PCMA)'],
                ['value' => 'g729a', 'label' => 'G.729a (low bandwidth)'],
            ],
            'audio_notes' => [
                'pstn' => 'Panggilan ke nomor regular/PSTN sebaiknya pakai PCMU (G.711 μ-law) dan nonaktifkan G.722/G.729 untuk hindari double-transcoding.',
                'vad' => 'Voice Activity Detection (VAD) bisa menyebabkan suara putus-putus/noise saat gateway PSTN. Default: nonaktif.',
            ],
            'tftp_path' => env('CISCO_TFTP_PATH', '/srv/tftp'),
            'directory_uri' => (new CiscoPhoneDirectoryXmlService())->resolveDirectoryUriForDisplay(''),
            'directory_uri_cnf' => CiscoPhoneDirectoryXmlService::defaultDirectoryUrl(true),
            'phonebook_embed_in_cnf' => CiscoPhoneDirectoryXmlService::shouldEmbedInCnf(),
            'phonebook_use_directory_url_tag' => CiscoPhoneDirectoryXmlService::shouldWriteDirectoryUrlTag(),
            'phonebook_settings' => CiscoPhoneDirectoryXmlService::settingsPayload(),
            'phonebook_entry_count' => CiscoPhoneDirectoryEntry::where('is_active', true)->count(),
            'phonebook_next_sort_order' => $this->nextPhonebookSortOrder(),
        ], 200);
    }

    public function phonebookIndex()
    {
        $data = CiscoPhoneDirectoryEntry::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        return Datatables::of($data)
            ->orderColumn('display_name', 'display_name $1')
            ->orderColumn('telephone', 'telephone $1')
            ->orderColumn('department', 'department $1')
            ->orderColumn('sort_order', 'sort_order $1')
            ->make(true);
    }

    public function phonebookStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'display_name' => 'required|string|max:120',
            'telephone' => 'required|string|max:40',
            'department' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $telephone = preg_replace('/\s+/', '', trim((string) $request->telephone));

        if ($request->id) {
            $entry = CiscoPhoneDirectoryEntry::find($request->id);
            if (!$entry) {
                return response()->json(['message' => 'Kontak tidak ditemukan'], 404);
            }
        } else {
            $entry = new CiscoPhoneDirectoryEntry();
        }

        $entry->display_name = trim((string) $request->display_name);
        $entry->telephone = $telephone;
        $entry->department = trim((string) ($request->department ?? '')) ?: null;
        $entry->is_active = filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN);

        if (!$request->id) {
            $entry->sort_order = $this->nextPhonebookSortOrder();
        }

        $entry->save();

        return response()->json([
            'message' => $request->id ? 'Kontak phonebook diperbarui' : 'Kontak phonebook ditambahkan',
            'data' => $entry,
        ], 200);
    }

    public function phonebookDestroy(Request $request)
    {
        $entry = CiscoPhoneDirectoryEntry::where('id', $request->id)->first();
        if (!$entry) {
            return response()->json(['message' => 'Kontak tidak ditemukan'], 404);
        }

        $entry->delete();
        $this->normalizePhonebookSortOrder();

        return response()->json(['message' => 'Kontak phonebook dihapus'], 200);
    }

    public function phonebookMove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'direction' => 'required|in:up,down',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $entries = CiscoPhoneDirectoryEntry::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $entries->search(function ($row) use ($request) {
            return (int) $row->id === (int) $request->id;
        });

        if ($index === false) {
            return response()->json(['message' => 'Kontak tidak ditemukan'], 404);
        }

        $targetIndex = $request->direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= $entries->count()) {
            return response()->json(['message' => 'Sudah di posisi paling ' . ($request->direction === 'up' ? 'atas' : 'bawah')], 400);
        }

        $list = $entries->values()->all();
        $moving = $list[$index];
        array_splice($list, $index, 1);
        array_splice($list, $targetIndex, 0, [$moving]);

        foreach ($list as $position => $row) {
            $order = $position + 1;
            if ((int) $row->sort_order !== $order) {
                CiscoPhoneDirectoryEntry::where('id', $row->id)->update(['sort_order' => $order]);
            }
        }

        return response()->json(['message' => 'Urutan kontak diperbarui'], 200);
    }

    public function phonebookSettingsSave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:32',
            'base_url' => 'nullable|string|max:255',
            'cnf_base_url' => 'nullable|string|max:255',
            'access_key' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $row = CiscoPhoneDirectorySetting::current();
        $title = trim((string) ($request->title ?? ''));
        $row->title = $title !== '' ? mb_substr($title, 0, 32) : 'Phonebook Kantor';
        $row->base_url = rtrim(trim((string) ($request->base_url ?? '')), '/') ?: null;
        $row->cnf_base_url = rtrim(trim((string) ($request->cnf_base_url ?? '')), '/') ?: null;
        $row->access_key = trim((string) ($request->access_key ?? '')) ?: null;
        $row->embed_in_cnf = filter_var($request->embed_in_cnf ?? false, FILTER_VALIDATE_BOOLEAN);
        $row->use_directory_url_tag = filter_var($request->use_directory_url_tag ?? false, FILTER_VALIDATE_BOOLEAN);
        $row->save();

        CiscoPhoneDirectoryXmlService::forgetSettingsCache();

        return response()->json([
            'message' => 'Pengaturan phonebook disimpan',
            'settings' => CiscoPhoneDirectoryXmlService::settingsPayload(),
            'directory_uri' => (new CiscoPhoneDirectoryXmlService())->resolveDirectoryUriForDisplay(''),
            'directory_uri_cnf' => CiscoPhoneDirectoryXmlService::defaultDirectoryUrl(true),
            'phonebook_embed_in_cnf' => CiscoPhoneDirectoryXmlService::shouldEmbedInCnf(),
        ], 200);
    }

    public function regenerateAll(Request $request)
    {
        return $this->regenerateActivePhoneConfigs(false);
    }

    /**
     * Rollback aman: cnf.xml tanpa directoryURL (untuk pulihkan register setelah regenerate phonebook).
     */
    public function regenerateAllSafe(Request $request)
    {
        try {
            $settings = CiscoPhoneDirectorySetting::current();
            $settings->embed_in_cnf = false;
            $settings->use_directory_url_tag = false;
            $settings->save();
            CiscoPhoneDirectoryXmlService::forgetSettingsCache();
        } catch (\Throwable $e) {
            // tabel pengaturan mungkin belum di-migrate
        }

        return $this->regenerateActivePhoneConfigs(true);
    }

    public function show(Request $request)
    {
        $config = CiscoPhoneConfig::where('id', $request->id)->where('is_active', true)->first();

        if (!$config) {
            return response()->json(['message' => 'Konfigurasi tidak ditemukan'], 404);
        }

        $payload = $config->toArray();
        $crypto = new Crypto();

        if (!empty($payload['auth_password'])) {
            $payload['auth_password'] = $crypto->decrypt($payload['auth_password']);
        }

        if (!empty($payload['phone_password'])) {
            $payload['phone_password'] = $crypto->decrypt($payload['phone_password']);
        }

        if (!empty($payload['config_json']['lines'])) {
            foreach ($payload['config_json']['lines'] as $index => $line) {
                if (!empty($line['auth_password'])) {
                    try {
                        $payload['config_json']['lines'][$index]['auth_password'] = $crypto->decrypt($line['auth_password']);
                    } catch (\Throwable $e) {
                        // password line mungkin plain dari input lama
                    }
                }
            }
        }

        return response()->json($payload, 200);
    }

    public function preview(Request $request)
    {
        try {
            $config = $this->buildConfigPayload($request);
            $xml = $this->cnfService()->generate(
                $config['config_json'],
                $config['mac_address'],
                $config['phone_model'],
                $config['extension'],
                $request->auth_password
            );

            return response()->json([
                'xml' => $xml,
                'filename' => CiscoPhoneCnfXmlService::buildFilename($config['mac_address']),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mac_address' => 'required|string|min:12|max:17',
            'phone_model' => 'required|string|max:50',
            'extension' => 'required|string|max:30',
            'auth_password' => 'required|string|max:100',
            'sip_server' => 'required|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $payload = $this->buildConfigPayload($request);
            $mac = $payload['mac_address'];

            $existing = CiscoPhoneConfig::where('mac_address', $mac)->where('is_active', true)->first();
            if ($existing && (!$request->id || (int) $request->id !== (int) $existing->id)) {
                return response()->json(['message' => 'MAC address sudah terdaftar'], 400);
            }

            $config = $request->id
                ? CiscoPhoneConfig::where('id', $request->id)->where('is_active', true)->firstOrFail()
                : new CiscoPhoneConfig();

            $oldMac = $config->mac_address;

            $crypto = new Crypto();
            $encryptedLinePasswords = [];
            foreach ($payload['config_json']['lines'] ?? [] as $line) {
                $linePass = $line['auth_password'] ?? $request->auth_password;
                $encryptedLinePasswords[] = $linePass ? $crypto->encrypt($linePass) : null;
            }

            $configJson = $payload['config_json'];
            foreach ($configJson['lines'] ?? [] as $index => &$line) {
                if (isset($encryptedLinePasswords[$index])) {
                    $line['auth_password'] = $encryptedLinePasswords[$index];
                }
            }
            unset($line);

            $config->mac_address = $mac;
            $config->label = $request->label;
            $config->phone_model = $payload['phone_model'];
            $config->extension = $payload['extension'];
            $config->display_name = $payload['display_name'] ?: $payload['extension'];
            $config->sip_server = $payload['sip_server'];
            $config->auth_name = $payload['auth_name'] ?: $payload['extension'];
            $config->auth_password = $crypto->encrypt($request->auth_password);
            $config->phone_password = $request->phone_password
                ? $crypto->encrypt($request->phone_password)
                : null;
            $config->config_json = $configJson;
            $config->created_by = $config->created_by ?: ($this->user_id ?? null);
            $config->is_active = true;
            $config->save();

            $plainConfig = CiscoPhoneCnfXmlService::mergeConfig(
                $this->plainConfigForXml($payload['config_json'], $request->auth_password)
            );

            $primaryLinePassword = $plainConfig['lines'][0]['auth_password'] ?? $request->auth_password;

            $xml = $this->cnfService()->generate(
                $plainConfig,
                $mac,
                $payload['phone_model'],
                $payload['extension'],
                $primaryLinePassword
            );

            $fileInfo = $this->cnfService()->writeToTftp($xml, $mac);

            if ($oldMac && $oldMac !== $mac) {
                $this->cnfService()->removeTftpFile($oldMac);
            }

            $config->cnf_filename = $fileInfo['filename'];
            $config->cnf_file_path = $fileInfo['path'];
            $config->last_generated_at = Carbon::now();
            $config->save();

            return response()->json([
                'message' => 'Konfigurasi pesawat telepon berhasil disimpan dan file cnf.xml telah dibuat',
                'filename' => $fileInfo['filename'],
                'path' => $fileInfo['path'],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function regenerate(Request $request)
    {
        $config = CiscoPhoneConfig::where('id', $request->id)->where('is_active', true)->first();

        if (!$config) {
            return response()->json(['message' => 'Konfigurasi tidak ditemukan'], 404);
        }

        $safe = filter_var($request->safe_mode ?? $request->strip_directory ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $result = $this->regenerateSinglePhoneConfig($config, $safe);
            if ($result === null) {
                return response()->json(['message' => 'Gagal generate cnf'], 500);
            }

            return response()->json([
                'message' => $safe
                    ? 'Rollback cnf.xml berhasil — directoryURL dihapus'
                    : 'File cnf.xml berhasil di-generate ulang',
                'filename' => $result['filename'],
                'path' => $result['path'],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        $config = CiscoPhoneConfig::where('id', $request->id)->where('is_active', true)->first();

        if (!$config) {
            return response()->json(['message' => 'Konfigurasi tidak ditemukan'], 404);
        }

        $removeFile = filter_var($request->remove_file ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($removeFile) {
            $this->cnfService()->removeTftpFile($config->mac_address);
        }

        $config->is_active = false;
        $config->save();

        return response()->json(['message' => 'Konfigurasi pesawat telepon berhasil dihapus'], 200);
    }

    private function buildConfigPayload(Request $request): array
    {
        $mac = CiscoPhoneCnfXmlService::normalizeMac($request->mac_address);
        $defaults = CiscoPhoneCnfXmlService::defaultConfig();

        $configJson = $request->config_json;
        if (is_string($configJson)) {
            $configJson = json_decode($configJson, true) ?: [];
        }
        if (!is_array($configJson)) {
            $configJson = [];
        }

        // Lines dari request adalah sumber kebenaran (hindari default merge menimpa edit)
        $lines = $this->parseLinesFromRequest($request, $configJson);

        $defaultsWithoutLines = $defaults;
        unset($defaultsWithoutLines['lines']);
        $configJson = array_replace_recursive($defaultsWithoutLines, $configJson);
        if (!empty($lines)) {
            $configJson['lines'] = $lines;
        }

        $configJson = CiscoPhoneCnfXmlService::mergeConfig($configJson);

        $configJson['sip_server'] = $request->sip_server ?: ($configJson['sip_server'] ?? $defaults['sip_server']);
        // IP PBX di callManager/processNodeName (backup CP-78xx pakai IP LAN PBX, bukan hostname)
        $configJson['call_manager_host'] = trim((string) ($request->input(
            'call_manager_host',
            $configJson['call_manager_host'] ?? ''
        )));
        $configJson['phone_password'] = $request->phone_password ?? ($configJson['phone_password'] ?? '');
        $configJson['date_template'] = $request->date_template ?? ($configJson['date_template'] ?? $defaults['date_template']);
        $configJson['time_zone'] = $request->time_zone ?? ($configJson['time_zone'] ?? $defaults['time_zone']);
        $configJson['nat_enabled'] = filter_var($request->nat_enabled ?? ($configJson['nat_enabled'] ?? false), FILTER_VALIDATE_BOOLEAN);
        $configJson['transport_layer_protocol'] = $request->transport_layer_protocol ?? ($configJson['transport_layer_protocol'] ?? 'AUTO');
        $configJson['timer_register_expires'] = (int) ($request->timer_register_expires ?? ($configJson['timer_register_expires'] ?? 3600));

        if ($request->filled('ntp_servers')) {
            $ntpServers = $request->ntp_servers;
            if (is_string($ntpServers)) {
                $ntpServers = json_decode($ntpServers, true) ?: [];
            }
            if (is_array($ntpServers)) {
                $configJson['ntp_servers'] = $ntpServers;
            }
        }

        $line1 = $configJson['lines'][0] ?? null;
        $extension = trim((string) ($line1['name'] ?? $request->extension ?? ''));

        if ($line1 && $extension !== '') {
            $configJson['lines'][0]['name'] = $extension;
            $configJson['lines'][0]['display_name'] = $line1['display_name'] ?? ($request->display_name ?: $extension);
            $configJson['lines'][0]['auth_name'] = $line1['auth_name'] ?? ($request->auth_name ?: $extension);
            $configJson['lines'][0]['contact'] = $line1['contact'] ?? $extension;
            $configJson['lines'][0]['proxy'] = $line1['proxy'] ?? $configJson['sip_server'];
            if (empty($configJson['lines'][0]['auth_password'])) {
                $configJson['lines'][0]['auth_password'] = $request->auth_password;
            }
        } elseif ($extension !== '') {
            $configJson['lines'][0] = array_replace_recursive(
                $defaults['lines'][0] ?? [],
                $configJson['lines'][0] ?? []
            );
            $configJson['lines'][0]['name'] = $extension;
            $configJson['lines'][0]['display_name'] = $request->display_name ?: $extension;
            $configJson['lines'][0]['auth_name'] = $request->auth_name ?: $extension;
            $configJson['lines'][0]['auth_password'] = $request->auth_password;
            $configJson['lines'][0]['contact'] = $extension;
            $configJson['lines'][0]['proxy'] = $configJson['sip_server'];
        }

        return [
            'mac_address' => $mac,
            'phone_model' => trim((string) $request->phone_model),
            'extension' => $extension,
            'display_name' => $configJson['lines'][0]['display_name'] ?? ($request->display_name ?: $extension),
            'auth_name' => $configJson['lines'][0]['auth_name'] ?? ($request->auth_name ?: $extension),
            'sip_server' => $configJson['sip_server'],
            'config_json' => $configJson,
        ];
    }

    private function parseLinesFromRequest(Request $request, array $configJson): array
    {
        if ($request->has('lines')) {
            $rawLines = $request->lines;
            if (is_string($rawLines)) {
                $decoded = json_decode($rawLines, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    return $this->normalizeLinesArray($decoded, $configJson['sip_server'] ?? null);
                }
            }
            if (is_array($rawLines) && count($rawLines) > 0) {
                return $this->normalizeLinesArray($rawLines, $configJson['sip_server'] ?? null);
            }
        }

        if (!empty($configJson['lines']) && is_array($configJson['lines'])) {
            return $this->normalizeLinesArray($configJson['lines'], $configJson['sip_server'] ?? null);
        }

        return [];
    }

    private function normalizeLinesArray(array $lines, ?string $sipServer = null): array
    {
        return array_values(array_map(function ($line, $index) use ($sipServer) {
            if (!is_array($line)) {
                return [];
            }

            $line['button'] = $index + 1;
            $line['feature_id'] = $line['feature_id'] ?? 9;
            $line['proxy'] = $line['proxy'] ?? $sipServer ?? '';
            $line['port'] = (int) ($line['port'] ?? 5060);
            $line['shared_line'] = filter_var($line['shared_line'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $line['auto_answer'] = filter_var($line['auto_answer'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $line['caller_name'] = filter_var($line['caller_name'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $line['caller_number'] = filter_var($line['caller_number'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $line['redirected_number'] = filter_var($line['redirected_number'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $line['dialed_number'] = filter_var($line['dialed_number'] ?? true, FILTER_VALIDATE_BOOLEAN);

            return $line;
        }, $lines, array_keys($lines)));
    }

    private function plainConfigForXml(array $configJson, string $fallbackPassword): array
    {
        $plain = $configJson;
        foreach ($plain['lines'] ?? [] as $index => &$line) {
            if (empty($line['auth_password'])) {
                $line['auth_password'] = $fallbackPassword;
            }
        }
        unset($line);

        return $plain;
    }

    private function nextPhonebookSortOrder(): int
    {
        $max = (int) CiscoPhoneDirectoryEntry::max('sort_order');

        return $max + 1;
    }

    private function normalizePhonebookSortOrder(): void
    {
        $entries = CiscoPhoneDirectoryEntry::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($entries as $position => $entry) {
            $order = $position + 1;
            if ((int) $entry->sort_order !== $order) {
                $entry->sort_order = $order;
                $entry->save();
            }
        }
    }

    private function regenerateActivePhoneConfigs(bool $forceEmptyDirectory): \Illuminate\Http\JsonResponse
    {
        $configs = CiscoPhoneConfig::where('is_active', true)->get();
        if ($configs->isEmpty()) {
            return response()->json(['message' => 'Tidak ada konfigurasi telepon aktif'], 400);
        }

        $ok = 0;
        $failed = [];

        foreach ($configs as $config) {
            try {
                $result = $this->regenerateSinglePhoneConfig($config, $forceEmptyDirectory);
                if ($result === null) {
                    throw new \RuntimeException('Generate cnf gagal');
                }
                $ok++;
            } catch (\Throwable $e) {
                $failed[] = ['id' => $config->id, 'mac' => $config->mac_address, 'error' => $e->getMessage()];
            }
        }

        $label = $forceEmptyDirectory
            ? 'Rollback provisioning selesai (tanpa directoryURL dan tanpa layanan Phonebook)'
            : 'Generate ulang selesai';

        return response()->json([
            'message' => "{$label}: {$ok} berhasil" . (count($failed) ? ', ' . count($failed) . ' gagal' : ''),
            'success_count' => $ok,
            'failed' => $failed,
            'safe_mode' => $forceEmptyDirectory,
        ], 200);
    }

    private function regenerateSinglePhoneConfig(CiscoPhoneConfig $config, bool $forceEmptyDirectory = false): ?array
    {
        $crypto = new Crypto();
        $authPassword = $crypto->decrypt($config->auth_password);
        $plainConfig = CiscoPhoneCnfXmlService::mergeConfig($config->config_json ?? []);

        if (!empty($plainConfig['lines'])) {
            foreach ($plainConfig['lines'] as $index => $line) {
                if (!empty($line['auth_password'])) {
                    try {
                        $plainConfig['lines'][$index]['auth_password'] = $crypto->decrypt($line['auth_password']);
                    } catch (\Throwable $e) {
                        $plainConfig['lines'][$index]['auth_password'] = $authPassword;
                    }
                } else {
                    $plainConfig['lines'][$index]['auth_password'] = $authPassword;
                }
            }
        }

        if ($forceEmptyDirectory) {
            $plainConfig['directory_uri'] = '';
            $plainConfig['skip_phonebook_service'] = true;
        }

        $primaryLinePassword = $plainConfig['lines'][0]['auth_password'] ?? $authPassword;

        $xml = $this->cnfService()->generate(
            $plainConfig,
            $config->mac_address,
            $config->phone_model,
            $config->extension,
            $primaryLinePassword,
            $forceEmptyDirectory
        );

        $fileInfo = $this->cnfService()->writeToTftp($xml, $config->mac_address);

        if ($forceEmptyDirectory) {
            $plainConfig['directory_uri'] = '';
        }
        $this->persistDirectoryUriOnConfig($config, $plainConfig);

        $config->cnf_filename = $fileInfo['filename'];
        $config->cnf_file_path = $fileInfo['path'];
        $config->last_generated_at = Carbon::now();
        $config->save();

        return $fileInfo;
    }

    private function persistDirectoryUriOnConfig(CiscoPhoneConfig $config, array $mergedPlainConfig): void
    {
        $json = $config->config_json ?? [];
        if (!is_array($json)) {
            $json = [];
        }
        $uri = trim((string) ($mergedPlainConfig['directory_uri'] ?? ''));
        if ($uri !== '') {
            $json['directory_uri'] = $uri;
        } else {
            unset($json['directory_uri']);
        }
        $config->config_json = $json;
    }

    private function phoneVendors(): array
    {
        return [
            ['id' => 'cisco', 'label' => 'Cisco / Linksys SPA'],
            ['id' => 'yealink', 'label' => 'Yealink'],
            ['id' => 'grandstream', 'label' => 'Grandstream'],
            ['id' => 'polycom', 'label' => 'Polycom'],
            ['id' => 'fanvil', 'label' => 'Fanvil'],
            ['id' => 'other', 'label' => 'Lainnya'],
        ];
    }

    private function allPhoneModels(): array
    {
        return array_values(array_unique(array_merge(
            $this->ciscoModels(),
            $this->yealinkModels(),
            $this->grandstreamModels(),
            $this->polycomModels(),
            $this->fanvilModels(),
            $this->otherModels()
        )));
    }

    /** Daftar model per vendor (id = phone_vendors.id). */
    private function phoneModelsByVendor(): array
    {
        return [
            'cisco' => $this->ciscoModels(),
            'yealink' => $this->yealinkModels(),
            'grandstream' => $this->grandstreamModels(),
            'polycom' => $this->polycomModels(),
            'fanvil' => $this->fanvilModels(),
            'other' => $this->otherModels(),
        ];
    }

    private function ciscoModels(): array
    {
        return [
            'CP-3905', 'CP-3911', 'CP-3925', 'CP-3941', 'CP-3945', 'CP-3951', 'CP-3965',
            'CP-7821', 'CP-7841', 'CP-7851', 'CP-7861', 'CP-8811', 'CP-8821', 'CP-8841',
            'CP-8851', 'CP-8861', 'CP-8941', 'CP-8945', 'CP-8961', 'CP-9951', 'CP-9971',
            'SPA303', 'SPA504G', 'SPA508G', 'SPA509G', 'SPA512G', 'SPA514G', 'SPA525G',
            'ATA191', 'ATA192',
        ];
    }

    private function yealinkModels(): array
    {
        return ['T19P E2', 'T21P E2', 'T23G', 'T27G', 'T31P', 'T33G', 'T42G', 'T43U', 'T46S', 'T48S', 'W52P', 'W60B'];
    }

    private function grandstreamModels(): array
    {
        return ['GXP1610', 'GXP1620', 'GXP1625', 'GXP2130', 'GXP2140', 'GXP2160', 'GXP2170', 'GRP2601', 'GRP2602', 'WP820'];
    }

    private function polycomModels(): array
    {
        return ['VVX 311', 'VVX 411', 'VVX 501'];
    }

    private function fanvilModels(): array
    {
        return ['X3SG', 'X5S', 'X6'];
    }

    private function otherModels(): array
    {
        return ['Custom'];
    }

    private function phoneModels(): array
    {
        return $this->allPhoneModels();
    }

    private function timeZones(): array
    {
        return [
            'SE Asia Standard Time',
            'Central Asia Standard Time',
            'Singapore Standard Time',
            'Tokyo Standard Time',
            'China Standard Time',
            'GMT Standard Time',
            'W. Europe Standard Time',
            'Eastern Standard Time',
            'Pacific Standard Time',
            'AUS Eastern Standard Time',
        ];
    }
}
