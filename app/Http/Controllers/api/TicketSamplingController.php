<?php

namespace App\Http\Controllers\api;

use App\Models\TicketSampling;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\GetAtasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use App\Services\Notification;
use App\Services\GetBawahan;

class TicketSamplingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $department = $request->attributes->get('user')->karyawan->id_department;
            if ($department == 14 && !in_array($this->user_id, [10, 15, 93, 123])) {
                $data = TicketSampling::where('is_active', true)
                    ->orderBy('id', 'desc');
                return Datatables::of($data)
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_sampling/' . $row->filename);
                        if (file_exists($filePath) && is_file($filePath)) {
                            return file_get_contents($filePath);
                        } else {
                            return 'File not found';
                        }
                    })
                    ->make(true);
            } else {

                $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
                $data = TicketSampling::whereIn('request_by', $getBawahan)
                    ->where('is_active', true)
                    ->orderBy('id', 'desc');

                return Datatables::of($data)
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_sampling/' . $row->filename);
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
        $allType = [
            "perubahan Jadwal",
            "perubahan Team",
            "perubahan Kategori",
            "Split Jadwal"
        ];

        return response()->json([
            'success' => true,
            'data' => $allType,
            'message' => 'Available type data retrieved successfully',
        ], 200);
    }


    public function void(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'VOID';
            $data->void_by = $this->karyawan;
            $data->void_time = Carbon::now();
            $data->void_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Sampling telah di void';

            $data->save();

            if ($this->karyawan == $data->created_by) {
                $user_programmer = MasterKaryawan::where('id_department', 7)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket Sampling Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-sampling')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Sampling Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-sampling')
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
                'message' => 'Gagal Proses void Ticket Sampling: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done By Client
    public function approve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'DONE';
            $data->done_by = $this->karyawan;
            $data->done_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Sampling telah dinyatakan selesai';

            $data->save();

            // $user_programming = MasterKaryawan::where('id_department', 7)
            //     ->whereNotIn('id', [10, 15, 93, 123])
            //     ->where('is_active', true)
            //     ->pluck('id')
            //     ->toArray();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Sampling Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-sampling')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses menyelesaikan Ticket Sampling: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done by Worker
    public function solve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'SOLVE';
            $data->solve_by = $this->karyawan;
            $data->solve_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Sampling dinyatakan selesai';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Sampling Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-sampling')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses solve Ticket Sampling: ' . $th->getMessage()
            ], 500);
        }
    }

    // Reject by Worker
    public function reject(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'REJECT';
            $data->rejected_by = $this->karyawan;
            $data->rejected_time = Carbon::now();
            $data->rejected_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Sampling telah di reject';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Sampling Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-sampling')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket Sampling: ' . $th->getMessage()
            ], 500);
        }
    }

    public function reOpen(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'REOPEN';
            $data->reopened_by = $this->karyawan;
            $data->reopened_time = Carbon::now();
            $data->reopened_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Sampling telah di re-open';

            $data->save();

            Notification::where('nama_lengkap', $data->solve_by)
                ->title('Ticket Sampling Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-sampling')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket Sampling: ' . $th->getMessage()
            ], 500);
        }
    }

    // Pending by Worker
    public function pending(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'PENDING';
            $data->pending_by = $this->karyawan;
            $data->pending_time = Carbon::now();
            $data->pending_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Sampling telah di pending';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Sampling Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-sampling')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses pending Ticket Sampling: ' . $th->getMessage()
            ], 500);
        }
    }

    // Process by Worker
    public function process(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->status = 'PROCESS';
            $data->process_by = $this->karyawan;
            $data->process_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Sampling sedang di process';

            $data->save();

            DB::commit();

            if ($this->karyawan == $data->created_by) {
                $user_programmer = MasterKaryawan::where('id_department', 14)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket Sampling Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-sampling')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Sampling Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-sampling')
                    ->send();
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses process Ticket Sampling: ' . $th->getMessage(),
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
                $data = new TicketSampling();
                $data->request_by = $this->karyawan;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->request_time = Carbon::now();

                $microtime = str_replace(".", "", microtime(true));
                $uniq_id = $microtime;
                $filename = $microtime . '.txt';
                $content = $request->details;
                $contentDir = 'ticket_sampling';

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
                    $filenameDok = "SAMPLING_" . $uniq_id . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);

                    $data->dokumentasi = $filenameDok;
                } else {
                    $data->dokumentasi = null;
                }

                $data->nomor_ticket = $uniq_id;
                $data->filename = $filename;
                $message = 'Ticket Sampling Telah Ditambahkan';
            } else {
                $data = TicketSampling::find($request->id);
                if (!$data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket Sampling tidak ditemukan'
                    ], 404);
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

                $contentDir = 'ticket_sampling';
                if (!file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                $content = $request->details;
                file_put_contents(public_path($contentDir . '/' . $data->filename), $content);
                $message = 'Ticket Sampling Telah Diperbarui';
            }

            $data->status = 'WAITING PROCESS';
            $data->no_quotation = $request->no_quotation;
            $data->periode_kontrak = $request->periode_kontrak ?? null;
            $data->type_quotation = $request->type_quotation;
            $data->kategori = $request->kategori;

            if($this->grade == 'MANAGER' && $data->kategori == 'PERUBAHAN_DATA') {
                $data->approved_by = $this->karyawan;
                $data->approved_time = Carbon::now()->format('Y-m-d H:i:s');
            }

            $data->save();

            $user_Sampling = MasterKaryawan::where('id_department', 14)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_Sampling)
                ->title('Ticket Sampling !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . str_replace('_', ' ', $data->kategori))
                ->url('/ticket-sampling')
                ->send();

            $getAtasan = GetAtasan::where('nama_lengkap', $this->karyawan)->get()->pluck('id');

            $isPerubahanData = $data->kategori == 'PERUBAHAN_DATA';
            Notification::whereIn('id', $getAtasan)
                ->title('Ticket Sampling !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . str_replace('_', ' ', $data->category) . ($isPerubahanData ? ' Yang Harus Disetujui Oleh Atasan' : ''))
                ->url('/ticket-sampling')
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
                'message' => 'Gagal Proses Ticket Sampling: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveTicket(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketSampling::find($request->id);
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');

            $data->save();

            $message = 'Ticket Sampling telah diapprove oleh ' . $this->karyawan .' dan siap untuk diproses oleh tim Admin Sampling';

            Notification::whereIn('nama_lengkap', $data->solve_by)
                ->title('Ticket Sampling Siap Diproses!')
                ->message($message)
                ->url('/ticket-sampling')
                ->send();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Sampling Update')
                ->message($message)
                ->url('/ticket-sampling')
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
                'message' => 'Gagal Proses Ticket Sampling: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getQuotation(Request $request) 
    {
        $search = $request->search ?? '';

        if ($request->type_quot == 'kontrak') {
            $data = QuotationKontrakH::with('detail')->select('no_document', 'id')
                ->where('is_active', true)
                ->where('no_document', 'like', '%' . $search . '%')
                ->limit(20)
                ->get();
        } else {
            $data = QuotationNonKontrak::select('no_document', 'id')
                ->where('no_document', 'like', '%' . $search . '%')
                ->where('is_active', true)
                ->limit(20)
                ->get();
        }

        return response()->json([
            'results' => $data->map(fn($v) => [
                'id' => $v->no_document,
                'text' => $v->no_document,
                'periode' => $v->detail ? $v->detail->pluck('periode_kontrak') : [],
            ])
        ], 200);
    }

}
