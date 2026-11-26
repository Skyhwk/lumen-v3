<?php

namespace App\Services;

use App\Jobs\SendTicketProgrammingConversationJob;
use App\Models\MasterKaryawan;
use App\Models\TicketSkK3;
use App\Models\TicketSkK3Conversation;
use App\Models\TicketSkK3ConversationRead;
use Carbon\Carbon;

class TicketSkK3ConversationService
{
    const K3_DEPARTMENT_ID = 19;
    const IT_DEPARTMENT_ID = 7;

    public static function resolveSenderRole($departmentId)
    {
        $dept = (int) $departmentId;
        return in_array($dept, [self::K3_DEPARTMENT_ID, self::IT_DEPARTMENT_ID], true) ? 'admin' : 'requester';
    }

    public static function isConversationClosed($status)
    {
        return in_array($status, ['VOID', 'REJECT', 'RELEASE DOKUMEN'], true);
    }

    public static function isConversationOpen($status)
    {
        return !self::isConversationClosed($status)
            && !in_array($status, ['WAITING APPROVE', 'WAITING PROCESS'], true);
    }

    public static function createMessage($ticket, $senderId, $senderName, $senderRole, $message, $attachment = null)
    {
        $conversation = TicketSkK3Conversation::create([
            'ticket_sk_k3_id' => $ticket->id,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_role' => $senderRole,
            'message' => $message,
            'attachment' => $attachment,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        self::notifyParticipants($ticket, $conversation, $senderId);

        return $conversation;
    }

    public static function formatConversation(TicketSkK3Conversation $item, $currentUserId)
    {
        return [
            'id' => $item->id,
            'ticket_sk_k3_id' => $item->ticket_sk_k3_id,
            'sender_id' => $item->sender_id,
            'sender_name' => $item->sender_name,
            'sender_role' => $item->sender_role,
            'message' => $item->message,
            'attachment' => $item->attachment,
            'attachment_url' => $item->attachment
                ? 'ticket_sk_k3/conversation/' . $item->attachment
                : null,
            'created_at' => $item->created_at,
            'is_own' => (int) $item->sender_id === (int) $currentUserId,
        ];
    }

    public static function getUnreadCount($ticketId, $userId)
    {
        $lastReadId = TicketSkK3ConversationRead::where('ticket_sk_k3_id', $ticketId)
            ->where('user_id', $userId)
            ->value('last_read_message_id') ?? 0;

        return TicketSkK3Conversation::where('ticket_sk_k3_id', $ticketId)
            ->where('id', '>', $lastReadId)
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', '!=', $userId)
                    ->orWhereNull('sender_id');
            })
            ->count();
    }

    public static function markAsRead($ticketId, $userId)
    {
        $latestMessageId = TicketSkK3Conversation::where('ticket_sk_k3_id', $ticketId)
            ->max('id') ?? 0;

        $read = TicketSkK3ConversationRead::where('ticket_sk_k3_id', $ticketId)
            ->where('user_id', $userId)
            ->first();

        if ($read) {
            $read->last_read_message_id = $latestMessageId;
            $read->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $read->save();
            return;
        }

        TicketSkK3ConversationRead::create([
            'ticket_sk_k3_id' => $ticketId,
            'user_id' => $userId,
            'last_read_message_id' => $latestMessageId,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    public static function getParticipantIds(TicketSkK3 $ticket)
    {
        $participants = [];

        $creator = MasterKaryawan::where('nama_lengkap', $ticket->created_by)
            ->where('is_active', true)
            ->value('id');
        if ($creator) {
            $participants[] = $creator;
        }

        $k3Users = MasterKaryawan::where('id_department', self::K3_DEPARTMENT_ID)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        return array_values(array_unique(array_merge($participants, $k3Users)));
    }

    public static function notifyParticipants(TicketSkK3 $ticket, TicketSkK3Conversation $conversation, $excludeUserId = null)
    {
        $participantIds = self::getParticipantIds($ticket);

        if ($excludeUserId) {
            $participantIds = array_values(array_filter(
                $participantIds,
                fn ($id) => (int) $id !== (int) $excludeUserId
            ));
        }

        if (empty($participantIds)) {
            return;
        }

        $users = MasterKaryawan::whereIn('id', $participantIds)
            ->where('is_active', true)
            ->get(['id']);

        $formatted = self::formatConversation($conversation, 0);
        $formatted['is_own'] = false;

        $job = new SendTicketProgrammingConversationJob([
            'data' => [
                'type' => 'ticket_sk_k3_conversation',
                'ticket_id' => $ticket->id,
                'conversation' => $formatted,
            ],
            'users' => $users,
        ]);
        $job->handle();
    }

    public static function notifyConversationClosed(TicketSkK3 $ticket, $closedBy)
    {
        $participantIds = self::getParticipantIds($ticket);
        if (empty($participantIds)) {
            return;
        }

        $users = MasterKaryawan::whereIn('id', $participantIds)
            ->where('is_active', true)
            ->get(['id']);

        $job = new SendTicketProgrammingConversationJob([
            'data' => [
                'type' => 'ticket_sk_k3_conversation_closed',
                'ticket_id' => $ticket->id,
                'status' => $ticket->status,
                'closed_by' => $closedBy,
            ],
            'users' => $users,
        ]);
        $job->handle();
    }
}
