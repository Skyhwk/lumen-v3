<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganLingkunganKerja;
use App\Models\DetailLingkunganKerja;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\LingkunganHeader;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Str;

// SERVICE
use App\Services\AnalystFormula;
use App\Models\AnalystFormula as Formula;

class FdlLingkunganKerjaController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganLingkunganKerja::with('detail', 'detailLingkunganKerja')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                if ($data != null) {
                    $data->no_sampel = $request->no_sampel_baru;
                    $data->no_sampel_lama = $request->no_sampel_lama;
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama,
                    ]);

                    LingkunganHeader::where('no_sampel', $request->no_sampel_lama)
                        ->update(
                            [
                                'no_sampel' => $request->no_sampel_baru,
                                'no_sampel_lama' => $request->no_sampel_lama
                            ]
                        );

                    WsValueLingkungan::where('no_sampel', $request->no_sampel_lama)
                        ->update(
                            [
                                'no_sampel' => $request->no_sampel_baru,
                                'no_sampel_lama' => $request->no_sampel_lama
                            ]
                        );

                    // update OrderDetail
                    $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)
                        ->first();

                    if ($order_detail_lama) {
                        OrderDetail::where('no_sampel', $request->no_sampel_baru)
                            ->where('is_active', 1)
                            ->update([
                                'tanggal_terima' => $order_detail_lama->tanggal_terima
                            ]);
                    }

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $request->no_sampel_lama . ' berhasil diubah menjadi ' . $request->no_sampel_baru
                    ]);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengubah data ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    public function approveData(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                if ($data != null) {
                    $order = OrderDetail::where('no_sampel', $data->no_sampel)->first();

                    if ($order) {
                        $tanggalTerima = $order->tanggal_terima;
                        $parameterArray = json_decode($order->parameter, true); // pastikan parameter disimpan sebagai JSON di database
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Order tidak ditemukan'
                        ], 404);
                    }

                    // Cek di Tabel Parameter

                    $parameter = [];
                    $id_parameter = [];

                    if (is_array($parameterArray)) {
                        foreach ($parameterArray as $item) {
                            $parts = explode(';', $item);
                            $id_parameter[] = trim($parts[0] ?? '');
                            $parameter[] = trim($parts[1] ?? '');
                        }
                    }

                    // Mapping target parameter ke field DB
                    $targetParams = [
                        'Suhu' => 'suhu',
                        'Kelembaban' => 'kelembapan',
                        'Laju Ventilasi' => 'auto_laju',
                        'Kecepatan Angin' => 'auto_laju',
                        'Kecepatan Angin (UA)' => 'auto_laju',
                        'Tekanan Udara' => 'tekanan_udara',
                        'Laju Ventilasi (8 Jam)' => 'auto_laju',
                        'Kelembaban 8J (LK)' => 'kelembapan',
                        'Kelembaban 8J' => 'kelembapan',
                        'Suhu 8J (LK)' => 'suhu',
                    ];

                    $foundParams = array_intersect($parameter, array_keys($targetParams));

                    // Ambil detail hanya sekali
                    $details = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)
                        ->get();

                    $filtered = $details->where('parameter', 'Pertukaran Udara');

                    // Aktifkan kembali ketika sudah di approve rumusnya oleh TA di spreadsheet
                    if ($filtered->isNotEmpty()) {
                        foreach ($filtered as $p) {
                            $masterParameter = Parameter::where('nama_lab', $p->parameter)->first();   
                        }
                        if(!empty($masterParameter)) {
                            $function = Formula::where('id_parameter', $masterParameter->id)->where('is_active', true)->first()->function;
                            $data_parsing = $request->all();
                            $data_parsing = (object) $data_parsing;
                            $data_parsing->data_lapangan = collect($filtered)->all();
                            $hasil = AnalystFormula::where('function', $function)
                                ->where('data', $data_parsing)
                                ->where('id_parameter', $masterParameter->id)
                                ->process();

                            // Simpan Header
                            $header = LingkunganHeader::updateOrCreate(
                                [
                                    'no_sampel' => $data->no_sampel,
                                    'parameter' => $masterParameter->nama_lab,
                                ],
                                [
                                    'id_parameter' => $masterParameter->id ?? null,
                                    'template_stp' => 30,
                                    'tanggal_terima' => $tanggalTerima,
                                    'created_by' => $this->karyawan,
                                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                                    'is_approved' => true,
                                    'approved_by' => $this->karyawan,
                                    'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
                                ]
                            );

                            // id header
                            $id_header = $header->id;

                            // Simpan ke WsValueLingkungan
                            WsValueLingkungan::updateOrCreate(
                                [
                                    'lingkungan_header_id' => $id_header,
                                    'no_sampel' => $data->no_sampel, // <- harus pakai no_sampel, bukan rata-rata
                                ],
                                [
                                    'C17' => $hasil['hasil'],
                                    'tanggal_terima' =>$tanggalTerima,
                                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                                    'created_by' => $this->karyawan,
                                ]
                            );

                            // Simpan ke WsValueUdara
                            WsValueUdara::updateOrCreate(
                                [
                                    'id_lingkungan_header' => $id_header,
                                    'no_sampel' => $data->no_sampel,
                                ],
                                [
                                    'hasil18' => $hasil['hasil'],
                                    'satuan' => $hasil['satuan'],
                                ]
                            );
                        }
                    }

                    if(!empty($foundParams)) {
                        // Loop setiap parameter
                        foreach ($foundParams as $index => $param) {
                            $column = $targetParams[$param];
                            $angkaKoma = Str::contains($param, 'Laju Ventilasi (8 Jam)');

                            // Handle kolom auto_laju
                            if ($column === 'auto_laju') {
                                $lokasi = optional($details->first())->lokasi;
                                $column = ($lokasi === 'Indoor') ? 'laju_ventilasi' : 'kecepatan_angin';
                            }

                            // Ambil rata-rata nilai parameter
                            $nilaiList = $details->pluck($column)->filter(fn($val) => $val !== null && $val !== '');
                            $rataRata = $nilaiList->count() > 0 ? round($nilaiList->avg(), $angkaKoma ? 2 : 1) : null;
                            // dd( $rataRata );
                            $satuan = null;
                            $lowerParam = strtolower($param);
                            if(str_contains($lowerParam, 'kecepatan angin')) {
                                $rataRata = round($rataRata * 3.6, 4);
                            }
                            
                            // Simpan Header (tetap seperti punya kamu)

                            $header = LingkunganHeader::updateOrCreate(
                                [
                                    'no_sampel' => $data->no_sampel,
                                    'id_parameter' => $id_parameter[$index] ?? null,
                                ],
                                [
                                    'parameter' => $param,
                                    'template_stp' => 30,
                                    'tanggal_terima' => $tanggalTerima,
                                    'created_by' => $this->karyawan,
                                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                                    'is_approved' => true,
                                    'approved_by' => $this->karyawan,
                                    'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
                                ]
                            );

                            $id_header = $header->id;

                            // Mapping parameter -> field
                            $map = [
                                'suhu' => [
                                    'lingkungan' => 'C11',
                                    'udara' => 'hasil12',
                                    'satuan' => '°C',
                                    'decimal' => 1
                                ],
                                'kelembaban' => [
                                    'lingkungan' => 'C4',
                                    'udara' => 'hasil5',
                                    'satuan' => '%',
                                    'decimal' => 1
                                ],
                                'laju ventilasi' => [
                                    'lingkungan' => 'C7',
                                    'udara' => 'hasil8',
                                    'satuan' => 'm/s',
                                    'decimal' => 1
                                ],
                                'kecepatan angin' => [
                                    'lingkungan' => 'C7',
                                    'udara' => 'hasil8',
                                    'satuan' => 'm/s',
                                    'decimal' => 4 // 👈 khusus ini
                                ],
                            ];


                            // Default
                            $lingkunganUpdate = [];
                            $udaraUpdate = [];

                            // Tentukan field berdasarkan parameter
                            foreach ($map as $key => $conf) {
                                if (Str::contains($lowerParam, $key)) {

                                    $satuan = $conf['satuan'];

                                    $formatted = $rataRata !== null
                                        ? number_format($rataRata, $conf['decimal'], '.', '')
                                        : null;

                                    $lingkunganUpdate[$conf['lingkungan']] = $formatted;
                                    $udaraUpdate[$conf['udara']] = $formatted;

                                    break;
                                }
                            }

                            $udaraUpdate['satuan'] = $satuan;

                            // ✅ Save Lingkungan
                            WsValueLingkungan::updateOrCreate(
                                [
                                    'lingkungan_header_id' => $header->id,
                                    'no_sampel' => $data->no_sampel,
                                ],
                                $lingkunganUpdate
                            );

                            // ✅ Save Udara
                            WsValueUdara::updateOrCreate(
                                [
                                    'id_lingkungan_header' => $header->id,
                                    'no_sampel' => $data->no_sampel,
                                ],
                                $udaraUpdate
                            );
                        }
                    }

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_approve' => true,
                        'approved_by' => $this->karyawan,
                        'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);

                    app(NotificationFdlService::class)->sendApproveNotification('Lingkungan Kerja', $data->no_sampel, $this->karyawan, $data->created_by);

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil diapprove'
                    ]);
                }
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melakukan approve ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                if ($data != null) {
                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;
                    $data->rejected_by = $this->karyawan;
                    $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_approve' => false,
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil direject'
                    ]);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melakukan reject ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    public function blockData(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                if ($request->is_blocked == true) {
                    $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_blocked' => true,
                        'blocked_by' => $this->karyawan,
                        'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Data no sample ' . $data->no_sampel . ' telah di block untuk user'
                    ], 200);
                } else {
                    $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_blocked' => false,
                        'blocked_by' => null,
                        'blocked_at' => null
                    ]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Data no sample ' . $data->no_sampel . ' telah di unblock untuk user'
                    ], 200);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melakukan reject ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganLingkunganKerja::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Lingkungan Kerja", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }
    
}