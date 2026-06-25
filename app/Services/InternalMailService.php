<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpImap\Mailbox;
use PHPMailer\PHPMailer\PHPMailer;
use Repository;

class InternalMailService
{
    private const CACHE_TTL = 120;
    private const AUTH_COOLDOWN = 300;
    private const INITIAL_SYNC_LIMIT = 500;
    private const CHUNK_SYNC_LIMIT = 200;
    private const TAIL_SYNC_LIMIT = 50;

    private const FOLDER_MAP = [
        'inbox'  => ['INBOX'],
        'outbox' => ['Sent', 'Sent Items', 'Sent Messages', 'Sent Mail', 'INBOX.Sent', 'INBOX/Sent', '[Gmail]/Sent Mail'],
        'trash'  => ['Trash', 'Deleted Items', 'Deleted Messages', '[Gmail]/Trash'],
        'spam'   => ['Junk', 'Spam', 'Junk E-mail', '[Gmail]/Spam'],
        'draft'  => ['Drafts', '[Gmail]/Drafts'],
    ];

    private int $idKaryawan;
    private ?string $legacyKey;

    public function __construct(int $idKaryawan, ?string $legacyKey = null)
    {
        if ($idKaryawan <= 0) {
            throw new \RuntimeException('ID karyawan tidak valid');
        }

        $this->idKaryawan = $idKaryawan;
        $this->legacyKey = $legacyKey;
    }

    private function storageKey(): string
    {
        return (string) $this->idKaryawan;
    }

    private function readRepository(string $dir): ?string
    {
        $raw = Repository::dir($dir)->key($this->storageKey())->get();
        if (!empty($raw) || empty($this->legacyKey)) {
            return $raw ?: null;
        }

        $legacy = Repository::dir($dir)->key($this->legacyKey)->get();
        if (!empty($legacy)) {
            Repository::dir($dir)->key($this->storageKey())->save($legacy);
        }

        return $legacy ?: null;
    }

    private function writeRepository(string $dir, string $value): void
    {
        Repository::dir($dir)->key($this->storageKey())->save($value);
    }

    public function getSettings(): ?array
    {
        $raw = $this->readRepository('setting_mail');
        if (empty($raw)) {
            return null;
        }

        return json_decode($raw, true);
    }

    public function checkUpdates(string $folder = 'inbox'): array
    {
        if (!$this->getSettings()) {
            return [
                'changed'        => false,
                'unread_count'   => 0,
                'total'          => 0,
                'new_count'      => 0,
                'needs_refresh'  => false,
                'not_configured' => true,
            ];
        }

        $meta = $this->getFolderMeta($folder) ?? [];

        if ($block = $this->getAuthBlock()) {
            return [
                'changed'       => false,
                'unread_count'  => (int) ($meta['unread_count'] ?? 0),
                'total'         => (int) ($meta['total'] ?? 0),
                'new_count'     => 0,
                'needs_refresh' => false,
                'error'         => $block['message'],
                'auth_blocked'  => true,
            ];
        }

        try {
            $status = $this->fetchMailboxStatus($folder);
        } catch (\Throwable $e) {
            return [
                'changed'       => false,
                'unread_count'  => (int) ($meta['unread_count'] ?? 0),
                'total'         => (int) ($meta['total'] ?? 0),
                'new_count'     => 0,
                'needs_refresh' => false,
                'error'         => $e->getMessage(),
                'auth_blocked'  => $this->isAuthError($e->getMessage()),
            ];
        }

        $prevTotal = (int) ($meta['total'] ?? 0);
        $hasIndex = $this->countIndexRows($folder) > 0;
        $changed = empty($meta)
            || $prevTotal !== (int) $status['total']
            || (int) ($meta['uidnext'] ?? 0) !== (int) $status['uidnext'];

        if (!$hasIndex) {
            $changed = $changed
                || (int) ($meta['unread_count'] ?? 0) !== (int) $status['unread_count'];
        }

        $newCount = $this->estimateNewMessageCount($meta, $status);
        $previousIndexedUnread = $hasIndex
            ? $this->countIndexedUnread($folder)
            : (int) ($meta['unread_count'] ?? 0);

        if ($folder === 'inbox' && $newCount > 0) {
            try {
                $this->syncFolderIndex($folder, false);
                $this->recalculateIndexedUnreadMeta($folder);
            } catch (\Throwable $e) {
                // Lanjut dengan fallback unread di bawah
            }
        }

        if ($changed) {
            if ($folder === 'inbox') {
                $this->updateFolderStatusFromImap($folder, $status);
            } else {
                $this->updateFolderMetaCounts($folder, $status);
            }
        }

        $unreadCount = $this->getDisplayUnreadCount($folder, $status);
        if ($folder === 'inbox' && $newCount > 0 && $unreadCount <= $previousIndexedUnread) {
            $unreadCount = $previousIndexedUnread + $newCount;
        }

        return [
            'changed'       => $changed,
            'unread_count'  => $unreadCount,
            'total'         => (int) $status['total'],
            'new_count'     => $newCount,
            'needs_refresh' => $changed && $folder === 'inbox',
        ];
    }

    public function fetchList(
        string $folder,
        int $page,
        int $perPage,
        ?string $query = null,
        bool $forceRefresh = false,
        ?string $sort = null,
        ?string $filter = null,
        bool $skipSync = false,
        bool $incrementalSync = false
    ): array {
        if ($folder === 'local_draft') {
            return $this->fetchLocalDrafts($page, $perPage, $query, $sort);
        }

        if (!$this->getSettings()) {
            return $this->emptyListNotConfigured();
        }

        $meta = $this->getFolderMeta($folder);
        $indexedCount = (int) ($meta['indexed_count'] ?? 0);
        $hasIndex = $indexedCount > 0;
        $stale = empty($meta) || empty($meta['synced_at'])
            || (time() - strtotime($meta['synced_at'])) > self::CACHE_TTL;

        if ($block = $this->getAuthBlock()) {
            if ($hasIndex) {
                $result = $this->queryEmailList($folder, $page, $perPage, $sort, $filter, $query, $meta);
                $result['error'] = $block['message'];
                return $result;
            }
            throw new \RuntimeException($block['message']);
        }

        $syncError = null;
        $newCount = 0;

        if (!$skipSync && empty($query)) {
            try {
                if ($forceRefresh) {
                    $newCount = $this->syncFolderIndex($folder, true);
                } elseif (!$hasIndex) {
                    $newCount = $this->syncFolderIndex($folder, false);
                } elseif ($stale || $incrementalSync) {
                    $newCount = $this->syncFolderIndex($folder, false);
                } else {
                    $this->ensurePageIndexed($folder, $page, $perPage);
                }
                $meta = $this->getFolderMeta($folder) ?? $meta;
            } catch (\Throwable $e) {
                $syncError = $e->getMessage();
                if (!$hasIndex) {
                    throw $e;
                }
            }
        }

        if ($folder === 'inbox' && $hasIndex) {
            $this->recalculateIndexedUnreadMeta($folder);
            $meta = $this->getFolderMeta($folder) ?? $meta;
        }

        $result = $this->queryEmailList($folder, $page, $perPage, $sort, $filter, $query, $meta);
        $result['new_count'] = $newCount;
        $result['from_cache'] = $skipSync;
        if ($syncError) {
            $result['error'] = $syncError;
        }

        return $result;
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
        $dirAttachments = public_path('email/' . $this->storageKey() . '/attachments');

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
                'url'      => env('APP_URL') . '/public/email/' . $this->storageKey() . '/attachments/' . end($pathParts),
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
        $ownEmail = $settings['email'] ?? null;

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
            'reply'     => $this->buildReplyRecipients($fromList, $toList, $ccList, $ownEmail),
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
        $this->updateIndexedEmailFlag($folder, $uid, $seen);
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

        $this->removeFromIndex($fromFolder, $uid);
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
        $this->removeFromIndex($folder, $uid);
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
        $this->configurePhpmailerSsl($mail, $settings);

        $mail->setFrom($settings['email'], $settings['full_name'] ?? $settings['email']);

        foreach ($this->parseRecipientEntries($data['to'] ?? '') as $recipient) {
            $mail->addAddress($recipient['email'], $recipient['name'] !== $recipient['email'] ? $recipient['name'] : '');
        }

        if (!empty($data['cc'])) {
            foreach ($this->parseRecipientEntries($data['cc']) as $cc) {
                $mail->addCC($cc['email'], $cc['name'] !== $cc['email'] ? $cc['name'] : '');
            }
        }

        if (!empty($data['bcc'])) {
            foreach ($this->parseRecipientEntries($data['bcc']) as $bcc) {
                $mail->addBCC($bcc['email'], $bcc['name'] !== $bcc['email'] ? $bcc['name'] : '');
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $data['subject'] ?? '';
        $mail->Body = $data['html_body'] ?? ($data['body'] ?? '');
        $mail->AltBody = strip_tags($mail->Body);

        if (!$mail->send()) {
            throw new \RuntimeException('Gagal mengirim email');
        }

        try {
            $this->saveSentCopyToImap($mail);
            $this->syncFolderIndex('outbox', false);
        } catch (\Throwable $e) {
            // Email sudah terkirim via SMTP; outbox akan disinkronkan saat folder dibuka
        }
    }

    private function saveSentCopyToImap(PHPMailer $mail): void
    {
        $settings = $this->getSettings();
        if (!$settings) {
            return;
        }

        $message = $mail->getSentMIMEMessage();
        if ($message === '') {
            return;
        }

        $message = preg_replace("/(?<!\r)\n/", "\r\n", str_replace("\r\n", "\n", $message));
        $message = rtrim($message) . "\r\n";

        foreach ($this->resolveOutboxFolderCandidates() as $imapFolder) {
            if ($this->tryAppendToFolder($settings, $imapFolder, $message)) {
                $cached = $this->getFolderCache();
                $cached['outbox'] = $imapFolder;
                $this->saveFolderCache($cached);
                return;
            }
        }

        $this->createAndAppendSentFolder($settings, self::FOLDER_MAP['outbox'][0], $message);
    }

    private function resolveOutboxFolderCandidates(): array
    {
        $cached = $this->getFolderCache();
        $candidates = self::FOLDER_MAP['outbox'];

        if (!empty($cached['outbox'])) {
            return array_values(array_unique(array_merge([$cached['outbox']], $candidates)));
        }

        return $candidates;
    }

    private function tryAppendToFolder(array $settings, string $imapFolder, string $message): bool
    {
        $path = $this->buildMailboxPath($settings, $imapFolder);
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
                return false;
            }

            $appended = @\imap_append($connection, $path, $message, '\\Seen');
            @\imap_close($connection);
            $this->clearImapErrors();

            return (bool) $appended;
        } finally {
            restore_error_handler($previousHandler);
        }
    }

    private function createAndAppendSentFolder(array $settings, string $imapFolder, string $message): void
    {
        $basePath = $this->buildMailboxPath($settings, '');
        $fullPath = $basePath . $imapFolder;
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
                return;
            }

            @\imap_createmailbox($connection, $fullPath);

            if (@\imap_reopen($connection, $fullPath)) {
                @\imap_append($connection, $fullPath, $message, '\\Seen');
                $cached = $this->getFolderCache();
                $cached['outbox'] = $imapFolder;
                $this->saveFolderCache($cached);
            }

            @\imap_close($connection);
            $this->clearImapErrors();
        } finally {
            restore_error_handler($previousHandler);
        }
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
        $this->writeRepository('mail_draft', json_encode(array_values($drafts)));

        return $draft;
    }

    public function deleteLocalDraft($id): void
    {
        $drafts = $this->getLocalDraftList();
        unset($drafts[$id]);
        $this->writeRepository('mail_draft', json_encode(array_values($drafts)));
    }

    private function emptyListNotConfigured(): array
    {
        return [
            'emails'         => [],
            'total'          => 0,
            'unread_count'   => 0,
            'indexed'        => 0,
            'new_count'      => 0,
            'not_configured' => true,
        ];
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

    private function syncFolderIndex(string $folder, bool $forceFull = false): int
    {
        $settings = $this->getSettings();
        if (!$settings) {
            throw new \RuntimeException('Setting email belum dikonfigurasi');
        }

        $meta = $this->getFolderMeta($folder);
        $beforeUid = $this->resolveLastUid($folder, $meta);

        $imapFolder = $this->resolveImapFolder($folder);
        $path = $this->buildMailboxPath($settings, $imapFolder);
        $connection = $this->openConnection($settings, $path);

        $statusObj = @\imap_status($connection, $path, \SA_MESSAGES | \SA_UNSEEN | \SA_UIDNEXT);
        $status = $statusObj ? $this->normalizeStatus($statusObj) : ['total' => 0, 'unread_count' => 0, 'uidnext' => 0];

        $indexedCount = (int) ($meta['indexed_count'] ?? 0);
        $lastUid = $beforeUid;

        if ($forceFull) {
            $this->clearIndexRows($folder);
            $indexedCount = 0;
            $lastUid = 0;
        }

        if ($indexedCount === 0) {
            $this->fetchAndStoreBySequence($connection, $folder, self::INITIAL_SYNC_LIMIT, $status);
        } else {
            $this->fetchAndStoreBySequence($connection, $folder, self::TAIL_SYNC_LIMIT, $status);

            if ($lastUid > 0 && (int) $status['uidnext'] > $lastUid + 1) {
                $this->fetchAndStoreByUidRange($connection, $folder, $lastUid + 1, (int) $status['uidnext'] - 1, $status);
            }
        }

        @\imap_close($connection);
        $this->clearImapErrors();

        return $this->countNewUidsSince($folder, $beforeUid);
    }

    private function countNewUidsSince(string $folder, int $sinceUid): int
    {
        if ($sinceUid <= 0) {
            return 0;
        }

        return (int) DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->where('uid', '>', $sinceUid)
            ->count();
    }

    private function resolveLastUid(string $folder, ?array $meta): int
    {
        $lastUid = (int) ($meta['last_uid'] ?? 0);
        if ($lastUid > 0) {
            return $lastUid;
        }

        $maxUid = DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->max('uid');

        return (int) ($maxUid ?? 0);
    }

    private function ensurePageIndexed(string $folder, int $page, int $perPage): void
    {
        $meta = $this->getFolderMeta($folder);
        if (!$meta) {
            return;
        }

        $needed = $page * $perPage;
        $indexedCount = (int) ($meta['indexed_count'] ?? 0);
        $total = (int) ($meta['total'] ?? 0);
        $minSeq = (int) ($meta['min_seq'] ?? 0);

        if ($needed <= $indexedCount || $indexedCount >= $total || $minSeq <= 1) {
            return;
        }

        $settings = $this->getSettings();
        if (!$settings) {
            return;
        }

        $imapFolder = $this->resolveImapFolder($folder);
        $path = $this->buildMailboxPath($settings, $imapFolder);
        $connection = $this->openConnection($settings, $path);

        $chunkStart = max(1, $minSeq - self::CHUNK_SYNC_LIMIT);
        $statusObj = @\imap_status($connection, $path, \SA_MESSAGES | \SA_UNSEEN | \SA_UIDNEXT);
        $status = $statusObj ? $this->normalizeStatus($statusObj) : [];
        $this->fetchAndStoreSequenceRange($connection, $folder, $chunkStart, $minSeq - 1, array_merge($meta, $status));

        @\imap_close($connection);
        $this->clearImapErrors();
    }

    private function fetchAndStoreBySequence($connection, string $folder, int $limit, array $status): void
    {
        $total = (int) @\imap_num_msg($connection);
        if ($total <= 0) {
            $this->saveFolderMeta($folder, array_merge($status, [
                'last_uid'      => 0,
                'min_seq'       => 0,
                'max_seq'       => 0,
                'indexed_count' => 0,
            ]));
            return;
        }

        $start = max(1, $total - $limit + 1);
        $this->fetchAndStoreSequenceRange($connection, $folder, $start, $total, $status);
    }

    private function fetchAndStoreSequenceRange($connection, string $folder, int $start, int $end, array $status): void
    {
        if ($start > $end || $end < 1) {
            return;
        }

        $overviews = @\imap_fetch_overview($connection, "{$start}:{$end}") ?: [];
        $rows = $this->mapOverviewsToRows($folder, $overviews);

        if (!empty($rows)) {
            $this->upsertEmailRows($folder, $rows);
        }

        $meta = $this->getFolderMeta($folder) ?? [];
        $uids = array_column($rows, 'uid');
        $lastUid = !empty($uids) ? max($uids) : (int) ($meta['last_uid'] ?? 0);
        $existingMin = (int) ($meta['min_seq'] ?? 0);

        $this->saveFolderMeta($folder, array_merge($status, [
            'last_uid'      => max((int) ($meta['last_uid'] ?? 0), $lastUid),
            'min_seq'       => $existingMin > 0 ? min($existingMin, $start) : $start,
            'max_seq'       => max((int) ($meta['max_seq'] ?? 0), $end),
            'indexed_count' => $this->countIndexRows($folder),
        ]));
    }

    private function fetchAndStoreByUidRange($connection, string $folder, int $fromUid, int $toUid, array $status): void
    {
        if ($fromUid > $toUid) {
            $this->saveFolderMeta($folder, array_merge($this->getFolderMeta($folder) ?? [], $status));
            return;
        }

        $uids = @\imap_search($connection, 'UID ' . $fromUid . ':' . $toUid, \SE_UID) ?: [];
        if (empty($uids)) {
            $this->saveFolderMeta($folder, array_merge($this->getFolderMeta($folder) ?? [], $status));
            return;
        }

        rsort($uids);
        $rows = [];
        foreach ($uids as $uid) {
            $overview = @\imap_fetch_overview($connection, (string) $uid, \FT_UID);
            if (empty($overview[0])) {
                continue;
            }
            $mapped = $this->mapOverviewsToRows($folder, [$overview[0]]);
            if (!empty($mapped[0])) {
                $rows[] = $mapped[0];
            }
        }

        if (!empty($rows)) {
            $this->upsertEmailRows($folder, $rows);
        }

        $meta = $this->getFolderMeta($folder) ?? [];
        $this->saveFolderMeta($folder, array_merge($meta, $status, [
            'last_uid'      => max((int) ($meta['last_uid'] ?? 0), $toUid),
            'indexed_count' => $this->countIndexRows($folder),
        ]));
    }

    private function mapOverviewsToRows(string $folder, array $overviews): array
    {
        $rows = [];
        foreach ($overviews as $item) {
            $uid = (int) ($item->uid ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $from = $this->decodeHeader($item->from ?? '');
            $to = $this->decodeHeader($item->to ?? '');

            $rows[] = [
                'uid'        => $uid,
                'seq_num'    => (int) ($item->msgno ?? 0),
                'from_addr'  => $from,
                'to_addr'    => $to ?: null,
                'subject'    => $this->decodeHeader($item->subject ?? '') ?: '(Tidak ada subjek)',
                'email_date' => isset($item->date) ? date('Y-m-d H:i:s', strtotime($item->date)) : null,
                'size_bytes' => (int) ($item->size ?? 0),
                'is_seen'    => ((int) ($item->seen ?? 0)) === 1,
            ];
        }

        return $rows;
    }

    private function getFolderMeta(string $folder): ?array
    {
        $row = DB::table('mail_folder_meta')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->first();

        return $row ? (array) $row : null;
    }

    private function saveFolderMeta(string $folder, array $data): void
    {
        $indexedCount = (int) ($data['indexed_count'] ?? $this->countIndexRows($folder));
        $unreadCount = (int) ($data['unread_count'] ?? 0);

        if ($indexedCount > 0) {
            $unreadCount = $this->countIndexedUnread($folder);
        }

        $payload = [
            'id_karyawan'   => $this->idKaryawan,
            'folder'        => $folder,
            'total'         => (int) ($data['total'] ?? 0),
            'unread_count'  => $unreadCount,
            'uidnext'       => (int) ($data['uidnext'] ?? 0),
            'last_uid'      => (int) ($data['last_uid'] ?? 0),
            'min_seq'       => (int) ($data['min_seq'] ?? 0),
            'max_seq'       => (int) ($data['max_seq'] ?? 0),
            'indexed_count' => $indexedCount,
            'synced_at'     => date('Y-m-d H:i:s'),
        ];

        DB::table('mail_folder_meta')->updateOrInsert(
            ['id_karyawan' => $this->idKaryawan, 'folder' => $folder],
            $payload
        );
    }

    private function updateFolderMetaCounts(string $folder, array $status): void
    {
        $meta = $this->getFolderMeta($folder) ?? [];
        $this->saveFolderMeta($folder, array_merge($meta, $status));
    }

    private function updateFolderStatusFromImap(string $folder, array $status): void
    {
        if (empty($this->getFolderMeta($folder))) {
            return;
        }

        DB::table('mail_folder_meta')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->update([
                'total'   => (int) ($status['total'] ?? 0),
                'uidnext' => (int) ($status['uidnext'] ?? 0),
            ]);

        if ($this->countIndexRows($folder) <= 0) {
            DB::table('mail_folder_meta')
                ->where('id_karyawan', $this->idKaryawan)
                ->where('folder', $folder)
                ->update(['unread_count' => (int) ($status['unread_count'] ?? 0)]);
        }
    }

    private function estimateNewMessageCount(?array $meta, array $status): int
    {
        if (empty($meta)) {
            return 0;
        }

        $prevUidNext = (int) ($meta['uidnext'] ?? 0);
        $currUidNext = (int) ($status['uidnext'] ?? 0);

        if ($prevUidNext > 0 && $currUidNext > $prevUidNext) {
            return $currUidNext - $prevUidNext;
        }

        $prevTotal = (int) ($meta['total'] ?? 0);

        return max(0, (int) ($status['total'] ?? 0) - $prevTotal);
    }

    private function upsertEmailRows(string $folder, array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('mail_list_index')->updateOrInsert(
                [
                    'id_karyawan' => $this->idKaryawan,
                    'folder'   => $folder,
                    'uid'      => (int) $row['uid'],
                ],
                [
                    'seq_num'    => (int) ($row['seq_num'] ?? 0),
                    'from_addr'  => $row['from_addr'] ?? '',
                    'to_addr'    => $row['to_addr'] ?? null,
                    'subject'    => $row['subject'] ?? '',
                    'email_date' => $row['email_date'] ?? null,
                    'size_bytes' => (int) ($row['size_bytes'] ?? 0),
                    'is_seen'    => !empty($row['is_seen']),
                ]
            );
        }
    }

    private function countIndexRows(string $folder): int
    {
        return (int) DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->count();
    }

    private function countIndexedUnread(string $folder): int
    {
        return (int) DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->where('is_seen', false)
            ->count();
    }

    private function getDisplayUnreadCount(string $folder, ?array $imapStatus = null): int
    {
        if ($this->countIndexRows($folder) > 0) {
            return $this->countIndexedUnread($folder);
        }

        if ($imapStatus !== null) {
            return (int) ($imapStatus['unread_count'] ?? 0);
        }

        $meta = $this->getFolderMeta($folder);

        return (int) ($meta['unread_count'] ?? 0);
    }

    private function recalculateIndexedUnreadMeta(string $folder): void
    {
        if (empty($this->getFolderMeta($folder))) {
            return;
        }

        DB::table('mail_folder_meta')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->update(['unread_count' => $this->countIndexedUnread($folder)]);
    }

    private function clearIndexRows(string $folder): void
    {
        DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->delete();
    }

    private function removeFromIndex(string $folder, $uid): void
    {
        DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->where('uid', (int) $uid)
            ->delete();

        $meta = $this->getFolderMeta($folder);
        if ($meta) {
            $total = max(0, (int) $meta['total'] - 1);
            $this->saveFolderMeta($folder, array_merge($meta, [
                'total'         => $total,
                'indexed_count' => max(0, (int) $meta['indexed_count'] - 1),
            ]));
        }
    }

    private function updateIndexedEmailFlag(string $folder, $uid, bool $seen): void
    {
        DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->where('uid', (int) $uid)
            ->update(['is_seen' => $seen]);

        $meta = $this->getFolderMeta($folder);
        if ($meta) {
            $this->recalculateIndexedUnreadMeta($folder);
        }
    }

    private function queryEmailList(
        string $folder,
        int $page,
        int $perPage,
        ?string $sort,
        ?string $filter,
        ?string $query,
        ?array $meta
    ): array {
        $meta = $meta ?? $this->getFolderMeta($folder) ?? [];
        $builder = DB::table('mail_list_index')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder);

        if ($query) {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $builder->where(function ($q) use ($like) {
                $q->where('subject', 'like', $like)
                    ->orWhere('from_addr', 'like', $like)
                    ->orWhere('to_addr', 'like', $like);
            });
        }

        if ($filter === 'unread') {
            $builder->where('is_seen', false);
        } elseif ($filter === 'read') {
            $builder->where('is_seen', true);
        }

        $sort = $sort ?: 'date_desc';
        switch ($sort) {
            case 'date_asc':
                $builder->orderBy('email_date', 'asc')->orderBy('uid', 'asc');
                break;
            case 'unread_first':
                $builder->orderBy('is_seen', 'asc')->orderBy('email_date', 'desc')->orderBy('uid', 'desc');
                break;
            case 'sender_asc':
                $builder->orderBy('from_addr', 'asc');
                break;
            case 'sender_desc':
                $builder->orderBy('from_addr', 'desc');
                break;
            case 'subject_asc':
                $builder->orderBy('subject', 'asc');
                break;
            case 'subject_desc':
                $builder->orderBy('subject', 'desc');
                break;
            case 'date_desc':
            default:
                $builder->orderBy('email_date', 'desc')->orderBy('uid', 'desc');
                break;
        }

        $listTotal = $builder->count();
        $mailboxTotal = (int) ($meta['total'] ?? $listTotal);

        if ($filter === 'unread' || $filter === 'read' || $query) {
            $mailboxTotal = $listTotal;
        }

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $builder->offset($offset)->limit($perPage)->get();

        $emails = [];
        foreach ($rows as $row) {
            $emails[] = [
                'id'      => (int) $row->uid,
                'from'    => $row->from_addr,
                'to'      => $row->to_addr,
                'subject' => $row->subject,
                'date'    => $row->email_date ? date('c', strtotime($row->email_date)) : null,
                'size'    => $this->formatSize((int) $row->size_bytes),
                'is_seen' => (bool) $row->is_seen,
            ];
        }

        return [
            'emails'       => $emails,
            'total'        => $mailboxTotal,
            'unread_count' => $this->getDisplayUnreadCount($folder),
            'indexed'      => (int) ($meta['indexed_count'] ?? $listTotal),
        ];
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

        if (function_exists('imap_timeout')) {
            @\imap_timeout(\IMAP_OPENTIMEOUT, 10);
            @\imap_timeout(\IMAP_READTIMEOUT, 20);
            @\imap_timeout(\IMAP_WRITETIMEOUT, 10);
            @\imap_timeout(\IMAP_CLOSETIMEOUT, 10);
        }

        $pathsToTry = [$path];
        $fallbackPath = $this->buildImapSslFallbackPath($settings, $path);
        if ($fallbackPath !== null && $fallbackPath !== $path) {
            $pathsToTry[] = $fallbackPath;
        }

        $previousHandler = set_error_handler(function () {
            return true;
        });

        try {
            $error = 'Unknown IMAP error';

            foreach ($pathsToTry as $tryPath) {
                $connection = @\imap_open(
                    $tryPath,
                    $settings['email'],
                    $settings['password'],
                    0,
                    1,
                    ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
                );

                if ($connection) {
                    $this->clearAuthBlock();
                    return $connection;
                }

                $error = $this->collectImapError();
                if (!$this->isSslNegotiationError($error)) {
                    break;
                }
            }

            if ($this->isAuthError($error)) {
                $this->recordAuthFailure(
                    'Autentikasi email gagal. Periksa email/password di Setting Mail, atau tunggu 5 menit jika akun terkunci.'
                );
            }
            throw new \RuntimeException('Koneksi IMAP gagal: ' . $error);
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
        $security = $this->buildImapSecurityFlag($settings);
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

    private function buildImapSecurityFlag(array $settings): string
    {
        $port = (int) ($settings['incoming']['port'] ?? 0);
        $protocol = $settings['protocol'] ?? 'imap';
        $security = $this->mapImapSecurity($settings['incoming']['connection_security'] ?? 'SSL');

        $implicitPort = $protocol === 'pop3' ? 995 : 993;
        $plainPort = $protocol === 'pop3' ? 110 : 143;

        if ($port === $implicitPort) {
            $security = 'ssl';
        } elseif ($port === $plainPort && $security === 'ssl') {
            $security = 'tls';
        } elseif ($security === 'notls' && $port === $implicitPort) {
            $security = 'ssl';
        }

        if (!self::shouldValidateMailCert($settings) && in_array($security, ['ssl', 'tls'], true)) {
            $security .= '/novalidate-cert';
        }

        return $security;
    }

    private function buildImapSslFallbackPath(array $settings, string $path): ?string
    {
        $port = (int) ($settings['incoming']['port'] ?? 0);
        $protocol = $settings['protocol'] ?? 'imap';
        $implicitPort = $protocol === 'pop3' ? 995 : 993;

        if ($port === $implicitPort || !preg_match('/^\{[^}]+\}(.*)$/s', $path, $matches)) {
            return null;
        }

        $fallbackSettings = $settings;
        $fallbackSettings['incoming']['port'] = $implicitPort;
        $fallbackSettings['incoming']['connection_security'] = 'SSL';

        return $this->buildMailboxPath($fallbackSettings, $matches[1]);
    }

    private function isSslNegotiationError(string $error): bool
    {
        $needles = [
            'SSL negotiation failed',
            'TLS/SSL failure',
            'Certificate failure',
            'Self signed certificate',
        ];

        foreach ($needles as $needle) {
            if (stripos($error, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function configurePhpmailerSslForSettings(PHPMailer $mail, array $settings): void
    {
        if (self::shouldValidateMailCert($settings)) {
            return;
        }

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    private function configurePhpmailerSsl(PHPMailer $mail, array $settings): void
    {
        self::configurePhpmailerSslForSettings($mail, $settings);
    }

    private static function shouldValidateMailCert(array $settings): bool
    {
        if (filter_var(env('MAIL_IMAP_NOVALIDATE_CERT', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $incoming = $settings['incoming'] ?? [];
        if (array_key_exists('validate_cert', $incoming)) {
            return filter_var($incoming['validate_cert'], FILTER_VALIDATE_BOOLEAN);
        }

        return true;
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

    private function clearCache(string $folder): void
    {
        $this->clearIndexRows($folder);
        DB::table('mail_folder_meta')
            ->where('id_karyawan', $this->idKaryawan)
            ->where('folder', $folder)
            ->delete();
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
        $raw = $this->readRepository('mail_draft');
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
        return array_column($this->parseRecipientEntries($recipients), 'email');
    }

    private function parseRecipientEntries(string $recipients): array
    {
        $recipients = trim($recipients);
        if ($recipients === '') {
            return [];
        }

        $entries = [];
        $parsed = @\imap_rfc822_parse_adrlist($recipients, 'localhost');
        if ($parsed) {
            foreach ($parsed as $addr) {
                if (empty($addr->mailbox) || empty($addr->host) || $addr->host === '.') {
                    continue;
                }

                $email = strtolower($addr->mailbox . '@' . $addr->host);
                $name = isset($addr->personal) ? trim($this->decodeHeader($addr->personal), " \t\"") : '';
                $entry = $this->makeRecipientEntry($name !== '' ? $name : null, $email);
                if ($entry) {
                    $entries[] = $entry;
                }
            }
        }

        if (!empty($entries)) {
            return $this->uniqueRecipients($entries);
        }

        foreach ($this->splitRecipientString($recipients) as $part) {
            $entry = $this->makeRecipientEntryFromDisplay($part);
            if (!$entry) {
                $entry = $this->makeRecipientEntry(null, $part);
            }
            if ($entry) {
                $entries[] = $entry;
            }
        }

        return $this->uniqueRecipients($entries);
    }

    private function splitRecipientString(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $raw)), function ($item) {
            return $item !== '' && $item !== ',';
        }));
    }

    private function buildReplyRecipients(array $fromList, array $toList, array $ccList, ?string $ownEmail): array
    {
        $toEntry = $fromList[0] ?? null;
        $to = $toEntry['email'] ?? '';

        $exclude = [];
        if ($ownEmail) {
            $exclude[] = strtolower(trim($ownEmail));
        }
        if ($to !== '') {
            $exclude[] = strtolower($to);
        }

        $ccParts = [];
        foreach ($this->uniqueRecipients(array_merge($toList, $ccList)) as $recipient) {
            $email = strtolower($recipient['email'] ?? '');
            if ($email === '' || in_array($email, $exclude, true)) {
                continue;
            }
            $ccParts[] = $recipient['email'];
        }

        return [
            'to' => $to,
            'cc' => implode(', ', $ccParts),
        ];
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
            return $this->parseRecipientEntries($value);
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

        if (preg_match('/^"([^"]*)"\s*<([^>]+)>$/', $display, $matches)) {
            $name = trim($matches[1]);
            $email = trim($matches[2]);
            return $this->makeRecipientEntry($name !== '' ? $name : null, $email);
        }

        if (preg_match('/^([^<]+?)<([^>]+)>$/', $display, $matches)) {
            $name = trim($matches[1], " \t\"");
            $email = trim($matches[2]);
            return $this->makeRecipientEntry($name !== '' ? $name : null, $email);
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

    public static function clearAuthBlockFor(int $idKaryawan, ?string $legacyKey = null): void
    {
        $key = (string) $idKaryawan;
        Repository::dir('mail_auth_block')->key($key)->save('');
        Repository::dir('mail_folder_cache')->key($key)->save('');

        if ($legacyKey) {
            Repository::dir('mail_auth_block')->key($legacyKey)->save('');
            Repository::dir('mail_folder_cache')->key($legacyKey)->save('');
        }
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
        $raw = $this->readRepository('mail_auth_block');
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
        $this->writeRepository('mail_auth_block', json_encode([
            'until'   => time() + self::AUTH_COOLDOWN,
            'message' => $message,
        ]));
    }

    private function clearAuthBlock(): void
    {
        $this->writeRepository('mail_auth_block', '');
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
        $raw = $this->readRepository('mail_folder_cache');
        if (empty($raw)) {
            return [];
        }

        return json_decode($raw, true) ?: [];
    }

    private function saveFolderCache(array $cache): void
    {
        $this->writeRepository('mail_folder_cache', json_encode($cache));
    }
}
