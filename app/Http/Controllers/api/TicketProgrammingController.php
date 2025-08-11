<?php

namespace App\Http\Controllers\api;

use App\Models\TicketProgramming;
use App\Models\MasterKaryawan;
use App\Models\AksesMenu;
// use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use App\Services\Notification;
use App\Services\GetBawahan;

class TicketProgrammingController extends Controller
{
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
            $menu = AksesMenu::select('akses')->where('user_id', $this->user_id)->first();
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
                    ->url('/ticket-programming')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            }

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
                ->url('/ticket-programming')
                ->send();

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
                ->url('/ticket-programming')
                ->send();

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
                ->url('/ticket-programming')
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
                ->url('/ticket-programming')
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
                $user_programmer = where('id_department', 7)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses process Ticket Programming: ' . $th->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if (empty($request->id)) {
                $data = new TicketProgramming();
                $data->request_by = $this->karyawan;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->request_time = Carbon::now();

                $microtime = str_replace(".", "", microtime(true));
                $uniq_id = $microtime;
                $filename = $microtime . '.txt';
                $content = $request->details;
                file_put_contents(public_path('ticket_programming/' . $filename), $content);

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
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();

                $content = $request->details;
                file_put_contents(public_path('ticket_programming/' . $data->filename), $content);
                $message = 'Ticket Programming Telah Diperbarui';
            }

            $data->status = 'WAITING PROCESS';
            $data->tingkat_masalah = $request->tingkat_masalah;

            $data->save();

            $user_programmer = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programmer)
                ->title('Ticket Programming !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . $data->tingkat_masalah)
                ->url('/ticket-programming')
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

}