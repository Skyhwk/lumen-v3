<?php

namespace App\Http\Controllers\api;

use App\Models\TicketProgramming;
use App\Models\MasterKaryawan;
use App\Models\AksesMenu;
use App\Models\TicketProgrammingConversation;
// use App\Models\User;
use App\Http\Controllers\Controller;
use App\Services\GetAtasan;
use App\Services\TicketProgrammingConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use App\Services\Notification;
use App\Services\GetBawahan;

class TicketProgrammingController extends Controller
{
    private const PENDING_SOLVE_LIMIT = 10;

    private function countPendingSolveTicketsForUser(): int
    {
        return TicketProgramming::where('created_by', $this->karyawan)
            ->where('status', 'SOLVE')
            ->where('is_active', true)
            ->count();
    }

    private function pendingSolveBlockedMessage(): string
    {
        return 'Terdapat lebih dari 10 ticket belum diselesaikan. Apakah ticket sudah selesai atau belum? '
            . 'Silahkan update terlebih dahulu melalui cara klik View, pastikan kondisi sudah Solve dan klik Done '
            . 'maka ticket akan dianggap selesai.';
    }

    public function checkPendingSolveTickets(Request $request)
    {
        try {
            $count = $this->countPendingSolveTicketsForUser();

            return response()->json([
                'success' => true,
                'count' => $count,
                'limit' => self::PENDING_SOLVE_LIMIT,
                'is_blocked' => $count > self::PENDING_SOLVE_LIMIT,
                'message' => $count > self::PENDING_SOLVE_LIMIT
                    ? $this->pendingSolveBlockedMessage()
                    : null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $department = $request->attributes->get('user')->karyawan->id_department;
            if ($department == 7 && !in_array($this->user_id, [10, 15, 93, 123])) {
                $data = TicketProgramming::where('is_active', true)
                    ->orderBy('id', 'desc');
                return Datatables::of($data)
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_programming/' . $row->filename);
                        if (file_exists($filePath) && is_file($filePath)) {
                            return file_get_contents($filePath);
                        } else {
                            return 'File not found';
                        }
                    })
                    ->addColumn('unread_count', function ($row) {
                        return TicketProgrammingConversationService::getUnreadCount($row->id, $this->user_id);
                    })
                    ->make(true);
            } else {

                $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
                $data = TicketProgramming::whereIn('request_by', $getBawahan)
                    ->where('is_active', true)
                    ->orderBy('id', 'desc');

                return Datatables::of($data)
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_programming/' . $row->filename);
                        if (file_exists($filePath) && is_file($filePath)) {
                            return file_get_contents($filePath);
                        } else {
                            return 'File not found';
                        }
                    })
                    ->addColumn('can_approve', function ($row) use ($getBawahan) {
                        // comment
                        return in_array($row->created_by, $getBawahan) && $this->karyawan != $row->created_by;
                    })
                    ->addColumn('unread_count', function ($row) {
                        return TicketProgrammingConversationService::getUnreadCount($row->id, $this->user_id);
                    })
                    ->make(true);
            }
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function getAllMenu(Request $request)
    {
        try {
            $masterKaryawan = MasterKaryawan::where('id', $this->user_id)->first();
            $menu = AksesMenu::select('akses')->where('user_id', $masterKaryawan->user_id)->first();
            if ($menu && is_string($menu->akses)) {
                $menus = json_decode($menu->akses, true);
            } else if ($menu && is_array($menu->akses)) {
                $menus = $menu->akses;
            } else {
                return response()->json([
                    'data' => [],
                    'message' => 'Invalid format for akses data.',
                ], 400);
            }
            if (is_array($menus)) {
                $allMenu = array_map(function ($item) {
                    return ['name' => $item['name']];
                }, $menus);

                return response()->json([
                    'success' => true,
                    'data' => $allMenu,
                    'message' => 'Available Menu data retrieved successfully',
                ], 200);
            } else {
                return response()->json([
                    'data' => [],
                    'message' => 'Data tidak sesuai format array.',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function void(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'VOID';
            $data->void_by = $this->karyawan;
            $data->void_time = Carbon::now();
            $data->void_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Programming telah di void';

            $data->save();

            if ($this->karyawan == $data->created_by) {
                $user_programmer = MasterKaryawan::where('id_department', 7)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/request/ticket-programming')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/request/ticket-programming')
                    ->send();
            }

            TicketProgrammingConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses void Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done By Client
    public function approve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'DONE';
            $data->done_by = $this->karyawan;
            $data->done_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Programming telah dinyatakan selesai';

            $data->save();

            $user_programming = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programming)
                ->title('Ticket Programming Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/ticket-programming')
                ->send();

            TicketProgrammingConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses menyelesaikan Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done by Worker
    public function solve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'SOLVE';
            $data->solve_by = $this->karyawan;
            $data->solve_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Programming dinyatakan selesai';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Programming Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/ticket-programming')
                ->send();

            TicketProgrammingConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses solve Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    // Reject by Worker
    public function reject(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'REJECT';
            $data->rejected_by = $this->karyawan;
            $data->rejected_time = Carbon::now();
            $data->rejected_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Programming telah di reject';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Programming Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/ticket-programming')
                ->send();

            TicketProgrammingConversationService::notifyConversationClosed($data, $this->karyawan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    public function reOpen(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'REOPEN';
            $data->reopened_by = $this->karyawan;
            $data->reopened_time = Carbon::now();
            $data->reopened_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Programming telah di re-open';

            $data->save();

            Notification::where('nama_lengkap', $data->solve_by)
                ->title('Ticket Programming Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/ticket-programming')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    // Pending by Worker
    public function pending(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'PENDING';
            $data->pending_by = $this->karyawan;
            $data->pending_time = Carbon::now();
            $data->pending_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Programming telah di pending';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Programming Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/ticket-programming')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses pending Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    // Process by Worker
    public function process(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->status = 'PROCESS';
            $data->process_by = $this->karyawan;
            $data->process_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Programming sedang di process';

            $data->save();

            DB::commit();

            if ($this->karyawan == $data->created_by) {
                $user_programmer = MasterKaryawan::where('id_department', 7)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/request/ticket-programming')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/request/ticket-programming')
                    ->send();
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses process Ticket Programming: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if (empty($request->id)) {
                $pendingSolveCount = $this->countPendingSolveTicketsForUser();
                if ($pendingSolveCount > self::PENDING_SOLVE_LIMIT) {
                    return response()->json([
                        'success' => false,
                        'message' => $this->pendingSolveBlockedMessage(),
                        'count' => $pendingSolveCount,
                        'limit' => self::PENDING_SOLVE_LIMIT,
                    ], 422);
                }

                $data = new TicketProgramming();
                $data->request_by = $this->karyawan;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->request_time = Carbon::now();

                $microtime = str_replace(".", "", microtime(true));
                $uniq_id = $microtime;
                $filename = $microtime . '.txt';
                $content = $request->details;
                $contentDir = 'ticket_programming';

                if (!file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                file_put_contents(public_path($contentDir . '/' . $filename), $content);

                if ($request->hasFile('dokumentasi')) {
                    $dir_dokumentasi = "ticket";

                    if (!file_exists(public_path($dir_dokumentasi))) {
                        mkdir(public_path($dir_dokumentasi), 0777, true);
                    }

                    $file = $request->file('dokumentasi');
                    $extTicket = $file->getClientOriginalExtension();
                    $filenameDok = "PROGRAMMING_" . $uniq_id . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);

                    $data->dokumentasi = $filenameDok;
                } else {
                    $data->dokumentasi = null;
                }

                $data->nama_menu = $request->nama_menu;
                $data->nomor_ticket = $uniq_id;
                $data->filename = $filename;
                $message = 'Ticket Programming Telah Ditambahkan';
            } else {
                $data = TicketProgramming::find($request->id);
                if (!$data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket Programming tidak ditemukan'
                    ], 404);
                }

                if ($data->created_by !== $this->karyawan) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya pembuat ticket yang dapat mengubah ticket ini',
                    ], 403);
                }

                if ($data->status !== 'WAITING PROCESS') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket tidak dapat diubah karena sudah diproses',
                    ], 422);
                }

                if ($request->hasFile('dokumentasi')) {
                    $dir_dokumentasi = "ticket";

                    if (!file_exists(public_path($dir_dokumentasi))) {
                        mkdir(public_path($dir_dokumentasi), 0777, true);
                    }

                    $file = $request->file('dokumentasi');
                    $extTicket = $file->getClientOriginalExtension();
                    $filenameDok = "PROGRAMMING_" . $data->nomor_ticket . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);
                } else {
                    $data->dokumentasi = null;
                }

                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();

                $contentDir = 'ticket_programming';
                if (!file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                $content = $request->details;
                file_put_contents(public_path($contentDir . '/' . $data->filename), $content);
                $message = 'Ticket Programming Telah Diperbarui';
            }

            $data->status = 'WAITING PROCESS';
            $data->kategori = $request->kategori;

            if($this->grade == 'MANAGER' && $data->kategori == 'PERUBAHAN_DATA') {
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            $data->save();

            // if (empty($request->id)) {
            //     TicketProgrammingConversationService::createMessage(
            //         $data,
            //         $this->user_id,
            //         $this->karyawan,
            //         TicketProgrammingConversationService::resolveSenderRole($this->department),
            //         $request->details
            //     );
            // }

            $user_programmer = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programmer)
                ->title('Ticket Programming !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . str_replace('_', ' ', $data->kategori))
                ->url('/request/ticket-programming')
                ->send();

            $getAtasan = GetAtasan::where('nama_lengkap', $this->karyawan)->get()->pluck('id');

            $isPerubahanData = $data->kategori == 'PERUBAHAN_DATA';
            Notification::whereIn('id', $getAtasan)
                ->title('Ticket Programming !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . str_replace('_', ' ', $data->category) . ($isPerubahanData ? ' Yang Harus Disetujui Oleh Atasan' : ''))
                ->url('/request/ticket-programming')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses Ticket Programming: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveTicket(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketProgramming::find($request->id);
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');

            $data->save();

            $message = 'Ticket Programming telah diapprove oleh ' . $this->karyawan .' dan siap untuk diproses oleh tim IT';

            $user_programmer = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programmer)
                ->title('Ticket Programming Siap Diproses!')
                ->message($message)
                ->url('/request/ticket-programming')
                ->send();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Programming Update')
                ->message($message)
                ->url('/request/ticket-programming')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses Ticket Programming: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getConversations(Request $request)
    {
        try {
            $ticket = TicketProgramming::find($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            // $existingCount = TicketProgrammingConversation::where('ticket_programming_id', $ticket->id)->count();
            // if ($existingCount === 0) {
            //     $filePath = public_path('ticket_programming/' . $ticket->filename);
            //     if (file_exists($filePath) && is_file($filePath)) {
            //         $initialContent = file_get_contents($filePath);
            //         if (!empty(trim(strip_tags($initialContent)))) {
            //             TicketProgrammingConversation::create([
            //                 'ticket_programming_id' => $ticket->id,
            //                 'sender_id' => null,
            //                 'sender_name' => $ticket->request_by,
            //                 'sender_role' => 'requester',
            //                 'message' => $initialContent,
            //                 'created_at' => $ticket->request_time ?? Carbon::now()->format('Y-m-d H:i:s'),
            //             ]);
            //         }
            //     }
            // }

            $conversations = TicketProgrammingConversation::where('ticket_programming_id', $ticket->id)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($item) {
                    return TicketProgrammingConversationService::formatConversation($item, $this->user_id);
                });

            return response()->json([
                'success' => true,
                'data' => $conversations,
                'is_closed' => TicketProgrammingConversationService::isConversationClosed($ticket->status),
                'is_open' => TicketProgrammingConversationService::isConversationOpen($ticket->status),
                'message' => 'Conversation berhasil diambil',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendConversation(Request $request)
    {
        DB::beginTransaction();
        try {
            $message = trim($request->message ?? '');
            $hasAttachment = $request->hasFile('attachment');

            if (empty($request->ticket_id) || (empty($message) && !$hasAttachment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket ID dan pesan atau lampiran wajib diisi',
                ], 422);
            }

            $ticket = TicketProgramming::find($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            if (TicketProgrammingConversationService::isConversationClosed($ticket->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket sudah ditutup, tidak dapat mengirim pesan',
                ], 422);
            }

            if (!TicketProgrammingConversationService::isConversationOpen($ticket->status)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation belum dibuka. Ticket harus diproses terlebih dahulu',
                ], 422);
            }

            $attachmentFilename = null;
            if ($hasAttachment) {
                $file = $request->file('attachment');
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower($file->getClientOriginalExtension());

                if (!in_array($ext, $allowedExt)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Format lampiran harus jpg, jpeg, png, gif, atau webp',
                    ], 422);
                }

                if ($file->getSize() > 2097152) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ukuran lampiran maksimal 2MB',
                    ], 422);
                }

                $dir = 'ticket_programming/conversation';
                if (!file_exists(public_path($dir))) {
                    mkdir(public_path($dir), 0777, true);
                }

                $attachmentFilename = 'CONV_' . $ticket->nomor_ticket . '_' . time() . '.' . $ext;
                $file->move(public_path($dir), $attachmentFilename);
            }

            $conversation = TicketProgrammingConversationService::createMessage(
                $ticket,
                $this->user_id,
                $this->karyawan,
                TicketProgrammingConversationService::resolveSenderRole($this->department),
                $message ?: '[Lampiran gambar]',
                $attachmentFilename
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => TicketProgrammingConversationService::formatConversation($conversation, $this->user_id),
                'message' => 'Pesan berhasil dikirim',
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim pesan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markConversationRead(Request $request)
    {
        try {
            if (empty($request->ticket_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket ID wajib diisi',
                ], 422);
            }

            $ticket = TicketProgramming::find($request->ticket_id);
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan',
                ], 404);
            }

            TicketProgrammingConversationService::markAsRead($request->ticket_id, $this->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Conversation ditandai sudah dibaca',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
