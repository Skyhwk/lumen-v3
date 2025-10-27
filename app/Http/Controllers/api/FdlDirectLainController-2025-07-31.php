<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganDirectLain;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\DirectLainHeader;
use App\Models\WsValueUdara;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use App\Models\AnalystFormula as Formula;
use App\Services\AnalystFormula;

class FdlDirectLainController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganDirectLain::with('detail')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.no_order', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('no_order', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('parameter', function ($query, $keyword) {
                $query->where('parameter', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganDirectLain::where('id', $request->id)->first();

                DirectLainHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                WsValueUdara::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // update OrderDetail
                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->filled('id')) {
                throw new \Exception('Invalid request: Missing ID');
            }

            // Retrieve initial record
            $initialRecord = DataLapanganDirectLain::findOrFail($request->id);
            $no_sample = $initialRecord->no_sampel;
            $parameterData = $initialRecord->parameter;
            $shift = explode('-', $initialRecord->shift)[0];

            // Get PO & parameter
            $po = OrderDetail::where('no_sampel', $no_sample)->firstOrFail();
            $parameter = Parameter::where('nama_lab', $parameterData)
                ->where('id_kategori', 4)
                ->where('is_active', true)
                ->first();

            $dataLapangan = DataLapanganDirectLain::where('no_sampel', $no_sample)
                ->where('parameter', $parameterData)
                ->where('shift', 'LIKE', $shift . '%')
                ->get();

            $TotalApprove = DataLapanganDirectLain::where('no_sampel', $no_sample)
                ->where('parameter', $parameterData)
                ->where('is_approve', 1)
                ->count();

            $approveCountNeeded = ($shift == '24 Jam') ? 3 : (($shift == '8 Jam' || $shift == '6 Jam') ? 2 : 1);

            // Always approve current record
            $fdl = $initialRecord;
            $fdl->is_approve = 1;
            $fdl->approved_by = $this->karyawan;
            $fdl->approved_at = Carbon::now();
            $fdl->save();

            if ($TotalApprove + 1 >= $approveCountNeeded) {
                $function = Formula::where('id_parameter', $parameter->id)
                    ->where('is_active', true)
                    ->value('function');

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', (object)$dataLapangan)
                    ->where('id_parameter', $parameter->id)
                    ->process();

                $header = DirectLainHeader::firstOrNew([
                    'no_sampel' => $no_sample,
                    'parameter' => $parameterData,
                ]);

                $header->fill([
                    'id_parameter' => $parameter->id,
                    'is_approve' => 1,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now(),
                    'created_by' => $header->exists ? $header->created_by : $this->karyawan,
                    'created_at' => $header->exists ? $header->created_at : Carbon::now(),
                    'is_active' => 1,
                ]);
                $header->save();

                WsValueUdara::updateOrCreate(
                    [
                        'no_sampel' => $no_sample,
                        'id_direct_lain_header' => $header->id,
                    ],
                    [
                        'id_po' => $po->id,
                        'is_active' => 1,
                        'hasil1' => $data_kalkulasi['hasil'],
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Data has been Approved',
                'status' => 'success'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = 0;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();
            // dd($data);

            // if($cek_sampler->pin_user!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Reject oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($cek_sampler->pin_user, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Reject',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganDirectLain::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganDirectLain::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Blocked for user',
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganDirectLain::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail sample Direct lainnya Berhasil';

        if (!$data) {
            return response()->json(['error' => 'Data not found'], 404);
        }

        return response()->json([
            'id' => $data->id ?? '-',
            'no_sample' => $data->no_sampel ?? '-',
            'no_order' => $data->detail->no_order ?? '-',
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
            'sampler' => $data->created_by ?? '-',
            'nama_perusahaan' => $data->detail->nama_perusahaan ?? '-',
            'keterangan' => $data->keterangan ?? '-',
            'keterangan_2' => $data->keterangan_2 ?? '-',
            'latitude' => $data->latitude ?? '-',
            'longitude' => $data->longitude ?? '-',
            'parameter' => $data->parameter ?? '-',
            'kondisi_lapangan' => $data->kondisi_lapangan ?? '-',
            'lokasi' => $data->lokasi ?? '-',
            'jenis_pengukuran' => $data->jenis_pengukuran ?? '-',
            'waktu' => $data->waktu ?? '-',
            'shift' => $data->shift ?? '-',
            'suhu' => $data->suhu ?? '-',
            'kelembaban' => $data->kelembaban ?? '-',
            'tekanan_udara' => $data->tekanan_udara ?? '-',
            'pengukuran' => $data->pengukuran ?? '-',
            'titik_koordinat' => $data->titik_koordinat ?? '-',
            'foto_lokasi' => $data->foto_lokasi_sample ?? '-',
            'foto_lain' => $data->foto_lain ?? '-',
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(3);
        $data = DataLapanganDirectLain::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}