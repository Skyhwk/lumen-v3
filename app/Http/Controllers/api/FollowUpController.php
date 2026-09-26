<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

// use Datatables;
use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\DFUS;
use App\Models\DFUSKeterangan;
use App\Models\KontakPelangganBlacklist;
use App\Models\LogWebphone;
use App\Models\OrderHeader;
use App\Models\MasterPelanggan;
use App\Models\MasterKaryawan;
use App\Models\MasterPelangganBlacklist;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Yajra\DataTables\DataTables as DataTables;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    public function index(Request $request)
    {
        $pelanggan = MasterPelanggan::with('kontak_pelanggan')->where('is_active', true);
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        switch ($jabatan) {
            case 24: // Sales Staff
                $pelanggan->where('sales_id', $this->user_id);
                break;

            case 148:
                $pelanggan->where('sales_id', $this->user_id);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $pelanggan->whereIn('sales_id', $bawahan);
                break;
        }

        $pelanggan = $pelanggan->orderBy('master_pelanggan.id', 'desc');

        return Datatables::of($pelanggan)
            ->filterColumn('kontak_pelanggan', function ($query, $keyword) {
                $query->whereHas('kontak_pelanggan', function ($q) use ($keyword) {
                    $q->where('no_tlp_perusahaan', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function getCustomerContact(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'pic_pelanggan'])->where('id', $request->id)->first();

        return response()->json(['data' => $data], 200);
    }

    public function randomstr($str, $no)
    {
        $str = str_replace(["'", '"', '+', '-', '=', ')', '(', '`', '~', '?', '/', '.', ',', '>', '<', ':', ';', '|', '!', '@', '#', '$', '%', '^', '&', '*', '[', ']', '{', '}'], '', str_replace([' ', '\t', ','], '', $str));
        return substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
    }

    private function checkForBlacklistedCustomer($nama_pelanggan, $kontak_pelanggan)
    {
        $blacklistedByName = MasterPelangganBlacklist::where('nama_pelanggan', $nama_pelanggan)->exists();
        if ($blacklistedByName) return response()->json(['message' => 'Pelanggan dengan nama: ' . $nama_pelanggan . ' telah terdaftar di daftar hitam'],  401);

        if ($kontak_pelanggan) {
            $kontak_pelanggan = preg_replace("/[^0-9]/", "", $kontak_pelanggan);

            if (substr($kontak_pelanggan, 0, 2) === "62") {
                $kontak_pelanggan = "0" . substr($kontak_pelanggan, 2);
            }

            $blacklistedByTelNumber = KontakPelangganBlacklist::where('no_tlp_perusahaan', $kontak_pelanggan)->exists();
            if ($blacklistedByTelNumber) return response()->json(['message' => 'Pelanggan dengan nomor telepon: ' . $kontak_pelanggan . ' telah terdaftar di daftar hitam'], 401);
        }
    }

    public function saveFollowUp(Request $request)
    {
        $response = $this->checkForBlacklistedCustomer($request->nama_pelanggan, $request->no_tlp_perusahaan);
        if ($response) return $response;

        // Generate no_urut
        $lastPelanggan = MasterPelanggan::orderBy('no_urut', 'desc')->first();
        $noUrut = str_pad($lastPelanggan ? (int) $lastPelanggan->no_urut + 1 : 1, 5, '0', STR_PAD_LEFT);

        // Generate id_pelanggan
        $timestamp = DATE('Y-m-d H:i:s');
        $namaPelangganUpper = strtoupper(str_replace([' ', '\t', ','], '', $request->nama_pelanggan));
        $idPelanggan = null;
        for ($i = 1; $i <= 10; $i++) {
            $generatedId = $this->randomstr($namaPelangganUpper, $i);
            if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                $idPelanggan = $generatedId;
                break;
            }
        }

        $no_tlp_perusahaan = preg_replace("/[^0-9]/", "", $request->no_tlp_perusahaan); // bersihin non-angka

        if (substr($no_tlp_perusahaan, 0, 2) === "62") { // convert depannya jadi 0
            $no_tlp_perusahaan = "0" . substr($no_tlp_perusahaan, 2);
        }

        // Menghapus PT, CV, UD, PD, Koperasi, Perum, Persero, BUMD, Yayasan (beserta variasi di belakang nama pelanggan)
        $clearNamaPelanggan = preg_replace('/(,?\s*\.?\s*(PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN))\s*$/i', '', $request->nama_pelanggan);

        // Cek duplikasi berdasarkan nama pelanggan dan no kontak
        // $existingData = MasterPelanggan::where('nama_pelanggan', $request->nama_pelanggan)
        //     ->orWhere('id_pelanggan', $idPelanggan)
        //     ->orWhereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
        //         $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
        //     })->first();

        $existingData = MasterPelanggan::whereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
            $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
        })->first();

        if ($existingData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pelanggan dengan nama dan atau nomor kontak sudah ada.'
            ], 401);
        }

        $existingData = MasterPelanggan::where('nama_pelanggan', 'like', '%' . $clearNamaPelanggan . '%')
            ->orWhereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan) {
                $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
            })->first();

        if ($existingData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pelanggan dengan nama dan atau nomor kontak sudah ada.'
            ], 401);
        }

        $cekLog = DB::table('log_webphone')
            ->join('master_karyawan', 'master_karyawan.id', '=', 'log_webphone.karyawan_id')
            ->where('log_webphone.number', 'like', '%' . preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], $no_tlp_perusahaan . '%'))
            ->select('master_karyawan.nama_lengkap', 'log_webphone.created_at', 'log_webphone.number')
            ->orderBy('log_webphone.created_at', 'desc')
            ->first();

        if ($cekLog) {
            $karyawan_now = $request->attributes->get('user');

            if (($karyawan_now->karyawan->id_jabatan != 148) && $cekLog->nama_lengkap != $this->karyawan && \Carbon\Carbon::parse($cekLog->created_at)->diffInDays(\Carbon\Carbon::now()) <= 10) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pelanggan sudah pernah dihubungi pada ' . $cekLog->created_at . ' oleh ' . $cekLog->nama_lengkap . '.'
                ], 401);
            }
        }


        $followUp = new MasterPelanggan;

        $followUp->id_cabang = 1;
        $followUp->no_urut = $noUrut;
        $followUp->id_pelanggan = $idPelanggan;
        $followUp->nama_pelanggan = $request->nama_pelanggan;
        $followUp->sales_id = $this->user_id;
        $followUp->sales_penanggung_jawab = $this->karyawan;
        $followUp->created_by = $this->karyawan;
        $followUp->created_at = $timestamp;
        $followUp->save();

        $followUp->kontak_pelanggan()->create([
            'pelanggan_id' => $followUp->id,
            'no_tlp_perusahaan' => $no_tlp_perusahaan
        ]);

        return response()->json(['message' => 'Created Successfully'], 200);
    }

    public function dfus(Request $request)
    {
        $tanggal = $request->tanggal ?: date('Y-m-d');
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $dfus = DFUS::with('keteranganTambahan')
            ->select('dfus.*')
            ->addSelect('p.id_pelanggan as idPelanggan', 'p.nama_pelanggan as namaPelanggan')
            ->join('master_pelanggan as p', function ($join) {
                $join->on('p.id_pelanggan', '=', 'dfus.id_pelanggan')->where('p.is_active', true);
            })
            ->where('dfus.tanggal', $tanggal)
            ->orderBy('dfus.tanggal', 'desc')
            ->orderBy('dfus.jam', 'desc');

        $salesNames = null;
        switch ($jabatan) {
            case 24: // Sales Staff
            case 148: // Sales Staff
                $dfus->where('dfus.sales_penanggung_jawab', $this->karyawan);
                $salesNames = [$this->karyawan];
                break;

            case 21: // Sales Supervisor
                $salesNames = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('nama_lengkap')
                    ->push($this->karyawan)
                    ->unique()
                    ->values()
                    ->all();
                $dfus->whereIn('dfus.sales_penanggung_jawab', $salesNames);
                break;
        }

        // Preload sekali: hindari N+1 (MasterKaryawan + LogWebphone per baris)
        $karyawanQuery = MasterKaryawan::query()->select('id', 'nama_lengkap');
        if ($salesNames !== null) {
            $karyawanQuery->whereIn('nama_lengkap', $salesNames);
        } else {
            $karyawanQuery->whereIn('nama_lengkap', function ($query) use ($tanggal) {
                $query->select('sales_penanggung_jawab')
                    ->from('dfus')
                    ->where('tanggal', $tanggal)
                    ->whereNotNull('sales_penanggung_jawab')
                    ->distinct();
            });
        }
        $karyawanIdsByName = $karyawanQuery->pluck('id', 'nama_lengkap');

        $logsByKaryawan = $karyawanIdsByName->isEmpty()
            ? collect()
            : LogWebphone::whereIn('karyawan_id', $karyawanIdsByName->values()->all())
                ->whereDate('created_at', $tanggal)
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('karyawan_id');

        $keteranganColumns = [
            'keterangan_perkenalan',
            'keterangan_proposal',
            'keterangan_review_manager',
            'keterangan_negosiasi_harga',
            'keterangan_maintain_call',
            'proposal',
        ];

        return DataTables::of($dfus)
            ->editColumn('pelanggan', fn($row) => [
                'id_pelanggan'  => $row->idPelanggan,
                'nama_pelanggan' => $row->namaPelanggan,
            ])
            ->filterColumn('pelanggan.nama_pelanggan', function ($query, $keyword) {
                $query->where('p.nama_pelanggan', 'like', "%{$keyword}%");
            })
            ->orderColumn('pelanggan.nama_pelanggan', 'p.nama_pelanggan $1')
            ->orderColumn('tanggal', 'dfus.tanggal $1')
            ->orderColumn('jam', 'dfus.jam $1')
            ->filterColumn('keterangan_activity', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where('dfus.keterangan_activity', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('keterangan_tambahan', function ($query, $value) use ($keteranganColumns) {
                $data = json_decode($value, true);
                if (!is_array($data)) {
                    return;
                }
                $kategori = $data['kategori'] ?? null;
                $keyword  = $data['keyword'] ?? null;

                if (!$keyword) {
                    return;
                }

                $query->whereHas('keteranganTambahan', function ($q) use ($kategori, $keyword, $keteranganColumns) {
                    if ($kategori && in_array($kategori, $keteranganColumns, true)) {
                        $q->where($kategori, 'like', "%{$keyword}%");
                        return;
                    }

                    $q->where(function ($x) use ($keyword, $keteranganColumns) {
                        foreach ($keteranganColumns as $index => $column) {
                            if ($index === 0) {
                                $x->where($column, 'like', "%{$keyword}%");
                            } else {
                                $x->orWhere($column, 'like', "%{$keyword}%");
                            }
                        }
                    });
                });
            })
            // ->addColumn('status_order', fn($row) => OrderHeader::where('id_pelanggan', $row->id_pelanggan)->where('is_active', true)->exists() ? 'REPEAT' : 'NEW')
            ->addColumn('status_order', fn() => 'Coming Soon')
            ->addColumn('log_webphone', function ($row) use ($karyawanIdsByName, $logsByKaryawan) {
                $karyawanId = $karyawanIdsByName[$row->sales_penanggung_jawab] ?? null;
                if (!$karyawanId) {
                    return [];
                }

                $rowLogs = $logsByKaryawan->get($karyawanId, collect());
                if (is_string($row->kontak) && strpos($row->kontak, ' - ') !== false) {
                    $kontak = explode(' - ', $row->kontak, 2)[1] ?? '';
                    if ($kontak !== '') {
                        $rowLogs = $rowLogs->filter(function ($log) use ($kontak) {
                            return strpos((string) $log->number, $kontak) !== false;
                        });
                    }
                }

                return $rowLogs->values()->toArray();
            })
            ->make(true);
    }

    public function saveDFUS(Request $request)
    {
        // dd($request->all());
        $message = null;

        switch ($request->action) {
            case 'updateDFUS':
                $dfus = DFUS::where('id', $request->id)->first();

                if ($request->pic_pelanggan)
                    $dfus->pic_pelanggan = $request->pic_pelanggan;
                if ($request->email_pic)
                    $dfus->email_pic = $request->email_pic;
                if ($request->no_pic)
                    $dfus->no_pic = $request->no_pic;
                if ($request->status) {
                    $dfus->status = $request->status == '-1' ? null : $request->status;
                    if ($request->status !== 'qt') {
                        $dfus->status_quotation = null;
                    }
                }
                if ($request->keterangan)
                    $dfus->keterangan = $request->keterangan;

                if ($request->column_name && $request->column_name != "") {
                    $dfus->{$request->column_name} = $request->value;
                }

                $dfus->updated_by = $this->karyawan;
                $dfus->save();

                $message = 'Saved Successfully.';
                break;

            case 'updateForecast':
                // Check for update or create new record
                $check = DFUS::where('id', $request->id)->first();
                $oldForecast = $check ? $check->forecast : null;
                $newForecast = date('Y-m-d H:i:s', strtotime($request->forecast));
                if ($oldForecast) {
                    // Update forecast on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast = $newForecast;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Update forecast on created before
                    $forecasted = DFUS::where(['id_pelanggan' => $request->id_pelanggan, 'tanggal' => date('Y-m-d', strtotime($oldForecast)), 'jam' => date('H:i:s', strtotime($oldForecast))])->first();
                    $forecasted->tanggal = date('Y-m-d', strtotime($newForecast));
                    $forecasted->jam = date('H:i:s', strtotime($newForecast));
                    $dfus->updated_by = $this->karyawan;
                    $forecasted->save();
                } else {
                    // Update forecast on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast = $newForecast;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Create new record
                    $dfus = new DFUS;
                    $dfus->id_pelanggan = $request->id_pelanggan;
                    $dfus->kontak = $request->kontak;
                    $dfus->sales_penanggung_jawab = $request->sales_penanggung_jawab;
                    $dfus->tanggal = $request->tanggal;
                    $dfus->jam = $request->jam;
                    $dfus->created_by = $this->karyawan;
                    $dfus->save();
                };

                $message = 'Saved Successfully.';
                break;

            case 'updateForecastPO':
                // Check for update or create new record
                $check = DFUS::where('id', $request->id)->first();
                $oldForecastPO = $check ? $check->forecast_po : null;
                $newForecastPO = date('Y-m-d H:i:s', strtotime($request->forecast_po));
                if ($oldForecastPO) {
                    // Update forecast_po on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast_po = $newForecastPO;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Update forecast_po on created before
                    $forecastedPO = DFUS::where(['id_pelanggan' => $request->id_pelanggan, 'tanggal' => date('Y-m-d', strtotime($oldForecastPO))])->first();
                    $forecastedPO->tanggal = date('Y-m-d', strtotime($newForecastPO));
                    $dfus->updated_by = $this->karyawan;
                    $forecastedPO->save();
                } else {
                    // Update forecast_po on original record
                    $dfus = DFUS::where('id', $request->id)->first();
                    $dfus->forecast_po = $newForecastPO;
                    $dfus->updated_by = $this->karyawan;
                    $dfus->save();

                    // Create new record
                    $dfus = new DFUS;
                    $dfus->id_pelanggan = $request->id_pelanggan;
                    $dfus->kontak = $request->kontak;
                    $dfus->sales_penanggung_jawab = $request->sales_penanggung_jawab;
                    $dfus->tanggal = $request->tanggal;
                    // $dfus->keterangan = 'Created PO';
                    $dfus->created_by = $this->karyawan;
                    $dfus->save();
                };

                $message = 'Saved Successfully.';
                break;

            default:
                if ($request->has('array_data')) {
                    $sudahDihubungi = [];
                    $data = [];
                    foreach ($request->array_data as $item) {
                        if (!isset($item['id_pelanggan']) || !isset($item['kontak_pelanggan']) || !isset($item['sales_penanggung_jawab'])) {
                            continue;
                        }

                        $kontak = preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], $item['kontak_pelanggan']);
                        if ($kontak == '') {
                            continue;
                        }

                        $cekLog = DB::table('log_webphone')
                            ->join('master_karyawan', 'master_karyawan.id', '=', 'log_webphone.karyawan_id')
                            ->where('log_webphone.number', 'like', '%' . $kontak . '%')
                            ->select('master_karyawan.nama_lengkap', 'log_webphone.created_at', 'log_webphone.number')
                            ->orderBy('log_webphone.created_at', 'desc')
                            ->first();

                        if (
                            $cekLog && $cekLog->nama_lengkap != $this->karyawan &&
                            \Carbon\Carbon::parse($cekLog->created_at)->diffInDays(\Carbon\Carbon::now()) <= 5
                        ) {
                            $time = Carbon::parse($cekLog->created_at)->translatedFormat('d F Y H:i');
                            $sudahDihubungi[] = "<strong>{$item['nama_pelanggan']}</strong><br /> oleh: <strong>{$cekLog->nama_lengkap}</strong><br />pada: {$time}";
                        }

                        $data[] = [
                            'id_pelanggan' => $item['id_pelanggan'],
                            'kontak' => 'Perusahaan - ' . $kontak,
                            'sales_penanggung_jawab' => $item['sales_penanggung_jawab'],
                            'tanggal' => $item['tanggal'] ?? Carbon::now()->format('Y-m-d'),
                            'jam' => $item['jam'] ?? Carbon::now()->format('H:i:s'),
                            'created_by' => $this->karyawan,
                            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ];
                    }


                    // kalau ada yang udah dihubungi, batalkan insert
                    if (!empty($sudahDihubungi)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Beberapa pelanggan sudah pernah dihubungi: <br /><br />' . implode('<br /><br />', $sudahDihubungi) . '<br /><br />Silahkan cek kembali data yang akan ditambahkan.'
                        ], 500);
                    }

                    // aman semua → insert
                    if (!empty($data)) {
                        // dd(DFUS::insert($data));
                        DFUS::insert($data);
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Data berhasil disimpan.'
                        ], 200);
                    }

                    // kalau kosong beneran
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Tidak ada data valid untuk disimpan.'
                    ], 204);
                } else {
                    if (!$request->id_pelanggan || !$request->kontak || !$request->sales_penanggung_jawab || !$request->tanggal || !$request->jam) {
                        $message = 'Data tidak lengkap.';
                    } else {

                        $cekLog = DB::table('log_webphone')
                            ->join('master_karyawan', 'master_karyawan.id', '=', 'log_webphone.karyawan_id')
                            ->where('log_webphone.number', 'like', '%' . preg_replace(['/[^0-9]/', '/^(\+62|62)/'], ['', '0'], \explode(' - ', $request->kontak)[1] . '%'))
                            ->select('master_karyawan.nama_lengkap', 'log_webphone.created_at', 'log_webphone.number')
                            ->orderBy('log_webphone.created_at', 'desc')
                            ->first();

                        $karyawan_now = $request->attributes->get('user');

                        if ($cekLog) {

                            if (($karyawan_now->karyawan->id_jabatan != 148) && $cekLog->nama_lengkap != $this->karyawan && \Carbon\Carbon::parse($cekLog->created_at)->diffInDays(\Carbon\Carbon::now()) <= 5) {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => 'Pelanggan sudah pernah dihubungi pada ' . $cekLog->created_at . ' oleh ' . $cekLog->nama_lengkap . '.'
                                ], 401);
                            }
                        }

                        $dfus = new DFUS;
                        $dfus->id_pelanggan = $request->id_pelanggan;
                        $dfus->kontak = $request->kontak;
                        $dfus->sales_penanggung_jawab = ($karyawan_now->karyawan->id_jabatan != 148) ?  $request->sales_penanggung_jawab : $this->karyawan;
                        $dfus->tanggal = $request->tanggal;
                        $dfus->jam = $request->jam;
                        $dfus->created_by = $this->karyawan;
                        $dfus->save();

                        $message = 'Data berhasil disimpan.';
                    }
                }
                break;
        }

        return response()->json(['message' => $message, 'status' => 'success'], 200);
    }

    public function exportDFUS(Request $request)
    {
        $type = $request->input('type', 'harian');
        $tanggal = $request->input('date', Carbon::now()->format('Y-m-d'));

        $hariIndo = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'No',
            'Hari',
            'Tanggal',
            'Jam',
            'Nama Pelanggan',
            'No. Telepon',
            'PIC Pelanggan',
            'E-Mail PIC',
            'No. Telepon PIC',
            'Sales Penanggung Jawab',
            'Call Status',
            'Forecast FU',
            'Forecast PO',
            'Status',
            'Status Call',
            'Keterangan'
        ];

        // Set header kolom
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $sheet->getStyle("A1:O1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
        ]);

        // ✅ Build query langsung tanpa batch
        $baseQuery = DFUS::with(['pelanggan', 'keteranganTambahan']);

        if ($type === 'bulanan') {
            $baseQuery->whereMonth('tanggal', date('m', strtotime($tanggal)))
                ->whereYear('tanggal', date('Y', strtotime($tanggal)));
        } else {
            $baseQuery->whereDate('tanggal', $tanggal);
        }

        switch ($jabatan) {
            case 24: // Sales Staff
                $baseQuery->where('sales_penanggung_jawab', $this->karyawan);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('nama_lengkap')
                    ->toArray();
                $bawahan[] = $this->karyawan;
                $baseQuery->whereIn('sales_penanggung_jawab', $bawahan);
                break;
        }

        // 🚀 Ambil semua data langsung
        $dfusData = $baseQuery
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam')
            ->get();

        return response()->json(['data' => $dfusData], 200);
        // $rowNumber = 2;

        // foreach ($dfusData as $index => $row) {
        //     $hari = $hariIndo[Carbon::parse($row->tanggal)->format('l')];
        //     $callStatus = '-';

        //     if (!empty($row->log_webphone)) {
        //         $statusList = [];

        //         foreach ($row->log_webphone as $log) {
        //             $durasi = explode(":", $log->time ?? "");
        //             $jam = (int)($durasi[0] ?? 0);
        //             $menit = (int)($durasi[1] ?? 0);
        //             $detik = (int)($durasi[2] ?? 0);

        //             $formatDurasi = [];
        //             if ($jam > 0) $formatDurasi[] = "{$jam} jam";
        //             if ($menit > 0) $formatDurasi[] = "{$menit} menit";
        //             if ($detik > 0 || empty($formatDurasi)) $formatDurasi[] = "{$detik} detik";

        //             $statusLog = $log->status_call ?? '-';
        //             if (!empty($log->time)) {
        //                 $statusLog .= "\n" . implode(" ", $formatDurasi);
        //             }
        //             $statusLog .= "\n" . Carbon::parse($log->created_at)->translatedFormat('d F Y H:i:s');

        //             $statusList[] = $statusLog;
        //         }

        //         $callStatus = implode("\n\n", $statusList);
        //     }

        //     $sheet->setCellValue('A' . $rowNumber, $index + 1);
        //     $sheet->setCellValue('B' . $rowNumber, $hari);
        //     $sheet->setCellValue('C' . $rowNumber, $row->tanggal);
        //     $sheet->setCellValue('D' . $rowNumber, $row->jam ?? '-');
        //     $sheet->setCellValue('E' . $rowNumber, $row->pelanggan->nama_pelanggan ?? '-');
        //     $sheet->setCellValue('F' . $rowNumber, $row->kontak ?? '-');
        //     $sheet->setCellValue('G' . $rowNumber, $row->pic_pelanggan ?? '-');
        //     $sheet->setCellValue('H' . $rowNumber, $row->email_pic ?? '-');
        //     $sheet->setCellValue('I' . $rowNumber, $row->no_pic ?? '-');
        //     $sheet->setCellValue('J' . $rowNumber, $row->sales_penanggung_jawab ?? '-');
        //     $sheet->setCellValue('K' . $rowNumber, $callStatus);
        //     $sheet->setCellValue('L' . $rowNumber, $row->forecast ?? '-');
        //     $sheet->setCellValue('M' . $rowNumber, $row->forecast_po ?? '-');
        //     $sheet->setCellValue('N' . $rowNumber, ($row->status == 'qt') ? 'Quotation' : (($row->status == 'req_qt') ? 'Request Quotation' : '-'));
        //     $sheet->setCellValue('O' . $rowNumber, $row->keterangan ?? '-');
        //     $sheet->setCellValue('P' . $rowNumber, self::getKeteranganTambahanActive($row->keteranganTambahan) ?? '-');

        //     $sheet->getStyle('K' . $rowNumber)->getAlignment()->setWrapText(true);
        //     $rowNumber++;
        // }

        // // Apply border dan alignment
        // $highestRow = $sheet->getHighestRow();
        // $highestColumn = $sheet->getHighestColumn();
        // $cellRange = "A1:$highestColumn$highestRow";

        // $sheet->getStyle($cellRange)->applyFromArray([
        //     'borders' => [
        //         'allBorders' => [
        //             'borderStyle' => Border::BORDER_THIN,
        //             'color' => ['argb' => '000000']
        //         ]
        //     ],
        //     'alignment' => [
        //         'horizontal' => Alignment::HORIZONTAL_CENTER,
        //         'vertical' => Alignment::VERTICAL_CENTER
        //     ]
        // ]);

        // foreach (['E', 'F', 'G', 'H', 'K'] as $col) {
        //     $sheet->getStyle("{$col}2:{$col}{$highestRow}")
        //           ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        // }

        // // Save file
        // $path = public_path('dfus/');
        // $fileName = 'DFUS_' . str_replace('-', '_', $tanggal) . '.xlsx';
        // (new Xlsx($spreadsheet))->save($path . $fileName);

        // return response()->json(['data' => $fileName], 200);
    }


    public function getDetailKeterangan(Request $request)
    {
        $keterangan = DFUSKeterangan::where('dfus_id', $request->id)->first();
        return response()->json(['data' => $keterangan], 200);
    }

    public function createOrUpdate(Request $request)
    {
        try {
            $keterangan = null;
            if ($request->id) {
                $keterangan = DFUSKeterangan::where('id', $request->id)->first();
                $keterangan->updated_by = $this->karyawan;
                $keterangan->updated_at = Carbon::now();
            } else {
                $keterangan = new DFUSKeterangan();
                $keterangan->dfus_id = $request->dfus_id;
                $keterangan->created_by = $this->karyawan;
                $keterangan->created_at = Carbon::now();
            }

            $keterangan->step_active = $request->step_active;

            if ($request->step_active == 1) {
                $keterangan->tanggal_perkenalan = $request->tanggal_perkenalan;
                $keterangan->keterangan_perkenalan = $request->keterangan_perkenalan;
            } else if ($request->step_active == 2) {
                $keterangan->proposal = $request->proposal;
                $keterangan->keterangan_proposal = $request->keterangan_proposal;
            } else if ($request->step_active == 3) {
                $keterangan->tanggal_review_manager = $request->tanggal_review_manager;
                $keterangan->keterangan_review_manager = $request->keterangan_review_manager;
            } else if ($request->step_active == 4) {
                $keterangan->keterangan_negosiasi_harga = $request->keterangan_negosiasi_harga;
            } else if ($request->step_active == 5) {
                $keterangan->maintain_call = $request->maintain_call;
                $keterangan->keterangan_maintain_call = $request->keterangan_maintain_call;
            }

            $keterangan->save();
            return response()->json(['message' => 'Data berhasil disimpan', 'success' => true, 'data' => $keterangan], 200);
        } catch (\Exception $th) {
            return  response()->json(['error' => $th], 400);
        }
    }

    public function updateStatusCalling(Request $request)
    {
        $allowed = ['NA', 'NI', 'D', 'PIC', 'FO'];
        $status = strtoupper(trim((string) $request->status));
        if (!in_array($status, $allowed, true)) {
            return response()->json([
                'message' => 'Status calling tidak valid. Gunakan: ' . implode(', ', $allowed),
                'success' => false,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $dfus = DFUS::where('id', $request->id)->first();
            if (!$dfus) {
                DB::rollBack();
                return response()->json(['message' => 'Data DFUS tidak ditemukan.', 'success' => false], 404);
            }

            $dfus->keterangan = $status;
            $dfus->save();

            if ($status === 'NI') {
                $request->id = MasterPelanggan::where('id_pelanggan', $dfus->id_pelanggan)->first()->id;
                $request->alasan = 'Nomor Invalid';
                $mpController = new MasterPelangganController($request);

                $mpController->blacklist($request);
            }
            DB::commit();
            return response()->json(['message' => 'Berhasil Update Status Calling ke ' . $status .  ($status === 'NI' ? ', serta menambahkan ke blacklist.' : ''), 'success' => true], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return  response()->json(['error' => $th], 400);
        }
    }

    public function getQuotationsByPelanggan(Request $request)
    {
        $idPelanggan = trim((string) ($request->id_pelanggan ?? ''));
        if ($idPelanggan === '') {
            return response()->json(['message' => 'id_pelanggan wajib diisi.', 'data' => []], 422);
        }

        $search = trim((string) ($request->search ?? ''));
        $limit = 50;

        $applyFilter = function ($query) use ($idPelanggan, $search) {
            $query->where('pelanggan_ID', $idPelanggan)->where('is_active', true);
            if ($search !== '') {
                $query->where('no_document', 'like', '%' . $search . '%');
            }
            return $query->orderByDesc('id')->limit(50);
        };

        $nonKontrak = $applyFilter(QuotationNonKontrak::query())->get(['id', 'no_document']);
        $kontrak = $applyFilter(QuotationKontrakH::query())->get(['id', 'no_document']);

        $docs = $nonKontrak->pluck('no_document')
            ->merge($kontrak->pluck('no_document'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $orderedDocs = $docs
            ? OrderHeader::whereIn('no_document', $docs)
                ->where('is_active', true)
                ->pluck('no_document')
                ->flip()
            : collect();

        $mapRow = function ($row, $type) use ($orderedDocs) {
            $noQuotation = $row->no_document;
            $ordered = $orderedDocs->has($noQuotation);
            return [
                'id_quotation' => (int) $row->id,
                'no_quotation' => $noQuotation,
                'type' => $type,
                'ordered' => $ordered,
                'text' => $ordered ? ($noQuotation . ' (ordered)') : $noQuotation,
            ];
        };

        $data = $nonKontrak->map(fn($row) => $mapRow($row, 'non_kontrak'))
            ->merge($kontrak->map(fn($row) => $mapRow($row, 'kontrak')))
            ->sortByDesc('id_quotation')
            ->values()
            ->take($limit)
            ->values()
            ->all();

        return response()->json(['data' => $data], 200);
    }

    public function saveKeteranganActivity(Request $request)
    {
        $dfus = DFUS::where('id', $request->id)->first();
        if (!$dfus) {
            return response()->json(['message' => 'Data DFUS tidak ditemukan.', 'success' => false], 404);
        }

        $text = trim((string) $request->input('keterangan_activity', ''));
        if ($text === '') {
            return response()->json(['message' => 'Keterangan activity wajib dipilih.', 'success' => false], 422);
        }

        // Batasi panjang wajar (teks single-choice)
        if (mb_strlen($text) > 500) {
            return response()->json(['message' => 'Keterangan activity terlalu panjang.', 'success' => false], 422);
        }

        $dfus->keterangan_activity = $text;
        $dfus->updated_by = $this->karyawan;
        $dfus->save();

        return response()->json([
            'message' => 'Keterangan Activity berhasil disimpan.',
            'success' => true,
            'data' => [
                'keterangan_activity' => $dfus->keterangan_activity,
            ],
        ], 200);
    }

    /** Cek no_quotation mana saja yang sudah ada di order_header (aktif) */
    public function checkQuotationsOrdered(Request $request)
    {
        $docs = $request->input('no_quotations', []);
        if (!is_array($docs)) {
            $docs = [];
        }
        $docs = array_values(array_unique(array_filter(array_map(function ($d) {
            return trim((string) $d);
        }, $docs))));

        $ordered = $docs
            ? OrderHeader::whereIn('no_document', $docs)
                ->where('is_active', true)
                ->pluck('no_document')
                ->values()
                ->all()
            : [];

        return response()->json(['data' => $ordered], 200);
    }

    public function saveStatusQuotation(Request $request)
    {
        $allowedStatus = ['cold', 'warm', 'hot'];
        $dfus = DFUS::where('id', $request->id)->first();
        if (!$dfus) {
            return response()->json(['message' => 'Data DFUS tidak ditemukan.', 'success' => false], 404);
        }

        $items = $request->input('items', []);
        if (!is_array($items)) {
            return response()->json(['message' => 'Format items tidak valid.', 'success' => false], 422);
        }

        $normalized = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $idQuotation = (int) ($item['id_quotation'] ?? 0);
            $noQuotation = trim((string) ($item['no_quotation'] ?? ''));
            $status = strtolower(trim((string) ($item['status'] ?? '')));
            if ($idQuotation <= 0 || $noQuotation === '' || !in_array($status, $allowedStatus, true)) {
                return response()->json([
                    'message' => 'Setiap item wajib punya id_quotation, no_quotation, dan status (cold/warm/hot).',
                    'success' => false,
                ], 422);
            }

            $type = strtolower(trim((string) ($item['type'] ?? '')));
            if (!in_array($type, ['kontrak', 'non_kontrak'], true)) {
                // QTC = kontrak, QT = non_kontrak
                $type = (stripos($noQuotation, '/QTC/') !== false || stripos($noQuotation, 'QTC/') !== false)
                    ? 'kontrak'
                    : 'non_kontrak';
            }

            $key = $type . '|' . $idQuotation . '|' . $noQuotation;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = [
                'id_quotation' => $idQuotation,
                'no_quotation' => $noQuotation,
                'status' => $status,
                'type' => $type,
            ];
        }

        if (count($normalized) === 0) {
            return response()->json(['message' => 'Pilih minimal satu quotation.', 'success' => false], 422);
        }

        try {
            DB::beginTransaction();

            $dfus->status = 'qt';
            $dfus->status_quotation = $normalized;
            $dfus->updated_by = $this->karyawan;
            $dfus->save();

            foreach ($normalized as $row) {
                if ($row['type'] === 'kontrak') {
                    // QTC → request_quotation_kontrak_H.status_quotation
                    QuotationKontrakH::where('id', $row['id_quotation'])->update([
                        'status_quotation' => $row['status'],
                    ]);
                } else {
                    // QT → request_quotation.status_quotation
                    QuotationNonKontrak::where('id', $row['id_quotation'])->update([
                        'status_quotation' => $row['status'],
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan status quotation: ' . $e->getMessage(),
                'success' => false,
            ], 500);
        }

        return response()->json([
            'message' => 'Status Quotation berhasil disimpan.',
            'success' => true,
            'data' => [
                'status' => $dfus->status,
                'status_quotation' => $dfus->status_quotation,
            ],
        ], 200);
    }

    public function getLog(Request $request)
    {
        $log = LogWebphone::with('karyawan:id,nama_lengkap')
            ->where('number', $request->number)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $log], 200);
    }
}
