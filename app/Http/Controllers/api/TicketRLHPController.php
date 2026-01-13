<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DraftAir;
// use App\Models\User;
use App\Models\ErgonomiHeader;
use App\Models\LhpsAirHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsHygieneSanitasiHeader;
use App\Models\LhpsIklimHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsLingHeader;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMicrobiologiHeader;
use App\Models\LhpsSwabTesHeader;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\MasterKaryawan;
use App\Models\OrderDetail;
use App\Models\ParameterFdl;
use App\Models\TicketRLHP;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class TicketRLHPController extends Controller
{
    // public function index(Request $request)
    // {
    //     try {
    //         $department = $request->attributes->get('user')->karyawan->id_department;
    //         if ($department == 17 && !in_array($this->user_id, [10, 15, 93, 123])) {
    //             $data = TicketRLHP::where('is_active', true)
    //                 ->orderBy('id', 'desc');
    //             return Datatables::of($data)
    //                 ->addColumn('reff', function ($row) {
    //                     $filePath = public_path('ticket_programming/' . $row->filename);
    //                     if (file_exists($filePath) && is_file($filePath)) {
    //                         return file_get_contents($filePath);
    //                     } else {
    //                         return 'File not found';
    //                     }
    //                 })
    //                 ->make(true);
    //         } else {
    //             $grade =$this->grade;
    //             if($grade == 'MANAGER') {
    //                 $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
    //             } else {
    //                 $getBawahan = [];
    //             }
    //             $data = TicketRLHP::whereIn('request_by', $getBawahan)
    //                 ->where('is_active', true)
    //                 ->orderBy('id', 'desc');

    //             return Datatables::of($data)
    //                 ->addColumn('reff', function ($row) {
    //                     $filePath = public_path('ticket_programming/' . $row->filename);
    //                     if (file_exists($filePath) && is_file($filePath)) {
    //                         return file_get_contents($filePath);
    //                     } else {
    //                         return 'File not found';
    //                     }
    //                 })
    //                 ->addColumn('can_approve', function ($row) use ($getBawahan) {
    //                     // comment
    //                     return in_array($row->created_by, $getBawahan) && $this->karyawan != $row->created_by;
    //                 })
    //                 ->make(true);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'data' => [],
    //             'message' => $e->getMessage(),
    //         ], 201);
    //     }
    // }
    public function index(Request $request)
    {
        try {
            $department = $request->attributes->get('user')->karyawan->id_department;

            if (($department == 17 || $department == 7) && ! in_array($this->user_id, [10, 15, 93, 123])) {
                $data = TicketRLHP::where('is_active', true)
                    ->whereNotIn('status', ['DONE', 'REJECT', 'VOID'])
                    ->where(function ($q) {
                        $q->where('kategori', 'TANGGAL')
                                ->where('status', '=', 'WAITING PROCESS')
                            ->orWhere(function ($q2) {
                                $q2->where('kategori', 'DATA')
                                    ->where('status', '!=', 'WAITING PROCESS');
                            });
                    })
                    ->orderByDesc('id');

                return DataTables::of($data)
                    ->editColumn('data_perusahaan', function ($row) {
                        return $row->data_perusahaan
                            ? json_decode($row->data_perusahaan, true)
                            : [];
                    })
                    ->editColumn('perubahan_data', function ($row) {
                        return $row->perubahan_data
                            ? json_decode($row->perubahan_data, true)
                            : [];
                    })
                    ->editColumn('perubahan_tanggal', function ($row) {
                        return $row->perubahan_tanggal
                            ? json_decode($row->perubahan_tanggal, true)
                            : [];
                    })
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_rlhp/' . $row->filename);
                        return file_exists($filePath) ? file_get_contents($filePath) : 'File not found';
                    })
                    ->make(true);

            } else {

                $grade = $this->grade;
                // dd($grade);
                if ($grade == 'MANAGER') {
                    $getBawahan = GetBawahan::where('id', $this->user_id)->get()
                        ->pluck('nama_lengkap')
                        ->toArray();
                } else {
                    $getBawahan = [$this->karyawan];
                }

                $data = TicketRLHP::whereIn('request_by', $getBawahan)
                    ->where('is_active', true)
                    ->orderByDesc('id');

                return DataTables::of($data)
                    ->editColumn('data_perusahaan', function ($row) {
                        return $row->data_perusahaan
                            ? json_decode($row->data_perusahaan, true)
                            : [];
                    })
                    ->editColumn('perubahan_data', function ($row) {
                        return $row->perubahan_data
                            ? json_decode($row->perubahan_data, true)
                            : [];
                    })
                    ->editColumn('perubahan_tanggal', function ($row) {
                        return $row->perubahan_tanggal
                            ? json_decode($row->perubahan_tanggal, true)
                            : [];
                    })
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_rlhp/' . $row->filename);
                        return file_exists($filePath) ? file_get_contents($filePath) : 'File not found';
                    })
                    ->addColumn('can_approve', function ($row) use ($getBawahan) {
                        return in_array($row->created_by, $getBawahan) && $this->karyawan != $row->created_by;
                    })
                    ->make(true);
            }

        } catch (\Exception $e) {
            return response()->json([
                'data'    => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function searchLhp(Request $request)
    {
        $data = OrderDetail::with(['orderHeader', 'lhps_air', 'lhps_ling', 'lhps_emisi_c'])
            ->where('is_active', true)
            ->where('cfr', $request->no_lhp)
            ->where(function ($q) {
                $q->whereHas('lhps_air')
                    ->orWhereHas('lhps_ling')
                    ->orWhereHas('lhps_emisi_c');
            })
            ->first();

        if (! $data) {
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $hasAir    = $data->relationLoaded('lhps_air') && $data->lhps_air;
        $hasLing   = $data->relationLoaded('lhps_ling') && $data->lhps_ling;
        $hasEmisiC = $data->relationLoaded('lhps_emisi_c') && $data->lhps_emisi_c;

        /**
         * Kalau cuma punya lhps_ling saja
         */
        if ($hasLing) {

            $parameter = json_decode($data->parameter, true) ?? [];

            if (count($parameter) < 2) {
                return response()->json([
                    'message' => 'No LHP ini Tidak dapat melakukan revisi LHP',
                ], 404);
            }
        }

        $param = OrderDetail::where('is_active', true)
            ->where('cfr', $request->no_lhp)
            ->pluck('parameter') // ambil kolom parameter saja
            ->flatMap(fn($p) => json_decode($p, true) ?? [])
            ->unique()
            ->map(function ($item) {
                [$id, $name] = explode(';', $item);
                return (object) [
                    'id'   => $id,
                    'name' => $name,
                ];
            })
            ->values();

        $data->parameter = $param;

        if ($hasAir) {
            $data->detailParameter = $data->lhps_air->lhpsAirDetail;
        }
        if ($hasLing) {
            $data->detailParameter = $data->lhps_ling->lhpsLingDetail;
        }
        if ($hasEmisiC) {
            $data->detailParameter = $data->lhps_emisi_c->lhpsEmisiCDetail;
        }

        // $arrayModels = [
        //     LhpsLingHeader::class,
        //     LhpsAirHeader::class,
        //     lhps_emisi_c::class
        // ]

        return response()->json([
            'data'    => $data,
            'message' => 'Data found',
        ], 200);
    }

    private function getTypeLHP($kategori2, $kategori3, $parameter)
    {
        $parameter = json_decode($parameter, true);
        if ($kategori2 == '1-Air') {
            return LhpsAirHeader::class;
        }

        if ($kategori2 == '4-Udara') {
            $ulk        = ["27-Udara Lingkungan Kerja"];
            $ulh        = ['11-Udara Ambient'];
            $ergonomi   = ["53-Ergonomi"];
            $getaran    = ["13-Getaran", "14-Getaran (Bangunan)", "15-Getaran (Kejut Bangunan)", "16-Getaran (Kejut Bangunan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)", "17-Getaran (Lengan & Tangan)", "20-Getaran (Seluruh Tubuh)"];
            $iklimKerja = ["21-Iklim Kerja"];
            $kebauan    = ["22-Kebauan"];
            $kebisingan = ["23-Kebisingan", '24-Kebisingan (24 Jam)', '25-Kebisingan (Indoor)', '26-Kualitas Udara Dalam Ruang'];
            $bakteri    = ["12-Udara Angka Kuman", '33-Mikrobiologi Udara', '27-Udara Lingkungan Kerja', '26-Kualitas Udara Dalam Ruang'];
            $swabTest   = ['46-Udara Swab Test'];
            $udaraUmum  = ["29-Udara Umum"];

            $paramPsikologi      = ["318;Psikologi"];
            $paramGelombangMikro = [
                "563;Medan Magnit Statis",
                "316;Power Density",
                "277;Medan Listrik",
                "236;Gelombang Elektro",
            ];
            $paramHygiene = [
                'K3-KB',
                'K3-KFK',
                'K3-KFS',
                'K3-KFPBP',
                'K3-KRU',
                'K3-KTRTHK',
            ];
            $paramKebisingan = [
                '271;Kebisingan (P8J)',
            ];
            $paramMicro     = ParameterFdl::where('nama_fdl', 'microbiologi')->first();
            $paramMicro     = json_decode($paramMicro->parameters, true);
            $paramExceptUlk = array_merge($paramMicro, ['Sinar UV', 'Ergonomi', 'Gelombang Elektro', 'Medan Listrik', 'Medan Magnit Statis', 'Power Density']);

            if (in_array($kategori3, $udaraUmum)) {
                return LhpsLingHeader::class;
            }
            if (! empty(array_intersect($paramPsikologi, $parameter))) {
                return LhpUdaraPsikologiHeader::class;
            }
            if (in_array($kategori3, $swabTest)) {
                return LhpsSwabTesHeader::class;
            }
            if (in_array($kategori3, $ulh)) {
                return LhpsLingHeader::class;
            }
            if (in_array($kategori3, $ulk)) {
                if (! empty(array_intersect($paramGelombangMikro, $parameter))) {
                    return LhpsMedanLMHeader::class;
                }
                if (! empty(array_intersect($paramHygiene, $parameter))) {
                    return LhpsHygieneSanitasiHeader::class;
                }
                if (empty(array_intersect($paramExceptUlk, $parameter))) {
                    return LhpsLingHeader::class;
                }
            }

            if (in_array($kategori3, array_merge($ulk, $ergonomi))) {
                if (in_array('230;Ergonomi', $parameter)) {
                    return ErgonomiHeader::class;
                }
            }

            if (in_array($kategori3, $getaran)) {
                return LhpsGetaranHeader::class;
            }

            if (in_array($kategori3, $iklimKerja)) {
                return LhpsIklimHeader::class;
            }

            // if (in_array($kategori3, $kebauan)) {
            //     return LhpsKebauanHeader::class;
            // }

            if (in_array($kategori3, $kebisingan)) {
                if (! empty(array_intersect($paramKebisingan, $parameter))) {
                    return LhpsKebisinganHeader::class;
                }
            }

            if (in_array($kategori3, $bakteri)) {
                if (! empty(array_intersect($paramMicro, $parameter))) {
                    return LhpsMicrobiologiHeader::class;
                }
            }

        }
    }

    public function void(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data             = TicketRLHP::find($request->id);
            $data->status     = 'VOID';
            $data->void_by    = $this->karyawan;
            $data->void_time  = Carbon::now();
            $data->void_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket R-LHP telah di void';

            $data->save();

            if ($this->karyawan == $data->created_by) {
                $user_tc = MasterKaryawan::where('id_department', 17)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_tc)
                    ->title('Ticket R-LHP Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-rlhp')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket R-LHP Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-rlhp')
                    ->send();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses void Ticket R-LHP: ' . $th->getMessage(),
            ], 500);
        }
    }

    // Done By Client
    public function approve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data            = TicketRLHP::find($request->id);
            $data->status    = 'DONE';
            $data->done_by   = $this->karyawan;
            $data->done_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket R-LHP telah dinyatakan selesai';

            $data->save();

            $user_tc = MasterKaryawan::where('id_department', 17)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_tc)
                ->title('Ticket R-LHP Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses menyelesaikan Ticket R-LHP: ' . $th->getMessage(),
            ], 500);
        }
    }

    // Done by Worker
    public function solve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data             = TicketRLHP::find($request->id);
            $data->status     = 'SOLVE';
            $data->solve_by   = $this->karyawan;
            $data->solve_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket R-LHP dinyatakan selesai';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket R-LHP Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses solve Ticket R-LHP: ' . $th->getMessage(),
            ], 500);
        }
    }

    // Reject by Worker
    public function reject(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data                 = TicketRLHP::find($request->id);
            $data->status         = 'REJECT';
            $data->rejected_by    = $this->karyawan;
            $data->rejected_time  = Carbon::now();
            $data->rejected_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket R-LHP telah di reject';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket R-LHP Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket R-LHP: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function reOpen(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data                 = TicketRLHP::find($request->id);
            $data->status         = 'REOPEN';
            $data->reopened_by    = $this->karyawan;
            $data->reopened_time  = Carbon::now();
            $data->reopened_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket R-LHP telah di re-open';

            $data->save();

            Notification::where('nama_lengkap', $data->solve_by)
                ->title('Ticket R-LHP Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses reject Ticket R-LHP: ' . $th->getMessage(),
            ], 500);
        }
    }

    // Pending by Worker
    public function pending(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data                = TicketRLHP::find($request->id);
            $data->status        = 'PENDING';
            $data->pending_by    = $this->karyawan;
            $data->pending_time  = Carbon::now();
            $data->pending_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket R-LHP telah di pending';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket R-LHP Update')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses pending Ticket R-LHP: ' . $th->getMessage(),
            ], 500);
        }
    }

    // Process by Worker
    public function process(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data               = TicketRLHP::find($request->id);
            $data->status       = 'PROCESS';
            $data->process_by   = $this->karyawan;
            $data->process_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket R-LHP sedang di process';

            $data->save();

            DB::commit();

            if ($this->karyawan == $data->created_by) {
                $user_tc = MasterKaryawan::where('id_department', 17)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_tc)
                    ->title('Ticket R-LHP Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-rlhp')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket R-LHP Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-rlhp')
                    ->send();
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses process Ticket R-LHP: ' . $th->getMessage(),
                'line'    => $th->getLine(),
                'file'    => $th->getFile(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            if (empty($request->id)) {
                $data               = new TicketRLHP();
                $data->request_by   = $this->karyawan;
                $data->created_by   = $this->karyawan;
                $data->created_at   = Carbon::now();
                $data->request_time = Carbon::now();

                $microtime  = str_replace(".", "", microtime(true));
                $uniq_id    = $microtime;
                $filename   = $microtime . '.txt';
                $content    = $request->details;
                $contentDir = 'ticket_rlhp';

                if (! file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                file_put_contents(public_path($contentDir . '/' . $filename), $content);

                if ($request->hasFile('dokumentasi')) {
                    $dir_dokumentasi = "ticket";

                    if (! file_exists(public_path($dir_dokumentasi))) {
                        mkdir(public_path($dir_dokumentasi), 0777, true);
                    }

                    $file        = $request->file('dokumentasi');
                    $extTicket   = $file->getClientOriginalExtension();
                    $filenameDok = "RLHP_" . $uniq_id . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);

                    $data->dokumentasi = $filenameDok;
                } else {
                    $data->dokumentasi = null;
                }

                $data->perubahan_tanggal = $request->perubahan_tanggal ? json_encode($request->perubahan_tanggal) : null;
                $data->perubahan_data    = $request->perubahan_data ? json_encode($request->perubahan_data) : null;
                $data->no_lhp            = $request->no_lhp;
                $data->data_perusahaan   = $request->data_perusahaan ? json_encode($request->data_perusahaan) : null;

                $data->nama_menu    = $request->nama_menu;
                $data->nomor_ticket = $uniq_id;
                $data->filename     = $filename;
                $message            = 'Ticket R-LHP Telah Ditambahkan';
            } else {
                $data = TicketRLHP::find($request->id);
                if (! $data) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket R-LHP tidak ditemukan',
                    ], 404);
                }

                if ($request->hasFile('dokumentasi')) {
                    $dir_dokumentasi = "ticket";

                    if (! file_exists(public_path($dir_dokumentasi))) {
                        mkdir(public_path($dir_dokumentasi), 0777, true);
                    }

                    $file        = $request->file('dokumentasi');
                    $extTicket   = $file->getClientOriginalExtension();
                    $filenameDok = "RLHP_" . $data->nomor_ticket . '.' . $extTicket;

                    // simpan ke folder public/ticket
                    $file->move(public_path($dir_dokumentasi), $filenameDok);
                } else {
                    $data->dokumentasi = null;
                }
                if ($request->hasFile('perubahan_tanggal')) {
                    $data->perubahan_tanggal = json_encode($request->perubahan_tanggal);
                }
                if ($request->hasFile('perubahan_data')) {
                    $data->perubahan_data = json_encode($request->perubahan_data);
                }

                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();

                $contentDir = 'ticket_rlhp';
                if (! file_exists(public_path($contentDir))) {
                    mkdir(public_path($contentDir), 0777, true);
                }

                $content = $request->details;
                file_put_contents(public_path($contentDir . '/' . $data->filename), $content);
                $message = 'Ticket R-LHP Telah Diperbarui';
            }

            $data->status   = 'WAITING PROCESS';
            $data->kategori = $request->kategori;

            if ($this->grade == 'MANAGER' && $data->kategori == 'PERUBAHAN_DATA') {
                $data->approved_by   = $this->karyawan;
                $data->approved_time = Carbon::now()->format('Y-m-d H:i:s');
            }

            $data->save();

            $user_tc = MasterKaryawan::where('id_department', 17)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_tc)
                ->title('Ticket R-LHP !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Perubahan ' . str_replace('_', ' ', $data->kategori))
                ->url('/ticket-rlhp')
                ->send();

            $getAtasan = GetAtasan::where('nama_lengkap', $this->karyawan)->get()->pluck('id');

            $isPerubahanData = $data->kategori == 'PERUBAHAN_DATA';
            Notification::whereIn('id', $getAtasan)
                ->title('Ticket R-LHP !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Perubahan ' . str_replace('_', ' ', $data->category) . ($isPerubahanData ? ' Yang Harus Disetujui Oleh Atasan' : ''))
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses Ticket R-LHP: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approveTicket(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data              = TicketRLHP::find($request->id);
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');

            $data->save();

            $message = 'Ticket R-LHP telah diapprove oleh ' . $this->karyawan . ' dan siap untuk diproses oleh tim Teknis';

            $user_tc = MasterKaryawan::where('id_department', 17)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_tc)
                ->title('Ticket R-LHP Siap Diproses!')
                ->message($message)
                ->url('/ticket-rlhp')
                ->send();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket R-LHP Update')
                ->message($message)
                ->url('/ticket-rlhp')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal Proses Ticket R-LHP: ' . $e->getMessage(),
            ], 500);
        }
    }
}
