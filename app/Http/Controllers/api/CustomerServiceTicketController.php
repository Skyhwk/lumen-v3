<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\customer\CsTicket;
use App\Models\customer\CsTicketMessage;
use App\Models\MasterKaryawan;
use App\Services\CustomerServiceConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class CustomerServiceTicketController extends Controller
{
    protected function findActiveTicket($ticketId): ?CsTicket
    {
        if (!$ticketId) {
            return null;
        }

        return CsTicket::where('is_active', true)->where('id', $ticketId)->first();
    }

    public function index(Request $request)
    {
        try {
            $query = CsTicket::where('is_active', true)
                ->orderByDesc('last_message_at')
                ->orderByDesc('id');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('assigned_to')) {
                $query->where('assigned_to', (int) $request->assigned_to);
            }

            return Datatables::of($query)
                ->addColumn('assigned_to_name', function ($row) {
                    return CustomerServiceConversationService::resolveStaffName($row->assigned_to) ?? '-';
                })
                ->addColumn('unread_count', function ($row) {
                    return CustomerServiceConversationService::getUnreadCount(
                        $row->id,
                        'staff',
                        (int) $this->user_id
                    );
                })
                ->make(true);
        } catch (\Throwable $exception) {
            return response()->json([
                'data' => [],
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $ticketId = $request->input('ticket_id') ?? $request->input('id');
            $ticketNo = $request->input('ticket_no');

            $query = CsTicket::where('is_active', true);
            if ($ticketId) {
                $query->where('id', $ticketId);
            } elseif ($ticketNo) {
                $query->where('ticket_no', $ticketNo);
            } else {
                return response()->json(['message' => 'Ticket ID atau ticket_no wajib diisi', 'status' => 422], 422);
            }

            $ticket = $query->first();
            if (!$ticket) {
                return response()->json(['message' => 'Ticket tidak ditemukan', 'status' => 404], 404);
            }

            $payload = CustomerServiceConversationService::transformTicket($ticket, 'staff', (int) $this->user_id);
            $payload['assigned_to_name'] = CustomerServiceConversationService::resolveStaffName($ticket->assigned_to);

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mendapatkan detail ticket',
                'data' => $payload,
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'status' => 500], 500);
        }
    }

    public function assign(Request $request)
    {
        try {
            $ticket = $this->findActiveTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json(['message' => 'Ticket tidak ditemukan', 'status' => 404], 404);
            }

            $assignedTo = $request->filled('assigned_to') ? (int) $request->assigned_to : null;
            if ($assignedTo) {
                $exists = MasterKaryawan::where('id', $assignedTo)->where('is_active', 1)->exists();
                if (!$exists) {
                    return response()->json(['message' => 'Karyawan tidak valid', 'status' => 422], 422);
                }
            }

            $updated = CustomerServiceConversationService::assignTicket($ticket, $assignedTo);
            $payload = CustomerServiceConversationService::transformTicket($updated, 'staff', (int) $this->user_id);
            $payload['assigned_to_name'] = CustomerServiceConversationService::resolveStaffName($updated->assigned_to);

            return response()->json([
                'status' => 200,
                'message' => 'Ticket berhasil di-assign',
                'data' => $payload,
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'status' => 500], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            $validStatuses = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
            $status = $request->input('status');

            if (!$status || !in_array($status, $validStatuses, true)) {
                return response()->json(['message' => 'Status tidak valid', 'status' => 422], 422);
            }

            $ticket = $this->findActiveTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json(['message' => 'Ticket tidak ditemukan', 'status' => 404], 404);
            }

            $updated = CustomerServiceConversationService::updateTicketStatus($ticket, $status);
            $payload = CustomerServiceConversationService::transformTicket($updated, 'staff', (int) $this->user_id);
            $payload['assigned_to_name'] = CustomerServiceConversationService::resolveStaffName($updated->assigned_to);

            return response()->json([
                'status' => 200,
                'message' => 'Status ticket berhasil diperbarui',
                'data' => $payload,
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'status' => 500], 500);
        }
    }

    public function getConversations(Request $request)
    {
        try {
            $ticket = $this->findActiveTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket tidak ditemukan'], 404);
            }

            $conversations = CsTicketMessage::where('ticket_id', $ticket->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($item) => CustomerServiceConversationService::formatMessage(
                    $item,
                    'staff',
                    (int) $this->user_id
                ));

            return response()->json([
                'success' => true,
                'data' => $conversations,
                'is_closed' => CustomerServiceConversationService::isClosed($ticket->status),
                'ticket_status' => $ticket->status,
                'message' => 'Conversation berhasil diambil',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function sendConversation(Request $request)
    {
        DB::beginTransaction();
        try {
            $message = trim($request->message ?? '');
            $hasAttachment = $request->hasFile('attachment');

            if (!$request->ticket_id || ($message === '' && !$hasAttachment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket ID dan pesan atau lampiran wajib diisi',
                ], 422);
            }

            $ticket = $this->findActiveTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket tidak ditemukan'], 404);
            }

            if (!CustomerServiceConversationService::canStaffSend($ticket->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket sudah ditutup, tidak dapat mengirim pesan',
                ], 422);
            }

            $conversation = CustomerServiceConversationService::createStaffMessage(
                $ticket->fresh(),
                (int) $this->user_id,
                $this->karyawan ?? 'Staff',
                $message,
                $request->file('attachment')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => CustomerServiceConversationService::formatMessage(
                    $conversation,
                    'staff',
                    (int) $this->user_id
                ),
                'message' => 'Pesan berhasil dikirim',
            ]);
        } catch (\InvalidArgumentException $exception) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function markConversationRead(Request $request)
    {
        try {
            if (!$request->ticket_id) {
                return response()->json(['success' => false, 'message' => 'Ticket ID wajib diisi'], 422);
            }

            $ticket = $this->findActiveTicket($request->ticket_id);
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket tidak ditemukan'], 404);
            }

            CustomerServiceConversationService::markAsRead($ticket->id, 'staff', (int) $this->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Conversation ditandai sudah dibaca',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function summary()
    {
        try {
            $base = CsTicket::where('is_active', true);

            return response()->json([
                'status' => 200,
                'data' => [
                    'open' => (clone $base)->where('status', 'open')->count(),
                    'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                    'waiting_customer' => (clone $base)->where('status', 'waiting_customer')->count(),
                    'resolved' => (clone $base)->where('status', 'resolved')->count(),
                    'closed' => (clone $base)->where('status', 'closed')->count(),
                    'total' => (clone $base)->count(),
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'status' => 500], 500);
        }
    }

    public function getStaffOptions()
    {
        try {
            $ids = collect(explode(',', (string) env('CS_NOTIFY_KARYAWAN_IDS', '')))
                ->map(fn ($item) => (int) trim($item))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $query = MasterKaryawan::where('is_active', 1)->orderBy('nama_lengkap');
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }

            $items = $query->get(['id', 'nama_lengkap'])->map(fn ($row) => [
                'id' => $row->id,
                'nama_lengkap' => $row->nama_lengkap,
            ]);

            return response()->json([
                'status' => 200,
                'data' => $items,
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'status' => 500], 500);
        }
    }
}
