<?php

namespace App\Services;

use PhpImap\Mailbox;
use PHPMailer\PHPMailer\PHPMailer;
use Repository;

class InternalMailService
{
    private const CACHE_TTL = 120;
    private const AUTH_COOLDOWN = 300;
    private const MAX_CACHE_ITEMS = 300;

    private const FOLDER_MAP = [
        'inbox'  => ['INBOX'],
        'outbox' => ['Sent', 'Sent Items', 'Sent Messages', '[Gmail]/Sent Mail'],
        'trash'  => ['Trash', 'Deleted Items', 'Deleted Messages', '[Gmail]/Trash'],
        'spam'   => ['Junk', 'Spam', 'Junk E-mail', '[Gmail]/Spam'],
        'draft'  => ['Drafts', '[Gmail]/Drafts'],
    ];

    private string $karyawan;

    public function __construct(string $karyawan)
    {
        $this->karyawan = $karyawan;
    }

    public function getSettings(): ?array
    {
        $raw = Repository::dir('setting_mail')->key($this->karyawan)->get();
        if (empty($raw)) {
            return null;
        }

        return json_decode($raw, true);
    }

    public function checkUpdates(string $folder = 'inbox'): array
    {
        $cache = $this->getCache($folder);
        $meta = $cache['meta'] ?? [];

        if ($block = $this->getAuthBlock()) {
            return [
                'changed'       => false,
                'unread_count'  => (int) ($meta['unread_count'] ?? 0),
                'total'         => (int) ($meta['total'] ?? 0),
                'needs_refresh' => false,
                'error'         => $block['message'],
                'auth_blocked'  => true,
            ];
        }

        if (!empty($meta['synced_at']) && (time() - (int) $meta['synced_at']) < self::CACHE_TTL) {
            return [
                'changed'       => false,
                'unread_count'  => (int) ($meta['unread_count'] ?? 0),
                'total'         => (int) ($meta['total'] ?? 0),
                'needs_refresh' => false,
            ];
        }

        try {
            $status = $this->fetchMailboxStatus($folder);
        } catch (\Throwable $e) {
            return [
                'changed'       => false,
                'unread_count'  => (int) ($meta['unread_count'] ?? 0),
                'total'         => (int) ($meta['total'] ?? 0),
                'needs_refresh' => false,
                'error'         => $e->getMessage(),
                'auth_blocked'  => $this->isAuthError($e->getMessage()),
            ];
        }

        $changed = empty($meta)
            || (int) ($meta['total'] ?? 0) !== (int) $status['total']
            || (int) ($meta['unread_count'] ?? 0) !== (int) $status['unread_count']
            || (int) ($meta['uidnext'] ?? 0) !== (int) $status['uidnext'];

        $stale = empty($meta['synced_at']) || (time() - (int) $meta['synced_at']) > self::CACHE_TTL;

        return [
            'changed'       => $changed,
            'unread_count'  => (int) $status['unread_count'],
            'total'         => (int) $status['total'],
            'needs_refresh' => $changed || $stale,
        ];
    }

    public function fetchList(
        string $folder,
        int $page,
        int $perPage,
        ?string $query = null,
        bool $forceRefresh = false,
        ?string $sort = null,
        ?string $filter = null
    ): array {
        if ($folder === 'local_draft') {
            return $this->fetchLocalDrafts($page, $perPage, $query, $sort);
        }

        $cache = $this->getCache($folder);
        $meta = $cache['meta'] ?? [];
        $stale = empty($meta['synced_at']) || (time() - (int) $meta['synced_at']) > self::CACHE_TTL;

        if (!$forceRefresh && !$stale && !empty($cache['emails']) && empty($query)) {
            return $this->paginateEmails($cache['emails'], $page, $perPage, $meta, $sort, $filter);
        }

        if ($block = $this->getAuthBlock()) {
            if (!empty($cache['emails'])) {
                $result = $this->paginateEmails($cache['emails'], $page, $perPage, $meta, $sort, $filter);
                $result['error'] = $block['message'];
                return $result;
            }
            throw new \RuntimeException($block['message']);
        }

        try {
            $emails = $this->syncFromServer($folder, $query);
        } catch (\Throwable $e) {
            if (!empty($cache['emails'])) {
                $result = $this->paginateEmails($cache['emails'], $page, $perPage, $meta, $sort, $filter);
                $result['error'] = $e->getMessage();
                return $result;
            }
            throw $e;
        }

        $meta = $this->getCache($folder)['meta'] ?? [];

        return $this->paginateEmails($emails, $page, $perPage, $meta, $sort, $filter);
    }

    public function getDetail(string $folder, $uid): array
    {
        if ($folder === 'local_draft') {
            return $this->getLocalDraft($uid);
        }

        $settings = $this->getSettings();
        if (!$settings) {
            throw new \RuntimeException('Setting email belum dikonfigurasi');
        }

        $imapFolder = $this->resolveImapFolder($folder);
        $dirAttachments = public_path('email/' . $this->karyawan . '/attachments');

        if (!is_dir($dirAttachments)) {
            mkdir($dirAttachments, 0775, true);
        }

        $mailbox = new Mailbox(
            $this->buildMailboxPath($settings, $imapFolder),
            $settings['email'],
            $settings['password'],
            $dirAttachments,
            'UTF-8'
        );

        $mail = $mailbox->getMail($uid);

        $attachments = [];
        foreach ($mail->getAttachments() as $attachment) {
            $pathParts = explode('/', $attachment->filePath);
            $attachments[] = [
                'filename' => $attachment->name,
                'size'     => $this->formatSize((int) ($attachment->size ?? 0)),
                'url'      => env('APP_URL') . '/public/email/' . $this->karyawan . '/attachments/' . end($pathParts),
            ];
        }

        $headerAddresses = $this->fetchHeaderAddresses($imapFolder, (int) $uid, $settings);
        $fromList = !empty($headerAddresses['from'])
            ? $headerAddresses['from']
            : [$this->makeRecipientEntry($mail->fromName, $mail->fromAddress)];
        $toList = !empty($headerAddresses['to'])
            ? $headerAddresses['to']
            : $this->normalizeRecipientField($mail->to ?? null);
        $ccList = !empty($headerAddresses['cc'])
            ? $headerAddresses['cc']
            : $this->normalizeRecipientField($mail->cc ?? null);
        $bccList = !empty($headerAddresses['bcc'])
            ? $headerAddresses['bcc']
            : $this->normalizeRecipientField($mail->bcc ?? null);

        $fromDisplay = $fromList[0]['display'] ?? $this->formatAddress($mail->fromName, $mail->fromAddress);

        return [
            'id'        => (int) $uid,
            'from'      => $fromDisplay,
            'from_list' => $fromList,
            'to'        => $this->joinRecipientDisplays($toList),
            'to_list'   => $toList,
            'cc'        => $this->joinRecipientDisplays($ccList),
            'cc_list'   => $ccList,
            'bcc'       => $this->joinRecipientDisplays($bccList),
            'bcc_list'  => $bccList,
            'subject'   => $this->decodeHeader($mail->subject ?? ''),
            'date'      => $mail->date ?? null,
            'size'      => $this->formatSize((int) ($mail->size ?? 0)),
            'html_body' => $mail->textHtml ?? '',
            'text_body' => $mail->textPlain ?? '',
            'attachments' => $attachments,
        ];
    }

    public function markSeen(string $folder, $uid, bool $seen = true): void
    {
        $connection = $this->connect($folder);

        if ($seen) {
            @\imap_setflag_full($connection, (string) $uid, '\\Seen', \ST_UID);
        } else {
            @\imap_clearflag_full($connection, (string) $uid, '\\Seen', \ST_UID);
        }

        @\imap_close($connection);
        $this->clearImapErrors();
        $this->updateCachedEmailFlag($folder, $uid, $seen);
    }

    public function moveToTrash(string $folder, $uid): void
    {
        $this->moveEmail($folder, $uid, 'trash');
    }

    public function moveEmail(string $fromFolder, $uid, string $toFolder): void
    {
        $connection = $this->connect($fromFolder);
        $targetFolder = $this->resolveImapFolder($toFolder);

        if (!\imap_mail_move($connection, (string) $uid, $targetFolder, \CP_UID)) {
            throw new \RuntimeException('Gagal memindahkan email: ' . \imap_last_error());
        }

        \imap_expunge($connection);
        \imap_close($connection);

        $this->removeFromCache($fromFolder, $uid);
        $this->clearCache($toFolder);
    }

    public function deletePermanent(string $folder, $uid): void
    {
        $connection = $this->connect($folder);

        if (!\imap_delete($connection, (string) $uid, \FT_UID)) {
            throw new \RuntimeException('Gagal menghapus email: ' . \imap_last_error());
        }

        \imap_expunge($connection);
        \imap_close($connection);
        $this->removeFromCache($folder, $uid);
    }

    public function emptyTrash(): array
    {
        return $this->emptyFolderPermanently('trash', 'sampah');
    }

    public function emptySpam(): array
    {
        return $this->emptyFolderPermanently('spam', 'spam');
    }

    private function emptyFolderPermanently(string $folder, string $label): array
    {
        $connection = $this->connect($folder);
        $messageNumbers = @\imap_search($connection, 'ALL', \SE_UID);

        $deleted = 0;
        $failed = 0;

        if ($messageNumbers) {
            foreach ($messageNumbers as $uid) {
                if (@\imap_delete($connection, (string) $uid, \FT_UID)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }

            @\imap_expunge($connection);
        }

        @\imap_close($connection);
        $this->clearImapErrors();
        $this->clearCache($folder);

        if ($failed > 0 && $deleted === 0) {
            throw new \RuntimeException("Gagal mengosongkan {$label}: " . $this->collectImapError());
        }

        return [
            'deleted' => $deleted,
            'failed'  => $failed,
            'message' => $deleted > 0
                ? "{$deleted} email dihapus permanen dari {$label}"
                : ucfirst($label) . ' sudah kosong',
        ];
    }

    public function sendEmail(array $data): void
    {
        $settings = $this->getSettings();
        if (!$settings) {
            throw new \RuntimeException('Setting email belum dikonfigurasi');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $settings['outgoing']['hostname'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['email'];
        $mail->Password = $settings['password'];
        $mail->SMTPSecure = $this->mapSmtpSecurity($settings['outgoing']['connection_security'] ?? 'TLS');
        $mail->Port = (int) $settings['outgoing']['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($settings['email'], $settings['full_name'] ?? $settings['email']);

        foreach ($this->parseRecipients($data['to'] ?? '') as $recipient) {
            $mail->addAddress($recipient);
        }

        if (!empty($data['cc'])) {
            foreach ($this->parseRecipients($data['cc']) as $cc) {
                $mail->addCC($cc);
            }
        }

        if (!empty($data['bcc'])) {
            foreach ($this->parseRecipients($data['bcc']) as $bcc) {
                $mail->addBCC($bcc);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $data['subject'] ?? '';
        $mail->Body = $data['html_body'] ?? ($data['body'] ?? '');
        $mail->AltBody = strip_tags($mail->Body);

        if (!$mail->send()) {
            throw new \RuntimeException('Gagal mengirim email');
        }

        $this->clearCache('outbox');
    }

    public function saveLocalDraft(array $data): array
    {
        $drafts = $this->getLocalDraftList();
        $id = $data['id'] ?? uniqid('draft_', true);

        $draft = [
            'id'        => $id,
            'to'        => $data['to'] ?? '',
            'cc'        => $data['cc'] ?? '',
            'bcc'       => $data['bcc'] ?? '',
            'subject'   => $data['subject'] ?? '',
            'html_body' => $data['html_body'] ?? '',
            'updated_at'=> date('c'),
        ];

        $drafts[$id] = $draft;
        Repository::dir('mail_draft')->key($this->karyawan)->save(json_encode(array_values($drafts)));

        return $draft;
    }

    public function deleteLocalDraft($id): void
    {
        $drafts = $this->getLocalDraftList();
        unset($drafts[$id]);
        Repository::dir('mail_draft')->key($this->karyawan)->save(json_encode(array_values($drafts)));
    }

    private function fetchMailboxStatus(string $folder): array
    {
        $settings = $this->getSettings();
        if (!$settings) {
            throw new \RuntimeException('Setting email belum dikonfigurasi');
        }

        $imapFolder = $this->resolveImapFolder($folder);
        $path = $this->buildMailboxPath($settings, $imapFolder);
        $connection = $this->openConnection($settings, $path);

        $status = @\imap_status($connection, $path, \SA_MESSAGES | \SA_UNSEEN | \SA_UIDNEXT);
        @\imap_close($connection);
        $this->clearImapErrors();

        if (!$status) {
            throw new \RuntimeException('Gagal membaca status mailbox: ' . $this->collectImapError());
        }

        return $this->normalizeStatus($status);
    }

    private function syncFromServer(string $folder, ?string $query = null): array
    {
        $settings = $this->getSettings();
        if (!$settings) {
            throw new \RuntimeException('Setting email belum dikonfigurasi');
        }

        $imapFolder = $this->resolveImapFolder($folder);
        $path = $this->buildMailboxPath($settings, $imapFolder);
        $connection = $this->openConnection($settings, $path);

        $statusObj = @\imap_status($connection, $path, \SA_MESSAGES | \SA_UNSEEN | \SA_UIDNEXT);
        $status = $statusObj ? $this->normalizeStatus($statusObj) : ['total' => 0, 'unread_count' => 0, 'uidnext' => 0];

        $searchCriteria = $query ? 'TEXT "' . addslashes($query) . '"' : 'ALL';
        $messageNumbers = @\imap_search($connection, $searchCriteria, \SE_UID);

        $emails = [];
        if ($messageNumbers) {
            rsort($messageNumbers);
            $messageNumbers = array_slice($messageNumbers, 0, self::MAX_CACHE_ITEMS);

            foreach ($messageNumbers as $uid) {
                $overview = @\imap_fetch_overview($connection, (string) $uid, \FT_UID);
                if (empty($overview[0])) {
                    continue;
                }

                $item = $overview[0];
                $emails[] = [
                    'id'      => (int) $uid,
                    'from'    => $this->decodeHeader($item->from ?? ''),
                    'subject' => $this->decodeHeader($item->subject ?? '') ?: '(Tidak ada subjek)',
                    'date'    => isset($item->date) ? date('c', strtotime($item->date)) : null,
                    'size'    => $this->formatSize((int) ($item->size ?? 0)),
                    'is_seen' => ((int) ($item->seen ?? 0)) === 1,
                ];
            }
        }

        @\imap_close($connection);
        $this->clearImapErrors();
        $this->saveCache($folder, $emails, $status);

        return $emails;
    }

    private function connect(string $folder)
    {
        $settings = $this->getSettings();
        if (!$settings) {
            throw new \RuntimeException('Setting email belum dikonfigurasi');
        }

        $imapFolder = $this->resolveImapFolder($folder);
        $path = $this->buildMailboxPath($settings, $imapFolder);

        return $this->openConnection($settings, $path);
    }

    private function openConnection(array $settings, string $path)
    {
        $this->assertNotAuthBlocked();

        $previousHandler = set_error_handler(function () {
            return true;
        });

        try {
            $connection = @\imap_open(
                $path,
                $settings['email'],
                $settings['password'],
                0,
                1,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
            );

            if (!$connection) {
                $error = $this->collectImapError();
                if ($this->isAuthError($error)) {
                    $this->recordAuthFailure(
                        'Autentikasi email gagal. Periksa email/password di Setting Mail, atau tunggu 5 menit jika akun terkunci.'
                    );
                }
                throw new \RuntimeException('Koneksi IMAP gagal: ' . $error);
            }

            $this->clearAuthBlock();
            return $connection;
        } finally {
            restore_error_handler($previousHandler);
            $this->clearImapErrors();
        }
    }

    private function resolveImapFolder(string $folder): string
    {
        if ($folder === 'inbox') {
            return 'INBOX';
        }

        $cached = $this->getFolderCache();
        if (!empty($cached[$folder])) {
            return $cached[$folder];
        }

        $candidates = self::FOLDER_MAP[$folder] ?? ['INBOX'];
        $settings = $this->getSettings();
        if (!$settings) {
            return $candidates[0];
        }

        $basePath = $this->buildMailboxPath($settings, '');
        $previousHandler = set_error_handler(function () {
            return true;
        });

        try {
            $connection = @\imap_open(
                $basePath,
                $settings['email'],
                $settings['password'],
                \OP_HALFOPEN,
                1,
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
            );

            if (!$connection) {
                return $candidates[0];
            }

            $mailboxes = @\imap_list($connection, $basePath, '*') ?: [];
            @\imap_close($connection);
            $this->clearImapErrors();
        } finally {
            restore_error_handler($previousHandler);
        }

        foreach ($candidates as $candidate) {
            $full = $basePath . $candidate;
            if (in_array($full, $mailboxes, true)) {
                $cached[$folder] = $candidate;
                $this->saveFolderCache($cached);
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function buildMailboxPath(array $settings, string $folder = ''): string
    {
        $security = $this->mapImapSecurity($settings['incoming']['connection_security'] ?? 'SSL');
        $protocol = $settings['protocol'] ?? 'imap';

        return sprintf(
            '{%s:%s/%s/%s}%s',
            $settings['incoming']['hostname'],
            $settings['incoming']['port'],
            $protocol,
            $security,
            $folder
        );
    }

    private function mapImapSecurity(string $security): string
    {
        $map = [
            'None'      => 'notls',
            'starttls'  => 'tls',
            'StartTLS'  => 'tls',
            'SSL'       => 'ssl',
            'TLS'       => 'tls',
        ];

        return $map[$security] ?? 'ssl';
    }

    private function mapSmtpSecurity(string $security): string
    {
        $map = [
            'None'     => '',
            'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
            'StartTLS' => PHPMailer::ENCRYPTION_STARTTLS,
            'SSL'      => PHPMailer::ENCRYPTION_SMTPS,
            'TLS'      => PHPMailer::ENCRYPTION_STARTTLS,
        ];

        return $map[$security] ?? PHPMailer::ENCRYPTION_STARTTLS;
    }

    private function getCache(string $folder): array
    {
        $raw = Repository::dir('mail_' . $folder)->key($this->karyawan)->get();
        if (empty($raw)) {
            return ['meta' => [], 'emails' => []];
        }

        return json_decode($raw, true) ?: ['meta' => [], 'emails' => []];
    }

    private function saveCache(string $folder, array $emails, array $meta): void
    {
        $meta['synced_at'] = time();
        Repository::dir('mail_' . $folder)->key($this->karyawan)->save(json_encode([
            'meta'   => $meta,
            'emails' => $emails,
        ]));
    }

    private function clearCache(string $folder): void
    {
        Repository::dir('mail_' . $folder)->key($this->karyawan)->save(json_encode([
            'meta'   => [],
            'emails' => [],
        ]));
    }

    private function updateCachedEmailFlag(string $folder, $uid, bool $seen): void
    {
        $cache = $this->getCache($folder);
        foreach ($cache['emails'] as &$email) {
            if ((int) $email['id'] === (int) $uid) {
                $email['is_seen'] = $seen;
                break;
            }
        }
        unset($email);

        if ($seen && isset($cache['meta']['unread_count']) && $cache['meta']['unread_count'] > 0) {
            $cache['meta']['unread_count']--;
        }

        Repository::dir('mail_' . $folder)->key($this->karyawan)->save(json_encode($cache));
    }

    private function removeFromCache(string $folder, $uid): void
    {
        $cache = $this->getCache($folder);
        $cache['emails'] = array_values(array_filter(
            $cache['emails'],
            function ($email) use ($uid) {
                return (int) $email['id'] !== (int) $uid;
            }
        ));

        if (isset($cache['meta']['total']) && $cache['meta']['total'] > 0) {
            $cache['meta']['total']--;
        }

        Repository::dir('mail_' . $folder)->key($this->karyawan)->save(json_encode($cache));
    }

    private function paginateEmails(
        array $emails,
        int $page,
        int $perPage,
        array $meta,
        ?string $sort = null,
        ?string $filter = null
    ): array {
        if (!empty($meta) && empty($meta['synced_at'])) {
            $meta['synced_at'] = time();
        }

        $unreadCount = (int) ($meta['unread_count'] ?? count(array_filter($emails, function ($e) {
            return empty($e['is_seen']);
        })));

        $emails = $this->applyListOptions($emails, $sort, $filter);

        $total = count($emails);
        $offset = max(0, ($page - 1) * $perPage);

        return [
            'emails'       => array_slice($emails, $offset, $perPage),
            'total'        => $total,
            'unread_count' => $unreadCount,
        ];
    }

    private function applyListOptions(array $emails, ?string $sort, ?string $filter): array
    {
        if ($filter === 'unread') {
            $emails = array_values(array_filter($emails, function ($e) {
                return empty($e['is_seen']);
            }));
        } elseif ($filter === 'read') {
            $emails = array_values(array_filter($emails, function ($e) {
                return !empty($e['is_seen']);
            }));
        }

        $sort = $sort ?: 'date_desc';

        usort($emails, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'date_asc':
                    return strcmp($a['date'] ?? '', $b['date'] ?? '');
                case 'unread_first':
                    $seenA = !empty($a['is_seen']);
                    $seenB = !empty($b['is_seen']);
                    if ($seenA !== $seenB) {
                        return $seenA <=> $seenB;
                    }
                    return strcmp($b['date'] ?? '', $a['date'] ?? '');
                case 'sender_asc':
                    return strcasecmp($a['from'] ?? '', $b['from'] ?? '');
                case 'sender_desc':
                    return strcasecmp($b['from'] ?? '', $a['from'] ?? '');
                case 'subject_asc':
                    return strcasecmp($a['subject'] ?? '', $b['subject'] ?? '');
                case 'subject_desc':
                    return strcasecmp($b['subject'] ?? '', $a['subject'] ?? '');
                case 'date_desc':
                default:
                    return strcmp($b['date'] ?? '', $a['date'] ?? '');
            }
        });

        return $emails;
    }

    private function fetchLocalDrafts(int $page, int $perPage, ?string $query, ?string $sort = null): array
    {
        $drafts = array_values($this->getLocalDraftList());

        if ($query) {
            $queryLower = strtolower($query);
            $drafts = array_values(array_filter($drafts, function ($draft) use ($queryLower) {
                return strpos(strtolower($draft['subject'] ?? ''), $queryLower) !== false
                    || strpos(strtolower($draft['to'] ?? ''), $queryLower) !== false;
            }));
        }

        $drafts = array_map(function ($draft) {
            $draft['date'] = $draft['updated_at'] ?? null;
            return $draft;
        }, $drafts);

        $drafts = $this->applyListOptions($drafts, $sort ?: 'date_desc', 'all');

        $total = count($drafts);
        $offset = max(0, ($page - 1) * $perPage);

        $items = array_map(function ($draft) {
            return [
                'id'      => $draft['id'],
                'from'    => 'Draft',
                'subject' => $draft['subject'] ?: '(Draft tanpa subjek)',
                'date'    => $draft['updated_at'] ?? null,
                'size'    => '-',
                'is_seen' => true,
            ];
        }, array_slice($drafts, $offset, $perPage));

        return [
            'emails'       => $items,
            'total'        => $total,
            'unread_count' => 0,
        ];
    }

    private function getLocalDraftList(): array
    {
        $raw = Repository::dir('mail_draft')->key($this->karyawan)->get();
        if (empty($raw)) {
            return [];
        }

        $list = json_decode($raw, true) ?: [];
        $indexed = [];
        foreach ($list as $item) {
            $indexed[$item['id']] = $item;
        }

        return $indexed;
    }

    private function getLocalDraft($id): array
    {
        $drafts = $this->getLocalDraftList();
        if (!isset($drafts[$id])) {
            throw new \RuntimeException('Draft tidak ditemukan');
        }

        $draft = $drafts[$id];
        $toList = $this->normalizeRecipientField($draft['to'] ?? null);
        $ccList = $this->normalizeRecipientField($draft['cc'] ?? null);
        $bccList = $this->normalizeRecipientField($draft['bcc'] ?? null);

        return [
            'id'        => $draft['id'],
            'from'      => 'Draft',
            'to'        => $this->joinRecipientDisplays($toList),
            'to_list'   => $toList,
            'cc'        => $this->joinRecipientDisplays($ccList),
            'cc_list'   => $ccList,
            'bcc'       => $this->joinRecipientDisplays($bccList),
            'bcc_list'  => $bccList,
            'subject'   => $draft['subject'] ?? '',
            'date'      => $draft['updated_at'] ?? null,
            'size'      => '-',
            'html_body' => $draft['html_body'] ?? '',
            'text_body' => strip_tags($draft['html_body'] ?? ''),
            'attachments' => [],
            'is_draft'  => true,
        ];
    }

    private function parseRecipients(string $recipients): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $recipients)), function ($item) {
            return $item !== '' && $item !== ',';
        }));
    }

    private function fetchHeaderAddresses(string $imapFolder, int $uid, array $settings): array
    {
        $path = $this->buildMailboxPath($settings, $imapFolder);
        $connection = $this->openConnection($settings, $path);
        $header = @\imap_fetchheader($connection, $uid, \FT_UID);
        @\imap_close($connection);
        $this->clearImapErrors();

        if (!$header) {
            return [];
        }

        $parsed = @\imap_rfc822_parse_headers($header);
        if (!$parsed) {
            return [];
        }

        return [
            'from' => $this->parseRfc822Addresses($parsed->from ?? []),
            'to'   => $this->parseRfc822Addresses($parsed->to ?? []),
            'cc'   => $this->parseRfc822Addresses($parsed->cc ?? []),
            'bcc'  => $this->parseRfc822Addresses($parsed->bcc ?? []),
        ];
    }

    private function parseRfc822Addresses($addresses): array
    {
        if (!is_array($addresses)) {
            return [];
        }

        $items = [];
        foreach ($addresses as $address) {
            $entry = $this->makeRecipientEntry(
                isset($address->personal) ? $this->decodeHeader($address->personal) : null,
                (!empty($address->mailbox) && !empty($address->host))
                    ? strtolower($address->mailbox . '@' . $address->host)
                    : null
            );
            if ($entry) {
                $items[] = $entry;
            }
        }

        return $items;
    }

    private function normalizeRecipientField($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $items = [];
            foreach ($this->parseRecipients($value) as $part) {
                $entry = $this->makeRecipientEntry(null, $part);
                if (!$entry) {
                    $entry = $this->makeRecipientEntryFromDisplay($part);
                }
                if ($entry) {
                    $items[] = $entry;
                }
            }
            return $items;
        }

        if (is_object($value)) {
            return $this->normalizeRecipientField(get_object_vars($value));
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $normalized = $this->normalizeRecipientField($entry);
            } elseif (is_object($entry)) {
                $normalized = $this->normalizeRecipientField([
                    'personal' => $entry->personal ?? ($entry->name ?? null),
                    'mailbox'  => $entry->mailbox ?? null,
                    'host'     => $entry->host ?? null,
                    'mail'     => $entry->mail ?? ($entry->email ?? null),
                ]);
            } elseif (is_array($entry)) {
                if (!empty($entry['mail']) || !empty($entry['email'])) {
                    $email = $entry['mail'] ?? $entry['email'];
                    $normalized = $this->normalizeRecipientField($email);
                } elseif (!empty($entry['mailbox']) && !empty($entry['host'])) {
                    $normalized = [$this->makeRecipientEntry(
                        $entry['personal'] ?? ($entry['name'] ?? null),
                        strtolower($entry['mailbox'] . '@' . $entry['host'])
                    )];
                } else {
                    $normalized = $this->normalizeRecipientField(
                        implode(', ', array_filter(array_map('strval', $entry)))
                    );
                }
            } else {
                continue;
            }

            foreach ($normalized as $item) {
                if ($item) {
                    $items[] = $item;
                }
            }
        }

        return $this->uniqueRecipients($items);
    }

    private function makeRecipientEntryFromDisplay(string $display): ?array
    {
        $display = trim($display);
        if ($display === '' || $display === ',') {
            return null;
        }

        if (preg_match('/^"?(.*?)"?\s*<([^>]+)>$/', $display, $matches)) {
            $name = trim($matches[1], '" ');
            $email = trim($matches[2]);
            return $this->makeRecipientEntry($name ?: null, $email);
        }

        if (strpos($display, '@') !== false) {
            return $this->makeRecipientEntry(null, $display);
        }

        return null;
    }

    private function makeRecipientEntry(?string $name, ?string $email): ?array
    {
        $email = trim((string) $email);
        $name = trim((string) $name);

        if ($email === '' && $name === '') {
            return null;
        }

        if ($email === '' && strpos($name, '@') !== false) {
            $email = $name;
            $name = '';
        }

        if ($email === '') {
            return null;
        }

        $name = $name !== '' ? $this->decodeHeader($name) : '';
        $display = $name ? $this->formatAddress($name, $email) : $email;

        return [
            'email'   => $email,
            'name'    => $name ?: $email,
            'display' => $display,
        ];
    }

    private function joinRecipientDisplays(array $recipients): string
    {
        if (empty($recipients)) {
            return '';
        }

        return implode(', ', array_column($recipients, 'display'));
    }

    private function uniqueRecipients(array $recipients): array
    {
        $unique = [];
        foreach ($recipients as $recipient) {
            if (empty($recipient['email'])) {
                continue;
            }
            $key = strtolower($recipient['email']);
            if (!isset($unique[$key])) {
                $unique[$key] = $recipient;
            }
        }

        return array_values($unique);
    }

    private function decodeHeader(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $decoded = \imap_utf8($value);
        return $decoded ?: $value;
    }

    private function formatAddress(?string $name, ?string $address): string
    {
        if ($name && $address) {
            return sprintf('"%s" <%s>', $name, $address);
        }

        return $address ?: ($name ?: '');
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '-';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    public static function clearAuthBlockFor(string $karyawan): void
    {
        Repository::dir('mail_auth_block')->key($karyawan)->save('');
        Repository::dir('mail_folder_cache')->key($karyawan)->save('');
    }

    private function normalizeStatus($status): array
    {
        return [
            'total'        => (int) ($status->messages ?? 0),
            'unread_count' => (int) ($status->unseen ?? 0),
            'uidnext'      => (int) ($status->uidnext ?? 0),
        ];
    }

    private function getAuthBlock(): ?array
    {
        $raw = Repository::dir('mail_auth_block')->key($this->karyawan)->get();
        if (empty($raw)) {
            return null;
        }

        $block = json_decode($raw, true);
        if (empty($block['until']) || time() >= (int) $block['until']) {
            $this->clearAuthBlock();
            return null;
        }

        return $block;
    }

    private function recordAuthFailure(string $message): void
    {
        Repository::dir('mail_auth_block')->key($this->karyawan)->save(json_encode([
            'until'   => time() + self::AUTH_COOLDOWN,
            'message' => $message,
        ]));
    }

    private function clearAuthBlock(): void
    {
        Repository::dir('mail_auth_block')->key($this->karyawan)->save('');
    }

    private function assertNotAuthBlocked(): void
    {
        if ($block = $this->getAuthBlock()) {
            throw new \RuntimeException($block['message']);
        }
    }

    private function isAuthError(string $message): bool
    {
        $message = strtolower($message);
        $needles = ['auth', 'login fail', 'authentication', 'invalid credentials', 'password'];

        foreach ($needles as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function collectImapError(): string
    {
        $errors = \imap_errors() ?: [];
        $alerts = \imap_alerts() ?: [];
        $this->clearImapErrors();

        $parts = array_filter([
            !empty($errors) ? end($errors) : null,
            !empty($alerts) ? end($alerts) : null,
            \imap_last_error() ?: null,
        ]);

        return trim(implode('. ', array_unique($parts))) ?: 'Unknown IMAP error';
    }

    private function clearImapErrors(): void
    {
        if (function_exists('imap_errors')) {
            @\imap_errors();
        }
        if (function_exists('imap_alerts')) {
            @\imap_alerts();
        }
    }

    private function getFolderCache(): array
    {
        $raw = Repository::dir('mail_folder_cache')->key($this->karyawan)->get();
        if (empty($raw)) {
            return [];
        }

        return json_decode($raw, true) ?: [];
    }

    private function saveFolderCache(array $cache): void
    {
        Repository::dir('mail_folder_cache')->key($this->karyawan)->save(json_encode($cache));
    }
}
