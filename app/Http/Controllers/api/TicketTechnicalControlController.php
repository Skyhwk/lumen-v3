<?php

namespace App\Http\Controllers\api;


use App\Models\{MasterKaryawan,MasterRegulasi,MasterKategori,Parameter,MasterBakumutu,AksesMenu,TicketTechnicalControl};
use App\Services\{GetAtasan,Notification,GetBawahan};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class TicketTechnicalControlController extends Controller
{
    public function index(Request $request)
    {
        try {
            $department = $request->attributes->get('user')->karyawan->id_department;
            
            if ($department == 17) {
                $data = TicketTechnicalControl::where('is_active', true)
                    ->orderBy('id', 'desc');
                return Datatables::of($data)
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_technical_control/' . $row->filename);
                        if (file_exists($filePath) && is_file($filePath)) {
                            return file_get_contents($filePath);
                        } else {
                            return 'File not found';
                        }
                    })
                    ->make(true);
            } else {
                $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
                $data = TicketTechnicalControl::whereIn('request_by', $getBawahan)
                    ->where('is_active', true)
                    ->orderBy('id', 'desc')->get();
                return Datatables::of($data)
                    ->addColumn('reff', function ($row) {
                        $filePath = public_path('ticket_technical_control/' . $row->filename);
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
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'VOID';
            $data->void_by = $this->karyawan;
            $data->void_time = Carbon::now();
            $data->void_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket technical Controll telah di void';

            $data->save();

            if ($this->karyawan == $data->created_by) {
                $user_programmer = MasterKaryawan::where('id_department', 7)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket technical Controll Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses void Ticket technical Controll: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done By Client
    public function approve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'DONE';
            $data->done_by = $this->karyawan;
            $data->done_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket technical Controll telah dinyatakan selesai';

            $data->save();

            $user_programming = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programming)
                ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses menyelesaikan Ticket technical Controll: ' . $th->getMessage()
            ], 500);
        }
    }

    // Done by Worker
    public function solve(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'SOLVE';
            $data->solve_by = $this->karyawan;
            $data->solve_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket technical Controll dinyatakan selesai';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses solve Ticket technical Controll: ' . $th->getMessage()
            ], 500);
        }
    }

    // Reject by Worker
    public function reject(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'REJECT';
            $data->rejected_by = $this->karyawan;
            $data->rejected_time = Carbon::now();
            $data->rejected_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket technical Controll telah di reject';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses reject Ticket technical Controll: ' . $th->getMessage()
            ], 500);
        }
    }

    public function reOpen(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'REOPEN';
            $data->reopened_by = $this->karyawan;
            $data->reopened_time = Carbon::now();
            $data->reopened_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket technical Controll telah di re-open';

            $data->save();

            Notification::where('nama_lengkap', $data->solve_by)
                ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses reject Ticket technical Controll: ' . $th->getMessage()
            ], 500);
        }
    }

    // Pending by Worker
    public function pending(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'PENDING';
            $data->pending_by = $this->karyawan;
            $data->pending_time = Carbon::now();
            $data->pending_notes = $request->notes;
            // $data->is_active = false;
            $message = 'Ticket technical Controll telah di pending';

            $data->save();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses pending Ticket technical Controll: ' . $th->getMessage()
            ], 500);
        }
    }

    // Process by Worker
    public function process(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->status = 'PROCESS';
            $data->process_by = $this->karyawan;
            $data->process_time = Carbon::now();
            // $data->is_active = false;
            $message = 'Ticket technical Controll sedang di process';

            $data->save();

            DB::commit();

            if ($this->karyawan == $data->created_by) {
                $user_programmer = MasterKaryawan::where('id_department', 7)
                    ->whereNotIn('id', [10, 15, 93, 123])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                Notification::whereIn('id', $user_programmer)
                    ->title('Ticket technical Controll Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            } else {
                Notification::where('nama_lengkap', $data->created_by)
                    ->title('Ticket technical Controll Update')
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
                'message' => 'Gagal Proses process Ticket technical Controll: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $microtime = str_replace(".", "", microtime(true));
            if (empty($request->id)) { //insert
                $data = new TicketTechnicalControl();
                $data->request_by = $this->karyawan;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
                $data->request_time = Carbon::now();
                $uniq_id = $microtime;
                if($request->kategori === 'Tanya regulasi'){ 
                    $filename = $microtime . '.txt';
                    // Ini HTML mentah dari Summernote
                    $content = $request->details; 
                    $contentDir = 'ticket_technical_control';

                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true);
                    
                    // Cek apakah content kosong untuk menghindari error DOMDocument
                    if (!empty($content)) {
                        $dom->loadHtml($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $images = $dom->getElementsByTagName('img');
                        
                        if (!file_exists(public_path($contentDir))) {
                            mkdir(public_path($contentDir), 0777, true);
                        }
                        
                        foreach($images as $k => $img){
                            $data_img = $img->getAttribute('src');
                            
                            // Cek apakah src mengandung base64
                            if(preg_match('/data:image/', $data_img)){
                                list($type, $data_img) = explode(';', $data_img);
                                list(, $data_img)      = explode(',', $data_img);
                                $data_img = base64_decode($data_img);

                                // Buat nama file unik untuk gambar
                                $imageName = time() . '_' . $k . '.png';
                                $path = public_path($contentDir . '/' . $imageName);

                                // Simpan file gambar fisik
                                file_put_contents($path, $data_img);
                                
                                // Ganti atribut src dari base64 menjadi URL gambar
                                $img->removeAttribute('src');
                                $img->setAttribute('src', \URL::asset($contentDir . '/' . $imageName));
                            }
                        }
                        // Ambil HTML yang sudah bersih dari base64
                        $cleanContent = $dom->saveHTML();
                    } else {
                        $cleanContent = '';
                    }

                    // KOREKSI: Simpan $cleanContent ke dalam file .txt, BUKAN $content
                    file_put_contents(public_path($contentDir . '/' . $filename), $cleanContent);

                    // JAWABAN: Susun array untuk disimpan ke dalam kolom JSON 'dokumentasi'
                    $dokumentasiData = [
                        'kategori'  => $request->kategori,
                        'regulasi'  => $request->regulasi,
                        'file_path' => $contentDir . '/' . $filename, // Path file .txt yang berisi HTML bersih
                    ];
                    
                    // Laravel akan otomatis mengkonversi array ini menjadi JSON 
                    // (asalkan di Model sudah di-cast: protected $casts = ['dokumentasi' => 'array'];)
                    $data->dokumentasi = $dokumentasiData;

                } else { 
                    // yang sudah berjalan
                    $dokumentasiData = [
                        'regulasi'          => $request->regulasi,
                        'kategori_regulasi' => $request->kategori_regulasi,
                        'parameter'         => $request->parameter,
                        'deskripsi'         => $request->deskripsi,
                    ];
                    $data->dokumentasi = $dokumentasiData;
                }
                // $data->nama_menu = $request->nama_menu;
                $data->nomor_ticket = $uniq_id;
                $message = 'Ticket Technical Control Telah Ditambahkan';
            }else{ //update
                $data= TicketTechnicalControl::where('id',$request->id)->first();
                if($request->kategori === 'Tanya regulasi'){ 
                    $filename = $microtime . '.txt';
                    // Ini HTML mentah dari Summernote
                    $content = $request->details; 
                    $contentDir = 'ticket_technical_control';

                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true);
                    
                    // Cek apakah content kosong untuk menghindari error DOMDocument
                    if (!empty($content)) {
                        $dom->loadHtml($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $images = $dom->getElementsByTagName('img');
                        
                        if (!file_exists(public_path($contentDir))) {
                            mkdir(public_path($contentDir), 0777, true);
                        }
                        
                        foreach($images as $k => $img){
                            $data_img = $img->getAttribute('src');
                            
                            // Cek apakah src mengandung base64
                            if(preg_match('/data:image/', $data_img)){
                                list($type, $data_img) = explode(';', $data_img);
                                list(, $data_img)      = explode(',', $data_img);
                                $data_img = base64_decode($data_img);

                                // Buat nama file unik untuk gambar
                                $imageName = time() . '_' . $k . '.png';
                                $path = public_path($contentDir . '/' . $imageName);

                                // Simpan file gambar fisik
                                file_put_contents($path, $data_img);
                                
                                // Ganti atribut src dari base64 menjadi URL gambar
                                $img->removeAttribute('src');
                                $img->setAttribute('src', \URL::asset($contentDir . '/' . $imageName));
                            }
                        }
                        // Ambil HTML yang sudah bersih dari base64
                        $cleanContent = $dom->saveHTML();
                    } else {
                        $cleanContent = '';
                    }

                    // KOREKSI: Simpan $cleanContent ke dalam file .txt, BUKAN $content
                    file_put_contents(public_path($contentDir . '/' . $filename), $cleanContent);

                    // JAWABAN: Susun array untuk disimpan ke dalam kolom JSON 'dokumentasi'
                    $dokumentasiData = [
                        'kategori'  => $request->kategori,
                        'regulasi'  => $request->regulasi,
                        'file_path' => $contentDir . '/' . $filename, // Path file .txt yang berisi HTML bersih
                    ];
                    
                    // Laravel akan otomatis mengkonversi array ini menjadi JSON 
                    // (asalkan di Model sudah di-cast: protected $casts = ['dokumentasi' => 'array'];)
                    $data->dokumentasi = $dokumentasiData;
                    $message = 'Ticket Technical Control Telah Diupdate';
                } else { 
                    // yang sudah berjalan
                    $dokumentasiData = [
                        'regulasi'          => $request->regulasi,
                        'kategori_regulasi' => $request->kategori_regulasi,
                        'parameter'         => $request->parameter,
                        'deskripsi'         => $request->deskripsi,
                    ];
                    $data->dokumentasi = $dokumentasiData;
                    $message = 'Ticket Technical Control Telah Diupdate';
                }
            }

            $data->status = 'WAITING PROCESS';
            $data->kategori = $request->kategori;

            if($this->grade == 'MANAGER' && $data->kategori == 'Minta Regulasi') {
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            $data->save();

            $user_programmer = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programmer)
                ->title('Ticket Technical Control !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . str_replace('_', ' ', $data->kategori))
                ->url('/ticket-technical-control')
                ->send();

            $getAtasan = GetAtasan::where('nama_lengkap', $this->karyawan)->get()->pluck('id');

            $isPerubahanData = $data->kategori == 'Minta Regulasi';
            Notification::whereIn('id', $getAtasan)
                ->title('Ticket technical Controll !')
                ->message($message . ' Oleh ' . $this->karyawan . ' Tingkat Masalah ' . str_replace('_', ' ', $data->category) . ($isPerubahanData ? ' Yang Harus Disetujui Oleh Atasan' : ''))
                ->url('/ticket-technical-control')
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
                'message' => 'Gagal Proses Ticket technical Controll: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveTicket(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $data = TicketTechnicalControl::find($request->id);
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');

            $data->save();

            $message = 'Ticket technical Controll telah diapprove oleh ' . $this->karyawan .' dan siap untuk diproses oleh tim IT';

            $user_programmer = MasterKaryawan::where('id_department', 7)
                ->whereNotIn('id', [10, 15, 93, 123])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            Notification::whereIn('id', $user_programmer)
                ->title('Ticket technical Controll Siap Diproses!')
                ->message($message)
                ->url('/ticket-programming')
                ->send();

            Notification::where('nama_lengkap', $data->created_by)
                ->title('Ticket technical Controll Update')
                ->message($message)
                ->url('/ticket-programming')
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
                'message' => 'Gagal Proses Ticket technical Controll: ' . $e->getMessage()
            ], 500);
        }
    }

   public function getParameter(Request $request)
    {
        try {
            $data = Parameter::with('hargaParameter')
                ->whereHas('hargaParameter')
                ->where('is_active', true)
                ->where('id_kategori', $request->id_kategori)
                ->select('id', 'nama_lab', 'nama_regulasi', 'nama_lhp', 'method', 'satuan','id_kategori')
                ->get();
    
            return response()->json([
                'message' => 'Data Parameter berhasil ditampilkan',
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['line'=>$th->getLine(),'file'=>$th->getFile,'message'=>$th->getMessage()],500);
        }
    }

    public function getKategoriRegulasi(Request $request)
    {
        try {
            $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
            return response()->json([
                'message' => 'Data Kategori berhasil ditampilkan',
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['line'=>$th->getLine(),'file'=>$th->getFile,'message'=>$th->getMessage()],500);
        }
    }
    public function getRegulasi(Request $request)
    {
        try {
            $data = MasterRegulasi::with(['bakumutu'])->where('is_active', true)->get();
            
            return response()->json(['message'=>'Data Regulasi Berhasil Di tampilkan','data'=>$data],200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['line'=>$th->getLine(),'file'=>$th->getFile,'message'=>$th->getMessage()],500);
        }
    }

    public function forward(Request $request)
    {
        try {
            //code...
            DB::transaction(function () use ($request) {
                $timestamp = DATE('Y-m-d H:i:s');
                if ($request->kategori === 'Minta Regulasi') {
                    // Ambil data ticket untuk mendapatkan info regulasi & parameter yang di-request
                    $ticket = TicketTechnicalControl::find($request->id);
    
                    if (!$ticket) {
                        throw new \Exception('Ticket tidak ditemukan');
                    }
    
                    // Parse dokumentasi/deskripsi ticket untuk ambil id regulasi & parameter
                    $ticketDetails    = $ticket->dokumentasi;
                    $inputRegulasi    = $ticketDetails['regulasi'] ?? null;
                    $parameterIds     = $ticketDetails['parameter'] ?? [];
                    $kategoriRegulasi = $ticketDetails['kategori_regulasi'] ?? null;
    
                    // Pastikan inputRegulasi tidak null atau kosong
                    if (empty($inputRegulasi)) {
                        throw new \Exception('Data Regulasi (ID atau Nama) tidak ditemukan di ticket');
                    }
    
                    $finalIdRegulasi = null;
                    // 1. Cari regulasi di database yang namanya sama persis dan is_active = 1
                    $existingRegulasi = MasterRegulasi::where('peraturan', $inputRegulasi)
                                                    ->where('id_kategori',$request->id_kategori_regulasi)
                                                    ->where('is_active', 1)
                                                    ->first();
                    // CEK LOGIKA: Apakah value berupa angka (termasuk string angka "001") atau bukan?
                    if ($existingRegulasi) {
                        // 2a. Jika ADA: Langsung gunakan ID-nya, tidak perlu insert baru
                        $finalIdRegulasi = $existingRegulasi->id;
                    } else {
                        // Jika bukan angka (berarti teks nama regulasi baru), Insert Regulasi Baru dulu
                        // Note: Sesuaikan 'MasterRegulasi' dan nama kolomnya dengan model di project Anda
                        $newRegulasi = MasterRegulasi::create([
                            'peraturan'     => $inputRegulasi, // Value teks tadi dijadikan nama regulasi
                            'nama_kategori' => $request->nama_kategori_regulasi,
                            'id_kategori' => $request->id_kategori_regulasi,
                            'deskripsi' => $request->deskripsi,
                            'created_by'        => $this->karyawan,
                            'created_at'        => $timestamp,
                        ]);
                        
                        // Dapatkan ID regulasi yang baru saja di-insert
                        $finalIdRegulasi = $newRegulasi->id; 
                    }
    
                    // --- Dari titik ini ke bawah, gunakan $finalIdRegulasi ---
    
                    // Ambil existing bakumutu berdasarkan regulasi ini
                    $existingBakumutuIds = MasterBakumutu::where('id_regulasi', $finalIdRegulasi)
                        ->pluck('id')
                        ->toArray();
    
                    // Loop parameter yang dikirim dari forward form
                    if ($request->has('forward_satuan') && is_array($request->forward_satuan)) {
                        foreach ($request->forward_satuan as $idParameter => $satuan) {
                            $bakumutuData = [
                                'id_regulasi'         => $finalIdRegulasi, // Gunakan ID final
                                'id_parameter'        => $idParameter,
                                'satuan'              => $satuan,
                                'method'              => $request->forward_method[$idParameter] ?? null,
                                'baku_mutu'           => ($request->forward_baku_mutu[$idParameter] ?? '') !== '' 
                                                            ? $request->forward_baku_mutu[$idParameter] 
                                                            : null,
                                'nama_header'         => $request->forward_nama_header[$idParameter] ?? null,
                                'durasi_pengukuran'   => $request->forward_durasi_pengukuran[$idParameter] ?? null,
                                'akreditasi'          => $request->forward_akreditasi[$idParameter] ?? null,
                            ];
    
                            // Selalu set created_by dan created_at untuk data baru
                            $bakumutuData['created_by'] = $this->karyawan;
                            $bakumutuData['created_at'] = $timestamp;
                            
                            MasterBakumutu::create($bakumutuData);    
                        }
                        
                    }
    
                    // Update status ticket menjadi PROCESS/DONE setelah forward berhasil

                    $ticket->status     = 'SOLVE';
                    $ticket->updated_by = $this->karyawan;
                    $ticket->updated_at = $timestamp;
                    $ticket->save();
                    
                }else{
                    $microtime = str_replace(".", "", microtime(true));
                    $uniq_id = $microtime;
                    $filename = $microtime . '.txt';
    
                    // Ini HTML mentah dari Summernote
                    $content = $request->jawab; 
                    $contentDir = 'ticket_technical_control_jawab';
    
                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true);
                    
                    // Cek apakah content kosong untuk menghindari error DOMDocument
                    if (!empty($content)) {
                        $dom->loadHtml($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $images = $dom->getElementsByTagName('img');
                        
                        if (!file_exists(public_path($contentDir))) {
                            mkdir(public_path($contentDir), 0777, true);
                        }
                        
                        foreach($images as $k => $img){
                            $data_img = $img->getAttribute('src');
                            
                            // Cek apakah src mengandung base64
                            if(preg_match('/data:image/', $data_img)){
                                list($type, $data_img) = explode(';', $data_img);
                                list(, $data_img)      = explode(',', $data_img);
                                $data_img = base64_decode($data_img);
    
                                // Buat nama file unik untuk gambar
                                $imageName = time() . '_' . $k . '.png';
                                $path = public_path($contentDir . '/' . $imageName);
    
                                // Simpan file gambar fisik
                                file_put_contents($path, $data_img);
                                
                                // Ganti atribut src dari base64 menjadi URL gambar
                                $img->removeAttribute('src');
                                $img->setAttribute('src', \URL::asset($contentDir . '/' . $imageName));
                            }
                        }
                        // Ambil HTML yang sudah bersih dari base64
                        $cleanContent = $dom->saveHTML();
                    } else {
                        $cleanContent = '';
                    }
    
                    // KOREKSI: Simpan $cleanContent ke dalam file .txt, BUKAN $content
                    file_put_contents(public_path($contentDir . '/' . $filename), $cleanContent);
    
                    // JAWABAN: Susun array untuk disimpan ke dalam kolom JSON 'dokumentasi'
                    $ticket = TicketTechnicalControl::find($request->id);
                    $dokumentasiPush = $ticket->dokumentasi;
                    $dokumentasiPush['jawab_path'] = $contentDir . '/' . $filename;
                    $ticket->dokumentasi = $dokumentasiPush;
                    $ticket->status      = 'SOLVE';
                    $ticket->solve_by  = $this->karyawan;
                    $ticket->solve_time  = $timestamp;
                    $ticket->save();
                }
            });
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'status'  => 'error',
                'line'  => $th->getLine(),
                'file'  => $th->getFile(),
                'message' => 'Gagal memproses ticket: ' . $th->getMessage()
            ], 500);
        }

        return response()->json(['message' => 'Ticket berhasil di-forward']);
    }
}
