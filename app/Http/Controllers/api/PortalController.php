<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SevenEcks\Tableify\Tableify;
use Telegram\Bot\Laravel\Facades\Telegram;
use Carbon\Carbon;
//models
use App\Models\{
    GenerateLink,
    QuotationNonKontrak,
    QuotationKontrakH,
    Barang,
    RecordPermintaanBarang,
    MasterKaryawan,
    KelengkapanKonfirmasiQs,
    SamplingPlan,
    Jadwal,
    Invoice,
    LhpsAirHeader,
    LhpsKebisinganHeader,
    LhpsPencahayaanHeader,
    LhpsIklimHeader,
    LhpUdaraPsikologiHeader,
    LhpsEmisiHeader,
    OrderDetail,
    Parameter,
    DraftErgonomiFile,
    LhpsAirCustom,
    LhpsAirDetail,
    MasterRegulasi,
    Printers
    
};
//services
use App\Services\{GeneratePraSampling, GetBawahan, LhpTemplate, SendTelegram, SamplingPlanServices, Printing, PrintLhp};
//jobs
use App\Jobs\{RenderSamplingPlan, JobPrintLhp};
use Illuminate\Support\Facades\Log;

class PortalController extends Controller
{
    public function ceklinkApi(Request $request)
    {
        try {
            if ($request->token != null) {

                $cek = GenerateLink::where('token', $request->token)
                    ->where('key', $request->key)
                    ->first();

                $uri = env('APP_URL');
                if ($cek != null) {
                    if ($request->mode == 'GETDATA') {
                        $combi = [];
                        $data = [];
                        if ($cek->quotation_status == 'non_kontrak') {

                            $data = QuotationNonKontrak::with([
                                'sampling' => function ($q) {
                                    $q->where('is_active', true);
                                },
                                'sampling.jadwal' => function ($q) {
                                    $q->where('is_active', true)->orderBy('tanggal');
                                },
                                'link',
                                'konfirmasi',
                                'orderD.orderDetail'
                            ])
                                ->select(['no_quotation', 'no_document', 'flag_status', 'id', 'pelanggan_ID', 'konfirmasi_order', 'is_ready_order'])
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();

                            if ($data) {
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = $data->sampling->isEmpty();
                                $data->typquot = 'nonkontrak';

                                $allJadwal = collect();
                                if ($data && !$data->chekjadwal) {
                                    foreach ($data->sampling as $sampling) {
                                        $jadwalAktif = $sampling->jadwal;
                                        if ($jadwalAktif->isNotEmpty()) {
                                            $tanggal = $jadwalAktif->pluck('tanggal')->unique()->sort()->values()->toArray();
                                            $jam_mulai = $jadwalAktif->min('jam_mulai');
                                            $jam_selesai = $jadwalAktif->max('jam_selesai');
                                            $id_sampling = $jadwalAktif->pluck('id_sampling')->unique()->values()->toArray();
                                            $value = [
                                                'tanggal' => $tanggal,
                                                'jam_mulai' => $jam_mulai,
                                                'jam_selesai' => $jam_selesai,
                                                'id_sampling' => $id_sampling
                                            ];
                                            $allJadwal->push($value);
                                        }
                                    }
                                }
                                $data->jadwal = $allJadwal;
                            }

                            if ($data && $data->orderD) {
                                $keteranganList = collect();
                                $keteranganList = $data->orderD->flatMap(function ($order) {
                                    return $order->orderDetail->pluck('keterangan_1');
                                })
                                    ->map(fn($keterangan) => trim($keterangan))
                                    ->filter(fn($val) => $val !== '')
                                    ->unique()
                                    ->values();

                                $keteranganArray = $keteranganList->toArray();

                                $data->penamaan_titik_sampling = $keteranganArray;
                            } else {
                                $data->penamaan_titik_sampling = [];
                            }

                            $uri = env('APP_URL') . '/public/quotation/';
                        } else if ($cek->quotation_status == 'kontrak') {
                            $data = QuotationKontrakH::with([
                                'sampling' => function ($q) {
                                    $q->where('is_active', true);
                                },
                                'sampling.jadwal' => function ($q) {
                                    $q->where('is_active', true)->orderBy('tanggal');
                                },
                                'detail',
                                'link',
                                'konfirmasi',
                                'orderD.orderDetail',
                                'detail:id,id_request_quotation_kontrak_h,periode_kontrak'
                            ])
                                ->select(['no_quotation', 'no_document', 'flag_status', 'id', 'pelanggan_ID', 'konfirmasi_order', 'is_ready_order'])
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();

                            if ($data) {
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = $data->sampling->isEmpty();
                                $data->typquot = 'kontrak';

                                $allJadwal = [];
                                $semuaPeriode = collect(); // kumpulan semua periode unik

                                if ($data->detail->isNotEmpty()) {
                                    $periodeH = $data->detail->pluck('periode_kontrak')->unique();
                                    $semuaPeriode = $periodeH->values(); // mulai dari periode detail
                                }

                                if ($data->sampling->isNotEmpty()) {
                                    $periodeSp = $data->sampling->pluck('periode_kontrak')->unique();
                                    $semuaPeriode = $semuaPeriode->merge($periodeSp)->unique()->values(); // gabungkan semua periode unik

                                    $mapById = collect();

                                    foreach ($data->sampling as $sampling) {
                                        $jadwalAktif = $sampling->jadwal->where('is_active', true);

                                        if ($jadwalAktif->isNotEmpty()) {
                                            // Simpan ke dalam map untuk akses parent parsial
                                            $mapById = $mapById->merge($jadwalAktif->keyBy('id'));

                                            foreach ($jadwalAktif as $jadwal) {
                                                // Ambil ID induk: kalau dia parsial, ambil parent-nya; kalau tidak, pakai dirinya
                                                $indukId = $jadwal->parsial ?? $jadwal->id;

                                                // Ambil periode dari induk
                                                $periodeInduk = isset($mapById[$jadwal->parsial]) ? $mapById[$jadwal->parsial]->periode : $jadwal->periode;

                                                // Inisialisasi array jika belum ada
                                                if (!isset($allJadwal[$periodeInduk])) {
                                                    $allJadwal[$periodeInduk] = [
                                                        'tanggal' => [],
                                                        'jam_mulai' => null,
                                                        'jam_selesai' => null,
                                                        'id_sampling' => [],
                                                    ];
                                                }

                                                // Gabungkan tanggal
                                                $allJadwal[$periodeInduk]['tanggal'][] = $jadwal->tanggal;

                                                // Ambil jam mulai paling awal dan jam selesai paling akhir
                                                if (
                                                    !$allJadwal[$periodeInduk]['jam_mulai'] ||
                                                    $jadwal->jam_mulai < $allJadwal[$periodeInduk]['jam_mulai']
                                                ) {
                                                    $allJadwal[$periodeInduk]['jam_mulai'] = $jadwal->jam_mulai;
                                                }

                                                if (
                                                    !$allJadwal[$periodeInduk]['jam_selesai'] ||
                                                    $jadwal->jam_selesai > $allJadwal[$periodeInduk]['jam_selesai']
                                                ) {
                                                    $allJadwal[$periodeInduk]['jam_selesai'] = $jadwal->jam_selesai;
                                                }

                                                if (!empty($jadwal->id_sampling)) {
                                                    $allJadwal[$periodeInduk]['id_sampling'] = array_unique(array_merge(
                                                        $allJadwal[$periodeInduk]['id_sampling'],
                                                        is_array($jadwal->id_sampling) ? $jadwal->id_sampling : [$jadwal->id_sampling]
                                                    ));
                                                }
                                            }
                                        }
                                    }

                                    // Bersihkan tanggal di setiap group
                                    foreach ($allJadwal as &$item) {
                                        $item['tanggal'] = array_values(array_unique($item['tanggal']));
                                    }
                                }

                                // Lengkapi semua periode dengan entri kosong jika belum ada
                                foreach ($semuaPeriode as $periodeItem) {
                                    if (!isset($allJadwal[$periodeItem])) {
                                        $allJadwal[$periodeItem] = [
                                            'tanggal' => [],
                                            'jam_mulai' => null,
                                            'jam_selesai' => null,
                                            'id_sampling' => [],
                                        ];
                                    }
                                }
                                //sentuhuan terakhir
                                foreach ($data->sampling as $sampling) {
                                    $periodeSampling = $sampling->periode_kontrak;

                                    // Pastikan periode itu sudah ada di allJadwal
                                    if (isset($allJadwal[$periodeSampling])) {
                                        // Cek jika belum ada id_sampling sama sekali di periode itu
                                        if (empty($allJadwal[$periodeSampling]['id_sampling'])) {
                                            $allJadwal[$periodeSampling]['id_sampling'][] = $sampling->id;
                                        }

                                        // Jika sudah ada, tapi belum termasuk ID ini, tambahkan juga
                                        elseif (!in_array($sampling->id, $allJadwal[$periodeSampling]['id_sampling'])) {
                                            $allJadwal[$periodeSampling]['id_sampling'][] = $sampling->id;
                                        }
                                    }
                                }
                                foreach ($allJadwal as $periode => &$info) {
                                    if (empty($info['tanggal']) && empty($info['id_sampling'])) {
                                        $info['status'] = 'baru'; // belum dijadwalkan sama sekali
                                    } elseif (empty($info['tanggal']) && !empty($info['id_sampling'])) {
                                        $info['status'] = 'proses'; // sampling sudah ada, tapi belum dijadwalkan
                                    } else {
                                        $info['status'] = 'selesai'; // sudah dijadwalkan
                                    }
                                }
                                $data->jadwal = $allJadwal;
                            }

                            if ($data && $data->orderD) {
                                $keteranganList = collect();
                                $keteranganList = $data->orderD->flatMap(function ($order) {
                                    return $order->orderDetail->pluck('keterangan_1');
                                })
                                    ->map(fn($keterangan) => trim($keterangan))
                                    ->filter(fn($val) => $val !== '')
                                    ->unique()
                                    ->values();

                                $periodeBelumKonfirmasi = $data->detail
                                    ->pluck('periode_kontrak')
                                    ->diff($data->konfirmasi->pluck('periode'))
                                    ->values();

                                $periode = $periodeBelumKonfirmasi
                                    ->map(function ($periodeKontrak) {
                                        $carbon = Carbon::createFromFormat('Y-m', $periodeKontrak)->locale('id');
                                        return [
                                            'id' => $periodeKontrak,
                                            'nama' => $carbon->translatedFormat('F Y'),
                                        ];
                                    })
                                    ->sortBy('id')
                                    ->values();

                                $data->penamaan_titik_sampling = $keteranganList->toArray();
                                $data->periode_kontrak = $periode->toArray();
                            } else {
                                $data->penamaan_titik_sampling = [];
                                $data->periode_kontrak = [];
                            }

                            $uri = env('APP_URL') . '/public/quotation/';
                        } else if ($cek->quotation_status == 'INVOICE') {
                            $data = Invoice::where('is_active', true)
                                ->where('invoice.id', $cek->id_quotation)
                                ->first();
                            if ($data) {
                                $data->flag_status = 'invoice';
                                $data->link->fileName_pdf = $cek->fileName_pdf;
                                $data->chekjadwal = null;
                            }
                            $uri = env('APP_URL') . '/public/invoice/';
                        } else if ($cek->quotation_status == 'promo') {
                            return response()
                                ->json(['message' => 'Document not valid anymmore', 'status' => '404'], 200);
                        } else if ($cek->quotation_status == 'draft_air') {
                            $data = LhpsAirHeader::with('link')
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();
                            $uri = env('APP_URL') . '/public/dokumen/LHPS/';
                            if ($data) {
                                $data->flag_status = 'draft';
                                $data->type = $cek->quotation_status;
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = null;
                            }
                        } else if ($cek->quotation_status == 'draft_kebisingan') {
                            $data = LhpsKebisinganHeader::with('link')
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();
                            $uri = env('APP_URL') . '/public/dokumen/LHPS/';
                            if ($data) {
                                $data->flag_status = 'draft';
                                $data->type = $cek->quotation_status;
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = null;
                            }
                        } else if ($cek->quotation_status == 'draft_pencahayaan') {
                            $data = LhpsPencahayaanHeader::with('link')
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();
                            $uri = env('APP_URL') . '/public/dokumen/LHPS/';
                            if ($data) {
                                $data->flag_status = 'draft';
                                $data->type = $cek->quotation_status;
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = null;
                            }
                        } else if ($cek->quotation_status == 'draft_emisi') {
                            $data = LhpsEmisiHeader::with('link')
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();
                            $uri = env('APP_URL') . '/public/dokumen/LHPS/';
                            if ($data) {
                                $data->flag_status = 'draft';
                                $data->type = $cek->quotation_status;
                                $data->chekjadwal = null;
                                // Pastikan relasi link tidak null sebelum mengakses fileName_pdf
                                if ($data->link) {
                                    $data->link->fileName_pdf = $cek->fileName_pdf;
                                }
                            }
                        } else if ($cek->quotation_status == 'lhp_psikologi') {
                            $data = LhpUdaraPsikologiHeader::with('link')
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();

                            $uri = env('APP_URL') . '/public/dokumen/LHP/';
                            if ($data) {
                                $data->flag_status = 'lhpp';
                                $data->type = $cek->quotation_status;
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = null;
                            }
                        } else if ($cek->quotation_status == 'draft_ergonomi') {
                            $data = DraftErgonomiFile::with('link', 'order_detail')
                                ->where('id', $cek->id_quotation)
                                ->first();
                            $uri = env('APP_URL') . '/public/draft_ergonomi/draft/';
                            if ($data !== null) {
                                $data->flag_status = 'draft';
                                $data->type = $cek->quotation_status;
                                $data->chekjadwal = null;
                                if ($data->link) {
                                    $data->link->fileName_pdf = $cek->fileName_pdf;
                                }
                                if ($data->order_detail->cfr) {
                                    $data->no_lhp = $data->order_detail->cfr;
                                }
                            }
                        } else if ($cek->quotation_status == 'draft_lhp_getaran'){
                            $data = LhpsGetaranHeader::with('link','order_detail')
                            ->where('id',$cek->id_quotation)
                            ->first();
                            $uri = env('APP_URL') . '/public/dokumen/LHPS/';
                            if($data !== null){
                                $data->type = $cek->quotation_status;
                                $data->chekjadwal =null;
                                if ($data->link) {
                                    $data->link->fileName_pdf = $cek->fileName_pdf;
                                }
                                if($data->order_detail->cfr){
                                    $data->no_lhp = $data->order_detail->cfr;
                                }
                            }
                        }else if ($cek->quotation_status == 'draft_iklim'){
                             $data = LhpsIklimHeader::with('link')
                                ->where('id', $cek->id_quotation)
                                ->where('is_active', true)
                                ->first();
                            $uri = env('APP_URL') . '/public/dokumen/LHPS/';
                            if ($data) {
                                $data->flag_status = 'draft';
                                $data->type = $cek->quotation_status;
                                $data->filename = $cek->fileName_pdf;
                                $data->chekjadwal = null;
                            }
                        }

                        if (DATE('Y-m-d') > $cek->expired) {
                            $link_lama = GenerateLink::where('token', $request->token)
                                ->first();
                            DB::table('expired_link_quotation')
                                ->insert([
                                    "token" => $link_lama->token,
                                    "key" => $link_lama->key,
                                    "id_quotation" => $link_lama->id_quotation,
                                    "quotation_status" => $link_lama->quotation_status,
                                    "expired" => $link_lama->expired,
                                    "password" => $link_lama->password,
                                    "fileName" => $link_lama->fileName,
                                    "fileName_pdf" => $link_lama->fileName_pdf,
                                    "type" => $link_lama->type,
                                    "created_at" => $link_lama->created_at,
                                    "created_by" => $link_lama->created_by,
                                    "status" => $link_lama->status,
                                    "is_reschedule" => $link_lama->is_reschedule
                                ]);

                            $link_lama->delete();
                            return response()
                                ->json(['message' => 'link has expired', 'status' => '300'], 200);
                        } else {
                            return response()
                                ->json(
                                    [
                                        'data' => $data,
                                        'message' => 'data hasbenn show',
                                        'qt_status' => $cek->quotation_status,
                                        'status' => '201',
                                        'uri' => $uri
                                    ],
                                    200
                                );
                        }
                    }
                } else {
                    return response()
                        ->json(['message' => 'Token not found.!', 'status' => '404'], 200);
                }
            } else {
                return response()
                    ->json(['message' => 'Token not found.!', 'status' => '404'], 200);
            }
        } catch (\Exception $ex) {
            //throw $th;
            return response()->json(["message" => $ex->getMessage(), "line" => $ex->getLine(), "file" => $ex->getFile()], 500);
        }
    }

    public function generateToken(Request $request)
    {

        if (!$request->token) {
            return response()->json(['message' => 'Token not found.!', 'status' => '404'], 401);
        }
        $token = $request->token;
        $expired = Carbon::now()->addMonths(3)->format('Y-m-d');
        $get_expired = DB::table('expired_link_quotation')
            ->where('token', $token)
            ->first();


        if ($get_expired) {
            $bodyToken = (object) $get_expired; // Mengubah $get_expired menjadi objek
            unset($bodyToken->id); // Menghilangkan id
            $bodyToken->expired = $expired; // Update value expired

            $id_quotation = $bodyToken->id_quotation;
            $quotation_status = $bodyToken->quotation_status;

            if ($quotation_status == 'non_kontrak') {
                $data = QuotationNonKontrak::where('id', $id_quotation)->first();
            } else if ($quotation_status == 'kontrak') {
                $data = QuotationKontrakH::where('id', $id_quotation)->first();
            }

            $bodyToken = (array) $bodyToken;
            if ($data) {
                $id_token = GenerateLink::insertGetId($bodyToken);
                $data->expired = $expired;
                $data->id_token = $id_token;
                $data->save();

                return response()->json(['message' => 'Token has been reactivated', 'status' => '200'], 200);
            } else {
                return response()->json(['message' => 'Token not found', 'status' => '404'], 200);
            }
        } else {
            return response()->json(['message' => 'Token not found', 'status' => '404'], 200);
        }
    }

    public function handlePengambilanBarang(Request $request)
    {
        switch ($request->mode) {
            case 'cektoken':
                $exp = \explode(".", $request->token);
                $db = DATE('Y', $exp[0]);

                $cek = DB::table('link_extend')->where('token', $request->token)->first();

                if ($cek != null) {
                    $cek_waktu = \date_diff(date_create($cek->create_date), date_create(DATE('Y-m-d H:i:s')));

                    if ($cek_waktu->i >= 5) {
                        //DB::table('link_extend')->where('token', $request->token)->delete();
                        return response()->json([
                            'message' => 'Token Expired.!',
                            'status' => 201
                        ], 200);
                    } else {
                        $dataUser = MasterKaryawan::with('divisi', 'jabatan')->where('id', $cek->user_id)->first();

                        $dataBarang = DB::table('barang')
                            ->where('is_active', 1)
                            ->where('akhir', '>', 0)
                            ->select('id', 'nama_barang', 'kode_barang', 'merk', 'ukuran', 'satuan')
                            ->get();


                        return response()->json([
                            'message' => 'Data Accepted.!',
                            'karyawan' => $dataUser,
                            'barang' => $dataBarang,
                            'status' => 200

                        ], 200);
                    }
                } else {
                    //token not found
                    return response()->json([
                        'message' => 'Token Not Found.!',
                        'status' => 203
                    ], 200);
                }
            case 'write':
                if (isset($request->id_cabang)) {
                    try {
                        DB::beginTransaction();
                        $request_id = \str_replace(".", "/", microtime(true));

                        $message = "Request-ID : " . $request_id . "\n";
                        $QEY = [
                            ['No', 'Kode', 'Nama Barang', 'Qty'],
                        ];

                        foreach ($request->barang as $key => $value) {
                            $cekBarang = DB::table('barang')->where('id', $value)->first();
                            $cekUser = DB::table('master_karyawan')->where('id', $request->userid)->first();
                            $body = [
                                'id_cabang' => 1, //$request->id_cabang    untuk menentukan cabang
                                'request_id' => $request_id,
                                'timestamp' => DATE('Y-m-d H:i:s'),
                                'id_user' => $request->userid,
                                'nama_karyawan' => $request->nama_lengkap,
                                'divisi' => $request->divisi_karyawan,
                                'id_kategori' => $cekBarang->id_kategori,
                                'id_barang' => $value,
                                'kode_barang' => $cekBarang->kode_barang,
                                'nama_barang' => $cekBarang->nama_barang,
                                'jumlah' => $request->jumlah[$key],
                                'keterangan' => $request->keperluan,
                            ];
                            DB::table('record_permintaan_barang')->insert($body);
                            array_push($QEY, array(($key + 1), $cekBarang->kode_barang, $cekBarang->nama_barang, $request->jumlah[$key]));
                        }
                        //DB::table('link_extend')->where('token', $request->token)->delete();

                        $table = Tableify::new($QEY);
                        $table = $table->make();
                        $table_data = $table->toArray();
                        foreach ($table_data as $row) {
                            $message .= $row . "\n";
                        }
                        $message .= 'Status Request Waiting Prosess';
                        $message = "<pre>" . $message . "</pre>";
                        $cekTele = DB::table('tele_chat')->where('pin_from', $cekUser->pin_user)->orderBy('id', 'DESC')->first();
                        self::update($cekUser->pin_user, $cekTele->message_id, $message);
                        DB::commit();
                        return response()->json([
                            'message' => 'Data hasbeen Save.!'
                        ], 200);
                    } catch (\Exception $ex) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Error : ' . $ex->getMessage(),
                            'line' => $ex->getLine(),
                            'file' => $ex->getFile()
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'message' => 'Missing Cabang.!'
                    ], 401);
                }
        }
    }

    public function update($chatID = null, $message_id = null, $message = '', $keyboard = array())
    {
        if ($keyboard != null) {
            $member = ['-1002229600148', '-1002197513895'];

            $telegram = new SendTelegram();
            $telegram = SendTelegram::text($message)
                ->to($chatID)->send();
            // return Telegram::send([
            //     'chat_id' => $chatID,
            //     'text' => $message,
            //     'parse_mode' => 'HTML',
            //     'message_id' => $message_id,
            //     'reply_markup' => $keyboard
            // ]);
        } else {
            // return Telegram::editMessageText([
            //     'chat_id' => $chatID,
            //     'message_id' => $message_id,
            //     'text' => $message,
            //     'parse_mode' => 'HTML'
            // ]);
            $telegram = new SendTelegram();
            $telegram = SendTelegram::text($message)
                ->to($chatID)->send();
        }
    }

    public function generatePraNoSample(Request $request)
    {
        DB::beginTransaction();
        try {
            $parse = new GeneratePraSampling;
            $parse->type($request->type);
            $parse->where('no_qt_baru', $request->no_document);
            $parse->where('generate', 'new');
            $parse->save();

            DB::commit();
            return response()->json([
                'message' => 'success',
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error : ' . $th->getMessage(),
            ], 500);
        }
    }

    public function reschadule(Request $request)
    {
        try {

            $type = explode('/', $request->no_qt);
            if ($type[1] === 'QTC') {
                $data = QuotationKontrakH::with([
                    'sampling' => function ($q) {
                        $q->where('is_active', true);
                    },
                    'sampling.jadwal' => function ($q) {
                        $q->where('is_active', true)->orderBy('tanggal');
                    },
                    'detail',
                    'orderD.orderDetail',
                    'link'
                ])
                    ->where('no_document', $request->no_qt)
                    ->where('is_active', true)
                    ->first();
                $allJadwal = [];
                $semuaPeriode = collect(); // kumpulan semua periode unik

                if ($data->detail->isNotEmpty()) {
                    $periodeH = $data->detail->pluck('periode_kontrak')->unique();
                    $semuaPeriode = $periodeH->values(); // mulai dari periode detail
                }

                if ($data->sampling->isNotEmpty()) {
                    $periodeSp = $data->sampling->pluck('periode_kontrak')->unique();
                    $semuaPeriode = $semuaPeriode->merge($periodeSp)->unique()->values(); // gabungkan semua periode unik

                    $mapById = collect();

                    foreach ($data->sampling as $sampling) {
                        $jadwalAktif = $sampling->jadwal->where('is_active', true);

                        if ($jadwalAktif->isNotEmpty()) {
                            // Simpan ke dalam map untuk akses parent parsial
                            $mapById = $mapById->merge($jadwalAktif->keyBy('id'));

                            foreach ($jadwalAktif as $jadwal) {
                                // Ambil ID induk: kalau dia parsial, ambil parent-nya; kalau tidak, pakai dirinya
                                $indukId = $jadwal->parsial ?? $jadwal->id;

                                // Ambil periode dari induk
                                $periodeInduk = isset($mapById[$jadwal->parsial]) ? $mapById[$jadwal->parsial]->periode : $jadwal->periode;

                                // Inisialisasi array jika belum ada
                                if (!isset($allJadwal[$periodeInduk])) {
                                    $allJadwal[$periodeInduk] = [
                                        'tanggal' => [],
                                        'jam_mulai' => null,
                                        'jam_selesai' => null,
                                        'id_sampling' => [],
                                    ];
                                }

                                // Gabungkan tanggal
                                $allJadwal[$periodeInduk]['tanggal'][] = $jadwal->tanggal;

                                // Ambil jam mulai paling awal dan jam selesai paling akhir
                                if (
                                    !$allJadwal[$periodeInduk]['jam_mulai'] ||
                                    $jadwal->jam_mulai < $allJadwal[$periodeInduk]['jam_mulai']
                                ) {
                                    $allJadwal[$periodeInduk]['jam_mulai'] = $jadwal->jam_mulai;
                                }

                                if (
                                    !$allJadwal[$periodeInduk]['jam_selesai'] ||
                                    $jadwal->jam_selesai > $allJadwal[$periodeInduk]['jam_selesai']
                                ) {
                                    $allJadwal[$periodeInduk]['jam_selesai'] = $jadwal->jam_selesai;
                                }

                                if (!empty($jadwal->id_sampling)) {
                                    $allJadwal[$periodeInduk]['id_sampling'] = array_unique(array_merge(
                                        $allJadwal[$periodeInduk]['id_sampling'],
                                        is_array($jadwal->id_sampling) ? $jadwal->id_sampling : [$jadwal->id_sampling]
                                    ));
                                }
                            }
                        }
                    }

                    // Bersihkan tanggal di setiap group
                    foreach ($allJadwal as &$item) {
                        $item['tanggal'] = array_values(array_unique($item['tanggal']));
                    }
                }

                // Lengkapi semua periode dengan entri kosong jika belum ada
                foreach ($semuaPeriode as $periodeItem) {
                    if (!isset($allJadwal[$periodeItem])) {
                        $allJadwal[$periodeItem] = [
                            'tanggal' => [],
                            'jam_mulai' => null,
                            'jam_selesai' => null,
                            'id_sampling' => [],
                        ];
                    }
                }
                //sentuhan terakhir
                foreach ($data->sampling as $sampling) {
                    $periodeSampling = $sampling->periode_kontrak;

                    // Pastikan periode itu sudah ada di allJadwal
                    if (isset($allJadwal[$periodeSampling])) {
                        // Cek jika belum ada id_sampling sama sekali di periode itu
                        if (empty($allJadwal[$periodeSampling]['id_sampling'])) {
                            $allJadwal[$periodeSampling]['id_sampling'][] = $sampling->id;
                        }

                        // Jika sudah ada, tapi belum termasuk ID ini, tambahkan juga
                        elseif (!in_array($sampling->id, $allJadwal[$periodeSampling]['id_sampling'])) {
                            $allJadwal[$periodeSampling]['id_sampling'][] = $sampling->id;
                        }
                    }
                }

                foreach ($allJadwal as $periode => &$info) {
                    if (empty($info['tanggal']) && empty($info['id_sampling'])) {
                        $info['status'] = 'baru'; // belum dijadwalkan sama sekali
                    } elseif (empty($info['tanggal']) && !empty($info['id_sampling'])) {
                        $info['status'] = 'proses'; // sampling sudah ada, tapi belum dijadwalkan
                    } else {
                        $info['status'] = 'selesai'; // sudah dijadwalkan
                    }
                }

                return response()->json($allJadwal, 200);
            } else {
                $data = QuotationNonKontrak::with([
                    'sampling' => function ($q) {
                        $q->where('is_active', true);
                    },
                    'sampling.jadwal' => function ($q) {
                        $q->where('is_active', true)->orderBy('tanggal');
                    },
                    'link'
                ])
                    ->where('no_document', $request->no_qt)
                    ->where('is_active', true)
                    ->first();

                $allJadwal = collect();
                if ($data && $data->sampling->isNotEmpty()) {
                    foreach ($data->sampling as $sampling) {
                        $jadwalAktif = $sampling->jadwal;
                        if ($jadwalAktif->isNotEmpty()) {
                            $tanggal = $jadwalAktif->pluck('tanggal')->unique()->sort()->values()->toArray();
                            $jam_mulai = $jadwalAktif->min('jam_mulai');
                            $jam_selesai = $jadwalAktif->max('jam_selesai');
                            $id_sampling = $jadwalAktif->pluck('id_sampling')->unique()->values()->toArray();

                            $value = [
                                'tanggal' => $tanggal,
                                'jam_mulai' => $jam_mulai,
                                'jam_selesai' => $jam_selesai,
                                'id_sampling' => $id_sampling
                            ];

                            $allJadwal->push($value);
                        }
                    }
                }
                return response()->json($allJadwal, 200);
            }
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine()]);
        }
    }

    public function rescheduleSamplingPlan(Request $request)
    {

        try {
            $dataArray = (object) [
                "no_quotation" => $request->no_quotation,
                "quotation_id" => $request->quotation_id,
                "karyawan" => 'user',
                "tanggal_sampling" => $request->tanggal_sampling,
                "jam_sampling" => $request->jam_sampling,
                "tambahan" => $request->tambahan,
                "keterangan_lain" => $request->keterangan_lain,
                "is_sabtu" => $request->is_sabtu,
                "is_minggu" => $request->is_minggu,
                "is_malam" => $request->is_malam,
                "mode" => $request->mode
            ];

            if ($request->periode) {

                if ($request->id_sampling != null && $request->id_sampling != '') {
                    $dataArray->id_sampling = $request->id_sampling;
                    $dataArray->periode = $request->periode;
                    $spServices = SamplingPlanServices::on('insertSingleKontrak', $dataArray)->insertSPSingleKontrakPortal();
                } else {
                    $dataArray->periode = $request->periode;
                    $spServices = SamplingPlanServices::on('insertKontrak', $dataArray)->insertSPKontrakPortal();
                }
            } else {
                if ($request->id_sampling != null && $request->id_sampling != '') {
                    $dataArray->id_sampling = $request->id_sampling;
                    $spServices = SamplingPlanServices::on('insertSingleNon', $dataArray)->insertSPSinglePortal();
                    return response()->json($spServices, 200);
                } else {
                    $spServices = SamplingPlanServices::on('insertNon', $dataArray)->insertSPPortal();
                    return response()->json($spServices, 200);
                }
            }

            if ($spServices) {
                $type = explode('/', $request->no_quotation)[1];
                if ($type == 'QTC') {
                    $status_quotation = 'kontrak';
                    $job = new RenderSamplingPlan($request->quotation_id, $status_quotation);
                } else {
                    $status_quotation = 'non_kontrak';
                    $job = new RenderSamplingPlan($request->quotation_id, $status_quotation);
                }
                $this->dispatch($job);

                return response()->json(['message' => 'Reschedule Request Sampling Plan Success', 'status' => 200], 200);
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Reschedule Request Sampling Plan Failed: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'status' => 401], 401);
        }
    }

    public function getDetailQuotation(Request $request)
    {
        $mode = explode('/', $request->no_quotation)[1];
        if ($mode == 'QTC') {
            $data = QuotationKontrakH::with('konfirmasi', 'orderD.orderDetail')
                ->where('no_document', $request->no_quotation)
                ->where('flag_status', 'sp')
                ->where('konfirmasi_order', false)
                ->where('is_ready_order', true)
                ->where('is_active', true)
                ->first();
        } else {
            $data = QuotationNonKontrak::with('konfirmasi', 'orderD.orderDetail')
                ->where('no_document', $request->no_quotation)
                ->where('flag_status', 'sp')
                ->where('konfirmasi_order', false)
                ->where('is_ready_order', true)
                ->where('is_active', true)
                ->first();
        }

        $keteranganList = collect();
        if ($data && $data->orderD) {
            $keteranganList = $data->orderD->flatMap(function ($order) {
                return $order->orderDetail->pluck('keterangan_1');
            })
                ->map(fn($keterangan) => trim($keterangan))
                ->filter(fn($val) => $val !== '')
                ->unique()
                ->values();

            $keteranganArray = $keteranganList->toArray();

            $data->penamaan_titik_sampling = $keteranganArray;
        } else {
            $data->penamaan_titik_sampling = [];
        }

        if ($data) {
            return response()->json($data, 200);
        } else {
            return response()->json('Data tidak ditemukan', 404);
        }
    }

    public function storeKonfirmasiOrder(Request $request)
    {
        return response()->json($request->all());

        DB::beginTransaction();
        try {
            $type = explode('/', $request->no_quotation)[1];
            if ($type == 'QTC') {
                $data = QuotationKontrakH::where('no_document', $request->no_quotation)->first();
            } else {
                $data = QuotationNonKontrak::where('no_document', $request->no_quotation)->first();
            }

            if (!$data) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            $konfirmasi = new KelengkapanKonfirmasiQs();

            $fileNames = [];
            $lampiranFiles = [];
            $path = 'konfirmasi_order/';
            $savePath = public_path($path);

            if (!file_exists($savePath)) {
                mkdir($savePath, 0777, true);
            }

            // Handle filename
            if (isset($request->filename)) {
                $base64Files = is_array($request->filename) ? $request->filename : [$request->filename];

                foreach ($base64Files as $base64File) {
                    $fileData = $this->extractBase64FileData($base64File);

                    if (!$fileData) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Format base64 tidak valid pada dokumen utama.'
                        ], 422);
                    }

                    $fileType = $fileData['type'];
                    $fileExtension = $fileData['extension'];
                    $fileContent = $fileData['content'];

                    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

                    if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Format file tidak didukung pada dokumen utama. Hanya PDF, JPG, JPEG, dan PNG yang diizinkan.'
                        ], 422);
                    }

                    $fileName = $this->generateFileName($fileExtension, 'main');

                    file_put_contents($savePath . '/' . $fileName, base64_decode($fileContent));
                    $fileNames[] = $fileName;
                }
            }

            // Handle lampiran (optional files - additional attachments)
            if (isset($request->lampiran)) {
                $base64Lampiran = is_array($request->lampiran) ? $request->lampiran : [$request->lampiran];

                foreach ($base64Lampiran as $base64File) {
                    $fileData = $this->extractBase64FileData($base64File);

                    if (!$fileData) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Format base64 tidak valid pada lampiran.'
                        ], 422);
                    }

                    $fileType = $fileData['type'];
                    $fileExtension = $fileData['extension'];
                    $fileContent = $fileData['content'];

                    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

                    if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Format file tidak didukung pada lampiran. Hanya PDF, JPG, JPEG, dan PNG yang diizinkan.'
                        ], 422);
                    }

                    $fileName = $this->generateFileName($fileExtension, 'lampiran');

                    file_put_contents($savePath . '/' . $fileName, base64_decode($fileContent));
                    $lampiranFiles[] = $fileName;
                }
            }

            $this->fillKonfirmasiData($konfirmasi, $request, $data, $fileNames, $lampiranFiles);
            $konfirmasi->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan',
                'data' => $konfirmasi,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    protected function extractBase64FileData($base64String)
    {
        if (strpos($base64String, ';base64,') !== false) {
            list($fileInfo, $fileContent) = explode(';base64,', $base64String);
            list(, $fileType) = explode(':', $fileInfo);

            $fileExtension = $this->getExtensionFromMimeType($fileType);

            return [
                'type' => $fileType,
                'extension' => $fileExtension,
                'content' => $fileContent
            ];
        } else {
            $decodedData = base64_decode($base64String);
            $fileType = $this->detectFileTypeFromContent($decodedData);

            return [
                'type' => $fileType['mime'],
                'extension' => $fileType['extension'],
                'content' => $base64String
            ];
        }
    }

    protected function detectFileTypeFromContent($data)
    {
        if (strpos($data, '%PDF') === 0) {
            return ['mime' => 'application/pdf', 'extension' => 'pdf'];
        }

        if (substr($data, 0, 3) === "\xFF\xD8\xFF") {
            return ['mime' => 'image/jpeg', 'extension' => 'jpg'];
        }

        if (substr($data, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
            return ['mime' => 'image/png', 'extension' => 'png'];
        }

        return ['mime' => 'application/pdf', 'extension' => 'pdf'];
    }

    protected function getExtensionFromMimeType($mimeType)
    {
        $mimeExtensionMap = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
        ];

        return $mimeExtensionMap[$mimeType] ?? pathinfo(parse_url($mimeType, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'pdf';
    }

    public function generateFileName($extension, $type = 'main')
    {
        $dateMonth = str_pad(date('m'), 2, '0', STR_PAD_LEFT) . str_pad(date('d'), 2, '0', STR_PAD_LEFT);

        $prefix = $type === 'lampiran' ? 'lampiran_titik' : 'konfirmasi';
        $fileName = $prefix . "_" . $dateMonth . "_" . microtime(true) . "." . $extension;
        $filename = str_replace(' ', '_', $fileName);

        return $filename;
    }

    private function fillKonfirmasiData($konfirmasi, $request, $data, $fileNames, $lampiranFiles = [])
    {
        $penamaan_titik_sampling = [];
        if (!empty($request->titik_sampling) && is_array($request->titik_sampling)) {
            $filtered = array_filter(array_map(function ($item) {
                return is_string($item) ? trim($item) : $item;
            }, $request->titik_sampling), function ($item) {
                return !is_null($item) && $item !== '';
            });

            if (!empty($filtered)) {
                $penamaan_titik_sampling = json_encode(array_values($filtered));
            }
        }

        $konfirmasi->periode = isset($request->periode) ? $request->periode : null;
        $konfirmasi->approval_order = $request->approval_order;
        $konfirmasi->filename = json_encode($fileNames);
        $konfirmasi->lampiran_titik = !empty($lampiranFiles) ? json_encode($lampiranFiles) : [];
        $konfirmasi->keterangan_approval_order = isset($request->menyusul) ? 'menyusul' : $request->tanggal;
        $konfirmasi->status_bap = isset($request->sudah_ada_bap) ? 1 : 0;
        $konfirmasi->nama_pic_bap = $request->nama_pic_bap ? trim($request->nama_pic_bap) : null;
        $konfirmasi->jabatan_pic_bap = $request->jabatan_pic_bap ? trim($request->jabatan_pic_bap) : null;
        $konfirmasi->penggabungan_lhp = isset($request->digabung) ? 1 : 0;
        $konfirmasi->keterangan_penggabungan_lhp = $request->keterangan_penggabungan ? trim($request->keterangan_penggabungan) : null;
        $konfirmasi->penamaan_titik_sampling = $penamaan_titik_sampling;
        $konfirmasi->no_quotation = $data->no_document;
        $konfirmasi->id_quotation = $data->id;
        $konfirmasi->type = explode('/', $request->no_quotation)[1] == 'QTC' ? 'kontrak' : 'non_kontrak';
    }

    public function handlePrintLhp(Request $request)
    {
        // $job = new JobPrintLhp($request->no_sampel);
        $services = new PrintLhp();
        try {
            // $run = $this->dispatch($job);
            $run = $services->print($request->no_sampel);
            if (!$run) {
                return response()->json(['message' => 'Failed to dispatch printing job', 'status' => '401'], 200);
            }
            return response()->json(['message' => 'Printing LHP job has been dispatched successfully', 'status' => '201'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to dispatch printing job: ' . $e->getMessage(), 'status' => '401'], 200);
        }
    }

    public function renderPenawaran(Request $request)
    {
        dd('belum kelar bos');
    }

    public function renderLhp(Request $request)
    {
        $orderDetails = OrderDetail::select('id', 'cfr', 'no_sampel', 'kategori_2', 'kategori_3')
            ->with(['lhps_air', 'lhps_emisi', 'lhps_emisi_c', 'lhps_getaran', 'lhps_kebisingan', 'lhps_ling', 'lhps_medanlm', 'lhps_pencahayaan', 'lhps_sinaruv', 'lhps_iklim', 'lhps_ergonomi'])
            ->where(['cfr' => $request->lhp_number, 'is_active' => true])
            ->get();

        $grouped = $orderDetails->map(function ($detail) {
            $validLhps = collect([$detail->lhps_air, $detail->lhps_emisi, $detail->lhps_emisi_c, $detail->lhps_getaran, $detail->lhps_kebisingan, $detail->lhps_ling, $detail->lhps_medanlm, $detail->lhps_pencahayaan, $detail->lhps_sinaruv, $detail->lhps_iklim, $detail->lhps_ergonomi])
                ->flatten(1)
                ->filter(fn($lhp) => $lhp && $lhp->is_active == 1 && $lhp->nama_karyawan && $lhp->jabatan_karyawan && $lhp->file_qr && $lhp->file_lhp && $lhp->tanggal_lhp);

            return [
                'kategori_2' => $detail->kategori_2,
                'kategori_3' => $detail->kategori_3,
                'lhps' => $validLhps->sortByDesc('id')->values(),
            ];
        })->filter(fn($row) => $row['lhps']->isNotEmpty())->first();

        if (!$grouped) return response()->json(['message' => 'LHP tidak ditemukan atau belum dirilis'], 404);

        $lhps = $grouped['lhps']->first();
        $kategori2 = $grouped['kategori_2'];
        $kategori3 = $grouped['kategori_3'];
        $lampiran = false;
        $view = null;
        $detail = null;
        $custom = null;

        if ($kategori2 == '1-Air') {
            $view = 'DraftAir';
            $detail = $lhps->lhpsAirDetail;
            $custom = $lhps->lhpsAirCustom;
        }

        if ($kategori2 == '4-Udara') {
            $lampiran = true;
            $parameter = json_decode($lhps->parameter_uji) ?: [];

            if (in_array($kategori3, ['11-Udara Ambient', '27-Udara Lingkungan Kerja'])) {
                if (in_array("Sinar UV", $parameter)) {
                    $view = 'DraftUlkSinarUv';
                    $detail = $lhps->lhpsSinaruvDetail;
                    $custom = $lhps->lhpsSinaruvCustom;
                } elseif (array_intersect($parameter, ["Medan Magnit Statis", "Medan Listrik", "Power Density"])) {
                    $view = 'DraftUlkMedanMagnet';
                    $detail = $lhps->lhpsMedanLMDetail;
                    $custom = $lhps->lhpsMedanLMCustom;
                } else {
                    $view = 'DraftUdaraAmbient';
                    $detail = $lhps->lhpsLingDetail;
                    $custom = $lhps->lhpsLingCustom;
                }
            } elseif ($kategori3 == '28-Pencahayaan') {
                $view = 'DraftPencahayaan';
                $detail = $lhps->lhpsPencahayaanDetail;
                $custom = $lhps->lhpsPencahayaanCustom;
            } elseif (in_array($kategori3, ['23-Kebisingan', '24-Kebisingan (24 Jam)', '25-Kebisingan (Indoor)'])) {
                $regulasi = json_decode($lhps->regulasi);
                $idRegulasi = $regulasi ? explode('-', $regulasi[0])[0] : '';
                $view = 'DraftKebisingan';

                if (in_array($idRegulasi, [54, 151, 167, 168, 382])) {
                    $masterRegulasi = MasterRegulasi::find($idRegulasi);
                    $deskripsi = $masterRegulasi ? $masterRegulasi->deskripsi : '';

                    $view = $deskripsi == 'Kebisingan LH - 24 Jam' ? 'DraftKebisinganLh24Jam' : 'DraftKebisinganLh';
                }

                $detail = $lhps->lhpsKebisinganDetail;
                $custom = $lhps->lhpsKebisinganCustom;
            } elseif ($kategori3 == '21-Iklim Kerja') {
                $view = array_intersect($parameter, ["ISBB", "ISBB (8 Jam)"]) ? 'DraftIklimPanas' : 'DraftIklimDingin';
                $detail = $lhps->lhpsIklimDetail;
                $custom = $lhps->lhpsIklimCustom;
            } elseif (in_array($kategori3, ['13-Getaran', '14-Getaran (Bangunan)', '15-Getaran (Kejut Bangunan)', '18-Getaran (Lingkungan)', '19-Getaran (Mesin)'])) {
                $view = 'DraftGetaran';
                $detail = $lhps->lhpsGetaranDetail;
                $custom = $lhps->lhpsGetaranCustom;
            } elseif (in_array($kategori3, ['17-Getaran (Lengan & Tangan)', '20-Getaran (Seluruh Tubuh)'])) {
                $view = 'DraftGetaranPersonal';
                $detail = $lhps->lhpsGetaranDetail;
                $custom = $lhps->lhpsGetaranCustom;
            }
        }

        if ($kategori2 == '5-Emisi') {
            if ($kategori3 == '34-Emisi Sumber Tidak Bergerak') {
                $view = 'DraftEmisiC';
                $detail = $lhps->lhpsEmisiCDetail;
                $custom = $lhps->lhpsEmisiCCustom;
            } else {
                $view = 'DraftEmisi';
                $detail = $lhps->lhpsEmisiDetail;
                $custom = $lhps->lhpsEmisiCustom;
            }
        }

        if (!$view) return response()->json(['message' => 'LHP tidak ditemukan atau belum dirilis'], 404);

        $groupedByPage = collect($custom)->groupBy('page')->toArray();

        $fileName = LhpTemplate::setDataDetail($detail)
            ->setDataHeader($lhps)
            ->setDataCustom($groupedByPage)
            ->whereView($view)
            ->useLampiran($lampiran)
            ->render();

        return response()->json(['file' => $fileName], 200);
    }

    public function renderInvoice(Request $request)
    {
        dd('belum kelar bos');
    }
}
