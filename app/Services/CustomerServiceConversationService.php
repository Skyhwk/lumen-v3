<?php

namespace App\Services;

use App\Jobs\SendCustomerServiceConversationJob;
use App\Models\customer\CsTicket;
use App\Models\customer\CsTicketMessage;
use App\Models\customer\CsTicketRead;
use App\Models\customer\Users;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CustomerServiceConversationService
{
    public const BOT_NAME = 'Customer Service';
    public const CLOSED_STATUSES = ['closed'];
    public const CLEAR_MESSAGE = 'Permintaan / Pertanyaan Anda telah selesai kami tindak lanjuti. Jika masih ada hal yang ingin ditanyakan atau disampaikan, silakan lanjutkan percakapan ini.' . "\n\n" . 'Jika tidak ada tanggapan dalam 1 jam, ticket ini akan dianggap selesai dan ditutup otomatis.';
    public const CLOSE_MESSAGE = 'Terima kasih telah menghubungi kami. Permintaan Anda telah kami selesaikan dan ticket ini telah ditutup. Jika Anda membutuhkan bantuan kembali, silakan buat ticket atau percakapan baru. Kami dengan senang hati akan membantu.';
    public const ATTACHMENT_DIR = 'cs_tickets/conversation';
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    public const MAX_ATTACHMENT_BYTES = 5242880; // 5MB

    public static function isClosed(?string $status): bool
    {
        return in_array($status, self::CLOSED_STATUSES, true);
    }

    public static function canCustomerSend(?string $status): bool
    {
        if (self::isClosed($status)) {
            return false;
        }

        return $status !== 'open';
    }

    public static function canStaffSend(?string $status): bool
    {
        if (self::isClosed($status)) {
            return false;
        }

        return $status !== 'open';
    }

    public static function createTicket(
        Users $portalUser,
        $customerId,
        string $customerName,
        string $subject,
        ?string $category,
        string $initialMessage,
        ?UploadedFile $attachment = null
    ): CsTicket {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $ticketNo = CustomerServiceTicketNumberGenerator::generate();

        $ticket = CsTicket::create([
            'ticket_no' => $ticketNo,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'created_by_user_id' => $portalUser->id,
            'created_by_name' => $portalUser->nama_lengkap ?? $portalUser->name ?? 'Customer',
            'subject' => $subject,
            'category' => $category,
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => $now,
            'last_message_preview' => self::buildPreview($initialMessage),
            'created_at' => $now,
            'updated_at' => $now,
            'is_active' => true,
        ]);

        self::createCustomerMessage($ticket, $portalUser, $initialMessage, $attachment);
        self::createBotAutoReply($ticket);

        self::notifyInternalNewTicket($ticket);
        self::notifyCustomerTicketCreated($ticket);

        return $ticket->fresh();
    }

    public static function createBotAutoReply(CsTicket $ticket): CsTicketMessage
    {
        return self::insertMessage($ticket, [
            'sender_type' => 'bot',
            'sender_id' => null,
            'sender_name' => self::BOT_NAME,
            'message' => CustomerServiceBusinessHours::buildWelcomeMessage(),
            'attachment' => null,
            'message_kind' => 'chat',
            'is_auto' => true,
        ], true);
    }

    public static function createCustomerMessage(
        CsTicket $ticket,
        Users $portalUser,
        string $message,
        ?UploadedFile $attachment = null
    ): CsTicketMessage {
        $savedAttachment = self::storeAttachment($ticket, $attachment);

        $conversation = self::insertMessage($ticket, [
            'sender_type' => 'customer',
            'sender_id' => $portalUser->id,
            'sender_name' => $portalUser->nama_lengkap ?? $portalUser->name ?? 'Customer',
            'message' => $message,
            'attachment' => $savedAttachment,
        ], true);

        self::maybeReopenTicket($ticket);
        self::notifyInternalCustomerReply($ticket);

        return $conversation;
    }

    public static function createStaffMessage(
        CsTicket $ticket,
        int $staffId,
        string $staffName,
        string $message,
        ?UploadedFile $attachment = null
    ): CsTicketMessage {
        $savedAttachment = self::storeAttachment($ticket, $attachment);

        $conversation = self::insertMessage($ticket, [
            'sender_type' => 'staff',
            'sender_id' => $staffId,
            'sender_name' => $staffName,
            'message' => $message,
            'attachment' => $savedAttachment,
            'message_kind' => 'chat',
            'is_auto' => false,
        ], true);

        self::notifyCustomerStaffReply($ticket, $conversation);

        return $conversation;
    }

    public static function insertMessage(CsTicket $ticket, array $data, bool $publishMqtt): CsTicketMessage
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $message = CsTicketMessage::create(array_merge([
            'message_kind' => 'chat',
            'is_auto' => false,
        ], $data, [
            'ticket_id' => $ticket->id,
            'created_at' => $now,
        ]));

        $previewSource = trim(strip_tags((string) ($data['message'] ?? '')));
        if ($previewSource === '' && !empty($data['attachment'])) {
            $previewSource = '[Lampiran]';
        }

        $ticket->last_message_at = $now;
        $ticket->last_message_preview = self::buildPreview($previewSource);
        $ticket->updated_at = $now;
        $ticket->save();

        if ($publishMqtt) {
            self::publishConversationUpdate($ticket, $message);
        }

        return $message;
    }

    public static function formatMessage(CsTicketMessage $item, string $readerType, int $readerId): array
    {
        $isOwn = false;

        if ($item->sender_type === 'customer' && $readerType === 'customer') {
            $isOwn = (int) $item->sender_id === (int) $readerId;
        }

        if ($item->sender_type === 'staff' && $readerType === 'staff') {
            $isOwn = (int) $item->sender_id === (int) $readerId;
        }

        return [
            'id' => $item->id,
            'ticket_id' => $item->ticket_id,
            'sender_type' => $item->sender_type,
            'sender_id' => $item->sender_id,
            'sender_name' => $item->sender_name,
            'message' => $item->message,
            'attachment' => $item->attachment,
            'attachment_url' => $item->attachment
                ? self::ATTACHMENT_DIR . '/' . $item->attachment
                : null,
            'created_at' => $item->created_at,
            'message_kind' => $item->message_kind ?? 'chat',
            'is_auto' => (bool) ($item->is_auto ?? false),
            'is_own' => $isOwn,
        ];
    }

    public static function getUnreadCount(int $ticketId, string $readerType, int $readerId): int
    {
        $lastReadId = CsTicketRead::where('ticket_id', $ticketId)
            ->where('reader_type', $readerType)
            ->where('reader_id', $readerId)
            ->value('last_read_message_id') ?? 0;

        return CsTicketMessage::where('ticket_id', $ticketId)
            ->where('id', '>', $lastReadId)
            ->where(function ($query) use ($readerType, $readerId) {
                if ($readerType === 'customer') {
                    $query->where('sender_type', 'staff');
                    return;
                }

                $query->where('sender_type', 'customer');
            })
            ->count();
    }

    public static function markAsRead(int $ticketId, string $readerType, int $readerId): void
    {
        $latestMessageId = CsTicketMessage::where('ticket_id', $ticketId)->max('id') ?? 0;
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $read = CsTicketRead::where('ticket_id', $ticketId)
            ->where('reader_type', $readerType)
            ->where('reader_id', $readerId)
            ->first();

        if ($read) {
            $read->last_read_message_id = $latestMessageId;
            $read->updated_at = $now;
            $read->save();
            return;
        }

        CsTicketRead::create([
            'ticket_id' => $ticketId,
            'reader_type' => $readerType,
            'reader_id' => $readerId,
            'last_read_message_id' => $latestMessageId,
            'updated_at' => $now,
        ]);
    }

    public static function needsProcessResume(CsTicket $ticket): bool
    {
        if ($ticket->status !== 'in_progress') {
            return false;
        }

        return !CsTicketMessage::where('ticket_id', $ticket->id)
            ->where('is_auto', true)
            ->whereIn('message_kind', ['system', 'auto_staff'])
            ->exists();
    }

    public static function processTicket(CsTicket $ticket, int $staffId, string $staffName): array
    {
        $resumePartialProcess = self::needsProcessResume($ticket);

        if ($ticket->status === 'in_progress') {
            if (!$resumePartialProcess) {
                throw new \InvalidArgumentException('Ticket sudah diproses.');
            }
        } elseif ($ticket->status !== 'open') {
            throw new \InvalidArgumentException('Ticket hanya dapat diproses saat status open.');
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        if (!$resumePartialProcess) {
            $ticket->status = 'in_progress';
            $ticket->processed_at = $now;
            $ticket->processed_by = $staffId;
            $ticket->assigned_to = $staffId;
            $ticket->updated_at = $now;
            $ticket->save();
        }

        $messages = [];

        $messages[] = self::insertMessage($ticket, [
            'sender_type' => 'bot',
            'sender_id' => null,
            'sender_name' => self::BOT_NAME,
            'message' => 'Anda terhubung ke ' . $staffName,
            'attachment' => null,
            'message_kind' => 'system',
            'is_auto' => true,
        ], true);

        $messages[] = self::insertMessage($ticket, [
            'sender_type' => 'staff',
            'sender_id' => $staffId,
            'sender_name' => $staffName,
            'message' => 'Halo, dengan ' . $staffName . ' apakah ada yang bisa kami bantu?',
            'attachment' => null,
            'message_kind' => 'auto_staff',
            'is_auto' => true,
        ], true);

        self::publishConversationUpdate($ticket->fresh(), null, [
            'type' => 'cs_ticket_status',
            'status' => 'in_progress',
            'processed_by' => $staffId,
            'processed_by_name' => $staffName,
        ]);

        return [
            'ticket' => $ticket->fresh(),
            'messages' => $messages,
        ];
    }

    public static function clearTicket(CsTicket $ticket, int $staffId, ?string $staffName = null): array
    {
        if ($ticket->status !== 'in_progress') {
            throw new \InvalidArgumentException('Ticket hanya dapat di-clear saat status in_progress.');
        }

        $staffName = $staffName ?: self::resolveStaffName($staffId) ?: 'Staff';
        $now = Carbon::now();
        $nowString = $now->format('Y-m-d H:i:s');

        $ticket->status = 'waiting_customer';
        $ticket->auto_close_at = $now->copy()->addHour()->format('Y-m-d H:i:s');
        $ticket->updated_at = $nowString;
        $ticket->save();

        $message = self::insertMessage($ticket, [
            'sender_type' => 'staff',
            'sender_id' => $staffId,
            'sender_name' => $staffName,
            'message' => self::CLEAR_MESSAGE,
            'attachment' => null,
            'message_kind' => 'auto_staff',
            'is_auto' => true,
        ], true);

        self::notifyCustomerStaffReply($ticket, $message);

        self::publishConversationUpdate($ticket->fresh(), null, [
            'type' => 'cs_ticket_status',
            'status' => 'waiting_customer',
            'auto_close_at' => $ticket->auto_close_at,
            'cleared_by' => $staffId,
        ]);

        return [
            'ticket' => $ticket->fresh(),
            'message' => $message,
        ];
    }

    public static function closeTicket(CsTicket $ticket, ?int $closedById = null, ?string $closedByName = null): CsTicket
    {
        if (self::isClosed($ticket->status)) {
            throw new \InvalidArgumentException('Ticket sudah ditutup.');
        }

        $now = Carbon::now();
        $nowString = $now->format('Y-m-d H:i:s');

        $ticket->status = 'closed';
        $ticket->closed_at = $nowString;
        $ticket->closed_by = $closedById;
        $ticket->archived_at = $now->copy()->addDay()->format('Y-m-d H:i:s');
        $ticket->auto_close_at = null;
        $ticket->updated_at = $nowString;
        $ticket->save();

        $staffName = $closedByName ?: ($closedById ? self::resolveStaffName($closedById) : null) ?: 'Staff';

        if ($closedById) {
            $message = self::insertMessage($ticket, [
                'sender_type' => 'staff',
                'sender_id' => $closedById,
                'sender_name' => $staffName,
                'message' => self::CLOSE_MESSAGE,
                'attachment' => null,
                'message_kind' => 'auto_staff',
                'is_auto' => true,
            ], true);

            self::notifyCustomerStaffReply($ticket, $message);
        } else {
            self::insertMessage($ticket, [
                'sender_type' => 'bot',
                'sender_id' => null,
                'sender_name' => self::BOT_NAME,
                'message' => self::CLOSE_MESSAGE,
                'attachment' => null,
                'message_kind' => 'chat',
                'is_auto' => true,
            ], true);
        }

        self::publishConversationUpdate($ticket->fresh(), null, [
            'type' => 'cs_ticket_closed',
            'status' => 'closed',
            'closed_by' => $closedById,
            'closed_by_name' => $closedByName,
            'archived_at' => $ticket->archived_at,
        ]);

        self::notifyCustomerStatusChange($ticket, 'closed');

        return $ticket->fresh();
    }

    public static function autoCloseExpiredTickets(): int
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $ticketIds = CsTicket::where('is_active', true)
            ->where('status', 'waiting_customer')
            ->whereNotNull('auto_close_at')
            ->where('auto_close_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id');

        $closed = 0;

        foreach ($ticketIds as $ticketId) {
            $ticket = CsTicket::where('is_active', true)->where('id', $ticketId)->first();
            if (!$ticket || $ticket->status !== 'waiting_customer') {
                continue;
            }

            if (!$ticket->auto_close_at || $ticket->auto_close_at > $now) {
                continue;
            }

            try {
                self::closeTicket($ticket, null, 'System');
                $closed++;
            } catch (\Throwable $exception) {
            }
        }

        return $closed;
    }

    public static function autoArchiveExpiredTickets(): int
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        return CsTicket::where('is_active', true)
            ->where('status', 'closed')
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $now)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);
    }

    public static function updateTicketStatus(CsTicket $ticket, string $status): CsTicket
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $ticket->status = $status;
        $ticket->updated_at = $now;

        if ($status === 'closed') {
            $ticket->closed_at = $now;
        }

        $ticket->save();

        self::publishConversationUpdate($ticket, null, [
            'type' => 'cs_ticket_status',
            'status' => $status,
        ]);

        if (in_array($status, ['resolved', 'closed'], true)) {
            self::notifyCustomerStatusChange($ticket, $status);
        }

        return $ticket->fresh();
    }

    public static function assignTicket(CsTicket $ticket, ?int $assignedTo): CsTicket
    {
        $ticket->assigned_to = $assignedTo;
        $ticket->updated_at = Carbon::now()->format('Y-m-d H:i:s');

        $ticket->save();

        return $ticket->fresh();
    }

    public static function storeAttachment(CsTicket $ticket, ?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Format lampiran harus jpg, jpeg, png, gif, webp, atau pdf.');
        }

        if ($file->getSize() > self::MAX_ATTACHMENT_BYTES) {
            throw new \InvalidArgumentException('Ukuran lampiran maksimal 5MB.');
        }

        $dir = public_path(self::ATTACHMENT_DIR);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'CS_' . $ticket->ticket_no . '_' . time() . '_' . Str::random(6) . '.' . $ext;
        $file->move($dir, $filename);

        return $filename;
    }

    public static function transformTicket(CsTicket $ticket, ?string $readerType = null, ?int $readerId = null): array
    {
        $payload = [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'customer_id' => $ticket->customer_id,
            'customer_name' => $ticket->customer_name,
            'created_by_user_id' => $ticket->created_by_user_id,
            'created_by_name' => $ticket->created_by_name,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to' => $ticket->assigned_to,
            'last_message_at' => $ticket->last_message_at,
            'last_message_preview' => $ticket->last_message_preview,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'closed_at' => $ticket->closed_at,
            'processed_at' => $ticket->processed_at,
            'processed_by' => $ticket->processed_by,
            'processed_by_name' => self::resolveStaffName($ticket->processed_by),
            'auto_close_at' => $ticket->auto_close_at,
            'archived_at' => $ticket->archived_at,
            'closed_by' => $ticket->closed_by,
            'is_closed' => self::isClosed($ticket->status),
            'can_customer_send' => self::canCustomerSend($ticket->status),
            'can_staff_send' => self::canStaffSend($ticket->status),
            'can_resume_process' => self::needsProcessResume($ticket),
        ];

        if ($readerType && $readerId) {
            $payload['unread_count'] = self::getUnreadCount($ticket->id, $readerType, $readerId);
        }

        return $payload;
    }

    protected static function buildPreview(?string $message, int $limit = 255): ?string
    {
        $text = trim((string) $message);
        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit, '…');
    }

    protected static function maybeReopenTicket(CsTicket $ticket): void
    {
        if ($ticket->status !== 'waiting_customer') {
            return;
        }

        $ticket->status = 'in_progress';
        $ticket->auto_close_at = null;
        $ticket->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $ticket->save();

        self::publishConversationUpdate($ticket->fresh(), null, [
            'type' => 'cs_ticket_status',
            'status' => 'in_progress',
        ]);
    }

    public static function buildConversationMeta(CsTicket $ticket, string $readerType = 'staff'): array
    {
        $canCompose = $readerType === 'staff'
            ? self::canStaffSend($ticket->status)
            : self::canCustomerSend($ticket->status);
        $processedByName = self::resolveStaffName($ticket->processed_by);
        $isClosed = self::isClosed($ticket->status);

        return [
            'is_closed' => $isClosed,
            'ticket_status' => $ticket->status,
            'can_compose' => $canCompose,
            'processed_by_name' => $processedByName,
            'auto_close_at' => $ticket->auto_close_at,
            'archived_at' => $ticket->archived_at,
            'isClosed' => $isClosed,
            'ticketStatus' => $ticket->status,
            'canCompose' => $canCompose,
            'processedByName' => $processedByName,
            'autoCloseAt' => $ticket->auto_close_at,
            'archivedAt' => $ticket->archived_at,
        ];
    }

    protected static function publishConversationUpdate(CsTicket $ticket, ?CsTicketMessage $message = null, array $extra = []): void
    {
        $formatted = $message
            ? self::formatMessage($message, 'customer', 0)
            : null;

        $payload = array_merge([
            'type' => 'cs_ticket_conversation',
            'ticket_id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'status' => $ticket->status,
            'conversation' => $formatted,
        ], self::buildConversationMeta($ticket, 'customer'), $extra);

        $ppiJob = new SendCustomerServiceConversationJob('/ppi/cs-ticket/' . $ticket->id, $payload);
        $ppiJob->handle();

        $v3Job = new SendCustomerServiceConversationJob('/v3/cs-ticket/' . $ticket->id, $payload);
        $v3Job->handle();
    }

    protected static function resolveInternalNotifyIds(): array
    {
        $csIds = collect(explode(',', (string) env('CS_NOTIFY_KARYAWAN_IDS', '')))
            ->map(fn ($item) => (int) trim($item))
            ->filter()
            ->values();

        if ($csIds->isEmpty()) {
            return [];
        }

        return $csIds->flatMap(function ($karyawanId) {
            return GetAtasan::where('id', $karyawanId)->get()->pluck('id');
        })->unique()->filter()->values()->all();
    }

    protected static function notifyCustomerTicketCreated(CsTicket $ticket): void
    {
        try {
            PpiNotification::where('id', $ticket->created_by_user_id)
                ->title('Ticket Customer Service Diterima')
                ->message("Ticket {$ticket->ticket_no} berhasil dibuat. Tim kami akan segera merespon.")
                ->url('/customer-service/' . $ticket->ticket_no)
                ->data([
                    'type' => 'customer_service',
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                ])
                ->send();
        } catch (\Throwable $exception) {
        }
    }

    protected static function notifyInternalNewTicket(CsTicket $ticket): void
    {
        $targets = self::resolveInternalNotifyIds();
        if (empty($targets)) {
            return;
        }

        try {
            Notification::whereIn('id', $targets)
                ->title('Ticket Customer Service Baru')
                ->message("Ticket baru {$ticket->ticket_no} dari {$ticket->customer_name}: {$ticket->subject}")
                ->url('/sales/customer-service?ticket=' . $ticket->ticket_no)
                ->send();
        } catch (\Throwable $exception) {
        }
    }

    protected static function notifyInternalCustomerReply(CsTicket $ticket): void
    {
        $targets = self::resolveInternalNotifyIds();
        if (empty($targets)) {
            return;
        }

        try {
            Notification::whereIn('id', $targets)
                ->title('Balasan Customer Service')
                ->message("Pelanggan membalas ticket {$ticket->ticket_no}")
                ->url('/sales/customer-service?ticket=' . $ticket->ticket_no)
                ->send();
        } catch (\Throwable $exception) {
        }
    }

    protected static function notifyCustomerStaffReply(CsTicket $ticket, CsTicketMessage $message): void
    {
        try {
            PpiNotification::where('id', $ticket->created_by_user_id)
                ->title('Balasan Customer Service')
                ->message("Tim kami membalas ticket {$ticket->ticket_no}")
                ->url('/customer-service/' . $ticket->ticket_no)
                ->data([
                    'type' => 'customer_service',
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'conversation' => self::formatMessage($message, 'customer', (int) $ticket->created_by_user_id),
                ])
                ->send();
        } catch (\Throwable $exception) {
        }
    }

    protected static function notifyCustomerStatusChange(CsTicket $ticket, string $status): void
    {
        $label = $status === 'closed' ? 'ditutup' : 'diperbarui';

        try {
            PpiNotification::where('id', $ticket->created_by_user_id)
                ->title('Update Ticket Customer Service')
                ->message("Ticket {$ticket->ticket_no} telah {$label}")
                ->url('/customer-service/' . $ticket->ticket_no)
                ->data([
                    'type' => 'customer_service',
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'status' => $status,
                ])
                ->send();
        } catch (\Throwable $exception) {
        }
    }

    public static function resolveStaffName(?int $staffId): ?string
    {
        if (!$staffId) {
            return null;
        }

        return MasterKaryawan::where('id', $staffId)->value('nama_lengkap');
    }
}
