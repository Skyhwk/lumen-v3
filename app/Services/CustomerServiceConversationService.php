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
    public const BOT_MESSAGE = 'Balasan pesan otomatis, tim kami akan segera merespon Anda.';
    public const CLOSED_STATUSES = ['closed'];
    public const ATTACHMENT_DIR = 'cs_tickets/conversation';
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    public const MAX_ATTACHMENT_BYTES = 5242880; // 5MB

    public static function isClosed(?string $status): bool
    {
        return in_array($status, self::CLOSED_STATUSES, true);
    }

    public static function canCustomerSend(?string $status): bool
    {
        return !self::isClosed($status);
    }

    public static function canStaffSend(?string $status): bool
    {
        return !self::isClosed($status);
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
            'message' => self::BOT_MESSAGE,
            'attachment' => null,
        ], false);
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
        ], true);

        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
            $ticket->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $ticket->save();
        }

        self::notifyCustomerStaffReply($ticket, $conversation);

        return $conversation;
    }

    public static function insertMessage(CsTicket $ticket, array $data, bool $publishMqtt): CsTicketMessage
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $message = CsTicketMessage::create(array_merge($data, [
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
                ? 'cs-tickets/' . $item->attachment
                : null,
            'created_at' => $item->created_at,
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

    public static function closeTicket(CsTicket $ticket, ?string $closedByName = null): CsTicket
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $ticket->status = 'closed';
        $ticket->closed_at = $now;
        $ticket->updated_at = $now;
        $ticket->save();

        self::publishConversationUpdate($ticket, null, [
            'type' => 'cs_ticket_closed',
            'closed_by' => $closedByName,
        ]);

        self::notifyCustomerStatusChange($ticket, 'closed');

        return $ticket->fresh();
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

        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
        }

        $ticket->save();

        return $ticket->fresh();
    }

    public static function storeAttachment(CsTicket $ticket, ?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension());
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
            'is_closed' => self::isClosed($ticket->status),
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
        if (!in_array($ticket->status, ['resolved', 'waiting_customer'], true)) {
            return;
        }

        $ticket->status = 'open';
        $ticket->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $ticket->save();
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
        ], $extra);

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
