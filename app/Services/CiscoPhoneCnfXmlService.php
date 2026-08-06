<?php

namespace App\Services;

use DOMDocument;

class CiscoPhoneCnfXmlService
{
    public static function defaultConfig(): array
    {
        return [
            'device_protocol' => 'SIP',
            'sip_server' => 'sip.intilab.com',
            'sip_port' => '5060',
            'backup_proxy' => '',
            'backup_proxy_port' => '',
            'outbound_proxy' => '',
            'outbound_proxy_port' => '',
            'emergency_proxy' => '',
            'emergency_proxy_port' => '',
            'register_with_proxy' => true,
            'nat_enabled' => false,
            'transport_layer_protocol' => 'UDP',
            'timer_register_expires' => 3600,
            'timer_register_delta' => 5,
            'timer_keep_alive_expires' => 120,
            'timer_subscribe_expires' => 120,
            'timer_subscribe_delta' => 5,
            'timer_t1' => 500,
            'timer_t2' => 4000,
            'timer_invite_expires' => 180,
            'timer_relay_expires' => 180,
            'start_media_port' => 16384,
            'stop_media_port' => 32766,
            'sip_invite_retx' => 6,
            'sip_invite_tx_interval' => 500,
            'sip_invite_tx_max_duration' => 32000,
            'sip_non_invite_retx' => 10,
            'sip_non_invite_tx_interval' => 500,
            'sip_non_invite_tx_max_duration' => 32000,
            'date_template' => 'D/M/Y',
            'time_zone' => 'SE Asia Standard Time',
            'ntp_servers' => [
                ['name' => '0.id.pool.ntp.org', 'ntp_mode' => 'unicast'],
            ],
            'phone_password' => '',
            'background_image_access' => false,
            'phone_personalization_disabled' => true,
            'dnd_call_alert' => 'flashOnly',
            'call_hold_ringback' => '1',
            'call_pickup_enabled' => true,
            'call_pickup_policy' => 'Cisco Call Pickup',
            'call_pickup_url' => '',
            'call_pickup_group_uri' => '',
            'cnf_join_enabled' => true,
            'meet_me_service_url' => '',
            'abbreviated_dial_url' => '',
            'rfc2543_hold' => false,
            'call_hold_ringback_duration' => 10,
            'call_forward_uri' => '',
            'call_transfer_uri' => '',
            'call_transfer_blind_uri' => '',
            'call_transfer_semi_attended_uri' => '',
            'call_transfer_attended_uri' => '',
            'enable_vad' => true,
            'preferred_codec' => 'G711ulaw',
            'dtmf_avt_payload' => 101,
            'dtmf_db_level' => 3,
            'dtmf_out_of_band' => 'avt',
            'network_locale' => 'Indonesia',
            'user_locale' => 'English_United_States',
            'network_load_file' => '',
            'load_information' => '',
            'auto_answer_enabled' => false,
            'auto_answer_mode' => 'auto_answer_with_speakerphone',
            'auto_answer_timer' => 1,
            'voicemail_uri' => '',
            'directory_uri' => '',
            'services_uri' => '',
            'messages_uri' => '',
            'information_uri' => '',
            'proxy_server_port' => 5060,
            'lines' => [
                [
                    'button' => 1,
                    'feature_id' => 9,
                    'name' => '',
                    'display_name' => '',
                    'auth_name' => '',
                    'auth_password' => '',
                    'proxy' => '',
                    'contact' => '',
                    'port' => 5060,
                    'caller_name' => true,
                    'caller_number' => true,
                    'redirected_number' => false,
                    'dialed_number' => true,
                    'max_num_calls' => 2,
                    'busy_trigger' => 1,
                    'ring_setting_idle' => 'Normal',
                    'ring_setting_active' => 'Normal',
                    'shared_line' => false,
                    'message_waiting_lamp_policy' => 1,
                    'message_waiting_amis_policy' => 1,
                    'voice_mail_profile' => '',
                    'auto_answer' => false,
                ],
            ],
        ];
    }

    public static function normalizeMac(string $mac): string
    {
        $clean = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));

        if (strlen($clean) !== 12) {
            throw new \InvalidArgumentException('MAC address harus 12 karakter hex (contoh: B4E9B08C1E6B)');
        }

        return $clean;
    }

    public static function buildFilename(string $mac): string
    {
        return 'SEP' . self::normalizeMac($mac) . '.cnf.xml';
    }

    public static function mergeConfig(array $input): array
    {
        $defaults = self::defaultConfig();
        $merged = array_replace_recursive($defaults, $input);

        if (!empty($merged['lines']) && is_array($merged['lines'])) {
            foreach ($merged['lines'] as $index => &$line) {
                $line['button'] = $line['button'] ?? ($index + 1);
                $line['feature_id'] = $line['feature_id'] ?? 9;
                $line['proxy'] = $line['proxy'] ?: ($merged['sip_server'] ?? '');
                $line['port'] = $line['port'] ?? ($merged['sip_port'] ?? 5060);
            }
            unset($line);
        }

        return $merged;
    }

    public function generate(array $config, string $mac, string $phoneModel, ?string $userId = null, ?string $password = null): string
    {
        $config = self::mergeConfig($config);
        $mac = self::normalizeMac($mac);
        $phoneModel = trim($phoneModel) ?: 'CP-3905';

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $device = $doc->createElement('device');
        $doc->appendChild($device);

        $this->appendTextNode($doc, $device, 'deviceProtocol', $config['device_protocol'] ?? 'SIP');
        $this->appendDevicePool($doc, $device, $config);
        $this->appendSipProfile($doc, $device, $config, $phoneModel);
        $this->appendCommonProfile($doc, $device, $config);
        $this->appendNetworkLocale($doc, $device, $config);
        $this->appendLoadInformation($doc, $device, $config, $phoneModel);

        if ($userId !== null && $userId !== '') {
            $this->appendTextNode($doc, $device, 'userId', $userId);
        }

        if ($password !== null && $password !== '') {
            $this->appendTextNode($doc, $device, 'password', $password);
        }

        return $doc->saveXML();
    }

    private function appendDevicePool(DOMDocument $doc, \DOMElement $device, array $config): void
    {
        $devicePool = $doc->createElement('devicePool');

        $callManagerGroup = $doc->createElement('callManagerGroup');
        $members = $doc->createElement('members');
        $member = $doc->createElement('member');
        $member->setAttribute('priority', '0');

        $callManager = $doc->createElement('callManager');
        $this->appendTextNode($doc, $callManager, 'processNodeName', $config['sip_server'] ?? '');

        $ports = $doc->createElement('ports');
        $this->appendTextNode($doc, $ports, 'sipPort', (string) ($config['sip_port'] ?? 5060));
        $callManager->appendChild($ports);

        $member->appendChild($callManager);
        $members->appendChild($member);
        $callManagerGroup->appendChild($members);
        $devicePool->appendChild($callManagerGroup);

        $dateTime = $doc->createElement('dateTime');
        $dateTimeSetting = $doc->createElement('dateTimeSetting');
        $this->appendTextNode($doc, $dateTimeSetting, 'dateTemplate', $config['date_template'] ?? 'D/M/Y');
        $this->appendTextNode($doc, $dateTimeSetting, 'timeZone', $config['time_zone'] ?? 'SE Asia Standard Time');

        $ntps = $doc->createElement('ntps');
        foreach ($config['ntp_servers'] ?? [] as $ntp) {
            if (empty($ntp['name'])) {
                continue;
            }
            $ntpEl = $doc->createElement('ntp');
            $this->appendTextNode($doc, $ntpEl, 'name', $ntp['name']);
            $this->appendTextNode($doc, $ntpEl, 'ntpMode', $ntp['ntp_mode'] ?? 'unicast');
            $ntps->appendChild($ntpEl);
        }
        $dateTimeSetting->appendChild($ntps);
        $dateTime->appendChild($dateTimeSetting);
        $devicePool->appendChild($dateTime);

        $device->appendChild($devicePool);
    }

    private function appendSipProfile(DOMDocument $doc, \DOMElement $device, array $config, string $phoneModel): void
    {
        $sipProfile = $doc->createElement('sipProfile');

        $this->appendTextNode($doc, $sipProfile, 'natEnabled', ($config['nat_enabled'] ?? false) ? 'true' : 'false');
        $this->appendTextNode($doc, $sipProfile, 'userAgent', $phoneModel);
        $this->appendTextNode($doc, $sipProfile, 'transportLayerProtocol', $config['transport_layer_protocol'] ?? 'UDP');
        $this->appendTextNode($doc, $sipProfile, 'timerRegisterExpires', (string) ($config['timer_register_expires'] ?? 3600));
        $this->appendTextNode($doc, $sipProfile, 'timerRegisterDelta', (string) ($config['timer_register_delta'] ?? 5));
        $this->appendTextNode($doc, $sipProfile, 'timerKeepAliveExpires', (string) ($config['timer_keep_alive_expires'] ?? 120));
        $this->appendTextNode($doc, $sipProfile, 'timerSubscribeExpires', (string) ($config['timer_subscribe_expires'] ?? 120));
        $this->appendTextNode($doc, $sipProfile, 'timerSubscribeDelta', (string) ($config['timer_subscribe_delta'] ?? 5));
        $this->appendTextNode($doc, $sipProfile, 'timerT1', (string) ($config['timer_t1'] ?? 500));
        $this->appendTextNode($doc, $sipProfile, 'timerT2', (string) ($config['timer_t2'] ?? 4000));
        $this->appendTextNode($doc, $sipProfile, 'timerInviteExpires', (string) ($config['timer_invite_expires'] ?? 180));
        $this->appendTextNode($doc, $sipProfile, 'timerRelayExpires', (string) ($config['timer_relay_expires'] ?? 180));
        $this->appendTextNode($doc, $sipProfile, 'startMediaPort', (string) ($config['start_media_port'] ?? 16384));
        $this->appendTextNode($doc, $sipProfile, 'stopMediaPort', (string) ($config['stop_media_port'] ?? 32766));

        $this->appendSipProxies($doc, $sipProfile, $config);
        $this->appendSipCallFeatures($doc, $sipProfile, $config);
        $this->appendSipStack($doc, $sipProfile, $config);
        $this->appendSipLines($doc, $sipProfile, $config);

        $device->appendChild($sipProfile);
    }

    private function appendSipProxies(DOMDocument $doc, \DOMElement $sipProfile, array $config): void
    {
        $sipProxies = $doc->createElement('sipProxies');
        $this->appendTextNode($doc, $sipProxies, 'backupProxy', $config['backup_proxy'] ?? '');
        $this->appendTextNode($doc, $sipProxies, 'backupProxyPort', (string) ($config['backup_proxy_port'] ?? ''));
        $this->appendTextNode($doc, $sipProxies, 'emergencyProxy', $config['emergency_proxy'] ?? '');
        $this->appendTextNode($doc, $sipProxies, 'emergencyProxyPort', (string) ($config['emergency_proxy_port'] ?? ''));
        $this->appendTextNode($doc, $sipProxies, 'outboundProxy', $config['outbound_proxy'] ?? '');
        $this->appendTextNode($doc, $sipProxies, 'outboundProxyPort', (string) ($config['outbound_proxy_port'] ?? ''));
        $this->appendTextNode($doc, $sipProxies, 'registerWithProxy', ($config['register_with_proxy'] ?? true) ? 'true' : 'false');
        $sipProfile->appendChild($sipProxies);
    }

    private function appendSipCallFeatures(DOMDocument $doc, \DOMElement $sipProfile, array $config): void
    {
        $sipCallFeatures = $doc->createElement('sipCallFeatures');
        $this->appendTextNode($doc, $sipCallFeatures, 'cnfJoinEnabled', ($config['cnf_join_enabled'] ?? true) ? 'true' : 'false');
        $this->appendTextNode($doc, $sipCallFeatures, 'callForwardUri', $config['call_forward_uri'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'callPickupUri', $config['call_pickup_url'] ?: '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'callPickupListUri', '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'callPickupGroupUri', $config['call_pickup_group_uri'] ?: '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'meetMeServiceUri', $config['meet_me_service_url'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'abbreviatedDialUri', $config['abbreviated_dial_url'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'rfc2543Hold', ($config['rfc2543_hold'] ?? false) ? 'true' : 'false');
        $this->appendTextNode($doc, $sipCallFeatures, 'callHoldRingback', $config['call_hold_ringback'] ?? '1');
        $this->appendTextNode($doc, $sipCallFeatures, 'callHoldRingbackDuration', (string) ($config['call_hold_ringback_duration'] ?? 10));
        $this->appendTextNode($doc, $sipCallFeatures, 'callTransferUri', $config['call_transfer_uri'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'callTransferBlindUri', $config['call_transfer_blind_uri'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'callTransferSemiAttendedUri', $config['call_transfer_semi_attended_uri'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'callTransferAttendedUri', $config['call_transfer_attended_uri'] ?? '#');
        $this->appendTextNode($doc, $sipCallFeatures, 'enableVad', ($config['enable_vad'] ?? true) ? 'true' : 'false');
        $this->appendTextNode($doc, $sipCallFeatures, 'dndCallAlert', $config['dnd_call_alert'] ?? 'flashOnly');
        $this->appendTextNode($doc, $sipCallFeatures, 'callPickupEnabled', ($config['call_pickup_enabled'] ?? true) ? 'true' : 'false');
        $this->appendTextNode($doc, $sipCallFeatures, 'callPickupPolicy', $config['call_pickup_policy'] ?? 'Cisco Call Pickup');
        $sipProfile->appendChild($sipCallFeatures);
    }

    private function appendSipStack(DOMDocument $doc, \DOMElement $sipProfile, array $config): void
    {
        $sipStack = $doc->createElement('sipStack');
        $this->appendTextNode($doc, $sipStack, 'sipInviteRetx', (string) ($config['sip_invite_retx'] ?? 6));
        $this->appendTextNode($doc, $sipStack, 'sipInviteTxInterval', (string) ($config['sip_invite_tx_interval'] ?? 500));
        $this->appendTextNode($doc, $sipStack, 'sipInviteTxMaxDuration', (string) ($config['sip_invite_tx_max_duration'] ?? 32000));
        $this->appendTextNode($doc, $sipStack, 'sipNonInviteRetx', (string) ($config['sip_non_invite_retx'] ?? 10));
        $this->appendTextNode($doc, $sipStack, 'sipNonInviteTxInterval', (string) ($config['sip_non_invite_tx_interval'] ?? 500));
        $this->appendTextNode($doc, $sipStack, 'sipNonInviteTxMaxDuration', (string) ($config['sip_non_invite_tx_max_duration'] ?? 32000));
        $sipProfile->appendChild($sipStack);
    }

    private function appendSipLines(DOMDocument $doc, \DOMElement $sipProfile, array $config): void
    {
        $sipLines = $doc->createElement('sipLines');

        foreach ($config['lines'] ?? [] as $line) {
            if (empty($line['name']) && empty($line['auth_name'])) {
                continue;
            }

            $lineEl = $doc->createElement('line');
            $lineEl->setAttribute('button', (string) ($line['button'] ?? 1));

            $this->appendTextNode($doc, $lineEl, 'featureID', (string) ($line['feature_id'] ?? 9));
            $this->appendTextNode($doc, $lineEl, 'name', $line['name'] ?? '');
            $this->appendTextNode($doc, $lineEl, 'displayName', $line['display_name'] ?? ($line['name'] ?? ''));
            $this->appendTextNode($doc, $lineEl, 'authName', $line['auth_name'] ?? ($line['name'] ?? ''));
            $this->appendTextNode($doc, $lineEl, 'authPassword', $line['auth_password'] ?? '');
            $this->appendTextNode($doc, $lineEl, 'proxy', $line['proxy'] ?? ($config['sip_server'] ?? ''));
            $this->appendTextNode($doc, $lineEl, 'port', (string) ($line['port'] ?? ($config['sip_port'] ?? 5060)));
            $this->appendTextNode($doc, $lineEl, 'contact', $line['contact'] ?? ($line['name'] ?? ''));
            $this->appendTextNode($doc, $lineEl, 'maxNumCalls', (string) ($line['max_num_calls'] ?? 2));
            $this->appendTextNode($doc, $lineEl, 'busyTrigger', (string) ($line['busy_trigger'] ?? 1));
            $this->appendTextNode($doc, $lineEl, 'ringSettingIdle', $line['ring_setting_idle'] ?? 'Normal');
            $this->appendTextNode($doc, $lineEl, 'ringSettingActive', $line['ring_setting_active'] ?? 'Normal');
            $this->appendTextNode($doc, $lineEl, 'sharedLine', ($line['shared_line'] ?? false) ? 'true' : 'false');
            $this->appendTextNode($doc, $lineEl, 'messageWaitingLampPolicy', (string) ($line['message_waiting_lamp_policy'] ?? 1));
            $this->appendTextNode($doc, $lineEl, 'messageWaitingAMISPolicy', (string) ($line['message_waiting_amis_policy'] ?? 1));

            if (!empty($line['voice_mail_profile'])) {
                $this->appendTextNode($doc, $lineEl, 'voiceMailProfile', $line['voice_mail_profile']);
            }

            $forwardCallInfoDisplay = $doc->createElement('forwardCallInfoDisplay');
            $this->appendTextNode($doc, $forwardCallInfoDisplay, 'callerName', ($line['caller_name'] ?? true) ? 'true' : 'false');
            $this->appendTextNode($doc, $forwardCallInfoDisplay, 'callerNumber', ($line['caller_number'] ?? true) ? 'true' : 'false');
            $this->appendTextNode($doc, $forwardCallInfoDisplay, 'redirectedNumber', ($line['redirected_number'] ?? false) ? 'true' : 'false');
            $this->appendTextNode($doc, $forwardCallInfoDisplay, 'dialedNumber', ($line['dialed_number'] ?? true) ? 'true' : 'false');
            $lineEl->appendChild($forwardCallInfoDisplay);

            if (!empty($line['auto_answer'])) {
                $autoAnswer = $doc->createElement('autoAnswer');
                $autoAnswer->setAttribute('enabled', 'true');
                $this->appendTextNode($doc, $autoAnswer, 'autoAnswerMode', $config['auto_answer_mode'] ?? 'auto_answer_with_speakerphone');
                $lineEl->appendChild($autoAnswer);
            }

            $sipLines->appendChild($lineEl);
        }

        $sipProfile->appendChild($sipLines);
    }

    private function appendCommonProfile(DOMDocument $doc, \DOMElement $device, array $config): void
    {
        $commonProfile = $doc->createElement('commonProfile');
        $this->appendTextNode($doc, $commonProfile, 'phonePassword', $config['phone_password'] ?? '');

        $backgroundImageAccess = ($config['background_image_access'] ?? false) ? 'true' : 'false';
        $this->appendTextNode($doc, $commonProfile, 'backgroundImageAccess', $backgroundImageAccess);

        $phonePersonalization = $doc->createElement('phonePersonalization');
        $phonePersonalization->setAttribute('disabled', ($config['phone_personalization_disabled'] ?? true) ? '1' : '0');
        $commonProfile->appendChild($phonePersonalization);

        $device->appendChild($commonProfile);
    }

    private function appendNetworkLocale(DOMDocument $doc, \DOMElement $device, array $config): void
    {
        if (empty($config['network_locale']) && empty($config['user_locale'])) {
            return;
        }

        if (!empty($config['network_locale'])) {
            $this->appendTextNode($doc, $device, 'networkLocale', $config['network_locale']);
        }

        if (!empty($config['user_locale'])) {
            $this->appendTextNode($doc, $device, 'userLocale', $config['user_locale']);
        }
    }

    private function appendLoadInformation(DOMDocument $doc, \DOMElement $device, array $config, string $phoneModel): void
    {
        $loadInfo = $config['load_information'] ?? '';
        if ($loadInfo === '' && !empty($config['network_load_file'])) {
            $loadInfo = $config['network_load_file'];
        }

        if ($loadInfo !== '') {
            $loadInformation = $doc->createElement('loadInformation');
            $loadInformation->setAttribute('model', $phoneModel);
            $loadInformation->appendChild($doc->createTextNode($loadInfo));
            $device->appendChild($loadInformation);
        }
    }

    private function appendTextNode(DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        if ($value !== '') {
            $node->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($node);
    }

    public function writeToTftp(string $xml, string $mac): array
    {
        $filename = self::buildFilename($mac);
        $directory = rtrim(env('CISCO_TFTP_PATH', '/srv/tftp'), DIRECTORY_SEPARATOR);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Folder TFTP tidak ditemukan dan gagal dibuat: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new \RuntimeException("Folder TFTP tidak dapat ditulis: {$directory}");
        }

        $written = file_put_contents($fullPath, $xml);
        if ($written === false) {
            throw new \RuntimeException("Gagal menulis file konfigurasi: {$fullPath}");
        }

        @chmod($fullPath, 0644);

        return [
            'filename' => $filename,
            'path' => $fullPath,
        ];
    }

    public function removeTftpFile(string $mac): void
    {
        $filename = self::buildFilename($mac);
        $directory = rtrim(env('CISCO_TFTP_PATH', '/srv/tftp'), DIRECTORY_SEPARATOR);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
