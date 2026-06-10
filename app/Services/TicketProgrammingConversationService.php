<?php

namespace App\Services;

use App\Jobs\SendTicketProgrammingConversationJob;
use App\Models\MasterKaryawan;
use App\Models\TicketProgramming;
use App\Models\TicketProgrammingConversation;
use App\Models\TicketProgrammingConversationRead;
use Carbon\Carbon;

class TicketProgrammingConversationService
{
    const IT_DEPARTMENT_ID = 7;
    const EXCLUDED_IT_USERS = [10, 15, 93, 123];

    public static function resolveSenderRole($departmentId)
    {
        return (int) $departmentId === self::IT_DEPARTMENT_ID ? 'admin' : 'requester';
    }

    public static function isConversationClosed($status)
    {
        return in_array($status, ['VOID', 'DONE', 'REJECT']);
    }

    public static function isConversationOpen($status)
    {
        return !self::isConversationClosed($status) && !in_array($status, ['WAITING PROCESS']);
    }

    public static function createMessage($ticket, $senderId, $senderName, $senderRole, $message, $attachment = null)
    {
        $conversation = TicketProgrammingConversation::create([
            'ticket_programming_id' => $ticket->id,
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

    public static function formatConversation(TicketProgrammingConversation $item, $currentUserId)
    {
        return [
            'id' => $item->id,
            'ticket_programming_id' => $item->ticket_programming_id,
            'sender_id' => $item->sender_id,
            'sender_name' => $item->sender_name,
            'sender_role' => $item->sender_role,
            'message' => $item->message,
            'attachment' => $item->attachment,
            'attachment_url' => $item->attachment
                ? 'ticket_programming/conversation/' . $item->attachment
                : null,
            'created_at' => $item->created_at,
            'is_own' => (int) $item->sender_id === (int) $currentUserId,
        ];
    }

    public static function getUnreadCount($ticketId, $userId)
    {
        $lastReadId = TicketProgrammingConversationRead::where('ticket_programming_id', $ticketId)
            ->where('user_id', $userId)
            ->value('last_read_message_id') ?? 0;

        return TicketProgrammingConversation::where('ticket_programming_id', $ticketId)
            ->where('id', '>', $lastReadId)
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', '!=', $userId)
                    ->orWhereNull('sender_id');
            })
            ->count();
    }

    public static function markAsRead($ticketId, $userId)
    {
        $latestMessageId = TicketProgrammingConversation::where('ticket_programming_id', $ticketId)
            ->max('id') ?? 0;

        $read = TicketProgrammingConversationRead::where('ticket_programming_id', $ticketId)
            ->where('user_id', $userId)
            ->first();

        if ($read) {
            $read->last_read_message_id = $latestMessageId;
            $read->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $read->save();
        } else {
            TicketProgrammingConversationRead::create([
                'ticket_programming_id' => $ticketId,
                'user_id' => $userId,
                'last_read_message_id' => $latestMessageId,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public static function getParticipantIds(TicketProgramming $ticket)
    {
        $participants = [];

        $creator = MasterKaryawan::where('nama_lengkap', $ticket->created_by)
            ->where('is_active', true)
            ->value('id');
        if ($creator) {
            $participants[] = $creator;
        }

        $itUsers = MasterKaryawan::where('id_department', self::IT_DEPARTMENT_ID)
            ->whereNotIn('id', self::EXCLUDED_IT_USERS)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        return array_values(array_unique(array_merge($participants, $itUsers)));
    }

    public static function notifyParticipants(TicketProgramming $ticket, TicketProgrammingConversation $conversation, $excludeUserId = null)
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

        $payload = [
            'title' => 'Ticket Programming',
            'message' => 'New Conversation on Ticket #' . $ticket->nomor_ticket,
            'url' => '/request/ticket-programming',
            'type' => 'ticket_programming_conversation',
            'ticket_id' => $ticket->id,
            'conversation' => $formatted,
        ];

        Notification::whereIn('id', $participantIds)
            ->title('Ticket Programming')
            ->message('New Conversation on Ticket #' . $ticket->nomor_ticket)
            ->url('/request/ticket-programming')
            ->send();

        $job = new SendTicketProgrammingConversationJob([
            'data' => $payload,
            'users' => $users,
        ]);

        $job->handle();
    }

    public static function notifyConversationClosed(TicketProgramming $ticket, $closedBy)
    {
        $participantIds = self::getParticipantIds($ticket);
        if (empty($participantIds)) {
            return;
        }

        $users = MasterKaryawan::whereIn('id', $participantIds)
            ->where('is_active', true)
            ->get(['id']);

        $payload = [
            'title' => 'Ticket Programming',
            'message' => 'Conversation closed on Ticket #' . $ticket->nomor_ticket,
            'url' => '/request/ticket-programming',
            'type' => 'ticket_programming_conversation_closed',
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'closed_by' => $closedBy,
        ];

        Notification::whereIn('id', $participantIds)
            ->title('Ticket Programming')
            ->message('Conversation ditutup pada Ticket #' . $ticket->nomor_ticket . ' oleh ' . $closedBy)
            ->url('/request/ticket-programming')
            ->send();

        $job = new SendTicketProgrammingConversationJob([
            'data' => $payload,
            'users' => $users,
        ]);

        $job->handle();
    }
}
