<?php

namespace App\Http\Controllers\api;

use App\Models\TicketRevisiQt;
use App\Models\MasterKaryawan;
// use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\GetAtasan;
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

class TicketRevisiQtController extends Controller
{
    public function index(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan;

        if (in_array($karyawan->id_jabatan, [24, 148])) { // so + cro
            $data = TicketRevisiQt::where(['request_by' => $karyawan->nama_lengkap, 'is_active' => true])->orderBy('id', 'desc');

            return Datatables::of($data)
                ->addColumn('reff', function ($row) {
                    $filePath = public_path('ticket_revisi_qt/' . $row->filename);
                    if (file_exists($filePath) && is_file($filePath)) {
                        return file_get_contents($filePath);
                    }

                    return 'File not found';
                })
                ->make(true);
        };

        if (in_array($karyawan->id_jabatan, [22, 23, 25])) { // sales adm
            $pic = DB::table('pic_tiket_revisi_qt')->first();
            if ($karyawan->id === $pic->sales_id) {
                $data = TicketRevisiQt::where(['is_active' => true])->orderBy('id', 'desc');
            } else {
                $data = TicketRevisiQt::where(['delegated_to' => $karyawan->id, 'is_active' => true])->orderBy('id', 'desc');
            }

            return Datatables::of($data)
                ->addColumn('reff', function ($row) {
                    $filePath = public_path('ticket_revisi_qt/' . $row->filename);
                    if (file_exists($filePath) && is_file($filePath)) {
                        return file_get_contents($filePath);
                    }

                    return 'File not found';
                })
                ->addColumn('can_delegate', $karyawan->id === $pic->sales_id)
                ->make(true);
        }

        $data = TicketRevisiQt::where(['is_active' => true])->orderBy('id', 'desc');

        return Datatables::of($data)
            ->addColumn('reff', function ($row) {
                $filePath = public_path('ticket_revisi_qt/' . $row->filename);
                if (file_exists($filePath) && is_file($filePath)) {
                    return file_get_contents($filePath);
                }

                return 'File not found';
            })
            ->make(true);
    }

    public function getQt(Request $request)
    {
        $search = $request->input('q');

        $kontrak = QuotationKontrakH::with('sales:id,nama_lengkap')
            ->select('id', 'no_document', 'pelanggan_ID', 'nama_perusahaan', 'sales_id')
            ->where('no_document', 'like', "%{$search}%")
            ->where('is_active', true);

        $nonKontrak = QuotationNonKontrak::with('sales:id,nama_lengkap')
            ->select('id', 'no_document', 'pelanggan_ID', 'nama_perusahaan', 'sales_id')
            ->where('no_document', 'like', "%{$search}%")
            ->where('is_active', true);

        $results = $kontrak
            ->unionAll($nonKontrak)
            ->orderBy('no_document', 'desc')
            ->limit(10)
            ->get();

        $results = $results->makeHidden(['id']);

        return response()->json($results, 200);
    }

    public function getQtDetail(Request $request)
    {
        $search = $request->input('no_document');

        $kontrak = QuotationKontrakH::with('sales:id,nama_lengkap')
            ->select('id', 'no_document', 'nama_perusahaan', 'sales_id')
            ->where('no_document', 'like', "%{$search}%")
            ->where('is_active', true);

        $nonKontrak = QuotationNonKontrak::with('sales:id,nama_lengkap')
            ->select('id', 'no_document', 'nama_perusahaan', 'sales_id')
            ->where('no_document', 'like', "%{$search}%")
            ->where('is_active', true);

        $results = $kontrak
            ->unionAll($nonKontrak)
            ->first();

        $results = $results->makeHidden(['id']);

        return response()->json(['data' => $results], 200);
    }

    public function void(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'VOID';
            $data->void_by = $this->karyawan;
            $data->void_time = Carbon::now();
            $data->void_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt telah di void';

            $data->save();

            if ($this->karyawan == $data->created_by) {
                $user_adm_sales = MasterKaryawan::whereIn('id_jabatan', [22, 23, 25])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_adm_sales)
                    ->title('Ticket Revisi Qt Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-revisi-qt')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Revisi Qt Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-revisi-qt')
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
                'message' => 'Gagal Proses void Ticket Revisi Qt: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done By Client
    public function approve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'DONE';
            $data->done_by = $this->karyawan;
            $data->done_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt telah dinyatakan selesai';

            $data->save();

            $user_adm_sales = MasterKaryawan::whereIn('id_jabatan', [22, 23, 25])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_adm_sales)
                ->title('Ticket Revisi Qt Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses menyelesaikan Ticket Revisi Qt: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done by Worker
    public function solve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'SOLVE';
            $data->solve_by = $this->karyawan;
            $data->solve_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt dinyatakan selesai';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Revisi Qt Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses solve Ticket Revisi Qt: ' . $th->getMessage()
            ], 500);
        }
    }

    // Reject by Worker
    public function reject(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'REJECT';
            $data->rejected_by = $this->karyawan;
            $data->rejected_time = Carbon::now();
            $data->rejected_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt telah di reject';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Revisi Qt Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket Revisi Qt: ' . $th->getMessage()
            ], 500);
        }
    }

    public function reOpen(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'REOPEN';
            $data->reopened_by = $this->karyawan;
            $data->reopened_time = Carbon::now();
            $data->reopened_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt telah di re-open';

            $data->save();

            Notification::where('nama_lengkap', $data->solve_by)
                ->title('Ticket Revisi Qt Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket Revisi Qt: ' . $th->getMessage()
            ], 500);
        }
    }

    // Pending by Worker
    public function pending(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'PENDING';
            $data->pending_by = $this->karyawan;
            $data->pending_time = Carbon::now();
            $data->pending_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt telah di pending';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket Revisi Qt Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses pending Ticket Revisi Qt: ' . $th->getMessage()
            ], 500);
        }
    }

    // Process by Worker
    public function process(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketRevisiQt::find($request->id);
            $data->status = 'PROCESS';
            $data->process_by = $this->karyawan;
            $data->process_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket Revisi Qt sedang di process';

            $data->save();

            DB::commit();

            if ($this->karyawan == $data->created_by) {
                $user_adm_sales = MasterKaryawan::whereIn('id_jabatan', [22, 23, 25])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_adm_sales)
                    ->title('Ticket Revisi Qt Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-revisi-qt')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket Revisi Qt Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-revisi-qt')
                    ->send();
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses process Ticket Revisi Qt: ' . $th->getMessage(),
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
                $data = new TicketRevisiQt();
                $data->request_by = $this->karyawan;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->request_time = Carbon::now();

                $microtime = str_replace(".", "", microtime(true));
                $uniq_id = $microtime;
                $filename = $microtime . '.txt';
                $content = $request->details;
                $contentDir = 'ticket_revisi_qt';

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
                    $filenameDok = "REVISIQT_" . $uniq_id . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);

                    $data->dokumentasi = $filenameDok;
                } else {
                    $data->dokumentasi = null;
                }

                $data->no_qt = $request->no_qt;
                $data->nomor_ticket = $uniq_id;
                $data->filename = $filename;
                $message = 'Ticket Revisi Qt Telah Ditambahkan';
            } else {
                $data = TicketRevisiQt::find($request->id);
                if (!$data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket Revisi Qt tidak ditemukan'
                    ], 404);
                }

                if ($request->hasFile('dokumentasi')) {
                    $dir_dokumentasi = "ticket";

                    if (!file_exists(public_path($dir_dokumentasi))) {
                        mkdir(public_path($dir_dokumentasi), 0777, true);
                    }

                    $file = $request->file('dokumentasi');
                    $extTicket = $file->getClientOriginalExtension();
                    $filenameDok = "REVISIQT_" . $data->nomor_ticket . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);
                } else {
                    $data->dokumentasi = null;
                }

                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();

                $contentDir = 'ticket_revisi_qt';
                if (!file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                $content = $request->details;
                file_put_contents(public_path($contentDir . '/' . $data->filename), $content);
                $message = 'Ticket Revisi Qt Telah Diperbarui';
            }

            $data->status = 'WAITING TO DELEGATE';

            $data->save();

            $user_adm_sales = MasterKaryawan::whereIn('id_jabatan', [22, 23, 25])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_adm_sales)
                ->title('Ticket Revisi Qt !')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
                ->send();

            $getAtasan = GetAtasan::where('nama_lengkap', $this->karyawan)->get()->pluck('id');

            Notification::whereIn('id', $getAtasan)
                ->title('Ticket Revisi Qt !')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-revisi-qt')
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
                'message' => 'Gagal Proses Ticket Revisi Qt: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSales(Request $request)
    {
        $sales = MasterKaryawan::where('is_active', true)->whereIn('id_jabatan', [22, 23, 25])->get(['id', 'nama_lengkap']);

        return response()->json([
            'success' => true,
            'data' => $sales,
            'pic' => DB::table('pic_tiket_revisi_qt')->first()
        ], 200);
    }

    public function setPIC(Request $request)
    {
        DB::table('pic_tiket_revisi_qt')->updateOrInsert(
            ['id' => 1],
            ['sales_id' => $request->sales_id]
        );

        return response()->json([
            'success' => true,
            'message' => 'PIC berhasil diupdate'
        ], 200);
    }

    public function delegate(Request $request)
    {
        TicketRevisiQt::where('id', $request->id)
            ->update([
                'delegated_by' => $this->karyawan,
                'delegated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'delegated_to' => $request->sales_id,
                'status' => 'WAITING PROCESS'
            ]);

        Notification::where('id', $request->sales_id)
            ->title('Ticket Revisi Qt !')
            ->message("Ticket revisi QT baru telah didelegasikan oleh {$this->karyawan} dan siap diproses oleh anda.")
            ->url('/ticket-revisi-qt')
            ->send();

        return response()->json([
            'success' => true,
            'message' => 'Ticket Revisi Qt telah di delegasikan oleh ' . $this->karyawan
        ], 200);
    }
}
