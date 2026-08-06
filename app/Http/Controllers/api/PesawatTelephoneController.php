<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\CiscoPhoneConfig;
use App\Services\CiscoPhoneCnfXmlService;
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
            'time_zones' => $this->timeZones(),
            'transport_protocols' => ['UDP', 'TCP', 'TLS'],
            'call_pickup_policies' => [
                'Cisco Call Pickup',
                'Cisco Group Call Pickup',
                'Cisco Other Call Pickup',
            ],
            'group_call_notes' => [
                'call_pickup' => 'Default aktif. Pickup grup membutuhkan konfigurasi pickup group di server SIP/PBX juga.',
                'cnf_join' => 'Default aktif. Bergabung ke conference call yang sudah berjalan (tombol Join/CNF di telepon).',
                'meet_me' => 'Default nonaktif (URI kosong). Isi Meet Me URI dari PBX jika ingin conference dial-in.',
                'shared_line' => 'Aktifkan per line untuk shared extension / group appearance.',
            ],
            'tftp_path' => env('CISCO_TFTP_PATH', '/srv/tftp'),
        ], 200);
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

            $existing = CiscoPhoneConfig::where('mac_address', $mac)->first();
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
            $config->display_name = $request->display_name ?: $payload['extension'];
            $config->sip_server = $payload['sip_server'];
            $config->auth_name = $request->auth_name ?: $payload['extension'];
            $config->auth_password = $crypto->encrypt($request->auth_password);
            $config->phone_password = $request->phone_password
                ? $crypto->encrypt($request->phone_password)
                : null;
            $config->config_json = $configJson;
            $config->created_by = $config->created_by ?: ($this->user_id ?? null);
            $config->is_active = true;
            $config->save();

            $plainConfig = $payload['config_json'];
            foreach ($plainConfig['lines'] ?? [] as $index => &$line) {
                $line['auth_password'] = $request->auth_password;
            }
            unset($line);

            $xml = $this->cnfService()->generate(
                $plainConfig,
                $mac,
                $payload['phone_model'],
                $payload['extension'],
                $request->auth_password
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

        try {
            $crypto = new Crypto();
            $authPassword = $crypto->decrypt($config->auth_password);
            $plainConfig = $config->config_json ?? [];

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

            $xml = $this->cnfService()->generate(
                $plainConfig,
                $config->mac_address,
                $config->phone_model,
                $config->extension,
                $authPassword
            );

            $fileInfo = $this->cnfService()->writeToTftp($xml, $config->mac_address);

            $config->cnf_filename = $fileInfo['filename'];
            $config->cnf_file_path = $fileInfo['path'];
            $config->last_generated_at = Carbon::now();
            $config->save();

            return response()->json([
                'message' => 'File cnf.xml berhasil di-generate ulang',
                'filename' => $fileInfo['filename'],
                'path' => $fileInfo['path'],
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

        $configJson = CiscoPhoneCnfXmlService::mergeConfig(array_replace_recursive($defaults, $configJson));

        $configJson['sip_server'] = $request->sip_server ?: ($configJson['sip_server'] ?? $defaults['sip_server']);
        $configJson['phone_password'] = $request->phone_password ?? ($configJson['phone_password'] ?? '');
        $configJson['date_template'] = $request->date_template ?? ($configJson['date_template'] ?? $defaults['date_template']);
        $configJson['time_zone'] = $request->time_zone ?? ($configJson['time_zone'] ?? $defaults['time_zone']);
        $configJson['nat_enabled'] = filter_var($request->nat_enabled ?? ($configJson['nat_enabled'] ?? false), FILTER_VALIDATE_BOOLEAN);
        $configJson['transport_layer_protocol'] = $request->transport_layer_protocol ?? ($configJson['transport_layer_protocol'] ?? 'UDP');
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

        if ($request->filled('lines')) {
            $lines = $request->lines;
            if (is_string($lines)) {
                $lines = json_decode($lines, true) ?: [];
            }
            if (is_array($lines) && count($lines) > 0) {
                $configJson['lines'] = $lines;
            }
        }

        $extension = trim((string) $request->extension);
        if (empty($configJson['lines'][0]['name'])) {
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
            'sip_server' => $configJson['sip_server'],
            'config_json' => $configJson,
        ];
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
            $this->otherModels()
        )));
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

    private function otherModels(): array
    {
        return ['VVX 311', 'VVX 411', 'VVX 501', 'X3SG', 'X5S', 'X6', 'Custom'];
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
