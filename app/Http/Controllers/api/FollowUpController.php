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
use App\Services\SalesTeamHierarchyService;
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
            ->filterColumn('pelanggan.id_pelanggan', function ($query, $keyword) {
                $query->where('p.id_pelanggan', 'like', "%{$keyword}%");
            })
            ->orderColumn('pelanggan.id_pelanggan', 'p.id_pelanggan $1')
            ->filterColumn('pelanggan.nama_pelanggan', function ($query, $keyword) {
                $query->where('p.nama_pelanggan', 'like', "%{$keyword}%");
            })
            ->orderColumn('pelanggan.nama_pelanggan', 'p.nama_pelanggan $1')
            ->orderColumn('tanggal', 'dfus.tanggal $1')
            ->orderColumn('jam', 'dfus.jam $1')
            ->filterColumn('forecast', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->whereDate('dfus.forecast', $keyword);
            })
            ->filterColumn('forecast_po', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->whereDate('dfus.forecast_po', $keyword);
            })
            ->filterColumn('keterangan', function ($query, $keyword) {
                $keyword = strtoupper(trim((string) $keyword));
                if ($keyword === '') {
                    return;
                }
                if ($keyword === 'PIC') {
                    $query->where('dfus.keterangan', 'PIC');
                    return;
                }
                if ($keyword === 'UNREACHABLE') {
                    $query->whereIn('dfus.keterangan', ['FO', 'NA', 'D']);
                    return;
                }
                if ($keyword === 'UNQUALIFIED') {
                    $query->where('dfus.keterangan', 'NI');
                    return;
                }
                $query->where('dfus.keterangan', $keyword);
            })
            ->filterColumn('keterangan_activity', function ($query, $keyword) use ($keteranganColumns) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where(function ($q) use ($keyword, $keteranganColumns) {
                    $q->where('dfus.keterangan_activity', 'like', '%' . $keyword . '%')
                        ->orWhereHas('keteranganTambahan', function ($sub) use ($keyword, $keteranganColumns) {
                            $sub->where(function ($x) use ($keyword, $keteranganColumns) {
                                foreach ($keteranganColumns as $index => $column) {
                                    if ($index === 0) {
                                        $x->where($column, 'like', '%' . $keyword . '%');
                                    } else {
                                        $x->orWhere($column, 'like', '%' . $keyword . '%');
                                    }
                                }
                            });
                        });
                });
            })
            ->filterColumn('keterangan_sales', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where('dfus.keterangan_sales', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('status_quotation', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where('dfus.status_quotation', 'like', '%' . $keyword . '%');
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
            ->toBase()
            ->merge($kontrak->map(fn($row) => $mapRow($row, 'kontrak'))->toBase())
            ->sortByDesc('id_quotation')
            ->values()
            ->take($limit)
            ->values()
            ->all();

        return response()->json(['data' => $data], 200);
    }

    public function saveKeteranganActivity(Request $request)
    {
        $allowedLabels = [
            'Perkenalan Awal & Identifikasi Peluang',
            'Kirim Company Profile',
            'Tidak melakukan pengujian',
            'Penawaran Masih Dalam Review Management',
            'Penawaran Masih Proses Penyesuaian Kebutuhan (Revisi)',
            'Negosiasi Harga',
            'Penawaran Tidak Disetujui Management/Void QT',
            'Follow Up PO atau Sign Quote',
            'Arrange Schedule Pengujian',
            'Follow Up Hasil Uji & Next Pengujian',
            'Maintain Customer',
        ];

        $dfus = DFUS::where('id', $request->id)->first();
        if (!$dfus) {
            return response()->json(['message' => 'Data DFUS tidak ditemukan.', 'success' => false], 404);
        }

        $raw = trim((string) $request->input('keterangan_activity', ''));
        if ($raw === '') {
            return response()->json(['message' => 'Keterangan activity wajib dipilih.', 'success' => false], 422);
        }

        // Ambil label opsi saja (abaikan baris tahap / "> ")
        // contoh masuk: "Tahap Closing...\n  > Arrange Schedule Pengujian"
        // hasil simpan: "Arrange Schedule Pengujian"
        $candidate = $raw;
        if (preg_match('/^\s*>\s*(.+)$/m', $raw, $matches)) {
            $candidate = trim($matches[1]);
        } else {
            $lines = preg_split('/\R+/', $raw) ?: [];
            $last = trim((string) end($lines));
            if ($last !== '') {
                $candidate = preg_replace('/^>\s*/', '', $last);
            }
        }

        $label = null;
        foreach ($allowedLabels as $allowed) {
            if (strcasecmp($candidate, $allowed) === 0 || stripos($candidate, $allowed) !== false) {
                $label = $allowed;
                break;
            }
            // FE kadang kirim full text — cocokkan label di dalamnya
            if (stripos($raw, $allowed) !== false) {
                $label = $allowed;
                break;
            }
        }

        if ($label === null) {
            return response()->json([
                'message' => 'Keterangan activity tidak valid.',
                'success' => false,
            ], 422);
        }

        $dfus->keterangan_activity = $label;
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

    public function saveKeteranganSales(Request $request)
    {
        $dfus = DFUS::where('id', $request->id)->first();
        if (!$dfus) {
            return response()->json(['message' => 'Data DFUS tidak ditemukan.', 'success' => false], 404);
        }

        $text = $this->htmlToPlainText((string) $request->input('keterangan_sales', ''));
        if (mb_strlen($text) > 5000) {
            return response()->json([
                'message' => 'Keterangan sales maksimal 5.000 karakter.',
                'success' => false,
            ], 422);
        }

        $dfus->keterangan_sales = $text === '' ? null : $text;
        $dfus->keterangan_by = $this->karyawan;
        $dfus->keterangan_at = Carbon::now();
        $dfus->updated_by = $this->karyawan;
        $dfus->save();

        return response()->json([
            'message' => 'Keterangan Sales berhasil disimpan.',
            'success' => true,
            'data' => [
                'keterangan_sales' => $dfus->keterangan_sales,
                'keterangan_by' => $dfus->keterangan_by,
                'keterangan_at' => $dfus->keterangan_at,
            ],
        ], 200);
    }

    private function htmlToPlainText(string $html): string
    {
        $raw = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $raw = preg_replace('/<\s*\/(p|div|li|h[1-6])\s*>/i', "\n", $raw) ?? $raw;
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public function summaryDfus(Request $request)
    {
        try {
            [$startDate, $endDate] = $this->resolveSummaryDfusDateRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 422);
        }

        $viewer = MasterKaryawan::find($this->user_id);
        if (!$viewer) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.', 'success' => false], 404);
        }

        $hierarchy = app(SalesTeamHierarchyService::class);
        $isDfusDivision = $this->viewerBelongsToDfusDivision($viewer, $hierarchy);
        $isManagerGrade = $this->viewerIsManagerGrade($viewer);
        if (!$isDfusDivision && !$isManagerGrade) {
            return $this->emptySummaryDfusResponse(
                $startDate->toDateString(),
                $endDate->toDateString()
            );
        }

        // Atasan (SPV/Manager) melihat timnya; sales staff hanya dirinya sendiri.
        $memberIds = $hierarchy->resolveViewerScopeMemberIds($viewer);
        $activeNames = DFUS::query()
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('sales_penanggung_jawab')
            ->distinct()
            ->pluck('sales_penanggung_jawab')
            ->filter()
            ->values()
            ->all();
        $activeNameSet = array_flip($activeNames);

        $hierarchyRows = $hierarchy->buildTableRows(function ($member) use ($activeNameSet) {
            if ((int) ($member->is_active ?? 0) === 1) {
                return true;
            }

            return isset($activeNameSet[(string) ($member->nama_lengkap ?? '')]);
        }, null, $memberIds);

        $rows = $this->buildSummaryDfusRows($startDate, $endDate, $hierarchyRows);
        $totals = $this->summarizeDfusRows($rows);
        $count = count($rows);
        $averages = [
            'total_call' => $count ? round($totals['total_call'] / $count, 1) : 0,
            'pic_contacted' => $count ? round($totals['pic_contacted'] / $count, 1) : 0,
            'avg_call_minutes' => $count
                ? round(array_sum(array_map(fn($row) => (float) ($row['avg_call_minutes'] ?? 0), $rows)) / $count, 1)
                : 0,
            'success_rate' => $count
                ? round(array_sum(array_map(fn($row) => (float) ($row['success_rate'] ?? 0), $rows)) / $count, 1)
                : 0,
        ];

        $nameToId = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['nama_lengkap'] ?? ''));
            if ($name !== '') {
                $nameToId[$name] = (int) ($row['karyawan_id'] ?? 0);
            }
        }

        $canFilterSales = $isManagerGrade
            || $hierarchy->viewerCanUseAllOption($viewer)
            || (
                !$hierarchy->isSalesStaff($viewer)
                && $hierarchy->getPool()->has((int) $viewer->id)
            );

        return response()->json([
            'success' => true,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'allowed' => true,
            'can_filter_sales' => $canFilterSales,
            'data' => $rows,
            'hourly' => $this->summaryDfusHourly($nameToId, $startDate->toDateString(), $endDate->toDateString()),
            'totals' => $totals,
            'averages' => $averages,
        ], 200);
    }

    private function resolveSummaryDfusDateRange(Request $request): array
    {
        $periodType = in_array($request->period_type, ['daily', 'yearly', 'range'], true)
            ? $request->period_type
            : 'monthly';

        if ($periodType === 'daily') {
            $startDate = Carbon::parse($request->date ?: $request->start_date ?: Carbon::now())->startOfDay();
            return [$startDate, $startDate->copy()->endOfDay()];
        }

        if ($periodType === 'yearly') {
            $year = max(2024, (int) ($request->year ?: Carbon::now()->year));
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            return [$startDate, $startDate->copy()->endOfYear()];
        }

        if ($periodType === 'range') {
            $startDate = Carbon::parse($request->start_date ?: Carbon::now()->startOfMonth())->startOfDay();
            $endDate = Carbon::parse($request->end_date ?: Carbon::now())->endOfDay();
            if ($endDate->lt($startDate)) {
                throw new \InvalidArgumentException('Tanggal selesai harus sama atau setelah tanggal mulai.');
            }
            return [$startDate, $endDate];
        }

        $parts = explode(' ', (string) $request->periode);
        $monthMap = [
            'Januari' => '01', 'Februari' => '02', 'Maret' => '03', 'April' => '04',
            'Mei' => '05', 'Juni' => '06', 'Juli' => '07', 'Agustus' => '08',
            'September' => '09', 'Oktober' => '10', 'November' => '11', 'Desember' => '12',
        ];
        $periode = count($parts) === 2 && isset($monthMap[$parts[0]])
            ? $parts[1] . '-' . $monthMap[$parts[0]]
            : Carbon::now()->format('Y-m');

        $startDate = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        return [$startDate, $startDate->copy()->endOfMonth()];
    }

    private function buildSummaryDfusRows(Carbon $startDate, Carbon $endDate, array $hierarchyRows): array
    {
        $salesIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column($hierarchyRows, 'karyawan_id')
        ))));

        if (empty($salesIds)) {
            return [];
        }

        $sales = MasterKaryawan::whereIn('id', $salesIds)->pluck('nama_lengkap', 'id');
        $salesNames = $sales->filter()->values()->all();
        $statusRows = empty($salesNames)
            ? collect()
            : DFUS::whereIn('sales_penanggung_jawab', $salesNames)
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereNotNull('keterangan')
                ->whereRaw("TRIM(keterangan) <> ''")
                ->selectRaw("sales_penanggung_jawab, UPPER(TRIM(keterangan)) as status, COUNT(*) as total")
                ->groupBy('sales_penanggung_jawab')
                ->groupByRaw("UPPER(TRIM(keterangan))")
                ->get();

        $statusesBySales = $statusRows->groupBy('sales_penanggung_jawab');
        $durationBySales = $this->summaryDfusDurationsBySales($salesIds, $startDate, $endDate);

        return array_map(function (array $row) use ($sales, $statusesBySales, $durationBySales) {
            $salesId = (int) $row['karyawan_id'];
            $name = $sales->get($salesId, $row['nama_lengkap'] ?? '');
            $statusCounts = $statusesBySales->get($name, collect())->pluck('total', 'status');
            $totalCalls = (int) $statusCounts->sum();
            $picContacted = (int) ($statusCounts->get('PIC', 0));
            $duration = $durationBySales->get($salesId);
            $durationCount = (int) ($duration->call_count ?? 0);
            $durationSeconds = (float) ($duration->total_seconds ?? 0);

            return array_merge($row, [
                'total_call' => $totalCalls,
                'pic_contacted' => $picContacted,
                'tidak_terhubung' => max(0, $totalCalls - $picContacted),
                'avg_call_minutes' => $durationCount > 0 ? round($durationSeconds / $durationCount / 60, 1) : 0,
                'avg_call_seconds' => $durationCount > 0 ? (int) round($durationSeconds / $durationCount) : 0,
                'duration_call_count' => $durationCount,
                'total_call_seconds' => $durationSeconds,
                'success_rate' => $totalCalls > 0 ? round(($picContacted / $totalCalls) * 100, 1) : 0,
            ]);
        }, $hierarchyRows);
    }

    private function summaryDfusHourly(array $nameToId, string $start, string $end): array
    {
        $names = array_values(array_filter(array_keys($nameToId)));
        if (empty($names)) {
            return [];
        }

        return DFUS::query()
            ->whereIn('sales_penanggung_jawab', $names)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('keterangan')
            ->whereRaw("TRIM(keterangan) <> ''")
            ->selectRaw("
                sales_penanggung_jawab as sales_name,
                HOUR(jam) as hour,
                COUNT(*) as calls,
                SUM(CASE WHEN UPPER(TRIM(keterangan)) = 'PIC' THEN 1 ELSE 0 END) as connected
            ")
            ->groupBy('sales_penanggung_jawab')
            ->groupByRaw('HOUR(jam)')
            ->get()
            ->map(function ($row) use ($nameToId) {
                $karyawanId = (int) ($nameToId[$row->sales_name] ?? 0);
                if ($karyawanId <= 0) {
                    return null;
                }

                return [
                    'karyawan_id' => $karyawanId,
                    'hour' => (int) $row->hour,
                    'calls' => (int) $row->calls,
                    'connected' => (int) $row->connected,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function summaryDfusDurationsBySales(array $salesIds, Carbon $startDate, Carbon $endDate)
    {
        $timeToSeconds = "
            CASE
                WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 2 THEN
                    (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 3600) +
                    (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(time, ':', 2), ':', -1) AS UNSIGNED) * 60) +
                    (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 1 THEN
                    (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 60) +
                    (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                ELSE 0
            END
        ";

        return DB::table('log_webphone')
            ->whereIn('karyawan_id', $salesIds)
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->whereNotNull('status_call')
            ->whereRaw("TRIM(status_call) <> ''")
            ->whereNotNull('time')
            ->where('time', '<>', '')
            ->selectRaw("karyawan_id, COUNT(*) as call_count, SUM({$timeToSeconds}) as total_seconds")
            ->groupBy('karyawan_id')
            ->get()
            ->keyBy('karyawan_id');
    }

    private function summarizeDfusRows(array $rows): array
    {
        $totalCall = array_sum(array_column($rows, 'total_call'));
        $picContacted = array_sum(array_column($rows, 'pic_contacted'));
        $durationCount = array_sum(array_column($rows, 'duration_call_count'));
        $durationSeconds = array_sum(array_column($rows, 'total_call_seconds'));

        return [
            'total_call' => $totalCall,
            'pic_contacted' => $picContacted,
            'avg_call_minutes' => $durationCount > 0 ? round($durationSeconds / $durationCount / 60, 1) : 0,
            'success_rate' => $totalCall > 0 ? round(($picContacted / $totalCall) * 100, 1) : 0,
        ];
    }

    public function summaryDfusDetail(Request $request)
    {
        try {
            [$startDate, $endDate] = $this->resolveSummaryDfusDateRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 422);
        }

        $viewer = MasterKaryawan::select('id', 'id_jabatan', 'id_department', 'grade', 'nama_lengkap')->find($this->user_id);
        if (!$viewer) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.', 'success' => false], 404);
        }

        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['message' => 'Sales tidak valid.', 'success' => false], 422);
        }

        $sales = MasterKaryawan::select('id', 'nama_lengkap')->find($karyawanId);
        if (!$sales) {
            return response()->json(['message' => 'Data sales tidak ditemukan.', 'success' => false], 404);
        }

        if (!$this->canViewSummaryDfusSales($viewer, $karyawanId)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke data sales ini.', 'success' => false], 403);
        }

        $start = $startDate->toDateString();
        $end = $endDate->toDateString();
        $perPage = max(1, min(50, (int) ($request->input('length') ?: $request->input('per_page') ?: 15)));
        $offset = $request->exists('start')
            ? max(0, (int) $request->input('start', 0))
            : (max(1, (int) $request->input('page', 1)) - 1) * $perPage;
        $searchInput = $request->input('search.value', $request->input('search', ''));
        $search = is_array($searchInput)
            ? trim((string) ($searchInput['value'] ?? ''))
            : trim((string) $searchInput);
        $picFilter = strtolower(trim((string) $request->input('pic_contacted', '')));

        $baseQuery = DFUS::query()
            ->from('dfus')
            ->leftJoin('master_pelanggan as p', 'p.id_pelanggan', '=', 'dfus.id_pelanggan')
            ->where('dfus.sales_penanggung_jawab', $sales->nama_lengkap)
            ->whereBetween('dfus.tanggal', [$start, $end]);

        $recordsTotal = (clone $baseQuery)->count('dfus.id');

        if ($picFilter === 'ya') {
            $baseQuery->whereRaw("UPPER(TRIM(COALESCE(dfus.keterangan, ''))) = 'PIC'");
        } elseif ($picFilter === 'tidak') {
            $baseQuery->whereRaw("UPPER(TRIM(COALESCE(dfus.keterangan, ''))) <> 'PIC'");
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $baseQuery->where(function ($query) use ($like) {
                $query->where('p.nama_pelanggan', 'like', $like)
                    ->orWhere('dfus.id_pelanggan', 'like', $like)
                    ->orWhere('dfus.pic_pelanggan', 'like', $like)
                    ->orWhere('dfus.keterangan', 'like', $like)
                    ->orWhere('dfus.keterangan_sales', 'like', $like)
                    ->orWhere('dfus.tanggal', 'like', $like);
            });
        }

        $recordsFiltered = (clone $baseQuery)->count('dfus.id');
        $lastPage = max(1, (int) ceil($recordsFiltered / $perPage));
        if ($offset >= $recordsFiltered && $recordsFiltered > 0) {
            $offset = ($lastPage - 1) * $perPage;
        }

        $dfusRows = $recordsFiltered === 0
            ? collect()
            : $baseQuery
                ->orderByDesc('dfus.tanggal')
                ->orderByDesc('dfus.jam')
                ->offset($offset)
                ->limit($perPage)
                ->get([
                    'dfus.id',
                    'dfus.tanggal',
                    'dfus.jam',
                    'dfus.id_pelanggan',
                    'dfus.kontak',
                    'dfus.no_pic',
                    'dfus.pic_pelanggan',
                    'dfus.keterangan',
                    'dfus.keterangan_sales',
                    'p.nama_pelanggan as nama_perusahaan',
                ]);

        $logsByDate = $this->summaryDfusLogsForBatch($karyawanId, $dfusRows);
        $data = $dfusRows->map(function ($row) use ($logsByDate) {
            $status = strtoupper(trim((string) $row->keterangan));
            $matchedLog = $this->matchSummaryDfusCallLog(
                $logsByDate->get($row->tanggal, collect()),
                $row->kontak,
                $row->no_pic
            );
            $durationTime = $matchedLog->time ?? null;

            return [
                'id' => $row->id,
                'tanggal' => $row->tanggal,
                'jam' => $row->jam,
                'id_pelanggan' => $row->id_pelanggan,
                'nama_perusahaan' => $row->nama_perusahaan ?: '-',
                'pic_pelanggan' => $row->pic_pelanggan ?: '-',
                'status_call' => $status !== '' ? $status : '-',
                'pic_contacted' => $status === 'PIC',
                'lama_panggilan' => $this->formatCallDuration($durationTime),
                'lama_panggilan_seconds' => $this->webphoneTimeToSeconds($durationTime),
                'catatan' => trim((string) ($row->keterangan_sales ?? '')) !== ''
                    ? trim((string) $row->keterangan_sales)
                    : '-',
            ];
        })->values();

        $from = $recordsFiltered === 0 ? 0 : $offset + 1;
        $to = min($offset + $perPage, $recordsFiltered);

        return response()->json([
            'success' => true,
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'sales' => [
                'karyawan_id' => $sales->id,
                'nama_lengkap' => $sales->nama_lengkap,
            ],
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'data' => $data,
            'meta' => [
                'page' => (int) floor($offset / $perPage) + 1,
                'per_page' => $perPage,
                'total' => $recordsFiltered,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ], 200);
    }

    private function viewerIsManagerGrade(MasterKaryawan $viewer): bool
    {
        $grade = strtoupper(trim((string) ($viewer->grade ?? '')));

        return in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true);
    }

    private function viewerBelongsToDfusDivision(MasterKaryawan $viewer, ?SalesTeamHierarchyService $hierarchy = null): bool
    {
        $hierarchy = $hierarchy ?: app(SalesTeamHierarchyService::class);

        if (in_array((int) $viewer->id, $hierarchy->salesExecutiveIds(), true)) {
            return true;
        }

        if ($hierarchy->isSalesStaff($viewer)) {
            return true;
        }

        if ((int) $viewer->id_jabatan === 21) {
            return true;
        }

        $viewer->loadMissing('divisi');
        $divisiName = strtoupper(trim((string) optional($viewer->divisi)->nama_divisi));
        if ($divisiName === '') {
            return false;
        }

        return str_contains($divisiName, 'SALES')
            || str_contains($divisiName, 'PENJUALAN')
            || str_contains($divisiName, 'MARKETING SALES');
    }

    private function emptySummaryDfusResponse(string $start, string $end)
    {
        return response()->json([
            'success' => true,
            'allowed' => false,
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'can_filter_sales' => false,
            'data' => [],
            'hourly' => [],
            'totals' => [
                'total_call' => 0,
                'pic_contacted' => 0,
                'avg_call_minutes' => 0,
                'success_rate' => 0,
            ],
            'averages' => [
                'total_call' => 0,
                'pic_contacted' => 0,
                'avg_call_minutes' => 0,
                'success_rate' => 0,
            ],
        ], 200);
    }

    private function canViewSummaryDfusSales(MasterKaryawan $viewer, int $karyawanId): bool
    {
        if ($this->viewerIsManagerGrade($viewer)) {
            return true;
        }

        if (!$this->viewerBelongsToDfusDivision($viewer)) {
            return false;
        }

        if ((int) $viewer->id === $karyawanId) {
            return true;
        }

        $jabatan = (int) $viewer->id_jabatan;
        if (in_array($jabatan, [24, 148], true)) {
            return false;
        }

        if ($jabatan === 21) {
            return MasterKaryawan::where('id', $karyawanId)
                ->whereJsonContains('atasan_langsung', (string) $viewer->id)
                ->exists();
        }

        return true;
    }

    private function summaryDfusLogsForBatch(int $karyawanId, $dfusRows)
    {
        if ($dfusRows->isEmpty()) {
            return collect();
        }

        $dates = $dfusRows->pluck('tanggal')->filter()->unique()->values();
        if ($dates->isEmpty()) {
            return collect();
        }

        return DB::table('log_webphone')
            ->where('karyawan_id', $karyawanId)
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereBetween('created_at', [$date . ' 00:00:00', $date . ' 23:59:59']);
                }
            })
            ->orderByDesc('created_at')
            ->get(['number', 'time', 'created_at'])
            ->groupBy(function ($log) {
                return Carbon::parse($log->created_at)->toDateString();
            });
    }

    private function matchSummaryDfusCallLog($dayLogs, $kontak, $noPic)
    {
        if (!$dayLogs || $dayLogs->isEmpty()) {
            return null;
        }

        $phones = array_values(array_filter([
            $this->normalizePhoneDigits($kontak),
            $this->normalizePhoneDigits(is_string($kontak) && strpos($kontak, ' - ') !== false ? explode(' - ', $kontak, 2)[1] : null),
            $this->normalizePhoneDigits($noPic),
        ]));

        $matched = $dayLogs->filter(function ($log) use ($phones) {
            $number = $this->normalizePhoneDigits($log->number);
            if ($number === '') {
                return false;
            }
            foreach ($phones as $phone) {
                if ($phone !== '' && (strpos($number, $phone) !== false || strpos($phone, $number) !== false)) {
                    return true;
                }
            }
            return false;
        });

        if ($matched->isEmpty()) {
            return null;
        }

        return $matched->sortByDesc(function ($log) {
            return $this->webphoneTimeToSeconds($log->time ?? null);
        })->first();
    }

    private function normalizePhoneDigits($value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '62') === 0) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }

    private function webphoneTimeToSeconds(?string $time): int
    {
        if (!$time) {
            return 0;
        }
        $parts = array_map('intval', explode(':', $time));
        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }
        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }
        return (int) $time;
    }

    private function formatCallDuration(?string $time): string
    {
        $seconds = $this->webphoneTimeToSeconds($time);
        if ($seconds <= 0) {
            return '-';
        }
        $minutes = intdiv($seconds, 60);
        $remain = $seconds % 60;
        if ($minutes > 0 && $remain > 0) {
            return $minutes . ' menit ' . $remain . ' detik';
        }
        if ($minutes > 0) {
            return $minutes . ' menit';
        }
        return $remain . ' detik';
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
